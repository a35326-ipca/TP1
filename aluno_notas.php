<?php
// Página do aluno para consulta das classificações finais já registadas.

require_once 'app_ui.php';

// Garante que apenas alunos autenticados com acesso desbloqueado entram nesta área.
require_aluno();
require_student_access_unlocked($pdo);

// Navegação base desta secção do portal do aluno.
$navItems = [
    app_nav_item('hub_aluno.php', 'Hub', 'home'),
    app_nav_item('perfil.php', 'Perfil', 'account'),
    app_nav_item('aluno_ficha.php', 'Ficha', 'profile'),
    app_nav_item('aluno_matricula.php', 'Matrícula', 'enrollment-student'),
    app_nav_item('aluno_notas.php', 'Notas', 'grades'),
];

// Substitui a navegação inicial pela versão dinâmica que respeita o estado atual do aluno.
$navItems = build_student_nav_items($pdo, (int) current_user()['id']);

// Obtém as pautas associadas ao aluno, limitadas às UCs de cursos em que existe matrícula aprovada.
$grades = db_fetch_all(
    $pdo,
    'SELECT
        gs.id,
        u.name AS unit_name,
        gs.academic_year,
        gs.season,
        gss.final_grade,
        (
            SELECT c.name
            FROM enrollment_requests er
            INNER JOIN study_plan sp ON sp.course_id = er.course_id
            INNER JOIN courses c ON c.id = er.course_id
            WHERE er.user_id = gss.student_user_id
              AND er.status = \'aprovado\'
              AND sp.unit_id = gs.unit_id
            ORDER BY er.created_at DESC
            LIMIT 1
        ) AS course_name
     FROM grade_sheet_students gss
     INNER JOIN grade_sheets gs ON gs.id = gss.sheet_id
     INNER JOIN units u ON u.id = gs.unit_id
     WHERE gss.student_user_id = ?
       AND EXISTS (
           SELECT 1
           FROM enrollment_requests er
           INNER JOIN study_plan sp ON sp.course_id = er.course_id
           WHERE er.user_id = gss.student_user_id
             AND er.status = \'aprovado\'
             AND sp.unit_id = gs.unit_id
       )
     ORDER BY gs.academic_year DESC, gs.season DESC, u.name ASC',
    [current_user()['id']]
);

// Isola apenas os registos onde a nota final já foi publicada.
$publishedGrades = array_values(array_filter(
    $grades,
    static fn (array $grade): bool => $grade['final_grade'] !== null
));

// Renderiza o cabeçalho comum da página e o enquadramento visual da área.
render_app_page_start(
    'Gc',
    'Bem-vindo às Notas',
    'Nesta área podes consultar de forma simples e organizada as classificações finais já registadas nas unidades curriculares em que estás inscrito, permitindo acompanhar o teu desempenho académico ao longo do tempo.',
    $navItems,
    'aluno_notas.php'
);

// Apresenta um resumo rápido das classificações já lançadas e dos registos encontrados.
render_metric_cards([
    [
        'label' => 'UCs com nota',
        'value' => count($publishedGrades),
        'hint' => 'Total de classificações finais já lançadas.',
    ],
    [
        'label' => 'Registos encontrados',
        'value' => count($grades),
        'hint' => 'Total de pautas associadas às tuas UCs.',
    ],
]);
?>
<section class="app-panel">
    <!-- Bloco principal de consulta das notas finais do aluno. -->
    <div class="app-panel__header">
        <div>
            <h2>Notas finais</h2>
            <p>Aqui podes consultar as classificações finais já registadas pelo funcionário nas pautas das unidades curriculares em que estás inscrito.</p>
        </div>
    </div>

    <div class="app-table-wrap">
        <table class="app-table app-table--student-grades">
            <colgroup>
                <col class="app-table__plan-course-col">
                <col class="app-table__plan-unit-col">
                <col class="app-table__course-created-col">
                <col class="app-table__plan-semester-col">
                <col class="app-table__course-state-col">
            </colgroup>
            <thead>
                <tr>
                    <th class="app-table__plan-course-col">Curso</th>
                    <th class="app-table__plan-unit-col">UC</th>
                    <th class="app-table__course-created-col">Ano letivo</th>
                    <th class="app-table__plan-semester-col">Época</th>
                    <th class="app-table__course-state-col">Nota final</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($grades === []): ?>
                    <!-- Estado vazio quando ainda não existem classificações disponíveis. -->
                    <tr>
                        <td colspan="5"><p class="empty-text">Ainda não existem notas disponíveis para consulta.</p></td>
                    </tr>
                <?php else: ?>
                    <!-- Lista de classificações encontradas para o aluno autenticado. -->
                    <?php foreach ($grades as $grade): ?>
                        <tr>
                            <td class="app-table__plan-course-col"><div class="app-text-flow--scroll"><?= h($grade['course_name'] ?? '-') ?></div></td>
                            <td class="app-table__plan-unit-col"><div class="app-text-flow--scroll"><?= h($grade['unit_name']) ?></div></td>
                            <td class="app-table__course-created-col"><?= h($grade['academic_year']) ?></td>
                            <td class="app-table__plan-semester-col"><?= h($grade['season']) ?></td>
                            <td class="app-table__course-state-col"><?= h($grade['final_grade'] !== null ? (string) $grade['final_grade'] : '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php
// Fecha a estrutura visual comum aberta no início da página.
render_app_page_end();
