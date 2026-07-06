import re

with open('C:/xampp/htdocs/ecom/admin/header.php', 'r', encoding='utf-8') as f:
    c = f.read()

omni_menu = """
		        <!-- OmniChannel Hub -->
		        <li class="treeview <?php if($cur_page == 'omni-channels.php' || $cur_page == 'omni-inbox.php') {echo 'active';} ?>">
		          <a href="#">
		            <i class="fa fa-comments text-aqua"></i>
		            <span>💬 OmniChannel Hub</span>
		            <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>
		          </a>
		          <ul class="treeview-menu">
		            <li><a href="omni-inbox.php"><i class="fa fa-inbox"></i> Unified Inbox</a></li>
		            <li><a href="omni-channels.php"><i class="fa fa-plug"></i> Channels Manager</a></li>
		          </ul>
		        </li>
"""

c = c.replace('</ul>\n		        </li>\n\n		        <!-- الطلبات -->', '</ul>\n		        </li>\n' + omni_menu + '\n		        <!-- الطلبات -->')

with open('C:/xampp/htdocs/ecom/admin/header.php', 'w', encoding='utf-8') as f:
    f.write(c)

print('admin/header.php updated.')
