<?php
function wajib_login() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../../index.php"); 
        exit();
    }
}

function batas_akses($role) {
    wajib_login();
    if ($_SESSION['role_id'] != $role) {
        http_response_code(403);
        die("<h1>403 Forbidden</h1><p>Maaf, Anda tidak memiliki akses ke halaman ini.</p>");
    }

    $identitas_role = [
        2 => 'nip',
        3 => 'nis'
    ];
    if (isset($identitas_role[$role]) && empty($_SESSION[$identitas_role[$role]])) {
        http_response_code(403);
        die("<h1>403 Forbidden</h1><p>Data profil akun tidak terhubung. Hubungi admin.</p>");
    }
}
?>