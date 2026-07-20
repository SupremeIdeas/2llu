import './bootstrap';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

// Alpine is provided by Livewire 3's bundled build (do not start a second
// Alpine instance here — Livewire injects and starts it globally).

// --- GSAP premium motion (Module 27.5) ---------------------------------------
// Bundled into our own JS via npm (no CDN, CSP-safe). Everything respects
// prefers-reduced-motion and degrades to the IntersectionObserver reveals.
function initGsapCraft() {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    // 1. Apple-style hero media: slow parallax scale as you scroll away.
    const heroMedia = document.querySelector('[data-hero-media]');
    if (heroMedia) {
        gsap.fromTo(heroMedia, { scale: 1.06, yPercent: 0 }, {
            scale: 1, yPercent: 8, ease: 'none',
            scrollTrigger: { trigger: heroMedia, start: 'top top', end: 'bottom top', scrub: true },
        });
    }

    // 2. Dynamic content switch on scroll: the products section pins while its
    //    panels crossfade in sequence.
    const pin = document.querySelector('[data-products-pin]');
    if (pin) {
        const panels = pin.querySelectorAll('[data-product-panel]');
        if (panels.length > 1) {
            pin.classList.add('gsap-pin'); // overlap panels only once GSAP owns them
            gsap.set(panels, { autoAlpha: 0, y: 24 });
            gsap.set(panels[0], { autoAlpha: 1, y: 0 });

            const tl = gsap.timeline({
                scrollTrigger: {
                    trigger: pin,
                    start: 'top top',
                    end: () => '+=' + panels.length * 90 + '%',
                    pin: true,
                    scrub: 0.4,
                },
            });
            panels.forEach((panel, i) => {
                if (i === 0) return;
                tl.to(panels[i - 1], { autoAlpha: 0, y: -24, duration: 1 }, i)
                  .fromTo(panel, { autoAlpha: 0, y: 24 }, { autoAlpha: 1, y: 0, duration: 1 }, i + 0.15);
            });
        }
    }

    // 3. Timeline draw: the progress rail fills as steps pass.
    const timeline = document.querySelector('[data-timeline]');
    if (timeline) {
        const rail = timeline.querySelector('[data-timeline-rail]');
        if (rail) {
            gsap.fromTo(rail, { scaleY: 0 }, {
                scaleY: 1, transformOrigin: 'top center', ease: 'none',
                scrollTrigger: { trigger: timeline, start: 'top 70%', end: 'bottom 55%', scrub: true },
            });
        }
    }

    // 4. Stat count-up (hero stats row).
    document.querySelectorAll('[data-countup]').forEach((el) => {
        const target = parseFloat(el.dataset.countup);
        if (Number.isNaN(target)) return;
        const suffix = el.dataset.suffix || '';
        gsap.fromTo(el, { innerText: 0 }, {
            innerText: target, duration: 1.6, ease: 'power2.out', snap: { innerText: 1 },
            onUpdate() { el.textContent = Math.round(parseFloat(el.textContent || '0')) + suffix; },
            scrollTrigger: { trigger: el, start: 'top 88%', once: true },
        });
    });
}

document.addEventListener('DOMContentLoaded', initGsapCraft);
document.addEventListener('livewire:navigated', () => {
    ScrollTrigger.getAll().forEach((t) => t.kill());
    initGsapCraft();
});

// --- Marketing scroll-craft (Module 27) -------------------------------------
// Three tiny IntersectionObservers, no external libraries (CSP-safe):
//  1. [data-reveal]        -> .is-revealed  (text/image reveal on scroll)
//  2. [data-bg="..."]      -> swaps a scene class on the .mkt-bg wrapper
//                             (background colour change on scroll)
//  3. [data-hero-sentinel] -> shows the sticky CTA once the hero scrolls away
function initScrollCraft() {
    const revealables = document.querySelectorAll('[data-reveal]:not(.is-revealed)');
    if (revealables.length) {
        const io = new IntersectionObserver((entries) => {
            entries.forEach((e) => {
                if (e.isIntersecting) {
                    e.target.classList.add('is-revealed');
                    io.unobserve(e.target);
                }
            });
        }, { threshold: 0.15 });
        revealables.forEach((el) => io.observe(el));
    }

    const bgWrapper = document.querySelector('.mkt-bg');
    const scenes = document.querySelectorAll('[data-bg]');
    if (bgWrapper && scenes.length) {
        const sceneClasses = ['bg-navy-scene', 'bg-teal-scene'];
        const bgIo = new IntersectionObserver((entries) => {
            entries.forEach((e) => {
                if (e.isIntersecting) {
                    sceneClasses.forEach((c) => bgWrapper.classList.remove(c));
                    const scene = e.target.dataset.bg;
                    if (scene && scene !== 'light') bgWrapper.classList.add(`bg-${scene}-scene`);
                }
            });
        }, { threshold: 0.4 });
        scenes.forEach((el) => bgIo.observe(el));
    }

    const sentinel = document.querySelector('[data-hero-sentinel]');
    const stickyCta = document.querySelector('.mkt-sticky-cta');
    if (sentinel && stickyCta) {
        new IntersectionObserver((entries) => {
            entries.forEach((e) => stickyCta.classList.toggle('is-shown', !e.isIntersecting));
        }, { threshold: 0 }).observe(sentinel);
    }
}

document.addEventListener('DOMContentLoaded', initScrollCraft);
document.addEventListener('livewire:navigated', initScrollCraft);

// --- Premium WebGL login scene (lazy) ---------------------------------------
// Three.js is dynamic-imported ONLY when the auth panel canvas is present, so it
// never lands in the main bundle. Skipped for reduced-motion, where the CSS orb
// fallback stays. Cleaned up on SPA navigation.
let _loginSceneDestroy = null;
function initLoginScene() {
    if (_loginSceneDestroy) { _loginSceneDestroy(); _loginSceneDestroy = null; }
    const canvas = document.querySelector('[data-webgl="login"]');
    if (!canvas) return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    import('./webgl/login-scene.js')
        .then(({ mountLoginScene }) => {
            _loginSceneDestroy = mountLoginScene(canvas);
            canvas.classList.add('is-live'); // fades the canvas in over the fallback
        })
        .catch(() => { /* bundle/WebGL failure → CSS fallback stays */ });
}

document.addEventListener('DOMContentLoaded', initLoginScene);
document.addEventListener('livewire:navigated', initLoginScene);
