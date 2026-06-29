<?php declare(strict_types = 1);

/**
 * NOC Network Topology — Interactive Hierarchical Map
 *
 * @var CView $this
 * @var array $data
 */

$server_name = $data['server_name'];
$proxies = $data['proxies'];
$hosts = $data['hosts'];
$host_problems = $data['host_problems'];

// ── Build nodes & edges with level assignments for hierarchical layout ──

$nodes = [];
$edges = [];

// Statistics counters
$total_healthy = 0;
$total_warning = 0;
$total_critical = 0;

// ── Level 0 — Zabbix Server (root) ──
$nodes[] = [
    'id'          => 'server_root',
    'label'       => $server_name,
    'title'       => '<div style="padding:6px"><b style="font-size:14px">' . htmlspecialchars($server_name) . '</b><br><span style="color:#94a3b8">Role:</span> Central Monitoring Engine<br><span style="color:#94a3b8">IP:</span> 127.0.0.1<br><span style="color:#94a3b8">Status:</span> <span style="color:#22c55e">● Running</span></div>',
    'level'       => 0,
    'shape'       => 'box',
    'margin'      => ['top' => 18, 'bottom' => 18, 'left' => 24, 'right' => 24],
    'color'       => ['background' => '#1e3a8a', 'border' => '#60a5fa', 'highlight' => ['background' => '#2563eb', 'border' => '#93c5fd']],
    'borderWidth' => 3,
    'shadow'      => ['enabled' => true, 'color' => 'rgba(59,130,246,0.35)', 'size' => 12, 'x' => 0, 'y' => 4],
    'font'        => ['color' => '#ffffff', 'size' => 16, 'face' => 'system-ui, -apple-system, sans-serif', 'bold' => ['color' => '#ffffff']],
    'chosen'      => true,
    'device_type' => 'server'
];

// ── Level 1 — Proxies ──
$proxy_map = [];
foreach ($proxies as $p) {
    $pid          = $p['proxyid'];
    $pname        = $p['name'];
    $proxy_node_id = 'proxy_' . $pid;
    $proxy_map[$pid] = $proxy_node_id;

    $host_count = is_array($p['hosts']) ? count($p['hosts']) : 0;

    $nodes[] = [
        'id'          => $proxy_node_id,
        'label'       => $pname . "\nActive Proxy · " . $host_count . " hosts",
        'title'       => '<div style="padding:6px"><b style="font-size:13px">' . htmlspecialchars($pname) . '</b><br><span style="color:#94a3b8">Type:</span> Active Proxy<br><span style="color:#94a3b8">ID:</span> ' . $pid . '<br><span style="color:#94a3b8">Monitored Hosts:</span> ' . $host_count . '</div>',
        'level'       => 1,
        'shape'       => 'box',
        'margin'      => ['top' => 14, 'bottom' => 14, 'left' => 20, 'right' => 20],
        'color'       => ['background' => '#1e293b', 'border' => '#6366f1', 'highlight' => ['background' => '#334155', 'border' => '#818cf8']],
        'borderWidth' => 2,
        'shadow'      => ['enabled' => true, 'color' => 'rgba(99,102,241,0.25)', 'size' => 8, 'x' => 0, 'y' => 3],
        'font'        => ['color' => '#e2e8f0', 'size' => 13, 'face' => 'system-ui, -apple-system, sans-serif'],
        'chosen'      => true,
        'device_type' => 'proxy'
    ];

    $edges[] = [
        'from'   => 'server_root',
        'to'     => $proxy_node_id,
        'width'  => 3,
        'color'  => ['color' => '#6366f1', 'highlight' => '#818cf8'],
        'dashes' => [8, 4],
        'smooth' => ['type' => 'cubicBezier', 'forceDirection' => 'vertical', 'roundness' => 0.4],
        'arrows' => ['to' => ['enabled' => true, 'scaleFactor' => 0.6]]
    ];
}

// ── Level 2 — Hosts ──
foreach ($hosts as $h) {
    $hid      = $h['hostid'];
    $hname    = $h['name'];
    $hstatus  = (int)$h['status'];
    $proxy_id = $h['proxyid'];

    if ($hstatus == 3) continue; // skip templates

    // Resolve main IP
    $ip = 'No IP';
    $iface_type_name = 'Agent';
    if (!empty($h['interfaces'])) {
        foreach ($h['interfaces'] as $iface) {
            if ($iface['main'] == 1) {
                $ip = $iface['ip'];
                $iface_type_name = ($iface['type'] == 2) ? 'SNMP' : 'Agent';
                break;
            }
        }
    }

    // Severity & tooltip
    $bg        = '#0f172a';
    $border    = '#10b981';
    $shadow_c  = 'rgba(16,185,129,0.2)';
    $status_html = '<span style="color:#22c55e">● Healthy</span>';
    $max_sev   = -1;
    $problem_lines = '';

    if (isset($host_problems[$hid]) && count($host_problems[$hid]) > 0) {
        foreach ($host_problems[$hid] as $prob) {
            if ($prob['severity'] > $max_sev) $max_sev = $prob['severity'];
            $pc = $prob['severity'] >= 4 ? '#f43f5e' : '#f59e0b';
            $problem_lines .= '<span style="color:' . $pc . '">▸ ' . htmlspecialchars($prob['name']) . '</span><br>';
        }
        if ($max_sev >= 4) {
            $bg       = '#300a12';
            $border   = '#f43f5e';
            $shadow_c = 'rgba(244,63,94,0.3)';
            $status_html = '<span style="color:#f43f5e">● ' . count($host_problems[$hid]) . ' Critical</span>';
            $total_critical++;
        } else {
            $bg       = '#2a1a00';
            $border   = '#f59e0b';
            $shadow_c = 'rgba(245,158,11,0.25)';
            $status_html = '<span style="color:#f59e0b">● ' . count($host_problems[$hid]) . ' Warning</span>';
            $total_warning++;
        }
    } else {
        $total_healthy++;
    }

    $tooltip = '<div style="padding:6px;min-width:200px"><b style="font-size:13px">' . htmlspecialchars($hname) . '</b><br>'
             . '<span style="color:#94a3b8">IP:</span> ' . htmlspecialchars($ip) . '<br>'
             . '<span style="color:#94a3b8">Interface:</span> ' . $iface_type_name . '<br>'
             . '<span style="color:#94a3b8">Status:</span> ' . $status_html . '<br>';
    if ($problem_lines) {
        $tooltip .= '<hr style="border-color:#334155;margin:6px 0"><span style="color:#94a3b8;font-size:11px">ACTIVE PROBLEMS</span><br>' . $problem_lines;
    }
    $tooltip .= '</div>';

    // Detect device type from templates + name
    $device_type = 'host';
    $name_lower = strtolower($hname);
    foreach ($h['parentTemplates'] as $t) {
        $tn = strtolower($t['name']);
        if (strpos($tn, 'firewall') !== false || strpos($tn, 'fortigate') !== false) $device_type = 'firewall';
        if (strpos($tn, 'windows') !== false || strpos($tn, 'linux') !== false)      $device_type = 'server';
        if (strpos($tn, 'switch') !== false || strpos($tn, 'mikrotik') !== false)    $device_type = 'switch';
    }
    if ($device_type === 'host') {
        if (strpos($name_lower, 'fortigate') !== false || strpos($name_lower, 'firewall') !== false) $device_type = 'firewall';
        elseif (strpos($name_lower, 'dc-') !== false || strpos($name_lower, 'server') !== false)     $device_type = 'server';
        elseif (strpos($name_lower, 'switch') !== false || strpos($name_lower, 'router') !== false)  $device_type = 'switch';
    }

    $nodes[] = [
        'id'          => 'host_' . $hid,
        'label'       => $hname . "\n" . $ip,
        'title'       => $tooltip,
        'level'       => 2,
        'shape'       => 'box',
        'margin'      => ['top' => 12, 'bottom' => 12, 'left' => 16, 'right' => 16],
        'color'       => ['background' => $bg, 'border' => $border, 'highlight' => ['background' => '#1e293b', 'border' => '#60a5fa']],
        'borderWidth' => 2,
        'shadow'      => ['enabled' => true, 'color' => $shadow_c, 'size' => 6, 'x' => 0, 'y' => 2],
        'font'        => ['color' => '#f1f5f9', 'size' => 12, 'face' => 'system-ui, -apple-system, sans-serif'],
        'chosen'      => true,
        'device_type' => $device_type
    ];

    // Link to proxy or directly to server
    $parent_id = 'server_root';
    if (!empty($proxy_id) && $proxy_id !== '0' && isset($proxy_map[$proxy_id])) {
        $parent_id = $proxy_map[$proxy_id];
    }

    $edges[] = [
        'from'   => $parent_id,
        'to'     => 'host_' . $hid,
        'width'  => $max_sev >= 4 ? 2.5 : ($max_sev >= 0 ? 2 : 1.5),
        'color'  => ['color' => ($max_sev >= 4 ? '#f43f5e' : ($max_sev >= 0 ? '#f59e0b' : '#10b981')), 'highlight' => '#60a5fa'],
        'smooth' => ['type' => 'cubicBezier', 'forceDirection' => 'vertical', 'roundness' => 0.4],
        'arrows' => ['to' => ['enabled' => true, 'scaleFactor' => 0.5]]
    ];
}

$total_hosts = count($hosts);
$total_proxies = count($proxies);
?>

<style>
/* ── NOC Topology Premium Styles ── */
.noc-page { padding: 0 0 20px 0; }

.noc-header {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    border: 1px solid #1e293b;
    border-radius: 12px;
    padding: 20px 28px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.noc-header h1 {
    font-size: 22px;
    font-weight: 700;
    color: #f8fafc;
    margin: 0;
    letter-spacing: -0.3px;
}
.noc-header .subtitle {
    color: #64748b;
    font-size: 13px;
    margin-top: 4px;
}

/* ── KPI Stat Cards ── */
.noc-stats {
    display: flex;
    gap: 12px;
}
.stat-card {
    background: #0f172a;
    border: 1px solid #334155;
    border-radius: 10px;
    padding: 12px 20px;
    text-align: center;
    min-width: 100px;
    transition: all 0.25s ease;
}
.stat-card:hover { border-color: #60a5fa; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.3); }
.stat-card .stat-val { font-size: 26px; font-weight: 800; color: #f1f5f9; line-height: 1.1; }
.stat-card .stat-label { font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 0.6px; margin-top: 4px; font-weight: 600; }
.stat-card.healthy .stat-val  { color: #22c55e; }
.stat-card.warning .stat-val  { color: #f59e0b; }
.stat-card.critical .stat-val { color: #f43f5e; }

/* ── Main Layout ── */
.noc-body {
    display: flex;
    gap: 14px;
    height: calc(100vh - 225px);
    min-height: 480px;
}

/* ── Sidebar ── */
.noc-sidebar {
    width: 280px;
    min-width: 280px;
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.sidebar-section {
    padding: 16px 18px;
    border-bottom: 1px solid #1e293b;
}
.sidebar-section:last-child { border-bottom: none; }
.sidebar-title {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: #475569;
    margin-bottom: 10px;
}

/* Search */
.noc-search {
    width: 100%;
    padding: 10px 14px;
    background: #1e293b;
    border: 1px solid #334155;
    border-radius: 8px;
    color: #f1f5f9;
    font-size: 13px;
    outline: none;
    transition: border-color 0.2s;
    box-sizing: border-box;
}
.noc-search:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.15); }
.noc-search::placeholder { color: #475569; }

/* Legend */
.legend-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 6px 0;
    font-size: 13px;
    color: #cbd5e1;
}
.legend-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
}

/* Buttons */
.noc-btn {
    width: 100%;
    padding: 10px 14px;
    border-radius: 8px;
    border: 1px solid #334155;
    background: #1e293b;
    color: #e2e8f0;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: center;
    margin-top: 6px;
}
.noc-btn:hover { background: #334155; border-color: #6366f1; color: #fff; }
.noc-btn:active { transform: scale(0.97); }
.noc-btn.active { background: #22c55e; border-color: #16a34a; color: #fff; }

/* ── Canvas Container ── */
.noc-canvas-wrap {
    flex: 1;
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 12px;
    position: relative;
    overflow: hidden;
}
#topology-canvas {
    width: 100%;
    height: 100%;
}

/* Zoom buttons */
.noc-zoom {
    position: absolute;
    top: 14px;
    right: 14px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    z-index: 10;
}
.noc-zoom button {
    width: 38px;
    height: 38px;
    background: rgba(15,23,42,0.85);
    backdrop-filter: blur(8px);
    border: 1px solid #334155;
    border-radius: 10px;
    color: #e2e8f0;
    font-size: 20px;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    line-height: 1;
}
.noc-zoom button:hover { background: #334155; color: #60a5fa; border-color: #60a5fa; }
.noc-zoom button:active { transform: scale(0.9); }

/* Node info panel (bottom-left overlay) */
.node-info-panel {
    position: absolute;
    bottom: 14px;
    left: 14px;
    background: rgba(15,23,42,0.92);
    backdrop-filter: blur(12px);
    border: 1px solid #334155;
    border-radius: 10px;
    padding: 14px 18px;
    color: #e2e8f0;
    font-size: 12px;
    max-width: 320px;
    z-index: 10;
    display: none;
    line-height: 1.6;
}
.node-info-panel.visible { display: block; animation: fadeSlideUp 0.3s ease; }
.node-info-panel .info-title { font-size: 15px; font-weight: 700; color: #f8fafc; margin-bottom: 6px; }
.node-info-panel .info-row { color: #94a3b8; }
.node-info-panel .info-row b { color: #e2e8f0; }

@keyframes fadeSlideUp {
    from { opacity: 0; transform: translateY(10px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* Tooltip override */
div.vis-network div.vis-tooltip {
    background-color: rgba(15,23,42,0.96) !important;
    backdrop-filter: blur(12px) !important;
    border: 1px solid #334155 !important;
    color: #f1f5f9 !important;
    border-radius: 10px !important;
    padding: 12px 16px !important;
    font-family: system-ui, -apple-system, sans-serif !important;
    font-size: 12px !important;
    box-shadow: 0 12px 40px rgba(0,0,0,0.5) !important;
    max-width: 350px !important;
    line-height: 1.6 !important;
}
</style>

<div class="noc-page">

    <!-- ── Header with KPI Cards ── -->
    <div class="noc-header">
        <div>
            <h1>Network Topology</h1>
            <div class="subtitle">Live auto-discovered network map · Powered by Zabbix API</div>
        </div>
        <div class="noc-stats">
            <div class="stat-card">
                <div class="stat-val"><?php echo $total_hosts; ?></div>
                <div class="stat-label">Devices</div>
            </div>
            <div class="stat-card">
                <div class="stat-val"><?php echo $total_proxies; ?></div>
                <div class="stat-label">Proxies</div>
            </div>
            <div class="stat-card healthy">
                <div class="stat-val"><?php echo $total_healthy; ?></div>
                <div class="stat-label">Healthy</div>
            </div>
            <div class="stat-card warning">
                <div class="stat-val"><?php echo $total_warning; ?></div>
                <div class="stat-label">Warning</div>
            </div>
            <div class="stat-card critical">
                <div class="stat-val"><?php echo $total_critical; ?></div>
                <div class="stat-label">Critical</div>
            </div>
        </div>
    </div>

    <!-- ── Body: Sidebar + Canvas ── -->
    <div class="noc-body">

        <!-- Sidebar -->
        <div class="noc-sidebar">
            <div class="sidebar-section">
                <div class="sidebar-title">Search</div>
                <input type="text" id="host-search" class="noc-search" placeholder="Device name or IP…" />
            </div>

            <div class="sidebar-section">
                <div class="sidebar-title">Legend</div>
                <div class="legend-row"><span class="legend-dot" style="background:#60a5fa"></span> Zabbix Server</div>
                <div class="legend-row"><span class="legend-dot" style="background:#6366f1"></span> Active Proxy</div>
                <div class="legend-row"><span class="legend-dot" style="background:#10b981"></span> Healthy Host</div>
                <div class="legend-row"><span class="legend-dot" style="background:#f59e0b"></span> Warning</div>
                <div class="legend-row"><span class="legend-dot" style="background:#f43f5e"></span> Critical / Down</div>
            </div>

            <div class="sidebar-section">
                <div class="sidebar-title">Controls</div>
                <button id="btn-freeze" class="noc-btn" onclick="togglePhysics()">⏸ Freeze Layout</button>
                <button class="noc-btn" onclick="fitMap()">⛶ Fit to Screen</button>
                <button class="noc-btn" onclick="resetSearch()">✕ Clear Search</button>
            </div>
        </div>

        <!-- Canvas -->
        <div class="noc-canvas-wrap">
            <div class="noc-zoom">
                <button onclick="zoomIn()" title="Zoom In">+</button>
                <button onclick="zoomOut()" title="Zoom Out">−</button>
                <button onclick="fitMap()" title="Fit">⛶</button>
            </div>
            <div id="topology-canvas"></div>

            <!-- Click info panel -->
            <div id="node-info" class="node-info-panel"></div>
        </div>

    </div>
</div>

<!-- Vis-Network (self-hosted, CSP-safe) -->
<script type="text/javascript" src="/zabbix/modules/companies/assets/vis-network.min.js"></script>

<script type="text/javascript">
(function() {
    'use strict';

    // ── Safety check ──
    var canvas = document.getElementById('topology-canvas');
    if (typeof vis === 'undefined') {
        canvas.innerHTML = '<div style="color:#f43f5e;padding:60px 30px;text-align:center;font-size:15px;font-weight:600">' +
            '❌ vis-network.min.js failed to load.<br>' +
            '<span style="color:#64748b;font-size:12px;font-weight:400">Check /zabbix/modules/companies/assets/vis-network.min.js path.</span></div>';
        return;
    }

    // ── Data from PHP ──
    var rawNodes = <?php echo json_encode($nodes, JSON_UNESCAPED_UNICODE); ?>;
    var rawEdges = <?php echo json_encode($edges, JSON_UNESCAPED_UNICODE); ?>;

    var nodesDS = new vis.DataSet(rawNodes);
    var edgesDS = new vis.DataSet(rawEdges);

    // ── Network options — hierarchical top-down tree ──
    var options = {
        layout: {
            hierarchical: {
                enabled: true,
                direction: 'UD',            // Up-Down tree
                sortMethod: 'directed',
                levelSeparation: 140,
                nodeSpacing: 200,
                treeSpacing: 220,
                blockShifting: true,
                edgeMinimization: true,
                parentCentralization: true
            }
        },
        nodes: {
            font: {
                multi: false,
                size: 12,
                face: 'system-ui, -apple-system, sans-serif'
            },
            shapeProperties: {
                borderRadius: 8
            }
        },
        edges: {
            width: 1.5,
            selectionWidth: 2,
            smooth: {
                type: 'cubicBezier',
                forceDirection: 'vertical',
                roundness: 0.4
            }
        },
        physics: {
            enabled: false            // hierarchical layout doesn't need physics
        },
        interaction: {
            hover: true,
            tooltipDelay: 80,
            zoomView: true,
            dragView: true,
            dragNodes: true,
            selectable: true,
            multiselect: false,
            navigationButtons: false,
            keyboard: {
                enabled: true,
                speed: { x: 10, y: 10, zoom: 0.04 }
            }
        }
    };

    var network = new vis.Network(canvas, { nodes: nodesDS, edges: edgesDS }, options);

    // Fit after initial render
    network.once('stabilized', function() {
        network.fit({ animation: { duration: 600, easingFunction: 'easeInOutQuad' } });
    });
    // Fallback fit
    setTimeout(function() { network.fit({ animation: { duration: 500 } }); }, 800);

    // ── Click handler — show info panel ──
    var infoPanel = document.getElementById('node-info');
    network.on('click', function(params) {
        if (params.nodes.length > 0) {
            var nodeId = params.nodes[0];
            var node = nodesDS.get(nodeId);
            if (node) {
                var type = (node.device_type || 'host').charAt(0).toUpperCase() + (node.device_type || 'host').slice(1);
                infoPanel.innerHTML = '<div class="info-title">' + escapeHtml(node.label.split('\n')[0]) + '</div>' +
                    '<div class="info-row"><b>Type:</b> ' + type + '</div>' +
                    (node.label.split('\n')[1] ? '<div class="info-row"><b>IP:</b> ' + escapeHtml(node.label.split('\n')[1].replace('IP: ', '')) + '</div>' : '');
                infoPanel.className = 'node-info-panel visible';
            }
        } else {
            infoPanel.className = 'node-info-panel';
        }
    });

    // ── Search ──
    var searchInput = document.getElementById('host-search');
    searchInput.addEventListener('input', function() {
        var q = this.value.toLowerCase().trim();
        if (!q) {
            // Reset all node opacity
            rawNodes.forEach(function(n) {
                nodesDS.update({ id: n.id, opacity: 1 });
            });
            infoPanel.className = 'node-info-panel';
            return;
        }
        var matchId = null;
        rawNodes.forEach(function(n) {
            var label = n.label.toLowerCase();
            if (label.indexOf(q) !== -1) {
                nodesDS.update({ id: n.id, opacity: 1 });
                if (!matchId) matchId = n.id;
            } else {
                nodesDS.update({ id: n.id, opacity: 0.15 });
            }
        });
        if (matchId) {
            network.selectNodes([matchId]);
            network.focus(matchId, { scale: 1.3, animation: { duration: 400, easingFunction: 'easeInOutQuad' } });
        }
    });

    // ── Expose functions globally ──
    var physicsOn = false;

    window.togglePhysics = function() {
        physicsOn = !physicsOn;
        network.setOptions({ physics: { enabled: physicsOn } });
        var btn = document.getElementById('btn-freeze');
        btn.textContent = physicsOn ? '▶ Unfreeze Layout' : '⏸ Freeze Layout';
        btn.className = physicsOn ? 'noc-btn active' : 'noc-btn';
    };

    window.fitMap = function() {
        network.fit({ animation: { duration: 500, easingFunction: 'easeInOutQuad' } });
    };

    window.zoomIn = function() {
        var scale = network.getScale();
        network.moveTo({ scale: scale * 1.3, animation: { duration: 250 } });
    };

    window.zoomOut = function() {
        var scale = network.getScale();
        network.moveTo({ scale: scale * 0.75, animation: { duration: 250 } });
    };

    window.resetSearch = function() {
        searchInput.value = '';
        rawNodes.forEach(function(n) {
            nodesDS.update({ id: n.id, opacity: 1 });
        });
        network.unselectAll();
        infoPanel.className = 'node-info-panel';
        network.fit({ animation: { duration: 400 } });
    };

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

})();
</script>
