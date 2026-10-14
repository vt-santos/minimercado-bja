<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/funcoes.php';
require_once __DIR__ . '/includes/autenticacao.php';

exigirLogin();
$pdo = Database::conectar();

$mensagemErro = '';

// ---------- Confirmar compra ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'confirmar') {
    if (!validarTokenCsrf($_POST['csrf_token'] ?? '')) {
        $mensagemErro = 'Sessao expirada, atualize a pagina.';
    } else {
        $compradorId = (int)($_POST['comprador_id'] ?? 0);
        $equipeId = (int)($_POST['equipe_id'] ?? 0) ?: null;
        $itensJson = $_POST['itens'] ?? '[]';
        $itens = json_decode($itensJson, true);

        if ($compradorId <= 0 || empty($itens) || !is_array($itens)) {
            $mensagemErro = 'Selecione o comprador e ao menos um produto.';
        } else {
            try {
                // Confere se o comprador existe
                $verificaComprador = $pdo->prepare('SELECT id FROM compradores WHERE id = :id AND ativo = 1');
                $verificaComprador->execute(['id' => $compradorId]);
                if (!$verificaComprador->fetch()) {
                    throw new RuntimeException('Comprador invalido.');
                }

                // Nome e preco SEMPRE vem do banco (nunca confiar no que o navegador envia)
                $buscaProduto = $pdo->prepare('SELECT id, nome, preco FROM produtos WHERE id = :id AND ativo = 1');
                $itensValidados = [];
                $valorTotal = 0;

                foreach ($itens as $item) {
                    $produtoId = (int)($item['produto_id'] ?? 0);
                    $quantidade = (int)($item['quantidade'] ?? 0);
                    if ($produtoId <= 0 || $quantidade <= 0 || $quantidade > 999) {
                        continue;
                    }
                    $buscaProduto->execute(['id' => $produtoId]);
                    $produtoBanco = $buscaProduto->fetch();
                    if (!$produtoBanco) {
                        continue;
                    }
                    $subtotal = round((float)$produtoBanco['preco'] * $quantidade, 2);
                    $valorTotal += $subtotal;
                    $itensValidados[] = [
                        'produto_id' => (int)$produtoBanco['id'],
                        'nome' => $produtoBanco['nome'],
                        'preco' => (float)$produtoBanco['preco'],
                        'quantidade' => $quantidade,
                        'subtotal' => $subtotal,
                    ];
                }

                if (empty($itensValidados)) {
                    throw new RuntimeException('Nenhum produto valido.');
                }

                $pdo->beginTransaction();

                $comandoCompra = $pdo->prepare(
                    'INSERT INTO compras (comprador_id, equipe_id, data_hora, valor_total, valor_pago, status_pagamento, criado_por)
                     VALUES (:comprador_id, :equipe_id, :data_hora, :valor_total, 0, \'pendente\', :criado_por)'
                );
                $comandoCompra->execute([
                    'comprador_id' => $compradorId,
                    'equipe_id' => $equipeId,
                    'data_hora' => agora(),
                    'valor_total' => $valorTotal,
                    'criado_por' => $_SESSION['usuario_id'],
                ]);
                $compraId = (int)$pdo->lastInsertId();

                $comandoItem = $pdo->prepare(
                    'INSERT INTO itens_compra (compra_id, produto_id, nome_produto, preco_unitario, quantidade, subtotal)
                     VALUES (:compra_id, :produto_id, :nome_produto, :preco_unitario, :quantidade, :subtotal)'
                );

                foreach ($itensValidados as $item) {
                    $comandoItem->execute([
                        'compra_id' => $compraId,
                        'produto_id' => $item['produto_id'],
                        'nome_produto' => $item['nome'],
                        'preco_unitario' => $item['preco'],
                        'quantidade' => $item['quantidade'],
                        'subtotal' => $item['subtotal'],
                    ]);
                }

                $pdo->commit();
                redirecionar(URL_BASE . 'compradetalhe.php?id=' . $compraId . '&sucesso=1');
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $mensagemErro = 'Nao foi possivel registrar a compra. Tente novamente.';
            }
        }
    }
}

$equipes = buscarEquipes($pdo);
$produtoPreSelecionado = (int)($_GET['produto'] ?? 0);

$tokenCsrf = gerarTokenCsrf();
$tituloPagina = 'Registrar Compra';
$paginaAtual = 'registrarcompra.php';
require __DIR__ . '/includes/cabecalho.php';
require __DIR__ . '/includes/menulateral.php';
?>
<main class="conteudo">
  <h3 class="mb-4">Registrar Compra</h3>

  <?php if ($mensagemErro): ?><div class="alert alert-danger"><?= limparTexto($mensagemErro) ?></div><?php endif; ?>

  <div class="row g-3">
    <div class="col-lg-7">
      <div class="cartao mb-3">
        <h5 class="mb-3">1. Comprador</h5>
        <div class="mb-3 position-relative">
          <label class="form-label">Nome do comprador</label>
          <input type="text" id="campoBuscaComprador" class="form-control" placeholder="Digite para buscar" autocomplete="off">
          <div id="listaSugestoes" class="list-group position-absolute w-100" style="z-index:20;"></div>
        </div>
        <div id="compradorSelecionado" class="alert alert-secondary d-none"></div>
        <p class="mb-3"><button type="button" class="btn btn-link p-0" id="linkNovoComprador">+ Cadastrar novo comprador (jovem ou equipe)</button></p>

        <label class="form-label">Equipe / círculo</label>
        <select id="campoEquipe" class="form-select">
          <option value="">Selecione</option>
          <?= opcoesEquipes($equipes) ?>
        </select>
      </div>

      <div class="cartao">
        <h5 class="mb-3">2. Produtos</h5>
        <div id="gradeProdutosCompra" class="grade-produtos"></div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="cartao resumo-fixo" id="cartaoResumo">
        <h5 class="mb-3">Resumo da Compra</h5>
        <div id="listaCarrinho"></div>
        <hr>
        <div class="d-flex justify-content-between fs-5 fw-bold mb-3">
          <span>Total</span>
          <span id="valorTotalCarrinho">R$ 0,00</span>
        </div>
        <button class="btn btn-vinho btn-toque w-100" id="botaoConfirmar" disabled>Confirmar Compra</button>
      </div>
    </div>
  </div>
  <div class="barra-total d-lg-none">
    <div>
      <div class="rotulo">Total</div>
      <div class="valor" id="valorTotalBarra">R$ 0,00</div>
    </div>
    <button type="button" class="btn btn-vinho btn-toque" id="botaoConfirmarBarra" disabled>Confirmar</button>
  </div>
</main>

<div class="modal fade" id="modalNovoComprador" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="formNovoComprador" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= $tokenCsrf ?>">
        <div class="modal-header">
          <h5 class="modal-title">Novo comprador</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <div id="erroNovoComprador" class="alert alert-danger alerta-fixo d-none"></div>
          <div class="mb-3">
            <label class="form-label">Nome completo *</label>
            <input type="text" name="nome" id="novoNome" class="form-control" required minlength="3">
          </div>
          <div class="mb-3">
            <label class="form-label">Círculo (jovens) ou equipe *</label>
            <select name="equipe_id" id="novoEquipe" class="form-select" required>
              <option value="">Selecione</option>
              <?= opcoesEquipes($equipes) ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Telefone</label>
            <input type="tel" name="telefone" class="form-control" placeholder="(61) 90000-0000">
          </div>
          <div class="form-text mb-2">Para jovens: quem vai pagar a conta.</div>
          <div class="row">
            <div class="col-sm-6 mb-3">
              <label class="form-label">Responsável</label>
              <input type="text" name="responsavel_nome" class="form-control">
            </div>
            <div class="col-sm-6 mb-3">
              <label class="form-label">Telefone do responsável</label>
              <input type="tel" name="telefone_responsavel" class="form-control" placeholder="(61) 90000-0000">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-vinho" id="botaoSalvarNovo">Cadastrar e selecionar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<form method="post" id="formConfirmar" style="display:none;">
  <input type="hidden" name="csrf_token" value="<?= $tokenCsrf ?>">
  <input type="hidden" name="acao" value="confirmar">
  <input type="hidden" name="comprador_id" id="campoCompradorId">
  <input type="hidden" name="equipe_id" id="campoEquipeId">
  <input type="hidden" name="itens" id="campoItens">
</form>

<script>
var produtoPreSelecionado = <?= (int)$produtoPreSelecionado ?>;
var carrinho = {}; // produto_id -> {produto_id, nome, preco, quantidade}
var compradorId = null;

// Escapa texto vindo do banco antes de colocar em innerHTML
function escapar(texto) {
  return String(texto == null ? '' : texto)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

// Le a resposta da API; em erro, usa a mensagem enviada pelo servidor
function lerRespostaJson(resposta) {
  return resposta.text().then(function (texto) {
    var dados = null;
    try { dados = JSON.parse(texto); } catch (e) { dados = null; }
    if (resposta.status === 401) { throw new Error('sessao expirada, faca login de novo'); }
    if (!resposta.ok || dados === null) {
      throw new Error((dados && dados.erro) ? dados.erro : 'resposta invalida do servidor (HTTP ' + resposta.status + ')');
    }
    return dados;
  });
}

// Aceita apenas cor hexadecimal valida (#rrggbb)
function corSegura(cor) {
  return /^#[0-9a-fA-F]{6}$/.test(cor || '') ? cor : '';
}

// Carrega produtos disponiveis
fetch('api/produtosdisponiveis.php')
  .then(function (resposta) {
    return lerRespostaJson(resposta);
  })
  .then(function (produtos) {
    var grade = document.getElementById('gradeProdutosCompra');

    if (produtos.length === 0) {
      grade.innerHTML = '<p class="text-muted">Nenhum produto cadastrado ainda. Cadastre produtos na aba "Produtos".</p>';
      return;
    }

    produtos.forEach(function (produto) {
      var card = document.createElement('div');
      card.className = 'cartaoproduto';
      card.innerHTML =
        '<div class="foto">&#127869;</div>' +
        '<div class="nome">' + escapar(produto.nome) + '</div>' +
        (produto.descricao ? '<div class="descricao">' + escapar(produto.descricao) + '</div>' : '') +
        '<div class="preco">R$ ' + parseFloat(produto.preco).toFixed(2).replace('.', ',') + '</div>' +
        '<button type="button" class="btn btn-vinho btn-sm w-100 botaoadicionar" id="add-' + produto.id + '" onclick="alterarQuantidade(' + produto.id + ', 1)">+ Adicionar</button>' +
        '<div class="seletorqtd d-none" id="seletor-' + produto.id + '">' +
        '<button type="button" onclick="alterarQuantidade(' + produto.id + ', -1)">-</button>' +
        '<input type="text" readonly id="qtd-' + produto.id + '" value="0">' +
        '<button type="button" onclick="alterarQuantidade(' + produto.id + ', 1)">+</button>' +
        '</div>';
      grade.appendChild(card);

      window['produtoDados' + produto.id] = produto;

      if (produtoPreSelecionado === produto.id) {
        alterarQuantidade(produto.id, 1);
      }
    });
  })
  .catch(function (erro) {
    document.getElementById('gradeProdutosCompra').innerHTML =
      '<div class="alert alert-danger">Nao foi possivel carregar os produtos: ' + escapar(erro.message) + '<br>Recarregue a pagina. Se continuar, abra Configuracoes &rarr; Diagnostico.</div>';
    console.error(erro);
  });

function alterarQuantidade(produtoId, delta) {
  var produto = window['produtoDados' + produtoId];
  var atual = carrinho[produtoId] ? carrinho[produtoId].quantidade : 0;
  var nova = Math.max(0, atual + delta);

  if (nova === 0) {
    delete carrinho[produtoId];
  } else {
    carrinho[produtoId] = {
      produto_id: produtoId,
      nome: produto.nome,
      preco: parseFloat(produto.preco),
      quantidade: nova
    };
  }

  var botaoAdicionar = document.getElementById('add-' + produtoId);
  var seletor = document.getElementById('seletor-' + produtoId);
  if (botaoAdicionar && seletor) {
    botaoAdicionar.classList.toggle('d-none', nova > 0);
    seletor.classList.toggle('d-none', nova === 0);
  }

  var campoQtd = document.getElementById('qtd-' + produtoId);
  if (campoQtd) { campoQtd.value = nova; }

  atualizarResumo();
}

function atualizarResumo() {
  var lista = document.getElementById('listaCarrinho');
  var total = 0;
  lista.innerHTML = '';

  var chaves = Object.keys(carrinho);
  if (chaves.length === 0) {
    lista.innerHTML = '<p class="text-muted">Nenhum produto selecionado.</p>';
  }

  chaves.forEach(function (chave) {
    var item = carrinho[chave];
    var subtotal = item.preco * item.quantidade;
    total += subtotal;

    var linha = document.createElement('div');
    linha.className = 'd-flex justify-content-between mb-2';
    linha.innerHTML =
      '<span>' + item.quantidade + 'x ' + escapar(item.nome) + '</span>' +
      '<span>R$ ' + subtotal.toFixed(2).replace('.', ',') + '</span>';
    lista.appendChild(linha);
  });

  var totalTexto = 'R$ ' + total.toFixed(2).replace('.', ',');
  document.getElementById('valorTotalCarrinho').textContent = totalTexto;
  var totalBarra = document.getElementById('valorTotalBarra');
  if (totalBarra) {
    var itens = Object.values(carrinho).reduce(function (soma, item) { return soma + item.quantidade; }, 0);
    totalBarra.textContent = totalTexto + (itens ? ' (' + itens + (itens === 1 ? ' item)' : ' itens)') : '');
  }
  verificarPodeConfirmar();
}

function verificarPodeConfirmar() {
  var pode = !!(compradorId && Object.keys(carrinho).length > 0);
  document.getElementById('botaoConfirmar').disabled = !pode;
  var botaoBarra = document.getElementById('botaoConfirmarBarra');
  if (botaoBarra) { botaoBarra.disabled = !pode; }
}

// Busca de comprador
var campoBusca = document.getElementById('campoBuscaComprador');
var listaSugestoes = document.getElementById('listaSugestoes');
var tempoBusca = null;

campoBusca.addEventListener('input', function () {
  clearTimeout(tempoBusca);
  var termo = campoBusca.value;

  if (termo.length < 2) {
    listaSugestoes.innerHTML = '';
    return;
  }

  tempoBusca = setTimeout(function () {
    fetch('api/buscarcompradores.php?termo=' + encodeURIComponent(termo))
      .then(function (resposta) {
        return lerRespostaJson(resposta);
      })
      .then(function (compradores) {
        listaSugestoes.innerHTML = '';

        if (compradores.length === 0) {
          var vazio = document.createElement('div');
          vazio.className = 'list-group-item text-muted';
          vazio.textContent = 'Nenhum comprador encontrado.';
          listaSugestoes.appendChild(vazio);
          listaSugestoes.appendChild(botaoCadastrarTermo(termo));
          return;
        }

        compradores.forEach(function (comprador) {
          var opcao = document.createElement('button');
          opcao.type = 'button';
          opcao.className = 'list-group-item list-group-item-action d-flex align-items-center gap-2';

          var textoOpcao = comprador.nome + (comprador.equipe_nome ? ' (' + comprador.equipe_nome + ')' : '');
          var html = '';
          if (corSegura(comprador.equipe_cor)) {
            html += '<span class="amostracor" style="background:' + corSegura(comprador.equipe_cor) + '"></span>';
          }
          html += '<span>' + escapar(textoOpcao) + '</span>';
          opcao.innerHTML = html;

          opcao.onclick = function () { selecionarComprador(comprador); };
          listaSugestoes.appendChild(opcao);
        });
        listaSugestoes.appendChild(botaoCadastrarTermo(termo));
      })
      .catch(function (erro) {
        listaSugestoes.innerHTML = '<div class="list-group-item text-danger">Erro ao buscar compradores: ' + escapar(erro.message) + '</div>';
        console.error(erro);
      });
  }, 300);
});

function selecionarComprador(comprador) {
  compradorId = comprador.id;
  document.getElementById('campoCompradorId').value = comprador.id;
  if (comprador.equipe_id) {
    document.getElementById('campoEquipe').value = comprador.equipe_id;
  }
  var painel = document.getElementById('compradorSelecionado');
  painel.classList.remove('d-none');
  var html = (corSegura(comprador.equipe_cor) ? '<span class="amostracor" style="background:' + corSegura(comprador.equipe_cor) + '"></span> ' : '') +
    '<span>Comprador selecionado: <strong>' + escapar(comprador.nome) + '</strong>' +
    (comprador.equipe_nome ? ' &middot; ' + escapar(comprador.equipe_nome) : '');
  if (comprador.responsavel_nome || comprador.telefone_responsavel) {
    html += '<br><small>Responsável: ' + escapar(comprador.responsavel_nome || '-') +
      (comprador.telefone_responsavel ? ' &middot; ' + escapar(comprador.telefone_responsavel) : '') + '</small>';
  }
  painel.innerHTML = html + '</span>';
  campoBusca.value = comprador.nome;
  listaSugestoes.innerHTML = '';
  verificarPodeConfirmar();
}

// ---------- Cadastro rapido de comprador ----------
var modalNovo = null;
function abrirCadastroRapido(nomeInicial) {
  var form = document.getElementById('formNovoComprador');
  form.reset();
  document.getElementById('erroNovoComprador').classList.add('d-none');
  document.getElementById('novoNome').value = nomeInicial || '';
  listaSugestoes.innerHTML = '';
  if (!modalNovo) { modalNovo = new bootstrap.Modal(document.getElementById('modalNovoComprador')); }
  modalNovo.show();
  setTimeout(function () { document.getElementById(nomeInicial ? 'novoEquipe' : 'novoNome').focus(); }, 300);
}

function botaoCadastrarTermo(termo) {
  var botao = document.createElement('button');
  botao.type = 'button';
  botao.className = 'list-group-item list-group-item-action text-success fw-semibold';
  botao.textContent = '+ Cadastrar "' + termo + '" como novo comprador';
  botao.onclick = function () { abrirCadastroRapido(termo); };
  return botao;
}

document.getElementById('linkNovoComprador').addEventListener('click', function () {
  abrirCadastroRapido(campoBusca.value.trim());
});

document.getElementById('formNovoComprador').addEventListener('submit', function (evento) {
  evento.preventDefault();
  var botao = document.getElementById('botaoSalvarNovo');
  var caixaErro = document.getElementById('erroNovoComprador');
  botao.disabled = true;
  fetch('api/cadastrarcomprador.php', { method: 'POST', body: new FormData(evento.target) })
    .then(lerRespostaJson)
    .then(function (comprador) {
      modalNovo.hide();
      selecionarComprador(comprador);
    })
    .catch(function (erro) {
      caixaErro.textContent = 'Nao foi possivel cadastrar: ' + erro.message;
      caixaErro.classList.remove('d-none');
    })
    .finally(function () { botao.disabled = false; });
});

document.getElementById('botaoConfirmarBarra').addEventListener('click', function () {
  document.getElementById('botaoConfirmar').click();
});

document.getElementById('botaoConfirmar').addEventListener('click', function () {
  document.getElementById('campoCompradorId').value = compradorId;
  document.getElementById('campoEquipeId').value = document.getElementById('campoEquipe').value;
  document.getElementById('campoItens').value = JSON.stringify(Object.values(carrinho));
  document.getElementById('formConfirmar').submit();
});
</script>

<?php require __DIR__ . '/includes/rodape.php'; ?>
