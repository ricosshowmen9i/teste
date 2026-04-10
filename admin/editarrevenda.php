<?php // @ioncube.dk criptografia
error_reporting(0);
session_start();
date_default_timezone_set('America/Sao_Paulo');
include('../AegisCore/conexao.php');

$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

// ========== HANDLERS DE POST (antes de qualquer output) ==========

function anti_sql($input) {
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function($m) { return ''; }, $input);
    return addslashes(strip_tags(trim($seg)));
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_SESSION['idrevenda']) ? (int)$_SESSION['idrevenda'] : 0);
$_SESSION['idrevenda'] = $id;

$msg_tipo = ''; $msg_titulo = ''; $msg_texto = '';

if ($id > 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {

    // Adicionar créditos
    if (isset($_POST['addcreditos'])) {
        $qtd = (int) anti_sql($_POST['quantidadecreditosadd'] ?? 0);
        if ($qtd > 0) {
            $conn->query("UPDATE atribuidos SET limite = limite + $qtd WHERE userid = '$id'");
            $msg_tipo = 'success'; $msg_titulo = 'Créditos Adicionados!'; $msg_texto = "$qtd créditos adicionados com sucesso.";
        } else {
            $msg_tipo = 'error'; $msg_titulo = 'Erro!'; $msg_texto = 'Quantidade inválida.';
        }
    }

    // Alterar modo
    elseif (isset($_POST['alterarmodo'])) {
        $novo_modo = anti_sql($_POST['modorevenda'] ?? '');
        $r = $conn->query("SELECT tipo FROM atribuidos WHERE userid = '$id'");
        $tipo_atual = ($r && $r->num_rows > 0) ? $r->fetch_assoc()['tipo'] : '';

        if ($tipo_atual == 'Credito' && $novo_modo == 'Validade') {
            $datahoje = date('Y-m-d H:i:s', strtotime('+1 days'));
            $conn->query("UPDATE atribuidos SET tipo = 'Validade', expira = '$datahoje' WHERE userid = '$id'");
            $conn->query("UPDATE atribuidos SET tipo = 'Validade', expira = '$datahoje' WHERE byid = '$id'");
            $msg_tipo = 'success'; $msg_titulo = 'Modo Alterado!'; $msg_texto = 'Alterado para Validade com sucesso.';
        } elseif ($tipo_atual == 'Validade' && $novo_modo == 'Credito') {
            $conn->query("UPDATE atribuidos SET tipo = 'Credito', expira = '' WHERE userid = '$id'");
            $conn->query("UPDATE atribuidos SET tipo = 'Credito', expira = '' WHERE byid = '$id'");
            $msg_tipo = 'success'; $msg_titulo = 'Modo Alterado!'; $msg_texto = 'Alterado para Crédito com sucesso.';
        } else {
            $msg_tipo = 'warning'; $msg_titulo = 'Sem Alteração'; $msg_texto = 'O modo já é o mesmo.';
        }
    }

    // Salvar edição principal
    elseif (isset($_POST['salvareditar'])) {
        $usuarioedit = anti_sql($_POST['usuarioedit'] ?? '');
        $senhaedit = anti_sql($_POST['senhaedit'] ?? '');
        $limiteedit = (int) anti_sql($_POST['limiteedit'] ?? 0);
        $whatsapp = str_replace([" ", "-"], "", anti_sql($_POST['whatsapp'] ?? ''));
        $valormensal = anti_sql($_POST['valormensal'] ?? '0');

        $r = $conn->query("SELECT tipo FROM atribuidos WHERE userid = '$id'");
        $tipo_atual = ($r && $r->num_rows > 0) ? $r->fetch_assoc()['tipo'] : 'Validade';

        $validadeedit = '';
        if ($tipo_atual != 'Credito') {
            $dias = (int) anti_sql($_POST['validadeedit'] ?? 0);
            if ($dias > 0) { $validadeedit = date('Y-m-d H:i:s', strtotime("+$dias days")); }
        }

        if (!empty($usuarioedit) && !empty($senhaedit)) {
            $check = $conn->query("SELECT id FROM accounts WHERE login = '$usuarioedit' AND id != '$id'");
            if ($check && $check->num_rows > 0) {
                $msg_tipo = 'error'; $msg_titulo = 'Erro!'; $msg_texto = 'Este login já está em uso por outro usuário.';
            } else {
                $sql_atrib = "UPDATE atribuidos SET limite='$limiteedit'";
                if (!empty($validadeedit)) { $sql_atrib .= ", expira='$validadeedit'"; }
                $sql_atrib .= ", valormensal='$valormensal' WHERE userid='$id'";
                $conn->query($sql_atrib);
                $conn->query("UPDATE accounts SET login='$usuarioedit', senha='$senhaedit', whatsapp='$whatsapp' WHERE id='$id'");
                $msg_tipo = 'success'; $msg_titulo = 'Salvo!'; $msg_texto = 'Revenda editada com sucesso.';
            }
        } else {
            $msg_tipo = 'error'; $msg_titulo = 'Erro!'; $msg_texto = 'Preencha login e senha.';
        }
    }
}

// ========== INCLUI HEADER ==========
include('headeradmin2.php');

if (!file_exists('suspenderrev.php')) { exit("<script>alert('Token Invalido!');</script>"); }
else { include_once 'suspenderrev.php'; }

if (!isset($_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe']) || !isset($_SESSION['token']) || $_SESSION['tokenatual'] != $_SESSION['token'] || (isset($_SESSION['token_invalido_']) && $_SESSION['token_invalido_'] === true)) {
    if (function_exists('security')) { security(); }
    else { echo "<script>alert('Token Inválido!');location.href='../index.php';</script>"; $_SESSION['token_invalido_'] = true; exit; }
}

if (file_exists('../AegisCore/temas.php')) {
    include_once '../AegisCore/temas.php';
    $temaAtual = initTemas($conn);
} else { $temaAtual = []; }

if (!$id) { echo "<script>alert('ID inválido!');history.back();</script>"; exit; }

// ========== BUSCAR DADOS ==========
$result_acc = $conn->query("SELECT * FROM accounts WHERE id = '$id'");
if (!$result_acc || $result_acc->num_rows == 0) { echo "<script>alert('Revendedor não encontrado!');history.back();</script>"; exit; }
$rev = $result_acc->fetch_assoc();

$result_atrib = $conn->query("SELECT * FROM atribuidos WHERE userid = '$id'");
$atrib = $result_atrib ? $result_atrib->fetch_assoc() : [];

$tipo = $atrib['tipo'] ?? 'Validade';
$limite = $atrib['limite'] ?? 0;
$expira_raw = $atrib['expira'] ?? '';
$valormensal = $atrib['valormensal'] ?? '0.00';
$suspenso = ($atrib['suspenso'] ?? 0) == 1;

$expira_formatada = ($expira_raw != '') ? date('d/m/Y H:i', strtotime($expira_raw)) : 'Nunca';
$dias_restantes = 0; $horas_restantes = 0; $conta_vencida = false;
if ($tipo == 'Validade' && $expira_raw != '') {
    $diferenca = strtotime($expira_raw) - time();
    $dias_restantes = floor($diferenca / 86400);
    $horas_restantes = floor(($diferenca % 86400) / 3600);
    if ($dias_restantes < 0) $conta_vencida = true;
}

$dono_login = 'admin';
if (!empty($rev['byid'])) { $r_d = $conn->query("SELECT login FROM accounts WHERE id = '".$rev['byid']."'"); if ($r_d && $r_d->num_rows > 0) { $d = $r_d->fetch_assoc(); $dono_login = $d['login']; } }

$categoria_nome = 'N/A';
if (!empty($atrib['categoriaid'])) { $r_c = $conn->query("SELECT nome FROM categorias WHERE subid = '".$atrib['categoriaid']."'"); if ($r_c && $r_c->num_rows > 0) { $c = $r_c->fetch_assoc(); $categoria_nome = $c['nome']; } }

$total_usuarios = 0; $r = $conn->query("SELECT COUNT(*) as t FROM ssh_accounts WHERE byid='$id'"); if($r){$rr=$r->fetch_assoc();$total_usuarios=$rr['t'];}
$total_onlines = 0; $r = $conn->query("SELECT COUNT(*) as t FROM ssh_accounts WHERE byid='$id' AND status='Online'"); if($r){$rr=$r->fetch_assoc();$total_onlines=$rr['t'];}
$total_revendas = 0; $r = $conn->query("SELECT COUNT(*) as t FROM accounts WHERE byid='$id' AND login!='admin'"); if($r){$rr=$r->fetch_assoc();$total_revendas=$rr['t'];}

$limite_usado = 0;
$r = $conn->query("SELECT COALESCE(SUM(limite),0) as t FROM ssh_accounts WHERE byid='$id'"); if($r){$rr=$r->fetch_assoc();$limite_usado+=$rr['t'];}
$r = $conn->query("SELECT COALESCE(SUM(limite),0) as t FROM atribuidos WHERE byid='$id'"); if($r){$rr=$r->fetch_assoc();$limite_usado+=$rr['t'];}
$limite_restante = $limite - $limite_usado;

$profile_image = $rev['profile_image'] ?? '';
$avatar_url = !empty($profile_image) ? '../uploads/profiles/'.$profile_image : 'https://ui-avatars.com/api/?name='.urlencode($rev['login']).'&size=120&background=7c3aed&color=fff&bold=true';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Editar - <?php echo htmlspecialchars($rev['login']); ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
<style>
<?php
if (function_exists('getCSSVariables')) { echo getCSSVariables($temaAtual); }
else { echo ':root{--primaria:#8b5cf6;--secundaria:#C850C0;--fundo:#0f172a;--fundo_claro:#1e293b;--texto:#ffffff;--sucesso:#10b981;--erro:#dc2626;--aviso:#f59e0b;--info:#3b82f6;}'; }
?>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Inter',sans-serif;background:var(--fundo,#0f172a);color:var(--texto,#fff);min-height:100vh;}
.app-content{margin-left:-670px!important;padding:0!important;}
.content-wrapper{max-width:1000px;margin:0 auto!important;padding:20px!important;}
.content-body{padding:0!important;}

/* ========== STATS CARD (igual criar usuário) ========== */
.stats-card{
    background:linear-gradient(135deg,var(--fundo_claro,#1e293b),var(--fundo,#0f172a));
    border-radius:20px;padding:20px 24px;margin-bottom:24px;
    border:1px solid rgba(255,255,255,0.08);
    display:flex;align-items:center;gap:20px;
    position:relative;overflow:hidden;transition:all .3s ease;
}
.stats-card:hover{transform:translateY(-2px);border-color:var(--primaria,#8b5cf6);}
.stats-card-icon{
    width:60px;height:60px;
    background:linear-gradient(135deg,#8b5cf6,#C850C0);
    border-radius:18px;display:flex;align-items:center;justify-content:center;
    font-size:32px;color:white;flex-shrink:0;
}
.stats-card-content{flex:1;min-width:0;}
.stats-card-title{font-size:13px;font-weight:600;color:rgba(255,255,255,0.5);text-transform:uppercase;margin-bottom:5px;}
.stats-card-value{
    font-size:36px;font-weight:800;
    background:linear-gradient(135deg,#fff,#a78bfa);
    -webkit-background-clip:text;-webkit-text-fill-color:transparent;line-height:1;
    overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
}
.stats-card-subtitle{font-size:12px;color:rgba(255,255,255,0.4);margin-top:4px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
.stats-card-decoration{position:absolute;right:20px;top:50%;transform:translateY(-50%);font-size:80px;opacity:0.05;}
.stats-badge{display:inline-flex;align-items:center;gap:3px;padding:2px 8px;border-radius:6px;font-size:9px;font-weight:700;}
.stats-badge-active{background:rgba(16,185,129,.15);color:#34d399;border:1px solid rgba(16,185,129,.3);}
.stats-badge-suspended{background:rgba(239,68,68,.15);color:#f87171;border:1px solid rgba(239,68,68,.3);}
.stats-badge-expired{background:rgba(245,158,11,.15);color:#fbbf24;border:1px solid rgba(245,158,11,.3);}
.stats-badge-credit{background:rgba(59,130,246,.15);color:#60a5fa;border:1px solid rgba(59,130,246,.3);}
.stats-badge-validity{background:rgba(139,92,246,.15);color:#a78bfa;border:1px solid rgba(139,92,246,.3);}

/* ========== MINI STATS ========== */
.mini-stats{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;}
.mini-stat{
    flex:1;min-width:100px;
    background:rgba(255,255,255,0.04);border-radius:12px;padding:12px;
    border:1px solid rgba(255,255,255,0.06);text-align:center;
    transition:all .2s;
}
.mini-stat:hover{border-color:var(--primaria,#8b5cf6);transform:translateY(-2px);}
.mini-stat-val{font-size:20px;font-weight:800;}
.mini-stat-lbl{font-size:9px;color:rgba(255,255,255,0.35);text-transform:uppercase;margin-top:2px;}

/* ========== MODERN CARD (igual criar usuário) ========== */
.modern-card{
    background:var(--fundo_claro,#1e293b);
    border-radius:16px;border:1px solid rgba(255,255,255,0.08);
    overflow:hidden;margin-bottom:16px;transition:all .2s;
}
.modern-card:hover{border-color:var(--primaria,#8b5cf6);}
.card-header-custom{padding:14px 18px;display:flex;align-items:center;gap:12px;}
.card-header-custom.primary{background:linear-gradient(135deg,#8b5cf6,#C850C0);}
.card-header-custom.green{background:linear-gradient(135deg,#10b981,#059669);}
.card-header-custom.orange{background:linear-gradient(135deg,#f59e0b,#f97316);}
.card-header-custom.blue{background:linear-gradient(135deg,#3b82f6,#2563eb);}
.header-icon{
    width:36px;height:36px;background:rgba(255,255,255,0.2);
    border-radius:10px;display:flex;align-items:center;justify-content:center;
    font-size:18px;color:white;
}
.header-title{font-size:14px;font-weight:700;color:white;}
.header-subtitle{font-size:10px;color:rgba(255,255,255,0.7);}
.card-body{padding:16px;}

/* ========== FORM (igual criar usuário) ========== */
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
.form-control:focus{border-color:var(--primaria,#8b5cf6);background:rgba(255,255,255,.09);box-shadow:0 0 0 3px rgba(139,92,246,0.12);}
.form-control::placeholder{color:rgba(255,255,255,.2);}
.form-control:disabled{opacity:.5;cursor:not-allowed;}
select.form-control{
    cursor:pointer;appearance:none;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23ffffff' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat:no-repeat;background-position:right 10px center;
}
select.form-control option{background:var(--fundo_claro,#1e293b);}
.form-hint{font-size:9px;color:rgba(255,255,255,.25);font-style:italic;margin-top:2px;}
.current-value{
    display:inline-flex;align-items:center;gap:4px;
    background:rgba(255,255,255,.05);padding:3px 8px;border-radius:6px;
    font-size:10px;color:rgba(255,255,255,.5);margin-top:3px;
}
.current-value i{font-size:11px;color:var(--primaria,#8b5cf6);}

/* ========== CONTROLE DE DIAS ========== */
.dias-control{
    display:flex;align-items:center;gap:0;
    background:rgba(255,255,255,.04);border:1.5px solid rgba(255,255,255,.08);
    border-radius:9px;overflow:hidden;transition:all .25s;
}
.dias-control:focus-within{border-color:var(--primaria,#8b5cf6);box-shadow:0 0 0 3px rgba(139,92,246,0.12);}
.dias-btn{
    width:40px;height:40px;border:none;cursor:pointer;
    display:flex;align-items:center;justify-content:center;
    font-size:16px;font-weight:700;transition:all .15s;flex-shrink:0;color:white;
}
.dias-minus{background:rgba(239,68,68,.2);}
.dias-minus:hover{background:rgba(239,68,68,.4);}
.dias-minus:active{background:rgba(239,68,68,.6);transform:scale(.95);}
.dias-plus{background:rgba(16,185,129,.2);}
.dias-plus:hover{background:rgba(16,185,129,.4);}
.dias-plus:active{background:rgba(16,185,129,.6);transform:scale(.95);}
.dias-input{
    flex:1;text-align:center;border:none!important;border-radius:0!important;
    background:transparent!important;box-shadow:none!important;
    font-size:18px!important;font-weight:800!important;
    padding:6px 4px!important;min-width:50px;
    -moz-appearance:textfield;
}
.dias-input::-webkit-outer-spin-button,.dias-input::-webkit-inner-spin-button{-webkit-appearance:none;margin:0;}

.dias-info{display:flex;flex-direction:column;gap:3px;margin-top:4px;}
.preview-data{
    display:inline-flex;align-items:center;gap:4px;
    font-size:11px;font-weight:700;color:#a78bfa;
    padding:4px 8px;background:rgba(139,92,246,.1);
    border:1px solid rgba(139,92,246,.2);border-radius:6px;
    transition:all .3s;min-height:26px;
}
.preview-data:empty{display:none;}

.dias-shortcuts{display:flex;flex-wrap:wrap;gap:5px;margin-top:6px;}
.shortcut-btn{
    padding:4px 10px;border:1px solid rgba(255,255,255,.1);
    background:rgba(255,255,255,.04);border-radius:7px;
    color:rgba(255,255,255,.5);font-size:10px;font-weight:600;
    cursor:pointer;transition:all .2s;font-family:inherit;
}
.shortcut-btn:hover{background:rgba(139,92,246,.2);border-color:rgba(139,92,246,.4);color:#a78bfa;transform:translateY(-1px);}
.shortcut-btn:active{transform:scale(.95);}
.shortcut-btn.active-shortcut{background:var(--primaria,#8b5cf6);border-color:var(--primaria,#8b5cf6);color:white;}

/* Info inline */
.info-inline{
    display:flex;align-items:center;gap:8px;
    background:rgba(139,92,246,.08);border:1px solid rgba(139,92,246,.15);
    border-radius:8px;padding:8px 12px;margin-bottom:12px;
}
.info-inline i{font-size:16px;color:#a78bfa;flex-shrink:0;}
.info-inline span{font-size:11px;color:rgba(255,255,255,.6);}
.info-inline strong{color:#a78bfa;}

/* ========== BOTÕES (igual criar usuário) ========== */
.btn{
    padding:8px 16px;border:none;border-radius:10px;font-weight:600;font-size:12px;
    cursor:pointer;display:inline-flex;align-items:center;justify-content:center;
    gap:6px;color:white;transition:all .2s;text-decoration:none;font-family:inherit;
}
.btn:hover{transform:translateY(-1px);filter:brightness(1.08);}
.btn-success{background:linear-gradient(135deg,var(--sucesso,#10b981),#059669);}
.btn-danger{background:linear-gradient(135deg,var(--erro,#dc2626),#b91c1c);}
.btn-warning{background:linear-gradient(135deg,var(--aviso,#f59e0b),#f97316);}
.btn-blue{background:linear-gradient(135deg,#3b82f6,#2563eb);}
.btn-cancel{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);}
.btn-cancel:hover{background:rgba(255,255,255,.15);}
.action-buttons{display:flex;justify-content:flex-end;gap:8px;margin-top:18px;flex-wrap:wrap;}

/* ========== MODAIS ========== */
.modal-overlay{
    position:fixed;inset:0;background:rgba(0,0,0,.85);
    display:none;align-items:center;justify-content:center;
    z-index:9999;backdrop-filter:blur(8px);padding:16px;
}
.modal-overlay.show{display:flex;}
.modal-container{animation:modalIn .3s ease;max-width:440px;width:92%;}
@keyframes modalIn{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
.modal-content{
    background:var(--fundo_claro,#1e293b);
    border-radius:20px;overflow:hidden;border:1px solid rgba(255,255,255,.1);
    box-shadow:0 25px 60px rgba(0,0,0,.5);
}
.modal-header{padding:14px 18px;display:flex;align-items:center;justify-content:space-between;}
.modal-header h5{margin:0;display:flex;align-items:center;gap:8px;font-size:14px;font-weight:700;color:#fff;}
.modal-header.success{background:linear-gradient(135deg,var(--sucesso,#10b981),#059669);}
.modal-header.error{background:linear-gradient(135deg,var(--erro,#dc2626),#b91c1c);}
.modal-header.warning{background:linear-gradient(135deg,var(--aviso,#f59e0b),#f97316);}
.modal-header.blue{background:linear-gradient(135deg,#3b82f6,#2563eb);}
.modal-header.confirm{background:linear-gradient(135deg,#8b5cf6,#6d28d9);}
.modal-close{background:rgba(255,255,255,.15);border:none;color:#fff;font-size:18px;cursor:pointer;width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;transition:all .2s;}
.modal-close:hover{background:rgba(255,255,255,.25);transform:rotate(90deg);}
.modal-body{padding:18px;}
.modal-footer{border-top:1px solid rgba(255,255,255,.07);padding:12px 18px;display:flex;justify-content:flex-end;gap:8px;flex-wrap:wrap;}

.modal-ic{width:70px;height:70px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:34px;animation:icPop .5s cubic-bezier(0.34,1.56,0.64,1) .15s both;}
@keyframes icPop{0%{transform:scale(0);opacity:0}100%{transform:scale(1);opacity:1}}
.modal-ic.success{background:rgba(16,185,129,.15);color:#34d399;border:2px solid rgba(16,185,129,.3);}
.modal-ic.error{background:rgba(239,68,68,.15);color:#f87171;border:2px solid rgba(239,68,68,.3);}
.modal-ic.warning{background:rgba(245,158,11,.15);color:#fbbf24;border:2px solid rgba(245,158,11,.3);}
.modal-ic.blue{background:rgba(59,130,246,.15);color:#60a5fa;border:2px solid rgba(59,130,246,.3);}
.modal-ic.confirm{background:rgba(139,92,246,.15);color:#a78bfa;border:2px solid rgba(139,92,246,.3);}

.modal-info-card{background:rgba(255,255,255,.04);border-radius:10px;padding:10px;margin-bottom:10px;border:1px solid rgba(255,255,255,.06);}
.modal-info-row{display:flex;align-items:center;justify-content:space-between;padding:6px 0;border-bottom:1px solid rgba(255,255,255,.04);}
.modal-info-row:last-child{border-bottom:none;}
.modal-info-label{font-size:10px;font-weight:600;color:rgba(255,255,255,.5);display:flex;align-items:center;gap:5px;}
.modal-info-label i{font-size:14px;}
.modal-info-value{font-size:11px;font-weight:700;color:white;}

/* Toast */
.toast-notification{
    position:fixed;bottom:20px;right:20px;color:#fff;
    padding:10px 16px;border-radius:10px;display:flex;align-items:center;gap:8px;
    z-index:10000;animation:toastIn .3s ease;font-weight:600;font-size:12px;
    box-shadow:0 8px 20px rgba(0,0,0,.3);
}
.toast-notification.ok{background:linear-gradient(135deg,var(--sucesso,#10b981),#059669);}
.toast-notification.err{background:linear-gradient(135deg,var(--erro,#dc2626),#b91c1c);}
@keyframes toastIn{from{transform:translateX(110%);opacity:0}to{transform:translateX(0);opacity:1}}

@media(max-width:768px){
    .app-content{margin-left:0!important;}
    .content-wrapper{padding:10px!important;}
    .form-grid{grid-template-columns:1fr;}
    .action-buttons{flex-direction:column;}
    .btn{width:100%;}
    .stats-card{padding:14px;gap:14px;}
    .stats-card-icon{width:48px;height:48px;font-size:24px;}
    .stats-card-value{font-size:24px;}
    .mini-stats{flex-direction:row;flex-wrap:wrap;}
    .mini-stat{min-width:80px;padding:10px 8px;}
    .mini-stat-val{font-size:16px;}
    .dias-shortcuts{justify-content:center;}
}
</style>
</head>
<body class="<?php echo htmlspecialchars(getBodyClass($temaAtual ?? ['classe'=>'theme-dark'])); ?>">
<div class="app-content content">
<div class="content-overlay"></div>
<div class="content-wrapper">
<div class="content-body">

    <!-- ========== STATS CARD (cabeçalho igual criar usuário) ========== -->
    <div class="stats-card">
        <div class="stats-card-icon"><i class='bx bx-store-alt'></i></div>
        <div class="stats-card-content">
            <div class="stats-card-title">Editar Revenda</div>
            <div class="stats-card-value"><?php echo htmlspecialchars($rev['login']); ?></div>
            <div class="stats-card-subtitle">
                ID #<?php echo $id; ?> — Dono: <?php echo htmlspecialchars($dono_login); ?>
                <?php if ($suspenso): ?><span class="stats-badge stats-badge-suspended"><i class='bx bx-lock'></i> Suspenso</span>
                <?php elseif ($conta_vencida): ?><span class="stats-badge stats-badge-expired"><i class='bx bx-time'></i> Vencido</span>
                <?php else: ?><span class="stats-badge stats-badge-active"><i class='bx bx-check-circle'></i> Ativo</span>
                <?php endif; ?>
                <?php if ($tipo == 'Credito'): ?><span class="stats-badge stats-badge-credit"><i class='bx bx-infinite'></i> Crédito</span>
                <?php else: ?><span class="stats-badge stats-badge-validity"><i class='bx bx-calendar'></i> Validade</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="stats-card-decoration"><i class='bx bx-store-alt'></i></div>
    </div>

    <!-- ========== MINI STATS ========== -->
    <div class="mini-stats">
        <div class="mini-stat"><div class="mini-stat-val" style="color:#818cf8;"><?php echo $total_usuarios; ?></div><div class="mini-stat-lbl">Usuários</div></div>
        <div class="mini-stat"><div class="mini-stat-val" style="color:#34d399;"><?php echo $total_onlines; ?></div><div class="mini-stat-lbl">Onlines</div></div>
        <div class="mini-stat"><div class="mini-stat-val" style="color:#a78bfa;"><?php echo $total_revendas; ?></div><div class="mini-stat-lbl">Revendas</div></div>
        <div class="mini-stat"><div class="mini-stat-val" style="color:#fbbf24;"><?php echo $limite; ?></div><div class="mini-stat-lbl">Limite</div></div>
        <div class="mini-stat"><div class="mini-stat-val" style="color:#34d399;"><?php echo max(0,$limite_restante); ?></div><div class="mini-stat-lbl">Disponível</div></div>
        <div class="mini-stat"><div class="mini-stat-val" style="color:<?php echo $conta_vencida?'#f87171':'#fbbf24'; ?>;"><?php echo $tipo=='Credito'?'∞':($conta_vencida?'Exp.':$dias_restantes.'d'); ?></div><div class="mini-stat-lbl">Restante</div></div>
    </div>

    <!-- ========== FORMULÁRIO PRINCIPAL ========== -->
    <form method="POST" action="editarrevenda.php?id=<?php echo $id; ?>" id="formEditar">
    <input type="hidden" name="salvareditar" value="1">

    <!-- CARD: DADOS DA CONTA -->
    <div class="modern-card">
        <div class="card-header-custom primary">
            <div class="header-icon"><i class='bx bx-user'></i></div>
            <div><div class="header-title">Dados da Conta</div><div class="header-subtitle">Login, senha e contato do revendedor</div></div>
        </div>
        <div class="card-body">
            <div class="form-grid">
                <div class="form-field">
                    <label><i class='bx bx-user' style="color:#818cf8;"></i> Login</label>
                    <input type="text" name="usuarioedit" class="form-control" value="<?php echo htmlspecialchars($rev['login']); ?>" required placeholder="Login do revendedor">
                </div>
                <div class="form-field">
                    <label><i class='bx bx-lock-alt' style="color:#e879f9;"></i> Senha</label>
                    <input type="text" name="senhaedit" class="form-control" value="<?php echo htmlspecialchars($rev['senha']); ?>" required placeholder="Senha do revendedor">
                </div>
                <div class="form-field">
                    <label><i class='bx bxl-whatsapp' style="color:#25D366;"></i> WhatsApp</label>
                    <input type="text" name="whatsapp" class="form-control" value="<?php echo htmlspecialchars($rev['whatsapp'] ?? ''); ?>" placeholder="5511999999999">
                </div>
                <div class="form-field">
                    <label><i class='bx bx-crown' style="color:#a78bfa;"></i> Dono</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($dono_login); ?> (ID: <?php echo $rev['byid']; ?>)" disabled>
                </div>
            </div>
        </div>
    </div>

    <!-- CARD: LIMITE E VALIDADE -->
    <div class="modern-card">
        <div class="card-header-custom green">
            <div class="header-icon"><i class='bx bx-layer'></i></div>
            <div><div class="header-title">Limite e Validade</div><div class="header-subtitle">Configurações de limite e tempo de acesso</div></div>
        </div>
        <div class="card-body">
            <div class="form-grid">
                <div class="form-field">
                    <label><i class='bx bx-group' style="color:#34d399;"></i> Limite Total</label>
                    <input type="number" name="limiteedit" class="form-control" value="<?php echo $limite; ?>" min="0" required>
                    <span class="current-value"><i class='bx bx-info-circle'></i> Usando: <?php echo $limite_usado; ?> | Livre: <?php echo max(0,$limite_restante); ?></span>
                </div>

                <?php if ($tipo != 'Credito'): ?>
                <div class="form-field full-width">
                    <label><i class='bx bx-calendar-edit' style="color:#fbbf24;"></i> Dias de Validade</label>
                    <div class="dias-control">
                        <button type="button" class="dias-btn dias-minus" onclick="ajustarDias(-1)"><i class='bx bx-minus'></i></button>
                        <input type="number" name="validadeedit" id="inputDias" class="form-control dias-input" value="<?php echo max(0,$dias_restantes); ?>" min="0" placeholder="0" oninput="atualizarPreviewData()">
                        <button type="button" class="dias-btn dias-plus" onclick="ajustarDias(1)"><i class='bx bx-plus'></i></button>
                    </div>
                    <div class="dias-info">
                        <span class="current-value">
                            <i class='bx bx-calendar'></i> Atual: <?php echo $expira_formatada; ?>
                            <?php if ($conta_vencida): ?>
                                <span style="color:#f87171;font-weight:700;"> (Expirado há <?php echo abs($dias_restantes); ?> dias)</span>
                            <?php else: ?>
                                <span style="color:#34d399;font-weight:700;"> (<?php echo $dias_restantes; ?>d <?php echo $horas_restantes; ?>h restantes)</span>
                            <?php endif; ?>
                        </span>
                        <span class="preview-data" id="previewData"></span>
                    </div>
                    <div class="dias-shortcuts">
                        <button type="button" class="shortcut-btn" onclick="setDias(7)">7d</button>
                        <button type="button" class="shortcut-btn" onclick="setDias(15)">15d</button>
                        <button type="button" class="shortcut-btn" onclick="setDias(30)">30d</button>
                        <button type="button" class="shortcut-btn" onclick="setDias(60)">60d</button>
                        <button type="button" class="shortcut-btn" onclick="setDias(90)">90d</button>
                        <button type="button" class="shortcut-btn" onclick="setDias(180)">180d</button>
                        <button type="button" class="shortcut-btn" onclick="setDias(365)">1 ano</button>
                    </div>
                    <span class="form-hint">💡 Digite os dias ou use os botões. Nova data calculada a partir de hoje.</span>
                </div>
                <?php endif; ?>

                <div class="form-field">
                    <label><i class='bx bx-dollar' style="color:#34d399;"></i> Valor Mensal (R$)</label>
                    <input type="text" name="valormensal" class="form-control" value="<?php echo $valormensal; ?>" placeholder="0.00">
                </div>
                <div class="form-field">
                    <label><i class='bx bx-category' style="color:#f472b6;"></i> Categoria</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($categoria_nome); ?>" disabled>
                </div>
            </div>
            <div class="action-buttons">
                <a href="javascript:history.back()" class="btn btn-cancel"><i class='bx bx-x'></i> Cancelar</a>
                <button type="submit" class="btn btn-success"><i class='bx bx-check'></i> Salvar Alterações</button>
            </div>
        </div>
    </div>
    </form>

    <!-- CARD: ALTERAR MODO -->
    <div class="modern-card">
        <div class="card-header-custom orange">
            <div class="header-icon"><i class='bx bx-transfer'></i></div>
            <div><div class="header-title">Alterar Modo</div><div class="header-subtitle">Trocar entre Crédito e Validade</div></div>
        </div>
        <div class="card-body">
            <div class="info-inline">
                <i class='bx bx-info-circle'></i>
                <span>Modo atual: <strong><?php echo $tipo; ?></strong> — Alterar afeta todos os sub-revendedores.</span>
            </div>
            <form method="POST" action="editarrevenda.php?id=<?php echo $id; ?>" id="formModo">
            <input type="hidden" name="alterarmodo" value="1">
            <div class="form-grid">
                <div class="form-field">
                    <label><i class='bx bx-transfer' style="color:#fbbf24;"></i> Novo Modo</label>
                    <select name="modorevenda" class="form-control" required>
                        <option value="Validade" <?php echo $tipo=='Validade'?'selected':''; ?>>📅 Validade</option>
                        <option value="Credito" <?php echo $tipo=='Credito'?'selected':''; ?>>♾️ Crédito</option>
                    </select>
                </div>
                <div class="form-field" style="justify-content:flex-end;">
                    <button type="button" class="btn btn-warning" onclick="confirmarModo()"><i class='bx bx-transfer'></i> Alterar Modo</button>
                </div>
            </div>
            </form>
        </div>
    </div>

    <!-- CARD: ADICIONAR CRÉDITOS -->
    <div class="modern-card">
        <div class="card-header-custom blue">
            <div class="header-icon"><i class='bx bx-plus-circle'></i></div>
            <div><div class="header-title">Adicionar Créditos</div><div class="header-subtitle">Adicionar limite extra ao revendedor</div></div>
        </div>
        <div class="card-body">
            <form method="POST" action="editarrevenda.php?id=<?php echo $id; ?>" id="formCreditos">
            <input type="hidden" name="addcreditos" value="1">
            <div class="form-grid">
                <div class="form-field">
                    <label><i class='bx bx-plus' style="color:#60a5fa;"></i> Quantidade</label>
                    <input type="number" name="quantidadecreditosadd" class="form-control" min="1" placeholder="Ex: 50" required>
                    <span class="current-value"><i class='bx bx-layer'></i> Limite atual: <?php echo $limite; ?></span>
                </div>
                <div class="form-field" style="justify-content:flex-end;">
                    <button type="button" class="btn btn-blue" onclick="confirmarCreditos()"><i class='bx bx-plus-circle'></i> Adicionar</button>
                </div>
            </div>
            </form>
        </div>
    </div>

</div></div></div>

<!-- ========== MODAL: CONFIRMAR MODO ========== -->
<div id="modalConfirmModo" class="modal-overlay">
<div class="modal-container"><div class="modal-content">
    <div class="modal-header warning"><h5><i class='bx bx-transfer'></i> Alterar Modo</h5><button class="modal-close" onclick="fecharModal('modalConfirmModo')"><i class='bx bx-x'></i></button></div>
    <div class="modal-body" style="text-align:center;">
        <div class="modal-ic warning"><i class='bx bx-transfer'></i></div>
        <p style="font-size:16px;font-weight:700;margin-bottom:6px;">Tem certeza?</p>
        <p style="font-size:12px;color:rgba(255,255,255,.6);line-height:1.7;">Alterar o modo afetará <strong style="color:white;">todos os sub-revendedores</strong> deste revendedor.<br>Modo atual: <strong style="color:#fbbf24;"><?php echo $tipo; ?></strong></p>
    </div>
    <div class="modal-footer">
        <button class="btn btn-cancel" onclick="fecharModal('modalConfirmModo')">Cancelar</button>
        <button class="btn btn-warning" onclick="fecharModal('modalConfirmModo');document.getElementById('formModo').submit();">Confirmar</button>
    </div>
</div></div>
</div>

<!-- ========== MODAL: CONFIRMAR CRÉDITOS ========== -->
<div id="modalConfirmCreditos" class="modal-overlay">
<div class="modal-container"><div class="modal-content">
    <div class="modal-header blue"><h5><i class='bx bx-plus-circle'></i> Adicionar Créditos</h5><button class="modal-close" onclick="fecharModal('modalConfirmCreditos')"><i class='bx bx-x'></i></button></div>
    <div class="modal-body" style="text-align:center;">
        <div class="modal-ic blue"><i class='bx bx-plus-circle'></i></div>
        <p style="font-size:16px;font-weight:700;margin-bottom:6px;">Confirmar Créditos</p>
        <p style="font-size:12px;color:rgba(255,255,255,.6);line-height:1.7;">Adicionar <strong style="color:white;" id="qtdCreditos">0</strong> créditos ao revendedor <strong style="color:#a78bfa;"><?php echo htmlspecialchars($rev['login']); ?></strong>?<br>Limite atual: <strong style="color:#fbbf24;"><?php echo $limite; ?></strong> → Novo: <strong style="color:#34d399;" id="novoLimite"><?php echo $limite; ?></strong></p>
    </div>
    <div class="modal-footer">
        <button class="btn btn-cancel" onclick="fecharModal('modalConfirmCreditos')">Cancelar</button>
        <button class="btn btn-blue" onclick="fecharModal('modalConfirmCreditos');document.getElementById('formCreditos').submit();">Adicionar</button>
    </div>
</div></div>
</div>

<!-- ========== MODAL: RESULTADO ========== -->
<div id="modalResultado" class="modal-overlay">
<div class="modal-container"><div class="modal-content">
    <div class="modal-header" id="resultHdr"><h5 id="resultHdrTitle"></h5><button class="modal-close" onclick="fecharResultado()"><i class='bx bx-x'></i></button></div>
    <div class="modal-body" style="text-align:center;">
        <div class="modal-ic" id="resultIc"></div>
        <p style="font-size:16px;font-weight:700;margin-bottom:6px;" id="resultTitle"></p>
        <p style="font-size:12px;color:rgba(255,255,255,.6);line-height:1.7;" id="resultText"></p>
    </div>
    <div class="modal-footer"><button class="btn" id="resultBtn" onclick="fecharResultado()">OK</button></div>
</div></div>
</div>

<script>
// ========== MODAIS ==========
function abrirModal(id){document.getElementById(id).classList.add('show');}
function fecharModal(id){document.getElementById(id).classList.remove('show');}
document.querySelectorAll('.modal-overlay').forEach(function(o){o.addEventListener('click',function(e){if(e.target===o)o.classList.remove('show');});});
document.addEventListener('keydown',function(e){if(e.key==='Escape')document.querySelectorAll('.modal-overlay.show').forEach(function(m){m.classList.remove('show');});});

function confirmarModo(){abrirModal('modalConfirmModo');}

function confirmarCreditos(){
    var qtd=parseInt(document.querySelector('input[name="quantidadecreditosadd"]').value)||0;
    if(qtd<1){mostrarResultado('error','Erro!','Informe uma quantidade válida (mínimo 1).');return;}
    document.getElementById('qtdCreditos').textContent=qtd;
    document.getElementById('novoLimite').textContent=<?php echo $limite; ?>+qtd;
    abrirModal('modalConfirmCreditos');
}

function mostrarResultado(tipo,titulo,texto){
    document.querySelectorAll('.modal-overlay.show').forEach(function(m){m.classList.remove('show');});
    var hdr=document.getElementById('resultHdr'),ht=document.getElementById('resultHdrTitle'),
        ic=document.getElementById('resultIc'),t=document.getElementById('resultTitle'),
        tx=document.getElementById('resultText'),btn=document.getElementById('resultBtn');
    var map={
        success:{hc:'modal-header success',ht:'<i class="bx bx-check-circle"></i> Sucesso',ic:'modal-ic success',i:'bx-check-circle',bc:'btn btn-success'},
        error:{hc:'modal-header error',ht:'<i class="bx bx-error-circle"></i> Erro',ic:'modal-ic error',i:'bx-error-circle',bc:'btn btn-danger'},
        warning:{hc:'modal-header warning',ht:'<i class="bx bx-error"></i> Aviso',ic:'modal-ic warning',i:'bx-error',bc:'btn btn-warning'},
    };
    var m=map[tipo]||map.warning;
    hdr.className=m.hc;ht.innerHTML=m.ht;ic.className=m.ic;ic.innerHTML='<i class="bx '+m.i+'"></i>';btn.className=m.bc;
    t.textContent=titulo;tx.innerHTML=texto;
    ic.style.animation='none';ic.offsetHeight;ic.style.animation='';
    abrirModal('modalResultado');
}
function fecharResultado(){fecharModal('modalResultado');}

// ========== CONTROLE DE DIAS ==========
function ajustarDias(delta){
    var input=document.getElementById('inputDias');if(!input)return;
    var val=parseInt(input.value)||0;val+=delta;if(val<0)val=0;input.value=val;
    atualizarPreviewData();highlightShortcut(val);
    var btn=delta>0?document.querySelector('.dias-plus'):document.querySelector('.dias-minus');
    btn.style.transform='scale(1.15)';setTimeout(function(){btn.style.transform='';},150);
}

function setDias(dias){
    var input=document.getElementById('inputDias');if(!input)return;input.value=dias;
    atualizarPreviewData();highlightShortcut(dias);
    input.style.color='#a78bfa';input.style.transform='scale(1.1)';
    setTimeout(function(){input.style.color='';input.style.transform='';},200);
}

function atualizarPreviewData(){
    var input=document.getElementById('inputDias'),preview=document.getElementById('previewData');
    if(!input||!preview)return;var dias=parseInt(input.value)||0;
    if(dias<=0){preview.innerHTML='';return;}
    var hoje=new Date(),nova=new Date(hoje.getTime()+(dias*86400000));
    var dd=String(nova.getDate()).padStart(2,'0'),mm=String(nova.getMonth()+1).padStart(2,'0'),
        yyyy=nova.getFullYear(),hh=String(nova.getHours()).padStart(2,'0'),min=String(nova.getMinutes()).padStart(2,'0');
    var meses=Math.floor(dias/30),diasR=dias%30,tempoTexto='';
    if(meses>0)tempoTexto+=meses+(meses===1?' mês':' meses');
    if(meses>0&&diasR>0)tempoTexto+=' e ';
    if(diasR>0||meses===0)tempoTexto+=diasR+'d';
    preview.innerHTML='<i class="bx bx-calendar-check" style="font-size:12px;"></i> Nova data: <strong>'+dd+'/'+mm+'/'+yyyy+' '+hh+':'+min+'</strong> ('+tempoTexto+')';
}

function highlightShortcut(dias){
    document.querySelectorAll('.shortcut-btn').forEach(function(btn){btn.classList.remove('active-shortcut');});
    document.querySelectorAll('.shortcut-btn').forEach(function(btn){
        var txt=btn.textContent.trim(),val=txt==='1 ano'?365:parseInt(txt);
        if(val===dias)btn.classList.add('active-shortcut');
    });
}

// Segurar botão + e -
(function(){
    var holdTimer=null,holdInterval=null;
    document.querySelectorAll('.dias-btn').forEach(function(btn){
        var delta=btn.classList.contains('dias-plus')?1:-1;
        function startH(){holdTimer=setTimeout(function(){holdInterval=setInterval(function(){ajustarDias(delta);},80);},400);}
        function stopH(){clearTimeout(holdTimer);clearInterval(holdInterval);}
        btn.addEventListener('mousedown',function(e){e.preventDefault();startH();});
        btn.addEventListener('mouseup',stopH);btn.addEventListener('mouseleave',stopH);
        btn.addEventListener('touchstart',function(e){e.preventDefault();ajustarDias(delta);startH();});
        btn.addEventListener('touchend',stopH);btn.addEventListener('touchcancel',stopH);
    });
})();

// Toast
function mostrarToast(msg,tipo){
    var t=document.createElement('div');t.className='toast-notification '+(tipo||'ok');
    t.innerHTML='<i class="bx '+(tipo==='err'?'bx-error-circle':'bx-check-circle')+'"></i> '+msg;
    document.body.appendChild(t);setTimeout(function(){t.remove();},3500);
}

// Init
document.addEventListener('DOMContentLoaded',function(){
    atualizarPreviewData();
    var input=document.getElementById('inputDias');
    if(input)highlightShortcut(parseInt(input.value)||0);
});

// Resultado do POST
<?php if (!empty($msg_tipo)): ?>
document.addEventListener('DOMContentLoaded',function(){
    mostrarResultado('<?php echo $msg_tipo; ?>','<?php echo addslashes($msg_titulo); ?>','<?php echo addslashes($msg_texto); ?>');
});
<?php endif; ?>
</script>
</body>
</html>
<?php
  

