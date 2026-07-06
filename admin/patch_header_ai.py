import re

with open('C:/xampp/htdocs/ecom/admin/header.php', 'r', encoding='utf-8') as f:
    c = f.read()

ai_menu = """
		        <!-- الذكاء الاصطناعي -->
		        <li class="treeview <?php if(in_array($cur_page, ['ai-products.php','ai-campaigns.php','ai-settings.php','ai-keywords.php','ai-faqs.php','ai-rules.php','ai-analytics.php','ai-chat-logs.php'])) {echo 'active';} ?>">
		          <a href="#">
		            <i class="fa fa-magic text-purple"></i>
		            <span>🤖 الذكاء الاصطناعي</span>
		            <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>
		          </a>
		          <ul class="treeview-menu">
		            <li><a href="ai-products.php"><i class="fa fa-circle-o"></i> بطاقات ذكاء المنتجات</a></li>
		            <li><a href="ai-campaigns.php"><i class="fa fa-circle-o"></i> الحملات الإعلانية</a></li>
		            <li><a href="ai-settings.php"><i class="fa fa-circle-o"></i> إعدادات الذكاء الاصطناعي</a></li>
		            <li><a href="ai-keywords.php"><i class="fa fa-circle-o"></i> الكلمات المفتاحية</a></li>
		            <li><a href="ai-faqs.php"><i class="fa fa-circle-o"></i> الأسئلة الشائعة</a></li>
		            <li><a href="ai-rules.php"><i class="fa fa-circle-o"></i> قواعد البيع</a></li>
		            <li><a href="ai-analytics.php"><i class="fa fa-circle-o"></i> Analytics</a></li>
		            <li><a href="ai-chat-logs.php"><i class="fa fa-circle-o"></i> سجل المحادثات</a></li>
		          </ul>
		        </li>
"""

c = c.replace('</ul>\n		        </li>\n\n		        <!-- الطلبات -->', '</ul>\n		        </li>\n' + ai_menu + '\n		        <!-- الطلبات -->')

with open('C:/xampp/htdocs/ecom/admin/header.php', 'w', encoding='utf-8') as f:
    f.write(c)

print('header.php patched successfully.')
