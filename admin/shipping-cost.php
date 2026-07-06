<?php require_once('header.php'); ?>
<?php

if(isset($_POST['form1'])) {
    $valid = 1;
    $error_message = '';
    $success_message = '';

    if(empty($_POST['from_state_id'])) {
        $valid = 0;
        $error_message .= 'You must select a wilaya to ship from.<br>';
    }

    $shipping_amounts = [];
    if (isset($_POST['shipping_amount']) && is_array($_POST['shipping_amount'])) {
        foreach ($_POST['shipping_amount'] as $key => $amount) {
            if(empty($amount)) {
                $valid = 0;
                $error_message .= 'Shipping amount cannot be empty for ' . htmlspecialchars($_POST['other_wilayas'][$key], ENT_QUOTES, 'UTF-8') . '.<br>';
            } elseif(!is_numeric($amount)) {
                $valid = 0;
                $error_message .= 'You must enter a valid number for ' . htmlspecialchars($_POST['other_wilayas'][$key], ENT_QUOTES, 'UTF-8') . '.<br>';
            } else {
                $shipping_amounts[$key] = $amount;
            }
        }
    }

    if($valid == 1) {
        foreach ($shipping_amounts as $wilaya_id => $amount) {
            $statement = $dbRepo->prepare("SELECT id FROM tbl_shipping_cost WHERE wilaya_id = ?");
            $statement->execute([$wilaya_id]);
            $existing = $statement->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                $statement = $dbRepo->prepare("UPDATE tbl_shipping_cost SET amount = ? WHERE wilaya_id = ?");
                $statement->execute([$amount, $wilaya_id]);
            } else {
                $statement = $dbRepo->prepare("INSERT INTO tbl_shipping_cost (wilaya_id, amount) VALUES (?, ?)");
                $statement->execute([$wilaya_id, $amount]);
            }
        }

        $success_message = 'Shipping costs have been updated successfully.';
    }
}

$statement = $dbRepo->prepare("SELECT id, name FROM tbl_wilaya");
$statement->execute();
$all_wilayas = $statement->fetchAll(PDO::FETCH_ASSOC);
?>

<section class="content-header">
    <div class="content-header-left">
        <h1>Manage Shipping Costs</h1>
    </div>
</section>

<section class="content">
    <div class="row">
        <div class="col-md-12">
            <?php if(!empty($error_message)): ?>
                <div class="callout callout-danger"><p><?php echo $error_message; ?></p></div>
            <?php endif; ?>

            <?php if(!empty($success_message)): ?>
                <div class="callout callout-success"><p><?php echo $success_message; ?></p></div>
            <?php endif; ?>

             <form class="form-horizontal" action="" method="post">
                 <?php csrf_field(); ?>
                 <div class="box box-info">
                     <div class="box-body">
                         <div class="form-group">
                             <label class="col-sm-2 control-label">Select Wilaya to Ship From <span>*</span></label>
                            <div class="col-sm-4">
                                <select name="from_state_id" class="form-control select2" id="from_state_id">
                                    <option value="">Select a wilaya</option>
                                    <?php foreach ($all_wilayas as $row): ?>
                                        <option value="<?php echo $row['id']; ?>"><?php echo $row['name']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div id="shipping_costs" style="display:none;">
                            <h4>Enter Shipping Costs for Other Wilayas</h4>
                            <div id="wilaya_list"></div>
                        </div>

                        <div class="form-group">
                            <div class="col-sm-6">
                                <button type="submit" class="btn btn-success pull-left" name="form1">Save</button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</section>

<script>
    document.getElementById('from_state_id').addEventListener('change', function() {
        var selectedWilaya = this.value;
        var shippingCostsDiv = document.getElementById('shipping_costs');
        var wilayaListDiv = document.getElementById('wilaya_list');
        
        wilayaListDiv.innerHTML = '';
        if (selectedWilaya) {
            shippingCostsDiv.style.display = 'block';
            var allWilayas = <?php echo json_encode($all_wilayas); ?>;
            allWilayas.forEach(function(wilaya) {
                if (wilaya.id != selectedWilaya) {
                    var div = document.createElement('div');
                    div.classList.add('form-group');
                    div.innerHTML = `
                        <label class="col-sm-2 control-label">${wilaya.name}</label>
                        <div class="col-sm-4">
                            <input type="text" class="form-control" name="shipping_amount[${wilaya.id}]" placeholder="Enter shipping amount">
                            <input type="hidden" name="other_wilayas[${wilaya.id}]" value="${wilaya.id}">
                        </div>
                    `;
                    wilayaListDiv.appendChild(div);
                }
            });
        } else {
            shippingCostsDiv.style.display = 'none';
        }
    });
</script>

<?php require_once('footer.php'); ?>