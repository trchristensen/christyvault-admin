<?php

namespace App\Enums;

enum Department: string
{
    case PAINT = 'paint';
    case SHIPPING = 'shipping';
    case PRODUCTION = 'production';
    case OFFICE = 'office';

    public function getLabel(): string
    {
        return match ($this) {
            self::PAINT => 'Paint',
            self::SHIPPING => 'Shipping',
            self::PRODUCTION => 'Production',
            self::OFFICE => 'Office',
        };
    }

    public static function getValues(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function getOptions(): array
    {
        return collect(self::cases())->mapWithKeys(fn($case) => [
            $case->value => $case->getLabel()
        ])->toArray();
    }
}
