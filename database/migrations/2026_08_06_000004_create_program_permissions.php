<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $permissionKey = $columnNames['permission_pivot_key'] ?? 'permission_id';
        $roleKey = $columnNames['role_pivot_key'] ?? 'role_id';
        $permissions = ['view programs', 'manage programs'];
        $now = now();

        DB::table($tableNames['permissions'])->insertOrIgnore(collect($permissions)->map(fn (string $permission): array => [
            'name' => $permission,
            'guard_name' => 'web',
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());

        DB::table($tableNames['roles'])->insertOrIgnore(collect([
            'admin',
            'super-admin',
            'manager',
        ])->map(fn (string $role): array => [
            'name' => $role,
            'guard_name' => 'web',
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());

        $permissionIds = DB::table($tableNames['permissions'])
            ->where('guard_name', 'web')
            ->whereIn('name', $permissions)
            ->pluck('id');
        $roleIds = DB::table($tableNames['roles'])
            ->where('guard_name', 'web')
            ->whereIn('name', ['admin', 'super-admin', 'manager'])
            ->pluck('id');

        DB::table($tableNames['role_has_permissions'])->insertOrIgnore(
            $roleIds
                ->crossJoin($permissionIds)
                ->map(fn (array $ids): array => [
                    $roleKey => $ids[0],
                    $permissionKey => $ids[1],
                ])
                ->all(),
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names');

        DB::table($tableNames['permissions'])
            ->where('guard_name', 'web')
            ->whereIn('name', ['view programs', 'manage programs'])
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
