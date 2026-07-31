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
