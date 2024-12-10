<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create positions table
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name');
            $table->timestamps();
        });

        // Create pivot table
        Schema::create('employee_position', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('position_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        // Migrate existing position data
        DB::transaction(function () {
            // Create a position record for each unique existing position
            $uniquePositions = DB::table('employees')
                ->select('position')
                ->distinct()
                ->pluck('position');

            foreach ($uniquePositions as $position) {
                DB::table('positions')->insert([
                    'name' => strtolower($position),
                    'display_name' => $position,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Migrate existing employee positions
            $employees = DB::table('employees')->get();
            foreach ($employees as $employee) {
                $position = DB::table('positions')
                    ->where('name', strtolower($employee->position))
                    ->first();

                if ($position) {
                    DB::table('employee_position')->insert([
                        'employee_id' => $employee->id,
                        'position_id' => $position->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });

        // Remove the old position column
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }

    public function down(): void
    {
        // Add back the position column
        Schema::table('employees', function (Blueprint $table) {
            $table->string('position')->after('phone');
        });

        // Migrate data back
        DB::transaction(function () {
            $employees = DB::table('employees')->get();
            foreach ($employees as $employee) {
                $position = DB::table('employee_position')
                    ->join('positions', 'positions.id', '=', 'employee_position.position_id')
                    ->where('employee_position.employee_id', $employee->id)
                    ->first();

                if ($position) {
                    DB::table('employees')
                        ->where('id', $employee->id)
                        ->update(['position' => $position->name]);
                }
            }
        });

        // Drop the new tables
        Schema::dropIfExists('employee_position');
        Schema::dropIfExists('positions');
    }
};
