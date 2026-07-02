<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_rates', function (Blueprint $table) {
            $table->id();
            $table->date('effective_date');
            $table->string('zone');
            $table->decimal('min_miles', 8, 2);
            $table->decimal('max_miles', 8, 2)->nullable();
            $table->string('miles_label');
            $table->decimal('price', 10, 2);
            $table->string('price_unit')->default('vault');
            $table->timestamps();

            $table->unique(['effective_date', 'zone']);
            $table->index(['effective_date', 'min_miles', 'max_miles']);
        });

        DB::table('delivery_rates')->insert([
            [
                'effective_date' => '2026-01-01',
                'zone' => 'Locals',
                'min_miles' => 0,
                'max_miles' => 10,
                'miles_label' => '0-10',
                'price' => 28,
                'price_unit' => 'vault',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'effective_date' => '2026-01-01',
                'zone' => 'A',
                'min_miles' => 10.01,
                'max_miles' => 50,
                'miles_label' => '10-50',
                'price' => 383,
                'price_unit' => 'delivery',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'effective_date' => '2026-01-01',
                'zone' => 'B',
                'min_miles' => 50.01,
                'max_miles' => 100,
                'miles_label' => '51-100',
                'price' => 536,
                'price_unit' => 'delivery',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'effective_date' => '2026-01-01',
                'zone' => 'C',
                'min_miles' => 100.01,
                'max_miles' => 150,
                'miles_label' => '101-150',
                'price' => 710,
                'price_unit' => 'delivery',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'effective_date' => '2026-01-01',
                'zone' => 'D',
                'min_miles' => 150.01,
                'max_miles' => 200,
                'miles_label' => '151-200',
                'price' => 882,
                'price_unit' => 'delivery',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'effective_date' => '2026-01-01',
                'zone' => 'E',
                'min_miles' => 200.01,
                'max_miles' => 250,
                'miles_label' => '201-250',
                'price' => 1056,
                'price_unit' => 'delivery',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'effective_date' => '2026-01-01',
                'zone' => 'F',
                'min_miles' => 250.01,
                'max_miles' => 300,
                'miles_label' => '251-300',
                'price' => 1229,
                'price_unit' => 'delivery',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_rates');
    }
};
