<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table): void {
            $table->timestamp('dispatch_confirmed_at')->nullable();
            $table->foreignId('dispatch_confirmed_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('dispatch_confirmed_by_user_id');
            $table->dropColumn('dispatch_confirmed_at');
        });
    }
};
