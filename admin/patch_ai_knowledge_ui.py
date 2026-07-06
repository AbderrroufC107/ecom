import re

with open('C:/xampp/htdocs/ecom/admin/ai-knowledge.php', 'r', encoding='utf-8') as f:
    c = f.read()

new_js = """
    function loadKnowledge() {
        const q = $('input[name="q"]').val();
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
"""

c = re.sub(r'function loadKnowledge\(\) \{.*?\}\s*// To solve the auth issue natively.*?\}\);', new_js + "\n});", c, flags=re.DOTALL)

with open('C:/xampp/htdocs/ecom/admin/ai-knowledge.php', 'w', encoding='utf-8') as f:
    f.write(c)

print("ai-knowledge.php patched.")
