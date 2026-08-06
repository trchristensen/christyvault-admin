<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_programs', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('category')->index();
            $table->text('summary')->nullable();
            $table->json('introduction')->nullable();
            $table->string('audience')->default('all_employees')->index();
            $table->json('plant_locations')->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('employee_program_position', function (Blueprint $table): void {
            $table->foreignId('position_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_program_id')->constrained('employee_programs')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['position_id', 'employee_program_id'], 'employee_program_position_primary');
        });

        Schema::create('employee_program_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_program_id')->constrained('employee_programs')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['employee_program_id', 'sort_order']);
        });

        Schema::create('employee_program_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_program_section_id')
                ->constrained('employee_program_sections')
                ->cascadeOnDelete();
            $table->string('type')->index();
            $table->foreignId('standard_operating_procedure_id')
                ->nullable()
                ->constrained('standard_operating_procedures')
                ->nullOnDelete();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('file_path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->string('media_type')->nullable();
            $table->text('external_url')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['employee_program_section_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_program_items');
        Schema::dropIfExists('employee_program_sections');
        Schema::dropIfExists('employee_program_position');
        Schema::dropIfExists('employee_programs');
    }
};
