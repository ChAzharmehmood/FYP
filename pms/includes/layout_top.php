<?php
/**
 * Shared page header + navigation. Usage in a page:
 *   $pageTitle = 'Reports'; require PMS_ROOT . '/includes/layout_top.php';
 *   ... page content ...
 *   require PMS_ROOT . '/includes/layout_bottom.php';
 * Set $layoutPublic = true for login/recovery pages (no sidebar).
 */
declare(strict_types=1);

$pageTitle    = $pageTitle ?? 'Police Management System';
$layoutPublic = $layoutPublic ?? false;
$user         = $layoutPublic ? null : current_user();
$currentFile  = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));

$menus = [
    ROLE_ADMIN => [
        ['Dashboard', 'dashboard.php', 'fa-gauge'],
        ['Reports', 'manage_reports.php', 'fa-file-lines'],
        ['New Report', 'generate_report.php', 'fa-file-circle-plus'],
        ['Report Analysis', 'report_analysis.php', 'fa-chart-column'],
        ['Search Criminal', 'search_criminal.php', 'fa-magnifying-glass'],
        ['Police Stations', 'view_policestation.php', 'fa-building-shield'],
        ['Staff', 'view_staff.php', 'fa-users'],
        ['Duties', 'view_duties.php', 'fa-clipboard-list'],
        ['Leave Requests', 'admin_leave_requests.php', 'fa-calendar-check'],
        ['Alerts', 'alerts.php', 'fa-bell'],
        ['Audit Log', 'audit_log.php', 'fa-shield-halved'],
    ],
    ROLE_STATION => [
        ['Dashboard', 'admin_dashboard.php', 'fa-gauge'],
        ['Reports', 'manage_reports.php', 'fa-file-lines'],
        ['New Report', 'generate_report.php', 'fa-file-circle-plus'],
        ['Search Criminal', 'search_criminal.php', 'fa-magnifying-glass'],
        ['Staff', 'manage_staff.php', 'fa-users'],
        ['Duties', 'view_duties.php', 'fa-clipboard-list'],
        ['Assign Duty', 'assign_duties.php', 'fa-user-clock'],
        ['Leave Requests', 'admin_leave_requests.php', 'fa-calendar-check'],
        ['Alerts', 'alerts.php', 'fa-bell'],
    ],
    ROLE_STAFF => [
        ['Dashboard', 'staff_dashboard.php', 'fa-gauge'],
        ['New Report', 'generate_report.php', 'fa-file-circle-plus'],
        ['Station Reports', 'view_reports.php', 'fa-file-lines'],
        ['My Duties', 'view_staff_duty.php', 'fa-clipboard-list'],
        ['Request Leave', 'leave_request.php', 'fa-calendar-plus'],
        ['My Leave', 'leave_status.php', 'fa-calendar-check'],
    ],
];
$menu = $user ? ($menus[$user['role']] ?? []) : [];
$appName = (string) config('app_name');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - <?= e($appName) ?></title>
    <link rel="icon" href="<?= e(app_url('img/logo1.png')) ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="<?= e(app_url('assets/css/app.css')) ?>?v=2">
</head>
<body class="<?= $layoutPublic ? 'public-page' : 'app-page' ?>">
<?php if ($layoutPublic): ?>
<main class="public-wrap">
<?php else: ?>
<a class="visually-hidden-focusable" href="#main">Skip to content</a>
<nav class="navbar navbar-dark bg-navy d-lg-none px-3">
    <button class="btn btn-outline-light btn-sm" type="button" data-bs-toggle="offcanvas" data-bs-target="#sideNav" aria-controls="sideNav" aria-label="Open menu">
        <i class="fa-solid fa-bars"></i>
    </button>
    <span class="navbar-brand mb-0 h1 fs-6"><?= e($appName) ?></span>
    <a class="btn btn-outline-light btn-sm" href="<?= e(app_url('profile.php')) ?>" aria-label="Profile"><i class="fa-solid fa-user"></i></a>
</nav>
<div class="app-shell">
    <aside class="sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="sideNav" aria-label="Main navigation">
        <div class="sidebar-brand">
            <img src="<?= e(app_url('img/logo1.png')) ?>" alt="" width="44" height="44">
            <div>
                <div class="fw-bold">AJK Police</div>
                <small>Management System</small>
            </div>
            <button type="button" class="btn-close btn-close-white d-lg-none ms-auto" data-bs-dismiss="offcanvas" data-bs-target="#sideNav" aria-label="Close"></button>
        </div>
        <ul class="nav flex-column sidebar-nav">
            <?php foreach ($menu as [$label, $href, $icon]): ?>
                <li class="nav-item">
                    <a class="nav-link<?= $currentFile === $href ? ' active' : '' ?>" href="<?= e(app_url($href)) ?>"<?= $currentFile === $href ? ' aria-current="page"' : '' ?>>
                        <i class="fa-solid <?= e($icon) ?> fa-fw"></i> <?= e($label) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <div class="sidebar-user">
            <div class="fw-semibold text-truncate"><?= e($user['name']) ?></div>
            <div class="small text-truncate"><?= e(role_label($user['role'])) ?></div>
            <?php if (!empty($user['station_name'])): ?>
                <div class="small text-truncate"><i class="fa-solid fa-building-shield fa-fw"></i> <?= e($user['station_name']) ?></div>
            <?php endif; ?>
            <div class="mt-2 d-flex gap-2">
                <a class="btn btn-sm btn-outline-light flex-fill" href="<?= e(app_url('profile.php')) ?>"><i class="fa-solid fa-user"></i> Profile</a>
                <form method="post" action="<?= e(app_url('logout.php')) ?>" class="flex-fill">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-outline-warning w-100" type="submit"><i class="fa-solid fa-right-from-bracket"></i> Logout</button>
                </form>
            </div>
        </div>
    </aside>
    <main id="main" class="app-main">
        <header class="page-header">
            <h1 class="h4 mb-0"><?= e($pageTitle) ?></h1>
            <div class="text-muted small d-none d-md-block"><?= e(role_label($user['role'])) ?><?= !empty($user['station_name']) ? ' · ' . e($user['station_name']) : '' ?></div>
        </header>
<?php endif; ?>
<?php foreach (flash_pull() as $f): ?>
    <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show" role="alert">
        <?= e($f['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endforeach; ?>
