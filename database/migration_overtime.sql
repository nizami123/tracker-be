-- Migrasi fitur Pengajuan Lembur (OVERTIME).
-- Jalankan SEKALI pada database yang sudah berjalan (phpMyAdmin > SQL).
--   * type  : tambah nilai 'OVERTIME'
--   * time  : jam MULAI lembur (kolom lama)
--   * end_time : jam SELESAI lembur (kolom baru)

ALTER TABLE `attendance_requests`
  MODIFY `type` enum('LATE','CHECK_IN','CHECK_OUT','LEAVE','OUTSIDE_OFFICE','OVERTIME') NOT NULL,
  ADD COLUMN `end_time` time DEFAULT NULL AFTER `time`;
