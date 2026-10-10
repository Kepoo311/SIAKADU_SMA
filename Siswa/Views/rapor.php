<?php
session_start();
require "../../DB/koneksi.php";
require "../../Middleware/auth.php";
batas_akses(3);
$nis_siswa = $_SESSION['nis'];

// 1. Ambil Data Widget Rapor
$stmt_r = $pdo->prepare("SELECT r.rata_rata, r.peringkat, r.status_kenaikan, 
                         (SELECT COUNT(*) FROM anggota_kelas ak2 WHERE ak2.kelas_id_kelas = ak.kelas_id_kelas) AS total_siswa 
                         FROM rapor r 
                         JOIN tahun_ajaran t ON r.tahun_ajaran_id_tahun = t.id_tahun 
                         JOIN anggota_kelas ak ON r.siswa_nis = ak.siswa_nis 
                         JOIN kelas k ON ak.kelas_id_kelas = k.id_kelas
                                      AND k.tahun_ajaran_id_tahun = t.id_tahun
                         WHERE r.siswa_nis = ? AND t.status_aktif = 'Y'");
$stmt_r->execute([$nis_siswa]);
$data_rapor = $stmt_r->fetch();

// 1. Ambil data mentah dari database
$stmt = $pdo->prepare("SELECT m.nama_mapel, 
                       AVG(CASE WHEN n.jenis_nilai = 'Tugas' THEN n.skor END) AS rata_tugas,
                       MAX(CASE WHEN n.jenis_nilai = 'UTS' THEN n.skor END) AS nilai_uts,
                       MAX(CASE WHEN n.jenis_nilai = 'UAS' THEN n.skor END) AS nilai_uas
                       FROM anggota_kelas ak
                       JOIN kelas k ON ak.kelas_id_kelas = k.id_kelas
                       JOIN tahun_ajaran t ON k.tahun_ajaran_id_tahun = t.id_tahun
                       JOIN jadwal j ON k.id_kelas = j.kelas_id_kelas
                       JOIN mata_pelajaran m ON j.mapel_kode_mapel = m.kode_mapel
                       LEFT JOIN nilai n ON m.kode_mapel = n.mapel_kode_mapel AND n.siswa_nis = ak.siswa_nis
                       WHERE ak.siswa_nis = ? AND t.status_aktif = 'Y'
                       GROUP BY m.kode_mapel, m.nama_mapel");
$stmt->execute([$nis_siswa]);
$list_nilai = $stmt->fetchAll();

function hitungNilaiAkhir(array $nilai): float {
    $tugas = (float) ($nilai['rata_tugas'] ?? 0);
    $uts = (float) ($nilai['nilai_uts'] ?? 0);
    $uas = (float) ($nilai['nilai_uas'] ?? 0);
    return round(($tugas * 0.3) + ($uts * 0.3) + ($uas * 0.4), 2);
}

$total_nilai_akhir = 0;
foreach ($list_nilai as $nilai) {
    $total_nilai_akhir += hitungNilaiAkhir($nilai);
}
$rata_rata_laporan = $list_nilai ? round($total_nilai_akhir / count($list_nilai), 2) : null;

// Fungsi PHP untuk menentukan Predikat Huruf
function tentukanPredikat($nilai) {
    if ($nilai >= 85) return 'A';
    if ($nilai >= 80) return 'A-';
    if ($nilai >= 75) return 'B+';
    if ($nilai >= 70) return 'B';
    return 'C';
}

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

$page = "rpr";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Siakadu | E Rapor</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
     <?php include '../../components/sidebar.php'; ?>
    <div class="content">
        <?php include '../../components/navbar.php'; ?>
        <h1 class = "titel">E-Rapor</h1>

        <div class="tabel_data">
        <div class ="stats2">
            <div class = "box">
                <div class = "ikon" style="background-color: #6083E1;">
                    <img src="../../public/img/ikonkelas.svg" alt="">
                </div>
                <div class="teks">
                    <p class = "boxttl">Rata=rata</p>
                    <p class="databx"><?= $rata_rata_laporan ?? '-' ?></p>
                </div>
            </div>
            <div class = "box">
                <div class = "ikon" style="background-color: #23AA6D;">
                    <img src="../../public/img/ikonkehadiran.svg" alt="">
                </div>
                <div class="teks">
                    <p class = "boxttl">Peringkat Kelas</p>
                    <p class="databx"><?= !empty($data_rapor['peringkat']) ? $data_rapor['peringkat'] : '-' ?> / <?= $data_rapor['total_siswa'] ?? '-' ?></p>
                </div>
            </div>
            <div class = "box">
                <div class = "ikon" style="background-color: #315DD2;">
                    <img src="../../public/img/ikonrata.svg" alt="">
                </div>
                <div class="teks">
                    <p class = "boxttl">Status Kenaikan</p>
                    <p class="databx"><?= $data_rapor['status_kenaikan'] ?? '-' ?></p>
                </div>
            </div>
        </div>

        <h1 class = "titel">Rekap Nilai</h1>
              <table class = "tbl_dkls" id="tbl_dkls">
                  <thead class ="thead_kls">
                      <tr>
                          <th>NO</th> 
                          <th>Mata Pelajaran</th>
                          <th>Nilai Akhir</th>
                          <th>Predikat</th>
                      </tr>
                  </thead>
                  <tbody>
            <?php 
                    if (count($list_nilai) > 0): 
                        $no = 1;
                        foreach ($list_nilai as $row):
                            // Kalkulasi murni di PHP (Contoh: Bobot Tugas 30%, UTS 30%, UAS 40%)
                            $nilai_akhir_rounded = hitungNilaiAkhir($row);
                            
                            // Panggil fungsi predikat PHP
                            $predikat = tentukanPredikat($nilai_akhir_rounded);
                    ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= htmlspecialchars($row['nama_mapel']) ?></td>
                        <td><?= $nilai_akhir_rounded ?></td>
                        <td><?= $predikat ?></td>
                    </tr>
                    <?php 
                        endforeach; 
                    else: 
                    ?>
                    <tr>
                        <td colspan="4" style="padding: 15px; text-align: center; color: #888;">Belum ada data nilai.</td>
                    </tr>
                    <?php endif; ?>
                  </tbody>
              </table>
          </div>
    </div>
</body>
</html>