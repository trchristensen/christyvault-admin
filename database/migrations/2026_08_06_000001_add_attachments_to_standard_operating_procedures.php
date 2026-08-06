<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('standard_operating_procedures', function (Blueprint $table): void {
            $table->json('draft_attachments')->nullable()->after('draft_content');
        });

        Schema::table('standard_operating_procedure_revisions', function (Blueprint $table): void {
            $table->json('attachments')->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('standard_operating_procedure_revisions', function (Blueprint $table): void {
            $table->dropColumn('attachments');
        });

        Schema::table('standard_operating_procedures', function (Blueprint $table): void {
            $table->dropColumn('draft_attachments');
        });
    }
};
