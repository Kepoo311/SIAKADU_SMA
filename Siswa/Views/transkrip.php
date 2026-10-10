<?php
session_start();
require "../../DB/koneksi.php";
require "../../Middleware/auth.php";
batas_akses(3);
$nis_siswa = $_SESSION['nis'];

$sql_nilai = "SELECT 
    m.nama_mapel, 
    m.kkm,
    ROUND(IFNULL(AVG(CASE WHEN n.jenis_nilai = 'Tugas' THEN n.skor END), 0), 2) AS nilai_tugas,
    MAX(CASE WHEN n.jenis_nilai = 'UTS' THEN n.skor ELSE 0 END) AS nilai_uts,
    MAX(CASE WHEN n.jenis_nilai = 'UAS' THEN n.skor ELSE 0 END) AS nilai_uas
FROM anggota_kelas ak
JOIN kelas k ON ak.kelas_id_kelas = k.id_kelas
JOIN tahun_ajaran t ON k.tahun_ajaran_id_tahun = t.id_tahun
JOIN jadwal j ON k.id_kelas = j.kelas_id_kelas
JOIN mata_pelajaran m ON j.mapel_kode_mapel = m.kode_mapel
LEFT JOIN nilai n ON m.kode_mapel = n.mapel_kode_mapel AND n.siswa_nis = ak.siswa_nis
WHERE ak.siswa_nis = ? 
  AND t.status_aktif = 'Y'
GROUP BY m.kode_mapel, m.nama_mapel, m.kkm
ORDER BY m.nama_mapel ASC;";

$stmt = $pdo->prepare($sql_nilai);
$stmt->execute([$nis_siswa]);
$data_nilai = $stmt->fetchAll();

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

$page = "trnskrp";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Siakadu | Transkrip</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include '../../components/sidebar.php'; ?>
    <div class="content">
        <?php include '../../components/navbar.php'; ?>
        <h1 class = "titel">Transkrip Nilai</h1>

                    <div class="tabel_data">
              <table class = "tbl_dkls" id="tbl_dkls">
                  <thead class ="thead_kls">
                    <!-- Baris Header Pertama -->
                    <tr>
                        <th rowspan="2">No</th>
                        <th rowspan="2">Mata Pelajaran</th>
                        <th rowspan="2">KKM</th>
                        <th colspan="4" class="col_nilai">Nilai</th>
                    </tr>
                    <!-- Baris Header Kedua (Anak dari "Nilai") -->
                    <tr>
                        <th>Tugas</th>
                        <th>UTS</th>
                        <th>UAS</th>
                        <th>Akhir</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php 
                    if (count($data_nilai) > 0):
                        $no = 1;
                        foreach ($data_nilai as $row): 
                            // Kalkulasi Nilai Akhir (Rata-rata dari Tugas, UTS, UAS)
                            $nilai_akhir = ($row['nilai_tugas'] + $row['nilai_uts'] + $row['nilai_uas']) / 3;
                            
                            // Formatting agar tidak ada desimal berlebih, atau tampil '-' jika nilai masih 0
                            $tugas = $row['nilai_tugas'] > 0 ? round($row['nilai_tugas']) : '-';
                            $uts   = $row['nilai_uts'] > 0 ? round($row['nilai_uts']) : '-';
                            $uas   = $row['nilai_uas'] > 0 ? round($row['nilai_uas']) : '-';
                            $akhir = $nilai_akhir > 0 ? round($nilai_akhir) : '-';
                    ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td>
                            <?= htmlspecialchars($row['nama_mapel']) ?>
                        </td>
                        <td><?= htmlspecialchars($row['kkm']) ?></td>
                        <td><?= $tugas ?></td>
                        <td><?= $uts ?></td>
                        <td><?= $uas ?></td>
                        <td><?= $akhir ?></td>
                    </tr>
                    <?php 
                        endforeach;
                    else: 
                    ?>
                    <tr>
                        <td colspan="7" style="padding: 20px; color: #888; border: 1px solid #eef2f5;">Data nilai belum tersedia.</td>
                    </tr>
                    <?php endif; ?>
                  </tbody>
              </table>
          </div>
    </div>
</body>
</html>