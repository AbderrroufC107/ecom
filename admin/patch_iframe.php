<?php
// 1. Patch header.php
$header_file = 'header.php';
$header_content = file_get_contents($header_file);

$style_to_add = '<?php if(isset($_GET[\'iframe\'])): ?>
<style>
.main-header, .main-sidebar, .main-footer { display: none !important; }
.content-wrapper { margin-left: 0 !important; margin-top: 0 !important; padding-top: 0 !important; z-index: 9999; }
body { background: #ecf0f5; padding-top: 0 !important; }
</style>
<script>
// If inside iframe, when order-details saves, it might redirect to order-details.php without iframe=1
// We ensure iframe=1 is kept or we reload parent if we want.
</script>
<?php endif; ?>
</head>';

$header_content = str_replace('</head>', $style_to_add, $header_content);
file_put_contents($header_file, $header_content);


// 2. Patch order.php
$order_file = 'order.php';
$order_content = file_get_contents($order_file);

$modal_html = '
<div class="modal fade orders-page" id="orderDetailsModal" tabindex="-1" role="dialog" aria-labelledby="orderDetailsModalLabel">
    <div class="modal-dialog modal-lg" role="document" style="width: 95%; max-width: 1300px; margin: 20px auto;">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header" style="background: #f8fbfe; border-bottom: 1px solid #e8eef4;">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="font-size: 28px; margin-top: -5px;"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="orderDetailsModalLabel" style="font-weight: bold;"><i class="fa fa-address-card-o"></i> تفاصيل الطلب والمتابعة</h4>
            </div>
            <div class="modal-body" style="padding: 0; background: #ecf0f5;">
                <iframe id="orderDetailsIframe" src="" style="width: 100%; height: 80vh; border: none; display: block;"></iframe>
            </div>
        </div>
    </div>
</div>
<script>
window.addEventListener("load", function() {
    if (typeof jQuery === "undefined") return;
    (function($) {
        // Intercept clicks to order-details.php
        $(document).on("click", "a[href^=\'order-details.php\']", function(e) {
            e.preventDefault();
            var href = $(this).attr("href");
            var iframeSrc = href + (href.indexOf("?") > -1 ? "&" : "?") + "iframe=1";
            $("#orderDetailsIframe").attr("src", iframeSrc);
            $("#orderDetailsModal").modal("show");
        });
        
        // Clear iframe source on close to stop playing media or loading
        $("#orderDetailsModal").on("hidden.bs.modal", function () {
            $("#orderDetailsIframe").attr("src", "");
            // Optionally reload the table to reflect any status changes made inside the iframe
            // location.reload();
        });
    })(jQuery);
});
</script>
';

// Insert before the last <script> or at the bottom before footer
$order_content = str_replace('<?php require_once(\'footer.php\'); ?>', $modal_html . "\n" . '<?php require_once(\'footer.php\'); ?>', $order_content);
file_put_contents($order_file, $order_content);

echo "Patched iframe modals";
