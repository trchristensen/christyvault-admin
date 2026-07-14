<?php

namespace App\Console\Commands;

use App\Models\OrderDeliveryPhoto;
use App\Services\DeliveryPhotoVariantGenerator;
use Illuminate\Console\Command;
use Throwable;

class GenerateDeliveryPhotoVariants extends Command
{
    protected $signature = 'delivery-photos:generate-variants
        {--force : Regenerate variants that already exist}
        {--limit= : Stop after processing this many photos}';

    protected $description = 'Generate optimized thumbnail and slideshow variants for delivery photos';

    public function handle(DeliveryPhotoVariantGenerator $generator): int
    {
        $force = (bool) $this->option('force');
        $limit = max(0, (int) ($this->option('limit') ?? 0));
        $processed = 0;
        $failed = 0;

        $query = OrderDeliveryPhoto::query()->orderBy('id');

        if (! $force) {
            $query->where(function ($query): void {
                $query->whereNull('thumbnail_path')->orWhereNull('display_path');
            });
        }

        foreach ($query->lazyById() as $photo) {
            if ($limit > 0 && $processed + $failed >= $limit) {
                break;
            }

            try {
                $generator->generate($photo, $force);
                $processed++;
                $this->line("Generated variants for photo #{$photo->getKey()}");
            } catch (Throwable $exception) {
                $failed++;
                $this->error("Photo #{$photo->getKey()}: {$exception->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("Generated: {$processed}; failed: {$failed}");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
