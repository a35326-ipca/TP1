<?php
// Página do funcionário para análise, decisão e eliminação de pedidos de matrícula.

require_once 'app_ui.php';

// Garante que apenas funcionários autenticados podem aceder a esta área.
require_funcionario();

// Navegação base desta área do funcionário.
$navItems = [
    app_nav_item('hub_funcionario.php', 'Hub', 'home'),
    app_nav_item('perfil.php', 'Perfil', 'account'),
    app_nav_item('funcionario_pedidos.php', 'Matrículas', 'enrollment'),
    app_nav_item('funcionario_pautas.php', 'Pautas', 'grades'),
];

// Renderiza o modal de revisão detalhada de um pedido específico.
function render_request_review_modal(array $reviewingRequest, array $decisionHistory, int $decisionHistoryCount, string $selectedDecisionStatus, string $decisionNotesValue): void
{
    ?>
    <div class="app-modal is-open" id="review-request-modal">
        <a href="funcionario_pedidos.php" class="app-modal__backdrop" aria-label="Fechar revisão da matrícula" data-modal-close></a>
        <section class="app-modal__dialog app-modal__dialog--review app-panel profile-panel" role="dialog" aria-modal="true" aria-labelledby="review-request-title">
            <div class="app-modal__header">
                <div>
                    <h2 id="review-request-title"><?= in_array(($reviewingRequest['status'] ?? ''), ['rejeitado', 'aprovado'], true) ? 'Editar matrícula' : 'Rever matrícula' ?></h2>
                    <p class="app-text-flow"><?= h($reviewingRequest['student_name']) ?> · <?= h($reviewingRequest['course_name']) ?></p>
                </div>
                <a href="funcionario_pedidos.php" class="app-modal__close" aria-label="Fechar modal" data-modal-close>
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
                        <p class="review-field--stacked"><strong>Observações do aluno:</strong> <span class="app-text-flow--scroll"><?= h($reviewingRequest['student_notes'] ?: '-') ?></span></p>
                        <p class="review-field--stacked"><strong>Observações atuais:</strong> <span class="app-text-flow--scroll"><?= h($reviewingRequest['decision_notes'] ?: '-') ?></span></p>
                    </div>
                </article>

                <article class="app-card review-profile-card review-profile-card--photo">
                    <h2>Fotografia do aluno</h2>
                    <p class="helper-text">Aqui está a fotografia submetida pelo aluno.</p>
                    <div class="review-profile-photo-wrap">
                        <?php if (!empty($reviewingRequest['student_photo_path'])): ?>
                            <img src="<?= h($reviewingRequest['student_photo_path']) ?>" alt="Fotografia do aluno" class="photo-preview photo-preview--modal" loading="lazy" decoding="async" fetchpriority="low">
                        <?php else: ?>
                            <p class="empty-text">Sem fotografia submetida.</p>
                        <?php endif; ?>
                    </div>
                </article>

                <article class="app-card review-profile-card review-profile-card--decision">
                    <h2>Decisão pedagógica</h2>
                    <form method="post" class="app-form review-profile-form" novalidate>
                        <!-- Token CSRF e identificação do pedido em revisão. -->
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
                        <?php if ($decisionHistoryCount > count($decisionHistory)): ?>
                            <p class="helper-text">A mostrar as 5 decisões mais recentes.</p>
                        <?php endif; ?>
                        <div class="decision-history">
                            <?php foreach ($decisionHistory as $historyItem): ?>
                                <article class="decision-history__item">
                                    <div class="decision-history__meta">
                                        <strong class="app-text-flow"><?= h($historyItem['decided_by_name'] ?? 'Sistema') ?></strong>
                                        <span class="app-text-flow"><?= h(date('Y-m-d H:i', strtotime((string) $historyItem['created_at']))) ?></span>
                                    </div>
                                    <p><strong>Estado:</strong> <span class="app-text-flow"><?= h($historyItem['previous_status']) ?> → <?= h($historyItem['new_status']) ?></span></p>
                                    <p class="review-field--stacked"><strong>Observações anteriores:</strong> <span class="app-text-flow--scroll"><?= h($historyItem['previous_decision_notes'] ?: '-') ?></span></p>
                                    <p class="review-field--stacked"><strong>Novas observações:</strong> <span class="app-text-flow--scroll"><?= h($historyItem['new_decision_notes'] ?: '-') ?></span></p>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>
            </div>
        </section>
    </div>
    <?php
}

// Processa a gravação da decisão sobre um pedido de matrícula.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf('funcionario_pedidos.php');

    if (($_POST['action'] ?? '') === 'decide_request') {
        // Recolhe os dados do formulário de decisão.
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

        // Só permite trabalhar com pedidos ainda geríveis nesta interface.
        if (!in_array($requestToReview['status'] ?? '', ['pendente', 'rejeitado', 'aprovado'], true)) {
            set_flash('error', 'Só podes abrir pedidos pendentes, rejeitados ou aprovados.');
            redirect_to('funcionario_pedidos.php');
        }

        // Valida a decisão escolhida antes de guardar.
        if (!in_array($status, ['aprovado', 'rejeitado'], true)) {
            set_flash('error', 'Seleciona uma decisão válida.');
            set_old_input([
                'status' => $status,
                'decision_notes' => $decisionNotes,
            ]);
            redirect_to('funcionario_pedidos.php?review=' . $id);
        }

        // Evita gravações redundantes quando não houve qualquer alteração.
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

        // Regista a decisão no histórico de auditoria.
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

        // Atualiza o estado atual do pedido.
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

// Limpa automaticamente pedidos rejeitados com mais de 10 dias.
db_execute(
    $pdo,
    "DELETE FROM enrollment_requests
     WHERE status = 'rejeitado'
       AND decided_at IS NOT NULL
       AND decided_at <= DATE_SUB(NOW(), INTERVAL 10 DAY)"
);

// Processa a eliminação manual de pedidos rejeitados.
if (isset($_GET['delete'])) {
    verify_csrf_value($_GET['csrf_token'] ?? null, 'funcionario_pedidos.php');

    $id = (int) $_GET['delete'];
    $requestToDelete = db_fetch_one(
        $pdo,
        'SELECT id, status
         FROM enrollment_requests
         WHERE id = ? LIMIT 1',
        [$id]
    );

    if (!$requestToDelete) {
        set_flash('error', 'O pedido selecionado não existe.');
        redirect_to('funcionario_pedidos.php');
    }

    if (($requestToDelete['status'] ?? '') !== 'rejeitado') {
        set_flash('error', 'Só é possível apagar pedidos rejeitados.');
        redirect_to('funcionario_pedidos.php');
    }

    db_execute($pdo, 'DELETE FROM enrollment_requests WHERE id = ?', [$id]);
    set_flash('success', 'Pedido rejeitado apagado com sucesso.');
    redirect_to('funcionario_pedidos.php');
}

// Resolve qual o pedido a abrir em modal de revisão e qual o candidato a eliminação.
$modalRequestId = isset($_GET['modal'], $_GET['id']) && $_GET['modal'] === 'review'
    ? (int) $_GET['id']
    : null;

$deleteCandidateId = isset($_GET['confirm_delete']) ? (int) $_GET['confirm_delete'] : null;
$reviewRequestId = isset($_GET['review']) ? (int) $_GET['review'] : null;
$activeReviewRequestId = $modalRequestId ?? $reviewRequestId;

// Carrega o pedido em revisão com os dados necessários para o modal.
$reviewingRequest = $activeReviewRequestId
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
        [$activeReviewRequestId]
    )
    : null;

if ($reviewingRequest && !in_array(($reviewingRequest['status'] ?? ''), ['pendente', 'rejeitado', 'aprovado'], true)) {
    set_flash('error', 'Só podes abrir para decisão pedidos pendentes, rejeitados ou aprovados.');
    redirect_to('funcionario_pedidos.php');
}

// Carrega o histórico resumido de decisões do pedido em revisão.
$decisionHistoryCount = $reviewingRequest
    ? (int) db_fetch_value(
        $pdo,
        'SELECT COUNT(*)
         FROM enrollment_request_decisions
         WHERE enrollment_request_id = ?',
        [(int) $reviewingRequest['id']]
    )
    : 0;

$decisionHistory = $reviewingRequest
    ? db_fetch_all(
        $pdo,
        'SELECT erd.*, reviewer.name AS decided_by_name
         FROM enrollment_request_decisions erd
         LEFT JOIN users reviewer ON reviewer.id = erd.decided_by
         WHERE erd.enrollment_request_id = ?
         ORDER BY erd.created_at DESC, erd.id DESC
         LIMIT 5',
        [(int) $reviewingRequest['id']]
    )
    : [];

// Carrega o pedido que poderá ser apagado após confirmação.
$deleteCandidate = $deleteCandidateId
    ? db_fetch_one(
        $pdo,
        'SELECT er.id, er.status, er.created_at, c.name AS course_name, u.name AS student_name
         FROM enrollment_requests er
         INNER JOIN courses c ON c.id = er.course_id
         INNER JOIN users u ON u.id = er.user_id
         WHERE er.id = ? LIMIT 1',
        [$deleteCandidateId]
    )
    : null;

if ($activeReviewRequestId && !$reviewingRequest) {
    set_flash('error', 'O pedido selecionado não existe.');
    redirect_to('funcionario_pedidos.php');
}

if ($deleteCandidateId && !$deleteCandidate) {
    set_flash('error', 'O pedido selecionado não existe.');
    redirect_to('funcionario_pedidos.php');
}

if ($deleteCandidate && ($deleteCandidate['status'] ?? '') !== 'rejeitado') {
    set_flash('error', 'Só é possível apagar pedidos rejeitados.');
    redirect_to('funcionario_pedidos.php');
}

// Lista principal de pedidos apresentada na tabela.
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

// Recupera os valores temporários do formulário do modal, se existirem.
$oldInput = get_old_input();
$selectedDecisionStatus = $oldInput['status'] ?? (($reviewingRequest && in_array(($reviewingRequest['status'] ?? ''), ['rejeitado', 'aprovado'], true)) ? $reviewingRequest['status'] : 'aprovado');
$decisionNotesValue = $oldInput['decision_notes'] ?? ($reviewingRequest['decision_notes'] ?? '');

// Quando o modal é pedido por fetch, devolve apenas o fragmento HTML necessário.
if ($modalRequestId && $reviewingRequest) {
    render_request_review_modal($reviewingRequest, $decisionHistory, $decisionHistoryCount, $selectedDecisionStatus, $decisionNotesValue);
    exit;
}

// Renderiza o cabeçalho comum da página.
render_app_page_start(
    'Gc',
    'Bem-vindo à Gestão de Pedidos de Matrícula',
    'Área operacional destinada à análise e decisão dos pedidos de matrícula, permitindo também manter um registo organizado de auditoria de todas as decisões tomadas ao longo do processo.',
    $navItems,
    'funcionario_pedidos.php'
);
?>
<?php if ($reviewingRequest): ?>
    <?php render_request_review_modal($reviewingRequest, $decisionHistory, $decisionHistoryCount, $selectedDecisionStatus, $decisionNotesValue); ?>
<?php endif; ?>

<?php if ($deleteCandidate): ?>
    <div class="app-modal is-open" id="delete-request-modal">
        <a href="funcionario_pedidos.php" class="app-modal__backdrop" aria-label="Fechar confirmação de eliminação"></a>

        <section class="app-modal__dialog app-panel profile-panel app-modal__dialog--compact" role="dialog" aria-modal="true" aria-labelledby="delete-request-title">
            <div class="app-modal__header">
                <div>
                    <h2 id="delete-request-title">Apagar matrícula rejeitada</h2>
                    <p>Vais apagar o pedido de <strong><?= h($deleteCandidate['student_name']) ?></strong> para o curso <strong><?= h($deleteCandidate['course_name']) ?></strong>.</p>
                </div>
                <a href="funcionario_pedidos.php" class="app-modal__close" aria-label="Fechar modal">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </a>
            </div>

            <div class="app-modal__content">
                <p class="helper-text">Esta ação remove definitivamente o pedido e o respetivo histórico de decisões.</p>
                <p class="helper-text">Submetido em: <strong><?= h(date('Y-m-d', strtotime((string) $deleteCandidate['created_at']))) ?></strong></p>
            </div>

            <div class="app-form__actions app-modal__actions app-modal__actions--single">
                <a href="funcionario_pedidos.php?delete=<?= (int) $deleteCandidate['id'] ?>&<?= h(csrf_query()) ?>" class="app-button app-button--danger">Confirmar eliminação</a>
            </div>
        </section>
    </div>
<?php endif; ?>

<?php if ($reviewingRequest || $deleteCandidate): ?>
    <script>
        // Garante o estado visual de modal aberto e fecha o modal com Escape.
        document.body.classList.add('app-modal-open');

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                window.location.href = 'funcionario_pedidos.php';
            }
        });
    </script>
<?php endif; ?>

<section class="app-panel">
    <!-- Tabela principal de gestão dos pedidos de matrícula. -->
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
                                    <a href="funcionario_pedidos.php?review=<?= (int) $request['id'] ?>" data-modal-url="funcionario_pedidos.php?modal=review&amp;id=<?= (int) $request['id'] ?>" title="Rever">
                                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                                        </svg>
                                    </a>
                                <?php elseif (in_array(($request['status'] ?? ''), ['rejeitado', 'aprovado'], true)): ?>
                                    <a href="funcionario_pedidos.php?review=<?= (int) $request['id'] ?>" data-modal-url="funcionario_pedidos.php?modal=review&amp;id=<?= (int) $request['id'] ?>" title="Editar">
                                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                                        </svg>
                                    </a>
                                    <?php if (($request['status'] ?? '') === 'rejeitado'): ?>
                                        <a href="funcionario_pedidos.php?confirm_delete=<?= (int) $request['id'] ?>" class="danger" title="Apagar">
                                            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                            </svg>
                                        </a>
                                    <?php endif; ?>
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
// Fecha a estrutura visual comum aberta no início da página.
render_app_page_end();
