<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">Dashboard</h4>
        <p class="text-gray small mb-0">Ringkasan aktivitas <?= $admin['role'] === 'SUPER_ADMIN' ? 'seluruh kantor' : 'kantor Anda' ?> hari ini</p>
    </div>
    <span class="badge-at badge-at-gray"><i class="bi bi-calendar3 me-1"></i><?= date('d F Y') ?></span>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-4 col-xl-2">
        <div class="at-stat-card">
            <div class="icon-box bg-icon-green"><i class="bi bi-people-fill"></i></div>
            <div>
                <div class="stat-value"><?= (int) $stats['total_karyawan'] ?></div>
                <div class="stat-label">Total Karyawan</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-4 col-xl-2">
        <div class="at-stat-card">
            <div class="icon-box bg-icon-green"><i class="bi bi-check-circle-fill"></i></div>
            <div>
                <div class="stat-value"><?= (int) $stats['hadir_hari_ini'] ?></div>
                <div class="stat-label">Hadir Hari Ini</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-4 col-xl-2">
        <div class="at-stat-card">
            <div class="icon-box bg-icon-orange"><i class="bi bi-exclamation-circle-fill"></i></div>
            <div>
                <div class="stat-value"><?= (int) $stats['belum_absen'] ?></div>
                <div class="stat-label">Belum Absen</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-4 col-xl-2">
        <div class="at-stat-card">
            <div class="icon-box bg-icon-blue"><i class="bi bi-geo-alt-fill"></i></div>
            <div>
                <div class="stat-value"><?= (int) $stats['sedang_tracking'] ?></div>
                <div class="stat-label">Sedang Tracking</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-4 col-xl-2">
        <div class="at-stat-card">
            <div class="icon-box bg-icon-orange"><i class="bi bi-file-earmark-text-fill"></i></div>
            <div>
                <div class="stat-value"><?= (int) $stats['pengajuan_menunggu'] ?></div>
                <div class="stat-label">Pengajuan Menunggu</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-4 col-xl-2">
        <div class="at-stat-card">
            <div class="icon-box bg-icon-blue"><i class="bi bi-truck-front-fill"></i></div>
            <div>
                <div class="stat-value"><?= (int) $stats['pengiriman_aktif'] ?></div>
                <div class="stat-label">Pengiriman Aktif</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="at-card h-100">
            <h6 class="fw-bold mb-3">Kehadiran 7 Hari Terakhir</h6>
            <canvas id="chartKehadiran" height="110"></canvas>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="at-card h-100">
            <h6 class="fw-bold mb-3">Hadir vs Belum/Tidak Hadir (Hari Ini)</h6>
            <canvas id="chartHadirVsTidak" height="130"></canvas>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="at-card h-100">
            <h6 class="fw-bold mb-3">Pengajuan Berdasarkan Status</h6>
            <canvas id="chartPengajuan" height="130"></canvas>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="at-card h-100">
            <h6 class="fw-bold mb-3">Pengiriman Kendaraan Berdasarkan Status</h6>
            <canvas id="chartPengiriman" height="130"></canvas>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const primary = '#1E9E5A';
    const palette = ['#1E9E5A', '#F5A623', '#E53935', '#2E7BE0', '#8A8F98'];

    function fetchJSON(url, cb) {
        fetch(url).then(r => r.json()).then(cb).catch(() => {});
    }

    fetchJSON(ADMIN_BASE_URL + 'admin/dashboard/chart_kehadiran', function (data) {
        new Chart(document.getElementById('chartKehadiran'), {
            type: 'bar',
            data: { labels: data.labels, datasets: [{ label: 'Jumlah Hadir', data: data.values, backgroundColor: primary, borderRadius: 6 }] },
            options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
        });
    });

    fetchJSON(ADMIN_BASE_URL + 'admin/dashboard/chart_hadir_vs_tidak', function (data) {
        new Chart(document.getElementById('chartHadirVsTidak'), {
            type: 'doughnut',
            data: { labels: data.labels, datasets: [{ data: data.values, backgroundColor: [palette[0], palette[4]] }] },
            options: { plugins: { legend: { position: 'bottom' } } }
        });
    });

    fetchJSON(ADMIN_BASE_URL + 'admin/dashboard/chart_pengajuan', function (data) {
        new Chart(document.getElementById('chartPengajuan'), {
            type: 'doughnut',
            data: { labels: data.labels, datasets: [{ data: data.values, backgroundColor: [palette[1], palette[0], palette[2]] }] },
            options: { plugins: { legend: { position: 'bottom' } } }
        });
    });

    fetchJSON(ADMIN_BASE_URL + 'admin/dashboard/chart_pengiriman', function (data) {
        new Chart(document.getElementById('chartPengiriman'), {
            type: 'doughnut',
            data: { labels: data.labels, datasets: [{ data: data.values, backgroundColor: [palette[3], palette[1], palette[0]] }] },
            options: { plugins: { legend: { position: 'bottom' } } }
        });
    });
});
</script>
