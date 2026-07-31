<?php

use App\Models\Backup;
use Illuminate\Support\Facades\Storage;

it('preserves the complete storage path for backup actions', function (): void {
    Storage::fake('r2');

    $filename = '2026-07-31-01-30-03.zip';
    $path = "laravel-backup/{$filename}";

    Storage::disk('r2')->put($path, 'backup contents');

    $rows = (new Backup)->getRows();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['filename'])->toBe($filename)
        ->and($rows[0]['path'])->toBe($path)
        ->and($rows[0]['size'])->toBe(strlen('backup contents'));
});

it('downloads backups through a temporary r2 url without streaming them through php', function (): void {
    Storage::fake('r2');

    $requestedPath = null;
    $requestedOptions = null;

    Storage::disk('r2')->buildTemporaryUrlsUsing(
        function (string $path, DateTimeInterface $expiration, array $options) use (&$requestedPath, &$requestedOptions): string {
            $requestedPath = $path;
            $requestedOptions = $options;

            return 'https://r2.example.test/temporary-backup-url';
        },
    );

    $backup = (new Backup)->forceFill([
        'filename' => '2026-07-31-01-30-03.zip',
        'path' => 'laravel-backup/2026-07-31-01-30-03.zip',
    ]);

    expect($backup->temporaryDownloadUrl())
        ->toBe('https://r2.example.test/temporary-backup-url')
        ->and($requestedPath)->toBe('laravel-backup/2026-07-31-01-30-03.zip')
        ->and($requestedOptions)->toMatchArray([
            'ResponseContentDisposition' => 'attachment; filename=2026-07-31-01-30-03.zip',
            'ResponseContentType' => 'application/zip',
        ]);
});
