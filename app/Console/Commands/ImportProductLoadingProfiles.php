<?php

namespace App\Console\Commands;

use App\Models\LoadingProfile;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ImportProductLoadingProfiles extends Command
{
    protected $signature = 'products:import-loading-profiles
        {--apply : Write changes. Without this flag the command is a dry run.}
        {--overwrite : Replace an existing different loading profile.}';

    protected $description = 'Safely assign version-controlled loading profiles to products by SKU';

    public function handle(): int
    {
        if (! Schema::hasTable('loading_profiles') || ! Schema::hasColumn('products', 'loading_profile_id')) {
            $this->error('Loading-profile tables or product assignment column are missing. Run migrations first.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $overwrite = (bool) $this->option('overwrite');
        $assignments = require database_path('data/product_loading_profiles.php');
        $profiles = LoadingProfile::query()->get()->keyBy('code');
        $summary = [
            'matched_products' => 0,
            'pending_changes' => 0,
            'updated_products' => 0,
            'unchanged_products' => 0,
            'preserved_conflicts' => 0,
            'unmatched_skus' => 0,
        ];
        $unmatched = [];
        $conflicts = [];

        foreach ($assignments as $assignment) {
            $profile = $profiles->get($assignment['profile_code']);

            if (! $profile) {
                $this->error("Loading profile [{$assignment['profile_code']}] does not exist. No records were changed.");

                return self::FAILURE;
            }

            $products = Product::query()
                ->where(DB::raw('UPPER(TRIM(sku))'), $this->normalizeSku($assignment['sku']))
                ->with('loadingProfile:id,code')
                ->get();

            if ($products->isEmpty()) {
                $summary['unmatched_skus']++;
                $unmatched[] = [$assignment['sku'], $assignment['profile_code']];

                continue;
            }

            foreach ($products as $product) {
                $summary['matched_products']++;

                if ($product->loading_profile_id === $profile->id) {
                    $summary['unchanged_products']++;

                    continue;
                }

                if ($product->loading_profile_id !== null && ! $overwrite) {
                    $summary['preserved_conflicts']++;
                    $conflicts[] = [
                        $product->sku,
                        $product->loadingProfile?->code ?? 'unknown',
                        $profile->code,
                    ];

                    continue;
                }

                $summary['pending_changes']++;

                if ($apply) {
                    $product->update(['loading_profile_id' => $profile->id]);
                    $summary['updated_products']++;
                }
            }
        }

        $this->table(['Check', 'Records'], [
            ['Products matched by normalized SKU', $summary['matched_products']],
            [$apply ? 'Products eligible for update' : 'Products that would be updated', $summary['pending_changes']],
            ['Products updated', $summary['updated_products']],
            ['Products already assigned correctly', $summary['unchanged_products']],
            ['Existing different profiles preserved', $summary['preserved_conflicts']],
            ['Configured SKUs not present in this database', $summary['unmatched_skus']],
        ]);

        if ($conflicts !== []) {
            $this->newLine();
            $this->warn('Existing different profiles were preserved. Use --overwrite with --apply only after review:');
            $this->table(['Product SKU', 'Existing Profile', 'Configured Profile'], $conflicts);
        }

        if ($unmatched !== []) {
            $this->newLine();
            $this->line('Configured products not found in this database:');
            $this->table(['Product SKU', 'Loading Profile'], $unmatched);
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
