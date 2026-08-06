<?php

namespace App\Http\Controllers;

use App\Models\EmployeeProgramItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class EmployeeProgramMaterialController extends Controller
{
    public function __invoke(Request $request, EmployeeProgramItem $item): BinaryFileResponse
    {
        $item->loadMissing('section.program');
        abort_unless($item->section?->program?->isVisibleTo($request->user()), 403);
        abort_unless($item->type === EmployeeProgramItem::TYPE_FILE && filled($item->file_path), 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($item->file_path), 404);

        $response = response()->file($disk->path($item->file_path), [
            'Content-Type' => $item->mime_type ?: 'application/octet-stream',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);

        $response->setContentDisposition(
            $request->boolean('download')
                ? ResponseHeaderBag::DISPOSITION_ATTACHMENT
                : ResponseHeaderBag::DISPOSITION_INLINE,
            $item->original_name ?: basename($item->file_path),
        );

        return $response;
    }
}
