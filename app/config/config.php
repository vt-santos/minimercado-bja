<?php
/**
 * MINIMERCADO BJA
 * Configuracoes gerais do sistema
 */

// Fuso horario do Brasil
date_default_timezone_set('America/Sao_Paulo');

// Nome do sistema
define('NOME_SISTEMA', 'MINIMERCADO BJA');

// Caminho base do sistema, detectado automaticamente (funciona tanto em subpasta
// como /minimercadobja/ quanto na raiz do site, sem precisar editar nada aqui)
$caminhoScript = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
if (basename($caminhoScript) === 'api') {
    $caminhoScript = dirname($caminhoScript); // paginas da pasta api/ usam a raiz do sistema
}
$urlBaseDetectada = rtrim($caminhoScript, '/') . '/';
define('URL_BASE', $urlBaseDetectada);

// Configuracoes de sessao segura
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');

// Sessao longa (12 h): evita ser deslogado entre uma venda e outra durante o evento.
// As sessoes ficam fora da pasta publica (criada pelo instalar_ubuntu.sh); se nao existir,
// usa o padrao do PHP.
$pastaSessoes = '/var/lib/minimercadobja/sessoes';
if (is_dir($pastaSessoes) && is_writable($pastaSessoes)) {
    session_save_path($pastaSessoes);
}
ini_set('session.gc_maxlifetime', '43200');
ini_set('session.gc_probability', '1');
ini_set('session.gc_divisor', '100');
$acessoHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
session_set_cookie_params(['lifetime' => 43200, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => $acessoHttps]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Exibicao de erros: desligada por padrao (modo evento). Para depurar, troque para true.
define('MODO_DEBUG', false);
error_reporting(E_ALL);
ini_set('display_errors', MODO_DEBUG ? 1 : 0);
ini_set('log_errors', 1);

/**
 * Erro inesperado (ex.: falha no banco): em vez de pagina em branco, mostra uma
 * mensagem clara. Nas chamadas da pasta api/ responde em JSON para a tela exibir.
 * O detalhe tecnico vai para o log do Apache (/var/log/apache2/error.log).
 */
set_exception_handler(function (Throwable $erro): void {
    error_log('[MINIMERCADO BJA] ' . get_class($erro) . ': ' . $erro->getMessage() . ' em ' . $erro->getFile() . ':' . $erro->getLine());
    $detalhe = $erro->getMessage();
    if (!headers_sent()) {
        http_response_code(500);
    }
    $ehApi = strpos(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/api/') !== false;
    if ($ehApi) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['erro' => 'Erro no servidor: ' . $detalhe]);
        return;
    }
    $texto = htmlspecialchars($detalhe, ENT_QUOTES, 'UTF-8');
    echo '<div style="max-width:720px;margin:40px auto;padding:20px;border-radius:12px;background:#fde4e4;color:#7a1018;font-family:Arial,sans-serif">'
        . '<h3 style="margin-top:0">Ocorreu um erro no sistema</h3>'
        . '<p>Tente recarregar a pagina. Se continuar, abra <strong>Configuracoes &rarr; Diagnostico</strong> para ver o que esta errado.</p>'
        . '<p style="font-size:13px;color:#555">Detalhe tecnico: ' . $texto . '</p>'
        . '<p><a href="' . URL_BASE . 'index.php">Voltar ao inicio</a></p></div>';
});
