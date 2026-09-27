const INITIALIZED = 'landingReelReady';
const BOOTSTRAPPED = 'blackopsLandingReelsBootstrapped';

const requestFrame = (callback) => typeof window.requestAnimationFrame === 'function'
  ? window.requestAnimationFrame(callback)
  : window.setTimeout(callback, 16);

const cancelFrame = (frame) => {
  if (typeof window.cancelAnimationFrame === 'function') window.cancelAnimationFrame(frame);
  else window.clearTimeout(frame);
};

const localChapterData = (root) => Array.from(root.querySelectorAll('[data-landing-reel-chapter]')).map((element) => ({
  element,
  start: Number(element.dataset.landingReelChapter),
  end: Number(element.dataset.landingReelChapterEnd),
}));

export function initLandingReel(root) {
  if (!(root instanceof HTMLElement) || root.dataset[INITIALIZED] === 'true') return () => {};

  const video = root.querySelector('[data-landing-reel-video]');
  const toggle = root.querySelector('[data-landing-reel-toggle]');
  const toggleLabel = root.querySelector('[data-landing-reel-toggle-label]');
  const failure = root.querySelector('[data-landing-reel-failure]');
  if (!(video instanceof HTMLVideoElement)
    || !(toggle instanceof HTMLButtonElement)
    || !(toggleLabel instanceof HTMLElement)) return () => {};

  const controller = new AbortController();
  const chapters = localChapterData(root);
  const sourceElements = Array.from(video.querySelectorAll('source'))
    .filter((source) => source.getAttribute('src')?.trim());
  const failedSources = new Set();
  let pausedByReader = root.dataset.landingReelReaderPaused === 'true';
  let onScreen = typeof IntersectionObserver !== 'function';
  let frame = null;
  let programmaticPause = false;
  let cleanedUp = false;
  let observer = null;
  let mediaFailure = false;

  root.dataset[INITIALIZED] = 'true';
  toggle.hidden = false;
  for (const button of root.querySelectorAll('[data-landing-reel-seek]')) {
    if (button instanceof HTMLButtonElement) button.disabled = false;
  }

  const paint = () => {
    const time = Number.isFinite(video.currentTime) ? video.currentTime : 0;
    for (const { element, start, end } of chapters) {
      const progress = Math.min(1, Math.max(0, (time - start) / (end - start)));
      const active = time >= start && time < end;
      element.style.setProperty('--reel-progress', active ? String(progress) : '0');
      if (active) element.setAttribute('aria-current', 'step');
      else element.removeAttribute('aria-current');
    }
  };

  const animate = () => {
    frame = null;
    if (cleanedUp) return;
    paint();
    if (!video.paused && !video.ended) frame = requestFrame(animate);
  };

  const schedulePaint = () => {
    if (frame === null) frame = requestFrame(animate);
  };

  const setFailure = (message) => {
    root.dataset.landingReelState = 'failed';
    if (failure instanceof HTMLElement) {
      failure.textContent = message;
      failure.hidden = false;
    }
  };

  const allSourcesFailed = () => sourceElements.length === 0 || failedSources.size === sourceElements.length;
  const hasNoSource = () => video.networkState === 3 && video.readyState === 0;
  const markTerminalMediaFailure = () => {
    mediaFailure = true;
    setFailure('映像を読み込めません。チャプターの説明とガイドを利用できます。');
  };
  const showTerminalMediaFailure = () => {
    markTerminalMediaFailure();
    sync();
  };
  const showMediaFailure = () => {
    if (!allSourcesFailed()) {
      sync();
      return;
    }
    showTerminalMediaFailure();
    update();
  };

  const sync = () => {
    const noSource = hasNoSource();
    if (!mediaFailure && (video.error || noSource)) markTerminalMediaFailure();
    const mediaUnavailable = mediaFailure || video.error || noSource;
    if (mediaUnavailable && !video.paused) pauseForLifecycle();
    const playing = !mediaUnavailable && !video.paused && !video.ended;
    const state = mediaUnavailable
      ? 'failed'
      : playing ? 'playing' : video.ended ? 'ended' : 'paused';
    root.dataset.landingReelState = state;
    toggle.dataset.state = state;
    toggleLabel.textContent = playing ? '一時停止' : video.ended ? 'もう一度再生' : '再生';
    toggle.setAttribute('aria-label', playing ? '映像を一時停止' : video.ended ? '映像をもう一度再生' : '映像を再生');
    if (playing && failure instanceof HTMLElement) failure.hidden = true;
    if (playing) schedulePaint();
    else if (frame !== null) {
      cancelFrame(frame);
      frame = null;
      paint();
    }
  };

  const checkMediaAvailability = () => {
    if (cleanedUp || mediaFailure) return;
    if (video.error || hasNoSource()) showTerminalMediaFailure();
  };

  const pauseForLifecycle = () => {
    if (video.paused) return;
    programmaticPause = true;
    video.pause();
  };

  const play = () => {
    if (cleanedUp || video.ended) return;
    const promise = video.play();
    if (promise && typeof promise.catch === 'function') {
      promise.catch(() => {
        if (!cleanedUp) {
          if (mediaFailure || video.error || hasNoSource()) {
            showTerminalMediaFailure();
            update();
            return;
          }
          root.dataset.landingReelAutoplay = 'rejected';
          setFailure('自動再生を開始できません。動画の再生ボタンまたはネイティブ操作を使えます。');
          sync();
        }
      });
    }
  };

  const update = () => {
    checkMediaAvailability();
    if (mediaFailure || video.error || hasNoSource()) {
      pauseForLifecycle();
      sync();
      return;
    }
    if (onScreen && !pausedByReader && !video.ended && document.visibilityState === 'visible') play();
    else pauseForLifecycle();
  };

  const onPause = () => {
    const wasProgrammatic = programmaticPause;
    programmaticPause = false;
    if (!wasProgrammatic && !video.ended && !cleanedUp) {
      pausedByReader = true;
      root.dataset.landingReelReaderPaused = 'true';
    }
    sync();
  };

  const onPlaying = () => {
    pausedByReader = false;
    root.dataset.landingReelReaderPaused = 'false';
    delete root.dataset.landingReelAutoplay;
    root.dataset.landingReelState = 'playing';
    if (!mediaFailure && !video.error && !hasNoSource() && failure instanceof HTMLElement) failure.hidden = true;
    sync();
  };

  toggle.addEventListener('click', () => {
    if (!video.paused && !video.ended) {
      pausedByReader = true;
      root.dataset.landingReelReaderPaused = 'true';
      video.pause();
      return;
    }
    pausedByReader = false;
    root.dataset.landingReelReaderPaused = 'false';
    if (video.ended) video.currentTime = 0;
    play();
  }, { signal: controller.signal });

  for (const button of root.querySelectorAll('[data-landing-reel-seek]')) {
    if (!(button instanceof HTMLButtonElement)) continue;
    button.addEventListener('click', () => {
      const start = Number(button.dataset.landingReelSeek);
      if (!Number.isFinite(start)) return;
      video.currentTime = start;
      pausedByReader = false;
      root.dataset.landingReelReaderPaused = 'false';
      play();
    }, { signal: controller.signal });
  }

  document.addEventListener('visibilitychange', update, { signal: controller.signal });
  for (const name of ['play', 'playing', 'pause', 'seeked', 'ended']) {
    video.addEventListener(name, name === 'pause' ? onPause : name === 'playing' ? onPlaying : sync, { signal: controller.signal });
  }
  video.addEventListener('error', () => {
    showTerminalMediaFailure();
    update();
  }, { signal: controller.signal });
  for (const source of sourceElements) {
    source.addEventListener('error', () => {
      failedSources.add(source);
      showMediaFailure();
    }, { signal: controller.signal });
  }
  for (const name of ['loadedmetadata', 'stalled', 'suspend', 'emptied', 'progress']) {
    video.addEventListener(name, checkMediaAvailability, { signal: controller.signal });
  }
  video.addEventListener('canplay', update, { signal: controller.signal });

  if (typeof IntersectionObserver === 'function') {
    observer = new IntersectionObserver((entries) => {
      onScreen = entries.some((entry) => entry.isIntersecting);
      update();
    }, { threshold: 0.35 });
    observer.observe(video);
  } else {
    update();
  }

  const cleanup = () => {
    if (cleanedUp) return;
    cleanedUp = true;
    onScreen = false;
    pausedByReader = true;
    pauseForLifecycle();
    if (frame !== null) cancelFrame(frame);
    frame = null;
    observer?.disconnect();
    controller.abort();
    root.dataset[INITIALIZED] = 'stopped';
  };
  document.addEventListener('astro:before-swap', cleanup, { once: true, signal: controller.signal });
  window.addEventListener('pagehide', cleanup, { once: true, signal: controller.signal });

  sync();
  update();

  return cleanup;
}

export function bootLandingReels() {
  if (typeof document === 'undefined') return;
  const boot = () => {
    for (const root of document.querySelectorAll('[data-landing-reel]')) initLandingReel(root);
  };
  boot();
  if (typeof window === 'undefined' || window[BOOTSTRAPPED]) return;
  window[BOOTSTRAPPED] = true;
  document.addEventListener('astro:page-load', boot);
  window.addEventListener('pageshow', boot);
}
