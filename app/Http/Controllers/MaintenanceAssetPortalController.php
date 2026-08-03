<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceAsset;
use App\Models\MaintenanceMeterReading;
use App\Models\MaintenanceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaintenanceAssetPortalController extends Controller
{
    public function show(string $token): View
    {
        $asset = MaintenanceAsset::query()
            ->where('qr_token', $token)
            ->with(['location', 'parent'])
            ->firstOrFail();

        return view('maintenance.portal', compact('asset'));
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $asset = MaintenanceAsset::query()->where('qr_token', $token)->firstOrFail();

        $validated = $request->validate([
            'requester_name' => ['required', 'string', 'max:255'],
            'requester_contact' => ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'priority' => ['required', 'in:low,normal,high,urgent,emergency'],
            'safety_related' => ['nullable', 'boolean'],
            'meter_reading' => ['nullable', 'numeric', 'min:0'],
            'photos' => ['nullable', 'array', 'max:4'],
            'photos.*' => ['image', 'max:8192'],
        ]);

        if (isset($validated['meter_reading']) && $asset->current_meter !== null && (float) $validated['meter_reading'] < (float) $asset->current_meter) {
            return back()->withErrors(['meter_reading' => 'The new meter reading cannot be lower than the current reading.'])->withInput();
        }

        $photoPaths = collect($request->file('photos', []))
            ->map(fn ($photo) => $photo->store('maintenance/requests', config('maintenance.disk')))
            ->all();

        $maintenanceRequest = MaintenanceRequest::create([
            'asset_id' => $asset->id,
            'location_id' => $asset->location_id,
            'requester_name' => $validated['requester_name'],
            'requester_contact' => $validated['requester_contact'] ?? null,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'priority' => $validated['priority'],
            'safety_related' => (bool) ($validated['safety_related'] ?? false),
            'status' => 'new',
            'photo_paths' => $photoPaths,
            'submitted_at' => now(),
        ]);

        if (isset($validated['meter_reading'])) {
            MaintenanceMeterReading::create([
                'asset_id' => $asset->id,
                'reading' => $validated['meter_reading'],
                'recorded_at' => now(),
                'source' => 'operator_request',
                'notes' => "Submitted with maintenance request #{$maintenanceRequest->id}",
            ]);
        }

        return redirect()->route('maintenance.assets.portal', $asset->qr_token)
            ->with('success', "Maintenance request #{$maintenanceRequest->id} was submitted.");
    }

    public function qr(string $token)
    {
        $asset = MaintenanceAsset::query()->where('qr_token', $token)->firstOrFail();

        return response($asset->generateQrCode())
            ->header('Content-Type', 'image/svg+xml')
            ->header('Content-Disposition', "attachment; filename=\"{$asset->asset_tag}-maintenance-qr.svg\"");
    }

    public function label(string $token): View
    {
        $asset = MaintenanceAsset::query()->where('qr_token', $token)->firstOrFail();

        return view('maintenance.label', compact('asset'));
    }
}
