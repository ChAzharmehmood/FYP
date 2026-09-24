<?php
/** Alerts: create, edit, deactivate, archive. Head office targets anyone; a station admin only their own station. */
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/alerts.php';
$me = require_role([ROLE_ADMIN, ROLE_STATION]);

$editing = null;
$editId  = get_int('edit', 0) ?? 0;
if ($editId > 0) {
    $editing = alert_find($editId);
    if (!$editing || !can_edit_alert($editing)) {
        not_found('Alert not found.');
    }
}

if (is_post()) {
    csrf_verify();
    $action = post_str('action', 20);
    $id     = post_int('id', 0) ?? 0;

    if ($action === 'save') {
        $title   = post_str('title', 150);
        $message = post_str('message', 2000);
        $target  = post_str('target_type', 10);
        $distId  = post_int('target_district_id');
        $statId  = post_int('target_station_id');
        $starts  = post_str('starts_at', 20);
        $expires = post_str('expires_at', 20);
        $errors  = [];
        if (is_station_admin()) {
            // Server-side enforcement: a station admin can only target their own station.
            $target = 'station';
            $statId = user_station_id();
            $distId = null;
        }
        if ($message === '') {
            $errors[] = 'Message is required.';
        }
        if (!in_array($target, ['all', 'district', 'station'], true)) {
            $errors[] = 'Invalid target.';
        }
        if ($target === 'district' && (!$distId || !db_one('SELECT id FROM districts WHERE id = ?', 'i', [$distId]))) {
            $errors[] = 'Choose a district.';
        }
        if ($target === 'station' && (!$statId || !db_one('SELECT id FROM police_stations WHERE id = ?', 'i', [$statId]))) {
            $errors[] = 'Choose a police station.';
        }
        if ($target === 'all') {
            $distId = null;
            $statId = null;
        }
        if ($target === 'district') {
            $statId = null;
        }
        if ($target === 'station') {
            $distId = null;
        }
        $startsAt  = $starts !== '' ? ($startsAt = valid_datetime_local($starts) ? date('Y-m-d H:i:s', strtotime($starts)) : null) : now_sql();
        $expiresAt = $expires !== '' ? (valid_datetime_local($expires) ? date('Y-m-d H:i:s', strtotime($expires)) : null) : null;
        if ($starts !== '' && $startsAt === null) {
            $errors[] = 'Invalid start time.';
        }
        if ($expires !== '' && $expiresAt === null) {
            $errors[] = 'Invalid expiry time.';
        }
        if ($startsAt && $expiresAt && strtotime($expiresAt) <= strtotime($startsAt)) {
            $errors[] = 'Expiry must be after the start time.';
        }
        if ($errors) {
            foreach ($errors as $er) {
                flash('danger', $er);
            }
            keep_old($_POST);
            redirect('alerts.php' . ($id ? '?edit=' . $id : ''));
        }
        if ($id > 0) {
            $a = alert_find($id);
            if (!$a || !can_edit_alert($a)) {
                deny('You may not edit that alert.');
            }
            db_exec('UPDATE alerts SET title = NULLIF(?, ""), message = ?, target_type = ?, target_district_id = ?, target_station_id = ?, starts_at = ?, expires_at = ? WHERE id = ?',
                'sssiissi', [$title, $message, $target, $distId, $statId, $startsAt, $expiresAt, $id]);
            audit_log('alert.update', 'alert', $id, ['target' => $target]);
            flash('success', 'Alert updated.');
        } else {
            db_exec('INSERT INTO alerts (title, message, target_type, target_district_id, target_station_id, starts_at, expires_at, status, is_active, created_by, created_at)
                     VALUES (NULLIF(?, ""), ?, ?, ?, ?, ?, ?, "active", 1, ?, NOW())',
                'sssiissi', [$title, $message, $target, $distId, $statId, $startsAt, $expiresAt, (int) $me['id']]);
            audit_log('alert.create', 'alert', db_insert_id(), ['target' => $target]);
            flash('success', 'Alert published.');
        }
        clear_old();
        redirect('alerts.php');
    }

    if (in_array($action, ['deactivate', 'activate', 'archive'], true)) {
        $a = alert_find($id);
        if (!$a || !can_edit_alert($a)) {
            deny('You may not change that alert.');
        }
        $new = $action === 'archive' ? 'archived' : ($action === 'activate' ? 'active' : 'inactive');
        db_exec('UPDATE alerts SET status = ?, is_active = ? WHERE id = ?', 'sii', [$new, $new === 'active' ? 1 : 0, $id]);
        audit_log('alert.' . $action, 'alert', $id);
        flash('success', 'Alert ' . $action . 'd.');
        redirect('alerts.php');
    }
}

$show = get_str('show', 10) ?: 'current';
[$scopeSql, $scopeTypes, $scopeParams] = ['1=1', '', []];
if (is_station_admin()) {
    $scopeSql = '(a.target_type = "all" OR (a.target_type = "station" AND a.target_station_id = ?) OR (a.target_type = "district" AND a.target_district_id = ?))';
    $scopeTypes = 'ii';
    $scopeParams = [(int) user_station_id(), (int) ($me['district_id'] ?? 0)];
}
$where = $show === 'archived' ? 'a.status = "archived"' : 'a.status <> "archived"';
$total = (int) db_value("SELECT COUNT(*) FROM alerts a WHERE $where AND $scopeSql", $scopeTypes, $scopeParams);
$pg    = paginate($total, 15);
$rows  = db_all(
    "SELECT a.*, s.name AS created_by_name, d.name AS district_name, ps.police_station_name AS station_name
     FROM alerts a LEFT JOIN staff s ON s.id = a.created_by LEFT JOIN districts d ON d.id = a.target_district_id LEFT JOIN police_stations ps ON ps.id = a.target_station_id
     WHERE $where AND $scopeSql ORDER BY a.created_at DESC LIMIT ? OFFSET ?",
    $scopeTypes . 'ii',
    array_merge($scopeParams, [$pg['per_page'], $pg['offset']])
);
$old = old_pull();
$fv = function (string $k, $default = '') use ($old, $editing) {
    return $old[$k] ?? ($editing[$k] ?? $default);
};
$toLocal = fn(?string $dt) => $dt ? date('Y-m-d\TH:i', strtotime($dt)) : '';

$pageTitle = 'Alerts';
require PMS_ROOT . '/includes/layout_top.php';
?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card"><div class="card-body">
            <h2 class="h6 text-uppercase text-muted mb-3"><?= $editing ? 'Edit alert #' . (int) $editing['id'] : 'New alert' ?></h2>
            <form method="post" class="needs-validation" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
                <div class="mb-3">
                    <label for="title" class="form-label">Title</label>
                    <input type="text" class="form-control" id="title" name="title" maxlength="150" value="<?= e($fv('title')) ?>">
                </div>
                <div class="mb-3">
                    <label for="message" class="form-label required">Message</label>
                    <textarea class="form-control" id="message" name="message" rows="4" required maxlength="2000"><?= e($fv('message')) ?></textarea>
                </div>
                <?php if (is_admin()): ?>
                <div class="mb-3">
                    <label for="target_type" class="form-label required">Send to</label>
                    <select class="form-select" id="target_type" name="target_type" required>
                        <?php foreach (['all' => 'Everyone', 'district' => 'One district', 'station' => 'One police station'] as $k => $v): ?>
                            <option value="<?= $k ?>" <?= $fv('target_type', 'all') === $k ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="target_district_id" class="form-label">District</label>
                    <select class="form-select" id="target_district_id" name="target_district_id">
                        <option value="">—</option>
                        <?php foreach (districts_all() as $d): ?>
                            <option value="<?= (int) $d['id'] ?>" <?= (int) $fv('target_district_id', 0) === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="target_station_id" class="form-label">Police station</label>
                    <select class="form-select" id="target_station_id" name="target_station_id">
                        <option value="">—</option>
                        <?php foreach (stations_all() as $s): ?>
                            <option value="<?= (int) $s['id'] ?>" <?= (int) $fv('target_station_id', 0) === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['police_station_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
                    <p class="small text-muted mb-3">This alert will be shown to staff of <strong><?= e($me['station_name'] ?? 'your station') ?></strong>.</p>
                <?php endif; ?>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label for="starts_at" class="form-label">Start</label>
                        <input type="datetime-local" class="form-control" id="starts_at" name="starts_at" value="<?= e($old['starts_at'] ?? $toLocal($editing['starts_at'] ?? null)) ?>">
                        <div class="form-text">Empty = now</div>
                    </div>
                    <div class="col-6">
                        <label for="expires_at" class="form-label">Expires</label>
                        <input type="datetime-local" class="form-control" id="expires_at" name="expires_at" value="<?= e($old['expires_at'] ?? $toLocal($editing['expires_at'] ?? null)) ?>">
                        <div class="form-text">Empty = no expiry</div>
                    </div>
                </div>
                <button type="submit" class="btn btn-navy"><?= $editing ? 'Save changes' : 'Publish alert' ?></button>
                <?php if ($editing): ?><a class="btn btn-outline-secondary" href="<?= e(app_url('alerts.php')) ?>">Cancel</a><?php endif; ?>
            </form>
        </div></div>
    </div>
    <div class="col-lg-8">
        <div class="card"><div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h6 text-uppercase text-muted mb-0"><?= $show === 'archived' ? 'Archived alerts' : 'Current alerts' ?> (<?= $total ?>)</h2>
                <div class="btn-group btn-group-sm">
                    <a class="btn btn-outline-secondary<?= $show !== 'archived' ? ' active' : '' ?>" href="?show=current">Current</a>
                    <a class="btn btn-outline-secondary<?= $show === 'archived' ? ' active' : '' ?>" href="?show=archived">Archived</a>
                </div>
            </div>
            <?php if (!$rows): ?>
                <div class="empty-state"><i class="fa-regular fa-bell-slash"></i><div>No alerts here.</div></div>
            <?php else: ?>
            <div class="table-wrap"><table class="table table-sm align-middle">
                <thead><tr><th>Alert</th><th>Target</th><th>Window</th><th>State</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $a): ?>
                    <tr>
                        <td>
                            <?php if ($a['title']): ?><div class="fw-semibold"><?= e($a['title']) ?></div><?php endif; ?>
                            <div class="small"><?= e(mb_strimwidth($a['message'], 0, 120, '…')) ?></div>
                            <div class="small text-muted">by <?= e($a['created_by_name'] ?? 'unknown') ?></div>
                        </td>
                        <td class="small"><?= e(alert_target_label($a)) ?></td>
                        <td class="small"><?= fmt_datetime($a['starts_at']) ?><br>to <?= $a['expires_at'] ? fmt_datetime($a['expires_at']) : 'no expiry' ?></td>
                        <td><?= status_badge($a['status']) ?><?= alert_is_live($a) ? ' <span class="badge text-bg-info">Live</span>' : '' ?></td>
                        <td class="table-actions text-end">
                            <?php if (can_edit_alert($a) && $a['status'] !== 'archived'): ?>
                                <a class="btn btn-sm btn-outline-primary" href="?edit=<?= (int) $a['id'] ?>">Edit</a>
                                <form method="post" class="d-inline">
                                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                    <?php if ($a['status'] === 'active'): ?>
                                        <button class="btn btn-sm btn-outline-secondary" name="action" value="deactivate">Deactivate</button>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-outline-success" name="action" value="activate">Activate</button>
                                    <?php endif; ?>
                                </form>
                                <form method="post" class="d-inline" data-confirm="Archive this alert? It will no longer be shown to anyone.">
                                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" name="action" value="archive">Archive</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <?= pagination_html($pg) ?>
            <?php endif; ?>
        </div></div>
    </div>
</div>
<?php require PMS_ROOT . '/includes/layout_bottom.php'; ?>
