<?php
$paginaAtual = $paginaAtual ?? '';

$itensMenu = [
    ['arquivo' => 'index.php',               'icone' => '&#127968;', 'texto' => 'Dashboard'],
    ['arquivo' => 'cardapio.php',             'icone' => '&#128722;', 'texto' => 'Cardapio'],
    ['arquivo' => 'registrarcompra.php',      'icone' => '&#10133;',  'texto' => 'Registrar Compra'],
    ['arquivo' => 'compradores.php',          'icone' => '&#128101;', 'texto' => 'Compradores'],
    ['arquivo' => 'importar.php',             'icone' => '&#128228;', 'texto' => 'Importar Lista'],
    ['arquivo' => 'equipes.php',              'icone' => '&#127970;', 'texto' => 'Equipes'],
    ['arquivo' => 'historico.php',            'icone' => '&#128203;', 'texto' => 'Historico de Compras'],
    ['arquivo' => 'contas.php',               'icone' => '&#128176;', 'texto' => 'Contas dos Compradores'],
    ['arquivo' => 'pagamentos.php',           'icone' => '&#128179;', 'texto' => 'Pagamentos'],
    ['arquivo' => 'relatorios.php',           'icone' => '&#128202;', 'texto' => 'Relatorios'],
    ['arquivo' => 'produtos.php',             'icone' => '&#128230;', 'texto' => 'Produtos'],
    ['arquivo' => 'configuracoes.php',        'icone' => '&#9881;',   'texto' => 'Configuracoes'],
];
?>
<nav class="barra">
  <div class="marca"><span aria-hidden="true">&#128717;</span><span>MINIMERCADO <span class="destaque">BJA</span></span></div>
  <?php foreach ($itensMenu as $item): ?>
    <a class="item <?= $paginaAtual === $item['arquivo'] ? 'ativo' : '' ?>" href="<?= URL_BASE . $item['arquivo'] ?>">
      <span><?= $item['icone'] ?></span> <?= $item['texto'] ?>
    </a>
  <?php endforeach; ?>
  <a class="item" href="<?= URL_BASE ?>logout.php" style="margin-top:16px; border-top:1px solid rgba(255,255,255,0.2); padding-top:16px;">
    <span>&#128682;</span> Sair
  </a>
</nav>
