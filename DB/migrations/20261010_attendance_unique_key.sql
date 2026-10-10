ALTER TABLE absensi
    ADD CONSTRAINT uq_absensi_siswa_jadwal_tanggal
    UNIQUE (siswa_nis, jadwal_id_jadwal, tanggal);
