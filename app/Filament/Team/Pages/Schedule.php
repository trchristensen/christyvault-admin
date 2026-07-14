<?php

namespace App\Filament\Team\Pages;

use App\Enums\PlantLocation;
use App\Jobs\GenerateDeliveryPhotoVariants;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use App\Models\CalendarDay;
use App\Models\Order;
use App\Models\OrderDeliveryPhoto;
use Illuminate\Support\Facades\Storage;
use Throwable;

class Schedule extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-text';

    protected string $view = 'filament.team.pages.schedule';

    protected static ?string $title = 'Delivery Schedule';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view team delivery schedule') ?? false;
    }

    // don't display title on page
    public function getTitle(): string
    {
        return '';
    }


    public array $dates = [];
    public string $selectedDate;
    public $orders;
    public array $selectedCalendarDays = [];

    public function mount()
    {
        $today = Carbon::today();
        $start = $today->copy()->subDays(14);
        $end = $this->scheduleEndDate($today, $this->scheduleDaysAhead());
        $allowedDeliveryTypes = $this->allowedDeliveryTypes();
        $calendarDays = CalendarDay::query()
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->orderBy('name')
            ->get()
            ->groupBy(fn(CalendarDay $calendarDay): string => $calendarDay->date->toDateString());
        $deliveryCounts = Order::query()
            ->selectRaw('assigned_delivery_date, plant_location, COUNT(*) as total')
            ->whereDate('assigned_delivery_date', '>=', $start->toDateString())
            ->whereDate('assigned_delivery_date', '<=', $end->toDateString())
            ->whereNotNull('assigned_delivery_date')
            ->when($allowedDeliveryTypes !== [], fn($query) => $query->whereIn('plant_location', $allowedDeliveryTypes))
            ->groupBy('assigned_delivery_date', 'plant_location')
            ->get()
            ->groupBy(fn($row): string => Carbon::parse($row->assigned_delivery_date)->toDateString())
            ->map(fn($rows) => $rows->pluck('total', 'plant_location'));

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            if ($date->isWeekend()) {
                continue;
            }

            $dateCalendarDays = $calendarDays
                ->get($date->toDateString(), collect())
                ->map(fn(CalendarDay $calendarDay): array => [
                    'name' => $calendarDay->name,
                    'type' => $calendarDay->type,
                    'type_label' => $calendarDay->type_label,
                    'blocks_delivery' => $calendarDay->blocks_delivery,
                    'opens_delivery' => $calendarDay->opens_delivery,
                ])
                ->values()
                ->toArray();
            $dateString = $date->toDateString();
            $dateDeliveryCounts = $deliveryCounts->get($dateString, collect());
            $deliveryMarkers = collect([
                [
                    'key' => 'colma_main',
                    'label' => 'Colma',
                    'count' => (int) ($dateDeliveryCounts['colma_main'] ?? 0),
                    'class' => 'delivery-marker-colma',
                ],
                [
                    'key' => 'colma_locals',
                    'label' => 'Locals',
                    'count' => (int) ($dateDeliveryCounts['colma_locals'] ?? 0),
                    'class' => 'delivery-marker-locals',
                ],
                [
                    'key' => 'tulare_plant',
                    'label' => 'Tulare',
                    'count' => (int) ($dateDeliveryCounts['tulare_plant'] ?? 0),
                    'class' => 'delivery-marker-tulare',
                ],
            ])
                ->filter(fn(array $marker): bool => $marker['count'] > 0)
                ->values()
                ->toArray();

            $this->dates[] = [
                'iso' => $dateString,
                'label' => $this->labelFor($date, $today),
                'weekday' => $date->format('D'),
                'day' => $date->format('j'),
                'month' => $date->format('F Y'),
                'calendar_days' => $dateCalendarDays,
                'blocks_delivery' => collect($dateCalendarDays)->contains('blocks_delivery', true),
                'delivery_markers' => $deliveryMarkers,
            ];
        }

        $initialDate = $today->copy();

        while ($initialDate->isWeekend()) {
            $initialDate->subDay();
        }

        $this->selectedDate = $initialDate->toDateString();
        $this->loadOrdersFor($this->selectedDate);
    }

    protected function labelFor(Carbon $date, Carbon $today): string
    {
        if ($date->isToday()) return 'Today';
        if ($date->isTomorrow()) return 'Tomorrow';
        if ($date->isYesterday()) return 'Yesterday';
        return '';
    }

    public function monthFor(Carbon $date): string
    {
        return $date->format('F Y');
    }

    public function selectDate(string $iso)
    {
        $this->selectedDate = $iso;
        $this->loadOrdersFor($iso);
    }

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
                    ->maxFiles(12)
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
                    ->helperText('Upload up to 12 photos. JPG, PNG, WebP, HEIC, and HEIF are accepted. Max 15 MB each.'),
                Textarea::make('notes')
                    ->label('Notes')
                    ->rows(3)
                    ->maxLength(1000)
                    ->placeholder('Optional: no cracks, left by marker, customer requested placement, etc.'),
            ])
            ->action(function (Action $action, array $data): void {
                $orderId = (int) ($action->getArguments()['order'] ?? 0);
                $order = Order::find($orderId);

                if (!$order || !$this->canManagePhotosFor($order)) {
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
                    ->filter(fn($path): bool => is_string($path) && filled($path));

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

                $this->loadOrdersFor($this->selectedDate);

                Notification::make()
                    ->title('Delivery photos uploaded')
                    ->body("Attached {$created} " . str('photo')->plural($created) . " to order #{$order->id}.")
                    ->success()
                    ->send();
            });
    }

    protected function canManagePhotosFor(Order $order): bool
    {
        if (!static::canAccess()) {
            return false;
        }

        if (!$order->assigned_delivery_date || $order->assigned_delivery_date->toDateString() !== $this->selectedDate) {
            return false;
        }

        $allowedDeliveryTypes = $this->allowedDeliveryTypes();

        return $allowedDeliveryTypes === []
            || in_array((string) $order->plant_location, $allowedDeliveryTypes, true);
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

    protected function allowedDeliveryTypes(): array
    {
        $types = auth()->user()?->team_schedule_delivery_types ?? [];

        return collect($types)
            ->filter(fn($type): bool => PlantLocation::tryFrom((string) $type) !== null)
            ->values()
            ->toArray();
    }

    protected function scheduleDaysAhead(): int
    {
        $daysAhead = auth()->user()?->team_schedule_days_ahead;

        if ($daysAhead === null || $daysAhead === '') {
            return 14;
        }

        return max(0, min(90, (int) $daysAhead));
    }

    protected function scheduleEndDate(Carbon $startDate, int $visibleWeekdaysAhead): Carbon
    {
        $date = $startDate->copy();
        $weekdaysFound = 0;

        while ($weekdaysFound < $visibleWeekdaysAhead) {
            $date->addDay();

            if ($date->isWeekend()) {
                continue;
            }

            $weekdaysFound++;
        }

        return $date;
    }

    protected function loadOrdersFor(string $iso)
    {
        $this->selectedCalendarDays = CalendarDay::query()
            ->whereDate('date', $iso)
            ->orderByDesc('blocks_delivery')
            ->orderBy('name')
            ->get()
            ->map(fn(CalendarDay $calendarDay): array => [
                'name' => $calendarDay->name,
                'type' => $calendarDay->type,
                'type_label' => $calendarDay->type_label,
                'blocks_delivery' => $calendarDay->blocks_delivery,
                'opens_delivery' => $calendarDay->opens_delivery,
                'notes' => $calendarDay->notes,
            ])
            ->values()
            ->toArray();

        $allowedDeliveryTypes = $this->allowedDeliveryTypes();

        $orders = Order::whereDate('assigned_delivery_date', $iso)
            ->when($allowedDeliveryTypes !== [], fn($query) => $query->whereIn('plant_location', $allowedDeliveryTypes))
            ->with(['location', 'orderProducts.product', 'driver', 'deliveryPhotos.uploadedBy'])
            ->withCount('deliveryPhotos')
            ->get();

        // Define the custom plant order
        $plantOrder = [
            'colma_main' => 1,
            'colma_locals' => 2,
            'tulare_plant' => 3,
        ];

        // Sort orders by plant order
        $sorted = $orders->sortBy(fn($order) => $plantOrder[$order->plant_location] ?? 999);

        // Group by plant_location safely for Blade
        $this->orders = collect([
            'colma_main' => $sorted->where('plant_location', 'colma_main'),
            'colma_locals' => $sorted->where('plant_location', 'colma_locals'),
            'tulare_plant' => $sorted->where('plant_location', 'tulare_plant'),
        ])->filter(fn($group) => $group->isNotEmpty());
    }
}
