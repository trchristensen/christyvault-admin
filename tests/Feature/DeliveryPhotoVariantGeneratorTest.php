<?php

use App\Jobs\GenerateDeliveryPhotoVariants;
use App\Models\OrderDeliveryPhoto;
use App\Services\DeliveryPhotoVariantGenerator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', ':memory:');
    config()->set('cache.default', 'array');

    Schema::create('order_delivery_photos', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('order_id');
        $table->unsignedBigInteger('uploaded_by_user_id')->nullable();
        $table->string('disk');
        $table->string('path');
        $table->string('thumbnail_path')->nullable();
        $table->string('display_path')->nullable();
        $table->string('original_filename')->nullable();
        $table->string('mime_type')->nullable();
        $table->unsignedBigInteger('size')->nullable();
        $table->text('notes')->nullable();
        $table->timestamps();
    });

    Storage::fake('delivery-photos');
    Cache::flush();
});

it('creates optimized thumbnail and display variants', function (): void {
    $source = new Imagick;
    $source->newImage(2400, 1200, '#336699');
    $source->setImageFormat('jpeg');
    $sourceContents = $source->getImagesBlob();
    $source->clear();

    Storage::disk('delivery-photos')->put('delivery-photos/ORD-1/photo.jpg', $sourceContents);

    $photo = OrderDeliveryPhoto::create([
        'order_id' => 1,
        'disk' => 'delivery-photos',
        'path' => 'delivery-photos/ORD-1/photo.jpg',
        'original_filename' => 'photo.jpg',
        'mime_type' => 'image/jpeg',
        'size' => strlen($sourceContents),
    ]);

    app(DeliveryPhotoVariantGenerator::class)->generate($photo);

    $photo->refresh();

    expect($photo->thumbnail_path)->toBe('delivery-photos/ORD-1/photo.thumb.jpg')
        ->and($photo->display_path)->toBe('delivery-photos/ORD-1/photo.display.jpg');

    Storage::disk('delivery-photos')->assertExists([
        $photo->thumbnail_path,
        $photo->display_path,
    ]);

    $thumbnail = new Imagick;
    $thumbnail->readImageBlob(Storage::disk('delivery-photos')->get($photo->thumbnail_path));
    $display = new Imagick;
    $display->readImageBlob(Storage::disk('delivery-photos')->get($photo->display_path));

    expect([$thumbnail->getImageWidth(), $thumbnail->getImageHeight()])->toBe([360, 360])
        ->and([$display->getImageWidth(), $display->getImageHeight()])->toBe([1920, 960])
        ->and($thumbnail->getImageFormat())->toBe('JPEG')
        ->and($display->getImageFormat())->toBe('JPEG');

    $thumbnail->clear();
    $display->clear();
});

it('falls back to the original URL until variants are ready', function (): void {
    Storage::disk('delivery-photos')->put('delivery-photos/ORD-1/photo.jpg', 'photo');

    $photo = new OrderDeliveryPhoto([
        'disk' => 'delivery-photos',
        'path' => 'delivery-photos/ORD-1/photo.jpg',
    ]);

    expect($photo->thumbnail_url)->toBe($photo->url)
        ->and($photo->display_url)->toBe($photo->url);
});

it('uses long-lived private browser caching for immutable photo objects', function (): void {
    expect(app(DeliveryPhotoVariantGenerator::class)->storageOptions())->toMatchArray([
        'visibility' => 'private',
        'ContentType' => 'image/jpeg',
        'CacheControl' => 'private, max-age=31536000, immutable',
    ]);
});

it('processes variants without requiring an external queue worker', function (): void {
    $job = new GenerateDeliveryPhotoVariants(new OrderDeliveryPhoto);

    expect($job->connection)->toBe('sync');
});
