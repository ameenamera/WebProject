<?php
require_once __DIR__ . '/../includes/auth.php';
deconnexion();
session_start();
flash('Vous avez été déconnecté.', 'info');
redirect('login.php');
