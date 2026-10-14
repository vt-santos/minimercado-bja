<?php
/**
 * MINIMERCADO BJA
 * Script de instalacao inicial.
 *
 * Execute este arquivo UMA UNICA VEZ pelo navegador, apos importar o
 * arquivo database/schema.sql, para criar o usuario administrador padrao.
 *
 * Apos a execucao, por seguranca, apague ou renomeie este arquivo.
 */

require_once __DIR__ . '/config/database.php';

$pdo = Database::conectar();

$usuarioPadrao = 'admin';
$senhaPadrao = 'admin123';

$consulta = $pdo->prepare('SELECT id FROM usuarios WHERE usuario = :usuario');
$consulta->execute(['usuario' => $usuarioPadrao]);

if ($consulta->fetch()) {
    echo 'O usuario administrador ja existe. Nenhuma acao foi necessaria. Por seguranca, apague este arquivo.';
    exit;
}

$hash = password_hash($senhaPadrao, PASSWORD_BCRYPT);

$insercao = $pdo->prepare('INSERT INTO usuarios (nome, usuario, senha_hash) VALUES (:nome, :usuario, :senha_hash)');
$insercao->execute([
    'nome' => 'Administrador',
    'usuario' => $usuarioPadrao,
    'senha_hash' => $hash,
]);

echo 'Usuario administrador criado com sucesso.<br>';
echo 'Usuario: admin<br>';
echo 'Senha: admin123<br><br>';
if (@unlink(__FILE__)) {
    echo 'Este instalador foi apagado automaticamente (seguranca). ';
} else {
    echo '<strong>Apague o arquivo instalar.php agora (gerenciador de arquivos da hospedagem). ';
}
echo 'Entre no sistema e troque a senha em Configuracoes.</strong>';
