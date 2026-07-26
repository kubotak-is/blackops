import { defineComponents } from 'blume';
import NoEditLayout from './components/NoEditLayout.astro';

export default defineComponents({
  layout: { Layout: NoEditLayout },
});
