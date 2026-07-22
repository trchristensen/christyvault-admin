<?php

namespace App\Livewire;

use App\Models\Order;
use App\Services\LoadPlanning\TripLoadPlanService;
use Livewire\Component;

class OrderModal extends Component
{
    public $showModal = false;

    public $order = null;

    public bool $showLoadSummary = false;

    protected $listeners = ['showOrderModal'];

    public function showOrderModal($orderId)
    {
        logger('OrderModal: showOrderModal called with ID: '.$orderId);
        $this->order = Order::with(['location.plantDriveDistanceOrigin', 'trip'])->find($orderId);
        logger('OrderModal: loaded order: '.($this->order ? $this->order->order_number : 'null'));
        $this->showLoadSummary = false;
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->order = null;
        $this->showLoadSummary = false;
    }

    public function canViewLoadSummary(): bool
    {
        return $this->order instanceof Order
            && filled($this->order->trip_id)
            && $this->order->trip?->loadSummaryIsVisibleTo(auth()->user());
    }

    public function openLoadSummary(): void
    {
        abort_unless($this->canViewLoadSummary(), 403);

        $this->showLoadSummary = true;
    }

    public function backToOrder(): void
    {
        $this->showLoadSummary = false;
    }

    public function editOrder()
    {
        if ($this->order) {
            return redirect()->route('filament.admin.resources.orders.edit', ['record' => $this->order]);
        }
    }

    public function duplicateOrder()
    {
        if ($this->order) {
            return redirect()->route('filament.admin.resources.orders.duplicate', ['record' => $this->order]);
        }
    }

    public function printDeliveryTag()
    {
        if ($this->order) {
            $this->dispatch('open-url', url: route('orders.print', ['order' => $this->order]));
        }
    }

    public function previewDeliveryTag()
    {
        if ($this->order) {
            $this->dispatch('open-url', url: route('orders.print.formbg', ['order' => $this->order]));
        }
    }

    public function deleteOrder()
    {
        if ($this->order) {
            $this->order->delete();
            $this->dispatch('order-deleted');
            $this->closeModal();
        }
    }

    public function render()
    {
        $loadSummary = null;

        if ($this->showLoadSummary && $this->canViewLoadSummary()) {
            $plan = app(TripLoadPlanService::class)->forTrip($this->order->trip);
            $loadSummary = [
                'result' => $plan['demand']->toArray(),
                'diagram' => $plan['diagram'],
                'fillAllocations' => $plan['fill_allocations'],
            ];
        }

        return view('livewire.order-modal', compact('loadSummary'));
    }
}
