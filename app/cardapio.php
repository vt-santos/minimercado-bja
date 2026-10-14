<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/funcoes.php';
require_once __DIR__ . '/includes/autenticacao.php';

exigirLogin();
$pdo = Database::conectar();

$produtos = $pdo->query('SELECT * FROM produtos ORDER BY ativo DESC, nome')->fetchAll();

$tituloPagina = 'Cardapio';
$paginaAtual = 'cardapio.php';
require __DIR__ . '/includes/cabecalho.php';
require __DIR__ . '/includes/menulateral.php';
?>
<main class="conteudo">
  <h3 class="mb-4">Cardapio</h3>

  <div class="grade-produtos">
    <?php foreach ($produtos as $produto): ?>
      <div class="cartaoproduto <?= $produto['ativo'] ? '' : 'indisponivel' ?>">
        <div class="foto">&#127869;</div>
        <div class="nome"><?= limparTexto($produto['nome']) ?></div>
        <div class="preco"><?= formatarMoeda((float)$produto['preco']) ?></div>
        <div>
          <?= $produto['ativo'] ? '<span class="badge badge-status pago">Disponivel</span>' : '<span class="badge badge-status pendente">Indisponivel</span>' ?>
        </div>
        <?php if ($produto['ativo']): ?>
          <a href="registrarcompra.php?produto=<?= $produto['id'] ?>" class="btn btn-vinho btn-toque">Comprar</a>
        <?php else: ?>
          <button class="btn btn-vinho btn-toque" disabled>Indisponivel</button>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</main>
<?php require __DIR__ . '/includes/rodape.php'; ?>
