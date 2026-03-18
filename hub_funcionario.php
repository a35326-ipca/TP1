<?php
require_once 'app_ui.php';

require_funcionario();

$navItems = [
    app_nav_item('hub_funcionario.php', 'Hub', 'home'),
    app_nav_item('perfil.php', 'Perfil', 'account'),
    app_nav_item('funcionario_pedidos.php', 'Matrículas', 'enrollment'),
    app_nav_item('funcionario_pautas.php', 'Pautas', 'grades'),
];

$cards = [
    [
        'label' => 'Pedidos por decidir',
        'value' => (int) db_fetch_value($pdo, "SELECT COUNT(*) FROM enrollment_requests WHERE status = 'pendente'"),
        'hint' => 'Pedidos de matrícula submetidos que ainda aguardam validação.',
    ],
    [
        'label' => 'Pautas criadas',
        'value' => (int) db_fetch_value($pdo, 'SELECT COUNT(*) FROM grade_sheets'),
        'hint' => 'Total de pautas já criadas para gestão académica.',
    ],
    [
        'label' => 'Alunos aprovados',
        'value' => (int) db_fetch_value($pdo, "SELECT COUNT(DISTINCT user_id) FROM enrollment_requests WHERE status = 'aprovado'"),
        'hint' => 'Número de alunos com pedidos já aprovados no sistema.',
    ],
];

render_app_page_start(
    'Gc',
    'Bem-vindo ao Hub',
    'Área de trabalho do funcionário que permite acompanhar os pedidos de matrícula, validar decisões e gerir as pautas de avaliação. A partir desta página, é possível aceder rapidamente às principais funcionalidades do sistema, facilitando a organização das tarefas e garantindo um acompanhamento mais simples, claro e eficiente de todo o processo. Este site foi pensado para funcionar principalmente em ecrãs de desktop ou portátil.',
    $navItems,
    'hub_funcionario.php'
);

render_metric_cards($cards);
?>
<section class="hub-grid hub-grid--compact">
    <article class="app-card hub-card hub-card--compact">
        <div class="hub-card__body">
            <h2>Pedidos de matrícula</h2>
            <p>Área onde é possível analisar os pedidos submetidos pelos alunos, registar decisões e acompanhar o histórico de cada processo de forma organizada.</p>
        </div>
        <div class="hub-card__actions">
            <a href="funcionario_pedidos.php" class="app-link">Abrir pedidos</a>
        </div>
    </article>
    <article class="app-card hub-card hub-card--compact">
        <div class="hub-card__body">
            <h2>Pautas de avaliação</h2>
            <p>Área que permite criar e gerir pautas por UC, ano letivo e época, facilitando a organização dos alunos e o registo das classificações finais.</p>
        </div>
        <div class="hub-card__actions">
            <a href="funcionario_pautas.php" class="app-link">Abrir pautas</a>
        </div>
    </article>
</section>
<?php
render_app_page_end();
