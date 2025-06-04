<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Filament\Resources\Traits\HasOrderForm;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Saade\FilamentFullCalendar\Components\FullCalendarComponent;
use Illuminate\Http\Request;

class DeliveryCalendar extends Page
{
    use HasOrderForm;

    protected static string $resource = OrderResource::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Delivery Management';
    protected static ?string $navigationLabel = 'Delivery Calendar';
    // protected static ?int $navigationSort = ; // Adjust this number to change the order in the sidebar
    protected static ?string $slug = 'delivery-calendar';
    protected static string $view = 'filament.resources.order-resource.pages.delivery-calendar';
    
    protected $listeners = ['openOrderModal'];
    
    public ?string $selectedDate = null;

    public function getTitle(): string
    {
        return 'Delivery Calendar';
    }

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make('createOrder')
                ->label('Create Order')
                ->model(Order::class)
                ->form(fn(Form $form) => $form->schema(static::getOrderFormSchema($this->selectedDate)))
                ->action(function (array $data) {
                    $order = Order::create($data);
                    
                    // Handle order products
                    if (isset($data['orderProducts'])) {
                        foreach ($data['orderProducts'] as $productData) {
                            $order->orderProducts()->create($productData);
                        }
                    }
                    
                    Notification::make()
                        ->title('Order created successfully')
                        ->success()
                        ->send();
                    
                    // Refresh the calendar to show the new order
                    $this->dispatch('refresh-calendar');
                        
                    return $order;
                })
                ->modalWidth('7xl'),
            Action::make('Print Calendar')
                ->url(route('delivery-calendar.print'))
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->openUrlInNewTab(),
        ];
    }

    public function getViewData(): array
    {
        return [
            'unassignedOrders' => \App\Models\Order::whereNull('assigned_delivery_date')->get(),
        ];
    }

    public function openOrderModal($orderId)
    {
        logger('DeliveryCalendar: dispatching showOrderModal event with ID: ' . $orderId);
        $this->dispatch('showOrderModal', $orderId);
    }

    public function openCreateOrderModal($date)
    {
        $this->selectedDate = $date;
        
        // Mount the createOrder action from header actions
        $this->mountAction('createOrder');
    }

    public function openCreateOrderModalFromHeader()
    {
        $this->selectedDate = null;
        
        // Mount the createOrder action from header actions
        $this->mountAction('createOrder');
    }
}
