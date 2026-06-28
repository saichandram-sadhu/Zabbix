<?php declare(strict_types = 1);

/**
 * @var CView $this
 * @var array $data
 */

$html_page = (new CHtmlPage())
    ->setTitle(_('Companies Management'))
    ->setDocUrl(CDocHelper::getUrl(CDocHelper::ADMINISTRATION_MODULE_LIST));

// Calculate metrics for KPIs
$total_companies = count($data['companies']);
$total_users = 0;
if (!empty($data['companies'])) {
    foreach ($data['companies'] as $c) {
        $total_users += count($c['users']);
    }
}

// Start output buffering to capture the entire HTML/CSS/JS cleanly
ob_start();
?>
<style>
    :root {
        --msp-primary: #0275d8;
        --msp-primary-hover: #025aa5;
        --msp-success: #10b981;
        --msp-danger: #ef4444;
        --msp-danger-hover: #dc2626;
    }

    .msp-container {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        display: flex;
        flex-direction: column;
        gap: 20px;
        margin-top: 15px;
    }

    /* Light Theme Mappings (Default) */
    .msp-container.msp-light {
        --card-bg: #ffffff;
        --card-border: #dfe4e8;
        --card-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        --text-main: #1f2c33;
        --text-muted: #768d99;
        --input-bg: #ffffff;
        --input-border: #dfe4e8;
        --input-text: #1f2c33;
        --table-header-bg: #f4f6f7;
        --table-header-text: #1f2c33;
        --table-border: #dfe4e8;
        --table-hover: #f8fafc;
        --badge-bg: #e6f2fc;
        --badge-text: #0275d8;
        --badge-border: #b3d7f7;
    }

    /* Dark Theme Mappings */
    .msp-container.msp-dark {
        --card-bg: #2b3b44;
        --card-border: #384b55;
        --card-shadow: 0 4px 6px rgba(0, 0, 0, 0.25);
        --text-main: #f2f2f2;
        --text-muted: #8898a1;
        --input-bg: #1f2c33;
        --input-border: #4f6470;
        --input-text: #f2f2f2;
        --table-header-bg: #1f2c33;
        --table-header-text: #f2f2f2;
        --table-border: #384b55;
        --table-hover: #33444f;
        --badge-bg: rgba(2, 117, 216, 0.2);
        --badge-text: #47a3f5;
        --badge-border: rgba(71, 163, 245, 0.4);
    }

    /* KPI Card Styling */
    .msp-kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 15px;
    }
    .msp-kpi-card {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: 8px;
        padding: 16px 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        box-shadow: var(--card-shadow);
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
    }
    .msp-kpi-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        border-color: var(--msp-primary);
    }
    .msp-kpi-info {
        display: flex;
        flex-direction: column;
    }
    .msp-kpi-value {
        font-size: 24px;
        font-weight: 700;
        color: var(--text-main);
        line-height: 1.2;
    }
    .msp-kpi-label {
        font-size: 11px;
        font-weight: 600;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-top: 4px;
    }
    .msp-kpi-icon-wrapper {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .msp-kpi-blue { background: rgba(2, 117, 216, 0.1); color: #0275d8; }
    .msp-kpi-green { background: rgba(16, 185, 129, 0.1); color: #10b981; }
    .msp-kpi-yellow { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
    .msp-kpi-purple { background: rgba(139, 92, 246, 0.1); color: #8b5cf6; }

    /* Main Workspace layout */
    .msp-main-grid {
        display: grid;
        grid-template-columns: 380px 1fr;
        gap: 20px;
    }
    @media (max-width: 1024px) {
        .msp-main-grid {
            grid-template-columns: 1fr;
        }
    }

    /* Card Styling */
    .msp-card {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: 8px;
        box-shadow: var(--card-shadow);
        padding: 20px;
        display: flex;
        flex-direction: column;
    }
    .msp-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid var(--card-border);
    }
    .msp-card-title {
        font-size: 15px;
        font-weight: 700;
        color: var(--text-main);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .msp-card-title svg {
        vertical-align: middle;
    }

    /* Form Controls */
    .msp-form-group {
        margin-bottom: 16px;
    }
    .msp-label {
        display: block;
        font-size: 12px;
        font-weight: 700;
        color: var(--text-muted);
        margin-bottom: 6px;
    }
    .msp-input {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid var(--input-border);
        border-radius: 4px;
        background: var(--input-bg);
        color: var(--input-text);
        font-size: 13px;
        transition: border-color 0.2s, box-shadow 0.2s;
        box-sizing: border-box;
    }
    .msp-input:focus {
        border-color: var(--msp-primary);
        box-shadow: 0 0 0 3px rgba(2, 117, 216, 0.15);
        outline: none;
    }
    .msp-password-container {
        position: relative;
        display: flex;
        align-items: center;
        width: 100%;
    }
    .msp-password-input {
        padding-right: 40px;
    }
    .msp-password-toggle-btn {
        position: absolute;
        right: 8px;
        background: none;
        border: none;
        cursor: pointer;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 4px;
        border-radius: 4px;
        transition: color 0.2s;
    }
    .msp-password-toggle-btn:hover {
        color: var(--text-main);
    }

    /* Buttons */
    .msp-btn {
        background: var(--msp-primary);
        color: #ffffff;
        border: none;
        padding: 10px 16px;
        border-radius: 4px;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s;
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        text-decoration: none;
        box-sizing: border-box;
    }
    .msp-btn:hover {
        background: var(--msp-primary-hover);
    }
    .msp-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    /* Search Box */
    .msp-search-box {
        position: relative;
        display: flex;
        align-items: center;
        width: 220px;
    }
    .msp-search-input {
        width: 100%;
        padding: 6px 10px 6px 32px !important;
        border: 1px solid var(--input-border);
        border-radius: 4px;
        background: var(--input-bg);
        color: var(--input-text);
        font-size: 12px;
        box-sizing: border-box;
        transition: border-color 0.2s;
    }
    .msp-search-input:focus {
        border-color: var(--msp-primary);
        outline: none;
    }
    .msp-search-icon {
        position: absolute;
        left: 10px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-muted);
        pointer-events: none;
        display: flex;
        align-items: center;
    }

    /* Table Styling */
    .msp-table-wrapper {
        overflow-x: auto;
    }
    .msp-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
        text-align: left;
    }
    .msp-table th {
        background: var(--table-header-bg);
        color: var(--table-header-text);
        font-weight: 700;
        padding: 12px 14px;
        border-bottom: 2px solid var(--card-border);
        white-space: nowrap;
    }
    .msp-table td {
        padding: 12px 14px;
        border-bottom: 1px solid var(--table-border);
        color: var(--text-main);
        vertical-align: middle;
    }
    .msp-company-row {
        transition: background 0.15s;
        cursor: pointer;
    }
    .msp-company-row:hover {
        background: var(--table-hover);
    }
    .msp-company-row.msp-selected-row {
        background-color: rgba(2, 117, 216, 0.08) !important;
        border-left: 3px solid var(--msp-primary);
    }
    .msp-company-name {
        font-weight: 700;
        color: var(--msp-primary);
        font-size: 13px;
    }
    .msp-details-cell {
        line-height: 1.4;
    }
    .msp-id-sub {
        display: inline-block;
        font-size: 11px;
        color: var(--text-muted);
        margin-top: 2px;
    }

    .msp-badge {
        display: inline-flex;
        align-items: center;
        background: var(--badge-bg);
        color: var(--badge-text);
        border: 1px solid var(--badge-border);
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 700;
        margin: 2px;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.2s;
    }
    .msp-badge:hover {
        background: var(--msp-primary);
        color: #ffffff;
        border-color: var(--msp-primary);
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(2, 117, 216, 0.2);
    }

    /* Action Buttons */
    .msp-btn-delete {
        background: transparent;
        color: var(--msp-danger);
        border: 1px solid var(--msp-danger);
        padding: 5px 10px;
        border-radius: 4px;
        cursor: pointer;
        font-weight: 700;
        font-size: 12px;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .msp-btn-delete:hover {
        background: var(--msp-danger);
        color: #ffffff;
    }

    /* Alerts */
    .msp-alert {
        padding: 10px 14px;
        border-radius: 4px;
        margin-bottom: 15px;
        display: none;
        font-size: 12px;
        font-weight: 700;
        line-height: 1.4;
        animation: slideDown 0.35s ease-out;
    }
    .msp-alert-error {
        background: rgba(239, 68, 68, 0.1);
        color: #f87171;
        border: 1px solid rgba(239, 68, 68, 0.2);
    }
    .msp-alert-success {
        background: rgba(16, 185, 129, 0.1);
        color: #34d399;
        border: 1px solid rgba(16, 185, 129, 0.2);
    }

    /* Details Drawer Panel Styles */
    .msp-close-details-btn {
        background: none;
        border: none;
        cursor: pointer;
        color: var(--text-muted);
        padding: 4px;
        display: flex;
        align-items: center;
        border-radius: 4px;
        transition: all 0.2s;
    }
    .msp-close-details-btn:hover {
        color: var(--msp-danger);
        background: rgba(239, 68, 68, 0.1);
    }

    .msp-section-header {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-muted);
        margin-bottom: 10px;
        padding-bottom: 4px;
        border-bottom: 1px solid var(--card-border);
    }

    /* Connected Hosts list */
    .msp-details-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
        max-height: 220px;
        overflow-y: auto;
        padding-right: 4px;
        margin-bottom: 15px;
    }
    .msp-details-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 14px;
        border: 1px solid var(--card-border);
        border-radius: 8px;
        background: var(--input-bg);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
        transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
    }
    .msp-details-item:hover {
        transform: translateX(2px);
        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        border-color: var(--msp-primary);
    }
    .msp-host-link {
        font-size: 13px;
        font-weight: 700;
        color: var(--msp-primary) !important;
        text-decoration: none;
        transition: color 0.2s;
    }
    .msp-host-link:hover {
        color: var(--msp-primary-hover) !important;
        text-decoration: underline !important;
    }

    /* Badge Status */
    .msp-badge-status {
        font-size: 10px;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 4px;
        display: inline-block;
        margin-top: 4px;
    }
    .msp-status-green {
        background: rgba(16, 185, 129, 0.1) !important;
        color: #10b981 !important;
        border: 1px solid rgba(16, 185, 129, 0.2) !important;
    }
    .msp-status-gray {
        background: rgba(148, 163, 184, 0.1) !important;
        color: #94a3b8 !important;
        border: 1px solid rgba(148, 163, 184, 0.2) !important;
    }

    /* Status Badges for AGT/SNMP */
    .msp-status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 50px;
        font-size: 10px;
        font-weight: 700;
        cursor: help;
        border: 1px solid transparent;
        transition: all 0.2s;
    }
    
    /* AGT/SNMP Badge states */
    .msp-status-badge.status-avail {
        background: rgba(16, 185, 129, 0.12) !important;
        color: #10b981 !important;
        border-color: rgba(16, 185, 129, 0.2) !important;
    }
    .msp-status-badge.status-down {
        background: rgba(239, 68, 68, 0.12) !important;
        color: #f87171 !important;
        border-color: rgba(239, 68, 68, 0.2) !important;
    }
    .msp-status-badge.status-none {
        background: rgba(148, 163, 184, 0.08) !important;
        color: #94a3b8 !important;
        border-color: rgba(148, 163, 184, 0.15) !important;
    }

    .msp-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        display: inline-block;
    }
    .status-avail .msp-dot { background-color: #10b981; box-shadow: 0 0 6px #10b981; }
    .status-down .msp-dot { background-color: #ef4444; box-shadow: 0 0 6px #ef4444; }
    .status-none .msp-dot { background-color: #94a3b8; }

    /* Dashboard button contrast and visibility */
    .msp-dashboard-btn {
        background: linear-gradient(135deg, #0275d8, #025aa5) !important;
        color: #ffffff !important;
        font-weight: 700 !important;
        box-shadow: 0 4px 6px rgba(2, 117, 216, 0.2) !important;
        border: none !important;
        padding: 12px 16px !important;
        font-size: 13px !important;
        transition: all 0.2s !important;
        border-radius: 6px !important;
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        box-sizing: border-box;
    }
    .msp-dashboard-btn:hover {
        background: linear-gradient(135deg, #025aa5, #01447e) !important;
        box-shadow: 0 6px 12px rgba(2, 117, 216, 0.3) !important;
        transform: translateY(-1px);
        color: #ffffff !important;
        text-decoration: none !important;
    }

    /* Problems List */
    .msp-problems-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
        max-height: 250px;
        overflow-y: auto;
        padding-right: 4px;
    }
    .msp-problem-item {
        padding: 12px 14px;
        border-radius: 8px;
        background: var(--input-bg);
        border: 1px solid var(--card-border);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
        font-size: 12px;
        transition: transform 0.2s, border-color 0.2s;
        cursor: pointer;
        display: block;
        text-decoration: none;
    }
    .msp-problem-item:hover {
        transform: translateX(2px);
        border-color: var(--msp-primary);
    }
    .msp-problem-host {
        font-weight: 700;
        color: var(--text-main);
        font-size: 12px;
    }
    .msp-problem-name {
        font-weight: 500;
        color: var(--text-main);
        margin: 6px 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-size: 12px;
    }
    .msp-problem-time {
        font-size: 10px;
        color: var(--text-muted);
    }

    /* Severity borders */
    .msp-problem-item.severity-5 { border-left: 4px solid #ef4444 !important; }
    .msp-problem-item.severity-4 { border-left: 4px solid #ff5a1f !important; }
    .msp-problem-item.severity-3 { border-left: 4px solid #f97316 !important; }
    .msp-problem-item.severity-2 { border-left: 4px solid #eab308 !important; }
    .msp-problem-item.severity-1 { border-left: 4px solid #3b82f6 !important; }
    .msp-problem-item.severity-0 { border-left: 4px solid #94a3b8 !important; }

    /* Animations */
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-8px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>

<div class='msp-container'>
    <!-- KPI Cards Summary bar -->
    <div class='msp-kpi-grid'>
        <a href='zabbix.php?action=companies.list' class='msp-kpi-card' style='text-decoration: none;'>
            <div class='msp-kpi-info'>
                <span class='msp-kpi-value'><?= $total_companies ?></span>
                <span class='msp-kpi-label'>Total Companies</span>
            </div>
            <div class='msp-kpi-icon-wrapper msp-kpi-blue'>
                <svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><rect x='4' y='2' width='16' height='20' rx='2' ry='2'></rect><line x1='9' y1='22' x2='9' y2='16'></line><line x1='15' y1='22' x2='15' y2='16'></line><line x1='9' y1='16' x2='15' y2='16'></line><path d='M8 6h.01'></path><path d='M16 6h.01'></path><path d='M8 10h.01'></path><path d='M16 10h.01'></path><path d='M12 6h.01'></path><path d='M12 10h.01'></path></svg>
            </div>
        </a>

        <a href='zabbix.php?action=hostgroup.list' class='msp-kpi-card' style='text-decoration: none;' target='_blank'>
            <div class='msp-kpi-info'>
                <span class='msp-kpi-value'><?= $total_companies ?></span>
                <span class='msp-kpi-label'>Host Groups</span>
            </div>
            <div class='msp-kpi-icon-wrapper msp-kpi-green'>
                <svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><rect x='2' y='2' width='20' height='8' rx='2' ry='2'></rect><rect x='2' y='14' width='20' height='8' rx='2' ry='2'></rect><line x1='6' y1='6' x2='6.01' y2='6'></line><line x1='6' y1='18' x2='6.01' y2='18'></line><line x1='20' y1='6' x2='20.01' y2='6'></line><line x1='20' y1='18' x2='20.01' y2='18'></line></svg>
            </div>
        </a>

        <a href='zabbix.php?action=usergroup.list' class='msp-kpi-card' style='text-decoration: none;' target='_blank'>
            <div class='msp-kpi-info'>
                <span class='msp-kpi-value'><?= $total_companies ?></span>
                <span class='msp-kpi-label'>User Groups</span>
            </div>
            <div class='msp-kpi-icon-wrapper msp-kpi-yellow'>
                <svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2'></path><circle cx='9' cy='7' r='4'></circle><path d='M23 21v-2a4 4 0 0 0-3-3.87'></path><path d='M16 3.13a4 4 0 0 1 0 7.75'></path></svg>
            </div>
        </a>

        <a href='zabbix.php?action=user.list' class='msp-kpi-card' style='text-decoration: none;' target='_blank'>
            <div class='msp-kpi-info'>
                <span class='msp-kpi-value'><?= $total_users ?></span>
                <span class='msp-kpi-label'>Active Admins</span>
            </div>
            <div class='msp-kpi-icon-wrapper msp-kpi-purple'>
                <svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z'></path></svg>
            </div>
        </a>
    </div>

    <!-- Main Content Workspace Grid -->
    <div class='msp-main-grid'>
        <!-- Side Panel Card (Form or Details) -->
        <div class='msp-card' id='msp-side-panel'>
            <!-- 1. Add Company Form (Initial view) -->
            <div id='msp-create-container'>
                <div class='msp-card-header'>
                    <div class='msp-card-title'>
                        <svg width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'><line x1='12' y1='5' x2='12' y2='19'></line><line x1='5' y1='12' x2='19' y2='12'></line></svg>
                        Add New Client Company
                    </div>
                </div>
                
                <div id='msp-alert-box' class='msp-alert'></div>
                
                <form id='msp-create-form'>
                    <div class='msp-form-group'>
                        <label class='msp-label' for='company-name'>Company Name</label>
                        <input type='text' id='company-name' name='name' class='msp-input' required placeholder='e.g. ClientA (No spaces)'>
                    </div>
                    <div class='msp-form-group'>
                        <label class='msp-label' for='admin-user'>Admin Username</label>
                        <input type='text' id='admin-user' name='user' class='msp-input' required placeholder='e.g. clienta_admin'>
                    </div>
                    <div class='msp-form-group'>
                        <label class='msp-label' for='admin-password'>Admin Password</label>
                        <div class='msp-password-container'>
                            <input type='password' id='admin-password' name='password' class='msp-input msp-password-input' required placeholder='e.g. SecretPassword123!'>
                            <button type='button' id='msp-password-toggle' class='msp-password-toggle-btn' tabindex='-1'>
                                <svg id='eye-show' class='msp-eye-icon' width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z'></path><circle cx='12' cy='12' r='3'></circle></svg>
                                <svg id='eye-hide' class='msp-eye-icon' style='display:none;' width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24'></path><line x1='1' y1='1' x2='23' y2='23'></line></svg>
                            </button>
                        </div>
                    </div>
                    <button type='submit' class='msp-btn'>Add & Configure Client</button>
                </form>
            </div>

            <!-- 2. Company Details (Hidden initially) -->
            <div id='msp-details-container' style='display: none;'>
                <!-- Dynamic details injected by JavaScript -->
            </div>
        </div>
        
        <!-- Registered Companies List Card -->
        <div class='msp-card'>
            <div class='msp-card-header'>
                <div class='msp-card-title'>
                    <svg width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><line x1='8' y1='6' x2='21' y2='6'></line><line x1='8' y1='12' x2='21' y2='12'></line><line x1='8' y1='18' x2='21' y2='18'></line><line x1='3' y1='6' x2='3.01' y2='6'></line><line x1='3' y1='12' x2='3.01' y2='12'></line><line x1='3' y1='18' x2='3.01' y2='18'></line></svg>
                    Registered Client Companies
                </div>
                <!-- Live Search Field -->
                <div class='msp-search-box'>
                    <div class='msp-search-icon'>
                        <svg width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><circle cx='11' cy='11' r='8'></circle><line x1='21' y1='21' x2='16.65' y2='16.65'></line></svg>
                    </div>
                    <input type='text' id='msp-search-input' placeholder='Search companies...' class='msp-search-input' onclick='event.stopPropagation();'>
                </div>
            </div>
            
            <div class='msp-table-wrapper'>
                <table class='msp-table'>
                    <thead>
                        <tr>
                            <th>Company Name</th>
                            <th>Host Group</th>
                            <th>User Group</th>
                            <th>Admin User(s)</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($data['companies'])): ?>
                            <tr class='msp-company-row'>
                                <td colspan='5' style='text-align:center; color: var(--text-muted); padding: 30px;'>No client companies registered yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($data['companies'] as $c): ?>
                                <?php
                                $users_str = '';
                                foreach ($c['users'] as $u) {
                                    $users_str .= "<a href='zabbix.php?action=user.edit&userid=" . htmlspecialchars((string)$u['userid']) . "' class='msp-badge' target='_blank' onclick='event.stopPropagation();' style='text-decoration: none;' title='Edit User'>" . htmlspecialchars($u['username']) . "</a>";
                                }
                                $comp_name_esc = htmlspecialchars($c['name']);
                                $group_id_esc = htmlspecialchars((string)$c['groupid']);
                                $usrgrp_id_esc = htmlspecialchars((string)$c['usrgrpid']);
                                ?>
                                <tr class='msp-company-row' data-name='<?= $comp_name_esc ?>' onclick='showCompanyDetails("<?= $comp_name_esc ?>")'>
                                    <td><strong class='msp-company-name'><?= $comp_name_esc ?></strong></td>
                                    <td>
                                        <div class='msp-details-cell'>
                                            <a href='zabbix.php?action=hostgroup.edit&groupid=<?= $group_id_esc ?>' style='color: inherit; text-decoration: none; font-weight: 500; border-bottom: 1px dotted var(--text-muted);' target='_blank' onclick='event.stopPropagation();'>
                                                Tenant - <?= $comp_name_esc ?>
                                            </a>
                                            <br>
                                            <span class='msp-id-sub'>ID: <?= $group_id_esc ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class='msp-details-cell'>
                                            <a href='zabbix.php?action=usergroup.edit&usrgrpid=<?= $usrgrp_id_esc ?>' style='color: inherit; text-decoration: none; font-weight: 500; border-bottom: 1px dotted var(--text-muted);' target='_blank' onclick='event.stopPropagation();'>
                                                Tenant - <?= $comp_name_esc ?> - Users
                                            </a>
                                            <br>
                                            <span class='msp-id-sub'>ID: <?= $usrgrp_id_esc ?></span>
                                        </div>
                                    </td>
                                    <td><?= $users_str ?></td>
                                    <td>
                                        <button type='button' class='msp-btn-delete' onclick='event.stopPropagation(); deleteCompany("<?= $comp_name_esc ?>");'>
                                            <svg width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round' style='vertical-align: middle; margin-right: 3px;'><polyline points='3 6 5 6 21 6'></polyline><path d='M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2'></path></svg>
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Theme auto-detection execution (runs immediately to prevent flash)
(() => {
    const link = document.querySelector('link[href*="assets/styles/"]');
    const href = link ? link.getAttribute('href') : '';
    const isDark = href.includes('dark') || href.includes('hc-dark');
    const container = document.querySelector('.msp-container');
    if (container) {
        container.classList.add(isDark ? 'msp-dark' : 'msp-light');
    }
})();
</script>

<script>
// Export server data to client side
const companyData = <?= json_encode($data['companies']) ?>;
const mspDashboardId = <?= json_encode($data['dashboard_id']) ?>;

// Helper functions for details panel
function escapeHtml(str) {
    if (!str) return '';
    return str.toString()
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function getSeverityLabel(severity) {
    const labels = {
        0: 'Not classified',
        1: 'Information',
        2: 'Warning',
        3: 'Average',
        4: 'High',
        5: 'Disaster'
    };
    return labels[severity] || 'Unknown';
}

function formatTime(timestamp) {
    const diff = Math.floor(Date.now() / 1000) - timestamp;
    if (diff < 60) return 'Just now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    
    const date = new Date(timestamp * 1000);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
}

function showCompanyDetails(name) {
    const comp = companyData.find(c => c.name === name);
    if (!comp) return;

    const createContainer = document.getElementById('msp-create-container');
    const detailsContainer = document.getElementById('msp-details-container');
    
    // Highlight row in table
    document.querySelectorAll('.msp-company-row').forEach(row => {
        row.classList.remove('msp-selected-row');
        if (row.getAttribute('data-name') === name) {
            row.classList.add('msp-selected-row');
        }
    });

    // 1. Hosts List HTML
    let hostsHtml = '';
    if (!comp.hosts || comp.hosts.length === 0) {
        hostsHtml = `<div style='padding: 10px 0; color: var(--text-muted); font-style: italic; font-size: 12px;'>No hosts connected to this company.</div>`;
    } else {
        hostsHtml = `<div class='msp-details-list'>`;
        comp.hosts.forEach(h => {
            const isMonitored = h.status == 0;
            const statusLabel = isMonitored ? 
                `<span class='msp-badge-status msp-status-green'>Monitored</span>` : 
                `<span class='msp-badge-status msp-status-gray'>Disabled</span>`;
            
            // Agent availability check
            let agentClass = 'status-none';
            let agentTitle = 'Agent: Not Configured';
            if (h.available === 1) {
                agentClass = 'status-avail';
                agentTitle = 'Agent: Available / Connected';
            } else if (h.available === 2) {
                agentClass = 'status-down';
                agentTitle = `Agent Error: ${escapeHtml(h.error || 'Connection timed out')}`;
            }

            // SNMP availability check
            let snmpClass = 'status-none';
            let snmpTitle = 'SNMP: Not Configured';
            if (h.snmp_available === 1) {
                snmpClass = 'status-avail';
                snmpTitle = 'SNMP: Available / Connected';
            } else if (h.snmp_available === 2) {
                snmpClass = 'status-down';
                snmpTitle = `SNMP Error: ${escapeHtml(h.snmp_error || 'Connection failed')}`;
            }

            hostsHtml += `
                <div class='msp-details-item'>
                    <div style='flex-grow: 1; min-width: 0; overflow: hidden; text-align: left;'>
                        <a href='zabbix.php?action=host.view&hostid=${h.hostid}' target='_blank' class='msp-host-link' title='View host dashboard and graphs'>
                            ${escapeHtml(h.name)}
                        </a>
                        <br>
                        ${statusLabel}
                    </div>
                    <div style='display: flex; gap: 8px; align-items: center;'>
                        <span class='msp-status-badge ${agentClass}' title='${agentTitle}'>
                            <span class='msp-dot'></span>
                            <span>AGT</span>
                        </span>
                        <span class='msp-status-badge ${snmpClass}' title='${snmpTitle}'>
                            <span class='msp-dot'></span>
                            <span>SNMP</span>
                        </span>
                    </div>
                </div>
            `;
        });
        hostsHtml += `</div>`;
    }

    // 2. Active Problems List HTML
    let problemsHtml = '';
    if (!comp.problems || comp.problems.length === 0) {
        problemsHtml = `<div style='padding: 10px 0; color: var(--text-muted); font-style: italic; font-size: 12px;'>No active alerts or logs found.</div>`;
    } else {
        problemsHtml = `<div class='msp-problems-list'>`;
        comp.problems.forEach(p => {
            const timeStr = formatTime(p.clock);
            const severityClass = `msp-severity-${p.severity}`;
            const severityLabel = getSeverityLabel(p.severity);
            problemsHtml += `
                <a href='tr_events.php?triggerid=${p.triggerid}&eventid=${p.eventid}' target='_blank' class='msp-problem-item severity-${p.severity}' style='text-align: left; text-decoration: none;' title='View trigger details'>
                    <div style='display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;'>
                        <span class='msp-problem-host'>${escapeHtml(p.host)}</span>
                        <span class='msp-severity-badge ${severityClass}'>${severityLabel}</span>
                    </div>
                    <div class='msp-problem-name' title='${escapeHtml(p.name)}'>${escapeHtml(p.name)}</div>
                    <div class='msp-problem-time'>${timeStr}</div>
                </a>
            `;
        });
        problemsHtml += `</div>`;
    }

    // 3. MSP Dashboard Link
    let dashboardBtnHtml = '';
    if (mspDashboardId) {
        dashboardBtnHtml = `
            <a href='zabbix.php?action=dashboard.view&dashboardid=${mspDashboardId}' target='_blank' class='msp-dashboard-btn' style='margin-bottom: 20px;'>
                <svg width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='vertical-align: middle;'><rect x='3' y='3' width='7' height='7'></rect><rect x='14' y='3' width='7' height='7'></rect><rect x='14' y='14' width='7' height='7'></rect><rect x='3' y='14' width='7' height='7'></rect></svg>
                Open MSP Dashboard
            </a>
        `;
    } else {
        dashboardBtnHtml = `
            <button type='button' class='msp-dashboard-btn' style='margin-bottom: 20px; background: var(--text-muted) !important; cursor: not-allowed;' disabled>
                Dashboard Not Generated
            </button>
        `;
    }

    // Render everything in the details drawer
    detailsContainer.innerHTML = `
        <div class='msp-card-header'>
            <div class='msp-card-title' style='color: var(--msp-primary);'>
                <svg width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z'></path></svg>
                ${escapeHtml(name)} Overview
            </div>
            <button type='button' class='msp-close-details-btn' onclick='hideCompanyDetails()' title='Back to Add Company Form'>
                <svg width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'><line x1='18' y1='6' x2='6' y2='18'></line><line x1='6' y1='6' x2='18' y2='18'></line></svg>
            </button>
        </div>

        ${dashboardBtnHtml}

        <div>
            <div class='msp-section-header'>Connected Hosts (${comp.hosts ? comp.hosts.length : 0})</div>
            ${hostsHtml}
        </div>

        <div style='margin-top: 15px;'>
            <div class='msp-section-header'>Live Logs & Alerts</div>
            ${problemsHtml}
        </div>
    `;

    createContainer.style.display = 'none';
    detailsContainer.style.display = 'block';
}

function hideCompanyDetails() {
    document.getElementById('msp-create-container').style.display = 'block';
    document.getElementById('msp-details-container').style.display = 'none';
    
    // Remove selection highlight from table
    document.querySelectorAll('.msp-company-row').forEach(row => {
        row.classList.remove('msp-selected-row');
    });
}

// Password hide/show toggle
document.addEventListener('DOMContentLoaded', () => {
    const passInput = document.getElementById('admin-password');
    const toggleBtn = document.getElementById('msp-password-toggle');
    const eyeShow = document.getElementById('eye-show');
    const eyeHide = document.getElementById('eye-hide');

    if (toggleBtn && passInput) {
        toggleBtn.addEventListener('click', () => {
            if (passInput.type === 'password') {
                passInput.type = 'text';
                eyeShow.style.display = 'none';
                eyeHide.style.display = 'block';
            } else {
                passInput.type = 'password';
                eyeShow.style.display = 'block';
                eyeHide.style.display = 'none';
            }
        });
    }

    // Live Search filter
    const searchInput = document.getElementById('msp-search-input');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            const q = e.target.value.toLowerCase().trim();
            const rows = document.querySelectorAll('.msp-company-row');
            rows.forEach(row => {
                const name = row.getAttribute('data-name');
                if (name) {
                    if (name.toLowerCase().includes(q)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                }
            });
        });
    }
});

document.getElementById('msp-create-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const name = document.getElementById('company-name').value.trim();
    const user = document.getElementById('admin-user').value.trim();
    const password = document.getElementById('admin-password').value;
    
    const alertBox = document.getElementById('msp-alert-box');
    alertBox.className = 'msp-alert';
    alertBox.style.display = 'none';
    
    const submitBtn = e.target.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = `
        <svg width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round' style='animation: msp-spin 1s linear infinite; margin-right: 8px;'><line x1='12' y1='2' x2='12' y2='6'></line><line x1='12' y1='18' x2='12' y2='22'></line><line x1='4.93' y1='4.93' x2='7.76' y2='7.76'></line><line x1='16.24' y1='16.24' x2='19.07' y2='19.07'></line><line x1='2' y1='12' x2='6' y2='12'></line><line x1='18' y1='12' x2='22' y2='12'></line><line x1='4.93' y1='19.07' x2='7.76' y2='16.24'></line><line x1='16.24' y1='7.76' x2='19.07' y2='4.93'></line></svg>
        Creating & Configuring...
    `;
    
    const url = new URL(window.location.href);
    url.searchParams.set('action', 'companies.create');
    
    fetch(url.toString(), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ name: name, user: user, password: password })
    })
    .then(res => res.json())
    .then(data => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
        if (data.success) {
            alertBox.className = 'msp-alert msp-alert-success';
            alertBox.innerText = 'Company successfully registered!';
            alertBox.style.display = 'block';
            setTimeout(() => { window.location.reload(); }, 1200);
        } else {
            alertBox.className = 'msp-alert msp-alert-error';
            alertBox.innerText = data.error || 'Failed to add company.';
            alertBox.style.display = 'block';
        }
    })
    .catch(err => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
        alertBox.className = 'msp-alert msp-alert-error';
        alertBox.innerText = 'Error: ' + err.message;
        alertBox.style.display = 'block';
    });
});

function deleteCompany(name) {
    if (!confirm('Are you sure you want to delete "' + name + '"? This will permanently delete its Host Group, User Group, and all associated Users.')) {
        return;
    }
    
    const url = new URL(window.location.href);
    url.searchParams.set('action', 'companies.delete');
    
    fetch(url.toString(), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ name: name })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Company deleted successfully.');
            window.location.reload();
        } else {
            alert('Failed to delete company: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(err => {
        alert('Error: ' + err.message);
    });
}
</script>
<style>
    @keyframes msp-spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
</style>
<?php
$html_content = ob_get_clean();

$html_page->addItem(new CObject($html_content));
$html_page->show();
