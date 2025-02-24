<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class Backup extends Model
{
    public $timestamps = false;
    
    // Make this a dynamic model that reads from storage
    public static function query()
    {
        $files = Storage::disk('r2')->files();
        
        // Filter for zip files and map to collection
        $backups = collect($files)
            ->filter(fn($file) => str_ends_with($file, '.zip'))
            ->map(function($file) {
                return new static([
                    'filename' => basename($file),
                    'size' => Storage::disk('r2')->size($file),
                    'date' => Carbon::createFromTimestamp(Storage::disk('r2')->lastModified($file)),
                ]);
            });
            
        return new \Illuminate\Database\Eloquent\Collection($backups);
    }

    // Allow dynamic property access
    public function __get($key)
    {
        return $this->attributes[$key] ?? null;
    }
}
