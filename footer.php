<?php
if (!isset($pdo) || !($pdo instanceof PDO)) {
	require_once __DIR__ . '/admin/inc/config.php';
}
if (!function_exists('front_get_settings')) {
	require_once __DIR__ . '/admin/inc/functions.php';
}

$footer_settings = front_get_settings($pdo);
$footer_about = trim((string)($footer_settings['footer_about'] ?? ''));
$contact_email = trim((string)($footer_settings['contact_email'] ?? ''));
$contact_phone = trim((string)($footer_settings['contact_phone'] ?? ''));
$contact_address = trim((string)($footer_settings['contact_address'] ?? ''));
$footer_copyright = $footer_settings['footer_copyright'] ?? '';
$newsletter_on_off = (int)($footer_settings['newsletter_on_off'] ?? 0);
$stripe_public_key = trim((string)($footer_settings['stripe_public_key'] ?? ''));
?>


<?php if($newsletter_on_off === 1 && ($footer_about !== '' || $contact_email !== '' || $contact_phone !== '')): ?>
<section class="home-newsletter">
	<div class="container">
		<div class="row">
			<div class="col-md-6 col-md-offset-3">
				<div class="single">
					<h3>ابق على تواصل معنا</h3>
					<?php if ($footer_about !== ''): ?>
						<p><?php echo nl2br(htmlspecialchars($footer_about, ENT_QUOTES, 'UTF-8')); ?></p>
					<?php endif; ?>
					<?php if ($contact_phone !== '' || $contact_email !== ''): ?>
						<p>
							<?php if ($contact_phone !== ''): ?>
								<span><?php echo htmlspecialchars($contact_phone, ENT_QUOTES, 'UTF-8'); ?></span>
							<?php endif; ?>
							<?php if ($contact_phone !== '' && $contact_email !== ''): ?>
								<span> | </span>
							<?php endif; ?>
							<?php if ($contact_email !== ''): ?>
								<a href="mailto:<?php echo htmlspecialchars($contact_email, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($contact_email, ENT_QUOTES, 'UTF-8'); ?></a>
							<?php endif; ?>
						</p>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
</section>
<?php endif; ?>




<div class="footer-bottom">
	<div class="container">
		<div class="row">
			<div class="col-md-12 copyright">
				<?php echo $footer_copyright; ?>
			</div>
		</div>
	</div>
</div>


<a href="#" class="scrollup">
	<i class="fa fa-angle-up"></i>
</a>
<?php if (is_file(__DIR__ . '/assets/dist/app.min.js')): ?>
	<script src="https://code.jquery.com/jquery-3.6.0.min.js" defer></script>
	<script src="https://js.stripe.com/v2/" defer></script>
	<script src="<?php echo function_exists('asset_url') ? asset_url('assets/dist/app.min.js') : 'assets/dist/app.min.js'; ?>" defer></script>
<?php else: ?>
	<script src="https://code.jquery.com/jquery-3.6.0.min.js" defer></script>
	<script src="https://js.stripe.com/v2/" defer></script>
	<script src="assets/js/bootstrap.min.js" defer></script>
	<script src="assets/js/megamenu.js" defer></script>
	<script src="assets/js/owl.carousel.min.js" defer></script>
	<script src="assets/js/owl.animate.js" defer></script>
	<script src="assets/js/jquery.magnific-popup.min.js" defer></script>
	<script src="assets/js/rating.js" defer></script>
	<script src="assets/js/jquery.touchSwipe.min.js" defer></script>
	<script src="assets/js/bootstrap-touch-slider.js" defer></script>
	<script src="assets/js/select2.full.min.js" defer></script>
	<script src="assets/js/site-security-device.js" defer></script>
	<script src="assets/js/custom.js" defer></script>
<?php endif; ?>
<script>
	function confirmDelete()
	{
	    return confirm("Sure you want to delete this data?");
	}
	


    document.addEventListener('DOMContentLoaded', function () {
        if (typeof Stripe === 'undefined' || typeof window.jQuery === 'undefined') {
            return;
        }

        $(document).on('submit', '#stripe_form', function () {
            // createToken returns immediately - the supplied callback submits the form if there are no errors
            $('#submit-button').prop("disabled", true);
            $("#msg-container").hide();
            Stripe.card.createToken({
                number: $('.card-number').val(),
                cvc: $('.card-cvc').val(),
                exp_month: $('.card-expiry-month').val(),
                exp_year: $('.card-expiry-year').val()
                // name: $('.card-holder-name').val()
            }, stripeResponseHandler);
	            return false;
	        });

	        Stripe.setPublishableKey('<?php echo htmlspecialchars($stripe_public_key, ENT_QUOTES, 'UTF-8'); ?>');

        function stripeResponseHandler(status, response) {
            if (response.error) {
                $('#submit-button').prop("disabled", false);
                $("#msg-container").html('<div style="color: red;border: 1px solid;margin: 10px 0px;padding: 5px;"><strong>Error:</strong> ' + response.error.message + '</div>');
                $("#msg-container").show();
            } else {
                var form$ = $("#stripe_form");
                var token = response['id'];
                form$.append("<input type='hidden' name='stripeToken' value='" + token + "' />");
                form$.get(0).submit();
            }
        }
    });
</script>


</body>
</html>
