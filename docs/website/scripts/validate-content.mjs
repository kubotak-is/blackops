import { copyFile, mkdir, rm, stat } from 'node:fs/promises';
import path from 'node:path';
import { spawn } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const source = path.join(root, 'src/content/docs/assets/community-board/blackops-board.png');
const targetDirectory = path.join(root, 'public/assets/community-board');
const target = path.join(targetDirectory, 'blackops-board.png');
let createdFile = false;
const createdDirectories = [];

try {
  try { await stat(target); } catch {
    let directory = targetDirectory;
    const missing = [];
    while (directory !== root) {
      try { await stat(directory); break; } catch { missing.push(directory); directory = path.dirname(directory); }
    }
    await mkdir(targetDirectory, { recursive: true });
    createdDirectories.push(...missing);
    await copyFile(source, target);
    createdFile = true;
  }
  const command = process.platform === 'win32' ? 'blume.cmd' : 'blume';
  const result = await new Promise((resolve) => {
    const child = spawn(path.join(root, 'node_modules/.bin', command), ['validate', '--strict'], { cwd: root, stdio: 'inherit' });
    child.on('close', (code, signal) => resolve(signal ? 1 : (code ?? 1)));
  });
  process.exitCode = result;
} finally {
  if (createdFile) await rm(target, { force: true });
  for (const directory of createdDirectories) await rm(directory, { recursive: false, force: true }).catch(() => {});
}
