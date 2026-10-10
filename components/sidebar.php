<div class="sidebar">
        <div class = "logo">
            <img src="../../public/img/logo.svg" alt="">
            <h2>Sistem akademik SMA</h2>
        </div>
        <script src="../../Views/toast.js" defer></script>
        <div class = "menu">
        <a href="dashboard.php" class ="<?= ($page == 'dash') ? 'active' : '' ?>"><img src="../../public/img/dash.svg">Dashboard</a>
        <a href="profile.php" class ="<?= ($page == 'prof') ? 'active' : '' ?>"><img src="../../public/img/prof.svg">Profile</a>
        <a href="kelassaya.php" class ="<?= ($page == 'kls') ? 'active' : '' ?>"><img src="../../public/img/kels.svg">Kelas Saya</a>
        <a href="jadwal.php" class ="<?= ($page == 'jdwl') ? 'active' : '' ?>"><img src="../../public/img/jdwl.svg">Jadwal Pelajaran</a>
        <a href="absensi.php" class ="<?= ($page == 'absn') ? 'active' : '' ?>"><img src="../../public/img/abs.svg">Riwayat Absensi</a>
        <a href="transkrip.php" class ="<?= ($page == 'trnskrp') ? 'active' : '' ?>"><img src="../../public/img/trans.svg">Transkrip Nilai</a>
        <a href="rapor.php" class ="<?= ($page == 'rpr') ? 'active' : '' ?>"><img src="../../public/img/rpr.svg">E-Rapor</a>
        <a href="eksul.php" class ="<?= ($page == 'eks') ? 'active' : '' ?>"><img src="../../public/img/ekss.svg">Ekstrakurikuler</a>
        </div>
    </div>