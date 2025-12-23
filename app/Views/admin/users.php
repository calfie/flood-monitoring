<!-- app/Views/admin/users.php -->
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

    <li class="nav-item active">
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
            Kelola Admin
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

<?php // ========== CONTENT ========== 
?>
<?= $this->section('content'); ?>

<?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert-danger">
        <?= esc(session()->getFlashdata('error')); ?>
    </div>
<?php endif; ?>

<?php if (session()->getFlashdata('success')): ?>
    <div class="alert alert-success">
        <?= esc(session()->getFlashdata('success')); ?>
    </div>
<?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Daftar Admin</h6>
    </div>
    <div class="card-body">

        <!-- Form tambah admin -->
        <form action="<?= base_url('admin/users/create'); ?>" method="post" class="mb-4">
            <?= csrf_field(); ?>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="username">Username</label>
                    <input type="text"
                        name="username"
                        id="username"
                        class="form-control"
                        required
                        value="<?= old('username'); ?>">
                </div>
                <div class="form-group col-md-4">
                    <label for="password">Password</label>
                    <input type="text"
                        name="password"
                        id="password"
                        class="form-control"
                        required>
                </div>
                <div class="form-group col-md-2">
                    <label for="role">Role</label>
                    <select name="role" id="role" class="form-control">
                        <option value="admin" selected>admin</option>
                    </select>
                </div>
                <div class="form-group col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-block">
                        Tambah Admin
                    </button>
                </div>
            </div>
        </form>

        <!-- Tabel admin -->
        <div class="table-responsive">
            <table class="table table-bordered table-hover mb-0">
                <thead class="thead-light">
                    <tr class="text-center">
                        <th style="width: 60px;">No</th>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th style="width: 120px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">
                                Belum ada data admin.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $no = 1; ?>
                        <?php foreach ($users as $id => $user): ?>
                            <tr>
                                <td class="text-center"><?= $no++; ?></td>
                                <td><?= esc($id); ?></td>
                                <td><?= esc($user['username'] ?? '-'); ?></td>
                                <td><?= esc($user['role'] ?? '-'); ?></td>
                                <td class="text-center">
                                    <form action="<?= base_url('admin/users/delete/' . urlencode($id)); ?>"
                                        method="post"
                                        onsubmit="return confirm('Hapus admin ini?');"
                                        style="display:inline-block;">
                                        <?= csrf_field(); ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            Hapus
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<?= $this->endSection(); ?>

<?php // ========== SCRIPTS (kalau perlu) ========== 
?>
<?= $this->section('scripts'); ?>
<!-- Belum ada JS khusus untuk halaman ini -->
<?= $this->endSection(); ?>