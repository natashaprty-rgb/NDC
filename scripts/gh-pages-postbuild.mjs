// Prepare dist/ for GitHub Pages: copy index.html to each client-side route so
// direct visits (e.g. /contact) return 200, add a 404 fallback, and skip Jekyll.
import { copyFileSync, mkdirSync, writeFileSync } from 'node:fs';

const routes = ['contact', 'projects/elms', 'projects/survey-management-system'];

for (const route of routes) {
  mkdirSync(`dist/${route}`, { recursive: true });
  copyFileSync('dist/index.html', `dist/${route}/index.html`);
}
copyFileSync('dist/index.html', 'dist/404.html');
writeFileSync('dist/.nojekyll', '');
