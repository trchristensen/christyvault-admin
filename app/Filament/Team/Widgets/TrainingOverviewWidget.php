<?php

namespace App\Filament\Team\Widgets;

use App\Filament\Team\Resources\TrainingAssignmentResource;
use App\Models\TrainingAssignment;
use Filament\Widgets\Widget;

class TrainingOverviewWidget extends Widget
{
    protected string $view = 'filament.team.widgets.training-overview-widget';

    protected static ?int $sort = 3;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->canViewTraining() ?? false;
    }

    protected function getViewData(): array
    {
        $user = auth()->user();
        $query = TrainingAssignment::query()
            ->visibleTo($user)
            ->with(['program', 'employee'])
            ->where('status', '!=', TrainingAssignment::STATUS_COMPLETED)
            ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_date')
            ->orderByDesc('assigned_at');

        return [
            'assignments' => $query->limit(6)->get(),
            'canManage' => $user?->canManageTraining() ?? false,
            'trainingUrl' => TrainingAssignmentResource::getUrl('index', panel: 'team'),
        ];
    }
}
