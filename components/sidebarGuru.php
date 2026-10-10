<div class="sidebar">
        <div class = "logo">
            <img src="../../public/img/logo.svg" alt="">
            <h2>Sistem akademik SMA</h2>
        </div>
        <script src="../../Views/toast.js" defer></script>
        <div class = "menu">
        <a href="dashboard.php" class ="<?= ($page == 'dash') ? 'active' : '' ?>"><img src="../../public/img/dash.svg">Dashboard</a>
        <a href="profile.php" class ="<?= ($page == 'prof') ? 'active' : '' ?>"><img src="../../public/img/prof.svg">Profile</a>
        <a href="jadwal.php" class ="<?= ($page == 'jdwl') ? 'active' : '' ?>"><img src="../../public/img/kels.svg">Jadwal</a>
        <a href="siswa_walikelas.php" class ="<?= ($page == 'siswa_walikelas') ? 'active' : '' ?>"><img src="../../public/img/prof.svg">Siswa Wali Kelas</a>
        <a href="nilai.php" class ="<?= ($page == 'nilai') ? 'active' : '' ?>"><img src="../../public/img/jdwl.svg">Nilai</a>
        <a href="absensi.php" class ="<?= ($page == 'absn') ? 'active' : '' ?>"><img src="../../public/img/abs.svg">Input Absensi</a>
        </div>
    </div>