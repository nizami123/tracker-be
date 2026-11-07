import { settings } from './store.js';
export function applyTheme() {
  document.documentElement.dataset.theme = settings.get().dark ? 'dark' : 'light';
  const meta = document.querySelector('meta[name="theme-color"]');
  if (meta) meta.content = settings.get().dark ? '#0E1116' : '#2F6FED';
}
