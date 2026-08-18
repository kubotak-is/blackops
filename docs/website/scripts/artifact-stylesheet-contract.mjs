export function extractStylesheetHrefs(html) {
  return [...html.matchAll(/<link\b[^>]*>/gi)]
    .map(([tag]) => /\brel=["']stylesheet["']/i.test(tag) ? tag.match(/\bhref=["']([^"']+)["']/i)?.[1] : null)
    .filter(Boolean);
}

export async function assertLinkedStylesheetContract(html, hrefs, label, options = {}) {
  void html;
  if (hrefs.length === 0) {
    throw new Error(`${label} must link a stylesheet owned by the rendered page.`);
  }
  if (typeof options.readStylesheet !== 'function') {
    throw new Error(`${label} requires an injected stylesheet reader.`);
  }
  const css = [];
  for (const href of hrefs) {
    if (!href.startsWith('/') || href.startsWith('//')) {
      throw new Error(`${label} contains a non-local stylesheet link: ${href}`);
    }
    const relative = decodeURIComponent(href.split(/[?#]/, 1)[0]).replace(/^\/+/, '');
    if (!relative || relative.split('/').includes('..')) {
      throw new Error(`${label} contains an unsafe stylesheet link: ${href}`);
    }
    try {
      css.push(await options.readStylesheet(relative));
    } catch {
      throw new Error(`${label} references a missing linked stylesheet: ${href}`);
    }
  }
  const rules = cssRules(css.join('\n'));
  if (!hasRule(rules, '[data-blume-nav-tree] a[aria-current=page]', 'box-shadow:inset3px00')) {
    throw new Error(`${label} linked stylesheets must own the active navigation contract.`);
  }
  if (!hasRule(rules, '.prose :not(pre)>code', 'overflow-wrap:anywhere', 'word-break:break-word')) {
    throw new Error(`${label} linked stylesheets must own the inline code wrapping contract.`);
  }
  if (!hasRule(rules, 'blume-mermaid', 'width:100%') || !hasRule(rules, 'blume-mermaid>div', 'min-width:42rem', 'width:100%') || !hasRule(rules, 'blume-mermaid svg', 'height:auto')) {
    throw new Error(`${label} linked stylesheets must own the Mermaid legibility contract.`);
  }
  assertAccessibilityStylesheetContract(css.join('\n'), label, options);
}

export function assertAccessibilityStylesheetContract(css, label, options = {}) {
  const rules = cssRules(css);
  const requireLandingSurfaces = options.requireLandingSurfaces === true || label === '/' || label === 'index.html';
  const lightLandingSurfaceSelectors = requireLandingSurfaces ? [
      '[data-theme=light] .landing-editor-language',
      '[data-theme=light] .landing-lifecycle-heading>span',
      '[data-theme=light] .landing-lifecycle-caption',
      '[data-theme=light] .landing-lifecycle-note',
      '[data-theme=light] .landing-lifecycle-rail small',
    ] : [];
  const required = [
    ['[data-theme=light] .not-prose[class~=bg-blue-500/10]>div>p:not(.text-foreground)', 'color:#6d6d6d', 'Light information callout contrast'],
    ['[data-theme=dark] .astro-code span[style*=--shiki-dark:#6A737D]', 'color:#707b87!important', 'Dark Shiki comment contrast'],
    ['[data-theme=dark] blume-mermaid .edgeLabel p', 'color:#d0d0d0!important', 'Dark Mermaid edge-label contrast'],
    ['[data-theme=dark] body>a[href=#blume-content]', 'color:#12201f', 'Dark skip-link contrast'],
    ...lightLandingSurfaceSelectors.map((selector) => [selector, 'color:#526966', 'Light Landing deep-surface muted contrast']),
    ['.blackops-overflow-focus:focus-visible', 'outline:3pxsolidvar(--bo-focus)', 'Overflow focus indicator'],
  ];
  for (const [selector, declaration, contract] of required) {
    if (!hasRule(rules, selector, declaration)) {
      throw new Error(`${label} linked stylesheets must own the ${contract} contract.`);
    }
  }
  if (requireLandingSurfaces) {
    assertLightLandingSurfaceContrast(rules, label, lightLandingSurfaceSelectors);
  }
  const forbidden = [
    ['.not-prose[class~=bg-blue-500/10]>div>p:not(.text-foreground)', 'color:#6f6f6f', 'old Light callout color'],
    ['.not-prose[class~=bg-blue-500/10]>div>p:not(.text-foreground)', 'color:#6d6d6d', 'unscoped Light callout color'],
    ['[data-theme=dark] .astro-code span[style*=--shiki-dark:#6A737D]', 'color:#6a737d', 'old Dark Shiki comment color'],
    ['[data-theme=dark] blume-mermaid .edgeLabel p', 'color:#cccccc!important', 'old Dark Mermaid edge-label color'],
    ['[data-theme=dark] body>a[href=#blume-content]', 'color:#fff', 'old Dark skip-link color'],
    ...(requireLandingSurfaces ? lightLandingSurfaceSelectors.map((selector) => [selector.replace('[data-theme=light] ', ''), 'color:#526966', 'unscoped Light Landing deep-surface muted color']) : []),
  ];
  for (const [selector, declaration, contract] of forbidden) {
    if (hasRule(rules, selector, declaration)) {
      throw new Error(`${label} linked stylesheets must not contain the ${contract}.`);
    }
  }
}

export function assertOverflowFocusContract(html, label, options = {}) {
  const landingCommand = [...html.matchAll(/<[^>]*\bclass=["'][^"']*\blanding-command\b[^"']*["'][^>]*>/gi)];
  const requireLandingSurfaces = options.requireLandingSurfaces === true || label === '/' || label === 'index.html';
  if (requireLandingSurfaces && landingCommand.length === 0) {
    throw new Error(`${label} Landing must contain a keyboard-focusable command overflow surface.`);
  }
  for (const [tag] of landingCommand) {
    if (!/\btabindex=["']0["']/i.test(tag)) {
      throw new Error(`${label} landing command overflow must be keyboard focusable.`);
    }
  }
  const landingCodePanel = /<[^>]*\bclass=["'][^"']*\blanding-code-panel\b[^"']*["'][^>]*>/i.test(html);
  const directLandingCodePre = /<[^>]*\bclass=["'][^"']*\blanding-code-panel\b[^"']*["'][^>]*>(?:\s*<div\b[^>]*>[\s\S]*?<\/div>\s*)*<pre\b[^>]*\btabindex=["']0["'][^>]*>/i.test(html);
  if (requireLandingSurfaces && !landingCodePanel) {
    throw new Error(`${label} Landing must contain a code-panel overflow surface.`);
  }
  if (landingCodePanel && !directLandingCodePre) {
    throw new Error(`${label} landing code overflow must be keyboard focusable.`);
  }
  if (/<blume-mermaid\b/i.test(html)) {
    const runtimeSource = options.runtimeSource ?? '';
    const enhancement = [...runtimeSource.matchAll(/for\s*\(\s*const\s+element\s+of\s+document\.querySelectorAll\(\s*['"]([^'"]+)['"]\s*\)\s*\)\s*\{([\s\S]*?)\}/g)]
      .find((match) => match[1] === '.landing-command, .landing-code-panel > pre, blume-mermaid');
    if (!enhancement) {
      throw new Error(`${label} Mermaid overflow must use the exact shared overflow selector.`);
    }
    const enhancementSource = enhancement[2];
    if (!/element\.classList\.add\(\s*['"]blackops-overflow-focus['"]\s*\)/.test(enhancementSource) || !/element\.tabIndex\s*=\s*0\b/.test(enhancementSource)) {
      throw new Error(`${label} Mermaid overflow must receive the shared focus class and tabIndex enhancement.`);
    }
  }
}

export function assertSearchFocusBoundarySourceContract({ component, landing, detail }, label = 'Search focus boundary source') {
  const markerCount = (component.match(/data-blackops-search-focus-boundary/g) ?? []).length;
  if (markerCount !== 1) {
    throw new Error(`${label} must define exactly one shared boundary marker; found ${markerCount}.`);
  }
  const script = component.match(/<script\b[^>]*\bdata-blackops-search-focus-boundary\b[^>]*>([\s\S]*?)<\/script>/i)?.[1] ?? '';
  if (!script) throw new Error(`${label} must contain the shared boundary script.`);
  const handler = extractSearchFocusBoundaryHandler(script, label, 'source');
  assertSearchFocusBoundaryOperationOrder(handler, label, 'source');
  assertSearchFocusBoundaryUsage(landing, /\.\.\/components\/SearchFocusBoundary\.astro/, 'Landing', label);
  assertSearchFocusBoundaryUsage(detail, /\.\/SearchFocusBoundary\.astro/, 'detail layout', label);
}

export function assertSearchFocusBoundaryArtifact(html, label) {
  const markers = [...html.matchAll(/<script\b(?=[^>]*\bdata-blackops-search-focus-boundary\b)[^>]*>/gi)];
  if (markers.length !== 1) {
    throw new Error(`${label} must contain exactly one generated Search focus boundary marker; found ${markers.length}.`);
  }
  const scriptStart = markers[0].index + markers[0][0].length;
  const scriptEnd = html.indexOf('</script>', scriptStart);
  const script = scriptEnd === -1 ? '' : html.slice(scriptStart, scriptEnd);
  const handler = extractSearchFocusBoundaryHandler(script, label, 'artifact');
  assertSearchFocusBoundaryOperationOrder(handler, label, 'artifact');
}

function extractSearchFocusBoundaryHandler(script, label, kind) {
  const handlers = [...script.matchAll(/document\.addEventListener\(\s*['"]keydown['"]\s*,\s*\(event\)\s*=>\s*\{([\s\S]*?)\}\s*,\s*true\s*\)/g)];
  const message = kind === 'artifact' ? 'generated boundary is missing' : 'is missing its bounded';
  if (handlers.length !== 1) throw new Error(`${label} ${message} exactly one capture keydown handler.`);
  const handler = handlers[0][1];
  for (const [pattern, contract] of [
    [/event\.key\s*!==\s*['"]Escape['"]/, 'Escape filtering'],
    [/event\.isComposing/, 'composition filtering'],
    [/event\.target\.closest\(\s*['"]\[data-blume-search-dialog\]['"]\s*\)/, 'dialog-origin filtering'],
    [/dialog\.open/, 'open-dialog filtering'],
  ]) {
    if (!pattern.test(handler)) throw new Error(`${label} ${message} ${contract} operation.`);
  }
  return handler;
}

function assertSearchFocusBoundaryOperationOrder(source, label, kind) {
  const operations = [
    [/event\.preventDefault\(\)/, 'native search-clear prevention'],
    [/dialog\.close\(\)/, 'dialog close'],
    [/dialog\.closest\(\s*['"]blume-search['"]\s*\)\s*\?\.\s*querySelector\(\s*['"]\[data-blume-search-open\]['"]\s*\)/, 'same-route search trigger lookup'],
    [/trigger\.focus\(\)/, 'search trigger focus return'],
  ];
  let offset = 0;
  for (const [pattern, contract] of operations) {
    const match = source.slice(offset).match(pattern);
    if (!match) {
      const message = kind === 'artifact' ? 'generated boundary is missing' : 'is missing its bounded';
      throw new Error(`${label} ${message} ${contract} or its required operation order.`);
    }
    offset += match.index + match[0].length;
  }
}

function assertSearchFocusBoundaryUsage(source, importPattern, surface, label) {
  const imports = [...source.matchAll(new RegExp(importPattern.source, `${importPattern.flags.replace('g', '')}g`))].length;
  const usages = (source.match(/<SearchFocusBoundary\s*\/>/g) ?? []).length;
  if (imports !== 1 || usages !== 1) {
    throw new Error(`${label} must include SearchFocusBoundary exactly once from ${surface}; found imports=${imports}, usages=${usages}.`);
  }
}

function cssRules(css) {
  return [...css.replace(/\/\*[\s\S]*?\*\//g, '').matchAll(/([^{}]+)\{([^{}]*)\}/g)].map(([, selector, body]) => ({
    selectors: selector.split(',').map((part) => part.replace(/["']/g, '').replace(/\\([^\w\s])/g, '$1').replace(/\s*([>+~])\s*/g, '$1').replace(/\s+/g, ' ').trim()),
    body: body.replace(/\s+/g, '').toLowerCase(),
  }));
}

function hasRule(rules, selector, ...declarations) {
  return rules.some(({ selectors, body }) => selectors.includes(selector) && declarations.every((declaration) => body.includes(declaration)));
}

function assertLightLandingSurfaceContrast(rules, label, selectors) {
  const surface = declarationValue(rules, ':root', '--bo-surface-deep');
  const foreground = declarationValue(rules, selectors[0], 'color');
  if (!isHexColor(surface) || !isHexColor(foreground)) {
    throw new Error(`${label} linked stylesheets must expose measurable Light Landing deep-surface foreground and background colors.`);
  }
  const ratio = contrastRatio(foreground, surface);
  if (ratio < 4.8) {
    throw new Error(`${label} linked stylesheets must keep Light Landing deep-surface muted text at least 4.8:1; measured ${ratio.toFixed(3)}:1.`);
  }
}

function declarationValue(rules, selector, property) {
  const rule = rules.find(({ selectors }) => selectors.includes(selector));
  if (!rule) return '';
  const escapedProperty = property.replace(/[.*+?^${}()|[\]\\]/g, '\\$&').toLowerCase();
  return rule.body.match(new RegExp(`${escapedProperty}:([^;]+)`))?.[1] ?? '';
}

function isHexColor(value) {
  return /^#[0-9a-f]{6}$/i.test(value);
}

function relativeLuminance(hex) {
  const channels = [0, 2, 4].map((offset) => Number.parseInt(hex.slice(offset + 1, offset + 3), 16) / 255);
  return channels
    .map((channel) => channel <= 0.03928 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4)
    .reduce((sum, channel, index) => sum + channel * [0.2126, 0.7152, 0.0722][index], 0);
}

function contrastRatio(first, second) {
  const light = Math.max(relativeLuminance(first), relativeLuminance(second));
  const dark = Math.min(relativeLuminance(first), relativeLuminance(second));
  return (light + 0.05) / (dark + 0.05);
}
