import './bootstrap';

// Alpine is provided by Livewire 3's bundled build (do not start a second
// Alpine instance here — Livewire injects and starts it globally).

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
