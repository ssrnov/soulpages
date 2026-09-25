/**
 * Loopr Performance Mode & Device Detection
 * Scores client capabilities, monitors FPS, and pauses out-of-viewport animations.
 */
(function() {
    // 1. Initial Defaults (High capability tier)
    const config = {
        tier: 'high',
        maxParticles: 15,
        enableBlur: true,
        enableShadows: true,
        animationScale: 1.0,
        reducedMotion: false
    };

    // 2. Capabilities Check
    try {
        // Prefers reduced motion
        const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (prefersReduced) {
            config.reducedMotion = true;
            config.tier = 'low';
            config.maxParticles = 0;
            config.enableBlur = false;
            config.enableShadows = false;
            config.animationScale = 0.0;
        } else {
            // Hardware metrics check
            const cores = navigator.hardwareConcurrency || 4;
            const memory = navigator.deviceMemory || 4; // RAM in GB (approx)
            
            // Check network status if available
            let slowNetwork = false;
            if (navigator.connection) {
                const conn = navigator.connection;
                if (conn.saveData || conn.effectiveType === 'slow-2g' || conn.effectiveType === '2g' || conn.effectiveType === '3g') {
                    slowNetwork = true;
                }
            }

            // Scoring system
            let score = 0;
            if (cores >= 8) score += 2;
            else if (cores >= 4) score += 1;

            if (memory >= 8) score += 2;
            else if (memory >= 4) score += 1;

            if (slowNetwork) score -= 2;

            // Screen resolution / viewport size can affect rendering weight
            if (window.innerWidth * window.innerHeight > 2000000) {
                // Large monitors are pixel-heavy
                score -= 1;
            }

            // Assign Tier
            if (score <= 0) {
                config.tier = 'low';
                config.maxParticles = 3;
                config.enableBlur = false;
                config.enableShadows = false;
                config.animationScale = 0.5;
            } else if (score <= 2) {
                config.tier = 'medium';
                config.maxParticles = 8;
                config.enableBlur = true; 
                config.enableShadows = false;
                config.animationScale = 0.75;
            } else {
                config.tier = 'high';
                config.maxParticles = 15;
                config.enableBlur = true;
                config.enableShadows = true;
                config.animationScale = 1.0;
            }
        }
    } catch (e) {
        console.warn("Performance detection failed, falling back to high.", e);
    }

    // 3. Export global config
    window.LOOPR_PERF = config;

    // Apply performance styles to document root immediately
    const applyStyles = () => {
        const root = document.documentElement;
        root.setAttribute('data-perf-tier', config.tier);
        
        if (config.reducedMotion) {
            root.classList.add('reduced-motion');
        } else {
            root.classList.remove('reduced-motion');
        }
        if (!config.enableBlur) {
            root.classList.add('disable-blur');
        } else {
            root.classList.remove('disable-blur');
        }
        if (!config.enableShadows) {
            root.classList.add('disable-shadows');
        } else {
            root.classList.remove('disable-shadows');
        }
    };

    // Downgrade performance when low FPS is detected
    function downgradePerformance() {
        const oldTier = config.tier;
        if (config.tier === 'high') {
            config.tier = 'medium';
            config.maxParticles = 8;
            config.enableShadows = false;
            config.animationScale = 0.75;
        } else if (config.tier === 'medium') {
            config.tier = 'low';
            config.maxParticles = 3;
            config.enableBlur = false;
            config.animationScale = 0.5;
        }
        
        applyStyles();
        console.warn(`[Loopr Performance] Low FPS detected! Downgraded tier from ${oldTier.toUpperCase()} to ${config.tier.toUpperCase()}`);
        
        // Dispatch custom event for dynamically loaded particles/etc to adjust
        window.dispatchEvent(new CustomEvent('loopr-perf-downgrade', { detail: config }));
    }

    // FPS Monitoring
    let lastTime = performance.now();
    let frameCount = 0;
    let fpsHistory = [];
    
    function monitorFPS() {
        if (config.tier === 'low') return;
        
        function check(time) {
            frameCount++;
            const delta = time - lastTime;
            if (delta >= 1000) {
                const fps = Math.round((frameCount * 1000) / delta);
                fpsHistory.push(fps);
                if (fpsHistory.length > 5) fpsHistory.shift();
                
                // If last 3 readings are below 40 FPS, trigger a downgrade
                if (fpsHistory.length >= 3 && fpsHistory.every(f => f < 40)) {
                    downgradePerformance();
                    fpsHistory = []; // Reset history after downgrade
                }
                
                frameCount = 0;
                lastTime = time;
            }
            if (config.tier !== 'low') {
                requestAnimationFrame(check);
            }
        }
        requestAnimationFrame(check);
    }

    // Intersection Observer to pause out-of-viewport elements (slides, animations, videos)
    function setupViewportObserver() {
        if (typeof IntersectionObserver === 'undefined') return;

        const observerOptions = {
            root: null,
            rootMargin: '100px',
            threshold: 0.0
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                const target = entry.target;
                if (entry.isIntersecting) {
                    // Resume animations
                    target.style.animationPlayState = 'running';
                    target.querySelectorAll('*').forEach(child => {
                        child.style.animationPlayState = 'running';
                    });
                    
                    // Resume video playback if it was previously playing
                    if (target.tagName === 'VIDEO' && target.dataset.shouldPlay === 'true') {
                        target.play().catch(() => {});
                    }
                } else {
                    // Pause animations to save CPU/GPU cycles
                    target.style.animationPlayState = 'paused';
                    target.querySelectorAll('*').forEach(child => {
                        child.style.animationPlayState = 'paused';
                    });
                    
                    // Pause video
                    if (target.tagName === 'VIDEO') {
                        if (!target.paused) {
                            target.dataset.shouldPlay = 'true';
                            target.pause();
                        } else {
                            target.dataset.shouldPlay = 'false';
                        }
                    }
                }
            });
        }, observerOptions);

        // Observe slide panels, video elements, canvases, and animated entry cards
        const selectors = '.slide-container, .slide, video, canvas, .live-photo-entrance';
        document.querySelectorAll(selectors).forEach(el => observer.observe(el));
    }

    // Initialize styling
    if (document.readyState === 'loading') {
        document.addEventListener("DOMContentLoaded", applyStyles);
    } else {
        applyStyles();
    }

    // Start FPS monitor and observer on full load (delay FPS monitoring to ignore load spike)
    window.addEventListener('load', () => {
        setupViewportObserver();
        setTimeout(monitorFPS, 3000);
        console.log(`[Loopr Performance] Device tier: ${config.tier.toUpperCase()} (Cores: ${navigator.hardwareConcurrency || 'unknown'}, Memory: ${navigator.deviceMemory || 'unknown'}GB)`);
    });
})();
