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

    <li class="nav-item">
        <a class="nav-link" href="<?= base_url('admin/log-sensor'); ?>">
            <span>Log Data Sensor</span>
        </a>
    </li>

    <li class="nav-item active">
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
            LOG QOS
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
        <h6 class="m-0 font-weight-bold text-primary">Log QoS Jaringan</h6>

        <div class="d-flex align-items-center">
            <button type="button"
                id="btnDeleteSelectedQos"
                class="btn btn-sm btn-danger mr-2">
                Hapus terpilih
            </button>

            <select id="nodeFilterQos" class="form-control form-control-sm mr-2" style="width: 120px;">
                <?php if (!empty($nodeIds)): ?>
                    <?php foreach ($nodeIds as $id): ?>
                        <option value="<?= esc($id); ?>"><?= esc($id); ?></option>
                    <?php endforeach; ?>
                <?php else: ?>
                    <option value="">(Tidak ada node)</option>
                <?php endif; ?>
            </select>

            <select id="rangeFilterQos" class="form-control form-control-sm" style="width: 150px;">
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
                            <input type="checkbox" id="checkAllQos">
                        </th>
                        <th style="width: 50px;">No</th>
                        <th style="width: 120px;">Tanggal</th>
                        <th style="width: 90px;">Jam</th>
                        <th style="width: 80px;">Node</th>
                        <th style="width: 120px;">Delay (ms)</th>
                        <th style="width: 150px;">Packet loss (%)</th>
                        <th style="width: 160px;">Throughput (bps)</th>
                        <th style="width: 120px;">Jitter (ms)</th>
                        <th style="width: 120px;">Status perangkat</th>
                        <th style="width: 90px;">Aksi</th>
                    </tr>
                </thead>
                <tbody id="logQosBody">
                    <tr>
                        <td colspan="11" class="text-center text-muted">
                            Belum ada data log QoS.
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
    async function loadLogQos() {
        const node = document.getElementById('nodeFilterQos').value;
        const range = document.getElementById('rangeFilterQos').value;
        const tbody = document.getElementById('logQosBody');

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

        const url = "<?= base_url('admin/load-qos'); ?>" +
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
                            Belum ada data QoS untuk rentang waktu ini.
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
                const delay = row.delay_ms ?? '-';
                const ploss = row.ploss ?? '-';
                const thput = row.thput ?? '-';
                const jitter = row.jitter_ms ?? '-';
                const status = row.status ?? '-';
                const key = row.key ?? '';

                let statusBadgeClass = 'badge-secondary';
                if (status === 'online') statusBadgeClass = 'badge-success';
                if (status === 'offline') statusBadgeClass = 'badge-secondary';

                html += `
                    <tr>
                        <td class="text-center">
                            <input type="checkbox"
                                   class="row-check-qos"
                                   data-key="${key}">
                        </td>
                        <td>${no}</td>
                        <td>${date}</td>
                        <td>${time}</td>
                        <td>${nodeId}</td>
                        <td>${delay}</td>
                        <td>${ploss}</td>
                        <td>${thput}</td>
                        <td>${jitter}</td>
                        <td><span class="badge ${statusBadgeClass}">${status}</span></td>
                        <td>
                            <button type="button"
                                class="btn btn-sm btn-danger"
                                onclick="deleteLogEntryQos('${nodeId}', '${key}')">
                                Hapus
                            </button>
                        </td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;

            const checkAll = document.getElementById('checkAllQos');
            if (checkAll) checkAll.checked = false;

        } catch (err) {
            console.error('Gagal load log QoS:', err);
            tbody.innerHTML = `
                <tr>
                    <td colspan="11" class="text-center text-danger">
                        Terjadi kesalahan saat mengambil data QoS.
                    </td>
                </tr>
            `;
        }
    }

    async function deleteLogEntryQos(node, key) {
        if (!key) {
            alert('Key log tidak ditemukan.');
            return;
        }
        if (!confirm('Yakin ingin menghapus data QoS ini?')) return;

        try {
            const res = await fetch("<?= base_url('admin/delete-log'); ?>", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'node=' + encodeURIComponent(node) +
                    '&key=' + encodeURIComponent(key)
            });

            const out = await res.json();
            console.log('delete-log qos:', out);

            if (out.status === 'ok') {
                loadLogQos();
            } else {
                alert('Gagal menghapus data: ' + (out.message ?? 'unknown error'));
            }
        } catch (err) {
            console.error('Gagal menghapus log QoS:', err);
            alert('Terjadi kesalahan saat menghapus data QoS.');
        }
    }

    async function bulkDeleteQos() {
        const node = document.getElementById('nodeFilterQos').value;
        if (!node) {
            alert('Pilih node terlebih dahulu.');
            return;
        }

        const checkboxes = document.querySelectorAll('#logQosBody .row-check-qos:checked');
        if (checkboxes.length === 0) {
            alert('Pilih minimal satu data yang akan dihapus.');
            return;
        }

        if (!confirm('Yakin ingin menghapus data QoS yang terpilih?')) return;

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
            console.log('bulk delete qos:', out);

            if (out.status === 'ok') {
                alert('Berhasil menghapus ' + (out.deleted ?? 0) + ' data QoS.');
                loadLogQos();
            } else {
                alert('Gagal menghapus data: ' + (out.message ?? 'unknown error'));
            }
        } catch (err) {
            console.error('Gagal bulk delete QoS:', err);
            alert('Terjadi kesalahan saat menghapus data QoS.');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const nodeFilter = document.getElementById('nodeFilterQos');
        const rangeFilter = document.getElementById('rangeFilterQos');
        const btnBulkDel = document.getElementById('btnDeleteSelectedQos');
        const checkAll = document.getElementById('checkAllQos');

        if (nodeFilter) nodeFilter.addEventListener('change', loadLogQos);
        if (rangeFilter) rangeFilter.addEventListener('change', loadLogQos);
        if (btnBulkDel) btnBulkDel.addEventListener('click', bulkDeleteQos);
        if (checkAll) {
            checkAll.addEventListener('change', function() {
                const checked = this.checked;
                document
                    .querySelectorAll('#logQosBody .row-check-qos')
                    .forEach(cb => cb.checked = checked);
            });
        }

        loadLogQos();
    });
</script>
<?= $this->endSection(); ?>