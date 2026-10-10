<?php
session_start();
require "../../DB/koneksi.php";
require "../../Middleware/auth.php";
batas_akses(2);

// Pastikan NIP sudah ada di session
$nip_guru = $_SESSION['nip']; 

// Eksekusi Kueri Jadwal Lengkap
$sql_jadwal_lengkap = "SELECT j.hari, j.jam_mulai, j.jam_selesai, m.nama_mapel, k.nama_kelas
                        FROM jadwal j
                        JOIN mata_pelajaran m ON j.mapel_kode_mapel = m.kode_mapel
                        JOIN kelas k ON j.kelas_id_kelas = k.id_kelas
                        WHERE j.guru_nip = ?
                        ORDER BY 
                            FIELD(j.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'),
                            j.jam_mulai ASC";

$stmt = $pdo->prepare($sql_jadwal_lengkap);
$stmt->execute([$nip_guru]);
$jadwal_lengkap = $stmt->fetchAll();

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

$page = "jdwl";

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jadwal Mengajar - SIAKAD Guru</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
        <?php include '../../components/sidebarGuru.php'; ?>
    <div class="content">
        <?php include '../../components/navbarGuru.php'; ?>
        <h1 class = "titel">Jadwal Pelajaran</h1>
                    <div class="tabel_data">
                <div class="table-tools">
                    <input type="search" data-table-search="teacher-schedule-list" placeholder="Cari hari, mata pelajaran, atau kelas..." aria-label="Cari jadwal berdasarkan hari, mata pelajaran, atau kelas">
                </div>
                <p class="table-search-results" data-table-results="teacher-schedule-list" aria-live="polite"></p>
                <p class="table-search-empty" data-table-empty="teacher-schedule-list" hidden>Tidak ada jadwal yang sesuai dengan pencarian.</p>
                <table class="tbl_dkls" id="teacher-schedule-list">
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
                        <?php if ($jadwal_lengkap): $no=1; foreach ($jadwal_lengkap as $row):
                            $jam = date('H:i', strtotime($row['jam_mulai'])) . ' - ' . date('H:i', strtotime($row['jam_selesai']));
                            ?>
                        <tr data-search="<?= htmlspecialchars(implode(' ', [$row['hari'], $jam, $row['nama_mapel'], $row['nama_kelas']]), ENT_QUOTES, 'UTF-8') ?>">
                            <td><?= $no++;?></td> 
                            <td><?= $row['hari'] ?></td>  
                            <td><?= $jam ?></td>  
                            <td><?= $row['nama_mapel'] ?></td>
                            <td><?= $row['nama_kelas'] ?></td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr data-empty-state><td colspan="5">Belum ada jadwal mengajar.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
    </div>

<script src="../../Views/table-search.js" defer></script>
</body>
</html>