<?php
session_start();
require "../../DB/koneksi.php";
require "../../Middleware/auth.php";
batas_akses(2);

$nip_guru = $_SESSION['nip'];

$sql = "SELECT
            g.nip,
            g.nama,
            g.status_kepegawaian,
            g.no_hp,
            g.email,
            r.nama_role
        FROM guru g
        JOIN `user` u ON g.user_id_user = u.id_user
        JOIN role r ON u.role_id_role = r.id_role
        WHERE g.nip = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$nip_guru]);
$profile = $stmt->fetch();

if (!$profile) {
    die("Data guru tidak ditemukan.");
}

$page = "prof";
$stats = [
    'nama_guru' => $profile['nama'],
    'nama_role' => $profile['nama_role']
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Siakad | Profil Guru</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="guru-profile-page">
    <?php include '../../components/sidebarGuru.php'; ?>
    <div class="content">
        <?php include '../../components/navbarGuru.php'; ?>
        <h1 class="titel">Profil Guru</h1>

        <div class="profile_s">
            <img src="../../public/img/profb.svg" alt="Foto profil guru">
            <div class="data_d">
                <p class="nama_d"><?= htmlspecialchars($profile['nama']) ?></p>
                <p class="detail_d">NIP <?= htmlspecialchars($profile['nip']) ?></p>
                <p class="detail_d"><?= htmlspecialchars($profile['status_kepegawaian']) ?></p>
            </div>
        </div>

        <div class="tabel_data">
            <h1>Informasi Pribadi</h1>
            <table class="tbl_diri">
                <tbody>
                    <tr>
                        <td>Nama Lengkap</td>
                        <td>:</td>
                        <td><?= htmlspecialchars($profile['nama']) ?></td>
                    </tr>
                    <tr>
                        <td>NIP</td>
                        <td>:</td>
                        <td><?= htmlspecialchars($profile['nip']) ?></td>
                    </tr>
                    <tr>
                        <td>Status Kepegawaian</td>
                        <td>:</td>
                        <td><?= htmlspecialchars($profile['status_kepegawaian']) ?></td>
                    </tr>
                    <tr>
                        <td>Email</td>
                        <td>:</td>
                        <td><?= htmlspecialchars($profile['email']) ?></td>
                    </tr>
                    <tr>
                        <td>No. HP</td>
                        <td>:</td>
                        <td><?= htmlspecialchars($profile['no_hp']) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>