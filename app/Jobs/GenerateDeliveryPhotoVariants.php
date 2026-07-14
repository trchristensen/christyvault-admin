<?php

namespace App\Jobs;

use App\Models\OrderDeliveryPhoto;
use App\Services\DeliveryPhotoVariantGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateDeliveryPhotoVariants implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(public OrderDeliveryPhoto $photo)
    {
        // The application does not run a persistent queue worker. The sync
        // connection still runs after the response because dispatch sites use
        // afterResponse(), keeping uploads responsive and processing reliable.
        $this->onConnection('sync');
    }

    public function handle(DeliveryPhotoVariantGenerator $generator): void
    {
        $generator->generate($this->photo->freshOrFail());
    }
}
