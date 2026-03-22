<?php
// Página do gestor para criação, edição, listagem e remoção de entradas do plano de estudos.

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

// Processa a criação e atualização de entradas do plano de estudos.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf('gestor_plano.php');

    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    $courseId = (int) ($_POST['course_id'] ?? 0);
    $unitId = (int) ($_POST['unit_id'] ?? 0);
    $yearNumber = (int) ($_POST['year_number'] ?? 0);
    $semester = (int) ($_POST['semester'] ?? 0);
    $editRedirect = 'gestor_plano.php?edit=' . $id;

    // Valida os campos principais antes de criar ou atualizar a entrada.
    if ($courseId <= 0 || $unitId <= 0 || $yearNumber < 1 || $yearNumber > 5 || !in_array($semester, [1, 2], true)) {
        set_flash('error', 'Seleciona curso, UC, ano curricular e semestre válidos.');
        redirect_to($action === 'update_plan' && $id > 0 ? $editRedirect : 'gestor_plano.php');
    }

    // Cria uma nova associação entre curso e UC.
    if ($action === 'create_plan') {
        $existing = db_fetch_one(
            $pdo,
            'SELECT id FROM study_plan WHERE course_id = ? AND unit_id = ? AND year_number = ? AND semester = ? LIMIT 1',
            [$courseId, $unitId, $yearNumber, $semester]
        );

        if ($existing) {
            set_flash('error', 'Esse vínculo já existe para o mesmo ano e semestre.');
            redirect_to('gestor_plano.php');
        }

        db_execute(
            $pdo,
            'INSERT INTO study_plan (course_id, unit_id, year_number, semester) VALUES (?, ?, ?, ?)',
            [$courseId, $unitId, $yearNumber, $semester]
        );

        set_flash('success', 'Entrada do plano criada com sucesso.');
        redirect_to('gestor_plano.php');
    }

    // Atualiza uma entrada existente do plano de estudos.
    if ($action === 'update_plan') {
        $currentEntry = db_fetch_one(
            $pdo,
            'SELECT id, course_id, unit_id, year_number, semester FROM study_plan WHERE id = ? LIMIT 1',
            [$id]
        );

        if (!$currentEntry) {
            set_flash('error', 'A entrada selecionada não existe.');
            redirect_to('gestor_plano.php');
        }

        if (
            $courseId === (int) $currentEntry['course_id'] &&
            $unitId === (int) $currentEntry['unit_id'] &&
            $yearNumber === (int) $currentEntry['year_number'] &&
            $semester === (int) $currentEntry['semester']
        ) {
            set_flash('error', 'Não existem alterações para guardar.');
            redirect_to($editRedirect);
        }

        $existing = db_fetch_one(
            $pdo,
            'SELECT id FROM study_plan WHERE course_id = ? AND unit_id = ? AND year_number = ? AND semester = ? AND id <> ? LIMIT 1',
            [$courseId, $unitId, $yearNumber, $semester, $id]
        );

        if ($existing) {
            set_flash('error', 'Já existe outro vínculo com esse curso, UC, ano e semestre.');
            redirect_to($editRedirect);
        }

        db_execute(
            $pdo,
            'UPDATE study_plan SET course_id = ?, unit_id = ?, year_number = ?, semester = ? WHERE id = ?',
            [$courseId, $unitId, $yearNumber, $semester, $id]
        );

        set_flash('success', 'Entrada do plano atualizada com sucesso.');
        redirect_to('gestor_plano.php');
    }
}

// Processa a remoção direta de uma entrada do plano.
if (isset($_GET['delete'])) {
    verify_csrf_value($_GET['csrf_token'] ?? null, 'gestor_plano.php');
    $id = (int) $_GET['delete'];
    db_execute($pdo, 'DELETE FROM study_plan WHERE id = ?', [$id]);
    set_flash('success', 'Entrada do plano removida com sucesso.');
    redirect_to('gestor_plano.php');
}

// Carrega a entrada em edição, o candidato a remoção e os dados auxiliares da página.
$editingEntry = isset($_GET['edit'])
    ? db_fetch_one($pdo, 'SELECT id, course_id, unit_id, year_number, semester FROM study_plan WHERE id = ? LIMIT 1', [(int) $_GET['edit']])
    : null;
$deleteCandidate = isset($_GET['confirm_delete'])
    ? db_fetch_one(
        $pdo,
        'SELECT sp.id, sp.year_number, sp.semester, c.name AS course_name, c.is_active, u.name AS unit_name
         FROM study_plan sp
         INNER JOIN courses c ON c.id = sp.course_id
         INNER JOIN units u ON u.id = sp.unit_id
         WHERE sp.id = ? LIMIT 1',
        [(int) $_GET['confirm_delete']]
    )
    : null;
$courses = db_fetch_all($pdo, 'SELECT id, name, is_active FROM courses ORDER BY is_active DESC, name');
$units = db_fetch_all($pdo, 'SELECT id, name FROM units ORDER BY name');
$entries = db_fetch_all(
    $pdo,
    'SELECT sp.id, sp.year_number, sp.semester, c.name AS course_name, c.is_active, u.name AS unit_name
     FROM study_plan sp
     INNER JOIN courses c ON c.id = sp.course_id
     INNER JOIN units u ON u.id = sp.unit_id
     ORDER BY c.name, sp.year_number, sp.semester, u.name'
);

// Renderiza o cabeçalho comum da página.
render_app_page_start(
    'Gc',
    'Bem-vindo ao Plano de Estudos',
    'Esta página permite associar Unidades Curriculares (UCs) aos diferentes cursos do sistema, organizando-as por ano curricular e semestre. O seu objetivo é garantir uma estrutura académica coerente, evitando duplicações incoerentes e assegurando que cada UC está corretamente integrada no plano de estudos.',
    $navItems,
    'gestor_plano.php'
);
?>
<section class="app-panel profile-panel">
    <!-- Formulário para criação de uma nova entrada do plano de estudos. -->
    <div class="app-panel__header">
        <div>
            <h2>Criar entrada</h2>
            <p>Esta secção permite criar uma nova associação entre um curso e uma Unidade Curricular (UC). Para isso, devem ser selecionados o curso, a UC correspondente, o ano curricular e o semestre. Esta informação permite organizar corretamente o plano de estudos de cada curso no sistema. A mesma UC pode ser associada ao mesmo curso mais do que uma vez apenas quando o ano curricular ou o semestre forem diferentes, evitando duplicações incoerentes.</p>
        </div>
    </div>

    <form method="post" class="app-form app-form--grid profile-form" novalidate>
        <!-- Token CSRF e ação de criação. -->
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="create_plan">

        <div class="app-field">
            <label for="course_id">Curso</label>
            <select id="course_id" name="course_id">
                <option value="">Seleciona um curso</option>
                <?php foreach ($courses as $course): ?>
                    <option value="<?= (int) $course['id'] ?>">
                        <?= h($course['name']) ?><?= (int) $course['is_active'] === 1 ? '' : ' (inativo)' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="app-field">
            <label for="unit_id">UC</label>
            <select id="unit_id" name="unit_id">
                <option value="">Seleciona uma UC</option>
                <?php foreach ($units as $unit): ?>
                    <option value="<?= (int) $unit['id'] ?>"><?= h($unit['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="app-field">
            <label for="year_number">Ano curricular</label>
            <select id="year_number" name="year_number">
                <?php for ($year = 1; $year <= 5; $year++): ?>
                    <option value="<?= $year ?>" <?= $year === 1 ? 'selected' : '' ?>><?= $year ?>.º ano</option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="app-field">
            <label for="semester">Semestre</label>
            <select id="semester" name="semester">
                <option value="1" selected>1.º semestre</option>
                <option value="2">2.º semestre</option>
            </select>
        </div>

        <div class="app-form__actions profile-form__actions">
            <button type="submit" class="app-button app-button--primary">Criar entrada</button>
        </div>
    </form>
</section>

<section class="app-panel">
    <!-- Tabela principal de consulta e gestão das entradas do plano. -->
    <div class="app-panel__header">
        <div>
            <h2>Gestão de planos</h2>
            <p>Esta secção apresenta a organização do plano de estudos de cada curso, estruturado por ano curricular e semestre. Através da tabela apresentada, é possível consultar as Unidades Curriculares associadas a cada curso e acompanhar a forma como estão distribuídas ao longo da formação. Esta organização permite manter o plano de estudos claro, consistente e facilmente gerível dentro do sistema.</p>
        </div>
    </div>

    <div class="app-table-wrap">
        <table class="app-table">
            <colgroup>
                <col class="app-table__plan-course-col">
                <col class="app-table__plan-unit-col">
                <col class="app-table__plan-year-col">
                <col class="app-table__plan-semester-col">
                <col class="app-table__plan-actions-col">
            </colgroup>
            <thead>
                <tr>
                    <th class="app-table__plan-course-col">Curso</th>
                    <th class="app-table__plan-unit-col">UC</th>
                    <th class="app-table__plan-year-col">Ano</th>
                    <th class="app-table__plan-semester-col">Semestre</th>
                    <th class="app-table__plan-actions-col">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entries as $entry): ?>
                    <tr>
                        <td class="app-table__plan-course-col">
                            <?= h($entry['course_name']) ?><br>
                            <?php if ((int) $entry['is_active'] !== 1): ?>
                                <span class="helper-text">Curso inativo</span>
                            <?php endif; ?>
                        </td>
                        <td class="app-table__plan-unit-col"><?= h($entry['unit_name']) ?></td>
                        <td class="app-table__plan-year-col"><?= (int) $entry['year_number'] ?>.º</td>
                        <td class="app-table__plan-semester-col"><?= (int) $entry['semester'] ?>.º</td>
                        <td class="app-table__plan-actions-col">
                            <div class="table-actions">
                                <a href="gestor_plano.php?edit=<?= (int) $entry['id'] ?>" title="Editar">
                                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                                    </svg>
                                </a>
                                <a href="gestor_plano.php?confirm_delete=<?= (int) $entry['id'] ?>" class="danger" title="Remover">
                                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.11 0 0 0-7.5 0" />
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

<?php if ($editingEntry): ?>
    <!-- Modal de edição de uma entrada existente do plano. -->
    <div class="app-modal is-open" id="edit-plan-modal">
        <a href="gestor_plano.php" class="app-modal__backdrop" aria-label="Fechar edição da entrada"></a>

        <section class="app-modal__dialog app-panel profile-panel" role="dialog" aria-modal="true" aria-labelledby="edit-plan-title">
            <div class="app-modal__header">
                <div>
                    <h2 id="edit-plan-title">Editar entrada</h2>
                    <p>Nesta parte, podes atualizar o curso, a UC, o ano curricular e o semestre desta entrada do plano de estudos.</p>
                </div>
                <a href="gestor_plano.php" class="app-modal__close" aria-label="Fechar modal">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </a>
            </div>

            <form method="post" class="app-form app-form--grid profile-form" novalidate>
                <!-- Token CSRF e identificação da entrada a atualizar. -->
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="update_plan">
                <input type="hidden" name="id" value="<?= (int) $editingEntry['id'] ?>">

                <div class="app-field">
                    <label for="edit-course_id">Curso</label>
                    <select id="edit-course_id" name="course_id">
                        <option value="">Seleciona um curso</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?= (int) $course['id'] ?>" <?= ((int) $editingEntry['course_id'] === (int) $course['id']) ? 'selected' : '' ?>>
                                <?= h($course['name']) ?><?= (int) $course['is_active'] === 1 ? '' : ' (inativo)' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="app-field">
                    <label for="edit-unit_id">UC</label>
                    <select id="edit-unit_id" name="unit_id">
                        <option value="">Seleciona uma UC</option>
                        <?php foreach ($units as $unit): ?>
                            <option value="<?= (int) $unit['id'] ?>" <?= ((int) $editingEntry['unit_id'] === (int) $unit['id']) ? 'selected' : '' ?>><?= h($unit['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="app-field">
                    <label for="edit-year_number">Ano curricular</label>
                    <select id="edit-year_number" name="year_number">
                        <?php for ($year = 1; $year <= 5; $year++): ?>
                            <option value="<?= $year ?>" <?= ((int) $editingEntry['year_number'] === $year) ? 'selected' : '' ?>><?= $year ?>.º ano</option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="app-field">
                    <label for="edit-semester">Semestre</label>
                    <select id="edit-semester" name="semester">
                        <option value="1" <?= ((int) $editingEntry['semester'] === 1) ? 'selected' : '' ?>>1.º semestre</option>
                        <option value="2" <?= ((int) $editingEntry['semester'] === 2) ? 'selected' : '' ?>>2.º semestre</option>
                    </select>
                </div>

                <div class="app-form__actions profile-form__actions">
                    <button type="submit" class="app-button app-button--primary">Guardar entrada</button>
                </div>
            </form>
        </section>
    </div>
<?php endif; ?>

<?php if ($deleteCandidate): ?>
    <!-- Modal de confirmação para remoção de uma entrada do plano. -->
    <div class="app-modal is-open" id="delete-plan-modal">
        <a href="gestor_plano.php" class="app-modal__backdrop" aria-label="Fechar confirmação de remoção"></a>

        <section class="app-modal__dialog app-panel profile-panel app-modal__dialog--compact" role="dialog" aria-modal="true" aria-labelledby="delete-plan-title">
            <div class="app-modal__header">
                <div>
                    <h2 id="delete-plan-title">Remover entrada</h2>
                    <p>Vais remover a entrada do plano para o curso <strong><?= h($deleteCandidate['course_name']) ?></strong> e a UC <strong><?= h($deleteCandidate['unit_name']) ?></strong>.</p>
                </div>
                <a href="gestor_plano.php" class="app-modal__close" aria-label="Fechar modal">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </a>
            </div>

            <div class="app-modal__content">
                <p class="helper-text">Ano curricular: <strong><?= (int) $deleteCandidate['year_number'] ?>.º</strong></p>
                <p class="helper-text">Semestre: <strong><?= (int) $deleteCandidate['semester'] ?>.º</strong></p>
            </div>

            <div class="app-form__actions app-modal__actions app-modal__actions--single">
                <a href="gestor_plano.php?delete=<?= (int) $deleteCandidate['id'] ?>&<?= h(csrf_query()) ?>" class="app-button app-button--danger">Confirmar eliminação</a>
            </div>
        </section>
    </div>
<?php endif; ?>

<?php if ($editingEntry || $deleteCandidate): ?>
    <script>
        // Garante o estado visual de modal aberto e fecha o modal com Escape.
        document.body.classList.add('app-modal-open');

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                window.location.href = 'gestor_plano.php';
            }
        });
    </script>
<?php endif; ?>
<?php
// Fecha a estrutura visual comum aberta no início da página.
render_app_page_end();
