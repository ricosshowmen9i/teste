<?php // @ioncube.dk $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf -> "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs" RANDOM
    $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf = "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs";
    function aleatorio813363($input)
    {
        ?>
<?php
error_reporting(0);
session_start();
include('../AegisCore/conexao.php');
include('headeradmin2.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

if (file_exists('../AegisCore/temas.php')) {
    include_once '../AegisCore/temas.php';
    $temaAtual = initTemas($conn);
} else { $temaAtual = []; }

if (!file_exists('suspenderrev.php')) { exit("<script>alert('Token Invalido!');</script>"); }
else { include_once 'suspenderrev.php'; }

if (!isset($_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe']) || !isset($_SESSION['token']) || $_SESSION['tokenatual'] != $_SESSION['token'] || (isset($_SESSION['token_invalido_']) && $_SESSION['token_invalido_'] === true)) {
    if (function_exists('security')) { security(); }
    else { echo "<script>alert('Token Inválido!');location.href='../index.php';</script>"; $_SESSION['token_invalido_'] = true; exit; }
}

function anti_sql($input) {
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function($m){ return ''; }, $input);
    return addslashes(strip_tags(trim($seg)));
}

function gerarCupom($tamanho = 8) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $ret = '';
    for ($i = 0; $i < $tamanho; $i++) $ret .= $chars[mt_rand(0, strlen($chars) - 1)];
    return $ret;
}

$cupon = gerarCupom(8);
$msg_sucesso = ''; $msg_erro = '';
$show_success = false; $show_error = false;

// ADICIONAR
if (isset($_POST['adicionarcupom'])) {
    $nome = anti_sql($_POST['nome']);
    $cupom = anti_sql($_POST['cupom']);
    $desconto = intval($_POST['desconto']);
    $vezesuso = intval($_POST['vezesuso']);

    if (empty($nome)) { $msg_erro = "Nome é obrigatório!"; $show_error = true; }
    elseif (empty($cupom)) { $msg_erro = "Código do cupom é obrigatório!"; $show_error = true; }
    elseif ($desconto < 1 || $desconto > 100) { $msg_erro = "Desconto deve ser entre 1% e 100%!"; $show_error = true; }
    elseif ($vezesuso < 1) { $msg_erro = "Limite de uso deve ser pelo menos 1!"; $show_error = true; }
    else {
        // Verificar duplicata
        $check = $conn->query("SELECT id FROM cupons WHERE cupom='$cupom' AND byid='" . $_SESSION['iduser'] . "'");
        if ($check && $check->num_rows > 0) { $msg_erro = "Este código de cupom já existe!"; $show_error = true; }
        else {
            $r = $conn->query("INSERT INTO cupons (nome, cupom, desconto, byid, usado, vezesuso) VALUES ('$nome', '$cupom', '$desconto', '" . $_SESSION['iduser'] . "', '0', '$vezesuso')");
            if ($r) { $msg_sucesso = "Cupom \"$nome\" criado com sucesso!"; $show_success = true; }
            else { $msg_erro = "Erro ao criar cupom!"; $show_error = true; }
        }
    }
}

// EDITAR
if (isset($_POST['editarcupom'])) {
    $id = intval($_POST['cupom_id']);
    $nome = anti_sql($_POST['nome']);
    $cupom = anti_sql($_POST['cupom']);
    $desconto = intval($_POST['desconto']);
    $vezesuso = intval($_POST['vezesuso']);

    $r = $conn->query("UPDATE cupons SET nome='$nome', cupom='$cupom', desconto='$desconto', vezesuso='$vezesuso' WHERE id='$id' AND byid='" . $_SESSION['iduser'] . "'");
    if ($r) { $msg_sucesso = "Cupom atualizado!"; $show_success = true; }
    else { $msg_erro = "Erro ao atualizar!"; $show_error = true; }
}

// DELETAR
if (isset($_POST['deletar'])) {
    $id = intval($_POST['id']);
    $r = $conn->query("DELETE FROM cupons WHERE id='$id' AND byid='" . $_SESSION['iduser'] . "'");
    if ($r) { $msg_sucesso = "Cupom excluído!"; $show_success = true; }
    else { $msg_erro = "Erro ao excluir!"; $show_error = true; }
}

// RESETAR USOS
if (isset($_POST['resetar_usos'])) {
    $id = intval($_POST['cupom_id']);
    $r = $conn->query("UPDATE cupons SET usado='0' WHERE id='$id' AND byid='" . $_SESSION['iduser'] . "'");
    if ($r) { $msg_sucesso = "Usos do cupom resetados!"; $show_success = true; }
    else { $msg_erro = "Erro ao resetar!"; $show_error = true; }
}

// BUSCAR CUPONS
$result = $conn->query("SELECT * FROM cupons WHERE byid='" . $_SESSION['iduser'] . "' ORDER BY id DESC");
$cupons = [];
$total_cupons = 0; $total_ativos = 0; $total_esgotados = 0; $total_usos = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $cupons[] = $row;
    $total_cupons++;
    $total_usos += intval($row['usado'] ?? 0);
    $usos = intval($row['usado'] ?? 0);
    $limite = intval($row['vezesuso']);
    if ($limite > 0 && $usos >= $limite) $total_esgotados++;
    else $total_ativos++;
}

date_default_timezone_set('America/Sao_Paulo');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cupons de Desconto</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
<style>
<?php
if (function_exists('getCSSVariables')) { echo getCSSVariables($temaAtual); }
else { echo ':root{--primaria:#4158D0;--secundaria:#C850C0;--fundo:#0f172a;--fundo_claro:#1e293b;--texto:#ffffff;--sucesso:#10b981;--erro:#dc2626;--aviso:#f59e0b;--info:#3b82f6;}'; }
?>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Inter',sans-serif;background:var(--fundo,#0f172a);color:var(--texto,#fff);min-height:100vh;}
.app-content{margin-left:-670px!important;padding:0!important;}
.content-wrapper{max-width:1000px;margin:0 auto!important;padding:20px!important;}

/* Stats */
.stats-card{background:linear-gradient(135deg,var(--fundo_claro,#1e293b),var(--fundo,#0f172a));border-radius:20px;padding:20px 24px;margin-bottom:20px;border:1px solid rgba(139,92,246,0.15);display:flex;align-items:center;gap:20px;position:relative;overflow:hidden;transition:all .3s;}
.stats-card:hover{transform:translateY(-2px);border-color:#8b5cf6;}
.stats-card-icon{width:60px;height:60px;background:linear-gradient(135deg,#8b5cf6,#a78bfa);border-radius:18px;display:flex;align-items:center;justify-content:center;font-size:32px;color:white;flex-shrink:0;}
.stats-card-content{flex:1;}
.stats-card-title{font-size:13px;font-weight:600;color:rgba(255,255,255,0.5);text-transform:uppercase;margin-bottom:5px;}
.stats-card-value{font-size:36px;font-weight:800;background:linear-gradient(135deg,#a78bfa,#c4b5fd);-webkit-background-clip:text;-webkit-text-fill-color:transparent;line-height:1;}
.stats-card-subtitle{font-size:12px;color:rgba(255,255,255,0.4);margin-top:4px;}
.stats-card-decoration{position:absolute;right:20px;top:50%;transform:translateY(-50%);font-size:80px;opacity:0.05;}

/* Mini Stats */
.mini-stats{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;}
.mini-stat{flex:1;min-width:80px;background:rgba(255,255,255,0.04);border-radius:12px;padding:12px;border:1px solid rgba(255,255,255,0.06);text-align:center;transition:all .2s;}
.mini-stat:hover{border-color:#8b5cf6;transform:translateY(-2px);}
.mini-stat-val{font-size:18px;font-weight:800;}
.mini-stat-lbl{font-size:9px;color:rgba(255,255,255,0.35);text-transform:uppercase;margin-top:2px;}

/* Modern Card */
.modern-card{background:var(--fundo_claro,#1e293b);border-radius:16px;border:1px solid rgba(255,255,255,0.08);overflow:hidden;margin-bottom:16px;transition:all .2s;}
.modern-card:hover{border-color:#8b5cf6;}
.card-header-custom{padding:14px 18px;display:flex;align-items:center;gap:12px;}
.card-header-custom.violet{background:linear-gradient(135deg,#8b5cf6,#7c3aed);}
.card-header-custom.green{background:linear-gradient(135deg,#10b981,#059669);}
.card-header-custom.error{background:linear-gradient(135deg,#dc2626,#b91c1c);}
.card-header-custom.warning{background:linear-gradient(135deg,#f59e0b,#f97316);}
.card-header-custom.processing{background:linear-gradient(135deg,#8b5cf6,#7c3aed);}
.header-icon{width:36px;height:36px;background:rgba(255,255,255,0.2);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;color:white;}
.header-info{flex:1;}
.header-title{font-size:14px;font-weight:700;color:white;}
.header-subtitle{font-size:10px;color:rgba(255,255,255,0.7);}
.card-body-custom{padding:16px;}

/* Create form */
.create-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.create-full{grid-column:1/-1;}
.form-group{margin-bottom:0;}
.form-label{display:flex;align-items:center;gap:4px;font-size:9px;font-weight:700;color:rgba(255,255,255,0.4);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;}
.form-label i{font-size:12px;}
.form-control{width:100%;padding:8px 12px;background:rgba(255,255,255,0.06);border:1.5px solid rgba(255,255,255,0.08);border-radius:9px;color:#fff;font-size:12px;font-family:inherit;outline:none;transition:all .25s;}
.form-control:focus{border-color:#8b5cf6;background:rgba(255,255,255,0.09);}
.form-control::placeholder{color:rgba(255,255,255,0.2);}
select.form-control{cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%23ffffff' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;}
select.form-control option{background:#1e293b;color:#fff;}
.form-control.mono{font-family:'Courier New',monospace;letter-spacing:1px;font-weight:700;font-size:13px;}

/* Code preview */
.code-preview{display:flex;align-items:center;gap:8px;}
.code-preview .form-control{flex:1;}
.btn-regen{background:rgba(139,92,246,0.15);border:1px solid rgba(139,92,246,0.25);border-radius:9px;padding:8px 12px;cursor:pointer;color:#a78bfa;font-size:14px;display:flex;align-items:center;transition:all .2s;}
.btn-regen:hover{background:rgba(139,92,246,0.25);transform:translateY(-1px);}

/* Slider */
.slider-container{display:flex;align-items:center;gap:10px;}
.slider{-webkit-appearance:none;width:100%;height:6px;background:rgba(255,255,255,0.08);border-radius:10px;outline:none;}
.slider::-webkit-slider-thumb{-webkit-appearance:none;width:18px;height:18px;border-radius:50%;background:linear-gradient(135deg,#8b5cf6,#a78bfa);cursor:pointer;border:2px solid rgba(255,255,255,0.2);}
.slider::-moz-range-thumb{width:18px;height:18px;border-radius:50%;background:linear-gradient(135deg,#8b5cf6,#a78bfa);cursor:pointer;border:2px solid rgba(255,255,255,0.2);}
.slider-value{background:rgba(139,92,246,0.15);border:1px solid rgba(139,92,246,0.25);border-radius:8px;padding:5px 10px;font-weight:800;font-size:13px;color:#a78bfa;min-width:52px;text-align:center;}

/* Submit */
.btn-submit{width:100%;padding:10px 16px;border:none;border-radius:10px;font-weight:700;font-size:12px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;color:white;transition:all .2s;font-family:inherit;background:linear-gradient(135deg,#8b5cf6,#7c3aed);margin-top:4px;}
.btn-submit:hover{transform:translateY(-1px);filter:brightness(1.08);box-shadow:0 6px 16px rgba(139,92,246,0.3);}

/* Cupons Grid */
.cupons-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px;}

/* Cupom Card */
.cupom-card{background:var(--fundo_claro,#1e293b);border-radius:14px;overflow:hidden;border:1px solid rgba(139,92,246,0.12);transition:all .25s;position:relative;}
.cupom-card:hover{transform:translateY(-3px);border-color:#8b5cf6;box-shadow:0 8px 25px rgba(139,92,246,0.12);}
.cupom-card.esgotado{opacity:.55;border-color:rgba(100,116,139,0.2);}
.cupom-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,#8b5cf6,#a78bfa,#c4b5fd,#a78bfa,#8b5cf6);background-size:200% 100%;animation:shimmerViolet 3s linear infinite;}
@keyframes shimmerViolet{0%{background-position:200% 0;}100%{background-position:-200% 0;}}

.cupom-header{background:linear-gradient(135deg,#8b5cf6,#7c3aed);padding:12px;display:flex;align-items:center;justify-content:space-between;}
.cupom-header.esgotado{background:linear-gradient(135deg,#475569,#334155);}
.cupom-header-info{display:flex;align-items:center;gap:10px;flex:1;min-width:0;}
.cupom-icon{width:36px;height:36px;background:rgba(255,255,255,0.2);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;color:white;flex-shrink:0;}
.cupom-text{flex:1;min-width:0;}
.cupom-nome{font-size:14px;font-weight:700;color:white;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.cupom-tipo{font-size:9px;color:rgba(255,255,255,0.7);margin-top:1px;}
.cupom-desconto-big{background:rgba(255,255,255,0.2);border-radius:10px;padding:4px 10px;font-size:16px;font-weight:800;color:white;flex-shrink:0;}

.cupom-body{padding:12px;}

/* Code box */
.code-box{background:rgba(139,92,246,0.06);border:1px dashed rgba(139,92,246,0.25);border-radius:10px;padding:10px;text-align:center;margin-bottom:10px;position:relative;}
.code-label{font-size:8px;color:rgba(255,255,255,0.35);text-transform:uppercase;font-weight:600;margin-bottom:4px;}
.code-value{font-family:'Courier New',monospace;font-size:18px;font-weight:800;color:#a78bfa;letter-spacing:2px;}
.btn-copy-code{position:absolute;top:8px;right:8px;background:rgba(139,92,246,0.15);border:none;border-radius:6px;padding:4px 8px;font-size:10px;cursor:pointer;color:#a78bfa;display:flex;align-items:center;gap:3px;transition:all .2s;font-family:inherit;font-weight:600;}
.btn-copy-code:hover{background:rgba(139,92,246,0.25);}
.btn-copy-code.copied{background:#10b981;color:white;}

/* Usage bar */
.usage-bar{margin-bottom:10px;}
.usage-header{display:flex;justify-content:space-between;margin-bottom:4px;}
.usage-label{font-size:9px;color:rgba(255,255,255,0.4);font-weight:600;}
.usage-count{font-size:10px;font-weight:700;}
.usage-count.ok{color:#34d399;} .usage-count.warn{color:#fbbf24;} .usage-count.full{color:#f87171;}
.usage-track{height:6px;background:rgba(255,255,255,0.06);border-radius:10px;overflow:hidden;}
.usage-fill{height:100%;border-radius:10px;transition:width .6s ease;}

/* Info grid */
.cupom-info-grid{display:grid;grid-template-columns:1fr 1fr;gap:5px;margin-bottom:8px;}
.cupom-info-row{display:flex;align-items:center;gap:5px;padding:5px 7px;background:rgba(255,255,255,0.03);border-radius:7px;}
.cupom-info-icon{width:22px;height:22px;background:rgba(255,255,255,0.05);border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0;}
.cupom-info-content{flex:1;min-width:0;}
.cupom-info-label{font-size:8px;color:rgba(255,255,255,0.4);font-weight:600;}
.cupom-info-value{font-size:10px;font-weight:600;}

/* Status chip */
.status-chip{display:inline-flex;align-items:center;gap:3px;padding:3px 8px;border-radius:16px;font-size:9px;font-weight:600;}
.chip-ativo{background:rgba(16,185,129,0.15);color:#34d399;border:1px solid rgba(16,185,129,0.2);}
.chip-esgotado{background:rgba(239,68,68,0.15);color:#f87171;border:1px solid rgba(239,68,68,0.2);}

/* Actions */
.cupom-actions{display:flex;gap:5px;flex-wrap:wrap;}
.action-btn{flex:1;min-width:55px;padding:6px 6px;border:none;border-radius:8px;font-weight:600;font-size:9px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:3px;color:white;transition:all .2s;font-family:inherit;}
.action-btn:hover{transform:translateY(-1px);filter:brightness(1.08);}
.action-btn:active{transform:scale(0.95);}
.btn-edit{background:linear-gradient(135deg,#4158D0,#6366f1);}
.btn-reset{background:linear-gradient(135deg,#f59e0b,#f97316);}
.btn-copy{background:linear-gradient(135deg,#06b6d4,#0891b2);}
.btn-danger{background:linear-gradient(135deg,#dc2626,#b91c1c);}

/* Empty */
.empty-state{grid-column:1/-1;text-align:center;padding:50px 20px;background:var(--fundo_claro,#1e293b);border-radius:16px;border:1px solid rgba(255,255,255,0.08);}
.empty-state-icon{width:80px;height:80px;border-radius:50%;background:rgba(139,92,246,0.1);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:40px;color:#a78bfa;border:2px solid rgba(139,92,246,0.2);}
.empty-state h3{font-size:16px;margin-bottom:6px;}
.empty-state p{font-size:11px;color:rgba(255,255,255,0.3);}

/* Search */
.search-row{display:flex;gap:10px;margin-bottom:14px;flex-wrap:wrap;}
.search-row .form-control{flex:1;min-width:150px;}

/* Modal */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.85);display:none;align-items:center;justify-content:center;z-index:9999;backdrop-filter:blur(8px);padding:16px;}
.modal-overlay.show{display:flex;}
.modal-container{animation:modalIn .3s ease;max-width:480px;width:92%;}
@keyframes modalIn{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
.modal-content-custom{background:var(--fundo_claro,#1e293b);border-radius:20px;overflow:hidden;border:1px solid rgba(255,255,255,.1);box-shadow:0 25px 60px rgba(0,0,0,.5);}
.modal-header-custom{padding:14px 18px;display:flex;align-items:center;justify-content:space-between;}
.modal-header-custom h5{margin:0;display:flex;align-items:center;gap:8px;font-size:14px;font-weight:700;color:#fff;}
.modal-close{background:rgba(255,255,255,.15);border:none;color:#fff;font-size:18px;cursor:pointer;width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;transition:all .2s;}
.modal-close:hover{background:rgba(255,255,255,.25);transform:rotate(90deg);}
.modal-body-custom{padding:18px;max-height:70vh;overflow-y:auto;}
.modal-footer-custom{border-top:1px solid rgba(255,255,255,.07);padding:12px 18px;display:flex;justify-content:center;gap:8px;flex-wrap:wrap;}
.modal-ic{width:70px;height:70px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:34px;animation:icPop .5s cubic-bezier(0.34,1.56,0.64,1) .15s both;}
@keyframes icPop{0%{transform:scale(0);opacity:0}100%{transform:scale(1);opacity:1}}
.modal-ic.success{background:rgba(16,185,129,.15);color:#34d399;border:2px solid rgba(16,185,129,.3);}
.modal-ic.error{background:rgba(239,68,68,.15);color:#f87171;border:2px solid rgba(239,68,68,.3);}
.modal-ic.warning{background:rgba(245,158,11,.15);color:#fbbf24;border:2px solid rgba(245,158,11,.3);}
.modal-info-card{background:rgba(255,255,255,.04);border-radius:12px;padding:12px;margin-bottom:12px;border:1px solid rgba(255,255,255,.06);}
.modal-info-row{display:flex;align-items:center;gap:8px;padding:4px 0;}
.modal-info-row i{font-size:14px;width:18px;text-align:center;}
.modal-info-row span{font-size:12px;color:rgba(255,255,255,.7);}
.modal-info-row strong{font-size:12px;color:#fff;}
.btn-modal{padding:8px 16px;border:none;border-radius:10px;font-weight:600;font-size:12px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;color:white;transition:all .2s;font-family:inherit;}
.btn-modal:hover{transform:translateY(-1px);filter:brightness(1.08);}
.btn-modal-cancel{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);}
.btn-modal-cancel:hover{background:rgba(255,255,255,.15);}
.btn-modal-ok{background:linear-gradient(135deg,#10b981,#059669);}
.btn-modal-danger{background:linear-gradient(135deg,#dc2626,#b91c1c);}
.btn-modal-warning{background:linear-gradient(135deg,#f59e0b,#f97316);}
.btn-modal-primary{background:linear-gradient(135deg,#8b5cf6,#7c3aed);}
.spinner-wrap{display:flex;flex-direction:column;align-items:center;gap:14px;padding:20px 0;}
.spinner-ring{width:44px;height:44px;border:3px solid rgba(255,255,255,.08);border-top-color:#8b5cf6;border-right-color:#a78bfa;border-radius:50%;animation:spin .8s linear infinite;}
@keyframes spin{to{transform:rotate(360deg)}}

/* Toast */
.toast-notification{position:fixed;bottom:20px;right:20px;color:#fff;padding:10px 16px;border-radius:10px;display:flex;align-items:center;gap:8px;z-index:10001;animation:toastIn .3s ease;font-weight:600;font-size:12px;box-shadow:0 8px 20px rgba(0,0,0,.3);}
.toast-notification.ok{background:linear-gradient(135deg,#10b981,#059669);}
.toast-notification.err{background:linear-gradient(135deg,#dc2626,#b91c1c);}
@keyframes toastIn{from{transform:translateX(110%);opacity:0}to{transform:translateX(0);opacity:1}}

.pagination-info{text-align:center;margin-top:16px;color:rgba(255,255,255,0.3);font-size:10px;}

@media(max-width:768px){
    .app-content{margin-left:0!important;}
    .content-wrapper{padding:10px!important;}
    .cupons-grid{grid-template-columns:1fr;}
    .stats-card{padding:14px;gap:14px;}
    .stats-card-icon{width:48px;height:48px;font-size:24px;}
    .stats-card-value{font-size:28px;}
    .mini-stats{flex-wrap:wrap;}
    .mini-stat{min-width:70px;}
    .create-grid{grid-template-columns:1fr;}
    .cupom-actions{display:grid;grid-template-columns:repeat(2,1fr);}
    .search-row{flex-direction:column;}
}
</style>
</head>
<body class="<?php echo htmlspecialchars(getBodyClass($temaAtual ?? ['classe'=>'theme-dark'])); ?>">
<div class="app-content content">
<div class="content-overlay"></div>
<div class="content-wrapper">

    <!-- Stats -->
    <div class="stats-card">
        <div class="stats-card-icon"><i class='bx bx-purchase-tag'></i></div>
        <div class="stats-card-content">
            <div class="stats-card-title">Cupons de Desconto</div>
            <div class="stats-card-value"><?php echo $total_cupons; ?> Cupons</div>
            <div class="stats-card-subtitle">Gerencie seus cupons promocionais</div>
        </div>
        <div class="stats-card-decoration"><i class='bx bx-purchase-tag'></i></div>
    </div>

    <!-- Mini Stats -->
    <div class="mini-stats">
        <div class="mini-stat"><div class="mini-stat-val" style="color:#a78bfa;"><?php echo $total_cupons; ?></div><div class="mini-stat-lbl">Total</div></div>
        <div class="mini-stat"><div class="mini-stat-val" style="color:#34d399;"><?php echo $total_ativos; ?></div><div class="mini-stat-lbl">Ativos</div></div>
        <div class="mini-stat"><div class="mini-stat-val" style="color:#f87171;"><?php echo $total_esgotados; ?></div><div class="mini-stat-lbl">Esgotados</div></div>
        <div class="mini-stat"><div class="mini-stat-val" style="color:#fbbf24;"><?php echo $total_usos; ?></div><div class="mini-stat-lbl">Total Usos</div></div>
    </div>

    <!-- Criar Cupom -->
    <div class="modern-card">
        <div class="card-header-custom violet">
            <div class="header-icon"><i class='bx bx-plus-circle'></i></div>
            <div class="header-info"><div class="header-title">Criar Novo Cupom</div><div class="header-subtitle">Preencha os dados para gerar um cupom de desconto</div></div>
        </div>
        <div class="card-body-custom">
            <form method="POST" id="formCriar">
            <div class="create-grid">
                <div class="form-group">
                    <label class="form-label"><i class='bx bx-tag' style="color:#a78bfa;"></i> Nome do Cupom</label>
                    <input type="text" class="form-control" name="nome" placeholder="Ex: Black Friday" required>
                </div>
                <div class="form-group">
                    <label class="form-label"><i class='bx bx-code-alt' style="color:#34d399;"></i> Código do Cupom</label>
                    <div class="code-preview">
                        <input type="text" class="form-control mono" name="cupom" id="cupomCode" value="<?php echo $cupon; ?>" required>
                        <button type="button" class="btn-regen" onclick="regenarCodigo()" title="Gerar novo código"><i class='bx bx-refresh'></i></button>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label"><i class='bx bx-percent' style="color:#fbbf24;"></i> Desconto: <span id="descontoDisplay" style="color:#a78bfa;font-size:11px;font-weight:800;">10%</span></label>
                    <div class="slider-container">
                        <input type="range" class="slider" name="desconto" id="descontoSlider" min="1" max="100" value="10" oninput="atualizarDesconto(this.value)">
                        <div class="slider-value" id="descontoValue">10%</div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label"><i class='bx bx-repeat' style="color:#60a5fa;"></i> Limite de Usos</label>
                    <input type="number" class="form-control" name="vezesuso" min="1" value="10" placeholder="Máximo de usos" required>
                </div>
                <div class="create-full">
                    <button type="submit" name="adicionarcupom" class="btn-submit"><i class='bx bx-plus-circle'></i> Criar Cupom</button>
                </div>
            </div>
            </form>
        </div>
    </div>

    <!-- Filtro -->
    <div class="search-row">
        <input type="text" class="form-control" id="searchCupom" placeholder="🔍 Buscar cupom por nome ou código..." oninput="filtrarCupons()">
        <select class="form-control" id="filterStatus" style="max-width:140px;" onchange="filtrarCupons()">
            <option value="todos">Todos</option>
            <option value="ativo">✅ Ativos</option>
            <option value="esgotado">❌ Esgotados</option>
        </select>
    </div>

    <!-- Grid de Cupons -->
    <div class="cupons-grid" id="cuponsGrid">
    <?php if(count($cupons) > 0): foreach($cupons as $c):
        $usos = intval($c['usado'] ?? 0);
        $limite = intval($c['vezesuso']);
        $esgotado = ($limite > 0 && $usos >= $limite);
        $pct_uso = $limite > 0 ? round(($usos / $limite) * 100) : 0;
        $fill_color = $pct_uso >= 100 ? '#ef4444' : ($pct_uso >= 70 ? '#f97316' : ($pct_uso >= 40 ? '#fbbf24' : '#34d399'));
        $count_class = $pct_uso >= 100 ? 'full' : ($pct_uso >= 70 ? 'warn' : 'ok');
    ?>
    <div class="cupom-card <?php echo $esgotado?'esgotado':''; ?>" data-nome="<?php echo strtolower(htmlspecialchars($c['nome'])); ?>" data-cupom="<?php echo strtolower(htmlspecialchars($c['cupom'])); ?>" data-status="<?php echo $esgotado?'esgotado':'ativo'; ?>" data-id="<?php echo $c['id']; ?>" data-nome-raw="<?php echo htmlspecialchars($c['nome']); ?>" data-cupom-raw="<?php echo htmlspecialchars($c['cupom']); ?>" data-desconto="<?php echo $c['desconto']; ?>" data-vezesuso="<?php echo $limite; ?>" data-usado="<?php echo $usos; ?>">
        <div class="cupom-header <?php echo $esgotado?'esgotado':''; ?>">
            <div class="cupom-header-info">
                <div class="cupom-icon"><i class='bx bx-purchase-tag'></i></div>
                <div class="cupom-text">
                    <div class="cupom-nome"><?php echo htmlspecialchars($c['nome']); ?></div>
                    <div class="cupom-tipo">Cupom de Desconto</div>
                </div>
            </div>
            <div class="cupom-desconto-big"><?php echo $c['desconto']; ?>%</div>
        </div>
        <div class="cupom-body">
            <!-- Código -->
            <div class="code-box">
                <div class="code-label">Código do Cupom</div>
                <div class="code-value"><?php echo htmlspecialchars($c['cupom']); ?></div>
                <button class="btn-copy-code" onclick="copiarCodigo(this,'<?php echo htmlspecialchars($c['cupom']); ?>')"><i class='bx bx-copy'></i> Copiar</button>
            </div>

            <!-- Usage bar -->
            <div class="usage-bar">
                <div class="usage-header">
                    <span class="usage-label">USO</span>
                    <span class="usage-count <?php echo $count_class; ?>"><?php echo $usos; ?> / <?php echo $limite; ?> (<?php echo $pct_uso; ?>%)</span>
                </div>
                <div class="usage-track"><div class="usage-fill" style="width:<?php echo min(100,$pct_uso); ?>%;background:<?php echo $fill_color; ?>;"></div></div>
            </div>

            <!-- Info -->
            <div class="cupom-info-grid">
                <div class="cupom-info-row"><div class="cupom-info-icon"><i class='bx bx-percent' style="color:#a78bfa;"></i></div><div class="cupom-info-content"><div class="cupom-info-label">DESCONTO</div><div class="cupom-info-value" style="color:#a78bfa;"><?php echo $c['desconto']; ?>%</div></div></div>
                <div class="cupom-info-row"><div class="cupom-info-icon"><i class='bx bx-repeat' style="color:#60a5fa;"></i></div><div class="cupom-info-content"><div class="cupom-info-label">LIMITE</div><div class="cupom-info-value"><?php echo $limite; ?> vezes</div></div></div>
                <div class="cupom-info-row"><div class="cupom-info-icon"><i class='bx bx-check-circle' style="color:#fbbf24;"></i></div><div class="cupom-info-content"><div class="cupom-info-label">USADO</div><div class="cupom-info-value"><?php echo $usos; ?> vezes</div></div></div>
                <div class="cupom-info-row"><div class="cupom-info-icon"><i class='bx bx-info-circle' style="color:<?php echo $esgotado?'#f87171':'#34d399'; ?>;"></i></div><div class="cupom-info-content"><div class="cupom-info-label">STATUS</div><div class="cupom-info-value"><?php echo $esgotado?'<span class="status-chip chip-esgotado"><i class="bx bx-x-circle"></i> Esgotado</span>':'<span class="status-chip chip-ativo"><i class="bx bx-check-circle"></i> Ativo</span>'; ?></div></div></div>
            </div>

            <!-- Actions -->
            <div class="cupom-actions">
                <button class="action-btn btn-edit" onclick="editarCupom(this)"><i class='bx bx-edit'></i> Editar</button>
                <button class="action-btn btn-copy" onclick="copiarCodigo(this,'<?php echo htmlspecialchars($c['cupom']); ?>')"><i class='bx bx-copy'></i> Copiar</button>
                <button class="action-btn btn-reset" onclick="resetarUsos(<?php echo $c['id']; ?>,'<?php echo addslashes($c['nome']); ?>')"><i class='bx bx-refresh'></i> Resetar</button>
                <button class="action-btn btn-danger" onclick="excluirCupom(<?php echo $c['id']; ?>,'<?php echo addslashes($c['nome']); ?>')"><i class='bx bx-trash'></i> Excluir</button>
            </div>
        </div>
    </div>
    <?php endforeach; else: ?>
    <div class="empty-state">
        <div class="empty-state-icon"><i class='bx bx-purchase-tag'></i></div>
        <h3>Nenhum cupom criado</h3>
        <p>Use o formulário acima para criar seu primeiro cupom de desconto.</p>
    </div>
    <?php endif; ?>
    </div>

    <div class="pagination-info">Total: <?php echo $total_cupons; ?> cupom(ns) — <?php echo date('d/m/Y H:i'); ?></div>

</div>
</div>

<!-- MODAIS -->

<!-- Editar -->
<div id="modalEditar" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom violet" style="background:linear-gradient(135deg,#4158D0,#6366f1);"><h5><i class='bx bx-edit'></i> Editar Cupom</h5><button class="modal-close" onclick="fecharModal('modalEditar')"><i class='bx bx-x'></i></button></div>
    <div class="modal-body-custom">
        <form method="POST" id="formEditar">
        <input type="hidden" name="cupom_id" id="e_id">
        <div class="create-grid">
            <div class="form-group"><label class="form-label"><i class='bx bx-tag' style="color:#a78bfa;"></i> Nome</label><input type="text" class="form-control" name="nome" id="e_nome" required></div>
            <div class="form-group"><label class="form-label"><i class='bx bx-code-alt' style="color:#34d399;"></i> Código</label><input type="text" class="form-control mono" name="cupom" id="e_cupom" required></div>
            <div class="form-group">
                <label class="form-label"><i class='bx bx-percent' style="color:#fbbf24;"></i> Desconto: <span id="e_descontoDisplay" style="color:#a78bfa;">10%</span></label>
                <div class="slider-container">
                    <input type="range" class="slider" name="desconto" id="e_desconto" min="1" max="100" value="10" oninput="document.getElementById('e_descontoDisplay').textContent=this.value+'%';document.getElementById('e_descontoVal').textContent=this.value+'%';">
                    <div class="slider-value" id="e_descontoVal">10%</div>
                </div>
            </div>
            <div class="form-group"><label class="form-label"><i class='bx bx-repeat' style="color:#60a5fa;"></i> Limite</label><input type="number" class="form-control" name="vezesuso" id="e_vezesuso" min="1" required></div>
        </div>
        <div style="display:flex;justify-content:center;gap:8px;margin-top:14px;">
            <button type="button" class="btn-modal btn-modal-cancel" onclick="fecharModal('modalEditar')"><i class='bx bx-x'></i> Cancelar</button>
            <button type="submit" name="editarcupom" class="btn-modal btn-modal-primary"><i class='bx bx-save'></i> Salvar</button>
        </div>
        </form>
    </div>
</div></div></div>

<!-- Excluir -->
<div id="modalExcluir" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom error"><h5><i class='bx bx-trash'></i> Excluir Cupom</h5><button class="modal-close" onclick="fecharModal('modalExcluir')"><i class='bx bx-x'></i></button></div>
    <div class="modal-body-custom">
        <div class="modal-ic error"><i class='bx bx-error-circle'></i></div>
        <div class="modal-info-card" id="excluirInfo"></div>
        <p style="text-align:center;font-size:11px;color:#f87171;font-weight:600;">⚠️ Esta ação NÃO pode ser desfeita!</p>
    </div>
    <div class="modal-footer-custom">
        <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalExcluir')"><i class='bx bx-x'></i> Cancelar</button>
        <button class="btn-modal btn-modal-danger" id="btnConfExcluir"><i class='bx bx-trash'></i> Excluir</button>
    </div>
</div></div></div>

<!-- Resetar -->
<div id="modalResetar" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom warning"><h5><i class='bx bx-refresh'></i> Resetar Usos</h5><button class="modal-close" onclick="fecharModal('modalResetar')"><i class='bx bx-x'></i></button></div>
    <div class="modal-body-custom">
        <div class="modal-ic warning"><i class='bx bx-refresh'></i></div>
        <div class="modal-info-card" id="resetarInfo"></div>
        <p style="text-align:center;font-size:11px;color:rgba(255,255,255,0.4);">O contador de usos será zerado.</p>
    </div>
    <div class="modal-footer-custom">
        <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalResetar')"><i class='bx bx-x'></i> Cancelar</button>
        <button class="btn-modal btn-modal-warning" id="btnConfResetar"><i class='bx bx-refresh'></i> Resetar</button>
    </div>
</div></div></div>

<!-- Sucesso -->
<div id="modalSucesso" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom green"><h5><i class='bx bx-check-circle'></i> Sucesso!</h5><button class="modal-close" onclick="fecharModal('modalSucesso')"><i class='bx bx-x'></i></button></div>
    <div class="modal-body-custom"><div class="modal-ic success"><i class='bx bx-check-circle'></i></div><p style="text-align:center;font-size:14px;font-weight:600;" id="sucessoMsg"><?php echo htmlspecialchars($msg_sucesso); ?></p></div>
    <div class="modal-footer-custom"><button class="btn-modal btn-modal-ok" onclick="fecharModal('modalSucesso')"><i class='bx bx-check'></i> OK</button></div>
</div></div></div>

<!-- Erro -->
<div id="modalErro" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom error"><h5><i class='bx bx-error-circle'></i> Erro!</h5><button class="modal-close" onclick="fecharModal('modalErro')"><i class='bx bx-x'></i></button></div>
    <div class="modal-body-custom"><div class="modal-ic error"><i class='bx bx-error-circle'></i></div><p style="text-align:center;font-size:14px;font-weight:600;" id="erroMsg"><?php echo htmlspecialchars($msg_erro); ?></p></div>
    <div class="modal-footer-custom"><button class="btn-modal btn-modal-danger" onclick="fecharModal('modalErro')"><i class='bx bx-x'></i> Fechar</button></div>
</div></div></div>

<!-- Processando -->
<div id="modalProcessando" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom processing"><h5><i class='bx bx-loader-alt bx-spin'></i> Processando...</h5></div>
    <div class="modal-body-custom"><div class="spinner-wrap"><div class="spinner-ring"></div><p style="font-size:13px;color:rgba(255,255,255,.6);">Aguarde...</p></div></div>
</div></div></div>

<script>
function abrirModal(id){document.getElementById(id).classList.add('show');}
function fecharModal(id){document.getElementById(id).classList.remove('show');}
document.querySelectorAll('.modal-overlay').forEach(function(o){o.addEventListener('click',function(e){if(e.target===o)o.classList.remove('show');});});
document.addEventListener('keydown',function(e){if(e.key==='Escape')document.querySelectorAll('.modal-overlay.show').forEach(function(m){m.classList.remove('show');});});

function toast(msg,tipo){var t=document.createElement('div');t.className='toast-notification '+(tipo==='err'?'err':'ok');t.innerHTML='<i class="bx '+(tipo==='err'?'bx-error-circle':'bx-check-circle')+'"></i> '+msg;document.body.appendChild(t);setTimeout(function(){t.remove();},3500);}

// Desconto slider
function atualizarDesconto(val){
    document.getElementById('descontoDisplay').textContent=val+'%';
    document.getElementById('descontoValue').textContent=val+'%';
}

// Gerar novo código
function regenarCodigo(){
    var chars='ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    var code='';
    for(var i=0;i<8;i++)code+=chars.charAt(Math.floor(Math.random()*chars.length));
    document.getElementById('cupomCode').value=code;
}

// Copiar código
function copiarCodigo(btn,code){
    navigator.clipboard.writeText(code).then(function(){
        var orig=btn.innerHTML;
        btn.classList.add('copied');
        btn.innerHTML='<i class="bx bx-check"></i> Copiado!';
        toast('Código copiado: '+code,'ok');
        setTimeout(function(){btn.classList.remove('copied');btn.innerHTML=orig;},2000);
    }).catch(function(){toast('Erro ao copiar!','err');});
}

// Filtrar
function filtrarCupons(){
    var busca=document.getElementById('searchCupom').value.toLowerCase();
    var status=document.getElementById('filterStatus').value;
    document.querySelectorAll('.cupom-card').forEach(function(c){
        var nome=c.dataset.nome||'';
        var cupom=c.dataset.cupom||'';
        var st=c.dataset.status||'';
        var mb=nome.includes(busca)||cupom.includes(busca);
        var ms=true;
        if(status==='ativo')ms=st==='ativo';
        else if(status==='esgotado')ms=st==='esgotado';
        c.style.display=(mb&&ms)?'':'none';
    });
}

// Editar
function editarCupom(btn){
    var card=btn.closest('.cupom-card');
    document.getElementById('e_id').value=card.dataset.id;
    document.getElementById('e_nome').value=card.dataset.nomeRaw;
    document.getElementById('e_cupom').value=card.dataset.cupomRaw;
    var desc=card.dataset.desconto;
    document.getElementById('e_desconto').value=desc;
    document.getElementById('e_descontoDisplay').textContent=desc+'%';
    document.getElementById('e_descontoVal').textContent=desc+'%';
    document.getElementById('e_vezesuso').value=card.dataset.vezesuso;
    abrirModal('modalEditar');
}

// Excluir
var _exId=null;
function excluirCupom(id,nome){
    _exId=id;
    document.getElementById('excluirInfo').innerHTML='<div class="modal-info-row"><i class="bx bx-purchase-tag" style="color:#a78bfa;"></i> <span>Cupom:</span> <strong>'+nome+'</strong></div><div class="modal-info-row"><i class="bx bx-id-card" style="color:#fbbf24;"></i> <span>ID:</span> <strong>#'+id+'</strong></div>';
    document.getElementById('btnConfExcluir').onclick=function(){submitAction('deletar',_exId);};
    abrirModal('modalExcluir');
}

// Resetar
var _rsId=null;
function resetarUsos(id,nome){
    _rsId=id;
    document.getElementById('resetarInfo').innerHTML='<div class="modal-info-row"><i class="bx bx-purchase-tag" style="color:#a78bfa;"></i> <span>Cupom:</span> <strong>'+nome+'</strong></div><div class="modal-info-row"><i class="bx bx-refresh" style="color:#fbbf24;"></i> <span>Ação:</span> <strong style="color:#fbbf24;">Zerar usos</strong></div>';
    document.getElementById('btnConfResetar').onclick=function(){submitAction('resetar_usos',_rsId);};
    abrirModal('modalResetar');
}

// Submit ação via form
function submitAction(action,id){
    fecharModal('modalExcluir');fecharModal('modalResetar');
    abrirModal('modalProcessando');
    var form=document.createElement('form');form.method='POST';
    if(action==='deletar'){
        form.innerHTML='<input type="hidden" name="deletar" value="1"><input type="hidden" name="id" value="'+id+'">';
    }else{
        form.innerHTML='<input type="hidden" name="resetar_usos" value="1"><input type="hidden" name="cupom_id" value="'+id+'">';
    }
    document.body.appendChild(form);form.submit();
}

// Modais de resultado
<?php if($show_success): ?>
document.addEventListener('DOMContentLoaded',function(){abrirModal('modalSucesso');});
<?php endif; ?>
<?php if($show_error): ?>
document.addEventListener('DOMContentLoaded',function(){abrirModal('modalErro');});
<?php endif; ?>
</script>
</body>
</html>
<?php
    }
    aleatorio813363($kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf);
?>

