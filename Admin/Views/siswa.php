<?php
session_start();
require "../../DB/koneksi.php";

if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../index.php");
    exit;
}

$pesan = "";
$editSiswa = null;
$csrfToken = $_SESSION['admin_crud_token'] ?? '';
if ($csrfToken === '') {
    $csrfToken = bin2hex(random_bytes(32));
    $_SESSION['admin_crud_token'] = $csrfToken;
}
$h = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM siswa WHERE nis = ?");
    $stmt->execute([trim((string) $_GET['edit'])]);
    $editSiswa = $stmt->fetch() ?: null;
    if (!$editSiswa) {
        $pesan = "<div class='admin-alert admin-alert-error'>Data siswa yang akan diedit tidak ditemukan.</div>";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!in_array($action, ['tambah_siswa', 'ubah_siswa', 'hapus_siswa'], true)) {
        $action = '';
    }
    if ($action !== '' && !hash_equals($csrfToken, (string) ($_POST['csrf_token'] ?? ''))) {
        $pesan = "<div class='admin-alert admin-alert-error'>Permintaan tidak valid. Muat ulang halaman dan coba lagi.</div>";
    } elseif ($action === 'hapus_siswa') {
        $nis = trim((string) ($_POST['nis'] ?? ''));
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT nis FROM siswa WHERE nis = ? FOR UPDATE");
            $stmt->execute([$nis]);
            if (!$stmt->fetch()) {
                throw new RuntimeException('not_found');
            }

            $dependencies = [
                'nilai (nilai)' => 'nilai',
                'rapor' => 'rapor',
                'absensi' => 'absensi',
                'kelas (anggota_kelas)' => 'anggota_kelas',
                'ekstrakurikuler (keanggotaan_ekskul)' => 'keanggotaan_ekskul',
            ];
            $found = [];
            foreach ($dependencies as $label => $table) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM `$table` WHERE siswa_nis = ?");
                $stmt->execute([$nis]);
                if ((int) $stmt->fetchColumn() > 0) {
                    $found[] = $label;
                }
            }
            if ($found) {
                throw new RuntimeException('dependencies:' . implode(', ', $found));
            }

            $stmt = $pdo->prepare("DELETE FROM siswa WHERE nis = ?");
            $stmt->execute([$nis]);
            $pdo->commit();
            $pesan = "<div class='admin-alert admin-alert-success'>Data siswa berhasil dihapus. Data orang tua dan akun login dipertahankan.</div>";
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e instanceof RuntimeException && str_starts_with($e->getMessage(), 'dependencies:')) {
                $pesan = "<div class='admin-alert admin-alert-error'>Siswa tidak dapat dihapus karena masih memiliki data " . $h(substr($e->getMessage(), 13)) . ". Hapus atau alihkan relasi tersebut terlebih dahulu; tidak ada data terkait yang dihapus.</div>";
            } elseif ($e instanceof RuntimeException && $e->getMessage() === 'not_found') {
                $pesan = "<div class='admin-alert admin-alert-error'>Data siswa tidak ditemukan.</div>";
            } else {
                $pesan = "<div class='admin-alert admin-alert-error'>Siswa tidak dapat dihapus karena masih memiliki relasi data. Tidak ada data terkait yang dihapus.</div>";
            }
        }
    } elseif ($action === 'tambah_siswa' || $action === 'ubah_siswa') {
        $nis = trim((string) ($_POST['nis'] ?? ''));
        $nisn = trim((string) ($_POST['nisn'] ?? ''));
        $nama = trim((string) ($_POST['nama'] ?? ''));
        $jenis_kelamin = (string) ($_POST['jenis_kelamin'] ?? '');
        $tanggal_lahir = trim((string) ($_POST['tanggal_lahir'] ?? ''));
        $tempat_lahir = trim((string) ($_POST['tempat_lahir'] ?? ''));
        $alamat = trim((string) ($_POST['alamat'] ?? ''));
        $no_hp = trim((string) ($_POST['no_hp'] ?? ''));
        $tanggalValid = DateTime::createFromFormat('!Y-m-d', $tanggal_lahir);
        $tanggalValid = $tanggalValid && $tanggalValid->format('Y-m-d') === $tanggal_lahir;

        if ($nis === '' || $nisn === '' || $nama === '' || !in_array($jenis_kelamin, ['L', 'P'], true) ||
            !$tanggalValid || $tempat_lahir === '' || $alamat === '' ||
            ($action === 'tambah_siswa' && $no_hp === '') ||
            strlen($nis) > 20 || strlen($nisn) > 20 || strlen($nama) > 100 ||
            strlen($tempat_lahir) > 100 || strlen($alamat) > 255 || strlen($no_hp) > 20) {
            $pesan = "<div class='admin-alert admin-alert-error'>Lengkapi data wajib dengan benar dan periksa batas panjang tiap kolom.</div>";
        } else {
            try {
                if ($action === 'ubah_siswa') {
                    $nisLama = trim((string) ($_POST['nis_lama'] ?? ''));
                    $stmt = $pdo->prepare("UPDATE siswa SET nisn = ?, nama = ?, jenis_kelamin = ?, tanggal_lahir = ?, tempat_lahir = ?, alamat = ?, no_hp = ? WHERE nis = ?");
                    $stmt->execute([$nisn, $nama, $jenis_kelamin, $tanggal_lahir, $tempat_lahir, $alamat, $no_hp !== '' ? $no_hp : null, $nisLama]);
                    if ($stmt->rowCount() === 0) {
                        $exists = $pdo->prepare("SELECT 1 FROM siswa WHERE nis = ?");
                        $exists->execute([$nisLama]);
                        if (!$exists->fetchColumn()) {
                            throw new RuntimeException('not_found');
                        }
                    }
                    $pesan = "<div class='admin-alert admin-alert-success'>Data siswa berhasil diperbarui.</div>";
                    $editSiswa = null;
                } else {
                    $namaDepan = explode(' ', $nama)[0];
                    $nama_ayah_default = "Bapak " . $namaDepan;
                    $nama_ibu_default = "Ibu " . $namaDepan;
                    $username_siswa = "siswa_" . strtolower($namaDepan);
                    $email_siswa = $nis . "@mail.com";
                    $password_siswa = password_hash("siswa123", PASSWORD_DEFAULT);
                    $role_siswa_id = 3;

                    $pdo->beginTransaction();
                    $stmt_max_user = $pdo->query("SELECT MAX(id_user) AS max_id FROM user");
                    $new_id_user = ($stmt_max_user->fetch()['max_id'] ?? 0) + 1;
                    $stmt_u_siswa = $pdo->prepare("INSERT INTO user (id_user, username, password, email, role_id_role) VALUES (?, ?, ?, ?, ?)");
                    $stmt_u_siswa->execute([$new_id_user, $username_siswa, $password_siswa, $email_siswa, $role_siswa_id]);
                    $stmt_max_ortu = $pdo->query("SELECT MAX(id) AS max_id FROM orang_tua");
                    $new_id_ortu = ($stmt_max_ortu->fetch()['max_id'] ?? 0) + 1;
                    $stmt_ortu = $pdo->prepare("INSERT INTO orang_tua (id, nama_ayah, nama_ibu, pekerjaan, alamat, no_hp, user_id_user) VALUES (?, ?, ?, 'Belum diisi', ?, ?, ?)");
                    $stmt_ortu->execute([$new_id_ortu, $nama_ayah_default, $nama_ibu_default, $alamat, $no_hp, $new_id_user]);
                    $stmt_siswa = $pdo->prepare("INSERT INTO siswa (nis, nisn, nama, jenis_kelamin, tanggal_lahir, tempat_lahir, alamat, no_hp, orang_tua_id, tahun_masuk, status_siswa, user_id_user) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Aktif', ?)");
                    $stmt_siswa->execute([$nis, $nisn, $nama, $jenis_kelamin, $tanggal_lahir, $tempat_lahir, $alamat, $no_hp !== '' ? $no_hp : null, $new_id_ortu, date('Y'), $new_id_user]);
                    $pdo->commit();
                    $pesan = "<div class='admin-alert admin-alert-success'>Data siswa, data orang tua, dan akun login berhasil ditambahkan. Username: <b>" . $h($username_siswa) . "</b>.</div>";
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $pesan = $e instanceof RuntimeException && $e->getMessage() === 'not_found'
                    ? "<div class='admin-alert admin-alert-error'>Data siswa tidak ditemukan.</div>"
                    : "<div class='admin-alert admin-alert-error'>Data siswa tidak dapat disimpan. Pastikan NIS/NISN belum digunakan dan data yang dimasukkan valid.</div>";
            }
        }
    }
}

// Ambil daftar siswa, kelas, dan status rapor pada tahun ajaran aktif.
$stmt = $pdo->query(
    "SELECT s.*, u.username, o.nama_ayah, o.nama_ibu,
            (SELECT k.nama_kelas
             FROM anggota_kelas ak
             JOIN kelas k ON ak.kelas_id_kelas = k.id_kelas
             WHERE ak.siswa_nis = s.nis
               AND k.tahun_ajaran_id_tahun = ta.id_tahun
             LIMIT 1) AS nama_kelas,
            r.status_kenaikan,
            ta.tahun AS tahun_ajaran,
            ta.semester
     FROM siswa s
     LEFT JOIN user u ON s.user_id_user = u.id_user
     LEFT JOIN orang_tua o ON s.user_id_user = o.user_id_user
     LEFT JOIN (SELECT id_tahun, tahun, semester FROM tahun_ajaran WHERE status_aktif = 'Y' LIMIT 1) ta ON 1 = 1
     LEFT JOIN rapor r ON r.siswa_nis = s.nis AND r.tahun_ajaran_id_tahun = ta.id_tahun
     ORDER BY s.nama ASC"
);
$list_siswa = $stmt->fetchAll();
$page = 'siswa';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Data Siswa - Admin SIAKAD</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include '../../components/sidebarAdmin.php'; ?>
    <main class="content">
        <?php include '../../components/navbarAdmin.php'; ?>
        <section class="admin-content">
        
        <header class="admin-page-heading">
            <h1>Kelola Data Siswa</h1>
            <a href="dashboard.php" class="back-link">&larr; Kembali ke Dashboard</a>
        </header>

        <?= $pesan; ?>

        <!-- Form Tambah / Edit Siswa -->
        <div class="admin-panel admin-form-panel">
            <h3><?= $editSiswa ? 'Edit Data Siswa' : 'Tambah Data Siswa' ?></h3>
            <form method="POST" action="" class="admin-form-grid">
                <input type="hidden" name="csrf_token" value="<?= $h($csrfToken) ?>">
                <?php if ($editSiswa): ?>
                    <input type="hidden" name="action" value="ubah_siswa">
                    <input type="hidden" name="nis_lama" value="<?= $h($editSiswa['nis']) ?>">
                <?php else: ?>
                    <input type="hidden" name="action" value="tambah_siswa">
                <?php endif; ?>
                <input type="text" name="nis" placeholder="Nomor Induk Siswa (NIS)" value="<?= $h($editSiswa['nis'] ?? '') ?>" <?= $editSiswa ? 'readonly' : '' ?> required maxlength="20">
                <input type="text" name="nisn" placeholder="NISN" value="<?= $h($editSiswa['nisn'] ?? '') ?>" required maxlength="20">
                <input type="text" name="nama" placeholder="Nama Lengkap Siswa" value="<?= $h($editSiswa['nama'] ?? '') ?>" required maxlength="100">
                
                <select name="jenis_kelamin" required>
                    <option value="">-- Pilih Jenis Kelamin --</option>
                    <option value="L" <?= ($editSiswa['jenis_kelamin'] ?? '') === 'L' ? 'selected' : '' ?>>Laki-laki (L)</option>
                    <option value="P" <?= ($editSiswa['jenis_kelamin'] ?? '') === 'P' ? 'selected' : '' ?>>Perempuan (P)</option>
                </select>

                <input type="date" name="tanggal_lahir" value="<?= $h($editSiswa['tanggal_lahir'] ?? '') ?>" required>
                <input type="text" name="tempat_lahir" placeholder="Tempat Lahir" value="<?= $h($editSiswa['tempat_lahir'] ?? '') ?>" required maxlength="100">
                <input type="text" name="alamat" placeholder="Alamat" value="<?= $h($editSiswa['alamat'] ?? '') ?>" required maxlength="255">
                <input type="text" name="no_hp" placeholder="No HP" value="<?= $h($editSiswa['no_hp'] ?? '') ?>" <?= $editSiswa ? '' : 'required' ?> maxlength="20">

                <div class="admin-form-actions">
                    <button type="submit" class="admin-btn admin-btn-primary"><?= $editSiswa ? 'Simpan Perubahan' : 'Simpan Data Siswa' ?></button>
                    <?php if ($editSiswa): ?><a href="siswa.php" class="admin-btn admin-btn-secondary">Batal</a><?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Tabel Daftar Siswa -->
        <div class="admin-panel admin-table-panel">
            <h3>Daftar Siswa Terdaftar<?= !empty($list_siswa[0]['tahun_ajaran']) ? ' — ' . $h($list_siswa[0]['tahun_ajaran'] . ' ' . $list_siswa[0]['semester']) : '' ?></h3>
            <div class="admin-table-tools">
                <input type="search" data-table-search="admin-student-list" placeholder="Cari nama, NIS, kelas, atau status..." aria-label="Cari siswa berdasarkan nama, NIS, kelas, atau status">
                <select data-table-filter="admin-student-list" aria-label="Filter status kenaikan kelas">
                    <option value="">Semua status kenaikan</option>
                    <option value="promoted">Naik Kelas</option>
                    <option value="retained">Tinggal Kelas</option>
                    <option value="pending">Belum ditentukan</option>
                    <option value="other">Status lainnya</option>
                </select>
            </div>
            <p class="admin-table-results" data-table-results="admin-student-list" aria-live="polite"></p>
            <p class="admin-table-no-results" data-table-empty="admin-student-list" hidden>Tidak ada siswa yang sesuai dengan pencarian.</p>
            <div class="admin-table-scroll">
            <table class="admin-data-table admin-table-xl" id="admin-student-list">
                <thead>
                    <tr>
                        <th>NIS</th><th>Nama Siswa</th><th>Kelas</th><th>Status Kenaikan</th><th>Data Orang Tua</th><th>Username Login Siswa</th><th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($list_siswa) > 0): foreach ($list_siswa as $s):
                        $statusKenaikan = trim((string) ($s['status_kenaikan'] ?? ''));
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
                            $s['nis'], $s['nama'], $s['nama_kelas'] ?? '', $statusLabel,
                            $s['nama_ayah'] ?? '', $s['nama_ibu'] ?? '', $s['username'] ?? '',
                        ]);
                    ?>
                    <tr data-status-filter="<?= $h($statusFilter) ?>" data-search="<?= $h($searchText) ?>">
                        <td class="admin-primary-cell"><?= htmlspecialchars($s['nis']) ?></td>
                        <td class="admin-primary-cell"><?= htmlspecialchars($s['nama']) ?></td>
                        <td><?= $h($s['nama_kelas'] ?? 'Belum ditempatkan') ?></td>
                        <td><span class="promotion-status promotion-status-<?= $h($statusFilter) ?>"><?= $h($statusLabel) ?></span></td>
                        <td>Ayah: <?= htmlspecialchars($s['nama_ayah'] ?? '-') ?><br>Ibu: <?= htmlspecialchars($s['nama_ibu'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($s['username'] ?? '-') ?></td>
                        <td class="admin-action-cell">
                            <a href="siswa.php?edit=<?= rawurlencode($s['nis']) ?>" class="admin-btn admin-btn-secondary">Ubah</a>
                            <form method="POST" action="" class="admin-inline-action" onsubmit="return confirm('Hapus data siswa ini? Data orang tua dan akun login akan tetap disimpan.');">
                                <input type="hidden" name="csrf_token" value="<?= $h($csrfToken) ?>">
                                <input type="hidden" name="action" value="hapus_siswa">
                                <input type="hidden" name="nis" value="<?= $h($s['nis']) ?>">
                                <button type="submit" class="admin-btn admin-btn-danger">Hapus</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr data-empty-state>
                        <td colspan="7" class="admin-empty-state">Belum ada data siswa.</td>
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