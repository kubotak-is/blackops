import { defineConfig } from 'blume';
import { blumeSidebar } from './site-navigation.mjs';

const experimentalNotice =
  'BlackOps1.xは試験的なバージョンです。Production Readyは2.xを予定しています。';

export default defineConfig({
  title: 'BlackOps - The PHP Framework',
  description: 'Headless Operation Framework for PHP 8.5.',
  logo: { text: 'BlackOps' },
  github: { owner: 'kubotak-is', repo: 'blackops' },
  content: {
    root: 'src/content/docs',
    pages: 'pages',
  },
  banner: {
    content: experimentalNotice,
    link: { href: '/releases/current-status/', text: 'Releases' },
    dismissible: false,
    id: 'blackops-1x-experimental',
  },
  navigation: {
    sidebar: {
      display: 'group',
      items: blumeSidebar,
    },
  },
  search: { provider: 'orama' },
  theme: {
    accent: { light: '#0f766e', dark: '#5eead4' },
    action: '#f97316',
    mode: 'system',
    radius: 'sm',
    fonts: { body: 'inter', display: 'ibm-plex-sans', mono: 'ibm-plex-mono' },
  },
  i18n: {
    locales: [{ code: 'ja', label: '日本語' }],
    defaultLocale: 'ja',
    hideDefaultLocalePrefix: true,
  },
  deployment: {
    output: 'static',
    site: 'https://blackops-php.pages.dev',
  },
  redirects: [
    { from: '/operations/lifecycle/', to: '/concepts/lifecycle/', status: 301 },
    { from: '/reference/security/', to: '/security/', status: 301 },
    { from: '/reference/troubleshooting/', to: '/troubleshooting/', status: 301 },
    { from: '/reference/current-status/', to: '/releases/current-status/', status: 301 },
  ],
  toc: { minHeadingLevel: 2, maxHeadingLevel: 3 },
  feedback: false,
  seo: {
    sitemap: true,
    robots: true,
    structuredData: true,
    og: { enabled: true },
  },
  ai: { llmsTxt: true },
});
