<?php require_once('header.php'); ?>

<?php
$page_content = front_get_page_content($pdo);
$about_title = $page_content['about_title'] ?? '';
$about_content = $page_content['about_content'] ?? '';
$about_banner = $page_content['about_banner'] ?? '';
$about_banner_url = trim((string)get_front_image_url($about_banner));
?>

<div class="page-banner"<?php if ($about_banner_url !== ''): ?> style="background-image: url('<?php echo htmlspecialchars($about_banner_url, ENT_QUOTES, 'UTF-8'); ?>');"<?php endif; ?>>
    <div class="inner">
        <h1><?php echo htmlspecialchars($about_title, ENT_QUOTES, 'UTF-8'); ?></h1>
    </div>
</div>

<div class="page">
    <div class="container">
        <div class="row">            
            <div class="col-md-12">
                
                <p>
                    <?php echo $about_content; ?>
                </p>

            </div>
        </div>
    </div>
</div>

<?php require_once('footer.php'); ?>
