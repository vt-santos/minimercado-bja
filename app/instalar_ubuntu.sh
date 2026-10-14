#!/usr/bin/env bash
# =====================================================================
# MINIMERCADO BJA - Instalador automatico para Ubuntu
#
# Como usar (dentro da pasta do projeto, no terminal):
#     sudo bash instalar_ubuntu.sh
#
# O que ele faz:
#   1. Instala Apache, PHP, MySQL e Avahi (acesso por nome na rede)
#   2. Copia o sistema para /var/www/html/minimercadobja
#   3. Cria o banco, um usuario proprio do MySQL (com senha aleatoria)
#      e importa as tabelas
#   4. Cria o usuario administrador (admin / admin123)
#   5. Libera a porta 80 no firewall (se o UFW estiver ativo)
#   6. Mostra os enderecos para abrir no tablet
#
# Pode rodar de novo sem medo: nao apaga dados ja cadastrados.
# =====================================================================
set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
  echo "Rode com sudo:  sudo bash instalar_ubuntu.sh"
  exit 1
fi

ORIGEM="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DESTINO="/var/www/html/minimercadobja"
BANCO="minimercadobja"
USUARIO_DB="minimercado"

echo ">>> [1/6] Instalando pacotes (precisa de internet so nesta etapa)..."
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get install -y apache2 mysql-server php php-cli php-mysql libapache2-mod-php php-mbstring avahi-daemon rsync openssl

systemctl enable --now apache2 mysql avahi-daemon

echo ">>> [2/6] Copiando o sistema para $DESTINO ..."
mkdir -p "$DESTINO"
# Preserva o arquivo de configuracao do banco caso ja exista
rsync -a --exclude 'config/database.local.php' --exclude 'instalar_ubuntu.sh' "$ORIGEM"/ "$DESTINO"/

echo ">>> [3/6] Configurando o banco de dados..."
ARQ_LOCAL="$DESTINO/config/database.local.php"
if [ -f "$ARQ_LOCAL" ]; then
  SENHA_DB="$(php -r '$c = require $argv[1]; echo $c["senha"] ?? "";' "$ARQ_LOCAL")"
fi
if [ -z "${SENHA_DB:-}" ]; then
  SENHA_DB="$(openssl rand -hex 12)"
fi

mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`$BANCO\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$USUARIO_DB'@'localhost' IDENTIFIED BY '$SENHA_DB';
ALTER USER '$USUARIO_DB'@'localhost' IDENTIFIED BY '$SENHA_DB';
GRANT ALL PRIVILEGES ON \`$BANCO\`.* TO '$USUARIO_DB'@'localhost';
FLUSH PRIVILEGES;
SQL

TABELAS="$(mysql -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$BANCO' AND table_name='usuarios';")"
if [ "$TABELAS" = "0" ]; then
  echo "    Importando tabelas (schema.sql)..."
  mysql < "$DESTINO/database/schema.sql"
else
  echo "    Banco ja existe, mantendo os dados atuais."
  # Atualiza campos novos (cor da equipe / responsavel) se ainda nao existirem
  COLUNA_COR="$(mysql -N -e "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='$BANCO' AND table_name='equipes' AND column_name='cor';")"
  if [ "$COLUNA_COR" = "0" ]; then
    echo "    Aplicando atualizacao_v2.sql..."
    mysql < "$DESTINO/database/atualizacao_v2.sql"
  fi
fi

cat > "$ARQ_LOCAL" <<PHP
<?php
// Gerado automaticamente por instalar_ubuntu.sh - nao compartilhe este arquivo.
return [
    'host'    => '127.0.0.1',
    'porta'   => '3306',
    'banco'   => '$BANCO',
    'usuario' => '$USUARIO_DB',
    'senha'   => '$SENHA_DB',
];
PHP

echo ">>> [4/6] Criando usuario administrador..."
php "$DESTINO/instalar.php" | sed 's#<br>#\n#g; s#<[^>]*>##g' || true
rm -f "$DESTINO/instalar.php"

echo ">>> [5/6] Permissoes, seguranca do Apache e firewall..."
chown -R www-data:www-data "$DESTINO"
chmod 640 "$ARQ_LOCAL"

# Pasta de sessoes fora da area publica (login dura 12 h sem cair)
mkdir -p /var/lib/minimercadobja/sessoes
chown -R www-data:www-data /var/lib/minimercadobja
chmod 700 /var/lib/minimercadobja/sessoes

# Bloqueia listagem de pastas e acesso web a arquivos internos
cat > /etc/apache2/conf-available/minimercadobja.conf <<CONF
<Directory $DESTINO>
    Options -Indexes
</Directory>
<Directory $DESTINO/config>
    Require all denied
</Directory>
<Directory $DESTINO/includes>
    Require all denied
</Directory>
<Directory $DESTINO/database>
    Require all denied
</Directory>
<FilesMatch "\.(sh|sql|md)\$">
    Require all denied
</FilesMatch>
CONF
a2enconf minimercadobja >/dev/null
if command -v ufw >/dev/null 2>&1 && ufw status | grep -q "Status: active"; then
  ufw allow 80/tcp
fi
systemctl restart apache2

echo ">>> [6/6] Pronto!"
NOME="$(hostname)"
echo
echo "=============================================================="
echo " Sistema instalado. Login: admin  |  Senha: admin123"
echo " TROQUE A SENHA no primeiro acesso (menu Configuracoes)."
echo
echo " No proprio notebook:  http://localhost/minimercadobja/"
echo " No tablet (por nome): http://$NOME.local/minimercadobja/"
for ip in $(hostname -I); do
  echo " No tablet (por IP):   http://$ip/minimercadobja/"
done
echo "=============================================================="
