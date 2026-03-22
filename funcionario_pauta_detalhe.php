<?php
// Página de detalhe da pauta, usada para adicionar alunos elegíveis e lançar notas finais.

require_once 'app_ui.php';

// Garante que apenas funcionários autenticados podem gerir pautas.
require_funcionario();

// Normaliza e valida a nota final introduzida manualmente.
function normalize_final_grade(mixed $value): array
{
    $trimmed = trim((string) $value);

    if ($trimmed === '') {
        return ['valid' => true, 'value' => null];
    }

    $normalized = str_replace(',', '.', $trimmed);

    if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $normalized)) {
        return ['valid' => false, 'value' => null];
    }

    $grade = (float) $normalized;

    if ($grade < 0 || $grade > 20) {
        return ['valid' => false, 'value' => null];
    }

    return ['valid' => true, 'value' => $grade];
}

// Obtém o identificador da pauta pedida e carrega os respetivos dados.
$sheetId = (int) ($_GET['id'] ?? 0);
$sheet = db_fetch_one(
    $pdo,
    'SELECT gs.id, gs.unit_id, gs.academic_year, gs.season, u.name AS unit_name
     FROM grade_sheets gs
     INNER JOIN units u ON u.id = gs.unit_id
     WHERE gs.id = ? LIMIT 1',
    [$sheetId]
);

if (!$sheet) {
    set_flash('error', 'A pauta selecionada não existe.');
    redirect_to('funcionario_pautas.php');
}

// Navegação base desta área do funcionário.
$navItems = [
    app_nav_item('hub_funcionario.php', 'Hub', 'home'),
    app_nav_item('perfil.php', 'Perfil', 'account'),
    app_nav_item('funcionario_pedidos.php', 'Matrículas', 'enrollment'),
    app_nav_item('funcionario_pautas.php', 'Pautas', 'grades'),
];

// Processa operações de escrita sobre a pauta.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf('funcionario_pauta_detalhe.php?id=' . $sheetId);
    $action = $_POST['action'] ?? '';

    // Guarda as notas finais já introduzidas na tabela.
    if ($action === 'save_grades') {
        $grades = is_array($_POST['grades'] ?? null) ? $_POST['grades'] : [];
        $currentGrades = db_fetch_all(
            $pdo,
            'SELECT id, final_grade
             FROM grade_sheet_students
             WHERE sheet_id = ?',
            [$sheetId]
        );

        // Indexa as notas atuais por linha para permitir comparação e validação.
        $currentGradesById = [];
        foreach ($currentGrades as $currentGrade) {
            $currentGradesById[(int) $currentGrade['id']] = $currentGrade['final_grade'] === null
                ? null
                : (float) $currentGrade['final_grade'];
        }

        // Valida apenas linhas que pertencem realmente à pauta atual.
        $validatedGrades = [];
        foreach ($grades as $rowId => $grade) {
            $rowId = (int) $rowId;

            if (!array_key_exists($rowId, $currentGradesById)) {
                continue;
            }

            $validated = normalize_final_grade($grade);
            if (!$validated['valid']) {
                set_flash('error', 'Cada nota deve estar vazia ou conter um valor numérico entre 0 e 20.');
                redirect_to('funcionario_pauta_detalhe.php?id=' . $sheetId);
            }

            $validatedGrades[$rowId] = $validated['value'];
        }

        // Evita gravações desnecessárias quando não houve alterações efetivas.
        $hasChanges = false;

        // Atualiza cada registo da pauta com a nova nota final.
        foreach ($validatedGrades as $rowId => $finalGrade) {
            if ($currentGradesById[$rowId] !== $finalGrade) {
                $hasChanges = true;
                break;
            }
        }

        if (!$hasChanges) {
            set_flash('error', 'Não existem alterações para guardar.');
            redirect_to('funcionario_pauta_detalhe.php?id=' . $sheetId);
        }

        foreach ($validatedGrades as $rowId => $finalGrade) {
            db_execute(
                $pdo,
                'UPDATE grade_sheet_students SET final_grade = ? WHERE id = ? AND sheet_id = ?',
                [$finalGrade, $rowId, $sheetId]
            );
        }

        set_flash('success', 'Notas guardadas com sucesso.');
        redirect_to('funcionario_pauta_detalhe.php?id=' . $sheetId);
    }

    // Adiciona à pauta alunos aprovados e ainda não associados.
    if ($action === 'add_students') {
        $studentIds = array_map('intval', $_POST['student_ids'] ?? []);

        foreach ($studentIds as $studentId) {
            db_execute(
                $pdo,
                'INSERT IGNORE INTO grade_sheet_students (sheet_id, student_user_id) VALUES (?, ?)',
                [$sheetId, $studentId]
            );
        }

        set_flash('success', 'Alunos adicionados à pauta.');
        redirect_to('funcionario_pauta_detalhe.php?id=' . $sheetId);
    }
}

// Carrega os alunos já presentes na pauta e os alunos elegíveis que ainda podem ser adicionados.
$rows = db_fetch_all(
    $pdo,
    'SELECT gss.id, gss.final_grade, u.name AS student_name, u.email AS student_email
     FROM grade_sheet_students gss
     INNER JOIN users u ON u.id = gss.student_user_id
     WHERE gss.sheet_id = ?
     ORDER BY u.name',
    [$sheetId]
);

$eligibleStudents = db_fetch_all(
    $pdo,
    'SELECT DISTINCT er.user_id AS id, u.name, u.email
     FROM enrollment_requests er
     INNER JOIN study_plan sp ON sp.course_id = er.course_id
     INNER JOIN users u ON u.id = er.user_id
     WHERE er.status = \'aprovado\'
       AND sp.unit_id = ?
       AND er.user_id NOT IN (
           SELECT student_user_id FROM grade_sheet_students WHERE sheet_id = ?
       )
     ORDER BY u.name',
    [(int) $sheet['unit_id'], $sheetId]
);

// Renderiza o cabeçalho comum da página e as ações de navegação contextual.
render_app_page_start(
    'Gc',
    'Detalhes da Pauta',
    'Nesta área pode consultar os alunos elegíveis associados à pauta selecionada e lançar as respetivas notas finais. Também pode editar e guardar as classificações de forma simples, mantendo o processo de avaliação organizado e atualizado.',
    $navItems,
    'funcionario_pautas.php',
    [
        [
            'href' => 'funcionario_pautas.php',
            'label' => 'Voltar às pautas',
            'class' => 'app-button--ghost app-button--icon',
            'icon_svg' => '<svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" /></svg>',
        ],
    ]
);
?>
<section class="metric-grid">
    <!-- Resumo rápido da pauta selecionada. -->
    <article class="metric-card">
        <span class="metric-card__label">UC</span>
        <strong class="metric-card__value"><?= h($sheet['unit_name']) ?></strong>
    </article>
    <article class="metric-card">
        <span class="metric-card__label">Ano letivo</span>
        <strong class="metric-card__value"><?= h($sheet['academic_year']) ?></strong>
    </article>
    <article class="metric-card">
        <span class="metric-card__label">Época</span>
        <strong class="metric-card__value"><?= h($sheet['season']) ?></strong>
    </article>
</section>

<?php if ($eligibleStudents !== []): ?>
    <section class="app-panel">
        <!-- Área de inclusão manual de alunos elegíveis ainda não associados à pauta. -->
        <div class="app-panel__header">
            <div>
                <h2>Adicionar alunos elegíveis</h2>
                <p>Selecione os alunos aprovados que pretende incluir nesta pauta.</p>
            </div>
        </div>

        <form method="post" class="app-form profile-form pautas-detail-form" novalidate>
            <!-- Token CSRF e indicação da ação a executar. -->
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="add_students">
            <div class="app-table-wrap">
                <table class="app-table app-table--eligible-students">
                    <colgroup>
                        <col class="app-table__select-col">
                        <col class="app-table__name-col">
                        <col class="app-table__email-col">
                    </colgroup>
                    <thead>
                        <tr>
                            <th class="app-table__select-col">Incluir</th>
                            <th class="app-table__name-col">Aluno</th>
                            <th class="app-table__email-col">E-mail</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($eligibleStudents as $student): ?>
                            <tr>
                                <td class="app-table__select-col">
                                    <label class="pautas-detail-check">
                                        <input type="checkbox" name="student_ids[]" value="<?= (int) $student['id'] ?>">
                                        <span>Selecionar</span>
                                    </label>
                                </td>
                                <td class="app-table__name-col">
                                    <div class="app-text-flow--scroll"><?= h($student['name']) ?></div>
                                </td>
                                <td class="app-table__email-col">
                                    <div class="app-text-flow--scroll"><?= h($student['email']) ?></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="app-form__actions profile-form__actions pautas-detail-form__actions">
                <button type="submit" class="app-button app-button--primary">Adicionar alunos selecionados</button>
            </div>
        </form>
    </section>
<?php endif; ?>

<section class="app-panel">
    <!-- Gestão e edição das notas finais já atribuídas aos alunos da pauta. -->
    <div class="app-panel__header">
        <div>
            <h2>Gestão das notas finais</h2>
            <p>Nesta secção pode editar e guardar as classificações finais dos alunos associados à pauta, garantindo que todas as notas ficam corretamente registadas e atualizadas de forma simples e organizada.</p>
        </div>
    </div>

    <form method="post" class="app-form profile-form pautas-detail-form" novalidate>
        <!-- Token CSRF e ação de gravação das classificações. -->
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_grades">
        <div class="app-table-wrap">
            <table class="app-table app-table--grades">
                <colgroup>
                    <col class="app-table__name-col">
                    <col class="app-table__email-col">
                    <col class="app-table__uc-name-col">
                </colgroup>
                <thead>
                    <tr>
                        <th class="app-table__name-col">Aluno</th>
                        <th class="app-table__email-col">E-mail</th>
                        <th class="app-table__uc-name-col">Nota final</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows === []): ?>
                        <!-- Estado vazio quando ainda não existem alunos na pauta. -->
                        <tr>
                            <td colspan="3"><p class="empty-text">Ainda não existem alunos associados a esta pauta.</p></td>
                        </tr>
                    <?php else: ?>
                        <!-- Lista editável das notas finais por aluno. -->
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td class="app-table__name-col"><div class="app-text-flow--scroll"><?= h($row['student_name']) ?></div></td>
                                <td class="app-table__email-col"><div class="app-text-flow--scroll"><?= h($row['student_email']) ?></div></td>
                                <td class="app-table__uc-name-col">
                                    <input
                                        type="text"
                                        name="grades[<?= (int) $row['id'] ?>]"
                                        value="<?= h($row['final_grade'] !== null ? (string) $row['final_grade'] : '') ?>"
                                        inputmode="decimal"
                                        placeholder="0-20"
                                    >
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($rows !== []): ?>
            <div class="app-form__actions profile-form__actions pautas-detail-form__actions">
                <button type="submit" class="app-button app-button--primary">Guardar notas</button>
            </div>
        <?php endif; ?>
    </form>
</section>
<?php
// Fecha a estrutura visual comum aberta no início da página.
render_app_page_end();
