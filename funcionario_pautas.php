<?php
require_once 'app_ui.php';

require_funcionario();

$navItems = [
    app_nav_item('hub_funcionario.php', 'Hub', 'home'),
    app_nav_item('perfil.php', 'Perfil', 'account'),
    app_nav_item('funcionario_pedidos.php', 'Matrículas', 'enrollment'),
    app_nav_item('funcionario_pautas.php', 'Pautas', 'grades'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf('funcionario_pautas.php');

    if (($_POST['action'] ?? '') === 'create_sheet') {
        $unitId = (int) ($_POST['unit_id'] ?? 0);
        $academicYear = trim((string) ($_POST['academic_year'] ?? ''));
        $season = trim((string) ($_POST['season'] ?? ''));

        if ($unitId <= 0 || $academicYear === '' || $season === '') {
            set_flash('error', 'Seleciona UC, ano letivo e época.');
            redirect_to('funcionario_pautas.php');
        }

        if (db_fetch_one($pdo, 'SELECT id FROM grade_sheets WHERE unit_id = ? AND academic_year = ? AND season = ? LIMIT 1', [$unitId, $academicYear, $season])) {
            set_flash('error', 'Já existe uma pauta para essa UC, ano letivo e época.');
            redirect_to('funcionario_pautas.php');
        }

        db_execute(
            $pdo,
            'INSERT INTO grade_sheets (unit_id, academic_year, season, created_by) VALUES (?, ?, ?, ?)',
            [$unitId, $academicYear, $season, current_user()['id']]
        );

        $sheetId = (int) $pdo->lastInsertId();

        $eligibleStudents = db_fetch_all(
            $pdo,
            'SELECT DISTINCT er.user_id
             FROM enrollment_requests er
             INNER JOIN study_plan sp ON sp.course_id = er.course_id
             WHERE er.status = \'aprovado\' AND sp.unit_id = ?',
            [$unitId]
        );

        foreach ($eligibleStudents as $student) {
            db_execute(
                $pdo,
                'INSERT IGNORE INTO grade_sheet_students (sheet_id, student_user_id) VALUES (?, ?)',
                [$sheetId, $student['user_id']]
            );
        }

        set_flash('success', 'Pauta criada com sucesso.');
        redirect_to('funcionario_pauta_detalhe.php?id=' . $sheetId);
    }
}

$units = db_fetch_all($pdo, 'SELECT id, name FROM units ORDER BY name');
$filterUnitId = (int) ($_GET['filter_unit_id'] ?? 0);
$filterSeason = trim((string) ($_GET['filter_season'] ?? ''));
$filterAcademicYear = trim((string) ($_GET['filter_academic_year'] ?? ''));
$sheetWhere = [];
$sheetParams = [];

if ($filterUnitId > 0) {
    $sheetWhere[] = 'gs.unit_id = ?';
    $sheetParams[] = $filterUnitId;
}

if ($filterSeason !== '') {
    $sheetWhere[] = 'gs.season = ?';
    $sheetParams[] = $filterSeason;
}

if ($filterAcademicYear !== '') {
    $sheetWhere[] = 'gs.academic_year = ?';
    $sheetParams[] = $filterAcademicYear;
}

$sheetWhereSql = $sheetWhere ? ('WHERE ' . implode(' AND ', $sheetWhere)) : '';
$sheets = db_fetch_all(
    $pdo,
    'SELECT gs.id, gs.academic_year, gs.season, gs.created_at, u.name AS unit_name, creator.name AS created_by_name,
            (SELECT COUNT(*) FROM grade_sheet_students gss WHERE gss.sheet_id = gs.id) AS total_students
     FROM grade_sheets gs
     INNER JOIN units u ON u.id = gs.unit_id
     INNER JOIN users creator ON creator.id = gs.created_by
     ' . $sheetWhereSql . '
     ORDER BY gs.created_at DESC',
    $sheetParams
);

render_app_page_start(
    'Gc',
    'Bem-vindo às Pautas de Avaliação',
    'Nesta área é possível criar pautas por Unidade Curricular, ano letivo e época de avaliação. Após a criação, pode aceder ao detalhe de cada pauta para lançar, consultar ou editar as classificações dos alunos, garantindo uma gestão simples e organizada de todo o processo de avaliação.',
    $navItems,
    'funcionario_pautas.php'
);
?>
<section class="app-panel profile-panel">
    <div class="app-panel__header">
        <div>
            <h2>Criar pauta</h2>
            <p>Nesta secção pode criar novas pautas de avaliação, bastando preencher os campos abaixo com a Unidade Curricular, o ano letivo e a época pretendida, de forma simples e rápida.</p>
        </div>
    </div>

    <form method="post" class="app-form app-form--grid profile-form pautas-form" novalidate>
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="create_sheet">

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
            <label for="academic_year">Ano letivo</label>
            <input id="academic_year" type="text" name="academic_year">
        </div>
        <div class="app-field pautas-form__field--full">
            <label for="season">Época</label>
            <select id="season" name="season">
                <option value="">Seleciona a época</option>
                <option value="Normal">Normal</option>
                <option value="Recurso">Recurso</option>
                <option value="Especial">Especial</option>
            </select>
        </div>

        <div class="app-form__actions profile-form__actions pautas-form__actions">
            <button type="submit" class="app-button app-button--primary">Criar pauta</button>
        </div>
    </form>
</section>

<section class="app-panel profile-panel">
    <div class="app-panel__header">
        <div>
            <h2>Gestão de pautas</h2>
            <p>Nesta secção pode consultar e gerir todas as pautas já criadas, tendo também a possibilidade de filtrar o histórico por Unidade Curricular, ano letivo e época. Desta forma, consegue encontrar e organizar a informação de forma mais rápida, simples e eficiente.</p>
        </div>
    </div>

    <form method="get" class="app-form app-form--grid profile-form pautas-form" novalidate>
        <div class="app-field">
            <label for="filter_unit_id">Filtrar por UC</label>
            <select id="filter_unit_id" name="filter_unit_id">
                <option value="">Todas</option>
                <?php foreach ($units as $unit): ?>
                    <option value="<?= (int) $unit['id'] ?>" <?= $filterUnitId === (int) $unit['id'] ? 'selected' : '' ?>><?= h($unit['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="app-field">
            <label for="filter_academic_year">Filtrar por ano letivo</label>
            <input id="filter_academic_year" type="text" name="filter_academic_year" value="<?= h($filterAcademicYear) ?>">
        </div>
        <div class="app-field pautas-form__field--full">
            <label for="filter_season">Filtrar por época</label>
            <select id="filter_season" name="filter_season">
                <option value="">Todas</option>
                <?php foreach (['Normal', 'Recurso', 'Especial'] as $seasonOption): ?>
                    <option value="<?= h($seasonOption) ?>" <?= $filterSeason === $seasonOption ? 'selected' : '' ?>><?= h($seasonOption) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="app-form__actions profile-form__actions pautas-form__actions pautas-form__actions--split">
            <button type="submit" class="app-button app-button--primary">Filtrar</button>
            <a href="funcionario_pautas.php" class="app-link">Limpar filtros</a>
        </div>
    </form>

    <div class="app-table-wrap">
        <table class="app-table app-table--sheets">
            <colgroup>
                <col class="app-table__course-name-col">
                <col class="app-table__course-created-col">
                <col class="app-table__course-state-col">
                <col class="app-table__profile-status-col">
                <col class="app-table__course-created-by-col">
                <col class="app-table__actions-col">
            </colgroup>
            <thead>
                <tr>
                    <th class="app-table__course-name-col">UC</th>
                    <th class="app-table__course-created-col">Ano letivo</th>
                    <th class="app-table__course-state-col">Época</th>
                    <th class="app-table__profile-status-col">Alunos</th>
                    <th class="app-table__course-created-by-col">Criada por</th>
                    <th class="app-table__actions-col">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($sheets === []): ?>
                    <tr>
                        <td colspan="6"><p class="empty-text">Não existem pautas para os filtros selecionados.</p></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($sheets as $sheet): ?>
                        <tr>
                            <td class="app-table__course-name-col"><?= h($sheet['unit_name']) ?></td>
                            <td class="app-table__course-created-col"><?= h($sheet['academic_year']) ?></td>
                            <td class="app-table__course-state-col"><?= h($sheet['season']) ?></td>
                            <td class="app-table__profile-status-col"><?= (int) $sheet['total_students'] ?></td>
                            <td class="app-table__course-created-by-col"><?= h($sheet['created_by_name']) ?></td>
                            <td class="app-table__actions-col">
                                <div class="table-actions">
                                    <a href="funcionario_pauta_detalhe.php?id=<?= (int) $sheet['id'] ?>" title="Abrir pauta">
                                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                                        </svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php
render_app_page_end();
