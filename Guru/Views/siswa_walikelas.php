<?php
session_start();
require "../../DB/koneksi.php";
require "../../Middleware/auth.php";
batas_akses(2);

$nip_guru = $_SESSION['nip'];
$stmt_guru = $pdo->prepare(
    "SELECT g.nama AS nama_guru, r.nama_role
     FROM guru g
     JOIN `user` u ON g.user_id_user = u.id_user
     JOIN role r ON u.role_id_role = r.id_role
     WHERE g.nip = ?"
);
$stmt_guru->execute([$nip_guru]);
$stats = $stmt_guru->fetch();
if (!$stats) {
    die("Data guru tidak ditemukan.");
}

$statusOptions = ['Naik Kelas', 'Tinggal Kelas', 'Belum ditentukan'];
$pesan = $_SESSION['guru_status_kenaikan_flash'] ?? null;
unset($_SESSION['guru_status_kenaikan_flash']);

$csrfToken = $_SESSION['guru_status_kenaikan_csrf'] ?? '';
if ($csrfToken === '') {
    $csrfToken = bin2hex(random_bytes(32));
    $_SESSION['guru_status_kenaikan_csrf'] = $csrfToken;
}

$tahun_ajaran_aktif = $pdo->query(
    "SELECT id_tahun, tahun, semester
     FROM tahun_ajaran
     WHERE status_aktif = 'Y'
     LIMIT 1"
)->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_POST['csrf_token']) || !hash_equals($csrfToken, (string) $_POST['csrf_token'])) {
            throw new InvalidArgumentException('Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.');
        }

        $nis = isset($_POST['nis']) && is_string($_POST['nis']) ? trim($_POST['nis']) : '';
        $status = isset($_POST['status_kenaikan']) && is_string($_POST['status_kenaikan']) ? $_POST['status_kenaikan'] : '';
        if ($nis === '' || strlen($nis) > 20 || !in_array($status, $statusOptions, true)) {
            throw new InvalidArgumentException('Siswa atau status kenaikan yang dipilih tidak valid.');
        }
        $pdo->beginTransaction();
        $tahunAktifUpdate = $pdo->query(
            "SELECT id_tahun
             FROM tahun_ajaran
             WHERE status_aktif = 'Y'
             LIMIT 1
             FOR UPDATE"
        )->fetchColumn();
        if ($tahunAktifUpdate === false) {
            throw new InvalidArgumentException('Tidak ada tahun ajaran aktif untuk diperbarui.');
        }

        $stmt_rapor = $pdo->prepare(
            "SELECT r.id_rapor
             FROM rapor r
             JOIN anggota_kelas ak ON ak.siswa_nis = r.siswa_nis
             JOIN kelas k ON k.id_kelas = ak.kelas_id_kelas
             JOIN tahun_ajaran t ON t.id_tahun = k.tahun_ajaran_id_tahun
             WHERE r.siswa_nis = ?
               AND r.tahun_ajaran_id_tahun = t.id_tahun
               AND t.id_tahun = ?
               AND k.guru_nip = ?
               AND t.status_aktif = 'Y'
             LIMIT 1
             FOR UPDATE"
        );
        $stmt_rapor->execute([$nis, $tahunAktifUpdate, $nip_guru]);
        $rapor = $stmt_rapor->fetch();
        if (!$rapor) {
            throw new InvalidArgumentException('Siswa tidak ditemukan di kelas wali Anda pada tahun ajaran aktif, atau rapornya belum tersedia.');
        }

        $stmt_update = $pdo->prepare(
            "UPDATE rapor
             SET status_kenaikan = ?
             WHERE id_rapor = ? AND tahun_ajaran_id_tahun = ?"
        );
        $stmt_update->execute([$status, $rapor['id_rapor'], $tahunAktifUpdate]);
        $pdo->commit();
        $_SESSION['guru_status_kenaikan_flash'] = ['success', 'Status kenaikan siswa berhasil diperbarui.'];
    } catch (InvalidArgumentException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['guru_status_kenaikan_flash'] = ['error', $e->getMessage()];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Gagal memperbarui status kenaikan siswa: ' . $e->getMessage());
        $_SESSION['guru_status_kenaikan_flash'] = ['error', 'Gagal memperbarui status kenaikan. Silakan coba lagi.'];
    }

    header('Location: siswa_walikelas.php');
    exit;
}

$daftar_siswa = [];
if ($tahun_ajaran_aktif) {
    $stmt_siswa = $pdo->prepare(
        "SELECT s.nis, s.nama, k.nama_kelas, r.status_kenaikan
         FROM kelas k
         JOIN anggota_kelas ak ON ak.kelas_id_kelas = k.id_kelas
         JOIN siswa s ON s.nis = ak.siswa_nis
         LEFT JOIN rapor r
           ON r.siswa_nis = s.nis
          AND r.tahun_ajaran_id_tahun = k.tahun_ajaran_id_tahun
         WHERE k.guru_nip = ?
           AND k.tahun_ajaran_id_tahun = ?
         ORDER BY k.nama_kelas ASC, s.nama ASC"
    );
    $stmt_siswa->execute([$nip_guru, $tahun_ajaran_aktif['id_tahun']]);
    $daftar_siswa = $stmt_siswa->fetchAll();
}

$statusFilterKey = static function ($status): string {
    $normalized = strtolower(trim((string) $status));
    if (str_contains($normalized, 'tinggal') || str_contains($normalized, 'tidak naik')) {
        return 'retained';
    }
    if (str_contains($normalized, 'naik')) {
        return 'promoted';
    }
    if ($normalized === '' || str_contains($normalized, 'belum')) {
        return 'pending';
    }
    return 'other';
};

$page = "siswa_walikelas";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Siswa Wali Kelas - SIAKAD Guru</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include '../../components/sidebarGuru.php'; ?>
    <div class="content">
        <?php include '../../components/navbarGuru.php'; ?>
        <h1 class="titel">Siswa Wali Kelas</h1>
        <?php if ($pesan): ?>
            <div class="form-alert form-alert-<?= htmlspecialchars($pesan[0], ENT_QUOTES, 'UTF-8') ?>" role="status">
                <?= htmlspecialchars($pesan[1], ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if (!$tahun_ajaran_aktif): ?>
            <div class="tabel_data">
                <p class="empty-state">Belum ada tahun ajaran aktif.</p>
            </div>
        <?php else: ?>
            <p class="table-search-results">
                Tahun ajaran <?= htmlspecialchars($tahun_ajaran_aktif['tahun'] . ' ' . $tahun_ajaran_aktif['semester'], ENT_QUOTES, 'UTF-8') ?>.
                Anda dapat mengubah status kenaikan siswa untuk rapor tahun ajaran aktif.
            </p>
            <div class="table-tools">
                <input type="search" data-table-search="homeroom-student-list" placeholder="Cari nama, NIS, kelas, atau status..." aria-label="Cari siswa wali kelas berdasarkan nama, NIS, kelas, atau status">
                <select data-table-filter="homeroom-student-list" aria-label="Filter status kenaikan kelas">
                    <option value="">Semua status kenaikan</option>
                    <option value="promoted">Naik Kelas</option>
                    <option value="retained">Tinggal Kelas</option>
                    <option value="pending">Belum ditentukan</option>
                    <option value="other">Status lainnya</option>
                </select>
            </div>
            <p class="table-search-results" data-table-results="homeroom-student-list" aria-live="polite"></p>
            <p class="table-search-empty" data-table-empty="homeroom-student-list" hidden>Tidak ada siswa yang sesuai dengan pencarian.</p>
            <div class="tabel_data">
                <table class="tbl_dkls" id="homeroom-student-list">
                    <thead class="thead_kls">
                        <tr>
                            <th>No</th>
                            <th>NIS</th>
                            <th>Nama Siswa</th>
                            <th>Kelas</th>
                            <th>Status Kenaikan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($daftar_siswa): $no = 1; foreach ($daftar_siswa as $siswa):
                            $statusRaw = trim((string) ($siswa['status_kenaikan'] ?? ''));
                            $statusLabel = $statusRaw !== '' ? $statusRaw : 'Belum ditentukan';
                            $statusKey = $statusFilterKey($statusLabel);
                            $searchText = implode(' ', [$siswa['nis'], $siswa['nama'], $siswa['nama_kelas'], $statusLabel]);
                        ?>
                            <tr data-search="<?= htmlspecialchars($searchText, ENT_QUOTES, 'UTF-8') ?>" data-status-filter="<?= htmlspecialchars($statusKey, ENT_QUOTES, 'UTF-8') ?>">
                                <td><?= $no++ ?></td>
                                <td><?= htmlspecialchars($siswa['nis'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($siswa['nama'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($siswa['nama_kelas'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><span class="promotion-status promotion-status-<?= htmlspecialchars($statusKey, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td>
                                    <form method="post" class="promotion-edit-form">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="nis" value="<?= htmlspecialchars($siswa['nis'], ENT_QUOTES, 'UTF-8') ?>">
                                        <label class="visually-hidden" for="status-<?= htmlspecialchars($siswa['nis'], ENT_QUOTES, 'UTF-8') ?>">Status kenaikan <?= htmlspecialchars($siswa['nama'], ENT_QUOTES, 'UTF-8') ?></label>
                                        <select id="status-<?= htmlspecialchars($siswa['nis'], ENT_QUOTES, 'UTF-8') ?>" name="status_kenaikan" class="form-select" required>
                                            <?php if (!in_array($statusLabel, $statusOptions, true)): ?>
                                                <option value="" selected disabled><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?> (status saat ini)</option>
                                            <?php endif; ?>
                                            <?php foreach ($statusOptions as $option): ?>
                                                <option value="<?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?>" <?= $statusLabel === $option ? 'selected' : '' ?>><?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-primary">Simpan</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr data-empty-state><td colspan="6" class="empty-state">Belum ada siswa yang ditempatkan pada kelas wali tahun ajaran aktif.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <script src="../../Views/table-search.js" defer></script>
</body>
</html>
