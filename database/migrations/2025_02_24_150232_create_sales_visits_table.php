<?php

use App\Enums\SalesVisitStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->timestamp('planned_at');
            $table->timestamp('completed_at')->nullable();
            $table->string('status')->default(SalesVisitStatus::PLANNED->value);
            $table->text('visit_notes')->nullable();
            $table->text('followup_summary')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_visits');
    }
};
