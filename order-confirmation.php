<?php require_once('header.php'); ?>

<div class="page">
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <div class="order-confirmation" style="text-align: center; padding: 50px 20px;">
                    <?php if(isset($_SESSION['success_message'])): ?>
                        <div class="alert alert-success" style="margin-bottom: 30px;">
                            <h3 style="color: #28a745; margin-bottom: 20px;">
                                <i class="fa fa-check-circle"></i> Order Placed Successfully!
                            </h3>
                            <p><?php echo $_SESSION['success_message']; ?></p>
                            <?php unset($_SESSION['success_message']); ?>
                        </div>
                    <?php endif; ?>

                    <p style="margin-top: 20px;">
                        <a href="index.php" class="btn btn-primary">Continue Shopping</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once('footer.php'); ?> 