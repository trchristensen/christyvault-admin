<?php

namespace App\Enums;

enum TaxableStatus: string
{
    case TAXABLE = 'taxable';
    case NON_TAXABLE = 'non_taxable';
}

enum ItemCategory: string
{
        // Taxable Purchases - Indirect Labor
    case RM_MACHINE_AND_EQUIPMENT = 'rm_machine_and_equipment';
    case FORKLIFTS = 'forklifts';
    case OTHER = 'other';
    case SUPPLIES = 'supplies';

        // Taxable Purchases - Shipping
    case SH_RM_FORKLIFTS = 'sh_rm_forklifts';
    case SH_VEHICLES = 'sh_vehicles';
    case SH_OTHER = 'sh_other';
    case SH_SUPPLIES = 'sh_supplies';

        // Taxable Purchases - Other
    case COST_OF_GOODS_SOLD_WILBERT = 'cost_of_goods_sold_wilbert';
    case NICHE = 'niche';

        // Non-Taxable Purchases
    case RAW_MATERIALS = 'raw_materials';
    case PRODUCTION_SUPPLIES = 'production_supplies';
        // Add other non-taxable categories as needed

    case OFFICE_SUPPLIES = 'office_supplies';
    case MISC = 'misc';

    public function getTaxableStatus(): TaxableStatus
    {
        return match ($this) {
            self::RAW_MATERIALS,
            self::PRODUCTION_SUPPLIES => TaxableStatus::NON_TAXABLE,
            default => TaxableStatus::TAXABLE
        };
    }

    public function label(): string
    {
        return match ($this) {
            // Existing taxable labels
            self::RM_MACHINE_AND_EQUIPMENT => 'R&M Machine and Equipment',
            self::FORKLIFTS => 'Forklifts',
            self::OTHER => 'Other',
            self::SUPPLIES => 'Supplies',

            self::SH_RM_FORKLIFTS => 'R&M Forklifts',
            self::SH_VEHICLES => 'Vehicles',
            self::SH_OTHER => 'Other',
            self::SH_SUPPLIES => 'Supplies',

            self::COST_OF_GOODS_SOLD_WILBERT => 'Cost of Goods Sold Wilbert',
            self::NICHE => 'Niche',

            // New non-taxable labels
            self::RAW_MATERIALS => 'Raw Materials',
            self::PRODUCTION_SUPPLIES => 'Production Supplies',
            // office supplies
            self::OFFICE_SUPPLIES => 'Office Supplies',
            // miscellaneous
            self::MISC => 'Miscellaneous',
        };
    }
}
