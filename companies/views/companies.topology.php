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

// 1. Zabbix Server Node (Root)
$nodes[] = [
    'id' => 'server_root',
    'label' => "🧠 " . $server_name . "\nIP: 127.0.0.1",
    'title' => "<b>Zabbix NOC Central Engine</b><br>Status: Running<br>IP: 127.0.0.1",
    'shape' => 'box',
    'margin' => 15,
    'color' => [
        'background' => '#1e3a8a', // Deep Blue
        'border' => '#3b82f6',
        'highlight' => [
            'background' => '#2563eb',
            'border' => '#60a5fa'
        ]
    ],
    'borderWidth' => 3,
    'font' => ['color' => '#ffffff', 'size' => 15, 'bold' => true, 'face' => 'Inter, system-ui, sans-serif']
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
        'shape' => 'box',
        'margin' => 12,
        'color' => [
            'background' => '#334155', // Slate Grey
            'border' => '#64748b',
            'highlight' => [
                'background' => '#475569',
                'border' => '#94a3b8'
            ]
        ],
        'borderWidth' => 2,
        'borderWidthSelected' => 3,
        'font' => ['color' => '#f1f5f9', 'size' => 13, 'bold' => true, 'face' => 'Inter, system-ui, sans-serif']
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

    // Skip templates
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
    $bg_color = '#0f172a'; // Dark Slate
    $border_color = '#10b981'; // Emerald Green (OK)
    $text_color = '#f8fafc';
    $problems_html = "<b>Status:</b> Healthy ✅";
    $max_severity = -1;

    if (isset($host_problems[$hid])) {
        $problems_html = "<b>Active Problems:</b><br>";
        foreach ($host_problems[$hid] as $p) {
            $severity_label = 'Warning';
            $p_color = '#f59e0b';
            if ($p['severity'] >= 4) {
                $severity_label = 'Critical';
                $p_color = '#f43f5e';
            }
            if ($p['severity'] > $max_severity) {
                $max_severity = $p['severity'];
            }
            $problems_html .= "- <span style='color:" . $p_color . ";'>" . htmlspecialchars($p['name']) . "</span><br>";
        }

        // Color node based on max severity
        if ($max_severity >= 4) {
            $border_color = '#f43f5e'; // Rose Red (Critical)
            $bg_color = '#4c0519'; // Deep Rose bg
        } else {
            $border_color = '#f59e0b'; // Amber Orange (Warning)
            $bg_color = '#451a03'; // Deep Amber bg
        }
    }

    // Host tooltip
    $tooltip = "<b>Device:</b> " . htmlspecialchars($hname) . "<br>" .
               "<b>IP Address:</b> " . htmlspecialchars($ip) . "<br>" .
               "<b>Inventory Description:</b> " . htmlspecialchars($description ?: 'N/A') . "<br>" .
               $problems_html;

    // Detect icon shape dynamically based on templates or names
    $label_prefix = "🖥️ ";
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
        $label_prefix = "🔥 ";
    } elseif ($is_server || strpos($name_lower, 'server') !== false || strpos($name_lower, 'win-') !== false || strpos($name_lower, 'dc-') !== false) {
        $label_prefix = "💻 ";
    } elseif ($is_switch || strpos($name_lower, 'switch') !== false || strpos($name_lower, 'mikrotik') !== false || strpos($name_lower, 'router') !== false) {
        $label_prefix = "🔌 ";
    }

    // Add Host Node
    $nodes[] = [
        'id' => 'host_' . $hid,
        'label' => $label_prefix . $hname . "\nIP: " . $ip,
        'title' => $tooltip,
        'shape' => 'box',
        'margin' => 10,
        'color' => [
            'background' => $bg_color,
            'border' => $border_color,
            'highlight' => [
                'background' => '#1e293b',
                'border' => '#3b82f6'
            ]
        ],
        'borderWidth' => 2,
        'borderWidthSelected' => 3,
        'font' => ['color' => $text_color, 'size' => 12, 'face' => 'Inter, system-ui, sans-serif']
    ];

    // Link Host to parent Proxy or Server Root
    $parent_id = 'server_root';
    if (!empty($proxy_id) && isset($proxy_map[$proxy_id])) {
        $parent_id = $proxy_map[$proxy_id];
    }

    $edges[] = [
        'from' => $parent_id,
        'to' => 'host_' . $hid,
        'color' => $max_severity >= 0 ? ($max_severity >= 4 ? '#f43f5e' : '#f59e0b') : '#10b981',
        'width' => $max_severity >= 0 ? 3 : 1.5,
        'length' => 140
    ];
}
?>

<div class="header-title" style="padding: 10px 0;">
    <h1 style="font-size: 24px; font-weight: 700; color: #f8fafc; margin-bottom: 5px;">🌐 Dynamic NOC Network Topology</h1>
    <div style="color: #94a3b8; font-size: 14px;">Real-time auto-generated network mapping with zero manual configuration. Zoom, pan, and click devices to view details.</div>
</div>

<div class="topology-container" style="display: flex; gap: 15px; margin-top: 10px; height: calc(100vh - 160px); min-height: 550px;">
    
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
                        <span style="display: inline-block; width: 12px; height: 12px; border-radius: 3px; background: #0f172a; border: 2px solid #10b981;"></span>
                        <span>Normal (Healthy)</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px; color: #e2e8f0;">
                        <span style="display: inline-block; width: 12px; height: 12px; border-radius: 3px; background: #451a03; border: 2px solid #f59e0b;"></span>
                        <span>Warning Alert</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px; color: #e2e8f0;">
                        <span style="display: inline-block; width: 12px; height: 12px; border-radius: 3px; background: #4c0519; border: 2px solid #f43f5e;"></span>
                        <span>Critical / Down Alert</span>
                    </div>
                </div>
            </div>

            <!-- Dynamic Auto-Layout -->
            <div style="margin-bottom: 20px;">
                <label style="color: #94a3b8; font-size: 12px; font-weight: 500; display: block; margin-bottom: 6px;">Physics Engine Configuration</label>
                <button onclick="togglePhysics()" class="btn-alt" style="width: 100%; padding: 8px 12px; background: #334155; border: 1px solid #475569; border-radius: 6px; color: #f1f5f9; font-size: 13px; cursor: pointer; transition: all 0.2s;" id="physics-toggle">Disable Physics (Freeze Nodes)</button>
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

<!-- Load Vis-Network script locally using root-relative path to prevent resolution bugs -->
<script type="text/javascript" src="/zabbix/modules/companies/assets/vis-network.min.js"></script>

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
    background: #475569 !important;
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
// Safety check if vis library is loaded
if (typeof vis === 'undefined') {
    document.getElementById('topology-canvas').innerHTML = `
        <div style="color: #ef4444; padding: 50px 20px; text-align: center; font-size: 16px; font-weight: 600;">
            ❌ Vis.js Topology Engine failed to load!
            <div style="font-size: 13px; color: #94a3b8; font-weight: normal; margin-top: 10px; line-height: 1.6;">
                Browser was blocked from loading the network graphics script by security policies.<br>
                Please verify absolute path <code>/zabbix/modules/companies/assets/vis-network.min.js</code> is accessible.
            </div>
        </div>
    `;
} else {
    // Process dynamic PHP data into JS Arrays
    const nodes = <?php echo json_encode($nodes); ?>;
    const edges = <?php echo json_encode($edges); ?>;

    // Configure Vis.js Network
    const container = document.getElementById('topology-canvas');
    const data = {
        nodes: new vis.DataSet(nodes),
        edges: new vis.DataSet(edges)
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
                gravitationalConstant: -70,
                centralGravity: 0.015,
                springLength: 140,
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
    }, 400);
}

// Zoom Actions
function zoomMap(scaleFactor) {
    if (typeof network !== 'undefined') {
        network.zoom(scaleFactor, { animation: { duration: 300 } });
    }
}

function fitMap() {
    if (typeof network !== 'undefined') {
        network.fit({ animation: { duration: 500 } });
    }
}

// Toggle physics (allow freezing node coordinates)
let physicsEnabled = true;
function togglePhysics() {
    if (typeof network !== 'undefined') {
        physicsEnabled = !physicsEnabled;
        network.setOptions({ physics: { enabled: physicsEnabled } });
        
        const btn = document.getElementById('physics-toggle');
        if (physicsEnabled) {
            btn.innerHTML = "Disable Physics (Freeze Nodes)";
            btn.style.background = "#334155";
        } else {
            btn.innerHTML = "Enable Physics (Float Nodes)";
            btn.style.background = "#10b981";
        }
    }
}

// Dynamic Search and Zoom
function searchHost() {
    if (typeof network !== 'undefined') {
        const input = document.getElementById('host-search').value.toLowerCase();
        if (!input) return;

        const rawNodes = <?php echo json_encode($nodes); ?>;
        const matchedNode = rawNodes.find(node => {
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
}
</script>
