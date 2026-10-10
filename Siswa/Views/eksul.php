<?php
session_start();
require "../../DB/koneksi.php";
require "../../Middleware/auth.php";
batas_akses(3);
$nis_siswa = $_SESSION['nis'];
$pesan = "";

// Proses Pendaftaran Ekskul Baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['daftar_ekskul'])) {
    $id_ekskul_pilihan = $_POST['ekstrakurikuler_id_ekskul'];
    $tanggal_bergabung = date('Y-m-d');
    $jabatan = 'Anggota'; // Jabatan default saat baru mendaftar
    $predikat = '-';      // Predikat kosong/strip sampai dinilai guru

    try {
        // Cek apakah siswa sudah mendaftar ekskul ini sebelumnya
        $cek_exist = $pdo->prepare("SELECT COUNT(*) FROM keanggotaan_ekskul WHERE siswa_nis = ? AND ekstrakurikuler_id_ekskul = ?");
        $cek_exist->execute([$nis_siswa, $id_ekskul_pilihan]);
        
        if ($cek_exist->fetchColumn() > 0) {
            $pesan = "<div class='form-alert form-alert-warning'>Anda sudah terdaftar di ekstrakurikuler ini.</div>";
        } else {
            // Generate ID Manual (MAX + 1)
            $stmt_max = $pdo->query("SELECT MAX(id_keanggotaan) AS max_id FROM keanggotaan_ekskul");
            $new_id = ($stmt_max->fetch()['max_id'] ?? 0) + 1;

            // Insert ke tabel keanggotaan_ekskul sesuai struktur database
            $stmt_insert = $pdo->prepare("INSERT INTO keanggotaan_ekskul (id_keanggotaan, tanggal_bergabung, jabatan, predikat, siswa_nis, ekstrakurikuler_id_ekskul) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt_insert->execute([$new_id, $tanggal_bergabung, $jabatan, $predikat, $nis_siswa, $id_ekskul_pilihan]);
            
            $pesan = "<div class='form-alert form-alert-success'>Berhasil mendaftar ekstrakurikuler!</div>";
        }
    } catch (PDOException $e) {
        error_log('Gagal mendaftarkan siswa ke ekstrakurikuler: ' . $e->getMessage());
        $pesan = $e->getCode() === '23000'
            ? "<div class='form-alert form-alert-warning'>Pendaftaran tidak dapat diproses karena Anda sudah terdaftar atau pilihan ekstrakurikuler tidak valid.</div>"
            : "<div class='form-alert form-alert-error'>Pendaftaran gagal. Silakan coba lagi.</div>";
    } catch (Exception $e) {
        error_log('Gagal mendaftarkan siswa ke ekstrakurikuler: ' . $e->getMessage());
        $pesan = "<div class='form-alert form-alert-error'>Terjadi kesalahan saat mendaftar ekstrakurikuler. Silakan coba lagi.</div>";
    }
}

// Ambil data ekskul yang diikuti siswa untuk tabel
$stmt = $pdo->prepare("SELECT e.nama_ekskul, e.hari_kegiatan, e.tempat, ke.jabatan, ke.predikat
                       FROM keanggotaan_ekskul ke
                       JOIN ekstrakurikuler e ON ke.ekstrakurikuler_id_ekskul = e.id_ekskul
                       WHERE ke.siswa_nis = ?
                       ORDER BY e.nama_ekskul ASC");
$stmt->execute([$nis_siswa]);
$list_ekskul = $stmt->fetchAll();

// Ambil semua daftar ekskul untuk pilihan dropdown
$stmt_all = $pdo->query("SELECT * FROM ekstrakurikuler ORDER BY nama_ekskul ASC");
$all_ekskul = $stmt_all->fetchAll();

$sql = "SELECT 
    s.nama AS nama_siswa,
    r.nama_role

FROM siswa s
JOIN `user` u ON s.user_id_user = u.id_user
JOIN role r ON u.role_id_role = r.id_role
WHERE s.nis = ?;
";

$stmt_s = $pdo->prepare($sql);
$stmt_s->execute([$nis_siswa]);
$stats =$stmt_s->fetch();

$page = "eks";

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Siakadu | Ekskul</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include '../../components/sidebar.php'; ?>
    <div class="content">
            <?php include '../../components/navbar.php'; ?>
            <h1 class = "titel">Ekskul Saya</h1>
               <?= $pesan; ?>
                <div class="div_Eks">
                <h3>Daftar Ekstrakurikuler Baru</h3>
                <form method="POST" action="">
                    <div class="form_in">
                        <select name="ekstrakurikuler_id_ekskul" required>
                            <option value="">-- Pilih Ekstrakurikuler --</option>
                            <?php foreach ($all_ekskul as $eks): ?>
                                <option value="<?= $eks['id_ekskul'] ?>">
                                    <?= htmlspecialchars($eks['nama_ekskul']) ?> 
                                    (<?= htmlspecialchars($eks['hari_kegiatan']) ?> - <?= htmlspecialchars($eks['tempat']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button class="butStyle" type="submit" name="daftar_ekskul">
                            + Daftar Sekarang
                        </button>
                    </div>
                </form>
            </div>
            <div class="tabel_data">
              <div class="table-tools">
                <input type="search" data-table-search="student-extracurricular-list" placeholder="Cari nama ekskul, hari, tempat, atau jabatan..." aria-label="Cari ekstrakurikuler berdasarkan nama, hari, tempat, atau jabatan">
              </div>
              <p class="table-search-results" data-table-results="student-extracurricular-list" aria-live="polite"></p>
              <p class="table-search-empty" data-table-empty="student-extracurricular-list" hidden>Tidak ada ekstrakurikuler yang sesuai dengan pencarian.</p>
              <table class="tbl_dkls" id="student-extracurricular-list">
                  <thead class ="thead_kls">
                      <tr data-search="<?= htmlspecialchars(implode(' ', [$row['nama_ekskul'], $row['hari_kegiatan'], $row['tempat'], $row['jabatan'], $row['predikat']]), ENT_QUOTES, 'UTF-8') ?>">
                          <th>NO</th> 
                          <th>Nama Ekstrakurikuler</th>
                          <th>Hari</th>
                          <th>Tempat</th>
                          <th>Status</th>
                          <th>Jabatan</th>
                      </tr>
                  </thead>
                  <tbody>
                        <?php 
                        if (count($list_ekskul) > 0):
                            $no = 1;
                            foreach ($list_ekskul as $row): 
                        ?>
                        <tr data-search="<?= htmlspecialchars(implode(' ', [$row['nama_ekskul'], $row['hari_kegiatan'], $row['tempat'], $row['jabatan'], $row['predikat']]), ENT_QUOTES, 'UTF-8') ?>">
                            <td><?= $no++ ?></td>
                            <td><?= htmlspecialchars($row['nama_ekskul']) ?></td>
                            <td><?= htmlspecialchars($row['hari_kegiatan']) ?></td>
                            <td><?= htmlspecialchars($row['tempat']) ?></td>
                            <td style="padding: 15px;">
                                <span style="background: #d1f2d1; color: #2e7d32; padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: bold;">Aktif</span>
                            </td>
                            <td><?= htmlspecialchars($row['jabatan']) ?></td>
                        </tr>
                        <?php 
                            endforeach;
                        else: 
                        ?>
                        <tr data-empty-state>
                            <td colspan="6" style="padding: 20px; text-align: center; color: #888;">Anda belum mengikuti ekstrakurikuler apapun.</td>
                        </tr>
                        <?php endif; ?>
                  </tbody>
              </table>
          </div>
    </div>
<script src="../../Views/table-search.js" defer></script>
</body>
</html>