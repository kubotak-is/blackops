import { generateContent } from './content-pipeline.mjs';
import { contentRoot, manifestPath, repositoryRoot, sourceRoot } from './website-paths.mjs';
import { contentMap, versionBanner } from '../content-map.mjs';
import { validateNavigation } from '../site-navigation.mjs';

validateNavigation(contentMap);
await generateContent({ sourceRoot, contentRoot, manifestPath, repositoryRoot, contentMap, banner: versionBanner });
console.log('Generated Starlight content from docs/guide.');
