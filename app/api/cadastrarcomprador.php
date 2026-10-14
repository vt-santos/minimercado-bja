<?php
/**
 * Cadastro rapido de comprador (usado na tela Registrar Compra).
 * Recebe POST com csrf_token, nome, equipe_id, telefone, responsavel_nome, telefone_responsavel.
 * Responde em JSON com os mesmos campos da busca de compradores.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/funcoes.php';
require_once __DIR__ . '/../includes/autenticacao.php';

header('Content-Type: application/json; charset=utf-8');

function responder(int $codigo, array $dados): void
{
    http_response_code($codigo);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!usuarioLogado()) {
    responder(401, ['erro' => 'Nao autenticado']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(405, ['erro' => 'Use POST']);
}
if (!validarTokenCsrf($_POST['csrf_token'] ?? '')) {
    responder(403, ['erro' => 'Sessao expirada. Recarregue a pagina.']);
}

$nome = trim(preg_replace('/\s+/', ' ', $_POST['nome'] ?? ''));
$equipeId = (int)($_POST['equipe_id'] ?? 0);
$telefone = trim($_POST['telefone'] ?? '');
$responsavel = trim($_POST['responsavel_nome'] ?? '');
$telefoneResponsavel = trim($_POST['telefone_responsavel'] ?? '');

if (mb_strlen($nome, 'UTF-8') < 3) {
    responder(422, ['erro' => 'Informe o nome completo.']);
}
if ($equipeId <= 0) {
    responder(422, ['erro' => 'Escolha o circulo ou a equipe.']);
}

$pdo = Database::conectar();

$consultaEquipe = $pdo->prepare('SELECT id, nome, cor FROM equipes WHERE id = :id');
$consultaEquipe->execute(['id' => $equipeId]);
$equipe = $consultaEquipe->fetch();
if (!$equipe) {
    responder(422, ['erro' => 'Equipe invalida.']);
}

// Evita duplicar: mesmo nome ja cadastrado devolve o cadastro existente
$existente = $pdo->prepare('SELECT id FROM compradores WHERE LOWER(nome) = LOWER(:nome) LIMIT 1');
$existente->execute(['nome' => $nome]);
$idExistente = $existente->fetch()['id'] ?? null;

if ($idExistente) {
    $id = (int)$idExistente;
    $jaExistia = true;
} else {
    $pdo->prepare(
        'INSERT INTO compradores (nome, equipe_id, telefone, responsavel_nome, telefone_responsavel, ativo)
         VALUES (:nome, :equipe_id, :telefone, :responsavel_nome, :telefone_responsavel, 1)'
    )->execute([
        'nome' => $nome,
        'equipe_id' => $equipeId,
        'telefone' => $telefone !== '' ? $telefone : null,
        'responsavel_nome' => $responsavel !== '' ? $responsavel : null,
        'telefone_responsavel' => $telefoneResponsavel !== '' ? $telefoneResponsavel : null,
    ]);
    $id = (int)$pdo->lastInsertId();
    $jaExistia = false;
}

$consulta = $pdo->prepare(
    'SELECT cp.id, cp.nome, cp.equipe_id, cp.responsavel_nome, cp.telefone_responsavel,
            eq.nome AS equipe_nome, eq.cor AS equipe_cor
     FROM compradores cp LEFT JOIN equipes eq ON eq.id = cp.equipe_id WHERE cp.id = :id'
);
$consulta->execute(['id' => $id]);
$comprador = $consulta->fetch();
$comprador['ja_existia'] = $jaExistia;

responder(200, $comprador);
