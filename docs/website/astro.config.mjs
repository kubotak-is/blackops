import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';
import mermaid from 'astro-mermaid';
import { sidebar } from './site-navigation.mjs';

export default defineConfig({
  site: 'https://blackops-docs.pages.dev',
  image: {
    service: { entrypoint: 'astro/assets/services/noop' },
  },
  integrations: [
    mermaid({
      autoTheme: true,
      mermaidConfig: {
        securityLevel: 'strict',
      },
    }),
    starlight({
      title: 'BlackOps',
      description: 'BlackOps Framework利用者向けドキュメント',
      customCss: ['./src/styles/diagram-responsive.css'],
      locales: {
        root: {
          label: '日本語',
          lang: 'ja',
        },
      },
      sidebar,
    }),
  ],
});
