<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ImportCatalogProductWeights extends Command
{
    protected $signature = 'products:import-catalog-weights
        {--apply : Write changes. Without this flag the command is a dry run.}
        {--overwrite : Replace an existing weight when it differs from the catalog.}';

    protected $description = 'Safely import product shipping weights from the version-controlled 2025 catalog data';

    public function handle(): int
    {
        if (! Schema::hasColumn('products', 'weight_lbs')) {
            $this->error('The products.weight_lbs column is missing. Run migrations first.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $overwrite = (bool) $this->option('overwrite');
        $catalog = require database_path('data/product_weights_2025.php');

        $summary = [
            'matched_products' => 0,
            'pending_changes' => 0,
            'updated_products' => 0,
            'unchanged_products' => 0,
            'preserved_conflicts' => 0,
            'unmatched_catalog_rows' => 0,
            'review_rows' => 0,
        ];
        $unmatched = [];
        $conflicts = [];
        $reviews = [];

        foreach ($catalog as $row) {
            if (isset($row['review'])) {
                $summary['review_rows']++;
                $reviews[] = [$row['catalog_sku'], $row['page'], $row['review']];

                continue;
            }

            $skus = collect([$row['catalog_sku'], ...($row['aliases'] ?? [])])
                ->map(fn (string $sku): string => $this->normalizeSku($sku))
                ->unique()
                ->values()
                ->all();

            $products = Product::query()
                ->whereIn(DB::raw('UPPER(TRIM(sku))'), $skus)
                ->get();

            if ($products->isEmpty()) {
                $summary['unmatched_catalog_rows']++;
                $unmatched[] = [$row['catalog_sku'], $row['page']];

                continue;
            }

            foreach ($products as $product) {
                $summary['matched_products']++;
                $catalogWeight = (float) $row['weight_lbs'];
                $currentWeight = $product->weight_lbs === null ? null : (float) $product->weight_lbs;

                if ($currentWeight !== null && abs($currentWeight - $catalogWeight) < 0.005) {
                    $summary['unchanged_products']++;

                    continue;
                }

                if ($currentWeight !== null && ! $overwrite) {
                    $summary['preserved_conflicts']++;
                    $conflicts[] = [$product->sku, $currentWeight, $catalogWeight];

                    continue;
                }

                $summary['pending_changes']++;

                if ($apply) {
                    $product->update(['weight_lbs' => $catalogWeight]);
                    $summary['updated_products']++;
                }
            }
        }

        $this->table(['Check', 'Records'], [
            ['Products matched by catalog SKU or approved alias', $summary['matched_products']],
            [$apply ? 'Products eligible for update' : 'Products that would be updated', $summary['pending_changes']],
            ['Products updated', $summary['updated_products']],
            ['Products already at catalog weight', $summary['unchanged_products']],
            ['Existing different weights preserved', $summary['preserved_conflicts']],
            ['Catalog rows not present in this database', $summary['unmatched_catalog_rows']],
            ['Catalog rows held for review', $summary['review_rows']],
        ]);

        if ($reviews !== []) {
            $this->newLine();
            $this->warn('Held for review because the printed catalog is internally inconsistent:');
            $this->table(['Catalog SKU', 'PDF page', 'Reason'], $reviews);
        }

        if ($conflicts !== []) {
            $this->newLine();
            $this->warn('Existing weights were preserved. Use --overwrite with --apply only after reviewing them:');
            $this->table(['Product SKU', 'Existing lb', 'Catalog lb'], $conflicts);
        }

        if ($unmatched !== []) {
            $this->newLine();
            $this->line('Catalog products not found in this database:');
            $this->table(['Catalog SKU', 'PDF page'], $unmatched);
        }

        if (! $apply) {
            $this->newLine();
            $this->warn('DRY RUN ONLY: no product records were changed.');
            $this->line('Use --apply after reviewing this report.');
        }

        return self::SUCCESS;
    }

    private function normalizeSku(string $sku): string
    {
        return mb_strtoupper(trim($sku));
    }
}
