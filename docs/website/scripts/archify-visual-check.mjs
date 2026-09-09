import path from 'node:path';
import { pathToFileURL } from 'node:url';

const [skillRoot, artifactPath, ...options] = process.argv.slice(2);
if (!skillRoot || !artifactPath || options.some((option) => option !== '--json')) {
  throw new Error('Usage: node archify-visual-check.mjs <pinned-archify-root> <delivered.html> [--json]');
}

const { ChromeVisualBrowser, runVisualCheck } = await import(
  pathToFileURL(path.join(path.resolve(skillRoot), 'bin/visual-check.mjs')).href,
);
const result = await runVisualCheck({
  artifactPath,
  chromePath: process.env.ARCHIFY_CHROME,
  browserFactory: async (executable) => ({
    async inspect(observation) {
      const browser = new ChromeVisualBrowser(executable);
      try {
        return await browser.inspect(observation);
      } finally {
        await browser.close();
      }
    },
  }),
});

console.log(JSON.stringify(result.receipt, null, 2));
process.exitCode = result.exitCode;
