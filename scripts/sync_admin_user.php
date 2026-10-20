<?php
// scripts/sync_admin_user.php
// Ensures admin user and credentials from .env are synchronized to SuiteCRM users table.

$repoDir = dirname(__DIR__);
$envPaths = [
    $repoDir . '/.env',
    '/opt/radio_crm_repo/.env',
    '/var/www/html/.env.radiocrm',
    '/var/www/html/.env',
    '/var/www/.env'
];

foreach ($envPaths as $envFile) {
    if (file_exists($envFile) && is_readable($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines) {
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || strpos($line, '#') === 0) {
                    continue;
                }
                if (strpos($line, '=') !== false) {
                    list($k, $v) = explode('=', $line, 2);
                    $k = trim($k);
                    $v = trim(trim($v), "\"'");
                    putenv("{$k}={$v}");
                    $_ENV[$k] = $v;
                }
            }
            break;
        }
    }
}

$dbHost = getenv('DB_HOST') ?: 'mariadb';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_NAME') ?: 'suitecrm_db';
$dbUser = getenv('DB_USER') ?: 'suitecrm_user';
$dbPass = getenv('DB_PASS') ?: 'ChangeMe_DB_Pass!';
$dbRootPass = getenv('DB_ROOT_PASS') ?: 'ChangeMe_Root_Pass!';
$adminUser = getenv('SUITECRM_ADMIN_USER') ?: 'admin';
$adminPass = getenv('SUITECRM_ADMIN_PASS') ?: 'admin';

if (empty($adminPass)) {
    $adminPass = 'admin';
}

// Connect to MariaDB
$mysqli = @new mysqli($dbHost, $dbUser, $dbPass, $dbName, (int)$dbPort);
if ($mysqli->connect_error) {
    // Try as root
    $mysqli = @new mysqli($dbHost, 'root', $dbRootPass, $dbName, (int)$dbPort);
    if ($mysqli->connect_error) {
        fwrite(STDERR, "[SyncAdmin] DB connection failed: " . $mysqli->connect_error . "\n");
        exit(1);
    }
}

// Remove any stale non-admin or secondary users
$mysqli->query("DELETE FROM users WHERE id != '1'");

// SuiteCRM password hash: password_hash(strtolower(md5($password)), PASSWORD_DEFAULT)
$userHash = password_hash(strtolower(md5($adminPass)), PASSWORD_DEFAULT);
$escapedHash = $mysqli->real_escape_string($userHash);
$escapedUser = $mysqli->real_escape_string($adminUser);

// Ensure User ID 1 exists with configured username, password, and generic system admin identity
$res = $mysqli->query("SELECT id FROM users WHERE id='1'");
if ($res && $res->num_rows > 0) {
    $mysqli->query("UPDATE users SET 
        user_name='{$escapedUser}', 
        user_hash='{$escapedHash}', 
        first_name='System', 
        last_name='Administrator', 
        title='System Administrator',
        department=NULL,
        phone_work=NULL,
        phone_home=NULL,
        phone_mobile=NULL,
        phone_other=NULL,
        phone_fax=NULL,
        address_street=NULL,
        address_city=NULL,
        address_state=NULL,
        address_country=NULL,
        address_postalcode=NULL,
        status='Active', 
        employee_status='Active', 
        is_admin=1, 
        deleted=0 
    WHERE id='1'");
    echo "[SyncAdmin] Updated User ID 1 (user_name='{$adminUser}') with configured password.\n";
} else {
    $mysqli->query("INSERT INTO users (id, user_name, user_hash, status, employee_status, is_admin, deleted, sugar_login, first_name, last_name, title, date_entered, date_modified) 
                    VALUES ('1', '{$escapedUser}', '{$escapedHash}', 'Active', 'Active', 1, 0, 1, 'System', 'Administrator', 'System Administrator', NOW(), NOW())");
    echo "[SyncAdmin] Created User ID 1 (user_name='{$adminUser}').\n";
}

echo "[SyncAdmin] Done! Admin credentials synchronized. Username: '{$adminUser}' can log in with password '{$adminPass}'.\n";
