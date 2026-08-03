<?php

namespace App\Services\Maintenance;

use App\Models\MaintenanceRequest;
use App\Models\MaintenanceWorkOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MaintenanceRequestConverter
{
    public function convert(MaintenanceRequest $request, User $user, array $overrides = []): MaintenanceWorkOrder
    {
        return DB::transaction(function () use ($request, $user, $overrides): MaintenanceWorkOrder {
            $request = MaintenanceRequest::query()->lockForUpdate()->findOrFail($request->id);

            if ($request->workOrder) {
                return $request->workOrder;
            }

            $workOrder = MaintenanceWorkOrder::create(array_merge([
                'asset_id' => $request->asset_id,
                'request_id' => $request->id,
                'created_by_user_id' => $user->id,
                'title' => $request->title,
                'description' => $request->description,
                'type' => $request->priority === 'emergency' ? 'emergency' : 'reactive',
                'priority' => $request->priority,
                'status' => 'approved',
                'safety_related' => $request->safety_related,
                'attachment_paths' => $request->photo_paths,
            ], $overrides));

            $request->update([
                'status' => 'accepted',
                'triaged_by_user_id' => $user->id,
                'triaged_at' => now(),
            ]);

            return $workOrder;
        });
    }
}
