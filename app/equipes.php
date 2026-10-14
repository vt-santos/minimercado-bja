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
        $cor = trim($_POST['cor'] ?? '') ?: '#6e0f1a';
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        $tipo = ($_POST['tipo'] ?? '') === 'circulo' ? 'circulo' : 'trabalho';

        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $cor)) {
            $cor = '#6e0f1a';
        }

        if ($nome !== '') {
            if ($id > 0) {
                $comando = $pdo->prepare('UPDATE equipes SET nome=:nome, cor=:cor, tipo=:tipo, ativo=:ativo WHERE id=:id');
                $comando->execute(['nome' => $nome, 'cor' => $cor, 'tipo' => $tipo, 'ativo' => $ativo, 'id' => $id]);
            } else {
                $comando = $pdo->prepare('INSERT INTO equipes (nome, cor, tipo, ativo) VALUES (:nome, :cor, :tipo, :ativo)');
                $comando->execute(['nome' => $nome, 'cor' => $cor, 'tipo' => $tipo, 'ativo' => $ativo]);
            }
            redirecionar(URL_BASE . 'equipes.php?sucesso=1');
        }
    }
}

$tokenCsrf = gerarTokenCsrf();
if (isset($_GET['excluir']) && validarTokenCsrf($_GET['csrf_token'] ?? '')) {
    $comando = $pdo->prepare('DELETE FROM equipes WHERE id = :id');
    $comando->execute(['id' => (int)$_GET['excluir']]);
    redirecionar(URL_BASE . 'equipes.php?excluido=1');
}

$equipes = $pdo->query(
    'SELECT eq.*,
            (SELECT COUNT(*) FROM compradores cp WHERE cp.equipe_id = eq.id) AS total_integrantes,
            COALESCE((SELECT SUM(c.valor_total) FROM compras c WHERE c.equipe_id = eq.id), 0) AS total_consumido
     FROM equipes eq
     ORDER BY CASE WHEN eq.tipo = \'circulo\' THEN 0 ELSE 1 END, eq.nome'
)->fetchAll();

$tituloPagina = 'Equipes';
$paginaAtual = 'equipes.php';
require __DIR__ . '/includes/cabecalho.php';
require __DIR__ . '/includes/menulateral.php';
?>
<main class="conteudo">
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h3 class="mb-0">Equipes</h3>
    <button class="btn btn-vinho btn-toque" data-bs-toggle="modal" data-bs-target="#modalEquipe" onclick="limparFormularioEquipe()">+ Nova Equipe</button>
  </div>

  <?php if (isset($_GET['sucesso'])): ?><div class="alert alert-success">Equipe salva com sucesso.</div><?php endif; ?>
  <?php if (isset($_GET['excluido'])): ?><div class="alert alert-success">Equipe removida com sucesso.</div><?php endif; ?>

  <div class="tabelaresponsiva">
    <table class="tabelabja">
      <thead><tr><th>Cor</th><th>Equipe</th><th>Tipo</th><th>Integrantes</th><th>Total consumido</th><th>Status</th><th>Acoes</th></tr></thead>
      <tbody>
        <?php foreach ($equipes as $equipe): ?>
          <tr>
            <td><span class="amostracor" style="background: <?= limparTexto($equipe['cor']) ?>"></span></td>
            <td><?= limparTexto($equipe['nome']) ?></td>
            <td><?= ($equipe['tipo'] ?? '') === 'circulo' ? 'Jovens (círculo)' : 'Trabalho' ?></td>
            <td><?= (int)$equipe['total_integrantes'] ?></td>
            <td><?= formatarMoeda((float)$equipe['total_consumido']) ?></td>
            <td><?= $equipe['ativo'] ? '<span class="badge badge-status pago">Ativa</span>' : '<span class="badge badge-status pendente">Inativa</span>' ?></td>
            <td>
              <button class="btn btn-sm btn-contorno-vinho" onclick='editarEquipe(<?= json_encode($equipe, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Editar</button>
              <a class="btn btn-sm btn-outline-danger" href="equipes.php?excluir=<?= $equipe['id'] ?>&csrf_token=<?= $tokenCsrf ?>" onclick="return confirm('Confirma a exclusao desta equipe?')">Excluir</a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($equipes)): ?>
          <tr><td colspan="7">Nenhuma equipe cadastrada.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</main>

<div class="modal fade" id="modalEquipe" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= $tokenCsrf ?>">
        <input type="hidden" name="acao" value="salvar">
        <input type="hidden" name="id" id="campoId" value="">
        <div class="modal-header">
          <h5 class="modal-title">Equipe</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Nome da equipe</label>
            <input type="text" name="nome" id="campoNome" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Tipo</label>
            <select name="tipo" id="campoTipo" class="form-select">
              <option value="trabalho">Equipe de trabalho</option>
              <option value="circulo">Círculo dos jovens do encontro</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Cor da equipe</label>
            <input type="color" name="cor" id="campoCor" class="form-control form-control-color" value="#6e0f1a">
            <div class="form-text">Ajuda a identificar a equipe rapidamente, mesmo para quem nao le o nome.</div>
          </div>
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="ativo" id="campoAtivo" checked>
            <label class="form-check-label" for="campoAtivo">Equipe ativa</label>
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
function limparFormularioEquipe() {
  document.getElementById('campoId').value = '';
  document.getElementById('campoNome').value = '';
  document.getElementById('campoCor').value = '#6e0f1a';
  document.getElementById('campoTipo').value = 'trabalho';
  document.getElementById('campoAtivo').checked = true;
}

function editarEquipe(equipe) {
  document.getElementById('campoId').value = equipe.id;
  document.getElementById('campoNome').value = equipe.nome;
  document.getElementById('campoCor').value = equipe.cor || '#6e0f1a';
  document.getElementById('campoTipo').value = equipe.tipo === 'circulo' ? 'circulo' : 'trabalho';
  document.getElementById('campoAtivo').checked = equipe.ativo == 1;
  var modal = new bootstrap.Modal(document.getElementById('modalEquipe'));
  modal.show();
}
</script>

<?php require __DIR__ . '/includes/rodape.php'; ?>
