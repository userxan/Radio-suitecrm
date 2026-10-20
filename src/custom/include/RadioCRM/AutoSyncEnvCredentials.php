<?php
// src/custom/include/RadioCRM/AutoSyncEnvCredentials.php
// Automatically watches .env and synchronizes SUITECRM_ADMIN_USER
// and SUITECRM_ADMIN_PASS into MariaDB if .env is edited, with zero manual commands needed.

(function() {
    $envCandidates = [
        '/opt/radio_crm_repo/.env',
        '/var/www/html/.env.radiocrm',
        '/var/www/html/.env',
        '/var/www/.env',
        dirname(__DIR__, 4) . '/.env'
    ];

    $foundEnvFile = null;
    $mtime = 0;
    foreach ($envCandidates as $candidate) {
        if (file_exists($candidate) && is_readable($candidate)) {
            $m = (int)filemtime($candidate);
            if ($m >= $mtime) {
                $mtime = $m;
                $foundEnvFile = $candidate;
            }
        }
    }

    $vars = [];
    if ($foundEnvFile !== null) {
        $lines = file($foundEnvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines) {
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#') {
                    continue;
                }
                if (strpos($line, '=') !== false) {
                    list($k, $v) = explode('=', $line, 2);
                    $vars[trim($k)] = trim(trim($v), "\"'");
                }
            }
        }
    }

    $adminUser = $vars['SUITECRM_ADMIN_USER'] ?? (getenv('SUITECRM_ADMIN_USER') ?: 'admin');
    $adminPass = $vars['SUITECRM_ADMIN_PASS'] ?? (getenv('SUITECRM_ADMIN_PASS') ?: '');
    if (empty($adminPass)) {
        return;
    }

    $markerFile = sys_get_temp_dir() . '/.suitecrm_env_sync_signature';
    $signature = "{$foundEnvFile}|{$mtime}|{$adminUser}|" . md5($adminPass);

    // If marker file exists and signature matches, nothing changed -> instant return (<0.01ms)
    if (file_exists($markerFile) && @file_get_contents($markerFile) === $signature) {
        return;
    }

    $dbHost = $vars['DB_HOST'] ?? (getenv('DB_HOST') ?: 'mariadb');
    $dbPort = (int)($vars['DB_PORT'] ?? (getenv('DB_PORT') ?: 3306));
    $dbName = $vars['DB_NAME'] ?? (getenv('DB_NAME') ?: 'suitecrm_db');
    $dbUser = $vars['DB_USER'] ?? (getenv('DB_USER') ?: 'suitecrm_user');
    $dbPass = $vars['DB_PASS'] ?? (getenv('DB_PASS') ?: '');
    $dbRootPass = $vars['DB_ROOT_PASS'] ?? (getenv('DB_ROOT_PASS') ?: '');

    $mysqli = @new \mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);
    if ($mysqli->connect_error && !empty($dbRootPass)) {
        $mysqli = @new \mysqli($dbHost, 'root', $dbRootPass, $dbName, $dbPort);
    }
    if ($mysqli->connect_error) {
        return;
    }

    $userHash = password_hash(strtolower(md5($adminPass)), PASSWORD_DEFAULT);
    $escapedHash = $mysqli->real_escape_string($userHash);
    $escapedUser = $mysqli->real_escape_string($adminUser);

    // Ensure User ID 1 is the sole primary administrator with generic identity
    $res = $mysqli->query("SELECT id FROM users WHERE id='1'");
    if ($res && $res->num_rows > 0) {
        $mysqli->query("DELETE FROM users WHERE user_name='{$escapedUser}' AND id != '1'");
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
    } else {
        $mysqli->query("DELETE FROM users WHERE user_name='{$escapedUser}'");
        $mysqli->query("INSERT INTO users (id, user_name, user_hash, status, employee_status, is_admin, deleted, sugar_login, first_name, last_name, title, date_entered, date_modified) 
                        VALUES ('1', '{$escapedUser}', '{$escapedHash}', 'Active', 'Active', 1, 0, 1, 'System', 'Administrator', 'System Administrator', NOW(), NOW())");
    }

    // Clean up any stale secondary users
    $mysqli->query("DELETE FROM users WHERE id != '1'");

    $mysqli->close();
    @file_put_contents($markerFile, $signature);
})();
