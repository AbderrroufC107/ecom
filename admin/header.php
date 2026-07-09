<?php
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
mb_regex_encoding('UTF-8');

// Start profiler
require_once __DIR__ . '/inc/profiler.php';
Profiler::start();

ob_start();
if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}
include("inc/config.php");
include("inc/functions.php");
include("inc/store.php");
include("inc/CSRF_Protect.php");
$csrf = new CSRF_Protect();

// Auto-verify CSRF token on all POST/PUT/DELETE requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'DELETE') {
    if (!isset($_POST['_csrf']) || !$csrf->isTokenValid($_POST['_csrf'])) {
        http_response_code(403);
        die('CSRF validation failed.');
    }
}

$error_message = '';
$success_message = '';
$error_message1 = '';
$success_message1 = '';

/**
 * Renders a hidden CSRF token input field for use in forms.
 */
if (!function_exists('csrf_field')) {
    function csrf_field(): void
    {
        global $csrf;
        if (isset($csrf)) {
            $csrf->echoInputField();
        }
    }
}

// Multi-tenant: allow both admin (tbl_user) and store owner (tbl_store_user)
if (!isset($_SESSION['user']) && !isset($_SESSION['store_user'])) {
	header('location: login.php');
	exit;
}

// ---- Role-based page access for Employees (default-deny whitelist) ----
// An Employee (tbl_employee, session id "emp_<id>", role "Employee") is limited
// to their own area only. Managers / Admins / Super Admins are NOT affected.
// To let employees open more pages, add the file name to $employee_allowed_pages.
if (isset($_SESSION['user'])) {
	$__role  = $_SESSION['user']['role'] ?? '';
	$__idRaw = (string) ($_SESSION['user']['id'] ?? '');
	$__isEmployee = ($__role === 'Employee') || (strpos($__idRaw, 'emp_') === 0);

	if ($__isEmployee) {
		$employee_allowed_pages = [
			'index.php',                 // لوحة القيادة (صفحة الهبوط بعد الدخول)
			'my-earnings.php',           // عمولاتي وسجل المدفوعات
			'ai-assistant.php',          // المساعد الذكي (مقيّد ببيانات الموظف)
			'logout.php',
			'telegram-link-action.php',  // ربط حساب تيليغرام الخاص به
			// لتمكين الموظف من معالجة الطلبات داخل اللوحة، أضِف الصفحات التالية:
			// 'order.php', 'order-details.php', 'order-change-status.php',
		];
		$__cur = basename($_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? ''));
		if ($__cur !== '' && !in_array($__cur, $employee_allowed_pages, true)) {
			$_SESSION['employee_access_denied'] = $__cur;
			header('location: index.php');
			exit;
		}
	}
}

// Resolve current store ID
store_ensure_tables($pdo);
$current_store_id = store_resolve($pdo);
if (!defined('STORE_ID')) {
	define('STORE_ID', $current_store_id);
}

// Store owner context: set store_id from session
if (isset($_SESSION['store_user']) && !isset($_SESSION['user'])) {
	$_SESSION['store_id'] = (int) $_SESSION['store_user']['store_id'];
	$current_store_id = (int) $_SESSION['store_user']['store_id'];
	if (!defined('STORE_ID')) {
		define('STORE_ID', $current_store_id);
	}
}

// Auto-scope: register a shutdown function to append store_id to all non-scoped queries?
// Instead, pages use store_build_where() / store_apply_where() explicitly.


 // Security headers
 header('X-Frame-Options: SAMEORIGIN');
 header('X-Content-Type-Options: nosniff');
 header('Referrer-Policy: strict-origin-when-cross-origin');
 header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
 header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://fonts.googleapis.com; font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self' ws://127.0.0.1:* http://127.0.0.1:* https:; frame-src 'self'; object-src 'none'; base-uri 'self'");
 
 // Bootstrap checkpoint
 Profiler::checkpoint('bootstrap_complete');
 ?>

<!DOCTYPE html>
<html lang="ar" dir="ltr">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<meta http-equiv="X-Frame-Options" content="SAMEORIGIN">
	<meta http-equiv="X-Content-Type-Options" content="nosniff">
	<meta name="referrer" content="strict-origin-when-cross-origin">
	<title>لوحة التحكم | متجر الثقة</title>

	<meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
	<link rel="stylesheet" href="css/select2.min.css">
	<link rel="stylesheet" href="css/dataTables.bootstrap.css">
	<link rel="stylesheet" href="css/AdminLTE.min.css">
	<link rel="stylesheet" href="css/_all-skins.min.css">
	<link rel="stylesheet" href="css/on-off-switch.css"/>
	<link rel="stylesheet" href="style.css?v=react-admin-20260516-2">
	<?php if(!isset($_GET['iframe'])): ?>
	<?php foreach (glob(__DIR__ . '/dist/admin-react-vendor-*.css') ?: array() as $admin_react_vendor_css): ?>
		<link rel="stylesheet" href="dist/<?php echo htmlspecialchars(basename($admin_react_vendor_css), ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo filemtime($admin_react_vendor_css); ?>">
	<?php endforeach; ?>
	<?php $admin_react_css_version = file_exists(__DIR__ . '/dist/admin-react.css') ? filemtime(__DIR__ . '/dist/admin-react.css') : time(); ?>
	<link rel="stylesheet" href="dist/admin-react.css?v=<?php echo $admin_react_css_version; ?>">
	<?php endif; ?>
	<style id="admin-react-critical-css">
		@font-face {
			font-family: 'InterLocal';
			src: url('../assets/fonts/inter-400.woff2') format('woff2');
			font-weight: 400;
			font-style: normal;
			font-display: swap;
		}

		@font-face {
			font-family: 'InterLocal';
			src: url('../assets/fonts/inter-600.woff2') format('woff2');
			font-weight: 600;
			font-style: normal;
			font-display: swap;
		}

		@font-face {
			font-family: 'InterLocal';
			src: url('../assets/fonts/inter-700.woff2') format('woff2');
			font-weight: 700;
			font-style: normal;
			font-display: swap;
		}

		@font-face {
			font-family: 'CairoLocal';
			src: url('../assets/fonts/cairo-400.woff2') format('woff2');
			font-weight: 400;
			font-style: normal;
			font-display: swap;
		}

		@font-face {
			font-family: 'CairoLocal';
			src: url('../assets/fonts/cairo-500.woff2') format('woff2');
			font-weight: 500;
			font-style: normal;
			font-display: swap;
		}

		@font-face {
			font-family: 'CairoLocal';
			src: url('../assets/fonts/cairo-700.woff2') format('woff2');
			font-weight: 700;
			font-style: normal;
			font-display: swap;
		}

		body.admin-react-pending .main-header,
		body.admin-react-pending .main-sidebar {
			display: none !important;
		}

		body.admin-react-pending .content-wrapper,
		body.admin-react-pending .right-side,
		body.admin-react-pending .main-footer {
			visibility: hidden !important;
			margin-right: 288px !important;
			margin-left: 0 !important;
			padding-top: 92px !important;
		}

		body.admin-react-pending #admin-react-shell:before {
			content: "";
			position: fixed;
			top: 0;
			bottom: 0;
			right: 0;
			z-index: 1043;
			width: 288px;
			background: #ffffff;
			border-left: 1px solid #e2e8f0;
			box-shadow: -12px 0 34px rgba(15, 23, 42, 0.06);
		}

		body.admin-react-pending #admin-react-shell:after {
			content: "";
			position: fixed;
			top: 0;
			right: 288px;
			left: 0;
			z-index: 1042;
			height: 72px;
			background: rgba(255,255,255,0.94);
			border-bottom: 1px solid rgba(226, 232, 240, 0.92);
			box-shadow: 0 10px 28px rgba(15, 23, 42, 0.04);
		}

		@media (max-width: 900px) {
			body.admin-react-pending .content-wrapper,
			body.admin-react-pending .right-side,
			body.admin-react-pending .main-footer {
				margin-right: 0 !important;
				margin-left: 0 !important;
				padding-top: 72px !important;
			}

			body.admin-react-pending #admin-react-shell:before {
				transform: translateX(100%);
			}

			body.admin-react-pending #admin-react-shell:after {
				right: 0;
				height: 72px;
			}
		}

		.sidebar-menu li:has(> a[href="top-category.php"]),
		.sidebar-menu li:has(> a[href="mid-category.php"]),
		.sidebar-menu li:has(> a[href="end-category.php"]) {
			display: none !important;
		}
	</style>

<script>
if (window.self !== window.top) {
    document.write('<style>.main-header, .main-sidebar, .main-footer { display: none !important; } .content-wrapper { margin-left: 0 !important; margin-top: 0 !important; padding-top: 0 !important; z-index: 9999; } body { background: #ecf0f5; padding-top: 0 !important; }</style>');
}
// CSRF token injection for all POST forms
(function() {
    var csrfToken = '<?php echo $csrf->getToken(); ?>';
    document.addEventListener('DOMContentLoaded', function() {
        var forms = document.querySelectorAll('form[method="post"]');
        for (var i = 0; i < forms.length; i++) {
            if (!forms[i].querySelector('input[name="_csrf"]')) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = '_csrf';
                input.value = csrfToken;
                forms[i].appendChild(input);
            }
        }
    });
})();
</script>
    <script>
        window.csrfToken = "<?= isset($csrf) ? $csrf->getToken() : '' ?>";
    </script>
</head>

<body class="hold-transition fixed skin-blue sidebar-mini admin-pro-theme admin-ltr-layout admin-react-pending">
	<link rel="stylesheet" href="assets/ui/main-ui.css?v=<?php echo time(); ?>">
	<div id="admin-react-shell" data-admin-name="<?php echo htmlspecialchars($_SESSION['user']['full_name'] ?? $_SESSION['store_user']['name'] ?? 'المدير', ENT_QUOTES, 'UTF-8'); ?>" data-current-lang="<?php echo htmlspecialchars($current_lang, ENT_QUOTES, 'UTF-8'); ?>"></div>

	<div class="wrapper">
	<style>
		/* Hide legacy title headers globally to avoid duplication with React topbar */
		section.content-header { display: none !important; }
		
		/* Enterprise UI overrides for legacy pages (Global) */
		.content-wrapper, .wrapper { direction: rtl !important; text-align: right !important; }
		.box { border-radius: 12px !important; box-shadow: 0 4px 20px rgba(0,0,0,0.05) !important; border: 1px solid #e2e8f0 !important; background: #fff !important; margin-bottom: 20px !important; }
		.box-header { border-bottom: 1px solid #e2e8f0 !important; padding: 15px 20px !important; }
		.box-title { font-weight: 700 !important; color: #1e293b !important; font-size: 16px !important; margin: 0 !important; display: inline-block; }
		
		.nav-tabs { border-bottom: 2px solid #e2e8f0 !important; margin: 0 0 15px 0 !important; padding: 0 10px !important; display: flex !important; gap: 8px !important; flex-wrap: wrap !important; list-style: none !important; }
		.nav-tabs > li { float: none !important; margin-bottom: -2px !important; }
		.nav-tabs > li > a { border: none !important; color: #64748b !important; font-weight: 600 !important; padding: 10px 16px !important; border-radius: 8px 8px 0 0 !important; transition: all 0.2s !important; background: transparent !important; margin: 0 !important; display: block; }
		.nav-tabs > li > a:hover { color: #3b82f6 !important; background: #f8fafc !important; text-decoration: none !important; }
		.nav-tabs > li.active > a, .nav-tabs > li.active > a:hover, .nav-tabs > li.active > a:focus { color: #3b82f6 !important; border: none !important; border-bottom: 2px solid #3b82f6 !important; background: transparent !important; cursor: default !important; text-decoration: none !important; }
		
		.table { margin-bottom: 0 !important; }
		.table-bordered { border: 1px solid #e2e8f0 !important; }
		.table-bordered > thead > tr > th { background: #f8fafc !important; color: #475569 !important; font-weight: 600 !important; border-bottom: 2px solid #e2e8f0 !important; border-top: none !important; padding: 12px 15px !important; }
		.table-bordered > tbody > tr > td, .table-bordered > tbody > tr > th { border-color: #f1f5f9 !important; vertical-align: middle !important; color: #334155 !important; padding: 12px 15px !important; }
		
		.btn { border-radius: 8px !important; font-weight: 600 !important; padding: 8px 16px !important; transition: all 0.2s !important; box-shadow: none !important; border: none !important; outline: none !important; }
		.btn-primary { background: #3b82f6 !important; color: #fff !important; }
		.btn-primary:hover { background: #2563eb !important; box-shadow: 0 4px 12px rgba(59,130,246,0.3) !important; }
		.btn-success { background: #10b981 !important; color: #fff !important; }
		.btn-success:hover { background: #059669 !important; box-shadow: 0 4px 12px rgba(16,185,129,0.3) !important; }
		.btn-danger { background: #ef4444 !important; color: #fff !important; }
		.btn-danger:hover { background: #dc2626 !important; box-shadow: 0 4px 12px rgba(239,68,68,0.3) !important; }
		.btn-default { background: #f1f5f9 !important; color: #475569 !important; }
		.btn-default:hover { background: #e2e8f0 !important; color: #1e293b !important; }
		
		.form-control { border-radius: 8px !important; border: 1px solid #cbd5e1 !important; padding: 10px 14px !important; height: auto !important; box-shadow: none !important; transition: border-color 0.2s !important; outline: none !important; }
		.form-control:focus { border-color: #3b82f6 !important; box-shadow: 0 0 0 3px rgba(59,130,246,0.1) !important; }
		
		.label { border-radius: 4px !important; padding: 4px 8px !important; font-weight: 600 !important; font-size: 11px !important; }
		h4 { font-weight: 700 !important; color: #1e293b !important; margin-bottom: 15px !important; }
		
		.nav-tabs-custom { background: transparent !important; box-shadow: none !important; }

		/* Fix Tailwind preflight breaking lists */
		ul.nav, ul.nav-tabs { list-style: none !important; padding: 0 !important; margin: 0 !important; }
	</style>
		   
		<?php if(!isset($_GET['iframe'])): ?>
		<?php foreach (glob(__DIR__ . '/dist/admin-react-vendor-*.css') ?: array() as $admin_react_vendor_css): ?>
			<link rel="stylesheet" href="dist/<?php echo htmlspecialchars(basename($admin_react_vendor_css), ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo filemtime($admin_react_vendor_css); ?>">
		<?php endforeach; ?>
		<?php $admin_react_css_version = file_exists(__DIR__ . '/dist/admin-react.css') ? filemtime(__DIR__ . '/dist/admin-react.css') : time(); ?>
		<link rel="stylesheet" href="dist/admin-react.css?v=<?php echo $admin_react_css_version; ?>">
		<?php endif; ?>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			window.setTimeout(function() {
				if (!document.body.classList.contains('admin-react-ready')) {
					var reactScript = document.querySelector('script[type="module"][src*="admin-react"]');
					var diag = {
						reactScriptFound: !!reactScript,
						reactScriptSrc: reactScript ? reactScript.src : null,
						bodyClasses: document.body.className,
						shellExists: !!document.getElementById('admin-react-shell'),
						pendingRemoved: true
					};
					try { fetch('log_error.php?err=' + encodeURIComponent('[Fallback] React not ready after 3.5s: ' + JSON.stringify(diag))); } catch(e) {}
					document.body.classList.remove('admin-react-pending');
				}
			}, 3500);
		});
		</script>
	
	<script>
	// Prevent nested UI if an iframe redirects without the iframe=1 parameter
	if (window.self !== window.top) {
		if (window.location.href.indexOf('iframe=1') === -1) {
			window.parent.location.href = window.location.href;
		}
	}
	</script>

		<header class="main-header">

			<a href="index.php" class="logo">
				<span class="logo-mini admin-logo-mini">MT</span>
				<span class="logo-lg admin-logo-text">متجر الثقة</span>
			</a>

			<nav class="navbar navbar-static-top">
				<a href="#" class="sidebar-toggle" data-toggle="offcanvas" role="button">
					<span class="sr-only">إظهار أو إخفاء القائمة</span>
				</a>

				<span class="admin-navbar-title">لوحة التحكم</span>

				<div class="navbar-custom-menu admin-user-menu">
					<ul class="nav navbar-nav ml-auto">
						<li class="nav-item dropdown no-arrow">
							<a class="nav-link dropdown-toggle admin-user-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
								<span class="mr-2 d-none d-lg-inline text-gray-600 small admin-user-name">
									<?php echo isset($_SESSION['user']) ? $_SESSION['user']['full_name'] : ($_SESSION['store_user']['name'] ?? 'المدير'); ?>
								</span>
								<i class="fa fa-user-circle-o"></i>
							</a>
							<div class="dropdown-menu dropdown-menu-right shadow animated--grow-in admin-user-dropdown" aria-labelledby="userDropdown">
								<a class="dropdown-item" href="profile-edit.php">
									<i class="fa fa-user fa-sm fa-fw mr-2 text-gray-400"></i>
									الملف الشخصي
								</a>
								<div class="dropdown-divider"></div>
								<a class="dropdown-item" href="logout.php">
									<i class="fa fa-sign-out fa-sm fa-fw mr-2 text-gray-400"></i>
									تسجيل الخروج
								</a>
							</div>
						</li>
					</ul>
				</div>

			</nav>
		</header>

  		<?php $cur_page = substr($_SERVER["SCRIPT_NAME"],strrpos($_SERVER["SCRIPT_NAME"],"/")+1); ?>
  		<aside class="main-sidebar">
    		<section class="sidebar">
      			<ul class="sidebar-menu">

		        <?php if (!empty($__isEmployee)): ?>
		        <!-- قائمة الموظف المحدودة (تُطابق قائمة السماح في حارس الوصول أعلاه) -->
		        <li class="treeview <?php if($cur_page == 'index.php') {echo 'active';} ?>">
		          <a href="index.php"><i class="fa fa-dashboard"></i> <span>لوحة القيادة</span></a>
		        </li>
		        <li class="treeview <?php if($cur_page == 'my-earnings.php') {echo 'active';} ?>">
		          <a href="my-earnings.php"><i class="fa fa-money"></i> <span>عمولاتي والمدفوعات</span></a>
		        </li>
		        <li class="treeview <?php if($cur_page == 'ai-assistant.php') {echo 'active';} ?>">
		          <a href="ai-assistant.php"><i class="fa fa-magic text-purple"></i> <span>💬 المساعد الذكي</span></a>
		        </li>
		        <?php else: ?>


		        <?php if (isset($_SESSION['store_user'])): ?>
		        <li class="treeview <?php if($cur_page == 'store-dashboard.php') {echo 'active';} ?>">
		          <a href="store-dashboard.php">
		            <i class="fa fa-dashboard"></i> <span>لوحة المتجر</span>
		          </a>
		        </li>
		        <?php endif; ?>

		        <li class="treeview <?php if($cur_page == 'index.php') {echo 'active';} ?>">
		          <a href="index.php">
		            <i class="fa fa-home"></i> <span>الرئيسية</span>
		          </a>
		        </li>

		        <!-- المنتجات -->
		        <li class="treeview <?php if(in_array($cur_page, ['product.php','product-add.php','product-edit.php','top-category.php','mid-category.php','end-category.php','size.php','size-add.php','size-edit.php','color.php','color-add.php','color-edit.php'])) {echo 'active';} ?>">
		          <a href="#">
		            <i class="fa fa-shopping-bag"></i>
		            <span>المنتجات</span>
		            <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>
		          </a>
		          <ul class="treeview-menu">
		            <li><a href="product.php"><i class="fa fa-circle-o"></i> إدارة المنتجات</a></li>
		            <li><a href="top-category.php"><i class="fa fa-circle-o"></i> الفئات الرئيسية</a></li>
		            <li><a href="mid-category.php"><i class="fa fa-circle-o"></i> الفئات الفرعية</a></li>
		            <li><a href="end-category.php"><i class="fa fa-circle-o"></i> الفئات النهائية</a></li>
		            <li><a href="size.php"><i class="fa fa-circle-o"></i> المقاسات</a></li>
		            <li><a href="color.php"><i class="fa fa-circle-o"></i> الألوان</a></li>
		          </ul>
		        </li>

		        <!-- الذكاء الاصطناعي -->
		        <li class="treeview <?php if(in_array($cur_page, ['ai-products.php','ai-campaigns.php','ai-settings.php','ai-keywords.php','ai-faqs.php','ai-rules.php','ai-analytics.php','ai-chat-logs.php','ai-assistant.php'])) {echo 'active';} ?>">
		          <a href="#">
		            <i class="fa fa-magic text-purple"></i>
		            <span>🤖 الذكاء الاصطناعي</span>
		            <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>
		          </a>
		          <ul class="treeview-menu">
		            <li><a href="ai-assistant.php"><i class="fa fa-comments text-purple"></i> المساعد الذكي</a></li>
		            <li><a href="ai-agents.php"><i class="fa fa-circle-o"></i> إدارة العملاء الآليين</a></li>
		            <li><a href="ai-tasks.php"><i class="fa fa-circle-o"></i> سجل المهام</a></li>
		            <li><a href="ai-analytics.php"><i class="fa fa-circle-o"></i> الإحصائيات</a></li>
		            <li><a href="ai-chat-logs.php"><i class="fa fa-circle-o"></i> سجلات المحادثات</a></li>
		          </ul>
		        </li>

		        <!-- AI Knowledge -->
		        <li class="treeview <?php if($cur_page == 'ai-knowledge.php') {echo 'active';} ?>">
		          <a href="#">
		            <i class="fa fa-book text-aqua"></i>
		            <span>🧠 مركز المعرفة (AI)</span>
		            <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>
		          </a>
		          <ul class="treeview-menu">
		            <li><a href="ai-knowledge.php"><i class="fa fa-circle-o"></i> قاعدة المعرفة</a></li>
		            <li><a href="ai-knowledge.php?category=sales"><i class="fa fa-circle-o"></i> قواعد المبيعات</a></li>
		            <li><a href="ai-knowledge.php?category=company"><i class="fa fa-circle-o"></i> سياسات الشركة</a></li>
		            <li><a href="ai-knowledge.php?category=shipping"><i class="fa fa-circle-o"></i> سياسات الشحن</a></li>
		            <li><a href="ai-knowledge.php?category=returns"><i class="fa fa-circle-o"></i> سياسات الاسترجاع</a></li>
		            <li><a href="ai-knowledge.php?category=payments"><i class="fa fa-circle-o"></i> سياسات الدفع</a></li>
		            <li><a href="ai-knowledge.php?category=products"><i class="fa fa-circle-o"></i> أدلة المنتجات</a></li>
		            <li><a href="ai-knowledge.php?category=marketing"><i class="fa fa-circle-o"></i> أدلة العلامة التجارية</a></li>
		            <li><a href="ai-knowledge.php?category=style"><i class="fa fa-circle-o"></i> أسلوب الكتابة</a></li>
		            <li><a href="ai-knowledge.php?category=variables"><i class="fa fa-circle-o"></i> متغيرات التلقين</a></li>
		          </ul>
		        </li>

		        <!-- OmniChannel Hub -->
		        <li class="treeview <?php if(in_array($cur_page, ['omni-channels.php','omni-inbox.php','omni-meta-monitor.php','omni-settings.php','omni-guide.php'])) {echo 'active';} ?>">
		          <a href="#">
		            <i class="fa fa-comments text-aqua"></i>
		            <span>💬 مركز الرسائل الشامل</span>
		            <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>
		          </a>
		          <ul class="treeview-menu">
		            <li><a href="omni-inbox.php"><i class="fa fa-inbox"></i> صندوق الوارد الموحد</a></li>
		            <li><a href="omni-channels.php"><i class="fa fa-plug"></i> إدارة القنوات</a></li>
		            <li><a href="omni-meta-monitor.php"><i class="fa fa-heartbeat text-danger"></i> مراقبة العمليات</a></li>
		            <li><a href="omni-settings.php"><i class="fa fa-cog"></i> إعدادات الربط</a></li>
		            <li><a href="omni-guide.php"><i class="fa fa-book text-warning"></i> استفسارات وتوضيحات</a></li>
		          </ul>
		        </li>

		        <!-- Marketing Center -->
		        <li class="treeview <?php if(in_array($cur_page, ['marketing-center.php','marketing-campaigns.php','marketing-accounts.php','marketing-adsets.php','marketing-ads.php','marketing-creatives.php','marketing-leads.php','marketing-analytics.php','marketing-ai.php','marketing-automation.php','marketing-ab-testing.php','marketing-pixel-center.php','marketing-audience.php','marketing-settings.php','marketing-campaign-wizard.php'])) {echo 'active';} ?>">
		          <a href="#">
		            <i class="fa fa-rocket" style="color:#1877f2"></i>
		            <span>🚀 مركز التسويق</span>
		            <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>
		          </a>
		          <ul class="treeview-menu">
		            <li><a href="marketing-center.php"><i class="fa fa-dashboard"></i> لوحة التحكم</a></li>
		            <li><a href="marketing-campaigns.php"><i class="fa fa-bullhorn"></i> الحملات</a></li>
		            <li><a href="marketing-audience.php"><i class="fa fa-users"></i> إدارة الجماهير</a></li>
		            <li><a href="marketing-creatives.php"><i class="fa fa-paint-brush"></i> استوديو الإبداع</a></li>
		            <li><a href="marketing-leads.php"><i class="fa fa-user-plus"></i> نماذج العملاء</a></li>
		            <li><a href="marketing-analytics.php"><i class="fa fa-line-chart"></i> الإحصائيات والعوائد</a></li>
		            <li><a href="marketing-ai.php"><i class="fa fa-brain text-info"></i> منسق الذكاء الاصطناعي</a></li>
		            <li><a href="marketing-automation.php"><i class="fa fa-bolt text-warning"></i> الأتمتة</a></li>
		            <li><a href="marketing-ab-testing.php"><i class="fa fa-flask"></i> اختبارات A/B</a></li>
		            <li><a href="marketing-pixel-center.php"><i class="fa fa-circle-o"></i> مركز البكسل</a></li>
		            <li><a href="marketing-accounts.php"><i class="fa fa-plug"></i> الحسابات الإعلانية</a></li>
		            <li><a href="marketing-campaign-wizard.php"><i class="fa fa-plus-circle text-success"></i> حملة جديدة</a></li>
		          </ul>
		        </li>

		        <!-- الطلبات -->
		        <li class="treeview <?php if(in_array($cur_page, ['order.php','order-details.php','order-statistics.php','exchange-requests.php','incomplete-orders.php'])) {echo 'active';} ?>">
		          <a href="#">
		            <i class="fa fa-sticky-note"></i>
		            <span>الطلبات</span>
		            <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>
		          </a>
		          <ul class="treeview-menu">
		            <li><a href="order.php"><i class="fa fa-circle-o"></i> إدارة الطلبات</a></li>
		            <li><a href="order-statistics.php"><i class="fa fa-circle-o"></i> الإحصائيات</a></li>
		            <li><a href="exchange-requests.php"><i class="fa fa-circle-o"></i> طلبات التبديل</a></li>
		            <li><a href="incomplete-orders.php"><i class="fa fa-circle-o"></i> الطلبات المعلقة</a></li>
		          </ul>
		        </li>

		        <!-- المتجر -->
		        <li class="treeview <?php if(in_array($cur_page, ['settings.php','slider.php','country.php','shipping-cost.php','shipping-cost-edit.php'])) {echo 'active';} ?>">
		          <a href="#">
		            <i class="fa fa-shopping-cart"></i>
		            <span>المتجر</span>
		            <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>
		          </a>
		          <ul class="treeview-menu">
		            <li><a href="settings.php"><i class="fa fa-circle-o"></i> الإعدادات</a></li>
		            <li><a href="slider.php"><i class="fa fa-circle-o"></i> السلايدر</a></li>
		            <li><a href="country.php"><i class="fa fa-circle-o"></i> الولايات / الدول</a></li>
		            <li><a href="shipping-cost.php"><i class="fa fa-circle-o"></i> تكاليف التوصيل</a></li>
		          </ul>
		        </li>

		        <!-- العملاء والموظفين -->
		        <li class="treeview <?php if(in_array($cur_page, ['customer.php','customer-add.php','customer-edit.php','employees.php','employee-ranking.php','employee-details.php','employee-add.php','commission-settings.php','performance-settings.php'])) {echo 'active';} ?>">
		          <a href="#">
		            <i class="fa fa-users"></i>
		            <span>العملاء والموظفين</span>
		            <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>
		          </a>
		          <ul class="treeview-menu">
		            <li><a href="customer.php"><i class="fa fa-circle-o"></i> العملاء</a></li>
		            <li><a href="employees.php"><i class="fa fa-circle-o"></i> الموظفين</a></li>
		            <li><a href="employee-ranking.php"><i class="fa fa-circle-o"></i> ترتيب الموظفين</a></li>
		            <li><a href="commission-settings.php"><i class="fa fa-circle-o"></i> العمولات</a></li>
		            <li><a href="performance-settings.php"><i class="fa fa-circle-o"></i> إعدادات التقييم</a></li>
		          </ul>
		        </li>

		        <!-- التوصيل -->
		        <li class="treeview <?php if($cur_page == 'delivery_list.php') {echo 'active';} ?>">
		          <a href="delivery_list.php">
		            <i class="fa fa-truck"></i> <span>شركات التوصيل</span>
		          </a>
		        </li>

		        <!-- الأحداث والتتبع -->
		        <li class="treeview <?php if(in_array($cur_page, ['event-settings.php','pixel.php','pixel-add.php','pixel-edit.php'])) {echo 'active';} ?>">
		          <a href="#">
		            <i class="fa fa-bell"></i>
		            <span>الأحداث والتتبع</span>
		            <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>
		          </a>
		          <ul class="treeview-menu">
		            <li><a href="event-settings.php"><i class="fa fa-circle-o"></i> مراقبة الأحداث</a></li>
		            <li><a href="pixel.php"><i class="fa fa-circle-o"></i> بكسلات التتبع</a></li>
		          </ul>
		        </li>

		        <!-- النظام -->
		        <li class="treeview <?php if(in_array($cur_page, ['site-security.php','audit-log.php','system-health.php','ai-insights.php','users.php','n8n-integration.php'])) {echo 'active';} ?>">
		          <a href="#">
		            <i class="fa fa-cog"></i>
		            <span>النظام</span>
		            <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>
		          </a>
		          <ul class="treeview-menu">
		            <li><a href="site-security.php"><i class="fa fa-circle-o"></i> أمان الموقع</a></li>
		            <li><a href="audit-log.php"><i class="fa fa-circle-o"></i> سجل التدقيق</a></li>
		            <li><a href="system-health.php"><i class="fa fa-circle-o"></i> صحة النظام</a></li>
		            <li><a href="ai-insights.php"><i class="fa fa-circle-o"></i> PHI Insights</a></li>
		            <li><a href="n8n-integration.php"><i class="fa fa-plug text-aqua"></i> تكامل n8n</a></li>
		            <?php if(isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'Super Admin'): ?>
		            <li><a href="users.php"><i class="fa fa-circle-o"></i> إدارة المستخدمين</a></li>
		            <?php endif; ?>
		          </ul>
		        </li>

        <?php endif; /* employee limited menu */ ?>
        </ul>
      </section>
    </aside>
    <?php Profiler::checkpoint('sidebar_rendered'); ?>

   		<div class="content-wrapper">
   		<?php Profiler::checkpoint('before_content'); ?>
