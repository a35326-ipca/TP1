<?php
// Página de registo de novas contas no sistema.
require_once 'auth.php';

// Se o utilizador já estiver autenticado, segue diretamente para o respetivo hub.
if (is_logged_in()) {
    redirect_to(dashboard_path_for_current_user());
}

// Processa a submissão do formulário de registo.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf('register.php');

    // Recolhe e normaliza os dados introduzidos.
    $name = trim($_POST['name'] ?? '');
    $email = normalize_email($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $oldInput = [
        'name' => $name,
        'email' => $email,
    ];

    if ($name === '' || $email === '' || $password === '' || $confirmPassword === '') {
        set_old_input($oldInput);
        set_flash('error', 'Preenche todos os campos do registo.');
        redirect_to('register.php');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        set_old_input($oldInput);
        set_flash('error', 'O e-mail introduzido não é válido.');
        redirect_to('register.php');
    }

    if (mb_strlen($name) < 3 || mb_strlen($name) > 120) {
        set_old_input($oldInput);
        set_flash('error', 'O nome deve ter entre 3 e 120 caracteres.');
        redirect_to('register.php');
    }

    if (strlen($password) < 8) {
        set_old_input($oldInput);
        set_flash('error', 'A palavra-passe deve ter pelo menos 8 caracteres.');
        redirect_to('register.php');
    }

    if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/\d/', $password)) {
        set_old_input($oldInput);
        set_flash('error', 'Usa uma palavra-passe com maiúsculas, minúsculas e números.');
        redirect_to('register.php');
    }

    if ($password !== $confirmPassword) {
        set_old_input($oldInput);
        set_flash('error', 'As palavras-passe não coincidem.');
        redirect_to('register.php');
    }

    $exists = db_fetch_one($pdo, 'SELECT id FROM users WHERE email = ? LIMIT 1', [$email]);

    if ($exists) {
        set_old_input($oldInput);
        set_flash('error', 'Já existe uma conta com esse e-mail.');
        redirect_to('register.php');
    }

    if (!create_account($pdo, $name, $email, $password)) {
        set_old_input($oldInput);
        set_flash('error', 'Não foi possível criar a conta.');
        redirect_to('register.php');
    }

    if (!login_user($pdo, $email, $password)) {
        set_flash('success', 'Conta criada com sucesso. Já podes iniciar sessão.');
        redirect_to('login.php');
    }

    set_flash('success', 'Conta criada com sucesso.');
    redirect_to('hub_aluno.php');
}

// Recupera mensagens flash e valores antigos do formulário para a interface.
$flash = get_flash();
$oldInput = get_old_input();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="statics/img/logo.png">
    <link rel="stylesheet" href="statics/toasts.css">
    <title>Gc</title>
    <style>
        /* Variáveis e base visual da página de registo. */
        :root {
            --text: #172217;
            --muted: #607060;
            --line: #d9e4d9;
            --green: #1f7a39;
            --green-soft: #e8f7ec;
            --red: #b42318;
            --red-soft: #fdeceb;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: radial-gradient(circle at top left, #ffffff 0%, #eef6ee 45%, #e4efe4 100%);
            color: var(--text);
        }
        .card {
            width: min(440px, 100%);
            padding: 28px;
            border: 1px solid var(--line);
            border-radius: 24px;
            background: rgba(255, 255, 255, 0.96);
            box-shadow: 0 18px 48px rgba(21, 41, 24, 0.08);
        }
        .brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 18px;
        }
        .brand img {
            width: 34px;
            height: 34px;
            object-fit: contain;
        }
        .brand span {
            font-weight: 700;
            color: var(--green);
        }
        h1 { margin: 0 0 10px; text-align: center; }
        p { margin: 0 0 18px; color: var(--muted); line-height: 1.6; text-align: center; }
        label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 14px; }
        .field { margin-bottom: 14px; }
        .password-field {
            position: relative;
        }
        input {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #cfe0d1;
            border-radius: 12px;
            font: inherit;
        }
        .password-field input {
            padding-right: 52px;
        }
        input[type="password"]::-ms-reveal,
        input[type="password"]::-ms-clear {
            display: none;
        }
        input:focus {
            outline: none;
            border-color: #8ab898;
        }
        .toggle-password {
            position: absolute;
            top: 50%;
            right: 14px;
            transform: translateY(-50%);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border: 0;
            padding: 0;
            background: transparent;
            color: #607060;
            cursor: pointer;
        }
        .toggle-password:hover {
            color: #1f7a39;
        }
        .toggle-password svg {
            width: 18px;
            height: 18px;
        }
        .btn {
            width: 100%;
            border: 0;
            border-radius: 12px;
            padding: 13px 16px;
            background: var(--green);
            color: #fff;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.18s ease;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        .links { margin-top: 16px; text-align: center; }
        .links a { color: var(--green); text-decoration: none; font-weight: 700; }
        @media (max-width: 640px) {
            body {
                padding: 16px;
                align-items: start;
            }
            .card {
                width: 100%;
                padding: 22px 18px;
                border-radius: 20px;
            }
            h1 {
                font-size: 2rem;
            }
            p {
                font-size: 15px;
                margin-bottom: 16px;
            }
            input, .btn {
                padding: 12px 13px;
            }
        }
        @media (max-width: 380px) {
            .card {
                padding: 18px 14px;
            }
            h1 {
                font-size: 1.8rem;
            }
            label {
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
    <!-- Estrutura para apresentação de mensagens temporárias. -->
    <div class="toast-stack" id="toastStack" aria-live="polite" aria-atomic="false"></div>
    <script id="flashData" type="application/json"><?= json_encode($flash, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?></script>

    <main class="card">
        <!-- Formulário principal de criação de conta. -->
        <h1>Criar conta</h1>
        <p>Crie a sua conta para ter acesso às funcionalidades da plataforma.</p>

        <form method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <div class="field">
                <label for="name">Nome</label>
                <input id="name" type="text" name="name" value="<?= old_value($oldInput, 'name') ?>" autocomplete="name" required>
            </div>
            <div class="field">
                <label for="email">E-mail</label>
                <input id="email" type="email" name="email" value="<?= old_value($oldInput, 'email') ?>" autocomplete="email" required>
            </div>
            <div class="field">
                <label for="password">Palavra-passe</label>
                <div class="password-field">
                    <input id="password" type="password" name="password" autocomplete="new-password" required>
                    <button class="toggle-password" type="button" data-toggle-password="password" aria-label="Mostrar palavra-passe">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                        </svg>
                    </button>
                </div>
            </div>
            <div class="field">
                <label for="confirm_password">Confirmar palavra-passe</label>
                <div class="password-field">
                    <input id="confirm_password" type="password" name="confirm_password" autocomplete="new-password" required>
                    <button class="toggle-password" type="button" data-toggle-password="confirm_password" aria-label="Mostrar palavra-passe">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                        </svg>
                    </button>
                </div>
            </div>
            <button class="btn" type="submit">Criar conta</button>
        </form>

        <div class="links">
            <a href="login.php">Voltar ao login</a>
        </div>
    </main>
    <script>
        // Ícones usados para alternar a visibilidade dos campos de palavra-passe.
        const eyeIcon = `
            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
            </svg>`;
        const eyeOffIcon = `
            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
            </svg>`;

        // Liga os botões de mostrar/ocultar palavra-passe aos respetivos campos.
        document.querySelectorAll('[data-toggle-password]').forEach((button) => {
            button.addEventListener('click', () => {
                const input = document.getElementById(button.dataset.togglePassword);
                const isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';
                button.innerHTML = isPassword ? eyeIcon : eyeOffIcon;
                button.setAttribute('aria-label', isPassword ? 'Ocultar palavra-passe' : 'Mostrar palavra-passe');
            });
        });
    </script>
    <script src="statics/toasts.js"></script>
</body>
</html>
