<?php
// Página do gestor para criação, edição, listagem e remoção de cursos.

require_once 'app_ui.php';

// Garante que apenas gestores autenticados podem aceder a esta área.
require_gestor();

// Navegação base da área de gestão.
$navItems = [
    app_nav_item('hub_gestor.php', 'Hub', 'home'),
    app_nav_item('perfil.php', 'Perfil', 'account'),
    app_nav_item('gestor_utilizadores.php', 'Utilizadores', 'users'),
    app_nav_item('gestor_cursos.php', 'Cursos', 'courses'),
    app_nav_item('gestor_ucs.php', 'UCs', 'units'),
    app_nav_item('gestor_plano.php', 'Plano', 'plan'),
    app_nav_item('gestor_fichas.php', 'Fichas', 'enrollment'),
];

// Processa operações de criação e atualização de cursos.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf('gestor_cursos.php');

    $action = $_POST['action'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $isActive = (int) ($_POST['is_active'] ?? 1) === 1 ? 1 : 0;

    // Cria um novo curso com o estado pretendido.
    if ($action === 'create_course') {
        if ($name === '') {
            set_flash('error', 'Indica o nome do curso.');
            redirect_to('gestor_cursos.php');
        }

        if (db_fetch_one($pdo, 'SELECT id FROM courses WHERE name = ? LIMIT 1', [$name])) {
            set_flash('error', 'Já existe um curso com esse nome.');
            redirect_to('gestor_cursos.php');
        }

        db_execute($pdo, 'INSERT INTO courses (name, is_active) VALUES (?, ?)', [$name, $isActive]);
        set_flash('success', 'Curso criado com sucesso.');
        redirect_to('gestor_cursos.php');
    }

    // Atualiza os dados de um curso existente.
    if ($action === 'update_course') {
        $id = (int) ($_POST['id'] ?? 0);
        $editRedirect = 'gestor_cursos.php?edit=' . $id;

        if ($id <= 0 || $name === '') {
            set_flash('error', 'Indica um nome válido para o curso.');
            redirect_to($editRedirect);
        }

        $currentCourse = db_fetch_one($pdo, 'SELECT id, name, is_active FROM courses WHERE id = ? LIMIT 1', [$id]);

        if (!$currentCourse) {
            set_flash('error', 'O curso selecionado não existe.');
            redirect_to('gestor_cursos.php');
        }

        if ($name === trim((string) $currentCourse['name']) && $isActive === (int) $currentCourse['is_active']) {
            set_flash('error', 'Não existem alterações para guardar.');
            redirect_to($editRedirect);
        }

        if (db_fetch_one($pdo, 'SELECT id FROM courses WHERE name = ? AND id <> ? LIMIT 1', [$name, $id])) {
            set_flash('error', 'Já existe outro curso com esse nome.');
            redirect_to($editRedirect);
        }

        db_execute($pdo, 'UPDATE courses SET name = ?, is_active = ? WHERE id = ?', [$name, $isActive, $id]);
        set_flash('success', 'Curso atualizado com sucesso.');
        redirect_to('gestor_cursos.php');
    }
}

// Processa a eliminação de um curso, respeitando dependências existentes.
if (isset($_GET['delete'])) {
    verify_csrf_value($_GET['csrf_token'] ?? null, 'gestor_cursos.php');
    $id = (int) $_GET['delete'];

    $hasPlanEntries = db_fetch_one($pdo, 'SELECT id FROM study_plan WHERE course_id = ? LIMIT 1', [$id]);
    $hasProfiles = db_fetch_one($pdo, 'SELECT id FROM student_profiles WHERE course_id = ? LIMIT 1', [$id]);
    $hasEnrollments = db_fetch_one($pdo, 'SELECT id FROM enrollment_requests WHERE course_id = ? LIMIT 1', [$id]);

    if ($hasPlanEntries || $hasProfiles || $hasEnrollments) {
        set_flash('error', 'Não é possível apagar este curso porque já está a ser usado no sistema.');
        redirect_to('gestor_cursos.php');
    }

    db_execute($pdo, 'DELETE FROM courses WHERE id = ?', [$id]);
    set_flash('success', 'Curso apagado com sucesso.');
    redirect_to('gestor_cursos.php');
}

// Carrega o curso em edição, o candidato a eliminação e a lista completa de cursos.
$editingCourse = isset($_GET['edit'])
    ? db_fetch_one($pdo, 'SELECT id, name, is_active FROM courses WHERE id = ? LIMIT 1', [(int) $_GET['edit']])
    : null;
$deleteCandidate = isset($_GET['confirm_delete'])
    ? db_fetch_one($pdo, 'SELECT id, name, is_active FROM courses WHERE id = ? LIMIT 1', [(int) $_GET['confirm_delete']])
    : null;
$courses = db_fetch_all($pdo, 'SELECT id, name, is_active, created_at, updated_at FROM courses ORDER BY is_active DESC, name');

// Renderiza o cabeçalho comum da página.
render_app_page_start(
    'Gc',
    'Bem-vindo à Gestão de Cursos',
    'Esta página permite gerir os cursos disponíveis no sistema, possibilitando a criação de novos cursos, a edição dos existentes e a desativação quando necessário. O seu objetivo é manter a oferta formativa organizada, garantindo que as alterações realizadas não afetam o histórico nem as relações já existentes dentro da plataforma.',
    $navItems,
    'gestor_cursos.php'
);
?>
<section class="app-panel profile-panel">
    <!-- Formulário para criação de novos cursos. -->
    <div class="app-panel__header">
        <div>
            <h2>Criar curso</h2>
            <p>Nesta secção é possível criar novos cursos, preenchendo os campos disponíveis abaixo com as informações necessárias. Após a criação, os cursos ficam disponíveis no sistema para utilização nas diferentes áreas da plataforma. Caso um curso seja marcado como inativo, deixará de aparecer nas áreas destinadas aos alunos.</p>
        </div>
    </div>

    <form method="post" class="app-form app-form--grid profile-form" novalidate>
        <!-- Token CSRF e ação de criação. -->
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="create_course">

        <div class="app-field">
            <label for="name">Nome do curso</label>
            <input id="name" type="text" name="name" value="" required>
        </div>
        <div class="app-field">
            <label for="is_active">Estado</label>
            <select id="is_active" name="is_active">
                <option value="1" selected>Ativo</option>
                <option value="0">Inativo</option>
            </select>
        </div>

        <div class="app-form__actions profile-form__actions">
            <button type="submit" class="app-button app-button--primary">Criar curso</button>
        </div>
    </form>
</section>

<section class="app-panel">
    <!-- Tabela principal de consulta e gestão dos cursos existentes. -->
    <div class="app-panel__header">
        <div>
            <h2>Gestão de cursos</h2>
            <p>Nesta secção é apresentada uma tabela com todos os cursos registados no sistema. A partir desta tabela, é possível gerir cada curso individualmente, permitindo consultar, editar ou atualizar as suas informações sempre que necessário.</p>
        </div>
    </div>

    <div class="app-table-wrap">
        <table class="app-table">
            <colgroup>
                <col class="app-table__course-name-col">
                <col class="app-table__course-state-col">
                <col class="app-table__course-created-col">
                <col class="app-table__course-updated-col">
                <col class="app-table__course-actions-col">
            </colgroup>
            <thead>
                <tr>
                    <th class="app-table__course-name-col">Nome</th>
                    <th class="app-table__course-state-col">Estado</th>
                    <th class="app-table__course-created-col">Criado</th>
                    <th class="app-table__course-updated-col">Atualizado</th>
                    <th class="app-table__course-actions-col">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($courses as $course): ?>
                    <tr>
                        <td class="app-table__course-name-col"><?= h($course['name']) ?></td>
                        <td class="app-table__course-state-col"><?= status_badge((int) $course['is_active'] === 1 ? 'ativo' : 'inativo') ?></td>
                        <td class="app-table__course-created-col"><?= h(date('Y-m-d', strtotime((string) $course['created_at']))) ?></td>
                        <td class="app-table__course-updated-col"><?= h(date('Y-m-d', strtotime((string) $course['updated_at']))) ?></td>
                        <td class="app-table__course-actions-col">
                            <div class="table-actions">
                                <a href="gestor_cursos.php?edit=<?= (int) $course['id'] ?>" title="Editar">
                                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                                    </svg>
                                </a>
                                <a href="gestor_cursos.php?confirm_delete=<?= (int) $course['id'] ?>" class="danger" title="Apagar">
                                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                    </svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($editingCourse): ?>
    <!-- Modal de edição de um curso existente. -->
    <div class="app-modal is-open" id="edit-course-modal">
        <a href="gestor_cursos.php" class="app-modal__backdrop" aria-label="Fechar edição do curso"></a>

        <section class="app-modal__dialog app-panel profile-panel" role="dialog" aria-modal="true" aria-labelledby="edit-course-title">
            <div class="app-modal__header">
                <div>
                    <h2 id="edit-course-title">Editar curso</h2>
                    <p>Aqui podes atualizar o nome e o estado do curso selecionado, mantendo a oferta formativa organizada e coerente no sistema.</p>
                </div>
                <a href="gestor_cursos.php" class="app-modal__close" aria-label="Fechar modal">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </a>
            </div>

            <form method="post" class="app-form app-form--grid profile-form" novalidate>
                <!-- Token CSRF e identificação do curso a atualizar. -->
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="update_course">
                <input type="hidden" name="id" value="<?= (int) $editingCourse['id'] ?>">

                <div class="app-field">
                    <label for="edit-name">Nome do curso</label>
                    <input id="edit-name" type="text" name="name" value="<?= h($editingCourse['name']) ?>" required>
                </div>
                <div class="app-field">
                    <label for="edit-is-active">Estado</label>
                    <select id="edit-is-active" name="is_active">
                        <option value="1" <?= ((int) $editingCourse['is_active'] === 1) ? 'selected' : '' ?>>Ativo</option>
                        <option value="0" <?= ((int) $editingCourse['is_active'] === 0) ? 'selected' : '' ?>>Inativo</option>
                    </select>
                </div>

                <div class="app-form__actions profile-form__actions">
                    <button type="submit" class="app-button app-button--primary">Guardar curso</button>
                </div>
            </form>
        </section>
    </div>
<?php endif; ?>

<?php if ($deleteCandidate): ?>
    <!-- Modal de confirmação para remoção de um curso. -->
    <div class="app-modal is-open" id="delete-course-modal">
        <a href="gestor_cursos.php" class="app-modal__backdrop" aria-label="Fechar confirmação de remoção"></a>

        <section class="app-modal__dialog app-panel profile-panel app-modal__dialog--compact" role="dialog" aria-modal="true" aria-labelledby="delete-course-title">
            <div class="app-modal__header">
                <div>
                    <h2 id="delete-course-title">Apagar curso</h2>
                    <p>Vais apagar o curso <strong><?= h($deleteCandidate['name']) ?></strong>. Esta ação remove o registo do curso do sistema.</p>
                </div>
                <a href="gestor_cursos.php" class="app-modal__close" aria-label="Fechar modal">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </a>
            </div>

            <div class="app-modal__content">
                <p class="helper-text">Estado atual: <strong><?= h((int) $deleteCandidate['is_active'] === 1 ? 'Ativo' : 'Inativo') ?></strong></p>
            </div>

            <div class="app-form__actions app-modal__actions app-modal__actions--single">
                <a href="gestor_cursos.php?delete=<?= (int) $deleteCandidate['id'] ?>&<?= h(csrf_query()) ?>" class="app-button app-button--danger">Confirmar eliminação</a>
            </div>
        </section>
    </div>
<?php endif; ?>

<?php if ($editingCourse || $deleteCandidate): ?>
    <script>
        // Garante o estado visual de modal aberto e fecha o modal com Escape.
        document.body.classList.add('app-modal-open');

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                window.location.href = 'gestor_cursos.php';
            }
        });
    </script>
<?php endif; ?>
<?php
// Fecha a estrutura visual comum aberta no início da página.
render_app_page_end();
