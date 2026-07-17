{{-- Brand preloader (Module 26). A full-screen brand-coloured loader shown until
     the page finishes loading, then faded out. Admin-toggleable; renders nothing
     when off. Self-removing on `load` with a hard timeout fallback so it can
     never trap the page. Respects reduced-motion (no spin). --}}
@if (\App\Support\BrandSettings::preloaderEnabled())
    <div id="nx-preloader" role="status" aria-label="Loading"
         style="position:fixed;inset:0;z-index:100;display:flex;align-items:center;justify-content:center;background:rgb(var(--brand-navy));transition:opacity .4s ease;">
        <span style="display:block;width:3rem;height:3rem;border-radius:9999px;border:3px solid rgba(255,255,255,.18);border-top-color:rgb(var(--brand-accent));animation:nx-preload-spin .8s linear infinite;"></span>
        <style>
            @keyframes nx-preload-spin { to { transform: rotate(360deg); } }
            @media (prefers-reduced-motion: reduce) { #nx-preloader span { animation: none; } }
            #nx-preloader.is-done { opacity: 0; pointer-events: none; }
        </style>
    </div>
    <script>
        (function () {
            var el = document.getElementById('nx-preloader');
            if (!el) return;
            var hide = function () {
                el.classList.add('is-done');
                setTimeout(function () { el.remove(); }, 500);
            };
            window.addEventListener('load', function () { setTimeout(hide, 150); });
            setTimeout(hide, 4000); // hard fallback — never trap the page
        })();
    </script>
@endif
