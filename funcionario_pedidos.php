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
    verify_csrf('funcionario_pedidos.php');

    if (($_POST['action'] ?? '') === 'decide_request') {
        $id = (int) ($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $decisionNotes = trim((string) ($_POST['decision_notes'] ?? ''));

        $requestToReview = db_fetch_one(
            $pdo,
            'SELECT id, status, decision_notes
             FROM enrollment_requests
             WHERE id = ? LIMIT 1',
            [$id]
        );

        if (!$requestToReview) {
            set_flash('error', 'O pedido selecionado não existe.');
            redirect_to('funcionario_pedidos.php');
        }

        if (!in_array($requestToReview['status'] ?? '', ['pendente', 'rejeitado', 'aprovado'], true)) {
            set_flash('error', 'Só podes abrir pedidos pendentes, rejeitados ou aprovados.');
            redirect_to('funcionario_pedidos.php');
        }

        if (!in_array($status, ['aprovado', 'rejeitado'], true)) {
            set_flash('error', 'Seleciona uma decisão válida.');
            set_old_input([
                'status' => $status,
                'decision_notes' => $decisionNotes,
            ]);
            redirect_to('funcionario_pedidos.php?review=' . $id);
        }

        if (
            in_array($requestToReview['status'] ?? '', ['rejeitado', 'aprovado'], true)
            && $status === ($requestToReview['status'] ?? '')
            && $decisionNotes === trim((string) ($requestToReview['decision_notes'] ?? ''))
        ) {
            set_flash('error', 'Não existem alterações para guardar.');
            set_old_input([
                'status' => $status,
                'decision_notes' => $decisionNotes,
            ]);
            redirect_to('funcionario_pedidos.php?review=' . $id);
        }

        db_execute(
            $pdo,
            'INSERT INTO enrollment_request_decisions (
                enrollment_request_id,
                previous_status,
                new_status,
                previous_decision_notes,
                new_decision_notes,
                decided_by
             ) VALUES (?, ?, ?, ?, ?, ?)',
            [
                $id,
                (string) $requestToReview['status'],
                $status,
                $requestToReview['decision_notes'] !== null ? (string) $requestToReview['decision_notes'] : null,
                $decisionNotes !== '' ? $decisionNotes : null,
                current_user()['id'],
            ]
        );

        db_execute(
            $pdo,
            'UPDATE enrollment_requests
             SET status = ?, decision_notes = ?, decided_by = ?, decided_at = NOW()
             WHERE id = ?',
            [$status, $decisionNotes !== '' ? $decisionNotes : null, current_user()['id'], $id]
        );

        set_flash('success', 'Pedido atualizado com sucesso.');
        redirect_to('funcionario_pedidos.php');
    }
}

$reviewingRequest = isset($_GET['review'])
    ? db_fetch_one(
        $pdo,
        'SELECT er.*, c.name AS course_name, u.name AS student_name, u.email AS student_email, reviewer.name AS decided_by_name,
                sp.photo_path AS student_photo_path
         FROM enrollment_requests er
         INNER JOIN courses c ON c.id = er.course_id
         INNER JOIN users u ON u.id = er.user_id
         LEFT JOIN student_profiles sp ON sp.user_id = er.user_id
         LEFT JOIN users reviewer ON reviewer.id = er.decided_by
         WHERE er.id = ? LIMIT 1',
        [(int) $_GET['review']]
    )
    : null;

if ($reviewingRequest && !in_array(($reviewingRequest['status'] ?? ''), ['pendente', 'rejeitado', 'aprovado'], true)) {
    set_flash('error', 'Só podes abrir para decisão pedidos pendentes, rejeitados ou aprovados.');
    redirect_to('funcionario_pedidos.php');
}

$decisionHistory = $reviewingRequest
    ? db_fetch_all(
        $pdo,
        'SELECT erd.*, reviewer.name AS decided_by_name
         FROM enrollment_request_decisions erd
         LEFT JOIN users reviewer ON reviewer.id = erd.decided_by
         WHERE erd.enrollment_request_id = ?
         ORDER BY erd.created_at DESC, erd.id DESC',
        [(int) $reviewingRequest['id']]
    )
    : [];

$requests = db_fetch_all(
    $pdo,
    'SELECT er.id, er.status, er.student_notes, er.decision_notes, er.created_at, er.decided_at,
            c.name AS course_name, u.name AS student_name, u.email AS student_email, reviewer.name AS decided_by_name
     FROM enrollment_requests er
     INNER JOIN courses c ON c.id = er.course_id
     INNER JOIN users u ON u.id = er.user_id
     LEFT JOIN users reviewer ON reviewer.id = er.decided_by
     ORDER BY FIELD(er.status, \'pendente\', \'rejeitado\', \'aprovado\'), er.created_at DESC'
);

$oldInput = get_old_input();
$selectedDecisionStatus = $oldInput['status'] ?? (($reviewingRequest && in_array(($reviewingRequest['status'] ?? ''), ['rejeitado', 'aprovado'], true)) ? $reviewingRequest['status'] : 'aprovado');
$decisionNotesValue = $oldInput['decision_notes'] ?? ($reviewingRequest['decision_notes'] ?? '');

render_app_page_start(
    'Gc',
    'Bem-vindo à Gestão de Pedidos de Matrícula',
    'Área operacional destinada à análise e decisão dos pedidos de matrícula, permitindo também manter um registo organizado de auditoria de todas as decisões tomadas ao longo do processo.',
    $navItems,
    'funcionario_pedidos.php'
);
?>
<?php if ($reviewingRequest): ?>
    <div class="app-modal is-open" id="review-request-modal">
        <a href="funcionario_pedidos.php" class="app-modal__backdrop" aria-label="Fechar revisão da matrícula"></a>
        <section class="app-modal__dialog app-modal__dialog--review app-panel profile-panel" role="dialog" aria-modal="true" aria-labelledby="review-request-title">
            <div class="app-modal__header">
                <div>
                    <h2 id="review-request-title"><?= in_array(($reviewingRequest['status'] ?? ''), ['rejeitado', 'aprovado'], true) ? 'Editar matrícula' : 'Rever matrícula' ?></h2>
                    <p class="app-text-flow"><?= h($reviewingRequest['student_name']) ?> · <?= h($reviewingRequest['course_name']) ?></p>
                </div>
                <a href="funcionario_pedidos.php" class="app-modal__close" aria-label="Fechar modal">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </a>
            </div>

            <div class="review-profile-grid">
                <article class="app-card review-profile-card review-profile-card--details">
                    <h2>Dados submetidos</h2>
                    <div class="review-profile-details">
                        <p><strong>Aluno:</strong> <span class="app-text-flow--scroll"><?= h($reviewingRequest['student_name']) ?></span></p>
                        <p><strong>E-mail:</strong> <span class="app-text-flow--scroll"><?= h($reviewingRequest['student_email']) ?></span></p>
                        <p><strong>Curso:</strong> <span class="app-text-flow--scroll"><?= h($reviewingRequest['course_name']) ?></span></p>
                        <p><strong>Estado atual:</strong> <span class="app-text-flow"><?= status_badge($reviewingRequest['status']) ?></span></p>
                        <p><strong>Submetido em:</strong> <span class="app-text-flow"><?= h(date('Y-m-d', strtotime((string) $reviewingRequest['created_at']))) ?></span></p>
                        <p><strong>Decidido por:</strong> <span class="app-text-flow--scroll"><?= h($reviewingRequest['decided_by_name'] ?? '-') ?></span></p>
                        <p><strong>Data da decisão:</strong> <span class="app-text-flow"><?= $reviewingRequest['decided_at'] ? h(date('Y-m-d H:i', strtotime((string) $reviewingRequest['decided_at']))) : '-' ?></span></p>
                        <p><strong>Observações do aluno:</strong> <span class="app-text-flow--scroll"><?= h($reviewingRequest['student_notes'] ?: '-') ?></span></p>
                        <p><strong>Observações atuais:</strong> <span class="app-text-flow--scroll"><?= h($reviewingRequest['decision_notes'] ?: '-') ?></span></p>
                    </div>
                </article>

                <article class="app-card review-profile-card review-profile-card--photo">
                    <h2>Fotografia do aluno</h2>
                    <p class="helper-text">Aqui está a fotografia submetida pelo aluno.</p>
                    <div class="review-profile-photo-wrap">
                        <?php if (!empty($reviewingRequest['student_photo_path'])): ?>
                            <img src="<?= h($reviewingRequest['student_photo_path']) ?>" alt="Fotografia do aluno" class="photo-preview photo-preview--modal">
                        <?php else: ?>
                            <p class="empty-text">Sem fotografia submetida.</p>
                        <?php endif; ?>
                    </div>
                </article>

                <article class="app-card review-profile-card review-profile-card--decision">
                    <h2>Decisão pedagógica</h2>
                    <form method="post" class="app-form review-profile-form" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="action" value="decide_request">
                        <input type="hidden" name="id" value="<?= (int) $reviewingRequest['id'] ?>">

                        <div class="app-field">
                            <label for="status">Decisão</label>
                            <select id="status" name="status">
                                <option value="aprovado" <?= $selectedDecisionStatus === 'aprovado' ? 'selected' : '' ?>>Aprovar</option>
                                <option value="rejeitado" <?= $selectedDecisionStatus === 'rejeitado' ? 'selected' : '' ?>>Rejeitar</option>
                            </select>
                        </div>
                        <div class="app-field review-profile-notes-field">
                            <label for="decision_notes">Observações do funcionário</label>
                            <textarea id="decision_notes" name="decision_notes" class="review-profile-notes"><?= h((string) $decisionNotesValue) ?></textarea>
                        </div>
                        <div class="app-form__actions review-profile-actions">
                            <button type="submit" class="app-button app-button--primary">Guardar decisão</button>
                        </div>
                    </form>
                </article>

                <article class="app-card review-profile-card review-profile-card--history">
                    <h2>Histórico de decisões</h2>
                    <?php if ($decisionHistory === []): ?>
                        <p class="empty-text">Ainda não existem decisões anteriores registadas para este pedido.</p>
                    <?php else: ?>
                        <div class="decision-history">
                            <?php foreach ($decisionHistory as $historyItem): ?>
                                <article class="decision-history__item">
                                    <div class="decision-history__meta">
                                        <strong class="app-text-flow"><?= h($historyItem['decided_by_name'] ?? 'Sistema') ?></strong>
                                        <span class="app-text-flow"><?= h(date('Y-m-d H:i', strtotime((string) $historyItem['created_at']))) ?></span>
                                    </div>
                                    <p><strong>Estado:</strong> <span class="app-text-flow"><?= h($historyItem['previous_status']) ?> → <?= h($historyItem['new_status']) ?></span></p>
                                    <p><strong>Observações anteriores:</strong> <span class="app-text-flow--scroll"><?= h($historyItem['previous_decision_notes'] ?: '-') ?></span></p>
                                    <p><strong>Novas observações:</strong> <span class="app-text-flow--scroll"><?= h($historyItem['new_decision_notes'] ?: '-') ?></span></p>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>
            </div>
        </section>
    </div>
<?php endif; ?>

<?php if ($reviewingRequest): ?>
    <script>
        document.body.classList.add('app-modal-open');

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                window.location.href = 'funcionario_pedidos.php';
            }
        });
    </script>
<?php endif; ?>

<section class="app-panel">
    <div class="app-panel__header">
        <div>
            <h2>Gestão de matrículas registadas</h2>
            <p>A prioridade visual é dada aos pedidos pendentes, permitindo ao funcionário gerir as matrículas submetidas pelos alunos de forma eficiente. Todas as decisões ficam registadas e podem ser consultadas no histórico de cada processo.</p>
        </div>
    </div>

    <div class="app-table-wrap">
        <table class="app-table app-table--profiles">
            <thead>
                <tr>
                    <th class="app-table__profile-student-col">Aluno</th>
                    <th class="app-table__profile-email-col">E-mail</th>
                    <th class="app-table__profile-course-col">Curso</th>
                    <th class="app-table__profile-submitted-col">Submetida</th>
                    <th class="app-table__profile-status-col">Estado</th>
                    <th class="app-table__profile-actions-col">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($requests as $request): ?>
                    <tr>
                        <td class="app-table__profile-student-col"><div class="app-text-flow--scroll"><?= h($request['student_name']) ?></div></td>
                        <td class="app-table__profile-email-col"><div class="app-text-flow--scroll"><?= h($request['student_email']) ?></div></td>
                        <td class="app-table__profile-course-col"><div class="app-text-flow--scroll"><?= h($request['course_name']) ?></div></td>
                        <td class="app-table__profile-submitted-col"><?= h(date('Y-m-d', strtotime((string) $request['created_at']))) ?></td>
                        <td class="app-table__profile-status-col"><?= status_badge($request['status']) ?></td>
                        <td class="app-table__profile-actions-col">
                            <div class="table-actions">
                                <?php if (($request['status'] ?? '') === 'pendente'): ?>
                                    <a href="funcionario_pedidos.php?review=<?= (int) $request['id'] ?>" title="Rever">
                                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                                        </svg>
                                    </a>
                                <?php elseif (in_array(($request['status'] ?? ''), ['rejeitado', 'aprovado'], true)): ?>
                                    <a href="funcionario_pedidos.php?review=<?= (int) $request['id'] ?>" title="Editar">
                                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                                        </svg>
                                    </a>
                                <?php else: ?>
                                    <span class="helper-text">Fechado</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php
render_app_page_end();
