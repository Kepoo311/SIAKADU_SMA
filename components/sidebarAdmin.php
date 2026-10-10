<aside class="sidebar">
    <div class="logo">
        <img src="../../public/img/logo.svg" alt="">
        <h2>Sistem akademik SMA</h2>
    </div>
    <nav class="menu" aria-label="Navigasi administrator">
        <a href="dashboard.php" class="<?= ($page === 'dash') ? 'active' : '' ?>">
            <img src="../../public/img/dash.svg" alt="">Dashboard
        </a>
        <a href="siswa.php" class="<?= ($page === 'siswa') ? 'active' : '' ?>">
            <img src="../../public/img/prof.svg" alt="">Kelola Siswa
        </a>
        <a href="guru.php" class="<?= ($page === 'guru') ? 'active' : '' ?>">
            <img src="../../public/img/prof.svg" alt="">Kelola Guru
        </a>
        <a href="m_kelas.php" class="<?= ($page === 'kelas') ? 'active' : '' ?>">
            <img src="../../public/img/kels.svg" alt="">Kelola Kelas
        </a>
        <a href="m_jadwal_siswa.php" class="<?= ($page === 'mapel') ? 'active' : '' ?>">
            <img src="../../public/img/jdwl.svg" alt="">Mata Pelajaran
        </a>
        <a href="m_jadwal_guru.php" class="<?= ($page === 'jadwal') ? 'active' : '' ?>">
            <img src="../../public/img/abs.svg" alt="">Jadwal Pelajaran
        </a>
        <a href="tahun_ajaran.php" class="<?= ($page === 'tahun') ? 'active' : '' ?>">
            <img src="../../public/img/jdwl.svg" alt="">Tahun Ajaran
        </a>
        <a href="ekstrakurikuler.php" class="<?= ($page === 'ekskul') ? 'active' : '' ?>">
            <img src="../../public/img/ekss.svg" alt="">Ekstrakurikuler
        </a>
    </nav>
</aside>
<script src="../../Views/toast.js" defer></script>
