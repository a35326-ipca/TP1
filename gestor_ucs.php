<?php
// Página do gestor para criação, edição, listagem e remoção de Unidades Curriculares.

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

// Processa a criação e atualização das UCs.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf('gestor_ucs.php');

    $action = $_POST['action'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    $editRedirect = 'gestor_ucs.php?edit=' . $id;

    // Valida o nome base antes de criar ou atualizar a UC.
    if ($name === '') {
        set_flash('error', 'Indica o nome da UC.');
        redirect_to($action === 'update_unit' && $id > 0 ? $editRedirect : 'gestor_ucs.php');
    }

    // Atualiza uma UC já existente.
    if ($action === 'update_unit') {
        $currentUnit = db_fetch_one($pdo, 'SELECT id, name FROM units WHERE id = ? LIMIT 1', [$id]);

        if (!$currentUnit) {
            set_flash('error', 'A UC selecionada não existe.');
            redirect_to('gestor_ucs.php');
        }

        if ($name === trim((string) $currentUnit['name'])) {
            set_flash('error', 'Não existem alterações para guardar.');
            redirect_to($editRedirect);
        }

        if (db_fetch_one($pdo, 'SELECT id FROM units WHERE name = ? AND id <> ? LIMIT 1', [$name, $id])) {
            set_flash('error', 'Já existe outra UC com esse nome.');
            redirect_to($editRedirect);
        }

        db_execute($pdo, 'UPDATE units SET name = ? WHERE id = ?', [$name, $id]);
        set_flash('success', 'UC atualizada com sucesso.');
        redirect_to('gestor_ucs.php');
    }

    // Cria uma nova UC na base de dados.
    if ($action === 'create_unit') {
        if (db_fetch_one($pdo, 'SELECT id FROM units WHERE name = ? LIMIT 1', [$name])) {
            set_flash('error', 'Já existe uma UC com esse nome.');
            redirect_to('gestor_ucs.php');
        }

        db_execute($pdo, 'INSERT INTO units (name) VALUES (?)', [$name]);
        set_flash('success', 'UC criada com sucesso.');
        redirect_to('gestor_ucs.php');
    }
}

// Processa a remoção direta de uma UC.
if (isset($_GET['delete'])) {
    verify_csrf_value($_GET['csrf_token'] ?? null, 'gestor_ucs.php');
    $id = (int) $_GET['delete'];
    db_execute($pdo, 'DELETE FROM units WHERE id = ?', [$id]);
    set_flash('success', 'UC removida com sucesso.');
    redirect_to('gestor_ucs.php');
}

// Carrega a UC em edição, o candidato a remoção e a lista completa de UCs.
$editingUnit = isset($_GET['edit'])
    ? db_fetch_one($pdo, 'SELECT id, name FROM units WHERE id = ? LIMIT 1', [(int) $_GET['edit']])
    : null;
$deleteCandidate = isset($_GET['confirm_delete'])
    ? db_fetch_one($pdo, 'SELECT id, name FROM units WHERE id = ? LIMIT 1', [(int) $_GET['confirm_delete']])
    : null;
$units = db_fetch_all($pdo, 'SELECT id, name, created_at, updated_at FROM units ORDER BY name');

// Renderiza o cabeçalho comum da página.
render_app_page_start(
    'Gc',
    'Bem-vindo à Gestão de Unidades Curriculares',
    'Esta página permite gerir a lista de Unidades Curriculares (UCs) utilizadas no sistema. O seu objetivo é garantir que as UCs se mantêm organizadas e consistentes, de forma a serem corretamente utilizadas nos planos de estudo e nas pautas de avaliação.',
    $navItems,
    'gestor_ucs.php'
);
?>
<section class="app-panel profile-panel">
    <!-- Formulário para criação de novas Unidades Curriculares. -->
    <div class="app-panel__header">
        <div>
            <h2>Criar UC</h2>
            <p>Nesta secção é possível criar novas Unidades Curriculares (UCs), preenchendo os campos disponíveis abaixo com as informações necessárias. As UCs criadas serão posteriormente reutilizadas no plano de estudos dos cursos e também nas pautas de avaliação, garantindo consistência na organização académica do sistema.</p>
        </div>
    </div>

    <form method="post" class="app-form app-form--grid profile-form" novalidate>
        <!-- Token CSRF e ação de criação. -->
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="create_unit">

        <div class="app-field profile-form__actions">
            <label for="name">Nome da UC</label>
            <input id="name" type="text" name="name" value="" required>
        </div>

        <div class="app-form__actions profile-form__actions">
            <button type="submit" class="app-button app-button--primary">Criar UC</button>
        </div>
    </form>
</section>

<section class="app-panel">
    <!-- Tabela principal de consulta e gestão das UCs existentes. -->
    <div class="app-panel__header">
        <div>
            <h2>Gestão de UCs</h2>
            <p>Secção que apresenta a base de Unidades Curriculares (UCs) utilizadas pelos cursos, pelo plano de estudos e pelas pautas de avaliação do sistema, permitindo consultar e gerir as UCs registadas.</p>
        </div>
    </div>

    <div class="app-table-wrap">
        <table class="app-table">
            <colgroup>
                <col class="app-table__uc-name-col">
                <col class="app-table__uc-created-col">
                <col class="app-table__uc-updated-col">
                <col class="app-table__uc-actions-col">
            </colgroup>
            <thead>
                <tr>
                    <th class="app-table__uc-name-col">Nome</th>
                    <th class="app-table__uc-created-col">Criada</th>
                    <th class="app-table__uc-updated-col">Atualizada</th>
                    <th class="app-table__uc-actions-col">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($units as $unit): ?>
                    <tr>
                        <td class="app-table__uc-name-col"><?= h($unit['name']) ?></td>
                        <td class="app-table__uc-created-col"><?= h(date('Y-m-d', strtotime((string) $unit['created_at']))) ?></td>
                        <td class="app-table__uc-updated-col"><?= h(date('Y-m-d', strtotime((string) $unit['updated_at']))) ?></td>
                        <td class="app-table__uc-actions-col">
                            <div class="table-actions">
                                <a href="gestor_ucs.php?edit=<?= (int) $unit['id'] ?>" title="Editar">
                                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                                    </svg>
                                </a>
                                <a href="gestor_ucs.php?confirm_delete=<?= (int) $unit['id'] ?>" class="danger" title="Remover">
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

<?php if ($editingUnit): ?>
    <!-- Modal de edição de uma UC existente. -->
    <div class="app-modal is-open" id="edit-unit-modal">
        <a href="gestor_ucs.php" class="app-modal__backdrop" aria-label="Fechar edição da UC"></a>

        <section class="app-modal__dialog app-panel profile-panel" role="dialog" aria-modal="true" aria-labelledby="edit-unit-title">
            <div class="app-modal__header">
                <div>
                    <h2 id="edit-unit-title">Editar UC</h2>
                    <p>Aqui podes atualizar o nome da Unidade Curricular selecionada, mantendo a base académica organizada e consistente.</p>
                </div>
                <a href="gestor_ucs.php" class="app-modal__close" aria-label="Fechar modal">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </a>
            </div>

            <form method="post" class="app-form app-form--grid profile-form" novalidate>
                <!-- Token CSRF e identificação da UC a atualizar. -->
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="update_unit">
                <input type="hidden" name="id" value="<?= (int) $editingUnit['id'] ?>">

                <div class="app-field profile-form__actions">
                    <label for="edit-name">Nome da UC</label>
                    <input id="edit-name" type="text" name="name" value="<?= h($editingUnit['name']) ?>" required>
                </div>

                <div class="app-form__actions profile-form__actions">
                    <button type="submit" class="app-button app-button--primary">Guardar UC</button>
                </div>
            </form>
        </section>
    </div>
<?php endif; ?>

<?php if ($deleteCandidate): ?>
    <!-- Modal de confirmação para remoção de uma UC. -->
    <div class="app-modal is-open" id="delete-unit-modal">
        <a href="gestor_ucs.php" class="app-modal__backdrop" aria-label="Fechar confirmação de remoção"></a>

        <section class="app-modal__dialog app-panel profile-panel app-modal__dialog--compact" role="dialog" aria-modal="true" aria-labelledby="delete-unit-title">
            <div class="app-modal__header">
                <div>
                    <h2 id="delete-unit-title">Remover UC</h2>
                    <p>Vais remover a Unidade Curricular <strong><?= h($deleteCandidate['name']) ?></strong>. Esta ação apaga o registo da UC do sistema.</p>
                </div>
                <a href="gestor_ucs.php" class="app-modal__close" aria-label="Fechar modal">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </a>
            </div>

            <div class="app-form__actions app-modal__actions app-modal__actions--single">
                <a href="gestor_ucs.php?delete=<?= (int) $deleteCandidate['id'] ?>&<?= h(csrf_query()) ?>" class="app-button app-button--danger">Confirmar eliminação</a>
            </div>
        </section>
    </div>
<?php endif; ?>

<?php if ($editingUnit || $deleteCandidate): ?>
    <script>
        // Garante o estado visual de modal aberto e fecha o modal com Escape.
        document.body.classList.add('app-modal-open');

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                window.location.href = 'gestor_ucs.php';
            }
        });
    </script>
<?php endif; ?>
<?php
// Fecha a estrutura visual comum aberta no início da página.
render_app_page_end();
