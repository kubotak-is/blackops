import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';
import { sidebar } from './site-navigation.mjs';

export default defineConfig({
  site: 'https://blackops-docs.pages.dev',
  integrations: [
    starlight({
      title: 'BlackOps',
      description: 'BlackOps Framework利用者向けドキュメント',
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
