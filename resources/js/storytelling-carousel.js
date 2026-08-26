/**
 * Storytelling Carousel (BLUEPRINT-batch1-sections §3) — the single reusable
 * Apple-style section pattern, shared across the product lines and the About
 * page. Built once here as an Alpine component + one shared nav-visibility store.
 *
 * Behaviour:
 *  - Auto-advance on a per-slide timer (admin-set read-seconds; sensible default
 *    computed server-side). Manual swipe/tap resets the timer.
 *  - Image layer crossfades on every change. The TEXT block slides in the swipe
 *    direction on a manual gesture, and gently crossfades on auto-advance (no
 *    gesture to mirror).
 *  - Registers its section with ONE shared IntersectionObserver so only the
 *    in-view section's bottom nav is active (mutual exclusion — §6 extends this
 *    to also coordinate with the global nav).
 *  - Fully reduced-motion aware (CSS disables the slide/fade animations) and
 *    pauses while the tab is hidden.
 */
export function registerStorytellingCarousel() {
    document.addEventListener('alpine:init', () => {
        const Alpine = window.Alpine;
        if (!Alpine) return;

        // One shared store: which section-nav is currently active, driven by a
        // single reused IntersectionObserver (lightweight — not N observers).
        if (!Alpine.store('sectionNav')) {
            Alpine.store('sectionNav', {
                active: null,       // section key whose bottom nav should show
                atBottom: false,    // near page end (global nav reveal — §6)
                _io: null,
                isActive(key) {
                    return this.active === key;
                },
                observe(el, key) {
                    if (!el) return;
                    el.__navKey = key;
                    if (!this._io) {
                        this._io = new IntersectionObserver((entries) => {
                            entries.forEach((e) => {
                                if (e.isIntersecting && e.intersectionRatio >= 0.5) {
                                    this.active = e.target.__navKey;
                                }
                            });
                        }, { threshold: [0.5] });
                    }
                    this._io.observe(el);
                },
            });
        }

        Alpine.data('storytellingCarousel', ({ key = '', count = 0, readSeconds = [], slides = [] } = {}) => ({
            index: 0,
            playing: true,
            dir: 0,          // -1 / +1 — text slide direction on a gesture
            gesture: false,  // was the last change a manual swipe?
            timer: null,
            touchX: null,
            reduced: false,
            key,
            count,
            slides,          // [{eyebrow,title,body,ctaLabel,ctaUrl,hasModal}] for the single text block

            get cur() {
                return this.slides[this.index] || {};
            },

            init() {
                this.reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                this.start();
                this.$store.sectionNav?.observe(this.$root, key);
                // Pause the timer while the tab is backgrounded.
                document.addEventListener('visibilitychange', () => {
                    document.hidden ? this.clear() : this.start();
                });
            },

            isActive(i) {
                return this.index === i;
            },

            start() {
                this.clear();
                if (!this.playing || this.count < 2) return;
                const secs = (readSeconds[this.index] || 6) * 1000;
                this.timer = setTimeout(() => {
                    this.dir = 0;
                    this.gesture = false;
                    this.change((this.index + 1) % this.count);
                }, secs);
            },

            clear() {
                if (this.timer) { clearTimeout(this.timer); this.timer = null; }
            },

            change(i) {
                if (i === this.index) { this.start(); return; }
                this.index = i;
                this.$nextTick(() => this.animateText());
                this.start();
            },

            goTo(i) {
                if (i === this.index) return;
                this.dir = i > this.index ? 1 : -1;
                this.gesture = false; // dot click = fade, not a directional slide
                this.change(i);
            },

            toggle() {
                this.playing = !this.playing;
                this.playing ? this.start() : this.clear();
            },

            onTouchStart(e) {
                this.touchX = e.changedTouches[0].clientX;
            },

            onTouchEnd(e) {
                if (this.touchX == null) return;
                const dx = e.changedTouches[0].clientX - this.touchX;
                this.touchX = null;
                if (Math.abs(dx) < 40) return;
                this.gesture = true;
                this.dir = dx < 0 ? 1 : -1;                 // swipe left → next
                this.change((this.index + this.dir + this.count) % this.count);
            },

            animateText() {
                const el = this.$refs.text;
                if (!el || this.reduced) return;
                el.classList.remove('nx-tin-left', 'nx-tin-right', 'nx-tin-fade');
                void el.offsetWidth; // reflow so the animation re-fires
                if (this.gesture) {
                    el.classList.add(this.dir > 0 ? 'nx-tin-right' : 'nx-tin-left');
                } else {
                    el.classList.add('nx-tin-fade');
                }
            },

            // Modal name for the currently-showing slide (FAB target).
            modalName() {
                return `${this.key}-${this.index}`;
            },
        }));
    });
}
