<?php

namespace App\Filament\Operations\Resources\PurchaseOrderResource\Pages;

use App\Filament\Operations\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreatePurchaseOrder extends CreateRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_user_id'] = Auth::id();
        $data['total_amount'] = $data['total_amount'] ?? 0;
        
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }

    protected function beforeFill(): void
    {
        // Set default values from URL parameters
        if (request()->has('supplier_id')) {
            $this->data['supplier_id'] = request('supplier_id');
        }

        if (request()->has('is_liner_load')) {
            $this->data['is_liner_load'] = request('is_liner_load') === 'Wilbert';
        }

        // Set default values
        $this->data['status'] = PurchaseOrder::STATUS_DRAFT;
        $this->data['order_date'] = now();
    }
}
