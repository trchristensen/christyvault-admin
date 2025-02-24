<?php

namespace App\Models;

use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Spatie\LaravelData\Data;
use Sushi\Sushi;
use Illuminate\Database\Eloquent\Model;

class Backup extends Model
{
    use Sushi;

    public function getRows()
    {
        $disk = Storage::disk('r2');
        $files = $disk->files('laravel-backup');
        $backups = [];

        foreach ($files as $file) {
            if (str_ends_with($file, '.zip')) {
                $backups[] = [
                    'filename' => basename($file),
                    'size' => $disk->size($file),
                    'date' => Carbon::createFromTimestamp($disk->lastModified($file))->toDateTimeString(),
                ];
            }
        }

        return $backups;
    }
}
