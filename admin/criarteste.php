<?php
session_start();
error_reporting(0);
include('../AegisCore/conexao.php');

set_time_limit(0);
ignore_user_abort(true);

$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) die("Connection failed: " . mysqli_connect_error());

// ========== SISTEMA DE TEMAS ==========
if (file_exists('../AegisCore/temas.php')) {
    include_once '../AegisCore/temas.php';
    $temaAtual = initTemas($conn);
    $listaTemas = getListaTemas($conn);
} else {
    $temaAtual = [];
    $listaTemas = [];
}

date_default_timezone_set('America/Sao_Paulo');
$datahoje = date('d-m-Y H:i:s');

function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff),
        mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000,
        mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff));
}

function getServidorToken($conn, $servidor_id) {
    $sql_token = "SELECT token FROM servidor_tokens WHERE servidor_id = '$servidor_id' AND status = 'ativo' ORDER BY id DESC LIMIT 1";
    $res_token = mysqli_query($conn, $sql_token);
    if ($res_token && mysqli_num_rows($res_token) > 0) {
        $row_token = mysqli_fetch_assoc($res_token);
        return $row_token['token'];
    }
    return md5($_SESSION['token'] ?? '');
}

function anti_sql($input) {
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function($m){ return ''; }, $input);
    return addslashes(strip_tags(trim($seg)));
}

function enviarWhatsAppEvolution($conn, $byid, $numero, $texto) {
    $byid  = intval($byid);
    $r_wpp = mysqli_query($conn, "SELECT * FROM whatsapp WHERE byid='$byid' LIMIT 1");
    if (!$r_wpp || mysqli_num_rows($r_wpp) == 0) return false;
    $wpp      = mysqli_fetch_assoc($r_wpp);
    $api_base = rtrim($wpp['apiurl'] ?? '', '/');
    $tok      = trim($wpp['token']   ?? '');
    $inst     = trim($wpp['sessao']  ?? '');
    if (empty($api_base) || empty($tok) || empty($inst)) return false;
    if (!preg_match('#^https?://#i', $api_base)) $api_base = 'http://' . $api_base;
    $num = preg_replace('/\D/', '', $numero);
    if (strlen($num) <= 11 && substr($num, 0, 2) !== '55') $num = '55' . $num;
    $url = $api_base . '/message/sendText/' . urlencode($inst);
    $payloads = [
        json_encode(['number' => $num, 'textMessage' => ['text' => $texto]]),
        json_encode(['number' => $num, 'text' => $texto, 'options' => ['delay' => 1200]]),
        json_encode(['number' => $num.'@s.whatsapp.net', 'textMessage' => ['text' => $texto]]),
    ];
    foreach ($payloads as $payload) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['apikey: '.$tok, 'Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        $body  = curl_exec($ch);
        $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);
        curl_close($ch);
        if ($errno === 0 && $code >= 200 && $code < 300) return true;
    }
    return false;
}

// ══════════════════════════════════════════════════════════════
// AJAX WhatsApp
// ══════════════════════════════════════════════════════════════
if (isset($_GET['ajax']) && $_GET['ajax'] === 'enviar_wpp') {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache');
    $byid    = intval($_SESSION['iduser'] ?? 0);
    $num_raw = trim($_POST['numero'] ?? '');
    $texto   = trim($_POST['texto']  ?? '');
    if (!$byid)                         { echo json_encode(['ok'=>false,'msg'=>'Sessão expirada.']); exit; }
    if (empty($num_raw)||empty($texto)) { echo json_encode(['ok'=>false,'msg'=>'Dados inválidos.']); exit; }
    $ok  = enviarWhatsAppEvolution($conn, $byid, $num_raw, $texto);
    $num = preg_replace('/\D/', '', $num_raw);
    echo json_encode(['ok'=>$ok,'msg'=>$ok?'Mensagem enviada para '.$num.'!':'Falha ao enviar.','numero'=>$num]);
    exit;
}

// ══════════════════════════════════════════════════════════════
// Variáveis de controle
// ══════════════════════════════════════════════════════════════
$show_modal             = false;
$show_error_modal       = false;
$error_message          = '';
$sucess_servers         = [];
$failed_servers         = [];
$modal_usuario          = '';
$modal_senha            = '';
$modal_expira           = date('Y-m-d H:i:s');
$modal_minutos          = '';
$modal_limite           = '';
$modal_uuid             = '';
$modal_v2ray            = '';
$modal_valormensal      = '0';
$modal_whatsapp_destino = '';
$mensagem_final         = '';
$wpp_enviado            = false;

// ══════════════════════════════════════════════════════════════
// POST — Criar teste
// ══════════════════════════════════════════════════════════════
if (isset($_POST['criaruser'])) {
    $usuariofin       = anti_sql($_POST['usuariofin']  ?? '');
    $senhafin         = anti_sql($_POST['senhafin']    ?? '');
    $validadefin      = anti_sql($_POST['validadefin'] ?? '60');
    $limitefin        = anti_sql($_POST['limitefin']   ?? '1');
    // ✅ CORREÇÃO: categoria NÃO passa pelo anti_sql
    $categoria        = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['categoria'] ?? '');
    $notas            = anti_sql($_POST['notas']       ?? '');
    $valormensal      = anti_sql($_POST['valormensal'] ?? '0');
    $whatsapp_destino = preg_replace('/\D/', '', $_POST['whatsapp'] ?? '');

    // Validações
    if      ($usuariofin == '')                         $error_message = 'Usuário não pode ser vazio!';
    elseif  ($senhafin == '')                           $error_message = 'Senha não pode ser vazia!';
    elseif  (preg_match('/[^a-z0-9]/i', $usuariofin)) $error_message = 'Usuário não pode conter caracteres especiais!';
    elseif  (preg_match('/[^a-z0-9]/i', $senhafin))   $error_message = 'Senha não pode conter caracteres especiais!';
    elseif  (empty($categoria))                        $error_message = 'Selecione uma categoria válida!';
    elseif  (intval($validadefin) < 1)                 $error_message = 'Informe os minutos do teste!';

    if (!$error_message) {
        $chk = mysqli_query($conn, "SELECT id FROM ssh_accounts WHERE login='$usuariofin'");
        if ($chk && mysqli_num_rows($chk) > 0) $error_message = 'Usuário já existe!';
    }

    if ($error_message) {
        $show_error_modal = true;
    } else {
        $v2ray         = (($_POST['v2ray'] ?? 'nao') === 'sim') ? 'sim' : 'nao';
        $formattedUuid = ($v2ray === 'sim') ? generateUUID() : '';

        // ✅ CORREÇÃO: usa $res_srv dedicado
        $res_srv = mysqli_query($conn, "SELECT * FROM servidores WHERE subid='$categoria'");
        $rows    = mysqli_fetch_all($res_srv, MYSQLI_ASSOC);

        $sucess         = false;
        $sucess_servers = [];
        $failed_servers = [];

        foreach ($rows as $user_data) {
            $socket = @fsockopen($user_data['ip'], 6969, $errno, $errstr, 3);

            if ($socket) {
                fclose($socket);

                $senha_token = getServidorToken($conn, $user_data['id']);

                if ($v2ray === 'sim') {
                    $comando = "sudo /etc/xis/addteste.sh $formattedUuid $usuariofin $senhafin $validadefin $limitefin";
                } else {
                    $comando = "sudo /etc/xis/atlasteste.sh $usuariofin $senhafin $validadefin $limitefin";
                }

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL,            $user_data['ip'] . ':6969');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER,     ['Senha: ' . $senha_token]);
                curl_setopt($ch, CURLOPT_POST,           1);
                curl_setopt($ch, CURLOPT_TIMEOUT,        30);
                curl_setopt($ch, CURLOPT_POSTFIELDS,     "comando=$comando");
                curl_exec($ch);
                curl_close($ch);

                $sucess_servers[] = $user_data['nome'];
                $sucess           = true;

            } else {
                $failed_servers[] = $user_data['nome'] . ' (porta 6969 fechada)';
            }
        }

        if (!$sucess) {
            $error_message    = 'Erro ao criar teste! Nenhum servidor disponível.' . (!empty($failed_servers) ? ' Offline: ' . implode(', ', $failed_servers) : '');
            $show_error_modal = true;
        } else {
            // Calcular expiração em minutos
            $expira = date('Y-m-d H:i:s', strtotime("+$validadefin minutes"));

            mysqli_query($conn, "INSERT INTO logs (revenda, byid, validade, texto, userid)
                VALUES ('{$_SESSION['login']}', '{$_SESSION['byid']}', '$datahoje',
                'Criou um Teste $usuariofin de $validadefin Minutos', '{$_SESSION['iduser']}')");

            mysqli_query($conn, "INSERT INTO ssh_accounts
                (login, senha, expira, limite, byid, categoriaid, lastview, bycredit, mainid, status, whatsapp, valormensal, uuid)
                VALUES ('$usuariofin','$senhafin','$expira','$limitefin','{$_SESSION['iduser']}',
                '$categoria','$notas','0','NULL','Offline','$whatsapp_destino','$valormensal','$formattedUuid')");

            // ✅ ENVIO WHATSAPP AUTOMÁTICO
            if (!empty($whatsapp_destino)) {
                $sql_msg = "SELECT mensagem FROM mensagens WHERE funcao='criarteste' AND (byid='{$_SESSION['iduser']}' OR byid='') AND ativo='ativada' ORDER BY byid DESC LIMIT 1";
                $r_msg   = mysqli_query($conn, $sql_msg);
                if ($r_msg && mysqli_num_rows($r_msg) > 0) {
                    $msg_template = mysqli_fetch_assoc($r_msg)['mensagem'];
                } else {
                    $min = intval($validadefin);
                    $dur = $min >= 60 ? floor($min/60).'h'.($min%60>0?' '.($min%60).'min':'') : $min.' min';
                    $msg_template = "⏱️ *Teste Criado!*\n\n👤 Usuário: {usuario}\n🔑 Senha: {senha}\n⏱️ Duração: {duracao}\n📅 Expira: {validade}\n👥 Limite: {limite} conexões\n\n🌐 https://{dominio}/";
                }
                $min = intval($validadefin);
                $dur = $min >= 60 ? floor($min/60).'h'.($min%60>0?' '.($min%60).'min':'') : $min.' min';
                $msg_final = str_replace(
                    ['{usuario}','{login}','{senha}','{validade}','{duracao}','{minutos}','{limite}','{dominio}'],
                    [$usuariofin,$usuariofin,$senhafin,date('d/m/Y H:i',strtotime($expira)),$dur,$validadefin,$limitefin,$_SERVER['HTTP_HOST']],
                    $msg_template
                );
                $wpp_enviado = enviarWhatsAppEvolution($conn, $_SESSION['iduser'], $whatsapp_destino, $msg_final);
            }

            // Mensagem modal personalizada
            $mensagem_modal = '';
            $sql_mm = "SELECT mensagem FROM mensagens_modal WHERE funcao='criarteste' AND byid='{$_SESSION['iduser']}' AND ativo='ativada' LIMIT 1";
            $r_mm   = mysqli_query($conn, $sql_mm);
            if ($r_mm && mysqli_num_rows($r_mm) > 0) $mensagem_modal = mysqli_fetch_assoc($r_mm)['mensagem'];
            if (!empty($mensagem_modal)) {
                $min = intval($validadefin);
                $dur = $min >= 60 ? floor($min/60).'h'.($min%60>0?' '.($min%60).'min':'') : $min.' min';
                $mensagem_final = str_replace(
                    ['{usuario}','{login}','{senha}','{validade}','{duracao}','{minutos}','{limite}','{dominio}'],
                    [$usuariofin,$usuariofin,$senhafin,date('d/m/Y H:i',strtotime($expira)),$dur,$validadefin,$limitefin,$_SERVER['HTTP_HOST']],
                    $mensagem_modal
                );
                $mensagem_final = nl2br(htmlspecialchars($mensagem_final));
            }

            $show_modal             = true;
            $modal_usuario          = $usuariofin;
            $modal_senha            = $senhafin;
            $modal_expira           = $expira;
            $modal_minutos          = $validadefin;
            $modal_limite           = $limitefin;
            $modal_uuid             = $formattedUuid;
            $modal_v2ray            = $v2ray;
            $modal_valormensal      = $valormensal;
            $modal_whatsapp_destino = $whatsapp_destino;
        }
    }
}

// ══════════════════════════════════════════════════════════════
// SEGURANÇA + HEADER
// ══════════════════════════════════════════════════════════════
if (!file_exists('suspenderrev.php')) exit("<script>alert('Token Invalido!');</script>");
else include_once 'suspenderrev.php';

if (!isset($_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe']) || !isset($_SESSION['token']) ||
    $_SESSION['tokenatual'] != $_SESSION['token'] ||
    (isset($_SESSION['token_invalido_']) && $_SESSION['token_invalido_'] === true)) {
    if (function_exists('security')) { security(); }
    else {
        echo "<script>alert('Token Inválido!');location.href='../index.php';</script>";
        $_SESSION['token_invalido_'] = true; exit;
    }
}

include('headeradmin2.php');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Criar Teste</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
<style>
<?php
if (function_exists('getCSSVariables')) {
    echo getCSSVariables($temaAtual);
} else {
    echo ':root{--primaria:#f59e0b;--secundaria:#f97316;--fundo:#0f172a;--fundo_claro:#1e293b;--texto:#ffffff;--sucesso:#10b981;--erro:#dc2626;--aviso:#f59e0b;--info:#3b82f6;}';
}
?>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Inter',sans-serif;background:var(--fundo,#0f172a);color:var(--texto,#fff);min-height:100vh;}
.app-content{margin-left:-670px!important;padding:0!important;}
.content-wrapper{max-width:1000px;margin:0 auto!important;padding:20px!important;}
.content-body{padding:0!important;}

/* Stats Card */
.stats-card{
    background:linear-gradient(135deg,var(--fundo_claro,#1e293b),var(--fundo,#0f172a));
    border-radius:20px;padding:20px 24px;margin-bottom:24px;
    border:1px solid rgba(255,255,255,0.08);
    display:flex;align-items:center;gap:20px;
    position:relative;overflow:hidden;transition:all .3s ease;
}
.stats-card:hover{transform:translateY(-2px);border-color:var(--primaria,#f59e0b);}
.stats-card-icon{
    width:60px;height:60px;
    background:linear-gradient(135deg,var(--primaria,#f59e0b),var(--secundaria,#f97316));
    border-radius:18px;display:flex;align-items:center;justify-content:center;
    font-size:32px;color:white;
}
.stats-card-content{flex:1;}
.stats-card-title{font-size:13px;font-weight:600;color:rgba(255,255,255,0.5);text-transform:uppercase;margin-bottom:5px;}
.stats-card-value{
    font-size:36px;font-weight:800;
    background:linear-gradient(135deg,#fff,var(--primaria,#f59e0b));
    -webkit-background-clip:text;-webkit-text-fill-color:transparent;line-height:1;
}
.stats-card-subtitle{font-size:12px;color:rgba(255,255,255,0.4);}
.stats-card-decoration{position:absolute;right:20px;top:50%;transform:translateY(-50%);font-size:80px;opacity:0.05;}

/* Modern Card */
.modern-card{
    background:var(--fundo_claro,#1e293b);
    border-radius:16px;border:1px solid rgba(255,255,255,0.08);
    overflow:hidden;margin-bottom:16px;transition:all .2s;
}
.modern-card:hover{border-color:var(--primaria,#f59e0b);}
.card-header{padding:14px 18px;display:flex;align-items:center;gap:12px;}
.card-header.warning{background:linear-gradient(135deg,var(--primaria,#f59e0b),var(--secundaria,#f97316));}
.header-icon{
    width:36px;height:36px;background:rgba(255,255,255,0.2);
    border-radius:10px;display:flex;align-items:center;justify-content:center;
    font-size:18px;color:white;
}
.header-title{font-size:14px;font-weight:700;color:white;}
.header-subtitle{font-size:10px;color:rgba(255,255,255,0.7);}
.card-body{padding:16px;}

/* Botões */
.btn{
    padding:8px 16px;border:none;border-radius:10px;font-weight:600;font-size:12px;
    cursor:pointer;display:inline-flex;align-items:center;justify-content:center;
    gap:6px;color:white;transition:all .2s;
}
.btn:hover{transform:translateY(-1px);filter:brightness(1.08);}
.btn-primary{background:linear-gradient(135deg,var(--primaria,#f59e0b),var(--secundaria,#f97316));}
.btn-success{background:linear-gradient(135deg,var(--sucesso,#10b981),#059669);}
.btn-danger{background:linear-gradient(135deg,var(--erro,#dc2626),#b91c1c);}
.btn-sm{padding:5px 12px;font-size:11px;border-radius:8px;}

/* Form */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.form-field{display:flex;flex-direction:column;gap:4px;}
.form-field.full-width{grid-column:1/-1;}
.form-field label{
    font-size:9px;font-weight:700;color:rgba(255,255,255,.4);
    text-transform:uppercase;letter-spacing:.5px;
    display:flex;align-items:center;gap:4px;
}
.form-field label i{font-size:12px;}
.form-control{
    width:100%;padding:8px 12px;
    background:rgba(255,255,255,.06);border:1.5px solid rgba(255,255,255,.08);
    border-radius:9px;color:#fff;font-size:12px;font-family:inherit;
    outline:none;transition:all .25s;
}
.form-control:focus{border-color:var(--primaria,#f59e0b);background:rgba(255,255,255,.09);}
.form-control::placeholder{color:rgba(255,255,255,.2);}
select.form-control option{background:var(--fundo_claro,#1e293b);}

/* Horas Select */
.horas-select{display:grid;grid-template-columns:repeat(4,1fr);gap:6px;margin-top:4px;}
.hora-option{
    background:rgba(255,255,255,.06);border:1.5px solid rgba(255,255,255,.08);
    border-radius:8px;padding:8px 4px;text-align:center;cursor:pointer;
    transition:all .3s;font-weight:600;color:rgba(255,255,255,.7);
}
.hora-option:hover{background:rgba(255,255,255,.1);border-color:var(--primaria,#f59e0b);}
.hora-option.active{
    background:linear-gradient(135deg,var(--primaria,#f59e0b),var(--secundaria,#f97316));
    color:white;border-color:transparent;
}
.hora-option .hora-label{font-size:13px;font-weight:800;display:block;}
.hora-option .hora-sub{font-size:9px;opacity:.8;display:block;}

/* V2Ray */
.v2ray-toggle{
    display:flex;gap:6px;background:rgba(255,255,255,.06);
    border:1.5px solid rgba(255,255,255,.08);border-radius:8px;padding:3px;
}
.v2ray-option{
    flex:1;padding:6px;text-align:center;border-radius:6px;cursor:pointer;
    transition:all .3s;display:flex;align-items:center;justify-content:center;
    gap:4px;font-weight:600;font-size:11px;color:rgba(255,255,255,.5);
}
.v2ray-option i{font-size:14px;}
.v2ray-option.active{background:linear-gradient(135deg,var(--primaria,#f59e0b),var(--secundaria,#f97316));color:white;}
.v2ray-option:not(.active):hover{background:rgba(255,255,255,.1);}
.text-success-badge{
    background:linear-gradient(135deg,var(--sucesso,#10b981),#059669);
    color:white;padding:2px 6px;border-radius:4px;font-size:8px;font-weight:700;margin-left:4px;
}

.action-buttons{display:flex;justify-content:flex-end;gap:8px;margin-top:18px;flex-wrap:wrap;}

/* Modais */
.modal-overlay{
    position:fixed;inset:0;background:rgba(0,0,0,.85);
    display:none;align-items:center;justify-content:center;
    z-index:9999;backdrop-filter:blur(8px);padding:16px;
}
.modal-overlay.show{display:flex;}
.modal-container{animation:modalIn .3s ease;max-width:520px;width:90%;}
.modal-container.wide{max-width:660px;}
@keyframes modalIn{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
.modal-content{
    background:var(--fundo_claro,#1e293b);
    border-radius:20px;overflow:hidden;border:1px solid rgba(255,255,255,.1);
}
.modal-header{padding:14px 18px;display:flex;align-items:center;justify-content:space-between;}
.modal-header h5{margin:0;display:flex;align-items:center;gap:8px;font-size:14px;font-weight:700;color:#fff;}
.modal-header.success{background:linear-gradient(135deg,var(--sucesso,#10b981),#059669);}
.modal-header.error{background:linear-gradient(135deg,var(--erro,#dc2626),#b91c1c);}
.modal-header.warning{background:linear-gradient(135deg,var(--primaria,#f59e0b),var(--secundaria,#f97316));}
.modal-close{background:none;border:none;color:#fff;font-size:20px;cursor:pointer;line-height:1;}
.modal-body{padding:18px;max-height:65vh;overflow-y:auto;}
.modal-footer{
    border-top:1px solid rgba(255,255,255,.07);
    padding:12px 18px;display:flex;justify-content:flex-end;gap:8px;flex-wrap:wrap;
}

/* Info rows */
.modal-info-card{background:rgba(255,255,255,.05);border-radius:12px;padding:12px;margin-bottom:12px;}
.modal-info-row{
    display:flex;align-items:center;justify-content:space-between;
    padding:8px 0;border-bottom:1px solid rgba(255,255,255,.05);
}
.modal-info-row:last-child{border-bottom:none;}
.modal-info-label{font-size:11px;font-weight:600;color:rgba(255,255,255,.6);display:flex;align-items:center;gap:6px;}
.modal-info-label i{font-size:15px;}
.modal-info-value{font-size:12px;font-weight:700;color:white;}
.modal-info-value.credential{
    background:rgba(0,0,0,.3);padding:2px 8px;
    border-radius:6px;font-family:monospace;letter-spacing:.5px;
}
.modal-info-value.orange{color:var(--primaria,#f59e0b);}
.modal-info-value.green{color:var(--sucesso,#10b981);}

/* Servidores */
.modal-server-list{background:rgba(0,0,0,.3);border-radius:10px;padding:10px;margin-top:10px;}
.modal-server-badge{
    display:inline-block;background:rgba(16,185,129,.2);
    border:1px solid rgba(16,185,129,.3);color:#10b981;
    padding:3px 8px;border-radius:16px;font-size:10px;margin:3px;
}
.modal-server-badge.fail{
    background:rgba(220,38,38,.2);border-color:rgba(220,38,38,.3);color:#dc2626;
}

/* Mensagem */
.mensagem-box{
    background:rgba(245,158,11,.1);border-left:3px solid var(--primaria,#f59e0b);
    border-radius:8px;padding:10px;margin-top:10px;font-size:11px;line-height:1.6;
    color:rgba(255,255,255,.85);
}

/* WhatsApp status */
.wpp-status-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:16px;font-size:10px;font-weight:600;}
.wpp-status-badge.enviado{background:rgba(37,211,102,.15);border:1px solid rgba(37,211,102,.3);color:#25D366;}
.wpp-status-badge.nao-enviado{background:rgba(100,116,139,.15);border:1px solid rgba(100,116,139,.3);color:#94a3b8;}

/* Toast */
.toast-notification{
    position:fixed;bottom:20px;right:20px;color:#fff;
    padding:10px 16px;border-radius:10px;display:flex;align-items:center;gap:8px;
    z-index:10000;animation:toastIn .3s ease;font-weight:600;font-size:12px;
}
.toast-notification.ok{background:linear-gradient(135deg,var(--sucesso,#10b981),#059669);}
.toast-notification.err{background:linear-gradient(135deg,var(--erro,#dc2626),#b91c1c);}
@keyframes toastIn{from{transform:translateX(110%);opacity:0}to{transform:translateX(0);opacity:1}}

/* Botão WPP manual */
.btn-wpp-manual{
    width:100%;margin-top:10px;padding:9px;border:none;border-radius:10px;
    background:linear-gradient(135deg,#25D366,#128C7E);color:white;
    font-weight:600;font-size:12px;cursor:pointer;display:flex;
    align-items:center;justify-content:center;gap:6px;transition:all .2s;
}
.btn-wpp-manual:hover{transform:translateY(-1px);filter:brightness(1.1);}
.btn-wpp-manual:disabled{opacity:.5;cursor:not-allowed;transform:none;}

@media(max-width:768px){
    .app-content{margin-left:0!important;}
    .content-wrapper{padding:10px!important;}
    .form-grid{grid-template-columns:1fr;}
    .horas-select{grid-template-columns:repeat(2,1fr);}
    .action-buttons{flex-direction:column;}
    .btn{width:100%;}
}
</style>
</head>
<body class="<?php echo htmlspecialchars(getBodyClass($temaAtual ?? ['classe'=>'theme-dark'])); ?>">
<div class="app-content content">
<div class="content-overlay"></div>
<div class="content-wrapper">
<div class="content-body">

<!-- Stats Card -->
<div class="stats-card">
    <div class="stats-card-icon"><i class='bx bx-timer'></i></div>
    <div class="stats-card-content">
        <div class="stats-card-title">Criar Teste</div>
        <div class="stats-card-value">Novo</div>
        <div class="stats-card-subtitle">Crie contas de teste para seus clientes</div>
    </div>
    <div class="stats-card-decoration"><i class='bx bx-timer'></i></div>
</div>

<!-- Card Formulário -->
<div class="modern-card">
    <div class="card-header warning">
        <div class="header-icon"><i class='bx bx-timer'></i></div>
        <div>
            <div class="header-title">Criar Teste</div>
            <div class="header-subtitle">Preencha os dados do teste</div>
        </div>
    </div>
    <div class="card-body">
        <button type="button" class="btn btn-primary btn-sm" onclick="abrirModalGerar()" style="margin-bottom:16px;">
            <i class='bx bx-shuffle'></i> Gerar Aleatório
        </button>

        <form method="POST" action="">
            <div class="form-grid">

                <!-- Categoria -->
                <div class="form-field full-width">
                    <label><i class='bx bx-category'></i> Categoria</label>
                    <select class="form-control" name="categoria" required>
                        <?php
                        $r_cat = mysqli_query($conn, "SELECT * FROM categorias ORDER BY id ASC");
                        $first = true;
                        while ($rc = mysqli_fetch_assoc($r_cat)):
                            echo '<option value="'.htmlspecialchars($rc['subid']).'" '.($first?'selected':'').'>'.htmlspecialchars($rc['nome']).'</option>';
                            $first = false;
                        endwhile;
                        ?>
                    </select>
                </div>

                <!-- Login -->
                <div class="form-field">
                    <label><i class='bx bx-user'></i> Login</label>
                    <input type="text" class="form-control" name="usuariofin" id="usuariofin"
                           placeholder="ex: teste123" required>
                </div>

                <!-- Senha -->
                <div class="form-field">
                    <label><i class='bx bx-lock-alt'></i> Senha</label>
                    <input type="text" class="form-control" name="senhafin" id="senhafin"
                           placeholder="ex: senha123" required>
                </div>

                <!-- Limite -->
                <div class="form-field">
                    <label><i class='bx bx-layer'></i> Limite</label>
                    <input type="number" class="form-control" name="limitefin" id="limitefin" value="1" min="1" required>
                </div>

                <!-- Duraç��o -->
                <div class="form-field">
                    <label><i class='bx bx-time'></i> Duração do Teste</label>
                    <input type="hidden" name="validadefin" id="validadefin" value="180">
                    <div class="horas-select">
                        <div class="hora-option" data-min="60">
                            <span class="hora-label">1h</span>
                            <span class="hora-sub">60 min</span>
                        </div>
                        <div class="hora-option active" data-min="180">
                            <span class="hora-label">3h</span>
                            <span class="hora-sub">180 min</span>
                        </div>
                        <div class="hora-option" data-min="360">
                            <span class="hora-label">6h</span>
                            <span class="hora-sub">360 min</span>
                        </div>
                        <div class="hora-option" data-min="720">
                            <span class="hora-label">12h</span>
                            <span class="hora-sub">720 min</span>
                        </div>
                    </div>
                </div>

                <!-- V2Ray -->
                <div class="form-field">
                    <label><i class='bx bx-shield-quarter'></i> V2Ray <span class="text-success-badge">BETA</span></label>
                    <div class="v2ray-toggle">
                        <div class="v2ray-option active" onclick="selectV2ray('nao')" id="v2rayNao">
                            <i class='bx bx-x-circle'></i> Não
                        </div>
                        <div class="v2ray-option" onclick="selectV2ray('sim')" id="v2raySim">
                            <i class='bx bx-check-circle'></i> Sim
                        </div>
                    </div>
                    <input type="hidden" name="v2ray" id="v2rayInput" value="nao">
                </div>

                <!-- Notas -->
                <div class="form-field">
                    <label><i class='bx bx-note'></i> Notas</label>
                    <input type="text" class="form-control" name="notas" placeholder="Observações">
                </div>

                <!-- Valor -->
                <div class="form-field">
                    <label><i class='bx bx-dollar'></i> Valor do Teste (R$)</label>
                    <input type="number" class="form-control" step="0.01" min="0"
                           name="valormensal" id="valormensal" value="0" placeholder="0,00">
                </div>

                <!-- WhatsApp -->
                <div class="form-field full-width">
                    <label><i class='bx bxl-whatsapp'></i> WhatsApp do Cliente</label>
                    <input type="text" class="form-control" name="whatsapp" id="whatsapp_input"
                           placeholder="5511999999999">
                    <small style="color:rgba(255,255,255,.3);font-size:9px;margin-top:3px;">
                        <i class='bx bx-info-circle'></i> Com DDI. Ex: 5511999999999 — mensagem enviada automaticamente
                    </small>
                </div>

            </div>
            <div class="action-buttons">
                <button type="reset" class="btn btn-danger"><i class='bx bx-x'></i> Cancelar</button>
                <button type="submit" name="criaruser" class="btn btn-success">
                    <i class='bx bx-check'></i> Criar Teste
                </button>
            </div>
        </form>
    </div>
</div>

</div></div></div>

<!-- ══════════════════════════════════════════
     MODAL: GERAR ALEATÓRIO
══════════════════════════════════════════ -->
<div id="modalGerar" class="modal-overlay">
<div class="modal-container"><div class="modal-content">
    <div class="modal-header warning">
        <h5><i class='bx bx-shuffle'></i> Dados Gerados!</h5>
        <button class="modal-close" onclick="fecharModal('modalGerar')"><i class='bx bx-x'></i></button>
    </div>
    <div class="modal-body">
        <div class="modal-info-card">
            <div class="modal-info-row">
                <div class="modal-info-label"><i class='bx bx-user'></i> Login gerado</div>
                <div class="modal-info-value credential" id="gerar-login-preview">—</div>
            </div>
            <div class="modal-info-row">
                <div class="modal-info-label"><i class='bx bx-lock-alt'></i> Senha gerada</div>
                <div class="modal-info-value credential" id="gerar-senha-preview">—</div>
            </div>
            <div class="modal-info-row">
                <div class="modal-info-label"><i class='bx bx-group'></i> Limite</div>
                <div class="modal-info-value">1 conexão</div>
            </div>
            <div class="modal-info-row">
                <div class="modal-info-label"><i class='bx bx-time'></i> Duração</div>
                <div class="modal-info-value orange">3 horas (180 min)</div>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button class="btn btn-success" onclick="fecharModal('modalGerar')">
            <i class='bx bx-check'></i> OK, usar
        </button>
        <button class="btn btn-primary" onclick="gerarNovamente()">
            <i class='bx bx-refresh'></i> Gerar outros
        </button>
    </div>
</div></div>
</div>

<!-- ══════════════════════════════════════════
     MODAL: SUCESSO
══════════════════════════════════════════ -->
<div id="modalSucesso" class="modal-overlay <?php echo $show_modal ? 'show' : ''; ?>">
<div class="modal-container wide"><div class="modal-content">
    <div class="modal-header success">
        <h5><i class='bx bx-check-circle'></i> Teste Criado com Sucesso!</h5>
        <button class="modal-close" onclick="fecharModal('modalSucesso')"><i class='bx bx-x'></i></button>
    </div>
    <div class="modal-body">
        <div class="modal-info-card">
            <div class="modal-info-row">
                <div class="modal-info-label"><i class='bx bx-user'></i> Usuário</div>
                <div class="modal-info-value credential"><?php echo $show_modal ? htmlspecialchars($modal_usuario) : ''; ?></div>
            </div>
            <div class="modal-info-row">
                <div class="modal-info-label"><i class='bx bx-lock-alt'></i> Senha</div>
                <div class="modal-info-value credential"><?php echo $show_modal ? htmlspecialchars($modal_senha) : ''; ?></div>
            </div>
            <div class="modal-info-row">
                <div class="modal-info-label"><i class='bx bx-time'></i> Duração</div>
                <div class="modal-info-value orange">
                    <?php
                    if ($show_modal) {
                        $min = intval($modal_minutos);
                        echo $min >= 60
                            ? floor($min/60).'h'.($min%60>0?' '.($min%60).'min':'').' ('.$min.' min)'
                            : $min.' minutos';
                    }
                    ?>
                </div>
            </div>
            <div class="modal-info-row">
                <div class="modal-info-label"><i class='bx bx-calendar-x'></i> Expira em</div>
                <div class="modal-info-value green"><?php echo $show_modal ? date('d/m/Y H:i', strtotime($modal_expira)) : ''; ?></div>
            </div>
            <div class="modal-info-row">
                <div class="modal-info-label"><i class='bx bx-group'></i> Limite</div>
                <div class="modal-info-value"><?php echo $show_modal ? $modal_limite.' conexões' : ''; ?></div>
            </div>
            <?php if ($show_modal && !empty($modal_uuid)): ?>
            <div class="modal-info-row">
                <div class="modal-info-label"><i class='bx bx-shield-quarter'></i> UUID V2Ray</div>
                <div class="modal-info-value" style="font-size:10px;word-break:break-all;max-width:55%;"><?php echo $modal_uuid; ?></div>
            </div>
            <?php endif; ?>
            <?php if ($show_modal && !empty($modal_whatsapp_destino)): ?>
            <div class="modal-info-row">
                <div class="modal-info-label"><i class='bx bxl-whatsapp'></i> WhatsApp</div>
                <div class="modal-info-value" style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;">
                    <span><?php echo htmlspecialchars($modal_whatsapp_destino); ?></span>
                    <?php if ($wpp_enviado): ?>
                    <span class="wpp-status-badge enviado"><i class='bx bx-check-double'></i> Mensagem enviada!</span>
                    <?php else: ?>
                    <span class="wpp-status-badge nao-enviado"><i class='bx bx-info-circle'></i> Não enviado automaticamente</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($sucess_servers)): ?>
        <div class="modal-server-list">
            <div style="font-size:11px;margin-bottom:6px;color:rgba(255,255,255,.6);">
                <i class='bx bx-check-circle' style="color:#10b981;"></i> Criado nos servidores:
            </div>
            <?php foreach($sucess_servers as $s): ?>
            <span class="modal-server-badge"><i class='bx bx-server' style="font-size:10px;"></i> <?php echo htmlspecialchars($s); ?></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($failed_servers)): ?>
        <div class="modal-server-list" style="margin-top:8px;">
            <div style="font-size:11px;margin-bottom:6px;color:rgba(220,38,38,.8);">
                <i class='bx bx-error-circle'></i> Falha nos servidores:
            </div>
            <?php foreach($failed_servers as $s): ?>
            <span class="modal-server-badge fail"><?php echo htmlspecialchars($s); ?></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($mensagem_final)): ?>
        <div class="mensagem-box"><?php echo $mensagem_final; ?></div>
        <?php endif; ?>

        <!-- ✅ BOTÃO WPP MANUAL -->
        <?php if ($show_modal && !empty($modal_whatsapp_destino) && !$wpp_enviado): ?>
        <button class="btn-wpp-manual" id="btnWppManual"
            onclick="enviarWppManual(
                '<?php echo htmlspecialchars($modal_whatsapp_destino); ?>',
                '<?php echo addslashes($modal_usuario); ?>',
                '<?php echo addslashes($modal_senha); ?>',
                '<?php echo $show_modal ? date("d/m/Y H:i", strtotime($modal_expira)) : ""; ?>',
                '<?php echo addslashes($modal_limite); ?>',
                <?php echo intval($modal_minutos); ?>
            )">
            <i class='bx bxl-whatsapp'></i> Enviar WhatsApp Agora
        </button>
        <?php endif; ?>
    </div>
    <div class="modal-footer">
        <a href="listarusuarios.php" class="btn btn-danger"><i class='bx bx-list-ul'></i> Lista</a>
        <button class="btn btn-primary" onclick="shareWhatsApp()"><i class='bx bxl-whatsapp'></i> Compartilhar</button>
        <button class="btn btn-success" onclick="copiarDados()"><i class='bx bx-copy'></i> Copiar</button>
    </div>
</div></div>
</div>

<!-- ══════════════════════════════════════════
     MODAL: ERRO
══════════════════════════════════════════ -->
<div id="modalErro" class="modal-overlay <?php echo $show_error_modal ? 'show' : ''; ?>">
<div class="modal-container"><div class="modal-content">
    <div class="modal-header error">
        <h5><i class='bx bx-error-circle'></i> Erro!</h5>
        <button class="modal-close" onclick="fecharModal('modalErro')"><i class='bx bx-x'></i></button>
    </div>
    <div class="modal-body">
        <div style="text-align:center;margin-bottom:16px;">
            <i class='bx bx-error-circle' style="font-size:54px;color:var(--erro,#dc2626);"></i>
        </div>
        <p style="text-align:center;color:rgba(255,255,255,.85);font-size:13px;">
            <?php echo htmlspecialchars($error_message); ?>
        </p>
    </div>
    <div class="modal-footer">
        <button class="btn btn-danger" onclick="fecharModal('modalErro')">
            <i class='bx bx-check'></i> OK
        </button>
    </div>
</div></div>
</div>

<script>
// ── Dados PHP → JS ─────────────────────────────────────────
var MODAL_USUARIO  = <?php echo json_encode($show_modal ? $modal_usuario  : ''); ?>;
var MODAL_SENHA    = <?php echo json_encode($show_modal ? $modal_senha    : ''); ?>;
var MODAL_EXPIRA   = <?php echo json_encode($show_modal ? date('d/m/Y H:i', strtotime($modal_expira)) : ''); ?>;
var MODAL_MINUTOS  = <?php echo intval($modal_minutos); ?>;
var MODAL_LIMITE   = <?php echo json_encode($show_modal ? $modal_limite   : ''); ?>;
var MODAL_UUID     = <?php echo json_encode($show_modal ? $modal_uuid     : ''); ?>;
var MODAL_WPP      = <?php echo json_encode($show_modal ? $modal_whatsapp_destino : ''); ?>;
var MODAL_DOMINIO  = <?php echo json_encode($_SERVER['HTTP_HOST']); ?>;

// ── Modais ──────────────────────────────────────────────────
function abrirModal(id){ document.getElementById(id).classList.add('show'); }
function fecharModal(id){ document.getElementById(id).classList.remove('show'); }

document.querySelectorAll('.modal-overlay').forEach(function(o){
    o.addEventListener('click', function(e){ if(e.target === o) o.classList.remove('show'); });
});
document.addEventListener('keydown', function(e){
    if(e.key === 'Escape')
        document.querySelectorAll('.modal-overlay.show').forEach(function(m){ m.classList.remove('show'); });
});

// ── Seletor de horas ────────────────────────────────────────
document.querySelectorAll('.hora-option').forEach(function(opt){
    opt.addEventListener('click', function(){
        document.querySelectorAll('.hora-option').forEach(function(o){ o.classList.remove('active'); });
        this.classList.add('active');
        document.getElementById('validadefin').value = this.dataset.min;
    });
});

// ── V2Ray ────────────────────────────────────────────────────
function selectV2ray(v){
    document.getElementById('v2rayInput').value = v;
    document.getElementById('v2raySim').classList.toggle('active', v === 'sim');
    document.getElementById('v2rayNao').classList.toggle('active', v === 'nao');
}

// ── Gerar aleatório ─────────────────────────────────────────
function gerarDados(){
    var nums = '0123456789', sufixo = '';
    for(var i = 0; i < 1; i++) sufixo += nums[Math.floor(Math.random() * 10)];
    var lets = 'abcdefghijklmnopqrstuvwxyz', u_let = '';
    for(var i = 0; i < 3; i++) u_let += lets[Math.floor(Math.random() * lets.length)];
    for(var i = 0; i < 3; i++) sufixo += nums[Math.floor(Math.random() * 10)];
    var usuario = sufixo[0] + u_let + sufixo.slice(1);
    var senha = usuario;
    document.getElementById('usuariofin').value  = usuario;
    document.getElementById('senhafin').value    = senha;
    document.getElementById('limitefin').value   = 1;
    document.getElementById('valormensal').value = '0';
    document.querySelectorAll('.hora-option').forEach(function(o){ o.classList.remove('active'); });
    document.querySelectorAll('.hora-option')[1].classList.add('active');
    document.getElementById('validadefin').value = '180';
    return { usuario: usuario, senha: senha };
}
function abrirModalGerar(){
    var d = gerarDados();
    document.getElementById('gerar-login-preview').textContent = d.usuario;
    document.getElementById('gerar-senha-preview').textContent = d.senha;
    abrirModal('modalGerar');
}
function gerarNovamente(){
    var d = gerarDados();
    document.getElementById('gerar-login-preview').textContent = d.usuario;
    document.getElementById('gerar-senha-preview').textContent = d.senha;
    mostrarToast('Novos dados gerados!', 'ok');
}

// ── Copiar ───────────────────────────────────────────────────
function copiarDados(){
    var horas  = Math.floor(MODAL_MINUTOS / 60);
    var duracao = horas > 0 ? horas + 'h (' + MODAL_MINUTOS + ' min)' : MODAL_MINUTOS + ' min';
    var t = '⏱️ TESTE CRIADO COM SUCESSO!\n'
          + '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n'
          + '👤 Login: '    + MODAL_USUARIO + '\n'
          + '🔑 Senha: '    + MODAL_SENHA   + '\n'
          + '⏱️ Duração: '  + duracao       + '\n'
          + '📅 Expira: '   + MODAL_EXPIRA  + '\n'
          + '🔗 Limite: '   + MODAL_LIMITE  + ' conexões\n';
    if(MODAL_UUID) t += '🛡 UUID: ' + MODAL_UUID + '\n';
    t += '\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n'
       + '🌐 https://' + MODAL_DOMINIO + '/\n'
       + '📆 Data: ' + new Date().toLocaleString('pt-BR') + '\n';
    navigator.clipboard.writeText(t)
        .then(function(){ mostrarToast('Copiado!', 'ok'); })
        .catch(function(){ mostrarToast('Erro ao copiar!', 'err'); });
}

// ── Compartilhar WhatsApp ────────────────────────────────────
function shareWhatsApp(){
    var horas  = Math.floor(MODAL_MINUTOS / 60);
    var duracao = horas > 0 ? horas + 'h (' + MODAL_MINUTOS + ' min)' : MODAL_MINUTOS + ' min';
    var txt = '⏱️ *Teste Criado!*\n\n'
            + '👤 Usuário: ' + MODAL_USUARIO + '\n'
            + '🔑 Senha: '   + MODAL_SENHA   + '\n'
            + '⏱️ Duração: ' + duracao       + '\n'
            + '📅 Expira: '  + MODAL_EXPIRA  + '\n'
            + '🔗 Limite: '  + MODAL_LIMITE  + ' conexões\n\n'
            + '🌐 https://'  + MODAL_DOMINIO + '/';
    var url = MODAL_WPP
        ? 'https://api.whatsapp.com/send?phone=' + MODAL_WPP + '&text=' + encodeURIComponent(txt)
        : 'https://api.whatsapp.com/send?text='  + encodeURIComponent(txt);
    window.open(url, '_blank');
}

// ── Enviar WPP manual via AJAX ──────────────���────────────────
function enviarWppManual(numero, usuario, senha, expira, limite, minutos){
    var btn = document.getElementById('btnWppManual');
    if(btn){ btn.disabled = true; btn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Enviando...'; }

    var horas   = Math.floor(minutos / 60);
    var duracao = horas > 0 ? horas + 'h (' + minutos + ' min)' : minutos + ' min';

    var texto = '⏱️ *Teste Criado!*\n\n'
              + '👤 Usuário: ' + usuario + '\n'
              + '🔑 Senha: '   + senha   + '\n'
              + '⏱️ Duração: ' + duracao + '\n'
              + '📅 Expira: '  + expira  + '\n'
              + '🔗 Limite: '  + limite  + ' conexões\n\n'
              + '🌐 https://'  + MODAL_DOMINIO + '/';

    var fd = new FormData();
    fd.append('numero', numero);
    fd.append('texto',  texto);

    fetch('criarteste.php?ajax=enviar_wpp', { method:'POST', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(data){
            if(data.ok){
                mostrarToast('WhatsApp enviado para ' + data.numero + '!', 'ok');
                if(btn) btn.innerHTML = '<i class="bx bx-check-circle"></i> Enviado!';
            } else {
                mostrarToast('Falha: ' + data.msg, 'err');
                if(btn){ btn.disabled = false; btn.innerHTML = '<i class="bx bxl-whatsapp"></i> Tentar Novamente'; }
            }
        })
        .catch(function(){
            mostrarToast('Erro de conexão!', 'err');
            if(btn){ btn.disabled = false; btn.innerHTML = '<i class="bx bxl-whatsapp"></i> Tentar Novamente'; }
        });
}

// ── Toast ────────────────────────────────────────────────────
function mostrarToast(msg, tipo){
    var t = document.createElement('div');
    t.className = 'toast-notification ' + (tipo || 'ok');
    t.innerHTML = '<i class="bx ' + (tipo === 'err' ? 'bx-error-circle' : 'bx-check-circle') + '"></i> ' + msg;
    document.body.appendChild(t);
    setTimeout(function(){ t.remove(); }, 3500);
}
</script>
</body>
</html>

