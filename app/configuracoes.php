<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/funcoes.php';
require_once __DIR__ . '/includes/autenticacao.php';

exigirLogin();
$pdo = Database::conectar();

$mensagem = '';
$mensagemTipo = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'trocarsenha') {
    if (!validarTokenCsrf($_POST['csrf_token'] ?? '')) {
        $mensagem = 'Sessao expirada, tente novamente.';
        $mensagemTipo = 'danger';
    } else {
        $senhaAtual = $_POST['senha_atual'] ?? '';
        $senhaNova = $_POST['senha_nova'] ?? '';
        $senhaConfirmacao = $_POST['senha_confirmacao'] ?? '';

        $consulta = $pdo->prepare('SELECT senha_hash FROM usuarios WHERE id = :id');
        $consulta->execute(['id' => $_SESSION['usuario_id']]);
        $usuario = $consulta->fetch();

        if (!$usuario || !password_verify($senhaAtual, $usuario['senha_hash'])) {
            $mensagem = 'Senha atual incorreta.';
            $mensagemTipo = 'danger';
        } elseif (strlen($senhaNova) < 6) {
            $mensagem = 'A nova senha deve ter ao menos 6 caracteres.';
            $mensagemTipo = 'danger';
        } elseif ($senhaNova !== $senhaConfirmacao) {
            $mensagem = 'A confirmacao de senha nao confere.';
            $mensagemTipo = 'danger';
        } else {
            $novoHash = password_hash($senhaNova, PASSWORD_BCRYPT);
            $pdo->prepare('UPDATE usuarios SET senha_hash = :hash WHERE id = :id')
                ->execute(['hash' => $novoHash, 'id' => $_SESSION['usuario_id']]);
            $mensagem = 'Senha atualizada com sucesso.';
            $_SESSION['senha_padrao'] = false;
        }
    }
}

$tokenCsrf = gerarTokenCsrf();
$tituloPagina = 'Configuracoes';
$paginaAtual = 'configuracoes.php';
require __DIR__ . '/includes/cabecalho.php';
require __DIR__ . '/includes/menulateral.php';
?>
<main class="conteudo">
  <h3 class="mb-4">Configuracoes</h3>

  <?php if ($mensagem): ?><div class="alert alert-<?= $mensagemTipo ?>"><?= limparTexto($mensagem) ?></div><?php endif; ?>

  <div class="cartao" style="max-width:480px;">
    <h5 class="mb-3">Alterar senha de acesso</h5>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= $tokenCsrf ?>">
      <input type="hidden" name="acao" value="trocarsenha">
      <div class="mb-3">
        <label class="form-label">Senha atual</label>
        <input type="password" name="senha_atual" class="form-control" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Nova senha</label>
        <input type="password" name="senha_nova" class="form-control" required minlength="6">
      </div>
      <div class="mb-3">
        <label class="form-label">Confirmar nova senha</label>
        <input type="password" name="senha_confirmacao" class="form-control" required minlength="6">
      </div>
      <button type="submit" class="btn btn-vinho w-100">Salvar Nova Senha</button>
    </form>
  </div>

  <div class="cartao mt-3" style="max-width:480px;">
    <h5 class="mb-2">Diagnostico do sistema</h5>
    <p class="text-muted mb-3">Mostra se o banco, os produtos, os compradores e o relogio estao certos. Use quando alguma tela aparecer vazia.</p>
    <a href="diagnostico.php" class="btn btn-contorno-vinho w-100">Abrir diagnostico</a>
  </div>
</main>
<?php require __DIR__ . '/includes/rodape.php'; ?>
