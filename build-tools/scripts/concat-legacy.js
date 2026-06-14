/**
 * Pre-build script: concatenates legacy JS files in dependency order
 * into a temporary file that Vite can process as a module entry.
 *
 * Usage: node scripts/concat-legacy.js
 */

import { readFileSync, writeFileSync, existsSync, mkdirSync } from 'fs';
import { resolve, dirname } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(__dirname, '..');
const JS_DIR = resolve(ROOT, '..', 'assets', 'js');
const VENDOR_DIR = resolve(ROOT, '..', 'assets', 'vendor');
const DIST_DIR = resolve(ROOT, '..', 'assets', 'dist');
const TMP_DIR = resolve(ROOT, 'tmp');

if (!existsSync(TMP_DIR)) mkdirSync(TMP_DIR, { recursive: true });
if (!existsSync(DIST_DIR)) mkdirSync(DIST_DIR, { recursive: true });

// Order matters: dependencies first
const LEGACY_FILES = [
  // jQuery plugins (bootstrap depends on jquery - loaded via CDN or bundled separately)
  'bootstrap.min.js',
  'jquery.touchSwipe.min.js',
  'owl.carousel.min.js',
  'owl.animate.js',
  'bootstrap-touch-slider-min.js',
  'jquery.magnific-popup.min.js',
  'rating.js',
  'select2.full.min.js',
  'megamenu.js',
  'wilayas-communes.js',
  'site-security-device.js',
  'custom.js',
  // React-based app scripts (loaded separately as modules)
  'index-react-home.js',
  'category-react-page.js',
];

let bundle = '';
let totalBytes = 0;

for (const file of LEGACY_FILES) {
  const fullPath = resolve(JS_DIR, file);
  if (!existsSync(fullPath)) {
    console.warn(`  [skip] ${file} not found`);
    continue;
  }
  const code = readFileSync(fullPath, 'utf-8');
  // Wrap non-module scripts in IIFE to avoid global conflicts
  bundle += `// ${file}\n(function(){\n${code}\n})();\n\n`;
  totalBytes += code.length;
  console.log(`  + ${file} (${code.length} bytes)`);
}

const tmpFile = resolve(TMP_DIR, 'legacy-bundle.js');
writeFileSync(tmpFile, bundle, 'utf-8');
console.log(`\nWrote ${totalBytes} bytes → ${tmpFile}`);
