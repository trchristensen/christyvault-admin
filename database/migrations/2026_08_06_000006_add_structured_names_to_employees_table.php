<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->string('first_name')->nullable()->after('name');
            $table->string('middle_name')->nullable()->after('first_name');
            $table->string('last_name')->nullable()->after('middle_name');
            $table->string('suffix', 20)->nullable()->after('last_name');
            $table->index(['last_name', 'first_name']);
        });

        DB::table('employees')
            ->select(['id', 'name'])
            ->orderBy('id')
            ->chunkById(100, function ($employees): void {
                foreach ($employees as $employee) {
                    DB::table('employees')
                        ->where('id', $employee->id)
                        ->update($this->splitName($employee->name));
                }
            });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->dropIndex(['last_name', 'first_name']);
            $table->dropColumn(['first_name', 'middle_name', 'last_name', 'suffix']);
        });
    }

    /**
     * @return array{first_name: ?string, middle_name: ?string, last_name: ?string, suffix: ?string}
     */
    private function splitName(?string $name): array
    {
        $normalized = trim((string) preg_replace('/\s+/', ' ', trim((string) $name)));
        $parts = $normalized === '' ? [] : explode(' ', $normalized);
        $suffix = null;

        if (count($parts) > 1 && in_array(strtolower((string) end($parts)), [
            'jr', 'jr.', 'sr', 'sr.', 'ii', 'iii', 'iv', 'v',
        ], true)) {
            $suffix = array_pop($parts);
        }

        $firstName = array_shift($parts);
        $lastName = $parts === [] ? null : array_pop($parts);

        return [
            'first_name' => $firstName ?: null,
            'middle_name' => $parts === [] ? null : implode(' ', $parts),
            'last_name' => $lastName ?: null,
            'suffix' => $suffix,
        ];
    }
};
