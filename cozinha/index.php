<?php
// Entrada do painel (/cozinha/ e o app "Cozinha"): quem já entrou vai direto para Pagamentos.
// Quem não entrou vê a tela de login, que fica em enquetes.php (o formulário envia para esta mesma URL).
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/admin_auth.php';

if (admin_logged_in() && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: pedidos');
    exit;
}
require __DIR__ . '/enquetes.php';
