<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_program_id',
        'prompt',
        'options',
        'explanation',
        'sort_order',
        'is_active',
        'locale',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(EmployeeProgram::class, 'employee_program_id');
    }

    public function normalizedOptions(): array
    {
        return collect($this->options ?? [])
            ->filter(fn (mixed $option): bool => is_array($option) && filled($option['label'] ?? null))
            ->values()
            ->map(fn (array $option, int $index): array => [
                'key' => (string) $index,
                'label' => trim((string) $option['label']),
                'correct' => (bool) ($option['correct'] ?? false),
            ])
            ->all();
    }
}
