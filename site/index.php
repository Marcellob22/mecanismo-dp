<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/dados.php';

if (!existeAlgumUsuario()) {
    header('Location: setup.php');
    exit;
}
header('Location: ' . (usuarioLogado() ? 'dashboard.php' : 'login.php'));
exit;
