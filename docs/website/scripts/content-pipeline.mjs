import { createHash } from 'node:crypto';
import { readdir, readFile, realpath, rm, mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';

const MARKDOWN_LINK = /(!?\[[^\]]*\])\(([^)]+)\)/g;
const EXTERNAL_TARGET = /^(?:[a-z][a-z0-9+.-]*:|\/\/)/i;
const REPOSITORY_ABSOLUTE_PATH = /(?:^|[`\s(])(?:\/home\/|\/Users\/|[A-Za-z]:\\)/m;
const FORBIDDEN_CONTENT = [
  { pattern: /docs\/internal(?:\/|\b)/i, label: 'docs/internal' },
  { pattern: /develop\//i, label: 'develop/' },
];

export async function generateContent({
  sourceRoot,
  contentRoot,
  manifestPath,
  repositoryRoot,
  contentMap = null,
  banner = null,
}) {
  const sourceDirectory = await realpath(sourceRoot);
  const repositoryDirectory = repositoryRoot === undefined ? null : await realpath(repositoryRoot);
  const sourceFiles = await markdownFiles(sourceDirectory);
  if (sourceFiles.length === 0) {
    throw new Error('Documentation source contains no Markdown files.');
  }

  const records = [];
  for (const source of sourceFiles) {
    const absolute = path.join(sourceDirectory, ...source.split('/'));
    const markdown = normalizeNewlines(await readFile(absolute, 'utf8'));
    validatePublicContent(markdown, source, repositoryDirectory);
    const { title, body } = extractTitle(markdown, source);
    const metadata = contentMap?.[source] ?? null;
    if (contentMap !== null && metadata === null) {
      throw new Error(`Documentation source is missing public metadata: ${source}`);
    }
    const slug = metadata === null ? slugFor(source) : publicSlug(metadata.slug, source);
    records.push({
      source,
      generated: `${slug}.md`,
      slug,
      title,
      body,
      metadata,
    });
  }

  if (contentMap !== null) {
    const unknown = Object.keys(contentMap).filter((source) => !sourceFiles.includes(source));
    if (unknown.length > 0) {
      throw new Error(`Public metadata references missing documentation source: ${unknown.sort().join(', ')}`);
    }
  }

  assertUniqueSlugs(records);
  const bySource = new Map(records.map((record) => [record.source, record]));
  const routes = new Set(records.map((record) => routeFor(record.slug)));
  const outputs = records.map((record) => {
    const body = rewriteAndValidateLinks(record, bySource, routes);
    const content = `---\n${frontmatter(record, banner)}---\n${body}`;

    return {
      ...record,
      content,
      hash: createHash('sha256').update(content).digest('hex'),
    };
  });

  const manifest = `${JSON.stringify(
    {
      schemaVersion: 1,
      pages: outputs.map(({ source, generated, slug, title, hash }) => ({ source, generated, slug, title, hash })),
    },
    null,
    2,
  )}\n`;

  await replaceGeneratedContent(contentRoot, manifestPath, outputs, manifest);

  return manifest;
}

function publicSlug(value, source) {
  if (typeof value !== 'string' || !/^[a-z0-9]+(?:-[a-z0-9]+)*(?:\/[a-z0-9]+(?:-[a-z0-9]+)*)*$/.test(value)) {
    throw new Error(`Documentation public slug must use lowercase kebab-case path segments: ${source}`);
  }

  return value;
}

function frontmatter(record, banner) {
  const values = {
    title: record.title,
    description: record.metadata?.description,
    template: record.metadata?.template,
    hero: record.metadata?.hero,
    banner,
  };

  return Object.entries(values)
    .filter(([, value]) => value !== undefined && value !== null)
    .map(([key, value]) => `${key}: ${JSON.stringify(value)}\n`)
    .join('');
}

async function markdownFiles(root) {
  const files = [];

  async function visit(directory, prefix) {
    const entries = await readdir(directory, { withFileTypes: true });
    entries.sort((left, right) => left.name.localeCompare(right.name, 'en'));

    for (const entry of entries) {
      const relative = prefix === '' ? entry.name : `${prefix}/${entry.name}`;
      if (entry.isSymbolicLink()) {
        throw new Error(`Documentation source must not contain symbolic links: ${relative}`);
      }
      if (entry.isDirectory()) {
        await visit(path.join(directory, entry.name), relative);
        continue;
      }
      if (entry.isFile() && entry.name.endsWith('.md')) {
        files.push(relative);
      }
    }
  }

  await visit(root, '');
  return files;
}

function validatePublicContent(markdown, source, repositoryDirectory) {
  for (const forbidden of FORBIDDEN_CONTENT) {
    if (forbidden.pattern.test(markdown)) {
      throw new Error(`Public documentation references forbidden content "${forbidden.label}": ${source}`);
    }
  }

  const normalizedRepository = repositoryDirectory?.split(path.sep).join('/');
  if (
    REPOSITORY_ABSOLUTE_PATH.test(markdown)
    || (normalizedRepository !== null && normalizedRepository !== undefined && markdown.includes(normalizedRepository))
  ) {
    throw new Error(`Public documentation contains a repository absolute path: ${source}`);
  }
}

function extractTitle(markdown, source) {
  const lines = markdown.split('\n');
  const firstContent = lines.find((line) => line.trim() !== '');
  if (firstContent === '---') {
    throw new Error(`Source Markdown frontmatter is not supported: ${source}`);
  }

  let fenced = false;
  let fenceMarker = '';
  let titleIndex = -1;
  let title = '';

  for (const [index, line] of lines.entries()) {
    const fence = line.match(/^\s*(```+|~~~+)/);
    if (fence !== null) {
      if (!fenced) {
        fenced = true;
        fenceMarker = fence[1][0];
      } else if (fence[1][0] === fenceMarker) {
        fenced = false;
        fenceMarker = '';
      }
      continue;
    }
    if (!fenced) {
      const heading = line.match(/^#\s+(.+?)\s*$/);
      if (heading !== null) {
        titleIndex = index;
        title = heading[1].replace(/\s+#+\s*$/, '').trim();
        break;
      }
    }
  }

  if (titleIndex === -1 || title === '') {
    throw new Error(`Documentation page requires a non-empty level-one title: ${source}`);
  }

  lines.splice(titleIndex, 1);
  return { title, body: lines.join('\n') };
}

function slugFor(source) {
  const withoutExtension = source.slice(0, -3);
  const segments = withoutExtension.split('/');
  if (segments.at(-1) === 'README') {
    segments[segments.length - 1] = 'index';
  }

  return segments.join('/');
}

function routeFor(slug) {
  return slug === 'index' ? '/' : `/${slug}/`;
}

function assertUniqueSlugs(records) {
  const slugs = new Map();
  for (const record of records) {
    const existing = slugs.get(record.slug);
    if (existing !== undefined) {
      throw new Error(`Duplicate documentation slug "${record.slug}": ${existing}, ${record.source}`);
    }
    slugs.set(record.slug, record.source);
  }
}

function rewriteAndValidateLinks(record, bySource, routes) {
  return mapProseLines(record.body, (line) =>
    line.replace(MARKDOWN_LINK, (match, label, target) => {
      const normalized = normalizeLinkTarget(target);
      if (normalized === null || normalized.startsWith('#')) {
        return match;
      }
      if (normalized.startsWith('/')) {
        const route = normalized.split(/[?#]/, 1)[0];
        if (!routes.has(route.endsWith('/') ? route : `${route}/`)) {
          throw new Error(`Broken internal documentation link in ${record.source}: ${target}`);
        }
        return match;
      }

      const [pathPart, suffix = ''] = splitLinkSuffix(normalized);
      const resolved = path.posix.normalize(path.posix.join(path.posix.dirname(record.source), pathPart));
      if (resolved === '..' || resolved.startsWith('../') || path.posix.isAbsolute(resolved)) {
        throw new Error(`Documentation link resolves outside docs/guide in ${record.source}: ${target}`);
      }
      const linked = bySource.get(resolved);
      if (linked === undefined) {
        throw new Error(`Broken internal documentation link in ${record.source}: ${target}`);
      }

      return `${label}(${routeFor(linked.slug)}${suffix})`;
    }),
  );
}

function normalizeLinkTarget(target) {
  const trimmed = target.trim();
  const withoutTitle = trimmed.startsWith('<')
    ? trimmed.slice(1, trimmed.indexOf('>'))
    : trimmed.split(/\s+/, 1)[0];
  if (withoutTitle === '' || EXTERNAL_TARGET.test(withoutTitle)) {
    return null;
  }

  try {
    return decodeURIComponent(withoutTitle);
  } catch {
    throw new Error(`Documentation link contains invalid URL encoding: ${target}`);
  }
}

function splitLinkSuffix(target) {
  const index = target.search(/[?#]/);
  return index === -1 ? [target, ''] : [target.slice(0, index), target.slice(index)];
}

function mapProseLines(markdown, transform) {
  const lines = markdown.split('\n');
  let fenced = false;
  let fenceMarker = '';

  return lines
    .map((line) => {
      const fence = line.match(/^\s*(```+|~~~+)/);
      if (fence !== null) {
        if (!fenced) {
          fenced = true;
          fenceMarker = fence[1][0];
        } else if (fence[1][0] === fenceMarker) {
          fenced = false;
          fenceMarker = '';
        }
        return line;
      }

      return fenced ? line : transform(line);
    })
    .join('\n');
}

async function replaceGeneratedContent(contentRoot, manifestPath, outputs, manifest) {
  await rm(contentRoot, { recursive: true, force: true });
  await rm(path.dirname(manifestPath), { recursive: true, force: true });
  await mkdir(contentRoot, { recursive: true });
  await mkdir(path.dirname(manifestPath), { recursive: true });

  for (const output of outputs) {
    const target = path.join(contentRoot, ...output.generated.split('/'));
    await mkdir(path.dirname(target), { recursive: true });
    await writeFile(target, output.content, 'utf8');
  }
  await writeFile(manifestPath, manifest, 'utf8');
}

function normalizeNewlines(markdown) {
  return markdown.replace(/\r\n?/g, '\n');
}
