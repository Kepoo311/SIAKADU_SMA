-- Fresh-install schema for SIAKADU.
-- Compatible collation for MySQL 8 and MariaDB; no application/user data is included.
SET NAMES utf8mb4;

CREATE TABLE `role` (
  `id_role` int NOT NULL,
  `nama_role` varchar(50) NOT NULL,
  PRIMARY KEY (`id_role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user` (
  `id_user` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role_id_role` int NOT NULL,
  PRIMARY KEY (`id_user`),
  KEY `user_role_fk` (`role_id_role`),
  CONSTRAINT `user_role_fk` FOREIGN KEY (`role_id_role`) REFERENCES `role` (`id_role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tahun_ajaran` (
  `id_tahun` int NOT NULL,
  `tahun` varchar(9) NOT NULL,
  `semester` varchar(20) NOT NULL,
  `tanggal_mulai` date NOT NULL,
  `tanggal_selesai` date NOT NULL,
  `status_aktif` varchar(1) NOT NULL,
  PRIMARY KEY (`id_tahun`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `jurusan` (
  `id_jurusan` int NOT NULL,
  `nama_jurusan` varchar(50) NOT NULL,
  `deskripsi` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_jurusan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `guru` (
  `nip` varchar(30) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `jenis_kelamin` varchar(1) NOT NULL,
  `status_kepegawaian` varchar(50) NOT NULL,
  `no_hp` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `alamat` varchar(255) DEFAULT NULL,
  `user_id_user` int NOT NULL,
  PRIMARY KEY (`nip`),
  UNIQUE KEY `user_id_user` (`user_id_user`),
  CONSTRAINT `guru_user_fk` FOREIGN KEY (`user_id_user`) REFERENCES `user` (`id_user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `orang_tua` (
  `id` int NOT NULL,
  `nama_ayah` varchar(100) NOT NULL,
  `nama_ibu` varchar(100) NOT NULL,
  `pekerjaan` varchar(100) NOT NULL,
  `alamat` varchar(255) NOT NULL,
  `no_hp` varchar(20) NOT NULL,
  `user_id_user` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id_user` (`user_id_user`),
  CONSTRAINT `orang_tua_user_fk` FOREIGN KEY (`user_id_user`) REFERENCES `user` (`id_user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `siswa` (
  `nis` varchar(20) NOT NULL,
  `nisn` varchar(20) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `jenis_kelamin` varchar(1) NOT NULL,
  `tanggal_lahir` date NOT NULL,
  `tempat_lahir` varchar(100) NOT NULL,
  `alamat` varchar(255) NOT NULL,
  `no_hp` varchar(20) DEFAULT NULL,
  `orang_tua_id` int NOT NULL,
  `tahun_masuk` varchar(4) NOT NULL,
  `status_siswa` varchar(20) NOT NULL,
  `user_id_user` int NOT NULL,
  PRIMARY KEY (`nis`),
  UNIQUE KEY `user_id_user` (`user_id_user`),
  KEY `siswa_orang_tua_fk` (`orang_tua_id`),
  CONSTRAINT `siswa_orang_tua_fk` FOREIGN KEY (`orang_tua_id`) REFERENCES `orang_tua` (`id`),
  CONSTRAINT `siswa_user_fk` FOREIGN KEY (`user_id_user`) REFERENCES `user` (`id_user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `kelas` (
  `id_kelas` int NOT NULL,
  `nama_kelas` varchar(50) NOT NULL,
  `tingkat` varchar(10) NOT NULL,
  `jurusan_id_jurusan` int NOT NULL,
  `guru_nip` varchar(30) NOT NULL,
  `tahun_ajaran_id_tahun` int NOT NULL,
  PRIMARY KEY (`id_kelas`),
  KEY `kelas_guru_fk` (`guru_nip`),
  KEY `kelas_jurusan_fk` (`jurusan_id_jurusan`),
  KEY `kelas_tahun_ajaran_fk` (`tahun_ajaran_id_tahun`),
  CONSTRAINT `kelas_guru_fk` FOREIGN KEY (`guru_nip`) REFERENCES `guru` (`nip`),
  CONSTRAINT `kelas_jurusan_fk` FOREIGN KEY (`jurusan_id_jurusan`) REFERENCES `jurusan` (`id_jurusan`),
  CONSTRAINT `kelas_tahun_ajaran_fk` FOREIGN KEY (`tahun_ajaran_id_tahun`) REFERENCES `tahun_ajaran` (`id_tahun`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `mata_pelajaran` (
  `kode_mapel` varchar(20) NOT NULL,
  `nama_mapel` varchar(100) NOT NULL,
  `kkm` int NOT NULL,
  `tahun_ajaran_id_tahun` int NOT NULL,
  PRIMARY KEY (`kode_mapel`),
  KEY `mapel_tahun_ajaran_fk` (`tahun_ajaran_id_tahun`),
  CONSTRAINT `mapel_tahun_ajaran_fk` FOREIGN KEY (`tahun_ajaran_id_tahun`) REFERENCES `tahun_ajaran` (`id_tahun`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ekstrakurikuler` (
  `id_ekskul` int NOT NULL,
  `nama_ekskul` varchar(100) NOT NULL,
  `hari_kegiatan` varchar(20) NOT NULL,
  `tempat` varchar(100) NOT NULL,
  `guru_nip` varchar(30) NOT NULL,
  PRIMARY KEY (`id_ekskul`),
  KEY `ekstrakurikuler_guru_fk` (`guru_nip`),
  CONSTRAINT `ekstrakurikuler_guru_fk` FOREIGN KEY (`guru_nip`) REFERENCES `guru` (`nip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `jadwal` (
  `id_jadwal` int NOT NULL,
  `hari` varchar(20) NOT NULL,
  `jam_mulai` time NOT NULL,
  `jam_selesai` time NOT NULL,
  `kelas_id_kelas` int NOT NULL,
  `guru_nip` varchar(30) NOT NULL,
  `mapel_kode_mapel` varchar(20) NOT NULL,
  PRIMARY KEY (`id_jadwal`),
  KEY `jadwal_guru_fk` (`guru_nip`),
  KEY `jadwal_kelas_fk` (`kelas_id_kelas`),
  KEY `jadwal_mapel_fk` (`mapel_kode_mapel`),
  CONSTRAINT `jadwal_guru_fk` FOREIGN KEY (`guru_nip`) REFERENCES `guru` (`nip`),
  CONSTRAINT `jadwal_kelas_fk` FOREIGN KEY (`kelas_id_kelas`) REFERENCES `kelas` (`id_kelas`),
  CONSTRAINT `jadwal_mapel_fk` FOREIGN KEY (`mapel_kode_mapel`) REFERENCES `mata_pelajaran` (`kode_mapel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `anggota_kelas` (
  `id_anggota` int NOT NULL AUTO_INCREMENT,
  `kelas_id_kelas` int NOT NULL,
  `siswa_nis` varchar(20) NOT NULL,
  PRIMARY KEY (`id_anggota`),
  KEY `anggota_kelas_kelas_fk` (`kelas_id_kelas`),
  KEY `anggota_kelas_siswa_fk` (`siswa_nis`),
  CONSTRAINT `anggota_kelas_kelas_fk` FOREIGN KEY (`kelas_id_kelas`) REFERENCES `kelas` (`id_kelas`),
  CONSTRAINT `anggota_kelas_siswa_fk` FOREIGN KEY (`siswa_nis`) REFERENCES `siswa` (`nis`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `keanggotaan_ekskul` (
  `id_keanggotaan` int NOT NULL,
  `tanggal_bergabung` date NOT NULL,
  `jabatan` varchar(50) NOT NULL,
  `predikat` varchar(5) NOT NULL,
  `siswa_nis` varchar(20) NOT NULL,
  `ekstrakurikuler_id_ekskul` int NOT NULL,
  PRIMARY KEY (`id_keanggotaan`),
  KEY `k_ekskul_ekstrakurikuler_fk` (`ekstrakurikuler_id_ekskul`),
  KEY `k_ekskul_siswa_fk` (`siswa_nis`),
  CONSTRAINT `k_ekskul_ekstrakurikuler_fk` FOREIGN KEY (`ekstrakurikuler_id_ekskul`) REFERENCES `ekstrakurikuler` (`id_ekskul`),
  CONSTRAINT `k_ekskul_siswa_fk` FOREIGN KEY (`siswa_nis`) REFERENCES `siswa` (`nis`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `nilai` (
  `id_nilai` int NOT NULL AUTO_INCREMENT,
  `jenis_nilai` varchar(50) NOT NULL,
  `skor` decimal(5,2) NOT NULL,
  `siswa_nis` varchar(20) NOT NULL,
  `mapel_kode_mapel` varchar(20) NOT NULL,
  PRIMARY KEY (`id_nilai`),
  KEY `nilai_mapel_fk` (`mapel_kode_mapel`),
  KEY `nilai_siswa_fk` (`siswa_nis`),
  CONSTRAINT `nilai_mapel_fk` FOREIGN KEY (`mapel_kode_mapel`) REFERENCES `mata_pelajaran` (`kode_mapel`),
  CONSTRAINT `nilai_siswa_fk` FOREIGN KEY (`siswa_nis`) REFERENCES `siswa` (`nis`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `rapor` (
  `id_rapor` int NOT NULL,
  `rata_rata` decimal(5,2) NOT NULL,
  `peringkat` int NOT NULL,
  `status_kenaikan` varchar(50) NOT NULL,
  `catatan_wali_kelas` varchar(255) DEFAULT NULL,
  `siswa_nis` varchar(20) NOT NULL,
  `tahun_ajaran_id_tahun` int NOT NULL,
  PRIMARY KEY (`id_rapor`),
  KEY `rapor_siswa_fk` (`siswa_nis`),
  KEY `rapor_tahun_ajaran_fk` (`tahun_ajaran_id_tahun`),
  CONSTRAINT `rapor_siswa_fk` FOREIGN KEY (`siswa_nis`) REFERENCES `siswa` (`nis`),
  CONSTRAINT `rapor_tahun_ajaran_fk` FOREIGN KEY (`tahun_ajaran_id_tahun`) REFERENCES `tahun_ajaran` (`id_tahun`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `absensi` (
  `id_absensi` int NOT NULL AUTO_INCREMENT,
  `tanggal` date NOT NULL,
  `status` varchar(20) NOT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `siswa_nis` varchar(20) NOT NULL,
  `jadwal_id_jadwal` int NOT NULL,
  PRIMARY KEY (`id_absensi`),
  UNIQUE KEY `uq_absensi_siswa_jadwal_tanggal` (`siswa_nis`,`jadwal_id_jadwal`,`tanggal`),
  KEY `absensi_jadwal_fk` (`jadwal_id_jadwal`),
  CONSTRAINT `absensi_jadwal_fk` FOREIGN KEY (`jadwal_id_jadwal`) REFERENCES `jadwal` (`id_jadwal`),
  CONSTRAINT `absensi_siswa_fk` FOREIGN KEY (`siswa_nis`) REFERENCES `siswa` (`nis`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `role` (`id_role`, `nama_role`) VALUES
  (1, 'Admin'),
  (2, 'Guru'),
  (3, 'Siswa');
