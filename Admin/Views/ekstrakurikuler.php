<?php
session_start();
require "../../DB/koneksi.php";

if (!isset($_SESSION['role_id']) || (int) $_SESSION['role_id'] !== 1) {
    http_response_code(403);
    header("Location: ../../Views/login.php");
    exit;
}

function ekskul_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function ekskul_valid_id($value)
{
    $id = filter_var($value, FILTER_VALIDATE_INT);
    return $id !== false && $id > 0 ? $id : false;
}

$hariList = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
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
        if ($action === 'save_ekskul') {
            $id = isset($_POST['id_ekskul']) ? ekskul_valid_id($_POST['id_ekskul']) : false;
            $isEdit = isset($_POST['id_ekskul']);
            $nama = isset($_POST['nama_ekskul']) && is_string($_POST['nama_ekskul']) ? trim($_POST['nama_ekskul']) : '';
            $hari = isset($_POST['hari_kegiatan']) && is_string($_POST['hari_kegiatan']) ? $_POST['hari_kegiatan'] : '';
            $tempat = isset($_POST['tempat']) && is_string($_POST['tempat']) ? trim($_POST['tempat']) : '';
            $guruNip = isset($_POST['guru_nip']) && is_string($_POST['guru_nip']) ? trim($_POST['guru_nip']) : '';

            if ($isEdit && $id === false) {
                throw new InvalidArgumentException('ID ekstrakurikuler tidak valid.');
            }
            if ($nama === '' || strlen($nama) > 100 || $tempat === '' || strlen($tempat) > 100 ||
                $guruNip === '' || strlen($guruNip) > 30 || !in_array($hari, $hariList, true)) {
                throw new InvalidArgumentException('Nama, hari kegiatan, tempat, dan pembina wajib diisi dengan format yang valid.');
            }
            $checkGuru = $pdo->prepare("SELECT 1 FROM guru WHERE nip = ?");
            $checkGuru->execute([$guruNip]);
            if (!$checkGuru->fetchColumn()) {
                throw new InvalidArgumentException('Guru pembina yang dipilih tidak ditemukan.');
            }

            if ($isEdit) {
                $check = $pdo->prepare("SELECT 1 FROM ekstrakurikuler WHERE id_ekskul = ?");
                $check->execute([$id]);
                if (!$check->fetchColumn()) {
                    throw new InvalidArgumentException('Ekstrakurikuler yang akan diubah tidak ditemukan.');
                }
                $stmt = $pdo->prepare(
                    "UPDATE ekstrakurikuler
                     SET nama_ekskul = ?, hari_kegiatan = ?, tempat = ?, guru_nip = ?
                     WHERE id_ekskul = ?"
                );
                $stmt->execute([$nama, $hari, $tempat, $guruNip, $id]);
                $pesan = ['success', 'Data ekstrakurikuler berhasil diperbarui.'];
            } else {
                $pdo->beginTransaction();
                $lockAcquired = (int) $pdo->query("SELECT GET_LOCK('siakadu_ekskul_id', 5)")->fetchColumn() === 1;
                if (!$lockAcquired) {
                    throw new RuntimeException('Tidak dapat menyiapkan ID ekstrakurikuler saat ini. Silakan coba lagi.');
                }
                $newId = (int) $pdo->query("SELECT COALESCE(MAX(id_ekskul), 0) + 1 FROM ekstrakurikuler")->fetchColumn();
                $stmt = $pdo->prepare(
                    "INSERT INTO ekstrakurikuler (id_ekskul, nama_ekskul, hari_kegiatan, tempat, guru_nip)
                     VALUES (?, ?, ?, ?, ?)"
                );
                $stmt->execute([$newId, $nama, $hari, $tempat, $guruNip]);
                $pdo->commit();
                $pesan = ['success', 'Ekstrakurikuler berhasil ditambahkan.'];
            }
        } elseif ($action === 'delete_ekskul') {
            $id = ekskul_valid_id($_POST['id_ekskul'] ?? null);
            if ($id === false) {
                throw new InvalidArgumentException('ID ekstrakurikuler tidak valid.');
            }
            $stmt = $pdo->prepare("DELETE FROM ekstrakurikuler WHERE id_ekskul = ?");
            $stmt->execute([$id]);
            if ($stmt->rowCount() === 0) {
                throw new InvalidArgumentException('Ekstrakurikuler tidak ditemukan atau sudah dihapus.');
            }
            $pesan = ['success', 'Ekstrakurikuler berhasil dihapus.'];
        } else {
            throw new InvalidArgumentException('Aksi tidak dikenal.');
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('CRUD ekstrakurikuler gagal: ' . $e->getMessage());
        if ($e->getCode() === '23000' && $action === 'delete_ekskul') {
            $pesan = ['error', 'Ekstrakurikuler tidak dapat dihapus karena masih memiliki anggota. Hapus atau pindahkan anggotanya terlebih dahulu.'];
        } elseif ($e->getCode() === '23000') {
            $pesan = ['error', 'Perubahan ekstrakurikuler tidak dapat dilakukan karena data duplikat atau relasi yang dipilih tidak valid.'];
        } else {
            $pesan = ['error', 'Perubahan ekstrakurikuler gagal. Periksa data dan relasinya, lalu coba lagi.'];
        }
    } catch (InvalidArgumentException|RuntimeException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $pesan = ['error', $e->getMessage()];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('CRUD ekstrakurikuler gagal: ' . $e->getMessage());
        $pesan = ['error', 'Terjadi kesalahan saat memproses ekstrakurikuler.'];
    } finally {
        if ($lockAcquired) {
            try {
                $pdo->query("SELECT RELEASE_LOCK('siakadu_ekskul_id')");
            } catch (PDOException $e) {
                error_log('Gagal melepas lock ID ekstrakurikuler: ' . $e->getMessage());
            }
        }
    }
}

$list_guru = $pdo->query("SELECT nip, nama FROM guru ORDER BY nama ASC")->fetchAll();
$list_ekskul = $pdo->query(
    "SELECT e.*, g.nama AS nama_guru,
            (SELECT COUNT(*) FROM keanggotaan_ekskul ke WHERE ke.ekstrakurikuler_id_ekskul = e.id_ekskul) AS jumlah_peserta
     FROM ekstrakurikuler e
     JOIN guru g ON e.guru_nip = g.nip
     ORDER BY e.nama_ekskul ASC"
)->fetchAll();

$edit_ekskul = null;
if (isset($_GET['edit'])) {
    $editId = ekskul_valid_id($_GET['edit']);
    if ($editId !== false) {
        $stmt = $pdo->prepare("SELECT * FROM ekstrakurikuler WHERE id_ekskul = ?");
        $stmt->execute([$editId]);
        $edit_ekskul = $stmt->fetch() ?: null;
    }
    if (!$edit_ekskul && !$pesan) {
        $pesan = ['error', 'Ekstrakurikuler yang dipilih untuk diubah tidak ditemukan.'];
    }
}

$page = 'ekskul';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Ekstrakurikuler - Admin SIAKAD</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include '../../components/sidebarAdmin.php'; ?>
    <main class="content">
        <?php include '../../components/navbarAdmin.php'; ?>
        <section class="admin-content">
            <header class="admin-page-heading">
                <h1>Kelola Ekstrakurikuler</h1>
                <a href="dashboard.php" class="back-link">&larr; Kembali ke Dashboard</a>
            </header>

            <?php if ($pesan): ?>
                <div class="admin-alert admin-alert-<?= ekskul_h($pesan[0]) ?>"><?= ekskul_h($pesan[1]) ?></div>
            <?php endif; ?>

            <div class="admin-panel admin-form-panel">
                <h3><?= $edit_ekskul ? 'Ubah Ekstrakurikuler' : 'Tambah Ekstrakurikuler' ?></h3>
                <form method="POST" action="" class="admin-form-grid">
                    <input type="hidden" name="csrf_token" value="<?= ekskul_h($csrfToken) ?>">
                    <input type="hidden" name="action" value="save_ekskul">
                    <?php if ($edit_ekskul): ?>
                        <input type="hidden" name="id_ekskul" value="<?= ekskul_h($edit_ekskul['id_ekskul']) ?>">
                    <?php endif; ?>
                    <label>
                        <span>Nama Ekstrakurikuler</span>
                        <input type="text" name="nama_ekskul" maxlength="100" required value="<?= ekskul_h($edit_ekskul['nama_ekskul'] ?? '') ?>">
                    </label>
                    <label>
                        <span>Hari Kegiatan</span>
                        <select name="hari_kegiatan" required>
                            <option value="">-- Pilih Hari --</option>
                            <?php foreach ($hariList as $hari): ?>
                                <option value="<?= ekskul_h($hari) ?>" <?= ($edit_ekskul['hari_kegiatan'] ?? '') === $hari ? 'selected' : '' ?>><?= ekskul_h($hari) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span>Tempat</span>
                        <input type="text" name="tempat" maxlength="100" required value="<?= ekskul_h($edit_ekskul['tempat'] ?? '') ?>">
                    </label>
                    <label>
                        <span>Guru Pembina</span>
                        <select name="guru_nip" required>
                            <option value="">-- Pilih Guru --</option>
                            <?php foreach ($list_guru as $guru): ?>
                                <option value="<?= ekskul_h($guru['nip']) ?>" <?= ($edit_ekskul['guru_nip'] ?? '') === $guru['nip'] ? 'selected' : '' ?>><?= ekskul_h($guru['nama']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <div class="admin-form-actions">
                        <button type="submit" class="admin-btn admin-btn-primary"><?= $edit_ekskul ? 'Simpan Perubahan' : 'Tambah Ekstrakurikuler' ?></button>
                        <?php if ($edit_ekskul): ?>
                            <a href="ekstrakurikuler.php" class="admin-btn admin-btn-secondary">Batal</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="admin-panel admin-table-panel">
                <h3>Daftar Ekstrakurikuler</h3>
                <div class="admin-table-tools">
                    <input type="search" data-table-search="admin-extracurricular-list" placeholder="Cari nama, hari, tempat, atau pembina..." aria-label="Cari ekstrakurikuler berdasarkan nama, hari, tempat, atau guru pembina">
                </div>
                <p class="admin-table-results" data-table-results="admin-extracurricular-list" aria-live="polite"></p>
                <p class="admin-table-no-results" data-table-empty="admin-extracurricular-list" hidden>Tidak ada ekstrakurikuler yang sesuai dengan pencarian.</p>
                <div class="admin-table-scroll">
                    <table class="admin-data-table admin-table-wide" id="admin-extracurricular-list">
                        <thead>
                            <tr><th>Nama</th><th>Hari</th><th>Tempat</th><th>Guru Pembina</th><th>Peserta</th><th>Aksi</th></tr>
                        </thead>
                        <tbody>
                            <?php if ($list_ekskul): foreach ($list_ekskul as $ekskul): ?>
                                <tr data-search="<?= ekskul_h(implode(' ', [$ekskul['nama_ekskul'], $ekskul['hari_kegiatan'], $ekskul['tempat'], $ekskul['nama_guru'], $ekskul['jumlah_peserta']])) ?>">
                                    <td class="admin-primary-cell"><?= ekskul_h($ekskul['nama_ekskul']) ?></td>
                                    <td><?= ekskul_h($ekskul['hari_kegiatan']) ?></td>
                                    <td><?= ekskul_h($ekskul['tempat']) ?></td>
                                    <td><?= ekskul_h($ekskul['nama_guru']) ?></td>
                                    <td><?= ekskul_h($ekskul['jumlah_peserta']) ?></td>
                                    <td>
                                        <div class="admin-actions">
                                            <a href="?edit=<?= ekskul_h($ekskul['id_ekskul']) ?>" class="admin-btn admin-btn-secondary">Ubah</a>
                                            <form method="POST" action="" onsubmit="return confirm('Hapus ekstrakurikuler ini? Penghapusan ditolak jika masih memiliki anggota.');">
                                                <input type="hidden" name="csrf_token" value="<?= ekskul_h($csrfToken) ?>">
                                                <input type="hidden" name="action" value="delete_ekskul">
                                                <input type="hidden" name="id_ekskul" value="<?= ekskul_h($ekskul['id_ekskul']) ?>">
                                                <button type="submit" class="admin-btn admin-btn-danger">Hapus</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr data-empty-state><td colspan="6" class="admin-empty-state">Belum ada ekstrakurikuler.</td></tr>
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
