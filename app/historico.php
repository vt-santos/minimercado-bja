<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/funcoes.php';
require_once __DIR__ . '/includes/autenticacao.php';

exigirLogin();
$pdo = Database::conectar();

$tokenCsrf = gerarTokenCsrf();

if (isset($_GET['marcarpago']) && validarTokenCsrf($_GET['csrf_token'] ?? '')) {
    $compraId = (int)$_GET['marcarpago'];
    $consultaCompra = $pdo->prepare('SELECT valor_total, valor_pago FROM compras WHERE id = :id');
    $consultaCompra->execute(['id' => $compraId]);
    $compraAtual = $consultaCompra->fetch();

    if ($compraAtual) {
        $saldoRestante = round((float)$compraAtual['valor_total'] - (float)$compraAtual['valor_pago'], 2);
        try {
            $pdo->beginTransaction();
            if ($saldoRestante > 0) {
                // Registra o saldo restante no historico de pagamentos, para os totais baterem
                $pdo->prepare('INSERT INTO pagamentos (compra_id, valor_pago, forma_pagamento, observacao, data_pagamento, registrado_por) VALUES (:compra_id, :valor, :forma, :obs, :data_pagamento, :usuario)')
                    ->execute(['compra_id' => $compraId, 'valor' => $saldoRestante, 'forma' => 'Nao informada', 'obs' => 'Marcado como pago pelo historico', 'data_pagamento' => agora(), 'usuario' => $_SESSION['usuario_id']]);
            }
            $pdo->prepare('UPDATE compras SET status_pagamento=\'pago\', valor_pago = valor_total WHERE id = :id')->execute(['id' => $compraId]);
            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
        }
    }
    redirecionar(URL_BASE . 'historico.php?atualizado=1');
}

if (isset($_GET['excluir']) && validarTokenCsrf($_GET['csrf_token'] ?? '')) {
    $pdo->prepare('DELETE FROM compras WHERE id = :id')->execute(['id' => (int)$_GET['excluir']]);
    redirecionar(URL_BASE . 'historico.php?excluido=1');
}

$nomeComprador = trim($_GET['comprador'] ?? '');
$equipeId = (int)($_GET['equipe'] ?? 0);
$dataInicial = trim($_GET['data_inicial'] ?? '');
$dataFinal = trim($_GET['data_final'] ?? '');
$status = trim($_GET['status'] ?? '');

$condicoes = [];
$parametros = [];

if ($nomeComprador !== '') {
    $condicoes[] = 'cp.nome LIKE :comprador';
    $parametros['comprador'] = '%' . $nomeComprador . '%';
}
if ($equipeId > 0) {
    $condicoes[] = 'c.equipe_id = :equipe';
    $parametros['equipe'] = $equipeId;
}
if ($dataInicial !== '') {
    $condicoes[] = 'c.data_hora >= :data_inicial';
    $parametros['data_inicial'] = $dataInicial . ' 00:00:00';
}
if ($dataFinal !== '') {
    $condicoes[] = 'c.data_hora <= :data_final';
    $parametros['data_final'] = $dataFinal . ' 23:59:59';
}
if ($status !== '') {
    $condicoes[] = 'c.status_pagamento = :status';
    $parametros['status'] = $status;
}

$sql = 'SELECT c.*, cp.nome AS comprador_nome, eq.nome AS equipe_nome,
               (SELECT COUNT(*) FROM itens_compra ic WHERE ic.compra_id = c.id) AS total_itens
        FROM compras c
        JOIN compradores cp ON cp.id = c.comprador_id
        LEFT JOIN equipes eq ON eq.id = c.equipe_id';

if ($condicoes) {
    $sql .= ' WHERE ' . implode(' AND ', $condicoes);
}
$sql .= ' ORDER BY c.data_hora DESC';

$consulta = $pdo->prepare($sql);
$consulta->execute($parametros);
$compras = $consulta->fetchAll();
$filtrando = !empty($condicoes);
$totalGeralCompras = (int)$pdo->query('SELECT COUNT(*) AS total FROM compras')->fetch()['total'];

$equipes = buscarEquipes($pdo, false);

$tituloPagina = 'Historico de Compras';
$paginaAtual = 'historico.php';
require __DIR__ . '/includes/cabecalho.php';
require __DIR__ . '/includes/menulateral.php';
?>
<main class="conteudo">
  <h3 class="mb-4">Historico de Compras</h3>

  <?php if (isset($_GET['atualizado'])): ?><div class="alert alert-success">Compra atualizada.</div><?php endif; ?>
  <?php if (isset($_GET['excluido'])): ?><div class="alert alert-success">Compra removida.</div><?php endif; ?>

  <div class="cartao mb-3">
    <form method="get" class="row g-2">
      <div class="col-md-3">
        <label class="form-label">Comprador</label>
        <input type="text" name="comprador" class="form-control" value="<?= limparTexto($nomeComprador) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label">Equipe</label>
        <select name="equipe" class="form-select">
          <option value="">Todas</option>
          <?= opcoesEquipes($equipes, $equipeId ?: null) ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">De</label>
        <input type="date" name="data_inicial" class="form-control" value="<?= limparTexto($dataInicial) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label">Ate</label>
        <input type="date" name="data_final" class="form-control" value="<?= limparTexto($dataFinal) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
          <option value="">Todos</option>
          <option value="pendente" <?= $status === 'pendente' ? 'selected' : '' ?>>Pendente</option>
          <option value="pago" <?= $status === 'pago' ? 'selected' : '' ?>>Pago</option>
          <option value="parcial" <?= $status === 'parcial' ? 'selected' : '' ?>>Parcial</option>
        </select>
      </div>
      <div class="col-md-1 d-flex align-items-end">
        <button class="btn btn-contorno-vinho w-100" type="submit">Filtrar</button>
        <?php if ($filtrando): ?><a class="btn btn-link btn-sm w-100" href="historico.php">Limpar</a><?php endif; ?>
      </div>
    </form>
  </div>

  <div class="tabelaresponsiva">
    <table class="tabelabja">
      <thead>
        <tr><th>ID</th><th>Data</th><th>Comprador</th><th>Equipe</th><th>Itens</th><th>Total</th><th>Status</th><th>Acoes</th></tr>
      </thead>
      <tbody>
        <?php foreach ($compras as $compra): ?>
          <tr>
            <td>#<?= str_pad($compra['id'], 6, '0', STR_PAD_LEFT) ?></td>
            <td><?= formatarDataHora($compra['data_hora']) ?></td>
            <td><?= limparTexto($compra['comprador_nome']) ?></td>
            <td><?= limparTexto($compra['equipe_nome'] ?? '-') ?></td>
            <td><?= (int)$compra['total_itens'] ?></td>
            <td><?= formatarMoeda((float)$compra['valor_total']) ?></td>
            <td><?= statusPagamentoLabel($compra['status_pagamento']) ?></td>
            <td>
              <a class="btn btn-sm btn-contorno-vinho" href="compradetalhe.php?id=<?= $compra['id'] ?>">Ver</a>
              <?php if ($compra['status_pagamento'] !== 'pago'): ?>
                <a class="btn btn-sm btn-outline-success" href="historico.php?marcarpago=<?= $compra['id'] ?>&csrf_token=<?= $tokenCsrf ?>">Marcar Pago</a>
              <?php endif; ?>
              <a class="btn btn-sm btn-outline-danger" href="historico.php?excluir=<?= $compra['id'] ?>&csrf_token=<?= $tokenCsrf ?>" onclick="return confirm('Confirma o cancelamento desta compra?')">Excluir</a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($compras)): ?>
          <tr><td colspan="8">
            <?php if ($totalGeralCompras === 0): ?>
              Nenhuma compra registrada ainda. As compras aparecem aqui depois de confirmadas em <a href="registrarcompra.php">Registrar Compra</a>.
            <?php else: ?>
              Nenhuma compra encontrada com esses filtros. <a href="historico.php">Mostrar todas</a>
            <?php endif; ?>
          </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</main>
<?php require __DIR__ . '/includes/rodape.php'; ?>
