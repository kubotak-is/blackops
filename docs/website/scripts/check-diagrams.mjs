import { readFile } from 'node:fs/promises';
import path from 'node:path';
import { JSDOM } from 'jsdom';
import { repositoryRoot } from './website-paths.mjs';

const diagramSources = [
  'core-concepts.md',
  'execution.md',
  'operation-lifecycle.md',
  'execution-context.md',
];

const dom = new JSDOM('<!doctype html><html><body></body></html>');
globalThis.window = dom.window;
globalThis.document = dom.window.document;

const { default: mermaid } = await import('mermaid');
mermaid.initialize({
  startOnLoad: false,
  securityLevel: 'strict',
});

let diagramCount = 0;
for (const sourceName of diagramSources) {
  const sourcePath = path.join(repositoryRoot, 'docs/guide', sourceName);
  const markdown = await readFile(sourcePath, 'utf8');
  const diagrams = [...markdown.matchAll(/```mermaid\n([\s\S]*?)\n```/g)];

  if (diagrams.length !== 1) {
    throw new Error(`${sourceName} must contain exactly one Mermaid diagram; found ${diagrams.length}.`);
  }

  const diagram = diagrams[0][1];
  if (!/^\s*accTitle:\s*\S.+$/m.test(diagram) || !/^\s*accDescr:\s*\S.+$/m.test(diagram)) {
    throw new Error(`${sourceName} Mermaid diagram must define accTitle and accDescr.`);
  }

  try {
    await mermaid.parse(diagram, { suppressErrors: false });
  } catch (error) {
    throw new Error(`${sourceName} contains invalid Mermaid syntax.`, { cause: error });
  }
  diagramCount += 1;
}

if (diagramCount !== 4) {
  throw new Error(`Documentation must contain exactly four validated Mermaid diagrams; found ${diagramCount}.`);
}

console.log('Mermaid syntax and accessibility metadata check passed.');
