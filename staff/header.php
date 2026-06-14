<?php
require_once __DIR__ . '/auth.php';
$cur_page = basename($_SERVER['SCRIPT_NAME']);
$employee_name = htmlspecialchars($employee['full_name'] ?? '', ENT_QUOTES, 'UTF-8');

// Security headers
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-Frame-Options" content="SAMEORIGIN">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta name="referrer" content="strict-origin-when-cross-origin">
    <title>بوابة الموظفين | متجر الثقة</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-body: #f4f6f9;
            --bg-sidebar: #1e293b;
            --bg-card: #ffffff;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --text-sidebar: #cbd5e1;
            --text-sidebar-active: #ffffff;
            --border-color: #e2e8f0;
            --accent: #0ea5e9;
            --accent-hover: #0284c7;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
        }

        [data-bs-theme="dark"] {
            --bg-body: #0f172a;
            --bg-sidebar: #0f172a;
            --bg-card: #1e293b;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --border-color: #334155;
            --accent: #38bdf8;
        }

        * { box-sizing: border-box; }
        body {
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            min-height: 100vh;
            margin: 0;
            padding: 0;
            transition: background 0.2s, color 0.2s;
        }

        .staff-wrapper {
            display: flex;
            min-height: 100vh;
        }

        .staff-sidebar {
            width: 260px;
            background: var(--bg-sidebar);
            color: var(--text-sidebar);
            padding: 0;
            flex-shrink: 0;
            position: fixed;
            top: 0;
            bottom: 0;
            right: 0;
            z-index: 1040;
            overflow-y: auto;
            transition: transform 0.3s ease;
        }

        .staff-sidebar-brand {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            font-size: 18px;
            font-weight: 700;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .staff-sidebar-brand i { font-size: 24px; color: var(--accent); }

        .staff-sidebar-user {
            padding: 16px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            font-size: 14px;
        }

        .staff-sidebar-user strong { display: block; color: #fff; }
        .staff-sidebar-user small { color: var(--text-sidebar); }

        .staff-sidebar-nav { padding: 12px 0; }
        .staff-sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            color: var(--text-sidebar);
            text-decoration: none;
            font-size: 14px;
            transition: all 0.15s;
            border-right: 3px solid transparent;
        }

        .staff-sidebar-nav a:hover,
        .staff-sidebar-nav a.active {
            background: rgba(255,255,255,0.06);
            color: var(--text-sidebar-active);
            border-right-color: var(--accent);
        }

        .staff-sidebar-nav a i { width: 20px; text-align: center; font-size: 16px; }

        .staff-main {
            flex: 1;
            margin-right: 260px;
            padding: 20px;
            min-height: 100vh;
        }

        .staff-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .staff-topbar h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .staff-topbar-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .staff-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            transition: background 0.2s, border-color 0.2s;
        }

        .staff-card-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }

        .staff-card-value {
            font-size: 28px;
            font-weight: 800;
            color: var(--text-primary);
        }

        .staff-card-label {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 4px;
        }

        .btn-staff {
            border-radius: 10px;
            font-weight: 600;
            padding: 8px 16px;
            transition: all 0.15s;
        }

        .staff-table th {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--text-secondary);
            border-bottom: 2px solid var(--border-color);
        }

        .staff-table td {
            vertical-align: middle;
            border-color: var(--border-color);
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-badge.pending { background: #fef3c7; color: #92400e; }
        .status-badge.confirmed { background: #dbeafe; color: #1e40af; }
        .status-badge.completed { background: #d1fae5; color: #065f46; }
        .status-badge.cancelled { background: #fee2e2; color: #991b1b; }
        .status-badge.returned { background: #fef3c7; color: #92400e; }

        [data-bs-theme="dark"] .status-badge.pending { background: #451a03; color: #fbbf24; }
        [data-bs-theme="dark"] .status-badge.confirmed { background: #1e3a5f; color: #93c5fd; }
        [data-bs-theme="dark"] .status-badge.completed { background: #064e3b; color: #34d399; }
        [data-bs-theme="dark"] .status-badge.cancelled { background: #450a0a; color: #fca5a5; }
        [data-bs-theme="dark"] .status-badge.returned { background: #451a03; color: #fbbf24; }

        .staff-empty {
            text-align: center;
            padding: 48px 20px;
            color: var(--text-secondary);
        }

        .staff-empty i { font-size: 48px; margin-bottom: 16px; opacity: 0.5; }

        .sidebar-toggle-btn {
            display: none;
            background: none;
            border: none;
            color: var(--text-primary);
            font-size: 24px;
            cursor: pointer;
            padding: 4px;
        }

        .theme-toggle-btn {
            background: none;
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            border-radius: 10px;
            padding: 6px 12px;
            cursor: pointer;
            font-size: 14px;
        }

        @media (max-width: 767px) {
            .staff-sidebar {
                transform: translateX(260px);
            }
            .staff-sidebar.open {
                transform: translateX(0);
            }
            .staff-main {
                margin-right: 0;
            }
            .sidebar-toggle-btn {
                display: inline-block;
            }
        }
    </style>
</head>
<body>
<div class="staff-wrapper">
    <aside class="staff-sidebar" id="staffSidebar">
        <div class="staff-sidebar-brand">
            <i class="bi bi-shop"></i>
            متجر الثقة
        </div>
        <div class="staff-sidebar-user">
            <strong><?php echo $employee_name; ?></strong>
            <small>موظف</small>
        </div>
        <nav class="staff-sidebar-nav">
            <a href="index.php" class="<?php echo $cur_page === 'index.php' ? 'active' : ''; ?>">
                <i class="bi bi-grid-1x2"></i> لوحة التحكم
            </a>
            <a href="orders.php" class="<?php echo $cur_page === 'orders.php' ? 'active' : ''; ?>">
                <i class="bi bi-card-list"></i> طلباتي
            </a>
            <a href="commissions.php" class="<?php echo $cur_page === 'commissions.php' ? 'active' : ''; ?>">
                <i class="bi bi-cash-coin"></i> عمولاتي
            </a>
            <a href="performance.php" class="<?php echo $cur_page === 'performance.php' ? 'active' : ''; ?>">
                <i class="bi bi-graph-up-arrow"></i> أدائي
            </a>
            <a href="profile.php" class="<?php echo $cur_page === 'profile.php' ? 'active' : ''; ?>">
                <i class="bi bi-person-gear"></i> ملفي الشخصي
            </a>
            <hr style="border-color:rgba(255,255,255,0.08);margin:12px 20px;">
            <a href="logout.php">
                <i class="bi bi-box-arrow-right"></i> تسجيل الخروج
            </a>
        </nav>
    </aside>

    <main class="staff-main">
        <div class="staff-topbar">
            <div>
                <button class="sidebar-toggle-btn" onclick="document.getElementById('staffSidebar').classList.toggle('open')">
                    <i class="bi bi-list"></i>
                </button>
                <h1><?php echo $page_title ?? 'لوحة التحكم'; ?></h1>
            </div>
            <div class="staff-topbar-actions">
                <button class="theme-toggle-btn" onclick="toggleTheme()">
                    <i class="bi bi-moon-stars" id="themeIcon"></i>
                </button>
            </div>
        </div>
