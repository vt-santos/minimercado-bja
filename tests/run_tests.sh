#!/usr/bin/env bash
# Roda toda a bateria de testes localmente ou no CI.
#   - verifica a sintaxe de todos os arquivos PHP, JS e shell
#   - sobe o sistema com o servidor embutido do PHP + SQLite
#   - executa o teste de ponta a ponta em dois cenarios:
#       novo   = banco criado pelo schema atual
#       antigo = banco criado por uma versao antiga (testa a migracao automatica)
#   - se o Playwright estiver instalado, roda tambem o teste no navegador
# Requisitos: php 8 (pdo_sqlite, mbstring), python3, curl. Opcional: playwright + chromium, node.
set -u
RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TMP="$(mktemp -d)"
FALHAS=0
trap 'kill $(jobs -p) 2>/dev/null; rm -rf "$TMP"' EXIT

echo "== Sintaxe"
for f in $(find "$RAIZ/app" -name '*.php'); do
  php -l "$f" > /dev/null || { echo "ERRO: $f"; FALHAS=$((FALHAS+1)); }
done
bash -n "$RAIZ/app/instalar_ubuntu.sh" && bash -n "$RAIZ/app/backup_banco.sh" || FALHAS=$((FALHAS+1))
if command -v node > /dev/null; then node --check "$RAIZ/app/assets/js/script.js" || FALHAS=$((FALHAS+1)); fi
echo "   ok"

PORTA=8790
for CENARIO in novo antigo; do
  echo "== Cenario: banco $CENARIO"
  INST="$TMP/app_$CENARIO"
  python3 -I "$RAIZ/tests/preparar_teste.py" "$RAIZ/app" "$INST" "$CENARIO" > /dev/null
  (cd "$INST" && php instalar.php > /dev/null)
  php -S 127.0.0.1:$PORTA -t "$INST" > "$TMP/servidor_$CENARIO.log" 2>&1 &
  sleep 1.5
  mkdir -p "$TMP/saida_$CENARIO"
  bash "$RAIZ/tests/e2e.sh" $PORTA "$RAIZ/tests/fixtures" "$TMP/saida_$CENARIO" | grep -E "FALHOU|RESULTADO" -A2
  [ "${PIPESTATUS[0]}" -eq 0 ] || FALHAS=$((FALHAS+1))
  if grep -qiE 'fatal|warning|notice|deprecated' "$TMP/servidor_$CENARIO.log"; then
    echo "   avisos do PHP:"; grep -iE 'fatal|warning|notice|deprecated' "$TMP/servidor_$CENARIO.log" | head -5; FALHAS=$((FALHAS+1))
  fi
  if python3 -c 'import playwright' 2>/dev/null; then
    python3 -c "import sqlite3; c=sqlite3.connect('$INST/teste.sqlite'); c.execute('DELETE FROM tentativas_login'); c.commit()"
    python3 -I "$RAIZ/tests/navegador.py" "http://127.0.0.1:$PORTA" "$TMP/saida_$CENARIO" | grep -E "FALHOU|RESULTADO"
    [ "${PIPESTATUS[0]}" -eq 0 ] || FALHAS=$((FALHAS+1))
  else
    echo "   (Playwright nao instalado: teste no navegador pulado)"
  fi
  kill %1 2>/dev/null; wait 2>/dev/null
  PORTA=$((PORTA+1))
done

if [ "$FALHAS" -eq 0 ]; then echo "TUDO OK"; else echo "FALHAS: $FALHAS"; exit 1; fi
