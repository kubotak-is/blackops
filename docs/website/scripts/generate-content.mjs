import { generateContent } from './content-pipeline.mjs';
import { contentRoot, manifestPath, repositoryRoot, sourceRoot } from './website-paths.mjs';

await generateContent({ sourceRoot, contentRoot, manifestPath, repositoryRoot });
console.log('Generated Starlight content from docs/guide.');
