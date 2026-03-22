<?php
// Página do aluno para criação e consulta de pedidos de matrícula.

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

// Processa a submissão de um novo pedido de matrícula.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf('aluno_matricula.php');

    // Recolhe e normaliza os dados enviados pelo formulário.
    $courseId = (int) ($_POST['course_id'] ?? 0);
    $studentNotes = trim((string) ($_POST['student_notes'] ?? ''));

    // Valida a seleção de um curso ativo antes de prosseguir.
    if ($courseId <= 0) {
        set_flash('error', 'Seleciona um curso ativo para criar o pedido.');
        redirect_to('aluno_matricula.php');
    }

    $course = db_fetch_one($pdo, 'SELECT id FROM courses WHERE id = ? AND is_active = 1 LIMIT 1', [$courseId]);

    if (!$course) {
        set_flash('error', 'O curso selecionado não está disponível.');
        redirect_to('aluno_matricula.php');
    }

    // Impede duplicação de pedidos pendentes para o mesmo curso.
    $pending = db_fetch_one(
        $pdo,
        'SELECT id FROM enrollment_requests WHERE user_id = ? AND course_id = ? AND status = ? LIMIT 1',
        [current_user()['id'], $courseId, 'pendente']
    );

    if ($pending) {
        set_flash('error', 'Já tens um pedido pendente para esse curso.');
        redirect_to('aluno_matricula.php');
    }

    // Aplica o limite de submissões definido para este tipo de pedido.
    if (!has_submission_limit_available($pdo, (int) current_user()['id'], 'enrollment_request')) {
        set_flash('error', 'Só podes criar 5 pedidos de matrícula em 24 horas. Tenta novamente mais tarde.');
        redirect_to('aluno_matricula.php');
    }

    // Cria o pedido com estado inicial pendente.
    db_execute(
        $pdo,
        'INSERT INTO enrollment_requests (user_id, course_id, student_notes, status) VALUES (?, ?, ?, ?)',
        [current_user()['id'], $courseId, $studentNotes !== '' ? $studentNotes : null, 'pendente']
    );

    set_flash('success', 'Pedido de matrícula criado com sucesso.');
    register_submission_event($pdo, (int) current_user()['id'], 'enrollment_request');
    redirect_to('aluno_matricula.php');
}

// Carrega os cursos disponíveis e o histórico de pedidos do aluno autenticado.
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

// Renderiza o cabeçalho comum da página e o enquadramento visual da área.
render_app_page_start(
    'Gc',
    'Bem-vindo ao Pedido de Matrícula',
    'Nesta área podes criar novos pedidos de matrícula e acompanhar o estado de cada submissão ao longo do processo. Também podes consultar as decisões registadas pelo funcionário, de forma simples, clara e organizada.',
    $navItems,
    'aluno_matricula.php'
);
?>
<section class="app-panel">
    <!-- Formulário para criação de um novo pedido de matrícula. -->
    <div class="app-panel__header">
        <div>
            <h2>Novo pedido</h2>
            <p>Nesta secção podes selecionar um curso disponível e criar um novo pedido de matrícula. Também podes acrescentar observações, se necessário, para fornecer informações adicionais ao funcionário durante a análise do pedido.</p>
        </div>
    </div>

    <form method="post" class="app-form app-form--grid profile-form student-enrollment-form" novalidate>
        <!-- Token CSRF para proteção da submissão. -->
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
            <label for="student_notes">Observações do aluno</label>
            <textarea id="student_notes" name="student_notes"></textarea>
        </div>

        <div class="app-form__actions profile-form__actions">
            <button type="submit" class="app-button app-button--primary">Criar pedido</button>
        </div>
    </form>
</section>

<section class="app-panel">
    <!-- Tabela com o histórico e o estado dos pedidos já submetidos. -->
    <div class="app-panel__header">
        <div>
            <h2>Histórico de pedidos</h2>
            <p>Nesta secção podes consultar o histórico de todos os pedidos de matrícula realizados, verificar o estado atual de cada um e acompanhar as decisões mais recentes associadas a cada pedido.</p>
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
                    <th>Decisão</th>
                    <th class="app-table__student-request-created-col">Criado em</th>
                    <th class="app-table__student-request-status-col">Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($requests === []): ?>
                    <!-- Estado vazio quando o aluno ainda não submeteu pedidos. -->
                    <tr>
                        <td colspan="5"><p class="empty-text">Ainda não tens pedidos de matrícula.</p></td>
                    </tr>
                <?php else: ?>
                    <!-- Lista cronológica dos pedidos do aluno. -->
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
// Fecha a estrutura visual comum aberta no início da página.
render_app_page_end();
