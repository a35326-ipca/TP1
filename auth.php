<?php
// Ficheiro central de autenticação, autorização e apoio à aplicação.
// Aqui ficam reunidas funções de:
// - acesso à base de dados;
// - gestão de sessão e utilizador autenticado;
// - verificação de permissões;
// - preparação automática de tabelas e dados base.

require_once 'config.php';

// Cabeçalhos HTTP de reforço básico de segurança para as páginas da aplicação.
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Tempo máximo de inatividade antes de expirar a sessão autenticada.
const SESSION_TIMEOUT_SECONDS = 1800;

// Helpers genéricos de leitura e escrita sobre PDO.
function db_fetch_all(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function db_fetch_one(PDO $pdo, string $sql, array $params = []): ?array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function db_fetch_value(PDO $pdo, string $sql, array $params = []): mixed
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchColumn();
}

function db_execute(PDO $pdo, string $sql, array $params = []): bool
{
    $stmt = $pdo->prepare($sql);

    return $stmt->execute($params);
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Helpers de introspeção do esquema, usados nas migrações automáticas.
function table_exists(PDO $pdo, string $table): bool
{
    $sql = "
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
    ";

    return (int) db_fetch_value($pdo, $sql, [$table]) > 0;
}

function column_exists(PDO $pdo, string $table, string $column): bool
{
    $sql = "
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
    ";

    return (int) db_fetch_value($pdo, $sql, [$table, $column]) > 0;
}

function index_exists(PDO $pdo, string $table, string $index): bool
{
    $sql = "
        SELECT COUNT(*)
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?
    ";

    return (int) db_fetch_value($pdo, $sql, [$table, $index]) > 0;
}

// Garante a existência e compatibilidade da tabela de utilizadores.
function ensure_users_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(190) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('gestor', 'funcionario', 'aluno') NOT NULL DEFAULT 'aluno',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $roleInfo = db_fetch_one($pdo, "SHOW COLUMNS FROM users LIKE 'role'");
    $type = $roleInfo['Type'] ?? '';

    if (str_contains($type, "'admin'") || str_contains($type, "'user'")) {
        $pdo->exec("ALTER TABLE users MODIFY role ENUM('gestor', 'funcionario', 'aluno', 'admin', 'user') NOT NULL DEFAULT 'aluno'");
        $pdo->exec("UPDATE users SET role = 'gestor' WHERE role = 'admin'");
        $pdo->exec("UPDATE users SET role = 'aluno' WHERE role = 'user'");
    }

    $pdo->exec("ALTER TABLE users MODIFY role ENUM('gestor', 'funcionario', 'aluno') NOT NULL DEFAULT 'aluno'");
}

// Cria e ajusta as tabelas principais da aplicação.
// Este bloco permite que o projeto se auto-prepare na primeira execução.
function ensure_primary_tables(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS courses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_courses_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS units (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_units_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS study_plan (
            id INT AUTO_INCREMENT PRIMARY KEY,
            course_id INT NOT NULL,
            unit_id INT NOT NULL,
            year_number TINYINT UNSIGNED NOT NULL,
            semester TINYINT UNSIGNED NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_study_plan_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
            CONSTRAINT fk_study_plan_unit FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE,
            UNIQUE KEY uq_study_plan (course_id, unit_id, year_number, semester)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS student_profiles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            course_id INT NOT NULL,
            full_name VARCHAR(150) DEFAULT '',
            birth_date DATE NULL,
            contact_email VARCHAR(190) DEFAULT '',
            phone VARCHAR(40) DEFAULT '',
            address VARCHAR(255) DEFAULT '',
            photo_path VARCHAR(255) DEFAULT NULL,
            notes TEXT NULL,
            status ENUM('rascunho', 'submetida', 'aprovada', 'rejeitada') NOT NULL DEFAULT 'rascunho',
            review_notes TEXT NULL,
            reviewed_by INT NULL,
            reviewed_at TIMESTAMP NULL DEFAULT NULL,
            submitted_at TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_student_profiles_user (user_id),
            CONSTRAINT fk_student_profile_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_student_profile_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE RESTRICT,
            CONSTRAINT fk_student_profile_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS deleted_student_profiles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            original_profile_id INT NOT NULL,
            user_id INT NOT NULL,
            course_id INT NOT NULL,
            full_name VARCHAR(150) DEFAULT '',
            birth_date DATE NULL,
            contact_email VARCHAR(190) DEFAULT '',
            phone VARCHAR(40) DEFAULT '',
            address VARCHAR(255) DEFAULT '',
            photo_path VARCHAR(255) DEFAULT NULL,
            notes TEXT NULL,
            status ENUM('rascunho', 'submetida', 'aprovada', 'rejeitada') NOT NULL DEFAULT 'rascunho',
            review_notes TEXT NULL,
            reviewed_by INT NULL,
            reviewed_at TIMESTAMP NULL DEFAULT NULL,
            submitted_at TIMESTAMP NULL DEFAULT NULL,
            original_created_at DATETIME NULL DEFAULT NULL,
            original_updated_at DATETIME NULL DEFAULT NULL,
            deleted_by INT NULL,
            deleted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            purge_after DATETIME NOT NULL,
            INDEX idx_deleted_student_profiles_purge_after (purge_after)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    if (!column_exists($pdo, 'student_profiles', 'birth_date')) {
        $pdo->exec("ALTER TABLE student_profiles ADD COLUMN birth_date DATE NULL AFTER full_name");
    }

    if (!column_exists($pdo, 'student_profiles', 'address')) {
        $pdo->exec("ALTER TABLE student_profiles ADD COLUMN address VARCHAR(255) DEFAULT '' AFTER phone");
    }

    if (table_exists($pdo, 'deleted_student_profiles')) {
        if (column_exists($pdo, 'deleted_student_profiles', 'original_created_at')) {
            $pdo->exec("ALTER TABLE deleted_student_profiles MODIFY original_created_at DATETIME NULL DEFAULT NULL");
        }

        if (column_exists($pdo, 'deleted_student_profiles', 'original_updated_at')) {
            $pdo->exec("ALTER TABLE deleted_student_profiles MODIFY original_updated_at DATETIME NULL DEFAULT NULL");
        }

        if (column_exists($pdo, 'deleted_student_profiles', 'deleted_at')) {
            $pdo->exec("ALTER TABLE deleted_student_profiles MODIFY deleted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
        }

        if (column_exists($pdo, 'deleted_student_profiles', 'purge_after')) {
            $pdo->exec("ALTER TABLE deleted_student_profiles MODIFY purge_after DATETIME NOT NULL");
        }
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS student_profile_decisions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_profile_id INT NOT NULL,
            previous_status ENUM('rascunho', 'submetida', 'aprovada', 'rejeitada') NOT NULL,
            new_status ENUM('rascunho', 'submetida', 'aprovada', 'rejeitada') NOT NULL,
            previous_review_notes TEXT NULL,
            new_review_notes TEXT NULL,
            reviewed_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_student_profile_decisions_profile_created (student_profile_id, created_at),
            CONSTRAINT fk_student_profile_decisions_profile FOREIGN KEY (student_profile_id) REFERENCES student_profiles(id) ON DELETE CASCADE,
            CONSTRAINT fk_student_profile_decisions_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS enrollment_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            course_id INT NOT NULL,
            status ENUM('pendente', 'aprovado', 'rejeitado') NOT NULL DEFAULT 'pendente',
            student_notes TEXT NULL,
            decision_notes TEXT NULL,
            decided_by INT NULL,
            decided_at TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_enrollment_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_enrollment_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE RESTRICT,
            CONSTRAINT fk_enrollment_decided_by FOREIGN KEY (decided_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS enrollment_request_decisions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            enrollment_request_id INT NOT NULL,
            previous_status ENUM('pendente', 'aprovado', 'rejeitado') NOT NULL,
            new_status ENUM('pendente', 'aprovado', 'rejeitado') NOT NULL,
            previous_decision_notes TEXT NULL,
            new_decision_notes TEXT NULL,
            decided_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_enrollment_request_decisions_request_created (enrollment_request_id, created_at),
            CONSTRAINT fk_enrollment_request_decisions_request FOREIGN KEY (enrollment_request_id) REFERENCES enrollment_requests(id) ON DELETE CASCADE,
            CONSTRAINT fk_enrollment_request_decisions_decided_by FOREIGN KEY (decided_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS submission_events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            event_type VARCHAR(80) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_submission_events_user_type_created (user_id, event_type, created_at),
            CONSTRAINT fk_submission_events_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS grade_sheets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            unit_id INT NOT NULL,
            academic_year VARCHAR(20) NOT NULL,
            season VARCHAR(30) NOT NULL,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_grade_sheets_unit FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE,
            CONSTRAINT fk_grade_sheets_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
            UNIQUE KEY uq_grade_sheets (unit_id, academic_year, season)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS grade_sheet_students (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sheet_id INT NOT NULL,
            student_user_id INT NOT NULL,
            final_grade DECIMAL(5,2) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_grade_sheet_students_sheet FOREIGN KEY (sheet_id) REFERENCES grade_sheets(id) ON DELETE CASCADE,
            CONSTRAINT fk_grade_sheet_students_user FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY uq_grade_sheet_students (sheet_id, student_user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

// Controlo de frequência de submissões por utilizador e tipo de evento.
function count_submission_events_last_24_hours(PDO $pdo, int $userId, string $eventType): int
{
    return (int) db_fetch_value(
        $pdo,
        "SELECT COUNT(*)
         FROM submission_events
         WHERE user_id = ? AND event_type = ? AND created_at >= (NOW() - INTERVAL 24 HOUR)",
        [$userId, $eventType]
    );
}

function has_submission_limit_available(PDO $pdo, int $userId, string $eventType, int $limit = 5): bool
{
    return count_submission_events_last_24_hours($pdo, $userId, $eventType) < $limit;
}

function register_submission_event(PDO $pdo, int $userId, string $eventType): void
{
    db_execute(
        $pdo,
        'INSERT INTO submission_events (user_id, event_type) VALUES (?, ?)',
        [$userId, $eventType]
    );
}

// Migra dados de tabelas antigas para a estrutura atual, quando existirem.
function migrate_legacy_catalog(PDO $pdo): void
{
    if (table_exists($pdo, 'cursos') && (int) db_fetch_value($pdo, "SELECT COUNT(*) FROM courses") === 0) {
        $activeSql = column_exists($pdo, 'cursos', 'ativo') ? 'COALESCE(ativo, 1)' : '1';
        $pdo->exec("
            INSERT INTO courses (name, is_active, created_at, updated_at)
            SELECT Nome, {$activeSql}, NOW(), NOW()
            FROM cursos
            ORDER BY Id_cursos
        ");
    }

    if (table_exists($pdo, 'disciplinas') && (int) db_fetch_value($pdo, "SELECT COUNT(*) FROM units") === 0) {
        $pdo->exec("
            INSERT INTO units (name, created_at, updated_at)
            SELECT nome_disciplina, NOW(), NOW()
            FROM disciplinas
            ORDER BY Id_disciplina
        ");
    }

    if (
        table_exists($pdo, 'plano_estudos')
        && table_exists($pdo, 'cursos')
        && table_exists($pdo, 'disciplinas')
        && (int) db_fetch_value($pdo, "SELECT COUNT(*) FROM study_plan") === 0
    ) {
        $yearSql = column_exists($pdo, 'plano_estudos', 'ano_curricular') ? 'COALESCE(op.ano_curricular, 1)' : '1';
        $semesterSql = column_exists($pdo, 'plano_estudos', 'semestre') ? 'COALESCE(op.semestre, 1)' : '1';

        $pdo->exec("
            INSERT INTO study_plan (course_id, unit_id, year_number, semester, created_at)
            SELECT DISTINCT nc.id, nu.id, {$yearSql}, {$semesterSql}, NOW()
            FROM plano_estudos op
            INNER JOIN cursos oc ON oc.Id_cursos = op.cursos
            INNER JOIN disciplinas od ON od.Id_disciplina = op.disciplinas
            INNER JOIN courses nc ON nc.name = oc.Nome
            INNER JOIN units nu ON nu.name = od.nome_disciplina
        ");
    }
}

// Migra matrículas antigas para o novo modelo de pedidos de matrícula.
function migrate_legacy_enrollments(PDO $pdo): void
{
    if (
        !table_exists($pdo, 'matriculas')
        || !table_exists($pdo, 'cursos')
        || (int) db_fetch_value($pdo, "SELECT COUNT(*) FROM enrollment_requests") > 0
    ) {
        return;
    }

    $pdo->exec("
        INSERT INTO enrollment_requests (user_id, course_id, status, student_notes, decision_notes, decided_at, created_at, updated_at)
        SELECT
            m.user_id,
            c.id,
            CASE m.estado
                WHEN 'aceite' THEN 'aprovado'
                WHEN 'recusada' THEN 'rejeitado'
                ELSE 'pendente'
            END,
            m.observacoes,
            m.observacao_gestor,
            m.reviewed_at,
            m.created_at,
            COALESCE(m.reviewed_at, m.created_at)
        FROM matriculas m
        INNER JOIN cursos oc ON oc.Id_cursos = m.curso_id
        INNER JOIN courses c ON c.name = oc.Nome
    ");
}

// Cria contas padrão mínimas para facilitar arranque e demonstração do sistema.
function ensure_default_accounts(PDO $pdo): void
{
    $defaultAccounts = [
        [
            'name' => 'Gestor',
            'email' => 'gestor@site.local',
            'password' => 'Gestor123!',
            'role' => 'gestor',
        ],
        [
            'name' => 'Funcionário',
            'email' => 'funcionario@site.local',
            'password' => 'Func12345!',
            'role' => 'funcionario',
        ],
        [
            'name' => 'Aluno Base',
            'email' => 'aluno@site.local',
            'password' => 'Aluno12345!',
            'role' => 'aluno',
        ],
    ];

    foreach ($defaultAccounts as $account) {
        $existing = db_fetch_one($pdo, 'SELECT id FROM users WHERE email = ? LIMIT 1', [$account['email']]);

        if ($existing) {
            continue;
        }

        db_execute(
            $pdo,
            'INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)',
            [
                $account['name'],
                $account['email'],
                password_hash($account['password'], PASSWORD_DEFAULT),
                $account['role'],
            ]
        );
    }
}

// Remove registos apagados cujo prazo de retenção já terminou.
function purge_deleted_student_profiles(PDO $pdo): void
{
    $expiredProfiles = db_fetch_all(
        $pdo,
        'SELECT id, photo_path
         FROM deleted_student_profiles
         WHERE purge_after <= NOW()'
    );

    foreach ($expiredProfiles as $expiredProfile) {
        delete_uploaded_file($expiredProfile['photo_path'] ?? null);
    }

    if ($expiredProfiles !== []) {
        $pdo->exec('DELETE FROM deleted_student_profiles WHERE purge_after <= NOW()');
    }
}

// Executa uma única vez a preparação inicial necessária para a aplicação funcionar.
function auth_bootstrap(PDO $pdo): void
{
    static $booted = false;

    if ($booted) {
        return;
    }

    ensure_users_schema($pdo);
    ensure_primary_tables($pdo);
    migrate_legacy_catalog($pdo);
    migrate_legacy_enrollments($pdo);
    ensure_default_accounts($pdo);
    purge_deleted_student_profiles($pdo);

    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    $booted = true;
}

// Mensagens transitórias e reposição de dados de formulários após redirecionamentos.
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}

function set_old_input(array $data): void
{
    $_SESSION['old_input'] = $data;
}

function get_old_input(): array
{
    $oldInput = $_SESSION['old_input'] ?? [];
    unset($_SESSION['old_input']);

    return is_array($oldInput) ? $oldInput : [];
}

function old_value(array $oldInput, string $key): string
{
    return h((string) ($oldInput[$key] ?? ''));
}

// Redirecionamento simples para centralizar saídas por HTTP.
function redirect_to(string $path = 'index.php'): void
{
    header('Location: ' . $path);
    exit;
}

// Estado atual do utilizador autenticado guardado em sessão.
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function user_role(): ?string
{
    return current_user()['role'] ?? null;
}

function current_user_role(): ?string
{
    return user_role();
}

// Verificações de papéis/perfis para controlo de acesso.
function has_role(string ...$roles): bool
{
    return is_logged_in() && in_array(user_role(), $roles, true);
}

function is_gestor(): bool
{
    return has_role('gestor');
}

function is_funcionario(): bool
{
    return has_role('funcionario');
}

function is_aluno(): bool
{
    return has_role('aluno');
}

function role_label(string $role): string
{
    return match ($role) {
        'gestor' => 'Gestor',
        'funcionario' => 'Funcionário',
        'aluno' => 'Aluno',
        default => ucfirst($role),
    };
}

function dashboard_path_for_current_user(): string
{
    if (is_gestor()) {
        return 'hub_gestor.php';
    }

    if (is_funcionario()) {
        return 'hub_funcionario.php';
    }

    return 'hub_aluno.php';
}

// Guardas de acesso: interrompem a navegação se o utilizador não cumprir os requisitos.
function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('error', 'Precisas de iniciar sessão para continuar.');
        redirect_to('login.php');
    }
}

function require_roles(string ...$roles): void
{
    require_login();

    if (!has_role(...$roles)) {
        set_flash('error', 'Não tens permissão para aceder a esta área.');
        redirect_to(dashboard_path_for_current_user());
    }
}

function require_gestor(): void
{
    require_roles('gestor');
}

function require_funcionario(): void
{
    require_roles('funcionario');
}

function require_aluno(): void
{
    require_roles('aluno');
}

// Regras específicas do fluxo do aluno, dependentes do estado da ficha.
function student_profile_status(PDO $pdo, ?int $userId = null): ?string
{
    $resolvedUserId = $userId ?? (int) (current_user()['id'] ?? 0);

    if ($resolvedUserId <= 0) {
        return null;
    }

    $status = db_fetch_value(
        $pdo,
        'SELECT status
         FROM student_profiles
         WHERE user_id = ? LIMIT 1',
        [$resolvedUserId]
    );

    return is_string($status) ? $status : null;
}

function student_access_unlocked(PDO $pdo, ?int $userId = null): bool
{
    return student_profile_status($pdo, $userId) === 'aprovada';
}

function require_student_access_unlocked(PDO $pdo): void
{
    if (!student_access_unlocked($pdo)) {
        set_flash('error', 'Só podes aceder a esta área depois de submeter a ficha e ela ser aprovada.');
        redirect_to('aluno_ficha.php');
    }
}

function normalize_email(string $email): string
{
    return mb_strtolower(trim($email));
}

// Atualiza os dados do utilizador em sessão a partir da base de dados.
function refresh_session_user(PDO $pdo, int $id): void
{
    $user = db_fetch_one($pdo, 'SELECT id, name, email, role, created_at FROM users WHERE id = ? LIMIT 1', [$id]);

    if ($user) {
        $_SESSION['user'] = $user;
    }
}

// Token CSRF usado para validar pedidos com alteração de estado.
function csrf_token(): string
{
    return $_SESSION['csrf_token'];
}
function csrf_query(): string
{
    return 'csrf_token=' . rawurlencode(csrf_token());
}

function verify_csrf_value(?string $token, string $path = 'login.php'): void
{
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        set_flash('error', 'Pedido inválido. Tenta novamente.');
        redirect_to($path);
    }
}

function verify_csrf(string $path = 'login.php'): void
{
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        set_flash('error', 'Pedido inválido. Tenta novamente.');
        redirect_to($path);
    }
}

// Limitação simples de tentativas falhadas de autenticação por sessão.
function record_login_attempt(): void
{
    $window = 900;
    $maxAttempts = 5;
    $now = time();
    $attempts = $_SESSION['login_attempts'] ?? [];

    $attempts = array_values(array_filter(
        $attempts,
        static fn (int $timestamp): bool => ($timestamp + $window) > $now
    ));

    $attempts[] = $now;
    $_SESSION['login_attempts'] = $attempts;

    if (count($attempts) > $maxAttempts) {
        set_flash('error', 'Muitas tentativas falhadas. Espera alguns minutos e tenta de novo.');
        redirect_to('login.php');
    }
}

function clear_login_attempts(): void
{
    unset($_SESSION['login_attempts']);
}

// Ações principais de autenticação: entrar, registar, sair e expirar sessão.
function login_user(PDO $pdo, string $email, string $password): bool
{
    $user = db_fetch_one($pdo, 'SELECT id, name, email, password_hash, role, created_at FROM users WHERE email = ? LIMIT 1', [$email]);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        record_login_attempt();

        return false;
    }

    clear_login_attempts();
    session_regenerate_id(true);
    unset($user['password_hash']);
    $_SESSION['user'] = $user;
    $_SESSION['last_activity_at'] = time();

    return true;
}

function create_account(PDO $pdo, string $name, string $email, string $password): bool
{
    return db_execute(
        $pdo,
        'INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)',
        [$name, $email, password_hash($password, PASSWORD_DEFAULT), 'aluno']
    );
}

function logout_current_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
    session_start();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function enforce_session_timeout(): void
{
    if (!is_logged_in()) {
        return;
    }

    $now = time();
    $lastActivity = (int) ($_SESSION['last_activity_at'] ?? $now);

    if (($now - $lastActivity) >= SESSION_TIMEOUT_SECONDS) {
        logout_current_user();
        set_flash('error', 'A sessão expirou por inatividade. Inicia sessão novamente.');
        redirect_to('login.php');
    }

    $_SESSION['last_activity_at'] = $now;
}

// Gestão de upload da fotografia do perfil com validações básicas.
function save_uploaded_profile_photo(array $file): array
{
    $errorCode = $file['error'] ?? UPLOAD_ERR_NO_FILE;

    if ($errorCode === UPLOAD_ERR_NO_FILE) {
        return ['path' => null, 'error' => null];
    }

    if ($errorCode !== UPLOAD_ERR_OK) {
        return ['path' => null, 'error' => 'Não foi possível carregar a fotografia.'];
    }

    if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
        return ['path' => null, 'error' => 'A fotografia deve ter no máximo 2MB.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    $extensions = [
        'image/jpeg' => '.jpg',
        'image/png' => '.png',
    ];

    if (!isset($extensions[$mime])) {
        return ['path' => null, 'error' => 'Só são aceites fotografias JPG ou PNG.'];
    }

    $directory = __DIR__ . DIRECTORY_SEPARATOR . 'statics' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'profiles';

    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        return ['path' => null, 'error' => 'Não foi possível preparar a pasta de fotografias.'];
    }

    $filename = uniqid('profile_', true) . $extensions[$mime];
    $destination = $directory . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['path' => null, 'error' => 'Não foi possível guardar a fotografia.'];
    }

    return ['path' => 'statics/uploads/profiles/' . $filename, 'error' => null];
}

// Remove ficheiros previamente guardados quando deixam de ser necessários.
function delete_uploaded_file(?string $path): void
{
    if (!$path) {
        return;
    }

    $fullPath = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}

// Arranque automático das rotinas essenciais sempre que este ficheiro é carregado.
auth_bootstrap($pdo);
enforce_session_timeout();
