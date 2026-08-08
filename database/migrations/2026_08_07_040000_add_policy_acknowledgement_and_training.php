<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('standard_operating_procedures', function (Blueprint $table): void {
            $table->string('document_type')->default('procedure')->after('id')->index();
            $table->boolean('acknowledgement_required')->default(false)->after('public_qr_enabled')->index();
            $table->text('draft_acknowledgement_text')->nullable()->after('draft_attachments');
            $table->string('default_locale', 10)->default('en')->after('draft_review_due_date');
        });

        Schema::table('standard_operating_procedure_revisions', function (Blueprint $table): void {
            $table->string('document_type')->default('procedure')->after('status')->index();
            $table->boolean('acknowledgement_required')->default(false)->after('attachments')->index();
            $table->text('acknowledgement_text')->nullable()->after('acknowledgement_required');
            $table->string('locale', 10)->default('en')->after('acknowledgement_text');
        });

        Schema::table('employee_programs', function (Blueprint $table): void {
            $table->boolean('training_enabled')->default(false)->after('status')->index();
            $table->unsignedSmallInteger('passing_score')->default(80)->after('training_enabled');
            $table->unsignedSmallInteger('estimated_minutes')->nullable()->after('passing_score');
            $table->unsignedInteger('content_version')->default(0)->after('estimated_minutes');
            $table->string('default_locale', 10)->default('en')->after('content_version');
        });

        DB::table('employee_programs')
            ->where('status', 'published')
            ->update(['content_version' => 1]);

        Schema::table('employee_program_items', function (Blueprint $table): void {
            $table->boolean('required_for_completion')->default(false)->after('external_url')->index();
        });

        Schema::table('employees', function (Blueprint $table): void {
            $table->string('preferred_locale', 10)->default('en')->after('christy_location');
        });

        Schema::create('training_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_program_id')->constrained('employee_programs')->cascadeOnDelete();
            $table->text('prompt');
            $table->json('options');
            $table->text('explanation')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->string('locale', 10)->default('en');
            $table->timestamps();

            $table->index(['employee_program_id', 'sort_order']);
        });

        Schema::create('document_acknowledgements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('standard_operating_procedure_revision_id')
                ->constrained('standard_operating_procedure_revisions')
                ->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method')->default('authenticated');
            $table->string('signed_name');
            $table->text('acknowledgement_text');
            $table->string('locale', 10)->default('en');
            $table->string('evidence_hash', 64)->unique();
            $table->string('evidence_file_path')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at');
            $table->timestamps();

            $table->unique(
                ['standard_operating_procedure_revision_id', 'employee_id'],
                'document_acknowledgements_revision_employee_unique',
            );
        });

        Schema::create('training_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_program_id')->constrained('employee_programs')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('assigned')->index();
            $table->date('due_date')->nullable()->index();
            $table->unsignedInteger('program_version')->default(1);
            $table->json('content_snapshot');
            $table->unsignedSmallInteger('latest_score')->nullable();
            $table->string('locale', 10)->default('en');
            $table->timestamp('assigned_at');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable()->index();
            $table->text('completion_certification')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index(['employee_program_id', 'status']);
        });

        Schema::create('training_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('training_assignment_id')->constrained('training_assignments')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('questionnaire_snapshot');
            $table->json('answers');
            $table->unsignedSmallInteger('score');
            $table->boolean('passed')->index();
            $table->string('locale', 10)->default('en');
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_attempts');
        Schema::dropIfExists('training_assignments');
        Schema::dropIfExists('document_acknowledgements');
        Schema::dropIfExists('training_questions');

        Schema::table('employees', function (Blueprint $table): void {
            $table->dropColumn('preferred_locale');
        });

        Schema::table('employee_program_items', function (Blueprint $table): void {
            $table->dropColumn('required_for_completion');
        });

        Schema::table('employee_programs', function (Blueprint $table): void {
            $table->dropColumn([
                'training_enabled',
                'passing_score',
                'estimated_minutes',
                'content_version',
                'default_locale',
            ]);
        });

        Schema::table('standard_operating_procedure_revisions', function (Blueprint $table): void {
            $table->dropColumn([
                'document_type',
                'acknowledgement_required',
                'acknowledgement_text',
                'locale',
            ]);
        });

        Schema::table('standard_operating_procedures', function (Blueprint $table): void {
            $table->dropColumn([
                'document_type',
                'acknowledgement_required',
                'draft_acknowledgement_text',
                'default_locale',
            ]);
        });
    }
};
