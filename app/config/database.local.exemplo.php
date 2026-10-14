<?php
/**
 * MODELO de configuracao do banco para HOSPEDAGEM.
 *
 * 1. Copie este arquivo com o nome  database.local.php  (mesma pasta config/).
 * 2. Troque os valores pelos dados que o painel da hospedagem mostra
 *    em "Bancos de dados MySQL" (host, nome do banco, usuario e senha).
 *
 * No Ubuntu com o instalar_ubuntu.sh este arquivo e criado sozinho.
 */
return [
    'host'    => 'sqlXXX.hospedagem.com',   // ex.: sql305.infinityfree.com ou localhost (Hostinger)
    'porta'   => '3306',
    'banco'   => 'nome_do_banco',            // ex.: if0_12345678_minimercado ou u123456789_minimercado
    'usuario' => 'usuario_do_banco',
    'senha'   => 'senha_do_banco',
];
