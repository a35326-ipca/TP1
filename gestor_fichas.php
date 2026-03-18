<?php
require_once 'app_ui.php';

require_gestor();

$navItems = [
    app_nav_item('hub_gestor.php', 'Hub', 'home'),
    app_nav_item('perfil.php', 'Perfil', 'account'),
    app_nav_item('gestor_utilizadores.php', 'Utilizadores', 'users'),
    app_nav_item('gestor_cursos.php', 'Cursos', 'courses'),
    app_nav_item('gestor_ucs.php', 'UCs', 'units'),
    app_nav_item('gestor_plano.php', 'Plano', 'plan'),
    app_nav_item('gestor_fichas.php', 'Fichas', 'enrollment'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf('gestor_fichas.php');

    $action = $_POST['action'] ?? '';

    if ($action === 'review_profile') {
        $id = (int) ($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $reviewNotes = trim($_POST['review_notes'] ?? '');

        $profileToReview = db_fetch_one($pdo, 'SELECT id, status, review_notes FROM student_profiles WHERE id = ? LIMIT 1', [$id]);

        if (!$profileToReview) {
            set_flash('error', 'A ficha selecionada não existe.');
            redirect_to('gestor_fichas.php');
        }

        if (!in_array($profileToReview['status'] ?? '', ['submetida', 'rejeitada', 'aprovada'], true)) {
            set_flash('error', 'Só podes editar fichas submetidas, rejeitadas ou aprovadas.');
            redirect_to('gestor_fichas.php');
        }

        if (!in_array($status, ['aprovada', 'rejeitada'], true)) {
            set_flash('error', 'Seleciona uma decisão válida para a ficha.');
            set_old_input([
                'status' => $status,
                'review_notes' => $reviewNotes,
            ]);
            redirect_to('gestor_fichas.php?review=' . $id);
        }

        if (
            in_array($profileToReview['status'] ?? '', ['rejeitada', 'aprovada'], true)
            && $status === ($profileToReview['status'] ?? '')
            && $reviewNotes === trim((string) ($profileToReview['review_notes'] ?? ''))
        ) {
            set_flash('error', 'Não existem alterações para guardar.');
            set_old_input([
                'status' => $status,
                'review_notes' => $reviewNotes,
            ]);
            redirect_to('gestor_fichas.php?review=' . $id);
        }

        db_execute(
            $pdo,
            'INSERT INTO student_profile_decisions (
                student_profile_id,
                previous_status,
                new_status,
                previous_review_notes,
                new_review_notes,
                reviewed_by
             ) VALUES (?, ?, ?, ?, ?, ?)',
            [
                $id,
                (string) $profileToReview['status'],
                $status,
                $profileToReview['review_notes'] !== null ? (string) $profileToReview['review_notes'] : null,
                $reviewNotes !== '' ? $reviewNotes : null,
                current_user()['id'],
            ]
        );

        db_execute(
            $pdo,
            'UPDATE student_profiles
             SET status = ?, review_notes = ?, reviewed_by = ?, reviewed_at = NOW()
             WHERE id = ?',
            [$status, $reviewNotes, current_user()['id'], $id]
        );

        set_flash('success', 'Ficha revista com sucesso.');
        redirect_to('gestor_fichas.php');
    }

    if ($action === 'delete_profile') {
        $id = (int) ($_POST['id'] ?? 0);
        $profileToDelete = db_fetch_one($pdo, 'SELECT * FROM student_profiles WHERE id = ? LIMIT 1', [$id]);

        if (!$profileToDelete) {
            set_flash('error', 'A ficha selecionada não existe.');
            redirect_to('gestor_fichas.php');
        }

        if (!in_array($profileToDelete['status'] ?? '', ['rejeitada', 'aprovada'], true)) {
            set_flash('error', 'Só podes eliminar fichas rejeitadas ou aprovadas.');
            redirect_to('gestor_fichas.php');
        }

        $pdo->beginTransaction();

        try {
            db_execute(
                $pdo,
                'INSERT INTO deleted_student_profiles (
                    original_profile_id, user_id, course_id, full_name, birth_date, contact_email, phone, address, photo_path,
                    notes, status, review_notes, reviewed_by, reviewed_at, submitted_at,
                    original_created_at, original_updated_at, deleted_by, purge_after
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 DAY))',
                [
                    $profileToDelete['id'],
                    $profileToDelete['user_id'],
                    $profileToDelete['course_id'],
                    $profileToDelete['full_name'],
                    $profileToDelete['birth_date'],
                    $profileToDelete['contact_email'],
                    $profileToDelete['phone'],
                    $profileToDelete['address'],
                    $profileToDelete['photo_path'],
                    $profileToDelete['notes'],
                    $profileToDelete['status'],
                    $profileToDelete['review_notes'],
                    $profileToDelete['reviewed_by'],
                    $profileToDelete['reviewed_at'],
                    $profileToDelete['submitted_at'],
                    $profileToDelete['created_at'],
                    $profileToDelete['updated_at'],
                    current_user()['id'],
                ]
            );

            db_execute($pdo, 'DELETE FROM student_profiles WHERE id = ?', [$id]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            set_flash('error', 'Não foi possível eliminar a ficha.');
            redirect_to('gestor_fichas.php');
        }

        set_flash('success', 'Ficha removida da lista ativa. A eliminação permanente acontece dentro de 10 dias.');
        redirect_to('gestor_fichas.php');
    }
}

$reviewingProfile = isset($_GET['review'])
    ? db_fetch_one(
        $pdo,
        'SELECT sp.*, c.name AS course_name, u.name AS student_name, u.email AS user_email
         FROM student_profiles sp
         INNER JOIN courses c ON c.id = sp.course_id
         INNER JOIN users u ON u.id = sp.user_id
         WHERE sp.id = ? LIMIT 1',
        [(int) $_GET['review']]
    )
    : null;

if ($reviewingProfile && !in_array($reviewingProfile['status'] ?? '', ['submetida', 'rejeitada', 'aprovada'], true)) {
    set_flash('error', 'Só podes abrir para edição fichas submetidas, rejeitadas ou aprovadas.');
    redirect_to('gestor_fichas.php');
}

$decisionHistory = $reviewingProfile
    ? db_fetch_all(
        $pdo,
        'SELECT spd.*, reviewer.name AS reviewed_by_name
         FROM student_profile_decisions spd
         LEFT JOIN users reviewer ON reviewer.id = spd.reviewed_by
         WHERE spd.student_profile_id = ?
         ORDER BY spd.created_at DESC, spd.id DESC',
        [(int) $reviewingProfile['id']]
    )
    : [];

$deleteCandidate = isset($_GET['confirm_delete'])
    ? db_fetch_one(
        $pdo,
        'SELECT sp.id, sp.full_name, sp.status, sp.submitted_at, c.name AS course_name
         FROM student_profiles sp
         INNER JOIN courses c ON c.id = sp.course_id
         WHERE sp.id = ? LIMIT 1',
        [(int) $_GET['confirm_delete']]
    )
    : null;

if ($deleteCandidate && !in_array($deleteCandidate['status'] ?? '', ['rejeitada', 'aprovada'], true)) {
    set_flash('error', 'Só podes eliminar fichas rejeitadas ou aprovadas.');
    redirect_to('gestor_fichas.php');
}

$profiles = db_fetch_all(
    $pdo,
    'SELECT sp.id, sp.full_name, sp.contact_email, sp.phone, sp.photo_path, sp.status, sp.submitted_at, sp.reviewed_at,
            c.name AS course_name, u.email AS account_email
     FROM student_profiles sp
     INNER JOIN courses c ON c.id = sp.course_id
     INNER JOIN users u ON u.id = sp.user_id
     WHERE sp.status IN (\'submetida\', \'rejeitada\', \'aprovada\')
     ORDER BY FIELD(sp.status, \'submetida\', \'rejeitada\', \'aprovada\'), sp.updated_at DESC'
);

$oldInput = get_old_input();
$selectedReviewStatus = $oldInput['status'] ?? (($reviewingProfile && in_array(($reviewingProfile['status'] ?? ''), ['rejeitada', 'aprovada'], true)) ? $reviewingProfile['status'] : 'aprovada');
$reviewNotesValue = $oldInput['review_notes'] ?? ($reviewingProfile['review_notes'] ?? '');

render_app_page_start(
    'Gc',
    'Bem-vindo à Gestão de Fichas',
    'Esta página permite consultar e validar as fichas submetidas pelos alunos ao sistema. Através desta secção, é possível analisar cada submissão, aprovar ou rejeitar as fichas e adicionar observações sempre que necessário. Todas as ações realizadas ficam registadas num sistema de auditoria, garantindo controlo, transparência e um acompanhamento detalhado de todo o processo.',
    $navItems,
    'gestor_fichas.php'
);
?>
<?php if ($reviewingProfile): ?>
    <div class="app-modal is-open" id="review-profile-modal">
        <a href="gestor_fichas.php" class="app-modal__backdrop" aria-label="Fechar revisão da ficha"></a>
        <section class="app-modal__dialog app-modal__dialog--review app-panel profile-panel" role="dialog" aria-modal="true" aria-labelledby="review-profile-title">
        <div class="app-modal__header">
            <div>
                <h2 id="review-profile-title"><?= in_array(($reviewingProfile['status'] ?? ''), ['rejeitada', 'aprovada'], true) ? 'Editar ficha' : 'Rever ficha' ?></h2>
                <p><?= h($reviewingProfile['full_name']) ?> · <?= h($reviewingProfile['course_name']) ?></p>
            </div>
            <a href="gestor_fichas.php" class="app-modal__close" aria-label="Fechar modal">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </a>
        </div>

        <div class="review-profile-grid">
            <article class="app-card review-profile-card review-profile-card--details">
                <h2>Dados submetidos</h2>
                <div class="review-profile-details">
                <p><strong>Nome:</strong> <?= h($reviewingProfile['full_name']) ?></p>
                <p><strong>Data de nascimento:</strong> <?= h((string) ($reviewingProfile['birth_date'] ?? '-')) ?></p>
                <p><strong>E-mail da conta:</strong> <?= h($reviewingProfile['user_email']) ?></p>
                <p><strong>E-mail de contacto:</strong> <?= h($reviewingProfile['contact_email']) ?></p>
                <p><strong>Telefone:</strong> <?= h($reviewingProfile['phone']) ?></p>
                <p><strong>Morada:</strong> <?= h($reviewingProfile['address'] ?? '-') ?></p>
                <p><strong>Curso:</strong> <?= h($reviewingProfile['course_name']) ?></p>
                <p><strong>Estado atual:</strong> <?= status_badge($reviewingProfile['status']) ?></p>
                <p><strong>Observações do aluno:</strong> <?= h($reviewingProfile['notes'] ?? '-') ?></p>
                </div>
                <?php if (!empty($reviewingProfile['photo_path'])): ?>
                    <img src="<?= h($reviewingProfile['photo_path']) ?>" alt="Fotografia do aluno" class="photo-preview">
                <?php endif; ?>
            </article>

            <article class="app-card review-profile-card review-profile-card--photo">
                <h2>Fotografia do aluno</h2>
                <p class="helper-text">Aqui está a fotografia submetida pelo aluno.</p>
                <div class="review-profile-photo-wrap">
                    <?php if (!empty($reviewingProfile['photo_path'])): ?>
                        <img src="<?= h($reviewingProfile['photo_path']) ?>" alt="Fotografia do aluno" class="photo-preview photo-preview--modal">
                    <?php else: ?>
                        <p class="empty-text">Sem fotografia submetida.</p>
                    <?php endif; ?>
                </div>
            </article>

            <article class="app-card review-profile-card review-profile-card--decision">
                <h2>Decisão pedagógica</h2>
                <form method="post" class="app-form review-profile-form" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="review_profile">
                    <input type="hidden" name="id" value="<?= (int) $reviewingProfile['id'] ?>">

                    <div class="app-field">
                        <label for="status">Decisão</label>
                        <select id="status" name="status">
                            <option value="aprovada" <?= $selectedReviewStatus === 'aprovada' ? 'selected' : '' ?>>Aprovar</option>
                            <option value="rejeitada" <?= $selectedReviewStatus === 'rejeitada' ? 'selected' : '' ?>>Rejeitar</option>
                        </select>
                    </div>
                    <div class="app-field review-profile-notes-field">
                        <label for="review_notes">Observações do gestor</label>
                        <textarea id="review_notes" name="review_notes" class="review-profile-notes"><?= h((string) $reviewNotesValue) ?></textarea>
                    </div>
                    <div class="app-form__actions review-profile-actions">
                        <button type="submit" class="app-button app-button--primary">Guardar decisão</button>
                    </div>
                </form>
            </article>

            <article class="app-card review-profile-card review-profile-card--history">
                <h2>Histórico de decisões</h2>
                <?php if ($decisionHistory === []): ?>
                    <p class="empty-text">Ainda não existem decisões anteriores registadas para esta ficha.</p>
                <?php else: ?>
                    <div class="decision-history">
                        <?php foreach ($decisionHistory as $historyItem): ?>
                            <article class="decision-history__item">
                                <div class="decision-history__meta">
                                    <strong class="app-text-flow"><?= h($historyItem['reviewed_by_name'] ?? 'Sistema') ?></strong>
                                    <span class="app-text-flow"><?= h(date('Y-m-d H:i', strtotime((string) $historyItem['created_at']))) ?></span>
                                </div>
                                <p><strong>Estado:</strong> <span class="app-text-flow"><?= h($historyItem['previous_status']) ?> → <?= h($historyItem['new_status']) ?></span></p>
                                <p><strong>Observações anteriores:</strong> <span class="app-text-flow--scroll"><?= h($historyItem['previous_review_notes'] ?: '-') ?></span></p>
                                <p><strong>Novas observações:</strong> <span class="app-text-flow--scroll"><?= h($historyItem['new_review_notes'] ?: '-') ?></span></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>
        </div>
        </section>
    </div>

<?php endif; ?>

<?php if ($deleteCandidate): ?>
    <div class="app-modal is-open" id="delete-profile-modal">
        <a href="gestor_fichas.php" class="app-modal__backdrop" aria-label="Fechar confirmação de eliminação"></a>

        <section class="app-modal__dialog app-panel profile-panel app-modal__dialog--compact" role="dialog" aria-modal="true" aria-labelledby="delete-profile-title">
            <div class="app-modal__header">
                <div>
                    <h2 id="delete-profile-title">Eliminar ficha</h2>
                    <p>Vais remover a ficha de <strong><?= h($deleteCandidate['full_name']) ?></strong>.</p>
                </div>
                <a href="gestor_fichas.php" class="app-modal__close" aria-label="Fechar modal">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </a>
            </div>

            <div class="app-modal__content">
                <p class="helper-text">Curso: <strong><?= h($deleteCandidate['course_name']) ?></strong></p>
                <p class="helper-text">Submetida: <strong><?= $deleteCandidate['submitted_at'] ? h(date('Y-m-d', strtotime((string) $deleteCandidate['submitted_at']))) : '-' ?></strong></p>
                <p class="helper-text">A eliminação permanente acontece automaticamente dentro de <strong>10 dias</strong>.</p>
            </div>

            <form method="post" class="app-form" novalidate>
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete_profile">
                <input type="hidden" name="id" value="<?= (int) $deleteCandidate['id'] ?>">

                <div class="app-form__actions app-modal__actions app-modal__actions--single">
                    <button type="submit" class="app-button app-button--danger">Confirmar eliminação</button>
                </div>
            </form>
        </section>
    </div>
<?php endif; ?>

<?php if ($reviewingProfile || $deleteCandidate): ?>
    <script>
        document.body.classList.add('app-modal-open');

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                window.location.href = 'gestor_fichas.php';
            }
        });
    </script>
<?php endif; ?>

<section class="app-panel">
    <div class="app-panel__header">
        <div>
            <h2>Gestão de Fichas</h2>
            <p>Nesta secção podes consultar todas as fichas registadas, ver os dados dos alunos, acompanhar o estado de cada submissão e realizar ações como editar ou remover quando necessário.</p>
        </div>
    </div>

    <div class="app-table-wrap">
        <table class="app-table app-table--profiles">
            <thead>
                <tr>
                    <th class="app-table__profile-student-col">Aluno</th>
                    <th class="app-table__profile-email-col">E-mail</th>
                    <th class="app-table__profile-phone-col">Telefone</th>
                    <th class="app-table__profile-course-col">Curso</th>
                    <th class="app-table__profile-submitted-col">Submetida</th>
                    <th class="app-table__profile-status-col">Estado</th>
                    <th class="app-table__profile-actions-col">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($profiles as $profile): ?>
                    <tr>
                        <td class="app-table__profile-student-col">
                            <?= h($profile['full_name']) ?>
                        </td>
                        <td class="app-table__profile-email-col"><?= h($profile['contact_email']) ?></td>
                        <td class="app-table__profile-phone-col"><?= h($profile['phone']) ?></td>
                        <td class="app-table__profile-course-col"><?= h($profile['course_name']) ?></td>
                        <td class="app-table__profile-submitted-col">
                            <?= $profile['submitted_at'] ? h(date('Y-m-d', strtotime((string) $profile['submitted_at']))) : '-' ?>
                        </td>
                        <td class="app-table__profile-status-col"><?= status_badge($profile['status']) ?></td>
                        <td class="app-table__profile-actions-col">
                            <div class="table-actions">
                                <?php if (($profile['status'] ?? '') === 'submetida'): ?>
                                    <a href="gestor_fichas.php?review=<?= (int) $profile['id'] ?>" title="Rever">
                                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                                        </svg>
                                    </a>
                                <?php elseif (in_array(($profile['status'] ?? ''), ['rejeitada', 'aprovada'], true)): ?>
                                    <a href="gestor_fichas.php?review=<?= (int) $profile['id'] ?>" title="Editar">
                                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                                        </svg>
                                    </a>
                                    <a href="gestor_fichas.php?confirm_delete=<?= (int) $profile['id'] ?>" class="danger" title="Eliminar">
                                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.11 0 0 0-7.5 0" />
                                        </svg>
                                    </a>
                                <?php else: ?>
                                    <span class="helper-text">Fechada</span>
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
