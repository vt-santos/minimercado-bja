<?php
/**
 * MINIMERCADO BJA
 * Atualizacao automatica da estrutura do banco.
 *
 * Bancos criados com versoes antigas do schema.sql nao tinham as colunas
 * de cor da equipe e de responsavel do comprador. Sem elas, a busca de
 * compradores e o cadastro falham e nenhuma compra consegue ser registrada
 * (por isso Historico, Contas e Relatorios ficavam vazios).
 *
 * Esta rotina confere as colunas e cria as que faltarem. Roda uma vez por
 * sessao, nao apaga nada e funciona em MySQL/MariaDB e SQLite.
 */

function colunaExiste(PDO $pdo, string $tabela, string $coluna): bool
{
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        foreach ($pdo->query('PRAGMA table_info(' . $tabela . ')')->fetchAll() as $info) {
            if ($info['name'] === $coluna) {
                return true;
            }
        }
        return false;
    }
    $consulta = $pdo->prepare(
        'SELECT COUNT(*) AS total FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = :tabela AND column_name = :coluna'
    );
    $consulta->execute(['tabela' => $tabela, 'coluna' => $coluna]);
    return (int)$consulta->fetch()['total'] > 0;
}

/** @return string[] colunas criadas nesta execucao */
function garantirEstruturaBanco(PDO $pdo): array
{
    $necessarias = [
        ['equipes', 'cor', "VARCHAR(7) NOT NULL DEFAULT '#6e0f1a'"],
        ['compradores', 'responsavel_nome', 'VARCHAR(150) NULL'],
        ['compradores', 'telefone_responsavel', 'VARCHAR(30) NULL'],
        // 'trabalho' = equipe de trabalho; 'circulo' = circulo dos jovens do encontro
        ['equipes', 'tipo', "VARCHAR(20) NOT NULL DEFAULT 'trabalho'"],
    ];
    $criadas = [];
    foreach ($necessarias as [$tabela, $coluna, $definicao]) {
        if (!colunaExiste($pdo, $tabela, $coluna)) {
            $pdo->exec('ALTER TABLE ' . $tabela . ' ADD COLUMN ' . $coluna . ' ' . $definicao);
            $criadas[] = $tabela . '.' . $coluna;
        }
    }

    // Registro de tentativas de login (protecao contra chute de senha na internet)
    $pdo->exec('CREATE TABLE IF NOT EXISTS tentativas_login (ip VARCHAR(45) NOT NULL, momento DATETIME NOT NULL)');

    garantirCirculos($pdo);
    return $criadas;
}

/** Os quatro circulos dos jovens do encontro, cada um com sua cor. */
const CIRCULOS_ENCONTRO = [
    'Círculo Rosa' => '#e84393',
    'Círculo Verde' => '#27ae60',
    'Círculo Azul' => '#2e86de',
    'Círculo Laranja' => '#f39c12',
];

function garantirCirculos(PDO $pdo): void
{
    $existentes = [];
    foreach ($pdo->query('SELECT id, nome FROM equipes')->fetchAll() as $eq) {
        $existentes[mb_strtolower($eq['nome'], 'UTF-8')] = (int)$eq['id'];
    }
    $inserir = $pdo->prepare("INSERT INTO equipes (nome, cor, tipo, ativo) VALUES (:nome, :cor, 'circulo', 1)");
    $marcar = $pdo->prepare("UPDATE equipes SET tipo = 'circulo' WHERE id = :id");
    foreach (CIRCULOS_ENCONTRO as $nome => $cor) {
        $chave = mb_strtolower($nome, 'UTF-8');
        if (isset($existentes[$chave])) {
            $marcar->execute(['id' => $existentes[$chave]]);
        } else {
            $inserir->execute(['nome' => $nome, 'cor' => $cor]);
        }
    }
}
