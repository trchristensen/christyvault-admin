<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class EmployeeProgramItem extends Model
{
    use HasFactory;

    public const TYPE_PROCEDURE = 'procedure';

    public const TYPE_FILE = 'file';

    public const TYPE_LINK = 'link';

    protected $fillable = [
        'employee_program_section_id',
        'type',
        'standard_operating_procedure_id',
        'title',
        'description',
        'file_path',
        'original_name',
        'mime_type',
        'media_type',
        'external_url',
        'required_for_completion',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'required_for_completion' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $item): void {
            if ($item->type === self::TYPE_PROCEDURE) {
                $item->file_path = null;
                $item->original_name = null;
                $item->mime_type = null;
                $item->media_type = null;
                $item->external_url = null;

                return;
            }

            if ($item->type === self::TYPE_LINK) {
                $item->standard_operating_procedure_id = null;
                $item->file_path = null;
                $item->original_name = null;
                $item->mime_type = null;
                $item->media_type = null;

                return;
            }

            $item->standard_operating_procedure_id = null;
            $item->external_url = null;

            if (! filled($item->file_path) || ! Storage::disk('local')->exists($item->file_path)) {
                $item->mime_type = null;
                $item->media_type = null;

                return;
            }

            $item->original_name = $item->original_name ?: basename($item->file_path);
            $item->mime_type = Storage::disk('local')->mimeType($item->file_path) ?: 'application/octet-stream';
            $item->media_type = match (true) {
                str_starts_with($item->mime_type, 'image/') => 'image',
                str_starts_with($item->mime_type, 'video/') => 'video',
                default => 'document',
            };
        });
    }

    public static function typeOptions(): array
    {
        return [
            self::TYPE_PROCEDURE => 'Policy or procedure',
            self::TYPE_FILE => 'File or video',
            self::TYPE_LINK => 'External link',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(EmployeeProgramSection::class, 'employee_program_section_id');
    }

    public function procedure(): BelongsTo
    {
        return $this->belongsTo(StandardOperatingProcedure::class, 'standard_operating_procedure_id');
    }

    public function isVisibleTo(?User $user): bool
    {
        if ($this->type !== self::TYPE_PROCEDURE) {
            return true;
        }

        return $this->procedure?->isVisibleTo($user) ?? false;
    }

    public function getDisplayTitleAttribute(): string
    {
        if (filled($this->title)) {
            return $this->title;
        }

        return match ($this->type) {
            self::TYPE_PROCEDURE => $this->procedure?->currentRevision?->title
                ?? $this->procedure?->title
                ?? 'Unavailable procedure',
            self::TYPE_FILE => pathinfo($this->original_name ?: basename((string) $this->file_path), PATHINFO_FILENAME),
            self::TYPE_LINK => 'Related link',
            default => 'Program item',
        };
    }
}
