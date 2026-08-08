<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TrainingAssignment extends Model
{
    use HasFactory;

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const COMPLETION_CERTIFICATION = 'I certify that I reviewed the assigned program materials and personally completed this training.';

    protected $fillable = [
        'employee_program_id',
        'employee_id',
        'assigned_by_user_id',
        'status',
        'due_date',
        'program_version',
        'content_snapshot',
        'latest_score',
        'locale',
        'assigned_at',
        'started_at',
        'completed_at',
        'completion_certification',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $assignment): void {
            $program = $assignment->program ?? EmployeeProgram::query()->findOrFail($assignment->employee_program_id);
            $assignment->status ??= self::STATUS_ASSIGNED;
            $assignment->assigned_at ??= now();
            $assignment->program_version = max(1, (int) $program->content_version);
            $assignment->locale = $assignment->locale ?: ($assignment->employee?->preferred_locale ?: $program->default_locale ?: 'en');
            $assignment->content_snapshot = $program->trainingSnapshot();
        });
    }

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'program_version' => 'integer',
            'content_snapshot' => 'array',
            'latest_score' => 'integer',
            'assigned_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_ASSIGNED => 'Assigned',
            self::STATUS_IN_PROGRESS => 'In progress',
            self::STATUS_COMPLETED => 'Completed',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(EmployeeProgram::class, 'employee_program_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(TrainingAttempt::class)->latest('submitted_at');
    }

    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user?->canViewTraining()) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->canManageTraining()) {
            return $query;
        }

        if (! $user->employee?->is_active) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('employee_id', $user->employee->getKey());
    }

    public function isVisibleTo(?User $user): bool
    {
        return static::query()->visibleTo($user)->whereKey($this)->exists();
    }

    public function belongsToUser(?User $user): bool
    {
        return $user?->employee?->is($this->employee) ?? false;
    }

    public function begin(User $user): void
    {
        $this->assertEmployeeUser($user);

        if ($this->status === self::STATUS_ASSIGNED) {
            $this->update([
                'status' => self::STATUS_IN_PROGRESS,
                'started_at' => now(),
            ]);
        }
    }

    public function questionnaire(): array
    {
        return data_get($this->content_snapshot, 'questions', []);
    }

    public function requiredPolicyRevisions(): array
    {
        return collect(data_get($this->content_snapshot, 'required_policy_revisions', []))
            ->map(fn (mixed $id): int => (int) $id)
            ->filter()
            ->values()
            ->all();
    }

    public function missingPolicyRevisionIds(): array
    {
        $required = $this->requiredPolicyRevisions();

        if ($required === []) {
            return [];
        }

        $acknowledged = DocumentAcknowledgement::query()
            ->where('employee_id', $this->employee_id)
            ->whereIn('standard_operating_procedure_revision_id', $required)
            ->pluck('standard_operating_procedure_revision_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        return array_values(array_diff($required, $acknowledged));
    }

    public function submitQuestionnaire(User $user, array $answers): TrainingAttempt
    {
        $this->assertEmployeeUser($user);
        $this->begin($user);
        $questions = collect($this->questionnaire())->values();

        if ($questions->isEmpty()) {
            throw ValidationException::withMessages(['questionnaire' => 'This training does not have a questionnaire.']);
        }

        $correct = 0;
        $recordedAnswers = [];

        foreach ($questions as $index => $question) {
            $answer = (string) data_get($answers, (string) $index, '');
            $options = collect($question['options'] ?? [])->keyBy(fn (array $option): string => (string) $option['key']);

            if ($answer === '' || ! $options->has($answer)) {
                throw ValidationException::withMessages([
                    "answers.{$index}" => 'Choose an answer for every question.',
                ]);
            }

            $selected = $options->get($answer);
            $isCorrect = (bool) ($selected['correct'] ?? false);
            $correct += $isCorrect ? 1 : 0;
            $recordedAnswers[] = [
                'question_index' => $index,
                'selected_key' => $answer,
                'selected_label' => $selected['label'] ?? null,
                'correct' => $isCorrect,
            ];
        }

        $score = (int) round(($correct / $questions->count()) * 100);
        $passingScore = (int) data_get($this->content_snapshot, 'passing_score', 80);
        $passed = $score >= $passingScore;

        return DB::transaction(function () use ($user, $questions, $recordedAnswers, $score, $passed): TrainingAttempt {
            $attempt = $this->attempts()->create([
                'user_id' => $user->getKey(),
                'questionnaire_snapshot' => $questions->all(),
                'answers' => $recordedAnswers,
                'score' => $score,
                'passed' => $passed,
                'locale' => $this->locale,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'submitted_at' => now(),
            ]);

            $this->update(['latest_score' => $score]);

            return $attempt;
        });
    }

    public function canComplete(): bool
    {
        $questionsPassed = $this->questionnaire() === []
            || $this->attempts()->where('passed', true)->exists();

        return $questionsPassed && $this->missingPolicyRevisionIds() === [];
    }

    public function complete(User $user, string $certification = self::COMPLETION_CERTIFICATION): void
    {
        $this->assertEmployeeUser($user);
        $this->begin($user);

        if (! $this->canComplete()) {
            throw ValidationException::withMessages([
                'completion' => 'Pass the questionnaire and acknowledge every required policy before completing this training.',
            ]);
        }

        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
            'completion_certification' => $certification,
        ]);
    }

    private function assertEmployeeUser(User $user): void
    {
        if (! $this->belongsToUser($user) || ! $user->canViewTraining()) {
            throw ValidationException::withMessages(['training' => 'This training assignment does not belong to you.']);
        }

        if ($this->status === self::STATUS_COMPLETED) {
            throw ValidationException::withMessages(['training' => 'This training is already complete.']);
        }
    }
}
