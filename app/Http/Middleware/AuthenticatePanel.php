<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatePanel extends Authenticate
{
    public function handle($request, Closure $next, ...$guards): Response
    {
        $user = Filament::auth()->user();
        $panel = Filament::getCurrentOrDefaultPanel();

        if ($user instanceof User && ! $user->canAccessPanel($panel)) {
            $preferredPanelId = $user->getPreferredPanelId();

            if ($preferredPanelId !== null && $user->canAccessPanelById($preferredPanelId)) {
                return redirect()->to(Filament::getPanel($preferredPanelId)->getUrl());
            }
        }

        return parent::handle($request, $next, ...$guards);
    }
}
