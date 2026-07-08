<?php

namespace App\Filament\Resources\OrderResource\Pages;

use Filament\Actions\DeleteAction;
use Illuminate\Database\Eloquent\Model;
use App\Filament\Resources\OrderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load the existing order products for the edit form
        $data['orderProducts'] = $this->record->orderProducts->map(function ($orderProduct) {
            return [
                'product_id' => $orderProduct->product_id,
                'is_custom_product' => $orderProduct->is_custom_product,
                'custom_description' => $orderProduct->custom_description,
                'quantity' => $orderProduct->quantity,
                'fill_load' => $orderProduct->fill_load,
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

        return $record;
    }
}
