<?php
/**
 * MINIMERCADO BJA
 * Controle de autenticacao e protecao de paginas
 */

require_once __DIR__ . '/../config/config.php';

function usuarioLogado(): bool
{
    return !empty($_SESSION['usuario_id']);
}

function exigirLogin(): void
{
    if (!usuarioLogado()) {
        redirecionar(URL_BASE . 'login.php');
    }
}

function nomeUsuarioLogado(): string
{
    return $_SESSION['usuario_nome'] ?? 'Administrador';
}
