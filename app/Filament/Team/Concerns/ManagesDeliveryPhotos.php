<?php

namespace App\Filament\Team\Concerns;

use App\Jobs\GenerateDeliveryPhotoVariants;
use App\Models\Order;
use App\Models\OrderDeliveryPhoto;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Throwable;

trait ManagesDeliveryPhotos
{
    private const int DELIVERY_PHOTO_UPLOAD_LIMIT = 20;

    public function uploadDeliveryPhotosAction(): Action
    {
        return Action::make('uploadDeliveryPhotos')
            ->label('Upload photos')
            ->icon('heroicon-o-camera')
            ->modalHeading('Upload delivery photos')
            ->modalDescription('Attach delivery proof photos to this order. Photos will be stored with your name and upload time.')
            ->schema([
                FileUpload::make('photos')
                    ->label('Photos')
                    ->disk('r2')
                    ->directory(function ($livewire): string {
                        $orderId = (int) ($livewire->getMountedAction()?->getArguments()['order'] ?? 0);

                        return Order::find($orderId)?->deliveryPhotoDirectory()
                            ?? 'delivery-photos/unassigned';
                    })
                    ->visibility('private')
                    ->multiple()
                    ->appendFiles()
                    ->maxFiles(self::DELIVERY_PHOTO_UPLOAD_LIMIT)
                    ->maxSize(15360)
                    ->acceptedFileTypes([
                        'image/jpeg',
                        'image/jpg',
                        'image/png',
                        'image/webp',
                        'image/heic',
                        'image/heif',
                    ])
                    ->storeFileNamesIn('photo_file_names')
                    ->imagePreviewHeight('120')
                    ->openable()
                    ->downloadable()
                    ->required()
                    ->helperText('Upload up to '.self::DELIVERY_PHOTO_UPLOAD_LIMIT.' photos. JPG, PNG, WebP, HEIC, and HEIF are accepted. Max 15 MB each.'),
                Textarea::make('notes')
                    ->label('Notes')
                    ->rows(3)
                    ->maxLength(1000)
                    ->placeholder('Optional: no cracks, left by marker, customer requested placement, etc.'),
            ])
            ->action(function (Action $action, array $data): void {
                $orderId = (int) ($action->getArguments()['order'] ?? 0);
                $order = Order::find($orderId);

                if (! $order || ! $this->deliveryPhotoOrderIsInScope($order)) {
                    Notification::make()
                        ->title('Cannot upload photos')
                        ->body('This order is not available on your delivery schedule.')
                        ->danger()
                        ->send();

                    return;
                }

                $photoPaths = $data['photos'] ?? [];
                if (is_string($photoPaths)) {
                    $photoPaths = [$photoPaths];
                }

                $photoPaths = collect($photoPaths)
                    ->filter(fn ($path): bool => is_string($path) && filled($path));

                if ($photoPaths->isEmpty()) {
                    Notification::make()
                        ->title('No photos uploaded')
                        ->warning()
                        ->send();

                    return;
                }

                $originalFileNames = $data['photo_file_names'] ?? [];
                if (is_string($originalFileNames)) {
                    $originalFileNames = [$originalFileNames];
                }

                $created = 0;

                foreach ($photoPaths as $key => $path) {
                    $metadata = $this->r2FileMetadata($path);
                    $originalFilename = $originalFileNames[$key]
                        ?? (is_int($key) ? ($originalFileNames[$created] ?? null) : null)
                        ?? basename($path);

                    $photo = OrderDeliveryPhoto::create([
                        'order_id' => $order->id,
                        'uploaded_by_user_id' => auth()->id(),
                        'disk' => 'r2',
                        'path' => $path,
                        'original_filename' => $originalFilename,
                        'mime_type' => $metadata['mime_type'],
                        'size' => $metadata['size'],
                        'notes' => $data['notes'] ?? null,
                    ]);

                    GenerateDeliveryPhotoVariants::dispatch($photo)->afterResponse();

                    $created++;
                }

                $this->refreshDeliveryPhotoView();

                Notification::make()
                    ->title('Delivery photos uploaded')
                    ->body("Attached {$created} ".str('photo')->plural($created)." to order #{$order->id}.")
                    ->success()
                    ->send();
            });
    }

    protected function r2FileMetadata(string $path): array
    {
        $disk = Storage::disk('r2');

        try {
            $mimeType = $disk->mimeType($path);
        } catch (Throwable) {
            $mimeType = null;
        }

        try {
            $size = $disk->size($path);
        } catch (Throwable) {
            $size = null;
        }

        return [
            'mime_type' => $mimeType,
            'size' => $size,
        ];
    }

    abstract protected function deliveryPhotoOrderIsInScope(Order $order): bool;

    abstract protected function refreshDeliveryPhotoView(): void;
}
