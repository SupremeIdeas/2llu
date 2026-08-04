<?php

namespace App\Livewire;

use App\Models\BrandPartner;
use App\Models\BrandPartnerHandle;
use App\Models\SocialFollowHandle;
use App\Services\Social\SocialFollowService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Brand Partner Hunt (BUILD-6 §C.4) — the platform's own "Follow us" handles plus
 * a scroll-through of brand partners, each a one-time follow-to-earn claim. The
 * reward is a surprise (never shown before the follow) and the grant is
 * server-side + one-time (SocialFollowService); a claimed button is re-checked
 * server-side on every render, never trusted from the client.
 */
#[Layout('components.layouts.customer')]
class BrandHunt extends Component
{
    public ?string $flash = null;

    /** Claim a platform handle follow (self-confirmed tap → server grant). */
    public function followHandle(int $id, SocialFollowService $svc): void
    {
        $handle = SocialFollowHandle::active()->find($id);
        if (! $handle) {
            return;
        }
        $this->grant($svc->claim(Auth::user()->fresh(), $handle), $handle->handle_label);
    }

    /** Claim a brand-partner handle follow. */
    public function followBrandHandle(int $id, SocialFollowService $svc): void
    {
        $handle = BrandPartnerHandle::active()->find($id);
        if (! $handle) {
            return;
        }
        $this->grant($svc->claim(Auth::user()->fresh(), $handle), $handle->handle_label);
    }

    private function grant(array $result, string $label): void
    {
        if ($result['already']) {
            $this->flash = 'You already claimed this one.';

            return;
        }
        if ($result['earned'] > 0) {
            $this->flash = "🎉 +{$result['earned']} NaaraCredits for following {$label}!";
            $this->dispatch('nx-toast', variant: 'hero', type: 'success',
                title: 'Reward unlocked', message: "+{$result['earned']} NaaraCredits added to your balance.");
            $this->dispatch('reward-claimed');
        } else {
            $this->flash = "Thanks for following {$label}!";
        }
    }

    public function render()
    {
        $svc = app(SocialFollowService::class);
        $user = Auth::user();

        return view('livewire.brand-hunt', [
            'platformHandles' => SocialFollowHandle::active()->ordered()->get(),
            'brands' => BrandPartner::active()->ordered()->with(['handles' => fn ($q) => $q->active()->ordered()])->get(),
            'claimedHandles' => $svc->claimedHandleIds($user),
            'claimedBrandHandles' => $svc->claimedBrandHandleIds($user),
        ]);
    }
}
