<?php

namespace App\Http\Controllers;

use App\Models\DocumentAcknowledgement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PolicyAcknowledgementEvidenceController extends Controller
{
    public function __invoke(Request $request, DocumentAcknowledgement $acknowledgement): BinaryFileResponse
    {
        abort_unless($request->user()?->canManageProcedures(), 403);

        $path = $acknowledgement->evidence_file_path;
        abort_unless(filled($path) && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => Storage::disk('local')->mimeType($path) ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; sandbox",
        ]);
    }
}
