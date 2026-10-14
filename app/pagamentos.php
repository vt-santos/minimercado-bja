<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/funcoes.php';
require_once __DIR__ . '/includes/autenticacao.php';

exigirLogin();
$pdo = Database::conectar();

$mensagemErro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'registrarpagamento') {
    if (!validarTokenCsrf($_POST['csrf_token'] ?? '')) {
        $mensagemErro = 'Sessao expirada, tente novamente.';
    } else {
        $compraId = (int)($_POST['compra_id'] ?? 0);
        $valorPago = (float)str_replace(',', '.', $_POST['valor_pago'] ?? '0');
        $forma = trim($_POST['forma_pagamento'] ?? '');

        $consultaCompra = $pdo->prepare('SELECT * FROM compras WHERE id = :id');
        $consultaCompra->execute(['id' => $compraId]);
        $compra = $consultaCompra->fetch();

        $saldoDevido = $compra ? (float)$compra['valor_total'] - (float)$compra['valor_pago'] : 0;

        if (!$compra || $valorPago <= 0) {
            $mensagemErro = 'Informe um valor de pagamento valido.';
        } elseif ($valorPago > $saldoDevido + 0.001) {
            $mensagemErro = 'O valor informado (' . formatarMoeda($valorPago) . ') e maior que o saldo devido (' . formatarMoeda($saldoDevido) . ').';
        } else {
            $pdo->prepare('INSERT INTO pagamentos (compra_id, valor_pago, forma_pagamento, data_pagamento, registrado_por) VALUES (:compra_id, :valor_pago, :forma, :data_pagamento, :usuario)')
                ->execute(['compra_id' => $compraId, 'valor_pago' => $valorPago, 'forma' => $forma, 'data_pagamento' => agora(), 'usuario' => $_SESSION['usuario_id']]);

            $novoValorPago = (float)$compra['valor_pago'] + $valorPago;
            $novoStatus = 'parcial';
            if ($novoValorPago >= (float)$compra['valor_total']) {
                $novoStatus = 'pago';
                $novoValorPago = (float)$compra['valor_total'];
            }

            $pdo->prepare('UPDATE compras SET valor_pago = :valor_pago, status_pagamento = :status WHERE id = :id')
                ->execute(['valor_pago' => $novoValorPago, 'status' => $novoStatus, 'id' => $compraId]);

            redirecionar(URL_BASE . 'compradetalhe.php?id=' . $compraId . '&pagamentoregistrado=1');
        }
    }
}

$compraSelecionadaId = (int)($_GET['compra'] ?? 0);
$compraSelecionada = null;
if ($compraSelecionadaId > 0) {
    $consulta = $pdo->prepare('SELECT c.*, cp.nome AS comprador_nome, cp.responsavel_nome, cp.telefone_responsavel FROM compras c JOIN compradores cp ON cp.id = c.comprador_id WHERE c.id = :id');
    $consulta->execute(['id' => $compraSelecionadaId]);
    $compraSelecionada = $consulta->fetch();
}

$comprasPendentes = $pdo->query(
    "SELECT c.*, cp.nome AS comprador_nome
     FROM compras c
     JOIN compradores cp ON cp.id = c.comprador_id
     WHERE c.status_pagamento != 'pago'
     ORDER BY c.data_hora DESC"
)->fetchAll();

$totalVendido = (float)$pdo->query('SELECT COALESCE(SUM(valor_total),0) AS total FROM compras')->fetch()['total'];
$totalRecebido = (float)$pdo->query('SELECT COALESCE(SUM(valor_pago),0) AS total FROM compras')->fetch()['total'];
$totalParcial = (float)$pdo->query("SELECT COALESCE(SUM(valor_total - valor_pago),0) AS total FROM compras WHERE status_pagamento = 'parcial'")->fetch()['total'];
$totalPendente = $totalVendido - $totalRecebido;

$tokenCsrf = gerarTokenCsrf();
$tituloPagina = 'Pagamentos';
$paginaAtual = 'pagamentos.php';
require __DIR__ . '/includes/cabecalho.php';
require __DIR__ . '/includes/menulateral.php';
?>
<main class="conteudo">
  <h3 class="mb-4">Pagamentos</h3>

  <?php if ($mensagemErro): ?><div class="alert alert-danger"><?= limparTexto($mensagemErro) ?></div><?php endif; ?>

  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="cartao indicador"><span class="rotulo">Total vendido</span><span class="valor"><?= formatarMoeda($totalVendido) ?></span></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="cartao indicador"><span class="rotulo">Total recebido</span><span class="valor"><?= formatarMoeda($totalRecebido) ?></span></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="cartao indicador"><span class="rotulo">Falta receber</span><span class="valor"><?= formatarMoeda($totalPendente) ?></span></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="cartao indicador"><span class="rotulo">Falta das parciais</span><span class="valor"><?= formatarMoeda($totalParcial) ?></span></div>
    </div>
  </div>

  <?php if ($compraSelecionada): ?>
    <div class="cartao mb-4">
      <h5 class="mb-3">Registrar pagamento — Compra #<?= str_pad($compraSelecionada['id'], 6, '0', STR_PAD_LEFT) ?></h5>
      <p><?= limparTexto($compraSelecionada['comprador_nome']) ?> — Total: <?= formatarMoeda((float)$compraSelecionada['valor_total']) ?> — Saldo: <?= formatarMoeda((float)$compraSelecionada['valor_total'] - (float)$compraSelecionada['valor_pago']) ?></p>
      <?php if ($compraSelecionada['responsavel_nome'] || $compraSelecionada['telefone_responsavel']): ?>
        <p class="text-muted mb-3">Responsavel: <?= limparTexto($compraSelecionada['responsavel_nome'] ?? '-') ?> — Tel: <?= limparTexto($compraSelecionada['telefone_responsavel'] ?? '-') ?></p>
      <?php endif; ?>
      <form method="post" class="row g-2">
        <input type="hidden" name="csrf_token" value="<?= $tokenCsrf ?>">
        <input type="hidden" name="acao" value="registrarpagamento">
        <input type="hidden" name="compra_id" value="<?= $compraSelecionada['id'] ?>">
        <div class="col-md-4">
          <label class="form-label">Valor pago</label>
          <input type="text" name="valor_pago" class="form-control" placeholder="0,00" required>
          <div class="form-text">Saldo devido: <?= formatarMoeda((float)$compraSelecionada['valor_total'] - (float)$compraSelecionada['valor_pago']) ?></div>
        </div>
        <div class="col-md-4">
          <label class="form-label">Forma de pagamento</label>
          <select name="forma_pagamento" class="form-select">
            <option value="Dinheiro">Dinheiro</option>
            <option value="Pix">Pix</option>
            <option value="Cartao">Cartao</option>
            <option value="Transferencia">Transferencia</option>
          </select>
        </div>
        <div class="col-md-4 d-flex align-items-end">
          <button class="btn btn-vinho w-100" type="submit">Registrar Pagamento</button>
        </div>
      </form>
    </div>
  <?php endif; ?>

  <div class="cartao">
    <h5 class="mb-3">Compras com saldo em aberto</h5>
    <div class="tabelaresponsiva">
      <table class="tabelabja">
        <thead><tr><th>ID</th><th>Data</th><th>Comprador</th><th>Total</th><th>Pago</th><th>Saldo</th><th>Status</th><th>Acoes</th></tr></thead>
        <tbody>
          <?php foreach ($comprasPendentes as $compra): ?>
            <tr>
              <td>#<?= str_pad($compra['id'], 6, '0', STR_PAD_LEFT) ?></td>
              <td><?= formatarDataHora($compra['data_hora']) ?></td>
              <td><?= limparTexto($compra['comprador_nome']) ?></td>
              <td><?= formatarMoeda((float)$compra['valor_total']) ?></td>
              <td><?= formatarMoeda((float)$compra['valor_pago']) ?></td>
              <td><?= formatarMoeda((float)$compra['valor_total'] - (float)$compra['valor_pago']) ?></td>
              <td><?= statusPagamentoLabel($compra['status_pagamento']) ?></td>
              <td><a class="btn btn-sm btn-vinho" href="pagamentos.php?compra=<?= $compra['id'] ?>">Registrar Pagamento</a></td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($comprasPendentes)): ?>
            <tr><td colspan="8">Nenhuma pendencia encontrada.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
<?php require __DIR__ . '/includes/rodape.php'; ?>
