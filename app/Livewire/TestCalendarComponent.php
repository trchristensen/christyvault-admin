<?php

namespace App\Livewire;

use App\Models\Order;
use App\Enums\OrderStatus;
use Illuminate\Support\Str;
use Livewire\Component;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Support\Contracts\TranslatableContentDriver;
use App\Filament\Resources\Traits\HasOrderForm;
use Filament\Forms\Form;

class TestCalendarComponent extends Component implements HasForms
{
    use HasOrderForm;
    use InteractsWithForms;

    public ?Order $editing = null;
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function editOrder($orderId): void
    {
        $this->editing = Order::find($orderId);
        $this->data = $this->editing->toArray();
        $this->form->fill($this->data);
        $this->dispatch('open-modal', id: 'edit-order');
    }

    public function saveOrder(): void
    {
        $data = $this->form->getState();

        if ($this->editing) {
            $this->editing->update($data);
            $this->dispatch('close-modal', id: 'edit-order');
            $this->editing = null;
            $this->data = [];
            $this->dispatch('calendar-updated');
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema(static::getOrderFormSchema())
            ->model($this->editing ?? new Order())
            ->statePath('data');
    }

    public function render()
    {
        // Get orders for the calendar
        $events = Order::query()
            ->with(['customer', 'orderProducts.product'])
            ->get()
            ->map(function (Order $order) {
                $isLocked = in_array($order->status, ['in_progress', 'completed', 'delivered']);
                return [
                    'id' => $order->id,
                    'title' => $order->customer?->name ?? $order->order_number,
                    'start' => $order->assigned_delivery_date?->format('Y-m-d') ?? $order->requested_delivery_date->format('Y-m-d'),
                    'allDay' => true,
                    'backgroundColor' => $order->assigned_delivery_date ? $this->getEventColor($order) : 'grey',
                    'borderColor' => 'transparent',
                    'editable' => !$isLocked,
                    'extendedProps' => [
                        'customerName' => $order->customer?->name,
                        'requestedDate' => $order->requested_delivery_date->format('m/d'),
                        'status' => Str::headline($order->status),
                        'isLocked' => $isLocked,
                        'products' => $order->orderProducts->map(function ($orderProduct) {
                            return [
                                'quantity' => $orderProduct->quantity,
                                'sku' => $orderProduct->product->sku,
                                'fill_load' => $orderProduct->fill_load
                            ];
                        })->toArray(),
                    ],
                ];
            })
            ->toArray();

        return view('livewire.test-calendar-component', [
            'events' => $events,
        ]);
    }

    // Required by HasForms interface
    public function getFormStatePath(): ?string
    {
        return 'data';
    }

    public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
    {
        return null;
    }

    protected function getEventColor(Order $order): string
    {
        $status = OrderStatus::from($order->status);
        return $status->color();
    }

    public function updateOrderDate($orderId, $newDate): bool
    {
        $order = Order::find($orderId);
        if ($order) {
            try {
                $order->update([
                    'assigned_delivery_date' => $newDate
                ]);

                // Return true to indicate success
                return true;
            } catch (\Exception $e) {
                // Return false to indicate failure
                return false;
            }
        }
        return false;
    }
}
