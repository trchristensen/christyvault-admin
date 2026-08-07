<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_pre_trip_inspection_defects', function (Blueprint $table): void {
            $table->string('driver_assessment')->default('stop')->index()->after('safety_related');
            $table->string('operating_decision')->nullable()->index()->after('status');
            $table->foreignId('reviewed_by_user_id')->nullable()->after('resolved_by_user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reported_at');
            $table->text('review_notes')->nullable()->after('resolution_notes');
        });
    }

    public function down(): void
    {
        Schema::table('trip_pre_trip_inspection_defects', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reviewed_by_user_id');
            $table->dropColumn([
                'driver_assessment',
                'operating_decision',
                'reviewed_at',
                'review_notes',
            ]);
        });
    }
};
