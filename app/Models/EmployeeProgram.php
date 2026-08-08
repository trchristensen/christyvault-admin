<?php

namespace App\Models;

use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmployeeProgram extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    public const AUDIENCE_ALL_EMPLOYEES = 'all_employees';

    public const AUDIENCE_SELECTED_POSITIONS = 'selected_positions';

    public const AUDIENCE_MANAGEMENT = 'management';

    protected $fillable = [
        'title',
        'slug',
        'category',
        'summary',
        'introduction',
        'audience',
        'plant_locations',
        'owner_user_id',
        'status',
        'training_enabled',
        'passing_score',
        'estimated_minutes',
        'content_version',
        'default_locale',
        'published_at',
        'archived_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $program): void {
            $program->slug = static::uniqueSlug($program->slug ?: $program->title);
        });
    }

    protected function casts(): array
    {
        return [
            'introduction' => 'array',
            'plant_locations' => 'array',
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
            'training_enabled' => 'boolean',
            'passing_score' => 'integer',
            'estimated_minutes' => 'integer',
            'content_version' => 'integer',
        ];
    }

    public static function audienceOptions(): array
    {
        return StandardOperatingProcedure::audienceOptions();
    }

    public static function plantOptions(): array
    {
        return StandardOperatingProcedure::plantOptions();
    }

    public static function categoryOptions(): array
    {
        return [
            'orientation' => 'Orientation',
            'safety' => 'Safety',
            'delivery' => 'Delivery',
            'production' => 'Production',
            'operations' => 'Operations',
            'quality' => 'Quality',
            'equipment' => 'Equipment',
            'human_resources' => 'Human Resources',
            'emergency' => 'Emergency Preparedness',
            'other' => 'Other',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function positions(): BelongsToMany
    {
        return $this->belongsToMany(
            Position::class,
            'employee_program_position',
            'employee_program_id',
            'position_id',
        )->withTimestamps();
    }

    public function sections(): HasMany
    {
        return $this->hasMany(EmployeeProgramSection::class)->orderBy('sort_order')->orderBy('id');
    }

    public function trainingQuestions(): HasMany
    {
        return $this->hasMany(TrainingQuestion::class)->orderBy('sort_order')->orderBy('id');
    }

    public function trainingAssignments(): HasMany
    {
        return $this->hasMany(TrainingAssignment::class);
    }

    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user?->canViewPrograms()) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->canManagePrograms()) {
            return $query;
        }

        $employee = $user->employee;

        if (! $employee?->is_active) {
            return $query->whereRaw('1 = 0');
        }

        $positionIds = $employee->positions()->pluck('positions.id')->all();

        return $query
            ->where('status', self::STATUS_PUBLISHED)
            ->whereNull('archived_at')
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
            });
    }

    public function isVisibleTo(?User $user): bool
    {
        return static::query()
            ->visibleTo($user)
            ->whereKey($this->getKey())
            ->exists();
    }

    public function appliesToEmployee(Employee $employee): bool
    {
        if (! $employee->is_active || $this->status !== self::STATUS_PUBLISHED || ! $this->training_enabled) {
            return false;
        }

        if (filled($this->plant_locations)
            && ! in_array($employee->christy_location, $this->plant_locations, true)) {
            return false;
        }

        return match ($this->audience) {
            self::AUDIENCE_ALL_EMPLOYEES => true,
            self::AUDIENCE_SELECTED_POSITIONS => $employee->positions()
                ->whereKey($this->positions()->pluck('positions.id'))
                ->exists(),
            self::AUDIENCE_MANAGEMENT => $employee->user?->canManagePrograms() ?? false,
            default => false,
        };
    }

    public function renderedIntroduction(): ?Htmlable
    {
        return filled($this->introduction)
            ? RichContentRenderer::make($this->introduction)
            : null;
    }

    public function publish(): void
    {
        if (! $this->sections()->whereHas('items')->exists()) {
            throw ValidationException::withMessages([
                'program' => 'Add at least one program section with an item before publishing.',
            ]);
        }

        if ($this->training_enabled) {
            foreach ($this->trainingQuestions()->where('is_active', true)->get() as $question) {
                $options = collect($question->normalizedOptions());

                if ($options->count() < 2 || $options->where('correct', true)->count() !== 1) {
                    throw ValidationException::withMessages([
                        'trainingQuestions' => 'Every active training question needs at least two choices and exactly one correct answer.',
                    ]);
                }
            }
        }

        $this->update([
            'status' => self::STATUS_PUBLISHED,
            'published_at' => now(),
            'archived_at' => null,
            'content_version' => max(1, ((int) $this->content_version) + 1),
        ]);
    }

    public function trainingSnapshot(): array
    {
        $this->loadMissing(['sections.items.procedure.currentRevision', 'trainingQuestions']);

        $requiredPolicyRevisions = $this->sections
            ->flatMap->items
            ->filter(fn (EmployeeProgramItem $item): bool => $item->required_for_completion
                && $item->type === EmployeeProgramItem::TYPE_PROCEDURE
                && $item->procedure?->document_type === StandardOperatingProcedure::TYPE_POLICY
                && $item->procedure?->currentRevision?->acknowledgement_required)
            ->pluck('procedure.current_revision_id')
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $requiredMaterials = $this->sections
            ->flatMap->items
            ->filter(fn (EmployeeProgramItem $item): bool => $item->required_for_completion)
            ->values()
            ->map(fn (EmployeeProgramItem $item): array => [
                'item_id' => $item->getKey(),
                'type' => $item->type,
                'title' => $item->display_title,
                'media_type' => $item->media_type,
                'document_revision_id' => $item->procedure?->current_revision_id,
            ])
            ->all();

        return [
            'program_id' => $this->getKey(),
            'program_title' => $this->title,
            'program_version' => max(1, (int) $this->content_version),
            'passing_score' => (int) $this->passing_score,
            'estimated_minutes' => $this->estimated_minutes,
            'locale' => $this->default_locale ?: 'en',
            'required_policy_revisions' => $requiredPolicyRevisions,
            'required_materials' => $requiredMaterials,
            'questions' => $this->trainingQuestions
                ->where('is_active', true)
                ->values()
                ->map(fn (TrainingQuestion $question, int $index): array => [
                    'key' => (string) $index,
                    'prompt' => $question->prompt,
                    'options' => $question->normalizedOptions(),
                    'explanation' => $question->explanation,
                    'locale' => $question->locale ?: $this->default_locale ?: 'en',
                ])
                ->all(),
        ];
    }

    public function moveToDraft(): void
    {
        $this->update([
            'status' => self::STATUS_DRAFT,
            'published_at' => null,
            'archived_at' => null,
        ]);
    }

    public function archive(): void
    {
        $this->update([
            'status' => self::STATUS_ARCHIVED,
            'archived_at' => now(),
        ]);
    }

    public function restoreToLibrary(): void
    {
        $this->update([
            'status' => $this->published_at ? self::STATUS_PUBLISHED : self::STATUS_DRAFT,
            'archived_at' => null,
        ]);
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
