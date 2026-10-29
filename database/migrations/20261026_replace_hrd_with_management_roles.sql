-- Replace existing HRD accounts with Management Umum and add Wadir Umum.
ALTER TABLE users
  MODIFY COLUMN `role` ENUM(
    'user',
    'teknisi',
    'teknisi_ipsrs',
    'teknisi_atem',
    'admin',
    'hrd',
    'management_umum',
    'wadir_umum',
    'keuangan',
    'akreditasi'
  ) NOT NULL DEFAULT 'user';

UPDATE users
SET role = 'management_umum'
WHERE role = 'hrd';

ALTER TABLE users
  MODIFY COLUMN `role` ENUM(
    'user',
    'teknisi',
    'teknisi_ipsrs',
    'teknisi_atem',
    'admin',
    'management_umum',
    'wadir_umum',
    'keuangan',
    'akreditasi'
  ) NOT NULL DEFAULT 'user';
