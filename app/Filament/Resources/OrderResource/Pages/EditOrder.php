<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load the existing order products for the edit form
        $data['orderProducts'] = $this->record->orderProducts->map(function ($orderProduct) {
            return [
                'product_id' => $orderProduct->product_id,
                'is_custom_product' => $orderProduct->is_custom_product,
                'custom_sku' => $orderProduct->custom_sku,
                'custom_name' => $orderProduct->custom_name,
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

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        // Update the order
        $record->update($data);

        // Handle order products manually since we're not using ->relationship()
        if (isset($data['orderProducts'])) {
            // Delete existing order products
            $record->orderProducts()->delete();
            
            // Create new ones
            foreach ($data['orderProducts'] as $productData) {
                $record->orderProducts()->create($productData);
            }
        }

        return $record;
    }
}
