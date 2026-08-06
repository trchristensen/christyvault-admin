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

        $this->update([
            'status' => self::STATUS_PUBLISHED,
            'published_at' => now(),
            'archived_at' => null,
        ]);
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
