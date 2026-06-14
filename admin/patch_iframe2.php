<?php
$header_file = 'header.php';
$header_content = file_get_contents($header_file);

// Remove the previous iframe patch
$header_content = preg_replace('/<\?php if\(isset\(\$_GET\[\'iframe\'\]\)\): \?>.*?<\?php endif; \?>/s', '', $header_content);

// Add the robust JS-based iframe detection
$style_to_add = '<script>
if (window.self !== window.top) {
    document.write(\'<style>.main-header, .main-sidebar, .main-footer { display: none !important; } .content-wrapper { margin-left: 0 !important; margin-top: 0 !important; padding-top: 0 !important; z-index: 9999; } body { background: #ecf0f5; padding-top: 0 !important; }</style>\');
}
</script>
</head>';

$header_content = str_replace('</head>', $style_to_add, $header_content);
file_put_contents($header_file, $header_content);
echo "Patched header with JS iframe detection";
