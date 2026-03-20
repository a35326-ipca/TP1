<?php
require_once 'app_ui.php';

require_aluno();

$navItems = [
    app_nav_item('hub_aluno.php', 'Hub', 'home'),
    app_nav_item('perfil.php', 'Perfil', 'account'),
    app_nav_item('aluno_ficha.php', 'Ficha', 'profile'),
    app_nav_item('aluno_matricula.php', "Matr\u{00ED}cula", 'enrollment-student'),
    app_nav_item('aluno_notas.php', 'Notas', 'grades'),
];

$navItems = build_student_nav_items($pdo, (int) current_user()['id']);
$studentAccessUnlocked = student_access_unlocked($pdo, (int) current_user()['id']);
$profileCardClasses = 'app-card hub-card hub-card--compact';

if (!$studentAccessUnlocked) {
    $profileCardClasses .= ' hub-card--full hub-card--centered';
}

$profile = db_fetch_one($pdo, 'SELECT status FROM student_profiles WHERE user_id = ? LIMIT 1', [current_user()['id']]);
$lastRequest = db_fetch_one(
    $pdo,
    'SELECT status FROM enrollment_requests WHERE user_id = ? ORDER BY created_at DESC LIMIT 1',
    [current_user()['id']]
);

$cards = [
    [
        'label' => 'Estado da ficha',
        'value' => $profile['status'] ?? 'sem ficha',
        'hint' => "Situa\u{00E7}\u{00E3}o atual da tua ficha.",
    ],
    [
        'label' => 'Pedidos criados',
        'value' => (int) db_fetch_value($pdo, 'SELECT COUNT(*) FROM enrollment_requests WHERE user_id = ?', [current_user()['id']]),
        'hint' => "Total de matr\u{00ED}culas submetidas por ti.",
    ],
    [
        'label' => "\u{00DA}ltimo pedido",
        'value' => $lastRequest['status'] ?? 'sem pedidos',
        'hint' => "Estado mais recente da tua matr\u{00ED}cula.",
    ],
];

render_app_page_start(
    'Gc',
    'Bem-vindo ao Hub do Aluno',
    "Nesta \u{00E1}rea podes acompanhar a tua ficha acad\u{00E9}mica, atualizar os teus dados e fazer o upload da tua fotografia. Tamb\u{00E9}m te permite criar e acompanhar pedidos de matr\u{00ED}cula de forma simples e organizada. Este site foi pensado para funcionar principalmente em ecr\u{00E3}s de desktop ou port\u{00E1}til.",
    $navItems,
    'hub_aluno.php'
);

render_metric_cards($cards);
?>
<section class="hub-grid hub-grid--compact">
    <article class="<?= h($profileCardClasses) ?>">
        <div class="hub-card__body">
            <h2>Ficha do aluno</h2>
            <p>Preenche e atualiza os teus dados pessoais e de contacto, adiciona a tua fotografia e submete a ficha para valida&ccedil;&atilde;o, garantindo que a tua informa&ccedil;&atilde;o est&aacute; completa e correta.</p>
        </div>
        <div class="hub-card__actions">
            <a href="aluno_ficha.php" class="app-link">Abrir ficha</a>
        </div>
    </article>
    <?php if ($studentAccessUnlocked): ?>
<article class="app-card hub-card hub-card--compact">
        <div class="hub-card__body">
            <h2>Pedido de matr&iacute;cula</h2>
            <p>Seleciona um curso dispon&iacute;vel, cria o teu pedido de matr&iacute;cula e acompanha todo o processo de decis&atilde;o por parte do funcion&aacute;rio. Permite-te gerir as tuas candidaturas de forma simples, organizada e sempre atualizada.</p>
        </div>
        <div class="hub-card__actions">
            <a href="aluno_matricula.php" class="app-link">Abrir pedidos</a>
        </div>
    </article>
    <article class="app-card hub-card hub-card--compact hub-card--full hub-card--centered">
        <div class="hub-card__body">
            <h2>Notas finais</h2>
            <p>Consulta de forma simples as classifica&ccedil;&otilde;es finais j&aacute; lan&ccedil;adas nas UCs em que est&aacute;s associado, sem alterar qualquer dado acad&eacute;mico.</p>
        </div>
        <div class="hub-card__actions">
            <a href="aluno_notas.php" class="app-link">Abrir notas</a>
        </div>
    </article>
    <?php endif; ?>
</section>
<?php
render_app_page_end();
