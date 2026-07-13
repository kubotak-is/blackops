import path from 'node:path';
import { fileURLToPath } from 'node:url';

export const websiteRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
export const repositoryRoot = path.resolve(websiteRoot, '../..');
export const sourceRoot = path.join(repositoryRoot, 'docs/guide');
export const contentRoot = path.join(websiteRoot, 'src/content/docs');
export const generatedRoot = path.join(websiteRoot, '.generated');
export const manifestPath = path.join(generatedRoot, 'content-manifest.json');
export const distRoot = path.join(websiteRoot, 'dist');
