<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/funcoes.php';
require_once __DIR__ . '/includes/autenticacao.php';

exigirLogin();
$pdo = Database::conectar();

$compraId = (int)($_GET['id'] ?? 0);

$consultaCompra = $pdo->prepare(
    'SELECT c.*, cp.nome AS comprador_nome, eq.nome AS equipe_nome
     FROM compras c
     JOIN compradores cp ON cp.id = c.comprador_id
     LEFT JOIN equipes eq ON eq.id = c.equipe_id
     WHERE c.id = :id'
);
$consultaCompra->execute(['id' => $compraId]);
$compra = $consultaCompra->fetch();

if (!$compra) {
    redirecionar(URL_BASE . 'historico.php');
}

$itens = $pdo->prepare('SELECT * FROM itens_compra WHERE compra_id = :id');
$itens->execute(['id' => $compraId]);
$itens = $itens->fetchAll();

$pagamentos = $pdo->prepare('SELECT * FROM pagamentos WHERE compra_id = :id ORDER BY data_pagamento DESC');
$pagamentos->execute(['id' => $compraId]);
$pagamentos = $pagamentos->fetchAll();

$saldo = (float)$compra['valor_total'] - (float)$compra['valor_pago'];

$tituloPagina = 'Detalhes da Compra';
$paginaAtual = 'historico.php';
require __DIR__ . '/includes/cabecalho.php';
require __DIR__ . '/includes/menulateral.php';
?>
<main class="conteudo">
  <?php if (isset($_GET['sucesso'])): ?><div class="alert alert-success">Compra registrada com sucesso.</div><?php endif; ?>

  <div class="cartao mb-3">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
      <div>
        <h4 class="mb-1">Compra #<?= str_pad($compra['id'], 6, '0', STR_PAD_LEFT) ?></h4>
        <div class="text-muted"><?= formatarDataHora($compra['data_hora']) ?></div>
      </div>
      <div><?= statusPagamentoLabel($compra['status_pagamento']) ?></div>
    </div>

    <div class="row mb-3">
      <div class="col-md-6">
        <strong>Comprador:</strong> <?= limparTexto($compra['comprador_nome']) ?><br>
        <strong>Equipe:</strong> <?= limparTexto($compra['equipe_nome'] ?? '-') ?>
      </div>
      <div class="col-md-6 text-md-end">
        <strong>Total:</strong> <?= formatarMoeda((float)$compra['valor_total']) ?><br>
        <strong>Pago:</strong> <?= formatarMoeda((float)$compra['valor_pago']) ?><br>
        <strong>Saldo:</strong> <?= formatarMoeda($saldo) ?>
      </div>
    </div>

    <div class="tabelaresponsiva mb-3">
      <table class="tabelabja">
        <thead><tr><th>Produto</th><th>Quantidade</th><th>Valor unitario</th><th>Subtotal</th></tr></thead>
        <tbody>
          <?php foreach ($itens as $item): ?>
            <tr>
              <td><?= limparTexto($item['nome_produto']) ?></td>
              <td><?= (int)$item['quantidade'] ?></td>
              <td><?= formatarMoeda((float)$item['preco_unitario']) ?></td>
              <td><?= formatarMoeda((float)$item['subtotal']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <a class="btn btn-contorno-vinho" href="historico.php">Voltar ao Historico</a>
    <?php if ($saldo > 0): ?>
      <a class="btn btn-vinho" href="pagamentos.php?compra=<?= $compra['id'] ?>">Registrar Pagamento</a>
    <?php endif; ?>
  </div>

  <?php if (!empty($pagamentos)): ?>
    <div class="cartao">
      <h5 class="mb-3">Pagamentos registrados</h5>
      <div class="tabelaresponsiva">
        <table class="tabelabja">
          <thead><tr><th>Data</th><th>Valor pago</th><th>Forma</th></tr></thead>
          <tbody>
            <?php foreach ($pagamentos as $pagamento): ?>
              <tr>
                <td><?= formatarDataHora($pagamento['data_pagamento']) ?></td>
                <td><?= formatarMoeda((float)$pagamento['valor_pago']) ?></td>
                <td><?= limparTexto($pagamento['forma_pagamento'] ?? '-') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</main>
<?php require __DIR__ . '/includes/rodape.php'; ?>
