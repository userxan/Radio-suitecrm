<?php
if (!defined('sugarEntry') || !sugarEntry) {
    define('sugarEntry', true);
}

require_once 'custom/include/RadioCRM/RadioStationConfigService.php';

global $db, $current_user;

$branding = RadioStationConfigService::getBranding($db);
$stationName = $branding['station_name'] ?? 'Radio CRM';

$docMdPath = '/opt/radio_crm_repo/docs/CRMSuite_Full_Documentation.md';
if (!file_exists($docMdPath)) {
    $docMdPath = dirname(__DIR__, 4) . '/docs/CRMSuite_Full_Documentation.md';
}
$markdownContent = file_exists($docMdPath) ? (string)file_get_contents($docMdPath) : '# CRMSuite Documentation';

/**
 * Simple Markdown-to-HTML converter for clean browser, Word (.doc), and printable HTML rendering
 */
function renderDocumentationHtml(string $md): string
{
    $lines = explode("\n", str_replace("\r\n", "\n", $md));
    $html = '';
    $inCodeBlock = false;
    $inTable = false;
    $inList = false;

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if (strpos($trimmed, '```') === 0) {
            if ($inList) {
                $html .= "</ul>\n";
                $inList = false;
            }
            if ($inTable) {
                $html .= "</tbody></table>\n";
                $inTable = false;
            }
            if (!$inCodeBlock) {
                $inCodeBlock = true;
                $html .= '<pre style="background:#0f172a;color:#f8fafc;padding:14px 16px;border-radius:8px;overflow-x:auto;font-size:12.5px;line-height:1.5;"><code>';
            } else {
                $inCodeBlock = false;
                $html .= "</code></pre>\n";
            }
            continue;
        }

        if ($inCodeBlock) {
            $html .= htmlspecialchars($line) . "\n";
            continue;
        }

        // Table detection
        if (strpos($trimmed, '|') === 0 && substr($trimmed, -1) === '|') {
            if ($inList) {
                $html .= "</ul>\n";
                $inList = false;
            }
            $cells = array_map('trim', explode('|', trim($trimmed, '|')));
            // Check if separator row
            $isSep = true;
            foreach ($cells as $c) {
                if (!preg_match('/^:?-{2,}:?$/', $c)) {
                    $isSep = false;
                    break;
                }
            }
            if ($isSep) {
                continue;
            }
            if (!$inTable) {
                $inTable = true;
                $html .= '<table style="width:100%;border-collapse:collapse;margin:14px 0;font-size:13px;"><thead><tr style="background:#f1f5f9;border-bottom:2px solid #cbd5e1;">';
                foreach ($cells as $c) {
                    $html .= '<th style="padding:9px 12px;text-align:left;border:1px solid #cbd5e1;">' . formatInlineMd($c) . '</th>';
                }
                $html .= '</tr></thead><tbody>';
            } else {
                $html .= '<tr style="border-bottom:1px solid #e2e8f0;">';
                foreach ($cells as $c) {
                    $html .= '<td style="padding:8px 12px;border:1px solid #e2e8f0;">' . formatInlineMd($c) . '</td>';
                }
                $html .= '</tr>';
            }
            continue;
        } elseif ($inTable) {
            $html .= "</tbody></table>\n";
            $inTable = false;
        }

        if ($trimmed === '---') {
            if ($inList) {
                $html .= "</ul>\n";
                $inList = false;
            }
            $html .= '<hr style="border:none;border-top:1px solid #e2e8f0;margin:24px 0;">' . "\n";
            continue;
        }

        if (preg_match('/^(#{1,4})\s+(.+)$/', $trimmed, $m)) {
            if ($inList) {
                $html .= "</ul>\n";
                $inList = false;
            }
            $level = strlen($m[1]);
            $text = formatInlineMd($m[2]);
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9\-]+/', '-', strip_tags($m[2])));
            $styles = [
                1 => 'font-size:26px;color:#0f172a;border-bottom:2px solid #1e3a8a;padding-bottom:8px;margin-top:24px;',
                2 => 'font-size:20px;color:#1e3a8a;margin-top:26px;margin-bottom:10px;',
                3 => 'font-size:16px;color:#0f172a;margin-top:18px;margin-bottom:8px;',
                4 => 'font-size:14px;color:#334155;margin-top:14px;margin-bottom:6px;',
            ];
            $html .= sprintf('<h%d id="%s" style="%s">%s</h%d>' . "\n", $level, htmlspecialchars($slug), $styles[$level], $text, $level);
            continue;
        }

        if (preg_match('/^[-*]\s+(.+)$/', $trimmed, $m) || preg_match('/^\d+\.\s+(.+)$/', $trimmed, $m)) {
            if (!$inList) {
                $inList = true;
                $html .= '<ul style="margin:8px 0 12px 22px;line-height:1.6;">' . "\n";
            }
            $html .= '<li style="margin-bottom:4px;">' . formatInlineMd($m[1]) . "</li>\n";
            continue;
        } elseif ($inList && $trimmed === '') {
            $html .= "</ul>\n";
            $inList = false;
            continue;
        }

        if ($trimmed !== '') {
            $html .= '<p style="margin:8px 0;line-height:1.65;color:#1e293b;">' . formatInlineMd($trimmed) . "</p>\n";
        }
    }

    if ($inList) {
        $html .= "</ul>\n";
    }
    if ($inTable) {
        $html .= "</tbody></table>\n";
    }
    if ($inCodeBlock) {
        $html .= "</code></pre>\n";
    }
    return $html;
}

function formatInlineMd(string $text): string
{
    $escaped = htmlspecialchars($text);
    $escaped = preg_replace('/`([^`]+)`/', '<code style="background:#f1f5f9;color:#0f172a;padding:2px 5px;border-radius:4px;font-size:90%;">$1</code>', $escaped);
    $escaped = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $escaped);
    $escaped = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $escaped);
    $escaped = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" style="color:#2563eb;text-decoration:none;">$1</a>', $escaped);
    return $escaped;
}

$renderedBodyHtml = renderDocumentationHtml($markdownContent);

$standaloneHtmlDoc = '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>CRMSuite Full Documentation</title>
<style>
  body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; color: #1e293b; max-width: 980px; margin: 28px auto; padding: 0 24px; line-height: 1.6; }
  table { width: 100%; border-collapse: collapse; margin: 14px 0; }
  th, td { border: 1px solid #cbd5e1; padding: 8px 10px; text-align: left; }
  th { background: #f1f5f9; }
  pre { background: #0f172a; color: #f8fafc; padding: 12px; border-radius: 6px; overflow-x: auto; }
  code { font-family: Consolas, Monaco, monospace; }
</style>
</head>
<body>' . $renderedBodyHtml . '</body></html>';

// Handle direct file downloads (md, html, doc)
$downloadFormat = $_GET['download'] ?? '';
if ($downloadFormat === 'md') {
    header('Content-Type: text/markdown; charset=utf-8');
    header('Content-Disposition: attachment; filename="CRMSuite_Full_Documentation.md"');
    echo $markdownContent;
    exit;
}
if ($downloadFormat === 'html') {
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="CRMSuite_Full_Documentation.html"');
    echo $standaloneHtmlDoc;
    exit;
}
if ($downloadFormat === 'doc') {
    header('Content-Type: application/msword; charset=utf-8');
    header('Content-Disposition: attachment; filename="CRMSuite_Full_Documentation.doc"');
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40"><head><meta charset="utf-8"><title>CRMSuite Full Documentation</title></head><body>' . $renderedBodyHtml . '</body></html>';
    exit;
}

// Handle AJAX OneDrive Device Code Initiation & Polling/Upload
$onedriveAction = $_POST['onedrive_action'] ?? '';
$graphClientId = '14d82eec-204b-4c2f-b7e8-296a70dab67e';

if ($onedriveAction === 'start_device_auth') {
    header('Content-Type: application/json');
    $ch = curl_init('https://login.microsoftonline.com/common/oauth2/v2.0/devicecode');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'client_id' => $graphClientId,
            'scope' => 'Files.ReadWrite offline_access',
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    echo $resp ?: json_encode(['error' => 'network_error']);
    exit;
}

if ($onedriveAction === 'poll_and_upload') {
    header('Content-Type: application/json');
    $deviceCode = trim((string)($_POST['device_code'] ?? ''));
    $folderName = trim((string)($_POST['folder_name'] ?? 'CRMSuite')) ?: 'CRMSuite';
    $folderName = preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $folderName);

    $ch = curl_init('https://login.microsoftonline.com/common/oauth2/v2.0/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:device_code',
            'client_id' => $graphClientId,
            'device_code' => $deviceCode,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $tokenRaw = curl_exec($ch);
    curl_close($ch);
    $tokenData = json_decode((string)$tokenRaw, true);

    if (empty($tokenData['access_token'])) {
        echo json_encode([
            'status' => 'pending_or_error',
            'error' => $tokenData['error'] ?? 'unknown',
            'error_description' => $tokenData['error_description'] ?? '',
        ]);
        exit;
    }

    $accessToken = $tokenData['access_token'];

    // Upload Markdown, HTML, and Word (.doc) into OneDrive:/CRMSuite/
    $filesToUpload = [
        'CRMSuite_Full_Documentation.md' => [
            'type' => 'text/markdown',
            'content' => $markdownContent,
        ],
        'CRMSuite_Full_Documentation.html' => [
            'type' => 'text/html',
            'content' => $standaloneHtmlDoc,
        ],
        'CRMSuite_Full_Documentation.doc' => [
            'type' => 'application/msword',
            'content' => '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40"><head><meta charset="utf-8"><title>CRMSuite Full Documentation</title></head><body>' . $renderedBodyHtml . '</body></html>',
        ],
    ];

    $uploaded = [];
    foreach ($filesToUpload as $fileName => $meta) {
        $url = "https://graph.microsoft.com/v1.0/me/drive/root:/" . rawurlencode($folderName) . "/" . rawurlencode($fileName) . ":/content";
        $upCh = curl_init($url);
        curl_setopt_array($upCh, [
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $meta['content'],
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: ' . $meta['type'],
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $upRaw = curl_exec($upCh);
        $httpCode = curl_getinfo($upCh, CURLINFO_HTTP_CODE);
        curl_close($upCh);
        $upJson = json_decode((string)$upRaw, true);
        $uploaded[] = [
            'file' => $fileName,
            'http_code' => $httpCode,
            'web_url' => $upJson['webUrl'] ?? '',
            'id' => $upJson['id'] ?? '',
        ];
    }

    echo json_encode([
        'status' => 'uploaded',
        'folder' => $folderName,
        'files' => $uploaded,
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>CRMSuite Full Documentation & OneDrive Upload</title>
<style>
  body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; background: #f8fafc; color: #0f172a; margin: 0; padding: 20px; }
  .wrap { max-width: 1080px; margin: 0 auto; }
  .top-bar { background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%); color: #fff; padding: 22px 26px; border-radius: 12px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; margin-bottom: 18px; }
  .btn { display: inline-block; padding: 9px 15px; border-radius: 6px; font-size: 13px; font-weight: 700; text-decoration: none; cursor: pointer; border: none; }
  .btn-primary { background: #2563eb; color: #fff; }
  .btn-success { background: #16a34a; color: #fff; }
  .btn-light { background: rgba(255,255,255,0.16); color: #fff; border: 1px solid rgba(255,255,255,0.3); }
  .onedrive-card { background: #eff6ff; border: 1px solid #93c5fd; border-radius: 10px; padding: 18px 22px; margin-bottom: 18px; }
  .doc-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 28px 36px; box-shadow: 0 2px 8px rgba(15,23,42,0.05); }
  @media print {
    .no-print { display: none !important; }
    body { background: #fff; padding: 0; }
    .doc-card { border: none; box-shadow: none; padding: 0; }
  }
</style>
</head>
<body>
<div class="wrap">
  <div class="top-bar no-print">
    <div>
      <div style="font-size:11px;text-transform:uppercase;letter-spacing:1px;opacity:0.85;"><?php echo htmlspecialchars($stationName); ?> • Complete Technical & User Manual</div>
      <h1 style="margin:4px 0 0 0;font-size:22px;">CRMSuite Full Program Documentation</h1>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <a class="btn btn-light" href="index.php?module=Administration&action=radio_documentation&download=doc">⬇ Download Word (.doc)</a>
      <a class="btn btn-light" href="index.php?module=Administration&action=radio_documentation&download=html">⬇ Download HTML</a>
      <a class="btn btn-light" href="index.php?module=Administration&action=radio_documentation&download=md">⬇ Download Markdown (.md)</a>
      <button type="button" class="btn btn-light" onclick="window.print()">🖨 Print / Save as PDF</button>
    </div>
  </div>

  <!-- OneDrive Direct Cloud Upload Panel -->
  <div class="onedrive-card no-print">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
      <div>
        <div style="font-weight:700;font-size:15px;color:#1e3a8a;">☁️ Upload Directly to Microsoft OneDrive (Folder: <code>CRMSuite</code>)</div>
        <div style="font-size:12.5px;color:#334155;margin-top:3px;">
          Uploads <strong>CRMSuite_Full_Documentation.doc</strong>, <strong>.html</strong>, and <strong>.md</strong> directly into your personal or business OneDrive under <code>/CRMSuite/</code>.
        </div>
      </div>
      <div style="display:flex;gap:8px;align-items:center;">
        <label style="font-size:12px;font-weight:600;color:#1e3a8a;">OneDrive Folder:</label>
        <input type="text" id="onedrive_folder" value="CRMSuite" style="padding:7px 10px;border:1px solid #93c5fd;border-radius:6px;font-size:13px;width:130px;font-weight:600;">
        <button type="button" id="btn_onedrive_upload" class="btn btn-primary" onclick="startOneDriveUpload()">☁️ Connect & Upload to OneDrive</button>
      </div>
    </div>
    <div id="onedrive_status_box" style="display:none;margin-top:14px;padding:12px 16px;background:#fff;border:1px solid #bfdbfe;border-radius:8px;font-size:13px;"></div>
  </div>

  <div class="doc-card">
    <?php echo $renderedBodyHtml; ?>
  </div>
</div>

<script>
var pollTimer = null;
function startOneDriveUpload() {
  var statusBox = document.getElementById('onedrive_status_box');
  var btn = document.getElementById('btn_onedrive_upload');
  statusBox.style.display = 'block';
  statusBox.innerHTML = 'Requesting Microsoft OneDrive authorization code...';
  btn.disabled = true;

  var fd = new FormData();
  fd.append('onedrive_action', 'start_device_auth');

  fetch('index.php?entryPoint=radio_crm_documentation', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (!data || !data.user_code) {
        statusBox.innerHTML = '<span style="color:#dc2626;font-weight:700;">Could not initialize Microsoft Device Login. Please use the Download buttons above.</span>';
        btn.disabled = false;
        return;
      }
      statusBox.innerHTML =
        '<div style="line-height:1.6;">' +
        '<strong>Step 1:</strong> Click <a href="' + data.verification_uri + '" target="_blank" style="color:#2563eb;font-weight:700;text-decoration:underline;">' + data.verification_uri + '</a> and enter code: ' +
        '<span style="background:#fef3c7;border:1px solid #f59e0b;padding:3px 10px;border-radius:6px;font-family:monospace;font-size:16px;font-weight:800;letter-spacing:1px;">' + data.user_code + '</span><br>' +
        '<strong>Step 2:</strong> Sign in with your Microsoft OneDrive account. As soon as you approve, this page will automatically create the <code>' + document.getElementById('onedrive_folder').value + '</code> folder and upload the documentation! ' +
        '<span id="onedrive_spinner" style="color:#2563eb;font-weight:600;">(Waiting for Microsoft sign-in...)</span>' +
        '</div>';

      if (pollTimer) clearInterval(pollTimer);
      pollTimer = setInterval(function() {
        pollOneDriveToken(data.device_code);
      }, 5000);
    })
    .catch(function() {
      statusBox.innerHTML = '<span style="color:#dc2626;">Network error contacting Microsoft login service.</span>';
      btn.disabled = false;
    });
}

function pollOneDriveToken(deviceCode) {
  var fd = new FormData();
  fd.append('onedrive_action', 'poll_and_upload');
  fd.append('device_code', deviceCode);
  fd.append('folder_name', document.getElementById('onedrive_folder').value || 'CRMSuite');

  fetch('index.php?entryPoint=radio_crm_documentation', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(res) {
      if (res && res.status === 'uploaded') {
        clearInterval(pollTimer);
        var box = document.getElementById('onedrive_status_box');
        var linksHtml = '';
        (res.files || []).forEach(function(f) {
          linksHtml += '<li style="margin-top:4px;">✅ <strong>' + f.file + '</strong> (HTTP ' + f.http_code + ')' +
            (f.web_url ? ' — <a href="' + f.web_url + '" target="_blank" style="color:#2563eb;font-weight:600;">Open in OneDrive ↗</a>' : '') +
            '</li>';
        });
        box.innerHTML =
          '<div style="color:#15803d;font-weight:700;font-size:14px;">🎉 Successfully uploaded documentation to OneDrive folder <code>/' + res.folder + '/</code>!</div>' +
          '<ul style="margin:6px 0 0 18px;">' + linksHtml + '</ul>';
        document.getElementById('btn_onedrive_upload').disabled = false;
      }
    });
}
</script>
</body>
</html>
