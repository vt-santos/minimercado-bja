<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/funcoes.php';
require_once __DIR__ . '/includes/autenticacao.php';

if (usuarioLogado()) {
    redirecionar(URL_BASE . 'index.php');
}

$mensagemErro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCsrf($_POST['csrf_token'] ?? '')) {
        $mensagemErro = 'Sessao expirada. Atualize a pagina e tente novamente.';
    } else {
        $usuarioDigitado = trim($_POST['usuario'] ?? '');
        $senhaDigitada = $_POST['senha'] ?? '';

        $pdo = Database::conectar();
        $ip = substr($_SERVER['REMOTE_ADDR'] ?? 'desconhecido', 0, 45);
        $limiteTempo = date('Y-m-d H:i:s', time() - 15 * 60);
        $pdo->prepare('DELETE FROM tentativas_login WHERE momento < :antigo')->execute(['antigo' => date('Y-m-d H:i:s', time() - 86400)]);
        $contaTentativas = $pdo->prepare('SELECT COUNT(*) AS total FROM tentativas_login WHERE ip = :ip AND momento >= :desde');
        $contaTentativas->execute(['ip' => $ip, 'desde' => $limiteTempo]);
        $bloqueado = (int)$contaTentativas->fetch()['total'] >= 8;

        if ($bloqueado) {
            $mensagemErro = 'Muitas tentativas erradas. Aguarde 15 minutos e tente de novo.';
        } elseif ($usuarioDigitado === '' || $senhaDigitada === '') {
            $mensagemErro = 'Informe usuario e senha.';
        } else {
            $consulta = $pdo->prepare('SELECT id, nome, senha_hash, ativo FROM usuarios WHERE usuario = :usuario LIMIT 1');
            $consulta->execute(['usuario' => $usuarioDigitado]);
            $usuarioEncontrado = $consulta->fetch();

            if ($usuarioEncontrado && (int)$usuarioEncontrado['ativo'] === 1 && password_verify($senhaDigitada, $usuarioEncontrado['senha_hash'])) {
                session_regenerate_id(true);
                $_SESSION['usuario_id'] = $usuarioEncontrado['id'];
                $_SESSION['usuario_nome'] = $usuarioEncontrado['nome'];
                $_SESSION['senha_padrao'] = ($senhaDigitada === 'admin123');
                $pdo->prepare('DELETE FROM tentativas_login WHERE ip = :ip')->execute(['ip' => $ip]);
                redirecionar(URL_BASE . 'index.php');
            } else {
                $pdo->prepare('INSERT INTO tentativas_login (ip, momento) VALUES (:ip, :momento)')->execute(['ip' => $ip, 'momento' => agora()]);
                $mensagemErro = 'Usuario ou senha invalidos.';
            }
        }
    }
}

$tokenCsrf = gerarTokenCsrf();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Login | <?= NOME_SISTEMA ?></title>
<link href="<?= URL_BASE ?>assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<link href="<?= URL_BASE ?>assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="telalogin">
  <div class="cartaologin">
    <div class="marcalogin">&#128717; MINIMERCADO BJA</div>
    <div class="sublinha">Acesso administrativo</div>

    <?php if ($mensagemErro): ?>
      <div class="alert alert-danger"><?= limparTexto($mensagemErro) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= $tokenCsrf ?>">
      <div class="mb-3 text-start">
        <label class="form-label">Usuario</label>
        <input type="text" name="usuario" class="form-control" required autofocus>
      </div>
      <div class="mb-4 text-start">
        <label class="form-label">Senha</label>
        <input type="password" name="senha" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-vinho w-100">Entrar</button>
    </form>
  </div>
</div>
</body>
</html>
