<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case IN_PRODUCTION = 'in_production';
    case READY_FOR_DELIVERY = 'ready_for_delivery';
    case OUT_FOR_DELIVERY = 'out_for_delivery';
    case ARRIVED = 'arrived';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';
    case INVOICED = 'invoiced';
    case COMPLETED = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::CONFIRMED => 'Confirmed',
            self::IN_PRODUCTION => 'In Production',
            self::READY_FOR_DELIVERY => 'Ready for Delivery',
            self::OUT_FOR_DELIVERY => 'Out for Delivery',
            self::DELIVERED => 'Delivered',
            self::CANCELLED => 'Cancelled',
            self::INVOICED => 'Invoiced',
            self::COMPLETED => 'Completed',
        };
    }

    // public function color(): string
    // {
    //     return match ($this) {
    //         self::PENDING => Colors::YELLOW[500],
    //         self::CONFIRMED => Colors::BLUE[500],
    //         self::IN_PRODUCTION => Colors::PURPLE[500],
    //         self::READY_FOR_DELIVERY => Colors::TEAL[500],
    //         self::OUT_FOR_DELIVERY => Colors::ORANGE[500],
    //         self::DELIVERED => Colors::GREEN[500],
    //         self::CANCELLED => Colors::RED[500],
    //         self::INVOICED => Colors::INDIGO[500],
    //         self::COMPLETED => Colors::GREEN[500],
    //     };
    // }

    public static function toArray(): array
    {
        return collect(self::cases())->mapWithKeys(fn($status) => [
            $status->value => $status->label()
        ])->toArray();
    }
}
