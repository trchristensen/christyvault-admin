<?php

namespace App\Http\Controllers;

use App\Models\StandardOperatingProcedure;
use App\Models\StandardOperatingProcedureRevision;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class ProcedurePortalController extends Controller
{
    public function show(string $token): View
    {
        [$procedure, $revision] = $this->publishedProcedure($token);

        return view('procedures.show', compact('procedure', 'revision'));
    }

    public function qr(string $token): Response
    {
        [$procedure] = $this->publishedProcedure($token);

        return response($procedure->generateQrCode())
            ->header('Content-Type', 'image/svg+xml')
            ->header('Content-Disposition', "attachment; filename=\"{$procedure->code}-qr.svg\"");
    }

    public function label(string $token): View
    {
        [$procedure, $revision] = $this->publishedProcedure($token);

        return view('procedures.label', compact('procedure', 'revision'));
    }

    public function attachment(Request $request, StandardOperatingProcedure $procedure, string $attachment): BinaryFileResponse
    {
        abort_unless($procedure->isVisibleTo($request->user()), 403);

        $revision = $procedure->currentRevision;
        abort_unless($revision, 404);

        return $this->attachmentResponse($request, $revision, $attachment);
    }

    public function publicAttachment(Request $request, string $token, string $attachment): BinaryFileResponse
    {
        [, $revision] = $this->publishedProcedure($token);

        return $this->attachmentResponse($request, $revision, $attachment, publicOnly: true);
    }

    /**
     * @return array{StandardOperatingProcedure, StandardOperatingProcedureRevision}
     */
    private function publishedProcedure(string $token): array
    {
        $procedure = StandardOperatingProcedure::query()
            ->where('qr_token', $token)
            ->where('public_qr_enabled', true)
            ->where('audience', '!=', StandardOperatingProcedure::AUDIENCE_MANAGEMENT)
            ->whereNull('archived_at')
            ->whereHas('currentRevision', fn ($query) => $query
                ->where('status', StandardOperatingProcedureRevision::STATUS_PUBLISHED)
                ->whereDate('effective_date', '<=', today()))
            ->with('currentRevision.publisher')
            ->firstOrFail();

        return [$procedure, $procedure->currentRevision];
    }

    private function attachmentResponse(
        Request $request,
        StandardOperatingProcedureRevision $revision,
        string $token,
        bool $publicOnly = false,
    ): BinaryFileResponse {
        $attachment = $revision->findAttachment($token, $publicOnly);
        abort_unless($attachment, 404);

        $disk = Storage::disk('local');
        $path = (string) $attachment['path'];
        abort_unless($disk->exists($path), 404);

        $response = response()->file($disk->path($path), [
            'Content-Type' => (string) ($attachment['mime_type'] ?? 'application/octet-stream'),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);

        $response->setContentDisposition(
            $request->boolean('download')
                ? ResponseHeaderBag::DISPOSITION_ATTACHMENT
                : ResponseHeaderBag::DISPOSITION_INLINE,
            (string) ($attachment['original_name'] ?? basename($path)),
        );

        return $response;
    }
}
