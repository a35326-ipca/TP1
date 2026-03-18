<?php
require_once 'auth.php';

if (is_logged_in()) {
    redirect_to(dashboard_path_for_current_user());
}

redirect_to('login.php');
