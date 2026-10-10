<?php
$host = getenv('SIAKADU_DB_HOST') ?: 'localhost';
$dbname = getenv('SIAKADU_DB_NAME') ?: 'siakadu';
$user = getenv('SIAKADU_DB_USER') ?: 'root';
$password = getenv('SIAKADU_DB_PASSWORD');
if ($password === false) {
    $password = '';
}
$port = getenv('SIAKADU_DB_PORT') ?: '3306';

try {
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $dbname);
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 5,
    ]);
} catch (PDOException $e) {
    error_log('Koneksi database gagal: ' . $e->getMessage());
    http_response_code(500);
    die('Koneksi database gagal. Silakan coba lagi nanti.');
}
?>