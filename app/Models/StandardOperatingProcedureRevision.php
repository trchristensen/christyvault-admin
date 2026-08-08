<?php

namespace App\Models;

use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StandardOperatingProcedureRevision extends Model
{
    use HasFactory;

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_SUPERSEDED = 'superseded';

    protected $fillable = [
        'standard_operating_procedure_id',
        'version',
        'status',
        'document_type',
        'code',
        'title',
        'category',
        'summary',
        'content',
        'attachments',
        'acknowledgement_required',
        'acknowledgement_text',
        'locale',
        'content_hash',
        'change_summary',
        'effective_date',
        'review_due_date',
        'published_by_user_id',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'attachments' => 'array',
            'acknowledgement_required' => 'boolean',
            'effective_date' => 'date',
            'review_due_date' => 'date',
            'published_at' => 'datetime',
        ];
    }

    public function procedure(): BelongsTo
    {
        return $this->belongsTo(StandardOperatingProcedure::class, 'standard_operating_procedure_id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by_user_id');
    }

    public function acknowledgements(): HasMany
    {
        return $this->hasMany(DocumentAcknowledgement::class, 'standard_operating_procedure_revision_id');
    }

    public function acknowledgementFor(?Employee $employee): ?DocumentAcknowledgement
    {
        if (! $employee) {
            return null;
        }

        return $this->acknowledgements()->where('employee_id', $employee->getKey())->first();
    }

    public function acknowledge(Employee $employee, User $user, string $signedName): DocumentAcknowledgement
    {
        if (! $this->acknowledgement_required
            || $this->document_type !== StandardOperatingProcedure::TYPE_POLICY
            || blank($this->acknowledgement_text)) {
            throw ValidationException::withMessages(['acknowledgement' => 'This document does not require an acknowledgment.']);
        }

        if (! $employee->user?->is($user)
            || ! $this->procedure?->currentRevision?->is($this)
            || ! $this->procedure?->isVisibleTo($user)) {
            throw ValidationException::withMessages(['acknowledgement' => 'You cannot acknowledge this policy.']);
        }

        $name = trim($signedName);

        if ($name === '') {
            throw ValidationException::withMessages(['signed_name' => 'Type your name to acknowledge this policy.']);
        }

        return $this->acknowledgements()->firstOrCreate(
            ['employee_id' => $employee->getKey()],
            [
                'user_id' => $user->getKey(),
                'method' => DocumentAcknowledgement::METHOD_AUTHENTICATED,
                'signed_name' => $name,
                'acknowledgement_text' => $this->acknowledgement_text,
                'locale' => $this->locale ?: 'en',
                'evidence_hash' => hash('sha256', implode('|', [
                    $this->getKey(),
                    $employee->getKey(),
                    $user->getKey(),
                    $name,
                    $this->acknowledgement_text,
                    Str::uuid()->toString(),
                ])),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'acknowledged_at' => now(),
            ],
        );
    }

    public function recordPaperAcknowledgement(
        Employee $employee,
        User $recorder,
        string $signedName,
        Carbon $acknowledgedAt,
        ?string $evidenceFilePath = null,
    ): DocumentAcknowledgement {
        if (! $recorder->canManageProcedures()
            || ! $this->acknowledgement_required
            || $this->document_type !== StandardOperatingProcedure::TYPE_POLICY
            || blank($this->acknowledgement_text)) {
            throw ValidationException::withMessages(['acknowledgement' => 'This policy cannot accept a paper acknowledgment.']);
        }

        $name = trim($signedName);

        if ($name === '') {
            throw ValidationException::withMessages(['signed_name' => 'Enter the name signed on the paper acknowledgment.']);
        }

        return $this->acknowledgements()->firstOrCreate(
            ['employee_id' => $employee->getKey()],
            [
                'user_id' => $employee->user_id,
                'method' => DocumentAcknowledgement::METHOD_PAPER_IMPORT,
                'signed_name' => $name,
                'acknowledgement_text' => $this->acknowledgement_text,
                'locale' => $this->locale ?: 'en',
                'evidence_hash' => hash('sha256', implode('|', [
                    $this->getKey(),
                    $employee->getKey(),
                    $name,
                    $acknowledgedAt->toIso8601String(),
                    $this->acknowledgement_text,
                    Str::uuid()->toString(),
                ])),
                'evidence_file_path' => $evidenceFilePath,
                'recorded_by_user_id' => $recorder->getKey(),
                'acknowledged_at' => $acknowledgedAt,
            ],
        );
    }

    public function getVersionLabelAttribute(): string
    {
        return "Version {$this->version}";
    }

    public function renderedContent(): Htmlable
    {
        return RichContentRenderer::make($this->content);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function attachmentItems(bool $publicOnly = false): Collection
    {
        return collect($this->attachments ?? [])
            ->filter(fn (mixed $attachment): bool => is_array($attachment) && filled($attachment['token'] ?? null))
            ->when(
                $publicOnly,
                fn (Collection $attachments): Collection => $attachments
                    ->where('public_qr_enabled', true),
            )
            ->values();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findAttachment(string $token, bool $publicOnly = false): ?array
    {
        return $this->attachmentItems($publicOnly)
            ->first(fn (array $attachment): bool => hash_equals((string) $attachment['token'], $token));
    }
}
