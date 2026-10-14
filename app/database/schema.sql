-- =====================================================================
-- MINIMERCADO BJA - Script de criacao do banco de dados
-- Banco: MySQL / MariaDB
-- =====================================================================

CREATE DATABASE IF NOT EXISTS minimercadobja CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE minimercadobja;

-- ---------------------------------------------------------------------
-- Tabela: usuarios (administradores do sistema)
-- ---------------------------------------------------------------------
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    usuario VARCHAR(60) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tabela: categorias
-- ---------------------------------------------------------------------
CREATE TABLE categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(80) NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tabela: produtos
-- ---------------------------------------------------------------------
CREATE TABLE produtos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    descricao VARCHAR(255) NULL,
    categoria_id INT NULL,
    preco DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    foto VARCHAR(255) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL,
    CONSTRAINT fk_produtos_categoria FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tabela: equipes
-- ---------------------------------------------------------------------
CREATE TABLE equipes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    cor VARCHAR(7) NOT NULL DEFAULT '#6e0f1a',
    tipo VARCHAR(20) NOT NULL DEFAULT 'trabalho',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tabela: compradores
-- ---------------------------------------------------------------------
CREATE TABLE compradores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    equipe_id INT NULL,
    telefone VARCHAR(30) NULL,
    email VARCHAR(150) NULL,
    responsavel_nome VARCHAR(150) NULL,
    telefone_responsavel VARCHAR(30) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_compradores_equipe FOREIGN KEY (equipe_id) REFERENCES equipes(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tabela: compras
-- ---------------------------------------------------------------------
CREATE TABLE compras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    comprador_id INT NOT NULL,
    equipe_id INT NULL,
    data_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    valor_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    valor_pago DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status_pagamento ENUM('pendente','pago','parcial') NOT NULL DEFAULT 'pendente',
    criado_por INT NULL,
    observacao VARCHAR(255) NULL,
    CONSTRAINT fk_compras_comprador FOREIGN KEY (comprador_id) REFERENCES compradores(id),
    CONSTRAINT fk_compras_equipe FOREIGN KEY (equipe_id) REFERENCES equipes(id) ON DELETE SET NULL,
    CONSTRAINT fk_compras_usuario FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tabela: itens_compra (guarda o preco praticado no momento da compra)
-- ---------------------------------------------------------------------
CREATE TABLE itens_compra (
    id INT AUTO_INCREMENT PRIMARY KEY,
    compra_id INT NOT NULL,
    produto_id INT NULL,
    nome_produto VARCHAR(120) NOT NULL,
    preco_unitario DECIMAL(10,2) NOT NULL,
    quantidade INT NOT NULL DEFAULT 1,
    subtotal DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_itens_compra FOREIGN KEY (compra_id) REFERENCES compras(id) ON DELETE CASCADE,
    CONSTRAINT fk_itens_produto FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tabela: pagamentos
-- ---------------------------------------------------------------------
CREATE TABLE pagamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    compra_id INT NOT NULL,
    valor_pago DECIMAL(10,2) NOT NULL,
    forma_pagamento VARCHAR(60) NULL,
    observacao VARCHAR(255) NULL,
    data_pagamento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    registrado_por INT NULL,
    CONSTRAINT fk_pagamentos_compra FOREIGN KEY (compra_id) REFERENCES compras(id) ON DELETE CASCADE,
    CONSTRAINT fk_pagamentos_usuario FOREIGN KEY (registrado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tabela: tentativas_login (limita chutes de senha)
-- ---------------------------------------------------------------------
CREATE TABLE tentativas_login (
    ip VARCHAR(45) NOT NULL,
    momento DATETIME NOT NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Indices auxiliares
-- ---------------------------------------------------------------------
CREATE INDEX idx_compras_comprador ON compras(comprador_id);
CREATE INDEX idx_compras_data ON compras(data_hora);
CREATE INDEX idx_compras_status ON compras(status_pagamento);
CREATE INDEX idx_itens_compra ON itens_compra(compra_id);
CREATE INDEX idx_produtos_ativo ON produtos(ativo);
CREATE INDEX idx_compradores_equipe ON compradores(equipe_id);

-- ---------------------------------------------------------------------
-- Dados iniciais
-- ---------------------------------------------------------------------

-- O usuario administrador padrao (usuario: admin / senha: admin123) e criado
-- automaticamente pelo arquivo instalar.php na primeira execucao do sistema,
-- pois o hash de senha precisa ser gerado pelo PHP em tempo real.

-- Categorias exemplo
INSERT INTO categorias (nome) VALUES ('Bebidas'), ('Salgados'), ('Doces'), ('Outros');

-- Circulos dos jovens do encontro
INSERT INTO equipes (nome, cor, tipo) VALUES
('Círculo Rosa', '#e84393', 'circulo'),
('Círculo Verde', '#27ae60', 'circulo'),
('Círculo Azul', '#2e86de', 'circulo'),
('Círculo Laranja', '#f39c12', 'circulo');

-- Equipes de trabalho e produtos NAO sao criados aqui: importe a lista de participantes
-- e o cardapio pelo menu "Importar Lista" (abas Participantes e Produtos).
