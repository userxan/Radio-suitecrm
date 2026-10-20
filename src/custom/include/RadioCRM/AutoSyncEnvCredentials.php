<?php
// src/custom/include/RadioCRM/AutoSyncEnvCredentials.php
// Automatically watches /opt/radio_crm_repo/.env and synchronizes SUITECRM_ADMIN_USER
// and SUITECRM_ADMIN_PASS into MariaDB if .env is edited, with zero manual commands needed.

(function() {
    $envFile = '/opt/radio_crm_repo/.env';
    if (!file_exists($envFile) || !is_readable($envFile)) {
        return;
    }

    $mtime = filemtime($envFile);
    $markerFile = sys_get_temp_dir() . '/.suitecrm_env_mtime';

    // If marker file exists and mtime matches, nothing changed -> instant return (0.01ms)
    if (file_exists($markerFile) && (int)@file_get_contents($markerFile) === $mtime) {
        return;
    }

    // .env was modified or created! Read the new settings:
    @file_put_contents($markerFile, (string)$mtime);

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines) {
        return;
    }

    $vars = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (strpos($line, '=') !== false) {
            list($k, $v) = explode('=', $line, 2);
            $vars[trim($k)] = trim($v);
        }
    }

    $adminUser = $vars['SUITECRM_ADMIN_USER'] ?? 'admin';
    $adminPass = $vars['SUITECRM_ADMIN_PASS'] ?? '';
    if (empty($adminPass)) {
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

    // Update User ID 1 (primary administrator)
    $mysqli->query("UPDATE users SET user_name='{$escapedUser}', user_hash='{$escapedHash}', status='Active', employee_status='Active', is_admin=1, deleted=0 WHERE id='1'");

    // If a secondary row with user_name = $adminUser exists with a different id, delete duplicate to satisfy Doctrine ORM
    $mysqli->query("DELETE FROM users WHERE user_name='{$escapedUser}' AND id != '1'");

    // Also ensure 'admin' and 'Xander' have active status and new password hash
    $extraUsers = ['admin', 'Xander'];
    foreach ($extraUsers as $extra) {
        if ($extra === $adminUser) {
            continue;
        }
        $escapedExtra = $mysqli->real_escape_string($extra);
        $chk = $mysqli->query("SELECT id FROM users WHERE user_name='{$escapedExtra}' AND deleted=0 LIMIT 1");
        if ($chk && $chk->num_rows > 0) {
            $mysqli->query("UPDATE users SET user_hash='{$escapedHash}', status='Active', employee_status='Active' WHERE user_name='{$escapedExtra}'");
        } else {
            $extraId = 'user_' . strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $extra)) . '_admin';
            $mysqli->query("INSERT INTO users (id, user_name, user_hash, status, employee_status, is_admin, deleted, sugar_login, first_name, last_name, date_entered, date_modified, created_by, modified_user_id) 
                            VALUES ('{$extraId}', '{$escapedExtra}', '{$escapedHash}', 'Active', 'Active', 1, 0, 1, 'Admin', '{$escapedExtra}', NOW(), NOW(), '1', '1')");
        }
    }

    $mysqli->close();
})();
