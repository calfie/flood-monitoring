<!-- app/Views/layouts/main.php -->
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $title ?? 'Flood Monitoring'; ?></title>

    <!-- SB Admin CSS -->
    <!-- Font Awesome -->
    <link rel="stylesheet"
        href="<?= base_url('vendor/fontawesome-free/css/all.min.css'); ?>"
        type="text/css">

    <!-- SB Admin 2 main CSS -->
    <link rel="stylesheet"
        href="<?= base_url('sbadmin/css/sb-admin-2.css'); ?>">

</head>

<body id="page-top">

    <!-- Page Wrapper -->
    <div id="wrapper">

        <!-- Sidebar -->
        <?= $this->renderSection('sidebar'); ?>
        <!-- End of Sidebar -->

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">

            <!-- Main Content -->
            <div id="content">

                <!-- Topbar -->
                <?= $this->renderSection('topbar'); ?>
                <!-- End of Topbar -->

                <!-- Begin Page Content -->
                <div class="container-fluid">
                    <?= $this->renderSection('content'); ?>
                </div>
                <!-- /.container-fluid -->

            </div>
            <!-- End of Main Content -->

            <!-- Footer -->
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>© <?= date('Y'); ?> Flood Monitoring</span>
                    </div>
                </div>
            </footer>
            <!-- End of Footer -->

        </div>
        <!-- End of Content Wrapper -->

    </div>
    <!-- End of Page Wrapper -->

    <!-- SB Admin JS -->
    <script src="<?= base_url('sbadmin/vendor/jquery/jquery.min.js'); ?>"></script>
    <script src="<?= base_url('sbadmin/vendor/bootstrap/js/bootstrap.bundle.min.js'); ?>"></script>
    <script src="<?= base_url('sbadmin/vendor/jquery-easing/jquery.easing.min.js'); ?>"></script>
    <script src="<?= base_url('sbadmin/js/sb-admin-2.min.js'); ?>"></script>

    <!-- Extra scripts per page -->
    <?= $this->renderSection('scripts'); ?>

</body>

</html>