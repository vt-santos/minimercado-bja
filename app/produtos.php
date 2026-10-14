<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/funcoes.php';
require_once __DIR__ . '/includes/autenticacao.php';

exigirLogin();
$pdo = Database::conectar();

$mensagem = '';

// ---------- Salvar (criar ou editar) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'salvar') {
    if (!validarTokenCsrf($_POST['csrf_token'] ?? '')) {
        $mensagem = 'Sessao expirada, tente novamente.';
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $categoriaId = (int)($_POST['categoria_id'] ?? 0) ?: null;
        $preco = (float)str_replace(',', '.', $_POST['preco'] ?? '0');
        $ativo = isset($_POST['ativo']) ? 1 : 0;

        if ($nome === '' || $preco <= 0) {
            $mensagem = 'Informe nome e preco validos para o produto.';
        } else {
            if ($id > 0) {
                $comando = $pdo->prepare('UPDATE produtos SET nome=:nome, descricao=:descricao, categoria_id=:categoria_id, preco=:preco, ativo=:ativo, atualizado_em=:agora WHERE id=:id');
                $comando->execute(['nome' => $nome, 'descricao' => $descricao, 'categoria_id' => $categoriaId, 'preco' => $preco, 'ativo' => $ativo, 'agora' => agora(), 'id' => $id]);
            } else {
                $comando = $pdo->prepare('INSERT INTO produtos (nome, descricao, categoria_id, preco, ativo) VALUES (:nome, :descricao, :categoria_id, :preco, :ativo)');
                $comando->execute(['nome' => $nome, 'descricao' => $descricao, 'categoria_id' => $categoriaId, 'preco' => $preco, 'ativo' => $ativo]);
            }
            redirecionar(URL_BASE . 'produtos.php?sucesso=1');
        }
    }
}

// ---------- Excluir ----------
$tokenCsrf = gerarTokenCsrf();
if (isset($_GET['excluir']) && validarTokenCsrf($_GET['csrf_token'] ?? '')) {
    $idExcluir = (int)$_GET['excluir'];
    $comando = $pdo->prepare('DELETE FROM produtos WHERE id = :id');
    $comando->execute(['id' => $idExcluir]);
    redirecionar(URL_BASE . 'produtos.php?excluido=1');
}

$categorias = $pdo->query('SELECT id, nome FROM categorias ORDER BY nome')->fetchAll();
$produtos = $pdo->query('SELECT p.*, c.nome AS categoria_nome FROM produtos p LEFT JOIN categorias c ON c.id = p.categoria_id ORDER BY p.nome')->fetchAll();

$produtoEditar = null;
if (isset($_GET['editar'])) {
    $consulta = $pdo->prepare('SELECT * FROM produtos WHERE id = :id');
    $consulta->execute(['id' => (int)$_GET['editar']]);
    $produtoEditar = $consulta->fetch();
}

$tituloPagina = 'Produtos';
$paginaAtual = 'produtos.php';
require __DIR__ . '/includes/cabecalho.php';
require __DIR__ . '/includes/menulateral.php';
?>
<main class="conteudo">
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h3 class="mb-0">Produtos</h3>
    <button class="btn btn-vinho btn-toque" data-bs-toggle="modal" data-bs-target="#modalProduto" onclick="limparFormularioProduto()">+ Novo Produto</button>
  </div>

  <?php if (isset($_GET['sucesso'])): ?><div class="alert alert-success">Produto salvo com sucesso.</div><?php endif; ?>
  <?php if (isset($_GET['excluido'])): ?><div class="alert alert-success">Produto removido com sucesso.</div><?php endif; ?>
  <?php if ($mensagem): ?><div class="alert alert-danger"><?= limparTexto($mensagem) ?></div><?php endif; ?>

  <div class="tabelaresponsiva">
    <table class="tabelabja">
      <thead>
        <tr><th>Nome</th><th>Categoria</th><th>Preco</th><th>Status</th><th>Acoes</th></tr>
      </thead>
      <tbody>
        <?php foreach ($produtos as $produto): ?>
          <tr>
            <td><?= limparTexto($produto['nome']) ?></td>
            <td><?= limparTexto($produto['categoria_nome'] ?? '-') ?></td>
            <td><?= formatarMoeda((float)$produto['preco']) ?></td>
            <td><?= $produto['ativo'] ? '<span class="badge badge-status pago">Disponivel</span>' : '<span class="badge badge-status pendente">Indisponivel</span>' ?></td>
            <td>
              <button class="btn btn-sm btn-contorno-vinho" onclick='editarProduto(<?= json_encode($produto, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Editar</button>
              <a class="btn btn-sm btn-outline-danger" href="produtos.php?excluir=<?= $produto['id'] ?>&csrf_token=<?= $tokenCsrf ?>" onclick="return confirm('Confirma a exclusao deste produto?')">Excluir</a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($produtos)): ?>
          <tr><td colspan="5">Nenhum produto cadastrado.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</main>

<!-- Modal de cadastro/edicao -->
<div class="modal fade" id="modalProduto" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="post" id="formProduto">
        <input type="hidden" name="csrf_token" value="<?= $tokenCsrf ?>">
        <input type="hidden" name="acao" value="salvar">
        <input type="hidden" name="id" id="campoId" value="">
        <div class="modal-header">
          <h5 class="modal-title">Produto</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Nome</label>
            <input type="text" name="nome" id="campoNome" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Descricao</label>
            <input type="text" name="descricao" id="campoDescricao" class="form-control">
          </div>
          <div class="row">
            <div class="col-6 mb-3">
              <label class="form-label">Categoria</label>
              <select name="categoria_id" id="campoCategoria" class="form-select">
                <option value="">Selecione</option>
                <?php foreach ($categorias as $categoria): ?>
                  <option value="<?= $categoria['id'] ?>"><?= limparTexto($categoria['nome']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-6 mb-3">
              <label class="form-label">Preco (R$)</label>
              <input type="text" name="preco" id="campoPreco" class="form-control" placeholder="0,00" required>
            </div>
          </div>
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="ativo" id="campoAtivo" checked>
            <label class="form-check-label" for="campoAtivo">Produto disponivel</label>
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
function limparFormularioProduto() {
  document.getElementById('campoId').value = '';
  document.getElementById('campoNome').value = '';
  document.getElementById('campoDescricao').value = '';
  document.getElementById('campoCategoria').value = '';
  document.getElementById('campoPreco').value = '';
  document.getElementById('campoAtivo').checked = true;
}

function editarProduto(produto) {
  document.getElementById('campoId').value = produto.id;
  document.getElementById('campoNome').value = produto.nome;
  document.getElementById('campoDescricao').value = produto.descricao || '';
  document.getElementById('campoCategoria').value = produto.categoria_id || '';
  document.getElementById('campoPreco').value = parseFloat(produto.preco).toFixed(2).replace('.', ',');
  document.getElementById('campoAtivo').checked = produto.ativo == 1;
  var modal = new bootstrap.Modal(document.getElementById('modalProduto'));
  modal.show();
}
</script>

<?php require __DIR__ . '/includes/rodape.php'; ?>
