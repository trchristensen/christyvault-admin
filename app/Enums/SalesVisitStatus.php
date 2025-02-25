<?php

namespace App\Enums;

enum SalesVisitStatus: string
{
    case PLANNED = 'planned';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case NO_SHOW = 'no_show';

    public function getLabel(): string
    {
        return match ($this) {
            self::PLANNED => 'Planned',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
            self::NO_SHOW => 'No Show',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PLANNED => 'gray',
            self::COMPLETED => 'success',
            self::CANCELLED => 'danger',
            self::NO_SHOW => 'warning',
        };
    }
}
