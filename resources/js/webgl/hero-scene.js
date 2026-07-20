// NaaraSim — marketing page WebGL heroes.
//
// One lazy module for every page hero, dispatched by a `variant` so Three.js is
// downloaded once and SHARED (Vite folds it into the same chunk the login scene
// uses). Bundled via Vite — never a CDN — so it stays CSP-safe. Each mounter
// returns a destroy() that disposes everything, and every scene installs its own
// IntersectionObserver so it stops rendering the moment it scrolls off-screen
// (one hero per page, and it idles when you're not looking at it). The caller
// guards prefers-reduced-motion; the CSS fallback stays put if WebGL is absent.

import * as THREE from 'three';

const TEAL = 0x0a6e6e;
const TEAL_LIGHT = 0x2dd4bf;
const GOLD = 0xd4a017;

/** Shared bootstrap: renderer + rAF loop that pauses off-screen and when hidden. */
function createStage(canvas) {
    let renderer;
    try {
        renderer = new THREE.WebGLRenderer({ canvas, alpha: true, antialias: true, powerPreference: 'low-power' });
    } catch (e) {
        return null; // no WebGL — the CSS fallback stays visible
    }
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));

    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(45, 1, 0.1, 100);

    function resize() {
        const el = canvas.parentElement || canvas;
        const w = canvas.clientWidth || el.clientWidth;
        const h = canvas.clientHeight || el.clientHeight;
        if (!w || !h) return;
        renderer.setSize(w, h, false);
        camera.aspect = w / h;
        camera.updateProjectionMatrix();
    }
    const ro = new ResizeObserver(resize);
    ro.observe(canvas.parentElement || canvas);
    resize();

    let raf = 0;
    let onScreen = true;
    let update = () => {};
    const clock = new THREE.Clock();
    function loop() {
        raf = 0;
        if (document.hidden || !onScreen) return;
        update(clock.getElapsedTime(), clock.getDelta());
        renderer.render(scene, camera);
        raf = requestAnimationFrame(loop);
    }
    function kick() {
        if (!raf && !document.hidden && onScreen) { clock.getDelta(); raf = requestAnimationFrame(loop); }
    }
    const io = new IntersectionObserver((entries) => {
        onScreen = entries[0].isIntersecting;
        onScreen ? kick() : (cancelAnimationFrame(raf), raf = 0);
    }, { threshold: 0.01 });
    io.observe(canvas);
    const onVisibility = () => (document.hidden ? (cancelAnimationFrame(raf), raf = 0) : kick());
    document.addEventListener('visibilitychange', onVisibility);

    return {
        scene, camera, renderer,
        setUpdate(fn) { update = fn; kick(); },
        teardown(disposeScene) {
            cancelAnimationFrame(raf);
            io.disconnect();
            ro.disconnect();
            document.removeEventListener('visibilitychange', onVisibility);
            disposeScene();
            renderer.dispose();
        },
    };
}

/** Gentle pointer parallax shared by the scenes. */
function pointerParallax(strength = 0.5) {
    const target = { x: 0, y: 0 };
    const onPointer = (e) => {
        const t = e.touches ? e.touches[0] : e;
        target.x = (t.clientX / window.innerWidth - 0.5) * strength;
        target.y = (t.clientY / window.innerHeight - 0.5) * strength;
    };
    window.addEventListener('pointermove', onPointer, { passive: true });
    return { target, dispose: () => window.removeEventListener('pointermove', onPointer) };
}

// ── Variant: connected planet (home hero) ───────────────────────────────────
// The login planet, scaled up for a full-bleed hero: faceted teal world, gold
// connectivity lattice, a field of orbiting nodes, animated gold connection
// arcs that pulse between points, and a faint starfield for depth.
function mountPlanet(canvas) {
    const stage = createStage(canvas);
    if (!stage) return () => {};
    const { scene, camera } = stage;
    camera.position.set(0, 0, 7.4);

    const world = new THREE.Group();
    world.position.y = -0.85;
    scene.add(world);

    const planetGeo = new THREE.IcosahedronGeometry(2.1, 3);
    const planet = new THREE.Mesh(
        planetGeo,
        new THREE.MeshStandardMaterial({ color: TEAL, flatShading: true, roughness: 0.5, metalness: 0.25 }),
    );
    world.add(planet);

    const latticeGeo = new THREE.EdgesGeometry(new THREE.IcosahedronGeometry(2.14, 3));
    const lattice = new THREE.LineSegments(
        latticeGeo,
        new THREE.LineBasicMaterial({ color: GOLD, transparent: true, opacity: 0.3 }),
    );
    world.add(lattice);

    // Orbiting connectivity nodes.
    const NODES = 320;
    const nodePos = new Float32Array(NODES * 3);
    for (let i = 0; i < NODES; i++) {
        const a = Math.random() * Math.PI * 2;
        const r = 3 + Math.random() * 2.2;
        nodePos[i * 3] = Math.cos(a) * r;
        nodePos[i * 3 + 1] = (Math.random() - 0.5) * 1.8;
        nodePos[i * 3 + 2] = Math.sin(a) * r;
    }
    const nodeGeo = new THREE.BufferGeometry();
    nodeGeo.setAttribute('position', new THREE.BufferAttribute(nodePos, 3));
    const nodes = new THREE.Points(
        nodeGeo,
        new THREE.PointsMaterial({ color: TEAL_LIGHT, size: 0.045, transparent: true, opacity: 0.9, depthWrite: false }),
    );
    nodes.rotation.z = 0.4;
    scene.add(nodes);

    // Gold connection arcs that "route" over the planet surface.
    const arcs = new THREE.Group();
    world.add(arcs);
    const arcMats = [];
    const surfacePoint = () => new THREE.Vector3().setFromSphericalCoords(
        2.12, Math.acos(2 * Math.random() - 1), Math.random() * Math.PI * 2,
    );
    for (let i = 0; i < 7; i++) {
        const a = surfacePoint();
        const b = surfacePoint();
        const mid = a.clone().add(b).multiplyScalar(0.5).setLength(2.9);
        const curve = new THREE.QuadraticBezierCurve3(a, mid, b);
        const geo = new THREE.BufferGeometry().setFromPoints(curve.getPoints(40));
        const mat = new THREE.LineBasicMaterial({ color: GOLD, transparent: true, opacity: 0.5 });
        arcMats.push({ mat, phase: Math.random() * Math.PI * 2 });
        arcs.add(new THREE.Line(geo, mat));
    }

    // Faint starfield for depth.
    const STARS = 220;
    const starPos = new Float32Array(STARS * 3);
    for (let i = 0; i < STARS; i++) {
        starPos[i * 3] = (Math.random() - 0.5) * 26;
        starPos[i * 3 + 1] = (Math.random() - 0.5) * 16;
        starPos[i * 3 + 2] = -6 - Math.random() * 10;
    }
    const starGeo = new THREE.BufferGeometry();
    starGeo.setAttribute('position', new THREE.BufferAttribute(starPos, 3));
    const stars = new THREE.Points(
        starGeo,
        new THREE.PointsMaterial({ color: 0xffffff, size: 0.04, transparent: true, opacity: 0.35, depthWrite: false }),
    );
    scene.add(stars);

    scene.add(new THREE.AmbientLight(0x0d1b2a, 1.1));
    const key = new THREE.DirectionalLight(TEAL_LIGHT, 2.4);
    key.position.set(-3, 2, 4);
    scene.add(key);
    const rim = new THREE.PointLight(GOLD, 9, 22);
    rim.position.set(4, -1, 2);
    scene.add(rim);

    const { target, dispose: disposePointer } = pointerParallax(0.5);

    stage.setUpdate((t) => {
        world.rotation.y = t * 0.1;
        world.rotation.x = Math.sin(t * 0.18) * 0.1;
        nodes.rotation.y = -t * 0.05;
        arcMats.forEach(({ mat, phase }) => { mat.opacity = 0.25 + 0.35 * (0.5 + 0.5 * Math.sin(t * 1.6 + phase)); });
        camera.position.x += (target.x * 1.3 - camera.position.x) * 0.04;
        camera.position.y += (-target.y * 1.3 - camera.position.y) * 0.04;
        camera.lookAt(0, -0.85, 0);
    });

    return () => stage.teardown(() => {
        disposePointer();
        planetGeo.dispose();
        planet.material.dispose();
        latticeGeo.dispose();
        lattice.material.dispose();
        nodeGeo.dispose();
        nodes.material.dispose();
        starGeo.dispose();
        stars.material.dispose();
        arcs.children.forEach((l) => { l.geometry.dispose(); l.material.dispose(); });
    });
}

const VARIANTS = { planet: mountPlanet };

export function mountHeroScene(canvas, variant) {
    const mounter = VARIANTS[variant];
    return mounter ? mounter(canvas) : () => {};
}
