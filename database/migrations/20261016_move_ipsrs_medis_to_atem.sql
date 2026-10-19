-- Move medical IPSRS categories and tickets into ATEM.
-- Run once after 20261016_create_atem_tickets.sql and take a database backup first.

ALTER TABLE tiket_atem
  ADD COLUMN source_ipsrs_id int unsigned DEFAULT NULL,
  ADD COLUMN rating tinyint(1) DEFAULT NULL,
  ADD COLUMN legacy_user_id int unsigned DEFAULT NULL,
  ADD COLUMN legacy_teknisi_id int unsigned DEFAULT NULL,
  MODIFY COLUMN user_id int unsigned DEFAULT NULL,
  ADD UNIQUE KEY uq_tiket_atem_source_ipsrs (source_ipsrs_id);

ALTER TABLE tiket_atem_log
  ADD COLUMN legacy_user_id int unsigned DEFAULT NULL;

ALTER TABLE komentar_atem
  ADD COLUMN legacy_user_id int unsigned DEFAULT NULL,
  MODIFY COLUMN user_id int unsigned DEFAULT NULL;

CREATE TABLE tiket_atem_foto (
  id int unsigned NOT NULL AUTO_INCREMENT,
  tiket_id int unsigned NOT NULL,
  user_id int unsigned DEFAULT NULL,
  legacy_user_id int unsigned DEFAULT NULL,
  nama_file varchar(255) NOT NULL,
  path varchar(500) NOT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tiket_atem_foto_tiket (tiket_id),
  KEY idx_tiket_atem_foto_user (user_id),
  CONSTRAINT fk_tiket_atem_foto_tiket FOREIGN KEY (tiket_id) REFERENCES tiket_atem (id) ON DELETE CASCADE,
  CONSTRAINT fk_tiket_atem_foto_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

START TRANSACTION;

INSERT IGNORE INTO kategori_atem (nama, deskripsi, icon, sla_jam, sla_respon_jam, aktif)
SELECT nama, deskripsi, icon, sla_jam, sla_respon_jam, 1
FROM kategori_ipsrs
WHERE jenis = 'Medis'
   OR id IN (
       SELECT DISTINCT kategori_id
       FROM tiket_ipsrs
       WHERE jenis_tiket = 'Medis' AND kategori_id IS NOT NULL
   );

INSERT INTO tiket_atem (
  nomor, judul, deskripsi, kategori_id, prioritas, status, user_id, teknisi_id,
  lokasi, aset_info, waktu_submit, waktu_proses, waktu_selesai,
  durasi_respon_menit, durasi_selesai_menit, catatan_teknisi, created_at,
  updated_at, source_ipsrs_id, rating, legacy_user_id, legacy_teknisi_id
)
SELECT
  t.nomor,
  t.judul,
  t.deskripsi,
  ka.id,
  t.prioritas,
  t.status,
  requester.id,
  technician.id,
  t.lokasi,
  NULLIF(CONCAT_WS(' | ',
      NULLIF(a.no_inventaris, ''),
      NULLIF(a.nama_aset, ''),
      NULLIF(a.merek, ''),
      NULLIF(a.model_aset, ''),
      NULLIF(a.serial_number, '')
  ), ''),
  t.waktu_submit,
  t.waktu_proses,
  t.waktu_selesai,
  t.durasi_respon_menit,
  t.durasi_selesai_menit,
  t.catatan_teknisi,
  t.created_at,
  t.updated_at,
  t.id,
  t.rating,
  CASE WHEN requester.id IS NULL THEN t.user_id ELSE NULL END,
  CASE WHEN t.teknisi_id IS NOT NULL AND technician.id IS NULL THEN t.teknisi_id ELSE NULL END
FROM tiket_ipsrs t
LEFT JOIN kategori_ipsrs ki ON ki.id = t.kategori_id
LEFT JOIN kategori_atem ka ON ka.nama = ki.nama
LEFT JOIN aset_ipsrs a ON a.id = t.aset_id
LEFT JOIN users requester ON requester.id = t.user_id
LEFT JOIN users technician ON technician.id = t.teknisi_id
WHERE t.jenis_tiket = 'Medis' OR ki.jenis = 'Medis';

CREATE TEMPORARY TABLE tmp_ipsrs_atem_ticket_map (
  ipsrs_id int unsigned NOT NULL PRIMARY KEY,
  atem_id int unsigned NOT NULL
) ENGINE=InnoDB;

INSERT INTO tmp_ipsrs_atem_ticket_map (ipsrs_id, atem_id)
SELECT t.id, atem.id
FROM tiket_ipsrs t
JOIN tiket_atem atem ON atem.source_ipsrs_id = t.id
LEFT JOIN kategori_ipsrs ki ON ki.id = t.kategori_id
WHERE t.jenis_tiket = 'Medis' OR ki.jenis = 'Medis';

INSERT INTO tiket_atem_log (tiket_id, user_id, status_dari, status_ke, keterangan, created_at, legacy_user_id)
SELECT m.atem_id, actor.id, l.status_dari, l.status_ke, l.keterangan, l.created_at,
       CASE WHEN l.user_id IS NOT NULL AND actor.id IS NULL THEN l.user_id ELSE NULL END
FROM tiket_ipsrs_log l
JOIN tmp_ipsrs_atem_ticket_map m ON m.ipsrs_id = l.tiket_id
LEFT JOIN users actor ON actor.id = l.user_id;

INSERT INTO komentar_atem (tiket_id, user_id, isi, created_at, legacy_user_id)
SELECT m.atem_id, actor.id, c.isi, c.created_at,
       CASE WHEN actor.id IS NULL THEN c.user_id ELSE NULL END
FROM komentar_ipsrs c
JOIN tmp_ipsrs_atem_ticket_map m ON m.ipsrs_id = c.tiket_id
LEFT JOIN users actor ON actor.id = c.user_id;

INSERT INTO tiket_atem_foto (tiket_id, user_id, nama_file, path, created_at, legacy_user_id)
SELECT m.atem_id, uploader.id, f.nama_file, f.path, f.created_at,
       CASE WHEN uploader.id IS NULL THEN f.user_id ELSE NULL END
FROM tiket_ipsrs_foto f
JOIN tmp_ipsrs_atem_ticket_map m ON m.ipsrs_id = f.tiket_id
LEFT JOIN users uploader ON uploader.id = f.user_id;

DELETE l FROM tiket_ipsrs_log l
JOIN tmp_ipsrs_atem_ticket_map m ON m.ipsrs_id = l.tiket_id;

DELETE c FROM komentar_ipsrs c
JOIN tmp_ipsrs_atem_ticket_map m ON m.ipsrs_id = c.tiket_id;

DELETE f FROM tiket_ipsrs_foto f
JOIN tmp_ipsrs_atem_ticket_map m ON m.ipsrs_id = f.tiket_id;

DELETE t FROM tiket_ipsrs t
JOIN tmp_ipsrs_atem_ticket_map m ON m.ipsrs_id = t.id;

DELETE FROM kategori_ipsrs WHERE jenis = 'Medis';

DROP TEMPORARY TABLE tmp_ipsrs_atem_ticket_map;

COMMIT;
