<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Actions\TripLoadSummaryAction;
use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Services\DeliveryTripService;
use App\Services\LoadPlanning\TripLoadPlanService;
use App\Services\TripVehicleConfigurationResolver;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        $save = $this->getSaveFormAction()
            ->label('Save changes')
            ->icon('heroicon-o-check')
            ->color('primary')
            ->size('lg')
            ->formId('form');

        $cancel = $this->getCancelFormAction()
            ->label('Cancel changes')
            ->icon('heroicon-o-x-mark')
            ->color('gray')
            ->size('lg')
            ->hiddenLabel()
            ->tooltip('Cancel changes');

        $loadSummary = TripLoadSummaryAction::make()
            ->size('lg')
            ->hiddenLabel()
            ->tooltip('Load summary');

        $printTag = Action::make('printDeliveryTag')
            ->label('Print delivery tag')
            ->icon('heroicon-o-printer')
            ->color('gray')
            ->size('lg')
            ->hiddenLabel()
            ->tooltip('Print delivery tag')
            ->url(fn (Order $record): string => route('orders.print', ['order' => $record]))
            ->openUrlInNewTab();

        $previewTag = Action::make('previewDeliveryTag')
            ->label('Preview delivery tag')
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->size('lg')
            ->hiddenLabel()
            ->tooltip('Preview delivery tag')
            ->url(fn (Order $record): string => route('orders.print.formbg', ['order' => $record]))
            ->openUrlInNewTab();

        $delete = DeleteAction::make()
            ->label('Delete order')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->size('lg')
            ->hiddenLabel()
            ->tooltip('Delete order')
            ->modalHeading('Delete this order?')
            ->modalDescription(fn (Order $record): string => "{$record->order_number} will be removed from active orders. You can restore it later.")
            ->modalSubmitActionLabel('Delete order');

        return [
            ActionGroup::make([
                $save,
                $cancel,
                $loadSummary,
                $printTag,
                $previewTag,
                $delete,
            ])->buttonGroup(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['load_preview_vehicle_configuration_id'] = $this->record->trip?->vehicle_configuration_id
            ?? app(TripVehicleConfigurationResolver::class)
                ->defaultForOrders([$this->record])
                ?->getKey();

        // Load the existing order products for the edit form
        $data['orderProducts'] = $this->record->orderProducts->map(function ($orderProduct) {
            return [
                'product_id' => $orderProduct->product_id,
                'is_custom_product' => $orderProduct->is_custom_product,
                'custom_description' => $orderProduct->custom_description,
                'quantity' => $orderProduct->quantity,
                'fill_load' => $orderProduct->fill_load,
                'planned_fill_quantity' => $orderProduct->planned_fill_quantity,
                'fill_priority' => $orderProduct->fill_priority,
                'fill_plan_source' => $orderProduct->fill_plan_source,
                'fill_locked_at' => $orderProduct->fill_locked_at?->toDateTimeString(),
                'price' => $orderProduct->price,
                'location' => $orderProduct->location,
                'notes' => $orderProduct->notes,
                'quantity_delivered' => $orderProduct->quantity_delivered,
            ];
        })->toArray();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $hasProducts = array_key_exists('orderProducts', $data);
        $products = $data['orderProducts'] ?? [];

        DB::transaction(function () use ($record, $data, $hasProducts, $products): void {
            $record->update(Arr::except($data, 'orderProducts'));

            if (! $hasProducts) {
                return;
            }

            $record->orderProducts()->delete();

            if ($products !== []) {
                $record->orderProducts()->createMany($products);
            }
        });

        $record->location?->updateOrderAnalytics();

        if ($record->trip) {
            app(TripLoadPlanService::class)->unlockFillPlan($record->trip);
            app(DeliveryTripService::class)->invalidateStopOrderConfirmation($record->trip);
        }

        return $record;
    }
}
