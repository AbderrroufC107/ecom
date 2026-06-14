<?php
$file = 'landing_page.php';
$content = file_get_contents($file);

$replacement = <<<'HTML'
<?php if (!empty($display_carousel_photos)): ?>
    <?php 
    $photo = $display_carousel_photos[0]; 
    $photo_url = $carousel_photo_optimized_urls[$photo] ?? get_front_optimized_image_url($photo, 980, 58);
    $photo_dims = $carousel_photo_sizes[$photo] ?? get_image_dimensions($photo, 1200, 1200);
    $photo_webp = resolve_webp_src($photo);
    $photo_webp_srcset = $carousel_photo_srcsets[$photo] ?? build_webp_srcset($photo);
    $photo_fallback_srcset = $carousel_photo_img_srcsets[$photo] ?? '';
    ?>
    <section class="landing-single-image" style="margin-top: 20px; margin-bottom: 20px;">
        <div class="container">
            <div class="landing-carousel-frame" style="background: transparent; box-shadow: none;">
                <?php if (!empty($product_name)): ?>
                    <h1 class="landing-carousel-title" style="text-align: center; font-weight: 900; margin-bottom: 15px; font-size: 1.5rem; color: #111827;"><?= htmlspecialchars($product_name) ?></h1>
                <?php endif; ?>
                <div style="width: 100%; display: flex; justify-content: center;">
                    <?php if ($photo_webp !== ''): ?>
                        <picture style="width: 100%; max-width: 800px;">
                            <source type="image/webp"
                                    srcset="<?= htmlspecialchars($photo_webp_srcset !== '' ? $photo_webp_srcset : $photo_webp) ?>"
                                    sizes="(max-width: 768px) 92vw, 800px">
                            <img src="<?= htmlspecialchars($photo_url, ENT_QUOTES, 'UTF-8') ?>"
                                 alt="<?= htmlspecialchars($product_name) ?>"
                                 width="<?= (int)($photo_dims['width'] ?? 1200) ?>"
                                 height="<?= (int)($photo_dims['height'] ?? 1200) ?>"
                                 loading="eager"
                                 decoding="sync"
                                 style="width: 100%; height: auto; border-radius: 12px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);"
                                 <?= $photo_fallback_srcset !== '' ? 'srcset="' . htmlspecialchars($photo_fallback_srcset, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                                 sizes="(max-width: 768px) 92vw, 800px">
                        </picture>
                    <?php else: ?>
                        <img src="<?= htmlspecialchars($photo_url, ENT_QUOTES, 'UTF-8') ?>"
                             alt="<?= htmlspecialchars($product_name) ?>"
                             width="<?= (int)($photo_dims['width'] ?? 1200) ?>"
                             height="<?= (int)($photo_dims['height'] ?? 1200) ?>"
                             loading="eager"
                             decoding="sync"
                             style="width: 100%; height: auto; border-radius: 12px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);"
                             <?= $photo_fallback_srcset !== '' ? 'srcset="' . htmlspecialchars($photo_fallback_srcset, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                             sizes="(max-width: 768px) 92vw, 800px">
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
<?php endif; ?>
HTML;

// Find the whole landing-carousel section
$pattern = '/<\?php if \(!empty\(\$display_carousel_photos\)\): \?>\s*<\?php \$landing_carousel_count = count\(\$display_carousel_photos\); \?>\s*<section class="landing-carousel.*?<\/section>\s*<\?php endif; \?>/s';

$content = preg_replace($pattern, $replacement, $content, 1);

file_put_contents($file, $content);
echo "Done";
?>
