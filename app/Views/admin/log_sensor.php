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
    <li class="nav-item">
        <a class="nav-link" href="<?= base_url('admin'); ?>">
            <span>Dashboard</span>
        </a>
    </li>

    <li class="nav-item active">
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
            Log Data Sensor
            <small class="text-muted d-block mt-n2">

            </small>
        </span>
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

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Log Data Sensor</h6>

        <div class="d-flex align-items-center">
            <button type="button"
                id="btnDeleteSelectedSensor"
                class="btn btn-sm btn-danger mr-2">
                Hapus terpilih
            </button>

            <select id="nodeFilter" class="form-control form-control-sm mr-2" style="width: 120px;">
                <?php if (!empty($nodeIds)): ?>
                    <?php foreach ($nodeIds as $id): ?>
                        <option value="<?= esc($id); ?>"><?= esc($id); ?></option>
                    <?php endforeach; ?>
                <?php else: ?>
                    <option value="">(Tidak ada node)</option>
                <?php endif; ?>
            </select>

            <select id="rangeFilter" class="form-control form-control-sm" style="width: 150px;">
                <option value="today">Hari ini</option>
                <option value="7d">7 hari terakhir</option>
                <option value="30d" selected>30 hari terakhir</option>
            </select>
        </div>
    </div>

    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-sm mb-0">
                <thead class="thead-light">
                    <tr>
                        <th style="width: 40px; text-align: center;">
                            <input type="checkbox" id="checkAllSensor">
                        </th>
                        <th style="width: 50px;">No</th>
                        <th style="width: 120px;">Tanggal</th>
                        <th style="width: 90px;">Jam</th>
                        <th style="width: 80px;">Node</th>
                        <th style="width: 120px;">Ketinggian (cm)</th>
                        <th style="width: 120px;">Arus (L/min)</th>
                        <th style="width: 90px;">Buzzer</th>
                        <th style="width: 120px;">Label parameter</th>
                        <th style="width: 90px;">Aksi</th>
                    </tr>
                </thead>
                <tbody id="logSensorBody">
                    <tr>
                        <td colspan="11" class="text-center text-muted">
                            Belum ada data log.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?= $this->endSection(); ?>

<?php // ========== SCRIPT ========== 
?>
<?= $this->section('scripts'); ?>
<script>
    async function loadLogSensor() {
        const node = document.getElementById('nodeFilter').value;
        const range = document.getElementById('rangeFilter').value;
        const tbody = document.getElementById('logSensorBody');

        if (!node) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="11" class="text-center text-muted">
                        Tidak ada node yang dipilih.
                    </td>
                </tr>
            `;
            return;
        }

        const url = "<?= base_url('admin/load-sensor'); ?>" +
            "?node=" + encodeURIComponent(node) +
            "&range=" + encodeURIComponent(range);

        try {
            const res = await fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const data = await res.json();

            if (!Array.isArray(data) || data.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="11" class="text-center text-muted">
                            Belum ada data log untuk rentang waktu ini.
                        </td>
                    </tr>
                `;
                return;
            }

            let html = '';
            data.forEach((row, index) => {
                const no = index + 1;
                const date = row.date ?? '-';
                const time = row.time ?? '-';
                const nodeId = row.node ?? '-';
                const dist = row.distance ?? '-';
                const flow = row.flow ?? '-';
                const buzzer = (row.buzzer ?? '-').toUpperCase();
                const status = row.status ?? '-';
                const label = row.label ?? '-';
                const key = row.key ?? '';

                let statusBadgeClass = 'badge-secondary';
                if (status === 'online') statusBadgeClass = 'badge-success';
                if (status === 'offline') statusBadgeClass = 'badge-secondary';

                let labelBadgeClass = 'badge-secondary';
                if (label === 'AMAN') labelBadgeClass = 'badge-success';
                else if (label === 'SIAGA') labelBadgeClass = 'badge-warning';
                else if (label === 'DARURAT') labelBadgeClass = 'badge-danger';

                html += `
                    <tr>
                        <td class="text-center">
                            <input type="checkbox"
                                   class="row-check-sensor"
                                   data-key="${key}">
                        </td>
                        <td>${no}</td>
                        <td>${date}</td>
                        <td>${time}</td>
                        <td>${nodeId}</td>
                        <td>${dist}</td>
                        <td>${flow}</td>
                        <td>${buzzer}</td>
                        <td><span class="badge ${statusBadgeClass}">${status}</span></td>
                        <td><span class="badge ${labelBadgeClass}">${label}</span></td>
                        <td>
                            <button type="button"
                                class="btn btn-sm btn-danger"
                                onclick="deleteLogEntrySensor('${nodeId}', '${key}')">
                                Hapus
                            </button>
                        </td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;

            // reset check-all ketika data baru di-load
            const checkAll = document.getElementById('checkAllSensor');
            if (checkAll) checkAll.checked = false;

        } catch (err) {
            console.error('Gagal load log sensor:', err);
            tbody.innerHTML = `
                <tr>
                    <td colspan="11" class="text-center text-danger">
                        Terjadi kesalahan saat mengambil data.
                    </td>
                </tr>
            `;
        }
    }

    async function deleteLogEntrySensor(node, key) {
        if (!key) {
            alert('Key log tidak ditemukan.');
            return;
        }
        if (!confirm('Yakin ingin menghapus data sensor ini?')) return;

        try {
            const res = await fetch("<?= base_url('admin/delete-log'); ?>", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'node=' + encodeURIComponent(node) + '&key=' + encodeURIComponent(key)
            });

            const out = await res.json();
            console.log('delete-log sensor:', out);

            if (out.status === 'ok') {
                loadLogSensor();
            } else {
                alert('Gagal menghapus data: ' + (out.message ?? 'unknown error'));
            }
        } catch (err) {
            console.error('Gagal menghapus log sensor:', err);
            alert('Terjadi kesalahan saat menghapus data sensor.');
        }
    }

    async function bulkDeleteSensor() {
        const node = document.getElementById('nodeFilter').value;
        if (!node) {
            alert('Pilih node terlebih dahulu.');
            return;
        }

        const checkboxes = document.querySelectorAll('#logSensorBody .row-check-sensor:checked');
        if (checkboxes.length === 0) {
            alert('Pilih minimal satu data yang akan dihapus.');
            return;
        }

        if (!confirm('Yakin ingin menghapus data sensor yang terpilih?')) return;

        const keys = Array.from(checkboxes)
            .map(cb => cb.getAttribute('data-key'))
            .filter(k => k && k !== '');

        if (keys.length === 0) {
            alert('Key data tidak ditemukan.');
            return;
        }

        const body =
            'node=' + encodeURIComponent(node) +
            keys.map(k => '&keys[]=' + encodeURIComponent(k)).join('');

        try {
            const res = await fetch("<?= base_url('admin/delete-logs'); ?>", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: body
            });

            const out = await res.json();
            console.log('bulk delete sensor:', out);

            if (out.status === 'ok') {
                alert('Berhasil menghapus ' + (out.deleted ?? 0) + ' data sensor.');
                loadLogSensor();
            } else {
                alert('Gagal menghapus data: ' + (out.message ?? 'unknown error'));
            }
        } catch (err) {
            console.error('Gagal bulk delete sensor:', err);
            alert('Terjadi kesalahan saat menghapus data sensor.');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const nodeFilter = document.getElementById('nodeFilter');
        const rangeFilter = document.getElementById('rangeFilter');
        const btnBulkDel = document.getElementById('btnDeleteSelectedSensor');
        const checkAll = document.getElementById('checkAllSensor');

        if (nodeFilter) nodeFilter.addEventListener('change', loadLogSensor);
        if (rangeFilter) rangeFilter.addEventListener('change', loadLogSensor);
        if (btnBulkDel) btnBulkDel.addEventListener('click', bulkDeleteSensor);
        if (checkAll) {
            checkAll.addEventListener('change', function() {
                const checked = this.checked;
                document
                    .querySelectorAll('#logSensorBody .row-check-sensor')
                    .forEach(cb => cb.checked = checked);
            });
        }

        loadLogSensor();
    });
</script>
<?= $this->endSection(); ?>