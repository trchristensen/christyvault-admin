<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Drop the existing check constraint
        DB::statement('ALTER TABLE trips DROP CONSTRAINT IF EXISTS trips_status_check');

        // Add the new check constraint with 'confirmed' status
        DB::statement("ALTER TABLE trips ADD CONSTRAINT trips_status_check CHECK (status::text = ANY (ARRAY['pending'::text, 'confirmed'::text, 'in_progress'::text, 'completed'::text, 'cancelled'::text]))");
    }

    public function down()
    {
        // Remove the new check constraint
        DB::statement('ALTER TABLE trips DROP CONSTRAINT IF EXISTS trips_status_check');

        // Add back the original check constraint without 'confirmed'
        DB::statement("ALTER TABLE trips ADD CONSTRAINT trips_status_check CHECK (status::text = ANY (ARRAY['pending'::text, 'in_progress'::text, 'completed'::text, 'cancelled'::text]))");
    }
};
