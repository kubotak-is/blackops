import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';

export default defineConfig({
  site: 'https://blackops-docs.pages.dev',
  integrations: [
    starlight({
      title: 'BlackOps',
      locales: {
        root: {
          label: '日本語',
          lang: 'ja',
        },
      },
    }),
  ],
});
