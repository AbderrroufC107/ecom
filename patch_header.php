<?php
$file = 'header.php';
$content = file_get_contents($file);

$search = '<?php if (!empty($facebook_pixel_id)): ?>';

$replace = '<?php
// Dynamic Product Pixels
$product_pixels = [];
$p_id_for_pixel = 0;
if (isset($_REQUEST[\'id\'])) {
    $p_id_for_pixel = (int)$_REQUEST[\'id\'];
} elseif (isset($_SESSION[\'purchase_pixel_data\'][\'content_ids\'][0])) {
    $p_id_for_pixel = (int)$_SESSION[\'purchase_pixel_data\'][\'content_ids\'][0];
}

if ($p_id_for_pixel > 0) {
    try {
        $stmt_px = $pdo->prepare("SELECT tp.* FROM tbl_pixel tp JOIN tbl_product_pixel tpp ON tp.id = tpp.pixel_id WHERE tpp.product_id = ?");
        $stmt_px->execute([$p_id_for_pixel]);
        $product_pixels = $stmt_px->fetchAll(PDO::FETCH_ASSOC);
    } catch(Exception $e) {}
}

if (!empty($product_pixels)) {
    foreach ($product_pixels as $px) {
        $network = strtolower($px[\'pixel_network\']);
        $pid = addslashes($px[\'pixel_id\']);
        if ($network === \'facebook\') {
            echo "<!-- Facebook Pixel Dynamic -->\n<script>\n!function(f,b,e,v,n,t,s)\n{if(f.fbq)return;n=f.fbq=function(){n.callMethod?\nn.callMethod.apply(n,arguments):n.queue.push(arguments)};\nif(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version=\'2.0\';\nn.queue=[];t=b.createElement(e);t.async=!0;\nt.src=v;s=b.getElementsByTagName(e)[0];\ns.parentNode.insertBefore(t,s)}(window, document,\'script\',\n\'https://connect.facebook.net/en_US/fbevents.js\');\nfbq(\'init\', \'{$pid}\');\nfbq(\'set\', \'autoConfig\', false, \'{$pid}\');\nfbq(\'track\', \'PageView\');\n</script>\n<noscript><img height=\"1\" width=\"1\" style=\"display:none\"\nsrc=\"https://www.facebook.com/tr?id={$pid}&amp;ev=PageView&amp;noscript=1\"\n/></noscript>\n<!-- End Facebook Pixel -->\n";
        } elseif ($network === \'tiktok\') {
            echo "<!-- TikTok Pixel Dynamic -->\n<script>\n!function (w, d, t) {\n  w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=[\"page\",\"track\",\"identify\",\"instances\",\"debug\",\"on\",\"off\",\"once\",\"ready\",\"alias\",\"group\",\"enableCookie\",\"disableCookie\",\"holdConsent\",\"revokeConsent\",\"grantConsent\"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(\nvar e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var r=\"https://analytics.tiktok.com/i18n/pixel/events.js\",o=n&&n.partner;ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=r,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};n=document.createElement(\"script\")\n;n.type=\"text/javascript\",n.async=!0,n.src=r+\"?sdkid=\"+e+\"&lib=\"+t;e=document.getElementsByTagName(\"script\")[0];e.parentNode.insertBefore(n,e)};\n  ttq.load(\'{$pid}\');\n  ttq.page();\n}(window, document, \'ttq\');\n</script>\n<!-- End TikTok Pixel -->\n";
        } elseif (!empty($px[\'pixel_script\'])) {
            echo "<!-- Custom Pixel Dynamic -->\n" . $px[\'pixel_script\'] . "\n<!-- End Custom Pixel -->\n";
        }
    }
}
?>
' . $search;

$content = str_replace($search, $replace, $content);
file_put_contents($file, $content);
echo "Patched header.php";
