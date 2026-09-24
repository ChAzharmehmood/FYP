<?php
/** Staff: my leave requests, with withdrawal of pending ones. */
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/leave.php';
$me = require_login();
$id = (int) $me['id'];

if (is_post()) {
    csrf_verify();
    $lr = leave_find(post_int('id', 0) ?? 0);
    if (!$lr || (int) $lr['staff_id'] !== $id) {
        not_found('Leave request not found.');
    }
    $err = leave_withdraw($lr);
    flash($err ? 'danger' : 'success', $err ?? 'Request withdrawn.');
    redirect('leave_status.php');
}
$total = (int) db_value('SELECT COUNT(*) FROM leave_requests WHERE staff_id = ?', 'i', [$id]);
$pg = paginate($total, 20);
$rows = db_all('SELECT lr.*, lt.name AS type_name, rv.name AS reviewed_by_name FROM leave_requests lr LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id LEFT JOIN staff rv ON rv.id = lr.reviewed_by
                WHERE lr.staff_id = ? ORDER BY lr.id DESC LIMIT ? OFFSET ?', 'iii', [$id, $pg['per_page'], $pg['offset']]);
$pageTitle = 'My Leave Requests';
require PMS_ROOT . '/includes/layout_top.php';
?>
<div class="card"><div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-2"><a class="btn btn-sm btn-navy" href="<?= e(app_url('leave_request.php')) ?>"><i class="fa-solid fa-calendar-plus"></i> New request</a><?= pagination_html($pg) ?></div>
    <?php if (!$rows): ?><div class="empty-state"><i class="fa-regular fa-calendar-check"></i><div>You have not requested any leave yet.</div></div>
    <?php else: ?>
    <div class="table-wrap"><table class="table table-hover align-middle">
        <thead><tr><th>#</th><th>Type</th><th>From</th><th>To</th><th class="text-end">Requested</th><th class="text-end">Approved</th><th>Status</th><th>Reviewed</th><th>Submitted</th><th></th></tr></thead>
        <tbody><?php foreach ($rows as $r): ?>
            <tr>
                <td><?= (int) $r['id'] ?></td><td><?= e($r['type_name'] ?? $r['leave_type']) ?></td><td><?= fmt_date($r['leave_start_date']) ?></td><td><?= fmt_date($r['leave_end_date']) ?></td>
                <td class="text-end"><?= (int) $r['requested_days'] ?></td><td class="text-end"><?= $r['status'] === 'approved' ? (int) $r['approved_days'] : '—' ?></td>
                <td><?= status_badge($r['status']) ?></td>
                <td class="small"><?= $r['reviewed_by_name'] ? e($r['reviewed_by_name']) . '<br>' . fmt_datetime($r['reviewed_at']) : '—' ?><?= $r['review_reason'] ? '<br><em>' . e($r['review_reason']) . '</em>' : '' ?></td>
                <td class="small"><?= fmt_datetime($r['created_at']) ?></td>
                <td class="text-end">
                    <?php if ($r['status'] === 'pending' && strtotime($r['leave_start_date']) >= strtotime(date('Y-m-d'))): ?>
                    <form method="post" data-confirm="Withdraw this request?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-sm btn-outline-secondary">Withdraw</button></form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
</div></div>
<?php require PMS_ROOT . '/includes/layout_bottom.php'; ?>
