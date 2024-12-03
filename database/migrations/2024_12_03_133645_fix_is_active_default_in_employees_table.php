<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First remove the default constraint
        DB::statement('ALTER TABLE employees ALTER COLUMN is_active DROP DEFAULT');

        // Then convert to integer
        DB::statement('ALTER TABLE employees ALTER COLUMN is_active TYPE integer USING CASE WHEN is_active THEN 1 ELSE 0 END');

        // Then convert to boolean
        DB::statement('ALTER TABLE employees ALTER COLUMN is_active TYPE boolean USING CASE WHEN is_active=1 THEN true ELSE false END');

        // Finally, set the new default
        DB::statement('ALTER TABLE employees ALTER COLUMN is_active SET DEFAULT true');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE employees ALTER COLUMN is_active SET DEFAULT true');
    }
};
