<?php require_once('header.php'); ?>

<?php

if(isset($_POST['form_about'])) {
    $valid = 1;
    $remove_about_banner = !empty($_POST['remove_about_banner']);

    if(empty($_POST['about_title'])) {
        $valid = 0;
        $error_message .= 'Title can not be empty<br>';
    }

    if(empty($_POST['about_content'])) {
        $valid = 0;
        $error_message .= 'Content can not be empty<br>';
    }

    list($image_ok, $new_banner) = store_image_input(
        'about_banner',
        'about_banner_url',
        'about-banner',
        '../assets/uploads',
        $error_message,
        false
    );

    if(!$image_ok) {
        $valid = 0;
    }

    if($valid == 1) {
        if($new_banner !== '') {
            $statement = $pdo->prepare("SELECT about_banner FROM tbl_page WHERE id=1");
            $statement->execute();
            $current_about_banner = $statement->fetchColumn();
            delete_local_image_file($current_about_banner, '../assets/uploads');

            $statement = $pdo->prepare("UPDATE tbl_page SET about_title=?,about_content=?,about_banner=?,about_meta_title=?,about_meta_keyword=?,about_meta_description=? WHERE id=1");
            $statement->execute(array($_POST['about_title'],$_POST['about_content'],$new_banner,$_POST['about_meta_title'],$_POST['about_meta_keyword'],$_POST['about_meta_description']));
        } elseif($remove_about_banner) {
            $statement = $pdo->prepare("SELECT about_banner FROM tbl_page WHERE id=1");
            $statement->execute();
            $current_about_banner = $statement->fetchColumn();
            delete_local_image_file($current_about_banner, '../assets/uploads');

            $statement = $pdo->prepare("UPDATE tbl_page SET about_title=?,about_content=?,about_banner='',about_meta_title=?,about_meta_keyword=?,about_meta_description=? WHERE id=1");
            $statement->execute(array($_POST['about_title'],$_POST['about_content'],$_POST['about_meta_title'],$_POST['about_meta_keyword'],$_POST['about_meta_description']));
        } else {
            $statement = $pdo->prepare("UPDATE tbl_page SET about_title=?,about_content=?,about_meta_title=?,about_meta_keyword=?,about_meta_description=? WHERE id=1");
            $statement->execute(array($_POST['about_title'],$_POST['about_content'],$_POST['about_meta_title'],$_POST['about_meta_keyword'],$_POST['about_meta_description']));
        }

        $success_message = 'About Page Information is updated successfully.';
    }
}

if(isset($_POST['form_faq'])) {
    $valid = 1;
    $remove_faq_banner = !empty($_POST['remove_faq_banner']);

    if(empty($_POST['faq_title'])) {
        $valid = 0;
        $error_message .= 'Title can not be empty<br>';
    }

    list($image_ok, $new_banner) = store_image_input(
        'faq_banner',
        'faq_banner_url',
        'faq-banner',
        '../assets/uploads',
        $error_message,
        false
    );

    if(!$image_ok) {
        $valid = 0;
    }

    if($valid == 1) {
        if($new_banner !== '') {
            $statement = $pdo->prepare("SELECT faq_banner FROM tbl_page WHERE id=1");
            $statement->execute();
            $current_faq_banner = $statement->fetchColumn();
            delete_local_image_file($current_faq_banner, '../assets/uploads');

            $statement = $pdo->prepare("UPDATE tbl_page SET faq_title=?,faq_banner=?,faq_meta_title=?,faq_meta_keyword=?,faq_meta_description=? WHERE id=1");
            $statement->execute(array($_POST['faq_title'],$new_banner,$_POST['faq_meta_title'],$_POST['faq_meta_keyword'],$_POST['faq_meta_description']));
        } elseif($remove_faq_banner) {
            $statement = $pdo->prepare("SELECT faq_banner FROM tbl_page WHERE id=1");
            $statement->execute();
            $current_faq_banner = $statement->fetchColumn();
            delete_local_image_file($current_faq_banner, '../assets/uploads');

            $statement = $pdo->prepare("UPDATE tbl_page SET faq_title=?,faq_banner='',faq_meta_title=?,faq_meta_keyword=?,faq_meta_description=? WHERE id=1");
            $statement->execute(array($_POST['faq_title'],$_POST['faq_meta_title'],$_POST['faq_meta_keyword'],$_POST['faq_meta_description']));
        } else {
            $statement = $pdo->prepare("UPDATE tbl_page SET faq_title=?,faq_meta_title=?,faq_meta_keyword=?,faq_meta_description=? WHERE id=1");
            $statement->execute(array($_POST['faq_title'],$_POST['faq_meta_title'],$_POST['faq_meta_keyword'],$_POST['faq_meta_description']));
        }

        $success_message = 'FAQ Page Information is updated successfully.';
    }
}

if(isset($_POST['form_contact'])) {
    $valid = 1;
    $remove_contact_banner = !empty($_POST['remove_contact_banner']);

    if(empty($_POST['contact_title'])) {
        $valid = 0;
        $error_message .= 'Title can not be empty<br>';
    }

    list($image_ok, $new_banner) = store_image_input(
        'contact_banner',
        'contact_banner_url',
        'contact-banner',
        '../assets/uploads',
        $error_message,
        false
    );

    if(!$image_ok) {
        $valid = 0;
    }

    if($valid == 1) {
        if($new_banner !== '') {
            $statement = $pdo->prepare("SELECT contact_banner FROM tbl_page WHERE id=1");
            $statement->execute();
            $current_contact_banner = $statement->fetchColumn();
            delete_local_image_file($current_contact_banner, '../assets/uploads');

            $statement = $pdo->prepare("UPDATE tbl_page SET contact_title=?,contact_banner=?,contact_meta_title=?,contact_meta_keyword=?,contact_meta_description=? WHERE id=1");
            $statement->execute(array($_POST['contact_title'],$new_banner,$_POST['contact_meta_title'],$_POST['contact_meta_keyword'],$_POST['contact_meta_description']));
        } elseif($remove_contact_banner) {
            $statement = $pdo->prepare("SELECT contact_banner FROM tbl_page WHERE id=1");
            $statement->execute();
            $current_contact_banner = $statement->fetchColumn();
            delete_local_image_file($current_contact_banner, '../assets/uploads');

            $statement = $pdo->prepare("UPDATE tbl_page SET contact_title=?,contact_banner='',contact_meta_title=?,contact_meta_keyword=?,contact_meta_description=? WHERE id=1");
            $statement->execute(array($_POST['contact_title'],$_POST['contact_meta_title'],$_POST['contact_meta_keyword'],$_POST['contact_meta_description']));
        } else {
            $statement = $pdo->prepare("UPDATE tbl_page SET contact_title=?,contact_meta_title=?,contact_meta_keyword=?,contact_meta_description=? WHERE id=1");
            $statement->execute(array($_POST['contact_title'],$_POST['contact_meta_title'],$_POST['contact_meta_keyword'],$_POST['contact_meta_description']));
        }

        $success_message = 'Contact Page Information is updated successfully.';
    }
}

?>
<section class="content-header">
    <div class="content-header-left">
        <h1>Page Settings</h1>
    </div>
</section>

<?php
$statement = $pdo->prepare("SELECT * FROM tbl_page WHERE id=1");
$statement->execute();
$result = $statement->fetchAll(PDO::FETCH_ASSOC);                           
foreach ($result as $row) {
    $about_title = $row['about_title'];
    $about_content = $row['about_content'];
    $about_banner = $row['about_banner'];
    $about_meta_title = $row['about_meta_title'];
    $about_meta_keyword = $row['about_meta_keyword'];
    $about_meta_description = $row['about_meta_description'];
    $faq_title = $row['faq_title'];
    $faq_banner = $row['faq_banner'];
    $faq_meta_title = $row['faq_meta_title'];
    $faq_meta_keyword = $row['faq_meta_keyword'];
    $faq_meta_description = $row['faq_meta_description'];
    $contact_title = $row['contact_title'];
    $contact_banner = $row['contact_banner'];
    $contact_meta_title = $row['contact_meta_title'];
    $contact_meta_keyword = $row['contact_meta_keyword'];
    $contact_meta_description = $row['contact_meta_description'];

}
?>


<section class="content" style="min-height:auto;margin-bottom: -30px;">
    <div class="row">
        <div class="col-md-12">
            <?php if($error_message): ?>
            <div class="callout callout-danger">
            
            <p>
            <?php echo $error_message; ?>
            </p>
            </div>
            <?php endif; ?>

            <?php if($success_message): ?>
            <div class="callout callout-success">
            
            <p><?php echo $success_message; ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="content">

    <div class="row">
        <div class="col-md-12">
                            
                <div class="nav-tabs-custom">
                    <ul class="nav nav-tabs">
                        <li class="active"><a href="#tab_1" data-toggle="tab">About Us</a></li>
                        <li><a href="#tab_2" data-toggle="tab">FAQ</a></li>
                        <li><a href="#tab_4" data-toggle="tab">Contact</a></li>

                    </ul>

                    <!-- About us Page Content -->

                    <div class="tab-content">
                        <div class="tab-pane active" id="tab_1">
                            <form class="form-horizontal" action="" method="post" enctype="multipart/form-data">
                            <div class="box box-info">
                                <div class="box-body">
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label">Page Title * </label>
                                        <div class="col-sm-5">
                                            <input class="form-control" type="text" name="about_title" value="<?php echo $about_title; ?>">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label">Page Content * </label>
                                        <div class="col-sm-8">
                                            <textarea class="form-control" name="about_content" id="editor1"><?php echo $about_content; ?></textarea>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label">Existing Banner Photo</label>
                                        <div class="col-sm-6" style="padding-top:6px;">
                                            <img src="<?php echo htmlspecialchars(get_admin_image_url($about_banner), ENT_QUOTES); ?>" class="existing-photo" style="height:80px;">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label">New Banner Photo</label>
                                        <div class="col-sm-6" style="padding-top:6px;">
                                            <input type="file" name="about_banner">
                                            <br><br>
                                            <input type="text" class="form-control" name="about_banner_url" placeholder="Or paste banner URL (https://...)" value="<?php echo is_external_image_url($about_banner) ? htmlspecialchars($about_banner, ENT_QUOTES) : ''; ?>">
                                            <br>
                                            <label style="font-weight:normal;">
                                                <input type="checkbox" name="remove_about_banner" value="1"> Delete existing banner
                                            </label>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label">Meta Title</label>
                                        <div class="col-sm-8">
                                            <input class="form-control" type="text" name="about_meta_title" value="<?php echo $about_meta_title; ?>">
                                        </div>
                                    </div>             
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label">Meta Keyword </label>
                                        <div class="col-sm-8">
                                            <textarea class="form-control" name="about_meta_keyword" style="height:100px;"><?php echo $about_meta_keyword; ?></textarea>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label">Meta Description </label>
                                        <div class="col-sm-8">
                                            <textarea class="form-control" name="about_meta_description" style="height:100px;"><?php echo $about_meta_description; ?></textarea>
                                        </div>
                                    </div>                                    
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label"></label>
                                        <div class="col-sm-6">
                                            <button type="submit" class="btn btn-success pull-left" name="form_about">Update</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            </form>
                        </div>

        <!-- FAQ Page Content -->

                        <div class="tab-pane" id="tab_2">
                            <form class="form-horizontal" action="" method="post" enctype="multipart/form-data">
                            <div class="box box-info">
                                <div class="box-body">
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label">Page Title * </label>
                                        <div class="col-sm-5">
                                            <input class="form-control" type="text" name="faq_title" value="<?php echo $faq_title; ?>">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label">Existing Banner Photo</label>
                                        <div class="col-sm-6" style="padding-top:6px;">
                                            <img src="<?php echo htmlspecialchars(get_admin_image_url($faq_banner), ENT_QUOTES); ?>" class="existing-photo" style="height:80px;">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label">New Banner Photo</label>
                                        <div class="col-sm-6" style="padding-top:6px;">
                                            <input type="file" name="faq_banner">
                                            <br><br>
                                            <input type="text" class="form-control" name="faq_banner_url" placeholder="Or paste banner URL (https://...)" value="<?php echo is_external_image_url($faq_banner) ? htmlspecialchars($faq_banner, ENT_QUOTES) : ''; ?>">
                                            <br>
                                            <label style="font-weight:normal;">
                                                <input type="checkbox" name="remove_faq_banner" value="1"> Delete existing banner
                                            </label>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label">Meta Title</label>
                                        <div class="col-sm-8">
                                            <input class="form-control" type="text" name="faq_meta_title" value="<?php echo $faq_meta_title; ?>">
                                        </div>
                                    </div>             
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label">Meta Keyword </label>
                                        <div class="col-sm-8">
                                            <textarea class="form-control" name="faq_meta_keyword" style="height:100px;"><?php echo $faq_meta_keyword; ?></textarea>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label">Meta Description </label>
                                        <div class="col-sm-8">
                                            <textarea class="form-control" name="faq_meta_description" style="height:100px;"><?php echo $faq_meta_description; ?></textarea>
                                        </div>
                                    </div>                                    
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label"></label>
                                        <div class="col-sm-6">
                                            <button type="submit" class="btn btn-success pull-left" name="form_faq">Update</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            </form>
                        </div>

                        <!-- End of FAQ Page Content -->

                        <div class="tab-pane" id="tab_4">
                            <form class="form-horizontal" action="" method="post" enctype="multipart/form-data">
                            <div class="box box-info">
                                <div class="box-body">
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label">Page Title * </label>
                                        <div class="col-sm-5">
                                            <input class="form-control" type="text" name="contact_title" value="<?php echo $contact_title; ?>">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label">Existing Banner Photo</label>
                                        <div class="col-sm-6" style="padding-top:6px;">
                                            <img src="<?php echo htmlspecialchars(get_admin_image_url($contact_banner), ENT_QUOTES); ?>" class="existing-photo" style="height:80px;">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label">New Banner Photo</label>
                                        <div class="col-sm-6" style="padding-top:6px;">
                                            <input type="file" name="contact_banner">
                                            <br><br>
                                            <input type="text" class="form-control" name="contact_banner_url" placeholder="Or paste banner URL (https://...)" value="<?php echo is_external_image_url($contact_banner) ? htmlspecialchars($contact_banner, ENT_QUOTES) : ''; ?>">
                                            <br>
                                            <label style="font-weight:normal;">
                                                <input type="checkbox" name="remove_contact_banner" value="1"> Delete existing banner
                                            </label>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label">Meta Title</label>
                                        <div class="col-sm-8">
                                            <input class="form-control" type="text" name="contact_meta_title" value="<?php echo $contact_meta_title; ?>">
                                        </div>
                                    </div>             
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label">Meta Keyword </label>
                                        <div class="col-sm-8">
                                            <textarea class="form-control" name="contact_meta_keyword" style="height:100px;"><?php echo $contact_meta_keyword; ?></textarea>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label">Meta Description </label>
                                        <div class="col-sm-8">
                                            <textarea class="form-control" name="contact_meta_description" style="height:100px;"><?php echo $contact_meta_description; ?></textarea>
                                        </div>
                                    </div>                                    
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label"></label>
                                        <div class="col-sm-6">
                                            <button type="submit" class="btn btn-success pull-left" name="form_contact">Update</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            </form>
                        </div>



                

            </form>
        </div>
    </div>

</section>

<?php require_once('footer.php'); ?>


