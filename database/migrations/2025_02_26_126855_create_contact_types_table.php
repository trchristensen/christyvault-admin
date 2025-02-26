<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // First drop the contact_type column from contacts
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn('contact_type');
        });

        // Create the contact_types table
        Schema::create('contact_types', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // 'sales', 'delivery', etc.
            $table->timestamps();
        });

        // Create the pivot table
        Schema::create('contact_contact_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_type_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_contact_type');
        Schema::dropIfExists('contact_types');

        Schema::table('contacts', function (Blueprint $table) {
            $table->string('contact_type')->nullable();
        });
    }
};
