<?php

namespace App\Support;

final class MaintenanceOptions
{
    public static function assetCategories(): array
    {
        return [
            'forklift' => 'Forklift',
            'piggyback_forklift' => 'Piggyback forklift',
            'backhoe' => 'Backhoe',
            'boom_truck' => 'Boom truck',
            'tractor' => 'Tractor',
            'truck' => 'Truck',
            'trailer' => 'Trailer',
            'gantry_crane' => 'Gantry crane',
            'batch_plant' => 'Batch plant',
            'mixer' => 'Mixer',
            'conveyor' => 'Conveyor',
            'silo' => 'Silo',
            'compressor' => 'Compressor',
            'facility' => 'Facility',
            'tooling' => 'Tooling',
            'other' => 'Other',
        ];
    }

    public static function assetStatuses(): array
    {
        return [
            'operational' => 'Operational',
            'restricted' => 'Operational with restrictions',
            'scheduled_downtime' => 'Scheduled downtime',
            'out_of_service' => 'Out of service',
            'retired' => 'Retired',
        ];
    }

    public static function criticalities(): array
    {
        return ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical'];
    }

    public static function priorities(): array
    {
        return ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent', 'emergency' => 'Emergency'];
    }

    public static function requestStatuses(): array
    {
        return ['new' => 'New', 'accepted' => 'Accepted', 'rejected' => 'Rejected', 'duplicate' => 'Duplicate'];
    }

    public static function workOrderStatuses(): array
    {
        return [
            'approved' => 'Approved',
            'scheduled' => 'Scheduled',
            'in_progress' => 'In progress',
            'waiting_on_parts' => 'Waiting on parts',
            'on_hold' => 'On hold',
            'pending_verification' => 'Pending verification',
            'completed' => 'Completed',
            'canceled' => 'Canceled',
        ];
    }

    public static function workOrderTypes(): array
    {
        return [
            'reactive' => 'Reactive',
            'preventive' => 'Preventive',
            'inspection' => 'Inspection',
            'emergency' => 'Emergency',
            'corrective' => 'Corrective follow-up',
        ];
    }

    public static function meterTypes(): array
    {
        return ['hours' => 'Hours', 'miles' => 'Miles', 'cycles' => 'Cycles / batches'];
    }

    public static function triggerTypes(): array
    {
        return ['calendar' => 'Calendar', 'meter' => 'Meter'];
    }

    public static function intervalUnits(): array
    {
        return ['days' => 'Days', 'weeks' => 'Weeks', 'months' => 'Months', 'years' => 'Years'];
    }

    public static function colorForPriority(?string $priority): string
    {
        return match ($priority) {
            'emergency', 'urgent' => 'danger',
            'high' => 'warning',
            'normal' => 'info',
            default => 'gray',
        };
    }

    public static function colorForAssetStatus(?string $status): string
    {
        return match ($status) {
            'operational' => 'success',
            'restricted', 'scheduled_downtime' => 'warning',
            'out_of_service' => 'danger',
            default => 'gray',
        };
    }
}
