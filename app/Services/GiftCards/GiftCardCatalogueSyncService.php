<?php

namespace App\Services\GiftCards;

use App\Models\GiftCardProduct;
use App\Support\SyncStatus;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Syncs BOTH gift-card providers into one catalogue (Naara Gift). Mirrors the
 * eSIM CatalogueSyncService and inherits the same queue-restart-on-key-save fix
 * (shared Zendit key; Reloadly is key-gated too). After upserting, it recomputes
 * `is_primary` so the storefront buys from Reloadly wherever both carry a brand,
 * and falls back to Zendit only where Reloadly doesn't (admin's chosen routing).
 */
class GiftCardCatalogueSyncService
{
    /** @var array<string, class-string<GiftCardProviderInterface>> */
    private const PROVIDERS = [
        'reloadly' => ReloadlyGiftCardService::class,
        'zendit' => ZenditVoucherService::class,
        // NAARA-BUILD-18 — registered gift adapters (enabled=false until onboarded).
        'bitrefill' => \App\Services\GiftCards\BitrefillService::class,
        'tillo' => \App\Services\GiftCards\TilloService::class, // placeholder tier
    ];

    public function provider(string $key): GiftCardProviderInterface
    {
        // Container binding first (tests can swap a fake), else the real service.
        if (app()->bound('giftcard.'.$key)) {
            return app('giftcard.'.$key);
        }
        $class = self::PROVIDERS[$key] ?? throw new \InvalidArgumentException("Unknown gift-card provider [$key].");

        return app($class);
    }

    /** Sync one provider; returns the number of products upserted. */
    public function sync(string $provider): int
    {
        $svc = $this->provider($provider);
        if (! $svc->available()) {
            SyncStatus::record('giftcards:'.$provider, false, 0, 'Provider keys not configured.');

            return 0;
        }

        try {
            $count = 0;
            foreach ($svc->getCatalogue() as $p) {
                if (($p['provider_product_id'] ?? '') === '') {
                    continue;
                }
                GiftCardProduct::updateOrCreate(
                    ['provider' => $p['provider'], 'provider_product_id' => $p['provider_product_id']],
                    collect($p)->only([
                        'brand_key', 'brand_name', 'country', 'currency', 'denomination_type',
                        'fixed_denominations', 'min_amount', 'max_amount', 'logo_url', 'brand_color',
                        'category', 'required_fields', 'redeem_instruction', 'cost_meta', 'provider_enabled',
                        'priceable',
                    ])->all(),
                );
                $count++;
            }

            $this->recomputePrimary();
            SyncStatus::record('giftcards:'.$provider, true, $count);

            return $count;
        } catch (Throwable $e) {
            SyncStatus::record('giftcards:'.$provider, false, null, $e->getMessage());

            return 0;
        }
    }

    /** Reloadly is primary per brand+country; Zendit is primary only where Reloadly is absent. */
    public function recomputePrimary(): void
    {
        GiftCardProduct::query()->update(['is_primary' => false]);
        GiftCardProduct::where('provider', 'reloadly')->update(['is_primary' => true]);

        GiftCardProduct::where('provider', 'zendit')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('gift_card_products as r')
                    ->whereColumn('r.brand_key', 'gift_card_products.brand_key')
                    ->where('r.provider', 'reloadly')
                    ->where(function ($w) {
                        $w->whereColumn('r.country', 'gift_card_products.country')
                            ->orWhere(function ($n) {
                                $n->whereNull('r.country')->whereNull('gift_card_products.country');
                            });
                    });
            })
            ->update(['is_primary' => true]);
    }
}
