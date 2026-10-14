#!/usr/bin/env bash
# Popula uma instancia rodando com DADOS FICTICIOS (para demonstracao e capturas de tela).
# Uso: popular_demo.sh <porta> <pasta_fixtures>
set -e
B=http://127.0.0.1:$1; F=$2; J=$(mktemp)
get() { curl -s -b $J -c $J "$B/$1"; }
tok() { get "$1" | grep -o 'name="csrf_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//'; }
url() { python3 -c 'import urllib.parse,sys;print(urllib.parse.quote(sys.argv[1]))' "$1"; }

T=$(tok login.php); curl -s -b $J -c $J -o /dev/null -d "csrf_token=$T&usuario=admin&senha=admin123" $B/login.php
T=$(tok configuracoes.php)
curl -s -b $J -c $J -o /dev/null -d "csrf_token=$T&acao=trocarsenha&senha_atual=admin123&senha_nova=demo-portfolio&senha_confirmacao=demo-portfolio" $B/configuracoes.php

for arq in participantes_demo jovens_demo; do
  T=$(tok "importar.php?aba=participantes")
  curl -s -b $J -c $J -o /dev/null -F "csrf_token=$T" -F "tipo=participantes" -F "arquivo=@$F/$arq.csv;type=text/csv" $B/importar.php
done
T=$(tok "importar.php?aba=produtos")
curl -s -b $J -c $J -o /dev/null -F "csrf_token=$T" -F "tipo=produtos" -F "desativar_outros=1" -F "arquivo=@$F/produtos_demo.csv;type=text/csv" $B/importar.php
T=$(tok "importar.php?aba=produtos")
curl -s -b $J -c $J -o /dev/null -F "csrf_token=$T" -F "tipo=produtos" -F "arquivo=@$F/precos_demo.csv;type=text/csv" $B/importar.php

P=$(get api/produtosdisponiveis.php)
pid() { echo "$P" | python3 -c 'import json,sys; print([p["id"] for p in json.load(sys.stdin) if p["nome"]==sys.argv[1]][0])' "$1"; }

# compra "<parte do nome>" "Produto:qtd,Produto:qtd"  (use _ no lugar de espaco no produto)
compra() {
  C=$(get "api/buscarcompradores.php?termo=$(url "$1")")
  CID=$(echo "$C" | python3 -c 'import json,sys; print(json.load(sys.stdin)[0]["id"])')
  EQ=$(echo "$C" | python3 -c 'import json,sys; print(json.load(sys.stdin)[0]["equipe_id"])')
  IT="["
  for par in ${2//,/ }; do n=${par%%:*}; q=${par##*:}; IT="$IT{\"produto_id\":$(pid "${n//_/ }"),\"quantidade\":$q},"; done
  IT="${IT%,}]"
  T=$(tok registrarcompra.php)
  curl -s -b $J -c $J -o /dev/null -w "%{redirect_url}" --data-urlencode "csrf_token=$T" --data-urlencode "itens=$IT" \
    -d "acao=confirmar&comprador_id=$CID&equipe_id=$EQ" $B/registrarcompra.php | grep -o 'id=[0-9]*' | cut -d= -f2
}
pagar() {
  T=$(tok "pagamentos.php?compra=$1")
  curl -s -b $J -c $J -o /dev/null -d "csrf_token=$T&acao=registrarpagamento&compra_id=$1&valor_pago=$2&forma_pagamento=$3" $B/pagamentos.php
}

c1=$(compra "Lucas Andrade" "Coca-Cola:2,Coxinha:1")
c2=$(compra "Helena Duarte" "Pão_de_Queijo:3,Água_Mineral:1")
c3=$(compra "Ana Clara" "Picolé:2,Guaraná:1")
c4=$(compra "Gabriela Monteiro" "Pastel:2,Coca-Cola_Zero:2")
c5=$(compra "João Miguel" "Coxinha:2,Coca-Cola:1")
c6=$(compra "Bruno Siqueira" "Pastel:1,Guaraná:1,Picolé:1")
c7=$(compra "Laura Demons" "Pão_de_Queijo:2,Picolé:1")
c8=$(compra "Gustavo Pereira" "Coca-Cola:3")
pagar $c1 19,50 Pix; pagar $c2 10,00 Dinheiro; pagar $c4 28,00 Cartao; pagar $c6 17,00 Pix; pagar $c8 10,00 Pix
rm -f $J
echo "demo pronta: 8 compras, 5 pagamentos (login admin / demo-portfolio)"
