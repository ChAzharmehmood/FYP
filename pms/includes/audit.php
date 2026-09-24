<?php
/**
 * Audit log. Records who did what to which record. Never stores passwords,
 * reset tokens, file contents, or full CNICs. This is an ordinary database
 * table: it supports accountability, it is not tamper-proof.
 */
declare(strict_types=1);

function audit_log(string $action, ?string $entityType = null, ?int $entityId = null, array $meta = [], ?array $actorOverride = null): void
{
    try {
        $actor = $actorOverride ?? (function_exists('current_user') ? current_user() : null);
        foreach (['password', 'password_hash', 'token', 'token_hash', 'new_password', 'current_password', 'password_confirm'] as $k) {
            unset($meta[$k]);
        }
        if (isset($meta['id_card_no'])) {
            $meta['id_card_no'] = mask_cnic((string) $meta['id_card_no']);
        }
        $json = $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null;
        db_exec(
            'INSERT INTO audit_logs (actor_id, actor_name, actor_role, action, entity_type, entity_id, meta, ip_address, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            'issssiss',
            [
                isset($actor['id']) ? (int) $actor['id'] : null,
                $actor['name'] ?? null,
                $actor['role'] ?? null,
                $action,
                $entityType,
                $entityId,
                $json,
                client_ip(),
            ]
        );
    } catch (Throwable $t) {
        error_log('audit_log failed: ' . $t->getMessage());
    }
}
