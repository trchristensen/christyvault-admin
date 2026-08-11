<?php

use App\Filament\Team\Widgets\TodaysDeliveriesWidget;
use App\Models\CalendarDay;
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

it('renders today and the next working day as a manual swipe carousel', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-08-06 16:31:00', 'America/Los_Angeles'));
    $user = deliveryCarouselUser();
    $todayOrder = deliveryCarouselOrder('Today Customer', '2026-08-06');
    $tomorrowOrder = deliveryCarouselOrder('Tomorrow Customer', '2026-08-07');

    $this->actingAs($user);

    $carousel = Livewire::test(TodaysDeliveriesWidget::class)
        ->assertSee('team-deliveries-carousel-track', false)
        ->assertSee('ResizeObserver', false)
        ->assertSee(':style="trackHeight ? { height: trackHeight } : {}"', false)
        ->assertSee('data-initial-slide="1"', false)
        ->assertSee("Today's Deliveries")
        ->assertSee('Friday’s Deliveries')
        ->assertSee('Today Customer')
        ->assertSee('Tomorrow Customer');

    expect($carousel->html())
        ->toContain("order: {$todayOrder->getKey()} })")
        ->not->toContain("order: {$tomorrowOrder->getKey()} })");
});

it('skips weekends and company closures when choosing the next working day', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-08-07 12:00:00', 'America/Los_Angeles'));
    $user = deliveryCarouselUser();
    CalendarDay::create([
        'date' => '2026-08-10',
        'name' => 'Company closure',
        'type' => CalendarDay::TYPE_CLOSURE,
        'blocks_delivery' => true,
        'opens_delivery' => false,
    ]);
    deliveryCarouselOrder('Saturday Customer', '2026-08-08');
    deliveryCarouselOrder('Monday Customer', '2026-08-10');
    deliveryCarouselOrder('Tuesday Customer', '2026-08-11');

    $this->actingAs($user);

    Livewire::test(TodaysDeliveriesWidget::class)
        ->assertSee('Tuesday')
        ->assertSee('Tue, Aug 11')
        ->assertSee('Tuesday Customer')
        ->assertDontSee('Saturday Customer')
        ->assertDontSee('Monday Customer');
});

it('uses a specially opened weekend as the next working day', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-08-07 12:00:00', 'America/Los_Angeles'));
    $user = deliveryCarouselUser();
    CalendarDay::create([
        'date' => '2026-08-08',
        'name' => 'Special Saturday deliveries',
        'type' => CalendarDay::TYPE_SPECIAL_OPEN_DAY,
        'blocks_delivery' => false,
        'opens_delivery' => true,
    ]);
    deliveryCarouselOrder('Special Saturday Customer', '2026-08-08');
    deliveryCarouselOrder('Monday Customer', '2026-08-10');

    $this->actingAs($user);

    Livewire::test(TodaysDeliveriesWidget::class)
        ->assertSee('Sat, Aug 8')
        ->assertSee('Special Saturday Customer')
        ->assertDontSee('Monday Customer');
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
