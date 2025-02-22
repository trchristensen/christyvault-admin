<?php

namespace App\Enums;

enum KanbanCardStatus: string
{
    case ACTIVE = 'active';
    case PENDING_ORDER = 'pending_order';
    case ORDERED = 'ordered';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::PENDING_ORDER => 'Pending Order',
            self::ORDERED => 'Ordered',
        };
    }
}
