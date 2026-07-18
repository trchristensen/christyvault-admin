<?php

namespace App\Services\LoadPlanning;

use JsonSerializable;

final class LoadDemandResult implements JsonSerializable
{
    public function __construct(
        public readonly array $summary,
        public readonly array $stops,
        public readonly array $warnings,
        public readonly ?array $vehicleConfiguration = null,
    ) {}

    public function isReadyForAutomaticPlacement(): bool
    {
        return collect($this->warnings)->doesntContain(
            fn (array $warning): bool => (bool) ($warning['blocking'] ?? false),
        );
    }

    public function toArray(): array
    {
        return [
            'summary' => $this->summary,
            'stops' => $this->stops,
            'warnings' => $this->warnings,
            'vehicle_configuration' => $this->vehicleConfiguration,
            'ready_for_automatic_placement' => $this->isReadyForAutomaticPlacement(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
