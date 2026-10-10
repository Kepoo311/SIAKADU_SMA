<?php 
session_start();
require "../../DB/koneksi.php";
require "../../Middleware/auth.php";

batas_akses(3);

$nis_siswa = $_SESSION['nis'];

$sql_stats = "SELECT 
                SUM(CASE WHEN status = 'Hadir' THEN 1 ELSE 0 END) AS total_hadir,
                SUM(CASE WHEN status = 'Izin' THEN 1 ELSE 0 END) AS total_izin,
                SUM(CASE WHEN status = 'Sakit' THEN 1 ELSE 0 END) AS total_sakit,
                SUM(CASE WHEN status = 'Alfa' THEN 1 ELSE 0 END) AS total_alfa
              FROM absensi WHERE siswa_nis = ?";
$stmt_stats = $pdo->prepare($sql_stats);
$stmt_stats->execute([$nis_siswa]);
$stats_abs = $stmt_stats->fetch();

$sql_history = "SELECT a.tanggal, m.nama_mapel, a.status, a.keterangan
                FROM absensi a
                JOIN jadwal j ON a.jadwal_id_jadwal = j.id_jadwal
                JOIN mata_pelajaran m ON j.mapel_kode_mapel = m.kode_mapel
                WHERE a.siswa_nis = ?
                ORDER BY a.tanggal DESC, j.jam_mulai ASC";
$stmt_hist = $pdo->prepare($sql_history);
$stmt_hist->execute([$nis_siswa]);
$riwayat_absensi = $stmt_hist->fetchAll();

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

$page = "absn";

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Siakadu | Absensi</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
      <?php include '../../components/sidebar.php'; ?>
    <div class="content">
            <?php include '../../components/navbar.php'; ?>
            <h1 class = "titel">Riwayat Absensi</h1>

            <div class="absStats">

              <div class="statsBx">
                <div class = "ikon" style="background-color: #DBF4CE;">
                    <img src="../../public/img/hadir.svg" alt="">
                </div>
                <div class="statsText">
                  <p class = "stitel">Hadir</p>
                  <p><?php echo $stats_abs['total_hadir'];?></p>
                </div>
              </div>
              <div class="statsBx">
                <div class = "ikon" style="background-color: #A2B8EB;">
                    <img src="../../public/img/izin.svg" alt="">
                </div>
                <div class="statsText">
                  <p class = "stitel">Izin</p>
                  <p><?php echo $stats_abs['total_izin'];?></p>
                </div>
              </div>
              <div class="statsBx">
                <div class = "ikon" style="background-color: #EDA1A1;">
                    <img src="../../public/img/sakit.svg" alt="">
                </div>
                <div class="statsText">
                  <p class = "stitel">Sakit</p>
                  <p><?php echo $stats_abs['total_sakit'];?></p>
                </div>
              </div>
              <div class="statsBx">
                <div class = "ikon" style="background-color: #F2B3F2;">
                    <img src="../../public/img/alfa.svg" alt="">
                </div>
                <div class="statsText">
                  <p class = "stitel">Alfa</p>
                  <p><?php echo $stats_abs['total_alfa'];?></p>
                </div>
              </div>

              
            </div>
            <div class="tabel_data">
              <div class="table-tools">
                <input type="search" data-table-search="student-attendance-list" placeholder="Cari tanggal, mata pelajaran, status, atau keterangan..." aria-label="Cari riwayat absensi berdasarkan tanggal, mata pelajaran, status, atau keterangan">
              </div>
              <p class="table-search-results" data-table-results="student-attendance-list" aria-live="polite"></p>
              <p class="table-search-empty" data-table-empty="student-attendance-list" hidden>Tidak ada riwayat absensi yang sesuai dengan pencarian.</p>
              <table class="tbl_dkls" id="student-attendance-list">
                  <thead class ="thead_kls">
                      <tr>
                          <th>NO</th> 
                          <th>Tanggal</th>
                          <th>Mata Pelajaran</th>
                          <th>Status</th>
                          <th>Keterangan</th>
                      </tr>
                  </thead>
                  <tbody>
                      <?php if ($riwayat_absensi): $no=1; foreach ($riwayat_absensi as $row):?>
                      <tr data-search="<?= htmlspecialchars(implode(' ', [$row['tanggal'], $row['nama_mapel'], $row['status'], $row['keterangan'] ?? '']), ENT_QUOTES, 'UTF-8') ?>">
                          <td><?= $no++;?></td> 
                          <td><?= $row['tanggal'] ?></td>  
                          <td><?= $row['nama_mapel'] ?></td>  
                          <td><p class ="stsIKON"><?= $row['status'] ?></p></td>
                          <td><?= $row['keterangan'] ?></td>
                      </tr>
                      <?php endforeach; else: ?>
                      <tr data-empty-state><td colspan="5">Belum ada riwayat absensi.</td></tr>
                      <?php endif; ?>
                  </tbody>
              </table>
          </div>
    </div>
<script src="../../Views/table-search.js" defer></script>
</body>
</html>