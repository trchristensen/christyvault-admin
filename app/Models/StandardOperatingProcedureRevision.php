<?php

namespace App\Models;

use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class StandardOperatingProcedureRevision extends Model
{
    use HasFactory;

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_SUPERSEDED = 'superseded';

    protected $fillable = [
        'standard_operating_procedure_id',
        'version',
        'status',
        'code',
        'title',
        'category',
        'summary',
        'content',
        'attachments',
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
