import { generateContent } from './content-pipeline.mjs';
import { contentRoot, manifestPath, repositoryRoot, sourceRoot } from './website-paths.mjs';
import { contentMap } from '../content-map.mjs';
import { validateNavigation } from '../site-navigation.mjs';

validateNavigation(contentMap);
await generateContent({ sourceRoot, contentRoot, manifestPath, repositoryRoot, contentMap });
console.log('Generated Blume content from docs/guide.');
