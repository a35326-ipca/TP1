<?php
require_once 'auth.php';

function app_nav_item(string $href, string $label, string $icon): array
{
    return [
        'href' => $href,
        'label' => $label,
        'icon' => $icon,
    ];
}

function app_icon(string $icon): string
{
    return match ($icon) {
        'home' => '<path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />',
        'profile' => '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />',
        'account' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />',
        'users' => '<path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />',
        'courses' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />',
        'units' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />',
        'plan' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />',
        'requests' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />',
        'enrollment' => '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />',
        'enrollment-student' => '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />',
        'grades' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 0 1-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-7.5A1.125 1.125 0 0 1 12 18.375m9.75-12.75c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125m19.5 0v1.5c0 .621-.504 1.125-1.125 1.125M2.25 5.625v1.5c0 .621.504 1.125 1.125 1.125m0 0h17.25m-17.25 0h7.5c.621 0 1.125.504 1.125 1.125M3.375 8.25c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m17.25-3.75h-7.5c-.621 0-1.125.504-1.125 1.125m8.625-1.125c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5M12 10.875v-1.5m0 1.5c0 .621-.504 1.125-1.125 1.125M12 10.875c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125M13.125 12h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125M20.625 12c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5M12 14.625v-1.5m0 1.5c0 .621-.504 1.125-1.125 1.125M12 14.625c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125m0 1.5v-1.5m0 0c0-.621.504-1.125 1.125-1.125m0 0h7.5" />',
        'review' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />',
        default => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2" />',
    };
}

function render_app_page_start(
    string $title,
    string $pageTitle,
    string $pageDescription,
    array $navItems,
    string $activeHref,
    array $headerActions = [],
    ?string $eyebrow = null
): void {
    $user = current_user();
    $flash = get_flash();
    $appCssVersion = (string) filemtime(__DIR__ . DIRECTORY_SEPARATOR . 'statics' . DIRECTORY_SEPARATOR . 'app.css');
    $toastCssVersion = (string) filemtime(__DIR__ . DIRECTORY_SEPARATOR . 'statics' . DIRECTORY_SEPARATOR . 'toasts.css');
    ?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="statics/img/logo.png">
    <link rel="stylesheet" href="statics/app.css?v=<?= h($appCssVersion) ?>">
    <link rel="stylesheet" href="statics/toasts.css?v=<?= h($toastCssVersion) ?>">
    <title><?= h($title) ?></title>
</head>
<body class="app-body">
    <div class="toast-stack" id="toastStack" aria-live="polite" aria-atomic="false"></div>
    <script id="flashData" type="application/json"><?= json_encode($flash, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?></script>

    <div class="app-shell">
        <aside class="app-dock" aria-label="Navegação principal">
            <nav class="app-dock__nav">
                <?php foreach ($navItems as $item): ?>
                    <a href="<?= h($item['href']) ?>" class="app-dock__link <?= $item['href'] === $activeHref ? 'is-active' : '' ?>" aria-label="<?= h($item['label']) ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
                            <?= app_icon($item['icon']) ?>
                        </svg>
                        <span><?= h($item['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
            <a href="logout.php" class="app-dock__link app-dock__link--logout" aria-label="Terminar sessão">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                </svg>
                <span>Sair</span>
            </a>
        </aside>

        <main class="app-main">
            <header class="app-header">
                <div>
                    <span class="app-header__eyebrow"><?= h($eyebrow ?? role_label($user['role'] ?? '')) ?></span>
                    <h1><?= h($pageTitle) ?></h1>
                    <p><?= h($pageDescription) ?></p>
                </div>
                <?php if ($headerActions): ?>
                    <div class="app-header__actions">
                        <?php foreach ($headerActions as $action): ?>
                            <a
                                href="<?= h($action['href']) ?>"
                                class="app-button <?= h($action['class'] ?? 'app-button--ghost') ?>"
                                aria-label="<?= h($action['label']) ?>"
                            >
                                <?php if (!empty($action['icon_svg'])): ?>
                                    <?= $action['icon_svg'] ?>
                                <?php else: ?>
                                    <?= h($action['label']) ?>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </header>
            <div class="app-content">
    <?php
}

function render_app_page_end(): void
{
    ?>
            </div>
        </main>
    </div>
    <div id="appModalRoot"></div>
    <script src="statics/toasts.js"></script>
    <script>
        (function () {
            const modalRoot = document.getElementById('appModalRoot');

            if (!modalRoot) {
                return;
            }

            let activeDynamicModal = null;

            function syncBodyModalState() {
                const hasOpenModal = document.querySelector('.app-modal.is-open');
                document.body.classList.toggle('app-modal-open', Boolean(hasOpenModal));
            }

            function closeDynamicModal() {
                if (!activeDynamicModal) {
                    return;
                }

                activeDynamicModal.remove();
                activeDynamicModal = null;
                syncBodyModalState();
            }

            async function openDynamicModal(url) {
                try {
                    const response = await fetch(url, {
                        headers: {
                            'X-Requested-With': 'fetch',
                            'Accept': 'text/html',
                        },
                    });

                    if (!response.ok) {
                        window.location.href = url;
                        return;
                    }

                    const html = await response.text();
                    modalRoot.innerHTML = html;
                    activeDynamicModal = modalRoot.querySelector('.app-modal');

                    if (!activeDynamicModal) {
                        window.location.href = url;
                        return;
                    }

                    syncBodyModalState();

                    const closeButton = activeDynamicModal.querySelector('.app-modal__close');
                    if (closeButton) {
                        closeButton.focus();
                    }
                } catch (error) {
                    window.location.href = url;
                }
            }

            document.addEventListener('click', function (event) {
                const trigger = event.target.closest('[data-modal-url]');

                if (trigger) {
                    if (
                        event.defaultPrevented
                        || event.button !== 0
                        || event.metaKey
                        || event.ctrlKey
                        || event.shiftKey
                        || event.altKey
                    ) {
                        return;
                    }

                    event.preventDefault();
                    openDynamicModal(trigger.getAttribute('data-modal-url'));
                    return;
                }

                const closeTrigger = event.target.closest('[data-modal-close]');

                if (closeTrigger && activeDynamicModal && modalRoot.contains(closeTrigger)) {
                    event.preventDefault();
                    closeDynamicModal();
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && activeDynamicModal) {
                    closeDynamicModal();
                }
            });
        })();
    </script>
</body>
</html>
    <?php
}

function render_metric_cards(array $cards): void
{
    if ($cards === []) {
        return;
    }
    ?>
    <section class="metric-grid">
        <?php foreach ($cards as $card): ?>
            <article class="metric-card">
                <span class="metric-card__label"><?= h($card['label']) ?></span>
                <strong class="metric-card__value"><?= h((string) $card['value']) ?></strong>
                <?php if (!empty($card['hint'])): ?>
                    <p class="metric-card__hint"><?= h($card['hint']) ?></p>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </section>
    <?php
}

function status_badge(string $status): string
{
    $class = match ($status) {
        'ativo', 'aprovado', 'aprovada' => 'success',
        'inativo', 'rejeitado', 'rejeitada' => 'danger',
        'submetida', 'pendente', 'rascunho' => 'warning',
        default => 'neutral',
    };

    return '<span class="status-badge status-badge--' . $class . '">' . h($status) . '</span>';
}
