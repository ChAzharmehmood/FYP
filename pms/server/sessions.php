<?php
// MySQL-backed sessions survive cold starts and multiple function instances.
class PmsDatabaseSessions implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    private mysqli $db;
    private ?string $lock = null;
    public function __construct(mysqli $db) { $this->db = $db; }
    public function open(string $path, string $name): bool { return true; }
    public function close(): bool {
        if ($this->lock !== null) {
            $stmt = $this->db->prepare('SELECT RELEASE_LOCK(?)');
            $stmt->bind_param('s', $this->lock);
            $stmt->execute();
            $this->lock = null;
        }
        return true;
    }
    public function read(string $id): string|false {
        $this->lock = 'pms:' . substr(hash('sha256', $id), 0, 60);
        $stmt = $this->db->prepare('SELECT GET_LOCK(?, 10)');
        $stmt->bind_param('s', $this->lock);
        $stmt->execute();
        if ((int)$stmt->get_result()->fetch_row()[0] !== 1) {
            $this->lock = null;
            throw new RuntimeException('Session busy; retry request.');
        }
        $stmt = $this->db->prepare('SELECT data FROM app_sessions WHERE id = ? AND expires_at > UNIX_TIMESTAMP()');
        $stmt->bind_param('s', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ? $row['data'] : '';
    }
    public function write(string $id, string $data): bool {
        $expires = time() + (int)ini_get('session.gc_maxlifetime');
        $stmt = $this->db->prepare('INSERT INTO app_sessions (id, data, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE data = VALUES(data), expires_at = VALUES(expires_at)');
        $stmt->bind_param('ssi', $id, $data, $expires);
        return $stmt->execute();
    }
    public function destroy(string $id): bool {
        $stmt = $this->db->prepare('DELETE FROM app_sessions WHERE id = ?');
        $stmt->bind_param('s', $id);
        return $stmt->execute();
    }
    public function gc(int $max_lifetime): int|false {
        $this->db->query('DELETE FROM app_sessions WHERE expires_at < UNIX_TIMESTAMP()');
        return $this->db->affected_rows;
    }
    public function validateId(string $id): bool {
        $stmt = $this->db->prepare('SELECT id FROM app_sessions WHERE id = ? AND expires_at > UNIX_TIMESTAMP()');
        $stmt->bind_param('s', $id);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }
    public function updateTimestamp(string $id, string $data): bool { return $this->write($id, $data); }
}
