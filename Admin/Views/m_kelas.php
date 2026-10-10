<?php
session_start();
require "../../DB/koneksi.php";

if (!isset($_SESSION['role_id']) || (int) $_SESSION['role_id'] !== 1) {
    header("Location: ../index.php");
    exit;
}

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function validId($value)
{
    $id = filter_var($value, FILTER_VALIDATE_INT);
    return $id !== false && $id > 0 ? $id : false;
}

if (empty($_SESSION['admin_crud_token'])) {
    $_SESSION['admin_crud_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['admin_crud_token'];
$pesan = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $classLockAcquired = false;
    $raporLockAcquired = false;
    try {
        if (!isset($_POST['csrf_token']) || !hash_equals($csrfToken, (string) $_POST['csrf_token'])) {
            throw new InvalidArgumentException('Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.');
        }

        $action = $_POST['action'] ?? '';
        if ($action === 'save_kelas') {
            $classId = isset($_POST['id_kelas']) ? validId($_POST['id_kelas']) : false;
            $isEdit = isset($_POST['id_kelas']);
            $name = isset($_POST['nama_kelas']) && is_string($_POST['nama_kelas']) ? trim($_POST['nama_kelas']) : '';
            $level = isset($_POST['tingkat']) && is_string($_POST['tingkat']) ? $_POST['tingkat'] : '';
            $jurusanId = validId($_POST['jurusan_id_jurusan'] ?? null);
            $guruNip = isset($_POST['guru_nip']) && is_string($_POST['guru_nip']) ? trim($_POST['guru_nip']) : '';
            $yearId = validId($_POST['tahun_ajaran_id_tahun'] ?? null);

            if ($isEdit && $classId === false) {
                throw new InvalidArgumentException('ID kelas tidak valid.');
            }
            if ($name === '' || strlen($name) > 50) {
                throw new InvalidArgumentException('Nama kelas wajib diisi dan maksimal 50 karakter.');
            }
            if (!in_array($level, ['X', 'XI', 'XII'], true)) {
                throw new InvalidArgumentException('Tingkat harus X, XI, atau XII.');
            }
            if ($jurusanId === false || $yearId === false || $guruNip === '' || strlen($guruNip) > 30) {
                throw new InvalidArgumentException('Jurusan, wali kelas, dan tahun ajaran wajib dipilih dengan ID yang valid.');
            }
            foreach ([
                ['SELECT 1 FROM jurusan WHERE id_jurusan = ?', $jurusanId, 'Jurusan'],
                ['SELECT 1 FROM guru WHERE nip = ?', $guruNip, 'Guru wali kelas'],
                ['SELECT 1 FROM tahun_ajaran WHERE id_tahun = ?', $yearId, 'Tahun ajaran'],
            ] as [$sql, $value, $label]) {
                $check = $pdo->prepare($sql);
                $check->execute([$value]);
                if (!$check->fetchColumn()) {
                    throw new InvalidArgumentException($label . ' yang dipilih tidak ditemukan.');
                }
            }

            if ($isEdit) {
                $check = $pdo->prepare("SELECT 1 FROM kelas WHERE id_kelas = ?");
                $check->execute([$classId]);
                if (!$check->fetchColumn()) {
                    throw new InvalidArgumentException('Kelas yang akan diubah tidak ditemukan.');
                }
                $stmt = $pdo->prepare("UPDATE kelas SET nama_kelas = ?, tingkat = ?, jurusan_id_jurusan = ?, guru_nip = ?, tahun_ajaran_id_tahun = ? WHERE id_kelas = ?");
                $stmt->execute([$name, $level, $jurusanId, $guruNip, $yearId, $classId]);
                $pesan = ['success', 'Data kelas berhasil diperbarui.'];
            } else {
                $pdo->beginTransaction();
                $classLockAcquired = (int) $pdo->query("SELECT GET_LOCK('siakadu_admin_kelas_id', 5)")->fetchColumn() === 1;
                if (!$classLockAcquired) {
                    throw new RuntimeException('Tidak dapat menyiapkan ID kelas saat ini. Silakan coba lagi.');
                }
                $newClassId = (int) $pdo->query("SELECT COALESCE(MAX(id_kelas), 0) + 1 FROM kelas")->fetchColumn();
                $stmt = $pdo->prepare("INSERT INTO kelas (id_kelas, nama_kelas, tingkat, jurusan_id_jurusan, guru_nip, tahun_ajaran_id_tahun) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$newClassId, $name, $level, $jurusanId, $guruNip, $yearId]);
                $pdo->commit();
                $pesan = ['success', 'Kelas baru berhasil ditambahkan.'];
            }
        } elseif ($action === 'delete_kelas') {
            $classId = validId($_POST['id_kelas'] ?? null);
            if ($classId === false) {
                throw new InvalidArgumentException('ID kelas tidak valid.');
            }
            $stmt = $pdo->prepare("DELETE FROM kelas WHERE id_kelas = ?");
            $stmt->execute([$classId]);
            if ($stmt->rowCount() === 0) {
                throw new InvalidArgumentException('Kelas tidak ditemukan atau sudah dihapus.');
            }
            $pesan = ['success', 'Kelas berhasil dihapus.'];
        } elseif ($action === 'add_member') {
            $classId = validId($_POST['kelas_id'] ?? null);
            $studentNis = isset($_POST['siswa_nis']) && is_string($_POST['siswa_nis']) ? trim($_POST['siswa_nis']) : '';
            if ($classId === false || $studentNis === '' || strlen($studentNis) > 20) {
                throw new InvalidArgumentException('Kelas dan siswa wajib dipilih dengan ID yang valid.');
            }

            $pdo->beginTransaction();
            $stmtClass = $pdo->prepare("SELECT tahun_ajaran_id_tahun FROM kelas WHERE id_kelas = ?");
            $stmtClass->execute([$classId]);
            $yearId = $stmtClass->fetchColumn();
            if ($yearId === false) {
                throw new InvalidArgumentException('Kelas yang dipilih tidak ditemukan.');
            }
            $stmtStudent = $pdo->prepare("SELECT 1 FROM siswa WHERE nis = ?");
            $stmtStudent->execute([$studentNis]);
            if (!$stmtStudent->fetchColumn()) {
                throw new InvalidArgumentException('Siswa yang dipilih tidak ditemukan.');
            }

            $checkOtherYearClass = $pdo->prepare(
                "SELECT 1
                 FROM anggota_kelas ak
                 JOIN kelas existing_class ON ak.kelas_id_kelas = existing_class.id_kelas
                 WHERE ak.siswa_nis = ?
                   AND existing_class.tahun_ajaran_id_tahun = ?
                   AND existing_class.id_kelas <> ?
                 LIMIT 1"
            );
            $checkOtherYearClass->execute([$studentNis, $yearId, $classId]);
            if ($checkOtherYearClass->fetchColumn()) {
                throw new InvalidArgumentException('Siswa sudah ditempatkan pada kelas lain di tahun ajaran ini.');
            }

            $checkMember = $pdo->prepare("SELECT id_anggota FROM anggota_kelas WHERE kelas_id_kelas = ? AND siswa_nis = ?");
            $checkMember->execute([$classId, $studentNis]);
            if ($checkMember->fetchColumn() === false) {
                $insertMember = $pdo->prepare("INSERT INTO anggota_kelas (kelas_id_kelas, siswa_nis) VALUES (?, ?)");
                $insertMember->execute([$classId, $studentNis]);
            }

            $checkRapor = $pdo->prepare("SELECT id_rapor FROM rapor WHERE siswa_nis = ? AND tahun_ajaran_id_tahun = ?");
            $checkRapor->execute([$studentNis, $yearId]);
            if ($checkRapor->fetchColumn() === false) {
                $raporLockAcquired = (int) $pdo->query("SELECT GET_LOCK('siakadu_rapor_id', 5)")->fetchColumn() === 1;
                if (!$raporLockAcquired) {
                    throw new RuntimeException('Tidak dapat menyiapkan rapor saat ini. Silakan coba lagi.');
                }
                $checkRapor->execute([$studentNis, $yearId]);
                if ($checkRapor->fetchColumn() === false) {
                    $newRaporId = (int) $pdo->query("SELECT COALESCE(MAX(id_rapor), 0) + 1 FROM rapor")->fetchColumn();
                    $insertRapor = $pdo->prepare(
                        "INSERT INTO rapor (id_rapor, rata_rata, peringkat, status_kenaikan, catatan_wali_kelas, siswa_nis, tahun_ajaran_id_tahun)
                         VALUES (?, 0, 0, 'Belum ditentukan', NULL, ?, ?)"
                    );
                    $insertRapor->execute([$newRaporId, $studentNis, $yearId]);
                }
            }
            $pdo->commit();
            $pesan = ['success', 'Penempatan kelas tersimpan dan rapor untuk tahun ajaran tersebut sudah dipastikan tersedia.'];
        } elseif ($action === 'remove_member') {
            $memberId = validId($_POST['id_anggota'] ?? null);
            if ($memberId === false) {
                throw new InvalidArgumentException('ID keanggotaan kelas tidak valid.');
            }
            $stmt = $pdo->prepare("DELETE FROM anggota_kelas WHERE id_anggota = ?");
            $stmt->execute([$memberId]);
            if ($stmt->rowCount() === 0) {
                throw new InvalidArgumentException('Keanggotaan kelas tidak ditemukan atau sudah dihapus.');
            }
            $pesan = ['success', 'Siswa berhasil dikeluarkan dari kelas. Data siswa dan rapornya tetap dipertahankan.'];
        } elseif ($action === 'move_member') {
            $memberId = validId($_POST['id_anggota'] ?? null);
            $classId = validId($_POST['kelas_id'] ?? null);
            if ($memberId === false || $classId === false) {
                throw new InvalidArgumentException('Keanggotaan dan kelas tujuan wajib dipilih dengan ID yang valid.');
            }
            $pdo->beginTransaction();
            $stmtMember = $pdo->prepare("SELECT siswa_nis, kelas_id_kelas FROM anggota_kelas WHERE id_anggota = ?");
            $stmtMember->execute([$memberId]);
            $member = $stmtMember->fetch();
            if (!$member) {
                throw new InvalidArgumentException('Keanggotaan kelas tidak ditemukan.');
            }
            $stmtClass = $pdo->prepare("SELECT tahun_ajaran_id_tahun FROM kelas WHERE id_kelas = ?");
            $stmtClass->execute([$classId]);
            $yearId = $stmtClass->fetchColumn();
            if ($yearId === false) {
                throw new InvalidArgumentException('Kelas tujuan tidak ditemukan.');
            }
            $sameMembership = $pdo->prepare("SELECT id_anggota FROM anggota_kelas WHERE kelas_id_kelas = ? AND siswa_nis = ? AND id_anggota <> ?");
            $sameMembership->execute([$classId, $member['siswa_nis'], $memberId]);
            if ($sameMembership->fetchColumn() !== false) {
                throw new InvalidArgumentException('Siswa tersebut sudah terdaftar di kelas tujuan.');
            }
            $checkOtherYearClass = $pdo->prepare(
                "SELECT 1
                 FROM anggota_kelas ak
                 JOIN kelas existing_class ON ak.kelas_id_kelas = existing_class.id_kelas
                 WHERE ak.siswa_nis = ?
                   AND existing_class.tahun_ajaran_id_tahun = ?
                   AND existing_class.id_kelas <> ?
                   AND ak.id_anggota <> ?
                 LIMIT 1"
            );
            $checkOtherYearClass->execute([$member['siswa_nis'], $yearId, $classId, $memberId]);
            if ($checkOtherYearClass->fetchColumn()) {
                throw new InvalidArgumentException('Siswa sudah ditempatkan pada kelas lain di tahun ajaran tujuan.');
            }
            $checkRapor = $pdo->prepare("SELECT id_rapor FROM rapor WHERE siswa_nis = ? AND tahun_ajaran_id_tahun = ?");
            $checkRapor->execute([$member['siswa_nis'], $yearId]);
            if ($checkRapor->fetchColumn() === false) {
                $raporLockAcquired = (int) $pdo->query("SELECT GET_LOCK('siakadu_rapor_id', 5)")->fetchColumn() === 1;
                if (!$raporLockAcquired) {
                    throw new RuntimeException('Tidak dapat menyiapkan rapor saat ini. Silakan coba lagi.');
                }
                $checkRapor->execute([$member['siswa_nis'], $yearId]);
                if ($checkRapor->fetchColumn() === false) {
                    $newRaporId = (int) $pdo->query("SELECT COALESCE(MAX(id_rapor), 0) + 1 FROM rapor")->fetchColumn();
                    $insertRapor = $pdo->prepare(
                        "INSERT INTO rapor (id_rapor, rata_rata, peringkat, status_kenaikan, catatan_wali_kelas, siswa_nis, tahun_ajaran_id_tahun)
                         VALUES (?, 0, 0, 'Belum ditentukan', NULL, ?, ?)"
                    );
                    $insertRapor->execute([$newRaporId, $member['siswa_nis'], $yearId]);
                }
            }
            $updateMember = $pdo->prepare("UPDATE anggota_kelas SET kelas_id_kelas = ? WHERE id_anggota = ?");
            $updateMember->execute([$classId, $memberId]);
            $pdo->commit();
            $pesan = ['success', 'Penempatan siswa berhasil dipindahkan. Rapor untuk tahun ajaran tujuan sudah dipastikan tersedia; data tahun sebelumnya tetap dipertahankan.'];
        } else {
            throw new InvalidArgumentException('Aksi tidak dikenal.');
        }
    } catch (InvalidArgumentException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $pesan = ['error', $e->getMessage()];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('CRUD kelas/anggota gagal: ' . $e->getMessage());
        if ($e->getCode() === '23000') {
            $pesan = $action === 'delete_kelas'
                ? ['error', 'Kelas tidak dapat dihapus karena masih memiliki anggota, jadwal, atau data terkait. Hapus relasi terkait terlebih dahulu; data tersebut tidak dihapus otomatis.']
                : ['error', 'Perubahan kelas atau anggota tidak dapat dilakukan karena data duplikat atau relasi yang dipilih tidak valid. Periksa data terkait lalu coba lagi.'];
        } else {
            $pesan = ['error', 'Perubahan kelas atau anggota gagal. Periksa data dan relasinya, lalu coba lagi.'];
        }
    } catch (RuntimeException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $pesan = ['error', $e->getMessage()];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('CRUD kelas/anggota gagal: ' . $e->getMessage());
        $pesan = ['error', 'Terjadi kesalahan saat memproses kelas atau anggota.'];
    } finally {
        if ($raporLockAcquired) {
            try {
                $pdo->query("SELECT RELEASE_LOCK('siakadu_rapor_id')");
            } catch (PDOException $e) {
                error_log('Gagal melepas lock pembuatan rapor: ' . $e->getMessage());
            }
        }
        if ($classLockAcquired) {
            try {
                $pdo->query("SELECT RELEASE_LOCK('siakadu_admin_kelas_id')");
            } catch (PDOException $e) {
                error_log('Gagal melepas lock pembuatan kelas: ' . $e->getMessage());
            }
        }
    }
}

$list_jurusan = $pdo->query("SELECT id_jurusan, nama_jurusan FROM jurusan ORDER BY nama_jurusan ASC")->fetchAll();
$list_guru = $pdo->query("SELECT nip, nama FROM guru ORDER BY nama ASC")->fetchAll();
$list_tahun_ajaran = $pdo->query("SELECT id_tahun, tahun, semester FROM tahun_ajaran ORDER BY id_tahun DESC")->fetchAll();
$list_kelas = $pdo->query(
    "SELECT k.*, j.nama_jurusan, g.nama AS nama_guru, t.tahun, t.semester
     FROM kelas k
     JOIN jurusan j ON k.jurusan_id_jurusan = j.id_jurusan
     JOIN guru g ON k.guru_nip = g.nip
     JOIN tahun_ajaran t ON k.tahun_ajaran_id_tahun = t.id_tahun
     ORDER BY k.nama_kelas ASC"
)->fetchAll();
$tahun_ajaran_aktif = $pdo->query(
    "SELECT id_tahun, tahun, semester
     FROM tahun_ajaran
     WHERE status_aktif = 'Y'
     LIMIT 1"
)->fetch();
$list_kelas_aktif = $pdo->query(
    "SELECT k.id_kelas, k.nama_kelas, t.tahun, t.semester
     FROM kelas k
     JOIN tahun_ajaran t ON k.tahun_ajaran_id_tahun = t.id_tahun
     WHERE t.status_aktif = 'Y'
     ORDER BY k.nama_kelas ASC"
)->fetchAll();
$list_siswa_belum_ada_kelas = $pdo->query(
    "SELECT s.nis, s.nama
     FROM siswa s
     WHERE s.status_siswa = 'Aktif'
       AND EXISTS (SELECT 1 FROM tahun_ajaran WHERE status_aktif = 'Y')
       AND NOT EXISTS (
           SELECT 1
           FROM anggota_kelas ak
           JOIN kelas k ON ak.kelas_id_kelas = k.id_kelas
           JOIN tahun_ajaran t ON k.tahun_ajaran_id_tahun = t.id_tahun
           WHERE ak.siswa_nis = s.nis AND t.status_aktif = 'Y'
       )
     ORDER BY s.nama ASC"
)->fetchAll();
$list_siswa = $pdo->query("SELECT nis, nama FROM siswa ORDER BY nama ASC")->fetchAll();
$list_anggota = $pdo->query(
    "SELECT ak.*, k.nama_kelas, k.tahun_ajaran_id_tahun, t.tahun, t.semester, s.nama AS nama_siswa,
            r.status_kenaikan
     FROM anggota_kelas ak
     JOIN kelas k ON ak.kelas_id_kelas = k.id_kelas
     JOIN tahun_ajaran t ON k.tahun_ajaran_id_tahun = t.id_tahun
     JOIN siswa s ON ak.siswa_nis = s.nis
     LEFT JOIN rapor r ON r.siswa_nis = ak.siswa_nis AND r.tahun_ajaran_id_tahun = k.tahun_ajaran_id_tahun
     ORDER BY k.nama_kelas ASC, s.nama ASC"
)->fetchAll();

$edit_kelas = null;
if (isset($_GET['edit_kelas'])) {
    $editId = validId($_GET['edit_kelas']);
    if ($editId !== false) {
        $stmt = $pdo->prepare("SELECT * FROM kelas WHERE id_kelas = ?");
        $stmt->execute([$editId]);
        $edit_kelas = $stmt->fetch() ?: null;
    }
    if (!$edit_kelas && !$pesan) {
        $pesan = ['error', 'Kelas yang dipilih untuk diubah tidak ditemukan.'];
    }
}
$page = 'kelas';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kelas &amp; Anggota - Admin SIAKAD</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include '../../components/sidebarAdmin.php'; ?>
    <main class="content">
        <?php include '../../components/navbarAdmin.php'; ?>
        <section class="admin-content">
        <header class="admin-page-heading">
            <h1>Kelola Kelas &amp; Anggota</h1>
            <a href="dashboard.php" class="back-link">&larr; Kembali ke Dashboard</a>
        </header>

        <?php if ($pesan): ?>
            <div class="admin-alert admin-alert-<?= h($pesan[0]) ?>"><?= h($pesan[1]) ?></div>
        <?php endif; ?>

        <div class="admin-form-cards">
            <div class="admin-form-card">
                <h3><?= $edit_kelas ? 'Ubah Data Kelas' : 'Tambah Kelas Baru' ?></h3>
                <form method="POST" action="" class="admin-stacked-form">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="action" value="save_kelas">
                    <?php if ($edit_kelas): ?><input type="hidden" name="id_kelas" value="<?= h($edit_kelas['id_kelas']) ?>"><?php endif; ?>
                    <input type="text" name="nama_kelas" placeholder="Nama Kelas (Cth: X IPA 1)" required maxlength="50" value="<?= h($edit_kelas['nama_kelas'] ?? '') ?>">
                    <select name="tingkat" required>
                        <option value="">-- Pilih Tingkat --</option>
                        <?php foreach (['X', 'XI', 'XII'] as $tingkat): ?>
                            <option value="<?= h($tingkat) ?>" <?= isset($edit_kelas['tingkat']) && $edit_kelas['tingkat'] === $tingkat ? 'selected' : '' ?>><?= h($tingkat) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="jurusan_id_jurusan" required>
                        <option value="">-- Pilih Jurusan --</option>
                        <?php foreach ($list_jurusan as $j): ?>
                            <option value="<?= h($j['id_jurusan']) ?>" <?= isset($edit_kelas['jurusan_id_jurusan']) && (int) $edit_kelas['jurusan_id_jurusan'] === (int) $j['id_jurusan'] ? 'selected' : '' ?>><?= h($j['nama_jurusan']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="guru_nip" required>
                        <option value="">-- Pilih Wali Kelas (Guru) --</option>
                        <?php foreach ($list_guru as $g): ?>
                            <option value="<?= h($g['nip']) ?>" <?= isset($edit_kelas['guru_nip']) && $edit_kelas['guru_nip'] === $g['nip'] ? 'selected' : '' ?>><?= h($g['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="tahun_ajaran_id_tahun" required>
                        <option value="">-- Pilih Tahun Ajaran --</option>
                        <?php foreach ($list_tahun_ajaran as $ta): ?>
                            <option value="<?= h($ta['id_tahun']) ?>" <?= isset($edit_kelas['tahun_ajaran_id_tahun']) && (int) $edit_kelas['tahun_ajaran_id_tahun'] === (int) $ta['id_tahun'] ? 'selected' : '' ?>><?= h($ta['tahun'] . ' ' . $ta['semester']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="admin-btn admin-btn-primary admin-btn-block"><?= $edit_kelas ? 'Simpan Perubahan' : 'Simpan Kelas' ?></button>
                    <?php if ($edit_kelas): ?><a href="m_kelas.php" class="admin-btn admin-btn-secondary">Batal</a><?php endif; ?>
                </form>
            </div>

            <div class="admin-form-card">
                <h3>Masukkan Siswa ke Kelas</h3>
                <form method="POST" action="" class="admin-stacked-form">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="action" value="add_member">
                    <select name="kelas_id" required>
                        <option value="">-- Pilih Kelas --</option>
                        <?php foreach ($list_kelas as $k): ?>
                            <option value="<?= h($k['id_kelas']) ?>"><?= h($k['nama_kelas'] . ' — ' . $k['tahun'] . ' ' . $k['semester']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="siswa_nis" required>
                        <option value="">-- Pilih Siswa --</option>
                        <?php foreach ($list_siswa as $s): ?>
                            <option value="<?= h($s['nis']) ?>"><?= h($s['nis'] . ' - ' . $s['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="admin-btn admin-btn-primary admin-btn-block">Masukkan ke Kelas</button>
                </form>
            </div>
        </div>

        <div class="admin-panel admin-table-panel unassigned-students">
            <h3>
                Siswa Belum Memiliki Kelas<?= $tahun_ajaran_aktif ? ' — ' . h($tahun_ajaran_aktif['tahun'] . ' ' . $tahun_ajaran_aktif['semester']) : '' ?>
            </h3>
            <?php if (!$tahun_ajaran_aktif): ?>
                <p class="dashboard-description">Belum ada tahun ajaran aktif. Aktifkan tahun ajaran untuk melihat siswa yang belum ditempatkan.</p>
            <?php else: ?>
            <div class="admin-table-tools">
                <input type="search" data-table-search="unassigned-student-list" placeholder="Cari NIS atau nama siswa..." aria-label="Cari siswa yang belum memiliki kelas">
            </div>
            <p class="admin-table-results" data-table-results="unassigned-student-list" aria-live="polite"></p>
            <p class="admin-table-no-results" data-table-empty="unassigned-student-list" hidden>Tidak ada siswa yang cocok.</p>
            <div class="admin-table-scroll">
                <table class="unassigned-students-table" id="unassigned-student-list">
                    <thead>
                        <tr>
                            <th>NIS</th>
                            <th>Nama Siswa</th>
                            <th>Tempatkan ke Kelas Aktif</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($list_siswa_belum_ada_kelas): foreach ($list_siswa_belum_ada_kelas as $siswa): ?>
                        <tr data-search="<?= h($siswa['nis'] . ' ' . $siswa['nama']) ?>">
                            <td><?= h($siswa['nis']) ?></td>
                            <td><?= h($siswa['nama']) ?></td>
                            <td>
                                <?php if ($list_kelas_aktif): ?>
                                <form method="POST" action="" class="unassigned-student-action">
                                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                    <input type="hidden" name="action" value="add_member">
                                    <input type="hidden" name="siswa_nis" value="<?= h($siswa['nis']) ?>">
                                    <select name="kelas_id" required aria-label="Pilih kelas untuk <?= h($siswa['nama']) ?>">
                                        <option value="">-- Pilih Kelas --</option>
                                        <?php foreach ($list_kelas_aktif as $kelas_aktif): ?>
                                            <option value="<?= h($kelas_aktif['id_kelas']) ?>"><?= h($kelas_aktif['nama_kelas'] . ' — ' . $kelas_aktif['tahun'] . ' ' . $kelas_aktif['semester']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="admin-btn admin-btn-primary">Tempatkan</button>
                                </form>
                                <?php else: ?>
                                    <span>Belum ada kelas untuk tahun ajaran aktif.</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr data-empty-state><td colspan="3" class="unassigned-students-empty">Semua siswa aktif sudah memiliki kelas pada tahun ajaran aktif.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <div class="admin-panel admin-table-panel admin-spaced-panel">
            <h3>Daftar Kelas</h3>
            <div class="admin-table-tools">
                <input type="search" data-table-search="admin-class-list" placeholder="Cari nama kelas, tingkat, jurusan, wali kelas..." aria-label="Cari kelas, jurusan, atau wali kelas">
            </div>
            <p class="admin-table-results" data-table-results="admin-class-list" aria-live="polite"></p>
            <p class="admin-table-no-results" data-table-empty="admin-class-list" hidden>Tidak ada kelas yang sesuai dengan pencarian.</p>
            <div class="admin-table-scroll">
            <table class="admin-data-table admin-table-wide" id="admin-class-list">
                <thead><tr>
                    <th>Nama Kelas</th><th>Tingkat</th><th>Jurusan</th>
                    <th>Wali Kelas</th><th>Tahun Ajaran</th><th>Aksi</th>
                </tr></thead>
                <tbody>
                    <?php if ($list_kelas): foreach ($list_kelas as $k): ?>
                    <tr data-search="<?= h(implode(' ', [$k['nama_kelas'], $k['tingkat'], $k['nama_jurusan'], $k['nama_guru'], $k['tahun'], $k['semester']])) ?>">
                        <td class="admin-primary-cell"><?= h($k['nama_kelas']) ?></td>
                        <td><?= h($k['tingkat']) ?></td>
                        <td><?= h($k['nama_jurusan']) ?></td>
                        <td><?= h($k['nama_guru']) ?></td>
                        <td><?= h($k['tahun'] . ' ' . $k['semester']) ?></td>
                        <td class="admin-action-cell">
                            <a href="?edit_kelas=<?= rawurlencode((string) $k['id_kelas']) ?>" class="admin-btn admin-btn-secondary">Ubah</a>
                            <form method="POST" action="" class="admin-inline-action" onsubmit="return confirm('Hapus kelas ini? Kelas yang masih memiliki anggota atau jadwal tidak dapat dihapus.');">
                                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                <input type="hidden" name="action" value="delete_kelas">
                                <input type="hidden" name="id_kelas" value="<?= h($k['id_kelas']) ?>">
                                <button type="submit" class="admin-btn admin-btn-danger">Hapus</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr data-empty-state><td colspan="6" class="admin-empty-state">Belum ada kelas.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>

        <div class="admin-panel admin-table-panel">
            <h3>Daftar Penempatan Siswa dalam Kelas</h3>
            <div class="admin-table-tools">
                <input type="search" data-table-search="admin-class-members" placeholder="Cari NIS, nama siswa, kelas, tahun ajaran..." aria-label="Cari siswa berdasarkan NIS, nama, kelas, atau tahun ajaran">
                <select data-table-filter="admin-class-members" aria-label="Filter status kenaikan kelas">
                    <option value="">Semua status kenaikan</option>
                    <option value="promoted">Naik Kelas</option>
                    <option value="retained">Tinggal Kelas</option>
                    <option value="pending">Belum ditentukan</option>
                    <option value="other">Status lainnya</option>
                </select>
            </div>
            <p class="admin-table-results" data-table-results="admin-class-members" aria-live="polite"></p>
            <p class="admin-table-no-results" data-table-empty="admin-class-members" hidden>Tidak ada penempatan yang sesuai dengan pencarian.</p>
            <div class="admin-table-scroll">
            <table class="admin-data-table admin-table-wide" id="admin-class-members">
                <thead><tr>
                    <th>No</th><th>Nama Kelas</th><th>Tahun Ajaran</th>
                    <th>NIS</th><th>Nama Siswa</th><th>Status Kenaikan</th><th>Aksi</th>
                </tr></thead>
                <tbody>
                    <?php if ($list_anggota): $no = 1; foreach ($list_anggota as $la):
                        $statusKenaikan = trim((string) ($la['status_kenaikan'] ?? ''));
                        $statusLabel = $statusKenaikan !== '' ? $statusKenaikan : 'Belum ditentukan';
                        $statusNormalized = strtolower($statusLabel);
                        if (str_contains($statusNormalized, 'tinggal') || str_contains($statusNormalized, 'tidak naik')) {
                            $statusFilter = 'retained';
                        } elseif (str_contains($statusNormalized, 'naik')) {
                            $statusFilter = 'promoted';
                        } elseif (str_contains($statusNormalized, 'belum')) {
                            $statusFilter = 'pending';
                        } else {
                            $statusFilter = 'other';
                        }
                        $searchText = implode(' ', [
                            $la['nama_kelas'], $la['tahun'], $la['semester'], $la['siswa_nis'],
                            $la['nama_siswa'], $statusLabel,
                        ]);
                    ?>
                    <tr data-status-filter="<?= h($statusFilter) ?>" data-search="<?= h($searchText) ?>">
                        <td><?= $no++ ?></td>
                        <td class="admin-primary-cell"><?= h($la['nama_kelas']) ?></td>
                        <td><?= h($la['tahun'] . ' ' . $la['semester']) ?></td>
                        <td><?= h($la['siswa_nis']) ?></td>
                        <td class="admin-primary-cell"><?= h($la['nama_siswa']) ?></td>
                        <td><span class="promotion-status promotion-status-<?= h($statusFilter) ?>"><?= h($statusLabel) ?></span></td>
                        <td class="admin-action-cell">
                            <form method="POST" action="" class="admin-inline-action">
                                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                <input type="hidden" name="action" value="move_member">
                                <input type="hidden" name="id_anggota" value="<?= h($la['id_anggota']) ?>">
                                <select name="kelas_id" required aria-label="Pindahkan siswa ke kelas">
                                    <option value="">Pindahkan ke...</option>
                                    <?php foreach ($list_kelas as $k): ?>
                                        <option value="<?= h($k['id_kelas']) ?>" <?= (int) $la['kelas_id_kelas'] === (int) $k['id_kelas'] ? 'disabled' : '' ?>><?= h($k['nama_kelas'] . ' — ' . $k['tahun'] . ' ' . $k['semester']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="admin-btn admin-btn-primary">Pindahkan</button>
                            </form>
                            <form method="POST" action="" class="admin-inline-action" onsubmit="return confirm('Keluarkan siswa ini dari kelas? Data siswa dan rapor tidak akan dihapus.');">
                                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                <input type="hidden" name="action" value="remove_member">
                                <input type="hidden" name="id_anggota" value="<?= h($la['id_anggota']) ?>">
                                <button type="submit" class="admin-btn admin-btn-danger">Keluarkan</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr data-empty-state><td colspan="7" class="admin-empty-state">Belum ada siswa yang dimasukkan ke dalam kelas.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>
        </section>
    </main>
    <script src="../../Views/table-search.js" defer></script>
</body>
</html>
