<?php
require_once 'app_ui.php';

require_aluno();
require_student_access_unlocked($pdo);

$navItems = [
    app_nav_item('hub_aluno.php', 'Hub', 'home'),
    app_nav_item('perfil.php', 'Perfil', 'account'),
    app_nav_item('aluno_ficha.php', 'Ficha', 'profile'),
    app_nav_item('aluno_matricula.php', "Matr\u{00ED}cula", 'enrollment-student'),
    app_nav_item('aluno_notas.php', 'Notas', 'grades'),
];

$navItems = build_student_nav_items($pdo, (int) current_user()['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf('aluno_matricula.php');

    $courseId = (int) ($_POST['course_id'] ?? 0);
    $studentNotes = trim((string) ($_POST['student_notes'] ?? ''));

    if ($courseId <= 0) {
        set_flash('error', 'Seleciona um curso ativo para criar o pedido.');
        redirect_to('aluno_matricula.php');
    }

    $course = db_fetch_one($pdo, 'SELECT id FROM courses WHERE id = ? AND is_active = 1 LIMIT 1', [$courseId]);

    if (!$course) {
        set_flash('error', "O curso selecionado n\u{00E3}o est\u{00E1} dispon\u{00ED}vel.");
        redirect_to('aluno_matricula.php');
    }

    $pending = db_fetch_one(
        $pdo,
        'SELECT id FROM enrollment_requests WHERE user_id = ? AND course_id = ? AND status = ? LIMIT 1',
        [current_user()['id'], $courseId, 'pendente']
    );

    if ($pending) {
        set_flash('error', "J\u{00E1} tens um pedido pendente para esse curso.");
        redirect_to('aluno_matricula.php');
    }

    if (!has_submission_limit_available($pdo, (int) current_user()['id'], 'enrollment_request')) {
        set_flash('error', "S\u{00F3} podes criar 5 pedidos de matr\u{00ED}cula em 24 horas. Tenta novamente mais tarde.");
        redirect_to('aluno_matricula.php');
    }

    db_execute(
        $pdo,
        'INSERT INTO enrollment_requests (user_id, course_id, student_notes, status) VALUES (?, ?, ?, ?)',
        [current_user()['id'], $courseId, $studentNotes !== '' ? $studentNotes : null, 'pendente']
    );

    set_flash('success', "Pedido de matr\u{00ED}cula criado com sucesso.");
    register_submission_event($pdo, (int) current_user()['id'], 'enrollment_request');
    redirect_to('aluno_matricula.php');
}

$courses = db_fetch_all($pdo, 'SELECT id, name FROM courses WHERE is_active = 1 ORDER BY name');
$requests = db_fetch_all(
    $pdo,
    'SELECT er.id, er.status, er.student_notes, er.decision_notes, er.decided_at, er.created_at, c.name AS course_name,
            u.name AS decided_by_name
     FROM enrollment_requests er
     INNER JOIN courses c ON c.id = er.course_id
     LEFT JOIN users u ON u.id = er.decided_by
     WHERE er.user_id = ?
     ORDER BY er.created_at DESC',
    [current_user()['id']]
);

render_app_page_start(
    'Gc',
    "Bem-vindo ao Pedido de Matr\u{00ED}cula",
    "Nesta \u{00E1}rea podes criar novos pedidos de matr\u{00ED}cula e acompanhar o estado de cada submiss\u{00E3}o ao longo do processo. Permite tamb\u{00E9}m consultar as decis\u{00F5}es registadas pelo funcion\u{00E1}rio, de forma simples, clara e organizada.",
    $navItems,
    'aluno_matricula.php'
);
?>
<section class="app-panel">
    <div class="app-panel__header">
        <div>
            <h2>Novo pedido</h2>
            <p>Nesta sec&ccedil;&atilde;o podes selecionar um curso dispon&iacute;vel e criar um novo pedido de matr&iacute;cula. Tens tamb&eacute;m a possibilidade de adicionar observa&ccedil;&otilde;es, caso seja necess&aacute;rio, para fornecer informa&ccedil;&otilde;es adicionais ao funcion&aacute;rio durante a an&aacute;lise do pedido.</p>
        </div>
    </div>

    <form method="post" class="app-form app-form--grid profile-form student-enrollment-form" novalidate>
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <div class="app-field student-enrollment-form__field-full">
            <label for="course_id">Curso</label>
            <select id="course_id" name="course_id">
                <option value="">Seleciona um curso</option>
                <?php foreach ($courses as $course): ?>
                    <option value="<?= (int) $course['id'] ?>"><?= h($course['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="app-field student-enrollment-form__field-full">
            <label for="student_notes">Observa&ccedil;&otilde;es do aluno</label>
            <textarea id="student_notes" name="student_notes"></textarea>
        </div>

        <div class="app-form__actions profile-form__actions">
            <button type="submit" class="app-button app-button--primary">Criar pedido</button>
        </div>
    </form>
</section>

<section class="app-panel">
    <div class="app-panel__header">
        <div>
            <h2>Hist&oacute;rico de pedidos</h2>
            <p>Nesta sec&ccedil;&atilde;o podes consultar o hist&oacute;rico de todos os pedidos de matr&iacute;cula realizados, verificar o estado atual de cada um e acompanhar as decis&otilde;es mais recentes associadas a cada pedido.</p>
        </div>
    </div>

    <div class="app-table-wrap">
        <table class="app-table app-table--student-requests">
            <colgroup>
                <col>
                <col>
                <col class="app-table__student-request-decision-col">
                <col class="app-table__student-request-created-col">
                <col class="app-table__student-request-status-col">
            </colgroup>
            <thead>
                <tr>
                    <th>Curso</th>
                    <th>Notas do aluno</th>
                    <th>Decis&atilde;o</th>
                    <th class="app-table__student-request-created-col">Criado em</th>
                    <th class="app-table__student-request-status-col">Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($requests === []): ?>
                    <tr>
                        <td colspan="5"><p class="empty-text">Ainda n&atilde;o tens pedidos de matr&iacute;cula.</p></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($requests as $request): ?>
                        <tr>
                            <td><div class="app-text-flow--scroll"><?= h($request['course_name']) ?></div></td>
                            <td><div class="app-text-flow--scroll"><?= h($request['student_notes'] ?: '-') ?></div></td>
                            <td class="app-table__student-request-decision-col">
                                <div class="app-text-flow--scroll"><?= h($request['decision_notes'] ?: '-') ?></div>
                                <div class="app-text-flow--scroll helper-text"><?= h($request['decided_by_name'] ?? '-') ?></div>
                            </td>
                            <td class="app-table__student-request-created-col"><?= h(date('Y-m-d', strtotime((string) $request['created_at']))) ?></td>
                            <td class="app-table__student-request-status-col"><?= status_badge($request['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php
render_app_page_end();
