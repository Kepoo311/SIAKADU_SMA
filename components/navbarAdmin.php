<?php
if (!isset($stats['nama_admin'])) {
    $stmt_admin_nav = $pdo->prepare(
        "SELECT u.username, r.nama_role
         FROM user u
         JOIN role r ON u.role_id_role = r.id_role
         WHERE u.id_user = ?"
    );
    $stmt_admin_nav->execute([$_SESSION['user_id'] ?? 0]);
    $admin_nav = $stmt_admin_nav->fetch();

    if (!$admin_nav) {
        die("Data administrator tidak ditemukan.");
    }

    $stats = [
        'nama_admin' => $admin_nav['username'],
        'nama_role' => $admin_nav['nama_role']
    ];
}
?>
<header class="profile">
    <img src="../../public/img/profb.svg" alt="">
    <div>
        <p><?= htmlspecialchars($stats['nama_admin']) ?></p>
        <p><?= htmlspecialchars($stats['nama_role']) ?> - <a href="../../Views/logout.php">Keluar</a></p>
    </div>
</header>
