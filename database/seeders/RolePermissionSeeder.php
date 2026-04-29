<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeder untuk Role & Permission Spatie (PRD §2).
 * Menggantikan kolom `role` enum di tabel users.
 * Role disimpan di database agar dinamis dan fleksibel.
 *
 * Guard: 'web' (default Laravel, Sanctum compatible).
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cache Spatie agar role/permission baru langsung berlaku
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ========== BUAT PERMISSIONS DULU (sebelum assign ke role) ==========
        // Mahasiswa permissions
        $submitPengajuan = Permission::create(['name' => 'submit-pengajuan', 'guard_name' => 'web']);
        $editPengajuan = Permission::create(['name' => 'edit-pengajuan', 'guard_name' => 'web']);
        $deletePengajuan = Permission::create(['name' => 'delete-pengajuan', 'guard_name' => 'web']);

        // Admin permissions
        $approvePengajuan = Permission::create(['name' => 'approve-pengajuan', 'guard_name' => 'web']);
        $rejectPengajuan = Permission::create(['name' => 'reject-pengajuan', 'guard_name' => 'web']);
        $viewAntrean = Permission::create(['name' => 'view-antrean', 'guard_name' => 'web']);

        // Superadmin permissions
        $manageUsers = Permission::create(['name' => 'manage-users', 'guard_name' => 'web']);
        $manageSettings = Permission::create(['name' => 'manage-settings', 'guard_name' => 'web']);
        $accessTrash = Permission::create(['name' => 'access-trash', 'guard_name' => 'web']);
        $forceDelete = Permission::create(['name' => 'force-delete', 'guard_name' => 'web']);

        // ========== BUAT ROLES ==========
        $superadmin = Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
        $admin = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $mahasiswa = Role::create(['name' => 'mahasiswa', 'guard_name' => 'web']);

        // ========== ASSIGN PERMISSIONS KE ROLES ==========
        $mahasiswa->givePermissionTo([
            $submitPengajuan,
            $editPengajuan,
            $deletePengajuan,
        ]);

        $admin->givePermissionTo([
            $approvePengajuan,
            $rejectPengajuan,
            $viewAntrean,
        ]);

        // Superadmin mendapat SEMUA permission
        $superadmin->givePermissionTo(Permission::all());
    }
}
