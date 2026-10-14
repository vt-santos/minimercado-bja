<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/funcoes.php';
require_once __DIR__ . '/includes/autenticacao.php';

exigirLogin();
$pdo = Database::conectar();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'salvar') {
    if (validarTokenCsrf($_POST['csrf_token'] ?? '')) {
        $id = (int)($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $equipeId = (int)($_POST['equipe_id'] ?? 0) ?: null;
        $telefone = trim($_POST['telefone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $responsavelNome = trim($_POST['responsavel_nome'] ?? '');
        $telefoneResponsavel = trim($_POST['telefone_responsavel'] ?? '');
        $ativo = isset($_POST['ativo']) ? 1 : 0;

        if ($nome !== '') {
            if ($id > 0) {
                $comando = $pdo->prepare('UPDATE compradores SET nome=:nome, equipe_id=:equipe_id, telefone=:telefone, email=:email, responsavel_nome=:responsavel_nome, telefone_responsavel=:telefone_responsavel, ativo=:ativo WHERE id=:id');
                $comando->execute(['nome' => $nome, 'equipe_id' => $equipeId, 'telefone' => $telefone, 'email' => $email, 'responsavel_nome' => $responsavelNome ?: null, 'telefone_responsavel' => $telefoneResponsavel ?: null, 'ativo' => $ativo, 'id' => $id]);
            } else {
                $comando = $pdo->prepare('INSERT INTO compradores (nome, equipe_id, telefone, email, responsavel_nome, telefone_responsavel, ativo) VALUES (:nome, :equipe_id, :telefone, :email, :responsavel_nome, :telefone_responsavel, :ativo)');
                $comando->execute(['nome' => $nome, 'equipe_id' => $equipeId, 'telefone' => $telefone, 'email' => $email, 'responsavel_nome' => $responsavelNome ?: null, 'telefone_responsavel' => $telefoneResponsavel ?: null, 'ativo' => $ativo]);
            }
            redirecionar(URL_BASE . 'compradores.php?sucesso=1');
        }
    }
}

$tokenCsrf = gerarTokenCsrf();

$erroExclusao = '';
if (isset($_GET['excluir']) && validarTokenCsrf($_GET['csrf_token'] ?? '')) {
    try {
        $comando = $pdo->prepare('DELETE FROM compradores WHERE id = :id');
        $comando->execute(['id' => (int)$_GET['excluir']]);
        redirecionar(URL_BASE . 'compradores.php?excluido=1');
    } catch (PDOException $e) {
        // Comprador possui compras registradas (restricao de chave estrangeira).
        $erroExclusao = 'Nao foi possivel excluir: este comprador ja possui compras registradas. Marque-o como inativo em vez de excluir.';
    }
}

$termoBusca = trim($_GET['busca'] ?? '');
$filtroEquipe = (int)($_GET['equipe'] ?? 0);

$sql = 'SELECT cp.*, eq.nome AS equipe_nome, eq.cor AS equipe_cor FROM compradores cp LEFT JOIN equipes eq ON eq.id = cp.equipe_id';
$condicoes = [];
$parametros = [];
if ($termoBusca !== '') {
    $condicoes[] = 'cp.nome LIKE :busca';
    $parametros['busca'] = '%' . $termoBusca . '%';
}
if ($filtroEquipe > 0) {
    $condicoes[] = 'cp.equipe_id = :equipe';
    $parametros['equipe'] = $filtroEquipe;
} elseif ($filtroEquipe === -1) {
    $condicoes[] = 'cp.equipe_id IS NULL';
}
if ($condicoes) {
    $sql .= ' WHERE ' . implode(' AND ', $condicoes);
}
$sql .= ' ORDER BY cp.nome';

$consulta = $pdo->prepare($sql);
$consulta->execute($parametros);
$compradores = $consulta->fetchAll();

$equipes = buscarEquipes($pdo);
$equipesFiltro = buscarEquipes($pdo, false);

$tituloPagina = 'Compradores';
$paginaAtual = 'compradores.php';
require __DIR__ . '/includes/cabecalho.php';
require __DIR__ . '/includes/menulateral.php';
?>
<main class="conteudo">
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h3 class="mb-0">Compradores</h3>
    <button class="btn btn-vinho btn-toque" data-bs-toggle="modal" data-bs-target="#modalComprador" onclick="limparFormularioComprador()">+ Novo Comprador</button>
  </div>

  <?php if (isset($_GET['sucesso'])): ?><div class="alert alert-success">Comprador salvo com sucesso.</div><?php endif; ?>
  <?php if (isset($_GET['excluido'])): ?><div class="alert alert-success">Comprador removido com sucesso.</div><?php endif; ?>
  <?php if ($erroExclusao): ?><div class="alert alert-danger"><?= limparTexto($erroExclusao) ?></div><?php endif; ?>

  <form method="get" class="row g-2 mb-3 align-items-end">
    <div class="col-12 col-md-5">
      <label class="form-label">Nome</label>
      <input type="text" name="busca" class="form-control" placeholder="Buscar por nome" value="<?= limparTexto($termoBusca) ?>">
    </div>
    <div class="col-8 col-md-4">
      <label class="form-label">Equipe / círculo</label>
      <select name="equipe" class="form-select" onchange="this.form.submit()">
        <option value="">Todas</option>
        <?= opcoesEquipes($equipesFiltro, $filtroEquipe > 0 ? $filtroEquipe : null) ?>
        <option value="-1" <?= $filtroEquipe === -1 ? 'selected' : '' ?>>Sem equipe</option>
      </select>
    </div>
    <div class="col-4 col-md-3">
      <button class="btn btn-contorno-vinho w-100" type="submit">Buscar</button>
    </div>
  </form>
  <p class="text-muted mb-2"><?= count($compradores) ?> comprador(es)
    <?php if ($termoBusca !== '' || $filtroEquipe !== 0): ?>&middot; <a href="compradores.php">Limpar filtros</a><?php endif; ?></p>

  <div class="tabelaresponsiva">
    <table class="tabelabja">
      <thead><tr><th>Nome</th><th>Equipe</th><th>Telefone</th><th>Responsavel</th><th>Status</th><th>Acoes</th></tr></thead>
      <tbody>
        <?php foreach ($compradores as $comprador): ?>
          <tr>
            <td><a href="contadetalhe.php?id=<?= $comprador['id'] ?>"><?= limparTexto($comprador['nome']) ?></a></td>
            <td>
              <?php if (!empty($comprador['equipe_cor'])): ?><span class="amostracor" style="background: <?= limparTexto($comprador['equipe_cor']) ?>"></span><?php endif; ?>
              <?= limparTexto($comprador['equipe_nome'] ?? '-') ?>
            </td>
            <td><?= limparTexto($comprador['telefone'] ?? '-') ?></td>
            <td>
              <?php if ($comprador['responsavel_nome'] || $comprador['telefone_responsavel']): ?>
                <?= limparTexto($comprador['responsavel_nome'] ?? '-') ?><br>
                <small class="text-muted"><?= limparTexto($comprador['telefone_responsavel'] ?? '-') ?></small>
              <?php else: ?>
                -
              <?php endif; ?>
            </td>
            <td><?= $comprador['ativo'] ? '<span class="badge badge-status pago">Ativo</span>' : '<span class="badge badge-status pendente">Inativo</span>' ?></td>
            <td>
              <button class="btn btn-sm btn-contorno-vinho" onclick='editarComprador(<?= json_encode($comprador, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Editar</button>
              <a class="btn btn-sm btn-outline-danger" href="compradores.php?excluir=<?= $comprador['id'] ?>&csrf_token=<?= $tokenCsrf ?>" onclick="return confirm('Confirma a exclusao deste comprador?')">Excluir</a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($compradores)): ?>
          <tr><td colspan="6">Nenhum comprador encontrado.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</main>

<div class="modal fade" id="modalComprador" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= $tokenCsrf ?>">
        <input type="hidden" name="acao" value="salvar">
        <input type="hidden" name="id" id="campoId" value="">
        <div class="modal-header">
          <h5 class="modal-title">Comprador</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Nome completo</label>
            <input type="text" name="nome" id="campoNome" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Equipe</label>
            <select name="equipe_id" id="campoEquipe" class="form-select">
              <option value="">Selecione</option>
              <?= opcoesEquipes($equipes) ?>
            </select>
          </div>
          <div class="row">
            <div class="col-6 mb-3">
              <label class="form-label">Telefone</label>
              <input type="text" name="telefone" id="campoTelefone" class="form-control">
            </div>
            <div class="col-6 mb-3">
              <label class="form-label">E-mail</label>
              <input type="email" name="email" id="campoEmail" class="form-control">
            </div>
          </div>
          <hr>
          <div class="form-text mb-2">Preencha abaixo apenas se este comprador for menor de idade e o pagamento for feito por um responsavel.</div>
          <div class="row">
            <div class="col-6 mb-3">
              <label class="form-label">Nome do responsavel</label>
              <input type="text" name="responsavel_nome" id="campoResponsavelNome" class="form-control">
            </div>
            <div class="col-6 mb-3">
              <label class="form-label">Telefone do responsavel</label>
              <input type="text" name="telefone_responsavel" id="campoTelefoneResponsavel" class="form-control">
            </div>
          </div>
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="ativo" id="campoAtivo" checked>
            <label class="form-check-label" for="campoAtivo">Comprador ativo</label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-vinho">Salvar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function limparFormularioComprador() {
  document.getElementById('campoId').value = '';
  document.getElementById('campoNome').value = '';
  document.getElementById('campoEquipe').value = '';
  document.getElementById('campoTelefone').value = '';
  document.getElementById('campoEmail').value = '';
  document.getElementById('campoResponsavelNome').value = '';
  document.getElementById('campoTelefoneResponsavel').value = '';
  document.getElementById('campoAtivo').checked = true;
}

function editarComprador(comprador) {
  document.getElementById('campoId').value = comprador.id;
  document.getElementById('campoNome').value = comprador.nome;
  document.getElementById('campoEquipe').value = comprador.equipe_id || '';
  document.getElementById('campoTelefone').value = comprador.telefone || '';
  document.getElementById('campoEmail').value = comprador.email || '';
  document.getElementById('campoResponsavelNome').value = comprador.responsavel_nome || '';
  document.getElementById('campoTelefoneResponsavel').value = comprador.telefone_responsavel || '';
  document.getElementById('campoAtivo').checked = comprador.ativo == 1;
  var modal = new bootstrap.Modal(document.getElementById('modalComprador'));
  modal.show();
}
</script>

<?php require __DIR__ . '/includes/rodape.php'; ?>
