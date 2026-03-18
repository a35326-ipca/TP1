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

$cards = [
    [
        'label' => 'Utilizadores',
        'value' => (int) db_fetch_value($pdo, 'SELECT COUNT(*) FROM users'),
        'hint' => 'Total de contas criadas no sistema.',
    ],
    [
        'label' => 'Cursos ativos',
        'value' => (int) db_fetch_value($pdo, 'SELECT COUNT(*) FROM courses WHERE is_active = 1'),
        'hint' => 'Cursos abertos para candidatura.',
    ],
    [
        'label' => 'UCs',
        'value' => (int) db_fetch_value($pdo, 'SELECT COUNT(*) FROM units'),
        'hint' => 'UCs registadas na base académica.',
    ],
    [
        'label' => 'Entradas no plano',
        'value' => (int) db_fetch_value($pdo, 'SELECT COUNT(*) FROM study_plan'),
        'hint' => 'Ligações entre curso, UC, ano e semestre.',
    ],
    [
        'label' => 'Fichas por validar',
        'value' => (int) db_fetch_value($pdo, "SELECT COUNT(*) FROM student_profiles WHERE status = 'submetida'"),
        'hint' => 'Fichas submetidas a aguardar decisão.',
    ],
];

render_app_page_start(
    'Gc',
    'Bem-vindo ao Hub',
    'Centro de controlo pedagógico que disponibiliza acesso completo às diferentes áreas de gestão do sistema. Através desta área, é possível supervisionar, organizar e administrar as principais funcionalidades da plataforma, permitindo uma gestão centralizada e eficiente de todos os recursos e processos associados ao funcionamento do sistema. Este site foi pensado para funcionar principalmente em ecrãs de desktop ou portátil.',
    $navItems,
    'hub_gestor.php'
);

render_metric_cards($cards);
?>
<section class="hub-grid">
    <article class="app-card hub-card">
        <div class="hub-card__body">
            <h2>Utilizadores e cargos</h2>
            <p>Área destinada à gestão dos utilizadores do sistema, incluindo alunos, funcionários e gestores, permitindo criar, editar, alterar cargos e remover contas quando necessário.</p>
        </div>
        <div class="hub-card__actions">
            <a href="gestor_utilizadores.php" class="app-link">Abrir gestão de utilizadores</a>
        </div>
    </article>
    <article class="app-card hub-card">
        <div class="hub-card__body">
            <h2>Cursos</h2>
            <p>Área que permite manter a oferta formativa organizada, garantindo que os cursos disponíveis no sistema estão ativos, consistentes e prontos para serem utilizados.</p>
        </div>
        <div class="hub-card__actions">
            <a href="gestor_cursos.php" class="app-link">Gerir cursos</a>
        </div>
    </article>
    <article class="app-card hub-card">
        <div class="hub-card__body">
            <h2>Unidades curriculares</h2>
            <p>Área destinada à atualização da base de Unidades Curriculares (UCs), utilizadas nos planos de estudo, nas fichas das disciplinas e nas pautas de avaliação do sistema.</p>
        </div>
        <div class="hub-card__actions">
            <a href="gestor_ucs.php" class="app-link">Gerir UCs</a>
        </div>
    </article>
    <article class="app-card hub-card">
        <div class="hub-card__body">
            <h2>Plano de estudos</h2>
            <p>Área que permite associar Unidades Curriculares (UCs) aos diferentes cursos, organizando-as por ano e semestre, evitando duplicações e garantindo uma estrutura académica coerente no sistema.</p>
        </div>
        <div class="hub-card__actions">
            <a href="gestor_plano.php" class="app-link">Configurar plano</a>
        </div>
    </article>
    <article class="app-card hub-card hub-card--compact hub-card--full hub-card--centered">
        <div class="hub-card__body">
            <h2>Fichas dos alunos</h2>
            <p>Área que permite consultar, aprovar ou rejeitar fichas submetidas, adicionando observações e mantendo um registo de auditoria para garantir transparência e controlo no processo.</p>
        </div>
        <div class="hub-card__actions">
            <a href="gestor_fichas.php" class="app-link">Rever fichas</a>
        </div>
    </article>
</section>
<?php
render_app_page_end();
