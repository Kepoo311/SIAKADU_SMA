<?php
session_start();
require "../../DB/koneksi.php";
require "../../Middleware/auth.php";
batas_akses(3);

$nis_siswa_login = $_SESSION['nis'];

$sql_kelas = "SELECT 
    k.id_kelas, 
    k.nama_kelas, 
    t.tahun AS tahun_ajaran,
    g.nama AS nama_wali_kelas,
    g.no_hp AS no_hp_wali_kelas
FROM anggota_kelas ak
JOIN kelas k ON ak.kelas_id_kelas = k.id_kelas
JOIN tahun_ajaran t ON k.tahun_ajaran_id_tahun = t.id_tahun
JOIN guru g ON k.guru_nip = g.nip
WHERE ak.siswa_nis = ? AND t.status_aktif = 'Y'
ORDER BY k.id_kelas DESC
LIMIT 1;";

$stmt_k = $pdo->prepare($sql_kelas);
$stmt_k->execute([$nis_siswa_login]);
$info_kelas = $stmt_k->fetch();

$daftar_siswa = [];
if ($info_kelas) {
    $sql_siswa = "SELECT s.nis, s.nama AS nama_siswa, s.jenis_kelamin
                  FROM anggota_kelas ak
                  JOIN siswa s ON ak.siswa_nis = s.nis
                  WHERE ak.kelas_id_kelas = ?
                  ORDER BY s.nama ASC";
    $stmt_s = $pdo->prepare($sql_siswa);
    $stmt_s->execute([$info_kelas['id_kelas']]);
    $daftar_siswa = $stmt_s->fetchAll();
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
$stmt_s->execute([$nis_siswa_login]);
$stats =$stmt_s->fetch();

$page = "kls";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Siakadu | Kelas Saya</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include '../../components/sidebar.php'; ?>
    <div class="content">
            <?php include '../../components/navbar.php'; ?>
            <h1 class = "titel">Kelas Saya</h1>

            <?php if ($info_kelas): ?>
            <div class="profile_s">
                <div class ="bgBULAT">
                    <img src="../../public/img/ikonkelasb.svg" alt="">
                </div>
                <div class="data_d">
                    <p class="nama_d"><?= htmlspecialchars($info_kelas['nama_kelas']) ?></p>
                    <p class="detail_d"><?= htmlspecialchars($info_kelas['tahun_ajaran']) ?></p>
                </div>
            </div>
            <div class="tab_button">
                <button class="tab_button_active" id="but_dkelas">Daftar Siswa</button>
                <button id="but_walkel">Wali Kelas</button>
            </div>
            <div class="tabel_data">
                <div class="table-tools">
                    <input type="search" data-table-search="tbl_dkls" placeholder="Cari nama, NIS, atau jenis kelamin..." aria-label="Cari siswa di kelas ini berdasarkan nama, NIS, atau jenis kelamin">
                </div>
                    <p class="table-search-results" data-table-results="tbl_dkls" aria-live="polite"></p>
                    <p class="table-search-empty" data-table-empty="tbl_dkls" hidden>Tidak ada siswa yang sesuai dengan pencarian.</p>
                    <table class="tbl_dkls" id="tbl_dkls">
                    <thead class ="thead_kls">
                        <tr>
                            <th>NO</th> 
                            <th>NIS</th>
                            <th>NAMA</th>
                            <th>JENIS KELAMIN</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($daftar_siswa): $no=1; foreach ($daftar_siswa as $row):?>
                        <tr data-search="<?= htmlspecialchars(implode(' ', [$row['nis'], $row['nama_siswa'], $row['jenis_kelamin']]), ENT_QUOTES, 'UTF-8') ?>">
                            <td><?= $no++;?></td> 
                            <td><?= $row['nis'] ?></td>  
                            <td><?= $row['nama_siswa'] ?></td>  
                            <td><?= $row['jenis_kelamin'] ?></td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr data-empty-state><td colspan="4">Belum ada siswa di kelas ini.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <table class = "tbl_walkel" id="tbl_walkel">
                    
                    <tbody>
                        
                         <tr>
                            <td style="width: 200px;">Nama Wali Kelas</td> 
                            <td style="width: 10px;">:</td>  
                            <td><?= htmlspecialchars($info_kelas['nama_wali_kelas']) ?></td>
                        </tr>
                        <tr>
                            <td>No. HP</td>
                            <td>:</td>
                            <td><?= htmlspecialchars($info_kelas['no_hp_wali_kelas']) ?></td>
                        </tr>
                        
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="profile_s">
                <div class="data_d">
                    <p class="nama_d">Belum ada kelas aktif</p>
                    <p class="detail_d">Hubungi admin untuk penempatan kelas pada tahun ajaran aktif.</p>
                </div>
            </div>
            <?php endif; ?>
    </div>
<script>
const butWalkel = document.getElementById("but_walkel");
const butDkelas = document.getElementById("but_dkelas");
const tableKls = document.getElementById("tbl_dkls");
const tableWalkel = document.getElementById("tbl_walkel");
const classSearchControls = document.querySelectorAll('[data-table-search="tbl_dkls"], [data-table-results="tbl_dkls"], [data-table-empty="tbl_dkls"]');

if (butWalkel && butDkelas && tableKls && tableWalkel) {
    butWalkel.addEventListener("click", function() {
        butWalkel.classList.add("tab_button_active");
        butDkelas.classList.remove("tab_button_active");
        tableWalkel.style.display = "table";
        tableKls.style.display = "none";
        classSearchControls.forEach((control) => { control.hidden = true; });
    });

    butDkelas.addEventListener("click", function() {
        butDkelas.classList.add("tab_button_active");
        butWalkel.classList.remove("tab_button_active");
        tableKls.style.display = "table";
        tableWalkel.style.display = "none";
        classSearchControls.forEach((control) => { control.hidden = false; });
    });
}
</script>
<script src="../../Views/table-search.js" defer></script>
</body>
</html>