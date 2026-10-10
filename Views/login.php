<?php
ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
require '../DB/koneksi.php';

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role_id'] == 1) header("Location: ../Admin/Views/dashboard.php");
    if ($_SESSION['role_id'] == 2) header("Location: ../Guru/Views/dashboard.php");
    if ($_SESSION['role_id'] == 3) header("Location: ../Siswa/Views/dashboard.php");
    exit();
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {  
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT id_user, password, role_id_role FROM user WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id_user'];
        $_SESSION['role_id'] = $user['role_id_role'];

        if ($user['role_id_role'] == 3) {
        $stmt_nis = $pdo->prepare("SELECT nis FROM siswa WHERE user_id_user = ?");
        $stmt_nis->execute([$user['id_user']]);
        $siswa = $stmt_nis->fetch();
        
        if ($siswa) {
            // SIMPAN NIS KE DALAM SESSION!
            $_SESSION['nis'] = $siswa['nis'];
        }
    }

    if ($user['role_id_role'] == 2) {
        $stmt_nip = $pdo->prepare("SELECT nip FROM guru WHERE user_id_user = ?");
        $stmt_nip->execute([$user['id_user']]);
        $guru = $stmt_nip->fetch();
        
        if ($guru) {
            // SIMPAN nip KE DALAM SESSION!
            $_SESSION['nip'] = $guru['nip'];
        }
    }
        
        header("Location: login.php");
        exit();
    } else {
        $error = "Username atau Password salah!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login SIAKAD</title>
    <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>">
    <script src="toast.js" defer></script>
</head>
<body>
    <div class="box">
    <div class="title">
        <h2>Sistem akademik SMA</h2>
    </div>
    
    <div class="login_form">
        <h1>Login</h1>
        <p class = "deks">Masuk dengan akun yang telah diberikan</p>
    <form method="POST" action="">
        <?php if ($error): ?>
            <p class="login_error form-alert form-alert-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <div class="input_i">
            <img src="../public/img/bi_person.svg" alt="">
            <input type="text" name="username" placeholder="Username" required>
        </div>
        <div class="input_i">
            <img src="../public/img/ant-design_lock-outlined.svg" alt="">
            <input type="password" name="password" placeholder="Password" required>
        </div>
        <button type="submit">Masuk</button>
        <p class="deks2">Belum punya akun? <a href="#">Hubungi admin</a></p>
    </form>
    </div>
    </div>

</body>
</html>