<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/funcoes.php';
require_once __DIR__ . '/includes/autenticacao.php';

exigirLogin();
$pdo = Database::conectar();

$tipo = $_GET['tipo'] ?? 'pessoas';
$dataInicial = ($_GET['data_inicial'] ?? date('Y-m-01')) . ' 00:00:00';
$dataFinal = ($_GET['data_final'] ?? date('Y-m-d')) . ' 23:59:59';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=relatorio_' . $tipo . '.csv');

$saida = fopen('php://output', 'w');
fprintf($saida, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM para acentuacao no Excel

if ($tipo === 'equipes') {
    fputcsv($saida, ['Equipe', 'Compradores', 'Compras', 'Total Consumido', 'Total Pago', 'Pendente'], ';', '"', '\\');
    $dados = $pdo->prepare(
        'SELECT eq.nome, (SELECT COUNT(*) FROM compradores cp WHERE cp.equipe_id = eq.id) AS total_compradores,
                COUNT(DISTINCT c.id) AS total_compras,
                COALESCE(SUM(c.valor_total),0) AS total_consumido, COALESCE(SUM(c.valor_pago),0) AS total_pago
         FROM equipes eq
         LEFT JOIN compras c ON c.equipe_id = eq.id AND c.data_hora BETWEEN :inicio AND :fim
         GROUP BY eq.id, eq.nome ORDER BY total_consumido DESC'
    );
    $dados->execute(['inicio' => $dataInicial, 'fim' => $dataFinal]);
    foreach ($dados->fetchAll() as $linha) {
        fputcsv($saida, [
            $linha['nome'], $linha['total_compradores'], $linha['total_compras'],
            number_format((float)$linha['total_consumido'], 2, ',', '.'),
            number_format((float)$linha['total_pago'], 2, ',', '.'),
            number_format((float)$linha['total_consumido'] - (float)$linha['total_pago'], 2, ',', '.'),
        ], ';', '"', '\\');
    }
} elseif ($tipo === 'produtos') {
    fputcsv($saida, ['Produto', 'Quantidade Vendida', 'Valor Total'], ';', '"', '\\');
    $dados = $pdo->prepare(
        'SELECT ic.nome_produto, SUM(ic.quantidade) AS quantidade, SUM(ic.subtotal) AS total
         FROM itens_compra ic JOIN compras c ON c.id = ic.compra_id
         WHERE c.data_hora BETWEEN :inicio AND :fim
         GROUP BY ic.nome_produto ORDER BY total DESC'
    );
    $dados->execute(['inicio' => $dataInicial, 'fim' => $dataFinal]);
    foreach ($dados->fetchAll() as $linha) {
        fputcsv($saida, [$linha['nome_produto'], $linha['quantidade'], number_format((float)$linha['total'], 2, ',', '.')], ';', '"', '\\');
    }
} else {
    fputcsv($saida, ['Nome', 'Equipe', 'Compras', 'Produtos', 'Total Gasto', 'Total Pago', 'Pendente'], ';', '"', '\\');
    $dados = $pdo->prepare(
        'SELECT cp.nome, eq.nome AS equipe_nome, COUNT(DISTINCT c.id) AS total_compras,
                COALESCE((
                    SELECT SUM(ic2.quantidade)
                    FROM itens_compra ic2
                    JOIN compras c2 ON c2.id = ic2.compra_id
                    WHERE c2.comprador_id = cp.id AND c2.data_hora BETWEEN :inicio2 AND :fim2
                ), 0) AS total_produtos,
                COALESCE(SUM(c.valor_total),0) AS total_gasto,
                COALESCE(SUM(c.valor_pago),0) AS total_pago
         FROM compradores cp
         LEFT JOIN equipes eq ON eq.id = cp.equipe_id
         LEFT JOIN compras c ON c.comprador_id = cp.id AND c.data_hora BETWEEN :inicio AND :fim
         GROUP BY cp.id, cp.nome, eq.nome HAVING total_compras > 0 ORDER BY total_gasto DESC'
    );
    $dados->execute([
        'inicio' => $dataInicial, 'fim' => $dataFinal,
        'inicio2' => $dataInicial, 'fim2' => $dataFinal,
    ]);
    foreach ($dados->fetchAll() as $linha) {
        fputcsv($saida, [
            $linha['nome'], $linha['equipe_nome'] ?? '-', $linha['total_compras'], $linha['total_produtos'],
            number_format((float)$linha['total_gasto'], 2, ',', '.'),
            number_format((float)$linha['total_pago'], 2, ',', '.'),
            number_format((float)$linha['total_gasto'] - (float)$linha['total_pago'], 2, ',', '.'),
        ], ';', '"', '\\');
    }
}

fclose($saida);
exit;
