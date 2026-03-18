<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = 'localhost';
$db = 'Tp1GcDataBase';
$user = 'root';
$pass = '';

$dsn = "mysql:host={$host};dbname={$db};charset=utf8mb4";

$pdoOptions = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $pdoOptions);
} catch (PDOException $exception) {
    die('Erro na ligação à base de dados: ' . $exception->getMessage());
}
