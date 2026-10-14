<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/funcoes.php';
require_once __DIR__ . '/includes/autenticacao.php';

exigirLogin();

$pdo = Database::conectar();

$totalProdutos = (int)$pdo->query('SELECT COUNT(*) AS total FROM produtos')->fetch()['total'];
$produtosDisponiveis = (int)$pdo->query('SELECT COUNT(*) AS total FROM produtos WHERE ativo = 1')->fetch()['total'];
$totalCompras = (int)$pdo->query('SELECT COUNT(*) AS total FROM compras')->fetch()['total'];
$valorTotalVendido = (float)$pdo->query('SELECT COALESCE(SUM(valor_total),0) AS total FROM compras')->fetch()['total'];
$totalCompradoresAtivos = (int)$pdo->query('SELECT COUNT(DISTINCT comprador_id) AS total FROM compras')->fetch()['total'];
$totalRecebido = (float)$pdo->query('SELECT COALESCE(SUM(valor_pago),0) AS total FROM compras')->fetch()['total'];
$totalPendente = $valorTotalVendido - $totalRecebido;

$produtosMaisVendidos = $pdo->query(
    'SELECT ic.nome_produto AS nome, SUM(ic.quantidade) AS quantidade
     FROM itens_compra ic
     GROUP BY ic.nome_produto
     ORDER BY quantidade DESC
     LIMIT 5'
)->fetchAll();

$equipesMaiorConsumo = $pdo->query(
    'SELECT e.nome AS nome, COALESCE(SUM(c.valor_total),0) AS total
     FROM equipes e
     LEFT JOIN compras c ON c.equipe_id = e.id
     GROUP BY e.id, e.nome
     ORDER BY total DESC
     LIMIT 5'
)->fetchAll();

$tituloPagina = 'Dashboard';
$paginaAtual = 'index.php';
require __DIR__ . '/includes/cabecalho.php';
require __DIR__ . '/includes/menulateral.php';
?>
<main class="conteudo">
  <h3 class="mb-4">Dashboard</h3>

  <div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
      <div class="cartao indicador">
        <span class="rotulo">Produtos cadastrados</span>
        <span class="valor"><?= $totalProdutos ?></span>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="cartao indicador">
        <span class="rotulo">Produtos disponiveis</span>
        <span class="valor"><?= $produtosDisponiveis ?></span>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="cartao indicador">
        <span class="rotulo">Total de compras</span>
        <span class="valor"><?= $totalCompras ?></span>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="cartao indicador">
        <span class="rotulo">Compradores atendidos</span>
        <span class="valor"><?= $totalCompradoresAtivos ?></span>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="cartao indicador">
        <span class="rotulo">Valor total vendido</span>
        <span class="valor"><?= formatarMoeda($valorTotalVendido) ?></span>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="cartao indicador">
        <span class="rotulo">Total recebido</span>
        <span class="valor"><?= formatarMoeda($totalRecebido) ?></span>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="cartao indicador">
        <span class="rotulo">Total em aberto</span>
        <span class="valor"><?= formatarMoeda($totalPendente) ?></span>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="cartao indicador">
        <span class="rotulo">Acesso rapido</span>
        <a href="<?= URL_BASE ?>registrarcompra.php" class="btn btn-vinho btn-toque mt-2">Registrar Compra</a>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-6">
      <div class="cartao">
        <h5 class="mb-3">Produtos mais vendidos</h5>
        <div class="tabelaresponsiva">
          <table class="tabelabja tabela-compacta">
            <thead><tr><th>Produto</th><th>Quantidade</th></tr></thead>
            <tbody>
              <?php if (empty($produtosMaisVendidos)): ?>
                <tr><td colspan="2">Nenhuma venda registrada ainda.</td></tr>
              <?php endif; ?>
              <?php foreach ($produtosMaisVendidos as $linha): ?>
                <tr>
                  <td><?= limparTexto($linha['nome']) ?></td>
                  <td><?= (int)$linha['quantidade'] ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="cartao">
        <h5 class="mb-3">Equipes com maior consumo</h5>
        <div class="tabelaresponsiva">
          <table class="tabelabja tabela-compacta">
            <thead><tr><th>Equipe</th><th>Total consumido</th></tr></thead>
            <tbody>
              <?php foreach ($equipesMaiorConsumo as $linha): ?>
                <tr>
                  <td><?= limparTexto($linha['nome']) ?></td>
                  <td><?= formatarMoeda((float)$linha['total']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</main>
<?php require __DIR__ . '/includes/rodape.php'; ?>
