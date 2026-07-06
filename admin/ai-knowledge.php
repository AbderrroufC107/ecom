<?php
require_once('header.php');
require_once('inc/config.php');

$cat_filter = isset($_GET['category']) ? $_GET['category'] : '';
?>
<section class="content-header">
    <div class="content-header-left">
        <h1>🧠 مركز المعرفة (AI) <?= $cat_filter ? "- " . ucfirst($cat_filter) : "" ?></h1>
    </div>
    <div class="content-header-right">
        <a href="ai-knowledge-add.php" class="btn btn-primary btn-sm"><i class="fa fa-plus"></i> إضافة عنصر معرفة جديد</a>
    </div>
</section>

<section class="content">
    <div class="row">
        <div class="col-md-12">
            <div class="box box-info">
                <div class="box-header with-border">
                    <h3 class="box-title">محرك البحث الشامل</h3>
                </div>
                <div class="box-body">
                    <form id="searchKnowledgeForm" class="row">
                        <div class="col-md-4">
                            <input type="text" name="q" class="form-control" placeholder="ابحث في العنوان أو المحتوى...">
                        </div>
                        <div class="col-md-3">
                            <select name="category" class="form-control">
                                <option value="">كل التصنيفات</option>
                                <?php
                                $stmt = $dbRepo->query("SELECT slug, name FROM tbl_ai_knowledge_categories ORDER BY sort_order");
                                while ($row = $stmt->fetch()) {
                                    $selected = ($cat_filter === $row['slug']) ? 'selected' : '';
                                    echo "<option value=\"{$row['slug']}\" $selected>{$row['name']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="button" class="btn btn-info" id="btn-search"><i class="fa fa-search"></i> بحث</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="box box-success">
                <div class="box-body table-responsive">
                    <table class="table table-bordered table-hover" id="knowledgeTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>العنوان</th>
                                <th>التصنيف</th>
                                <th>النوع</th>
                                <th>اللغة</th>
                                <th>الأولوية</th>
                                <th>الحالة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Results loaded via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
$(document).ready(function() {
    
    function loadKnowledge() {        const q = $('input[name="q"]').val();
        const category = $('select[name="category"]').val();
        
        $('#knowledgeTable tbody').html('<tr><td colspan="8" class="text-center"><i class="fa fa-spinner fa-spin"></i> جاري التحميل...</td></tr>');
        
        $.ajax({
            url: 'ajax-knowledge-search.php',
            type: 'GET',
            data: { q: q, category: category },
            success: function(response) {
                let res = JSON.parse(response);
                if(res.status === 'success') {
                    let html = '';
                    if(res.data.length === 0) {
                        html = '<tr><td colspan="8" class="text-center">لا توجد بيانات</td></tr>';
                    } else {
                        res.data.forEach(function(row) {
                            html += '<tr>';
                            html += '<td>' + row.id + '</td>';
                            html += '<td>' + row.title + '</td>';
                            html += '<td>' + (row.category_name || 'N/A') + '</td>';
                            html += '<td><span class="label label-default">' + row.knowledge_type + '</span></td>';
                            html += '<td>' + row.language + '</td>';
                            html += '<td>' + row.priority + '</td>';
                            html += '<td>' + (row.is_active == 1 ? '<span class="label label-success">نشط</span>' : '<span class="label label-danger">معطل</span>') + '</td>';
                            html += '<td><a href="#" class="btn btn-xs btn-primary"><i class="fa fa-edit"></i> تعديل</a></td>';
                            html += '</tr>';
                        });
                    }
                    $('#knowledgeTable tbody').html(html);
                }
            }
        });
    }

    $('#btn-search').click(loadKnowledge);
    
    // Initial load
    loadKnowledge();

});
</script>
<?php require_once('footer.php'); ?>
