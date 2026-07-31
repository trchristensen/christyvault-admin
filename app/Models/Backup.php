<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Sushi\Sushi;
use Symfony\Component\HttpFoundation\HeaderUtils;

class Backup extends Model
{
    use Sushi;

    public function getRows(): array
    {
        $disk = Storage::disk('r2');
        $files = $disk->files('laravel-backup');
        $backups = [];

        foreach ($files as $file) {
            if (str_ends_with($file, '.zip')) {
                $backups[] = [
                    'filename' => basename($file),
                    'path' => $file,
                    'size' => $disk->size($file),
                    'date' => Carbon::createFromTimestamp($disk->lastModified($file))->toDateTimeString(),
                ];
            }
        }

        return $backups;
    }

    public function temporaryDownloadUrl(): string
    {
        return Storage::disk('r2')->temporaryUrl(
            $this->path,
            now()->addMinutes(10),
            [
                'ResponseContentDisposition' => HeaderUtils::makeDisposition(
                    'attachment',
                    $this->filename,
                    $this->filename,
                ),
                'ResponseContentType' => 'application/zip',
            ],
        );
    }
}
