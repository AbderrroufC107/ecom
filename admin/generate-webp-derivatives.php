<?php
/**
 * Generates the responsive WebP derivatives (-w480/-w768/-w1024/-w1280.webp)
 * that get_front_optimized_image_url()/build_webp_srcset() already look for.
 *
 * Most uploaded product photos never had these generated (the old
 * _gen_webp.py at the project root was a stub), so every page fell back to
 * proxying the original — sometimes 5-6 MB PNGs straight from a phone —
 * through wsrv.nl on every request. That round trip is the main cause of the
 * 40s LCP / 7+ MB page weight seen in Lighthouse.
 *
 * Safe to re-run: skips any derivative that already exists on disk.
 * Run via CLI: php admin/generate-webp-derivatives.php
 */

if (php_sapi_name() !== 'cli' && !isset($_GET['run_setup'])) {
    http_response_code(403);
    exit('Forbidden. Run via CLI: php admin/generate-webp-derivatives.php');
}

if (!extension_loaded('gd')) {
    exit("GD extension is not available on this PHP. Ask your host to enable it, then re-run.\n");
}

$uploadsDir = realpath(__DIR__ . '/../assets/uploads');
if ($uploadsDir === false) {
    exit("assets/uploads not found.\n");
}

$widths = [480, 768, 1024, 1280];
$quality = 72;

$files = array_merge(
    glob($uploadsDir . '/*.jpg') ?: [],
    glob($uploadsDir . '/*.jpeg') ?: [],
    glob($uploadsDir . '/*.JPG') ?: [],
    glob($uploadsDir . '/*.JPEG') ?: [],
    glob($uploadsDir . '/*.png') ?: [],
    glob($uploadsDir . '/*.PNG') ?: []
);

$sourceCount = 0;
$derivativesGenerated = 0;
$derivativesSkipped = 0;
$bytesBefore = 0;
$bytesAfter = 0;
$failed = [];

foreach ($files as $path) {
    $filename = basename($path);
    $base = pathinfo($filename, PATHINFO_FILENAME);
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    // Don't try to re-derive from an already-generated derivative.
    if (preg_match('/-w\d+$/', $base)) {
        continue;
    }

    $dims = @getimagesize($path);
    if (!$dims || $dims[0] <= 0 || $dims[1] <= 0) {
        $failed[] = $filename . ' (unreadable dimensions)';
        continue;
    }
    [$origWidth, $origHeight] = $dims;

    $targetWidths = array_values(array_unique(array_filter($widths, static fn($w) => $w <= $origWidth)));
    if (empty($targetWidths)) {
        // Image is smaller than every target width: still emit one derivative at its native size.
        $targetWidths = [$origWidth];
    }

    $pendingWidths = array_filter($targetWidths, function ($w) use ($uploadsDir, $base) {
        $outPath = $uploadsDir . '/' . $base . '-w' . $w . '.webp';
        if (is_file($outPath)) {
            $GLOBALS['derivativesSkipped']++;
            return false;
        }
        return true;
    });
    if (empty($pendingWidths)) {
        continue;
    }

    $image = $ext === 'png' ? @imagecreatefrompng($path) : @imagecreatefromjpeg($path);
    if (!$image) {
        $failed[] = $filename . ' (could not decode)';
        continue;
    }
    imagepalettetotruecolor($image);
    imagealphablending($image, true);
    imagesavealpha($image, true);

    $sourceCount++;
    $originalSize = filesize($path);

    foreach ($pendingWidths as $w) {
        $h = max(1, (int) round($origHeight * ($w / $origWidth)));
        $resized = imagescale($image, $w, $h, IMG_BICUBIC);
        if (!$resized) {
            $failed[] = $filename . " (resize to {$w}w failed)";
            continue;
        }
        imagealphablending($resized, false);
        imagesavealpha($resized, true);

        $outPath = $uploadsDir . '/' . $base . '-w' . $w . '.webp';
        if (imagewebp($resized, $outPath, $quality)) {
            $derivativesGenerated++;
            $bytesBefore += $originalSize;
            $bytesAfter += filesize($outPath);
            echo "  {$filename} -> " . basename($outPath) . ' (' . round(filesize($outPath) / 1024) . " KiB)\n";
        } else {
            $failed[] = $filename . " (webp write failed for {$w}w)";
        }
        imagedestroy($resized);
    }
    imagedestroy($image);
}

echo "\n=== Done ===\n";
echo "Source images scanned: " . count($files) . "\n";
echo "Images processed: {$sourceCount}\n";
echo "Derivatives generated: {$derivativesGenerated}\n";
echo "Derivatives already existed (skipped): {$derivativesSkipped}\n";
if ($bytesBefore > 0) {
    echo 'Approx. size for the originals behind new derivatives: ' . round($bytesBefore / 1048576, 1) . " MiB\n";
    echo 'Size of the new WebP derivatives: ' . round($bytesAfter / 1048576, 1) . " MiB\n";
}
if (!empty($failed)) {
    echo "\nFailed (" . count($failed) . "):\n";
    foreach ($failed as $f) {
        echo "  - {$f}\n";
    }
}
