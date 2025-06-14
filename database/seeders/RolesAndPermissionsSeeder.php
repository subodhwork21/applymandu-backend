<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {
        // Clear cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 🌟 Define permissions
        $permissions = [
            'register_as_jobseeker',
            'view_employer_dashboard',
            'register_as_employer',
            'view_jobseeker_dashboard',
            'view_jobs',
            'apply_jobs',
            // Admin permissions
            'manage_users',
            'manage_roles',
            'manage_permissions',
            'view_admin_dashboard',
            'manage_jobs',
            'manage_applications',
            'manage_system_settings',
        ];

        // Create permissions with 'api' guard
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'api']
            );
        }

        // 🌟 Create roles with 'api' guard
        $employerRole = Role::firstOrCreate(
            ['name' => 'employer', 'guard_name' => 'api']
        );

        $jobseekerRole = Role::firstOrCreate(
            ['name' => 'jobseeker', 'guard_name' => 'api']
        );

        $adminRole = Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'api']
        );

        // 🌟 Assign permissions to roles
        // Only sync permissions if the role was just created (doesn't have any permissions yet)
        if ($employerRole->permissions()->count() === 0) {
            // Employer permissions
            $employerRole->syncPermissions([
                'register_as_jobseeker',
                'view_employer_dashboard',
            ]);
        }

        if ($jobseekerRole->permissions()->count() === 0) {
            // Jobseeker permissions
            $jobseekerRole->syncPermissions([
                'register_as_employer',
                'view_jobseeker_dashboard',
                'view_jobs',
                'apply_jobs',
            ]);
        }

        if ($adminRole->permissions()->count() === 0) {
            // Admin permissions - give admin all permissions
            $adminRole->syncPermissions($permissions);
        }

        $this->command->info('Roles and permissions seeded successfully!');
    }
}
