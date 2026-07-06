import re

with open('C:/xampp/htdocs/ecom/admin/header.php', 'r', encoding='utf-8') as f:
    c = f.read()

knowledge_menu = """
		        <!-- AI Knowledge -->
		        <li class="treeview <?php if($cur_page == 'ai-knowledge.php') {echo 'active';} ?>">
		          <a href="#">
		            <i class="fa fa-book text-aqua"></i>
		            <span>🧠 AI Knowledge</span>
		            <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>
		          </a>
		          <ul class="treeview-menu">
		            <li><a href="ai-knowledge.php"><i class="fa fa-circle-o"></i> Knowledge Base</a></li>
		            <li><a href="ai-knowledge.php?category=sales"><i class="fa fa-circle-o"></i> Sales Rules</a></li>
		            <li><a href="ai-knowledge.php?category=company"><i class="fa fa-circle-o"></i> Company Policies</a></li>
		            <li><a href="ai-knowledge.php?category=shipping"><i class="fa fa-circle-o"></i> Shipping Policies</a></li>
		            <li><a href="ai-knowledge.php?category=returns"><i class="fa fa-circle-o"></i> Return Policies</a></li>
		            <li><a href="ai-knowledge.php?category=payments"><i class="fa fa-circle-o"></i> Payment Policies</a></li>
		            <li><a href="ai-knowledge.php?category=products"><i class="fa fa-circle-o"></i> Product Guides</a></li>
		            <li><a href="ai-knowledge.php?category=marketing"><i class="fa fa-circle-o"></i> Brand Guides</a></li>
		            <li><a href="ai-knowledge.php?category=style"><i class="fa fa-circle-o"></i> Writing Style</a></li>
		            <li><a href="ai-knowledge.php?category=variables"><i class="fa fa-circle-o"></i> Prompt Variables</a></li>
		          </ul>
		        </li>
"""

c = c.replace('</ul>\n		        </li>\n\n		        <!-- الطلبات -->', '</ul>\n		        </li>\n' + knowledge_menu + '\n		        <!-- الطلبات -->')

with open('C:/xampp/htdocs/ecom/admin/header.php', 'w', encoding='utf-8') as f:
    f.write(c)

print('admin/header.php updated.')
