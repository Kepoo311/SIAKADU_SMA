<?php
session_start();
require "../../DB/koneksi.php";

if (!isset($_SESSION['role_id']) || (int) $_SESSION['role_id'] !== 1) {
    http_response_code(403);
    header("Location: ../../Views/login.php");
    exit;
}

$pesan = '';
$tahun_diedit = null;
$csrfToken = $_SESSION['admin_crud_token'] ?? '';
if ($csrfToken === '') {
    $csrfToken = bin2hex(random_bytes(32));
    $_SESSION['admin_crud_token'] = $csrfToken;
}
$id_edit = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);
if ($id_edit) {
    $stmt_edit = $pdo->prepare("SELECT * FROM tahun_ajaran WHERE id_tahun = ?");
    $stmt_edit->execute([$id_edit]);
    $tahun_diedit = $stmt_edit->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($csrfToken, (string) $_POST['csrf_token'])) {
        $pesan = "<div class='admin-alert admin-alert-error'>Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.</div>";
    } elseif (isset($_POST['simpan_tahun'])) {
        $id_tahun = filter_var($_POST['id_tahun'] ?? null, FILTER_VALIDATE_INT);
        $tahun = trim(is_string($_POST['tahun'] ?? null) ? $_POST['tahun'] : '');
        $semester = $_POST['semester'] ?? '';
        $tanggal_mulai = $_POST['tanggal_mulai'] ?? '';
        $tanggal_selesai = $_POST['tanggal_selesai'] ?? '';
        $aktif = ($_POST['status_aktif'] ?? 'N') === 'Y' ? 'Y' : 'N';

        try {
            if ($id_tahun === false || $id_tahun < 0) {
                throw new InvalidArgumentException('ID tahun ajaran tidak valid.');
            }
            if (!preg_match('/^\d{4}\/\d{4}$/', $tahun) ||
                (int) substr($tahun, 5, 4) !== (int) substr($tahun, 0, 4) + 1 ||
                !in_array($semester, ['Ganjil', 'Genap'], true)) {
                throw new InvalidArgumentException('Tahun ajaran atau semester tidak valid.');
            }
            $mulai = DateTime::createFromFormat('!Y-m-d', is_string($tanggal_mulai) ? $tanggal_mulai : '');
            $selesai = DateTime::createFromFormat('!Y-m-d', is_string($tanggal_selesai) ? $tanggal_selesai : '');
            if (!$mulai || !$selesai || $mulai->format('Y-m-d') !== $tanggal_mulai ||
                $selesai->format('Y-m-d') !== $tanggal_selesai || $selesai < $mulai) {
                throw new InvalidArgumentException('Rentang tanggal tahun ajaran tidak valid.');
            }

            $pdo->beginTransaction();
            if ($id_tahun > 0) {
                $stmt_exists = $pdo->prepare("SELECT 1 FROM tahun_ajaran WHERE id_tahun = ?");
                $stmt_exists->execute([$id_tahun]);
                if (!$stmt_exists->fetchColumn()) {
                    throw new InvalidArgumentException('Tahun ajaran yang akan diubah tidak ditemukan.');
                }
            }
            if ($id_tahun && $aktif === 'N') {
                $stmt_aktif = $pdo->prepare("SELECT status_aktif FROM tahun_ajaran WHERE id_tahun = ?");
                $stmt_aktif->execute([$id_tahun]);
                if ($stmt_aktif->fetchColumn() === 'Y') {
                    $stmt_aktif_lain = $pdo->prepare("SELECT COUNT(*) FROM tahun_ajaran WHERE status_aktif = 'Y' AND id_tahun <> ?");
                    $stmt_aktif_lain->execute([$id_tahun]);
                    if ((int) $stmt_aktif_lain->fetchColumn() === 0) {
                        throw new InvalidArgumentException('Aktifkan tahun ajaran lain terlebih dahulu.');
                    }
                }
            }

            if ($id_tahun) {
                $stmt = $pdo->prepare("UPDATE tahun_ajaran
                                       SET tahun = ?, semester = ?, tanggal_mulai = ?, tanggal_selesai = ?, status_aktif = ?
                                       WHERE id_tahun = ?");
                $stmt->execute([$tahun, $semester, $tanggal_mulai, $tanggal_selesai, $aktif, $id_tahun]);
            } else {
                $lock = (int) $pdo->query("SELECT GET_LOCK('siakadu_tahun_id', 5)")->fetchColumn();
                if ($lock !== 1) {
                    throw new RuntimeException('Tidak dapat membuat tahun ajaran saat ini. Silakan coba lagi.');
                }
                $new_id = (int) $pdo->query("SELECT COALESCE(MAX(id_tahun), 0) + 1 FROM tahun_ajaran")->fetchColumn();
                $stmt = $pdo->prepare("INSERT INTO tahun_ajaran
                                       (id_tahun, tahun, semester, tanggal_mulai, tanggal_selesai, status_aktif)
                                       VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$new_id, $tahun, $semester, $tanggal_mulai, $tanggal_selesai, $aktif]);
            }

            if ($aktif === 'Y') {
                $pdo->prepare("UPDATE tahun_ajaran SET status_aktif = 'N' WHERE id_tahun <> ?")
                    ->execute([$id_tahun ?: $new_id]);
            }
            $pdo->commit();
            $pesan = "<div class='admin-alert admin-alert-success'>Tahun ajaran berhasil disimpan.</div>";
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Gagal menyimpan tahun ajaran: ' . $e->getMessage());
            $pesan = "<div class='admin-alert admin-alert-error'>Gagal menyimpan tahun ajaran. Periksa data yang terkait lalu coba lagi.</div>";
        } catch (InvalidArgumentException|RuntimeException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $pesan = "<div class='admin-alert admin-alert-error'>" . htmlspecialchars($e->getMessage()) . "</div>";
        } finally {
            if (isset($lock) && $lock === 1) {
                try {
                    $pdo->query("SELECT RELEASE_LOCK('siakadu_tahun_id')");
                } catch (PDOException $e) {
                    error_log('Gagal melepas lock tahun ajaran: ' . $e->getMessage());
                }
            }
        }
    } elseif (isset($_POST['hapus_tahun'])) {
        $id_tahun = filter_var($_POST['id_tahun'] ?? null, FILTER_VALIDATE_INT);
        try {
            if (!$id_tahun) {
                throw new InvalidArgumentException('Tahun ajaran tidak valid.');
            }
            $stmt = $pdo->prepare("DELETE FROM tahun_ajaran WHERE id_tahun = ? AND status_aktif <> 'Y'");
            $stmt->execute([$id_tahun]);
            $pesan = $stmt->rowCount()
                ? "<div class='admin-alert admin-alert-success'>Tahun ajaran berhasil dihapus.</div>"
                : "<div class='admin-alert admin-alert-error'>Tahun aktif tidak dapat dihapus.</div>";
        } catch (PDOException $e) {
            error_log('Gagal menghapus tahun ajaran: ' . $e->getMessage());
            $pesan = "<div class='admin-alert admin-alert-error'>Tahun ajaran masih dipakai oleh data akademik dan tidak dapat dihapus.</div>";
        } catch (InvalidArgumentException $e) {
            $pesan = "<div class='admin-alert admin-alert-error'>" . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}

$list_tahun = $pdo->query("SELECT * FROM tahun_ajaran ORDER BY id_tahun DESC")->fetchAll();
$page = 'tahun';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Tahun Ajaran - Admin SIAKAD</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include '../../components/sidebarAdmin.php'; ?>
    <main class="content">
        <?php include '../../components/navbarAdmin.php'; ?>
        <section class="admin-content">
            <header class="admin-page-heading">
                <h1>Kelola Tahun Ajaran</h1>
                <a href="dashboard.php" class="back-link">&larr; Kembali ke Dashboard</a>
            </header>
            <?= $pesan ?>

            <div class="admin-panel admin-form-panel">
                <h3><?= $tahun_diedit ? 'Ubah Tahun Ajaran' : 'Tambah Tahun Ajaran' ?></h3>
                <form method="POST" action="" class="admin-form-grid admin-academic-year-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="id_tahun" value="<?= (int) ($tahun_diedit['id_tahun'] ?? 0) ?>">
                    <label>
                        <span>Tahun Ajaran</span>
                        <input type="text" name="tahun" placeholder="2026/2027" pattern="\d{4}/\d{4}" value="<?= htmlspecialchars($tahun_diedit['tahun'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                    </label>
                    <label>
                        <span>Semester</span>
                        <select name="semester" required>
                        <option value="">-- Semester --</option>
                        <?php foreach (['Ganjil', 'Genap'] as $semester): ?>
                            <option value="<?= $semester ?>" <?= ($tahun_diedit['semester'] ?? '') === $semester ? 'selected' : '' ?>><?= $semester ?></option>
                        <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span>Tanggal Mulai</span>
                        <input type="date" name="tanggal_mulai" value="<?= htmlspecialchars($tahun_diedit['tanggal_mulai'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                    </label>
                    <label>
                        <span>Tanggal Selesai</span>
                        <input type="date" name="tanggal_selesai" value="<?= htmlspecialchars($tahun_diedit['tanggal_selesai'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                    </label>
                    <label>
                        <span>Status Tahun Ajaran</span>
                        <select name="status_aktif">
                        <option value="N" <?= ($tahun_diedit['status_aktif'] ?? 'N') === 'N' ? 'selected' : '' ?>>Tidak aktif</option>
                        <option value="Y" <?= ($tahun_diedit['status_aktif'] ?? '') === 'Y' ? 'selected' : '' ?>>Aktif</option>
                        </select>
                    </label>
                    <div class="admin-form-actions">
                        <button type="submit" name="simpan_tahun" class="admin-btn admin-btn-primary">Simpan</button>
                        <?php if ($tahun_diedit): ?><a class="admin-btn admin-btn-secondary" href="tahun_ajaran.php">Batal</a><?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="admin-panel admin-table-panel">
                <h3>Daftar Tahun Ajaran</h3>
                <div class="admin-table-scroll">
                    <table class="admin-data-table admin-table-wide">
                        <thead><tr><th>Tahun</th><th>Semester</th><th>Periode</th><th>Status</th><th>Aksi</th></tr></thead>
                        <tbody>
                        <?php foreach ($list_tahun as $tahun): ?>
                            <tr>
                                <td><?= htmlspecialchars($tahun['tahun']) ?></td>
                                <td><?= htmlspecialchars($tahun['semester']) ?></td>
                                <td><?= htmlspecialchars($tahun['tanggal_mulai']) ?> - <?= htmlspecialchars($tahun['tanggal_selesai']) ?></td>
                                <td><?= $tahun['status_aktif'] === 'Y' ? 'Aktif' : 'Tidak aktif' ?></td>
                                <td>
                                    <div class="admin-actions">
                                    <a class="admin-btn admin-btn-secondary" href="?edit=<?= (int) $tahun['id_tahun'] ?>">Ubah</a>
                                    <?php if ($tahun['status_aktif'] !== 'Y'): ?>
                                    <form method="POST" action="" onsubmit="return confirm('Hapus tahun ajaran ini?')">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="id_tahun" value="<?= (int) $tahun['id_tahun'] ?>">
                                        <button type="submit" name="hapus_tahun" class="admin-btn admin-btn-danger">Hapus</button>
                                    </form>
                                    <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
