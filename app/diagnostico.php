<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/funcoes.php';
require_once __DIR__ . '/includes/autenticacao.php';
require_once __DIR__ . '/includes/migracao.php';

exigirLogin();
$pdo = Database::conectar();

$verificacoes = []; // [titulo, ok(bool|null), detalhe]
$avisos = [];

// Banco
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
$verificacoes[] = ['Conexao com o banco', true, strtoupper($driver) . ' ' . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION)];

// Estrutura (cria colunas faltantes, se houver)
$criadas = garantirEstruturaBanco($pdo);
$verificacoes[] = ['Estrutura do banco', true, $criadas ? 'Colunas criadas agora: ' . implode(', ', $criadas) : 'Completa (cor da equipe e responsavel do comprador)'];

// Relogio
$horaPhp = agora();
if ($driver === 'mysql') {
    $horaBanco = (string)$pdo->query('SELECT NOW() AS agora')->fetch()['agora'];
    $diferenca = abs(strtotime($horaBanco) - strtotime($horaPhp));
    $verificacoes[] = ['Relogio do banco x sistema', $diferenca < 120, 'Sistema: ' . formatarDataHora($horaPhp) . ' | Banco: ' . formatarDataHora($horaBanco)];
    if ($diferenca >= 120) {
        $avisos[] = 'O relogio do banco esta diferente do sistema. As compras novas usam a hora do sistema, mas confira a data/hora do notebook.';
    }
} else {
    $verificacoes[] = ['Relogio do sistema', true, formatarDataHora($horaPhp)];
}

// Contagens
$contar = fn(string $sql): int => (int)$pdo->query($sql)->fetch()['total'];
$totalProdutos = $contar('SELECT COUNT(*) AS total FROM produtos');
$produtosAtivos = $contar('SELECT COUNT(*) AS total FROM produtos WHERE ativo = 1 AND preco > 0');
$totalEquipes = $contar('SELECT COUNT(*) AS total FROM equipes');
$totalCompradores = $contar('SELECT COUNT(*) AS total FROM compradores');
$compradoresAtivos = $contar('SELECT COUNT(*) AS total FROM compradores WHERE ativo = 1');
$totalCompras = $contar('SELECT COUNT(*) AS total FROM compras');
$totalPagamentos = $contar('SELECT COUNT(*) AS total FROM pagamentos');
$ultimaCompra = $pdo->query('SELECT MAX(data_hora) AS ultima FROM compras')->fetch()['ultima'] ?? null;

$verificacoes[] = ['Produtos a venda', $produtosAtivos > 0, $produtosAtivos . ' disponiveis com preco (de ' . $totalProdutos . ' cadastrados)'];
$verificacoes[] = ['Equipes', $totalEquipes > 0, (string)$totalEquipes];
$verificacoes[] = ['Compradores', $compradoresAtivos > 0, $compradoresAtivos . ' ativos (de ' . $totalCompradores . ')'];
$verificacoes[] = ['Compras registradas', $totalCompras > 0 ? true : null, $totalCompras . ($ultimaCompra ? ' (ultima: ' . formatarDataHora($ultimaCompra) . ')' : '')];
$verificacoes[] = ['Pagamentos registrados', null, (string)$totalPagamentos];

if ($totalCompradores === 0) {
    $avisos[] = 'Nao ha compradores cadastrados. Sem comprador nao da para registrar compra, e por isso Historico, Contas e Relatorios ficam vazios. Use o menu "Importar Lista".';
}
if ($produtosAtivos === 0) {
    $avisos[] = 'Nenhum produto esta disponivel com preco. Em "Produtos", informe o preco e marque "Produto disponivel".';
}
if ($totalCompras === 0 && $totalCompradores > 0) {
    $avisos[] = 'Ainda nao ha compras registradas: Historico, Contas (valores) e Relatorios so mostram dados depois da primeira compra confirmada.';
}

// Arquivos
$bootstrapOk = is_file(__DIR__ . '/assets/vendor/bootstrap/css/bootstrap.min.css') && is_file(__DIR__ . '/assets/vendor/bootstrap/js/bootstrap.bundle.min.js');
$verificacoes[] = ['Arquivos de estilo (Bootstrap)', $bootstrapOk, $bootstrapOk ? 'Encontrados' : 'Faltando em assets/vendor/bootstrap'];
$verificacoes[] = ['Endereco base do sistema', true, URL_BASE];

$tituloPagina = 'Diagnostico';
$paginaAtual = 'configuracoes.php';
require __DIR__ . '/includes/cabecalho.php';
require __DIR__ . '/includes/menulateral.php';
?>
<main class="conteudo">
  <h3 class="mb-4">Diagnostico do sistema</h3>

  <?php foreach ($avisos as $aviso): ?>
    <div class="alert alert-warning alerta-fixo"><?= limparTexto($aviso) ?></div>
  <?php endforeach; ?>

  <div class="tabelaresponsiva">
    <table class="tabelabja tabela-compacta">
      <thead><tr><th>Verificacao</th><th>Situacao</th><th>Detalhe</th></tr></thead>
      <tbody>
        <?php foreach ($verificacoes as [$titulo, $ok, $detalhe]): ?>
          <tr>
            <td><?= limparTexto($titulo) ?></td>
            <td>
              <?php if ($ok === true): ?><span class="badge badge-status pago">OK</span>
              <?php elseif ($ok === false): ?><span class="badge badge-status pendente">Atencao</span>
              <?php else: ?><span class="badge badge-status parcial">Info</span><?php endif; ?>
            </td>
            <td><?= limparTexto($detalhe) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="text-muted mt-3">Se algo estiver como "Atencao" e voce nao souber resolver, tire um print desta tela.</p>
</main>
<?php require __DIR__ . '/includes/rodape.php'; ?>
