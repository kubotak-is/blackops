import { defineConfig } from 'blume';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { blumeSidebar } from './site-navigation.mjs';

const websiteRoot = path.dirname(fileURLToPath(import.meta.url));
const localFont = (file: string) => path.join(websiteRoot, 'public/fonts', file);

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
    fonts: {
      body: {
        name: 'Ubuntu Sans',
        variants: [{ src: localFont('UbuntuSans.ttf'), weight: '400..700' }],
      },
      display: {
        name: 'Ubuntu Sans',
        variants: [{ src: localFont('UbuntuSans.ttf'), weight: '400..700' }],
      },
      mono: {
        name: 'Ubuntu Mono',
        variants: [{ src: localFont('UbuntuMono.ttf'), weight: '400..700' }],
      },
    },
  },
  i18n: {
    locales: [{ code: 'ja', label: '日本語' }],
    defaultLocale: 'ja',
    hideDefaultLocalePrefix: true,
    ui: {
      ja: {
        actions: {
          copied: 'コピーしました',
          copyCode: 'コードをコピー',
          edit: 'GitHub で編集',
          export: 'エクスポート',
          exportEpub: 'EPUBへエクスポート',
          exportPdf: 'PDFへエクスポート',
          generating: '生成中…',
        },
        nav: {
          closeNavigation: 'ナビゲーションを閉じる',
          githubRepository: 'GitHub リポジトリ',
          navigation: 'ナビゲーション',
          primary: 'メインナビゲーション',
          sections: 'セクション',
          toggleNavigation: 'ナビゲーションを開閉',
          toggleTheme: 'カラーテーマを切り替え',
        },
        page: {
          next: '次へ',
          pagination: 'ページネーション',
          previous: '前へ',
          skipToContent: '本文へ移動',
        },
        search: {
          button: '検索',
          label: 'ドキュメントを検索',
          placeholder: 'ドキュメントを検索…',
        },
      },
    },
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
