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
        $viewPlantPermission = 'view plant time off requests';
        $viewAllPermission = 'view all time off requests';
        $managePlantPermission = 'manage plant time off requests';
        $manageAllPermission = 'manage all time off requests';
        $now = now();

        DB::table($tableNames['permissions'])->insertOrIgnore(collect([
            $viewPlantPermission,
            $viewAllPermission,
            $managePlantPermission,
            $manageAllPermission,
        ])->map(fn (string $permission): array => [
            'name' => $permission,
            'guard_name' => 'web',
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());

        DB::table($tableNames['roles'])->insertOrIgnore(collect([
            'admin',
            'super-admin',
            'manager',
            'foreman',
        ])->map(fn (string $role): array => [
            'name' => $role,
            'guard_name' => 'web',
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());

        $permissionIds = DB::table($tableNames['permissions'])
            ->where('guard_name', 'web')
            ->whereIn('name', [
                $viewPlantPermission,
                $viewAllPermission,
                $managePlantPermission,
                $manageAllPermission,
            ])
            ->pluck('id', 'name');
        $allRoleIds = DB::table($tableNames['roles'])
            ->where('guard_name', 'web')
            ->whereIn('name', ['admin', 'super-admin'])
            ->pluck('id');
        $plantRoleIds = DB::table($tableNames['roles'])
            ->where('guard_name', 'web')
            ->whereIn('name', ['manager', 'foreman'])
            ->pluck('id');

        DB::table($tableNames['role_has_permissions'])->insertOrIgnore(
            $allRoleIds
                ->map(fn (int $roleId): array => [
                    $roleKey => $roleId,
                    $permissionKey => $permissionIds[$viewAllPermission],
                ])
                ->merge($allRoleIds->map(fn (int $roleId): array => [
                    $roleKey => $roleId,
                    $permissionKey => $permissionIds[$manageAllPermission],
                ]))
                ->merge($plantRoleIds->map(fn (int $roleId): array => [
                    $roleKey => $roleId,
                    $permissionKey => $permissionIds[$viewPlantPermission],
                ]))
                ->all(),
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table(config('permission.table_names.permissions', 'permissions'))
            ->where('guard_name', 'web')
            ->whereIn('name', [
                'view plant time off requests',
                'view all time off requests',
                'manage plant time off requests',
                'manage all time off requests',
            ])
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
