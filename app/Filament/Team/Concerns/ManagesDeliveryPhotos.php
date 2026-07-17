<?php

namespace App\Filament\Team\Concerns;

use App\Jobs\GenerateDeliveryPhotoVariants;
use App\Models\Order;
use App\Models\OrderDeliveryPhoto;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Throwable;

trait ManagesDeliveryPhotos
{
    private const int DELIVERY_PHOTO_UPLOAD_LIMIT = 20;

    private const string MOBILE_CAMERA_VISIBILITY_EXPRESSION = "(navigator.userAgentData && navigator.userAgentData.mobile) || /Android|iPhone|iPad|iPod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)";

    public function uploadDeliveryPhotosAction(): Action
    {
        return Action::make('uploadDeliveryPhotos')
            ->label('Upload photos')
            ->icon('heroicon-o-camera')
            ->modalHeading('Upload delivery photos')
            ->modalDescription('Add delivery proof photos to this order.')
            ->schema([
                $this->deliveryPhotoFileUpload(
                    name: 'camera_photos',
                    label: 'Take photos',
                    fileNamesStatePath: 'camera_photo_file_names',
                )
                    ->hiddenLabel()
                    ->placeholder($this->deliveryCameraPlaceholder())
                    ->extraInputAttributes(['capture' => 'environment'])
                    ->extraFieldWrapperAttributes([
                        'class' => 'delivery-camera-field',
                        'x-show' => self::MOBILE_CAMERA_VISIBILITY_EXPRESSION,
                        'x-cloak' => true,
                    ]),
                $this->deliveryPhotoFileUpload(
                    name: 'photos',
                    label: 'Choose existing photos',
                    fileNamesStatePath: 'photo_file_names',
                )
                    ->helperText('Up to '.self::DELIVERY_PHOTO_UPLOAD_LIMIT.' photos total · 15 MB each.'),
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

                $photoUploads = $this->deliveryPhotoUploads($data);

                if ($photoUploads->isEmpty()) {
                    Notification::make()
                        ->title('No photos uploaded')
                        ->warning()
                        ->send();

                    $action->halt();
                }

                if ($photoUploads->count() > self::DELIVERY_PHOTO_UPLOAD_LIMIT) {
                    Notification::make()
                        ->title('Too many photos')
                        ->body('Choose no more than '.self::DELIVERY_PHOTO_UPLOAD_LIMIT.' photos total between the camera and existing-photo options.')
                        ->danger()
                        ->send();

                    $action->halt();
                }

                $created = 0;

                foreach ($photoUploads as $upload) {
                    $path = $upload['path'];
                    $metadata = $this->r2FileMetadata($path);

                    $photo = OrderDeliveryPhoto::create([
                        'order_id' => $order->id,
                        'uploaded_by_user_id' => auth()->id(),
                        'disk' => 'r2',
                        'path' => $path,
                        'original_filename' => $upload['original_filename'],
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

    protected function deliveryCameraPlaceholder(): string
    {
        return <<<'HTML'
            <span class="delivery-camera-cta">
                <span class="delivery-camera-cta-icon" aria-hidden="true">
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z" />
                    </svg>
                </span>
                <span class="delivery-camera-cta-label">Take Photo</span>
            </span>
            HTML;
    }

    protected function deliveryPhotoFileUpload(string $name, string $label, string $fileNamesStatePath): FileUpload
    {
        return FileUpload::make($name)
            ->label($label)
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
            ->storeFileNamesIn($fileNamesStatePath)
            ->imagePreviewHeight('120')
            ->openable()
            ->downloadable();
    }

    /**
     * @return Collection<int, array{path: string, original_filename: string}>
     */
    protected function deliveryPhotoUploads(array $data): Collection
    {
        $uploads = collect();

        foreach ([
            'camera_photos' => 'camera_photo_file_names',
            'photos' => 'photo_file_names',
        ] as $pathsKey => $namesKey) {
            $paths = is_array($data[$pathsKey] ?? null)
                ? $data[$pathsKey]
                : [$data[$pathsKey] ?? null];
            $names = is_array($data[$namesKey] ?? null)
                ? $data[$namesKey]
                : [$data[$namesKey] ?? null];
            $nameValues = array_values($names);
            $position = 0;

            foreach ($paths as $key => $path) {
                if (! is_string($path) || blank($path)) {
                    continue;
                }

                $uploads->push([
                    'path' => $path,
                    'original_filename' => $names[$key]
                        ?? $nameValues[$position]
                        ?? basename($path),
                ]);

                $position++;
            }
        }

        return $uploads;
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
