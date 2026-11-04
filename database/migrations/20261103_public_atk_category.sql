ALTER TABLE atk_pengajuan
  ADD COLUMN atk_kategori_id INT UNSIGNED NULL AFTER pemohon_divisi,
  ADD INDEX idx_atk_pengajuan_kategori (atk_kategori_id);
