<?php
$file = 'landing_page.php';
$content = file_get_contents($file);

// Add strong CSS to ensure wilaya and commune are side by side
$extra_css = <<<'CSS'
/* Force wilaya + commune side by side */
.form-row {
    display: flex !important;
    flex-wrap: nowrap !important;
    gap: 10px;
    margin-bottom: 15px;
}
.form-row > .form-group {
    flex: 1 1 0 !important;
    min-width: 0 !important;
    padding: 0 !important;
    margin-bottom: 0 !important;
}
@media (max-width: 480px) {
    .form-row {
        flex-wrap: wrap !important;
    }
    .form-row > .form-group {
        flex: 1 1 100% !important;
    }
}
CSS;

$content = str_replace('/* Force wilaya + commune side by side */', '', $content);
$content = str_replace('/* Delivery Banner */', $extra_css . "\n\n/* Delivery Banner */", $content);

file_put_contents($file, $content);
echo "Done";
?>
