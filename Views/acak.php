<?php
// jadwal.php
session_start();
require '../DB/koneksi.php'; // Pastikan file koneksi.php dari langkah sebelumnya ada
require '../Middleware/auth.php'; 
batas_akses(2); 

// 1. Siapkan Kueri SQL
$sql = "SELECT 
            j.hari, 
            j.jam_mulai, 
            j.jam_selesai, 
            k.nama_kelas, 
            m.nama_mapel, 
            g.nama AS nama_guru
        FROM jadwal j
        JOIN kelas k ON j.kelas_id_kelas = k.id_kelas
        JOIN mata_pelajaran m ON j.mapel_kode_mapel = m.kode_mapel
        JOIN guru g ON j.guru_nip = g.nip
        ORDER BY 
            FIELD(j.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'),
            k.nama_kelas ASC,
            j.jam_mulai ASC";

// 2. Eksekusi Kueri menggunakan PDO
$stmt = $pdo->query($sql);
$data_jadwal = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Jadwal Pelajaran SIAKAD</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background-color: #4CAF50; color: white; }
        tr:nth-child(even) { background-color: #f2f2f2; }
        tr:hover { background-color: #ddd; }
    </style>
</head>
<body>

    <h2>Daftar Jadwal Pelajaran (Semua Kelas)</h2>
    
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Hari</th>
                <th>Jam</th>
                <th>Kelas</th>
                <th>Mata Pelajaran</th>
                <th>Guru Pengajar</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            // Cek apakah data kosong
            if (count($data_jadwal) > 0): 
                $no = 1;
                foreach ($data_jadwal as $row): 
                    // Format waktu agar detik (00) tidak tampil, cukup HH:MM
                    $waktu_mulai = date('H:i', strtotime($row['jam_mulai']));
                    $waktu_selesai = date('H:i', strtotime($row['jam_selesai']));
            ?>
                <tr>
                    <td><?php echo $no++; ?></td>
                    <td><?php echo htmlspecialchars($row['hari']); ?></td>
                    <td><?php echo $waktu_mulai . ' - ' . $waktu_selesai; ?></td>
                    <td><?php echo htmlspecialchars($row['nama_kelas']); ?></td>
                    <td><?php echo htmlspecialchars($row['nama_mapel']); ?></td>
                    <td><?php echo htmlspecialchars($row['nama_guru']); ?></td>
                </tr>
            <?php 
                endforeach; 
            else: 
            ?>
                <tr>
                    <td colspan="6" style="text-align: center;">Tidak ada data jadwal ditemukan.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

</body>
</html>