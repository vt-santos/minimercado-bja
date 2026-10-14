<?php
/**
 * MINIMERCADO BJA
 * Conexao segura com o banco de dados usando PDO
 */

class Database
{
    private static ?PDO $conexao = null;

    // Valores padrao (XAMPP). No Ubuntu, o instalador cria o arquivo
    // config/database.local.php com usuario e senha proprios, que tem prioridade.
    private const HOST = '127.0.0.1';
    private const PORTA = '3306';
    private const BANCO = 'minimercadobja';
    private const USUARIO = 'root';
    private const SENHA = '';
    private const CHARSET = 'utf8mb4';

    private static function configuracao(): array
    {
        $padrao = [
            'host' => self::HOST, 'porta' => self::PORTA, 'banco' => self::BANCO,
            'usuario' => self::USUARIO, 'senha' => self::SENHA,
        ];
        $arquivoLocal = __DIR__ . '/database.local.php';
        if (is_file($arquivoLocal)) {
            $local = require $arquivoLocal;
            if (is_array($local)) {
                return array_merge($padrao, $local);
            }
        }
        return $padrao;
    }

    /** Cria colunas que faltam em bancos antigos (uma vez por sessao). */
    private static function prepararEstrutura(PDO $pdo): void
    {
        $sessaoAtiva = session_status() === PHP_SESSION_ACTIVE;
        if ($sessaoAtiva && !empty($_SESSION['estrutura_banco_ok'])) {
            return;
        }
        require_once __DIR__ . '/../includes/migracao.php';
        garantirEstruturaBanco($pdo);
        if ($sessaoAtiva) {
            $_SESSION['estrutura_banco_ok'] = true;
        }
    }

    public static function conectar(): PDO
    {
        if (self::$conexao === null) {
            $cfg = self::configuracao();
            // 'dsn' opcional no database.local.php permite apontar para outro banco (ex.: testes)
            $dsn = $cfg['dsn'] ?? ('mysql:host=' . $cfg['host'] . ';port=' . $cfg['porta'] . ';dbname=' . $cfg['banco'] . ';charset=' . self::CHARSET);

            $opcoes = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                $pdo = new PDO($dsn, $cfg['usuario'], $cfg['senha'], $opcoes);
            } catch (PDOException $e) {
                http_response_code(500);
                die('Erro ao conectar ao banco de dados. Verifique se o MySQL esta ligado (sudo systemctl status mysql) e as configuracoes em config/database.local.php');
            }

            // Mesmo fuso horario do PHP: sem isto, compras feitas a noite caem
            // no dia seguinte (o MySQL do Ubuntu costuma usar UTC) e somem do filtro "Hoje".
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
                $pdo->exec("SET time_zone = '" . date('P') . "'");
            }

            self::prepararEstrutura($pdo);
            self::$conexao = $pdo;
        }

        return self::$conexao;
    }
}
