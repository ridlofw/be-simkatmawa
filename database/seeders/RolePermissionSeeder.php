<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeder untuk Role & Permission Spatie (PRD §2).
 * Menggunakan firstOrCreate dan syncPermissions agar aman dijalankan berulang kali.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cache Spatie agar role/permission baru langsung berlaku
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ========== BUAT PERMISSIONS ==========
        $permissions = [
            'submit-pengajuan',
            'edit-pengajuan',
            'delete-pengajuan',
            'approve-pengajuan',
            'reject-pengajuan',
            'view-antrean',
            'manage-users',
            'manage-settings',
            'access-trash',
            'force-delete'
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
        }

        // ========== BUAT ROLES ==========
        $superadmin = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $mahasiswa = Role::firstOrCreate(['name' => 'mahasiswa', 'guard_name' => 'web']);

        // ========== ASSIGN PERMISSIONS KE ROLES ==========
        // Gunakan syncPermissions agar relasi selalu sesuai dengan kode (menghapus yang lama jika perlu)
        $mahasiswa->syncPermissions([
            'submit-pengajuan',
            'edit-pengajuan',
            'delete-pengajuan',
        ]);

        $admin->syncPermissions([
            'approve-pengajuan',
            'reject-pengajuan',
            'view-antrean',
        ]);

        // Superadmin mendapat SEMUA permission
        $superadmin->syncPermissions(Permission::all());
    }
}
