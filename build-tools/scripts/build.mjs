/**
 * Production build script for asset bundling.
 * Requires Node.js 18+ and npm dependencies installed.
 *
 * Usage: node scripts/build.mjs
 *
 * Bundles:
 *   - assets/css/*.css        → assets/dist/styles.min.css
 *   - assets/vendor/react*js   → assets/dist/app.min.js (vendor)
 *   - assets/js/*.js           → assets/dist/app.min.js (legacy app code)
 */

import { readFileSync, writeFileSync, existsSync, mkdirSync, copyFileSync } from 'fs';
import { resolve, dirname } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(__dirname, '..', '..');
const CSS_DIR = resolve(ROOT, 'assets', 'css');
const JS_DIR = resolve(ROOT, 'assets', 'js');
const VENDOR_DIR = resolve(ROOT, 'assets', 'vendor');
const DIST_DIR = resolve(ROOT, 'assets', 'dist');
const NM_DIR = resolve(__dirname, '..', 'node_modules');

const IS_VERBOSE = process.argv.includes('--verbose');

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
function log(msg) {
  process.stdout.write(msg + '\n');
}

function bytes(bytes) {
  const units = ['B', 'KB', 'MB'];
  let i = 0;
  let val = bytes;
  while (val >= 1024 && i < units.length - 1) { val /= 1024; i++; }
  return val.toFixed(1) + ' ' + units[i];
}

// ---------------------------------------------------------------------------
// Step 1: Bundle CSS
// ---------------------------------------------------------------------------
function buildCSS() {
  log('\n--- CSS Bundle ---');

  const files = [
    'bootstrap.min.css',
    'font-awesome.min.css',
    'spacing.css',
    'main.css',
    'responsive.css',
    'owl.carousel.min.css',
    'owl.theme.default.min.css',
    'magnific-popup.css',
    'bootstrap-touch-slider.css',
    'rating.css',
    'animate.min.css',
    'tree-menu.css',
    'select2.min.css',
  ];

  let combined = '';
  let rawTotal = 0;

  for (const file of files) {
    const path = resolve(CSS_DIR, file);
    if (!existsSync(path)) {
      log(`  [skip] ${file}`);
      continue;
    }
    const code = readFileSync(path, 'utf-8');
    combined += `/* ${file} */\n${code}\n`;
    rawTotal += code.length;
    log(`  + ${file} (${bytes(code.length)})`);
  }

  const out = resolve(DIST_DIR, 'styles.min.css');
  mkdirSync(DIST_DIR, { recursive: true });
  writeFileSync(out, combined, 'utf-8');

  log(`\n  Raw total: ${bytes(rawTotal)}`);
  log(`  Output: ${out} (${bytes(combined.length)})`);

  // Minify with esbuild if available
  try {
    const esbuild = await import('esbuild');
    const result = await esbuild.transform(combined, {
      loader: 'css',
      minify: true,
    });
    writeFileSync(out, result.code, 'utf-8');
    log(`  Minified: ${bytes(result.code.length)} (${Math.round((1 - result.code.length / rawTotal) * 100)}% reduction)`);
  } catch {
    log('  [info] esbuild not available; CSS written unminified.');
    log('  To minify: npm install esbuild');
  }
}

// ---------------------------------------------------------------------------
// Step 2: Vendor JS (React from node_modules)
// ---------------------------------------------------------------------------
function copyVendorReact() {
  const reactUmd = resolve(NM_DIR, 'react', 'umd', 'react.production.min.js');
  const domUmd   = resolve(NM_DIR, 'react-dom', 'umd', 'react-dom.production.min.js');

  mkdirSync(VENDOR_DIR, { recursive: true });

  if (existsSync(reactUmd) && existsSync(domUmd)) {
    copyFileSync(reactUmd, resolve(VENDOR_DIR, 'react.production.min.js'));
    copyFileSync(domUmd,   resolve(VENDOR_DIR, 'react-dom.production.min.js'));
    log(`  + react.production.min.js (${bytes(readFileSync(reactUmd).length)})`);
    log(`  + react-dom.production.min.js (${bytes(readFileSync(domUmd).length)})`);
    return true;
  }

  log('  [warn] React UMD not found in node_modules.');
  log('  Run: npm install  (inside build-tools/)');
  return false;
}

// ---------------------------------------------------------------------------
// Step 3: Bundle JS (React vendor + legacy app code)
// ---------------------------------------------------------------------------
function buildJS() {
  log('\n--- JS Bundle ---');

  const vendorFiles = [
    'react.production.min.js',
    'react-dom.production.min.js',
  ];

  const appFiles = [
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
    'index-react-home.js',
    'category-react-page.js',
  ];

  let combined = '';
  let rawTotal = 0;

  // Vendor first
  for (const file of vendorFiles) {
    const path = resolve(VENDOR_DIR, file);
    if (!existsSync(path)) {
      log(`  [skip vendor] ${file} — not found in assets/vendor/`);
      log(`  Run CSS combiner first or download React manually.`);
      continue;
    }
    const code = readFileSync(path, 'utf-8');
    combined += `${code}\n`;
    rawTotal += code.length;
    log(`  [vendor] + ${file} (${bytes(code.length)})`);
  }

  // Legacy app code
  for (const file of appFiles) {
    const path = resolve(JS_DIR, file);
    if (!existsSync(path)) {
      log(`  [skip] ${file}`);
      continue;
    }
    const code = readFileSync(path, 'utf-8');
    combined += `;(function(){/* ${file} */\n${code}\n})();\n`;
    rawTotal += code.length;
    log(`  + ${file} (${bytes(code.length)})`);
  }

  const out = resolve(DIST_DIR, 'app.min.js');
  writeFileSync(out, combined, 'utf-8');
  log(`\n  Raw total: ${bytes(rawTotal)}`);
  log(`  Output: ${out} (${bytes(combined.length)})`);

  // Minify with terser if available
  try {
    const { minify } = await import('terser');
    const result = await minify(combined, {
      compress: { drop_console: false },
      mangle: true,
      format: { comments: false },
    });
    if (result.code) {
      writeFileSync(out, result.code, 'utf-8');
      log(`  Minified: ${bytes(result.code.length)} (${Math.round((1 - result.code.length / rawTotal) * 100)}% reduction)`);
    }
  } catch {
    log('  [info] terser not available; JS written unminified.');
    log('  To minify: npm install terser');
  }
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------
async function main() {
  log('=== ECOM Asset Bundle Build ===\n');

  mkdirSync(DIST_DIR, { recursive: true });

  buildCSS();
  log('');
  copyVendorReact();
  buildJS();

  log('\n=== Done ===');
  log(`Output directory: ${DIST_DIR}`);

  const stylesSize = existsSync(resolve(DIST_DIR, 'styles.min.css'))
    ? readFileSync(resolve(DIST_DIR, 'styles.min.css')).length
    : 0;
  const appSize = existsSync(resolve(DIST_DIR, 'app.min.js'))
    ? readFileSync(resolve(DIST_DIR, 'app.min.js')).length
    : 0;

  log(`  styles.min.css: ${bytes(stylesSize)}`);
  log(`  app.min.js:     ${bytes(appSize)}`);
  log(`  Total:          ${bytes(stylesSize + appSize)}`);
}

main().catch(console.error);
