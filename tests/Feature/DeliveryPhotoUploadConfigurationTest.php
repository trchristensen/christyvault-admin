<?php

use App\Filament\Team\Widgets\TodaysDeliveriesWidget;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Schema;

it('offers rear-camera capture on mobile while keeping the existing photo picker', function (): void {
    $widget = app(TodaysDeliveriesWidget::class);
    $action = $widget->uploadDeliveryPhotosAction()->livewire($widget);
    $schema = $action->getSchema(Schema::make($widget));
    $components = $schema?->getFlatComponents(withHidden: true) ?? [];
    $cameraUpload = $components['camera_photos'] ?? null;
    $photoUpload = $components['photos'] ?? null;

    expect($cameraUpload)
        ->toBeInstanceOf(FileUpload::class)
        ->and($cameraUpload->getLabel())
        ->toBe('Take photos')
        ->and($cameraUpload->isLabelHidden())
        ->toBeTrue()
        ->and($cameraUpload->getPlaceholder())
        ->toContain('<svg', 'delivery-camera-cta-icon', 'Take Photo')
        ->and($cameraUpload->getMaxFiles())
        ->toBe(20)
        ->and($cameraUpload->getExtraInputAttributeBag()->get('capture'))
        ->toBe('environment')
        ->and($cameraUpload->getExtraFieldWrapperAttributes()['x-show'] ?? null)
        ->toContain('Android', 'iPhone', 'iPad', 'maxTouchPoints')
        ->and($photoUpload)
        ->toBeInstanceOf(FileUpload::class)
        ->and($photoUpload->getLabel())
        ->toBe('Choose existing photos')
        ->and($photoUpload->getMaxFiles())
        ->toBe(20)
        ->and($photoUpload->getExtraInputAttributeBag()->get('capture'))
        ->toBeNull();
});

it('combines captured and existing photos for the delivery upload', function (): void {
    $widget = app(TodaysDeliveriesWidget::class);
    $method = new ReflectionMethod($widget, 'deliveryPhotoUploads');

    $uploads = $method->invoke($widget, [
        'camera_photos' => ['camera-key' => 'delivery-photos/ORD-1/camera.jpg'],
        'camera_photo_file_names' => ['camera-key' => 'camera.jpg'],
        'photos' => ['picker-key' => 'delivery-photos/ORD-1/existing.heic'],
        'photo_file_names' => ['picker-key' => 'existing.heic'],
    ]);

    expect($uploads->all())->toBe([
        [
            'path' => 'delivery-photos/ORD-1/camera.jpg',
            'original_filename' => 'camera.jpg',
        ],
        [
            'path' => 'delivery-photos/ORD-1/existing.heic',
            'original_filename' => 'existing.heic',
        ],
    ]);
});
