<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/funcoes.php';
require_once __DIR__ . '/includes/autenticacao.php';

exigirLogin();
$pdo = Database::conectar();

$nomeComprador = trim($_GET['comprador'] ?? '');
$equipeId = (int)($_GET['equipe'] ?? 0);
$status = trim($_GET['status'] ?? '');

$condicoes = [];
$parametros = [];

if ($nomeComprador !== '') {
    $condicoes[] = 'cp.nome LIKE :comprador';
    $parametros['comprador'] = '%' . $nomeComprador . '%';
}
if ($equipeId > 0) {
    $condicoes[] = 'cp.equipe_id = :equipe';
    $parametros['equipe'] = $equipeId;
}

$sql = 'SELECT cp.id, cp.nome, eq.nome AS equipe_nome,
               COUNT(c.id) AS total_compras,
               COALESCE(SUM(c.valor_total), 0) AS total_gasto,
               COALESCE(SUM(c.valor_pago), 0) AS total_pago
        FROM compradores cp
        LEFT JOIN equipes eq ON eq.id = cp.equipe_id
        LEFT JOIN compras c ON c.comprador_id = cp.id';

if ($condicoes) {
    $sql .= ' WHERE ' . implode(' AND ', $condicoes);
}

$sql .= ' GROUP BY cp.id, cp.nome, eq.nome ORDER BY cp.nome';

$consulta = $pdo->prepare($sql);
$consulta->execute($parametros);
$contas = $consulta->fetchAll();
$filtrando = !empty($condicoes);

$equipes = buscarEquipes($pdo, false);

$tituloPagina = 'Contas dos Compradores';
$paginaAtual = 'contas.php';
require __DIR__ . '/includes/cabecalho.php';
require __DIR__ . '/includes/menulateral.php';
?>
<main class="conteudo">
  <h3 class="mb-4">Contas dos Compradores</h3>

  <div class="cartao mb-3">
    <form method="get" class="row g-2">
      <div class="col-md-4">
        <label class="form-label">Comprador</label>
        <input type="text" name="comprador" class="form-control" value="<?= limparTexto($nomeComprador) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Equipe</label>
        <select name="equipe" class="form-select">
          <option value="">Todas</option>
          <?= opcoesEquipes($equipes, $equipeId ?: null) ?>
        </select>
      </div>
      <div class="col-md-2 d-flex align-items-end">
        <button class="btn btn-contorno-vinho w-100" type="submit">Filtrar</button>
        <?php if ($filtrando): ?><a class="btn btn-link btn-sm w-100" href="contas.php">Limpar</a><?php endif; ?>
      </div>
    </form>
  </div>

  <div class="tabelaresponsiva">
    <table class="tabelabja">
      <thead><tr><th>Nome</th><th>Equipe</th><th>Compras</th><th>Total gasto</th><th>Total pago</th><th>Saldo pendente</th><th>Acoes</th></tr></thead>
      <tbody>
        <?php foreach ($contas as $conta): ?>
          <?php $saldo = (float)$conta['total_gasto'] - (float)$conta['total_pago']; ?>
          <tr>
            <td><?= limparTexto($conta['nome']) ?></td>
            <td><?= limparTexto($conta['equipe_nome'] ?? '-') ?></td>
            <td><?= (int)$conta['total_compras'] ?></td>
            <td><?= formatarMoeda((float)$conta['total_gasto']) ?></td>
            <td><?= formatarMoeda((float)$conta['total_pago']) ?></td>
            <td><?= formatarMoeda($saldo) ?></td>
            <td><a class="btn btn-sm btn-contorno-vinho" href="contadetalhe.php?id=<?= $conta['id'] ?>">Ver Detalhes</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($contas)): ?>
          <tr><td colspan="7">
            <?php if ($filtrando): ?>
              Nenhum comprador encontrado com esses filtros. <a href="contas.php">Mostrar todos</a>
            <?php else: ?>
              Nenhum comprador cadastrado ainda. Use o menu <a href="importar.php">Importar Lista</a> ou cadastre em <a href="compradores.php">Compradores</a>.
            <?php endif; ?>
          </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</main>
<?php require __DIR__ . '/includes/rodape.php'; ?>
