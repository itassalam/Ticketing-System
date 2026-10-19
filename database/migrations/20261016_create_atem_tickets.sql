ALTER TABLE users
  MODIFY role enum('user','teknisi','teknisi_ipsrs','teknisi_atem','admin','hrd','keuangan','akreditasi')
  NOT NULL DEFAULT 'user';

CREATE TABLE IF NOT EXISTS kategori_atem (
  id int unsigned NOT NULL AUTO_INCREMENT,
  nama varchar(100) NOT NULL,
  deskripsi text DEFAULT NULL,
  icon varchar(50) NOT NULL DEFAULT 'fa-heart-pulse',
  sla_jam int NOT NULL DEFAULT 24,
  sla_respon_jam int NOT NULL DEFAULT 4,
  aktif tinyint(1) NOT NULL DEFAULT 1,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_kategori_atem_nama (nama),
  KEY idx_kategori_atem_aktif (aktif)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO kategori_atem (nama, deskripsi, icon, sla_jam, sla_respon_jam) VALUES
  ('Anjungan', 'Unit anjungan ATEM lengkap', 'fa-desktop', 24, 4),
  ('Printer Tiket', 'Printer thermal pencetak nomor antrian', 'fa-print', 24, 4),
  ('Touchscreen / Monitor', 'Layar sentuh dan monitor anjungan', 'fa-hand-pointer', 48, 8),
  ('Barcode / QR Scanner', 'Pemindai kartu, barcode, dan QR pasien', 'fa-qrcode', 24, 4),
  ('Mini PC / CPU', 'Unit komputer di dalam anjungan', 'fa-microchip', 48, 8),
  ('Jaringan (Switch / AP)', 'Perangkat jaringan pendukung ATEM', 'fa-network-wired', 24, 4),
  ('UPS / Kelistrikan', 'UPS, stabilizer, dan catu daya', 'fa-plug', 72, 12),
  ('Lainnya', 'Permasalahan ATEM lainnya', 'fa-tag', 48, 8)
ON DUPLICATE KEY UPDATE nama = VALUES(nama);

CREATE TABLE IF NOT EXISTS tiket_atem (
  id int unsigned NOT NULL AUTO_INCREMENT,
  nomor varchar(30) DEFAULT NULL,
  judul varchar(255) NOT NULL,
  deskripsi text NOT NULL,
  kategori_id int unsigned DEFAULT NULL,
  prioritas enum('Rendah','Sedang','Tinggi') NOT NULL DEFAULT 'Sedang',
  status enum('menunggu','diproses','selesai','ditolak','tidak_bisa') NOT NULL DEFAULT 'menunggu',
  user_id int unsigned NOT NULL,
  teknisi_id int unsigned DEFAULT NULL,
  lokasi varchar(255) DEFAULT NULL,
  aset_info varchar(255) DEFAULT NULL,
  waktu_submit datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  waktu_proses datetime DEFAULT NULL,
  waktu_selesai datetime DEFAULT NULL,
  durasi_respon_menit int unsigned DEFAULT NULL,
  durasi_selesai_menit int unsigned DEFAULT NULL,
  catatan_teknisi text DEFAULT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tiket_atem_nomor (nomor),
  KEY idx_tiket_atem_status (status),
  KEY idx_tiket_atem_user (user_id),
  KEY idx_tiket_atem_teknisi (teknisi_id),
  KEY idx_tiket_atem_kategori (kategori_id),
  KEY idx_tiket_atem_waktu (waktu_submit),
  CONSTRAINT fk_tiket_atem_kategori FOREIGN KEY (kategori_id) REFERENCES kategori_atem (id) ON DELETE SET NULL,
  CONSTRAINT fk_tiket_atem_user FOREIGN KEY (user_id) REFERENCES users (id),
  CONSTRAINT fk_tiket_atem_teknisi FOREIGN KEY (teknisi_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tiket_atem_log (
  id int unsigned NOT NULL AUTO_INCREMENT,
  tiket_id int unsigned NOT NULL,
  user_id int unsigned DEFAULT NULL,
  status_dari varchar(30) DEFAULT NULL,
  status_ke varchar(30) NOT NULL,
  keterangan text DEFAULT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tiket_atem_log_tiket (tiket_id),
  KEY idx_tiket_atem_log_user (user_id),
  CONSTRAINT fk_tiket_atem_log_tiket FOREIGN KEY (tiket_id) REFERENCES tiket_atem (id) ON DELETE CASCADE,
  CONSTRAINT fk_tiket_atem_log_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS komentar_atem (
  id int unsigned NOT NULL AUTO_INCREMENT,
  tiket_id int unsigned NOT NULL,
  user_id int unsigned NOT NULL,
  isi text NOT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_komentar_atem_tiket (tiket_id),
  KEY idx_komentar_atem_user (user_id),
  CONSTRAINT fk_komentar_atem_tiket FOREIGN KEY (tiket_id) REFERENCES tiket_atem (id) ON DELETE CASCADE,
  CONSTRAINT fk_komentar_atem_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
