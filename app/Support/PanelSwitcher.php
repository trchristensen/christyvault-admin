<?php

namespace App\Support;

use App\Notifications\KanbanCardQuantityUpdated;
use App\Notifications\KanbanCardScanned;
use App\Notifications\MaintenanceRequestSubmitted;
use App\Notifications\MaintenanceWorkOrderAssigned;
use Illuminate\Database\Eloquent\Builder;

final class PanelSwitcher
{
    /**
     * @return array{
     *     id: string,
     *     label: string,
     *     url: string,
     *     icon: string,
     * }
     */
    public static function current(string $panelId): array
    {
        $panel = self::panels()[$panelId] ?? [
            'label' => str($panelId)->headline().' Panel',
            'url' => '#',
            'icon' => 'heroicon-o-squares-2x2',
        ];

        return [
            'id' => $panelId,
            ...$panel,
        ];
    }

    /**
     * @return array<int, array{
     *     id: string,
     *     label: string,
     *     url: string,
     *     icon: string,
     *     unread: int,
     * }>
     */
    public static function options(string $currentPanelId): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        $panels = self::panels();

        unset($panels[$currentPanelId]);

        return collect($panels)
            ->filter(fn (array $panel, string $panelId): bool => $user->canAccessPanelById($panelId))
            ->map(fn (array $panel, string $panelId): array => [
                'id' => $panelId,
                ...$panel,
                'unread' => self::unreadCount($panelId),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, array{
     *     label: string,
     *     url: string,
     *     icon: string,
     * }>
     */
    private static function panels(): array
    {
        return [
            'admin' => [
                'label' => 'Admin Panel',
                'url' => '/',
                'icon' => 'heroicon-o-building-office',
            ],
            'operations' => [
                'label' => 'Operations Panel',
                'url' => '/operations',
                'icon' => 'heroicon-o-briefcase',
            ],
            'sales' => [
                'label' => 'Sales Panel',
                'url' => '/sales',
                'icon' => 'heroicon-o-presentation-chart-line',
            ],
            'team' => [
                'label' => 'Team Panel',
                'url' => '/team',
                'icon' => 'heroicon-o-users',
            ],
            'maintenance' => [
                'label' => 'Maintenance Panel',
                'url' => '/maintenance',
                'icon' => 'heroicon-o-wrench-screwdriver',
            ],
        ];
    }

    private static function unreadCount(string $panelId): int
    {
        $user = auth()->user();
        $notificationTypes = self::notificationTypesFor($panelId);

        if (! $user) {
            return 0;
        }

        return $user->unreadNotifications()
            ->where(function (Builder $query) use ($panelId, $notificationTypes): void {
                $query->where('data->panel', $panelId);

                if ($notificationTypes !== []) {
                    $query->orWhereIn('type', $notificationTypes);
                }
            })
            ->count();
    }

    /**
     * Notification classes are retained as a fallback for records created
     * before destination-panel metadata was added.
     *
     * @return array<class-string>
     */
    private static function notificationTypesFor(string $panelId): array
    {
        return match ($panelId) {
            'operations' => [
                KanbanCardScanned::class,
                KanbanCardQuantityUpdated::class,
            ],
            'maintenance' => [
                MaintenanceRequestSubmitted::class,
                MaintenanceWorkOrderAssigned::class,
            ],
            default => [],
        };
    }
}
