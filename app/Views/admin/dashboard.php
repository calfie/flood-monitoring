<!-- app/Views/admin/dashboard.php -->
<?= $this->extend('layouts/main'); ?>

<?php // ========== SIDEBAR ADMIN ========== 
?>
<?= $this->section('sidebar'); ?>
<ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">

    <!-- Brand -->
    <a class="sidebar-brand d-flex align-items-center justify-content-center" href="<?= base_url('/admin'); ?>">
        <div class="sidebar-brand-text mx-3">ENYODERAMIL</div>
    </a>

    <hr class="sidebar-divider my-0">

    <!-- Menu -->
    <li class="nav-item active">
        <a class="nav-link" href="<?= base_url('admin'); ?>">
            <span>Dashboard</span>
        </a>
    </li>

    <li class="nav-item">
        <a class="nav-link" href="<?= base_url('admin/log-sensor'); ?>">
            <span>Log Data Sensor</span>
        </a>
    </li>

    <li class="nav-item">
        <a class="nav-link" href="<?= base_url('admin/log-qos'); ?>">
            <span>Log QoS Jaringan</span>
        </a>
    </li>

    <li class="nav-item">
        <a class="nav-link" href="<?= base_url('admin/log-signal'); ?>">
            <span>Log RSSI & SNR</span>
        </a>
    </li>


    <li class="nav-item">
        <a class="nav-link" href="<?= base_url('admin/users'); ?>">
            <span>Kelola Admin</span>
        </a>
    </li>

</ul>
<?= $this->endSection(); ?>

<?php // ========== TOPBAR ========== 
?>
<?= $this->section('topbar'); ?>
<nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">

    <div class="w-100 text-center">
        <span class="navbar-brand mb-0 h5 font-weight-bold dashboard-title d-block">
            Monitoring Banjir

            <small class="text-muted d-block mt-n2">
                Status:
                <?php
                $overall = $overallStatus ?? '-';
                $color   = 'text-secondary';
                if ($overall === 'DARURAT') {
                    $color = 'text-danger';
                } elseif ($overall === 'SIAGA') {
                    $color = 'text-warning';
                } elseif ($overall === 'AMAN') {
                    $color = 'text-success';
                }
                ?>
                <span class="<?= $color; ?> font-weight-bold">
                    <?= esc($overall); ?>
            </small>
    </div>

    <ul class="navbar-nav ml-auto">
        <li class="nav-item mr-3 d-none d-lg-block align-self-center">
            <span class="text-muted small">
                <?= esc($username ?? 'admin'); ?>
            </span>
        </li>
        <li class="nav-item">
            <a href="<?= base_url('admin/logout'); ?>" class="btn btn-sm btn-outline-danger">
                Logout
            </a>
        </li>
    </ul>
</nav>
<?= $this->endSection(); ?>

<?php // ========== KONTEN ========== 
?>
<?= $this->section('content'); ?>

<?php if (empty($nodes)): ?>
    <div class="alert alert-warning">
        Data perangkat belum tersedia.
    </div>
<?php else: ?>
    <div class="row">
        <?php foreach ($nodes as $nodeId => $node): ?>
            <div class="col-xl-6 col-md-6 mb-4">
                <div class="card card-border-left h-100"
                    data-node-id="<?= esc($nodeId); ?>">

                    <!-- HEADER ALA WINDOW -->
                    <div class="card-header card-window-header">
                        <div class="window-dots">
                            <span class="dot dot-red"></span>
                            <span class="dot dot-yellow"></span>
                            <span class="dot dot-green"></span>
                        </div>
                        <div class="window-title">
                            <?= esc($nodeId); ?>
                        </div>
                    </div>

                    <!-- ISI CARD -->
                    <div class="card-body">

                        <p class="mb-1">
                            Status perangkat:
                            <strong class="node-status">
                                <?= esc($node['status'] ?? '-'); ?>
                            </strong>
                        </p>

                        <p class="mb-1">
                            Ketinggian:
                            <span class="node-distance">
                                <?= esc($node['distance_cm'] ?? '-'); ?>
                            </span> cm
                        </p>

                        <p class="mb-1">
                            Arus:
                            <span class="node-flow">
                                <?= esc($node['flow_lpm'] ?? '-'); ?>
                            </span> L/min
                        </p>

                        <p class="mb-1">
                            Buzzer:
                            <span class="node-buzzer">
                                <?= esc($node['buzzer'] ?? '-'); ?>
                            </span>
                        </p>

                        <p class="mb-1">
                            Label parameter:
                            <?php
                            $label = $node['label_param'] ?? '-';
                            $badgeClass = 'badge-secondary';
                            if ($label === 'AMAN') {
                                $badgeClass = 'badge-success';
                            } elseif ($label === 'SIAGA') {
                                $badgeClass = 'badge-warning';
                            } elseif ($label === 'DARURAT' || $label === 'WASPADA') {
                                $badgeClass = 'badge-danger';
                            }
                            ?>
                            <span class="badge <?= $badgeClass; ?> node-label">
                                <?= esc($label); ?>
                            </span>
                        </p>

                        <small class="text-muted">
                            Update terakhir:
                            <span class="node-updated">
                                <?= esc($node['updated_ms_readable'] ?? '-'); ?>
                            </span>
                        </small>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<hr class="my-4">


<!-- GRAFIK -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Grafik Ketinggian &amp; Arus</h5>

    <div class="d-flex">
        <select id="nodeSelect" class="form-control form-control-sm mr-2" style="width: 120px;">
            <?php foreach ($nodes as $nodeId => $node): ?>
                <option value="<?= esc($nodeId); ?>">
                    <?= esc($nodeId); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select id="rangeSelect" class="form-control form-control-sm" style="width: 150px;">
            <option value="today">Hari ini</option>
            <option value="7d">7 hari terakhir</option>
            <option value="30d" selected>30 hari terakhir</option>
        </select>
    </div>
</div>

<canvas id="chartSensor" height="110"></canvas>

<?= $this->endSection(); ?>

<?php // ========== SCRIPT ========== 
?>
<?= $this->section('scripts'); ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom@1.2.1/dist/chartjs-plugin-zoom.min.js"></script>

<script>
    let sensorChart = null;
    let lastHistoryData = [];

    // ==== Helper: parse timestamp pakai logged_at (string) ====
    function parseTimestamp(raw) {
        if (!raw) return null;

        // contoh raw: "2025-11-23 00:49:38"
        if (typeof raw === 'string' && raw.length >= 19 && raw[10] === ' ') {
            const iso = raw.replace(' ', 'T') + '+07:00'; // jadi ISO 8601
            const d = new Date(iso);
            if (!isNaN(d.getTime())) return d;
        }

        // fallback: kalau cuma jam "HH:MM:SS"
        if (typeof raw === 'string' && raw.length === 8 && raw[2] === ':' && raw[5] === ':') {
            const today = new Date();
            const iso = today.toISOString().substring(0, 10) + 'T' + raw + '+07:00';
            const d = new Date(iso);
            if (!isNaN(d.getTime())) return d;
        }

        return null;
    }

    // ====== REFRESH CARD PERANGKAT ======
    async function refreshDevices() {
        try {
            const res = await fetch("<?= base_url('api/devices'); ?>");
            const devices = await res.json();

            if (!devices || typeof devices !== 'object') return;

            Object.keys(devices).forEach(nodeId => {
                const card = document.querySelector('.card[data-node-id="' + nodeId + '"]');
                if (!card) return;

                const data = devices[nodeId];

                const statusEl = card.querySelector('.node-status');
                const distEl = card.querySelector('.node-distance');
                const flowEl = card.querySelector('.node-flow');
                const buzzerEl = card.querySelector('.node-buzzer');
                const labelEl = card.querySelector('.node-label');
                const updatedEl = card.querySelector('.node-updated');

                if (statusEl && data.status) statusEl.textContent = data.status;
                if (distEl) distEl.textContent = data.distance_cm ?? '-';
                if (flowEl) flowEl.textContent = data.flow_lpm ?? '-';
                if (buzzerEl) buzzerEl.textContent = data.buzzer ?? '-';
                if (labelEl && data.label_param) labelEl.textContent = data.label_param;

                if (updatedEl && data.logged_at) {
                    const d = parseTimestamp(data.logged_at);
                    updatedEl.textContent = d ? d.toLocaleString('id-ID') : data.logged_at;
                }
            });
        } catch (err) {
            console.error('Gagal refresh devices:', err);
        }
    }

    // ====== AMBIL HISTORY UNTUK GRAFIK ======
    async function loadHistoryFromAPI() {
        const node = document.getElementById('nodeSelect').value;
        const range = document.getElementById('rangeSelect').value;
        const url = "<?= base_url('api/history'); ?>/" +
            encodeURIComponent(node) + "?range=" +
            encodeURIComponent(range);

        try {
            const res = await fetch(url);
            const data = await res.json();
            lastHistoryData = Array.isArray(data) ? data : [];
        } catch (err) {
            console.error('Gagal ambil data history:', err);
            lastHistoryData = [];
        }

        renderChart();
    }

    // ====== RENDER / UPDATE GRAFIK ======
    function renderChart() {
        const labels = lastHistoryData.map(row => {
            // pakai logged_at sebagai prioritas
            const rawTs = row.logged_at ?? row.time_node ?? null;
            const d = parseTimestamp(rawTs);

            if (d) {
                const datePart = d.toLocaleDateString('id-ID');
                const timePart = d.toLocaleTimeString('id-ID', {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
                return datePart + ' ' + timePart;
            }

            return '';
        });

        const heightValues = lastHistoryData.map(row => Number(row.distance_cm ?? 0));
        const flowValues = lastHistoryData.map(row => Number(row.flow_lpm ?? 0));

        const ctx = document.getElementById('chartSensor').getContext('2d');

        if (!sensorChart) {
            sensorChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                            label: 'Ketinggian Air (cm)',
                            data: heightValues,
                            borderWidth: 2,
                            fill: false,
                            tension: 0.2,
                            yAxisID: 'yHeight'
                        },
                        {
                            label: 'Arus Air (L/min)',
                            data: flowValues,
                            borderWidth: 2,
                            fill: false,
                            tension: 0.2,
                            yAxisID: 'yFlow'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    scales: {
                        yHeight: {
                            type: 'linear',
                            position: 'left',
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Ketinggian (cm)'
                            }
                        },
                        yFlow: {
                            type: 'linear',
                            position: 'right',
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Arus (L/min)'
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        },
                        x: {
                            ticks: {
                                maxRotation: 45,
                                minRotation: 0,
                                autoSkip: true,
                                maxTicksLimit: 15
                            }
                        }
                    },
                    plugins: {
                        zoom: {
                            zoom: {
                                wheel: {
                                    enabled: true
                                },
                                pinch: {
                                    enabled: true
                                },
                                mode: 'x'
                            },
                            pan: {
                                enabled: true,
                                mode: 'x'
                            }
                        },
                        legend: {
                            display: true
                        }
                    }
                }
            });
        } else {
            sensorChart.data.labels = labels;
            sensorChart.data.datasets[0].data = heightValues;
            sensorChart.data.datasets[1].data = flowValues;
            sensorChart.update('none');
        }
    }


    // ====== INIT ======
    document.addEventListener('DOMContentLoaded', () => {
        const nodeSelect = document.getElementById('nodeSelect');
        const rangeSelect = document.getElementById('rangeSelect');

        if (nodeSelect) nodeSelect.addEventListener('change', loadHistoryFromAPI);
        if (rangeSelect) rangeSelect.addEventListener('change', loadHistoryFromAPI);


        refreshDevices();
        loadHistoryFromAPI();

        setInterval(() => {
            refreshDevices();
            loadHistoryFromAPI();
        }, 15000);
    });
</script>
<?= $this->endSection(); ?>