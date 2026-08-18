<?php
require_once __DIR__ . '/../includes/auth.php';
redirect(estConnecte() ? 'dashboard.php' : 'login.php');
