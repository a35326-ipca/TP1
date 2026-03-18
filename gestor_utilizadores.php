<?php
require_once 'app_ui.php';

require_gestor();

$navItems = [
    app_nav_item('hub_gestor.php', 'Hub', 'home'),
    app_nav_item('perfil.php', 'Perfil', 'account'),
    app_nav_item('gestor_utilizadores.php', 'Utilizadores', 'users'),
    app_nav_item('gestor_cursos.php', 'Cursos', 'courses'),
    app_nav_item('gestor_ucs.php', 'UCs', 'units'),
    app_nav_item('gestor_plano.php', 'Plano', 'plan'),
    app_nav_item('gestor_fichas.php', 'Fichas', 'enrollment'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf('gestor_utilizadores.php');

    $action = $_POST['action'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $email = normalize_email($_POST['email'] ?? '');
    $role = $_POST['role'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($action === 'create_user') {
        if ($name === '' || $email === '' || $password === '' || !in_array($role, ['aluno', 'funcionario', 'gestor'], true)) {
            set_flash('error', 'Preenche nome, e-mail, cargo e palavra-passe.');
            redirect_to('gestor_utilizadores.php');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_flash('error', 'O e-mail do utilizador não é válido.');
            redirect_to('gestor_utilizadores.php');
        }

        if (db_fetch_one($pdo, 'SELECT id FROM users WHERE email = ? LIMIT 1', [$email])) {
            set_flash('error', 'Já existe um utilizador com esse e-mail.');
            redirect_to('gestor_utilizadores.php');
        }

        db_execute(
            $pdo,
            'INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)',
            [$name, $email, password_hash($password, PASSWORD_DEFAULT), $role]
        );

        set_flash('success', 'Utilizador criado com sucesso.');
        redirect_to('gestor_utilizadores.php');
    }

    if ($action === 'update_user') {
        $id = (int) ($_POST['id'] ?? 0);
        $editRedirect = 'gestor_utilizadores.php?edit=' . $id;

        if ($id <= 0 || $name === '' || $email === '' || !in_array($role, ['aluno', 'funcionario', 'gestor'], true)) {
            set_flash('error', 'Dados inválidos para editar o utilizador.');
            redirect_to($editRedirect);
        }

        $currentUserData = db_fetch_one($pdo, 'SELECT name, email, role FROM users WHERE id = ? LIMIT 1', [$id]);

        if (!$currentUserData) {
            set_flash('error', 'O utilizador selecionado não existe.');
            redirect_to('gestor_utilizadores.php');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_flash('error', 'O e-mail do utilizador não é válido.');
            redirect_to($editRedirect);
        }

        $currentName = trim((string) ($currentUserData['name'] ?? ''));
        $currentEmail = normalize_email((string) ($currentUserData['email'] ?? ''));
        $currentRole = (string) ($currentUserData['role'] ?? '');

        if ($name === $currentName && $email === $currentEmail && $role === $currentRole && $password === '') {
            set_flash('error', 'Não existem alterações para guardar.');
            redirect_to($editRedirect);
        }

        if (db_fetch_one($pdo, 'SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1', [$email, $id])) {
            set_flash('error', 'Já existe outro utilizador com esse e-mail.');
            redirect_to($editRedirect);
        }

        if ($password !== '' && strlen($password) < 8) {
            set_flash('error', 'A nova palavra-passe deve ter pelo menos 8 caracteres.');
            redirect_to($editRedirect);
        }

        if ($password !== '') {
            db_execute(
                $pdo,
                'UPDATE users SET name = ?, email = ?, role = ?, password_hash = ? WHERE id = ?',
                [$name, $email, $role, password_hash($password, PASSWORD_DEFAULT), $id]
            );
        } else {
            db_execute(
                $pdo,
                'UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?',
                [$name, $email, $role, $id]
            );
        }

        if ((int) current_user()['id'] === $id) {
            refresh_session_user($pdo, $id);
        }

        set_flash('success', 'Utilizador atualizado com sucesso.');
        redirect_to('gestor_utilizadores.php');
    }
}

if (isset($_GET['delete'])) {
    verify_csrf_value($_GET['csrf_token'] ?? null, 'gestor_utilizadores.php');
    $id = (int) $_GET['delete'];

    if ($id === (int) current_user()['id']) {
        set_flash('error', 'Não podes remover a tua própria conta.');
        redirect_to('gestor_utilizadores.php');
    }

    db_execute($pdo, 'DELETE FROM users WHERE id = ?', [$id]);
    set_flash('success', 'Utilizador removido com sucesso.');
    redirect_to('gestor_utilizadores.php');
}

$editingUser = isset($_GET['edit'])
    ? db_fetch_one($pdo, 'SELECT id, name, email, role FROM users WHERE id = ? LIMIT 1', [(int) $_GET['edit']])
    : null;
$deleteCandidate = isset($_GET['confirm_delete'])
    ? db_fetch_one($pdo, 'SELECT id, name, email, role FROM users WHERE id = ? LIMIT 1', [(int) $_GET['confirm_delete']])
    : null;
$users = db_fetch_all($pdo, "SELECT id, name, email, role, created_at FROM users ORDER BY FIELD(role, 'gestor', 'funcionario', 'aluno'), name");

render_app_page_start(
    'Gc',
    'Bem-vindo à Gestão de Utilizadores',
    'Esta página permite gerir os acessos ao sistema, bem como organizar os cargos e os dados base das contas existentes. O seu objetivo é garantir que cada utilizador possui as permissões adequadas e que as informações associadas às contas se mantêm organizadas e atualizadas dentro da plataforma.',
    $navItems,
    'gestor_utilizadores.php'
);
?>
<section class="app-panel profile-panel">
    <div class="app-panel__header">
        <div>
            <h2>Criar utilizador</h2>
            <p>Esta secção permite adicionar novas contas ao sistema, sejam elas de aluno, funcionário ou gestor. Ao criar uma conta, são definidos os dados básicos do utilizador e o respetivo cargo, garantindo que cada pessoa tem acesso às funcionalidades adequadas dentro da plataforma.</p>
        </div>
    </div>

    <form method="post" class="app-form app-form--grid profile-form" novalidate>
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="create_user">

        <div class="app-field">
            <label for="name">Nome</label>
            <input id="name" type="text" name="name" value="" required>
        </div>
        <div class="app-field">
            <label for="email">E-mail</label>
            <input id="email" type="email" name="email" value="" required>
        </div>
        <div class="app-field">
            <label for="role">Cargo</label>
            <select id="role" name="role">
                <?php foreach (['aluno', 'funcionario', 'gestor'] as $role): ?>
                    <option value="<?= h($role) ?>" <?= $role === 'aluno' ? 'selected' : '' ?>><?= h(role_label($role)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="app-field">
            <label for="password">Palavra-passe</label>
            <div class="password-field">
                <input id="password" type="password" name="password" value="">
                <button class="toggle-password" type="button" data-toggle-password="password" aria-label="Mostrar palavra-passe">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                    </svg>
                </button>
            </div>
        </div>

        <div class="app-form__actions profile-form__actions">
            <button type="submit" class="app-button app-button--primary">Criar utilizador</button>
        </div>
    </form>
</section>

<section class="app-panel">
    <div class="app-panel__header">
        <div>
            <h2>Gestão de utilizadores</h2>
            <p>Secção que apresenta a lista completa de utilizadores do sistema, juntamente com o respetivo cargo atual. Através desta área é possível consultar as contas existentes e aceder às opções de gestão, como editar dados, alterar cargos ou remover utilizadores quando necessário.</p>
        </div>
    </div>

    <div class="app-table-wrap">
        <table class="app-table">
            <colgroup>
                <col class="app-table__col-name">
                <col class="app-table__col-email">
                <col class="app-table__col-role">
                <col class="app-table__col-date">
                <col class="app-table__col-actions">
            </colgroup>
            <thead>
                <tr>
                    <th class="app-table__name-col">Nome</th>
                    <th class="app-table__email-col">E-mail</th>
                    <th class="app-table__role-col">Cargo</th>
                    <th class="app-table__date-col">Registado</th>
                    <th class="app-table__actions-col">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $userRow): ?>
                    <tr>
                        <td class="app-table__name-col"><?= h($userRow['name']) ?></td>
                        <td class="app-table__email-col"><?= h($userRow['email']) ?></td>
                        <td class="app-table__role-col"><?= status_badge(role_label($userRow['role'])) ?></td>
                        <td class="app-table__date-col"><?= h(date('Y-m-d', strtotime((string) $userRow['created_at']))) ?></td>
                        <td class="app-table__actions-col">
                            <div class="table-actions">
                                <a href="gestor_utilizadores.php?edit=<?= (int) $userRow['id'] ?>" title="Editar">
                                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                                    </svg>
                                </a>
                                <?php if ((int) $userRow['id'] !== (int) current_user()['id']): ?>
                                    <a href="gestor_utilizadores.php?confirm_delete=<?= (int) $userRow['id'] ?>" class="danger" title="Remover">
                                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                        </svg>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($editingUser): ?>
    <div class="app-modal is-open" id="edit-user-modal">
        <a href="gestor_utilizadores.php" class="app-modal__backdrop" aria-label="Fechar edição do utilizador"></a>

        <section class="app-modal__dialog app-panel profile-panel" role="dialog" aria-modal="true" aria-labelledby="edit-user-title">
            <div class="app-modal__header">
                <div>
                    <h2 id="edit-user-title">Editar utilizador</h2>
                    <p>Nesta parte, podes atualizar os dados da conta selecionada e ajustar o cargo ou o nível de acesso do utilizador, mantendo as informações e as permissões corretas no sistema.</p>
                </div>
                <a href="gestor_utilizadores.php" class="app-modal__close" aria-label="Fechar modal">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </a>
            </div>

            <form method="post" class="app-form app-form--grid profile-form" novalidate>
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="update_user">
                <input type="hidden" name="id" value="<?= (int) $editingUser['id'] ?>">

                <div class="app-field">
                    <label for="edit-name">Nome</label>
                    <input id="edit-name" type="text" name="name" value="<?= h($editingUser['name']) ?>" required>
                </div>
                <div class="app-field">
                    <label for="edit-email">E-mail</label>
                    <input id="edit-email" type="email" name="email" value="<?= h($editingUser['email']) ?>" required>
                </div>
                <div class="app-field">
                    <label for="edit-role">Cargo</label>
                    <select id="edit-role" name="role">
                        <?php foreach (['aluno', 'funcionario', 'gestor'] as $role): ?>
                            <option value="<?= h($role) ?>" <?= $editingUser['role'] === $role ? 'selected' : '' ?>><?= h(role_label($role)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="app-field">
                    <label for="edit-password">Nova palavra-passe (opcional)</label>
                    <div class="password-field">
                        <input id="edit-password" type="password" name="password" value="">
                        <button class="toggle-password" type="button" data-toggle-password="edit-password" aria-label="Mostrar palavra-passe">
                            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="app-form__actions profile-form__actions">
                    <button type="submit" class="app-button app-button--primary">Guardar utilizador</button>
                </div>
            </form>
        </section>
    </div>
<?php endif; ?>

<?php if ($deleteCandidate && (int) $deleteCandidate['id'] !== (int) current_user()['id']): ?>
    <div class="app-modal is-open" id="delete-user-modal">
        <a href="gestor_utilizadores.php" class="app-modal__backdrop" aria-label="Fechar confirmação de remoção"></a>

        <section class="app-modal__dialog app-panel profile-panel app-modal__dialog--compact" role="dialog" aria-modal="true" aria-labelledby="delete-user-title">
            <div class="app-modal__header">
                <div>
                    <h2 id="delete-user-title">Remover utilizador</h2>
                    <p>Vais remover a conta de <strong><?= h($deleteCandidate['name']) ?></strong>. Esta ação elimina o acesso deste utilizador ao sistema.</p>
                </div>
                <a href="gestor_utilizadores.php" class="app-modal__close" aria-label="Fechar modal">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </a>
            </div>

            <div class="app-modal__content">
                <p class="helper-text">E-mail: <strong><?= h($deleteCandidate['email']) ?></strong></p>
                <p class="helper-text">Cargo: <strong><?= h(role_label($deleteCandidate['role'])) ?></strong></p>
            </div>

            <div class="app-form__actions app-modal__actions app-modal__actions--single">
                <a href="gestor_utilizadores.php?delete=<?= (int) $deleteCandidate['id'] ?>&<?= h(csrf_query()) ?>" class="app-button app-button--danger">Confirmar eliminação</a>
            </div>
        </section>
    </div>
<?php endif; ?>

<?php if ($editingUser || $deleteCandidate): ?>
    <script>
        document.body.classList.add('app-modal-open');

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                window.location.href = 'gestor_utilizadores.php';
            }
        });
    </script>
<?php endif; ?>
<script>
    const eyeIcon = `
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
        </svg>`;
    const eyeOffIcon = `
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
        </svg>`;

    document.querySelectorAll('[data-toggle-password]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.dataset.togglePassword);
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            button.innerHTML = isPassword ? eyeIcon : eyeOffIcon;
            button.setAttribute('aria-label', isPassword ? 'Ocultar palavra-passe' : 'Mostrar palavra-passe');
        });
    });
</script>
<?php
render_app_page_end();
