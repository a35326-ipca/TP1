<?php
// Página de perfil comum a todos os tipos de utilizador autenticado.

require_once __DIR__ . '/app_ui.php';

// Garante que apenas utilizadores autenticados podem aceder a esta área.
require_login();

// Devolve a navegação adequada ao papel atual do utilizador.
function current_nav_items(): array
{
    $role = current_user_role();

    if ($role === 'gestor') {
        return [
            app_nav_item('hub_gestor.php', 'Hub', 'home'),
            app_nav_item('perfil.php', 'Perfil', 'account'),
            app_nav_item('gestor_utilizadores.php', 'Utilizadores', 'users'),
            app_nav_item('gestor_cursos.php', 'Cursos', 'courses'),
            app_nav_item('gestor_ucs.php', 'UCs', 'units'),
            app_nav_item('gestor_plano.php', 'Plano', 'plan'),
            app_nav_item('gestor_fichas.php', 'Fichas', 'profile'),
        ];
    }

    if ($role === 'funcionario') {
        return [
            app_nav_item('hub_funcionario.php', 'Hub', 'home'),
            app_nav_item('perfil.php', 'Perfil', 'account'),
            app_nav_item('funcionario_pedidos.php', 'Matrículas', 'enrollment'),
            app_nav_item('funcionario_pautas.php', 'Pautas', 'grades'),
        ];
    }

    return build_student_nav_items($GLOBALS['pdo'], (int) current_user()['id']);
}

// Processa a atualização dos dados da conta.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf('perfil.php');

    // Recolhe e normaliza os dados submetidos pelo formulário.
    $currentUser = current_user();
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    $currentName = (string) ($currentUser['name'] ?? '');
    $currentEmail = (string) ($currentUser['email'] ?? '');

    $sameName = $name === $currentName;
    $sameEmail = $email === $currentEmail;
    $passwordBlank = $password === '' && $confirmPassword === '';

    // Impede submissões sem alterações reais.
    if ($sameName && $sameEmail && $passwordBlank) {
        set_flash('error', 'Não existem alterações para guardar.');
        redirect_to('perfil.php');
    }

    // Valida os campos obrigatórios da conta.
    if ($name === '' || $email === '') {
        set_flash('error', 'Preenche nome e e-mail.');
        redirect_to('perfil.php');
    }

    if (mb_strlen($name) < 3 || mb_strlen($name) > 120) {
        set_flash('error', 'O nome deve ter entre 3 e 120 caracteres.');
        redirect_to('perfil.php');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        set_flash('error', 'O e-mail introduzido não é válido.');
        redirect_to('perfil.php');
    }

    if (db_fetch_one($pdo, 'SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1', [$email, $currentUser['id']])) {
        set_flash('error', 'Já existe outra conta com esse e-mail.');
        redirect_to('perfil.php');
    }

    // Prepara a alteração da palavra-passe apenas quando o utilizador a pretende mudar.
    $newPasswordHash = null;
    if ($password !== '' || $confirmPassword !== '') {
        if ($password === '' || $confirmPassword === '') {
            set_flash('error', 'Preenche os dois campos da palavra-passe.');
            redirect_to('perfil.php');
        }

        if (mb_strlen($password) < 8) {
            set_flash('error', 'A palavra-passe deve ter pelo menos 8 caracteres.');
            redirect_to('perfil.php');
        }

        if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/\d/', $password)) {
            set_flash('error', 'Usa uma palavra-passe com maiúsculas, minúsculas e números.');
            redirect_to('perfil.php');
        }

        if ($password !== $confirmPassword) {
            set_flash('error', 'As palavras-passe não coincidem.');
            redirect_to('perfil.php');
        }

        $newPasswordHash = password_hash($password, PASSWORD_DEFAULT);
    }

    // Atualiza a conta, com ou sem alteração da palavra-passe.
    if ($newPasswordHash !== null) {
        db_execute(
            $pdo,
            'UPDATE users SET name = ?, email = ?, password_hash = ? WHERE id = ?',
            [$name, $email, $newPasswordHash, $currentUser['id']]
        );
    } else {
        db_execute(
            $pdo,
            'UPDATE users SET name = ?, email = ? WHERE id = ?',
            [$name, $email, $currentUser['id']]
        );
    }

    // Recarrega os dados da sessão após a atualização do perfil.
    refresh_session_user($pdo, (int) $currentUser['id']);
    set_flash('success', 'Perfil atualizado com sucesso.');
    redirect_to('perfil.php');
}

// Carrega os dados da conta e elementos auxiliares para a interface.
$user = current_user();
$navItems = current_nav_items();
$pageTitle = current_user_role() === 'gestor'
    ? 'Perfil do Gestor'
    : (current_user_role() === 'funcionario' ? 'Perfil do Funcionário' : 'Perfil do Aluno');
$roleLabel = current_user_role() === 'gestor'
    ? 'Gestor'
    : (current_user_role() === 'funcionario' ? 'Funcionário' : 'Aluno');

// Renderiza o cabeçalho comum da página.
render_app_page_start(
    'Perfil',
    'Bem-vindo ao Perfil',
    'Área destinada à atualização dos dados da conta de utilizador. Nesta secção é possível alterar informações de acesso e manter os dados pessoais atualizados, garantindo que a conta permanece correta, segura e devidamente configurada para a utilização da plataforma.',
    $navItems,
    'perfil.php'
);
?>

<?php
// Apresenta métricas rápidas sobre a conta autenticada.
render_metric_cards([
    [
        'label' => 'Cargo',
        'value' => $roleLabel,
    ],
    [
        'label' => 'Conta criada em',
        'value' => date('Y-m-d', strtotime((string) ($user['created_at'] ?? 'now'))),
    ],
]);
?>

<section class="app-panel">
    <!-- Formulário principal de atualização do perfil. -->
    <h2>Atualizar dados</h2>
    <p>Nesta secção é possível atualizar informações pessoais da conta, como o nome e o endereço de e-mail, bem como definir uma nova palavra-passe, caso seja necessário, garantindo que os dados de acesso permanecem atualizados e seguros.</p>

    <form method="post" class="app-form app-form--grid profile-form" novalidate>
        <!-- Token CSRF para proteção da submissão. -->
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

        <div class="app-field">
            <label for="name">Nome</label>
            <input id="name" type="text" name="name" value="<?= h($user['name'] ?? '') ?>" maxlength="120" required>
        </div>

        <div class="app-field">
            <label for="email">E-mail</label>
            <input id="email" type="email" name="email" value="<?= h($user['email'] ?? '') ?>" maxlength="160" required>
        </div>

        <div class="app-field">
            <label for="password">Nova palavra-passe</label>
            <div class="password-field">
                <input id="password" type="password" name="password" autocomplete="new-password">
                <button class="toggle-password" type="button" data-toggle-password="password" aria-label="Mostrar palavra-passe">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                    </svg>
                </button>
            </div>
        </div>

        <div class="app-field">
            <label for="confirm_password">Confirmar nova palavra-passe</label>
            <div class="password-field">
                <input id="confirm_password" type="password" name="confirm_password" autocomplete="new-password">
                <button class="toggle-password" type="button" data-toggle-password="confirm_password" aria-label="Mostrar palavra-passe">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                    </svg>
                </button>
            </div>
        </div>

        <div class="app-form__actions profile-form__actions">
            <button type="submit" class="app-button app-button--primary">Guardar alterações</button>
        </div>
    </form>
</section>

<script>
    // Ícones usados para alternar a visibilidade dos campos de palavra-passe.
    const eyeIcon = `
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
        </svg>`;

    const eyeOffIcon = `
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
        </svg>`;

    // Liga os botões de mostrar/ocultar palavra-passe aos respetivos campos.
    document.querySelectorAll('[data-toggle-password]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.dataset.togglePassword);
            if (!input) {
                return;
            }

            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            button.innerHTML = isPassword ? eyeIcon : eyeOffIcon;
            button.setAttribute('aria-label', isPassword ? 'Ocultar palavra-passe' : 'Mostrar palavra-passe');
        });
    });
</script>

<?php
// Fecha a estrutura visual comum aberta no início da página.
render_app_page_end();
?>
