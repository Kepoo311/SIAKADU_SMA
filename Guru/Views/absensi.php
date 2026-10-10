<?php
session_start();
require "../../DB/koneksi.php";
require "../../Middleware/auth.php";
batas_akses(2);

$nip_guru = $_SESSION['nip'];

$pesan = "";
if (empty($_SESSION['guru_absensi_csrf'])) {
    $_SESSION['guru_absensi_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['guru_absensi_csrf'];

// 2. Proses Simpan Absensi saat Form di-submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_absensi'])) {
    try {
        if (!isset($_POST['csrf_token']) || !hash_equals($csrfToken, (string) $_POST['csrf_token'])) {
            throw new InvalidArgumentException('Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.');
        }
        $jadwal_id = filter_var($_POST['jadwal_id'] ?? null, FILTER_VALIDATE_INT);
        $tanggal = $_POST['tanggal'] ?? '';
        $status_arr = $_POST['status'] ?? null;
        $keterangan_arr = $_POST['keterangan'] ?? [];
        $tanggal_valid = is_string($tanggal) ? DateTime::createFromFormat('!Y-m-d', $tanggal) : false;

        if (!$jadwal_id || !$tanggal_valid || $tanggal_valid->format('Y-m-d') !== $tanggal || $tanggal > date('Y-m-d')) {
            throw new InvalidArgumentException('Jadwal atau tanggal tidak valid.');
        }
        if (!is_array($status_arr) || !$status_arr || !is_array($keterangan_arr)) {
            throw new InvalidArgumentException('Data absensi tidak lengkap.');
        }

        $stmt_valid_jadwal = $pdo->prepare("SELECT j.kelas_id_kelas, j.hari, t.tanggal_mulai, t.tanggal_selesai
                                            FROM jadwal j
                                            JOIN kelas k ON j.kelas_id_kelas = k.id_kelas
                                            JOIN tahun_ajaran t ON k.tahun_ajaran_id_tahun = t.id_tahun
                                            WHERE j.id_jadwal = ? AND j.guru_nip = ? AND t.status_aktif = 'Y'");
        $stmt_valid_jadwal->execute([$jadwal_id, $nip_guru]);
        $jadwal_dipilih = $stmt_valid_jadwal->fetch();
        if (!$jadwal_dipilih) {
            throw new InvalidArgumentException('Jadwal tidak ditemukan atau bukan jadwal aktif Anda.');
        }
        if ($tanggal < $jadwal_dipilih['tanggal_mulai'] || $tanggal > $jadwal_dipilih['tanggal_selesai']) {
            throw new InvalidArgumentException('Tanggal absensi harus berada dalam periode tahun ajaran aktif.');
        }
        $hari_tanggal = [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Minggu',
        ];
        if (($hari_tanggal[(int) $tanggal_valid->format('N')] ?? '') !== $jadwal_dipilih['hari']) {
            throw new InvalidArgumentException('Tanggal absensi harus sesuai dengan hari jadwal pelajaran.');
        }

        $stmt_roster = $pdo->prepare("SELECT siswa_nis FROM anggota_kelas WHERE kelas_id_kelas = ?");
        $stmt_roster->execute([$jadwal_dipilih['kelas_id_kelas']]);
        $daftar_nis = array_fill_keys(array_column($stmt_roster->fetchAll(), 'siswa_nis'), true);
        if (count($status_arr) !== count($daftar_nis)) {
            throw new InvalidArgumentException('Daftar absensi harus mencakup seluruh siswa di kelas.');
        }
        $status_diizinkan = ['Hadir', 'Izin', 'Sakit', 'Alfa'];
        foreach ($status_arr as $nis => $status) {
            $nis = (string) $nis;
            if (!isset($daftar_nis[$nis]) || !is_string($status) || !in_array($status, $status_diizinkan, true)) {
                throw new InvalidArgumentException('Daftar siswa atau status absensi tidak valid.');
            }
        }

        foreach ($keterangan_arr as $nis => $keterangan) {
            $nis = (string) $nis;
            if (!isset($daftar_nis[$nis]) || !is_string($keterangan) ||
                preg_match_all('/./us', $keterangan, $matches) === false || count($matches[0]) > 255) {
                throw new InvalidArgumentException('Keterangan absensi harus valid dan maksimal 255 karakter.');
            }
        }

        $pdo->beginTransaction();

        foreach ($status_arr as $nis => $status) {
            $nis = (string) $nis;
            $keterangan = is_string($keterangan_arr[$nis] ?? null) ? trim($keterangan_arr[$nis]) : '';

            $save = $pdo->prepare(
                "INSERT INTO absensi (siswa_nis, jadwal_id_jadwal, tanggal, status, keterangan)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE status = VALUES(status), keterangan = VALUES(keterangan)"
            );
            $save->execute([$nis, $jadwal_id, $tanggal, $status, $keterangan]);
        }

        $pdo->commit();
        $pesan = "<div class='form-alert form-alert-success'>Absensi berhasil disimpan!</div>";
    } catch (InvalidArgumentException $e) {
        $pesan = "<div class='form-alert form-alert-error'>" . htmlspecialchars($e->getMessage()) . "</div>";
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Gagal menyimpan absensi guru: ' . $e->getMessage());
        $pesan = "<div class='form-alert form-alert-error'>Gagal menyimpan absensi. Silakan coba lagi.</div>";
    }
}

// 3. Ambil semua jadwal aktif agar absensi susulan untuk hari jadwal tetap dapat diisi
$sql_jadwal = "SELECT j.id_jadwal, j.hari, j.jam_mulai, j.jam_selesai, m.nama_mapel, k.id_kelas, k.nama_kelas
               FROM jadwal j
               JOIN mata_pelajaran m ON j.mapel_kode_mapel = m.kode_mapel
               JOIN kelas k ON j.kelas_id_kelas = k.id_kelas
               JOIN tahun_ajaran t ON k.tahun_ajaran_id_tahun = t.id_tahun
               WHERE j.guru_nip = ? AND t.status_aktif = 'Y'
               ORDER BY FIELD(j.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'), j.jam_mulai ASC";
$stmt_j = $pdo->prepare($sql_jadwal);
$stmt_j->execute([$nip_guru]);
$list_jadwal_aktif = $stmt_j->fetchAll();

// 4. Ambil data siswa dan absensi jika guru sudah memilih jadwal
$selected_jadwal_id = filter_var($_GET['jadwal_id'] ?? null, FILTER_VALIDATE_INT) ?: null;

// Tangkap tanggal, prioritaskan dari POST (jika baru submit), lalu GET, lalu hari ini
$selected_tanggal = $_POST['tanggal'] ?? $_GET['tanggal'] ?? date('Y-m-d');
if (!is_string($selected_tanggal)) {
    $selected_tanggal = date('Y-m-d');
}

$daftar_siswa = [];
$info_sesi = null;
$data_absensi = []; // Array baru untuk menyimpan rekam jejak absensi

if ($selected_jadwal_id) {
    $stmt_sesi = $pdo->prepare("SELECT j.*, m.nama_mapel, k.nama_kelas, k.id_kelas 
                                FROM jadwal j 
                                JOIN mata_pelajaran m ON j.mapel_kode_mapel = m.kode_mapel 
                                JOIN kelas k ON j.kelas_id_kelas = k.id_kelas 
                                JOIN tahun_ajaran t ON k.tahun_ajaran_id_tahun = t.id_tahun
                                WHERE j.id_jadwal = ? AND j.guru_nip = ? AND t.status_aktif = 'Y'");
    $stmt_sesi->execute([$selected_jadwal_id, $nip_guru]);
    $info_sesi = $stmt_sesi->fetch();

    if ($info_sesi) {
        // A. Ambil daftar siswa
        $stmt_s = $pdo->prepare("SELECT DISTINCT s.nis, s.nama, s.jenis_kelamin
                                 FROM anggota_kelas ak 
                                 JOIN siswa s ON ak.siswa_nis = s.nis 
                                 WHERE ak.kelas_id_kelas = ? 
                                 ORDER BY s.nama ASC");
        $stmt_s->execute([$info_sesi['id_kelas']]);
        $daftar_siswa = $stmt_s->fetchAll();

        // B. Ambil data absensi yang sudah ada di database untuk jadwal & tanggal ini
        $stmt_absensi = $pdo->prepare("SELECT siswa_nis, status, keterangan 
                                       FROM absensi 
                                       WHERE jadwal_id_jadwal = ? AND tanggal = ?");
        $stmt_absensi->execute([$selected_jadwal_id, $selected_tanggal]);
        
        // Ubah jadi array dengan kunci NIS agar mudah dicek di HTML
        while ($row = $stmt_absensi->fetch()) {
            $data_absensi[$row['siswa_nis']] = [
                'status' => $row['status'],
                'keterangan' => $row['keterangan']
            ];
        }
    }
}

$sql = "SELECT 
    s.nama AS nama_guru,
    r.nama_role

FROM guru s
JOIN `user` u ON s.user_id_user = u.id_user
JOIN role r ON u.role_id_role = r.id_role
WHERE s.nip = ?;
";

$stmt_s = $pdo->prepare($sql);
$stmt_s->execute([$nip_guru]);
$stats =$stmt_s->fetch();

$page = "absn";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Input Absensi - SIAKAD Guru</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="absensi-page">
         <?php include '../../components/sidebarGuru.php'; ?>
    <div class="content">
        <?php include '../../components/navbarGuru.php'; ?>
        <h1 class = "titel">Jadwal Pelajaran</h1>

        <?= $pesan; ?>

        <div class="absensi-panel">
            <h3 class="panel-title">1. Pilih Jadwal Mengajar</h3>
            <form method="GET" action="" class="jadwal-form">
                <select name="jadwal_id" required class="form-select">
                    <option value="">-- Pilih Mata Pelajaran & Kelas --</option>
                    <?php foreach ($list_jadwal_aktif as $jdw): ?>
                        <option value="<?= $jdw['id_jadwal'] ?>" <?= ($selected_jadwal_id == $jdw['id_jadwal']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($jdw['hari'], ENT_QUOTES, 'UTF-8') ?>, [<?= date('H:i', strtotime($jdw['jam_mulai'])) ?>] - <?= htmlspecialchars($jdw['nama_mapel'], ENT_QUOTES, 'UTF-8') ?> (Kelas <?= htmlspecialchars($jdw['nama_kelas'], ENT_QUOTES, 'UTF-8') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary">Pilih</button>
            </form>
        </div>

        <?php if ($info_sesi): ?>
        <div class="absensi-panel">
            <div class="absensi-panel-header">
                <h3 class="panel-title">Form Absensi Kelas: <?= htmlspecialchars($info_sesi['nama_kelas']) ?></h3>
                <p class="panel-meta">Mata Pelajaran: <strong><?= htmlspecialchars($info_sesi['nama_mapel']) ?></strong> | Waktu: <?= $info_sesi['jam_mulai'] ?> - <?= $info_sesi['jam_selesai'] ?></p>
            </div>

            <form method="POST" action="" class="attendance-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="jadwal_id" value="<?= htmlspecialchars((string) $selected_jadwal_id, ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-group">
                    <label class="form-label">Tanggal Absen:</label>
                    <input type="date" name="tanggal" value="<?= htmlspecialchars($selected_tanggal, ENT_QUOTES, 'UTF-8') ?>" max="<?= date('Y-m-d') ?>" required class="form-control form-date">
                    <small class="attendance-date-hint">Tanggal mendatang tidak dapat dipilih. Absensi susulan harus menggunakan hari jadwal yang sesuai.</small>
                </div>

                <?php if ($daftar_siswa): ?>
                <div class="table-tools">
                    <input type="search" data-table-search="attendance-student-list" placeholder="Cari nama atau NIS siswa..." aria-label="Cari siswa untuk absensi berdasarkan nama atau NIS">
                </div>
                <p class="table-search-results" data-table-results="attendance-student-list" aria-live="polite"></p>
                <p class="table-search-empty" data-table-empty="attendance-student-list" hidden>Tidak ada siswa yang sesuai dengan pencarian.</p>
                <?php endif; ?>

                <div class="attendance-table-scroll">
                <table class="attendance-table" id="attendance-student-list">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>NIS</th>
                            <th>Nama Siswa</th>
                            <th>Status Kehadiran</th>
                            <th>Keterangan (Opsional)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($daftar_siswa) > 0): $no = 1; foreach ($daftar_siswa as $siswa): ?>
                        <?php 
                            $nis = $siswa['nis'];
                            $status_tersimpan = $data_absensi[$nis]['status'] ?? 'Hadir';
                            $keterangan_tersimpan = $data_absensi[$nis]['keterangan'] ?? '';
                        ?>
                        <tr class="attendance-row" data-search="<?= htmlspecialchars($nis . ' ' . $siswa['nama'], ENT_QUOTES, 'UTF-8') ?>">
                            <td><?= $no++ ?></td>
                            <td><?= htmlspecialchars($nis, ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="student-name"><?= htmlspecialchars($siswa['nama'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <select name="status[<?= $nis ?>]" class="form-control select-status">
                                    <option value="Hadir" <?= $status_tersimpan === 'Hadir' ? 'selected' : '' ?>>Hadir</option>
                                    <option value="Izin" <?= $status_tersimpan === 'Izin' ? 'selected' : '' ?>>Izin</option>
                                    <option value="Sakit" <?= $status_tersimpan === 'Sakit' ? 'selected' : '' ?>>Sakit</option>
                                    <option value="Alfa" <?= $status_tersimpan === 'Alfa' ? 'selected' : '' ?>>Alfa</option>
                                </select>
                            </td>
                            <td>
                                <input type="text" name="keterangan[<?= htmlspecialchars($nis, ENT_QUOTES, 'UTF-8') ?>]" value="<?= htmlspecialchars($keterangan_tersimpan, ENT_QUOTES, 'UTF-8') ?>" maxlength="255" placeholder="Catatan..." class="form-control text-input">
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr data-empty-state>
                            <td colspan="5" class="empty-state">Tidak ada siswa di dalam kelas ini.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>

                <div class="btn-wrap">
                    <button type="submit" name="simpan_absensi" class="btn btn-success">Simpan Absensi</button>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>
<script src="../../Views/table-search.js" defer></script>
</body>
</html>