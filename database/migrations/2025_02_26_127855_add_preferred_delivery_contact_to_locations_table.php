<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->foreignId('preferred_delivery_contact_id')
                ->nullable()
                ->constrained('contacts')
                ->nullOnDelete();  // If contact is deleted, just set to null
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropForeign(['preferred_delivery_contact_id']);
            $table->dropColumn('preferred_delivery_contact_id');
        });
    }
};
