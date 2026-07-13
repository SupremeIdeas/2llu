<?php

namespace App\Livewire\Admin;

use App\Models\User;
use App\Services\Staff\StaffService;
use App\Support\StaffScopes;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Admin → Staff (blueprint Section 27). Super-admin-only: create staff members
 * and grant/revoke granular scopes. The privilege-escalation guard lives in
 * StaffService; this component only surfaces scopes the actor may grant.
 */
#[Layout('components.layouts.admin')]
class Staff extends Component
{
    #[Validate('required|string|max:120')]
    public string $name = '';

    #[Validate('required|email|max:190|unique:users,email')]
    public string $email = '';

    #[Validate('required|string|min:8')]
    public string $password = '';

    /** Selected scopes for the create form. */
    public array $scopes = [];

    public ?string $saved = null;

    public function mount(StaffService $service): void
    {
        // Hard gate: only a super admin may even open this page.
        $service->assertCanManageStaff(Auth::user());
    }

    public function createStaff(StaffService $service): void
    {
        $this->validate();

        $service->createStaff(Auth::user(), [
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
        ], $this->scopes);

        $this->reset('name', 'email', 'password', 'scopes');
        $this->saved = 'Staff member created.';
    }

    public function toggleScope(int $staffId, string $scope, StaffService $service): void
    {
        $staff = User::findOrFail($staffId);
        $current = $staff->getPermissionNames()->all();

        $next = in_array($scope, $current, true)
            ? array_values(array_diff($current, [$scope]))
            : array_values(array_merge($current, [$scope]));

        $service->syncScopes(Auth::user(), $staff, $next);
        $this->saved = 'Scopes updated.';
    }

    public function revoke(int $staffId, StaffService $service): void
    {
        $service->revokeStaff(Auth::user(), User::findOrFail($staffId));
        $this->saved = 'Staff access revoked.';
    }

    public function render()
    {
        return view('livewire.admin.staff', [
            'staff' => User::role('staff')->orderBy('name')->get(),
            'grantable' => (new StaffService)->grantableScopes(Auth::user()),
            'labels' => StaffScopes::labels(),
        ]);
    }
}
