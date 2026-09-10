import { copyFile, mkdir, readFile, rm, rmdir, stat } from 'node:fs/promises';
import path from 'node:path';
import { spawn } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { loadDiagramManifest } from './archify-diagrams.mjs';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const boardSource = path.join(root, 'src/content/docs/assets/community-board/blackops-board.png');
const boardTarget = path.join(root, 'public/assets/community-board/blackops-board.png');
const diagramManifest = await loadDiagramManifest();
const stagedAssets = [
  { source: boardSource, target: boardTarget },
  ...diagramManifest.diagrams.map((entry) => {
    const relative = path.posix.relative('docs/guide/assets', entry.pngPath);
    return {
      source: path.join(root, 'src/content/docs/assets', ...relative.split('/')),
      target: path.join(root, 'public/assets', ...relative.split('/')),
    };
  }),
];
const createdFiles = [];
const createdDirectories = [];

try {
  for (const { source, target } of stagedAssets) await stageAsset({ source, target });
  const command = process.platform === 'win32' ? 'blume.cmd' : 'blume';
  const result = await new Promise((resolve) => {
    let settled = false;
    const finish = (code) => {
      if (settled) return;
      settled = true;
      resolve(code);
    };
    try {
      const child = spawn(path.join(root, 'node_modules/.bin', command), ['validate', '--strict'], { cwd: root, stdio: 'inherit' });
      child.once('error', (error) => {
        console.error(`Unable to start Blume validation: ${error.message}`);
        finish(1);
      });
      child.once('close', (code, signal) => finish(signal ? 1 : (code ?? 1)));
    } catch (error) {
      console.error(`Unable to start Blume validation: ${error.message}`);
      finish(1);
    }
  });
  process.exitCode = result;
} finally {
  for (const file of createdFiles) await rm(file, { force: true });
  for (const directory of [...new Set(createdDirectories)].sort((left, right) => right.length - left.length)) {
    try {
      await rmdir(directory);
    } catch (error) {
      if (!['ENOENT', 'ENOTEMPTY'].includes(error?.code)) throw error;
    }
  }
}

async function stageAsset({ source, target }) {
  try {
    await stat(target);
    const [expected, actual] = await Promise.all([readFile(source), readFile(target)]);
    if (!expected.equals(actual)) throw new Error(`Existing staged asset differs from its source: ${target}`);
    return;
  } catch (error) {
    if (error?.code !== 'ENOENT') throw error;
  }

  const targetDirectory = path.dirname(target);
  const missing = [];
  let directory = targetDirectory;
  while (directory !== root) {
    try {
      await stat(directory);
      break;
    } catch (error) {
      if (error?.code !== 'ENOENT') throw error;
      missing.push(directory);
      directory = path.dirname(directory);
    }
  }
  await mkdir(targetDirectory, { recursive: true });
  createdDirectories.push(...missing);
  await copyFile(source, target);
  createdFiles.push(target);
}
