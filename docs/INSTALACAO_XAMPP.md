# MINIMERCADO BJA

> Instalacao em **Windows com XAMPP**. Para hospedagem ou Ubuntu, veja o [`GUIA_EVENTO.md`](GUIA_EVENTO.md).
> Os caminhos abaixo sao relativos a pasta `app/` do repositorio.


Sistema web completo para gestao de um minimercado interno: cardapio, registro de compras, controle de equipes e compradores, contas individuais, pagamentos e relatorios. Desenvolvido em PHP puro (PDO), MySQL/MariaDB, Bootstrap 5 e JavaScript, com foco em uso em tablets e notebooks.

## Tecnologias

- PHP 8+
- MySQL ou MariaDB
- PDO com prepared statements
- Bootstrap 5
- HTML5, CSS3, JavaScript puro

## Estrutura de diretorios

```
minimercadobja/
  api/                 endpoints AJAX (produtos e busca de compradores)
  assets/css/          estilo visual do sistema
  assets/js/           scripts gerais
  config/              configuracao e conexao com banco
  database/schema.sql  script de criacao do banco
  includes/            cabecalho, menu, rodape, funcoes e autenticacao
  *.php                paginas do sistema
```

## Instalacao no XAMPP

1. Instale o XAMPP e inicie os servicos Apache e MySQL pelo painel de controle.
2. Copie a pasta `minimercadobja` inteira para dentro de `C:\xampp\htdocs\` (Windows) ou `/opt/lampp/htdocs/` (Linux).
3. Abra o phpMyAdmin em `http://localhost/phpmyadmin`.
4. Va em "Importar" e selecione o arquivo `database/schema.sql` para criar o banco `minimercadobja` com as tabelas e dados de exemplo. Se voce ja tinha instalado o sistema antes e so quer atualizar o banco (sem perder dados), rode `database/atualizacao_v2.sql` em vez do schema.sql inteiro.
5. Confira o arquivo `config/database.php`. Por padrao ele usa usuario `root` e senha em branco, que e o padrao do XAMPP. Ajuste caso seu ambiente seja diferente.
6. No navegador, acesse `http://localhost/minimercadobja/instalar.php` uma unica vez. Esse script cria o usuario administrador padrao:
   - Usuario: `admin`
   - Senha: `admin123`
7. Apague ou renomeie o arquivo `instalar.php` apos a instalacao, por seguranca.
8. Acesse `http://localhost/minimercadobja/login.php` e entre com o usuario criado.
9. Va em "Configuracoes" e troque a senha padrao assim que possivel.

## Fluxo principal de uso

1. Fazer login.
2. No menu, abrir "Registrar Compra".
3. Buscar o comprador pelo nome (autocompletar) ou cadastrar um novo em "Compradores".
4. Selecionar a equipe.
5. Tocar nos botoes de mais e menos para escolher os produtos e quantidades.
6. Conferir o resumo e o valor total, que sao calculados automaticamente.
7. Confirmar a compra.
8. Consultar depois em "Historico de Compras", "Contas dos Compradores" ou "Relatorios".

## Regras importantes ja implementadas

- O preco de cada produto e gravado no momento da compra (na tabela `itens_compra`), portanto alterar o preco no cardapio nunca modifica compras ja registradas.
- Produtos marcados como indisponiveis nao aparecem para novas compras.
- Cada compra possui status `pendente`, `parcial` ou `pago`, atualizado automaticamente conforme os pagamentos sao registrados.
- Todas as paginas internas exigem login ativo (protecao de sessao).
- Todas as consultas usam PDO com prepared statements, evitando SQL Injection.
- Tokens CSRF protegem os formularios de login, produtos, compradores, equipes, compras, pagamentos e senha.
- Senhas sao armazenadas com hash bcrypt (`password_hash`), nunca em texto puro.
- Layout responsivo prioritario para tablets e notebooks, com botoes grandes e cardapio em cards adaptaveis.

## Possiveis expansoes futuras

O codigo foi organizado em camadas simples (config, includes, paginas, api) para facilitar a adicao de:

- Leitura de QR Code para identificar comprador ou produto
- Controle de estoque
- Controle de caixa diario
- Niveis diferentes de usuario (operador, gerente, administrador)
- Integracao com meios de pagamento
- Impressao de comprovantes
- Notificacoes
- Auditoria de operacoes (log de alteracoes)

## Seguranca

Antes de colocar o sistema em producao:

- Altere a senha do usuario `admin`.
- Apague o arquivo `instalar.php`.
- Desative a exibicao de erros em `config/config.php` (`display_errors`).
- Defina uma senha forte para o usuario do banco de dados em `config/database.php`, em vez de usar `root` sem senha.
- Sirva o sistema por HTTPS caso fique acessivel fora da rede local.
