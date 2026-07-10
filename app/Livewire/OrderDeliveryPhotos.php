<?php

namespace App\Livewire;

use App\Models\Order;
use App\Models\OrderDeliveryPhoto;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Throwable;

class OrderDeliveryPhotos extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    public Order $order;

    public function mount(Order $order): void
    {
        $this->order = $order;
    }

    public function deleteDeliveryPhotoAction(): Action
    {
        return Action::make('deleteDeliveryPhoto')
            ->label('Delete photo')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->modalHeading('Delete delivery photo?')
            ->modalDescription('This will remove the photo from the order and delete it from storage.')
            ->modalSubmitActionLabel('Yes, delete photo')
            ->requiresConfirmation()
            ->visible(fn(): bool => $this->canDeletePhotos())
            ->action(function (Action $action): void {
                if (!$this->canDeletePhotos()) {
                    Notification::make()
                        ->title('Cannot delete photo')
                        ->body('You do not have permission to delete order photos.')
                        ->danger()
                        ->send();

                    return;
                }

                $photoId = (int) ($action->getArguments()['photo'] ?? 0);
                $photo = OrderDeliveryPhoto::query()
                    ->where('order_id', $this->order->getKey())
                    ->find($photoId);

                if (!$photo) {
                    Notification::make()
                        ->title('Photo not found')
                        ->danger()
                        ->send();

                    return;
                }

                try {
                    if (filled($photo->path)) {
                        Storage::disk($photo->disk ?: 'r2')->delete($photo->path);
                    }
                } catch (Throwable) {
                    Notification::make()
                        ->title('Could not delete photo')
                        ->body('The file could not be removed from storage. Try again in a moment.')
                        ->danger()
                        ->send();

                    return;
                }

                $photo->delete();
                $this->order->refresh();

                Notification::make()
                    ->title('Delivery photo deleted')
                    ->success()
                    ->send();
            });
    }

    public function canDeletePhotos(): bool
    {
        return auth()->user()?->can('can delete order photos') ?? false;
    }

    public function render()
    {
        return view('livewire.order-delivery-photos', [
            'deliveryPhotos' => $this->order
                ->deliveryPhotos()
                ->with('uploadedBy')
                ->latest()
                ->get(),
            'canDeletePhotos' => $this->canDeletePhotos(),
        ]);
    }
}
