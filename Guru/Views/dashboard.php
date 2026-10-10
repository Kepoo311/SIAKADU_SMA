<?php
session_start();
require "../../DB/koneksi.php";
require "../../Middleware/auth.php";
batas_akses(2);

$nip_guru = $_SESSION['nip']; 

$stmt_guru = $pdo->prepare("SELECT nip, nama, status_kepegawaian, no_hp, email FROM guru WHERE nip = ?");
$stmt_guru->execute([$nip_guru]);
$guru = $stmt_guru->fetch();

if (!$guru) {
    die("Data guru tidak ditemukan.");
}
$hari_inggris = date('l');
$daftar_hari = [
    'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 
    'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
];
$hari_ini = $daftar_hari[$hari_inggris];

$sql_jadwal = "SELECT j.jam_mulai, j.jam_selesai, m.nama_mapel, k.nama_kelas
               FROM jadwal j
               JOIN mata_pelajaran m ON j.mapel_kode_mapel = m.kode_mapel
               JOIN kelas k ON j.kelas_id_kelas = k.id_kelas
               WHERE j.guru_nip = ? AND j.hari = ?
               ORDER BY j.jam_mulai ASC";
$stmt_jadwal = $pdo->prepare($sql_jadwal);
$stmt_jadwal->execute([$nip_guru, $hari_ini]);
$jadwal_hari_ini = $stmt_jadwal->fetchAll();

$stmt_stat = $pdo->prepare("SELECT COUNT(DISTINCT kelas_id_kelas) AS total_kelas FROM jadwal WHERE guru_nip = ?");
$stmt_stat->execute([$nip_guru]);
$stat_kelas = $stmt_stat->fetch();

$stmt_mapel = $pdo->prepare("SELECT COUNT(DISTINCT mapel_kode_mapel) AS total_mapel FROM jadwal WHERE guru_nip = ?");
$stmt_mapel->execute([$nip_guru]);
$stat_mapel = $stmt_mapel->fetch();

$stmt_siswa = $pdo->prepare("
    SELECT COUNT(DISTINCT ak.siswa_nis) AS total_siswa 
    FROM jadwal j 
    JOIN anggota_kelas ak ON j.kelas_id_kelas = ak.kelas_id_kelas 
    WHERE j.guru_nip = ?
");
$stmt_siswa->execute([$nip_guru]);
$stat_siswa = $stmt_siswa->fetch();

$sql = "SELECT 
    s.nama AS nama_guru,
    r.nama_role

FROM guru s
JOIN `user` u ON s.user_id_user = u.id_user
JOIN role r ON u.role_id_role = r.id_role
WHERE s.nip = ?;
";

$stmt_s = $pdo->prepare($sql);
$stmt_s->execute([$nip_guru]);
$stats =$stmt_s->fetch();

$page = "dash";

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dasbor Guru - SIAKAD</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include '../../components/sidebarGuru.php'; ?>
    <div class="content">
        <?php include '../../components/navbarGuru.php'; ?>
        <h1 class = "titel">Selamat Datang, <?php echo $stats['nama_guru'];?></h1>

        <div class ="stats">
            <div class = "box">
                <div class = "ikon" style="background-color: #6083E1;">
                    <img src="../../public/img/guru-total-kelas.svg" alt="Ikon total kelas">
                </div>
                <div class="teks">
                    <p class = "boxttl">Total Kelas</p>
                    <p class="databx"><?php echo $stat_kelas['total_kelas'];?></p>
                </div>
            </div>
            <div class = "box">
                <div class = "ikon" style="background-color: #23AA6D;">
                    <img src="../../public/img/guru-mata-pelajaran.svg" alt="Ikon mata pelajaran">
                </div>
                <div class="teks">
                    <p class = "boxttl">Mata Pelajaran</p>
                    <p class="databx"><?php echo $stat_mapel['total_mapel'];?></p>
                </div>
            </div>
            <div class = "box">
                <div class = "ikon" style="background-color: #315DD2;">
                    <img src="../../public/img/guru-total-siswa.svg" alt="Ikon total siswa">
                </div>
                <div class="teks">
                    <p class = "boxttl">Total Siswa</p>
                    <p class="databx"><?php echo $stat_siswa['total_siswa'];?></p>
                </div>
            </div>
            <div class = "box">
                <div class = "ikon" style="background-color: #9950C7;">
                    <img src="../../public/img/guru-jadwal-hari-ini.svg" alt="Ikon jadwal hari ini">
                </div>
                <div class="teks">
                    <p class = "boxttl">hari ini(<?= $hari_ini ?>)</p>
                    <p class="databx"><?= count($jadwal_hari_ini) ?> Sesi</p>
                </div>
            </div>

        </div>

        <div class="jadwal">
        <h1>Jadwal Hari ini</h1>
                <table>
                    <tbody>
                    <?php if (count($jadwal_hari_ini) > 0): $no = 1; foreach ($jadwal_hari_ini as $row): 
                        $jam = date('H:i', strtotime($row['jam_mulai'])) . ' - ' . date('H:i', strtotime($row['jam_selesai']));
                    ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= $jam ?></td>
                        <td><?= htmlspecialchars($row['nama_mapel']) ?></td>
                        <td><?= htmlspecialchars($row['nama_kelas']) ?></td>
                        <td>
                            <a href="absensi.php">Isi Absen</a>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr>
                        <td colspan="5">Tidak ada jadwal mengajar hari ini. Nikmati waktu istirahat Anda!</td>
                    </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
    </div>

    <div>

</body>
</html> 