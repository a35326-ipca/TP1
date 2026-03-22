<?php
// Página do aluno para criação, atualização e submissão da ficha pessoal.

require_once 'app_ui.php';

// Garante que apenas alunos autenticados podem aceder a esta área.
require_aluno();

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

// Carrega o perfil atual do aluno e os cursos ativos disponíveis.
$profile = db_fetch_one($pdo, 'SELECT * FROM student_profiles WHERE user_id = ? LIMIT 1', [current_user()['id']]);
$activeCourses = db_fetch_all($pdo, 'SELECT id, name FROM courses WHERE is_active = 1 ORDER BY name');
$editable = !$profile || in_array($profile['status'], ['rascunho', 'rejeitada'], true);

// Processa a gravação do rascunho ou a submissão da ficha.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf('aluno_ficha.php');

    // Impede alterações quando a ficha já não está num estado editável.
    if (!$editable) {
        set_flash('error', 'A ficha atual não pode ser editada neste estado.');
        redirect_to('aluno_ficha.php');
    }

    // Recolhe e normaliza os dados recebidos do formulário.
    $action = $_POST['action'] ?? '';
    $courseId = (int) ($_POST['course_id'] ?? 0);
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $birthDate = trim((string) ($_POST['birth_date'] ?? ''));
    $contactEmail = normalize_email((string) ($_POST['contact_email'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $address = trim((string) ($_POST['address'] ?? ''));
    $notes = trim((string) ($_POST['notes'] ?? ''));

    // Valida o curso escolhido para a ficha.
    if ($courseId <= 0) {
        set_flash('error', 'Seleciona um curso válido para a ficha.');
        redirect_to('aluno_ficha.php');
    }

    $selectedCourse = db_fetch_one($pdo, 'SELECT id FROM courses WHERE id = ? AND is_active = 1 LIMIT 1', [$courseId]);

    if (!$selectedCourse) {
        set_flash('error', 'O curso selecionado já não está disponível para a ficha.');
        redirect_to('aluno_ficha.php');
    }

    // Valida o e-mail de contacto, quando preenchido.
    if ($contactEmail !== '' && !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
        set_flash('error', 'O e-mail de contacto não é válido.');
        redirect_to('aluno_ficha.php');
    }

    // Valida a data de nascimento e impede datas futuras.
    if ($birthDate !== '') {
        $birthDateObject = DateTime::createFromFormat('Y-m-d', $birthDate);
        $validBirthDate = $birthDateObject && $birthDateObject->format('Y-m-d') === $birthDate;

        if (!$validBirthDate || $birthDate > date('Y-m-d')) {
            set_flash('error', 'Indica uma data de nascimento válida.');
            redirect_to('aluno_ficha.php');
        }
    }

    // Processa o eventual upload da fotografia.
    $upload = save_uploaded_profile_photo($_FILES['photo'] ?? []);

    if ($upload['error']) {
        set_flash('error', $upload['error']);
        redirect_to('aluno_ficha.php');
    }

    $photoPath = $upload['path'] ?? ($profile['photo_path'] ?? null);

    // Quando a ação é submeter, aplica validações adicionais obrigatórias.
    if ($action === 'submit_profile') {
        if ($fullName === '' || $birthDate === '' || $contactEmail === '' || $phone === '' || $address === '' || !$photoPath) {
            if ($upload['path']) {
                delete_uploaded_file($upload['path']);
            }

            set_flash('error', 'Para submeter a ficha tens de preencher nome, data de nascimento, e-mail, telefone, morada e fotografia.');
            redirect_to('aluno_ficha.php');
        }

        if (!has_submission_limit_available($pdo, (int) current_user()['id'], 'student_profile_submission')) {
            if ($upload['path']) {
                delete_uploaded_file($upload['path']);
            }

            set_flash('error', 'Só podes submeter a ficha 5 vezes em 24 horas. Tenta novamente mais tarde.');
            redirect_to('aluno_ficha.php');
        }

        $status = 'submetida';
    } else {
        $status = 'rascunho';
    }

    // Atualiza a ficha existente ou cria uma nova, consoante o caso.
    if ($profile) {
        $resetReviewData = $status === 'submetida'
            ? [null, null, null]
            : [$profile['review_notes'] ?? null, $profile['reviewed_by'] ?? null, $profile['reviewed_at'] ?? null];

        db_execute(
            $pdo,
            'UPDATE student_profiles
             SET course_id = ?, full_name = ?, birth_date = ?, contact_email = ?, phone = ?, address = ?, photo_path = ?, notes = ?,
                 status = ?, review_notes = ?, reviewed_by = ?, reviewed_at = ?,
                 submitted_at = CASE WHEN ? = \'submetida\' THEN NOW() ELSE submitted_at END
             WHERE user_id = ?',
            [
                $courseId,
                $fullName,
                $birthDate !== '' ? $birthDate : null,
                $contactEmail,
                $phone,
                $address,
                $photoPath,
                $notes,
                $status,
                $resetReviewData[0],
                $resetReviewData[1],
                $resetReviewData[2],
                $status,
                current_user()['id'],
            ]
        );
    } else {
        db_execute(
            $pdo,
            'INSERT INTO student_profiles (user_id, course_id, full_name, birth_date, contact_email, phone, address, photo_path, notes, status, submitted_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                current_user()['id'],
                $courseId,
                $fullName,
                $birthDate !== '' ? $birthDate : null,
                $contactEmail,
                $phone,
                $address,
                $photoPath,
                $notes,
                $status,
                $status === 'submetida' ? date('Y-m-d H:i:s') : null,
            ]
        );
    }

    // Remove a fotografia anterior se tiver sido substituída por uma nova.
    if ($upload['path'] && !empty($profile['photo_path']) && $profile['photo_path'] !== $upload['path']) {
        delete_uploaded_file($profile['photo_path']);
    }

    if ($status === 'submetida') {
        register_submission_event($pdo, (int) current_user()['id'], 'student_profile_submission');
    }

    set_flash('success', $status === 'submetida' ? 'Ficha submetida com sucesso.' : 'Rascunho guardado com sucesso.');
    redirect_to('aluno_ficha.php');
}

// Recarrega a ficha já com o nome do curso para apresentação no ecrã.
$profile = db_fetch_one(
    $pdo,
    'SELECT sp.*, c.name AS course_name
     FROM student_profiles sp
     INNER JOIN courses c ON c.id = sp.course_id
     WHERE sp.user_id = ? LIMIT 1',
    [current_user()['id']]
);
$editable = !$profile || in_array($profile['status'], ['rascunho', 'rejeitada'], true);

// Renderiza o cabeçalho comum da página e o enquadramento visual da área.
render_app_page_start(
    'Gc',
    'Bem-vindo à Ficha do Aluno',
    'Nesta área podes preencher e atualizar os teus dados pessoais, adicionar a tua fotografia e submeter a ficha para validação pedagógica. Este processo é necessário para formalizar a tua integração como aluno no sistema escolar, garantindo que a tua informação está completa, correta e pronta para análise.',
    $navItems,
    'aluno_ficha.php'
);
?>
<section class="app-panel">
    <!-- Resumo do estado atual da ficha e das condições de acesso às restantes áreas. -->
    <div class="app-panel__header">
        <div>
            <h2>Estado atual</h2>
            <p><strong>Só poderás aceder à matrícula e às notas depois de preencheres a ficha e de esta ser aprovada.</strong></p>
            <p><?= $profile ? 'A tua ficha já existe e podes acompanhar o estado abaixo.' : 'Ainda não tens ficha criada. Começa por guardar um rascunho.' ?></p>
            <p>* = Campos obrigatórios</p>
        </div>
    </div>

    <div class="review-profile-grid student-profile-summary-grid">
        <article class="app-card review-profile-card review-profile-card--details student-profile-summary-card">
            <h2>Dados submetidos</h2>
            <div class="review-profile-details">
                <p><strong>Estado atual:</strong> <span class="app-text-flow"><?= $profile ? status_badge($profile['status']) : status_badge('sem ficha') ?></span></p>
                <p><strong>Curso:</strong> <span class="app-text-flow--scroll"><?= h($profile['course_name'] ?? '-') ?></span></p>
                <p><strong>Data de nascimento:</strong> <span class="app-text-flow"><?= h((string) ($profile['birth_date'] ?? '-')) ?></span></p>
                <p><strong>Morada:</strong> <span class="app-text-flow--scroll"><?= h($profile['address'] ?? '-') ?></span></p>
                <p><strong>Data de submissão:</strong> <span class="app-text-flow"><?= h((string) ($profile['submitted_at'] ?? '-')) ?></span></p>
                <p><strong>Observações da análise:</strong> <span class="app-text-flow--scroll"><?= h($profile['review_notes'] ?? '-') ?></span></p>
            </div>
        </article>
        <article class="app-card review-profile-card review-profile-card--photo student-profile-summary-card">
            <h2>Fotografia do aluno</h2>
            <p class="helper-text">Aqui podes ver a fotografia submetida na tua ficha.</p>
            <div class="review-profile-photo-wrap">
                <?php if (!empty($profile['photo_path'])): ?>
                    <img src="<?= h($profile['photo_path']) ?>" alt="Fotografia do aluno" class="photo-preview photo-preview--modal student-profile-photo-preview">
                <?php else: ?>
                    <p class="empty-text">Ainda não existe fotografia submetida.</p>
                <?php endif; ?>
            </div>
        </article>
    </div>
</section>

<section class="app-panel">
    <!-- Formulário principal da ficha do aluno. -->
    <div class="app-panel__header">
        <div>
            <h2><?= $editable ? 'Editar ficha' : 'Ficha bloqueada' ?></h2>
            <p><?= $editable ? 'Podes guardar um rascunho ou submeter a ficha para validação.' : 'A ficha só volta a estar editável se ficar em rascunho ou rejeitada.' ?></p>
        </div>
    </div>

    <form method="post" enctype="multipart/form-data" class="app-form app-form--grid" novalidate>
        <!-- Token CSRF para proteção da submissão. -->
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <div class="app-field">
            <label for="course_id">Curso pretendido *</label>
            <select id="course_id" name="course_id" <?= $editable ? '' : 'disabled' ?>>
                <option value="">Seleciona um curso</option>
                <?php foreach ($activeCourses as $course): ?>
                    <option value="<?= (int) $course['id'] ?>" <?= ((int) ($profile['course_id'] ?? 0) === (int) $course['id']) ? 'selected' : '' ?>><?= h($course['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="app-field">
            <label for="full_name">Nome completo *</label>
            <input id="full_name" type="text" name="full_name" value="<?= h($profile['full_name'] ?? '') ?>" <?= $editable ? '' : 'disabled' ?>>
        </div>
        <div class="app-field">
            <label for="birth_date">Data de nascimento *</label>
            <input id="birth_date" type="date" name="birth_date" value="<?= h($profile['birth_date'] ?? '') ?>" <?= $editable ? '' : 'disabled' ?>>
        </div>
        <div class="app-field">
            <label for="contact_email">E-mail de contacto *</label>
            <input id="contact_email" type="email" name="contact_email" value="<?= h($profile['contact_email'] ?? current_user()['email']) ?>" <?= $editable ? '' : 'disabled' ?>>
        </div>
        <div class="app-field">
            <label for="phone">Telefone *</label>
            <input id="phone" type="text" name="phone" value="<?= h($profile['phone'] ?? '') ?>" <?= $editable ? '' : 'disabled' ?>>
        </div>
        <div class="app-field">
            <label for="address">Morada *</label>
            <input id="address" type="text" name="address" value="<?= h($profile['address'] ?? '') ?>" <?= $editable ? '' : 'disabled' ?>>
        </div>
        <div class="app-field student-profile-photo-field">
            <label for="photo">Fotografia (JPG/PNG até 2 MB) *</label>
            <input id="photo" type="file" name="photo" accept=".jpg,.jpeg,.png" <?= $editable ? '' : 'disabled' ?>>
        </div>
        <div class="app-field student-profile-notes-field">
            <label for="notes">Observações</label>
            <textarea id="notes" name="notes" <?= $editable ? '' : 'disabled' ?>><?= h($profile['notes'] ?? '') ?></textarea>
        </div>

        <?php if ($editable): ?>
            <div class="app-form__actions profile-form__actions">
                <button type="submit" name="action" value="save_draft" class="app-button app-button--ghost">Guardar rascunho</button>
                <button type="submit" name="action" value="submit_profile" class="app-button app-button--primary">Submeter ficha</button>
            </div>
        <?php endif; ?>
    </form>
</section>
<?php
// Fecha a estrutura visual comum aberta no início da página.
render_app_page_end();
