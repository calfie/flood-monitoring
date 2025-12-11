<?= $this->extend('layouts/main'); ?>

<?php // ====== TOPBAR KHUSUS LOGIN ====== 
?>
<?= $this->section('topbar'); ?>
<nav class="navbar navbar-light bg-transparent px-4 pt-3">
    <span class="navbar-brand mb-0 h5 font-weight-bold">
        ENYODERAMIL
    </span>

    </span>
    <a href="<?= base_url('/'); ?>" class="btn btn-outline-secondary btn-sm">
        Kembali ke dashboard publik
    </a>
</nav>
<?= $this->endSection(); ?>

<?= $this->section('content'); ?>

<div class="login-page-wrapper"
    style="background: url('<?= base_url('sbadmin/img/Sungai.jpg'); ?>') center center / cover no-repeat fixed;">

    <div class="d-flex justify-content-center align-items-center" style="min-height: 80vh;">
        <div class="col-lg-4">
            <div class="card shadow">
                <div class="card-body">
                    <h5 class="text-center mb-4" style="font-weight:700;">Login Admin</h5>
                    <?php if (session()->getFlashdata('error')): ?>
                        <div class="alert alert-danger py-2">
                            <?= esc(session()->getFlashdata('error')); ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="<?= base_url('admin/login'); ?>">
                        <?= csrf_field(); ?>
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text"
                                name="username"
                                class="form-control"
                                value="<?= old('username'); ?>"
                                required>
                        </div>
                        <div class="form-group">
                            <label>Password</label>
                            <div class="input-group">
                                <input type="password"
                                    name="password"
                                    id="password"
                                    class="form-control"
                                    required>

                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary"
                                        type="button"
                                        id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <button class="btn btn-primary btn-block mt-3" type="submit">
                            Login
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection(); ?>
<?= $this->section('scripts'); ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const pwd = document.getElementById('password');
        const btn = document.getElementById('togglePassword');
        const icon = btn.querySelector('i');

        btn.addEventListener('click', function() {
            const isHidden = pwd.type === 'password';
            pwd.type = isHidden ? 'text' : 'password';

            // ganti ikon eye / eye-slash
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
        });
    });
</script>
<?= $this->endSection(); ?>