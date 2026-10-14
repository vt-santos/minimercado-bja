-- =====================================================================
-- MINIMERCADO BJA - Atualizacao v2
-- Execute este script UMA VEZ, apenas se o banco ja foi criado antes
-- (com o schema.sql antigo, sem os campos abaixo).
--
-- Se voce ainda vai criar o banco do zero, NAO precisa rodar este
-- arquivo: basta usar o schema.sql atualizado, que ja inclui tudo.
--
-- Como rodar:
--   sudo mysql minimercadobja < database/atualizacao_v2.sql
-- =====================================================================

USE minimercadobja;

ALTER TABLE equipes
    ADD COLUMN cor VARCHAR(7) NOT NULL DEFAULT '#6e0f1a' AFTER nome;

ALTER TABLE compradores
    ADD COLUMN responsavel_nome VARCHAR(150) NULL AFTER email,
    ADD COLUMN telefone_responsavel VARCHAR(30) NULL AFTER responsavel_nome;
