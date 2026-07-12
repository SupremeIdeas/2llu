<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Seed the four platform roles (blueprint Section 3.1).
     *
     * super_admin bypasses every gate (Gate::before in AppServiceProvider);
     * admin + super_admin may view Horizon; staff/user cannot.
     */
    public function run(): void
    {
        foreach (['super_admin', 'admin', 'staff', 'user'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }
}
