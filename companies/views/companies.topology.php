<?php declare(strict_types = 1);

/**
 * @var CView $this
 * @var array $data
 */

$server_name = $data['server_name'];
$proxies = $data['proxies'];
$hosts = $data['hosts'];
$host_problems = $data['host_problems'];

// Build Vis.js Nodes and Edges arrays
$nodes = [];
$edges = [];

// 1. Zabbix Server Node
$nodes[] = [
    'id' => 'server_root',
    'label' => "🧠 " . $server_name . "\nIP: 127.0.0.1",
    'title' => "<b>Zabbix NOC Central Engine</b><br>Status: Running<br>IP: 127.0.0.1",
    'group' => 'server',
    'value' => 25,
    'shape' => 'image',
    'image' => 'assets/img/icon-server.svg', // will fall back to Vis.js built-ins if missing
    'font' => ['color' => '#ffffff', 'size' => 14, 'bold' => true]
];

// Map of active proxies
$proxy_map = [];
foreach ($proxies as $p) {
    $pid = $p['proxyid'];
    $pname = $p['name'];
    $proxy_node_id = 'proxy_' . $pid;
    $proxy_map[$pid] = $proxy_node_id;

    // Add Proxy Node
    $nodes[] = [
        'id' => $proxy_node_id,
        'label' => "🔁 " . $pname . "\n(Active Proxy)",
        'title' => "<b>Zabbix Active Proxy</b><br>Name: " . htmlspecialchars($pname) . "<br>ID: " . $pid,
        'group' => 'proxy',
        'value' => 18,
        'shape' => 'image',
        'image' => 'assets/img/icon-router.svg',
        'font' => ['color' => '#e2e8f0', 'size' => 12]
    ];

    // Link Proxy to Server
    $edges[] = [
        'from' => 'server_root',
        'to' => $proxy_node_id,
        'length' => 180,
        'width' => 3,
        'color' => '#3b82f6',
        'dashes' => true
    ];
}

// Add Host Nodes and link them
foreach ($hosts as $h) {
    $hid = $h['hostid'];
    $hname = $h['name'];
    $hstatus = (int)$h['status'];
    $proxy_id = $h['proxyid'];
    $description = $h['description'];

    // Skip templates (templates are not active hosts in Zabbix)
    if ($hstatus == 3) {
        continue;
    }

    // Resolve main IP
    $ip = 'No IP';
    if (!empty($h['interfaces'])) {
        foreach ($h['interfaces'] as $iface) {
            if ($iface['main'] == 1) {
                $ip = $iface['ip'];
                break;
            }
        }
    }

    // Resolve host alert severity and tooltip HTML
    $color = '#22c55e'; // Green (OK)
    $border_color = '#16a34a';
    $problems_html = "<b>Status:</b> Healthy ✅";
    $has_issues = false;
    $max_severity = -1;

    if (isset($host_problems[$hid])) {
        $has_issues = true;
        $problems_html = "<b>Active Problems:</b><br>";
        foreach ($host_problems[$hid] as $p) {
            $severity_label = 'Warning';
            $p_color = '#eab308';
            if ($p['severity'] >= 4) {
                $severity_label = 'Critical';
                $p_color = '#ef4444';
            }
            if ($p['severity'] > $max_severity) {
                $max_severity = $p['severity'];
            }
            $problems_html .= "- <span style='color:" . $p_color . ";'>" . htmlspecialchars($p['name']) . "</span><br>";
        }

        // Color node based on max severity
        if ($max_severity >= 4) {
            $color = '#ef4444'; // Red (High/Disaster)
            $border_color = '#b91c1c';
        } else {
            $color = '#f59e0b'; // Orange/Yellow (Average/Warning)
            $border_color = '#d97706';
        }
    }

    // Host tooltip
    $tooltip = "<b>Device:</b> " . htmlspecialchars($hname) . "<br>" .
               "<b>IP Address:</b> " . htmlspecialchars($ip) . "<br>" .
               "<b>Inventory Description:</b> " . htmlspecialchars($description ?: 'N/A') . "<br>" .
               $problems_html;

    // Detect icon shape dynamically based on templates or names
    $icon_shape = 'dot';
    $custom_image = '';
    $name_lower = strtolower($hname);
    
    // Check template types
    $is_firewall = false;
    $is_server = false;
    $is_switch = false;
    
    foreach ($h['parentTemplates'] as $t) {
        $tname = strtolower($t['name']);
        if (strpos($tname, 'firewall') !== false || strpos($tname, 'fortigate') !== false) {
            $is_firewall = true;
        }
        if (strpos($tname, 'windows') !== false || strpos($tname, 'linux') !== false || strpos($tname, 'server') !== false) {
            $is_server = true;
        }
        if (strpos($tname, 'switch') !== false || strpos($tname, 'snmp') !== false || strpos($tname, 'mikrotik') !== false) {
            $is_switch = true;
        }
    }

    if ($is_firewall || strpos($name_lower, 'firewall') !== false || strpos($name_lower, 'fortigate') !== false) {
        $icon_shape = 'image';
        $custom_image = 'firewall';
        $label_prefix = "🔥 ";
    } elseif ($is_server || strpos($name_lower, 'server') !== false || strpos($name_lower, 'win-') !== false || strpos($name_lower, 'dc-') !== false) {
        $icon_shape = 'image';
        $custom_image = 'server';
        $label_prefix = "💻 ";
    } elseif ($is_switch || strpos($name_lower, 'switch') !== false || strpos($name_lower, 'mikrotik') !== false || strpos($name_lower, 'router') !== false) {
        $icon_shape = 'image';
        $custom_image = 'router';
        $label_prefix = "🔌 ";
    } else {
        $icon_shape = 'dot';
        $label_prefix = "🖥️ ";
    }

    // Add Host Node
    $node = [
        'id' => 'host_' . $hid,
        'label' => $label_prefix . $hname . "\nIP: " . $ip,
        'title' => $tooltip,
        'value' => 12,
        'font' => ['color' => '#ffffff', 'size' => 11]
    ];

    if ($icon_shape == 'image') {
        $node['shape'] = 'image';
        // Base64 or standard icons matching alert severity border
        $node['image'] = $custom_image; // processed below
    } else {
        $node['shape'] = 'dot';
        $node['color'] = [
            'background' => $color,
            'border' => $border_color,
            'highlight' => [
                'background' => '#3b82f6',
                'border' => '#2563eb'
            ]
        ];
    }

    // Custom coloring for image nodes (we pass the state dynamically to VisJS)
    $node['borderWidth'] = $has_issues ? 3 : 1;
    $node['color'] = [
        'border' => $border_color,
        'background' => '#1e293b'
    ];
    $node['status'] = $has_issues ? ($max_severity >= 4 ? 'critical' : 'warning') : 'ok';
    $node['device_type'] = $custom_image ?: 'generic';

    $nodes[] = $node;

    // Link Host to parent Proxy or Server Root
    $parent_id = 'server_root';
    if (!empty($proxy_id) && isset($proxy_map[$proxy_id])) {
        $parent_id = $proxy_map[$proxy_id];
    }

    $edges[] = [
        'from' => $parent_id,
        'to' => 'host_' . $hid,
        'color' => $has_issues ? ($max_severity >= 4 ? '#ef4444' : '#f59e0b') : '#22c55e',
        'width' => $has_issues ? 3 : 1.5,
        'length' => 140
    ];
}
?>

<div class="header-title">
    <h1 style="font-size: 24px; font-weight: 700; color: #f8fafc; margin-bottom: 5px;">🌐 Dynamic NOC Network Topology</h1>
    <div style="color: #94a3b8; font-size: 14px;">Real-time auto-generated network mapping with zero manual configuration. Zoom, pan, and click devices to view details.</div>
</div>

<div class="topology-container" style="display: flex; gap: 15px; margin-top: 15px; height: calc(100vh - 160px); min-height: 550px;">
    
    <!-- Controls & Info Box -->
    <div class="control-panel" style="width: 320px; background: #1e293b; border-radius: 8px; border: 1px solid #334155; padding: 20px; display: flex; flex-direction: column; justify-content: space-between; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);">
        <div>
            <h2 style="font-size: 16px; font-weight: 600; color: #f1f5f9; margin-bottom: 15px; border-bottom: 1px solid #334155; padding-bottom: 8px;">🛠️ NOC Control Center</h2>
            
            <!-- Host Search -->
            <div style="margin-bottom: 20px;">
                <label style="color: #94a3b8; font-size: 12px; font-weight: 500; display: block; margin-bottom: 6px;">Search Device or IP</label>
                <input type="text" id="host-search" placeholder="Type name or IP..." style="width: 100%; padding: 8px 12px; background: #0f172a; border: 1px solid #475569; border-radius: 6px; color: #f1f5f9; font-size: 13px;" onkeyup="searchHost()">
            </div>

            <!-- Legends -->
            <div style="margin-bottom: 25px;">
                <label style="color: #94a3b8; font-size: 12px; font-weight: 500; display: block; margin-bottom: 8px;">Legend & Status</label>
                <div style="display: flex; flex-direction: column; gap: 8px; font-size: 13px;">
                    <div style="display: flex; align-items: center; gap: 10px; color: #e2e8f0;">
                        <span style="display: inline-block; width: 12px; height: 12px; border-radius: 50%; background: #22c55e; border: 1px solid #16a34a;"></span>
                        <span>Normal (Healthy)</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px; color: #e2e8f0;">
                        <span style="display: inline-block; width: 12px; height: 12px; border-radius: 50%; background: #f59e0b; border: 1px solid #d97706;"></span>
                        <span>Warning Alert</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px; color: #e2e8f0;">
                        <span style="display: inline-block; width: 12px; height: 12px; border-radius: 50%; background: #ef4444; border: 1px solid #b91c1c;"></span>
                        <span>Critical / Down Alert</span>
                    </div>
                </div>
            </div>

            <!-- Dynamic Auto-Layout -->
            <div style="margin-bottom: 20px;">
                <label style="color: #94a3b8; font-size: 12px; font-weight: 500; display: block; margin-bottom: 6px;">Physics Engine Configuration</label>
                <button onclick="togglePhysics()" class="btn-alt" style="width: 100%; padding: 8px 12px; background: #3b4252; border: 1px solid #4c566a; border-radius: 6px; color: #e5e9f0; font-size: 13px; cursor: pointer; transition: all 0.2s;" id="physics-toggle">Disable Physics (Freeze Nodes)</button>
            </div>
        </div>

        <div>
            <!-- Stats -->
            <div style="background: #0f172a; padding: 15px; border-radius: 6px; border: 1px solid #334155;">
                <div style="color: #64748b; font-size: 11px; font-weight: 600; text-transform: uppercase; margin-bottom: 5px;">Network Summary</div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; color: #f1f5f9; font-size: 13px;">
                    <div>Total Devices: <b><?php echo count($hosts); ?></b></div>
                    <div>Active Proxies: <b><?php echo count($proxies); ?></b></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Network Canvas Container -->
    <div style="flex-grow: 1; background: #0f172a; border-radius: 8px; border: 1px solid #334155; position: relative; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); overflow: hidden;">
        
        <!-- Zoom Navigation Overlay -->
        <div class="zoom-controls" style="position: absolute; top: 15px; right: 15px; display: flex; flex-direction: column; gap: 5px; z-index: 10;">
            <button onclick="zoomMap(1.2)" class="zoom-btn" title="Zoom In">+</button>
            <button onclick="zoomMap(0.8)" class="zoom-btn" title="Zoom Out">-</button>
            <button onclick="fitMap()" class="zoom-btn" title="Fit to Screen">⛶</button>
        </div>

        <!-- The Canvas -->
        <div id="topology-canvas" style="width: 100%; height: 600px; min-height: 550px;"></div>
    </div>
</div>

<!-- Load Vis-Network script locally -->
<script type="text/javascript" src="modules/companies/assets/vis-network.min.js"></script>

<style>
/* CSS Styling */
.zoom-btn {
    width: 36px;
    height: 36px;
    background: #1e293b;
    border: 1px solid #334155;
    border-radius: 6px;
    color: #f1f5f9;
    font-size: 18px;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    box-shadow: 0 2px 4px rgb(0 0 0 / 0.1);
}
.zoom-btn:hover {
    background: #334155;
    color: #3b82f6;
}
.btn-alt:hover {
    background: #434c5e !important;
}

/* Custom tooltips matching dark style */
div.vis-network div.vis-tooltip {
    background-color: #1e293b !important;
    border: 1px solid #475569 !important;
    color: #f1f5f9 !important;
    border-radius: 6px !important;
    padding: 10px !important;
    font-family: inherit !important;
    font-size: 12px !important;
    box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1) !important;
}
</style>

<script type="text/javascript">
// Process dynamic PHP data into JS Arrays
const rawNodes = <?php echo json_encode($nodes); ?>;
const rawEdges = <?php echo json_encode($edges); ?>;

// Set up node icons based on device types using clean SVG strings
const svgIcons = {
    server: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="64" height="64"><rect x="2" y="2" width="20" height="6" rx="1" fill="#475569" stroke="#94a3b8" stroke-width="1.5"/><circle cx="5" cy="5" r="1.5" fill="#22c55e"/><line x1="8" y1="5" x2="18" y2="5" stroke="#94a3b8" stroke-width="1.5" stroke-linecap="round"/><rect x="2" y="9" width="20" height="6" rx="1" fill="#475569" stroke="#94a3b8" stroke-width="1.5"/><circle cx="5" cy="12" r="1.5" fill="#22c55e"/><line x1="8" y1="12" x2="18" y2="12" stroke="#94a3b8" stroke-width="1.5" stroke-linecap="round"/><rect x="2" y="16" width="20" height="6" rx="1" fill="#475569" stroke="#94a3b8" stroke-width="1.5"/><circle cx="5" cy="19" r="1.5" fill="#ef4444"/><line x1="8" y1="19" x2="18" y2="19" stroke="#94a3b8" stroke-width="1.5" stroke-linecap="round"/></svg>`,
    router: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="64" height="64"><circle cx="12" cy="12" r="9" fill="#1e293b" stroke="#3b82f6" stroke-width="2"/><line x1="5" y1="12" x2="19" y2="12" stroke="#3b82f6" stroke-width="2"/><line x1="12" y1="5" x2="12" y2="19" stroke="#3b82f6" stroke-width="2"/><polygon points="9,3 12,0 15,3" fill="#3b82f6"/><polygon points="9,21 12,24 15,21" fill="#3b82f6"/><polygon points="3,9 0,12 3,15" fill="#3b82f6"/><polygon points="21,9 24,12 21,15" fill="#3b82f6"/></svg>`,
    firewall: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="64" height="64"><rect x="2" y="2" width="20" height="20" rx="2" fill="#ef4444" stroke="#b91c1c" stroke-width="2"/><path d="M2,6 H22 M2,10 H22 M2,14 H22 M2,18 H22" stroke="#b91c1c" stroke-width="1.5"/><path d="M6,2 V6 M12,2 V6 M18,2 V6 M4,6 V10 M9,6 V10 M14,6 V10 M19,6 V10 M7,10 V14 M13,10 V14 M19,10 V14 M4,14 V18 M10,14 V18 M16,14 V18 M7,18 V22 M13,18 V22 M19,18 V22" stroke="#b91c1c" stroke-width="1.5"/></svg>`
};

// Convert device shape types to SVG URL data strings
const nodes = rawNodes.map(node => {
    if (node.shape === 'image') {
        const type = node.image; // 'server', 'router', 'firewall'
        const borderCol = node.color.border;
        let svg = svgIcons[type] || svgIcons['server'];
        
        // Inject alert status glow colors
        if (node.status === 'critical') {
            svg = svg.replace(/stroke="#[a-zA-Z0-9]+"/g, 'stroke="#ef4444"').replace(/fill="#[a-zA-Z0-9]+"/g, 'fill="#fef2f2"');
        } else if (node.status === 'warning') {
            svg = svg.replace(/stroke="#[a-zA-Z0-9]+"/g, 'stroke="#f59e0b"').replace(/fill="#[a-zA-Z0-9]+"/g, 'fill="#fffbeb"');
        }
        
        const url = "data:image/svg+xml;charset=utf-8," + encodeURIComponent(svg);
        node.image = url;
    }
    return node;
});

// Configure Vis.js Network
const container = document.getElementById('topology-canvas');
const data = {
    nodes: new vis.DataSet(nodes),
    edges: new vis.DataSet(rawEdges)
};

const options = {
    nodes: {
        scaling: {
            min: 10,
            max: 30
        },
        font: {
            size: 12,
            face: 'Inter, system-ui, sans-serif'
        }
    },
    edges: {
        width: 1.5,
        smooth: {
            type: 'continuous',
            forceDirection: 'none',
            roundness: 0.5
        },
        arrows: {
            to: { enabled: false }
        }
    },
    physics: {
        enabled: true,
        solver: 'forceAtlas2Based',
        forceAtlas2Based: {
            gravitationalConstant: -50,
            centralGravity: 0.010,
            springLength: 120,
            springConstant: 0.08,
            damping: 0.4
        },
        stabilization: {
            enabled: true,
            iterations: 1000,
            updateInterval: 50,
            onlyDynamicEdges: false,
            fit: true
        }
    },
    interaction: {
        hover: true,
        zoomView: true,
        dragView: true,
        selectable: true,
        tooltipDelay: 100
    }
};

// Initialize network
let network = new vis.Network(container, data, options);

// Force fit and redraw to prevent size computation bugs
setTimeout(() => {
    network.redraw();
    network.fit();
}, 300);

// Zoom Actions
function zoomMap(scaleFactor) {
    network.zoom(scaleFactor, { animation: { duration: 300 } });
}

function fitMap() {
    network.fit({ animation: { duration: 500 } });
}

// Toggle physics (allow freezing node coordinates)
let physicsEnabled = true;
function togglePhysics() {
    physicsEnabled = !physicsEnabled;
    network.setOptions({ physics: { enabled: physicsEnabled } });
    
    const btn = document.getElementById('physics-toggle');
    if (physicsEnabled) {
        btn.innerHTML = "Disable Physics (Freeze Nodes)";
        btn.style.background = "#3b4252";
    } else {
        btn.innerHTML = "Enable Physics (Float Nodes)";
        btn.style.background = "#22c55e";
    }
}

// Dynamic Search and Zoom
function searchHost() {
    const input = document.getElementById('host-search').value.toLowerCase();
    if (!input) return;

    const matchedNode = nodes.find(node => {
        const label = node.label.toLowerCase();
        return label.includes(input);
    });

    if (matchedNode) {
        network.selectNodes([matchedNode.id]);
        network.focus(matchedNode.id, {
            scale: 1.5,
            animation: { duration: 500 }
        });
    }
}
</script>
