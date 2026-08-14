<?php

use App\Filament\Maintenance\Resources\MaintenanceAssetResource\Pages\ListMaintenanceAssets;
use App\Models\Location;
use App\Models\MaintenanceAsset;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Filament::setCurrentPanel('maintenance');
});

it('filters assets by plant and uses the requested table defaults', function (): void {
    $manager = User::factory()->create();
    $manager->assignRole(Role::findOrCreate('maintenance-manager', 'web'));

    $colma = Location::create([
        'name' => 'Christy Vault - Colma',
        'address_line1' => '1 Colma Plant Road',
        'city' => 'Colma',
        'state' => 'CA',
        'postal_code' => '94014',
        'location_type' => 'christy_vault',
    ]);
    $tulare = Location::create([
        'name' => 'Christy Vault - Tulare',
        'address_line1' => '1 Tulare Plant Road',
        'city' => 'Tulare',
        'state' => 'CA',
        'postal_code' => '93274',
        'location_type' => 'christy_vault',
    ]);

    $colmaAsset = MaintenanceAsset::create([
        'asset_tag' => 'COLMA-'.uniqid(),
        'name' => 'Colma test asset',
        'category' => 'forklift',
        'location_id' => $colma->id,
    ]);
    $tulareAsset = MaintenanceAsset::create([
        'asset_tag' => 'TULARE-'.uniqid(),
        'name' => 'Tulare test asset',
        'category' => 'truck',
        'location_id' => $tulare->id,
    ]);

    $this->actingAs($manager);

    $list = Livewire::test(ListMaintenanceAssets::class);
    $table = $list->instance()->getTable();

    expect(array_keys($list->instance()->getTabs()))->toBe(['all', 'colma', 'tulare'])
        ->and($table->getDefaultGroup()?->getId())->toBe('category')
        ->and($table->getDefaultPaginationPageOption())->toBe(20)
        ->and($table->getPaginationPageOptions())->toBe([20, 50, 100]);

    $list->set('activeTab', 'colma')
        ->assertCanSeeTableRecords([$colmaAsset])
        ->assertCanNotSeeTableRecords([$tulareAsset])
        ->set('activeTab', 'tulare')
        ->assertCanSeeTableRecords([$tulareAsset])
        ->assertCanNotSeeTableRecords([$colmaAsset]);
});
