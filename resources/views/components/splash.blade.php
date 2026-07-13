@php
    $splash = \App\Support\SplashSettings::current();
@endphp
@if ($splash['enabled'])
    {{-- Opening splash (blueprint Section 24). The correct theme paints on the
         first frame via the pre-paint script in <head>, so the background never
         flashes the wrong colour. Logos are chosen for the active theme; the
         overlay fades out after a minimum display time and (optionally) shows
         once per session. --}}
    <div x-data="{
            visible: true,
            dark: document.documentElement.classList.contains('dark'),
            productLogo: '',
            brandLogo: '',
            init() {
                if ({{ $splash['show_once_per_session'] ? 'true' : 'false' }} && sessionStorage.getItem('naara_splash_shown')) {
                    this.visible = false;
                    return;
                }
                this.productLogo = this.dark ? @js($splash['product_logo_dark']) : @js($splash['product_logo_light']);
                this.brandLogo = this.dark ? @js($splash['brand_logo_dark']) : @js($splash['brand_logo_light']);
                sessionStorage.setItem('naara_splash_shown', '1');
                setTimeout(() => { this.visible = false }, {{ $splash['duration_ms'] }});
            }
         }"
         x-show="visible"
         x-transition:leave="transition-opacity ease-out duration-400"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         x-cloak
         class="fixed inset-0 z-[9999] flex flex-col items-center justify-center bg-[#F8F9FA] dark:bg-navy"
         role="status" aria-label="{{ $splash['product_name'] }} loading">
        <div class="flex flex-col items-center">
            <template x-if="productLogo">
                <img :src="productLogo" alt="{{ $splash['product_name'] }}" class="mb-3 h-16" fetchpriority="high">
            </template>
            <span class="text-2xl font-bold text-primary-dark dark:text-primary">{{ $splash['product_name'] }}</span>
        </div>
        <div class="absolute bottom-10 flex items-center gap-2 opacity-70">
            <span class="text-sm text-slate-500 dark:text-slate-400">{{ $splash['brand_tagline'] }}</span>
            <template x-if="brandLogo">
                <img :src="brandLogo" alt="brand" class="h-5">
            </template>
        </div>
    </div>
@endif
