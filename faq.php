<?php require_once('header.php'); ?>

<?php
$page_content = front_get_page_content($pdo);
$faq_title = $page_content['faq_title'] ?? '';
$faq_banner = $page_content['faq_banner'] ?? '';
$faq_banner_url = trim((string)get_front_image_url($faq_banner));
?>

<div class="page-banner"<?php if ($faq_banner_url !== ''): ?> style="background-image: url('<?php echo htmlspecialchars($faq_banner_url, ENT_QUOTES, 'UTF-8'); ?>');"<?php endif; ?>>
    <div class="inner">
        <h1><?php echo htmlspecialchars($faq_title, ENT_QUOTES, 'UTF-8'); ?></h1>
    </div>
</div>

<div class="page">
    <div class="container">
        <div class="row">            
            <div class="col-md-12">
                
                <div class="panel-group" id="faqAccordion">                    

                    <?php
                    $statement = $pdo->prepare("SELECT * FROM tbl_faq");
                    $statement->execute();
                    $result = $statement->fetchAll(PDO::FETCH_ASSOC);                            
                    foreach ($result as $row) {
                        ?>
                        <div class="panel panel-default">
                            <div class="panel-heading accordion-toggle question-toggle collapsed" data-toggle="collapse" data-parent="#faqAccordion" data-target="#question<?php echo $row['faq_id']; ?>">
                                <h4 class="panel-title">
                                    Q: <?php echo $row['faq_title']; ?>
                                </h4>
                            </div>
                            <div id="question<?php echo $row['faq_id']; ?>" class="panel-collapse collapse" style="height: 0px;">
                                <div class="panel-body">
                                    <h5><span class="label label-primary">Answer</span></h5>
                                    <p>
                                        <?php echo $row['faq_content']; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <?php
                    }
                    ?>
                    
                </div>

            </div>
        </div>
    </div>
</div>

<?php require_once('footer.php'); ?>
