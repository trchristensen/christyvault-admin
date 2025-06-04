<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case WILL_CALL = 'will_call';
    case PICKED_UP = 'picked_up';
    case IN_PRODUCTION = 'in_production';
    case READY_FOR_DELIVERY = 'ready_for_delivery';
    case OUT_FOR_DELIVERY = 'out_for_delivery';
    case ARRIVED = 'arrived';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';
    case INVOICED = 'invoiced';
    case COMPLETED = 'completed';
    case TRANSFER = 'plant transfer';
    case TRANSFERRED = 'plant transferred';
    case SHIPPED = 'shipped';


    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::CONFIRMED => 'Confirmed',
            self::WILL_CALL => 'Will Call',
            self::IN_PRODUCTION => 'In Production',
            self::READY_FOR_DELIVERY => 'Ready for Delivery',
            self::OUT_FOR_DELIVERY => 'Out for Delivery',
            self::PICKED_UP => 'Picked Up',
            self::ARRIVED => 'Arrived',
            self::DELIVERED => 'Delivered',
            self::CANCELLED => 'Cancelled',
            self::INVOICED => 'Invoiced',
            self::COMPLETED => 'Completed',
            self::TRANSFER => "Plant Transfer",
            self::TRANSFERRED => "Transferred",
            self::SHIPPED => 'Shipped via Carrier',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => '#FFA500',     // Orange
            self::WILL_CALL => '#FFA500',   // Orange
            self::PICKED_UP => '#FFA500',   // Orange
            self::CONFIRMED => '#4299E1',   // Blue
            self::IN_PRODUCTION => '#9F7AEA', // Purple
            self::READY_FOR_DELIVERY => '#48BB78',   // Green
            self::OUT_FOR_DELIVERY => '#48BB78',   // Green
            self::ARRIVED => '#48BB78',   // Green
            self::DELIVERED => '#48BB78',   // Green
            self::CANCELLED => '#E53E3E',   // Red
            self::INVOICED => '#38B2AC',    // Teal
            self::COMPLETED => '#2F855A',   // Dark Green
            self::TRANSFER => '#FFA500',   // Orange
            self::TRANSFERRED => '#FFA500',   // Orange
            self::SHIPPED => '#805D3B',   // Brown
        };
    }

    public static function toArray(): array
    {
        return collect(self::cases())->mapWithKeys(fn($status) => [
            $status->value => $status->label()
        ])->toArray();
    }
}
