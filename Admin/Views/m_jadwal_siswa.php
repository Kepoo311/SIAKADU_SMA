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
    try {
        if (!isset($_POST['csrf_token']) || !hash_equals($csrfToken, (string) $_POST['csrf_token'])) {
            throw new InvalidArgumentException('Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.');
        }

        $action = $_POST['action'] ?? '';
        if ($action === 'save_mapel') {
            $oldCode = isset($_POST['old_kode_mapel']) && is_string($_POST['old_kode_mapel'])
                ? trim($_POST['old_kode_mapel']) : '';
            $code = isset($_POST['kode_mapel']) && is_string($_POST['kode_mapel'])
                ? trim($_POST['kode_mapel']) : '';
            $name = isset($_POST['nama_mapel']) && is_string($_POST['nama_mapel'])
                ? trim($_POST['nama_mapel']) : '';
            $kkm = filter_var($_POST['kkm'] ?? null, FILTER_VALIDATE_INT);
            $yearId = validId($_POST['tahun_ajaran_id_tahun'] ?? null);

            if ($code === '' || strlen($code) > 20 || $name === '' || strlen($name) > 100) {
                throw new InvalidArgumentException('Kode (maksimal 20 karakter) dan nama mata pelajaran (maksimal 100 karakter) wajib diisi.');
            }
            if ($kkm === false || $kkm < 0 || $kkm > 100) {
                throw new InvalidArgumentException('KKM harus berupa bilangan bulat antara 0 dan 100.');
            }
            if ($yearId === false) {
                throw new InvalidArgumentException('Tahun ajaran wajib dipilih dengan ID yang valid.');
            }
            $checkYear = $pdo->prepare("SELECT 1 FROM tahun_ajaran WHERE id_tahun = ?");
            $checkYear->execute([$yearId]);
            if (!$checkYear->fetchColumn()) {
                throw new InvalidArgumentException('Tahun ajaran yang dipilih tidak ditemukan.');
            }

            if ($oldCode !== '') {
                $checkOld = $pdo->prepare("SELECT 1 FROM mata_pelajaran WHERE kode_mapel = ?");
                $checkOld->execute([$oldCode]);
                if (!$checkOld->fetchColumn()) {
                    throw new InvalidArgumentException('Mata pelajaran yang akan diubah tidak ditemukan.');
                }
                $stmt = $pdo->prepare("UPDATE mata_pelajaran SET kode_mapel = ?, nama_mapel = ?, kkm = ?, tahun_ajaran_id_tahun = ? WHERE kode_mapel = ?");
                $stmt->execute([$code, $name, $kkm, $yearId, $oldCode]);
                $pesan = ['success', 'Mata pelajaran berhasil diperbarui.'];
            } else {
                $stmt = $pdo->prepare("INSERT INTO mata_pelajaran (kode_mapel, nama_mapel, kkm, tahun_ajaran_id_tahun) VALUES (?, ?, ?, ?)");
                $stmt->execute([$code, $name, $kkm, $yearId]);
                $pesan = ['success', 'Mata pelajaran berhasil ditambahkan.'];
            }
        } elseif ($action === 'delete_mapel') {
            $code = isset($_POST['kode_mapel']) && is_string($_POST['kode_mapel'])
                ? trim($_POST['kode_mapel']) : '';
            if ($code === '' || strlen($code) > 20) {
                throw new InvalidArgumentException('Kode mata pelajaran tidak valid.');
            }
            $stmt = $pdo->prepare("DELETE FROM mata_pelajaran WHERE kode_mapel = ?");
            $stmt->execute([$code]);
            if ($stmt->rowCount() === 0) {
                throw new InvalidArgumentException('Mata pelajaran tidak ditemukan atau sudah dihapus.');
            }
            $pesan = ['success', 'Mata pelajaran berhasil dihapus.'];
        } else {
            throw new InvalidArgumentException('Aksi tidak dikenal.');
        }
    } catch (InvalidArgumentException $e) {
        $pesan = ['error', $e->getMessage()];
    } catch (PDOException $e) {
        error_log('CRUD mata pelajaran gagal: ' . $e->getMessage());
        if ($e->getCode() === '23000') {
            $pesan = ['error', 'Perubahan tidak dapat dilakukan karena kode mata pelajaran sudah digunakan atau mata pelajaran ini masih dirujuk jadwal. Hapus relasi terkait terlebih dahulu; data terkait tidak dihapus otomatis.'];
        } else {
            $pesan = ['error', 'Mata pelajaran gagal disimpan. Periksa data dan relasinya, lalu coba lagi.'];
        }
    } catch (Throwable $e) {
        error_log('CRUD mata pelajaran gagal: ' . $e->getMessage());
        $pesan = ['error', 'Terjadi kesalahan saat memproses mata pelajaran.'];
    }
}

$list_tahun_ajaran = $pdo->query("SELECT id_tahun, tahun, semester FROM tahun_ajaran ORDER BY id_tahun DESC")->fetchAll();
$list_mapel = $pdo->query(
    "SELECT m.*, t.tahun, t.semester
     FROM mata_pelajaran m
     LEFT JOIN tahun_ajaran t ON m.tahun_ajaran_id_tahun = t.id_tahun
     ORDER BY m.kode_mapel ASC"
)->fetchAll();

$edit_mapel = null;
if (isset($_GET['edit_mapel'])) {
    $editCode = is_string($_GET['edit_mapel']) ? trim($_GET['edit_mapel']) : '';
    if ($editCode !== '' && strlen($editCode) <= 20) {
        $stmt = $pdo->prepare("SELECT * FROM mata_pelajaran WHERE kode_mapel = ?");
        $stmt->execute([$editCode]);
        $edit_mapel = $stmt->fetch() ?: null;
    }
    if (!$edit_mapel && !$pesan) {
        $pesan = ['error', 'Mata pelajaran yang dipilih untuk diubah tidak ditemukan.'];
    }
}
$page = 'mapel';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Mata Pelajaran - Admin SIAKAD</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include '../../components/sidebarAdmin.php'; ?>
    <main class="content">
        <?php include '../../components/navbarAdmin.php'; ?>
        <section class="admin-content">
        <header class="admin-page-heading">
            <h1>Kelola Mata Pelajaran</h1>
            <a href="dashboard.php" class="back-link">&larr; Kembali ke Dashboard</a>
        </header>

        <?php if ($pesan): ?>
            <div class="admin-alert admin-alert-<?= h($pesan[0]) ?>"><?= h($pesan[1]) ?></div>
        <?php endif; ?>

        <div class="admin-panel admin-form-panel">
            <h3><?= $edit_mapel ? 'Ubah Mata Pelajaran' : 'Tambah Mata Pelajaran Baru' ?></h3>
            <form method="POST" action="" class="admin-form-grid">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="action" value="save_mapel">
                <?php if ($edit_mapel): ?>
                    <input type="hidden" name="old_kode_mapel" value="<?= h($edit_mapel['kode_mapel']) ?>">
                <?php endif; ?>
                <label><span>Kode Mapel</span><input type="text" name="kode_mapel" placeholder="Contoh: MP111" required maxlength="20" value="<?= h($edit_mapel['kode_mapel'] ?? '') ?>"></label>
                <label><span>Nama Mata Pelajaran</span><input type="text" name="nama_mapel" placeholder="Nama Mata Pelajaran" required maxlength="100" value="<?= h($edit_mapel['nama_mapel'] ?? '') ?>"></label>
                <label><span>Nilai KKM</span><input type="number" name="kkm" placeholder="Contoh: 75" required min="0" max="100" step="1" value="<?= h($edit_mapel['kkm'] ?? '') ?>"></label>
                <label><span>Tahun Ajaran</span><select name="tahun_ajaran_id_tahun" required>
                    <option value="">-- Pilih Tahun Ajaran --</option>
                    <?php foreach ($list_tahun_ajaran as $ta): ?>
                        <option value="<?= h($ta['id_tahun']) ?>" <?= isset($edit_mapel['tahun_ajaran_id_tahun']) && (int) $edit_mapel['tahun_ajaran_id_tahun'] === (int) $ta['id_tahun'] ? 'selected' : '' ?>><?= h($ta['tahun'] . ' ' . $ta['semester']) ?></option>
                    <?php endforeach; ?>
                </select></label>
                <div class="admin-form-actions">
                    <button type="submit" class="admin-btn admin-btn-primary"><?= $edit_mapel ? 'Simpan Perubahan' : 'Simpan Mapel' ?></button>
                    <?php if ($edit_mapel): ?><a href="m_jadwal_siswa.php" class="admin-btn admin-btn-secondary">Batal</a><?php endif; ?>
                </div>
            </form>
        </div>

        <div class="admin-panel admin-table-panel">
            <h3>Daftar Mata Pelajaran</h3>
            <div class="admin-table-tools">
                <input type="search" data-table-search="admin-subject-list" placeholder="Cari kode, nama mata pelajaran, atau tahun ajaran..." aria-label="Cari mata pelajaran berdasarkan kode, nama, atau tahun ajaran">
            </div>
            <p class="admin-table-results" data-table-results="admin-subject-list" aria-live="polite"></p>
            <p class="admin-table-no-results" data-table-empty="admin-subject-list" hidden>Tidak ada mata pelajaran yang sesuai dengan pencarian.</p>
            <div class="admin-table-scroll">
            <table class="admin-data-table admin-table-wide" id="admin-subject-list">
                <thead>
                    <tr>
                        <th>Kode Mapel</th>
                        <th>Nama Mata Pelajaran</th>
                        <th>KKM</th>
                        <th>Tahun Ajaran</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($list_mapel): foreach ($list_mapel as $m): ?>
                    <tr data-search="<?= h(implode(' ', [$m['kode_mapel'], $m['nama_mapel'], $m['kkm'], $m['tahun'] ?? '', $m['semester'] ?? ''])) ?>">
                        <td class="admin-primary-cell"><?= h($m['kode_mapel']) ?></td>
                        <td class="admin-primary-cell"><?= h($m['nama_mapel']) ?></td>
                        <td><?= h($m['kkm']) ?></td>
                        <td><?= h(($m['tahun'] ?? '-') . ' ' . ($m['semester'] ?? '')) ?></td>
                        <td class="admin-action-cell">
                            <a href="?edit_mapel=<?= rawurlencode($m['kode_mapel']) ?>" class="admin-btn admin-btn-secondary">Ubah</a>
                            <form method="POST" action="" class="admin-inline-action" onsubmit="return confirm('Hapus mata pelajaran ini? Penghapusan akan ditolak jika masih dirujuk jadwal.');">
                                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                <input type="hidden" name="action" value="delete_mapel">
                                <input type="hidden" name="kode_mapel" value="<?= h($m['kode_mapel']) ?>">
                                <button type="submit" class="admin-btn admin-btn-danger">Hapus</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr data-empty-state><td colspan="5" class="admin-empty-state">Belum ada mata pelajaran.</td></tr>
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
