<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?= isset($tituloPagina) ? limparTexto($tituloPagina) . ' | ' : '' ?><?= NOME_SISTEMA ?></title>
<link href="<?= URL_BASE ?>assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<link href="<?= URL_BASE ?>assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="topopagina">
  <button class="botao abrirmenu btn btn-vinho btn-toque" aria-label="Abrir menu">&#9776;</button>
  <strong><?= NOME_SISTEMA ?></strong>
  <span style="width:48px"></span>
</div>
<div class="fundomenu"></div>
<?php if (!empty($_SESSION['senha_padrao'])): ?>
<div class="aviso-senha">A senha ainda e a padrao (admin123). <a href="<?= URL_BASE ?>configuracoes.php">Troque agora</a>, principalmente se o sistema estiver na internet.</div>
<?php endif; ?>
<div class="layout">
