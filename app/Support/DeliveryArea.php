<?php

namespace App\Support;

use App\Models\Location;
use App\Models\Order;
use Illuminate\Support\Str;

final class DeliveryArea
{
    public const LOCAL_CITIES = [
        'colma',
        'south san francisco',
    ];

    public static function isLocalCity(?string $city): bool
    {
        return in_array(Str::lower(trim((string) $city)), self::LOCAL_CITIES, true);
    }

    public static function isLocalLocation(?Location $location): bool
    {
        return $location !== null && self::isLocalCity($location->city);
    }

    public static function isLocalOrder(Order $order): bool
    {
        return self::isLocalLocation($order->location);
    }
}
