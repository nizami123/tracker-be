<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? $pageTitle . ' - ' : '' ?>Samsu Tracker Admin</title>
    <link rel="icon" type="image/png" href="<?= base_url('assets/admin/img/logo.png') ?>">

    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- DataTables (Bootstrap 5 styling) -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.11/css/dataTables.bootstrap5.min.css">
    <!-- Leaflet -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

    <link rel="stylesheet" href="<?= base_url('assets/admin/css/admin.css') ?>">
</head>
<body>

<div class="at-topbar">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm btn-outline-secondary d-lg-none" type="button"
                data-bs-toggle="offcanvas" data-bs-target="#atSidebar" aria-controls="atSidebar">
            <i class="bi bi-list"></i>
        </button>
        <div class="brand">
            <span class="brand-icon">
                <img src="<?= base_url('assets/admin/img/logo.png') ?>" alt="Samsu Tracker" style="width:100%;height:100%;object-fit:contain;padding:4px;">
            </span>
            <span class="d-none d-sm-inline">Samsu Tracker Admin</span>
        </div>
    </div>

    <div class="dropdown">
        <button class="btn btn-light d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown">
            <span class="avatar-circle"><?= strtoupper(substr($admin['name'] ?? '?', 0, 1)) ?></span>
            <span class="d-none d-sm-flex flex-column align-items-start lh-sm">
                <strong style="font-size:13px;"><?= html_escape($admin['name'] ?? '-') ?></strong>
                <span class="text-gray" style="font-size:11px;"><?= html_escape($admin['role'] ?? '-') ?></span>
            </span>
            <i class="bi bi-chevron-down small"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="<?= site_url('admin/profile') ?>"><i class="bi bi-person me-2"></i>Profil</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="<?= site_url('admin/auth/logout') ?>"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
        </ul>
    </div>
</div>

<div class="at-layout">
