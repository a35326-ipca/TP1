<?php
// Página de saída da aplicação.
require_once 'auth.php';

// Termina a sessão atual do utilizador.
logout_current_user();
// Define a mensagem de confirmação a apresentar após o redirecionamento.
set_flash('success', 'Sessão terminada com sucesso.');
// Envia o utilizador de volta para a página de login.
redirect_to('login.php');
