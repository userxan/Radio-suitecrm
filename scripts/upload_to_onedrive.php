<?php
/**
 * Automated Microsoft OneDrive Uploader for CRMSuite Documentation
 * Uses Microsoft Graph OAuth2 Device Code Flow to create `/CRMSuite/` in the user's OneDrive
 * and upload CRMSuite_Full_Documentation.md, .html, and .doc.
 */

$repoRoot = dirname(__DIR__);
$mdPath = $repoRoot . '/docs/CRMSuite_Full_Documentation.md';
if (!file_exists($mdPath)) {
    fwrite(STDERR, "Error: Documentation file not found at $mdPath\n");
    exit(1);
}

$markdownContent = (string)file_get_contents($mdPath);

// Convert Markdown to clean HTML for .html and .doc files
function mdToHtmlSimple(string $md): string
{
    $lines = explode("\n", str_replace("\r\n", "\n", $md));
    $html = '';
    $inCode = false;
    $inTable = false;
    $inList = false;

    $inline = function (string $t): string {
        $e = htmlspecialchars($t);
        $e = preg_replace('/`([^`]+)`/', '<code style="background:#f1f5f9;padding:2px 5px;border-radius:4px;">$1</code>', $e);
        $e = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $e);
        $e = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $e);
        return $e;
    };

    foreach ($lines as $line) {
        $trim = trim($line);
        if (strpos($trim, '```') === 0) {
            if ($inList) { $html .= "</ul>\n"; $inList = false; }
            if ($inTable) { $html .= "</tbody></table>\n"; $inTable = false; }
            if (!$inCode) {
                $inCode = true;
                $html .= '<pre style="background:#0f172a;color:#f8fafc;padding:12px;border-radius:6px;"><code>';
            } else {
                $inCode = false;
                $html .= "</code></pre>\n";
            }
            continue;
        }
        if ($inCode) {
            $html .= htmlspecialchars($line) . "\n";
            continue;
        }
        if (strpos($trim, '|') === 0 && substr($trim, -1) === '|') {
            if ($inList) { $html .= "</ul>\n"; $inList = false; }
            $cells = array_map('trim', explode('|', trim($trim, '|')));
            $sep = true;
            foreach ($cells as $c) {
                if (!preg_match('/^:?-{2,}:?$/', $c)) { $sep = false; break; }
            }
            if ($sep) continue;
            if (!$inTable) {
                $inTable = true;
                $html .= '<table border="1" cellspacing="0" cellpadding="6" style="width:100%;border-collapse:collapse;margin:12px 0;"><thead><tr style="background:#f1f5f9;">';
                foreach ($cells as $c) $html .= '<th>' . $inline($c) . '</th>';
                $html .= '</tr></thead><tbody>';
            } else {
                $html .= '<tr>';
                foreach ($cells as $c) $html .= '<td>' . $inline($c) . '</td>';
                $html .= '</tr>';
            }
            continue;
        } elseif ($inTable) {
            $html .= "</tbody></table>\n";
            $inTable = false;
        }
        if ($trim === '---') {
            if ($inList) { $html .= "</ul>\n"; $inList = false; }
            $html .= "<hr>\n";
            continue;
        }
        if (preg_match('/^(#{1,4})\s+(.+)$/', $trim, $m)) {
            if ($inList) { $html .= "</ul>\n"; $inList = false; }
            $lvl = strlen($m[1]);
            $html .= "<h{$lvl}>" . $inline($m[2]) . "</h{$lvl}>\n";
            continue;
        }
        if (preg_match('/^[-*]\s+(.+)$/', $trim, $m) || preg_match('/^\d+\.\s+(.+)$/', $trim, $m)) {
            if (!$inList) { $inList = true; $html .= "<ul>\n"; }
            $html .= '<li>' . $inline($m[1]) . "</li>\n";
            continue;
        } elseif ($inList && $trim === '') {
            $html .= "</ul>\n";
            $inList = false;
            continue;
        }
        if ($trim !== '') {
            $html .= '<p>' . $inline($trim) . "</p>\n";
        }
    }
    if ($inList) $html .= "</ul>\n";
    if ($inTable) $html .= "</tbody></table>\n";
    if ($inCode) $html .= "</code></pre>\n";
    return $html;
}

$bodyHtml = mdToHtmlSimple($markdownContent);
$fullHtml = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>CRMSuite Full Documentation</title><style>body{font-family:Segoe UI,Arial,sans-serif;max-width:960px;margin:24px auto;padding:0 20px;line-height:1.6;color:#1e293b;}table{border-collapse:collapse;width:100%;}th,td{border:1px solid #cbd5e1;padding:8px;text-align:left;}th{background:#f1f5f9;}</style></head><body>' . $bodyHtml . '</body></html>';
$wordDoc = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40"><head><meta charset="utf-8"><title>CRMSuite Full Documentation</title></head><body>' . $bodyHtml . '</body></html>';

// Save static copies in docs/ as well
file_put_contents($repoRoot . '/docs/CRMSuite_Full_Documentation.html', $fullHtml);
file_put_contents($repoRoot . '/docs/CRMSuite_Full_Documentation.doc', $wordDoc);

if (($argv[1] ?? '') === '--generate-only') {
    echo "Generated static HTML and Word (.doc) files in docs/\n";
    exit(0);
}

$clientId = '14d82eec-204b-4c2f-b7e8-296a70dab67e';
$folderName = $argv[1] ?? 'CRMSuite';

$ch = curl_init('https://login.microsoftonline.com/common/oauth2/v2.0/devicecode');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'client_id' => $clientId,
        'scope' => 'Files.ReadWrite offline_access',
    ]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
]);
$resp = curl_exec($ch);
curl_close($ch);
$deviceData = json_decode((string)$resp, true);

if (empty($deviceData['device_code'])) {
    fwrite(STDERR, "Failed to request device code: $resp\n");
    exit(1);
}

echo "======================================================================\n";
echo "MICROSOFT ONEDRIVE AUTHORIZATION REQUIRED\n";
echo "1. Open URL : " . $deviceData['verification_uri'] . "\n";
echo "2. Enter Code: " . $deviceData['user_code'] . "\n";
echo "Target Folder: OneDrive:/" . $folderName . "/\n";
echo "======================================================================\n";
fflush(STDOUT);

$interval = max(5, (int)($deviceData['interval'] ?? 5));
$expiresAt = time() + (int)($deviceData['expires_in'] ?? 900);

while (time() < $expiresAt) {
    sleep($interval);
    $tch = curl_init('https://login.microsoftonline.com/common/oauth2/v2.0/token');
    curl_setopt_array($tch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:device_code',
            'client_id' => $clientId,
            'device_code' => $deviceData['device_code'],
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $tRaw = curl_exec($tch);
    curl_close($tch);
    $tJson = json_decode((string)$tRaw, true);

    if (!empty($tJson['access_token'])) {
        $token = $tJson['access_token'];
        echo "Authenticated with Microsoft OneDrive! Uploading files to /{$folderName}/...\n";
        fflush(STDOUT);

        $uploads = [
            'CRMSuite_Full_Documentation.doc' => ['application/msword', $wordDoc],
            'CRMSuite_Full_Documentation.html' => ['text/html', $fullHtml],
            'CRMSuite_Full_Documentation.md' => ['text/markdown', $markdownContent],
        ];

        foreach ($uploads as $fname => [$mime, $body]) {
            $url = "https://graph.microsoft.com/v1.0/me/drive/root:/" . rawurlencode($folderName) . "/" . rawurlencode($fname) . ":/content";
            $uch = curl_init($url);
            curl_setopt_array($uch, [
                CURLOPT_CUSTOMREQUEST => 'PUT',
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $token,
                    'Content-Type: ' . $mime,
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
            ]);
            $uRaw = curl_exec($uch);
            $code = curl_getinfo($uch, CURLINFO_HTTP_CODE);
            curl_close($uch);
            $uJson = json_decode((string)$uRaw, true);
            $webUrl = $uJson['webUrl'] ?? 'uploaded';
            echo "[HTTP {$code}] Uploaded {$fname} -> {$webUrl}\n";
            fflush(STDOUT);
        }

        echo "OneDrive Upload Complete!\n";
        exit(0);
    }

    $err = $tJson['error'] ?? '';
    if ($err !== 'authorization_pending' && $err !== 'slow_down') {
        fwrite(STDERR, "OneDrive authorization failed: {$tRaw}\n");
        exit(1);
    }
}

fwrite(STDERR, "OneDrive authorization timed out.\n");
exit(1);
