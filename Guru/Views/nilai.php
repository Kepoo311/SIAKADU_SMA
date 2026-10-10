<?php
session_start();
require "../../DB/koneksi.php";
require "../../Middleware/auth.php";
batas_akses(2);

$nip_guru = $_SESSION['nip'];
$pesan = "";

// 1. Ambil Parameter Filter dari URL
$selected_kelas_id = filter_var($_GET['kelas_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
$selected_mapel_kode = isset($_GET['mapel_kode']) && is_string($_GET['mapel_kode']) ? $_GET['mapel_kode'] : null;
$jenis_nilai_diizinkan = ['Tugas', 'UTS', 'UAS'];
$selected_jenis_nilai = $_GET['jenis_nilai'] ?? 'Tugas';
if (!in_array($selected_jenis_nilai, $jenis_nilai_diizinkan, true)) {
    $selected_jenis_nilai = 'Tugas';
}

// 2. Proses Simpan / Update Nilai saat Form di-submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_nilai'])) {
    try {
        $kelas_id = filter_var($_POST['kelas_id'] ?? null, FILTER_VALIDATE_INT);
        $mapel_kode = $_POST['mapel_kode'] ?? null;
        $jenis_nilai = $_POST['jenis_nilai'] ?? null;
        $affected_nis = [];

        if (!$kelas_id || !is_string($mapel_kode) || $mapel_kode === '' ||
            !in_array($jenis_nilai, $jenis_nilai_diizinkan, true)) {
            throw new InvalidArgumentException('Kelas, mata pelajaran, atau jenis nilai tidak valid.');
        }

        $pdo->beginTransaction();

        $stmt_tugas = $pdo->prepare("SELECT 1
                                     FROM jadwal j
                                     JOIN kelas k ON j.kelas_id_kelas = k.id_kelas
                                     JOIN tahun_ajaran t ON k.tahun_ajaran_id_tahun = t.id_tahun
                                     WHERE j.guru_nip = ? AND j.kelas_id_kelas = ?
                                       AND j.mapel_kode_mapel = ? AND t.status_aktif = 'Y'
                                     LIMIT 1");
        $stmt_tugas->execute([$nip_guru, $kelas_id, $mapel_kode]);
        if (!$stmt_tugas->fetchColumn()) {
            throw new InvalidArgumentException('Kelas atau mata pelajaran bukan penugasan aktif Anda.');
        }

        $stmt_roster = $pdo->prepare("SELECT siswa_nis FROM anggota_kelas WHERE kelas_id_kelas = ?");
        $stmt_roster->execute([$kelas_id]);
        $daftar_nis = array_fill_keys(array_column($stmt_roster->fetchAll(), 'siswa_nis'), true);
        $validasi_nis = static function ($nis) use ($daftar_nis): void {
            if (!is_string($nis) && !is_int($nis)) {
                throw new InvalidArgumentException('Data siswa tidak valid.');
            }
            if (!isset($daftar_nis[$nis])) {
                throw new InvalidArgumentException('Siswa bukan anggota kelas yang dipilih.');
            }
        };
        $validasi_skor = static function ($skor): float {
            if (!is_scalar($skor) || !is_numeric($skor) || (float) $skor < 0 || (float) $skor > 100) {
                throw new InvalidArgumentException('Nilai harus berupa angka antara 0 sampai 100.');
            }
            return (float) $skor;
        };

        if ($jenis_nilai === 'Tugas') {
            foreach (['skor_lama', 'skor_baru'] as $field) {
                if (isset($_POST[$field]) && !is_array($_POST[$field])) {
                    throw new InvalidArgumentException('Data nilai tugas tidak valid.');
                }
            }
            if (isset($_POST['skor_lama']) && is_array($_POST['skor_lama'])) {
                foreach ($_POST['skor_lama'] as $nis => $array_id_nilai) {
                    $validasi_nis($nis);
                    if (!is_array($array_id_nilai)) {
                        throw new InvalidArgumentException('Data nilai tugas tidak valid.');
                    }

                    foreach ($array_id_nilai as $id_nilai => $skor) {
                        $id_nilai = filter_var($id_nilai, FILTER_VALIDATE_INT);
                        if (!$id_nilai) {
                            throw new InvalidArgumentException('Identitas nilai tugas tidak valid.');
                        }
                        $cek = $pdo->prepare("SELECT id_nilai FROM nilai
                                              WHERE id_nilai = ? AND siswa_nis = ?
                                                AND mapel_kode_mapel = ? AND jenis_nilai = 'Tugas'");
                        $cek->execute([$id_nilai, $nis, $mapel_kode]);
                        if (!$cek->fetchColumn()) {
                            throw new InvalidArgumentException('Nilai tugas tidak ditemukan pada kelas dan mata pelajaran ini.');
                        }

                        if (!is_scalar($skor)) {
                            throw new InvalidArgumentException('Nilai tugas tidak valid.');
                        }
                        if (trim((string) $skor) === '') {
                            $delete = $pdo->prepare("DELETE FROM nilai
                                                     WHERE id_nilai = ? AND siswa_nis = ?
                                                       AND mapel_kode_mapel = ? AND jenis_nilai = 'Tugas'");
                            $delete->execute([$id_nilai, $nis, $mapel_kode]);
                        } else {
                            $skor = $validasi_skor($skor);
                            $update = $pdo->prepare("UPDATE nilai SET skor = ?
                                                     WHERE id_nilai = ? AND siswa_nis = ?
                                                       AND mapel_kode_mapel = ? AND jenis_nilai = 'Tugas'");
                            $update->execute([$skor, $id_nilai, $nis, $mapel_kode]);
                        }
                        $affected_nis[$nis] = true;
                    }
                }
            }

            if (isset($_POST['skor_baru']) && is_array($_POST['skor_baru'])) {
                foreach ($_POST['skor_baru'] as $nis => $skor_baru) {
                    $validasi_nis($nis);
                    if ($skor_baru !== '' && $skor_baru !== null) {
                        $skor_baru = $validasi_skor($skor_baru);
                        $insert = $pdo->prepare("INSERT INTO nilai (siswa_nis, mapel_kode_mapel, jenis_nilai, skor) VALUES (?, ?, 'Tugas', ?)");
                        $insert->execute([$nis, $mapel_kode, $skor_baru]);
                        $affected_nis[$nis] = true;
                    }
                }
            }
        } else {
            $nilai_ujian = $_POST['nilai_ujian'] ?? [];
            if (!is_array($nilai_ujian)) {
                throw new InvalidArgumentException('Data nilai ujian tidak valid.');
            }
            foreach ($nilai_ujian as $nis => $skor) {
                $validasi_nis($nis);

                $cek = $pdo->prepare("SELECT id_nilai FROM nilai
                                      WHERE siswa_nis = ? AND mapel_kode_mapel = ? AND jenis_nilai = ?");
                $cek->execute([$nis, $mapel_kode, $jenis_nilai]);
                $existing = $cek->fetch();

                if ($skor === '') {
                    if ($existing) {
                        $delete = $pdo->prepare("DELETE FROM nilai WHERE id_nilai = ? AND siswa_nis = ?
                                                 AND mapel_kode_mapel = ? AND jenis_nilai = ?");
                        $delete->execute([$existing['id_nilai'], $nis, $mapel_kode, $jenis_nilai]);
                        $affected_nis[$nis] = true;
                    }
                } else {
                    $skor = $validasi_skor($skor);
                    if ($existing) {
                        $update = $pdo->prepare("UPDATE nilai SET skor = ?
                                                 WHERE id_nilai = ? AND siswa_nis = ?
                                                   AND mapel_kode_mapel = ? AND jenis_nilai = ?");
                        $update->execute([$skor, $existing['id_nilai'], $nis, $mapel_kode, $jenis_nilai]);
                    } else {
                        $insert = $pdo->prepare("INSERT INTO nilai (siswa_nis, mapel_kode_mapel, jenis_nilai, skor) VALUES (?, ?, ?, ?)");
                        $insert->execute([$nis, $mapel_kode, $jenis_nilai, $skor]);
                    }
                    $affected_nis[$nis] = true;
                }
            }
        }

        $stmt_avg = $pdo->prepare("SELECT ROUND(AVG(nilai_mapel), 2) AS rata_rata
                                   FROM (
                                       SELECT m.kode_mapel,
                                           COALESCE(AVG(CASE WHEN n.jenis_nilai = 'Tugas' THEN n.skor END), 0) * 0.3
                                           + COALESCE(MAX(CASE WHEN n.jenis_nilai = 'UTS' THEN n.skor END), 0) * 0.3
                                           + COALESCE(MAX(CASE WHEN n.jenis_nilai = 'UAS' THEN n.skor END), 0) * 0.4 AS nilai_mapel
                                       FROM anggota_kelas ak
                                       JOIN kelas k ON ak.kelas_id_kelas = k.id_kelas
                                       JOIN tahun_ajaran t ON k.tahun_ajaran_id_tahun = t.id_tahun
                                       JOIN jadwal j ON k.id_kelas = j.kelas_id_kelas
                                       JOIN mata_pelajaran m ON j.mapel_kode_mapel = m.kode_mapel
                                       LEFT JOIN nilai n ON n.siswa_nis = ak.siswa_nis
                                                        AND n.mapel_kode_mapel = m.kode_mapel
                                       WHERE ak.siswa_nis = ? AND t.status_aktif = 'Y'
                                       GROUP BY m.kode_mapel
                                   ) nilai_per_mapel");
        $stmt_update_rapor = $pdo->prepare("UPDATE rapor r
                                           JOIN tahun_ajaran t ON r.tahun_ajaran_id_tahun = t.id_tahun
                                           SET r.rata_rata = ?
                                           WHERE r.siswa_nis = ? AND t.status_aktif = 'Y'");
        foreach (array_keys($affected_nis) as $nis) {
            $stmt_avg->execute([$nis]);
            $rata_rata_baru = $stmt_avg->fetchColumn();
            $stmt_update_rapor->execute([$rata_rata_baru === false || $rata_rata_baru === null ? 0 : $rata_rata_baru, $nis]);
        }

        $pdo->commit();
        $pesan = "<div id='notifikasi' class='form-alert form-alert-success'>Nilai berhasil disimpan dan rata-rata rapor telah diperbarui!</div>";
        $selected_kelas_id = $kelas_id;
        $selected_mapel_kode = $mapel_kode;
        $selected_jenis_nilai = $jenis_nilai;
    } catch (InvalidArgumentException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $pesan = "<div id='notifikasi' class='form-alert form-alert-error'>" . htmlspecialchars($e->getMessage()) . "</div>";
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Gagal menyimpan nilai guru: ' . $e->getMessage());
        $pesan = "<div id='notifikasi' class='form-alert form-alert-error'>Gagal menyimpan nilai. Silakan coba lagi.</div>";
    }
}

// 3. Ambil Daftar Kelas & Mapel Unik yang Diampu oleh Guru Ini
$sql_filter = "SELECT DISTINCT j.kelas_id_kelas, k.nama_kelas, j.mapel_kode_mapel, m.nama_mapel
               FROM jadwal j
               JOIN kelas k ON j.kelas_id_kelas = k.id_kelas
               JOIN mata_pelajaran m ON j.mapel_kode_mapel = m.kode_mapel
               JOIN tahun_ajaran t ON k.tahun_ajaran_id_tahun = t.id_tahun
               WHERE j.guru_nip = ? AND t.status_aktif = 'Y'
               ORDER BY k.nama_kelas ASC, m.nama_mapel ASC";
$stmt_f = $pdo->prepare($sql_filter);
$stmt_f->execute([$nip_guru]);
$list_tugas_mengajar = $stmt_f->fetchAll();

// 4. Ambil Data Siswa jika Kelas dan Mapel sudah dipilih di Filter
$daftar_siswa = [];

if ($selected_kelas_id && $selected_mapel_kode) {
    $stmt_s = $pdo->prepare("SELECT s.nis, s.nama 
                             FROM anggota_kelas ak 
                             JOIN siswa s ON ak.siswa_nis = s.nis 
                             JOIN kelas k ON ak.kelas_id_kelas = k.id_kelas
                             JOIN tahun_ajaran t ON k.tahun_ajaran_id_tahun = t.id_tahun
                             JOIN jadwal j ON j.kelas_id_kelas = k.id_kelas
                             WHERE ak.kelas_id_kelas = ? AND j.mapel_kode_mapel = ?
                               AND j.guru_nip = ? AND t.status_aktif = 'Y'
                             GROUP BY s.nis, s.nama
                             ORDER BY s.nama ASC");
    $stmt_s->execute([$selected_kelas_id, $selected_mapel_kode, $nip_guru]);
    $daftar_siswa = $stmt_s->fetchAll();
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

$page = "nilai";

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Entri Nilai Siswa - SIAKAD Guru</title>
    <link rel="stylesheet" href="style.css">
    <style>

    </style>
</head>
<body>

    <?php include '../../components/sidebarGuru.php'; ?>
    <div class="content">
        <?php include '../../components/navbarGuru.php'; ?>
        <h1 class = "titel">Input Nilai Siswa</h1>

        <?php if ($pesan): ?>
    <?= $pesan ?>
<?php endif; ?>

        <div class="card">
            <h3 class="filter-title">Pilih Kelas & Jenis Penilaian</h3>
            <form method="GET" action="" class="filter-form">
                
                <select name="gabungan" required onchange="parseGabungan(this)" class="select-field">
                    <option value="">-- Pilih Kelas & Mata Pelajaran --</option>
                    <?php foreach ($list_tugas_mengajar as $tm): 
                        $val = htmlspecialchars($tm['kelas_id_kelas'] . "|" . $tm['mapel_kode_mapel']);
                        $is_selected = ($selected_kelas_id == $tm['kelas_id_kelas'] && $selected_mapel_kode == $tm['mapel_kode_mapel']) ? 'selected' : '';
                    ?>
                        <option value="<?= $val ?>" <?= $is_selected ?>>
                            Kelas <?= htmlspecialchars($tm['nama_kelas']) ?> - <?= htmlspecialchars($tm['nama_mapel']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <input type="hidden" name="kelas_id" id="kelas_id" value="<?= htmlspecialchars((string) $selected_kelas_id) ?>">
                <input type="hidden" name="mapel_kode" id="mapel_kode" value="<?= htmlspecialchars((string) $selected_mapel_kode) ?>">

                <select name="jenis_nilai" required class="select-field select-field--small">
                    <option value="Tugas" <?= ($selected_jenis_nilai == 'Tugas') ? 'selected' : '' ?>>Tugas</option>
                    <option value="UTS" <?= ($selected_jenis_nilai == 'UTS') ? 'selected' : '' ?>>UTS</option>
                    <option value="UAS" <?= ($selected_jenis_nilai == 'UAS') ? 'selected' : '' ?>>UAS</option>
                </select>

                <button type="submit" class="btn-primary">Tampilkan</button>
            </form>
        </div>

        <?php if ($selected_kelas_id && $selected_mapel_kode): ?>
        <div class="card table-card">
            <div class="table-header">
                <h3>Input Nilai (Kategori: <?= htmlspecialchars($selected_jenis_nilai) ?>)</h3>
            </div>

            <form method="POST" action="">
                <input type="hidden" name="mapel_kode" value="<?= htmlspecialchars((string) $selected_mapel_kode) ?>">
                <input type="hidden" name="kelas_id" value="<?= (int) $selected_kelas_id ?>">
                <input type="hidden" name="jenis_nilai" value="<?= $selected_jenis_nilai ?>">

                <table class="nilai-table">
                    <thead>
                        <tr>
                            <th style="width: 5%;">No</th>
                            <th style="width: 15%;">NIS</th>
                            <th style="width: 35%;">Nama Siswa</th>
                            <th style="width: 45%;">
                                <?= ($selected_jenis_nilai === 'Tugas') ? 'Daftar Nilai Tugas' : 'Nilai Ujian (0 - 100)' ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($daftar_siswa) > 0): $no = 1; foreach ($daftar_siswa as $s): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= htmlspecialchars($s['nis']) ?></td>
                            <td class="nama-siswa"><?= htmlspecialchars($s['nama']) ?></td>
                            <td>
                                <?php if ($selected_jenis_nilai === 'Tugas'): ?>
                                    <div class="tugas-wrap">
                                        <?php
                                        $stmt_t = $pdo->prepare("SELECT id_nilai, skor FROM nilai WHERE siswa_nis = ? AND mapel_kode_mapel = ? AND jenis_nilai = 'Tugas' ORDER BY id_nilai ASC");
                                        $stmt_t->execute([$s['nis'], $selected_mapel_kode]);
                                        $riwayat_tugas = $stmt_t->fetchAll();
                                        
                                        $tugas_ke = 1;
                                        foreach ($riwayat_tugas as $tugas):
                                        ?>
                                            <div class="task-box">
                                                <span class="task-label">Tugas <?= $tugas_ke++ ?></span>
                                                <input type="hidden" name="skor_lama[<?= htmlspecialchars($s['nis']) ?>][<?= (int) $tugas['id_nilai'] ?>]" value="<?= htmlspecialchars($tugas['skor']) ?>">
                                                <input type="number" name="skor_lama[<?= htmlspecialchars($s['nis']) ?>][<?= (int) $tugas['id_nilai'] ?>]" value="<?= htmlspecialchars($tugas['skor']) ?>" min="0" max="100" class="task-input">
                                            </div>
                                        <?php endforeach; ?>

                                        <div class="task-box--new">
                                            <span class="task-label">+ Baru</span>
                                            <input type="number" name="skor_baru[<?= htmlspecialchars($s['nis']) ?>]" placeholder="0" min="0" max="100" class="new-task-input">
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <?php
                                    $stmt_u = $pdo->prepare("SELECT skor FROM nilai WHERE siswa_nis = ? AND mapel_kode_mapel = ? AND jenis_nilai = ?");
                                    $stmt_u->execute([$s['nis'], $selected_mapel_kode, $selected_jenis_nilai]);
                                    $nilai_ujian_row = $stmt_u->fetch();
                                    $skor_ujian = $nilai_ujian_row['skor'] ?? '';
                                    ?>
                                    <input type="number" name="nilai_ujian[<?= htmlspecialchars($s['nis']) ?>]" value="<?= htmlspecialchars((string) $skor_ujian) ?>" min="0" max="100" placeholder="0" class="ujian-input">
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr>
                            <td colspan="4" class="empty-state">Tidak ada siswa di kelas ini.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div class="submit-row">
                    <button type="submit" name="simpan_nilai" class="submit-btn">Simpan Semua Nilai</button>
                </div>
            </form>
        </div>
        <?php endif; ?>

    </div>

    <script>
    function parseGabungan(selectObj) {
        let val = selectObj.value;
        if (val) {
            let arr = val.split('|');
            document.getElementById('kelas_id').value = arr[0];
            document.getElementById('mapel_kode').value = arr[1];
        } else {
            document.getElementById('kelas_id').value = '';
            document.getElementById('mapel_kode').value = '';
        }
    }
    window.onload = function() {
        let sel = document.querySelector('select[name="gabungan"]');
        if (sel && sel.value === "") {
            let kId = <?= json_encode($selected_kelas_id) ?>;
            let mKode = <?= json_encode($selected_mapel_kode, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
            if(kId && mKode) {
                sel.value = kId + "|" + mKode;
            }
        }
    }
</script>
</body>
</html>