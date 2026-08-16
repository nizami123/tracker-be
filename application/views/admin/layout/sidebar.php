<?php
// Small local helper so each <a> below stays a one-liner.
if (!function_exists('at_nav_link')) {
    function at_nav_link(string $key, string $active, string $url, string $icon, string $label): void
    {
        $isActive = ($active === $key) ? ' active' : '';
        echo '<a href="' . site_url($url) . '" class="nav-link' . $isActive . '">'
            . '<i class="bi ' . $icon . '"></i><span>' . $label . '</span></a>';
    }
}
$active = $activeMenu ?? '';
?>
<div class="offcanvas-lg offcanvas-start at-sidebar" tabindex="-1" id="atSidebar" aria-labelledby="atSidebarLabel">
    <div class="offcanvas-header d-lg-none border-bottom">
        <h6 class="offcanvas-title" id="atSidebarLabel">Menu</h6>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#atSidebar"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column p-0">
        <nav class="nav flex-column py-2">
            <?php at_nav_link('dashboard', $active, 'admin/dashboard', 'bi-speedometer2', 'Dashboard'); ?>
            <?php at_nav_link('monitoring_aktif', $active, 'admin/monitoring', 'bi-broadcast', 'Tracking Aktif'); ?>

            <div class="nav-section-title">Master Data</div>
            <?php at_nav_link('master_kantor', $active, 'admin/offices', 'bi-building', 'Kantor'); ?>
            <?php at_nav_link('master_karyawan', $active, 'admin/employees', 'bi-people', 'Karyawan'); ?>
            <?php at_nav_link('master_kendaraan', $active, 'admin/vehicles', 'bi-truck', 'Kendaraan'); ?>
            <?php at_nav_link('master_admin', $active, 'admin/admin_users', 'bi-shield-lock', 'User Admin'); ?>

            <div class="nav-section-title">Absensi</div>
            <?php at_nav_link('history_absensi', $active, 'admin/attendances', 'bi-calendar-check', 'History Absensi'); ?>
            <?php at_nav_link('tracking_karyawan', $active, 'admin/attendance_tracking', 'bi-geo-alt', 'Tracking Karyawan'); ?>

            <div class="nav-section-title">Pengajuan</div>
            <?php at_nav_link('pengajuan', $active, 'admin/requests?status=PENDING', 'bi-file-earmark-text', 'Pengajuan'); ?>
            <?php at_nav_link('riwayat_pengajuan', $active, 'admin/requests', 'bi-clock-history', 'Riwayat Pengajuan'); ?>

            <div class="nav-section-title">Pengiriman Kendaraan</div>
            <?php at_nav_link('history_pengiriman', $active, 'admin/deliveries', 'bi-truck-front', 'History Pengiriman'); ?>
            <?php at_nav_link('tracking_kendaraan', $active, 'admin/delivery_tracking', 'bi-signpost-2', 'Tracking Kendaraan'); ?>

            <div class="nav-section-title">Laporan</div>
            <?php at_nav_link('laporan_absensi', $active, 'admin/reports/attendance', 'bi-bar-chart', 'Laporan Absensi'); ?>
            <?php at_nav_link('laporan_pengajuan', $active, 'admin/reports/requests', 'bi-bar-chart', 'Laporan Pengajuan'); ?>
            <?php at_nav_link('laporan_pengiriman', $active, 'admin/reports/deliveries', 'bi-bar-chart', 'Laporan Pengiriman'); ?>

            <div class="nav-section-title">Setting</div>
            <?php at_nav_link('profil', $active, 'admin/profile', 'bi-person-circle', 'Profil'); ?>
            <a href="<?= site_url('admin/auth/logout') ?>" class="nav-link text-danger">
                <i class="bi bi-box-arrow-right"></i><span>Logout</span>
            </a>
        </nav>

        <div class="mt-auto p-3">
            <div class="at-card p-3 text-center" style="background: var(--at-primary-light); border:none;">
                <div class="fw-bold" style="color: var(--at-primary); font-size: 12.5px;">
                    <?php if (($admin['role'] ?? '') === 'SUPER_ADMIN'): ?>
                        <i class="bi bi-globe-asia-australia me-1"></i>Akses: Semua Kantor
                    <?php else: ?>
                        <i class="bi bi-building me-1"></i>Akses: Kantor Sendiri
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="at-main">
    <div class="at-content">
