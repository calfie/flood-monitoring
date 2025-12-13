<?= $this->extend('layouts/main'); ?>

<?= $this->section('sidebar'); ?>
<ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">

    <a class="sidebar-brand d-flex align-items-center justify-content-center" href="<?= base_url('/admin'); ?>">
        <div class="sidebar-brand-text mx-3">ENYODERAMIL</div>
    </a>

    <hr class="sidebar-divider my-0">

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

    <li class="nav-item">
        <a class="nav-link" href="<?= base_url('admin/log-qos'); ?>">
            <span>Log QoS Jaringan</span>
        </a>
    </li>

    <li class="nav-item active">
        <a class="nav-link" href="<?= base_url('admin/log-signal'); ?>">
            <span>Log Signal (RSSI & SNR)</span>
        </a>
    </li>

    <li class="nav-item">
        <a class="nav-link" href="<?= base_url('admin/users'); ?>">
            <span>Kelola Admin</span>
        </a>
    </li>
</ul>
<?= $this->endSection(); ?>

<?= $this->section('topbar'); ?>
<nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
    <div class="w-100 text-center">
        <span class="navbar-brand mb-0 h5 font-weight-bold dashboard-title d-block">
            Log Signal (RSSI & SNR)
        </span>
    </div>
    <ul class="navbar-nav ml-auto">
        <li class="nav-item mr-3 d-none d-lg-block align-self-center">
            <span class="text-muted small"><?= esc($username ?? 'admin'); ?></span>
        </li>
        <li class="nav-item">
            <a href="<?= base_url('admin/logout'); ?>" class="btn btn-sm btn-outline-danger">Logout</a>
        </li>
    </ul>
</nav>
<?= $this->endSection(); ?>

<?= $this->section('content'); ?>
<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Log Signal</h6>

        <div class="d-flex align-items-center">
            <button type="button" id="btnDeleteSelectedSignal" class="btn btn-sm btn-danger mr-2">
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
                        <th style="width: 40px; text-align:center;">
                            <input type="checkbox" id="checkAllSignal">
                        </th>
                        <th style="width: 50px;">No</th>
                        <th style="width: 120px;">Tanggal</th>
                        <th style="width: 90px;">Jam</th>
                        <th style="width: 80px;">Node</th>
                        <th style="width: 110px;">Ketinggian</th>
                        <th style="width: 110px;">Arus</th>
                        <th style="width: 110px;">RSSI (dBm)</th>
                        <th style="width: 110px;">SNR (dB)</th>
                        <th style="width: 90px;">Aksi</th>
                    </tr>
                </thead>
                <tbody id="logSignalBody">
                    <tr>
                        <td colspan="10" class="text-center text-muted">Belum ada data log.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection(); ?>

<?= $this->section('scripts'); ?>
<script>
    async function loadLogSignal() {
        const node = document.getElementById('nodeFilter').value;
        const range = document.getElementById('rangeFilter').value;
        const tbody = document.getElementById('logSignalBody');

        if (!node) {
            tbody.innerHTML = `<tr><td colspan="10" class="text-center text-muted">Tidak ada node yang dipilih.</td></tr>`;
            return;
        }

        const url = "<?= base_url('admin/load-signal'); ?>" +
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
                tbody.innerHTML = `<tr><td colspan="10" class="text-center text-muted">Belum ada data log untuk rentang waktu ini.</td></tr>`;
                return;
            }

            let html = '';
            data.forEach((row, index) => {
                const no = index + 1;
                html += `
          <tr>
            <td class="text-center">
              <input type="checkbox" class="row-check-signal" data-key="${row.key ?? ''}">
            </td>
            <td>${no}</td>
            <td>${row.date ?? '-'}</td>
            <td>${row.time ?? '-'}</td>
            <td>${row.node ?? '-'}</td>
            <td>${row.distance ?? '-'}</td>
            <td>${row.flow ?? '-'}</td>
            <td>${row.rssi ?? '-'}</td>
            <td>${row.snr ?? '-'}</td>
            <td>
              <button type="button" class="btn btn-sm btn-danger"
                onclick="deleteSignal('${row.node ?? ''}', '${row.key ?? ''}')">
                Hapus
              </button>
            </td>
          </tr>
        `;
            });

            tbody.innerHTML = html;

            const checkAll = document.getElementById('checkAllSignal');
            if (checkAll) checkAll.checked = false;

        } catch (err) {
            console.error('Gagal load log signal:', err);
            tbody.innerHTML = `<tr><td colspan="10" class="text-center text-danger">Terjadi kesalahan saat mengambil data.</td></tr>`;
        }
    }

    async function deleteSignal(node, key) {
        if (!key) return alert('Key log tidak ditemukan.');
        if (!confirm('Yakin ingin menghapus data signal ini?')) return;

        const body = 'node=' + encodeURIComponent(node) + '&key=' + encodeURIComponent(key);

        const res = await fetch("<?= base_url('admin/delete-signal-log'); ?>", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body
        });

        const out = await res.json();
        if (out.status === 'ok') loadLogSignal();
        else alert('Gagal menghapus: ' + (out.message ?? 'unknown'));
    }

    async function bulkDeleteSignal() {
        const node = document.getElementById('nodeFilter').value;
        const checks = document.querySelectorAll('#logSignalBody .row-check-signal:checked');
        if (!node) return alert('Pilih node terlebih dahulu.');
        if (checks.length === 0) return alert('Pilih minimal satu data.');

        if (!confirm('Yakin ingin menghapus data signal yang terpilih?')) return;

        const keys = Array.from(checks).map(cb => cb.dataset.key).filter(Boolean);
        const body = 'node=' + encodeURIComponent(node) + keys.map(k => '&keys[]=' + encodeURIComponent(k)).join('');

        const res = await fetch("<?= base_url('admin/delete-signal-logs'); ?>", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body
        });

        const out = await res.json();
        if (out.status === 'ok') {
            alert('Berhasil menghapus ' + (out.deleted ?? 0) + ' data.');
            loadLogSignal();
        } else {
            alert('Gagal menghapus: ' + (out.message ?? 'unknown'));
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('nodeFilter')?.addEventListener('change', loadLogSignal);
        document.getElementById('rangeFilter')?.addEventListener('change', loadLogSignal);
        document.getElementById('btnDeleteSelectedSignal')?.addEventListener('click', bulkDeleteSignal);
        document.getElementById('checkAllSignal')?.addEventListener('change', function() {
            document.querySelectorAll('#logSignalBody .row-check-signal')
                .forEach(cb => cb.checked = this.checked);
        });

        loadLogSignal();
    });
</script>
<?= $this->endSection(); ?>