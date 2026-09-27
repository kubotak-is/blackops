import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { JSDOM } from 'jsdom';
import test from 'node:test';
import { initLandingReel } from '../scripts/landing-reel.mjs';

const rootDir = new URL('..', import.meta.url);
const read = (file) => readFile(new URL(file, rootDir), 'utf8');
const readableHtml = (html) => html
  .replace(/<[^>]*>/g, '')
  .replace(/&lt;/g, '<')
  .replace(/&gt;/g, '>')
  .replace(/&quot;/g, '"')
  .replace(/&#39;/g, "'")
  .replace(/&amp;/g, '&');

const fixture = () => `
  <section data-landing-reel>
    <figure>
      <video controls data-landing-reel-video>
        <source src="/assets/reel/blackops-reel.webm" type="video/webm">
        <source src="/assets/reel/blackops-reel.mp4" type="video/mp4">
      </video>
      <p data-landing-reel-failure hidden role="status"></p>
      <button type="button" data-landing-reel-toggle hidden>
        <span data-landing-reel-toggle-label>再生</span>
      </button>
    </figure>
    <ol>
      <li data-landing-reel-chapter="0" data-landing-reel-chapter-end="3">
        <button type="button" data-landing-reel-seek="0" disabled>One</button>
      </li>
    </ol>
  </section>`;

test('landing reel source keeps native fallback, illustrative boundaries, and valid frontend calls', async () => {
  const component = await read('components/LandingReel.astro');
  const reel = await read('reel/reel.html');
  const capture = await read('reel/capture.mjs');
  const render = await read('reel/render.sh');
  const readableReel = readableHtml(reel);

  assert.match(component, /\bcontrols\b/);
  assert.match(component, /data-landing-reel-failure/);
  assert.match(component, /説明用の映像です/);
  assert.match(component, /\.landing-reel__toggle[\s\S]*top: clamp/);
  assert.match(readableReel, /accepted\.ok/);
  assert.match(readableReel, /accepted\.kind\s*!==\s*['"]accepted['"]/);
  assert.match(readableReel, /accepted\.data\.operationId/);
  assert.match(readableReel, /maxWaitMilliseconds:\s*15_000/);
  assert.match(reel, /Observed Journal/);
  assert.match(reel, /field omitted \(default\)/);
  assert.match(reel, /Mask指定時:\s*\[masked\]/);
  assert.match(reel, /masked \? 'Mask指定時: \[masked\]' : 'field omitted \(default\)'/);
  assert.match(reel, /\$\('sens'\)\.textContent = '\"reports@example\.com\"'/);
  assert.match(reel, /\[masked\]/);
  assert.doesNotMatch(reel, /Retrying/);
  const statusWords = [...reel.matchAll(/<div[^>]*class="status-word[^"]*"[^>]*data-st[^>]*>([^<]+)<\/div>/g)]
    .map(([, label]) => label.trim());
  const journalSource = reel.match(/const JOURNAL = \[(.*?)\];/s)?.[1];
  const rowStatusSource = reel.match(/const ROW_STATUS = \[([^\]]+)\]/)?.[1];
  assert.ok(journalSource);
  assert.ok(rowStatusSource);
  const events = [...journalSource.matchAll(/\['([^']+)'/g)].map(([, event]) => event);
  const rowStatus = [...rowStatusSource.matchAll(/\d+/g)].map(([value]) => Number(value));
  const stateByEvent = {
    'operation.received': 'Received',
    'operation.accepted': 'Accepted',
    'attempt.started': 'Running',
    'attempt.failed': 'Supervising',
    'attempt.retry_scheduled': 'Retry scheduled',
    'attempt.succeeded': 'Finalizing',
    'operation.completed': 'Completed',
  };
  assert.equal(statusWords.length, 7);
  assert.equal(new Set(statusWords).size, statusWords.length);
  assert.deepEqual(events.map((event, index) => statusWords[rowStatus[index]]), events.map((event) => stateByEvent[event]));
  assert.match(reel, /Illustrative example/);
  assert.match(reel, /Illustrative output/);
  assert.match(capture, /from 'playwright-core'/);
  assert.match(capture, /127\.0\.0\.1/);
  assert.match(capture, /path\.relative\(root, candidate\)/);
  assert.match(render, /playwright-core 1\.63\.0/);
  assert.match(render, /libx264/);
  assert.match(render, /libvpx-vp9/);
});

test('landing reel pause persists and cleanup stops its observers and frame work', async () => {
  const dom = new JSDOM(fixture(), { url: 'https://blackops.test/' });
  const root = dom.window.document.querySelector('[data-landing-reel]');
  const video = root.querySelector('[data-landing-reel-video]');
  const toggle = root.querySelector('[data-landing-reel-toggle]');
  const seek = root.querySelector('[data-landing-reel-seek]');
  const failure = root.querySelector('[data-landing-reel-failure]');
  const originals = new Map();
  let playing = false;
  let ended = false;
  let currentTime = 0;
  let networkState = 0;
  let readyState = 0;
  let rejectNextPlay = false;
  let rejectPendingPlay;
  let observerCallback;
  let disconnected = false;
  const globals = {
    window: dom.window,
    document: dom.window.document,
    HTMLElement: dom.window.HTMLElement,
    HTMLVideoElement: dom.window.HTMLVideoElement,
    HTMLButtonElement: dom.window.HTMLButtonElement,
    AbortController: dom.window.AbortController,
    IntersectionObserver: class {
      constructor(callback) {
        observerCallback = callback;
      }

      observe() {}

      disconnect() {
        disconnected = true;
      }
    },
  };

  for (const [name, value] of Object.entries(globals)) {
    originals.set(name, { exists: Object.hasOwn(globalThis, name), value: globalThis[name] });
    globalThis[name] = value;
  }
  Object.defineProperty(dom.window.document, 'visibilityState', { configurable: true, value: 'visible' });
  Object.defineProperty(video, 'paused', { configurable: true, get: () => !playing });
  Object.defineProperty(video, 'ended', { configurable: true, get: () => ended });
  Object.defineProperty(video, 'networkState', { configurable: true, get: () => networkState });
  Object.defineProperty(video, 'readyState', { configurable: true, get: () => readyState });
  Object.defineProperty(video, 'currentTime', {
    configurable: true,
    get: () => currentTime,
    set: (value) => { currentTime = value; },
  });
  Object.defineProperty(video, 'play', {
    configurable: true,
    value: () => {
      playing = true;
      video.dispatchEvent(new dom.window.Event('playing'));
      if (!rejectNextPlay) return Promise.resolve();
      rejectNextPlay = false;
      return new Promise((resolve, reject) => {
        rejectPendingPlay = reject;
      });
    },
  });
  Object.defineProperty(video, 'pause', {
    configurable: true,
    value: () => {
      playing = false;
      video.dispatchEvent(new dom.window.Event('pause'));
    },
  });
  dom.window.requestAnimationFrame = () => 1;
  dom.window.cancelAnimationFrame = () => {};

  let cleanup;
  try {
    cleanup = initLandingReel(root);
    assert.equal(video.controls, true);
    assert.equal(toggle.hidden, false);
    assert.equal(seek.disabled, false);

    observerCallback([{ isIntersecting: true }]);
    await Promise.resolve();
    assert.equal(root.dataset.landingReelState, 'playing');

    toggle.click();
    assert.equal(root.dataset.landingReelState, 'paused');
    observerCallback([{ isIntersecting: true }]);
    assert.equal(root.dataset.landingReelState, 'paused');
    assert.equal(playing, false);

    dom.window.dispatchEvent(new dom.window.Event('pagehide'));
    assert.equal(root.dataset.landingReelReady, 'stopped');
    cleanup = initLandingReel(root);
    observerCallback([{ isIntersecting: true }]);
    await Promise.resolve();
    assert.equal(root.dataset.landingReelState, 'paused');
    assert.equal(playing, false, 'an explicit reader pause survives lifecycle re-init');

    toggle.click();
    assert.equal(root.dataset.landingReelState, 'playing');
    cleanup();
    assert.equal(root.dataset.landingReelReady, 'stopped');
    assert.equal(disconnected, true);
    assert.equal(playing, false);

    cleanup = initLandingReel(root);
    const sources = [...video.querySelectorAll('source')];
    sources[0].dispatchEvent(new dom.window.Event('error'));
    assert.equal(failure.hidden, true, 'one failed source does not hide the native fallback');
    sources[1].dispatchEvent(new dom.window.Event('error'));
    assert.equal(failure.hidden, false, 'fallback appears after every eligible source fails');
    assert.match(failure.textContent, /映像を読み込めません/);

    cleanup();
    failure.hidden = true;
    networkState = 2;
    readyState = 0;
    playing = false;
    const missedSources = [...video.querySelectorAll('source')];
    missedSources[0].dispatchEvent(new dom.window.Event('error'));
    cleanup = initLandingReel(root);
    networkState = 3;
    playing = true;
    missedSources[1].dispatchEvent(new dom.window.Event('error'));
    assert.equal(failure.hidden, false, 'NETWORK_NO_SOURCE after a missed source error still shows media guidance');
    assert.equal(playing, false, 'terminal NETWORK_NO_SOURCE pauses pending playback');

    cleanup();
    failure.hidden = true;
    networkState = 3;
    readyState = 0;
    playing = true;
    cleanup = initLandingReel(root);
    assert.equal(failure.hidden, false, 'an initialized NETWORK_NO_SOURCE state is terminal even when source events were missed');
    assert.equal(root.dataset.landingReelState, 'failed');
    assert.equal(playing, false, 'pending playback is stopped after a terminal media failure');

    cleanup();
    failure.hidden = true;
    networkState = 0;
    playing = false;
    cleanup = initLandingReel(root);
    rejectNextPlay = true;
    observerCallback([{ isIntersecting: true }]);
    assert.equal(typeof rejectPendingPlay, 'function');
    video.dispatchEvent(new dom.window.Event('error'));
    assert.equal(failure.hidden, false, 'a direct video MediaError is terminal without source events');
    rejectPendingPlay(new Error('autoplay rejection after media error'));
    await Promise.resolve();
    await Promise.resolve();
    assert.match(failure.textContent, /映像を読み込めません/);
    assert.doesNotMatch(failure.textContent, /自動再生を開始できません/);
  } finally {
    cleanup?.();
    for (const [name, original] of originals) {
      if (original.exists) globalThis[name] = original.value;
      else delete globalThis[name];
    }
    dom.window.close();
  }
});
