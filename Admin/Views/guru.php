<?php
session_start();
require "../../DB/koneksi.php";

if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../index.php");
    exit;
}

$pesan = "";
$editGuru = null;
$csrfToken = $_SESSION['admin_crud_token'] ?? '';
if ($csrfToken === '') {
    $csrfToken = bin2hex(random_bytes(32));
    $_SESSION['admin_crud_token'] = $csrfToken;
}
$h = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM guru WHERE nip = ?");
    $stmt->execute([trim((string) $_GET['edit'])]);
    $editGuru = $stmt->fetch() ?: null;
    if (!$editGuru) {
        $pesan = "<div class='admin-alert admin-alert-error'>Data guru yang akan diedit tidak ditemukan.</div>";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!in_array($action, ['tambah_guru', 'ubah_guru', 'hapus_guru'], true)) {
        $action = '';
    }
    if ($action !== '' && !hash_equals($csrfToken, (string) ($_POST['csrf_token'] ?? ''))) {
        $pesan = "<div class='admin-alert admin-alert-error'>Permintaan tidak valid. Muat ulang halaman dan coba lagi.</div>";
    } elseif ($action === 'hapus_guru') {
        $nip = trim((string) ($_POST['nip'] ?? ''));
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT nip FROM guru WHERE nip = ? FOR UPDATE");
            $stmt->execute([$nip]);
            if (!$stmt->fetch()) {
                throw new RuntimeException('not_found');
            }

            $dependencies = [
                'kelas' => 'kelas',
                'jadwal' => 'jadwal',
                'ekstrakurikuler' => 'ekstrakurikuler',
            ];
            $found = [];
            foreach ($dependencies as $label => $table) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM `$table` WHERE guru_nip = ?");
                $stmt->execute([$nip]);
                if ((int) $stmt->fetchColumn() > 0) {
                    $found[] = $label;
                }
            }
            if ($found) {
                throw new RuntimeException('dependencies:' . implode(', ', $found));
            }

            $stmt = $pdo->prepare("DELETE FROM guru WHERE nip = ?");
            $stmt->execute([$nip]);
            $pdo->commit();
            $pesan = "<div class='admin-alert admin-alert-success'>Data guru berhasil dihapus. Akun login tidak dihapus.</div>";
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e instanceof RuntimeException && str_starts_with($e->getMessage(), 'dependencies:')) {
                $pesan = "<div class='admin-alert admin-alert-error'>Guru tidak dapat dihapus karena masih digunakan oleh " . $h(substr($e->getMessage(), 13)) . ". Hapus atau alihkan relasi tersebut terlebih dahulu; tidak ada data terkait yang dihapus.</div>";
            } elseif ($e instanceof RuntimeException && $e->getMessage() === 'not_found') {
                $pesan = "<div class='admin-alert admin-alert-error'>Data guru tidak ditemukan.</div>";
            } else {
                $pesan = "<div class='admin-alert admin-alert-error'>Guru tidak dapat dihapus karena masih memiliki relasi data. Tidak ada data terkait yang dihapus.</div>";
            }
        }
    } elseif ($action === 'tambah_guru' || $action === 'ubah_guru') {
        $nip = trim((string) ($_POST['nip'] ?? ''));
        $nama = trim((string) ($_POST['nama'] ?? ''));
        $jenis_kelamin = (string) ($_POST['jenis_kelamin'] ?? '');
        $status_kepegawaian = (string) ($_POST['status_kepegawaian'] ?? '');
        $no_hp = trim((string) ($_POST['no_hp'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $alamat = trim((string) ($_POST['alamat'] ?? ''));

        if ($nip === '' || $nama === '' || !in_array($jenis_kelamin, ['L', 'P'], true) ||
            !in_array($status_kepegawaian, ['Tetap', 'Honorer'], true) || $no_hp === '' ||
            $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            strlen($nip) > 30 || strlen($nama) > 100 || strlen($no_hp) > 20 ||
            strlen($email) > 100 || strlen($alamat) > 255) {
            $pesan = "<div class='admin-alert admin-alert-error'>Lengkapi data dengan benar. Periksa format email dan batas panjang tiap kolom.</div>";
        } else {
            try {
                if ($action === 'ubah_guru') {
                    $nipLama = trim((string) ($_POST['nip_lama'] ?? ''));
                    $stmt = $pdo->prepare("UPDATE guru SET nama = ?, jenis_kelamin = ?, status_kepegawaian = ?, no_hp = ?, email = ?, alamat = ? WHERE nip = ?");
                    $stmt->execute([$nama, $jenis_kelamin, $status_kepegawaian, $no_hp, $email, $alamat !== '' ? $alamat : null, $nipLama]);
                    if ($stmt->rowCount() === 0) {
                        $exists = $pdo->prepare("SELECT 1 FROM guru WHERE nip = ?");
                        $exists->execute([$nipLama]);
                        if (!$exists->fetchColumn()) {
                            throw new RuntimeException('not_found');
                        }
                    }
                    $pesan = "<div class='admin-alert admin-alert-success'>Data guru berhasil diperbarui.</div>";
                    $editGuru = null;
                } else {
                    $username_guru = "guru_" . strtolower(explode(' ', $nama)[0]);
                    $password_default = password_hash("guru123", PASSWORD_DEFAULT);
                    $role_guru_id = 2;
                    $pdo->beginTransaction();
                    $stmt_max_user = $pdo->query("SELECT MAX(id_user) AS max_id FROM user");
                    $new_id_user = ($stmt_max_user->fetch()['max_id'] ?? 0) + 1;
                    $stmt_u_guru = $pdo->prepare("INSERT INTO user (id_user, username, password, email, role_id_role) VALUES (?, ?, ?, ?, ?)");
                    $stmt_u_guru->execute([$new_id_user, $username_guru, $password_default, $email, $role_guru_id]);
                    $stmt_guru = $pdo->prepare("INSERT INTO guru (nip, nama, jenis_kelamin, status_kepegawaian, no_hp, email, alamat, user_id_user) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt_guru->execute([$nip, $nama, $jenis_kelamin, $status_kepegawaian, $no_hp, $email, $alamat !== '' ? $alamat : null, $new_id_user]);
                    $pdo->commit();
                    $pesan = "<div class='admin-alert admin-alert-success'>Data guru dan akun login berhasil ditambahkan. Username: <b>" . $h($username_guru) . "</b>.</div>";
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $pesan = $e instanceof RuntimeException && $e->getMessage() === 'not_found'
                    ? "<div class='admin-alert admin-alert-error'>Data guru tidak ditemukan.</div>"
                    : "<div class='admin-alert admin-alert-error'>Data guru tidak dapat disimpan. Pastikan NIP dan email belum digunakan dan data yang dimasukkan valid.</div>";
            }
        }
    }
}

// Ambil daftar seluruh guru beserta informasi akunnya
$stmt = $pdo->query("SELECT g.*, u.username FROM guru g LEFT JOIN user u ON g.user_id_user = u.id_user ORDER BY g.nama ASC");
$list_guru = $stmt->fetchAll();
$page = 'guru';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Data Guru - Admin SIAKAD</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include '../../components/sidebarAdmin.php'; ?>
    <main class="content">
        <?php include '../../components/navbarAdmin.php'; ?>
        <section class="admin-content">
        
        <header class="admin-page-heading">
            <h1>Kelola Data Guru</h1>
            <a href="dashboard.php" class="back-link">&larr; Kembali ke Dashboard</a>
        </header>

        <?= $pesan; ?>

        <!-- Form Tambah / Edit Guru -->
        <div class="admin-panel admin-form-panel">
            <h3><?= $editGuru ? 'Edit Data Guru' : 'Tambah Data Guru & Akun' ?></h3>
            <form method="POST" action="" class="admin-form-grid">
                <input type="hidden" name="csrf_token" value="<?= $h($csrfToken) ?>">
                <?php if ($editGuru): ?>
                    <input type="hidden" name="action" value="ubah_guru">
                    <input type="hidden" name="nip_lama" value="<?= $h($editGuru['nip']) ?>">
                <?php else: ?>
                    <input type="hidden" name="action" value="tambah_guru">
                <?php endif; ?>
                <input type="text" name="nip" placeholder="Nomor Induk Pegawai (NIP)" value="<?= $h($editGuru['nip'] ?? '') ?>" <?= $editGuru ? 'readonly' : '' ?> required maxlength="30">
                <input type="text" name="nama" placeholder="Nama Lengkap & Gelar" value="<?= $h($editGuru['nama'] ?? '') ?>" required maxlength="100">
                
                <select name="jenis_kelamin" required>
                    <option value="">-- Pilih Jenis Kelamin --</option>
                    <option value="L" <?= ($editGuru['jenis_kelamin'] ?? '') === 'L' ? 'selected' : '' ?>>Laki-laki (L)</option>
                    <option value="P" <?= ($editGuru['jenis_kelamin'] ?? '') === 'P' ? 'selected' : '' ?>>Perempuan (P)</option>
                </select>

                <select name="status_kepegawaian" required>
                    <option value="">-- Status Kepegawaian --</option>
                    <option value="Tetap" <?= ($editGuru['status_kepegawaian'] ?? '') === 'Tetap' ? 'selected' : '' ?>>Tetap</option>
                    <option value="Honorer" <?= ($editGuru['status_kepegawaian'] ?? '') === 'Honorer' ? 'selected' : '' ?>>Honorer</option>
                </select>

                <input type="text" name="no_hp" placeholder="No HP" value="<?= $h($editGuru['no_hp'] ?? '') ?>" required maxlength="20">
                <input type="email" name="email" placeholder="Email Guru" value="<?= $h($editGuru['email'] ?? '') ?>" required maxlength="100">
                <input type="text" name="alamat" placeholder="Alamat (opsional)" value="<?= $h($editGuru['alamat'] ?? '') ?>" maxlength="255" class="admin-form-wide">

                <div class="admin-form-actions">
                    <button type="submit" class="admin-btn admin-btn-primary"><?= $editGuru ? 'Simpan Perubahan' : 'Simpan Guru & Buat Akun' ?></button>
                    <?php if ($editGuru): ?><a href="guru.php" class="admin-btn admin-btn-secondary">Batal</a><?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Tabel Daftar Guru -->
        <div class="admin-panel admin-table-panel">
            <h3>Daftar Guru Terdaftar</h3>
            <div class="admin-table-tools">
                <input type="search" data-table-search="admin-teacher-list" placeholder="Cari nama, NIP, status, email..." aria-label="Cari guru berdasarkan nama, NIP, status, atau email">
            </div>
            <p class="admin-table-results" data-table-results="admin-teacher-list" aria-live="polite"></p>
            <p class="admin-table-no-results" data-table-empty="admin-teacher-list" hidden>Tidak ada guru yang sesuai dengan pencarian.</p>
            <div class="admin-table-scroll">
            <table class="admin-data-table admin-table-xl" id="admin-teacher-list">
                <thead>
                    <tr>
                        <th>NIP</th><th>Nama Lengkap</th><th>Status</th><th>No HP</th><th>Email</th><th>Username Login</th><th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($list_guru) > 0): foreach ($list_guru as $g): ?>
                    <tr data-search="<?= $h(implode(' ', [$g['nip'], $g['nama'], $g['status_kepegawaian'], $g['no_hp'], $g['email'], $g['username'] ?? ''])) ?>">
                        <td class="admin-primary-cell"><?= htmlspecialchars($g['nip']) ?></td>
                        <td class="admin-primary-cell"><?= htmlspecialchars($g['nama']) ?></td>
                        <td><?= htmlspecialchars($g['status_kepegawaian']) ?></td>
                        <td><?= htmlspecialchars($g['no_hp']) ?></td>
                        <td><?= htmlspecialchars($g['email']) ?></td>
                        <td><?= htmlspecialchars($g['username'] ?? '-') ?></td>
                        <td class="admin-action-cell">
                            <a href="guru.php?edit=<?= rawurlencode($g['nip']) ?>" class="admin-btn admin-btn-secondary">Ubah</a>
                            <form method="POST" action="" class="admin-inline-action" onsubmit="return confirm('Hapus data guru ini? Akun login akan tetap disimpan.');">
                                <input type="hidden" name="csrf_token" value="<?= $h($csrfToken) ?>">
                                <input type="hidden" name="action" value="hapus_guru">
                                <input type="hidden" name="nip" value="<?= $h($g['nip']) ?>">
                                <button type="submit" class="admin-btn admin-btn-danger">Hapus</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr data-empty-state>
                        <td colspan="7" class="admin-empty-state">Belum ada data guru.</td>
                    </tr>
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