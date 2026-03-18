<?php
require_once 'app_ui.php';

require_aluno();

$navItems = [
    app_nav_item('hub_aluno.php', 'Hub', 'home'),
    app_nav_item('perfil.php', 'Perfil', 'account'),
    app_nav_item('aluno_ficha.php', 'Ficha', 'profile'),
    app_nav_item('aluno_matricula.php', 'Matrícula', 'enrollment-student'),
    app_nav_item('aluno_notas.php', 'Notas', 'grades'),
];

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
        'hint' => 'Situação atual da tua ficha.',
    ],
    [
        'label' => 'Pedidos criados',
        'value' => (int) db_fetch_value($pdo, 'SELECT COUNT(*) FROM enrollment_requests WHERE user_id = ?', [current_user()['id']]),
        'hint' => 'Total de matrículas submetidas por ti.',
    ],
    [
        'label' => 'Último pedido',
        'value' => $lastRequest['status'] ?? 'sem pedidos',
        'hint' => 'Estado mais recente da tua matrícula.',
    ],
];

render_app_page_start(
    'Gc',
    'Bem-vindo ao Hub do Aluno',
    'Nesta área podes acompanhar a tua ficha académica, atualizar os teus dados e fazer o upload da tua fotografia. Também te permite criar e acompanhar pedidos de matrícula de forma simples e organizada. Este site foi pensado para funcionar principalmente em ecrãs de desktop ou portátil.',
    $navItems,
    'hub_aluno.php'
);

render_metric_cards($cards);
?>
<section class="hub-grid hub-grid--compact">
    <article class="app-card hub-card hub-card--compact">
        <div class="hub-card__body">
            <h2>Ficha do aluno</h2>
            <p>Preenche e atualiza os teus dados pessoais e de contacto, adiciona a tua fotografia e submete a ficha para validação, garantindo que a tua informação está completa e correta.</p>
        </div>
        <div class="hub-card__actions">
            <a href="aluno_ficha.php" class="app-link">Abrir ficha</a>
        </div>
    </article>
    <article class="app-card hub-card hub-card--compact">
        <div class="hub-card__body">
            <h2>Pedido de matrícula</h2>
            <p>Seleciona um curso disponível, cria o teu pedido de matrícula e acompanha todo o processo de decisão por parte do funcionário. Permite-te gerir as tuas candidaturas de forma simples, organizada e sempre atualizada.</p>
        </div>
        <div class="hub-card__actions">
            <a href="aluno_matricula.php" class="app-link">Abrir pedidos</a>
        </div>
    </article>
    <article class="app-card hub-card hub-card--compact hub-card--full hub-card--centered">
        <div class="hub-card__body">
            <h2>Notas finais</h2>
            <p>Consulta de forma simples as classificações finais já lançadas nas UCs em que estás associado, sem alterar qualquer dado académico.</p>
        </div>
        <div class="hub-card__actions">
            <a href="aluno_notas.php" class="app-link">Abrir notas</a>
        </div>
    </article>
</section>
<?php
render_app_page_end();
