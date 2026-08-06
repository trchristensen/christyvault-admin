<?php

namespace App\Models;

use Endroid\QrCode\Builder\Builder as QrCodeBuilder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StandardOperatingProcedure extends Model
{
    use HasFactory;

    public const AUDIENCE_ALL_EMPLOYEES = 'all_employees';

    public const AUDIENCE_SELECTED_POSITIONS = 'selected_positions';

    public const AUDIENCE_MANAGEMENT = 'management';

    protected $fillable = [
        'code',
        'title',
        'slug',
        'category',
        'summary',
        'audience',
        'plant_locations',
        'public_qr_enabled',
        'qr_token',
        'owner_user_id',
        'current_revision_id',
        'draft_content',
        'draft_attachments',
        'draft_change_summary',
        'draft_effective_date',
        'draft_review_due_date',
        'archived_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $procedure): void {
            $procedure->code = Str::upper($procedure->code);
            $procedure->slug = static::uniqueSlug($procedure->slug ?: $procedure->title);
            $procedure->qr_token ??= Str::random(48);
        });

        static::updating(function (self $procedure): void {
            if ($procedure->isDirty('code')) {
                $procedure->code = Str::upper($procedure->code);
            }
        });

        static::saving(function (self $procedure): void {
            if ($procedure->audience === self::AUDIENCE_MANAGEMENT) {
                $procedure->public_qr_enabled = false;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'plant_locations' => 'array',
            'public_qr_enabled' => 'boolean',
            'draft_content' => 'array',
            'draft_attachments' => 'array',
            'draft_effective_date' => 'date',
            'draft_review_due_date' => 'date',
            'archived_at' => 'datetime',
        ];
    }

    public static function audienceOptions(): array
    {
        return [
            self::AUDIENCE_ALL_EMPLOYEES => 'All employees',
            self::AUDIENCE_SELECTED_POSITIONS => 'Selected positions',
            self::AUDIENCE_MANAGEMENT => 'Management only',
        ];
    }

    public static function categoryOptions(): array
    {
        return [
            'safety' => 'Safety',
            'delivery' => 'Delivery',
            'production' => 'Production',
            'operations' => 'Operations',
            'quality' => 'Quality',
            'equipment' => 'Equipment',
            'emergency' => 'Emergency',
            'human_resources' => 'Human Resources',
            'other' => 'Other',
        ];
    }

    public static function categoryDescriptions(): array
    {
        return [
            'safety' => 'Safe work practices, hazard prevention, and incident response.',
            'delivery' => 'Preparing, transporting, and completing customer deliveries.',
            'production' => 'Shop-floor manufacturing, handling, and quality procedures.',
            'operations' => 'Daily coordination, documentation, and company workflows.',
            'quality' => 'Inspection standards and steps that protect product quality.',
            'equipment' => 'Correct inspection, operation, and care of company equipment.',
            'emergency' => 'Immediate actions for urgent events and workplace emergencies.',
            'human_resources' => 'Employee reporting, workplace expectations, and support.',
            'other' => 'Additional company procedures and reference material.',
        ];
    }

    public static function plantOptions(): array
    {
        return [
            'colma' => 'Colma',
            'tulare' => 'Tulare',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function currentRevision(): BelongsTo
    {
        return $this->belongsTo(StandardOperatingProcedureRevision::class, 'current_revision_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(StandardOperatingProcedureRevision::class)->latest('version');
    }

    public function positions(): BelongsToMany
    {
        return $this->belongsToMany(Position::class)->withTimestamps();
    }

    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user?->canViewProcedures()) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->canManageProcedures()) {
            return $query;
        }

        $employee = $user->employee;

        if (! $employee?->is_active) {
            return $query->whereRaw('1 = 0');
        }

        $positionIds = $employee->positions()->pluck('positions.id')->all();

        return $query
            ->whereNull('archived_at')
            ->whereNotNull('current_revision_id')
            ->where(function (Builder $audienceQuery) use ($positionIds): void {
                $audienceQuery
                    ->where('audience', self::AUDIENCE_ALL_EMPLOYEES)
                    ->orWhere(function (Builder $positionQuery) use ($positionIds): void {
                        $positionQuery
                            ->where('audience', self::AUDIENCE_SELECTED_POSITIONS)
                            ->whereHas('positions', fn (Builder $query): Builder => $query->whereKey($positionIds));
                    });
            })
            ->where(function (Builder $plantQuery) use ($employee): void {
                $plantQuery
                    ->whereNull('plant_locations')
                    ->orWhereJsonLength('plant_locations', 0)
                    ->orWhereJsonContains('plant_locations', $employee->christy_location);
            })
            ->whereHas('currentRevision', fn (Builder $revisionQuery): Builder => $revisionQuery
                ->where('status', StandardOperatingProcedureRevision::STATUS_PUBLISHED)
                ->whereDate('effective_date', '<=', today()));
    }

    public function isVisibleTo(?User $user): bool
    {
        return static::query()
            ->visibleTo($user)
            ->whereKey($this->getKey())
            ->exists();
    }

    public function publishDraft(User $publisher): StandardOperatingProcedureRevision
    {
        if (! $publisher->canManageProcedures()) {
            throw ValidationException::withMessages([
                'publish' => 'You are not allowed to publish procedures.',
            ]);
        }

        if (blank($this->draft_content)) {
            throw ValidationException::withMessages([
                'draft_content' => 'Add procedure content before publishing.',
            ]);
        }

        if ($this->archived_at) {
            throw ValidationException::withMessages([
                'publish' => 'Restore this procedure before publishing another version.',
            ]);
        }

        if ($this->current_revision_id && blank($this->draft_change_summary)) {
            throw ValidationException::withMessages([
                'draft_change_summary' => 'Describe what changed before publishing a new version.',
            ]);
        }

        return DB::transaction(function () use ($publisher): StandardOperatingProcedureRevision {
            $procedure = static::query()->lockForUpdate()->findOrFail($this->getKey());
            $contentHash = $procedure->draftHash();

            if ($procedure->currentRevision?->content_hash === $contentHash) {
                throw ValidationException::withMessages([
                    'publish' => 'There are no unpublished procedure changes.',
                ]);
            }

            $procedure->revisions()
                ->where('status', StandardOperatingProcedureRevision::STATUS_PUBLISHED)
                ->update(['status' => StandardOperatingProcedureRevision::STATUS_SUPERSEDED]);

            $revision = $procedure->revisions()->create([
                'version' => ((int) $procedure->revisions()->max('version')) + 1,
                'status' => StandardOperatingProcedureRevision::STATUS_PUBLISHED,
                'code' => $procedure->code,
                'title' => $procedure->title,
                'category' => $procedure->category,
                'summary' => $procedure->summary,
                'content' => $procedure->draft_content,
                'attachments' => $procedure->normalizedDraftAttachments(),
                'content_hash' => $contentHash,
                'change_summary' => $procedure->draft_change_summary,
                'effective_date' => $procedure->draft_effective_date ?? today(),
                'review_due_date' => $procedure->draft_review_due_date,
                'published_by_user_id' => $publisher->getKey(),
                'published_at' => now(),
            ]);

            $procedure->update(['current_revision_id' => $revision->getKey()]);
            $this->refresh();

            return $revision;
        });
    }

    public function hasUnpublishedChanges(): bool
    {
        return filled($this->draft_content)
            && $this->currentRevision?->content_hash !== $this->draftHash();
    }

    public function getStatusLabelAttribute(): string
    {
        if ($this->archived_at) {
            return 'Archived';
        }

        if (! $this->current_revision_id) {
            return 'Draft';
        }

        return $this->hasUnpublishedChanges() ? 'Published · changes pending' : 'Published';
    }

    public function getQrUrlAttribute(): string
    {
        return route('procedures.public.show', $this->qr_token);
    }

    public function archive(): void
    {
        $this->update([
            'archived_at' => now(),
            'public_qr_enabled' => false,
        ]);
    }

    public function restoreToLibrary(): void
    {
        $this->update(['archived_at' => null]);
    }

    public function generateQrCode(int $size = 600): string
    {
        return (new QrCodeBuilder(
            writer: new SvgWriter,
            writerOptions: [SvgWriter::WRITER_OPTION_EXCLUDE_XML_DECLARATION => true],
            validateResult: false,
            data: $this->qr_url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: $size,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        ))->build()->getString();
    }

    private function draftHash(): string
    {
        return hash('sha256', json_encode([
            'code' => $this->code,
            'title' => $this->title,
            'category' => $this->category,
            'summary' => $this->summary,
            'content' => $this->draft_content,
            'attachments' => $this->normalizedDraftAttachments(),
            'effective_date' => $this->draft_effective_date?->toDateString(),
            'review_due_date' => $this->draft_review_due_date?->toDateString(),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<int, array{
     *     token: string,
     *     path: string,
     *     original_name: string,
     *     title: string,
     *     description: string|null,
     *     mime_type: string,
     *     media_type: string,
     *     public_qr_enabled: bool
     * }>
     */
    private function normalizedDraftAttachments(): array
    {
        return collect($this->draft_attachments ?? [])
            ->filter(fn (mixed $attachment): bool => is_array($attachment) && filled($attachment['path'] ?? null))
            ->map(function (array $attachment): array {
                $path = (string) $attachment['path'];
                $originalName = trim((string) ($attachment['original_name'] ?? '')) ?: basename($path);
                $mimeType = Storage::disk('local')->mimeType($path) ?: 'application/octet-stream';
                $mediaType = match (true) {
                    str_starts_with($mimeType, 'image/') => 'image',
                    str_starts_with($mimeType, 'video/') => 'video',
                    default => 'document',
                };

                return [
                    'token' => substr(hash('sha256', $path), 0, 24),
                    'path' => $path,
                    'original_name' => $originalName,
                    'title' => trim((string) ($attachment['title'] ?? '')) ?: pathinfo($originalName, PATHINFO_FILENAME),
                    'description' => filled($attachment['description'] ?? null)
                        ? trim((string) $attachment['description'])
                        : null,
                    'mime_type' => $mimeType,
                    'media_type' => $mediaType,
                    'public_qr_enabled' => (bool) ($attachment['public_qr_enabled'] ?? false),
                ];
            })
            ->values()
            ->all();
    }

    private static function uniqueSlug(string $value): string
    {
        $base = Str::slug($value) ?: Str::random(10);
        $slug = $base;
        $suffix = 2;

        while (static::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
