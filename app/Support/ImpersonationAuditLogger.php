<?php

namespace App\Support;

use App\Models\User;
use STS\FilamentImpersonate\Events\EnterImpersonation;
use STS\FilamentImpersonate\Events\LeaveImpersonation;

class ImpersonationAuditLogger
{
    public function started(EnterImpersonation $event): void
    {
        if (! $event->impersonator instanceof User || ! $event->impersonated instanceof User) {
            return;
        }

        $this->log('impersonation_started', 'Started', $event->impersonator, $event->impersonated);
    }

    public function ended(LeaveImpersonation $event): void
    {
        if (! $event->impersonator instanceof User || ! $event->impersonated instanceof User) {
            return;
        }

        $this->log('impersonation_ended', 'Stopped', $event->impersonator, $event->impersonated);
    }

    private function log(string $event, string $verb, User $impersonator, User $impersonated): void
    {
        activity('authentication')
            ->performedOn($impersonated)
            ->causedBy($impersonator)
            ->event($event)
            ->withProperties([
                'impersonator_id' => $impersonator->getKey(),
                'impersonated_id' => $impersonated->getKey(),
                'ip_address' => request()->ip(),
            ])
            ->log("{$verb} impersonating {$impersonated->name}");
    }
}
