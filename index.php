<?php
// =====================================================================
// VIP Imports — Ponto de entrada
// =====================================================================
session_start();

$destino = isset($_SESSION['usuario'])
    ? 'app/view/painel.php'
    : 'app/view/login.php';

header('Location: ' . $destino);
exit;
