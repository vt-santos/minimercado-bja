#!/usr/bin/env bash
# Teste de ponta a ponta (HTTP) contra uma instancia rodando.
# Uso: e2e.sh <porta> <pasta_fixtures> <pasta_tmp>
B=http://127.0.0.1:$1; DADOS=$2; T=$3; J=$T/cj.txt; rm -f $J
OK=0; FALHA=0
ok()    { echo "  PASSOU  $1"; OK=$((OK+1)); }
falha() { echo "  FALHOU  $1"; FALHA=$((FALHA+1)); }
confere() { if echo "$2" | grep -q -- "$3"; then ok "$1"; else falha "$1 (esperava: $3)"; echo "$2" | sed 's/<[^>]*>//g' | grep -v '^\s*$' | head -8 | sed 's/^/          /'; fi; }
nao_tem() { if echo "$2" | grep -q -- "$3"; then falha "$1 (nao devia ter: $3)"; else ok "$1"; fi; }
get()  { curl -s -b $J -c $J "$B/$1"; }
tok()  { get "$1" | grep -o 'name="csrf_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//'; }

echo "1. Login"
TK=$(tok login.php)
R=$(curl -s -b $J -c $J -o /dev/null -w "%{http_code} %{redirect_url}" -d "csrf_token=$TK&usuario=admin&senha=admin123" $B/login.php)
confere "login redireciona ao painel" "$R" "302 .*index.php"

echo "2. Telas vazias explicam o motivo"
confere "historico vazio explica" "$(get historico.php)" "Nenhuma compra registrada ainda"
confere "contas vazia sugere importar" "$(get contas.php)" "Nenhum comprador cadastrado ainda"
confere "relatorios vazio explica" "$(get relatorios.php)" "Ainda nao ha nenhuma compra"
confere "diagnostico aponta falta de compradores" "$(get diagnostico.php)" "Nao ha compradores cadastrados"

echo "3. Importar participantes"
TK=$(tok "importar.php?aba=participantes")
R=$(curl -s -b $J -c $J -F "csrf_token=$TK" -F "tipo=participantes" -F "arquivo=@$DADOS/participantes_demo.csv;type=text/csv" $B/importar.php)
confere "23 participantes importados" "$R" "<strong>23</strong> participante"
confere "equipes criadas" "$R" "Equipes criadas"
R=$(curl -s -b $J -c $J -F "csrf_token=$TK" -F "tipo=participantes" -F "arquivo=@$DADOS/participantes_demo.csv;type=text/csv" $B/importar.php)
confere "reimportar nao duplica" "$R" "<strong>0</strong> participante"

echo "4. Importar produtos (sem preco)"
TK=$(tok "importar.php?aba=produtos")
R=$(curl -s -b $J -c $J -F "csrf_token=$TK" -F "tipo=produtos" -F "desativar_outros=1" -F "arquivo=@$DADOS/produtos_demo.csv;type=text/csv" $B/importar.php)
confere "17 produtos novos" "$R" "<strong>17</strong> produto(s) novo"
confere "avisa que ficaram sem preco" "$R" "17 produto(s) sem preco ficaram indisponiveis"
confere "API nao oferece produto sem preco" "$(get api/produtosdisponiveis.php)" '^\[\]$'

echo "5. Definir precos (planilha com preco)"
printf '\xef\xbb\xbfProduto;Categoria;Preco\nCoca-Cola;Bebidas;R$ 6,00\nCoxinha;Salgados;7,50\nPicole;Doces;4\n' > $T/precos.csv
TK=$(tok "importar.php?aba=produtos")
R=$(curl -s -b $J -c $J -F "csrf_token=$TK" -F "tipo=produtos" -F "arquivo=@$T/precos.csv;type=text/csv" $B/importar.php)
confere "3 produtos atualizados (Picole sem acento casou com Picolé)" "$R" "<strong>3</strong> atualizado"
P=$(get api/produtosdisponiveis.php)
PR=$(echo "$P" | python3 -c 'import json,sys; print(" ".join("%s=%.2f" % (p["nome"], float(p["preco"])) for p in json.load(sys.stdin)))')
confere "Coca-Cola a venda por 6.00" "$PR" "Coca-Cola=6.00"
confere "Coxinha a venda por 7.50" "$PR" "Coxinha=7.50"
confere "Coca-Cola traz o volume (Lata 310 ml)" "$P" "Lata 310 ml"
ID_COCA=$(echo "$P" | python3 -c 'import json,sys; print([p["id"] for p in json.load(sys.stdin) if p["nome"]=="Coca-Cola"][0])')
ID_COX=$(echo "$P" | python3 -c 'import json,sys; print([p["id"] for p in json.load(sys.stdin) if p["nome"]=="Coxinha"][0])')

echo "6. Busca de comprador traz a cor da equipe"
C=$(get "api/buscarcompradores.php?termo=Lucas%20Andr")
confere "acha o Lucas (Minimercado)" "$C" '"nome":"Lucas Andrade Moreira"'
confere "vem a cor da equipe" "$C" '"equipe_cor":"#'
ID_V=$(echo "$C" | python3 -c 'import json,sys; print(json.load(sys.stdin)[0]["id"])')
EQ_V=$(echo "$C" | python3 -c 'import json,sys; print(json.load(sys.stdin)[0]["equipe_id"])')

echo "7. Registrar compra (preco vem do banco, nao do navegador)"
TK=$(tok registrarcompra.php)
ITENS="[{\"produto_id\":$ID_COCA,\"preco\":0.01,\"quantidade\":2},{\"produto_id\":$ID_COX,\"preco\":0.01,\"quantidade\":1}]"
R=$(curl -s -b $J -c $J -o /dev/null -w "%{redirect_url}" --data-urlencode "csrf_token=$TK" --data-urlencode "itens=$ITENS" -d "acao=confirmar&comprador_id=$ID_V&equipe_id=$EQ_V" $B/registrarcompra.php)
confere "compra confirmada" "$R" "compradetalhe.php?id="
D=$(get "${R#$B/}")
confere "total 2x6,00 + 1x7,50 = R\$ 19,50" "$D" "R\$ 19,50"

echo "8. Historico, Contas e Relatorios com dados e filtros"
confere "historico lista a compra" "$(get historico.php)" "Lucas Andrade Moreira"
confere "historico filtro por nome" "$(get 'historico.php?comprador=lucas')" "Lucas Andrade Moreira"
confere "historico filtro por equipe" "$(get "historico.php?equipe=$EQ_V")" "Lucas Andrade Moreira"
HOJE=$(TZ=America/Sao_Paulo date +%Y-%m-%d)
confere "historico filtro por data de hoje" "$(get "historico.php?data_inicial=$HOJE&data_final=$HOJE")" "Lucas Andrade Moreira"
confere "historico filtro sem resultado explica" "$(get 'historico.php?comprador=zzzz')" "Nenhuma compra encontrada com esses filtros"
confere "contas lista todos (procura outro nome)" "$(get contas.php)" "Helena Duarte Campos"
confere "contas filtro por nome" "$(get 'contas.php?comprador=Lucas%20Andrade')" "R\$ 19,50"
R=$(get 'relatorios.php?periodo=hoje')
confere "relatorio de hoje: pessoa" "$R" "Lucas Andrade Moreira"
confere "relatorio de hoje: total de vendas" "$R" "R\$ 19,50"
confere "relatorio de hoje: produto Coca-Cola" "$R" "Coca-Cola"
confere "relatorio mes atual" "$(get relatorios.php)" "R\$ 19,50"
confere "relatorio periodo personalizado" "$(get "relatorios.php?periodo=personalizado&data_inicial=$HOJE&data_final=$HOJE")" "R\$ 19,50"
confere "exportar CSV por pessoa" "$(get "relatorioexportar.php?tipo=pessoas&data_inicial=$HOJE&data_final=$HOJE")" '"Lucas Andrade Moreira";Minimercado;1;3;19,50;0,00;19,50'

echo "9. Pagamentos"
CID=$(echo "$R" | grep -o 'compradetalhe.php?id=[0-9]*' | head -1 | cut -d= -f2); CID=${CID:-1}
TK=$(tok "pagamentos.php?compra=$CID")
R=$(curl -s -b $J -c $J -d "csrf_token=$TK&acao=registrarpagamento&compra_id=$CID&valor_pago=30,00&forma_pagamento=Pix" $B/pagamentos.php)
confere "recusa pagar mais que o saldo" "$R" "maior que o saldo devido"
curl -s -b $J -c $J -o /dev/null -d "csrf_token=$TK&acao=registrarpagamento&compra_id=$CID&valor_pago=10,00&forma_pagamento=Pix" $B/pagamentos.php
confere "pagamento parcial" "$(get "compradetalhe.php?id=$CID")" "Parcial"
LINK=$(get historico.php | grep -o 'href="historico.php?marcarpago=[^"]*"' | head -1 | sed 's/href="//;s/"$//;s/&amp;/\&/g')
confere "historico tem o link Marcar Pago com token" "$LINK" "marcarpago=.*csrf_token=."
get "$LINK" > /dev/null
D=$(get "compradetalhe.php?id=$CID")
confere "marcar pago quita (selo Pago)" "$D" 'badge-status pago">Pago'
confere "marcar pago registra o restante (9,50) no historico de pagamentos" "$D" "R\$ 9,50</td>"
confere "relatorio: total recebido 19,50" "$(get relatorios.php)" "Total recebido</span><span class=\"valor\">R\$ 19,50"
SEM=$(get "historico.php?marcarpago=$CID")
confere "sem token o link nao faz nada (protecao CSRF)" "$SEM" "Historico de Compras"

echo "10. Diagnostico e equipes"
D=$(get diagnostico.php)
confere "diagnostico abre" "$D" "Diagnostico do sistema"
nao_tem "diagnostico sem alerta de compradores" "$D" "Nao ha compradores"
E=$(get equipes.php)
confere "equipe Gráfica com acento" "$E" "Gráfica"
N=$(echo "$E" | grep -o 'amostracor" style="background: #[0-9a-fA-F]*' | sort -u | wc -l)
if [ "$N" -ge 12 ]; then ok "12 equipes (8 + 4 circulos) com cores diferentes ($N)"; else falha "cores repetidas ($N distintas)"; fi


echo "10b. Compradores: filtro por equipe e circulos"
EQ_MINI=$(get compradores.php | grep -o '<option value="[0-9]*" data-cor="[^"]*">Minimercado</option>' | head -1 | grep -o 'value="[0-9]*"' | grep -o '[0-9]*')
R=$(get "compradores.php?equipe=$EQ_MINI")
confere "filtro Minimercado mostra 4 compradores" "$R" "4 comprador(es)"
nao_tem "filtro Minimercado nao mostra outra equipe" "$R" "Helena Duarte"
confere "lista agrupa circulos dos jovens" "$R" 'optgroup label="Jovens do encontro'
for c in Rosa Verde Azul Laranja; do confere "circulo $c existe" "$R" ">Círculo $c</option>"; done
ROSA=$(echo "$R" | grep -o '<option value="[0-9]*" data-cor="#e84393">Círculo Rosa' | head -1 | grep -o 'value="[0-9]*"' | grep -o '[0-9]*')
confere "Circulo Rosa com a cor rosa" "$ROSA" "[0-9]"

echo "10c. Cadastro rapido de jovem (tela de compra)"
TK=$(tok registrarcompra.php)
R=$(curl -s -b $J -c $J -d "csrf_token=$TK&nome=Joana%20Teste%20Lima&equipe_id=$ROSA&responsavel_nome=Ana%20Lima&telefone_responsavel=(61)%2098888-0000" $B/api/cadastrarcomprador.php)
confere "cadastra jovem no Circulo Rosa" "$R" '"equipe_nome":"Círculo Rosa"'
confere "devolve responsavel" "$R" '"responsavel_nome":"Ana Lima"'
confere "marca como novo" "$R" '"ja_existia":false'
R=$(curl -s -b $J -c $J -d "csrf_token=$TK&nome=joana%20teste%20lima&equipe_id=$ROSA" $B/api/cadastrarcomprador.php)
confere "nome repetido nao duplica" "$R" '"ja_existia":true'
R=$(curl -s -b $J -c $J -w "__%{http_code}" -d "csrf_token=$TK&nome=Sem%20Circulo" $B/api/cadastrarcomprador.php)
confere "exige circulo/equipe" "$R" "circulo ou a equipe.*__422"
R=$(curl -s -b $J -c $J -w "__%{http_code}" -d "csrf_token=errado&nome=X%20Y%20Z&equipe_id=$ROSA" $B/api/cadastrarcomprador.php)
confere "recusa sem token" "$R" "__403"
confere "Joana aparece no filtro do Circulo Rosa" "$(get "compradores.php?equipe=$ROSA")" "Joana Teste Lima"

echo "10d. Importar jovens com coluna Circulo"
printf '\xef\xbb\xbfNome;Circulo;Responsavel;Telefone do responsavel\nPedro Jovem Um;verde;Carlos;(61) 97777-0001\nLia Jovem Dois;Círculo Azul;;\n' > $T/jovens.csv
TK=$(tok "importar.php?aba=participantes")
R=$(curl -s -b $J -c $J -F "csrf_token=$TK" -F "tipo=participantes" -F "arquivo=@$T/jovens.csv;type=text/csv" $B/importar.php)
confere "2 jovens importados" "$R" "<strong>2</strong> participante"
nao_tem "nao criou equipe 'verde' nova" "$R" "Equipes criadas"
C=$(get "api/buscarcompradores.php?termo=Pedro%20Jovem")
confere "Pedro foi para o Circulo Verde" "$C" '"equipe_nome":"Círculo Verde"'

echo "10e. Seguranca"
confere "aviso de senha padrao aparece" "$(get index.php)" "A senha ainda e a padrao"
J2=$T/cj_ataque.txt; rm -f $J2
for i in 1 2 3 4 5 6 7 8 9; do
  TK2=$(curl -s -b $J2 -c $J2 $B/login.php | grep -o 'name="csrf_token" value="[^"]*"' | sed 's/.*value="//;s/"//')
  R=$(curl -s -b $J2 -c $J2 -d "csrf_token=$TK2&usuario=admin&senha=errada$i" $B/login.php)
done
confere "bloqueia depois de 8 senhas erradas" "$R" "Muitas tentativas erradas"
TK2=$(curl -s -b $J2 -c $J2 $B/login.php | grep -o 'name="csrf_token" value="[^"]*"' | sed 's/.*value="//;s/"//')
R=$(curl -s -b $J2 -c $J2 -d "csrf_token=$TK2&usuario=admin&senha=admin123" $B/login.php)
confere "bloqueio vale ate para a senha certa (15 min)" "$R" "Muitas tentativas erradas"

echo "11. Paginas sem erro de PHP"
for p in index.php cardapio.php registrarcompra.php "compradores.php?equipe=1" compradores.php equipes.php historico.php contas.php pagamentos.php relatorios.php produtos.php configuracoes.php importar.php diagnostico.php "importar.php?aba=produtos"; do
  H=$(curl -s -b $J -w "__%{http_code}" "$B/$p")
  if echo "$H" | grep -qE "Warning|Fatal error|Notice|Deprecated|Ocorreu um erro"; then falha "$p tem erro"; echo "$H" | grep -oE "(Warning|Fatal error|Notice|Deprecated)[^<]{0,160}" | head -2;
  elif [[ "$H" != *__200 ]]; then falha "$p HTTP ${H##*__}"; else ok "$p"; fi
done
echo "RESULTADO: $OK passaram, $FALHA falharam"
[ "$FALHA" -eq 0 ]
