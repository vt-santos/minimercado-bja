<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/funcoes.php';
require_once __DIR__ . '/includes/autenticacao.php';

exigirLogin();
$pdo = Database::conectar();

$periodo = $_GET['periodo'] ?? 'mes_atual';

switch ($periodo) {
    case 'hoje':
        $dataInicial = date('Y-m-d 00:00:00');
        $dataFinal = date('Y-m-d 23:59:59');
        break;
    case 'ultimos7':
        $dataInicial = date('Y-m-d 00:00:00', strtotime('-6 days'));
        $dataFinal = date('Y-m-d 23:59:59');
        break;
    case 'mes_anterior':
        $dataInicial = date('Y-m-01 00:00:00', strtotime('first day of last month'));
        $dataFinal = date('Y-m-t 23:59:59', strtotime('last day of last month'));
        break;
    case 'personalizado':
        $dataInicial = ($_GET['data_inicial'] ?? date('Y-m-01')) . ' 00:00:00';
        $dataFinal = ($_GET['data_final'] ?? date('Y-m-d')) . ' 23:59:59';
        break;
    case 'mes_atual':
    default:
        $dataInicial = date('Y-m-01 00:00:00');
        $dataFinal = date('Y-m-t 23:59:59');
        break;
}

// Relatorio por pessoa
$relatorioPessoas = $pdo->prepare(
    'SELECT cp.nome, eq.nome AS equipe_nome,
            COUNT(DISTINCT c.id) AS total_compras,
            COALESCE((
                SELECT SUM(ic2.quantidade)
                FROM itens_compra ic2
                JOIN compras c2 ON c2.id = ic2.compra_id
                WHERE c2.comprador_id = cp.id AND c2.data_hora BETWEEN :inicio2 AND :fim2
            ), 0) AS total_produtos,
            COALESCE(SUM(c.valor_total), 0) AS total_gasto,
            COALESCE(SUM(c.valor_pago), 0) AS total_pago
     FROM compradores cp
     LEFT JOIN equipes eq ON eq.id = cp.equipe_id
     LEFT JOIN compras c ON c.comprador_id = cp.id AND c.data_hora BETWEEN :inicio AND :fim
     GROUP BY cp.id, cp.nome, eq.nome
     HAVING total_compras > 0
     ORDER BY total_gasto DESC'
);
$relatorioPessoas->execute([
    'inicio' => $dataInicial, 'fim' => $dataFinal,
    'inicio2' => $dataInicial, 'fim2' => $dataFinal,
]);
$relatorioPessoas = $relatorioPessoas->fetchAll();

// Relatorio por equipe
$relatorioEquipes = $pdo->prepare(
    'SELECT eq.nome,
            (SELECT COUNT(*) FROM compradores cp WHERE cp.equipe_id = eq.id) AS total_compradores,
            COUNT(DISTINCT c.id) AS total_compras,
            COALESCE(SUM(c.valor_total), 0) AS total_consumido,
            COALESCE(SUM(c.valor_pago), 0) AS total_pago
     FROM equipes eq
     LEFT JOIN compras c ON c.equipe_id = eq.id AND c.data_hora BETWEEN :inicio AND :fim
     GROUP BY eq.id, eq.nome
     ORDER BY total_consumido DESC'
);
$relatorioEquipes->execute(['inicio' => $dataInicial, 'fim' => $dataFinal]);
$relatorioEquipes = $relatorioEquipes->fetchAll();

// Relatorio por produto
$relatorioProdutos = $pdo->prepare(
    'SELECT ic.nome_produto, SUM(ic.quantidade) AS quantidade, SUM(ic.subtotal) AS total
     FROM itens_compra ic
     JOIN compras c ON c.id = ic.compra_id
     WHERE c.data_hora BETWEEN :inicio AND :fim
     GROUP BY ic.nome_produto
     ORDER BY total DESC'
);
$relatorioProdutos->execute(['inicio' => $dataInicial, 'fim' => $dataFinal]);
$relatorioProdutos = $relatorioProdutos->fetchAll();

// Relatorio financeiro
$consultaFinanceiro = $pdo->prepare(
    "SELECT COALESCE(SUM(valor_total),0) AS total_vendas,
            COALESCE(SUM(valor_pago),0) AS total_recebido,
            COALESCE(SUM(valor_total - valor_pago),0) AS total_pendente,
            COALESCE(SUM(CASE WHEN status_pagamento = 'parcial' THEN valor_total - valor_pago ELSE 0 END),0) AS total_parcial
     FROM compras WHERE data_hora BETWEEN :inicio AND :fim"
);
$consultaFinanceiro->execute(['inicio' => $dataInicial, 'fim' => $dataFinal]);
$financeiro = $consultaFinanceiro->fetch();
$totalGeralCompras = (int)$pdo->query('SELECT COUNT(*) AS total FROM compras')->fetch()['total'];

$tituloPagina = 'Relatorios';
$paginaAtual = 'relatorios.php';
require __DIR__ . '/includes/cabecalho.php';
require __DIR__ . '/includes/menulateral.php';
?>
<main class="conteudo">
  <h3 class="mb-4">Relatorios</h3>

  <div class="cartao mb-4">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label">Periodo</label>
        <select name="periodo" class="form-select" onchange="this.form.submit()">
          <option value="hoje" <?= $periodo === 'hoje' ? 'selected' : '' ?>>Hoje</option>
          <option value="ultimos7" <?= $periodo === 'ultimos7' ? 'selected' : '' ?>>Ultimos 7 dias</option>
          <option value="mes_atual" <?= $periodo === 'mes_atual' ? 'selected' : '' ?>>Este mes</option>
          <option value="mes_anterior" <?= $periodo === 'mes_anterior' ? 'selected' : '' ?>>Mes anterior</option>
          <option value="personalizado" <?= $periodo === 'personalizado' ? 'selected' : '' ?>>Personalizado</option>
        </select>
      </div>
      <?php if ($periodo === 'personalizado'): ?>
        <div class="col-md-3">
          <label class="form-label">De</label>
          <input type="date" name="data_inicial" class="form-control" value="<?= substr($dataInicial, 0, 10) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Ate</label>
          <input type="date" name="data_final" class="form-control" value="<?= substr($dataFinal, 0, 10) ?>">
        </div>
        <div class="col-md-2">
          <button class="btn btn-contorno-vinho w-100" type="submit">Aplicar</button>
        </div>
      <?php endif; ?>
    </form>
  </div>

  <?php if ((float)$financeiro['total_vendas'] <= 0 && empty($relatorioProdutos)): ?>
    <div class="alert alert-info alerta-fixo">
      Nenhuma compra entre <?= formatarData($dataInicial) ?> e <?= formatarData($dataFinal) ?>.
      <?php if ($totalGeralCompras > 0): ?>Existem <?= $totalGeralCompras ?> compra(s) em outras datas: escolha outro periodo.<?php else: ?>Ainda nao ha nenhuma compra registrada no sistema.<?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="cartao indicador"><span class="rotulo">Total de vendas</span><span class="valor"><?= formatarMoeda((float)$financeiro['total_vendas']) ?></span></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="cartao indicador"><span class="rotulo">Total recebido</span><span class="valor"><?= formatarMoeda((float)$financeiro['total_recebido']) ?></span></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="cartao indicador"><span class="rotulo">Falta receber</span><span class="valor"><?= formatarMoeda((float)$financeiro['total_pendente']) ?></span></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="cartao indicador"><span class="rotulo">Falta das parciais</span><span class="valor"><?= formatarMoeda((float)$financeiro['total_parcial']) ?></span></div>
    </div>
  </div>

  <div class="cartao mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="mb-0">Relatorio por pessoa</h5>
      <a class="btn btn-sm btn-contorno-vinho" href="relatorioexportar.php?tipo=pessoas&periodo=<?= $periodo ?>&data_inicial=<?= substr($dataInicial,0,10) ?>&data_final=<?= substr($dataFinal,0,10) ?>">Exportar CSV</a>
    </div>
    <div class="tabelaresponsiva">
      <table class="tabelabja">
        <thead><tr><th>Nome</th><th>Equipe</th><th>Compras</th><th>Produtos</th><th>Total gasto</th><th>Pago</th><th>Pendente</th></tr></thead>
        <tbody>
          <?php foreach ($relatorioPessoas as $linha): ?>
            <tr>
              <td><?= limparTexto($linha['nome']) ?></td>
              <td><?= limparTexto($linha['equipe_nome'] ?? '-') ?></td>
              <td><?= (int)$linha['total_compras'] ?></td>
              <td><?= (int)$linha['total_produtos'] ?></td>
              <td><?= formatarMoeda((float)$linha['total_gasto']) ?></td>
              <td><?= formatarMoeda((float)$linha['total_pago']) ?></td>
              <td><?= formatarMoeda((float)$linha['total_gasto'] - (float)$linha['total_pago']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($relatorioPessoas)): ?><tr><td colspan="7">Sem dados no periodo.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="cartao mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="mb-0">Relatorio por equipe</h5>
      <a class="btn btn-sm btn-contorno-vinho" href="relatorioexportar.php?tipo=equipes&periodo=<?= $periodo ?>&data_inicial=<?= substr($dataInicial,0,10) ?>&data_final=<?= substr($dataFinal,0,10) ?>">Exportar CSV</a>
    </div>
    <div class="tabelaresponsiva">
      <table class="tabelabja">
        <thead><tr><th>Equipe</th><th>Compradores</th><th>Compras</th><th>Total consumido</th><th>Total pago</th><th>Pendente</th></tr></thead>
        <tbody>
          <?php foreach ($relatorioEquipes as $linha): ?>
            <tr>
              <td><?= limparTexto($linha['nome']) ?></td>
              <td><?= (int)$linha['total_compradores'] ?></td>
              <td><?= (int)$linha['total_compras'] ?></td>
              <td><?= formatarMoeda((float)$linha['total_consumido']) ?></td>
              <td><?= formatarMoeda((float)$linha['total_pago']) ?></td>
              <td><?= formatarMoeda((float)$linha['total_consumido'] - (float)$linha['total_pago']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="cartao">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="mb-0">Relatorio por produto</h5>
      <a class="btn btn-sm btn-contorno-vinho" href="relatorioexportar.php?tipo=produtos&periodo=<?= $periodo ?>&data_inicial=<?= substr($dataInicial,0,10) ?>&data_final=<?= substr($dataFinal,0,10) ?>">Exportar CSV</a>
    </div>
    <div class="tabelaresponsiva">
      <table class="tabelabja">
        <thead><tr><th>Produto</th><th>Quantidade vendida</th><th>Valor total</th></tr></thead>
        <tbody>
          <?php if (empty($relatorioProdutos)): ?><tr><td colspan="3">Sem dados no periodo.</td></tr><?php endif; ?>
          <?php foreach ($relatorioProdutos as $linha): ?>
            <tr>
              <td><?= limparTexto($linha['nome_produto']) ?></td>
              <td><?= (int)$linha['quantidade'] ?></td>
              <td><?= formatarMoeda((float)$linha['total']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
<?php require __DIR__ . '/includes/rodape.php'; ?>
