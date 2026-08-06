<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('standard_operating_procedures', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('category')->index();
            $table->text('summary')->nullable();
            $table->string('audience')->default('all_employees')->index();
            $table->json('plant_locations')->nullable();
            $table->boolean('public_qr_enabled')->default(false)->index();
            $table->string('qr_token', 64)->unique();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('draft_content')->nullable();
            $table->text('draft_change_summary')->nullable();
            $table->date('draft_effective_date')->nullable();
            $table->date('draft_review_due_date')->nullable();
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('standard_operating_procedure_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('standard_operating_procedure_id')
                ->constrained('standard_operating_procedures')
                ->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('status')->default('published')->index();
            $table->string('code');
            $table->string('title');
            $table->string('category');
            $table->text('summary')->nullable();
            $table->json('content');
            $table->string('content_hash', 64);
            $table->text('change_summary')->nullable();
            $table->date('effective_date');
            $table->date('review_due_date')->nullable()->index();
            $table->foreignId('published_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at');
            $table->timestamps();

            $table->unique(
                ['standard_operating_procedure_id', 'version'],
                'sop_revisions_procedure_version_unique',
            );
        });

        Schema::table('standard_operating_procedures', function (Blueprint $table): void {
            $table->foreignId('current_revision_id')
                ->nullable()
                ->after('owner_user_id')
                ->constrained('standard_operating_procedure_revisions')
                ->nullOnDelete();
        });

        Schema::create('position_standard_operating_procedure', function (Blueprint $table): void {
            $table->foreignId('position_id')->constrained()->cascadeOnDelete();
            $table->foreignId('standard_operating_procedure_id')
                ->constrained('standard_operating_procedures')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->primary(
                ['position_id', 'standard_operating_procedure_id'],
                'position_sop_primary',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('position_standard_operating_procedure');

        Schema::table('standard_operating_procedures', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('current_revision_id');
        });

        Schema::dropIfExists('standard_operating_procedure_revisions');
        Schema::dropIfExists('standard_operating_procedures');
    }
};
