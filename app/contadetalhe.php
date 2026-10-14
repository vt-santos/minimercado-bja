<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/funcoes.php';
require_once __DIR__ . '/includes/autenticacao.php';

exigirLogin();
$pdo = Database::conectar();

$compradorId = (int)($_GET['id'] ?? 0);

$consultaComprador = $pdo->prepare(
    'SELECT cp.*, eq.nome AS equipe_nome FROM compradores cp LEFT JOIN equipes eq ON eq.id = cp.equipe_id WHERE cp.id = :id'
);
$consultaComprador->execute(['id' => $compradorId]);
$comprador = $consultaComprador->fetch();

if (!$comprador) {
    redirecionar(URL_BASE . 'contas.php');
}

$resumoProdutos = $pdo->prepare(
    'SELECT ic.nome_produto, SUM(ic.quantidade) AS quantidade, SUM(ic.subtotal) AS total
     FROM itens_compra ic
     JOIN compras c ON c.id = ic.compra_id
     WHERE c.comprador_id = :id
     GROUP BY ic.nome_produto
     ORDER BY total DESC'
);
$resumoProdutos->execute(['id' => $compradorId]);
$resumoProdutos = $resumoProdutos->fetchAll();

$compras = $pdo->prepare('SELECT * FROM compras WHERE comprador_id = :id ORDER BY data_hora DESC');
$compras->execute(['id' => $compradorId]);
$compras = $compras->fetchAll();

$totalGasto = array_sum(array_column($compras, 'valor_total'));
$totalPago = array_sum(array_column($compras, 'valor_pago'));
$saldoPendente = $totalGasto - $totalPago;

$tituloPagina = 'Conta de ' . $comprador['nome'];
$paginaAtual = 'contas.php';
require __DIR__ . '/includes/cabecalho.php';
require __DIR__ . '/includes/menulateral.php';
?>
<main class="conteudo">
  <div class="cartao mb-3">
    <h3 class="mb-1"><?= limparTexto($comprador['nome']) ?></h3>
    <div class="text-muted mb-3">Equipe: <?= limparTexto($comprador['equipe_nome'] ?? '-') ?></div>
    <?php if ($comprador['responsavel_nome'] || $comprador['telefone_responsavel']): ?>
      <div class="alert alert-secondary py-2">
        <strong>Responsavel:</strong> <?= limparTexto($comprador['responsavel_nome'] ?? '-') ?>
        &nbsp;|&nbsp; <strong>Telefone:</strong> <?= limparTexto($comprador['telefone_responsavel'] ?? '-') ?>
      </div>
    <?php endif; ?>

    <div class="row g-3">
      <div class="col-6 col-md-3">
        <div class="cartao indicador"><span class="rotulo">Total gasto</span><span class="valor"><?= formatarMoeda($totalGasto) ?></span></div>
      </div>
      <div class="col-6 col-md-3">
        <div class="cartao indicador"><span class="rotulo">Total pago</span><span class="valor"><?= formatarMoeda($totalPago) ?></span></div>
      </div>
      <div class="col-6 col-md-3">
        <div class="cartao indicador"><span class="rotulo">Saldo pendente</span><span class="valor"><?= formatarMoeda($saldoPendente) ?></span></div>
      </div>
      <div class="col-6 col-md-3">
        <div class="cartao indicador"><span class="rotulo">Status</span><span class="valor"><?= $saldoPendente > 0 ? 'Pendente' : 'Quitado' ?></span></div>
      </div>
    </div>
  </div>

  <div class="cartao mb-3">
    <h5 class="mb-3">Produtos comprados</h5>
    <div class="tabelaresponsiva">
      <table class="tabelabja">
        <thead><tr><th>Produto</th><th>Quantidade total</th><th>Valor total</th></tr></thead>
        <tbody>
          <?php foreach ($resumoProdutos as $produto): ?>
            <tr>
              <td><?= limparTexto($produto['nome_produto']) ?></td>
              <td><?= (int)$produto['quantidade'] ?></td>
              <td><?= formatarMoeda((float)$produto['total']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($resumoProdutos)): ?>
            <tr><td colspan="3">Nenhuma compra registrada.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="cartao">
    <h5 class="mb-3">Historico de compras</h5>
    <div class="tabelaresponsiva">
      <table class="tabelabja">
        <thead><tr><th>Data</th><th>Total</th><th>Status</th><th>Acoes</th></tr></thead>
        <tbody>
          <?php foreach ($compras as $compra): ?>
            <tr>
              <td><?= formatarDataHora($compra['data_hora']) ?></td>
              <td><?= formatarMoeda((float)$compra['valor_total']) ?></td>
              <td><?= statusPagamentoLabel($compra['status_pagamento']) ?></td>
              <td><a class="btn btn-sm btn-contorno-vinho" href="compradetalhe.php?id=<?= $compra['id'] ?>">Ver</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
<?php require __DIR__ . '/includes/rodape.php'; ?>
