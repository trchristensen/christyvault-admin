<?php

use App\Filament\Team\Widgets\TodaysDeliveriesWidget;
use App\Models\Location;
use App\Models\Order;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Filament::setCurrentPanel('team');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function deliveryCarouselUser(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('driver', 'web'));
    $user->givePermissionTo(Permission::findOrCreate('view team delivery schedule', 'web'));

    return $user;
}

function deliveryCarouselOrder(string $name, string $date): Order
{
    $order = Order::factory()->create([
        'location_id' => Location::factory()->create(['name' => $name])->getKey(),
        'status' => 'ready_for_delivery',
        'is_printed' => true,
    ]);

    $order->forceFill([
        'assigned_delivery_date' => $date,
        'plant_location' => 'colma_main',
    ])->saveQuietly();

    return $order;
}

it('renders today and tomorrow as a manual swipe carousel', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-08-06 16:31:00', 'America/Los_Angeles'));
    $user = deliveryCarouselUser();
    $todayOrder = deliveryCarouselOrder('Today Customer', '2026-08-06');
    $tomorrowOrder = deliveryCarouselOrder('Tomorrow Customer', '2026-08-07');

    $this->actingAs($user);

    $carousel = Livewire::test(TodaysDeliveriesWidget::class)
        ->assertSee('team-deliveries-carousel-track', false)
        ->assertSee('data-initial-slide="1"', false)
        ->assertSee("Today's Deliveries")
        ->assertSee("Tomorrow's Deliveries")
        ->assertSee('Today Customer')
        ->assertSee('Tomorrow Customer');

    expect($carousel->html())
        ->toContain("order: {$todayOrder->getKey()} })")
        ->not->toContain("order: {$tomorrowOrder->getKey()} })");
});

it('uses the 4:30 PM Pacific cutoff only to choose the initial slide', function (): void {
    $user = deliveryCarouselUser();
    $this->actingAs($user);

    Carbon::setTestNow(Carbon::parse('2026-08-06 16:29:59', 'America/Los_Angeles'));

    Livewire::test(TodaysDeliveriesWidget::class)
        ->assertSee('data-initial-slide="0"', false)
        ->assertSee('Swipe, scroll, or use the day buttons');

    Carbon::setTestNow(Carbon::parse('2026-08-06 16:30:00', 'America/Los_Angeles'));

    Livewire::test(TodaysDeliveriesWidget::class)
        ->assertSee('data-initial-slide="1"', false)
        ->assertSee('Swipe, scroll, or use the day buttons');
});
