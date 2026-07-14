<?php

use App\Filament\Resources\ActivityResource;
use App\Models\Order;
use App\Models\User;
use App\Policies\ActivityPolicy;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Models\Activity;

it('registers the activity policy and keeps the resource read only', function (): void {
    $activity = new Activity;

    expect(Gate::getPolicyFor(Activity::class))->toBeInstanceOf(ActivityPolicy::class)
        ->and(ActivityResource::canCreate())->toBeFalse()
        ->and(ActivityResource::canEdit($activity))->toBeFalse()
        ->and(ActivityResource::canDelete($activity))->toBeFalse()
        ->and(ActivityResource::canDeleteAny())->toBeFalse();
});

it('allows only administrators to view activity', function (): void {
    $admin = Mockery::mock(User::class);
    $admin->shouldReceive('hasRole')
        ->once()
        ->with(['admin', 'super-admin'])
        ->andReturnTrue();

    $employee = Mockery::mock(User::class);
    $employee->shouldReceive('hasRole')
        ->once()
        ->with(['admin', 'super-admin'])
        ->andReturnFalse();

    $policy = new ActivityPolicy;

    expect($policy->viewAny($admin))->toBeTrue()
        ->and($policy->viewAny($employee))->toBeFalse();
});

it('formats activity subjects, users, and changed properties', function (): void {
    $activity = new Activity([
        'subject_type' => Order::class,
        'subject_id' => 42,
        'properties' => [
            'old' => ['status' => 'pending'],
            'attributes' => ['status' => 'delivered', 'delivered_at' => '2026-07-14 12:00:00'],
            'source' => 'admin',
        ],
    ]);
    $activity->setRelation('causer', new User([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
    ]));

    expect(ActivityResource::causerLabel($activity))->toBe('Admin User')
        ->and(ActivityResource::subjectLabel($activity))->toBe('Order #42')
        ->and(ActivityResource::changedAttributes($activity))->toBe('status, delivered_at')
        ->and(ActivityResource::hasChanges($activity))->toBeTrue()
        ->and(ActivityResource::additionalProperties($activity))->toBe(['source' => 'admin'])
        ->and(ActivityResource::displayValues([
            'enabled' => true,
            'empty' => null,
            'tags' => ['paid', 'priority'],
        ]))->toBe([
            'enabled' => 'true',
            'empty' => 'null',
            'tags' => '["paid","priority"]',
        ]);
});
