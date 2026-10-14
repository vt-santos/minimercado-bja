<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/funcoes.php';
require_once __DIR__ . '/includes/autenticacao.php';

exigirLogin();
$pdo = Database::conectar();

/** Minusculas, sem acentos e sem pontuacao: "Gráfica " e "grafica" viram a mesma chave. */
function chaveTexto(string $texto): string
{
    $mapa = [
        'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c',
    ];
    $texto = strtr(mb_strtolower(trim($texto), 'UTF-8'), $mapa);
    return trim(preg_replace('/[^a-z0-9]+/', ' ', $texto));
}

/** Le o CSV enviado: devolve [cabecalhos normalizados => indice, linhas] ou string de erro. */
function lerCsvEnviado(string $campo)
{
    if (!isset($_FILES[$campo]) || $_FILES[$campo]['error'] !== UPLOAD_ERR_OK) {
        return 'Selecione um arquivo CSV para importar.';
    }
    if ($_FILES[$campo]['size'] > 1024 * 1024) {
        return 'Arquivo muito grande (maximo 1 MB).';
    }
    $conteudo = (string)file_get_contents($_FILES[$campo]['tmp_name']);
    $conteudo = preg_replace('/^\xEF\xBB\xBF/', '', $conteudo); // BOM do Excel
    if (!mb_check_encoding($conteudo, 'UTF-8')) {
        $conteudo = mb_convert_encoding($conteudo, 'UTF-8', 'Windows-1252'); // CSV do Excel em portugues
    }
    $linhas = preg_split('/\r\n|\n|\r/', trim($conteudo));
    $primeira = $linhas[0] ?? '';
    $delimitador = substr_count($primeira, ';') >= substr_count($primeira, ',') ? ';' : ',';
    $cabecalhos = [];
    foreach (str_getcsv($primeira, $delimitador, '"', '\\') as $i => $titulo) {
        $cabecalhos[chaveTexto((string)$titulo)] = $i;
    }
    $dados = [];
    for ($i = 1; $i < count($linhas); $i++) {
        if (trim($linhas[$i]) !== '') {
            $dados[] = str_getcsv($linhas[$i], $delimitador, '"', '\\');
        }
    }
    return [$cabecalhos, $dados];
}

/** Indice da primeira coluna cujo titulo esteja entre os sinonimos (ou null). */
function colunaPorSinonimos(array $cabecalhos, array $sinonimos): ?int
{
    foreach ($sinonimos as $sinonimo) {
        if (isset($cabecalhos[$sinonimo])) {
            return $cabecalhos[$sinonimo];
        }
    }
    return null;
}

function celula(array $linha, ?int $indice): string
{
    return $indice === null ? '' : trim((string)($linha[$indice] ?? ''));
}

// 18 cores bem diferentes entre si para as equipes novas
const PALETA_EQUIPES = [
    '#e6194b', '#3cb44b', '#4363d8', '#f58231', '#911eb4', '#42d4f4',
    '#f032e6', '#9a6324', '#469990', '#800000', '#808000', '#000075',
    '#bfef45', '#fabed4', '#ffe119', '#dcbeff', '#aaffc3', '#a9a9a9',
];

$abaAtiva = ($_GET['aba'] ?? $_POST['tipo'] ?? 'participantes') === 'produtos' ? 'produtos' : 'participantes';
$resultado = null;
$mensagemErro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCsrf($_POST['csrf_token'] ?? '')) {
        $mensagemErro = 'Sessao expirada, atualize a pagina e tente novamente.';
    } else {
        $leitura = lerCsvEnviado('arquivo');
        if (is_string($leitura)) {
            $mensagemErro = $leitura;
        } elseif ($abaAtiva === 'participantes') {
            // ======================= PARTICIPANTES =======================
            [$cab, $linhas] = $leitura;
            $colNome = colunaPorSinonimos($cab, ['nome', 'nome completo', 'participante', 'jovem']);
            $colEquipe = colunaPorSinonimos($cab, ['equipe', 'equipe de trabalho', 'time', 'grupo', 'circulo', 'cor do circulo', 'equipe circulo']);
            $colTelefone = colunaPorSinonimos($cab, ['telefone', 'celular', 'whatsapp', 'fone', 'contato']);
            $colResp = colunaPorSinonimos($cab, ['responsavel', 'nome do responsavel', 'nome responsavel']);
            $colTelResp = colunaPorSinonimos($cab, ['telefone do responsavel', 'telefone responsavel', 'tel responsavel', 'celular responsavel', 'contato responsavel']);
            $colCor = colunaPorSinonimos($cab, ['cor', 'cor da equipe']);

            if ($colNome === null) {
                $mensagemErro = 'Nao encontrei a coluna "Nome" na primeira linha da planilha. Use o modelo para conferir os titulos das colunas.';
            } else {
                $equipes = [];
                foreach ($pdo->query('SELECT id, nome, cor FROM equipes')->fetchAll() as $eq) {
                    $equipes[chaveTexto($eq['nome'])] = $eq;
                }
                $coresUsadas = array_map(fn($eq) => strtolower((string)$eq['cor']), $equipes);
                $proximaCor = function () use (&$coresUsadas): string {
                    foreach (PALETA_EQUIPES as $cor) {
                        if (!in_array($cor, $coresUsadas, true)) {
                            $coresUsadas[] = $cor;
                            return $cor;
                        }
                    }
                    return PALETA_EQUIPES[count($coresUsadas) % count(PALETA_EQUIPES)];
                };

                $compradoresExistentes = [];
                foreach ($pdo->query('SELECT id, nome FROM compradores')->fetchAll() as $cp) {
                    $compradoresExistentes[chaveTexto($cp['nome'])] = (int)$cp['id'];
                }

                $inserirEquipe = $pdo->prepare('INSERT INTO equipes (nome, cor, ativo) VALUES (:nome, :cor, 1)');
                $renomearEquipe = $pdo->prepare('UPDATE equipes SET nome = :nome WHERE id = :id');
                $mudarCor = $pdo->prepare('UPDATE equipes SET cor = :cor WHERE id = :id');
                $inserirComprador = $pdo->prepare(
                    'INSERT INTO compradores (nome, equipe_id, telefone, responsavel_nome, telefone_responsavel, ativo)
                     VALUES (:nome, :equipe_id, :telefone, :responsavel_nome, :telefone_responsavel, 1)'
                );
                $atualizarComprador = $pdo->prepare(
                    'UPDATE compradores SET equipe_id = COALESCE(:equipe_id, equipe_id), telefone = COALESCE(:telefone, telefone),
                            responsavel_nome = COALESCE(:responsavel_nome, responsavel_nome),
                            telefone_responsavel = COALESCE(:telefone_responsavel, telefone_responsavel)
                     WHERE id = :id'
                );

                $novos = 0; $atualizados = 0; $semNome = 0; $equipesCriadas = [];
                $atualizarExistentes = !empty($_POST['atualizar_existentes']);
                try {
                    $pdo->beginTransaction();
                    foreach ($linhas as $linha) {
                        $nome = celula($linha, $colNome);
                        if ($nome === '') { $semNome++; continue; }

                        $equipeId = null;
                        $nomeEquipe = celula($linha, $colEquipe);
                        // "Rosa", "verde", "Círculo Azul"... viram o circulo dos jovens
                        $chaveCirculo = preg_replace('/^circulo /', '', chaveTexto($nomeEquipe));
                        $circulos = ['rosa' => 'Círculo Rosa', 'verde' => 'Círculo Verde', 'azul' => 'Círculo Azul', 'laranja' => 'Círculo Laranja'];
                        if (isset($circulos[$chaveCirculo])) {
                            $nomeEquipe = $circulos[$chaveCirculo];
                        }
                        if ($nomeEquipe !== '') {
                            $chave = chaveTexto($nomeEquipe);
                            $corPlanilha = strtolower(celula($linha, $colCor));
                            $corValida = preg_match('/^#[0-9a-f]{6}$/', $corPlanilha) ? $corPlanilha : '';
                            if (!isset($equipes[$chave])) {
                                $cor = $corValida ?: $proximaCor();
                                $inserirEquipe->execute(['nome' => $nomeEquipe, 'cor' => $cor]);
                                $equipes[$chave] = ['id' => (int)$pdo->lastInsertId(), 'nome' => $nomeEquipe, 'cor' => $cor];
                                $equipesCriadas[] = $nomeEquipe;
                            } else {
                                // "Grafica" ja cadastrada vira "Gráfica" (grafia da planilha)
                                if ($equipes[$chave]['nome'] !== $nomeEquipe) {
                                    $renomearEquipe->execute(['nome' => $nomeEquipe, 'id' => $equipes[$chave]['id']]);
                                    $equipes[$chave]['nome'] = $nomeEquipe;
                                }
                                if ($corValida && $corValida !== strtolower((string)$equipes[$chave]['cor'])) {
                                    $mudarCor->execute(['cor' => $corValida, 'id' => $equipes[$chave]['id']]);
                                    $equipes[$chave]['cor'] = $corValida;
                                }
                            }
                            $equipeId = (int)$equipes[$chave]['id'];
                        }

                        $dados = [
                            'equipe_id' => $equipeId,
                            'telefone' => celula($linha, $colTelefone) ?: null,
                            'responsavel_nome' => celula($linha, $colResp) ?: null,
                            'telefone_responsavel' => celula($linha, $colTelResp) ?: null,
                        ];
                        $chaveNome = chaveTexto($nome);
                        if (isset($compradoresExistentes[$chaveNome])) {
                            if ($atualizarExistentes) {
                                $atualizarComprador->execute($dados + ['id' => $compradoresExistentes[$chaveNome]]);
                                $atualizados++;
                            }
                            continue;
                        }
                        $inserirComprador->execute($dados + ['nome' => $nome]);
                        $compradoresExistentes[$chaveNome] = (int)$pdo->lastInsertId();
                        $novos++;
                    }
                    $pdo->commit();
                    $resultado = ['tipo' => 'participantes', 'novos' => $novos, 'atualizados' => $atualizados,
                                  'ignorados' => count($linhas) - $novos - $atualizados - $semNome,
                                  'sem_nome' => $semNome, 'equipes_criadas' => $equipesCriadas];
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) { $pdo->rollBack(); }
                    error_log('[MINIMERCADO BJA] importacao participantes: ' . $e->getMessage());
                    $mensagemErro = 'Nao foi possivel importar. Nenhum dado foi gravado. Detalhe: ' . $e->getMessage();
                }
            }
        } else {
            // ========================== PRODUTOS ==========================
            [$cab, $linhas] = $leitura;
            $colNome = colunaPorSinonimos($cab, ['produto', 'nome', 'item']);
            $colCategoria = colunaPorSinonimos($cab, ['categoria', 'tipo']);
            $colPreco = colunaPorSinonimos($cab, ['preco', 'valor', 'preco r', 'valor r']);
            $colDescricao = colunaPorSinonimos($cab, ['descricao', 'volume', 'detalhe']);

            if ($colNome === null) {
                $mensagemErro = 'Nao encontrei a coluna "Produto" na primeira linha da planilha.';
            } else {
                $categorias = [];
                foreach ($pdo->query('SELECT id, nome FROM categorias')->fetchAll() as $cat) {
                    $categorias[chaveTexto($cat['nome'])] = (int)$cat['id'];
                }
                $produtosExistentes = [];
                foreach ($pdo->query('SELECT id, nome FROM produtos')->fetchAll() as $pr) {
                    $produtosExistentes[chaveTexto($pr['nome'])] = (int)$pr['id'];
                }
                $inserirCategoria = $pdo->prepare('INSERT INTO categorias (nome) VALUES (:nome)');
                $inserirProduto = $pdo->prepare(
                    'INSERT INTO produtos (nome, descricao, categoria_id, preco, ativo) VALUES (:nome, :descricao, :categoria_id, :preco, :ativo)'
                );
                $atualizarProduto = $pdo->prepare(
                    'UPDATE produtos SET descricao = COALESCE(:descricao, descricao), categoria_id = COALESCE(:categoria_id, categoria_id),
                            preco = :preco, ativo = :ativo, atualizado_em = :agora WHERE id = :id'
                );

                $novos = 0; $atualizados = 0; $semPreco = []; $naPlanilha = [];
                try {
                    $pdo->beginTransaction();
                    foreach ($linhas as $linha) {
                        $nome = celula($linha, $colNome);
                        if ($nome === '') { continue; }

                        $categoriaId = null;
                        $nomeCategoria = celula($linha, $colCategoria);
                        if ($nomeCategoria !== '') {
                            $chaveCat = chaveTexto($nomeCategoria);
                            if (!isset($categorias[$chaveCat])) {
                                $inserirCategoria->execute(['nome' => $nomeCategoria]);
                                $categorias[$chaveCat] = (int)$pdo->lastInsertId();
                            }
                            $categoriaId = $categorias[$chaveCat];
                        }

                        // aceita "5", "5,50", "R$ 5,50", "1.234,50"
                        $precoTexto = preg_replace('/[^0-9,.]/', '', celula($linha, $colPreco));
                        if (strpos($precoTexto, ',') !== false) {
                            $precoTexto = str_replace(',', '.', str_replace('.', '', $precoTexto));
                        }
                        $preco = $precoTexto === '' ? 0.0 : round((float)$precoTexto, 2);
                        // sem preco = fica indisponivel (nunca vende a R$ 0,00)
                        $ativo = $preco > 0 ? 1 : 0;
                        if ($preco <= 0) { $semPreco[] = $nome; }

                        $dados = [
                            'descricao' => celula($linha, $colDescricao) ?: null,
                            'categoria_id' => $categoriaId,
                            'preco' => $preco,
                            'ativo' => $ativo,
                        ];
                        $chaveNome = chaveTexto($nome);
                        $naPlanilha[] = $chaveNome;
                        if (isset($produtosExistentes[$chaveNome])) {
                            $atualizarProduto->execute($dados + ['agora' => agora(), 'id' => $produtosExistentes[$chaveNome]]);
                            $atualizados++;
                        } else {
                            $inserirProduto->execute($dados + ['nome' => $nome]);
                            $produtosExistentes[$chaveNome] = (int)$pdo->lastInsertId();
                            $novos++;
                        }
                    }

                    // Opcional: tira do cardapio o que nao esta na planilha (ex.: produtos de exemplo).
                    // Desativa em vez de apagar, para nao mexer nas compras antigas.
                    $desativados = [];
                    if (!empty($_POST['desativar_outros'])) {
                        $desativar = $pdo->prepare('UPDATE produtos SET ativo = 0 WHERE id = :id');
                        foreach ($pdo->query('SELECT id, nome, ativo FROM produtos')->fetchAll() as $pr) {
                            if (!in_array(chaveTexto($pr['nome']), $naPlanilha, true) && (int)$pr['ativo'] === 1) {
                                $desativar->execute(['id' => $pr['id']]);
                                $desativados[] = $pr['nome'];
                            }
                        }
                    }
                    $pdo->commit();
                    $resultado = ['tipo' => 'produtos', 'novos' => $novos, 'atualizados' => $atualizados,
                                  'sem_preco' => $semPreco, 'desativados' => $desativados];
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) { $pdo->rollBack(); }
                    error_log('[MINIMERCADO BJA] importacao produtos: ' . $e->getMessage());
                    $mensagemErro = 'Nao foi possivel importar. Nenhum dado foi gravado. Detalhe: ' . $e->getMessage();
                }
            }
        }
    }
}

$tokenCsrf = gerarTokenCsrf();
$tituloPagina = 'Importar Lista';
$paginaAtual = 'importar.php';
require __DIR__ . '/includes/cabecalho.php';
require __DIR__ . '/includes/menulateral.php';
?>
<main class="conteudo">
  <h3 class="mb-3">Importar planilha</h3>

  <ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link <?= $abaAtiva === 'participantes' ? 'active' : '' ?>" href="importar.php?aba=participantes">Participantes</a></li>
    <li class="nav-item"><a class="nav-link <?= $abaAtiva === 'produtos' ? 'active' : '' ?>" href="importar.php?aba=produtos">Produtos</a></li>
  </ul>

  <?php if ($mensagemErro): ?><div class="alert alert-danger alerta-fixo"><?= limparTexto($mensagemErro) ?></div><?php endif; ?>

  <?php if ($resultado && $resultado['tipo'] === 'participantes'): ?>
    <div class="alert alert-success alerta-fixo">
      <strong><?= (int)$resultado['novos'] ?></strong> participante(s) novo(s) cadastrado(s).
      <?php if ($resultado['atualizados']): ?><br><?= (int)$resultado['atualizados'] ?> ja existiam e foram atualizados.<?php endif; ?>
      <?php if ($resultado['ignorados'] > 0): ?><br><?= (int)$resultado['ignorados'] ?> ja existiam e foram mantidos como estavam.<?php endif; ?>
      <?php if ($resultado['sem_nome'] > 0): ?><br><?= (int)$resultado['sem_nome'] ?> linha(s) sem nome ignorada(s).<?php endif; ?>
      <?php if (!empty($resultado['equipes_criadas'])): ?>
        <br>Equipes criadas (com cor automatica): <?= limparTexto(implode(', ', $resultado['equipes_criadas'])) ?>.
        Para trocar a cor, use a tela <a href="equipes.php">Equipes</a>.
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($resultado && $resultado['tipo'] === 'produtos'): ?>
    <div class="alert alert-success alerta-fixo">
      <strong><?= (int)$resultado['novos'] ?></strong> produto(s) novo(s) e <strong><?= (int)$resultado['atualizados'] ?></strong> atualizado(s).
      <?php if (!empty($resultado['desativados'])): ?><br>Retirados do cardapio (nao estavam na planilha): <?= limparTexto(implode(', ', $resultado['desativados'])) ?>.<?php endif; ?>
    </div>
    <?php if (!empty($resultado['sem_preco'])): ?>
      <div class="alert alert-warning alerta-fixo">
        <strong><?= count($resultado['sem_preco']) ?> produto(s) sem preco ficaram indisponiveis</strong> (nao aparecem para venda):
        <?= limparTexto(implode(', ', $resultado['sem_preco'])) ?>.
        Informe o preco na tela <a href="produtos.php">Produtos</a> e marque "Produto disponivel", ou preencha a coluna Preco e importe de novo.
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <div class="cartao">
    <?php if ($abaAtiva === 'participantes'): ?>
      <p>Envie uma planilha <strong>CSV</strong> (no Excel ou Google Planilhas: <em>Arquivo &rarr; Salvar como / Fazer download &rarr; CSV</em>).
        A primeira linha deve ter os titulos das colunas:</p>
      <ul>
        <li><strong>Nome</strong> (obrigatoria) &middot; <strong>Equipe</strong> &middot; <strong>Telefone</strong></li>
        <li>Para os <strong>jovens do encontro</strong>: use a coluna <strong>Circulo</strong> (ou Equipe) com <em>Rosa</em>, <em>Verde</em>, <em>Azul</em> ou <em>Laranja</em>.</li>
        <li>Opcionais: <strong>Responsavel</strong> e <strong>Telefone do responsavel</strong> (menores de idade), <strong>Cor</strong> (ex.: #3cb44b)</li>
      </ul>
      <p class="mb-1">Equipes que nao existem sao criadas com uma cor diferente para cada uma. Nomes ja cadastrados nao sao duplicados.</p>
      <p><a href="<?= URL_BASE ?>assets/modelo_importacao.csv" download>Modelo para equipes de trabalho</a> &middot;
        <a href="<?= URL_BASE ?>assets/modelo_jovens.csv" download>Modelo para jovens (círculos)</a></p>
      <form method="post" enctype="multipart/form-data" class="mt-3">
        <input type="hidden" name="csrf_token" value="<?= $tokenCsrf ?>">
        <input type="hidden" name="tipo" value="participantes">
        <div class="mb-3"><input type="file" name="arquivo" accept=".csv,.txt,text/csv" class="form-control" required></div>
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" name="atualizar_existentes" id="atualizarExistentes">
          <label class="form-check-label" for="atualizarExistentes">Atualizar equipe e telefone de quem ja esta cadastrado</label>
        </div>
        <button type="submit" class="btn btn-vinho btn-toque">Importar participantes</button>
      </form>
    <?php else: ?>
      <p>Envie uma planilha <strong>CSV</strong> com os itens a venda. Colunas:</p>
      <ul>
        <li><strong>Produto</strong> (obrigatoria) &middot; <strong>Categoria</strong> &middot; <strong>Preco</strong> (ex.: 5,00) &middot; <strong>Descricao</strong> (ex.: 310 ml)</li>
      </ul>
      <p class="mb-1">Produto <strong>sem preco</strong> entra como <strong>indisponivel</strong> e nao aparece para venda ate voce informar o preco.
        Produto que ja existe (mesmo nome) tem preco e categoria atualizados; o preco das compras ja feitas nao muda.</p>
      <p><a href="<?= URL_BASE ?>assets/modelo_produtos.csv" download>Baixar planilha modelo</a></p>
      <form method="post" enctype="multipart/form-data" class="mt-3">
        <input type="hidden" name="csrf_token" value="<?= $tokenCsrf ?>">
        <input type="hidden" name="tipo" value="produtos">
        <div class="mb-3"><input type="file" name="arquivo" accept=".csv,.txt,text/csv" class="form-control" required></div>
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" name="desativar_outros" id="desativarOutros" checked>
          <label class="form-check-label" for="desativarOutros">Tirar do cardapio os produtos que nao estao na planilha (ex.: os produtos de exemplo). Eles nao sao apagados.</label>
        </div>
        <button type="submit" class="btn btn-vinho btn-toque">Importar produtos</button>
      </form>
    <?php endif; ?>
  </div>
</main>
<?php require __DIR__ . '/includes/rodape.php'; ?>
