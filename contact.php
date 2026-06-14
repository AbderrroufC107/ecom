<?php require_once('header.php'); ?>

<?php
$page_content = front_get_page_content($pdo);
$settings = front_get_settings($pdo);

$contact_title = $page_content['contact_title'] ?? 'اتصل بنا';
$contact_banner = $page_content['contact_banner'] ?? '';
$contact_map_iframe = $settings['contact_map_iframe'] ?? '';
$contact_email = trim((string)($settings['contact_email'] ?? ''));
$contact_phone = trim((string)($settings['contact_phone'] ?? ''));
$contact_address = trim((string)($settings['contact_address'] ?? ''));
$receive_email = trim((string)($settings['receive_email'] ?? ''));
$receive_email_subject = trim((string)($settings['receive_email_subject'] ?? ''));
$receive_email_thank_you_message = trim((string)($settings['receive_email_thank_you_message'] ?? ''));
$contact_banner_url = trim((string)get_front_image_url($contact_banner));

$contact_form_values = [
    'visitor_name' => '',
    'visitor_email' => '',
    'visitor_phone' => '',
    'visitor_message' => ''
];
$contact_form_errors = [];
$contact_form_success = '';
?>

<div class="page-banner"<?php if ($contact_banner_url !== ''): ?> style="background-image: url('<?php echo htmlspecialchars($contact_banner_url, ENT_QUOTES, 'UTF-8'); ?>');"<?php endif; ?>>
    <div class="inner">
        <h1><?php echo htmlspecialchars($contact_title, ENT_QUOTES, 'UTF-8'); ?></h1>
    </div>
</div>

<div class="page">
    <div class="container">
        <div class="row">            
            <div class="col-md-12">
                <h3>راسلنا</h3>
                <div class="row cform">
                    <div class="col-md-8">
                        <div class="well well-sm">
                            
                            <?php
                            if (isset($_POST['form_contact'])) {
                                foreach ($contact_form_values as $field => $value) {
                                    $contact_form_values[$field] = trim((string)($_POST[$field] ?? ''));
                                }

                                if ($contact_form_values['visitor_name'] === '') {
                                    $contact_form_errors[] = 'يرجى إدخال الاسم.';
                                }

                                if ($contact_form_values['visitor_phone'] === '') {
                                    $contact_form_errors[] = 'يرجى إدخال رقم الهاتف.';
                                }

                                if ($contact_form_values['visitor_email'] === '') {
                                    $contact_form_errors[] = 'يرجى إدخال البريد الإلكتروني.';
                                } elseif (!filter_var($contact_form_values['visitor_email'], FILTER_VALIDATE_EMAIL)) {
                                    $contact_form_errors[] = 'يرجى إدخال بريد إلكتروني صحيح.';
                                }

                                if ($contact_form_values['visitor_message'] === '') {
                                    $contact_form_errors[] = 'يرجى كتابة الرسالة.';
                                }

                                if ($receive_email === '') {
                                    $contact_form_errors[] = 'بريد الاستقبال غير مضبوط حالياً. يرجى المحاولة لاحقاً.';
                                }

                                if (empty($contact_form_errors)) {
                                    $mail_subject = $receive_email_subject !== '' ? $receive_email_subject : 'رسالة جديدة من صفحة التواصل';
                                    $encoded_subject = '=?UTF-8?B?' . base64_encode($mail_subject) . '?=';
                                    $message = '<html><body dir="rtl" style="font-family:Arial,sans-serif;">'
                                        . '<h3>رسالة جديدة من صفحة التواصل</h3>'
                                        . '<table cellpadding="8" cellspacing="0" border="1" style="border-collapse:collapse;">'
                                        . '<tr><td><strong>الاسم</strong></td><td>' . htmlspecialchars($contact_form_values['visitor_name'], ENT_QUOTES, 'UTF-8') . '</td></tr>'
                                        . '<tr><td><strong>البريد الإلكتروني</strong></td><td>' . htmlspecialchars($contact_form_values['visitor_email'], ENT_QUOTES, 'UTF-8') . '</td></tr>'
                                        . '<tr><td><strong>رقم الهاتف</strong></td><td>' . htmlspecialchars($contact_form_values['visitor_phone'], ENT_QUOTES, 'UTF-8') . '</td></tr>'
                                        . '<tr><td><strong>الرسالة</strong></td><td>' . nl2br(htmlspecialchars($contact_form_values['visitor_message'], ENT_QUOTES, 'UTF-8')) . '</td></tr>'
                                        . '</table>'
                                        . '</body></html>';
                                    $headers = [
                                        'MIME-Version: 1.0',
                                        'Content-Type: text/html; charset=UTF-8',
                                        'From: ' . $contact_form_values['visitor_email'],
                                        'Reply-To: ' . $contact_form_values['visitor_email'],
                                        'X-Mailer: PHP/' . phpversion()
                                    ];

                                    $mail_sent = @mail($receive_email, $encoded_subject, $message, implode("\r\n", $headers));
                                    if ($mail_sent) {
                                        $contact_form_success = $receive_email_thank_you_message !== '' ? $receive_email_thank_you_message : 'تم إرسال رسالتك بنجاح. سنعود إليك في أقرب وقت.';
                                        foreach ($contact_form_values as $field => $value) {
                                            $contact_form_values[$field] = '';
                                        }
                                    } else {
                                        $contact_form_errors[] = 'تعذر إرسال الرسالة حالياً. حاول مرة أخرى بعد قليل.';
                                    }
                                }
                            }
                            ?>

                            <?php if (!empty($contact_form_errors)): ?>
                                <div class="alert alert-danger">
                                    <?php foreach ($contact_form_errors as $form_error): ?>
                                        <div><?php echo htmlspecialchars($form_error, ENT_QUOTES, 'UTF-8'); ?></div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($contact_form_success !== ''): ?>
                                <div class="alert alert-success">
                                    <?php echo htmlspecialchars($contact_form_success, ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            <?php endif; ?>


                            <form action="" method="post">
                            <?php $csrf->echoInputField(); ?>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="visitor_name">الاسم</label>
                                        <input id="visitor_name" type="text" class="form-control" name="visitor_name" placeholder="أدخل الاسم" value="<?php echo htmlspecialchars($contact_form_values['visitor_name'], ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="visitor_email">البريد الإلكتروني</label>
                                        <input id="visitor_email" type="email" class="form-control" name="visitor_email" placeholder="أدخل البريد الإلكتروني" value="<?php echo htmlspecialchars($contact_form_values['visitor_email'], ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="visitor_phone">رقم الهاتف</label>
                                        <input id="visitor_phone" type="text" class="form-control" name="visitor_phone" placeholder="أدخل رقم الهاتف" value="<?php echo htmlspecialchars($contact_form_values['visitor_phone'], ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="visitor_message">الرسالة</label>
                                        <textarea id="visitor_message" name="visitor_message" class="form-control" rows="9" cols="25" placeholder="اكتب رسالتك"><?php echo htmlspecialchars($contact_form_values['visitor_message'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <input type="submit" value="إرسال الرسالة" class="btn btn-primary pull-right" name="form_contact">
                                </div>
                            </div>
                            </form>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <legend><span class="glyphicon glyphicon-globe"></span> معلومات التواصل</legend>
                        <?php if ($contact_address !== ''): ?>
                        <address>
                            <?php echo nl2br(htmlspecialchars($contact_address, ENT_QUOTES, 'UTF-8')); ?>
                        </address>
                        <?php endif; ?>
                        <?php if ($contact_phone !== ''): ?>
                        <address>
                            <strong>الهاتف:</strong><br>
                            <span><?php echo htmlspecialchars($contact_phone, ENT_QUOTES, 'UTF-8'); ?></span>
                        </address>
                        <?php endif; ?>
                        <?php if ($contact_email !== ''): ?>
                        <address>
                            <strong>البريد الإلكتروني:</strong><br>
                            <a href="mailto:<?php echo htmlspecialchars($contact_email, ENT_QUOTES, 'UTF-8'); ?>"><span><?php echo htmlspecialchars($contact_email, ENT_QUOTES, 'UTF-8'); ?></span></a>
                        </address>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($contact_map_iframe !== ''): ?>
                <h3>موقعنا على الخريطة</h3>
                <?php echo $contact_map_iframe; ?>
                <?php endif; ?>
                
            </div>
        </div>
    </div>
</div>

<?php require_once('footer.php'); ?>
