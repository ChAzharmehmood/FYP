<?php
if (isset($conn) && $conn instanceof mysqli) return;
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    if (getenv('VERCEL')) {
        foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'] as $key) {
            if (getenv($key) === false || getenv($key) === '') throw new RuntimeException('Missing database configuration.');
        }
    }
    $conn = mysqli_init();
    $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);
    $flags = 0;
    if (getenv('DB_SSL') === 'true') {
        $ca = getenv('DB_SSL_CA_PEM');
        $caPath = null;
        if ($ca) {
            $caPath = '/tmp/pms-mysql-ca.pem';
            if (file_put_contents($caPath, $ca) === false) throw new RuntimeException('Cannot write CA certificate.');
        }
        $conn->ssl_set(null, null, $caPath, null, null);
        $conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, true);
        $flags = MYSQLI_CLIENT_SSL;
    }
    $conn->real_connect(getenv('DB_HOST') ?: 'localhost', getenv('DB_USER') ?: 'root',
        getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '',
        getenv('DB_NAME') ?: 'db_pms', (int)(getenv('DB_PORT') ?: 3306), null, $flags);
    $conn->set_charset('utf8mb4');
} catch (Throwable $e) {
    error_log('PMS database connection failed: ' . $e->getMessage());
    http_response_code(503);
    exit('Database unavailable. Check database environment variables and server logs.');
}
