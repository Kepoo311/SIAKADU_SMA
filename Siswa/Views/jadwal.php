<?php 
session_start();
require "../../DB/koneksi.php";
require "../../Middleware/auth.php";
batas_akses(3);

$nis_siswa = $_SESSION['nis'];

$sql_jadwal = "SELECT 
    j.jam_mulai, 
    j.jam_selesai, 
    m.nama_mapel, 
    k.nama_kelas,
    j.hari
FROM jadwal j
JOIN mata_pelajaran m ON j.mapel_kode_mapel = m.kode_mapel
JOIN kelas k ON j.kelas_id_kelas = k.id_kelas
JOIN anggota_kelas ak ON k.id_kelas = ak.kelas_id_kelas
JOIN tahun_ajaran t ON k.tahun_ajaran_id_tahun = t.id_tahun
WHERE ak.siswa_nis = ? AND t.status_aktif = 'Y'
ORDER BY 
    FIELD(j.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'), 
    j.jam_mulai ASC;";

$stmt_j = $pdo->prepare($sql_jadwal);
$stmt_j->execute([$nis_siswa]);
$jadwal = $stmt_j->fetchAll();

$sql = "SELECT 
    s.nama AS nama_siswa,
    r.nama_role

FROM siswa s
JOIN `user` u ON s.user_id_user = u.id_user
JOIN role r ON u.role_id_role = r.id_role
WHERE s.nis = ?;
";

$stmt_s = $pdo->prepare($sql);
$stmt_s->execute([$nis_siswa]);
$stats =$stmt_s->fetch();

$page = "jdwl";

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Siakadu | Jadwal</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include '../../components/sidebar.php'; ?>
    <div class="content">
        <?php include '../../components/navbar.php'; ?>
        <h1 class = "titel">Jadwal Pelajaran</h1>
                    <div class="tabel_data">
                <div class="table-tools">
                    <input type="search" data-table-search="student-schedule-list" placeholder="Cari hari, waktu, mata pelajaran, atau kelas..." aria-label="Cari jadwal berdasarkan hari, waktu, mata pelajaran, atau kelas">
                </div>
                <p class="table-search-results" data-table-results="student-schedule-list" aria-live="polite"></p>
                <p class="table-search-empty" data-table-empty="student-schedule-list" hidden>Tidak ada jadwal yang sesuai dengan pencarian.</p>
                <table class="tbl_dkls" id="student-schedule-list">
                    <thead class ="thead_kls">
                        <tr>
                            <th>NO</th> 
                            <th>HARI</th>
                            <th>WAKTU</th>
                            <th>NAMA MAPEL</th>
                            <th>NAMA KELAS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($jadwal): $no=1; foreach ($jadwal as $row):?>
                        <tr data-search="<?= htmlspecialchars(implode(' ', [$row['hari'], $row['jam_mulai'], $row['jam_selesai'], $row['nama_mapel'], $row['nama_kelas']]), ENT_QUOTES, 'UTF-8') ?>">
                            <td><?= $no++;?></td> 
                            <td><?= htmlspecialchars($row['hari']) ?></td>  
                            <td><?= htmlspecialchars($row['jam_mulai']) ?> - <?= htmlspecialchars($row['jam_selesai']) ?></td>  
                            <td><?= htmlspecialchars($row['nama_mapel']) ?></td>
                            <td><?= htmlspecialchars($row['nama_kelas']) ?></td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr data-empty-state><td colspan="5">Tidak ada jadwal untuk tahun ajaran aktif.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
    </div>
<script src="../../Views/table-search.js" defer></script>
</body>
</html>