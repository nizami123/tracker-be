<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - Employee Tracker</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= base_url('assets/admin/css/admin.css') ?>">
</head>
<body>

<div class="at-login-wrap">
    <div class="at-login-card">
        <div class="text-center">
            <div class="at-login-logo"><i class="bi bi-geo-alt-fill"></i></div>
            <h5 class="fw-bold mb-0">Employee Tracker</h5>
            <p class="text-gray small mb-4">Panel Admin</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 small"><?= html_escape($error) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= site_url('admin/auth/login') ?>">
            <div class="mb-3">
                <label class="form-label small fw-bold">Email</label>
                <input type="email" name="email" class="form-control" placeholder="admin@example.com" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-bold">Password</label>
                <div class="input-group">
                    <input type="password" name="password" id="atPassword" class="form-control" placeholder="Password" required>
                    <button class="btn btn-outline-secondary" type="button" id="atTogglePassword"><i class="bi bi-eye"></i></button>
                </div>
            </div>
            <button type="submit" class="btn btn-at-primary w-100 fw-bold py-2">MASUK</button>
        </form>

        <p class="text-center text-gray small mt-4 mb-0">
            Khusus akun dengan role SUPER_ADMIN atau ADMIN_KANTOR.
        </p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('atTogglePassword').addEventListener('click', function () {
    const input = document.getElementById('atPassword');
    const icon = this.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
    }
});
</script>
</body>
</html>
