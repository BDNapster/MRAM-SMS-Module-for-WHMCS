<?php
/**
 * MRAM SMS Module for WHMCS
 * Integrates with msg.mram.com.bd SMS gateway
 *
 * @package    WHMCS
 * @author     MRAM SMS Module
 * @version    1.0.0
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

function mram_sms_config()
{
    return [
        'name'        => 'MRAM SMS',
        'description' => 'Send SMS notifications to clients via MRAM SMS Gateway (msg.mram.com.bd). Supports bulk SMS, automated notifications, and delivery tracking.',
        'version'     => '10.0.0',
        'author'      => 'MRAM SMS Module',
        'fields'      => [
            'api_key' => [
                'FriendlyName' => 'API Key',
                'Type'         => 'password',
                'Size'         => '60',
                'Description'  => 'Your MRAM SMS API Key',
            ],
            'sender_id' => [
                'FriendlyName' => 'Sender ID',
                'Type'         => 'text',
                'Size'         => '20',
                'Description'  => 'Approved Sender ID / Masking Name',
            ],
            'sms_type' => [
                'FriendlyName' => 'SMS Type',
                'Type'         => 'dropdown',
                'Options'      => 'text,unicode',
                'Default'      => 'text',
                'Description'  => 'text = English, unicode = Bangla',
            ],
            'admin_phone' => [
                'FriendlyName' => 'Admin Phone',
                'Type'         => 'text',
                'Size'         => '20',
                'Description'  => 'Admin phone for alert SMS (e.g. 8801XXXXXXXXX)',
            ],
            'sms_label' => [
                'FriendlyName' => 'SMS Label',
                'Type'         => 'dropdown',
                'Options'      => 'transactional,promotional',
                'Default'      => 'transactional',
                'Description'  => 'SMS purpose label',
            ],
            'country_code' => [
                'FriendlyName' => 'Default Country Code',
                'Type'         => 'text',
                'Size'         => '5',
                'Default'      => '88',
                'Description'  => 'Default country code prefix (e.g. 88 for Bangladesh)',
            ],
        ],
    ];
}

function mram_sms_activate()
{
    try {
        // SMS Templates table
        if (!Capsule::schema()->hasTable('mod_mram_sms_templates')) {
            Capsule::schema()->create('mod_mram_sms_templates', function ($table) {
                $table->increments('id');
                $table->string('hook_name', 100)->unique();
                $table->string('friendly_name', 255);
                $table->text('message_template');
                $table->text('admin_template')->nullable();
                $table->text('available_variables');
                $table->tinyInteger('is_active')->default(1);
                $table->tinyInteger('admin_notify')->default(0);
                $table->timestamps();
            });
        }

        // Upgrade: add missing columns for older installs (order matters)
        if (Capsule::schema()->hasTable('mod_mram_sms_templates')) {
            $upgradeCols = [
                'message_template'    => function ($table) { $table->text('message_template')->nullable(); },
                'admin_template'      => function ($table) { $table->text('admin_template')->nullable(); },
                'available_variables' => function ($table) { $table->text('available_variables')->nullable(); },
                'is_active'           => function ($table) { $table->tinyInteger('is_active')->default(1); },
                'admin_notify'        => function ($table) { $table->tinyInteger('admin_notify')->default(0); },
                'created_at'          => function ($table) { $table->timestamp('created_at')->nullable(); },
                'updated_at'          => function ($table) { $table->timestamp('updated_at')->nullable(); },
            ];
            foreach ($upgradeCols as $col => $callback) {
                if (!Capsule::schema()->hasColumn('mod_mram_sms_templates', $col)) {
                    Capsule::schema()->table('mod_mram_sms_templates', $callback);
                }
            }
        }

        // SMS Log table - upgrade columns for older installs
        if (Capsule::schema()->hasTable('mod_mram_sms_log')) {
            $logUpgradeCols = [
                'client_id'     => function ($table) { $table->integer('client_id')->default(0); },
                'phone'         => function ($table) { $table->string('phone', 20)->nullable(); },
                'message'       => function ($table) { $table->text('message')->nullable(); },
                'hook_name'     => function ($table) { $table->string('hook_name', 100)->default('manual'); },
                'status'        => function ($table) { $table->string('status', 20)->default('pending'); },
                'api_response'  => function ($table) { $table->string('api_response', 255)->nullable(); },
                'sms_shoot_id'  => function ($table) { $table->string('sms_shoot_id', 100)->nullable(); },
                'error_code'    => function ($table) { $table->string('error_code', 10)->nullable(); },
                'created_at'    => function ($table) { $table->timestamp('created_at')->nullable(); },
            ];
            foreach ($logUpgradeCols as $col => $callback) {
                if (!Capsule::schema()->hasColumn('mod_mram_sms_log', $col)) {
                    Capsule::schema()->table('mod_mram_sms_log', $callback);
                }
            }
        }

        // SMS Log table
        if (!Capsule::schema()->hasTable('mod_mram_sms_log')) {
            Capsule::schema()->create('mod_mram_sms_log', function ($table) {
                $table->increments('id');
                $table->integer('client_id')->default(0);
                $table->string('phone', 20);
                $table->text('message');
                $table->string('hook_name', 100)->default('manual');
                $table->string('status', 20)->default('pending');
                $table->string('api_response', 255)->nullable();
                $table->string('sms_shoot_id', 100)->nullable();
                $table->string('error_code', 10)->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        // Insert default templates
        mram_sms_insert_default_templates();

        return [
            'status'      => 'success',
            'description' => 'MRAM SMS Module activated successfully. Please configure your API key and Sender ID.',
        ];
    } catch (\Exception $e) {
        return [
            'status'      => 'error',
            'description' => 'Error: ' . $e->getMessage(),
        ];
    }
}

function mram_sms_deactivate()
{
    try {
        Capsule::schema()->dropIfExists('mod_mram_sms_templates');
        Capsule::schema()->dropIfExists('mod_mram_sms_log');

        return [
            'status'      => 'success',
            'description' => 'MRAM SMS Module deactivated. All SMS data has been removed.',
        ];
    } catch (\Exception $e) {
        return [
            'status'      => 'error',
            'description' => 'Error: ' . $e->getMessage(),
        ];
    }
}

function mram_sms_output($vars)
{
    require_once __DIR__ . '/mram_sms_api.php';
    require_once __DIR__ . '/functions.php';

    $modulelink = $vars['modulelink'];
    $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'dashboard';

    // Handle AJAX requests
    if ($action === 'ajax_test_sms') {
        mram_sms_ajax_test($vars);
        return;
    }
    if ($action === 'ajax_send_bulk') {
        mram_sms_ajax_bulk($vars);
        return;
    }
    if ($action === 'ajax_save_template') {
        mram_sms_ajax_save_template();
        return;
    }
    if ($action === 'ajax_toggle_template') {
        mram_sms_ajax_toggle_template();
        return;
    }
    if ($action === 'ajax_check_balance') {
        ob_clean();
        header('Content-Type: application/json');
        $api = new MramSmsApi($vars['api_key']);
        $balance = $api->getBalance();
        echo json_encode(['balance' => $balance]);
        die();
    }
    if ($action === 'ajax_get_client_data') {
        mram_sms_ajax_get_client_data();
        return;
    }
    if ($action === 'ajax_send_manual') {
        mram_sms_ajax_send_manual($vars);
        return;
    }
    if ($action === 'ajax_check_dlr') {
        ob_clean();
        header('Content-Type: application/json');
        $api = new MramSmsApi($vars['api_key']);
        $shootId = isset($_REQUEST['shoot_id']) ? $_REQUEST['shoot_id'] : '';
        $dlr = $api->getDeliveryReport($shootId);
        echo json_encode(['dlr' => $dlr]);
        die();
    }
    if ($action === 'ajax_get_reports') {
        ob_clean();
        header('Content-Type: application/json');
        $api = new MramSmsApi($vars['api_key']);
        $dlr = $api->getDeliveryReport('');
        echo json_encode(['success' => true, 'data' => $dlr]);
        die();
    }

    // Navigation tabs
    echo '<style>
    .mram-nav { display:flex; gap:0; margin-bottom:20px; border-bottom:2px solid #ddd; }
    .mram-nav a { padding:10px 20px; text-decoration:none; color:#555; border-bottom:2px solid transparent; margin-bottom:-2px; font-weight:500; }
    .mram-nav a.active { color:#4e73df; border-bottom-color:#4e73df; }
    .mram-nav a:hover { color:#4e73df; }
    .mram-card { background:#fff; border:1px solid #e3e6f0; border-radius:6px; padding:20px; margin-bottom:20px; box-shadow:0 1px 3px rgba(0,0,0,0.05); }
    .mram-card h3 { margin-top:0; color:#333; border-bottom:1px solid #eee; padding-bottom:10px; }
    .mram-badge { display:inline-block; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:600; }
    .mram-badge-success { background:#d4edda; color:#155724; }
    .mram-badge-danger { background:#f8d7da; color:#721c24; }
    .mram-badge-warning { background:#fff3cd; color:#856404; }
    .mram-badge-info { background:#d1ecf1; color:#0c5460; }
    .mram-btn { display:inline-block; padding:8px 16px; border:none; border-radius:4px; cursor:pointer; font-size:14px; text-decoration:none; color:#fff; }
    .mram-btn-primary { background:#4e73df; }
    .mram-btn-success { background:#1cc88a; }
    .mram-btn-danger { background:#e74a3b; }
    .mram-btn-info { background:#36b9cc; }
    .mram-btn:hover { opacity:0.9; }
    .mram-table { width:100%; border-collapse:collapse; }
    .mram-table th, .mram-table td { padding:10px 12px; text-align:left; border-bottom:1px solid #e3e6f0; }
    .mram-table th { background:#f8f9fc; font-weight:600; color:#333; }
    .mram-table tr:hover { background:#f8f9fc; }
    .mram-stats { display:grid; grid-template-columns:repeat(auto-fit, minmax(200px,1fr)); gap:15px; margin-bottom:20px; }
    .mram-stat-card { background:#fff; border:1px solid #e3e6f0; border-radius:6px; padding:20px; text-align:center; }
    .mram-stat-card .number { font-size:28px; font-weight:700; color:#4e73df; }
    .mram-stat-card .label { font-size:13px; color:#888; margin-top:5px; }
    textarea.mram-textarea { width:100%; min-height:80px; padding:8px; border:1px solid #d1d3e2; border-radius:4px; font-family:monospace; }
    .mram-form-group { margin-bottom:15px; }
    .mram-form-group label { display:block; font-weight:600; margin-bottom:5px; color:#333; }
    .mram-form-group input[type=text], .mram-form-group select, .mram-form-group textarea { width:100%; max-width:500px; padding:8px; border:1px solid #d1d3e2; border-radius:4px; }
    .mram-switch { position:relative; display:inline-block; width:44px; height:24px; }
    .mram-switch input { opacity:0; width:0; height:0; }
    .mram-slider { position:absolute; cursor:pointer; top:0; left:0; right:0; bottom:0; background:#ccc; border-radius:24px; transition:.3s; }
    .mram-slider:before { position:absolute; content:""; height:18px; width:18px; left:3px; bottom:3px; background:#fff; border-radius:50%; transition:.3s; }
    .mram-switch input:checked + .mram-slider { background:#1cc88a; }
    .mram-switch input:checked + .mram-slider:before { transform:translateX(20px); }
    </style>';

    echo '<div class="mram-nav">';
    $tabs = ['dashboard' => '📊 Dashboard', 'templates' => '📝 Templates', 'bulk' => '📨 Bulk SMS', 'manual' => '✉️ Manual SMS', 'logs' => '📋 SMS Log', 'reports' => '📈 Reports', 'settings' => '⚙️ Settings'];
    foreach ($tabs as $key => $label) {
        $active = ($action === $key) ? 'active' : '';
        echo "<a href='{$modulelink}&action={$key}' class='{$active}'>{$label}</a>";
    }
    echo '</div>';

    switch ($action) {
        case 'templates':
            mram_sms_page_templates($vars);
            break;
        case 'bulk':
            mram_sms_page_bulk($vars);
            break;
        case 'manual':
            mram_sms_page_manual($vars);
            break;
        case 'reports':
            mram_sms_page_reports($vars);
            break;
        case 'logs':
            mram_sms_page_logs($vars);
            break;
        case 'settings':
            mram_sms_page_settings($vars);
            break;
        default:
            mram_sms_page_dashboard($vars);
            break;
    }
    echo '<div style="text-align:center; margin-top:30px; padding:15px; border-top:1px solid #e3e6f0; color:#888; font-size:13px;">';
    echo 'MRAM SMS Gateway Module v10.0 | Developed by <a href="https://codeisoft.com" target="_blank" style="color:#4e73df; text-decoration:none;">Codeisoft</a>';
    echo '</div>';
}

function mram_sms_page_dashboard($vars)
{
    require_once __DIR__ . '/mram_sms_api.php';
    $api = new MramSmsApi($vars['api_key']);

    // Stats
    $totalSent = Capsule::table('mod_mram_sms_log')->count();
    $totalSuccess = Capsule::table('mod_mram_sms_log')->where('status', 'sent')->count();
    $totalFailed = Capsule::table('mod_mram_sms_log')->where('status', 'failed')->count();
    $todaySent = Capsule::table('mod_mram_sms_log')->whereDate('created_at', date('Y-m-d'))->count();
    $activeTemplates = Capsule::table('mod_mram_sms_templates')->where('is_active', 1)->count();
    $totalTemplates = Capsule::table('mod_mram_sms_templates')->count();

    echo '<div class="mram-stats">';
    echo '<div class="mram-stat-card"><div class="number" id="mram-balance">...</div><div class="label">SMS Balance</div></div>';
    echo "<div class='mram-stat-card'><div class='number'>{$totalSent}</div><div class='label'>Total SMS Sent</div></div>";
    echo "<div class='mram-stat-card'><div class='number' style='color:#1cc88a'>{$totalSuccess}</div><div class='label'>Delivered</div></div>";
    echo "<div class='mram-stat-card'><div class='number' style='color:#e74a3b'>{$totalFailed}</div><div class='label'>Failed</div></div>";
    echo "<div class='mram-stat-card'><div class='number'>{$todaySent}</div><div class='label'>Today</div></div>";
    echo "<div class='mram-stat-card'><div class='number'>{$activeTemplates}/{$totalTemplates}</div><div class='label'>Active Templates</div></div>";
    echo '</div>';

    // Configuration status
    echo '<div class="mram-card"><h3>⚡ Quick Status</h3>';
    $apiKey = $vars['api_key'];
    $senderId = $vars['sender_id'];
    echo '<table class="mram-table">';
    echo '<tr><td><strong>API Key</strong></td><td>' . (!empty($apiKey) ? '<span class="mram-badge mram-badge-success">Configured</span>' : '<span class="mram-badge mram-badge-danger">Not Set</span>') . '</td></tr>';
    echo '<tr><td><strong>Sender ID</strong></td><td>' . (!empty($senderId) ? '<span class="mram-badge mram-badge-success">' . htmlspecialchars($senderId) . '</span>' : '<span class="mram-badge mram-badge-danger">Not Set</span>') . '</td></tr>';
    echo '<tr><td><strong>SMS Type</strong></td><td><span class="mram-badge mram-badge-info">' . htmlspecialchars($vars['sms_type'] ?: 'text') . '</span></td></tr>';
    echo '<tr><td><strong>Label</strong></td><td><span class="mram-badge mram-badge-info">' . htmlspecialchars($vars['sms_label'] ?: 'transactional') . '</span></td></tr>';
    echo '</table></div>';

    // Recent SMS
    $recent = Capsule::table('mod_mram_sms_log')->orderBy('created_at', 'desc')->limit(10)->get();
    echo '<div class="mram-card"><h3>📬 Recent SMS</h3>';
    if (count($recent) > 0) {
        echo '<table class="mram-table"><thead><tr><th>Time</th><th>Phone</th><th>Hook</th><th>Status</th><th>Message</th></tr></thead><tbody>';
        foreach ($recent as $sms) {
            $statusClass = $sms->status === 'sent' ? 'mram-badge-success' : ($sms->status === 'failed' ? 'mram-badge-danger' : 'mram-badge-warning');
            $msg = htmlspecialchars($sms->message);
            echo "<tr><td>{$sms->created_at}</td><td>{$sms->phone}</td><td>{$sms->hook_name}</td><td><span class='mram-badge {$statusClass}'>{$sms->status}</span></td><td style='max-width:350px; word-wrap:break-word; white-space:normal;'>{$msg}</td></tr>";
        }
        echo '</tbody></table>';
    } else {
        echo '<p style="color:#888;">No SMS sent yet.</p>';
    }
    echo '</div>';

    // Balance check script
    $modulelink = $vars['modulelink'];
    echo '<script>
    fetch("' . $modulelink . '&action=ajax_check_balance")
        .then(function(r){if(!r.ok)throw new Error("HTTP "+r.status);return r.text();})
        .then(function(t){try{var d=JSON.parse(t);document.getElementById("mram-balance").textContent=d.balance||"Error";}catch(e){var m=t.match(/"balance"\s*:\s*"([^"]+)"/);document.getElementById("mram-balance").textContent=m?m[1]:"Error";}})
        .catch(function(e){console.log("Balance error:",e);document.getElementById("mram-balance").textContent="Error";});
    </script>';
}

function mram_sms_page_templates($vars)
{
    $templates = Capsule::table('mod_mram_sms_templates')->orderBy('friendly_name')->get();
    $modulelink = $vars['modulelink'];

    echo '<div class="mram-card"><h3>📝 SMS Templates</h3>';
    echo '<p style="color:#666;">Customize SMS messages for each notification event. Use variables like <code>{client_name}</code> in your templates.</p>';
    echo '<table class="mram-table"><thead><tr><th>Event</th><th>Status</th><th>Template</th><th>Variables</th><th>Actions</th></tr></thead><tbody>';

    foreach ($templates as $tpl) {
        $checked = $tpl->is_active ? 'checked' : '';
        echo "<tr id='tpl-row-{$tpl->id}'>";
        echo "<td><strong>{$tpl->friendly_name}</strong><br><small style='color:#888;'>{$tpl->hook_name}</small></td>";
        echo "<td><label class='mram-switch'><input type='checkbox' {$checked} onchange=\"toggleTemplate({$tpl->id}, this.checked)\"><span class='mram-slider'></span></label></td>";
        echo "<td><textarea class='mram-textarea' id='tpl-msg-{$tpl->id}' rows='3' style='width:300px;'>" . htmlspecialchars($tpl->message_template) . "</textarea></td>";
        echo "<td><small style='color:#666;'>" . htmlspecialchars($tpl->available_variables) . "</small></td>";
        echo "<td><button class='mram-btn mram-btn-primary' onclick=\"saveTemplate({$tpl->id})\">💾 Save</button></td>";
        echo "</tr>";
    }
    echo '</tbody></table></div>';

    echo "<script>
    function saveTemplate(id) {
        var msg = document.getElementById('tpl-msg-'+id).value;
        fetch('{$modulelink}&action=ajax_save_template', {
            method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:'id='+id+'&message='+encodeURIComponent(msg)
        }).then(r=>r.json()).then(d=>{
            alert(d.success ? 'Template saved!' : 'Error: '+d.error);
        });
    }
    function toggleTemplate(id, active) {
        fetch('{$modulelink}&action=ajax_toggle_template', {
            method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:'id='+id+'&active='+(active?1:0)
        }).then(r=>r.json()).then(d=>{
            if(!d.success) alert('Error toggling template');
        });
    }
    </script>";
}

function mram_sms_page_bulk($vars)
{
    $modulelink = $vars['modulelink'];

    echo '<div class="mram-card"><h3>📨 Send Bulk SMS</h3>';
    echo '<div class="mram-form-group"><label>Recipients</label>';
    echo '<select id="bulk-target" onchange="toggleBulkInput()" style="max-width:300px;">';
    echo '<option value="custom">Custom Numbers</option>';
    echo '<option value="all_clients">All Clients</option>';
    echo '<option value="active_clients">Active Clients Only</option>';
    echo '</select></div>';
    echo '<div class="mram-form-group" id="bulk-numbers-group"><label>Phone Numbers</label>';
    echo '<textarea id="bulk-numbers" class="mram-textarea" placeholder="Enter numbers separated by comma or new line. E.g: 8801XXXXXXXXX" style="max-width:500px;"></textarea>';
    echo '<small style="color:#888;">Separate multiple numbers with comma, plus (+), or new line</small></div>';
    echo '<div class="mram-form-group"><label>Message</label>';
    echo '<textarea id="bulk-message" class="mram-textarea" placeholder="Type your SMS message here..." style="max-width:500px;" oninput="updateCharCount()"></textarea>';
    echo '<small id="char-count" style="color:#888;">0 characters | 0 SMS parts</small></div>';
    echo '<div class="mram-form-group"><label>SMS Type</label>';
    echo '<select id="bulk-sms-type" style="max-width:200px;">';
    echo '<option value="text">Text (English)</option>';
    echo '<option value="unicode">Unicode (Bangla)</option>';
    echo '</select></div>';
    echo '<button class="mram-btn mram-btn-success" onclick="sendBulk()" id="bulk-send-btn">📤 Send SMS</button>';
    echo ' <span id="bulk-status" style="margin-left:10px;"></span>';
    echo '</div>';

    // Send history (last 20 bulk)
    $bulkLogs = Capsule::table('mod_mram_sms_log')->where('hook_name', 'bulk')->orderBy('created_at', 'desc')->limit(20)->get();
    if (count($bulkLogs) > 0) {
        echo '<div class="mram-card"><h3>📋 Recent Bulk SMS</h3>';
        echo '<table class="mram-table"><thead><tr><th>Time</th><th>Phone</th><th>Status</th><th>Message</th></tr></thead><tbody>';
        foreach ($bulkLogs as $sms) {
            $statusClass = $sms->status === 'sent' ? 'mram-badge-success' : 'mram-badge-danger';
            echo "<tr><td>{$sms->created_at}</td><td>{$sms->phone}</td><td><span class='mram-badge {$statusClass}'>{$sms->status}</span></td><td>" . htmlspecialchars(mb_substr($sms->message, 0, 80)) . "</td></tr>";
        }
        echo '</tbody></table></div>';
    }

    echo "<script>
    function toggleBulkInput() {
        var target = document.getElementById('bulk-target').value;
        document.getElementById('bulk-numbers-group').style.display = (target==='custom') ? 'block' : 'none';
    }
    function updateCharCount() {
        var msg = document.getElementById('bulk-message').value;
        var len = msg.length;
        var parts = len <= 160 ? 1 : Math.ceil(len / 153);
        document.getElementById('char-count').textContent = len + ' characters | ' + parts + ' SMS part(s)';
    }
    function sendBulk() {
        var btn = document.getElementById('bulk-send-btn');
        btn.disabled = true;
        btn.textContent = '⏳ Sending...';
        var data = 'target='+document.getElementById('bulk-target').value
            +'&numbers='+encodeURIComponent(document.getElementById('bulk-numbers').value)
            +'&message='+encodeURIComponent(document.getElementById('bulk-message').value)
            +'&sms_type='+document.getElementById('bulk-sms-type').value;
        fetch('{$modulelink}&action=ajax_send_bulk', {
            method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:data
        }).then(r=>r.json()).then(d=>{
            btn.disabled = false; btn.textContent = '📤 Send SMS';
            document.getElementById('bulk-status').innerHTML = d.success
                ? '<span class=\"mram-badge mram-badge-success\">Sent to '+d.count+' number(s)</span>'
                : '<span class=\"mram-badge mram-badge-danger\">Error: '+d.error+'</span>';
        }).catch(()=>{ btn.disabled=false; btn.textContent='📤 Send SMS'; });
    }
    </script>";
}

function mram_sms_page_logs($vars)
{
    $page = isset($_REQUEST['p']) ? max(1, (int)$_REQUEST['p']) : 1;
    $perPage = 50;
    $offset = ($page - 1) * $perPage;

    $statusFilter = isset($_REQUEST['status_filter']) ? $_REQUEST['status_filter'] : '';
    $hookFilter = isset($_REQUEST['hook_filter']) ? $_REQUEST['hook_filter'] : '';

    $query = Capsule::table('mod_mram_sms_log');
    if ($statusFilter) $query->where('status', $statusFilter);
    if ($hookFilter) $query->where('hook_name', $hookFilter);

    $total = $query->count();
    $logs = $query->orderBy('created_at', 'desc')->offset($offset)->limit($perPage)->get();

    $modulelink = $vars['modulelink'];

    echo '<div class="mram-card"><h3>📋 SMS Log</h3>';

    // Filters
    echo "<form method='get' style='margin-bottom:15px; display:flex; gap:10px; align-items:end;'>";
    echo "<input type='hidden' name='module' value='mram_sms'><input type='hidden' name='action' value='logs'>";
    echo "<div><label style='font-size:12px;'>Status</label><br><select name='status_filter' style='padding:6px;'><option value=''>All</option>";
    foreach (['sent','failed','pending'] as $s) {
        $sel = $statusFilter === $s ? 'selected' : '';
        echo "<option value='{$s}' {$sel}>{$s}</option>";
    }
    echo "</select></div>";
    echo "<div><label style='font-size:12px;'>Hook</label><br><select name='hook_filter' style='padding:6px;'><option value=''>All</option>";
    $hooks = Capsule::table('mod_mram_sms_log')->select('hook_name')->distinct()->pluck('hook_name');
    foreach ($hooks as $h) {
        $sel = $hookFilter === $h ? 'selected' : '';
        echo "<option value='{$h}' {$sel}>{$h}</option>";
    }
    echo "</select></div>";
    echo "<div><button type='submit' class='mram-btn mram-btn-info'>🔍 Filter</button></div>";
    echo "</form>";

    echo '<table class="mram-table"><thead><tr><th>ID</th><th>Time</th><th>Client</th><th>Phone</th><th>Hook</th><th>Status</th><th>Error</th><th>Message</th></tr></thead><tbody>';
    foreach ($logs as $sms) {
        $statusClass = $sms->status === 'sent' ? 'mram-badge-success' : ($sms->status === 'failed' ? 'mram-badge-danger' : 'mram-badge-warning');
        $msg = htmlspecialchars($sms->message);
        echo "<tr><td>{$sms->id}</td><td>{$sms->created_at}</td><td>" . ($sms->client_name ?: "N/A") . "</td><td>{$sms->phone}</td><td>{$sms->hook_name}</td><td><span class='mram-badge {$statusClass}'>{$sms->status}</span></td><td>" . htmlspecialchars($sms->error_message ?: "") . "</td><td style='max-width:350px; word-wrap:break-word;'>{$msg}</td></tr>";
    }
    echo '</tbody></table>';

    // Pagination
    $totalPages = ceil($total / $perPage);
    if ($totalPages > 1) {
        echo '<div style="margin-top:15px; text-align:center;">';
        for ($i = 1; $i <= $totalPages; $i++) {
            $style = ($i === $page) ? 'font-weight:bold; text-decoration:underline;' : '';
            echo "<a href='{$modulelink}&action=logs&p={$i}&status_filter={$statusFilter}&hook_filter={$hookFilter}' style='margin:0 5px; {$style}'>{$i}</a>";
        }
        echo '</div>';
    }
    echo "<p style='color:#888; margin-top:10px;'>Showing " . count($logs) . " of {$total} records</p>";
    echo '</div>';
}

function mram_sms_page_settings($vars)
{
    $modulelink = $vars['modulelink'];

    echo '<div class="mram-card"><h3>⚙️ Module Settings</h3>';
    echo '<p>Configure your MRAM SMS settings in <strong>Setup → Addon Modules → MRAM SMS</strong>.</p>';
    echo '<table class="mram-table">';
    echo '<tr><td><strong>API Key</strong></td><td>' . (!empty($vars['api_key']) ? '••••••' . substr($vars['api_key'], -8) : '<span class="mram-badge mram-badge-danger">Not Set</span>') . '</td></tr>';
    echo '<tr><td><strong>Sender ID</strong></td><td>' . htmlspecialchars($vars['sender_id'] ?: 'Not Set') . '</td></tr>';
    echo '<tr><td><strong>SMS Type</strong></td><td>' . htmlspecialchars($vars['sms_type'] ?: 'text') . '</td></tr>';
    echo '<tr><td><strong>Admin Phone</strong></td><td>' . htmlspecialchars($vars['admin_phone'] ?: 'Not Set') . '</td></tr>';
    echo '<tr><td><strong>SMS Label</strong></td><td>' . htmlspecialchars($vars['sms_label'] ?: 'transactional') . '</td></tr>';
    echo '<tr><td><strong>Country Code</strong></td><td>' . htmlspecialchars($vars['country_code'] ?: '88') . '</td></tr>';
    echo '</table></div>';

    // Test SMS
    echo '<div class="mram-card"><h3>🧪 Test SMS</h3>';
    echo '<div class="mram-form-group"><label>Phone Number</label><input type="text" id="test-phone" placeholder="8801XXXXXXXXX" style="max-width:300px;"></div>';
    echo '<div class="mram-form-group"><label>Message</label><textarea id="test-message" class="mram-textarea" style="max-width:500px;">Hello! This is a test SMS from MRAM SMS WHMCS Module.</textarea></div>';
    echo '<button class="mram-btn mram-btn-success" onclick="testSms()" id="test-btn">📤 Send Test SMS</button>';
    echo ' <span id="test-status"></span>';
    echo '</div>';

    // Balance & Price
    echo '<div class="mram-card"><h3>💰 Account Info</h3>';
    echo '<button class="mram-btn mram-btn-info" onclick="checkBalance()">Check Balance</button>';
    echo ' <span id="balance-result" style="margin-left:10px;"></span>';
    echo '</div>';

    // Error code reference
    echo '<div class="mram-card"><h3>📖 Error Code Reference</h3>';
    echo '<table class="mram-table"><thead><tr><th>Code</th><th>Meaning</th></tr></thead><tbody>';
    $errors = [
        '1002'=>'Sender Id/Masking Not Found','1003'=>'API Not Found','1004'=>'SPAM Detected',
        '1005'=>'Internal Error','1006'=>'Internal Error','1007'=>'Balance Insufficient',
        '1008'=>'Message is empty','1009'=>'Message Type Not Set','1010'=>'Invalid User & Password',
        '1011'=>'Invalid User Id','1012'=>'Invalid Number','1013'=>'API limit error',
        '1014'=>'No matching template','1015'=>'SMS Content Validation Fails',
        '1016'=>'IP address not allowed','1019'=>'SMS Purpose Missing',
    ];
    foreach ($errors as $code => $msg) {
        echo "<tr><td><code>{$code}</code></td><td style='max-width:350px; word-wrap:break-word; white-space:normal;'>{$msg}</td></tr>";
    }
    echo '</tbody></table></div>';

    echo "<script>
    function testSms() {
        var btn = document.getElementById('test-btn');
        btn.disabled = true; btn.textContent = '⏳ Sending...';
        fetch('{$modulelink}&action=ajax_test_sms', {
            method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:'phone='+encodeURIComponent(document.getElementById('test-phone').value)+'&message='+encodeURIComponent(document.getElementById('test-message').value)
        }).then(r=>r.json()).then(d=>{
            btn.disabled = false; btn.textContent = '📤 Send Test SMS';
            document.getElementById('test-status').innerHTML = d.success
                ? '<span class=\"mram-badge mram-badge-success\">Sent!</span>'
                : '<span class=\"mram-badge mram-badge-danger\">Failed: '+d.error+'</span>';
        });
    }
    function checkBalance() {
        fetch('{$modulelink}&action=ajax_check_balance').then(r=>r.json()).then(d=>{
            document.getElementById('balance-result').innerHTML = '<span class=\"mram-badge mram-badge-info\">Balance: '+d.balance+'</span>';
        });
    }
    </script>";
}

function mram_sms_insert_default_templates()
{
    $hasTemplateCreatedAt = Capsule::schema()->hasColumn('mod_mram_sms_templates', 'created_at');
    $hasTemplateUpdatedAt = Capsule::schema()->hasColumn('mod_mram_sms_templates', 'updated_at');

    $templates = [
        ['AcceptOrder', 'Order Accepted', 'Dear {client_name}, your order #{order_id} has been accepted. Thank you!', '{client_name}, {order_id}, {order_num}, {amount}'],
        ['InvoiceCreated', 'Invoice Created', 'Dear {client_name}, invoice #{invoice_id} for {amount} {currency} has been generated. Due date: {due_date}.', '{client_name}, {invoice_id}, {amount}, {currency}, {due_date}'],
        ['InvoicePaid', 'Invoice Paid', 'Dear {client_name}, payment of {amount} {currency} received for invoice #{invoice_id}. Thank you!', '{client_name}, {invoice_id}, {amount}, {currency}, {payment_method}'],
        ['InvoicePaymentReminder', 'Payment Reminder', 'Dear {client_name}, reminder: invoice #{invoice_id} for {amount} {currency} is due on {due_date}. Please pay promptly.', '{client_name}, {invoice_id}, {amount}, {currency}, {due_date}'],
        ['AfterModuleCreate', 'Service Activated', 'Dear {client_name}, your service {service} has been activated successfully!', '{client_name}, {service}, {domain}, {server}, {username}, {password}'],
        ['AfterModuleSuspend', 'Service Suspended', 'Dear {client_name}, your service {service} has been suspended. Please contact support.', '{client_name}, {service}, {domain}, {suspend_reason}'],
        ['AfterModuleUnsuspend', 'Service Unsuspended', 'Dear {client_name}, your service {service} has been reactivated. Thank you!', '{client_name}, {service}, {domain}'],
        ['AfterModuleChangePassword', 'Password Changed', 'Dear {client_name}, password for {service} has been changed successfully.', '{client_name}, {service}, {domain}'],
        ['AfterModuleChangePackage', 'Package Changed', 'Dear {client_name}, your service {service} package has been updated.', '{client_name}, {service}, {domain}, {package}'],
        ['AfterRegistrarRegistration', 'Domain Registered', 'Dear {client_name}, domain {domain} registered successfully! Expires: {expiry_date}.', '{client_name}, {domain}, {expiry_date}, {registrar}'],
        ['AfterRegistrarRenewal', 'Domain Renewed', 'Dear {client_name}, domain {domain} renewed successfully! New expiry: {expiry_date}.', '{client_name}, {domain}, {expiry_date}'],
        ['ClientAdd', 'Welcome (Admin)', 'New client registered: {client_name} ({email}), Phone: {phone}.', '{client_name}, {email}, {phone}, {company}'],
        ['ClientAreaRegister', 'Welcome Client', 'Welcome {client_name}! Your account has been created. Log in at {whmcs_url}.', '{client_name}, {email}, {whmcs_url}'],
        ['ClientChangePassword', 'Password Changed', 'Dear {client_name}, your account password has been changed. If not you, contact support immediately.', '{client_name}'],
        ['TicketOpen', 'Ticket Opened', 'Dear {client_name}, support ticket #{ticket_id} ({subject}) has been opened. We will respond shortly.', '{client_name}, {ticket_id}, {subject}, {department}, {priority}'],
        ['TicketAdminReply', 'Ticket Reply', 'Dear {client_name}, ticket #{ticket_id} has a new reply from support. Please check your client area.', '{client_name}, {ticket_id}, {subject}, {reply_message}'],
        ['TicketUserReply', 'Ticket User Reply (Admin)', 'Client {client_name} replied to ticket #{ticket_id}: {subject}.', '{client_name}, {ticket_id}, {subject}'],
        ['TicketClose', 'Ticket Closed', 'Dear {client_name}, ticket #{ticket_id} ({subject}) has been closed. Thank you!', '{client_name}, {ticket_id}, {subject}'],
        ['DomainRenewalReminder', 'Domain Renewal Reminder', 'Dear {client_name}, domain {domain} expires on {expiry_date}. Please renew to avoid losing it.', '{client_name}, {domain}, {expiry_date}, {days_until_expiry}'],
    ];

    foreach ($templates as $t) {
        $insertData = [
            'hook_name'           => $t[0],
            'friendly_name'       => $t[1],
            'message_template'    => $t[2],
            'available_variables' => $t[3],
            'is_active'           => 1,
            'admin_notify'        => in_array($t[0], ['ClientAdd', 'TicketUserReply']) ? 1 : 0,
        ];

        if ($hasTemplateCreatedAt) {
            $insertData['created_at'] = date('Y-m-d H:i:s');
        }

        if ($hasTemplateUpdatedAt) {
            $insertData['updated_at'] = date('Y-m-d H:i:s');
        }

        Capsule::table('mod_mram_sms_templates')->insertOrIgnore($insertData);
    }
}

function mram_sms_page_manual($vars)
{
    $modulelink = $vars['modulelink'];

    // Get all active clients
    $clients = Capsule::table('tblclients')
        ->where('status', 'Active')
        ->orderBy('firstname')
        ->get(['id', 'firstname', 'lastname', 'phonenumber', 'email']);

    // Get templates for quick select
    $templates = Capsule::table('mod_mram_sms_templates')->where('is_active', 1)->orderBy('friendly_name')->get();

    echo '<div class="mram-card"><h3>✉️ Send Manual SMS</h3>';
    echo '<p style="color:#666;">Select a client to auto-fill all template variables including latest invoice data.</p>';

    // Client picker
    echo '<div class="mram-form-group"><label>Select Client</label>';
    echo '<select id="manual-client" onchange="onClientSelect()" style="max-width:500px;">';
    echo '<option value="">-- Choose a client --</option>';
    foreach ($clients as $c) {
        $name = htmlspecialchars(trim($c->firstname . ' ' . $c->lastname));
        $phone = htmlspecialchars($c->phonenumber);
        echo "<option value='{$c->id}' data-phone='{$phone}'>{$name} ({$phone})</option>";
    }
    echo '</select></div>';

    // Auto-filled variables display
    echo '<div id="client-vars" style="display:none; background:#f8f9fc; border:1px solid #e3e6f0; border-radius:6px; padding:15px; margin-bottom:15px;">';
    echo '<h4 style="margin:0 0 10px 0; color:#4e73df;">📋 Auto-filled Variables</h4>';
    echo '<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px,1fr)); gap:8px;">';
    echo '<div><small style="color:#888;">Client Name</small><br><strong id="var-client_name">-</strong></div>';
    echo '<div><small style="color:#888;">Email</small><br><strong id="var-client_email">-</strong></div>';
    echo '<div><small style="color:#888;">Invoice #</small><br><strong id="var-invoice_id">-</strong></div>';
    echo '<div><small style="color:#888;">Amount</small><br><strong id="var-amount">-</strong></div>';
    echo '<div><small style="color:#888;">Currency</small><br><strong id="var-currency">-</strong></div>';
    echo '<div><small style="color:#888;">Due Date</small><br><strong id="var-due_date">-</strong></div>';
    echo '<div><small style="color:#888;">Company</small><br><strong id="var-company_name">-</strong></div>';
    echo '</div></div>';

    // Phone number
    echo '<div class="mram-form-group"><label>Phone Number</label>';
    echo '<input type="text" id="manual-phone" placeholder="8801XXXXXXXXX" style="max-width:300px;" inputmode="numeric"></div>';

    // Template picker
    echo '<div class="mram-form-group"><label>Quick Template</label>';
    echo '<select id="manual-template" onchange="onTemplateSelect()" style="max-width:500px;">';
    echo '<option value="">-- Select a template (optional) --</option>';
    foreach ($templates as $tpl) {
        $msg = htmlspecialchars($tpl->message_template);
        echo "<option value='" . htmlspecialchars($tpl->message_template) . "' data-vars='" . htmlspecialchars($tpl->available_variables) . "'>{$tpl->friendly_name}</option>";
    }
    echo '</select></div>';

    // Message
    echo '<div class="mram-form-group"><label>Message</label>';
    echo '<textarea id="manual-message" class="mram-textarea" placeholder="Type your message or select a template..." style="max-width:500px;" oninput="updateManualCharCount()"></textarea>';
    echo '<small id="manual-char-count" style="color:#888;">0 characters | 0 SMS part(s)</small></div>';

    echo '<button class="mram-btn mram-btn-success" onclick="sendManual()" id="manual-send-btn">📤 Send SMS</button>';
    echo ' <span id="manual-status" style="margin-left:10px;"></span>';
    echo '</div>';

    // Preview card
    echo '<div class="mram-card" id="preview-card" style="display:none;"><h3>👁️ Message Preview</h3>';
    echo '<div id="message-preview" style="background:#f0f0f0; padding:15px; border-radius:6px; font-family:monospace; white-space:pre-wrap;"></div>';
    echo '</div>';

    echo "<script>
    var clientData = {};

    function onClientSelect() {
        var sel = document.getElementById('manual-client');
        var clientId = sel.value;
        if (!clientId) {
            document.getElementById('client-vars').style.display = 'none';
            document.getElementById('manual-phone').value = '';
            clientData = {};
            updatePreview();
            return;
        }

        fetch('{$modulelink}&action=ajax_get_client_data&client_id=' + clientId)
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    clientData = d;
                    document.getElementById('manual-phone').value = d.phone || '';
                    document.getElementById('var-client_name').textContent = d.client_name || '-';
                    document.getElementById('var-client_email').textContent = d.client_email || '-';
                    document.getElementById('var-invoice_id').textContent = d.invoice_id || 'No invoice';
                    document.getElementById('var-amount').textContent = d.amount || '-';
                    document.getElementById('var-currency').textContent = d.currency || '-';
                    document.getElementById('var-due_date').textContent = d.due_date || '-';
                    document.getElementById('var-company_name').textContent = d.company_name || '-';
                    document.getElementById('client-vars').style.display = 'block';
                    updatePreview();
                }
            });
    }

    function onTemplateSelect() {
        var sel = document.getElementById('manual-template');
        if (sel.value) {
            document.getElementById('manual-message').value = sel.value;
            updateManualCharCount();
            updatePreview();
        }
    }

    function replaceVars(msg) {
        if (!clientData.client_name) return msg;
        msg = msg.replace(/\{client_name\}/g, clientData.client_name || '');
        msg = msg.replace(/\{first_name\}/g, clientData.first_name || '');
        msg = msg.replace(/\{last_name\}/g, clientData.last_name || '');
        msg = msg.replace(/\{client_email\}/g, clientData.client_email || '');
        msg = msg.replace(/\{company_name\}/g, clientData.company_name || '');
        msg = msg.replace(/\{invoice_id\}/g, clientData.invoice_id || '');
        msg = msg.replace(/\{amount\}/g, clientData.amount || '');
        msg = msg.replace(/\{currency\}/g, clientData.currency || '');
        msg = msg.replace(/\{due_date\}/g, clientData.due_date || '');
        return msg;
    }

    function updatePreview() {
        var msg = document.getElementById('manual-message').value;
        var preview = replaceVars(msg);
        var previewCard = document.getElementById('preview-card');
        var previewDiv = document.getElementById('message-preview');
        if (msg.trim()) {
            previewDiv.textContent = preview;
            previewCard.style.display = 'block';
        } else {
            previewCard.style.display = 'none';
        }
    }

    function updateManualCharCount() {
        var msg = document.getElementById('manual-message').value;
        var len = msg.length;
        var parts = len <= 160 ? 1 : Math.ceil(len / 153);
        document.getElementById('manual-char-count').textContent = len + ' characters | ' + parts + ' SMS part(s)';
        updatePreview();
    }

    function sendManual() {
        var btn = document.getElementById('manual-send-btn');
        btn.disabled = true; btn.textContent = '⏳ Sending...';
        var clientId = document.getElementById('manual-client').value || '0';
        var phone = document.getElementById('manual-phone').value;
        var message = document.getElementById('manual-message').value;

        fetch('{$modulelink}&action=ajax_send_manual', {
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:'phone='+encodeURIComponent(phone)+'&message='+encodeURIComponent(message)+'&client_id='+clientId
        }).then(r=>r.json()).then(d=>{
            btn.disabled = false; btn.textContent = '📤 Send SMS';
            if (d.success) {
                document.getElementById('manual-status').innerHTML = '<span class=\"mram-badge mram-badge-success\">✅ Sent! Message: '+d.message_sent.substring(0,50)+'...</span>';
            } else {
                document.getElementById('manual-status').innerHTML = '<span class=\"mram-badge mram-badge-danger\">❌ Failed: '+d.error+'</span>';
            }
        }).catch(function(){
            btn.disabled = false; btn.textContent = '📤 Send SMS';
        });
    }

    // Auto-update preview when message changes
    document.getElementById('manual-message').addEventListener('input', updatePreview);
    </script>";
}

function mram_sms_page_reports($vars)
{
    require_once __DIR__ . '/mram_sms_api.php';
    $modulelink = $vars['modulelink'];

    // Local stats from log table
    $totalSent = Capsule::table('mod_mram_sms_log')->count();
    $totalSuccess = Capsule::table('mod_mram_sms_log')->where('status', 'sent')->count();
    $totalFailed = Capsule::table('mod_mram_sms_log')->where('status', 'failed')->count();

    // Calculate total cost estimate (from local records)
    $smsWithIds = Capsule::table('mod_mram_sms_log')->whereNotNull('sms_shoot_id')->where('sms_shoot_id', '!=', '')->count();

    echo '<div class="mram-card"><h3>📈 Reports & Statistics</h3>';

    // Summary stats cards
    echo '<div class="mram-stats">';
    echo "<div class='mram-stat-card'><div class='number' style='color:#4e73df'>{$totalSent}</div><div class='label'>Total SMS Sent</div></div>";
    echo "<div class='mram-stat-card'><div class='number' style='color:#1cc88a'>{$totalSuccess}</div><div class='label'>Delivered</div></div>";
    echo "<div class='mram-stat-card'><div class='number' style='color:#e74a3b'>{$totalFailed}</div><div class='label'>Failed</div></div>";
    echo "<div class='mram-stat-card'><div class='number' id='report-balance'>...</div><div class='label'>Current Balance</div></div>";
    echo '</div>';
    echo '</div>';

    // DLR Section - fetched from MRAM API
    echo '<div class="mram-card"><h3>📊 View DLR (Delivery Reports from MRAM API)</h3>';
    echo '<p style="color:#666; margin-bottom:15px;">Delivery reports fetched directly from <strong>msg.mram.com.bd</strong> API.</p>';

    // Lookup by Shoot ID
    echo '<div style="display:flex; gap:10px; margin-bottom:15px; align-items:center;">';
    echo '<input type="text" id="dlr-shoot-id" placeholder="Enter Shoot ID (leave blank for all)" style="padding:8px; border:1px solid #d1d3e2; border-radius:4px; width:300px;">';
    echo '<button class="mram-btn mram-btn-info" onclick="fetchDLR()">🔍 Fetch DLR</button>';
    echo '<span id="dlr-status" style="margin-left:10px;"></span>';
    echo '</div>';

    echo '<div id="dlr-results">';
    echo '<p style="color:#888;">Click "Fetch DLR" to load delivery reports from the MRAM API.</p>';
    echo '</div>';
    echo '</div>';

    // Local SMS Log with shoot IDs for quick DLR lookup
    $recentWithIds = Capsule::table('mod_mram_sms_log')
        ->whereNotNull('sms_shoot_id')
        ->where('sms_shoot_id', '!=', '')
        ->orderBy('created_at', 'desc')
        ->limit(20)
        ->get();

    if (count($recentWithIds) > 0) {
        echo '<div class="mram-card"><h3>🔗 Recent SMS with Shoot IDs (Quick DLR Lookup)</h3>';
        echo '<table class="mram-table"><thead><tr><th>Time</th><th>Phone</th><th>Shoot ID</th><th>Status</th><th>Action</th></tr></thead><tbody>';
        foreach ($recentWithIds as $sms) {
            $statusClass = $sms->status === 'sent' ? 'mram-badge-success' : 'mram-badge-danger';
            echo "<tr>";
            echo "<td>{$sms->created_at}</td>";
            echo "<td>{$sms->phone}</td>";
            echo "<td><code>{$sms->sms_shoot_id}</code></td>";
            echo "<td><span class='mram-badge {$statusClass}'>{$sms->status}</span></td>";
            echo "<td><button class='mram-btn mram-btn-info' style='padding:4px 10px; font-size:12px;' onclick=\"viewDLR('{$sms->sms_shoot_id}')\">View DLR</button></td>";
            echo "</tr>";
        }
        echo '</tbody></table></div>';
    }

    // JavaScript for DLR fetching
    echo "<script>
    // Fetch balance
    fetch('{$modulelink}&action=ajax_check_balance')
        .then(function(r){return r.json();})
        .then(function(d){document.getElementById('report-balance').textContent=d.balance||'Error';})
        .catch(function(){document.getElementById('report-balance').textContent='Error';});

    function viewDLR(shootId) {
        document.getElementById('dlr-shoot-id').value = shootId;
        fetchDLR();
    }

    function fetchDLR() {
        var shootId = document.getElementById('dlr-shoot-id').value.trim();
        var statusEl = document.getElementById('dlr-status');
        var resultsEl = document.getElementById('dlr-results');
        statusEl.innerHTML = '<span class=\"mram-badge mram-badge-warning\">⏳ Loading...</span>';

        fetch('{$modulelink}&action=ajax_check_dlr&shoot_id=' + encodeURIComponent(shootId))
            .then(function(r){return r.json();})
            .then(function(d){
                statusEl.innerHTML = '<span class=\"mram-badge mram-badge-success\">✅ Loaded</span>';
                var dlr = d.dlr;

                if (!dlr || (typeof dlr === 'string' && dlr.trim() === '')) {
                    resultsEl.innerHTML = '<p style=\"color:#888;\">No DLR data returned. The shoot ID may be invalid or DLR not yet available.</p>';
                    return;
                }

                // If DLR is a string (error or simple response)
                if (typeof dlr === 'string') {
                    resultsEl.innerHTML = '<div class=\"mram-card\" style=\"margin:0;\"><pre style=\"white-space:pre-wrap; word-wrap:break-word;\">' + escapeHtml(dlr) + '</pre></div>';
                    return;
                }

                // If DLR is an array of records
                if (Array.isArray(dlr)) {
                    renderDLRTable(dlr);
                    return;
                }

                // If DLR is an object (single record or structured response)
                if (typeof dlr === 'object') {
                    // Check if it has a data array
                    if (dlr.data && Array.isArray(dlr.data)) {
                        renderDLRTable(dlr.data);
                    } else if (dlr.error) {
                        resultsEl.innerHTML = '<p style=\"color:#e74a3b;\">Error: ' + escapeHtml(String(dlr.error)) + '</p>';
                    } else {
                        // Single record - show as table
                        renderDLRTable([dlr]);
                    }
                    return;
                }

                resultsEl.innerHTML = '<pre>' + escapeHtml(JSON.stringify(dlr, null, 2)) + '</pre>';
            })
            .catch(function(e){
                statusEl.innerHTML = '<span class=\"mram-badge mram-badge-danger\">❌ Error</span>';
                resultsEl.innerHTML = '<p style=\"color:#e74a3b;\">Failed to fetch DLR: ' + e.message + '</p>';
            });
    }

    function renderDLRTable(records) {
        var resultsEl = document.getElementById('dlr-results');
        if (!records || records.length === 0) {
            resultsEl.innerHTML = '<p style=\"color:#888;\">No delivery records found.</p>';
            return;
        }

        // Get all unique keys from records
        var keys = [];
        records.forEach(function(r) {
            Object.keys(r).forEach(function(k) {
                if (keys.indexOf(k) === -1) keys.push(k);
            });
        });

        var html = '<div style=\"margin-bottom:10px; color:#666;\">Showing ' + records.length + ' record(s)</div>';
        html += '<div style=\"overflow-x:auto;\"><table class=\"mram-table\"><thead><tr>';
        keys.forEach(function(k) {
            html += '<th>' + escapeHtml(k) + '</th>';
        });
        html += '</tr></thead><tbody>';

        records.forEach(function(r) {
            html += '<tr>';
            keys.forEach(function(k) {
                var val = r[k] !== undefined && r[k] !== null ? String(r[k]) : '';
                // Color-code status fields
                if (k.toLowerCase().indexOf('status') !== -1) {
                    var lower = val.toLowerCase();
                    if (lower === 'delivered' || lower === 'sent') {
                        val = '<span class=\"mram-badge mram-badge-success\">' + escapeHtml(val) + '</span>';
                    } else if (lower === 'failed' || lower === 'rejected' || lower === 'undelivered') {
                        val = '<span class=\"mram-badge mram-badge-danger\">' + escapeHtml(val) + '</span>';
                    } else if (lower === 'pending' || lower === 'queued') {
                        val = '<span class=\"mram-badge mram-badge-warning\">' + escapeHtml(val) + '</span>';
                    } else {
                        val = escapeHtml(val);
                    }
                } else {
                    val = escapeHtml(val);
                }
                html += '<td style=\"max-width:300px; word-wrap:break-word; white-space:normal;\">' + val + '</td>';
            });
            html += '</tr>';
        });

        html += '</tbody></table></div>';
        resultsEl.innerHTML = html;
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }
    </script>";
}
