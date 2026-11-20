(() => {
    'use strict';
    const preference = window.matchMedia('(prefers-reduced-motion: reduce)');
    const toggle = document.querySelector('.motion-toggle');
    const scene = document.querySelector('.stadium-scene');
    let paused = preference.matches;
    let frame = 0;
    const resetScene = () => {
        cancelAnimationFrame(frame);
        scene?.style.removeProperty('--tilt-x');
        scene?.style.removeProperty('--tilt-y');
    };
    const updateMotion = () => {
        document.body.classList.toggle('motion-paused', paused || preference.matches);
        if (toggle) {
            toggle.hidden = preference.matches;
            toggle.setAttribute('aria-pressed', String(paused));
            toggle.textContent = paused ? 'Resume animation' : 'Pause animation';
        }
        resetScene();
    };
    toggle?.addEventListener('click', () => { paused = !paused; updateMotion(); });
    preference.addEventListener('change', () => { paused = preference.matches; updateMotion(); });
    document.body.classList.add('motion-ready');
    updateMotion();
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver(entries => {
            for (const entry of entries) {
                if (!entry.isIntersecting) continue;
                entry.target.classList.add('is-revealed');
                observer.unobserve(entry.target);
            }
        }, { threshold: .12 });
        document.querySelectorAll('[data-reveal]').forEach(element => observer.observe(element));
    }
    scene?.addEventListener('pointermove', event => {
        if (paused || preference.matches || event.pointerType !== 'mouse' || !window.matchMedia('(hover: hover) and (pointer: fine)').matches) return;
        const bounds = scene.getBoundingClientRect();
        const x = Math.max(-1, Math.min(1, (event.clientX - bounds.left) / bounds.width * 2 - 1));
        const y = Math.max(-1, Math.min(1, (event.clientY - bounds.top) / bounds.height * 2 - 1));
        cancelAnimationFrame(frame);
        frame = requestAnimationFrame(() => {
            scene.style.setProperty('--tilt-x', `${x * 3}deg`);
            scene.style.setProperty('--tilt-y', `${-y * 2}deg`);
        });
    });
    scene?.addEventListener('pointerleave', resetScene);
    document.addEventListener('visibilitychange', () => {
        document.body.classList.toggle('motion-paused', document.hidden || paused || preference.matches);
        if (document.hidden) resetScene();
    });
})();
