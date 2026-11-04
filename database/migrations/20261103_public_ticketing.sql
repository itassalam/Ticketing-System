ALTER TABLE tiket
  MODIFY user_id INT UNSIGNED NULL COMMENT 'Pemohon akun, kosong untuk laporan publik',
  ADD COLUMN pemohon_nama VARCHAR(100) NULL AFTER user_id,
  ADD COLUMN pemohon_telepon VARCHAR(30) NULL AFTER pemohon_nama,
  ADD COLUMN pemohon_divisi VARCHAR(100) NULL AFTER pemohon_telepon;

ALTER TABLE tiket_log
  MODIFY user_id INT UNSIGNED NULL;

ALTER TABLE tiket_atem
  MODIFY user_id INT UNSIGNED NULL,
  ADD COLUMN pemohon_nama VARCHAR(100) NULL AFTER user_id,
  ADD COLUMN pemohon_telepon VARCHAR(30) NULL AFTER pemohon_nama,
  ADD COLUMN pemohon_divisi VARCHAR(100) NULL AFTER pemohon_telepon;

ALTER TABLE tiket_ipsrs
  MODIFY user_id INT UNSIGNED NULL,
  ADD COLUMN pemohon_nama VARCHAR(100) NULL AFTER user_id,
  ADD COLUMN pemohon_telepon VARCHAR(30) NULL AFTER pemohon_nama,
  ADD COLUMN pemohon_divisi VARCHAR(100) NULL AFTER pemohon_telepon;

ALTER TABLE atk_pengajuan
  MODIFY user_id INT UNSIGNED NULL,
  ADD COLUMN pemohon_nama VARCHAR(100) NULL AFTER user_id,
  ADD COLUMN pemohon_telepon VARCHAR(30) NULL AFTER pemohon_nama,
  ADD COLUMN pemohon_divisi VARCHAR(100) NULL AFTER pemohon_telepon;

ALTER TABLE atk_permintaan
  MODIFY user_id INT UNSIGNED NULL,
  ADD COLUMN pemohon_nama VARCHAR(100) NULL AFTER user_id,
  ADD COLUMN pemohon_telepon VARCHAR(30) NULL AFTER pemohon_nama,
  ADD COLUMN pemohon_divisi VARCHAR(100) NULL AFTER pemohon_telepon;
