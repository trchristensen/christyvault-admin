<?php

namespace App\Enums;

enum PlantLocation: string
{
    case COLMA_MAIN = 'colma_main';
    case TULARE_PLANT = 'tulare_plant';
    case COLMA_LOCALS = 'colma_locals';

    public function getLabel(): string
    {
        return match ($this) {
            self::COLMA_MAIN => 'Colma',
            self::TULARE_PLANT => 'Tulare',
            self::COLMA_LOCALS => 'Locals (Colma)',
        };
    }
}
