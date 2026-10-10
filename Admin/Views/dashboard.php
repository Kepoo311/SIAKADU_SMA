<?php
session_start();
require "../../DB/koneksi.php";

// Validasi Hak Akses: Hanya Admin (role_id 1 / role Admin) yang boleh masuk
// Sesuaikan angka role_id dengan database Anda (misal: 1 = Admin)
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../index.php?pesan= unauthorized");
    exit;
}

// Ambil Statistik Ringkas untuk Kartu Dashboard
$total_siswa = $pdo->query("SELECT COUNT(*) FROM siswa")->fetchColumn();
$total_guru = $pdo->query("SELECT COUNT(*) FROM guru")->fetchColumn();
$total_kelas = $pdo->query("SELECT COUNT(*) FROM kelas")->fetchColumn();
$total_mapel = $pdo->query("SELECT COUNT(*) FROM mata_pelajaran")->fetchColumn();

$stmt_admin = $pdo->prepare(
    "SELECT u.username, r.nama_role
     FROM user u
     JOIN role r ON u.role_id_role = r.id_role
     WHERE u.id_user = ?"
);
$stmt_admin->execute([$_SESSION['user_id']]);
$admin = $stmt_admin->fetch();

if (!$admin) {
    die("Data administrator tidak ditemukan.");
}

$stats = [
    'nama_admin' => $admin['username'],
    'nama_role' => $admin['nama_role']
];
$page = 'dash';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - SIAKAD</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include '../../components/sidebarAdmin.php'; ?>
    <main class="content">
        <?php include '../../components/navbarAdmin.php'; ?>
        <section class="dashboard-content">
            <h1 class="titel">Selamat Datang, <?= htmlspecialchars($admin['username']) ?></h1>
            <p class="dashboard-description">Ringkasan data akademik sekolah.</p>

            <div class="stats">
                <article class="box">
                    <div class="ikon ikon-siswa">
                        <img src="../../public/img/guru-total-siswa.svg" alt="">
                    </div>
                    <div class="teks">
                        <p class="boxttl">Total Siswa</p>
                        <p class="databx"><?= htmlspecialchars((string) $total_siswa) ?></p>
                    </div>
                </article>
                <article class="box">
                    <div class="ikon ikon-guru">
                        <img src="../../public/img/prof.svg" alt="">
                    </div>
                    <div class="teks">
                        <p class="boxttl">Total Guru</p>
                        <p class="databx"><?= htmlspecialchars((string) $total_guru) ?></p>
                    </div>
                </article>
                <article class="box">
                    <div class="ikon ikon-kelas">
                        <img src="../../public/img/guru-total-kelas.svg" alt="">
                    </div>
                    <div class="teks">
                        <p class="boxttl">Total Kelas</p>
                        <p class="databx"><?= htmlspecialchars((string) $total_kelas) ?></p>
                    </div>
                </article>
                <article class="box">
                    <div class="ikon ikon-mapel">
                        <img src="../../public/img/guru-mata-pelajaran.svg" alt="">
                    </div>
                    <div class="teks">
                        <p class="boxttl">Mata Pelajaran</p>
                        <p class="databx"><?= htmlspecialchars((string) $total_mapel) ?></p>
                    </div>
                </article>
            </div>

            <section class="welcome-panel">
                <h2>Panel Administrator SIAKAD</h2>
                <p>Kelola data siswa, guru, kelas, mata pelajaran, dan penjadwalan dalam 1 sistem.</p>
            </section>
        </section>
    </main>
</body>
</html>