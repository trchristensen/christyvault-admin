<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('passwordless-login.table', 'passwordless_login_tokens');

        Schema::create($table, function (Blueprint $table) {
            $table->id();
            $table->string('authenticatable_type');
            $table->unsignedBigInteger('authenticatable_id');
            $table->string('token', 255);
            $table->string('guard')->nullable();
            $table->string('redirect_url')->nullable();
            $table->string('failure_url')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('use_count')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('last_used_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['authenticatable_type', 'authenticatable_id'], 'pl_tokens_auth_index');
            $table->index('token');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        $table = config('passwordless-login.table', 'passwordless_login_tokens');
        Schema::dropIfExists($table);
    }
};
