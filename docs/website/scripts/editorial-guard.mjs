const forbiddenEditorialPatterns = [
  ['Javascript', /\bJavascript\b/g],
  ['NuxtJS', /\bNuxtJS\b/g],
  ['Project CLI', /\bProject CLI\b/g],
  ['このPage', /このPage/g],
  ['各Page', /各Page/g],
  ['一Page', /一Page/g],
  ['次のFile', /次のFile/g],
  ['生成済みFile', /生成済みFile/g],
  ['全File', /全File/g],
  ['次のCommand', /次のCommand/g],
  ['各Command', /各Command/g],
  ['確認Command', /確認Command/g],
  ['Latest Stable', /\bLatest Stable\b/g],
  ['Document Channel', /\bDocument Channel\b/g],
  ['Task Report', /\bTask Report\b/g],
  ['Consumer Evidence', /\bConsumer Evidence\b/g],
  ['Phase number', /\bPhase\s+\d+\b/g],
  ['保守Evidence', /保守Evidence/g],
  ['Worker Retry Evidence', /Worker Retry Evidence/g],
  ['stale shared publication claim', /Community BoardとDocumentation WebsiteはLocal／CIだけ/g],
  ['stale external publication claim', /External Publication／Deploy/g],
  ['Symptom label', /\*\*Symptom:\*\*/g],
  ['Likely Cause label', /\*\*Likely Cause:\*\*/g],
  ['How to Verify label', /\*\*How to Verify:\*\*/g],
  ['Fix label', /\*\*Fix:\*\*/g],
];

/**
 * Return the Markdown text that readers see, excluding executable or exact
 * contract material. Code fences are length-aware so both ``` and ~~~ forms
 * (including longer fences) remain protected.
 */
export function displayedMarkdown(markdown) {
  const lines = markdown.replace(/\r\n/g, '\n').split('\n');
  const visible = [];
  let fence = null;
  let htmlComment = false;

  for (const line of lines) {
    let sourceLine = line;
    if (fence === null) {
      if (htmlComment) {
        const end = sourceLine.indexOf('-->');
        if (end === -1) continue;
        sourceLine = sourceLine.slice(end + 3);
        htmlComment = false;
      }
      while (true) {
        const start = sourceLine.indexOf('<!--');
        if (start === -1) break;
        const end = sourceLine.indexOf('-->', start + 4);
        if (end === -1) {
          sourceLine = sourceLine.slice(0, start);
          htmlComment = true;
          break;
        }
        sourceLine = `${sourceLine.slice(0, start)}${sourceLine.slice(end + 3)}`;
      }
    }

    const marker = sourceLine.match(/^\s*(`{3,}|~{3,})/);
    if (fence === null && marker) {
      fence = {
        character: marker[1][0],
        length: marker[1].length,
        language: sourceLine.slice(marker[1].length).trim().split(/\s+/u)[0].toLocaleLowerCase('en'),
      };
      continue;
    }
    if (fence !== null) {
      const closing = line.match(/^\s*(`{3,}|~{3,})\s*$/);
      if (closing && closing[1][0] === fence.character && closing[1].length >= fence.length) fence = null;
      else if (fence.language === 'mermaid') visible.push(mermaidLabels(line));
      else if (fence.language !== 'json' && fence.language !== 'jsonl' && fence.language !== 'text' && fence.language !== 'plaintext') {
        visible.push(codeComment(line));
      }
      continue;
    }

    let text = sourceLine;
    // Link destinations are navigation contracts, not displayed prose.
    text = text.replace(/\]\([^)]*\)/g, ']()');
    // Inline code (including longer backtick delimiters) is an exact token.
    text = text.replace(/(`+)([\s\S]*?)\1/g, '');
    visible.push(text);
  }

  return visible.join('\n');
}

function codeComment(line) {
  const match = line.match(/(?:\/\/|#(?!\[)|\/\*|\*|\*\/)(.*)$/u);
  return match?.[1] ?? '';
}

function mermaidLabels(line) {
  if (/^\s*(?:accTitle|accDescr)\s*:/u.test(line)) return line.replace(/^\s*(?:accTitle|accDescr)\s*:\s*/u, '');
  return [...line.matchAll(/["']([^"']+)["']/gu)].map((match) => match[1]).join(' ');
}

export function findEditorialViolations(markdown) {
  const visible = displayedMarkdown(markdown);
  const violations = [];
  for (const [label, pattern] of forbiddenEditorialPatterns) {
    pattern.lastIndex = 0;
    for (const match of visible.matchAll(pattern)) violations.push({ label, value: match[0] });
  }
  return violations;
}

export function validateEditorial(markdown, { file = '<markdown>' } = {}) {
  const violations = findEditorialViolations(markdown);
  if (violations.length === 0) return;
  const details = violations.map(({ label, value }) => `${label}=${value}`).join(', ');
  throw new Error(`Editorial guard rejected ${file}: ${details}`);
}

export const editorialForbiddenLabels = forbiddenEditorialPatterns.map(([label]) => label);
