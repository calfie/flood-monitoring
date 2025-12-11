<!-- app/Views/dashboard/public.php -->
<?= $this->extend('layouts/main'); ?>

<?php // ========== SIDEBAR (PUBLIK) ========== 
?>
<?= $this->section('sidebar'); ?>
<ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">
    <!-- Brand saja, tanpa menu -->
    <a class="sidebar-brand d-flex align-items-center justify-content-center" href="<?= base_url('/'); ?>">
        <div class="sidebar-brand-text mx-3">ENYODERAMIL</div>
    </a>
</ul>
<?= $this->endSection(); ?>

<?php // ========== TOPBAR (SAMA MODELNYA DENGAN ADMIN) ========== 
?>
<?= $this->section('topbar'); ?>
<nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">

    <div class="d-flex align-items-center justify-content-between w-100">

        <!-- Judul & status di tengah -->
        <div class="flex-grow-1 text-center">
            <span class="navbar-brand mb-0 h5 font-weight-bold dashboard-title d-block">
                Monitoring Banjir
                <small class="text-muted d-block mt-n2">
                    Status:
                    <span id="overallStatus"
                        class="<?= ($overallStatus ?? '-') === 'AMAN'
                                    ? 'text-success'
                                    : (($overallStatus ?? '-') === 'SIAGA'
                                        ? 'text-warning'
                                        : (($overallStatus ?? '-') === 'DARURAT'
                                            ? 'text-danger'
                                            : 'text-muted')); ?>">
                        <?= esc($overallStatus ?? '-'); ?>
                    </span>
                </small>
            </span>
        </div>

        <!-- Tombol ke login admin di kanan -->
        <ul class="navbar-nav">
            <li class="nav-item">
                <a href="<?= base_url('admin/login'); ?>"
                    class="btn btn-primary btn-sm px-3" style="font-weight:600;">
                    Admin Login
                </a>
            </li>
        </ul>

    </div>
</nav>
<?= $this->endSection(); ?>



<?php
// helper kecil buat warna label awal (nanti di-update JS juga)
function labelBadgeClass($label)
{
    switch ($label) {
        case 'AMAN':
            return 'badge badge-success';
        case 'SIAGA':
            return 'badge badge-warning';
        case 'DARURAT':
        case 'WASPADA':
            return 'badge badge-danger';
        default:
            return 'badge badge-secondary';
    }
}
?>

<?= $this->section('content'); ?>

<!-- TIDAK PERLU JUDUL LAGI DI SINI, SUDAH DI TOPBAR -->

<!-- KARTU KONDISI NODE, DISAMAKAN DENGAN ADMIN (PAKE HEADER "WINDOW") -->
<div class="row">
    <?php foreach ($nodes as $nodeId => $node): ?>
        <div class="col-xl-6 col-md-6 mb-4">
            <div class="card card-border-left h-100" data-node-id="<?= esc($nodeId); ?>">

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
                        <strong class="node-status"><?= esc($node['status'] ?? '-'); ?></strong>
                    </p>

                    <p class="mb-1">
                        Ketinggian:
                        <span class="node-distance"><?= esc($node['distance_cm'] ?? '-'); ?></span> cm
                    </p>

                    <p class="mb-1">
                        Arus:
                        <span class="node-flow"><?= esc($node['flow_lpm'] ?? '-'); ?></span> L/min
                    </p>

                    <p class="mb-1">
                        Buzzer:
                        <span class="node-buzzer"><?= esc($node['buzzer'] ?? '-'); ?></span>
                    </p>

                    <p class="mb-1">
                        Label parameter:
                        <?php $label = $node['label_param'] ?? '-'; ?>
                        <span class="node-label <?= labelBadgeClass($label); ?>">
                            <?= esc($label); ?>
                        </span>
                    </p>

                    <small class="text-muted">
                        Update terakhir:
                        <span class="node-updated"><?= esc($node['updated_ms_readable'] ?? '-'); ?></span>
                    </small>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<hr class="my-4">

<!-- KONTROL GRAFIK (SAMA MODELNYA DENGAN ADMIN) -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Grafik Ketinggian &amp; Arus</h5>

    <div class="d-flex gap-2">
        <!-- Pilih Node -->
        <select id="nodeSelect" class="form-control form-control-sm mr-2" style="width: 120px;">
            <?php foreach ($nodes as $nodeId => $node): ?>
                <option value="<?= esc($nodeId); ?>"><?= esc($nodeId); ?></option>
            <?php endforeach; ?>
        </select>

        <!-- Pilih Range Waktu -->
        <select id="rangeSelect" class="form-control form-control-sm" style="width: 150px;">
            <option value="today">Hari ini</option>
            <option value="7d" selected>7 hari terakhir</option>
            <option value="30d">30 hari terakhir</option>
        </select>
    </div>
</div>

<canvas id="chartSensor" height="100"></canvas>

<?= $this->endSection(); ?>


<!-- ===================== SCRIPT (tidak diubah banyak, cuma penyesuaian kecil) ===================== -->
<?= $this->section('scripts'); ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    let sensorChart = null;
    let lastHistoryData = [];

    // Ranking status untuk overall
    const STATUS_RANK = {
        'AMAN': 1,
        'SIAGA': 2,
        'DARURAT': 3
    };

    function classifyStatus(distanceCm) {
        const d = parseFloat(distanceCm);
        if (isNaN(d) || d < 0) return '-';
        if (d >= 100) return 'AMAN';
        if (d >= 60) return 'SIAGA';
        return 'DARURAT';
    }

    function parseTimestamp(raw) {
        if (!raw) return null;

        if (typeof raw === 'string' && raw.length >= 19 && raw[10] === ' ') {
            const iso = raw.replace(' ', 'T') + '+07:00';
            const d = new Date(iso);
            if (!isNaN(d.getTime())) return d;
        }

        if (typeof raw === 'string' && raw.length === 8 && raw[2] === ':' && raw[5] === ':') {
            const today = new Date();
            const datePart = today.toISOString().substring(0, 10);
            const iso = datePart + 'T' + raw + '+07:00';
            const d = new Date(iso);
            if (!isNaN(d.getTime())) return d;
        }

        return null;
    }

    async function refreshDevices() {
        try {
            const res = await fetch("<?= base_url('api/devices'); ?>");
            const devices = await res.json();

            if (!devices || typeof devices !== 'object') return;

            let worstRank = 0;

            Object.keys(devices).forEach(nodeId => {
                const card = document.querySelector(`.card[data-node-id="${nodeId}"]`);
                if (!card) return;

                const data = devices[nodeId];

                const statusEl = card.querySelector('.node-status');
                const distEl = card.querySelector('.node-distance');
                const flowEl = card.querySelector('.node-flow');
                const buzzerEl = card.querySelector('.node-buzzer');
                const labelEl = card.querySelector('.node-label');
                const updatedEl = card.querySelector('.node-updated');

                const distanceVal = data.distance_cm ?? '-';

                if (statusEl && data.status) statusEl.textContent = data.status;
                if (distEl) distEl.textContent = distanceVal;
                if (flowEl) flowEl.textContent = data.flow_lpm ?? '-';
                if (buzzerEl) buzzerEl.textContent = data.buzzer ?? '-';

                const label = classifyStatus(distanceVal);
                if (labelEl) {
                    labelEl.textContent = label;
                    labelEl.className = 'node-label badge ' + (
                        label === 'AMAN' ? 'badge-success' :
                        label === 'SIAGA' ? 'badge-warning' :
                        label === 'DARURAT' ? 'badge-danger' :
                        'badge-secondary'
                    );
                }

                if (updatedEl && data.logged_at) {
                    const d = parseTimestamp(data.logged_at);
                    updatedEl.textContent = d ?
                        d.toLocaleString('id-ID') :
                        data.logged_at;
                }

                if (STATUS_RANK[label] && STATUS_RANK[label] > worstRank) {
                    worstRank = STATUS_RANK[label];
                }
            });

            const overallEl = document.getElementById('overallStatus');
            if (overallEl) {
                let label = '-';
                if (worstRank > 0) {
                    label = Object.keys(STATUS_RANK).find(k => STATUS_RANK[k] === worstRank) || '-';
                }
                overallEl.textContent = label;
                overallEl.classList.remove('text-success', 'text-warning', 'text-danger', 'text-muted');
                if (label === 'AMAN') overallEl.classList.add('text-success');
                else if (label === 'SIAGA') overallEl.classList.add('text-warning');
                else if (label === 'DARURAT') overallEl.classList.add('text-danger');
                else overallEl.classList.add('text-muted');
            }

        } catch (err) {
            console.error("Gagal refresh devices:", err);
        }
    }

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
            console.error("Gagal ambil data history:", err);
            lastHistoryData = [];
        }

        renderChart();
    }

    function renderChart() {
        const labels = lastHistoryData.map(row => {
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
                                maxTicksLimit: 20
                            }
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

    document.addEventListener('DOMContentLoaded', () => {
        const nodeSelect = document.getElementById('nodeSelect');
        const rangeSelect = document.getElementById('rangeSelect');

        if (nodeSelect) nodeSelect.addEventListener('change', loadHistoryFromAPI);
        if (rangeSelect) rangeSelect.addEventListener('change', loadHistoryFromAPI);

        refreshDevices();
        loadHistoryFromAPI();

        setInterval(async () => {
            const currentScroll = window.scrollY;
            await Promise.all([
                refreshDevices(),
                loadHistoryFromAPI()
            ]);
            window.scrollTo(0, currentScroll);
        }, 15000);
    });
</script>
<?= $this->endSection(); ?>