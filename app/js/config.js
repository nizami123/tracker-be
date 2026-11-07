// Konfigurasi PWA. Samakan dengan BuildConfig.BASE_URL di aplikasi Android.
export const CONFIG = {
	// Alamat backend CI3. Harus diakhiri "/".
	// Jika PWA ini di-host di domain/origin YANG SAMA dengan backend, isi "/" agar tidak perlu CORS.
	// API_BASE: 'https://tracker.siternal.my.id/',
	API_BASE: "/",

	APP_NAME: "Samsu Tracker",
	VERSION: "1.0.0",

	WORK_START: "08:00",
	WORK_END: "16:30",

	TRACKING_INTERVAL_S: 600, // 10 menit (Constants.TRACKING_INTERVAL_MS)
	DELIVERY_INTERVAL_S: 600, // 10 menit
	SYNC_BATCH: 100,
};
