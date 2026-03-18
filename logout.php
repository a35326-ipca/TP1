<?php
require_once 'auth.php';

logout_current_user();
set_flash('success', 'Sessão terminada com sucesso.');
redirect_to('login.php');
