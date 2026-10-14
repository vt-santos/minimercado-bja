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
$termo = trim($_GET['termo'] ?? '');

$consulta = $pdo->prepare(
    'SELECT cp.id, cp.nome, cp.equipe_id, cp.responsavel_nome, cp.telefone_responsavel, eq.nome AS equipe_nome, eq.cor AS equipe_cor
     FROM compradores cp
     LEFT JOIN equipes eq ON eq.id = cp.equipe_id
     WHERE cp.ativo = 1 AND cp.nome LIKE :termo
     ORDER BY cp.nome
     LIMIT 15'
);
$consulta->execute(['termo' => '%' . $termo . '%']);

echo json_encode($consulta->fetchAll(), JSON_UNESCAPED_UNICODE);
