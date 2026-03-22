<?php
// Ficheiro de configuração base da aplicação.
// Responsabilidades principais:
// - iniciar a sessão PHP;
// - definir os dados de ligação à base de dados;
// - criar a ligação PDO usada pelo resto do projeto.

// Garante que a sessão existe antes de qualquer acesso a $_SESSION.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Parâmetros de ligação ao servidor MySQL.
// Neste projeto a base de dados usada é "Tp1GcDataBase".
$host = 'localhost';
$db = 'Tp1GcDataBase';
$user = 'root';
$pass = '';

// DSN do PDO: identifica o driver, o servidor, a base de dados e o charset.
$dsn = "mysql:host={$host};dbname={$db};charset=utf8mb4";

// Opções da ligação PDO.
// - ERRMODE_EXCEPTION: lança exceções em caso de erro SQL;
// - FETCH_ASSOC: devolve resultados como arrays associativos;
// - EMULATE_PREPARES false: usa prepared statements nativos quando possível.
$pdoOptions = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    // Cria a ligação global à base de dados para reutilização no projeto.
    $pdo = new PDO($dsn, $user, $pass, $pdoOptions);
} catch (PDOException $exception) {
    // Interrompe a execução se a aplicação não conseguir ligar-se à base de dados.
    die('Erro na ligação à base de dados: ' . $exception->getMessage());
}
