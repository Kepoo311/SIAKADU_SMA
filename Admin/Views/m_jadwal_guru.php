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

$hariList = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
if (empty($_SESSION['admin_crud_token'])) {
    $_SESSION['admin_crud_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['admin_crud_token'];
$pesan = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lockAcquired = false;
    try {
        if (!isset($_POST['csrf_token']) || !hash_equals($csrfToken, (string) $_POST['csrf_token'])) {
            throw new InvalidArgumentException('Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.');
        }
        $action = $_POST['action'] ?? '';
        if ($action === 'save_jadwal') {
            $jadwalId = isset($_POST['id_jadwal']) ? validId($_POST['id_jadwal']) : false;
            $kelasId = validId($_POST['kelas_id_kelas'] ?? null);
            $mapelCode = isset($_POST['mapel_kode_mapel']) && is_string($_POST['mapel_kode_mapel'])
                ? trim($_POST['mapel_kode_mapel']) : '';
            $guruNip = isset($_POST['guru_nip']) && is_string($_POST['guru_nip'])
                ? trim($_POST['guru_nip']) : '';
            $hari = isset($_POST['hari']) && is_string($_POST['hari']) ? $_POST['hari'] : '';
            $jamMulai = isset($_POST['jam_mulai']) && is_string($_POST['jam_mulai']) ? $_POST['jam_mulai'] : '';
            $jamSelesai = isset($_POST['jam_selesai']) && is_string($_POST['jam_selesai']) ? $_POST['jam_selesai'] : '';
            $isEdit = isset($_POST['id_jadwal']);

            if ($isEdit && $jadwalId === false) {
                throw new InvalidArgumentException('ID jadwal tidak valid.');
            }
            if ($kelasId === false || $mapelCode === '' || strlen($mapelCode) > 20 || $guruNip === '' || strlen($guruNip) > 30) {
                throw new InvalidArgumentException('Kelas, mata pelajaran, dan guru wajib dipilih dengan ID yang valid.');
            }
            if (!in_array($hari, $hariList, true)) {
                throw new InvalidArgumentException('Hari jadwal harus Senin sampai Sabtu.');
            }
            $timePattern = '/^(?:[01]\d|2[0-3]):[0-5]\d$/';
            if (!preg_match($timePattern, $jamMulai) || !preg_match($timePattern, $jamSelesai) || $jamMulai >= $jamSelesai) {
                throw new InvalidArgumentException('Jam harus valid dan jam selesai harus setelah jam mulai.');
            }

            foreach ([
                ['SELECT 1 FROM kelas WHERE id_kelas = ?', $kelasId, 'Kelas'],
                ['SELECT 1 FROM mata_pelajaran WHERE kode_mapel = ?', $mapelCode, 'Mata pelajaran'],
                ['SELECT 1 FROM guru WHERE nip = ?', $guruNip, 'Guru'],
            ] as [$sql, $value, $label]) {
                $check = $pdo->prepare($sql);
                $check->execute([$value]);
                if (!$check->fetchColumn()) {
                    throw new InvalidArgumentException($label . ' yang dipilih tidak ditemukan.');
                }
            }

            if ($isEdit) {
                $check = $pdo->prepare("SELECT 1 FROM jadwal WHERE id_jadwal = ?");
                $check->execute([$jadwalId]);
                if (!$check->fetchColumn()) {
                    throw new InvalidArgumentException('Jadwal yang akan diubah tidak ditemukan.');
                }
                $stmt = $pdo->prepare("UPDATE jadwal SET kelas_id_kelas = ?, mapel_kode_mapel = ?, guru_nip = ?, hari = ?, jam_mulai = ?, jam_selesai = ? WHERE id_jadwal = ?");
                $stmt->execute([$kelasId, $mapelCode, $guruNip, $hari, $jamMulai, $jamSelesai, $jadwalId]);
                $pesan = ['success', 'Jadwal berhasil diperbarui.'];
            } else {
                $pdo->beginTransaction();
                $lockAcquired = (int) $pdo->query("SELECT GET_LOCK('siakadu_jadwal_id', 5)")->fetchColumn() === 1;
                if (!$lockAcquired) {
                    throw new RuntimeException('Tidak dapat menyiapkan ID jadwal saat ini. Silakan coba lagi.');
                }
                $newId = (int) $pdo->query("SELECT COALESCE(MAX(id_jadwal), 0) + 1 FROM jadwal")->fetchColumn();
                $stmt = $pdo->prepare("INSERT INTO jadwal (id_jadwal, kelas_id_kelas, mapel_kode_mapel, guru_nip, hari, jam_mulai, jam_selesai) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$newId, $kelasId, $mapelCode, $guruNip, $hari, $jamMulai, $jamSelesai]);
                $pdo->commit();
                $pesan = ['success', 'Jadwal berhasil ditambahkan.'];
            }
        } elseif ($action === 'delete_jadwal') {
            $jadwalId = validId($_POST['id_jadwal'] ?? null);
            if ($jadwalId === false) {
                throw new InvalidArgumentException('ID jadwal tidak valid.');
            }
            $checkAbsensi = $pdo->prepare("SELECT COUNT(*) FROM absensi WHERE jadwal_id_jadwal = ?");
            $checkAbsensi->execute([$jadwalId]);
            if ((int) $checkAbsensi->fetchColumn() > 0) {
                throw new InvalidArgumentException('Jadwal tidak dapat dihapus karena masih digunakan pada data absensi. Hapus data absensi terkait terlebih dahulu.');
            }
            $stmt = $pdo->prepare("DELETE FROM jadwal WHERE id_jadwal = ?");
            $stmt->execute([$jadwalId]);
            if ($stmt->rowCount() === 0) {
                throw new InvalidArgumentException('Jadwal tidak ditemukan atau sudah dihapus.');
            }
            $pesan = ['success', 'Jadwal berhasil dihapus.'];
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
        error_log('CRUD jadwal gagal: ' . $e->getMessage());
        if ($action === 'delete_jadwal' && $e->getCode() === '23000') {
            $pesan = ['error', 'Jadwal tidak dapat dihapus karena masih digunakan oleh data terkait. Data terkait tidak dihapus otomatis.'];
        } elseif ($e->getCode() === '23000') {
            $pesan = ['error', 'Jadwal tidak dapat disimpan karena data duplikat atau relasi yang dipilih tidak valid.'];
        } else {
            $pesan = ['error', 'Jadwal gagal disimpan. Periksa data dan relasinya, lalu coba lagi.'];
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
        error_log('CRUD jadwal gagal: ' . $e->getMessage());
        $pesan = ['error', 'Terjadi kesalahan saat memproses jadwal.'];
    } finally {
        if ($lockAcquired) {
            try {
                $pdo->query("SELECT RELEASE_LOCK('siakadu_jadwal_id')");
            } catch (PDOException $e) {
                error_log('Gagal melepas lock ID jadwal: ' . $e->getMessage());
            }
        }
    }
}

$list_kelas = $pdo->query("SELECT id_kelas, nama_kelas FROM kelas ORDER BY nama_kelas ASC")->fetchAll();
$list_mapel = $pdo->query("SELECT kode_mapel, nama_mapel FROM mata_pelajaran ORDER BY nama_mapel ASC")->fetchAll();
$list_guru = $pdo->query("SELECT nip, nama FROM guru ORDER BY nama ASC")->fetchAll();
$sqlJadwal = "SELECT j.*, k.nama_kelas, m.nama_mapel, g.nama AS nama_guru
              FROM jadwal j
              JOIN kelas k ON j.kelas_id_kelas = k.id_kelas
              JOIN mata_pelajaran m ON j.mapel_kode_mapel = m.kode_mapel
              JOIN guru g ON j.guru_nip = g.nip
              ORDER BY FIELD(j.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'), j.jam_mulai ASC";
$list_jadwal = $pdo->query($sqlJadwal)->fetchAll();

$edit_jadwal = null;
if (isset($_GET['edit_jadwal'])) {
    $editId = validId($_GET['edit_jadwal']);
    if ($editId !== false) {
        $stmt = $pdo->prepare("SELECT * FROM jadwal WHERE id_jadwal = ?");
        $stmt->execute([$editId]);
        $edit_jadwal = $stmt->fetch() ?: null;
    }
    if (!$edit_jadwal && !$pesan) {
        $pesan = ['error', 'Jadwal yang dipilih untuk diubah tidak ditemukan.'];
    }
}
$page = 'jadwal';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Jadwal Pelajaran - Admin SIAKAD</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include '../../components/sidebarAdmin.php'; ?>
    <main class="content">
        <?php include '../../components/navbarAdmin.php'; ?>
        <section class="admin-content">
        <header class="admin-page-heading">
            <h1>Kelola Jadwal Pelajaran</h1>
            <a href="dashboard.php" class="back-link">&larr; Kembali ke Dashboard</a>
        </header>

        <?php if ($pesan): ?>
            <div class="admin-alert admin-alert-<?= h($pesan[0]) ?>"><?= h($pesan[1]) ?></div>
        <?php endif; ?>

        <div class="admin-panel admin-form-panel">
            <h3><?= $edit_jadwal ? 'Ubah Jadwal Mengajar' : 'Tambah Jadwal Mengajar Baru' ?></h3>
            <form method="POST" action="" class="admin-form-grid">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="action" value="save_jadwal">
                <?php if ($edit_jadwal): ?><input type="hidden" name="id_jadwal" value="<?= h($edit_jadwal['id_jadwal']) ?>"><?php endif; ?>
                <label><span>Kelas</span><select name="kelas_id_kelas" required>
                    <option value="">-- Pilih Kelas --</option>
                    <?php foreach ($list_kelas as $k): ?>
                        <option value="<?= h($k['id_kelas']) ?>" <?= isset($edit_jadwal['kelas_id_kelas']) && (int) $edit_jadwal['kelas_id_kelas'] === (int) $k['id_kelas'] ? 'selected' : '' ?>><?= h($k['nama_kelas']) ?></option>
                    <?php endforeach; ?>
                </select></label>
                <label><span>Mata Pelajaran</span><select name="mapel_kode_mapel" required>
                    <option value="">-- Pilih Mata Pelajaran --</option>
                    <?php foreach ($list_mapel as $m): ?>
                        <option value="<?= h($m['kode_mapel']) ?>" <?= isset($edit_jadwal['mapel_kode_mapel']) && $edit_jadwal['mapel_kode_mapel'] === $m['kode_mapel'] ? 'selected' : '' ?>><?= h($m['nama_mapel']) ?></option>
                    <?php endforeach; ?>
                </select></label>
                <label><span>Guru Pengampu</span><select name="guru_nip" required>
                    <option value="">-- Pilih Guru Pengampu --</option>
                    <?php foreach ($list_guru as $g): ?>
                        <option value="<?= h($g['nip']) ?>" <?= isset($edit_jadwal['guru_nip']) && $edit_jadwal['guru_nip'] === $g['nip'] ? 'selected' : '' ?>><?= h($g['nama']) ?></option>
                    <?php endforeach; ?>
                </select></label>
                <label><span>Hari</span><select name="hari" required>
                    <option value="">-- Pilih Hari --</option>
                    <?php foreach ($hariList as $hari): ?>
                        <option value="<?= h($hari) ?>" <?= isset($edit_jadwal['hari']) && $edit_jadwal['hari'] === $hari ? 'selected' : '' ?>><?= h($hari) ?></option>
                    <?php endforeach; ?>
                </select></label>
                <label><span>Jam Mulai</span><input type="time" name="jam_mulai" required value="<?= h(isset($edit_jadwal['jam_mulai']) ? substr($edit_jadwal['jam_mulai'], 0, 5) : '') ?>"></label>
                <label><span>Jam Selesai</span><input type="time" name="jam_selesai" required value="<?= h(isset($edit_jadwal['jam_selesai']) ? substr($edit_jadwal['jam_selesai'], 0, 5) : '') ?>"></label>
                <div class="admin-form-actions">
                    <button type="submit" class="admin-btn admin-btn-primary"><?= $edit_jadwal ? 'Simpan Perubahan' : 'Simpan Jadwal' ?></button>
                    <?php if ($edit_jadwal): ?><a href="m_jadwal_guru.php" class="admin-btn admin-btn-secondary">Batal</a><?php endif; ?>
                </div>
            </form>
        </div>

        <div class="admin-panel admin-table-panel">
            <h3>Daftar Jadwal Pelajaran Sekolah</h3>
            <div class="admin-table-tools">
                <input type="search" data-table-search="admin-schedule-list" placeholder="Cari kelas, mata pelajaran, guru, atau hari..." aria-label="Cari jadwal berdasarkan kelas, mata pelajaran, guru, atau hari">
            </div>
            <p class="admin-table-results" data-table-results="admin-schedule-list" aria-live="polite"></p>
            <p class="admin-table-no-results" data-table-empty="admin-schedule-list" hidden>Tidak ada jadwal yang sesuai dengan pencarian.</p>
            <div class="admin-table-scroll">
            <table class="admin-data-table admin-table-xl" id="admin-schedule-list">
                <thead>
                    <tr>
                        <th>Hari &amp; Waktu</th>
                        <th>Kelas</th>
                        <th>Mata Pelajaran</th>
                        <th>Guru Pengampu</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($list_jadwal): foreach ($list_jadwal as $j): ?>
                    <tr data-search="<?= h(implode(' ', [$j['hari'], $j['jam_mulai'], $j['jam_selesai'], $j['nama_kelas'], $j['nama_mapel'], $j['nama_guru']])) ?>">
                        <td><b><?= h($j['hari']) ?></b><br><span class="admin-table-subtext"><?= h($j['jam_mulai']) ?> - <?= h($j['jam_selesai']) ?></span></td>
                        <td class="admin-primary-cell"><?= h($j['nama_kelas']) ?></td>
                        <td class="admin-primary-cell"><?= h($j['nama_mapel']) ?></td>
                        <td><?= h($j['nama_guru']) ?></td>
                        <td class="admin-action-cell">
                            <a href="?edit_jadwal=<?= rawurlencode((string) $j['id_jadwal']) ?>" class="admin-btn admin-btn-secondary">Ubah</a>
                            <form method="POST" action="" class="admin-inline-action" onsubmit="return confirm('Hapus jadwal ini? Penghapusan akan ditolak jika masih dirujuk data lain.');">
                                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                <input type="hidden" name="action" value="delete_jadwal">
                                <input type="hidden" name="id_jadwal" value="<?= h($j['id_jadwal']) ?>">
                                <button type="submit" class="admin-btn admin-btn-danger">Hapus</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr data-empty-state><td colspan="5" class="admin-empty-state">Belum ada jadwal pelajaran yang diatur.</td></tr>
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
