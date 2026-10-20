<?php

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once 'include/MVC/View/views/view.detail.php';

class RT_RadioCampaignsViewDetail extends ViewDetail
{
    public function display()
    {
        global $db;

        $recordId = trim((string) ($this->bean->id ?? ($_REQUEST['record'] ?? '')));
        if ($recordId !== '' && isset($this->bean) && is_object($this->bean)) {
            if (is_file('custom/include/RadioCRM/RadioScheduleService.php')) {
                require_once 'custom/include/RadioCRM/RadioScheduleService.php';
                $audioInfo = \RadioCRM\RadioScheduleService::findCampaignAudioFile(
                    $db,
                    $recordId,
                    (string) ($this->bean->filename ?? '')
                );
                if (is_array($audioInfo) && !empty($audioInfo['original_name'])) {
                    $this->bean->filename = (string) $audioInfo['original_name'];
                    $this->bean->file = (string) $audioInfo['original_name'];
                    $this->bean->file_mime_type = (string) ($audioInfo['mime_type'] ?? 'audio/mpeg');
                }
            }
        }

        // Check if an Invoice already exists for this campaign
        $existingInv = null;
        if ($recordId !== '' && is_object($db) && !empty($this->bean->name)) {
            $campNameEsc = $db->quote((string) $this->bean->name);
            $invRes = $db->query(
                "SELECT id, number, name, total_amount, status
                 FROM aos_invoices
                 WHERE name LIKE 'Invoice - {$campNameEsc}%' AND deleted = 0
                 ORDER BY date_entered DESC LIMIT 1",
                false
            );
            $existingInv = $invRes ? $db->fetchByAssoc($invRes) : null;
        }

        $audioFilename = trim((string) ($this->bean->file ?: ($this->bean->filename ?? '')));
        $audioMime = trim((string) ($this->bean->file_mime_type ?: 'audio/mpeg'));
        $token = trim((string) ($this->bean->client_approval_token ?? ''));
        $audioStatus = trim((string) ($_GET['audio_status'] ?? ''));
        $audioError = trim((string) ($_GET['audio_error'] ?? ''));

        $idEsc = htmlspecialchars($recordId, ENT_QUOTES, 'UTF-8');
        $fnEsc = htmlspecialchars($audioFilename, ENT_QUOTES, 'UTF-8');
        $mimeEsc = htmlspecialchars($audioMime, ENT_QUOTES, 'UTF-8');
        $tokEsc = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');

        echo <<<HTML
<script type="text/javascript">
window.uploadCampaignAudioInline = function(recordId) {
    var input = document.getElementById('inline_campaign_audio_input');
    var statusEl = document.getElementById('inline_campaign_audio_status');
    var btnEl = document.getElementById('inline_campaign_audio_btn');
    if (!input || !input.files || input.files.length === 0) {
        alert('Please choose an MP3 (.mp3) or WAV (.wav) audio file first.');
        return;
    }
    var file = input.files[0];
    var ext = (file.name.split('.').pop() || '').toLowerCase();
    if (ext !== 'mp3' && ext !== 'wav' && ext !== 'wave') {
        alert('Only MP3 (.mp3) and WAV (.wav) audio files are supported.');
        return;
    }
    if (statusEl) {
        statusEl.style.color = '#0969da';
        statusEl.textContent = 'Uploading ' + file.name + '...';
    }
    if (btnEl) {
        btnEl.disabled = true;
    }
    var fd = new FormData();
    fd.append('campaign_audio_file', file);
    fd.append('record', recordId);
    fd.append('ajax', '1');
    fetch('index.php?entryPoint=upload_campaign_audio&record=' + encodeURIComponent(recordId) + '&ajax=1', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res && res.success) {
            if (statusEl) {
                statusEl.style.color = '#1a7f37';
                statusEl.textContent = '✓ Uploaded ' + (res.filename || file.name) + '! Reloading...';
            }
            window.location.href = 'index.php?module=RT_RadioCampaigns&action=DetailView&record=' + encodeURIComponent(recordId) + '&audio_status=uploaded';
        } else {
            if (btnEl) btnEl.disabled = false;
            var msg = (res && res.message) ? res.message : 'Upload failed.';
            if (statusEl) {
                statusEl.style.color = '#cf222e';
                statusEl.textContent = '✗ ' + msg;
            } else {
                alert(msg);
            }
        }
    })
    .catch(function(err) {
        if (btnEl) btnEl.disabled = false;
        if (statusEl) {
            statusEl.style.color = '#cf222e';
            statusEl.textContent = '✗ Upload error: ' + err;
        }
    });
};
</script>
HTML;

        if ($recordId !== '') {
            echo '<div id="radio_campaign_command_bar" style="background:#f8f9fa;border:1px solid #d0d7de;border-left:4px solid #5E2B83;border-radius:6px;padding:12px 16px;margin:10px 0 14px 0;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;">';

            if ($audioStatus === 'uploaded') {
                echo '<div style="background:#dafbe1;color:#1a7f37;border:1px solid #4ac26b;padding:8px 12px;border-radius:4px;margin-bottom:10px;font-weight:600;font-size:13px;">✓ Advert audio file (' . $fnEsc . ') uploaded and synced successfully.</div>';
            } elseif ($audioStatus === 'removed') {
                echo '<div style="background:#fff8c5;color:#9a6700;border:1px solid #d4a72c;padding:8px 12px;border-radius:4px;margin-bottom:10px;font-weight:600;font-size:13px;">Advert audio file removed.</div>';
            } elseif ($audioError !== '') {
                echo '<div style="background:#ffebe9;color:#cf222e;border:1px solid #ff8182;padding:8px 12px;border-radius:4px;margin-bottom:10px;font-weight:600;font-size:13px;">✗ ' . htmlspecialchars($audioError, ENT_QUOTES, 'UTF-8') . '</div>';
            }

            echo '<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">';
            echo '<div style="display:flex;align-items:center;flex-wrap:wrap;gap:8px;">';
            echo '<span style="font-weight:700;color:#5E2B83;font-size:13px;margin-right:4px;">📻 Campaign Actions:</span>';

            // 1. Convert to Invoice button (Primary highlighted action)
            $invBtnLabel = $existingInv ? '💳 Update / Open Invoice' : '💳 Convert to Invoice';
            echo '<a href="index.php?entryPoint=convert_campaign_to_invoice&record=' . $idEsc . '" id="top_convert_to_invoice_btn" style="display:inline-block;padding:6px 14px;background:#1a7f37;color:#ffffff;font-weight:600;font-size:13px;border-radius:5px;text-decoration:none;">' . $invBtnLabel . '</a>';

            if ($existingInv && !empty($existingInv['id'])) {
                $invIdEsc = htmlspecialchars((string) $existingInv['id'], ENT_QUOTES, 'UTF-8');
                $invNumEsc = htmlspecialchars((string) ($existingInv['number'] ?? ''), ENT_QUOTES, 'UTF-8');
                echo '<a href="index.php?module=AOS_Invoices&action=DetailView&record=' . $invIdEsc . '" style="display:inline-block;padding:6px 12px;background:#dafbe1;color:#1a7f37;border:1px solid #4ac26b;font-weight:600;font-size:12px;border-radius:5px;text-decoration:none;">📄 View Invoice #' . $invNumEsc . '</a>';
            }

            // 2. Export to Myriad
            echo '<a href="index.php?entryPoint=export_radio_campaign_to_myriad&record=' . $idEsc . '" style="display:inline-block;padding:6px 12px;background:#5E2B83;color:#ffffff;font-weight:600;font-size:12px;border-radius:5px;text-decoration:none;">📡 Export to Myriad</a>';

            // 3. Reconcile Playout Logs
            echo '<a href="index.php?entryPoint=radio_campaign_reconcile_logs&record=' . $idEsc . '" style="display:inline-block;padding:6px 12px;background:#0969da;color:#ffffff;font-weight:600;font-size:12px;border-radius:5px;text-decoration:none;">📋 Reconcile Logs</a>';

            // 4. Make-Good Spots
            echo '<a href="index.php?entryPoint=radio_campaign_reconcile_logs&record=' . $idEsc . '&makegood=1" style="display:inline-block;padding:6px 12px;background:#6e7781;color:#ffffff;font-weight:600;font-size:12px;border-radius:5px;text-decoration:none;">🔄 Make-Good Spots</a>';

            // 5. Studio Live-Read Sheet
            echo '<a href="index.php?entryPoint=generate_presenter_liveread_sheet&record=' . $idEsc . '" target="_blank" style="display:inline-block;padding:6px 12px;background:#ffffff;color:#24292f;border:1px solid #d0d7de;font-weight:600;font-size:12px;border-radius:5px;text-decoration:none;">🎙️ Live-Read Sheet</a>';

            // 6. Broadcast Affidavit
            echo '<a href="index.php?entryPoint=generate_broadcast_affidavit&record=' . $idEsc . '" target="_blank" style="display:inline-block;padding:6px 12px;background:#ffffff;color:#24292f;border:1px solid #d0d7de;font-weight:600;font-size:12px;border-radius:5px;text-decoration:none;">📜 Broadcast Affidavit</a>';

            // 7. Client Review Link
            if ($tokEsc !== '') {
                echo '<a href="index.php?entryPoint=radio_campaign_client_review&token=' . $tokEsc . '" target="_blank" style="display:inline-block;padding:6px 12px;background:#ffffff;color:#5E2B83;border:1px solid #5E2B83;font-weight:600;font-size:12px;border-radius:5px;text-decoration:none;">🔗 Client Review Portal</a>';
            }

            echo '</div>';
            echo '</div>';

            // Direct MP3 / WAV Audio Uploader & Player Bar (outside <form name="DetailView">)
            echo '<div style="margin-top:10px;padding-top:10px;border-top:1px solid #e1e4e8;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">';
            echo '<div style="display:flex;align-items:center;flex-wrap:wrap;gap:10px;">';
            echo '<span style="font-weight:700;color:#24292f;font-size:13px;">🎵 Advert Audio (MP3 / WAV):</span>';
            if ($audioFilename !== '') {
                $extBadge = strtoupper((string) pathinfo($audioFilename, PATHINFO_EXTENSION)) ?: 'AUDIO';
                echo '<span style="background:#ddf4ff;color:#0969da;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700;">' . htmlspecialchars($extBadge, ENT_QUOTES, 'UTF-8') . '</span>';
                echo '<a href="index.php?entryPoint=upload_campaign_audio&record=' . $idEsc . '&download=1" style="font-weight:600;color:#0969da;font-size:13px;text-decoration:underline;">' . $fnEsc . '</a>';
                echo '<audio controls preload="metadata" style="height:30px;width:250px;vertical-align:middle;"><source src="index.php?entryPoint=upload_campaign_audio&record=' . $idEsc . '&stream=1" type="' . $mimeEsc . '"></audio>';
                echo '<a href="index.php?entryPoint=upload_campaign_audio&record=' . $idEsc . '&download=1" style="font-size:12px;color:#0969da;text-decoration:none;font-weight:600;">⬇ Download</a>';
                echo '<a href="index.php?entryPoint=upload_campaign_audio&record=' . $idEsc . '&remove_audio=1" onclick="return confirm(\'Remove attached audio file?\');" style="font-size:12px;color:#cf222e;text-decoration:none;font-weight:600;">🗑 Remove</a>';
            } else {
                echo '<span style="color:#57606a;font-size:12px;">No audio file uploaded yet. Select an <strong>.mp3</strong> or <strong>.wav</strong> file:</span>';
            }
            echo '</div>';

            echo '<form method="POST" action="index.php?entryPoint=upload_campaign_audio" enctype="multipart/form-data" style="margin:0;display:flex;align-items:center;gap:8px;">';
            echo '<input type="hidden" name="record" value="' . $idEsc . '" />';
            echo '<input type="file" name="campaign_audio_file" accept=".mp3,.wav,audio/mpeg,audio/wav,audio/x-wav" required style="font-size:12px;" />';
            $uploadLabel = ($audioFilename !== '') ? '⬆ Replace MP3 / WAV' : '⬆ Upload MP3 / WAV';
            echo '<button type="submit" style="padding:5px 12px;background:#5E2B83;color:#ffffff;border:none;border-radius:4px;font-weight:600;font-size:12px;cursor:pointer;">' . $uploadLabel . '</button>';
            echo '</form>';
            echo '</div>';

            echo '</div>';
        }

        parent::display();
    }
}
