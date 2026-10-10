<?php
session_start();
require "../../DB/koneksi.php";
require "../../Middleware/auth.php";
batas_akses(3);

$sql = "SELECT 
    s.nis, 
    s.nama as nama_siswa, 
    s.jenis_kelamin, 
    s.tanggal_lahir, 
    s.tempat_lahir, 
    s.alamat, 
    s.no_hp,
    s.tahun_masuk,
    k.nama_kelas,
    o.nama_ayah, 
    o.nama_ibu, 
    o.pekerjaan, 
    r.nama_role,
    o.no_hp AS no_hp_ortu
FROM siswa s
JOIN `user` u ON s.user_id_user = u.id_user
JOIN role r ON u.role_id_role = r.id_role
LEFT JOIN anggota_kelas ak ON s.nis = ak.siswa_nis
LEFT JOIN kelas k ON ak.kelas_id_kelas = k.id_kelas
LEFT JOIN orang_tua o ON s.orang_tua_id = o.id
WHERE s.nis = ?;
";
$nis_siswa = $_SESSION['nis'];

$stmt_p = $pdo->prepare($sql);
$stmt_p->execute([$nis_siswa]);
$profile =$stmt_p->fetch();

$jenis_kelamin = ($profile['jenis_kelamin'] == 'L') ? 'Laki-laki' : 'Perempuan';
$page = "prof";

$stats = $profile;
?>



<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Siakad | Profile</title>
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
        <?php include '../../components/sidebar.php'; ?>
        <div class="content">
            <?php include '../../components/navbar.php'; ?>
            <h1 class = "titel">Profil Siswa</h1>
            
            <div class="profile_s">
                <img src="../../public/img/profb.svg" alt="">
                <div class="data_d">
                    <p class ="nama_d"><?php echo $profile['nama_siswa'];?></p>
                    <p class ="detail_d">NIS <?php echo $profile['nis'];?></p>
                    <p class ="detail_d"><?php echo $profile['tahun_masuk'];?></p>
                    <p class ="detail_d"><?php echo $profile['nama_kelas'];?></p>
                </div>
            </div>
            <div class="tab_button">
                <button class="tab_button_active" id="but_datdir">Data Diri</button>
                <button id="but_ortu">Orang Tua</button>
            </div>
            <div class="tabel_data">
                <h1>Informasi Pribadi</h1>
                <table class = "tbl_diri" id="tbl_diri">
                    <tbody>
                        
                        <tr>
                            <td style="width: 200px;">Nama Lengkap</td> 
                            <td style="width: 10px;">:</td>  
                            <td><?php echo $profile['nama_siswa']; ?></td>
                        </tr>
                        <tr>
                            <td>Jenis Kelamin</td>
                            <td>:</td>
                            <td><?php echo $jenis_kelamin; ?></td>
                        </tr>
                        <tr>
                            <td>Tanggal Lahir</td>
                            <td>:</td>
                            <td><?php echo $profile['tanggal_lahir']; ?></td>
                        </tr>
                        <tr>
                            <td>Tempat Lahir</td>
                            <td>:</td>
                            <td><?php echo $profile['tempat_lahir']; ?></td>
                        </tr>
                        <tr>
                            <td>Alamat</td>
                            <td>:</td>
                            <td><?php echo $profile['alamat']; ?></td>
                        </tr>
                        <tr>
                            <td>No. HP</td>
                            <td>:</td>
                            <td><?php echo $profile['no_hp']; ?></td>
                        </tr>
                        
                    </tbody>
                </table>
                
                <table class = "tbl_org_tua" id="tbl_org_tua">
                    <tbody>
                        
                         <tr>
                            <td style="width: 200px;">Nama Ayah</td> 
                            <td style="width: 10px;">:</td>  
                            <td><?php echo $profile['nama_ayah']; ?></td>
                        </tr>
                        <tr>
                            <td>Nama Ibu</td>
                            <td>:</td>
                            <td><?php echo $profile['nama_ibu'];?></td>
                        </tr>
                        <tr>
                            <td>Pekerjaan</td>
                            <td>:</td>
                            <td><?php echo $profile['pekerjaan']; ?></td>
                        </tr>
                        <tr>
                            <td>No Hp</td>
                            <td>:</td>
                            <td><?php echo $profile['no_hp_ortu']; ?></td>
                        </tr>
                        
                    </tbody>
                </table>
            </div>
    </div>
</body>
</html>

<script>
const ortuTabBut = document.getElementById("but_ortu");
const DatdirTabBut = document.getElementById("but_datdir");
const tableDiri = document.getElementById("tbl_diri");
const tableOrtu = document.getElementById("tbl_org_tua");

ortuTabBut.addEventListener("click", function() {
    ortuTabBut.classList.add("tab_button_active");
    DatdirTabBut.classList.remove("tab_button_active");

    tableOrtu.style.display = "table";
    tableDiri.style.display = "none";
});

DatdirTabBut.addEventListener("click", function() {
    DatdirTabBut.classList.add("tab_button_active");
    ortuTabBut.classList.remove("tab_button_active");

    tableDiri.style.display = "table";
    tableOrtu.style.display = "none";
});

</script>