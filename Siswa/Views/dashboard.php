<?php
session_start();
require "../../DB/koneksi.php";
require "../../Middleware/auth.php";
batas_akses(3);

$sql = "SELECT 
    s.nama AS nama_siswa,
    r.nama_role,
    k.nama_kelas,
    
    COALESCE(rata_nilai.rata_rata_nilai, 0) AS rata_rata_nilai,
     
    (SELECT COUNT(e.id_keanggotaan) 
     FROM keanggotaan_ekskul e 
     WHERE e.siswa_nis = s.nis) AS jumlah_ekskul,
     
    ROUND(
        IFNULL(
            (SELECT COUNT(*) FROM absensi a WHERE a.siswa_nis = s.nis AND a.status = 'Hadir') 
            / 
            NULLIF((SELECT COUNT(*) FROM absensi a2 WHERE a2.siswa_nis = s.nis), 0) * 100
        , 0)
    , 2) AS persen_kehadiran

FROM siswa s
JOIN `user` u ON s.user_id_user = u.id_user
JOIN role r ON u.role_id_role = r.id_role
LEFT JOIN (
    SELECT nilai_mapel.siswa_nis, ROUND(AVG(nilai_mapel.nilai_akhir), 2) AS rata_rata_nilai
    FROM (
        SELECT ak2.siswa_nis, m2.kode_mapel,
            ROUND(
                COALESCE(AVG(CASE WHEN n2.jenis_nilai = 'Tugas' THEN n2.skor END), 0) * 0.3
                + COALESCE(MAX(CASE WHEN n2.jenis_nilai = 'UTS' THEN n2.skor END), 0) * 0.3
                + COALESCE(MAX(CASE WHEN n2.jenis_nilai = 'UAS' THEN n2.skor END), 0) * 0.4,
            2) AS nilai_akhir
        FROM anggota_kelas ak2
        JOIN kelas k2 ON ak2.kelas_id_kelas = k2.id_kelas
        JOIN tahun_ajaran t2 ON k2.tahun_ajaran_id_tahun = t2.id_tahun
        JOIN jadwal j2 ON k2.id_kelas = j2.kelas_id_kelas
        JOIN mata_pelajaran m2 ON j2.mapel_kode_mapel = m2.kode_mapel
        LEFT JOIN nilai n2 ON n2.siswa_nis = ak2.siswa_nis AND n2.mapel_kode_mapel = m2.kode_mapel
        WHERE t2.status_aktif = 'Y'
        GROUP BY ak2.siswa_nis, m2.kode_mapel
    ) nilai_mapel
    GROUP BY nilai_mapel.siswa_nis
) rata_nilai ON rata_nilai.siswa_nis = s.nis
LEFT JOIN anggota_kelas ak ON s.nis = ak.siswa_nis
    AND ak.kelas_id_kelas IN (
        SELECT k_active.id_kelas
        FROM kelas k_active
        JOIN tahun_ajaran t_active ON k_active.tahun_ajaran_id_tahun = t_active.id_tahun
        WHERE t_active.status_aktif = 'Y'
    )
LEFT JOIN kelas k ON ak.kelas_id_kelas = k.id_kelas
    AND k.tahun_ajaran_id_tahun = (
        SELECT id_tahun FROM tahun_ajaran WHERE status_aktif = 'Y' LIMIT 1
    )
WHERE s.nis = ?;
";

$nis_siswa = $_SESSION['nis'];

$stmt_s = $pdo->prepare($sql);
$stmt_s->execute([$nis_siswa]);
$stats =$stmt_s->fetch();

$hari_inggris = date('l');
$daftar_hari = [
    'Sunday' => 'Minggu',
    'Monday' => 'Senin',
    'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu'
];
$hari_ini = $daftar_hari[$hari_inggris];

$sql_jadwal = "SELECT 
    j.jam_mulai, 
    j.jam_selesai, 
    m.nama_mapel, 
    k.nama_kelas
FROM jadwal j
JOIN mata_pelajaran m ON j.mapel_kode_mapel = m.kode_mapel
JOIN kelas k ON j.kelas_id_kelas = k.id_kelas
JOIN anggota_kelas ak ON k.id_kelas = ak.kelas_id_kelas
JOIN tahun_ajaran t ON k.tahun_ajaran_id_tahun = t.id_tahun
WHERE ak.siswa_nis = ? AND j.hari = ? AND t.status_aktif = 'Y'
ORDER BY j.jam_mulai ASC;";

$stmt_j = $pdo->prepare($sql_jadwal);
$stmt_j->execute([$nis_siswa, $hari_ini]);
$jadwal = $stmt_j->fetchAll();

$page = 'dash';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Siswa</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include '../../components/sidebar.php'; ?>
    <div class="content">
        <?php include '../../components/navbar.php'; ?>
        <h1 class="titel">Selamat Datang, <?= htmlspecialchars($stats['nama_siswa'] ?? 'Siswa') ?></h1>

        <div class ="stats">
            <div class = "box">
                <div class = "ikon" style="background-color: #6083E1;">
                    <img src="../../public/img/ikonkelas.svg" alt="">
                </div>
                <div class="teks">
                    <p class = "boxttl">Kelas</p>
                    <p class="databx"><?= htmlspecialchars($stats['nama_kelas'] ?? 'Belum ditempatkan') ?></p>
                </div>
            </div>
            <div class = "box">
                <div class = "ikon" style="background-color: #23AA6D;">
                    <img src="../../public/img/ikonkehadiran.svg" alt="">
                </div>
                <div class="teks">
                    <p class = "boxttl">Total Kehadiran</p>
                    <p class="databx"><?= htmlspecialchars($stats['persen_kehadiran'] ?? '0') ?> %</p>
                </div>
            </div>
            <div class = "box">
                <div class = "ikon" style="background-color: #315DD2;">
                    <img src="../../public/img/ikonrata.svg" alt="">
                </div>
                <div class="teks">
                    <p class = "boxttl">Rata-rata Nilai</p>
                    <p class="databx"><?= htmlspecialchars($stats['rata_rata_nilai'] ?? '0') ?></p>
                </div>
            </div>
            <div class = "box">
                <div class = "ikon" style="background-color: #9950C7;">
                    <img src="../../public/img/ikonkelas.svg" alt="">
                </div>
                <div class="teks">
                    <p class = "boxttl">Ekstrakurikuler</p>
                    <p class="databx"><?= htmlspecialchars($stats['jumlah_ekskul'] ?? '0') ?></p>
                </div>
            </div>

        </div>

        <div class="jadwal">
        <h1>Jadwal Hari ini</h1>
                <table>
                    <tbody>
                        <?php if ($jadwal): $no = 1; foreach ($jadwal as $row):?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= htmlspecialchars($row['jam_mulai']) ?> - <?= htmlspecialchars($row['jam_selesai']) ?></td>
                            <td><?= htmlspecialchars($row['nama_mapel']) ?></td>
                            <td><?= htmlspecialchars($row['nama_kelas']) ?></td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="4">Tidak ada jadwal pada hari ini.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
    </div>
</body>
</html>
