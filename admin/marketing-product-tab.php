<?php
require_once 'inc/config.php';

$p_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$p_id) {
    echo '<div class="alert alert-danger">معرف المنتج غير صالح.</div>';
    exit;
}

// Fetch Campaigns
$stmt = $dbRepo->prepare("SELECT platform, campaign_id, ad_id, post_id, story_id, reel_id FROM tbl_ai_campaign WHERE p_id = ?");
$stmt->execute([$p_id]);
$campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Media
$stmt = $dbRepo->prepare("SELECT media_type, media_url FROM tbl_ai_media WHERE p_id = ?");
$stmt->execute([$p_id]);
$medias = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<style>
.mkg-section-title { font-weight: bold; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; margin-top: 30px; margin-bottom: 20px; color: #ec4899; }
.mkg-card { border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-bottom: 15px; background: #fdf2f8; }
.remove-row-btn { color: #ef4444; cursor: pointer; }
.remove-row-btn:hover { color: #dc2626; }
</style>

<form id="marketingProductForm">
    <input type="hidden" name="p_id" value="<?= $p_id ?>">
    
    <h4 class="mkg-section-title"><i class="fa fa-bullhorn"></i> الحملات الإعلانية (Campaigns)</h4>
    <div id="campaigns-container">
        <?php foreach ($campaigns as $camp): ?>
        <div class="row mkg-card">
            <div class="col-md-2">
                <select class="form-control" name="camp_platforms[]">
                    <option value="facebook" <?= $camp['platform'] === 'facebook' ? 'selected' : '' ?>>Facebook</option>
                    <option value="instagram" <?= $camp['platform'] === 'instagram' ? 'selected' : '' ?>>Instagram</option>
                    <option value="tiktok" <?= $camp['platform'] === 'tiktok' ? 'selected' : '' ?>>TikTok</option>
                    <option value="snapchat" <?= $camp['platform'] === 'snapchat' ? 'selected' : '' ?>>Snapchat</option>
                </select>
            </div>
            <div class="col-md-2"><input type="text" class="form-control" name="camp_campaign_ids[]" value="<?= htmlspecialchars($camp['campaign_id']) ?>" placeholder="Campaign ID"></div>
            <div class="col-md-2"><input type="text" class="form-control" name="camp_ad_ids[]" value="<?= htmlspecialchars($camp['ad_id']) ?>" placeholder="Ad ID"></div>
            <div class="col-md-2"><input type="text" class="form-control" name="camp_post_ids[]" value="<?= htmlspecialchars($camp['post_id']) ?>" placeholder="Post ID"></div>
            <div class="col-md-2"><input type="text" class="form-control" name="camp_story_ids[]" value="<?= htmlspecialchars($camp['story_id']) ?>" placeholder="Story/Reel ID"></div>
            <div class="col-md-1 text-center" style="padding-top: 8px;"><i class="fa fa-trash fa-lg remove-row-btn"></i></div>
        </div>
        <?php endforeach; ?>
    </div>
    <button type="button" class="btn btn-default btn-sm" id="btn-add-campaign"><i class="fa fa-plus"></i> إضافة حملة</button>

    <h4 class="mkg-section-title"><i class="fa fa-image"></i> ميديا التسويق (Marketing Media)</h4>
    <div id="media-container">
        <?php foreach ($medias as $media): ?>
        <div class="row mkg-card">
            <div class="col-md-3">
                <select class="form-control" name="media_types[]">
                    <option value="photo" <?= $media['media_type'] === 'photo' ? 'selected' : '' ?>>صورة (Photo)</option>
                    <option value="video" <?= $media['media_type'] === 'video' ? 'selected' : '' ?>>فيديو (Video)</option>
                    <option value="tiktok_video" <?= $media['media_type'] === 'tiktok_video' ? 'selected' : '' ?>>TikTok Video</option>
                    <option value="reel_video" <?= $media['media_type'] === 'reel_video' ? 'selected' : '' ?>>Reel Video</option>
                </select>
            </div>
            <div class="col-md-8">
                <input type="text" class="form-control" name="media_urls[]" value="<?= htmlspecialchars($media['media_url']) ?>" placeholder="الرابط (URL)">
            </div>
            <div class="col-md-1 text-center" style="padding-top: 8px;">
                <i class="fa fa-trash fa-lg remove-row-btn"></i>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <button type="button" class="btn btn-default btn-sm" id="btn-add-media"><i class="fa fa-plus"></i> إضافة ميديا</button>

    <div style="margin-top: 30px; text-align: left;">
        <button type="button" class="btn btn-primary btn-lg" id="btn-save-marketing">
            <i class="fa fa-save"></i> حفظ بيانات التسويق
        </button>
    </div>
</form>

<script>
$(document).ready(function() {
    $(document).on('click', '.remove-row-btn', function() {
        $(this).closest('.row').remove();
    });

    $('#btn-add-campaign').click(function() {
        $('#campaigns-container').append(
            '<div class="row mkg-card">' +
            '<div class="col-md-2"><select class="form-control" name="camp_platforms[]"><option value="facebook">Facebook</option><option value="instagram">Instagram</option><option value="tiktok">TikTok</option><option value="snapchat">Snapchat</option></select></div>' +
            '<div class="col-md-2"><input type="text" class="form-control" name="camp_campaign_ids[]" placeholder="Campaign ID"></div>' +
            '<div class="col-md-2"><input type="text" class="form-control" name="camp_ad_ids[]" placeholder="Ad ID"></div>' +
            '<div class="col-md-2"><input type="text" class="form-control" name="camp_post_ids[]" placeholder="Post ID"></div>' +
            '<div class="col-md-2"><input type="text" class="form-control" name="camp_story_ids[]" placeholder="Story/Reel ID"></div>' +
            '<div class="col-md-1 text-center" style="padding-top: 8px;"><i class="fa fa-trash fa-lg remove-row-btn"></i></div></div>'
        );
    });

    $('#btn-add-media').click(function() {
        $('#media-container').append(
            '<div class="row mkg-card">' +
            '<div class="col-md-3"><select class="form-control" name="media_types[]"><option value="photo">صورة (Photo)</option><option value="video">فيديو (Video)</option><option value="tiktok_video">TikTok Video</option><option value="reel_video">Reel Video</option></select></div>' +
            '<div class="col-md-8"><input type="text" class="form-control" name="media_urls[]" placeholder="الرابط (URL)"></div>' +
            '<div class="col-md-1 text-center" style="padding-top: 8px;"><i class="fa fa-trash fa-lg remove-row-btn"></i></div></div>'
        );
    });

    $('#btn-save-marketing').click(function() {
        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> جاري الحفظ...');
        
        $.ajax({
            url: 'ajax-marketing-product-save.php',
            type: 'POST',
            data: $('#marketingProductForm').serialize(),
            success: function(response) {
                btn.prop('disabled', false).html('<i class="fa fa-save"></i> حفظ بيانات التسويق');
                let res = JSON.parse(response);
                if(res.status === 'success') {
                    alert('تم حفظ البيانات بنجاح!');
                } else {
                    alert('حدث خطأ: ' + res.message);
                }
            },
            error: function() {
                btn.prop('disabled', false).html('<i class="fa fa-save"></i> حفظ بيانات التسويق');
                alert('حدث خطأ غير متوقع.');
            }
        });
    });
});
</script>
