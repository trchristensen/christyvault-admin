<?php

namespace App\Support;

use SpykApp\FilamentPasswordlessLogin\FilamentPasswordlessLoginPlugin;

final class FilamentLoginMode
{
    public static function configure(FilamentPasswordlessLoginPlugin $plugin): FilamentPasswordlessLoginPlugin
    {
        $passwordLoginEnabled = self::passwordLoginEnabled();

        return $plugin
            ->showPasswordLoginLink($passwordLoginEnabled)
            ->loginAction($passwordLoginEnabled);
    }

    public static function passwordLoginEnabled(?string $environment = null): bool
    {
        return ($environment ?? app()->environment()) !== 'production';
    }
}
