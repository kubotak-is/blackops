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
  let seekFailure = false;
  let seekLoading = false;
  let seekFallbackController = null;
  let seekFallbackPromise = null;
  let seekFallbackSource = null;
  let seekFallbackObjectUrl = null;
  let seekRequest = 0;
  let seekCompletionPending = false;
  let playAttempt = 0;

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
  const clearSeekLoading = () => {
    if (!seekLoading) return;
    seekLoading = false;
    if (!seekFailure && !mediaFailure && !video.error && !hasNoSource() && failure instanceof HTMLElement) {
      failure.hidden = true;
      failure.textContent = '';
    }
  };

  const allSourcesFailed = () => sourceElements.length === 0 || failedSources.size === sourceElements.length;
  const hasNoSource = () => video.networkState === 3 && video.readyState === 0;
  const abortError = () => {
    const error = new Error('The seek fallback was cancelled.');
    error.name = 'AbortError';
    return error;
  };
  const hasUsableSeekableRange = () => {
    try {
      const ranges = video.seekable;
      for (let index = 0; index < ranges.length; index += 1) {
        const start = ranges.start(index);
        const end = ranges.end(index);
        if (Number.isFinite(start) && Number.isFinite(end) && end > start) return true;
      }
    } catch {
      return false;
    }
    return false;
  };
  const hasSeekableTarget = (target) => {
    try {
      const ranges = video.seekable;
      for (let index = 0; index < ranges.length; index += 1) {
        const start = ranges.start(index);
        const end = ranges.end(index);
        if (Number.isFinite(start) && Number.isFinite(end) && target >= start && target <= end) return true;
      }
    } catch {
      return false;
    }
    return false;
  };
  const selectedSource = () => {
    const currentSrc = typeof video.currentSrc === 'string' ? video.currentSrc.trim() : '';
    const source = sourceElements.find((candidate) => {
      const sourceUrl = candidate.getAttribute('src')?.trim() || '';
      return sourceUrl && currentSrc && (candidate.src === currentSrc || sourceUrl === currentSrc);
    }) || sourceElements.find((candidate) => candidate.getAttribute('src')?.trim());
    return {
      url: currentSrc || source?.src || source?.getAttribute('src')?.trim() || '',
      type: source?.getAttribute('type')?.trim() || '',
    };
  };
  const removeSeekFallback = (reloadOriginal = false) => {
    const hadFallback = Boolean(seekFallbackSource || seekFallbackObjectUrl);
    seekFallbackSource?.remove();
    seekFallbackSource = null;
    if (seekFallbackObjectUrl && typeof globalThis.URL?.revokeObjectURL === 'function') {
      globalThis.URL.revokeObjectURL(seekFallbackObjectUrl);
    }
    seekFallbackObjectUrl = null;
    if (reloadOriginal && hadFallback && typeof video.load === 'function') {
      video.autoplay = false;
      try {
        video.load();
      } catch {
        // The native source remains available through the original <source> elements.
      }
    }
    return hadFallback;
  };
  const seekFallbackPending = () => Boolean(seekFallbackPromise || seekCompletionPending);
  const showSeekFailure = () => {
    seekLoading = false;
    seekFailure = true;
    setFailure('チャプターを読み込めません。チャプターの説明とガイドを利用できます。');
    sync();
  };
  const waitForSeekFallback = (fallbackController, fallbackSource) => new Promise((resolve, reject) => {
    let settled = false;
    const events = ['loadedmetadata', 'durationchange', 'canplay', 'progress', 'error'];
    const finish = (error) => {
      if (settled) return;
      settled = true;
      for (const name of events) video.removeEventListener(name, inspect);
      fallbackSource.removeEventListener('error', onSourceError);
      fallbackController.signal.removeEventListener('abort', onAbort);
      if (error) reject(error);
      else resolve();
    };
    const inspect = () => {
      if (cleanedUp || fallbackController.signal.aborted) {
        finish(abortError());
        return;
      }
      if (video.error) {
        finish(new Error('The media fallback failed.'));
        return;
      }
      if (hasUsableSeekableRange()) finish();
    };
    const onSourceError = () => finish(new Error('The media fallback source failed.'));
    const onAbort = () => finish(abortError());
    for (const name of events) video.addEventListener(name, inspect);
    fallbackSource.addEventListener('error', onSourceError, { once: true });
    fallbackController.signal.addEventListener('abort', onAbort, { once: true });
  });
  const ensureSeekFallback = () => {
    if (seekFallbackPromise) return seekFallbackPromise;
    if (seekFallbackObjectUrl) return Promise.resolve();
    const source = selectedSource();
    if (!source.url || typeof fetch !== 'function' || typeof globalThis.URL?.createObjectURL !== 'function') {
      return Promise.reject(new Error('The media fallback is unavailable.'));
    }
    const fallbackController = new AbortController();
    seekFallbackController = fallbackController;
    let promise;
    promise = fetch(source.url, { signal: fallbackController.signal })
      .then((response) => {
        if (!response.ok || typeof response.blob !== 'function') throw new Error('The media fallback request failed.');
        return response.blob();
      })
      .then((blob) => {
        if (cleanedUp || fallbackController.signal.aborted) throw abortError();
        seekFallbackObjectUrl = globalThis.URL.createObjectURL(blob);
        if (cleanedUp || fallbackController.signal.aborted) throw abortError();
        seekFallbackSource = video.ownerDocument.createElement('source');
        seekFallbackSource.setAttribute('src', seekFallbackObjectUrl);
        if (source.type) seekFallbackSource.setAttribute('type', source.type);
        video.insertBefore(seekFallbackSource, video.firstChild);
        const ready = waitForSeekFallback(fallbackController, seekFallbackSource);
        video.autoplay = false;
        if (typeof video.load === 'function') video.load();
        return ready;
      })
      .finally(() => {
        if (seekFallbackPromise === promise) {
          seekFallbackPromise = null;
          seekFallbackController = null;
        }
      });
    seekFallbackPromise = promise;
    return promise;
  };
  const markTerminalMediaFailure = () => {
    seekLoading = false;
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
    const fallbackPending = seekFallbackPending();
    const noSource = hasNoSource();
    if (!fallbackPending && !mediaFailure && (video.error || noSource)) markTerminalMediaFailure();
    const mediaUnavailable = !fallbackPending && (mediaFailure || video.error || noSource);
    if ((fallbackPending || mediaUnavailable) && !video.paused) pauseForLifecycle();
    const playing = !fallbackPending && !mediaUnavailable && !video.paused && !video.ended;
    const state = fallbackPending || mediaUnavailable
      ? 'failed'
      : playing ? 'playing' : video.ended ? 'ended' : 'paused';
    root.dataset.landingReelState = state;
    toggle.dataset.state = fallbackPending ? pausedByReader ? 'paused' : 'playing' : state;
    const actionLabel = fallbackPending
      ? pausedByReader ? '再生' : '一時停止'
      : playing ? '一時停止' : video.ended ? 'もう一度再生' : '再生';
    toggleLabel.textContent = actionLabel;
    toggle.setAttribute('aria-label', fallbackPending && pausedByReader
      ? 'チャプター読込後に映像を再生'
      : actionLabel === '一時停止' ? '映像を一時停止' : actionLabel === 'もう一度再生' ? '映像をもう一度再生' : '映像を再生');
    if (playing && !seekFailure && failure instanceof HTMLElement) failure.hidden = true;
    if (playing) schedulePaint();
    else if (frame !== null) {
      cancelFrame(frame);
      frame = null;
      paint();
    }
  };

  const checkMediaAvailability = () => {
    if (cleanedUp || mediaFailure || seekFallbackPending()) return;
    if (video.error || hasNoSource()) showTerminalMediaFailure();
  };

  const pauseForLifecycle = () => {
    playAttempt += 1;
    if (video.paused) return;
    programmaticPause = true;
    video.pause();
  };

  const play = () => {
    if (cleanedUp || video.ended) return;
    const attempt = ++playAttempt;
    const promise = video.play();
    if (promise && typeof promise.catch === 'function') {
      promise.catch((error) => {
        if (!cleanedUp) {
          if (attempt !== playAttempt || (error?.name === 'AbortError' && video.paused)) return;
          if (seekFallbackPending()) return;
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
    if (seekFallbackPending()) {
      pauseForLifecycle();
      sync();
      return;
    }
    if (mediaFailure || video.error || hasNoSource()) {
      pauseForLifecycle();
      sync();
      return;
    }
    if (onScreen && !pausedByReader && !video.ended && document.visibilityState === 'visible') play();
    else pauseForLifecycle();
  };

  const applyChapterSeek = (start) => {
    if (cleanedUp || mediaFailure || video.error || hasNoSource()) return false;
    playAttempt += 1;
    try {
      video.currentTime = start;
    } catch {
      showSeekFailure();
      return false;
    }
    clearSeekLoading();
    if (onScreen && document.visibilityState === 'visible' && !pausedByReader && !video.ended) play();
    else update();
    return true;
  };
  const seekToChapter = (start) => {
    const request = ++seekRequest;
    pausedByReader = false;
    root.dataset.landingReelReaderPaused = 'false';
    seekFailure = false;
    const fallbackPending = seekFallbackPending();
    if (!fallbackPending && hasSeekableTarget(start)) {
      seekCompletionPending = false;
      applyChapterSeek(start);
      return;
    }
    if (!fallbackPending) seekCompletionPending = true;
    seekLoading = true;
    pauseForLifecycle();
    setFailure('チャプターを読み込んでいます…');
    ensureSeekFallback()
      .then(() => {
        if (cleanedUp || request !== seekRequest) return;
        seekCompletionPending = false;
        applyChapterSeek(start);
      })
      .catch((error) => {
        if (cleanedUp || request !== seekRequest || error?.name === 'AbortError') return;
        seekCompletionPending = false;
        removeSeekFallback(true);
        showSeekFailure();
      });
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
    if (seekFallbackPending()) {
      pauseForLifecycle();
      sync();
      return;
    }
    if (pausedByReader && video.paused) {
      pauseForLifecycle();
      sync();
      return;
    }
    pausedByReader = false;
    root.dataset.landingReelReaderPaused = 'false';
    delete root.dataset.landingReelAutoplay;
    seekFailure = false;
    root.dataset.landingReelState = 'playing';
    if (!mediaFailure && !video.error && !hasNoSource() && failure instanceof HTMLElement) failure.hidden = true;
    sync();
  };

  toggle.addEventListener('click', () => {
    if (seekFallbackPending()) {
      if (pausedByReader) {
        pausedByReader = false;
        root.dataset.landingReelReaderPaused = 'false';
      } else {
        pausedByReader = true;
        root.dataset.landingReelReaderPaused = 'true';
        pauseForLifecycle();
      }
      sync();
      return;
    }
    if (!video.paused && !video.ended) {
      pausedByReader = true;
      root.dataset.landingReelReaderPaused = 'true';
      playAttempt += 1;
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
      seekToChapter(start);
    }, { signal: controller.signal });
  }

  document.addEventListener('visibilitychange', update, { signal: controller.signal });
  for (const name of ['play', 'playing', 'pause', 'seeked', 'ended']) {
    video.addEventListener(name, name === 'pause' ? onPause : name === 'playing' ? onPlaying : sync, { signal: controller.signal });
  }
  video.addEventListener('error', () => {
    if (seekFallbackPending()) return;
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
    clearSeekLoading();
    controller.abort();
    seekRequest += 1;
    seekCompletionPending = false;
    seekFallbackController?.abort();
    seekFallbackController = null;
    seekFallbackPromise = null;
    removeSeekFallback();
    onScreen = false;
    pausedByReader = true;
    pauseForLifecycle();
    if (frame !== null) cancelFrame(frame);
    frame = null;
    observer?.disconnect();
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
