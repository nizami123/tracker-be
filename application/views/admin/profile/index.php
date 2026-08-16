<div class="mb-3">
    <h4 class="fw-bold mb-0">Profil</h4>
    <p class="text-gray small mb-0">Kelola informasi akun Anda.</p>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="at-card">
            <h6 class="fw-bold mb-3">Informasi Akun</h6>
            <div id="profileError" class="alert alert-danger py-2 small d-none"></div>
            <div id="profileSuccess" class="alert alert-success py-2 small d-none"></div>
            <form id="profileForm">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Kode</label>
                    <input type="text" class="form-control" value="<?= html_escape($admin['employee_code']) ?>" disabled>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Nama Lengkap *</label>
                    <input type="text" class="form-control" name="name" value="<?= html_escape($admin['name']) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Email</label>
                    <input type="email" class="form-control" value="<?= html_escape($admin['email']) ?>" disabled>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">No. Telepon</label>
                    <input type="text" class="form-control" name="phone" value="<?= html_escape($admin['phone'] ?: '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Role</label>
                    <input type="text" class="form-control" value="<?= html_escape($admin['role']) ?>" disabled>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Kantor</label>
                    <input type="text" class="form-control" value="<?= html_escape($office['name'] ?? '-') ?>" disabled>
                </div>
                <button type="submit" class="btn btn-at-primary">Simpan Perubahan</button>
            </form>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="at-card">
            <h6 class="fw-bold mb-3">Ubah Password</h6>
            <div id="passwordError" class="alert alert-danger py-2 small d-none"></div>
            <div id="passwordSuccess" class="alert alert-success py-2 small d-none"></div>
            <form id="passwordForm">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Password Lama *</label>
                    <input type="password" class="form-control" name="current_password" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Password Baru *</label>
                    <input type="password" class="form-control" name="new_password" required minlength="6">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Konfirmasi Password Baru *</label>
                    <input type="password" class="form-control" name="confirm_password" required minlength="6">
                </div>
                <button type="submit" class="btn btn-at-primary">Ubah Password</button>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('profileForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const fd = new FormData(this);
        const err = document.getElementById('profileError'), ok = document.getElementById('profileSuccess');
        err.classList.add('d-none'); ok.classList.add('d-none');
        fetch(ADMIN_BASE_URL + 'admin/profile/update', { method: 'POST', body: fd })
            .then(r => r.json()).then(res => {
                if (res.success) { ok.textContent = res.message; ok.classList.remove('d-none'); }
                else { err.textContent = res.message; err.classList.remove('d-none'); }
            });
    });

    document.getElementById('passwordForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const fd = new FormData(this);
        const err = document.getElementById('passwordError'), ok = document.getElementById('passwordSuccess');
        err.classList.add('d-none'); ok.classList.add('d-none');
        fetch(ADMIN_BASE_URL + 'admin/profile/change_password', { method: 'POST', body: fd })
            .then(r => r.json()).then(res => {
                if (res.success) { ok.textContent = res.message; ok.classList.remove('d-none'); document.getElementById('passwordForm').reset(); }
                else { err.textContent = res.message; err.classList.remove('d-none'); }
            });
    });
});
</script>
