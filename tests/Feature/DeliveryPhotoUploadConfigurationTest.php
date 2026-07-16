<?php

use App\Filament\Team\Widgets\TodaysDeliveriesWidget;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Schema;

it('allows drivers to upload up to twenty delivery photos at once', function (): void {
    $widget = app(TodaysDeliveriesWidget::class);
    $action = $widget->uploadDeliveryPhotosAction()->livewire($widget);
    $schema = $action->getSchema(Schema::make($widget));
    $photoUpload = $schema?->getFlatComponents(withHidden: true)['photos'] ?? null;

    expect($photoUpload)
        ->toBeInstanceOf(FileUpload::class)
        ->and($photoUpload->getMaxFiles())
        ->toBe(20);
});
