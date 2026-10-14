<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/autenticacao.php';

header('Content-Type: application/json; charset=utf-8');

if (!usuarioLogado()) {
    http_response_code(401);
    echo json_encode(['erro' => 'Nao autenticado']);
    exit;
}

$pdo = Database::conectar();
$produtos = $pdo->query('SELECT id, nome, descricao, preco, categoria_id FROM produtos WHERE ativo = 1 AND preco > 0 ORDER BY categoria_id, nome')->fetchAll();

echo json_encode($produtos, JSON_UNESCAPED_UNICODE);
