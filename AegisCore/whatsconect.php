<?php
// whatsconect_rev.php — WhatsApp Evolution API (Revendedor) com suporte a temas
if (session_status() === PHP_SESSION_NONE) session_start();
error_reporting(0);
date_default_timezone_set('America/Sao_Paulo');

include('../AegisCore/conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if ($conn) $conn->set_charset("utf8mb4");

// ========== INCLUIR SISTEMA DE TEMAS ==========
include_once '../AegisCore/temas.php';
$temaAtual = initTemas($conn);
$listaTemas = getListaTemas($conn);

if (!file_exists('../admin/suspenderrev.php')) {
    exit("<script>alert('Token Invalido!');</script>");
} else {
    include_once '../admin/suspenderrev.php';
}

if (!isset($_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe']) || !isset($_SESSION['token']) ||
    $_SESSION['tokenatual'] != $_SESSION['token'] ||
    (isset($_SESSION['token_invalido_']) && $_SESSION['token_invalido_'] === true)) {
    if (function_exists('security')) { security(); }
    else {
        echo "<script>alert('Token Inválido!');</script>";
        echo "<script>location.href='../index.php';</script>";
        $_SESSION['token_invalido_'] = true; exit;
    }
}

function esc_s($v) {
    global $conn;
    return mysqli_real_escape_string($conn, strip_tags(trim($v)));
}

function curlEvo($url, $token, $method = 'GET', $body = null, $timeout = 15) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => ['apikey: '.$token, 'Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    $resp  = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['body'=>$resp,'errno'=>$errno,'error'=>$error,'code'=>$code];
}

function carregarApiAdmin($conn) {
    $r = $conn->query("SELECT evo_apiurl, evo_token FROM configs LIMIT 1");
    if ($r && $r->num_rows > 0) {
        $cfg = $r->fetch_assoc();
        if (!empty($cfg['evo_apiurl']) && !empty($cfg['evo_token']))
            return ['apiurl' => $cfg['evo_apiurl'], 'token' => $cfg['evo_token']];
    }
    return ['apiurl' => '', 'token' => ''];
}

function carregarWppRev($conn, $byid) {
    $r = $conn->query("SELECT * FROM whatsapp WHERE byid='" . intval($byid) . "' LIMIT 1");
    return ($r && $r->num_rows > 0) ? $r->fetch_assoc() : [];
}

// ══════════════════════════════════════════════════════════════════
// AJAX
// ══════════════════════════════════════════════════════════════════
if (isset($_GET['ajax'])) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache');

    if (!isset($_SESSION['login'], $_SESSION['iduser'])) {
        echo json_encode(['ok' => false, 'msg' => 'Sessão expirada.']); exit;
    }

    $byid      = intval($_SESSION['iduser']);
    $wpp       = carregarWppRev($conn, $byid);
    $api_admin = carregarApiAdmin($conn);
    $api_base  = rtrim($api_admin['apiurl'] ?? '', '/');
    $tok       = $api_admin['token'] ?? '';
    $inst      = trim($wpp['sessao'] ?? '');

    if (!empty($api_base) && !preg_match('#^https?://#i', $api_base))
        $api_base = 'http://' . $api_base;

    $acao = $_GET['ajax'];

    if ($acao === 'status') {
        if (empty($api_base) || empty($tok) || empty($inst)) {
            echo json_encode(['state' => 'not_configured']); exit;
        }
        $r = curlEvo($api_base . '/instance/connectionState/' . urlencode($inst), $tok, 'GET', null, 8);
        if ($r['errno'] !== 0) { echo json_encode(['state' => 'error', 'error' => $r['error']]); exit; }
        $d = json_decode($r['body'], true);
        if ($d === null) { echo json_encode(['state' => 'error']); exit; }
        if (!isset($d['state']) && isset($d['instance']['state'])) $d['state'] = $d['instance']['state'];
        echo json_encode($d); exit;
    }

    if ($acao === 'criar') {
        $nome = trim($_POST['instancia'] ?? '');
        if (empty($nome) || empty($api_base) || empty($tok)) {
            echo json_encode(['erro' => 'API não configurada pelo administrador.']); exit;
        }
        $r_check = curlEvo($api_base . '/instance/connectionState/' . urlencode($nome), $tok, 'GET', null, 8);
        $d_check  = json_decode($r_check['body'], true);
        $ja_existe = ($r_check['code'] === 200 && $d_check !== null);

        if (!$ja_existe) {
            $body = json_encode(['instanceName' => $nome, 'qrcode' => true, 'integration' => 'WHATSAPP-BAILEYS']);
            $r    = curlEvo($api_base . '/instance/create', $tok, 'POST', $body, 20);
            if ($r['errno'] !== 0) { echo json_encode(['erro' => 'cURL #' . $r['errno'] . ': ' . $r['error']]); exit; }
            $d        = json_decode($r['body'], true);
            $criou    = isset($d['instance']['instanceName']) || isset($d['instanceName']);
            $ja_existe_api = stripos($r['body'], 'exist') !== false;
            if (!$criou && !$ja_existe_api) {
                echo json_encode(['erro' => ($d['message'] ?? ($d['error'] ?? mb_substr($r['body'], 0, 200)))]); exit;
            }
        }

        $nome_s = esc_s($nome);
        $chk    = $conn->query("SELECT id FROM whatsapp WHERE byid='$byid'");
        if ($chk && $chk->num_rows > 0)
            $conn->query("UPDATE whatsapp SET sessao='$nome_s' WHERE byid='$byid'");
        else
            $conn->query("INSERT INTO whatsapp (byid, sessao, apiurl, token, ativo) VALUES ('$byid','$nome_s','','','1')");

        echo json_encode(['ok' => true, 'msg' => $ja_existe ? 'Instância vinculada!' : 'Instância criada com sucesso!', 'instancia' => $nome]);
        exit;
    }

    if ($acao === 'qr') {
        if (empty($api_base) || empty($tok) || empty($inst)) {
            echo json_encode(['erro' => 'Configure e salve a instância primeiro.']); exit;
        }
        $r_state = curlEvo($api_base . '/instance/connectionState/' . urlencode($inst), $tok, 'GET', null, 8);
        $d_state  = json_decode($r_state['body'], true);
        $state    = $d_state['instance']['state'] ?? $d_state['state'] ?? '';
        if ($state === 'open') {
            echo json_encode(['state' => 'open']); exit;
        }
        $r = curlEvo($api_base . '/instance/connect/' . urlencode($inst), $tok, 'GET', null, 20);
        if ($r['errno'] !== 0) { echo json_encode(['erro' => 'cURL #' . $r['errno'] . ': ' . $r['error']]); exit; }
        $d = json_decode($r['body'], true);
        echo ($d !== null) ? $r['body'] : json_encode(['erro' => 'Resposta inválida']);
        exit;
    }

    if ($acao === 'logout') {
        if (empty($api_base) || empty($tok) || empty($inst)) {
            echo json_encode(['ok' => false, 'msg' => 'Instância não configurada.']); exit;
        }
        $r = curlEvo($api_base . '/instance/logout/' . urlencode($inst), $tok, 'DELETE', null, 12);
        $d = json_decode($r['body'], true);
        echo ($d !== null) ? $r['body'] : json_encode(['ok' => true]);
        exit;
    }

    if ($acao === 'deletar_inst') {
        if (empty($api_base) || empty($tok)) {
            echo json_encode(['ok' => false, 'msg' => 'API não configurada.']); exit;
        }
        if (empty($inst)) {
            $conn->query("UPDATE whatsapp SET sessao='' WHERE byid='$byid'");
            echo json_encode(['ok' => true, 'msg' => 'Banco limpo!']); exit;
        }
        curlEvo($api_base . '/instance/logout/' . urlencode($inst), $tok, 'DELETE', null, 6);
        sleep(1);
        $r = curlEvo($api_base . '/instance/delete/' . urlencode($inst), $tok, 'DELETE', null, 15);
        if ($r['code'] === 404 || ($r['code'] >= 200 && $r['code'] < 300)) {
            $conn->query("UPDATE whatsapp SET sessao='' WHERE byid='$byid'");
            echo json_encode(['ok' => true, 'msg' => 'Instância deletada!']); exit;
        }
        $d = json_decode($r['body'], true);
        echo json_encode(['ok' => false, 'msg' => 'Erro HTTP ' . $r['code'] . ': ' . ($d['message'] ?? mb_substr($r['body'], 0, 200))]);
        exit;
    }

    if ($acao === 'testar') {
        $num_raw = $_POST['numero'] ?? '';
        $txt     = $_POST['texto']  ?? 'Teste Atlas Painel ✅';
        if (empty($num_raw) || empty($inst) || empty($api_base) || empty($tok)) {
            echo json_encode(['ok' => false, 'msg' => 'Configure a instância antes de testar.']); exit;
        }
        $num = preg_replace('/\D/', '', $num_raw);
        if (strlen($num) <= 11 && substr($num, 0, 2) !== '55') $num = '55' . $num;
        $url = $api_base . '/message/sendText/' . urlencode($inst);
        $r   = curlEvo($url, $tok, 'POST', json_encode(['number' => $num, 'textMessage' => ['text' => $txt]]), 20);
        if ($r['code'] >= 400 || $r['errno'] !== 0)
            $r = curlEvo($url, $tok, 'POST', json_encode(['number' => $num, 'text' => $txt, 'options' => ['delay' => 0]]), 20);
        $d  = json_decode($r['body'], true);
        $ok = ($r['code'] >= 200 && $r['code'] < 300 && $r['errno'] === 0 &&
               (isset($d['key']) || isset($d['id']) || isset($d['messageId'])));
        echo json_encode(['ok' => $ok, 'msg' => $ok ? '✅ Mensagem enviada para ' . $num . '!' : 'Falha HTTP ' . $r['code'] . ': ' . ($d['message'] ?? $d['error'] ?? mb_substr($r['body'], 0, 200)), 'numero' => $num]);
        exit;
    }

    if ($acao === 'get_msg') {
        $id  = intval($_GET['id'] ?? 0);
        $res = $conn->query("SELECT * FROM mensagens WHERE id='$id' AND byid='$byid' LIMIT 1");
        echo ($res && $res->num_rows > 0) ? json_encode($res->fetch_assoc()) : json_encode([]);
        exit;
    }

    echo json_encode(['ok' => false, 'msg' => 'Ação desconhecida.']); exit;
}

// ══════════════════════════════════════════════════════════════════
// PÁGINA NORMAL
// ══════════════════════════════════════════════════════════════════
include('header2.php');

if (!isset($_SESSION['login'], $_SESSION['iduser'])) {
    echo "<script>location.href='../index.php';</script>"; exit;
}

$byid = intval($_SESSION['iduser']);

$conn->query("CREATE TABLE IF NOT EXISTS whatsapp (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, byid INT NOT NULL DEFAULT 0, sessao VARCHAR(100) DEFAULT '', token TEXT DEFAULT NULL, apiurl VARCHAR(255) DEFAULT '', ativo TINYINT(1) DEFAULT 1, UNIQUE KEY uk_byid (byid)) DEFAULT CHARSET=utf8mb4");
$conn->query("CREATE TABLE IF NOT EXISTS mensagens (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, byid INT NOT NULL DEFAULT 0, funcao VARCHAR(50) NOT NULL, mensagem TEXT, ativo VARCHAR(20) DEFAULT 'ativada', hora VARCHAR(5) DEFAULT '08:00') DEFAULT CHARSET=utf8mb4");

$wpp        = carregarWppRev($conn, $byid);
$inst_atual = trim($wpp['sessao'] ?? '');
$api_admin  = carregarApiAdmin($conn);
$api_base   = rtrim($api_admin['apiurl'] ?? '', '/');
$tok_admin  = $api_admin['token'] ?? '';
$api_ok     = !empty($api_base) && !empty($tok_admin);
$inst_ok    = !empty($inst_atual);

$res_acc    = $conn->query("SELECT whatsapp FROM accounts WHERE id='$byid' LIMIT 1");
$acc        = ($res_acc && $res_acc->num_rows > 0) ? $res_acc->fetch_assoc() : [];
$numero_wpp = $acc['whatsapp'] ?? '';

$msg = ''; $tipo = '';

if (isset($_POST['salvar_inst'])) {
    $inst   = esc_s($_POST['instancia'] ?? '');
    $numero = esc_s($_POST['numero']    ?? '');
    if (empty($inst)) { $msg = '⚠️ Informe o nome!'; $tipo = 'err'; }
    else {
        $chk = $conn->query("SELECT id FROM whatsapp WHERE byid='$byid'");
        if ($chk && $chk->num_rows > 0) $conn->query("UPDATE whatsapp SET sessao='$inst' WHERE byid='$byid'");
        else $conn->query("INSERT INTO whatsapp (byid,sessao,apiurl,token,ativo) VALUES ('$byid','$inst','','','1')");
        $conn->query("UPDATE accounts SET whatsapp='$numero' WHERE id='$byid'");
        $inst_atual = $inst; $numero_wpp = $numero; $inst_ok = true;
        $msg = '✅ Instância salva: ' . htmlspecialchars($inst); $tipo = 'ok';
    }
}
if (isset($_POST['adicionar'])) {
    $mens = $conn->real_escape_string($_POST['mensagem'] ?? '');
    $func = esc_s($_POST['funcao'] ?? '');
    $atv  = esc_s($_POST['ativo']  ?? 'ativada');
    $hora = esc_s($_POST['add_hora'] ?? '08:00');
    $chk  = $conn->query("SELECT id FROM mensagens WHERE funcao='$func' AND byid='$byid'");
    if ($chk && $chk->num_rows > 0) { $msg = '⚠️ Este evento já tem mensagem!'; $tipo = 'err'; }
    else { $conn->query("INSERT INTO mensagens (byid,funcao,mensagem,ativo,hora) VALUES ('$byid','$func','$mens','$atv','$hora')"); $msg = '✅ Mensagem adicionada!'; $tipo = 'ok'; }
}
if (isset($_POST['editar'])) {
    $id   = intval($_POST['edit_id']);
    $mens = $conn->real_escape_string($_POST['edit_mensagem'] ?? '');
    $func = esc_s($_POST['edit_funcao'] ?? '');
    $atv  = esc_s($_POST['edit_ativo']  ?? 'ativada');
    $hora = esc_s($_POST['edit_hora']   ?? '08:00');
    $conn->query("UPDATE mensagens SET mensagem='$mens',funcao='$func',ativo='$atv',hora='$hora' WHERE id='$id' AND byid='$byid'");
    $msg = '✅ Mensagem atualizada!'; $tipo = 'ok';
}
if (isset($_POST['deletar'])) {
    $conn->query("DELETE FROM mensagens WHERE id='" . intval($_POST['edit_id']) . "' AND byid='$byid'");
    $msg = '✅ Mensagem removida!'; $tipo = 'ok';
}

$wpp_online = false;
if ($api_ok && $inst_ok) {
    $api_n = $api_base;
    if (!preg_match('#^https?://#i', $api_n)) $api_n = 'http://' . $api_n;
    $r      = curlEvo($api_n . '/instance/connectionState/' . urlencode($inst_atual), $tok_admin, 'GET', null, 5);
    $sd     = json_decode($r['body'], true);
    $wpp_online = (($sd['instance']['state'] ?? $sd['state'] ?? '') === 'open');
}

$res_mens = $conn->query("SELECT * FROM mensagens WHERE byid='$byid' ORDER BY id ASC");
$total_mens = $res_mens ? $res_mens->num_rows : 0;

$funcoes_labels = [
    'criarusuario'    => ['label' => 'Criar Usuário',    'icon' => 'bx-user-plus',     'cor' => '#818cf8'],
    'criarteste'      => ['label' => 'Criar Teste',      'icon' => 'bx-test-tube',     'cor' => '#34d399'],
    'criarrevenda'    => ['label' => 'Criar Revenda',    'icon' => 'bx-store-alt',     'cor' => '#fbbf24'],
    'contaexpirada'   => ['label' => 'Usuário Vencendo', 'icon' => 'bx-time',          'cor' => '#f87171'],
    'revendaexpirada' => ['label' => 'Revenda Vencendo', 'icon' => 'bx-calendar-x',   'cor' => '#fb923c'],
    'renovacaopag'    => ['label' => 'Pagto Aprovado',   'icon' => 'bx-check-circle',  'cor' => '#10b981'],
    'planoaprovado'   => ['label' => 'Plano Aprovado',   'icon' => 'bx-dollar-circle', 'cor' => '#a78bfa'],
];
$templates = [
    'criarusuario'    => "🎉 *Usuário Criado!*\n\n👤 Usuário: {usuario}\n🔑 Senha: {senha}\n📅 Validade: {validade}\n👥 Limite: {limite}\n\n🌐 Renovação: https://{dominio}/renovar.php",
    'criarteste'      => "🧪 *Teste Criado!*\n\n👤 Usuário: {usuario}\n🔑 Senha: {senha}\n⏱ Duração: {validade} min\n👥 Limite: {limite}",
    'criarrevenda'    => "🏪 *Revenda Criada!*\n\n👤 Revenda: {usuario}\n🔑 Senha: {senha}\n📅 Validade: {validade}\n👥 Limite: {limite}\n\n🌐 Painel: https://{dominio}/",
    'contaexpirada'   => "⚠️ *Conta vencendo!*\n\n👤 Usuário: {usuario}\n📅 Validade: {validade}\n\n🔄 Renove: https://{dominio}/renovar.php",
    'revendaexpirada' => "⚠️ *Revenda vencendo!*\n\n👤 Revenda: {usuario}\n📅 Validade: {validade}\n\n🔄 Acesse o painel.",
    'renovacaopag'    => "✅ *Pagamento Aprovado!*\n\n👤 Usuário: {usuario}\n📅 Nova validade: {validade}\n\nObrigado! 🙏",
    'planoaprovado'   => "✅ *Plano Aprovado!*\n\n👤 Revenda: {usuario}\n📅 Nova validade: {validade}\n👥 Limite: {limite}\n\nBem-vindo! 🚀",
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>WhatsApp — Evolution API</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Inter',sans-serif;background:var(--fundo,#0f172a);color:var(--texto,#fff);min-height:100vh;}
.app-content{margin-left:-390px!important;padding:0!important;}
.content-wrapper{max-width:1040px;margin:0 auto!important;padding:20px!important;}
.content-body{padding:0!important;}

/* Stats Card */
.stats-card{
    background:linear-gradient(135deg,var(--fundo_claro,#1e293b),var(--fundo,#0f172a));
    border-radius:20px;padding:20px 24px;margin-bottom:24px;
    border:1px solid rgba(255,255,255,0.08);
    display:flex;align-items:center;gap:20px;
    position:relative;overflow:hidden;
    transition:all .3s ease;
}
.stats-card:hover{transform:translateY(-2px);border-color:var(--primaria,#10b981);}
.stats-card-icon{
    width:60px;height:60px;background:linear-gradient(135deg,#25D366,#128C7E);
    border-radius:18px;display:flex;align-items:center;justify-content:center;
    font-size:32px;color:white;
}
.stats-card-content{flex:1;}
.stats-card-title{font-size:13px;font-weight:600;color:rgba(255,255,255,0.5);text-transform:uppercase;margin-bottom:5px;}
.stats-card-value{font-size:36px;font-weight:800;background:linear-gradient(135deg,#fff,var(--primaria,#10b981));-webkit-background-clip:text;-webkit-text-fill-color:transparent;line-height:1;}
.stats-card-subtitle{font-size:12px;color:rgba(255,255,255,0.4);}
.stats-card-decoration{position:absolute;right:20px;top:50%;transform:translateY(-50%);font-size:80px;opacity:0.05;}

/* Modern Card */
.modern-card{
    background:var(--fundo_claro,#1e293b);
    border-radius:16px;
    border:1px solid rgba(255,255,255,0.08);
    overflow:hidden;
    margin-bottom:16px;
    transition:all .2s;
}
.modern-card:hover{border-color:var(--primaria,#10b981);}
.card-header{
    padding:14px 18px;
    display:flex;
    align-items:center;
    gap:12px;
}
.card-header.api{background:linear-gradient(135deg,#38bdf8,#0284c7);}
.card-header.wpp{background:linear-gradient(135deg,#25D366,#128C7E);}
.card-header.msg{background:linear-gradient(135deg,#8b5cf6,#6d28d9);}
.card-header.var{background:linear-gradient(135deg,#06b6d4,#0891b2);}
.header-icon{
    width:36px;height:36px;
    background:rgba(255,255,255,0.2);
    border-radius:10px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:18px;
    color:white;
}
.header-title{font-size:14px;font-weight:700;color:white;}
.header-subtitle{font-size:10px;color:rgba(255,255,255,0.7);}
.header-right{margin-left:auto;display:flex;gap:8px;align-items:center;}
.card-body{padding:16px;}

/* Badges */
.sb{display:inline-flex;align-items:center;gap:4px;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:600;}
.sb.ok{background:rgba(16,185,129,.15);color:#10b981;border:1px solid rgba(16,185,129,.3);}
.sb.off{background:rgba(220,38,38,.15);color:#ef4444;border:1px solid rgba(220,38,38,.3);}
.sb.warn{background:rgba(245,158,11,.15);color:#fbbf24;border:1px solid rgba(245,158,11,.3);}

/* Botões */
.btn{
    padding:8px 16px;
    border:none;
    border-radius:10px;
    font-weight:600;
    font-size:12px;
    cursor:pointer;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:6px;
    color:white;
    transition:all .2s;
}
.btn:hover{transform:translateY(-1px);filter:brightness(1.08);}
.btn-wpp{background:linear-gradient(135deg,#25D366,#128C7E);}
.btn-primary{background:linear-gradient(135deg,#3b82f6,#2563eb);}
.btn-success{background:linear-gradient(135deg,#10b981,#059669);}
.btn-danger{background:linear-gradient(135deg,#dc2626,#b91c1c);}
.btn-warning{background:linear-gradient(135deg,#f59e0b,#f97316);}
.btn-gray{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);color:white;}
.btn-purple{background:linear-gradient(135deg,#8b5cf6,#6d28d9);}
.btn-sm{padding:5px 12px;font-size:11px;border-radius:8px;}

/* Formulários */
.fg{margin-bottom:14px;}
.fg label{display:flex;align-items:center;gap:5px;font-size:10px;font-weight:700;color:rgba(255,255,255,.4);text-transform:uppercase;margin-bottom:5px;}
.fc{
    width:100%;padding:9px 13px;
    background:rgba(255,255,255,.06);
    border:1px solid rgba(255,255,255,.1);
    border-radius:10px;
    color:#fff;
    font-size:12px;
    font-family:inherit;
    outline:none;
}
.fc:focus{border-color:#25D366;}
select.fc, select.fc option{background:#1e293b;color:#fff;}
textarea.fc{resize:vertical;min-height:90px;}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px;}

/* Feedback */
.fb{padding:10px 14px;border-radius:10px;font-size:12px;display:flex;align-items:center;gap:8px;margin-bottom:14px;}
.fb.ok{background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.25);color:#10b981;}
.fb.err{background:rgba(220,38,38,.1);border:1px solid rgba(220,38,38,.25);color:#f87171;}
.fb.info{background:rgba(59,130,246,.1);border:1px solid rgba(59,130,246,.25);color:#60a5fa;}

/* Nota */
.nota{background:rgba(59,130,246,.08);border-left:3px solid #3b82f6;padding:10px 14px;border-radius:8px;margin-bottom:14px;}
.nota small{color:rgba(255,255,255,.6);font-size:11px;}
.nota.warn{border-left-color:#f59e0b;}
.nota.danger{border-left-color:#dc2626;}

/* Instância */
.inst-box{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);border-radius:14px;padding:16px;position:relative;}
.inst-box.on{border-color:rgba(37,211,102,.5);background:rgba(37,211,102,.04);}
.inst-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;}
.inst-name{font-size:15px;font-weight:700;display:flex;align-items:center;gap:6px;}
.inst-name i{font-size:18px;color:#25D366;}
.inst-detail{font-size:11px;color:rgba(255,255,255,.5);margin-bottom:12px;display:flex;align-items:center;gap:5px;}
.inst-actions{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-top:8px;}
.pulse-dot{width:8px;height:8px;border-radius:50%;display:inline-block;}
.pulse-dot.on{background:#25D366;box-shadow:0 0 0 2px rgba(37,211,102,.3);animation:pulse 2s infinite;}
.pulse-dot.off{background:#ef4444;}
@keyframes pulse{0%,100%{box-shadow:0 0 0 2px rgba(37,211,102,.3)}50%{box-shadow:0 0 0 4px rgba(37,211,102,.1)}}

/* Tabela */
.tbl{width:100%;border-collapse:collapse;}
.tbl th,.tbl td{padding:10px 12px;text-align:left;border-bottom:1px solid rgba(255,255,255,.04);font-size:11px;}
.tbl th{color:rgba(255,255,255,.35);font-size:9px;text-transform:uppercase;}
.chip{display:inline-flex;align-items:center;gap:3px;padding:3px 8px;border-radius:20px;font-size:10px;font-weight:600;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.08);}
.te{text-align:center;color:rgba(255,255,255,.3);padding:30px;font-size:12px;}

/* Variáveis */
.varb{display:flex;flex-wrap:wrap;gap:8px;}
.vari{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:8px;padding:6px 12px;cursor:pointer;transition:all .2s;}
.vari:hover{background:rgba(139,92,246,.15);border-color:rgba(139,92,246,.3);}
.vari code{color:#a78bfa;font-size:11px;}
.vari span{color:rgba(255,255,255,.35);font-size:9px;margin-left:4px;}

/* QR Code */
#qr-area{text-align:center;padding:12px 0;}
#qr-area img{max-width:180px;border-radius:12px;}
.qm{font-size:11px;margin-top:8px;}
.qok{font-size:13px;color:#10b981;padding:20px 0;}

/* Modais */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.85);display:none;align-items:center;justify-content:center;z-index:9999;backdrop-filter:blur(8px);padding:16px;}
.modal-overlay.show{display:flex;}
.modal-container{animation:mIn .3s ease;max-width:500px;width:90%;}
.modal-container.wide{max-width:650px;}
@keyframes mIn{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
.modal-content{background:#1e293b;border-radius:20px;overflow:hidden;border:1px solid rgba(255,255,255,.1);}
.modal-header{padding:14px 18px;display:flex;align-items:center;justify-content:space-between;}
.modal-header h5{margin:0;display:flex;align-items:center;gap:8px;font-size:14px;font-weight:700;color:#fff;}
.modal-header.success{background:linear-gradient(135deg,#25D366,#128C7E);}
.modal-header.error{background:linear-gradient(135deg,#dc2626,#b91c1c);}
.modal-header.warning{background:linear-gradient(135deg,#f59e0b,#f97316);}
.modal-header.primary{background:linear-gradient(135deg,#3b82f6,#2563eb);}
.modal-header.purple{background:linear-gradient(135deg,#8b5cf6,#6d28d9);}
.modal-close{background:none;border:none;color:#fff;font-size:20px;cursor:pointer;}
.modal-body{padding:18px;max-height:60vh;overflow-y:auto;}
.modal-footer{border-top:1px solid rgba(255,255,255,.07);padding:12px 18px;display:flex;justify-content:flex-end;gap:8px;flex-wrap:wrap;}

/* Toast */
.toast{position:fixed;bottom:20px;right:20px;color:#fff;padding:10px 16px;border-radius:10px;display:flex;align-items:center;gap:8px;z-index:10000;animation:toastIn .3s ease;font-weight:600;font-size:12px;}
.toast.ok{background:linear-gradient(135deg,#25D366,#128C7E);}
.toast.err{background:linear-gradient(135deg,#dc2626,#b91c1c);}
@keyframes toastIn{from{transform:translateX(110%);opacity:0}to{transform:translateX(0);opacity:1}}
.spin{display:inline-block;width:12px;height:12px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:sp .7s linear infinite;}
@keyframes sp{to{transform:rotate(360deg)}}

@media(max-width:768px){
    .app-content{margin-left:0!important;}
    .content-wrapper{padding:10px!important;}
    .grid2{grid-template-columns:1fr;}
    .inst-actions{grid-template-columns:repeat(2,1fr);}
    .stats-card{padding:16px;}
    .stats-card-icon{width:48px;height:48px;font-size:26px;}
    .stats-card-value{font-size:28px;}
}
</style>
</head>
<body class="<?php echo htmlspecialchars(getBodyClass(<?php
// whatsconect_rev.php — WhatsApp Evolution API (Revendedor) com suporte a temas
if (session_status() === PHP_SESSION_NONE) session_start();
error_reporting(0);
date_default_timezone_set('America/Sao_Paulo');

include('../AegisCore/conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if ($conn) $conn->set_charset("utf8mb4");

// ========== INCLUIR SISTEMA DE TEMAS ==========
include_once '../AegisCore/temas.php';
$temaAtual = initTemas($conn);
$listaTemas = getListaTemas($conn);

if (!file_exists('../admin/suspenderrev.php')) {
    exit("<script>alert('Token Invalido!');</script>");
} else {
    include_once '../admin/suspenderrev.php';
}

if (!isset($_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe']) || !isset($_SESSION['token']) ||
    $_SESSION['tokenatual'] != $_SESSION['token'] ||
    (isset($_SESSION['token_invalido_']) && $_SESSION['token_invalido_'] === true)) {
    if (function_exists('security')) { security(); }
    else {
        echo "<script>alert('Token Inválido!');</script>";
        echo "<script>location.href='../index.php';</script>";
        $_SESSION['token_invalido_'] = true; exit;
    }
}

function esc_s($v) {
    global $conn;
    return mysqli_real_escape_string($conn, strip_tags(trim($v)));
}

function curlEvo($url, $token, $method = 'GET', $body = null, $timeout = 15) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => ['apikey: '.$token, 'Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    $resp  = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['body'=>$resp,'errno'=>$errno,'error'=>$error,'code'=>$code];
}

function carregarApiAdmin($conn) {
    $r = $conn->query("SELECT evo_apiurl, evo_token FROM configs LIMIT 1");
    if ($r && $r->num_rows > 0) {
        $cfg = $r->fetch_assoc();
        if (!empty($cfg['evo_apiurl']) && !empty($cfg['evo_token']))
            return ['apiurl' => $cfg['evo_apiurl'], 'token' => $cfg['evo_token']];
    }
    return ['apiurl' => '', 'token' => ''];
}

function carregarWppRev($conn, $byid) {
    $r = $conn->query("SELECT * FROM whatsapp WHERE byid='" . intval($byid) . "' LIMIT 1");
    return ($r && $r->num_rows > 0) ? $r->fetch_assoc() : [];
}

// ══════════════════════════════════════════════════════════════════
// AJAX
// ══════════════════════════════════════════════════════════════════
if (isset($_GET['ajax'])) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache');

    if (!isset($_SESSION['login'], $_SESSION['iduser'])) {
        echo json_encode(['ok' => false, 'msg' => 'Sessão expirada.']); exit;
    }

    $byid      = intval($_SESSION['iduser']);
    $wpp       = carregarWppRev($conn, $byid);
    $api_admin = carregarApiAdmin($conn);
    $api_base  = rtrim($api_admin['apiurl'] ?? '', '/');
    $tok       = $api_admin['token'] ?? '';
    $inst      = trim($wpp['sessao'] ?? '');

    if (!empty($api_base) && !preg_match('#^https?://#i', $api_base))
        $api_base = 'http://' . $api_base;

    $acao = $_GET['ajax'];

    if ($acao === 'status') {
        if (empty($api_base) || empty($tok) || empty($inst)) {
            echo json_encode(['state' => 'not_configured']); exit;
        }
        $r = curlEvo($api_base . '/instance/connectionState/' . urlencode($inst), $tok, 'GET', null, 8);
        if ($r['errno'] !== 0) { echo json_encode(['state' => 'error', 'error' => $r['error']]); exit; }
        $d = json_decode($r['body'], true);
        if ($d === null) { echo json_encode(['state' => 'error']); exit; }
        if (!isset($d['state']) && isset($d['instance']['state'])) $d['state'] = $d['instance']['state'];
        echo json_encode($d); exit;
    }

    if ($acao === 'criar') {
        $nome = trim($_POST['instancia'] ?? '');
        if (empty($nome) || empty($api_base) || empty($tok)) {
            echo json_encode(['erro' => 'API não configurada pelo administrador.']); exit;
        }
        $r_check = curlEvo($api_base . '/instance/connectionState/' . urlencode($nome), $tok, 'GET', null, 8);
        $d_check  = json_decode($r_check['body'], true);
        $ja_existe = ($r_check['code'] === 200 && $d_check !== null);

        if (!$ja_existe) {
            $body = json_encode(['instanceName' => $nome, 'qrcode' => true, 'integration' => 'WHATSAPP-BAILEYS']);
            $r    = curlEvo($api_base . '/instance/create', $tok, 'POST', $body, 20);
            if ($r['errno'] !== 0) { echo json_encode(['erro' => 'cURL #' . $r['errno'] . ': ' . $r['error']]); exit; }
            $d        = json_decode($r['body'], true);
            $criou    = isset($d['instance']['instanceName']) || isset($d['instanceName']);
            $ja_existe_api = stripos($r['body'], 'exist') !== false;
            if (!$criou && !$ja_existe_api) {
                echo json_encode(['erro' => ($d['message'] ?? ($d['error'] ?? mb_substr($r['body'], 0, 200)))]); exit;
            }
        }

        $nome_s = esc_s($nome);
        $chk    = $conn->query("SELECT id FROM whatsapp WHERE byid='$byid'");
        if ($chk && $chk->num_rows > 0)
            $conn->query("UPDATE whatsapp SET sessao='$nome_s' WHERE byid='$byid'");
        else
            $conn->query("INSERT INTO whatsapp (byid, sessao, apiurl, token, ativo) VALUES ('$byid','$nome_s','','','1')");

        echo json_encode(['ok' => true, 'msg' => $ja_existe ? 'Instância vinculada!' : 'Instância criada com sucesso!', 'instancia' => $nome]);
        exit;
    }

    if ($acao === 'qr') {
        if (empty($api_base) || empty($tok) || empty($inst)) {
            echo json_encode(['erro' => 'Configure e salve a instância primeiro.']); exit;
        }
        $r_state = curlEvo($api_base . '/instance/connectionState/' . urlencode($inst), $tok, 'GET', null, 8);
        $d_state  = json_decode($r_state['body'], true);
        $state    = $d_state['instance']['state'] ?? $d_state['state'] ?? '';
        if ($state === 'open') {
            echo json_encode(['state' => 'open']); exit;
        }
        $r = curlEvo($api_base . '/instance/connect/' . urlencode($inst), $tok, 'GET', null, 20);
        if ($r['errno'] !== 0) { echo json_encode(['erro' => 'cURL #' . $r['errno'] . ': ' . $r['error']]); exit; }
        $d = json_decode($r['body'], true);
        echo ($d !== null) ? $r['body'] : json_encode(['erro' => 'Resposta inválida']);
        exit;
    }

    if ($acao === 'logout') {
        if (empty($api_base) || empty($tok) || empty($inst)) {
            echo json_encode(['ok' => false, 'msg' => 'Instância não configurada.']); exit;
        }
        $r = curlEvo($api_base . '/instance/logout/' . urlencode($inst), $tok, 'DELETE', null, 12);
        $d = json_decode($r['body'], true);
        echo ($d !== null) ? $r['body'] : json_encode(['ok' => true]);
        exit;
    }

    if ($acao === 'deletar_inst') {
        if (empty($api_base) || empty($tok)) {
            echo json_encode(['ok' => false, 'msg' => 'API não configurada.']); exit;
        }
        if (empty($inst)) {
            $conn->query("UPDATE whatsapp SET sessao='' WHERE byid='$byid'");
            echo json_encode(['ok' => true, 'msg' => 'Banco limpo!']); exit;
        }
        curlEvo($api_base . '/instance/logout/' . urlencode($inst), $tok, 'DELETE', null, 6);
        sleep(1);
        $r = curlEvo($api_base . '/instance/delete/' . urlencode($inst), $tok, 'DELETE', null, 15);
        if ($r['code'] === 404 || ($r['code'] >= 200 && $r['code'] < 300)) {
            $conn->query("UPDATE whatsapp SET sessao='' WHERE byid='$byid'");
            echo json_encode(['ok' => true, 'msg' => 'Instância deletada!']); exit;
        }
        $d = json_decode($r['body'], true);
        echo json_encode(['ok' => false, 'msg' => 'Erro HTTP ' . $r['code'] . ': ' . ($d['message'] ?? mb_substr($r['body'], 0, 200))]);
        exit;
    }

    if ($acao === 'testar') {
        $num_raw = $_POST['numero'] ?? '';
        $txt     = $_POST['texto']  ?? 'Teste Atlas Painel ✅';
        if (empty($num_raw) || empty($inst) || empty($api_base) || empty($tok)) {
            echo json_encode(['ok' => false, 'msg' => 'Configure a instância antes de testar.']); exit;
        }
        $num = preg_replace('/\D/', '', $num_raw);
        if (strlen($num) <= 11 && substr($num, 0, 2) !== '55') $num = '55' . $num;
        $url = $api_base . '/message/sendText/' . urlencode($inst);
        $r   = curlEvo($url, $tok, 'POST', json_encode(['number' => $num, 'textMessage' => ['text' => $txt]]), 20);
        if ($r['code'] >= 400 || $r['errno'] !== 0)
            $r = curlEvo($url, $tok, 'POST', json_encode(['number' => $num, 'text' => $txt, 'options' => ['delay' => 0]]), 20);
        $d  = json_decode($r['body'], true);
        $ok = ($r['code'] >= 200 && $r['code'] < 300 && $r['errno'] === 0 &&
               (isset($d['key']) || isset($d['id']) || isset($d['messageId'])));
        echo json_encode(['ok' => $ok, 'msg' => $ok ? '✅ Mensagem enviada para ' . $num . '!' : 'Falha HTTP ' . $r['code'] . ': ' . ($d['message'] ?? $d['error'] ?? mb_substr($r['body'], 0, 200)), 'numero' => $num]);
        exit;
    }

    if ($acao === 'get_msg') {
        $id  = intval($_GET['id'] ?? 0);
        $res = $conn->query("SELECT * FROM mensagens WHERE id='$id' AND byid='$byid' LIMIT 1");
        echo ($res && $res->num_rows > 0) ? json_encode($res->fetch_assoc()) : json_encode([]);
        exit;
    }

    echo json_encode(['ok' => false, 'msg' => 'Ação desconhecida.']); exit;
}

// ══════════════════════════════════════════════════════════════════
// PÁGINA NORMAL
// ══════════════════════════════════════════════════════════════════
include('header2.php');

if (!isset($_SESSION['login'], $_SESSION['iduser'])) {
    echo "<script>location.href='../index.php';</script>"; exit;
}

$byid = intval($_SESSION['iduser']);

$conn->query("CREATE TABLE IF NOT EXISTS whatsapp (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, byid INT NOT NULL DEFAULT 0, sessao VARCHAR(100) DEFAULT '', token TEXT DEFAULT NULL, apiurl VARCHAR(255) DEFAULT '', ativo TINYINT(1) DEFAULT 1, UNIQUE KEY uk_byid (byid)) DEFAULT CHARSET=utf8mb4");
$conn->query("CREATE TABLE IF NOT EXISTS mensagens (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, byid INT NOT NULL DEFAULT 0, funcao VARCHAR(50) NOT NULL, mensagem TEXT, ativo VARCHAR(20) DEFAULT 'ativada', hora VARCHAR(5) DEFAULT '08:00') DEFAULT CHARSET=utf8mb4");

$wpp        = carregarWppRev($conn, $byid);
$inst_atual = trim($wpp['sessao'] ?? '');
$api_admin  = carregarApiAdmin($conn);
$api_base   = rtrim($api_admin['apiurl'] ?? '', '/');
$tok_admin  = $api_admin['token'] ?? '';
$api_ok     = !empty($api_base) && !empty($tok_admin);
$inst_ok    = !empty($inst_atual);

$res_acc    = $conn->query("SELECT whatsapp FROM accounts WHERE id='$byid' LIMIT 1");
$acc        = ($res_acc && $res_acc->num_rows > 0) ? $res_acc->fetch_assoc() : [];
$numero_wpp = $acc['whatsapp'] ?? '';

$msg = ''; $tipo = '';

if (isset($_POST['salvar_inst'])) {
    $inst   = esc_s($_POST['instancia'] ?? '');
    $numero = esc_s($_POST['numero']    ?? '');
    if (empty($inst)) { $msg = '⚠️ Informe o nome!'; $tipo = 'err'; }
    else {
        $chk = $conn->query("SELECT id FROM whatsapp WHERE byid='$byid'");
        if ($chk && $chk->num_rows > 0) $conn->query("UPDATE whatsapp SET sessao='$inst' WHERE byid='$byid'");
        else $conn->query("INSERT INTO whatsapp (byid,sessao,apiurl,token,ativo) VALUES ('$byid','$inst','','','1')");
        $conn->query("UPDATE accounts SET whatsapp='$numero' WHERE id='$byid'");
        $inst_atual = $inst; $numero_wpp = $numero; $inst_ok = true;
        $msg = '✅ Instância salva: ' . htmlspecialchars($inst); $tipo = 'ok';
    }
}
if (isset($_POST['adicionar'])) {
    $mens = $conn->real_escape_string($_POST['mensagem'] ?? '');
    $func = esc_s($_POST['funcao'] ?? '');
    $atv  = esc_s($_POST['ativo']  ?? 'ativada');
    $hora = esc_s($_POST['add_hora'] ?? '08:00');
    $chk  = $conn->query("SELECT id FROM mensagens WHERE funcao='$func' AND byid='$byid'");
    if ($chk && $chk->num_rows > 0) { $msg = '⚠️ Este evento já tem mensagem!'; $tipo = 'err'; }
    else { $conn->query("INSERT INTO mensagens (byid,funcao,mensagem,ativo,hora) VALUES ('$byid','$func','$mens','$atv','$hora')"); $msg = '✅ Mensagem adicionada!'; $tipo = 'ok'; }
}
if (isset($_POST['editar'])) {
    $id   = intval($_POST['edit_id']);
    $mens = $conn->real_escape_string($_POST['edit_mensagem'] ?? '');
    $func = esc_s($_POST['edit_funcao'] ?? '');
    $atv  = esc_s($_POST['edit_ativo']  ?? 'ativada');
    $hora = esc_s($_POST['edit_hora']   ?? '08:00');
    $conn->query("UPDATE mensagens SET mensagem='$mens',funcao='$func',ativo='$atv',hora='$hora' WHERE id='$id' AND byid='$byid'");
    $msg = '✅ Mensagem atualizada!'; $tipo = 'ok';
}
if (isset($_POST['deletar'])) {
    $conn->query("DELETE FROM mensagens WHERE id='" . intval($_POST['edit_id']) . "' AND byid='$byid'");
    $msg = '✅ Mensagem removida!'; $tipo = 'ok';
}

$wpp_online = false;
if ($api_ok && $inst_ok) {
    $api_n = $api_base;
    if (!preg_match('#^https?://#i', $api_n)) $api_n = 'http://' . $api_n;
    $r      = curlEvo($api_n . '/instance/connectionState/' . urlencode($inst_atual), $tok_admin, 'GET', null, 5);
    $sd     = json_decode($r['body'], true);
    $wpp_online = (($sd['instance']['state'] ?? $sd['state'] ?? '') === 'open');
}

$res_mens = $conn->query("SELECT * FROM mensagens WHERE byid='$byid' ORDER BY id ASC");
$total_mens = $res_mens ? $res_mens->num_rows : 0;

$funcoes_labels = [
    'criarusuario'    => ['label' => 'Criar Usuário',    'icon' => 'bx-user-plus',     'cor' => '#818cf8'],
    'criarteste'      => ['label' => 'Criar Teste',      'icon' => 'bx-test-tube',     'cor' => '#34d399'],
    'criarrevenda'    => ['label' => 'Criar Revenda',    'icon' => 'bx-store-alt',     'cor' => '#fbbf24'],
    'contaexpirada'   => ['label' => 'Usuário Vencendo', 'icon' => 'bx-time',          'cor' => '#f87171'],
    'revendaexpirada' => ['label' => 'Revenda Vencendo', 'icon' => 'bx-calendar-x',   'cor' => '#fb923c'],
    'renovacaopag'    => ['label' => 'Pagto Aprovado',   'icon' => 'bx-check-circle',  'cor' => '#10b981'],
    'planoaprovado'   => ['label' => 'Plano Aprovado',   'icon' => 'bx-dollar-circle', 'cor' => '#a78bfa'],
];
$templates = [
    'criarusuario'    => "🎉 *Usuário Criado!*\n\n👤 Usuário: {usuario}\n🔑 Senha: {senha}\n📅 Validade: {validade}\n👥 Limite: {limite}\n\n🌐 Renovação: https://{dominio}/renovar.php",
    'criarteste'      => "🧪 *Teste Criado!*\n\n👤 Usuário: {usuario}\n🔑 Senha: {senha}\n⏱ Duração: {validade} min\n👥 Limite: {limite}",
    'criarrevenda'    => "🏪 *Revenda Criada!*\n\n👤 Revenda: {usuario}\n🔑 Senha: {senha}\n📅 Validade: {validade}\n👥 Limite: {limite}\n\n🌐 Painel: https://{dominio}/",
    'contaexpirada'   => "⚠️ *Conta vencendo!*\n\n👤 Usuário: {usuario}\n📅 Validade: {validade}\n\n🔄 Renove: https://{dominio}/renovar.php",
    'revendaexpirada' => "⚠️ *Revenda vencendo!*\n\n👤 Revenda: {usuario}\n📅 Validade: {validade}\n\n🔄 Acesse o painel.",
    'renovacaopag'    => "✅ *Pagamento Aprovado!*\n\n👤 Usuário: {usuario}\n📅 Nova validade: {validade}\n\nObrigado! 🙏",
    'planoaprovado'   => "✅ *Plano Aprovado!*\n\n👤 Revenda: {usuario}\n📅 Nova validade: {validade}\n👥 Limite: {limite}\n\nBem-vindo! 🚀",
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>WhatsApp — Evolution API</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Inter',sans-serif;background:var(--fundo,#0f172a);color:var(--texto,#fff);min-height:100vh;}
.app-content{margin-left:-390px!important;padding:0!important;}
.content-wrapper{max-width:1040px;margin:0 auto!important;padding:20px!important;}
.content-body{padding:0!important;}

/* Stats Card */
.stats-card{
    background:linear-gradient(135deg,var(--fundo_claro,#1e293b),var(--fundo,#0f172a));
    border-radius:20px;padding:20px 24px;margin-bottom:24px;
    border:1px solid rgba(255,255,255,0.08);
    display:flex;align-items:center;gap:20px;
    position:relative;overflow:hidden;
    transition:all .3s ease;
}
.stats-card:hover{transform:translateY(-2px);border-color:var(--primaria,#10b981);}
.stats-card-icon{
    width:60px;height:60px;background:linear-gradient(135deg,#25D366,#128C7E);
    border-radius:18px;display:flex;align-items:center;justify-content:center;
    font-size:32px;color:white;
}
.stats-card-content{flex:1;}
.stats-card-title{font-size:13px;font-weight:600;color:rgba(255,255,255,0.5);text-transform:uppercase;margin-bottom:5px;}
.stats-card-value{font-size:36px;font-weight:800;background:linear-gradient(135deg,#fff,var(--primaria,#10b981));-webkit-background-clip:text;-webkit-text-fill-color:transparent;line-height:1;}
.stats-card-subtitle{font-size:12px;color:rgba(255,255,255,0.4);}
.stats-card-decoration{position:absolute;right:20px;top:50%;transform:translateY(-50%);font-size:80px;opacity:0.05;}

/* Modern Card */
.modern-card{
    background:var(--fundo_claro,#1e293b);
    border-radius:16px;
    border:1px solid rgba(255,255,255,0.08);
    overflow:hidden;
    margin-bottom:16px;
    transition:all .2s;
}
.modern-card:hover{border-color:var(--primaria,#10b981);}
.card-header{
    padding:14px 18px;
    display:flex;
    align-items:center;
    gap:12px;
}
.card-header.api{background:linear-gradient(135deg,#38bdf8,#0284c7);}
.card-header.wpp{background:linear-gradient(135deg,#25D366,#128C7E);}
.card-header.msg{background:linear-gradient(135deg,#8b5cf6,#6d28d9);}
.card-header.var{background:linear-gradient(135deg,#06b6d4,#0891b2);}
.header-icon{
    width:36px;height:36px;
    background:rgba(255,255,255,0.2);
    border-radius:10px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:18px;
    color:white;
}
.header-title{font-size:14px;font-weight:700;color:white;}
.header-subtitle{font-size:10px;color:rgba(255,255,255,0.7);}
.header-right{margin-left:auto;display:flex;gap:8px;align-items:center;}
.card-body{padding:16px;}

/* Badges */
.sb{display:inline-flex;align-items:center;gap:4px;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:600;}
.sb.ok{background:rgba(16,185,129,.15);color:#10b981;border:1px solid rgba(16,185,129,.3);}
.sb.off{background:rgba(220,38,38,.15);color:#ef4444;border:1px solid rgba(220,38,38,.3);}
.sb.warn{background:rgba(245,158,11,.15);color:#fbbf24;border:1px solid rgba(245,158,11,.3);}

/* Botões */
.btn{
    padding:8px 16px;
    border:none;
    border-radius:10px;
    font-weight:600;
    font-size:12px;
    cursor:pointer;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:6px;
    color:white;
    transition:all .2s;
}
.btn:hover{transform:translateY(-1px);filter:brightness(1.08);}
.btn-wpp{background:linear-gradient(135deg,#25D366,#128C7E);}
.btn-primary{background:linear-gradient(135deg,#3b82f6,#2563eb);}
.btn-success{background:linear-gradient(135deg,#10b981,#059669);}
.btn-danger{background:linear-gradient(135deg,#dc2626,#b91c1c);}
.btn-warning{background:linear-gradient(135deg,#f59e0b,#f97316);}
.btn-gray{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);color:white;}
.btn-purple{background:linear-gradient(135deg,#8b5cf6,#6d28d9);}
.btn-sm{padding:5px 12px;font-size:11px;border-radius:8px;}

/* Formulários */
.fg{margin-bottom:14px;}
.fg label{display:flex;align-items:center;gap:5px;font-size:10px;font-weight:700;color:rgba(255,255,255,.4);text-transform:uppercase;margin-bottom:5px;}
.fc{
    width:100%;padding:9px 13px;
    background:rgba(255,255,255,.06);
    border:1px solid rgba(255,255,255,.1);
    border-radius:10px;
    color:#fff;
    font-size:12px;
    font-family:inherit;
    outline:none;
}
.fc:focus{border-color:#25D366;}
select.fc, select.fc option{background:#1e293b;color:#fff;}
textarea.fc{resize:vertical;min-height:90px;}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px;}

/* Feedback */
.fb{padding:10px 14px;border-radius:10px;font-size:12px;display:flex;align-items:center;gap:8px;margin-bottom:14px;}
.fb.ok{background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.25);color:#10b981;}
.fb.err{background:rgba(220,38,38,.1);border:1px solid rgba(220,38,38,.25);color:#f87171;}
.fb.info{background:rgba(59,130,246,.1);border:1px solid rgba(59,130,246,.25);color:#60a5fa;}

/* Nota */
.nota{background:rgba(59,130,246,.08);border-left:3px solid #3b82f6;padding:10px 14px;border-radius:8px;margin-bottom:14px;}
.nota small{color:rgba(255,255,255,.6);font-size:11px;}
.nota.warn{border-left-color:#f59e0b;}
.nota.danger{border-left-color:#dc2626;}

/* Instância */
.inst-box{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);border-radius:14px;padding:16px;position:relative;}
.inst-box.on{border-color:rgba(37,211,102,.5);background:rgba(37,211,102,.04);}
.inst-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;}
.inst-name{font-size:15px;font-weight:700;display:flex;align-items:center;gap:6px;}
.inst-name i{font-size:18px;color:#25D366;}
.inst-detail{font-size:11px;color:rgba(255,255,255,.5);margin-bottom:12px;display:flex;align-items:center;gap:5px;}
.inst-actions{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-top:8px;}
.pulse-dot{width:8px;height:8px;border-radius:50%;display:inline-block;}
.pulse-dot.on{background:#25D366;box-shadow:0 0 0 2px rgba(37,211,102,.3);animation:pulse 2s infinite;}
.pulse-dot.off{background:#ef4444;}
@keyframes pulse{0%,100%{box-shadow:0 0 0 2px rgba(37,211,102,.3)}50%{box-shadow:0 0 0 4px rgba(37,211,102,.1)}}

/* Tabela */
.tbl{width:100%;border-collapse:collapse;}
.tbl th,.tbl td{padding:10px 12px;text-align:left;border-bottom:1px solid rgba(255,255,255,.04);font-size:11px;}
.tbl th{color:rgba(255,255,255,.35);font-size:9px;text-transform:uppercase;}
.chip{display:inline-flex;align-items:center;gap:3px;padding:3px 8px;border-radius:20px;font-size:10px;font-weight:600;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.08);}
.te{text-align:center;color:rgba(255,255,255,.3);padding:30px;font-size:12px;}

/* Variáveis */
.varb{display:flex;flex-wrap:wrap;gap:8px;}
.vari{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:8px;padding:6px 12px;cursor:pointer;transition:all .2s;}
.vari:hover{background:rgba(139,92,246,.15);border-color:rgba(139,92,246,.3);}
.vari code{color:#a78bfa;font-size:11px;}
.vari span{color:rgba(255,255,255,.35);font-size:9px;margin-left:4px;}

/* QR Code */
#qr-area{text-align:center;padding:12px 0;}
#qr-area img{max-width:180px;border-radius:12px;}
.qm{font-size:11px;margin-top:8px;}
.qok{font-size:13px;color:#10b981;padding:20px 0;}

/* Modais */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.85);display:none;align-items:center;justify-content:center;z-index:9999;backdrop-filter:blur(8px);padding:16px;}
.modal-overlay.show{display:flex;}
.modal-container{animation:mIn .3s ease;max-width:500px;width:90%;}
.modal-container.wide{max-width:650px;}
@keyframes mIn{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
.modal-content{background:#1e293b;border-radius:20px;overflow:hidden;border:1px solid rgba(255,255,255,.1);}
.modal-header{padding:14px 18px;display:flex;align-items:center;justify-content:space-between;}
.modal-header h5{margin:0;display:flex;align-items:center;gap:8px;font-size:14px;font-weight:700;color:#fff;}
.modal-header.success{background:linear-gradient(135deg,#25D366,#128C7E);}
.modal-header.error{background:linear-gradient(135deg,#dc2626,#b91c1c);}
.modal-header.warning{background:linear-gradient(135deg,#f59e0b,#f97316);}
.modal-header.primary{background:linear-gradient(135deg,#3b82f6,#2563eb);}
.modal-header.purple{background:linear-gradient(135deg,#8b5cf6,#6d28d9);}
.modal-close{background:none;border:none;color:#fff;font-size:20px;cursor:pointer;}
.modal-body{padding:18px;max-height:60vh;overflow-y:auto;}
.modal-footer{border-top:1px solid rgba(255,255,255,.07);padding:12px 18px;display:flex;justify-content:flex-end;gap:8px;flex-wrap:wrap;}

/* Toast */
.toast{position:fixed;bottom:20px;right:20px;color:#fff;padding:10px 16px;border-radius:10px;display:flex;align-items:center;gap:8px;z-index:10000;animation:toastIn .3s ease;font-weight:600;font-size:12px;}
.toast.ok{background:linear-gradient(135deg,#25D366,#128C7E);}
.toast.err{background:linear-gradient(135deg,#dc2626,#b91c1c);}
@keyframes toastIn{from{transform:translateX(110%);opacity:0}to{transform:translateX(0);opacity:1}}
.spin{display:inline-block;width:12px;height:12px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:sp .7s linear infinite;}
@keyframes sp{to{transform:rotate(360deg)}}

@media(max-width:768px){
    .app-content{margin-left:0!important;}
    .content-wrapper{padding:10px!important;}
    .grid2{grid-template-columns:1fr;}
    .inst-actions{grid-template-columns:repeat(2,1fr);}
    .stats-card{padding:16px;}
    .stats-card-icon{width:48px;height:48px;font-size:26px;}
    .stats-card-value{font-size:28px;}
}
</style>
</head>
<body>
<div class="app-content content">
<div class="content-overlay"></div>
<div class="content-wrapper">
<div class="content-body">

<!-- Stats Card -->
<div class="stats-card">
    <div class="stats-card-icon"><i class='bx bxl-whatsapp'></i></div>
    <div class="stats-card-content">
        <div class="stats-card-title">WhatsApp Evolution API</div>
        <div class="stats-card-value"><?php echo $total_mens; ?></div>
        <div class="stats-card-subtitle">mensagem(ns) configurada(s)</div>
    </div>
    <div class="stats-card-decoration"><i class='bx bxl-whatsapp'></i></div>
</div>

<?php if ($msg): ?>
<div class="fb <?php echo $tipo; ?>"><i class='bx bx-<?php echo $tipo==='ok'?'check-circle':'error-circle'; ?>'></i><?php echo htmlspecialchars($msg); ?></div>
<?php endif; ?>

<!-- CARD: API -->
<div class="modern-card">
    <div class="card-header api">
        <div class="header-icon"><i class='bx bx-server'></i></div>
        <div><div class="header-title">Servidor Evolution API</div><div class="header-subtitle">Configurado pelo administrador</div></div>
        <div class="header-right">
            <span class="sb <?php echo $api_ok?'ok':'warn'; ?>">
                <i class='bx bx-<?php echo $api_ok?'check-circle':'time'; ?>'></i>
                <?php echo $api_ok?'Disponível':'Aguardando'; ?>
            </span>
        </div>
    </div>
    <div class="card-body">
        <?php if ($api_ok): ?>
        <div class="fb ok" style="margin:0;"><i class='bx bx-shield-check'></i>API configurada pelo administrador. Pronta para uso.</div>
        <?php else: ?>
        <div class="fb err" style="margin:0;"><i class='bx bx-error-circle'></i>API ainda não configurada. Entre em contato com o administrador.</div>
        <?php endif; ?>
    </div>
</div>

<!-- CARD: INSTÂNCIA -->
<div class="modern-card" <?php echo !$api_ok?'style="opacity:.5;pointer-events:none;"':''; ?>>
    <div class="card-header wpp">
        <div class="header-icon"><i class='bx bxl-whatsapp'></i></div>
        <div><div class="header-title">Minha Instância</div><div class="header-subtitle">Conexão pessoal</div></div>
        <div class="header-right">
            <?php if ($wpp_online): ?>
            <span class="sb ok"><span class="pulse-dot on"></span>Online</span>
            <?php elseif ($inst_ok): ?>
            <span class="sb off"><span class="pulse-dot off"></span>Offline</span>
            <?php endif; ?>
            <button class="btn btn-wpp btn-sm" onclick="abrirModal('mInst')">
                <i class='bx bx-plus'></i><?php echo $inst_ok?'Editar':'Configurar'; ?>
            </button>
        </div>
    </div>
    <div class="card-body">
        <?php if ($inst_ok): ?>
        <div class="inst-box <?php echo $wpp_online?'on':'off'; ?>">
            <div class="inst-top">
                <div class="inst-name">
                    <i class='bx bxl-whatsapp'></i>
                    <?php echo htmlspecialchars($inst_atual); ?>
                </div>
                <span class="sb <?php echo $wpp_online?'ok':'off'; ?>">
                    <span class="pulse-dot <?php echo $wpp_online?'on':'off'; ?>"></span>
                    <?php echo $wpp_online?'Online':'Offline'; ?>
                </span>
            </div>
            <div class="inst-detail">
                <i class='bx bx-phone'></i>
                <?php echo $numero_wpp?htmlspecialchars($numero_wpp):'Número não cadastrado'; ?>
            </div>
            <div class="inst-actions">
                <button class="btn btn-wpp" onclick="abrirQR()">
                    <i class='bx bx-<?php echo $wpp_online?'refresh':'plug'; ?>'></i>
                    <?php echo $wpp_online?'Reconectar':'Conectar'; ?>
                </button>
                <button class="btn btn-warning" onclick="abrirModal('mTestar')">
                    <i class='bx bx-send'></i>Testar
                </button>
                <button class="btn btn-danger" onclick="confirmarLogout()">
                    <i class='bx bx-log-out'></i>Desconectar
                </button>
                <button class="btn btn-danger" onclick="confirmarDeletar()">
                    <i class='bx bx-trash'></i>Excluir
                </button>
            </div>
        </div>
        <?php else: ?>
        <div class="te">
            <i class='bx bxl-whatsapp' style="font-size:42px;display:block;margin-bottom:10px;opacity:.2;"></i>
            Nenhuma instância configurada.<br>
            <button class="btn btn-wpp" style="margin-top:12px;" onclick="abrirModal('mInst')">
                <i class='bx bx-plus'></i>Configurar agora
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- CARD: MENSAGENS -->
<div class="modern-card" <?php echo !$inst_ok?'style="opacity:.5;pointer-events:none;"':''; ?>>
    <div class="card-header msg">
        <div class="header-icon"><i class='bx bx-message-detail'></i></div>
        <div><div class="header-title">Mensagens</div><div class="header-subtitle">Disparadas nos eventos</div></div>
        <div class="header-right">
            <button class="btn btn-purple btn-sm" onclick="abrirModal('mAdd')">
                <i class='bx bx-plus'></i>Adicionar
            </button>
        </div>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="tbl">
            <thead>
                <tr>
                    <th>Mensagem</th>
                    <th>Evento</th>
                    <th>Horário</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($res_mens && $res_mens->num_rows > 0):
                $res_mens->data_seek(0);
                while ($rm = $res_mens->fetch_assoc()):
                    $fl = $funcoes_labels[$rm['funcao']] ?? ['label'=>$rm['funcao'],'icon'=>'bx-bell','cor'=>'#94a3b8'];
                    $hv = in_array($rm['funcao'],['contaexpirada','revendaexpirada']);
            ?>
                <tr>
                    <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo htmlspecialchars(mb_substr($rm['mensagem'],0,45)).(mb_strlen($rm['mensagem'])>45?'…':''); ?></td>
                    <td><span class="chip"><i class='bx <?php echo $fl['icon']; ?>' style="color:<?php echo $fl['cor']; ?>"></i><?php echo $fl['label']; ?></span></td>
                    <td><?php echo $hv?($rm['hora']??'08:00'):'—'; ?></td>
                    <td><span class="sb <?php echo $rm['ativo']==='ativada'?'ok':'off'; ?>"><?php echo $rm['ativo']==='ativada'?'Ativa':'Inativa'; ?></span></td>
                    <td><button class="btn btn-gray btn-sm" onclick="editarMsg(<?php echo $rm['id']; ?>)"><i class='bx bx-edit-alt'></i></button></td>
                </tr>
            <?php endwhile; else: ?>
                <tr><td colspan="5" class="te"><i class='bx bx-message-x' style="font-size:28px;"></i><br>Nenhuma mensagem</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- CARD: VARIÁVEIS -->
<div class="modern-card">
    <div class="card-header var">
        <div class="header-icon"><i class='bx bx-code-curly'></i></div>
        <div><div class="header-title">Variáveis</div><div class="header-subtitle">Clique para copiar</div></div>
    </div>
    <div class="card-body">
        <div class="varb">
            <?php foreach(['{usuario}'=>'Login','{senha}'=>'Senha','{validade}'=>'Validade','{limite}'=>'Limite','{dominio}'=>'Domínio'] as $v=>$d): ?>
            <div class="vari" onclick="copiarVar('<?php echo $v; ?>')"><code><?php echo $v; ?></code><span><?php echo $d; ?></span></div>
            <?php endforeach; ?>
        </div>
        <div id="copy-ok" class="fb ok" style="display:none;margin-top:8px;"><i class='bx bx-check-circle'></i>Copiado!</div>
    </div>
</div>

</div></div></div>

<!-- MODAIS -->
<div class="modal-overlay" id="mInst"><div class="modal-container"><div class="modal-content">
    <div class="modal-header success"><h5><i class='bx bxl-whatsapp'></i>Instância WhatsApp</h5><button class="modal-close" onclick="fecharModal('mInst')"><i class='bx bx-x'></i></button></div>
    <form method="POST"><div class="modal-body">
        <div class="nota warn"><small>⚠️ Crie apenas uma vez. Se já existir, será vinculada.</small></div>
        <div class="fg"><label><i class='bx bx-cube'></i> Nome</label><input class="fc" type="text" name="instancia" id="inst_nome" value="<?php echo htmlspecialchars($inst_atual); ?>" placeholder="ex: minha_revenda_01"></div>
        <div class="fg"><label><i class='bx bx-phone'></i> Número WhatsApp</label><input class="fc" type="text" name="numero" value="<?php echo htmlspecialchars($numero_wpp); ?>" placeholder="5511999999999"></div>
        <div id="criar-res"></div>
    </div><div class="modal-footer">
        <button type="button" class="btn btn-gray" onclick="fecharModal('mInst')"><i class='bx bx-x'></i>Cancelar</button>
        <button type="button" class="btn btn-primary" onclick="executarCriar()"><i class='bx bx-play'></i>Criar</button>
        <button type="submit" name="salvar_inst" class="btn btn-success"><i class='bx bx-save'></i>Salvar</button>
    </div></form>
</div></div></div>

<div class="modal-overlay" id="mQR"><div class="modal-container"><div class="modal-content">
    <div class="modal-header success"><h5><i class='bx bx-qr-scan'></i>Conectar</h5><button class="modal-close" onclick="fecharModal('mQR');pararVerif();"><i class='bx bx-x'></i></button></div>
    <div class="modal-body"><div id="qr-area"><div class="qm"><span class="spin"></span> Gerando QR...</div></div><div class="nota"><small>📱 WhatsApp → Configurações → Dispositivos Vinculados</small></div></div>
    <div class="modal-footer"><button class="btn btn-gray" onclick="fecharModal('mQR');pararVerif();"><i class='bx bx-x'></i>Fechar</button><button class="btn btn-primary" onclick="carregarQR()"><i class='bx bx-refresh'></i>Novo QR</button><button class="btn btn-success" onclick="verificarStatus()"><i class='bx bx-check-circle'></i>Verificar</button></div>
</div></div></div>

<div class="modal-overlay" id="mTestar"><div class="modal-container"><div class="modal-content">
    <div class="modal-header warning"><h5><i class='bx bx-send'></i>Testar Envio</h5><button class="modal-close" onclick="fecharModal('mTestar')"><i class='bx bx-x'></i></button></div>
    <div class="modal-body"><div class="fg"><label><i class='bx bx-phone'></i>Número</label><input class="fc" type="text" id="test_num" value="<?php echo htmlspecialchars($numero_wpp); ?>" placeholder="5511999999999"></div><div class="fg"><label><i class='bx bx-message-square-detail'></i>Mensagem</label><textarea class="fc" id="test_msg" rows="4">✅ Teste WhatsApp! Conexão funcionando! 🚀</textarea></div><div id="test-res"></div></div>
    <div class="modal-footer"><button class="btn btn-gray" onclick="fecharModal('mTestar')"><i class='bx bx-x'></i>Fechar</button><button class="btn btn-warning" onclick="executarTeste()"><i class='bx bx-send'></i>Enviar</button></div>
</div></div></div>

<div class="modal-overlay" id="mLogout"><div class="modal-container"><div class="modal-content">
    <div class="modal-header error"><h5><i class='bx bx-log-out'></i>Desconectar</h5><button class="modal-close" onclick="fecharModal('mLogout')"><i class='bx bx-x'></i></button></div>
    <div class="modal-body"><div class="nota danger"><small>⚠️ Irá desconectar o WhatsApp. Para reconectar, escaneie o QR novamente.</small></div><p style="text-align:center;">Desconectar <b><?php echo htmlspecialchars($inst_atual); ?></b>?</p><div id="logout-res"></div></div>
    <div class="modal-footer"><button class="btn btn-gray" onclick="fecharModal('mLogout')"><i class='bx bx-x'></i>Cancelar</button><button class="btn btn-danger" id="btn-logout" onclick="executarLogout()"><i class='bx bx-log-out'></i>Desconectar</button></div>
</div></div></div>

<div class="modal-overlay" id="mDeletar"><div class="modal-container"><div class="modal-content">
    <div class="modal-header error"><h5><i class='bx bx-trash'></i>Deletar Instância</h5><button class="modal-close" onclick="fecharModal('mDeletar')"><i class='bx bx-x'></i></button></div>
    <div class="modal-body"><div class="nota danger"><small>🚨 Irreversível! A instância será deletada da API e desvinculada.</small></div><p style="text-align:center;">Deletar <b style="color:#f87171;"><?php echo htmlspecialchars($inst_atual); ?></b>?</p><div id="deletar-res"></div></div>
    <div class="modal-footer"><button class="btn btn-gray" onclick="fecharModal('mDeletar')"><i class='bx bx-x'></i>Cancelar</button><button class="btn btn-danger" id="btn-deletar" onclick="executarDeletar()"><i class='bx bx-trash'></i>Confirmar</button></div>
</div></div></div>

<div class="modal-overlay" id="mEditar"><div class="modal-container wide"><div class="modal-content">
    <div class="modal-header purple"><h5><i class='bx bx-edit-alt'></i>Editar Mensagem</h5><button class="modal-close" onclick="fecharModal('mEditar')"><i class='bx bx-x'></i></button></div>
    <form method="POST"><input type="hidden" name="edit_id" id="edit_id"><div class="modal-body">
        <div class="fg"><label><i class='bx bx-message-square-detail'></i>Mensagem</label><textarea class="fc" name="edit_mensagem" id="edit_mens" rows="6"></textarea></div>
        <div class="grid2">
            <div class="fg"><label><i class='bx bx-calendar-event'></i>Evento</label>
                <select class="fc" name="edit_funcao" id="edit_func" onchange="toggleHoraEdit()">
                    <?php foreach($funcoes_labels as $k=>$fl):?>
                    <option value="<?php echo $k;?>"><?php echo $fl['label'];?></option>
                    <?php endforeach;?>
                </select>
            </div>
            <div class="fg" id="he_w" style="display:none;"><label><i class='bx bx-time-five'></i>Horário</label><input class="fc" type="time" name="edit_hora" id="edit_hora"></div>
            <div class="fg"><label><i class='bx bx-toggle-left'></i>Status</label>
                <select class="fc" name="edit_ativo" id="edit_ativo">
                    <option value="ativada">Ativada</option>
                    <option value="desativado">Desativada</option>
                </select>
            </div>
        </div>
    </div><div class="modal-footer">
        <button type="submit" name="deletar" class="btn btn-danger" onclick="return confirm('Apagar?')"><i class='bx bx-trash'></i>Apagar</button>
        <button type="button" class="btn btn-gray" onclick="fecharModal('mEditar')"><i class='bx bx-x'></i>Cancelar</button>
        <button type="submit" name="editar" class="btn btn-purple"><i class='bx bx-save'></i>Salvar</button>
    </div></form>
</div></div></div>

<div class="modal-overlay" id="mAdd"><div class="modal-container wide"><div class="modal-content">
    <div class="modal-header success"><h5><i class='bx bx-plus'></i>Adicionar Mensagem</h5><button class="modal-close" onclick="fecharModal('mAdd')"><i class='bx bx-x'></i></button></div>
    <form method="POST"><div class="modal-body">
        <div class="fg"><label><i class='bx bx-calendar-event'></i>Evento</label>
            <select class="fc" name="funcao" id="add_func" onchange="preencherTpl();toggleHoraAdd();">
                <?php foreach($funcoes_labels as $k=>$fl):?>
                <option value="<?php echo $k;?>"><?php echo $fl['label'];?></option>
                <?php endforeach;?>
            </select>
        </div>
        <div class="fg" id="ha_w" style="display:none;"><label><i class='bx bx-time-five'></i>Horário</label><input class="fc" type="time" name="add_hora" id="add_hora" value="08:00"></div>
        <div class="fg"><label><i class='bx bx-message-square-detail'></i>Mensagem</label><textarea class="fc" name="mensagem" id="add_mens" rows="6"></textarea></div>
        <div class="fg"><label><i class='bx bx-toggle-left'></i>Status</label>
            <select class="fc" name="ativo">
                <option value="ativada">Ativada</option>
                <option value="desativado">Desativada</option>
            </select>
        </div>
    </div><div class="modal-footer">
        <button type="button" class="btn btn-gray" onclick="fecharModal('mAdd')"><i class='bx bx-x'></i>Cancelar</button>
        <button type="submit" name="adicionar" class="btn btn-success"><i class='bx bx-save'></i>Salvar</button>
    </div></form>
</div></div></div>

<script>
const TPLS = <?php echo json_encode($templates, JSON_UNESCAPED_UNICODE); ?>;
const PHP_URL = '<?php echo basename($_SERVER['PHP_SELF']); ?>';
let _vi = null;

function abrirModal(id){ document.getElementById(id).classList.add('show'); }
function fecharModal(id){ document.getElementById(id).classList.remove('show'); }
document.querySelectorAll('.modal-overlay').forEach(o=>{o.addEventListener('click',function(e){if(e.target===o)o.classList.remove('show');});});
document.addEventListener('keydown',e=>{if(e.key==='Escape')document.querySelectorAll('.modal-overlay.show').forEach(m=>m.classList.remove('show'));});

function toast(msg, tipo) {
    var t = document.createElement('div');
    t.className = 'toast ' + (tipo || 'ok');
    t.innerHTML = '<i class="bx bx-' + (tipo === 'err' ? 'error-circle' : 'check-circle') + '"></i>' + msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3000);
}

function abrirQR(){ abrirModal('mQR'); carregarQR(); }
function carregarQR() {
    document.getElementById('qr-area').innerHTML = '<div class="qm"><span class="spin"></span> Gerando QR...</div>';
    fetch(PHP_URL + '?ajax=qr', { credentials: 'same-origin' })
        .then(r => r.json())
        .then(d => {
            if (d.state === 'open') {
                document.getElementById('qr-area').innerHTML = '<div class="qok"><i class="bx bx-check-circle"></i> Já conectado!</div>';
                setTimeout(() => location.reload(), 1500);
                return;
            }
            var b64 = d.base64 || d.qrcode || null;
            if (b64) {
                document.getElementById('qr-area').innerHTML = '<img src="' + b64 + '" alt="QR"><div class="qm">📱 Escaneie</div>';
                iniciarVerif();
            } else if (d.erro) {
                document.getElementById('qr-area').innerHTML = '<div class="qm" style="color:#f87171;"><i class="bx bx-error-circle"></i> ' + d.erro + '</div>';
            } else {
                document.getElementById('qr-area').innerHTML = '<div class="qm" style="color:#f87171;">QR não disponível.</div>';
            }
        })
        .catch(e => { document.getElementById('qr-area').innerHTML = '<div class="qm" style="color:#f87171;">' + e.message + '</div>'; });
}
function iniciarVerif(){ pararVerif(); _vi = setInterval(verificarStatus, 5000); }
function pararVerif(){ if(_vi){ clearInterval(_vi); _vi = null; } }
function verificarStatus() {
    fetch(PHP_URL + '?ajax=status', { credentials: 'same-origin' })
        .then(r => r.json())
        .then(d => {
            var st = d.state || (d.instance && d.instance.state) || '';
            if (st === 'open') {
                pararVerif();
                document.getElementById('qr-area').innerHTML = '<div class="qok"><i class="bx bx-check-circle"></i> Conectado!<br><small>Recarregando...</small></div>';
                setTimeout(() => location.reload(), 1500);
            }
        });
}

function executarCriar() {
    var nome = document.getElementById('inst_nome').value.trim();
    var res  = document.getElementById('criar-res');
    if (!nome) { res.innerHTML = '<div class="fb err"><i class="bx bx-error-circle"></i> Informe o nome!</div>'; return; }
    res.innerHTML = '<div class="fb info"><span class="spin"></span> Processando...</div>';
    var fd = new FormData(); fd.append('instancia', nome);
    fetch(PHP_URL + '?ajax=criar', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(r => r.json())
        .then(d => {
            if (d.ok) {
                res.innerHTML = '<div class="fb ok"><i class="bx bx-check-circle"></i> ' + d.msg + ' Recarregando...</div>';
                setTimeout(() => location.reload(), 1500);
            } else {
                res.innerHTML = '<div class="fb err"><i class="bx bx-error-circle"></i> ' + (d.erro || JSON.stringify(d)) + '</div>';
            }
        })
        .catch(e => { res.innerHTML = '<div class="fb err">' + e.message + '</div>'; });
}

function confirmarLogout() { abrirModal('mLogout'); }
function executarLogout() {
    var btn = document.getElementById('btn-logout');
    var res = document.getElementById('logout-res');
    btn.disabled = true;
    btn.innerHTML = '<span class="spin"></span> Desconectando...';
    res.innerHTML = '';
    fetch(PHP_URL + '?ajax=logout', { method: 'POST', credentials: 'same-origin' })
        .then(() => { toast('Desconectado!', 'ok'); setTimeout(() => location.reload(), 1200); })
        .catch(e => { res.innerHTML = '<div class="fb err">' + e.message + '</div>'; btn.disabled = false; btn.innerHTML = '<i class="bx bx-log-out"></i> Desconectar'; });
}

function confirmarDeletar() { abrirModal('mDeletar'); }
function executarDeletar() {
    var btn = document.getElementById('btn-deletar');
    var res = document.getElementById('deletar-res');
    btn.disabled = true;
    btn.innerHTML = '<span class="spin"></span> Deletando...';
    res.innerHTML = '';
    fetch(PHP_URL + '?ajax=deletar_inst', { method: 'POST', credentials: 'same-origin' })
        .then(r => r.json())
        .then(d => {
            if (d.ok) {
                res.innerHTML = '<div class="fb ok"><i class="bx bx-check-circle"></i> ' + d.msg + '</div>';
                setTimeout(() => location.reload(), 1500);
            } else {
                res.innerHTML = '<div class="fb err"><i class="bx bx-error-circle"></i> ' + d.msg + '</div>';
                btn.disabled = false;
                btn.innerHTML = '<i class="bx bx-trash"></i> Confirmar';
            }
        })
        .catch(e => { res.innerHTML = '<div class="fb err">' + e.message + '</div>'; btn.disabled = false; btn.innerHTML = '<i class="bx bx-trash"></i> Confirmar'; });
}

function executarTeste() {
    var num = document.getElementById('test_num').value.trim();
    var txt = document.getElementById('test_msg').value;
    var res = document.getElementById('test-res');
    if (!num) { res.innerHTML = '<div class="fb err"><i class="bx bx-error-circle"></i> Informe o número!</div>'; return; }
    res.innerHTML = '<div class="fb info"><span class="spin"></span> Enviando...</div>';
    var fd = new FormData(); fd.append('numero', num); fd.append('texto', txt);
    fetch(PHP_URL + '?ajax=testar', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(r => r.json())
        .then(d => {
            if (d.ok) res.innerHTML = '<div class="fb ok"><i class="bx bx-check-circle"></i> ' + d.msg + '</div>';
            else res.innerHTML = '<div class="fb err"><i class="bx bx-error-circle"></i> ' + d.msg + '</div>';
        })
        .catch(e => { res.innerHTML = '<div class="fb err">' + e.message + '</div>'; });
}

function editarMsg(id) {
    fetch(PHP_URL + '?ajax=get_msg&id=' + id, { credentials: 'same-origin' })
        .then(r => r.json())
        .then(d => {
            if (!d.id) return;
            document.getElementById('edit_id').value = d.id;
            document.getElementById('edit_mens').value = d.mensagem;
            document.getElementById('edit_func').value = d.funcao;
            document.getElementById('edit_ativo').value = d.ativo;
            document.getElementById('edit_hora').value = d.hora || '08:00';
            toggleHoraEdit();
            abrirModal('mEditar');
        });
}

function preencherTpl(){ var f = document.getElementById('add_func').value; if(TPLS[f]) document.getElementById('add_mens').value = TPLS[f]; }
function toggleHoraAdd(){ var f = document.getElementById('add_func').value; document.getElementById('ha_w').style.display = (f === 'contaexpirada' || f === 'revendaexpirada') ? 'block' : 'none'; }
function toggleHoraEdit(){ var f = document.getElementById('edit_func').value; document.getElementById('he_w').style.display = (f === 'contaexpirada' || f === 'revendaexpirada') ? 'block' : 'none'; }

function copiarVar(v) {
    navigator.clipboard.writeText(v).then(function() {
        var el = document.getElementById('copy-ok'); el.style.display = 'flex';
        clearTimeout(window._ct); window._ct = setTimeout(function() { el.style.display = 'none'; }, 2000);
    });
}

window.addEventListener('load', function() { preencherTpl(); toggleHoraAdd(); });
</script>
</body>
</html>
h2_tema ?? ['classe'=>'theme-dark'])); ?>">
<div class="app-content content">
<div class="content-overlay"></div>
<div class="content-wrapper">
<div class="content-body">

<!-- Stats Card -->
<div class="stats-card">
    <div class="stats-card-icon"><i class='bx bxl-whatsapp'></i></div>
    <div class="stats-card-content">
        <div class="stats-card-title">WhatsApp Evolution API</div>
        <div class="stats-card-value"><?php echo $total_mens; ?></div>
        <div class="stats-card-subtitle">mensagem(ns) configurada(s)</div>
    </div>
    <div class="stats-card-decoration"><i class='bx bxl-whatsapp'></i></div>
</div>

<?php if ($msg): ?>
<div class="fb <?php echo $tipo; ?>"><i class='bx bx-<?php echo $tipo==='ok'?'check-circle':'error-circle'; ?>'></i><?php echo htmlspecialchars($msg); ?></div>
<?php endif; ?>

<!-- CARD: API -->
<div class="modern-card">
    <div class="card-header api">
        <div class="header-icon"><i class='bx bx-server'></i></div>
        <div><div class="header-title">Servidor Evolution API</div><div class="header-subtitle">Configurado pelo administrador</div></div>
        <div class="header-right">
            <span class="sb <?php echo $api_ok?'ok':'warn'; ?>">
                <i class='bx bx-<?php echo $api_ok?'check-circle':'time'; ?>'></i>
                <?php echo $api_ok?'Disponível':'Aguardando'; ?>
            </span>
        </div>
    </div>
    <div class="card-body">
        <?php if ($api_ok): ?>
        <div class="fb ok" style="margin:0;"><i class='bx bx-shield-check'></i>API configurada pelo administrador. Pronta para uso.</div>
        <?php else: ?>
        <div class="fb err" style="margin:0;"><i class='bx bx-error-circle'></i>API ainda não configurada. Entre em contato com o administrador.</div>
        <?php endif; ?>
    </div>
</div>

<!-- CARD: INSTÂNCIA -->
<div class="modern-card" <?php echo !$api_ok?'style="opacity:.5;pointer-events:none;"':''; ?>>
    <div class="card-header wpp">
        <div class="header-icon"><i class='bx bxl-whatsapp'></i></div>
        <div><div class="header-title">Minha Instância</div><div class="header-subtitle">Conexão pessoal</div></div>
        <div class="header-right">
            <?php if ($wpp_online): ?>
            <span class="sb ok"><span class="pulse-dot on"></span>Online</span>
            <?php elseif ($inst_ok): ?>
            <span class="sb off"><span class="pulse-dot off"></span>Offline</span>
            <?php endif; ?>
            <button class="btn btn-wpp btn-sm" onclick="abrirModal('mInst')">
                <i class='bx bx-plus'></i><?php echo $inst_ok?'Editar':'Configurar'; ?>
            </button>
        </div>
    </div>
    <div class="card-body">
        <?php if ($inst_ok): ?>
        <div class="inst-box <?php echo $wpp_online?'on':'off'; ?>">
            <div class="inst-top">
                <div class="inst-name">
                    <i class='bx bxl-whatsapp'></i>
                    <?php echo htmlspecialchars($inst_atual); ?>
                </div>
                <span class="sb <?php echo $wpp_online?'ok':'off'; ?>">
                    <span class="pulse-dot <?php echo $wpp_online?'on':'off'; ?>"></span>
                    <?php echo $wpp_online?'Online':'Offline'; ?>
                </span>
            </div>
            <div class="inst-detail">
                <i class='bx bx-phone'></i>
                <?php echo $numero_wpp?htmlspecialchars($numero_wpp):'Número não cadastrado'; ?>
            </div>
            <div class="inst-actions">
                <button class="btn btn-wpp" onclick="abrirQR()">
                    <i class='bx bx-<?php echo $wpp_online?'refresh':'plug'; ?>'></i>
                    <?php echo $wpp_online?'Reconectar':'Conectar'; ?>
                </button>
                <button class="btn btn-warning" onclick="abrirModal('mTestar')">
                    <i class='bx bx-send'></i>Testar
                </button>
                <button class="btn btn-danger" onclick="confirmarLogout()">
                    <i class='bx bx-log-out'></i>Desconectar
                </button>
                <button class="btn btn-danger" onclick="confirmarDeletar()">
                    <i class='bx bx-trash'></i>Excluir
                </button>
            </div>
        </div>
        <?php else: ?>
        <div class="te">
            <i class='bx bxl-whatsapp' style="font-size:42px;display:block;margin-bottom:10px;opacity:.2;"></i>
            Nenhuma instância configurada.<br>
            <button class="btn btn-wpp" style="margin-top:12px;" onclick="abrirModal('mInst')">
                <i class='bx bx-plus'></i>Configurar agora
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- CARD: MENSAGENS -->
<div class="modern-card" <?php echo !$inst_ok?'style="opacity:.5;pointer-events:none;"':''; ?>>
    <div class="card-header msg">
        <div class="header-icon"><i class='bx bx-message-detail'></i></div>
        <div><div class="header-title">Mensagens</div><div class="header-subtitle">Disparadas nos eventos</div></div>
        <div class="header-right">
            <button class="btn btn-purple btn-sm" onclick="abrirModal('mAdd')">
                <i class='bx bx-plus'></i>Adicionar
            </button>
        </div>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="tbl">
            <thead>
                <tr>
                    <th>Mensagem</th>
                    <th>Evento</th>
                    <th>Horário</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($res_mens && $res_mens->num_rows > 0):
                $res_mens->data_seek(0);
                while ($rm = $res_mens->fetch_assoc()):
                    $fl = $funcoes_labels[$rm['funcao']] ?? ['label'=>$rm['funcao'],'icon'=>'bx-bell','cor'=>'#94a3b8'];
                    $hv = in_array($rm['funcao'],['contaexpirada','revendaexpirada']);
            ?>
                <tr>
                    <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo htmlspecialchars(mb_substr($rm['mensagem'],0,45)).(mb_strlen($rm['mensagem'])>45?'…':''); ?></td>
                    <td><span class="chip"><i class='bx <?php echo $fl['icon']; ?>' style="color:<?php echo $fl['cor']; ?>"></i><?php echo $fl['label']; ?></span></td>
                    <td><?php echo $hv?($rm['hora']??'08:00'):'—'; ?></td>
                    <td><span class="sb <?php echo $rm['ativo']==='ativada'?'ok':'off'; ?>"><?php echo $rm['ativo']==='ativada'?'Ativa':'Inativa'; ?></span></td>
                    <td><button class="btn btn-gray btn-sm" onclick="editarMsg(<?php echo $rm['id']; ?>)"><i class='bx bx-edit-alt'></i></button></td>
                </tr>
            <?php endwhile; else: ?>
                <tr><td colspan="5" class="te"><i class='bx bx-message-x' style="font-size:28px;"></i><br>Nenhuma mensagem</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- CARD: VARIÁVEIS -->
<div class="modern-card">
    <div class="card-header var">
        <div class="header-icon"><i class='bx bx-code-curly'></i></div>
        <div><div class="header-title">Variáveis</div><div class="header-subtitle">Clique para copiar</div></div>
    </div>
    <div class="card-body">
        <div class="varb">
            <?php foreach(['{usuario}'=>'Login','{senha}'=>'Senha','{validade}'=>'Validade','{limite}'=>'Limite','{dominio}'=>'Domínio'] as $v=>$d): ?>
            <div class="vari" onclick="copiarVar('<?php echo $v; ?>')"><code><?php echo $v; ?></code><span><?php echo $d; ?></span></div>
            <?php endforeach; ?>
        </div>
        <div id="copy-ok" class="fb ok" style="display:none;margin-top:8px;"><i class='bx bx-check-circle'></i>Copiado!</div>
    </div>
</div>

</div></div></div>

<!-- MODAIS -->
<div class="modal-overlay" id="mInst"><div class="modal-container"><div class="modal-content">
    <div class="modal-header success"><h5><i class='bx bxl-whatsapp'></i>Instância WhatsApp</h5><button class="modal-close" onclick="fecharModal('mInst')"><i class='bx bx-x'></i></button></div>
    <form method="POST"><div class="modal-body">
        <div class="nota warn"><small>⚠️ Crie apenas uma vez. Se já existir, será vinculada.</small></div>
        <div class="fg"><label><i class='bx bx-cube'></i> Nome</label><input class="fc" type="text" name="instancia" id="inst_nome" value="<?php echo htmlspecialchars($inst_atual); ?>" placeholder="ex: minha_revenda_01"></div>
        <div class="fg"><label><i class='bx bx-phone'></i> Número WhatsApp</label><input class="fc" type="text" name="numero" value="<?php echo htmlspecialchars($numero_wpp); ?>" placeholder="5511999999999"></div>
        <div id="criar-res"></div>
    </div><div class="modal-footer">
        <button type="button" class="btn btn-gray" onclick="fecharModal('mInst')"><i class='bx bx-x'></i>Cancelar</button>
        <button type="button" class="btn btn-primary" onclick="executarCriar()"><i class='bx bx-play'></i>Criar</button>
        <button type="submit" name="salvar_inst" class="btn btn-success"><i class='bx bx-save'></i>Salvar</button>
    </div></form>
</div></div></div>

<div class="modal-overlay" id="mQR"><div class="modal-container"><div class="modal-content">
    <div class="modal-header success"><h5><i class='bx bx-qr-scan'></i>Conectar</h5><button class="modal-close" onclick="fecharModal('mQR');pararVerif();"><i class='bx bx-x'></i></button></div>
    <div class="modal-body"><div id="qr-area"><div class="qm"><span class="spin"></span> Gerando QR...</div></div><div class="nota"><small>📱 WhatsApp → Configurações → Dispositivos Vinculados</small></div></div>
    <div class="modal-footer"><button class="btn btn-gray" onclick="fecharModal('mQR');pararVerif();"><i class='bx bx-x'></i>Fechar</button><button class="btn btn-primary" onclick="carregarQR()"><i class='bx bx-refresh'></i>Novo QR</button><button class="btn btn-success" onclick="verificarStatus()"><i class='bx bx-check-circle'></i>Verificar</button></div>
</div></div></div>

<div class="modal-overlay" id="mTestar"><div class="modal-container"><div class="modal-content">
    <div class="modal-header warning"><h5><i class='bx bx-send'></i>Testar Envio</h5><button class="modal-close" onclick="fecharModal('mTestar')"><i class='bx bx-x'></i></button></div>
    <div class="modal-body"><div class="fg"><label><i class='bx bx-phone'></i>Número</label><input class="fc" type="text" id="test_num" value="<?php echo htmlspecialchars($numero_wpp); ?>" placeholder="5511999999999"></div><div class="fg"><label><i class='bx bx-message-square-detail'></i>Mensagem</label><textarea class="fc" id="test_msg" rows="4">✅ Teste WhatsApp! Conexão funcionando! 🚀</textarea></div><div id="test-res"></div></div>
    <div class="modal-footer"><button class="btn btn-gray" onclick="fecharModal('mTestar')"><i class='bx bx-x'></i>Fechar</button><button class="btn btn-warning" onclick="executarTeste()"><i class='bx bx-send'></i>Enviar</button></div>
</div></div></div>

<div class="modal-overlay" id="mLogout"><div class="modal-container"><div class="modal-content">
    <div class="modal-header error"><h5><i class='bx bx-log-out'></i>Desconectar</h5><button class="modal-close" onclick="fecharModal('mLogout')"><i class='bx bx-x'></i></button></div>
    <div class="modal-body"><div class="nota danger"><small>⚠️ Irá desconectar o WhatsApp. Para reconectar, escaneie o QR novamente.</small></div><p style="text-align:center;">Desconectar <b><?php echo htmlspecialchars($inst_atual); ?></b>?</p><div id="logout-res"></div></div>
    <div class="modal-footer"><button class="btn btn-gray" onclick="fecharModal('mLogout')"><i class='bx bx-x'></i>Cancelar</button><button class="btn btn-danger" id="btn-logout" onclick="executarLogout()"><i class='bx bx-log-out'></i>Desconectar</button></div>
</div></div></div>

<div class="modal-overlay" id="mDeletar"><div class="modal-container"><div class="modal-content">
    <div class="modal-header error"><h5><i class='bx bx-trash'></i>Deletar Instância</h5><button class="modal-close" onclick="fecharModal('mDeletar')"><i class='bx bx-x'></i></button></div>
    <div class="modal-body"><div class="nota danger"><small>🚨 Irreversível! A instância será deletada da API e desvinculada.</small></div><p style="text-align:center;">Deletar <b style="color:#f87171;"><?php echo htmlspecialchars($inst_atual); ?></b>?</p><div id="deletar-res"></div></div>
    <div class="modal-footer"><button class="btn btn-gray" onclick="fecharModal('mDeletar')"><i class='bx bx-x'></i>Cancelar</button><button class="btn btn-danger" id="btn-deletar" onclick="executarDeletar()"><i class='bx bx-trash'></i>Confirmar</button></div>
</div></div></div>

<div class="modal-overlay" id="mEditar"><div class="modal-container wide"><div class="modal-content">
    <div class="modal-header purple"><h5><i class='bx bx-edit-alt'></i>Editar Mensagem</h5><button class="modal-close" onclick="fecharModal('mEditar')"><i class='bx bx-x'></i></button></div>
    <form method="POST"><input type="hidden" name="edit_id" id="edit_id"><div class="modal-body">
        <div class="fg"><label><i class='bx bx-message-square-detail'></i>Mensagem</label><textarea class="fc" name="edit_mensagem" id="edit_mens" rows="6"></textarea></div>
        <div class="grid2">
            <div class="fg"><label><i class='bx bx-calendar-event'></i>Evento</label>
                <select class="fc" name="edit_funcao" id="edit_func" onchange="toggleHoraEdit()">
                    <?php foreach($funcoes_labels as $k=>$fl):?>
                    <option value="<?php echo $k;?>"><?php echo $fl['label'];?></option>
                    <?php endforeach;?>
                </select>
            </div>
            <div class="fg" id="he_w" style="display:none;"><label><i class='bx bx-time-five'></i>Horário</label><input class="fc" type="time" name="edit_hora" id="edit_hora"></div>
            <div class="fg"><label><i class='bx bx-toggle-left'></i>Status</label>
                <select class="fc" name="edit_ativo" id="edit_ativo">
                    <option value="ativada">Ativada</option>
                    <option value="desativado">Desativada</option>
                </select>
            </div>
        </div>
    </div><div class="modal-footer">
        <button type="submit" name="deletar" class="btn btn-danger" onclick="return confirm('Apagar?')"><i class='bx bx-trash'></i>Apagar</button>
        <button type="button" class="btn btn-gray" onclick="fecharModal('mEditar')"><i class='bx bx-x'></i>Cancelar</button>
        <button type="submit" name="editar" class="btn btn-purple"><i class='bx bx-save'></i>Salvar</button>
    </div></form>
</div></div></div>

<div class="modal-overlay" id="mAdd"><div class="modal-container wide"><div class="modal-content">
    <div class="modal-header success"><h5><i class='bx bx-plus'></i>Adicionar Mensagem</h5><button class="modal-close" onclick="fecharModal('mAdd')"><i class='bx bx-x'></i></button></div>
    <form method="POST"><div class="modal-body">
        <div class="fg"><label><i class='bx bx-calendar-event'></i>Evento</label>
            <select class="fc" name="funcao" id="add_func" onchange="preencherTpl();toggleHoraAdd();">
                <?php foreach($funcoes_labels as $k=>$fl):?>
                <option value="<?php echo $k;?>"><?php echo $fl['label'];?></option>
                <?php endforeach;?>
            </select>
        </div>
        <div class="fg" id="ha_w" style="display:none;"><label><i class='bx bx-time-five'></i>Horário</label><input class="fc" type="time" name="add_hora" id="add_hora" value="08:00"></div>
        <div class="fg"><label><i class='bx bx-message-square-detail'></i>Mensagem</label><textarea class="fc" name="mensagem" id="add_mens" rows="6"></textarea></div>
        <div class="fg"><label><i class='bx bx-toggle-left'></i>Status</label>
            <select class="fc" name="ativo">
                <option value="ativada">Ativada</option>
                <option value="desativado">Desativada</option>
            </select>
        </div>
    </div><div class="modal-footer">
        <button type="button" class="btn btn-gray" onclick="fecharModal('mAdd')"><i class='bx bx-x'></i>Cancelar</button>
        <button type="submit" name="adicionar" class="btn btn-success"><i class='bx bx-save'></i>Salvar</button>
    </div></form>
</div></div></div>

<script>
const TPLS = <?php echo json_encode($templates, JSON_UNESCAPED_UNICODE); ?>;
const PHP_URL = '<?php echo basename($_SERVER['PHP_SELF']); ?>';
let _vi = null;

function abrirModal(id){ document.getElementById(id).classList.add('show'); }
function fecharModal(id){ document.getElementById(id).classList.remove('show'); }
document.querySelectorAll('.modal-overlay').forEach(o=>{o.addEventListener('click',function(e){if(e.target===o)o.classList.remove('show');});});
document.addEventListener('keydown',e=>{if(e.key==='Escape')document.querySelectorAll('.modal-overlay.show').forEach(m=>m.classList.remove('show'));});

function toast(msg, tipo) {
    var t = document.createElement('div');
    t.className = 'toast ' + (tipo || 'ok');
    t.innerHTML = '<i class="bx bx-' + (tipo === 'err' ? 'error-circle' : 'check-circle') + '"></i>' + msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3000);
}

function abrirQR(){ abrirModal('mQR'); carregarQR(); }
function carregarQR() {
    document.getElementById('qr-area').innerHTML = '<div class="qm"><span class="spin"></span> Gerando QR...</div>';
    fetch(PHP_URL + '?ajax=qr', { credentials: 'same-origin' })
        .then(r => r.json())
        .then(d => {
            if (d.state === 'open') {
                document.getElementById('qr-area').innerHTML = '<div class="qok"><i class="bx bx-check-circle"></i> Já conectado!</div>';
                setTimeout(() => location.reload(), 1500);
                return;
            }
            var b64 = d.base64 || d.qrcode || null;
            if (b64) {
                document.getElementById('qr-area').innerHTML = '<img src="' + b64 + '" alt="QR"><div class="qm">📱 Escaneie</div>';
                iniciarVerif();
            } else if (d.erro) {
                document.getElementById('qr-area').innerHTML = '<div class="qm" style="color:#f87171;"><i class="bx bx-error-circle"></i> ' + d.erro + '</div>';
            } else {
                document.getElementById('qr-area').innerHTML = '<div class="qm" style="color:#f87171;">QR não disponível.</div>';
            }
        })
        .catch(e => { document.getElementById('qr-area').innerHTML = '<div class="qm" style="color:#f87171;">' + e.message + '</div>'; });
}
function iniciarVerif(){ pararVerif(); _vi = setInterval(verificarStatus, 5000); }
function pararVerif(){ if(_vi){ clearInterval(_vi); _vi = null; } }
function verificarStatus() {
    fetch(PHP_URL + '?ajax=status', { credentials: 'same-origin' })
        .then(r => r.json())
        .then(d => {
            var st = d.state || (d.instance && d.instance.state) || '';
            if (st === 'open') {
                pararVerif();
                document.getElementById('qr-area').innerHTML = '<div class="qok"><i class="bx bx-check-circle"></i> Conectado!<br><small>Recarregando...</small></div>';
                setTimeout(() => location.reload(), 1500);
            }
        });
}

function executarCriar() {
    var nome = document.getElementById('inst_nome').value.trim();
    var res  = document.getElementById('criar-res');
    if (!nome) { res.innerHTML = '<div class="fb err"><i class="bx bx-error-circle"></i> Informe o nome!</div>'; return; }
    res.innerHTML = '<div class="fb info"><span class="spin"></span> Processando...</div>';
    var fd = new FormData(); fd.append('instancia', nome);
    fetch(PHP_URL + '?ajax=criar', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(r => r.json())
        .then(d => {
            if (d.ok) {
                res.innerHTML = '<div class="fb ok"><i class="bx bx-check-circle"></i> ' + d.msg + ' Recarregando...</div>';
                setTimeout(() => location.reload(), 1500);
            } else {
                res.innerHTML = '<div class="fb err"><i class="bx bx-error-circle"></i> ' + (d.erro || JSON.stringify(d)) + '</div>';
            }
        })
        .catch(e => { res.innerHTML = '<div class="fb err">' + e.message + '</div>'; });
}

function confirmarLogout() { abrirModal('mLogout'); }
function executarLogout() {
    var btn = document.getElementById('btn-logout');
    var res = document.getElementById('logout-res');
    btn.disabled = true;
    btn.innerHTML = '<span class="spin"></span> Desconectando...';
    res.innerHTML = '';
    fetch(PHP_URL + '?ajax=logout', { method: 'POST', credentials: 'same-origin' })
        .then(() => { toast('Desconectado!', 'ok'); setTimeout(() => location.reload(), 1200); })
        .catch(e => { res.innerHTML = '<div class="fb err">' + e.message + '</div>'; btn.disabled = false; btn.innerHTML = '<i class="bx bx-log-out"></i> Desconectar'; });
}

function confirmarDeletar() { abrirModal('mDeletar'); }
function executarDeletar() {
    var btn = document.getElementById('btn-deletar');
    var res = document.getElementById('deletar-res');
    btn.disabled = true;
    btn.innerHTML = '<span class="spin"></span> Deletando...';
    res.innerHTML = '';
    fetch(PHP_URL + '?ajax=deletar_inst', { method: 'POST', credentials: 'same-origin' })
        .then(r => r.json())
        .then(d => {
            if (d.ok) {
                res.innerHTML = '<div class="fb ok"><i class="bx bx-check-circle"></i> ' + d.msg + '</div>';
                setTimeout(() => location.reload(), 1500);
            } else {
                res.innerHTML = '<div class="fb err"><i class="bx bx-error-circle"></i> ' + d.msg + '</div>';
                btn.disabled = false;
                btn.innerHTML = '<i class="bx bx-trash"></i> Confirmar';
            }
        })
        .catch(e => { res.innerHTML = '<div class="fb err">' + e.message + '</div>'; btn.disabled = false; btn.innerHTML = '<i class="bx bx-trash"></i> Confirmar'; });
}

function executarTeste() {
    var num = document.getElementById('test_num').value.trim();
    var txt = document.getElementById('test_msg').value;
    var res = document.getElementById('test-res');
    if (!num) { res.innerHTML = '<div class="fb err"><i class="bx bx-error-circle"></i> Informe o número!</div>'; return; }
    res.innerHTML = '<div class="fb info"><span class="spin"></span> Enviando...</div>';
    var fd = new FormData(); fd.append('numero', num); fd.append('texto', txt);
    fetch(PHP_URL + '?ajax=testar', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(r => r.json())
        .then(d => {
            if (d.ok) res.innerHTML = '<div class="fb ok"><i class="bx bx-check-circle"></i> ' + d.msg + '</div>';
            else res.innerHTML = '<div class="fb err"><i class="bx bx-error-circle"></i> ' + d.msg + '</div>';
        })
        .catch(e => { res.innerHTML = '<div class="fb err">' + e.message + '</div>'; });
}

function editarMsg(id) {
    fetch(PHP_URL + '?ajax=get_msg&id=' + id, { credentials: 'same-origin' })
        .then(r => r.json())
        .then(d => {
            if (!d.id) return;
            document.getElementById('edit_id').value = d.id;
            document.getElementById('edit_mens').value = d.mensagem;
            document.getElementById('edit_func').value = d.funcao;
            document.getElementById('edit_ativo').value = d.ativo;
            document.getElementById('edit_hora').value = d.hora || '08:00';
            toggleHoraEdit();
            abrirModal('mEditar');
        });
}

function preencherTpl(){ var f = document.getElementById('add_func').value; if(TPLS[f]) document.getElementById('add_mens').value = TPLS[f]; }
function toggleHoraAdd(){ var f = document.getElementById('add_func').value; document.getElementById('ha_w').style.display = (f === 'contaexpirada' || f === 'revendaexpirada') ? 'block' : 'none'; }
function toggleHoraEdit(){ var f = document.getElementById('edit_func').value; document.getElementById('he_w').style.display = (f === 'contaexpirada' || f === 'revendaexpirada') ? 'block' : 'none'; }

function copiarVar(v) {
    navigator.clipboard.writeText(v).then(function() {
        var el = document.getElementById('copy-ok'); el.style.display = 'flex';
        clearTimeout(window._ct); window._ct = setTimeout(function() { el.style.display = 'none'; }, 2000);
    });
}

window.addEventListener('load', function() { preencherTpl(); toggleHoraAdd(); });
</script>
</body>
</html>


