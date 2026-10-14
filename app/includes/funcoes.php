<?php
/**
 * MINIMERCADO BJA
 * Funcoes auxiliares gerais
 */

/** Data e hora atual no fuso do sistema (America/Sao_Paulo), no formato do banco. */
function agora(): string
{
    return date('Y-m-d H:i:s');
}

/** Equipes para listas de escolha: circulos dos jovens primeiro, depois equipes de trabalho. */
function buscarEquipes(PDO $pdo, bool $somenteAtivas = true): array
{
    $sql = 'SELECT id, nome, cor, tipo FROM equipes' . ($somenteAtivas ? ' WHERE ativo = 1' : '')
         . " ORDER BY CASE WHEN tipo = 'circulo' THEN 0 ELSE 1 END, nome";
    return $pdo->query($sql)->fetchAll();
}

/** <option> agrupadas: "Jovens do encontro (circulos)" e "Equipes de trabalho". */
function opcoesEquipes(array $equipes, $selecionada = null): string
{
    $grupos = ['circulo' => [], 'trabalho' => []];
    foreach ($equipes as $eq) {
        $grupos[($eq['tipo'] ?? '') === 'circulo' ? 'circulo' : 'trabalho'][] = $eq;
    }
    $rotulos = ['circulo' => 'Jovens do encontro (círculos)', 'trabalho' => 'Equipes de trabalho'];
    $html = '';
    foreach ($grupos as $tipo => $lista) {
        if (!$lista) {
            continue;
        }
        $html .= '<optgroup label="' . $rotulos[$tipo] . '">';
        foreach ($lista as $eq) {
            $sel = ((string)$selecionada !== '' && (int)$selecionada === (int)$eq['id']) ? ' selected' : '';
            $html .= '<option value="' . (int)$eq['id'] . '" data-cor="' . limparTexto($eq['cor'] ?? '') . '"' . $sel . '>'
                   . limparTexto($eq['nome']) . '</option>';
        }
        $html .= '</optgroup>';
    }
    return $html;
}

function formatarMoeda(float $valor): string
{
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

function formatarData(string $dataHora): string
{
    $timestamp = strtotime($dataHora);
    return date('d/m/Y', $timestamp);
}

function formatarDataHora(string $dataHora): string
{
    $timestamp = strtotime($dataHora);
    return date('d/m/Y H:i', $timestamp);
}

function limparTexto(?string $texto): string
{
    return htmlspecialchars(trim($texto ?? ''), ENT_QUOTES, 'UTF-8');
}

function redirecionar(string $destino): void
{
    header('Location: ' . $destino);
    exit;
}

function statusPagamentoLabel(string $status): string
{
    $mapa = [
        'pendente' => '<span class="badge badge-status pendente">Pendente</span>',
        'pago' => '<span class="badge badge-status pago">Pago</span>',
        'parcial' => '<span class="badge badge-status parcial">Parcial</span>',
    ];
    return $mapa[$status] ?? $status;
}

function gerarTokenCsrf(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validarTokenCsrf(?string $token): bool
{
    return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
