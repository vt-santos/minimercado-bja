# MINIMERCADO BJA - Guia do Evento

## 0. Onde o sistema vai rodar

**Recomendado: hospedagem na internet.** O tablet acessa pelo 4G (hotspot do celular que anda junto
com ele) e funciona em qualquer lugar do evento onde o celular tenha sinal. O notebook vira opcional.
Plano B: rodar no notebook (secoes 1 a 3), mas ai o tablet so funciona perto do notebook.

### Passo a passo da hospedagem
1. **Crie a conta** numa hospedagem com PHP 8 e MySQL:
   - gratis: InfinityFree (infinityfree.com) - da um endereco gratuito tipo `minimercadobja.infinityfreeapp.com`;
   - paga (mais estavel): Hostinger ou similar, plano basico de hospedagem.
2. **Crie o banco MySQL** no painel (menu "MySQL Databases" / "Bancos de dados").
   Anote: **host**, **nome do banco**, **usuario** e **senha**.
3. **Crie as tabelas:** abra o **phpMyAdmin** pelo painel, clique no banco criado, va em **Importar**
   e envie `database/schema_hospedagem.sql`.
4. **Envie os arquivos:** pelo gerenciador de arquivos do painel (ou FileZilla/FTP), envie **o conteudo**
   da pasta `minimercadobja` para a pasta `htdocs` (InfinityFree) ou `public_html` (Hostinger).
5. **Configure o banco:** dentro de `config/`, copie `database.local.exemplo.php` com o nome
   `database.local.php` e preencha com os dados do passo 2.
6. **Ative o HTTPS** (certificado SSL gratuito no painel) e use sempre o endereco com `https://`.
7. **Crie o login:** abra `https://SEU-ENDERECO/instalar.php` uma vez (ele se apaga sozinho depois).
   Entre com admin / admin123 e **troque a senha na hora** (Configuracoes) - o sistema fica avisando ate trocar.
8. **Carregue os dados** (secao 5): participantes, produtos e precos.
9. **Teste no tablet com o 4G** (Wi-Fi desligado): faca uma compra, confira Historico e Relatorios,
   depois apague a compra de teste.

### No dia
- Tablet conectado no hotspot do celular que fica com quem esta vendendo (ou tablet com chip).
- Outros celulares/notebook podem abrir o mesmo endereco ao mesmo tempo: todos veem os mesmos dados.
- **Sem sinal?** Anote no papel (nome, circulo/equipe, itens e valor) e lance quando o sinal voltar.
- **Toda noite:** phpMyAdmin -> banco -> **Exportar** (SQL) e salve o arquivo no celular/pendrive;
  e baixe os CSVs em Relatorios.
- **Depois do evento:** exporte os relatorios finais e **apague o banco** da hospedagem
  (tem telefones de menores - LGPD).

## Jovens do encontro (circulos)
Os 4 circulos ja vem criados com suas cores: **Rosa, Verde, Azul e Laranja** (menu Equipes, tipo "Circulo").
- **Na hora da compra:** em *Registrar Compra*, digite o nome. Se o jovem nao estiver cadastrado, toque em
  **"+ Cadastrar ... como novo comprador"**, escolha o circulo e, se for menor, o responsavel que vai pagar.
  Ele ja fica selecionado para a compra.
- **Com lista pronta:** *Importar Lista* -> Participantes, com as colunas Nome; Circulo (Rosa/Verde/Azul/Laranja);
  Responsavel; Telefone do responsavel. Modelo: `assets/modelo_jovens.csv`.
- *Compradores* filtra por equipe ou circulo; *Relatorios por equipe* mostra quanto cada circulo consumiu.

## 1. (Plano B) Instalar no notebook (Ubuntu) - uma vez só

1. Extraia o zip e abra o terminal dentro da pasta `minimercadobja`.
2. Rode: `sudo bash instalar_ubuntu.sh` (precisa de internet **só nesta etapa**).
3. Ao final ele mostra os enderecos. Abra `http://localhost/minimercadobja/` e entre com
   **admin / admin123**. Troque a senha em *Configuracoes*.

**Ja tem uma versao anterior instalada?** Extraia o zip novo e rode `sudo bash instalar_ubuntu.sh` de novo.
Ele atualiza os arquivos e mantem tudo o que ja foi cadastrado. Colunas novas do banco sao criadas sozinhas.

## 2. Rede no dia do evento (SEM depender de internet)

O sistema nao usa internet nenhuma (o Bootstrap ja vai dentro do projeto). Basta o tablet
e o notebook estarem na mesma rede Wi-Fi.

### Opcao recomendada: o proprio notebook cria o Wi-Fi
1. Ubuntu: **Configuracoes > Wi-Fi > menu (tres pontinhos) > Ativar Hotspot Wi-Fi**.
   Defina nome (ex.: `MiniBJA`) e senha.
2. No tablet, conecte nesse Wi-Fi e abra: **http://10.42.0.1/minimercadobja/**
   (esse IP e fixo no hotspot do Ubuntu).
3. Dica: salve o endereco nos favoritos / adicione a tela inicial do tablet.

Verifique antes se a placa Wi-Fi aceita: `iw list | grep -A8 "Supported interface modes"` (precisa aparecer `AP`).

### Plano B: hotspot do celular
1. Ligue o hotspot do celular; conecte notebook **e** tablet nele.
2. No notebook, descubra o IP: `hostname -I` (ex.: 192.168.43.20).
3. No tablet abra `http://IP-DO-NOTEBOOK/minimercadobja/`.
   (O endereco por nome `.local` pode nao funcionar em tablet Android - prefira o IP.)
4. O IP pode mudar se reconectar: confira de novo com `hostname -I`.

## 3. Notebook ligado nos 3 dias
- Deixe na tomada.
- Configuracoes > Energia: suspensao automatica em **Nunca**; ao fechar a tampa: **Nao fazer nada**.
- Desligue o protetor de tela com bloqueio.

## 4. Backup (muito importante - sao dados de dinheiro)
- Backup manual: `sudo bash /var/www/html/minimercadobja/backup_banco.sh`
- Automatico a cada 15 min: `sudo crontab -e` e adicione  
  `*/15 * * * * bash /var/www/html/minimercadobja/backup_banco.sh >/dev/null 2>&1`
- Os arquivos ficam em `/var/backups/minimercado`. **Ao fim de cada dia, copie essa pasta para um pendrive.**
- Restaurar um backup:  
  `gunzip -c ARQUIVO.sql.gz | sudo mysql minimercadobja`

## 5. Carregar participantes e cardapio (menu "Importar Lista")
Os arquivos `participantes_evento.csv` e `produtos_evento.csv` vem **separados do sistema**.
Guarde-os fora da pasta `/var/www/html` (eles tem telefones de menores e nao devem ficar acessiveis pela rede).

1. **Aba Participantes** -> envie `participantes_evento.csv` (193 pessoas, 17 equipes).
   As equipes sao criadas sozinhas, cada uma com uma cor diferente. Para trocar a cor: menu *Equipes*.
   Pode importar de novo sem medo: quem ja existe nao e duplicado.
2. **Aba Produtos** -> envie `produtos_evento.csv` (17 itens: 11 bebidas, 5 salgados, picole).
   Deixe marcado "Tirar do cardapio os produtos que nao estao na planilha" (remove os produtos de exemplo).
   **Os itens entram sem preco e ficam indisponiveis** ate voce informar o preco. Duas formas:
   - abra `produtos_evento.csv` no Excel/Planilhas, preencha a coluna **Preco** (ex.: `6,00`), salve como CSV e importe de novo; ou
   - menu *Produtos* -> *Editar* -> informe o preco e marque "Produto disponivel".
3. Abra *Configuracoes -> Diagnostico*: tudo deve estar "OK".

## 6. Checklist de teste (faca na quarta a noite, ~15 min)
- [ ] Abre `http://localhost/minimercadobja/` com estilo normal (cores vinho, menu, botoes).
- [ ] No tablet, conectado no Wi-Fi do notebook, abre o sistema e faz login.
- [ ] *Configuracoes -> Diagnostico*: tudo "OK".
- [ ] Importar participantes e produtos (passo 5) e definir os precos.
- [ ] *Equipes*: cada equipe com sua cor; *Compradores* mostra a bolinha de cor.
- [ ] *Registrar Compra*: digitar um nome sugere o comprador **com a cor**; "+ Adicionar" e os botoes +/- funcionam; o total confere; confirmar leva aos detalhes.
- [ ] *Historico*, *Contas* e *Relatorios* mostram a compra; filtrar por nome, equipe e "Hoje" funciona.
- [ ] *Pagamentos*: pagar parcial, depois o restante; tentar pagar mais que o saldo mostra erro.
- [ ] Rodar o backup manual e ver o arquivo em `/var/backups/minimercado`.
- [ ] **Apague as compras de teste** antes do evento (*Historico -> Excluir*).

## 7. Se algo der errado
| Sintoma | O que fazer |
|---|---|
| Pagina sem estilo | `Ctrl+Shift+R`. Confira `ls assets/vendor/bootstrap/css/` em `/var/www/html/minimercadobja` |
| "Erro ao conectar ao banco" | `sudo systemctl status mysql`; rode `sudo bash instalar_ubuntu.sh` de novo |
| Tablet nao abre o site | Mesmo Wi-Fi? `sudo ufw allow 80/tcp`; `sudo systemctl restart apache2` |
| Historico/Contas/Relatorios vazios | Abra *Configuracoes -> Diagnostico*: ele diz se faltam compradores, produtos com preco ou compras |
| Busca de comprador nao sugere nomes | Diagnostico; confira se a lista foi importada e se o comprador esta "Ativo" |
| Mensagem "Ocorreu um erro no sistema" | Tire um print (tem o detalhe tecnico) e veja `sudo tail -n 30 /var/log/apache2/error.log` |
