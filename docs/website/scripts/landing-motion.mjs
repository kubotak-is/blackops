const FALLBACK_TARGETS = [
  ['.landing-hero__identity', 'hero'],
  ['.landing-hero__actions', 'actions'],
  ['.landing-hero__code', 'code'],
  ['.landing-entry-points', 'entry-points'],
  ['.landing-demo', 'diagram'],
  ['.landing-resources', 'resources'],
];

const BACKDROP_SELECTOR = '[data-landing-backdrop]';
const NEON_TITLE_SELECTOR = '[data-landing-neon-title]';
const NEON_TITLE_DELAYS = [0, 135, 62, 228, 96, 286, 154, 344];
const NEON_TITLE_DURATIONS = [2140, 1980, 2320, 2050, 2240, 1910, 2290, 2020];

let activeCleanup = null;
let astroPageLoadBound = false;

const noOperation = () => {};

const markFallbackTargets = (shell, targets) => {
  for (const [selector, name] of FALLBACK_TARGETS) {
    const target = shell.querySelector(selector);
    if (!target || targets.includes(target)) continue;
    target.setAttribute('data-landing-reveal', name);
    target.dataset.landingMotionAuto = 'true';
    targets.push(target);
  }
};

const revealTarget = (target) => {
  target.classList.add('landing-motion-visible');
  target.style.removeProperty('will-change');
};

export function bootLandingMotion(root = typeof document === 'undefined' ? null : document) {
  activeCleanup?.();
  activeCleanup = null;

  if (!root) return noOperation;
  const view = root.defaultView ?? (typeof window === 'undefined' ? null : window);
  const shell = root.querySelector('.landing-shell');
  if (!view || !shell) return noOperation;

  const targets = Array.from(shell.querySelectorAll('[data-landing-reveal]'));
  markFallbackTargets(shell, targets);
  if (targets.length === 0) return noOperation;

  const rootElement = root.documentElement;
  rootElement.dataset.landingMotion = 'ready';
  const motionPreference = view.matchMedia?.('(prefers-reduced-motion: reduce)');
  const reducedMotion = motionPreference?.matches === true;
  const backdrop = shell.querySelector(BACKDROP_SELECTOR);
  const neonTitle = shell.querySelector(NEON_TITLE_SELECTOR);
  const pageIsVisible = () => root.hidden !== true && root.visibilityState !== 'hidden';
  const delayProperties = new Set();
  const reveal = (target) => revealTarget(target);

  let titleOriginalText = null;
  let titleLetters = [];
  let titleObserver = null;
  let onTitleVisibilityChange = null;
  let onMotionPreferenceChange = null;
  let titleInViewport = false;
  let titleStarted = false;
  let titleSettled = false;

  if (neonTitle && !reducedMotion) {
    titleOriginalText = neonTitle.textContent ?? '';
    titleLetters = Array.from(titleOriginalText, (character, index) => {
      const letter = root.createElement('span');
      letter.className = 'landing-brand__letter';
      letter.textContent = character;
      letter.style.setProperty('--landing-neon-delay', `${NEON_TITLE_DELAYS[index % NEON_TITLE_DELAYS.length]}ms`);
      letter.style.setProperty('--landing-neon-duration', `${NEON_TITLE_DURATIONS[index % NEON_TITLE_DURATIONS.length]}ms`);
      return letter;
    });
    neonTitle.replaceChildren(...titleLetters);
    neonTitle.dataset.landingNeonEnhanced = 'true';
  }

  const settleNeonTitle = () => {
    if (!neonTitle || titleLetters.length === 0) return;
    titleSettled = true;
    neonTitle.classList.remove('landing-neon-arrival');
    neonTitle.classList.add('landing-neon-settled');
  };
  const startNeonTitle = () => {
    if (!neonTitle || titleLetters.length === 0 || titleSettled || titleStarted) return;
    titleStarted = true;
    neonTitle.classList.add('landing-neon-arrival');
  };

  if (neonTitle && titleLetters.length > 0 && motionPreference) {
    onMotionPreferenceChange = (event) => {
      if (event.matches) settleNeonTitle();
    };
    if (typeof motionPreference.addEventListener === 'function') {
      motionPreference.addEventListener('change', onMotionPreferenceChange);
    }
  }

  targets.forEach((target, index) => {
    if (!target.hasAttribute('data-landing-delay')) {
      target.style.setProperty('--landing-reveal-delay', `${Math.min(index, 6) * 70}ms`);
      delayProperties.add(target);
    }
  });

  let observer = null;
  if (reducedMotion || typeof view.IntersectionObserver !== 'function') {
    targets.forEach(reveal);
  } else {
    observer = new view.IntersectionObserver((entries) => {
      for (const entry of entries) {
        if (!entry.isIntersecting) continue;
        reveal(entry.target);
        observer.unobserve(entry.target);
      }
    }, {
      rootMargin: '0px 0px -10% 0px',
      threshold: 0.16,
    });
    targets.forEach((target) => observer.observe(target));
  }

  let backdropObserver = null;
  let onBackdropVisibilityChange = null;
  if (backdrop && !reducedMotion && typeof view.IntersectionObserver === 'function') {
    let inViewport = false;
    const updateBackdropState = () => {
      if (inViewport && pageIsVisible()) {
        backdrop.setAttribute('data-landing-backdrop-active', 'true');
      } else {
        backdrop.removeAttribute('data-landing-backdrop-active');
      }
    };
    backdropObserver = new view.IntersectionObserver((entries) => {
      const entry = entries.at(-1);
      if (!entry) return;
      inViewport = entry.isIntersecting === true;
      updateBackdropState();
    }, {
      rootMargin: '20% 0px 20% 0px',
      threshold: 0,
    });
    backdropObserver.observe(backdrop);
    onBackdropVisibilityChange = updateBackdropState;
    root.addEventListener('visibilitychange', onBackdropVisibilityChange);
  }

  if (neonTitle && titleLetters.length > 0) {
    if (typeof view.IntersectionObserver !== 'function') {
      startNeonTitle();
    } else {
      titleObserver = new view.IntersectionObserver((entries) => {
        const entry = entries.at(-1);
        if (!entry) return;
        titleInViewport = entry.isIntersecting === true;
        if (titleInViewport && pageIsVisible()) startNeonTitle();
        else if (!titleInViewport && titleStarted) settleNeonTitle();
      }, {
        rootMargin: '12% 0px 12% 0px',
        threshold: 0.01,
      });
      titleObserver.observe(neonTitle);
    }
    onTitleVisibilityChange = () => {
      if (!pageIsVisible()) {
        if (titleStarted) settleNeonTitle();
        return;
      }
      if (titleInViewport) startNeonTitle();
    };
    root.addEventListener('visibilitychange', onTitleVisibilityChange);
  }

  const onFocusIn = (event) => {
    let target = event.target;
    while (target && shell.contains(target)) {
      if (typeof target.matches === 'function' && target.matches('[data-landing-reveal]')) reveal(target);
      if (target === shell) break;
      target = target.parentElement;
    }
  };
  root.addEventListener('focusin', onFocusIn);

  const onPageShow = (event) => {
    if (event.persisted) bootLandingMotion(root);
  };
  const onBeforeSwap = () => cleanup();
  const cleanup = () => {
    observer?.disconnect();
    backdropObserver?.disconnect();
    titleObserver?.disconnect();
    root.removeEventListener('focusin', onFocusIn);
    if (onBackdropVisibilityChange) root.removeEventListener('visibilitychange', onBackdropVisibilityChange);
    if (onTitleVisibilityChange) root.removeEventListener('visibilitychange', onTitleVisibilityChange);
    if (motionPreference && onMotionPreferenceChange) {
      if (typeof motionPreference.removeEventListener === 'function') motionPreference.removeEventListener('change', onMotionPreferenceChange);
    }
    root.removeEventListener('astro:before-swap', onBeforeSwap);
    view.removeEventListener('pagehide', onPageHide);
    view.removeEventListener('pageshow', onPageShow);
    backdrop?.removeAttribute('data-landing-backdrop-active');
    if (neonTitle && titleOriginalText !== null) {
      neonTitle.textContent = titleOriginalText;
      neonTitle.removeAttribute('data-landing-neon-enhanced');
      neonTitle.classList.remove('landing-neon-arrival', 'landing-neon-settled');
    }
    for (const target of targets) {
      target.classList.remove('landing-motion-visible');
      if (delayProperties.has(target)) target.style.removeProperty('--landing-reveal-delay');
      target.style.removeProperty('will-change');
      if (target.dataset.landingMotionAuto === 'true') {
        target.removeAttribute('data-landing-reveal');
        delete target.dataset.landingMotionAuto;
      }
    }
    delete rootElement.dataset.landingMotion;
    if (activeCleanup === cleanup) activeCleanup = null;
  };
  const onPageHide = (event) => {
    const persisted = event.persisted === true;
    cleanup();
    if (persisted) view.addEventListener('pageshow', onPageShow, { once: true });
  };

  view.addEventListener('pagehide', onPageHide);
  view.addEventListener('pageshow', onPageShow);
  root.addEventListener('astro:before-swap', onBeforeSwap, { once: true });
  activeCleanup = cleanup;
  return cleanup;
}

if (typeof document !== 'undefined' && !astroPageLoadBound) {
  astroPageLoadBound = true;
  document.addEventListener('astro:page-load', () => bootLandingMotion());
}
