<?php

namespace App\Livewire\Admin;

use App\Models\User;
use App\Services\Account\AccountService;
use App\Support\Auditor;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Admin → Users (blueprint Section 27, owner request). super_admin/admin can
 * see every registered user, search/filter them, inspect a profile (wallet,
 * orders, roles, KYC), and activate / deactivate an account. Deleting a user is
 * NOT done here — that stays a super-admin-approved lifecycle action (S26), so
 * staff can manage almost anything EXCEPT deleting users.
 */
#[Layout('components.layouts.admin')]
class Users extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filter = 'all'; // all | active | deactivated | staff | merchants

    public ?int $viewingId = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 404);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilter(): void
    {
        $this->resetPage();
    }

    public function view(int $id): void
    {
        $this->viewingId = $this->viewingId === $id ? null : $id;
    }

    /** Deactivate / reactivate an account (never delete — that's S26). */
    public function toggleActive(int $id, AccountService $accounts): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $user = User::findOrFail($id);

        // Never let an admin lock themselves out, and never touch a super admin
        // unless you are one.
        if ($user->id === Auth::id()) {
            $this->dispatch('nx-toast', type: 'error', message: 'You can’t deactivate your own account here.');

            return;
        }
        if ($user->hasRole('super_admin') && ! Auth::user()->hasRole('super_admin')) {
            $this->dispatch('nx-toast', type: 'error', message: 'Only a super admin can manage a super admin.');

            return;
        }

        if ($user->isDeactivated()) {
            $accounts->reactivate($user);
            $msg = $user->name.' reactivated.';
        } else {
            $accounts->deactivate($user);
            $msg = $user->name.' deactivated.';
        }
        Auditor::log('admin.user_toggled', 'User', $user->id, ['active' => ! $user->fresh()->isDeactivated()]);
        $this->dispatch('nx-toast', type: 'success', message: $msg);
    }

    public function render()
    {
        $users = User::query()
            ->when($this->search !== '', function ($q) {
                $t = '%'.$this->search.'%';
                $q->where(fn ($w) => $w->where('name', 'like', $t)->orWhere('email', 'like', $t));
            })
            ->when($this->filter === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->filter === 'deactivated', fn ($q) => $q->where('is_active', false))
            ->when($this->filter === 'merchants', fn ($q) => $q->whereNotNull('merchant_id')
                ->orWhereHas('merchantAccount'))
            ->when($this->filter === 'staff', fn ($q) => $q->whereHas('roles', fn ($r) => $r->whereIn('name', ['staff', 'admin', 'super_admin'])))
            ->latest('id')
            ->paginate(15);

        $viewing = $this->viewingId ? User::with('wallet')->find($this->viewingId) : null;

        return view('livewire.admin.users', [
            'users' => $users,
            'viewing' => $viewing,
            'totals' => [
                'all' => User::count(),
                'active' => User::where('is_active', true)->count(),
                'deactivated' => User::where('is_active', false)->count(),
            ],
        ]);
    }
}
