<?php
/** Audit log viewer (head office only, read-only). */
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
$me = require_role([ROLE_ADMIN]);
if (!can_view_audit()) {
    deny();
}

$action = get_str('action', 80);
$actor  = get_str('actor', 100);
$entity = get_str('entity', 40);
$from   = get_str('from', 10);
$to     = get_str('to', 10);
$where  = ['1=1'];
$types  = '';
$params = [];
if ($action !== '') {
    $where[] = 'a.action LIKE ?';
    $types .= 's';
    $params[] = $action . '%';
}
if ($actor !== '') {
    $where[] = 'a.actor_name LIKE ?';
    $types .= 's';
    $params[] = '%' . $actor . '%';
}
if ($entity !== '') {
    $where[] = 'a.entity_type = ?';
    $types .= 's';
    $params[] = $entity;
}
if ($from !== '' && valid_date($from)) {
    $where[] = 'a.created_at >= ?';
    $types .= 's';
    $params[] = $from . ' 00:00:00';
}
if ($to !== '' && valid_date($to)) {
    $where[] = 'a.created_at <= ?';
    $types .= 's';
    $params[] = $to . ' 23:59:59';
}
$w = implode(' AND ', $where);
$total = (int) db_value("SELECT COUNT(*) FROM audit_logs a WHERE $w", $types, $params);
$pg = paginate($total, 50);
$rows = db_all("SELECT a.* FROM audit_logs a WHERE $w ORDER BY a.id DESC LIMIT ? OFFSET ?", $types . 'ii', array_merge($params, [$pg['per_page'], $pg['offset']]));
if (get_str('export') === 'csv') {
    audit_log('audit.export', 'audit_log', null, ['rows' => count($rows)]);
    csv_download('audit_log.csv', ['Time', 'Actor', 'Role', 'Action', 'Entity', 'Entity id', 'Details', 'IP'],
        array_map(fn($r) => [$r['created_at'], $r['actor_name'], $r['actor_role'], $r['action'], $r['entity_type'], $r['entity_id'], $r['meta'], $r['ip_address']], $rows));
}
$entities = db_all('SELECT DISTINCT entity_type FROM audit_logs WHERE entity_type IS NOT NULL ORDER BY entity_type');

$pageTitle = 'Audit Log';
require PMS_ROOT . '/includes/layout_top.php';
?>
<div class="card mb-3"><div class="card-body">
    <form method="get" class="row g-2 align-items-end">
        <div class="col-md-2"><label for="action" class="form-label">Action</label><input class="form-control" id="action" name="action" value="<?= e($action) ?>" placeholder="login, report…"></div>
        <div class="col-md-2"><label for="actor" class="form-label">Actor</label><input class="form-control" id="actor" name="actor" value="<?= e($actor) ?>"></div>
        <div class="col-md-2"><label for="entity" class="form-label">Entity</label>
            <select class="form-select" id="entity" name="entity"><option value="">All</option>
            <?php foreach ($entities as $en): ?><option value="<?= e($en['entity_type']) ?>" <?= $entity === $en['entity_type'] ? 'selected' : '' ?>><?= e($en['entity_type']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><label for="from" class="form-label">From</label><input type="date" class="form-control" id="from" name="from" value="<?= e($from) ?>"></div>
        <div class="col-md-2"><label for="to" class="form-label">To</label><input type="date" class="form-control" id="to" name="to" value="<?= e($to) ?>"></div>
        <div class="col-md-2 d-flex gap-2"><button class="btn btn-navy">Filter</button><a class="btn btn-outline-secondary" href="<?= e(app_url('audit_log.php')) ?>">Reset</a></div>
        <div class="col-12 small"><a href="<?= e(query_link(['export' => 'csv'])) ?>"><i class="fa-solid fa-file-csv"></i> Export this page as CSV</a></div>
    </form>
</div></div>
<div class="card"><div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-2"><span class="text-muted small"><?= $total ?> entries</span><?= pagination_html($pg) ?></div>
    <?php if (!$rows): ?><div class="empty-state"><i class="fa-regular fa-clipboard"></i><div>No audit entries match.</div></div>
    <?php else: ?>
    <div class="table-wrap"><table class="table table-sm table-hover align-middle">
        <thead><tr><th>Time</th><th>Actor</th><th>Action</th><th>Entity</th><th>Details</th><th>IP</th></tr></thead>
        <tbody><?php foreach ($rows as $r): ?>
            <tr>
                <td class="small text-nowrap"><?= fmt_datetime($r['created_at']) ?></td>
                <td class="small"><?= e($r['actor_name'] ?? '—') ?><?= $r['actor_role'] ? '<br><span class="text-muted">' . e(role_label($r['actor_role'])) . '</span>' : '' ?></td>
                <td><code><?= e($r['action']) ?></code></td>
                <td class="small"><?= e($r['entity_type'] ?? '') ?><?= $r['entity_id'] !== null ? ' #' . (int) $r['entity_id'] : '' ?></td>
                <td class="small text-break" style="max-width:360px"><?= e($r['meta'] ?? '') ?></td>
                <td class="small"><?= e($r['ip_address'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
    <p class="small text-muted mt-3 mb-0">The audit log is a normal database table written by the application. It supports accountability; it is not tamper-proof against someone with direct database access.</p>
</div></div>
<?php require PMS_ROOT . '/includes/layout_bottom.php'; ?>
