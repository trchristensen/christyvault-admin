<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Order;

class OrderModal extends Component
{
    public $showModal = false;
    public $order = null;

    protected $listeners = ['showOrderModal'];

    public function showOrderModal($orderId)
    {
        logger('OrderModal: showOrderModal called with ID: ' . $orderId);
        $this->order = Order::with('location')->find($orderId);
        logger('OrderModal: loaded order: ' . ($this->order ? $this->order->order_number : 'null'));
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->order = null;
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
        return view('livewire.order-modal');
    }
}
