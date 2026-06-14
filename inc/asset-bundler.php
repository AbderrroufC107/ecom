<?php
/**
 * Asset Bundler - PHP-based CSS/JS combiner and minifier.
 *
 * Usage (CLI): php inc/asset-bundler.php
 * Usage (Web): /inc/asset-bundler.php?mode=css  (or &mode=js)
 *
 * Output: assets/dist/styles.min.css and assets/dist/app.min.js
 */

define('BASE_DIR', __DIR__ . '/..');
define('CSS_DIR', BASE_DIR . '/assets/css');
define('JS_DIR', BASE_DIR . '/assets/js');
define('DIST_DIR', BASE_DIR . '/assets/dist');
define('VENDOR_DIR', BASE_DIR . '/assets/vendor');

$mode = isset($_GET['mode']) ? $_GET['mode'] : 'all';

// Ensure output directories exist
if (!is_dir(DIST_DIR)) {
    mkdir(DIST_DIR, 0755, true);
}
if (!is_dir(VENDOR_DIR)) {
    mkdir(VENDOR_DIR, 0755, true);
}

// ---------------------------------------------------------------------------
// Minification helpers (lightweight, no external deps)
// ---------------------------------------------------------------------------
function minify_css($css) {
    // Remove comments
    $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
    // Remove whitespace around selectors, properties, braces
    $css = preg_replace('/\s*([{}|:;,>~+=])\s*/', '$1', $css);
    // Remove unnecessary semicolons before closing brace
    $css = preg_replace('/;}/', '}', $css);
    // Collapse whitespace
    $css = preg_replace('/\s+/', ' ', $css);
    // Trim leading/trailing spaces
    $css = trim($css);
    // Fix url() spacing
    $css = preg_replace('/\burl\s*\((\s*)(.*?)(\s*)\)/', 'url($2)', $css);
    return $css;
}

function minify_js($js) {
    // Remove single-line comments (but not inside strings)
    $js = preg_replace('/^\s*\/\/.*$/m', '', $js);
    // Remove multi-line comments
    $js = preg_replace('/\/\*[\s\S]*?\*\//', '', $js);
    // Collapse whitespace
    $js = preg_replace('/\s+/', ' ', $js);
    // Trim
    $js = trim($js);
    return $js;
}

// ---------------------------------------------------------------------------
// CSS Combiner
// ---------------------------------------------------------------------------
function build_css() {
    echo "[CSS] Building styles.min.css...\n";

    $files = [
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

    $combined = '';
    foreach ($files as $file) {
        $path = CSS_DIR . '/' . $file;
        if (is_file($path)) {
            $content = file_get_contents($path);
            $min = minify_css($content);
            $combined .= "/* {$file} */\n" . $min . "\n";
            echo "  + {$file} (" . strlen($content) . " -> " . strlen($min) . " bytes)\n";
        } else {
            echo "  - {$file} (skipped, not found)\n";
        }
    }

    $out = DIST_DIR . '/styles.min.css';
    file_put_contents($out, $combined, LOCK_EX);
    echo "[CSS] Wrote " . strlen($combined) . " bytes to {$out}\n";
}

// ---------------------------------------------------------------------------
// JS Combiner (order matters: vendor first, then libs, then app code)
// ---------------------------------------------------------------------------
function build_js() {
    echo "[JS] Building app.min.js...\n";

    ensure_react_vendor();

    $files = [
        VENDOR_DIR . '/react.production.min.js',
        VENDOR_DIR . '/react-dom.production.min.js',
        JS_DIR . '/bootstrap.min.js',
        JS_DIR . '/jquery.touchSwipe.min.js',
        JS_DIR . '/owl.carousel.min.js',
        JS_DIR . '/owl.animate.js',
        JS_DIR . '/bootstrap-touch-slider-min.js',
        JS_DIR . '/jquery.magnific-popup.min.js',
        JS_DIR . '/rating.js',
        JS_DIR . '/select2.full.min.js',
        JS_DIR . '/megamenu.js',
        JS_DIR . '/wilayas-communes.js',
        JS_DIR . '/site-security-device.js',
        JS_DIR . '/custom.js',
    ];

    $combined = '';
    foreach ($files as $path) {
        $name = basename($path);
        if (is_file($path)) {
            $content = file_get_contents($path);
            $min = minify_js($content);
            $combined .= "(function(){/* {$name} */\n" . $min . "\n})();\n";
            echo "  + {$name} (" . strlen($content) . " -> " . strlen($min) . " bytes)\n";
        } else {
            echo "  - {$name} (skipped, not found)\n";
        }
    }

    $out = DIST_DIR . '/app.min.js';
    file_put_contents($out, $combined, LOCK_EX);
    echo "[JS] Wrote " . strlen($combined) . " bytes to {$out}\n";
}

// ---------------------------------------------------------------------------
// Download React vendor files locally (avoids unpkg CDN at runtime)
// ---------------------------------------------------------------------------
function ensure_react_vendor() {
    $target_r = VENDOR_DIR . '/react.production.min.js';
    $target_d = VENDOR_DIR . '/react-dom.production.min.js';

    if (is_file($target_r) && is_file($target_d)) {
        echo "  [vendor] React already cached locally.\n";
        return;
    }

    echo "  [vendor] Downloading React from unpkg...\n";

    $react_url = 'https://unpkg.com/react@18/umd/react.production.min.js';
    $dom_url   = 'https://unpkg.com/react-dom@18/umd/react-dom.production.min.js';

    $r = @file_get_contents($react_url);
    if ($r !== false) {
        file_put_contents($target_r, $r, LOCK_EX);
        echo "  [vendor] Downloaded react.production.min.js\n";
    } else {
        echo "  [vendor] WARNING: Failed to download React. Place it manually at: {$target_r}\n";
    }

    $d = @file_get_contents($dom_url);
    if ($d !== false) {
        file_put_contents($target_d, $d, LOCK_EX);
        echo "  [vendor] Downloaded react-dom.production.min.js\n";
    } else {
        echo "  [vendor] WARNING: Failed to download ReactDOM. Place it manually at: {$target_d}\n";
    }
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------
$is_cli = (php_sapi_name() === 'cli');

if ($mode === 'css' || $mode === 'all') {
    build_css();
}
if ($mode === 'js' || $mode === 'all') {
    build_js();
}

if ($is_cli) {
    echo "\nDone. Files in: " . realpath(DIST_DIR) . "\n";
} else {
    echo "<pre>Done. Files in: " . realpath(DIST_DIR) . "</pre>";
}
