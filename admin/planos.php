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

// Admin ID = 1 (admin do sistema)
$admin_id = 1;

// ===== GARANTIR TABELA =====
$conn->query("CREATE TABLE IF NOT EXISTS `planos_pagamento` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `byid` INT(11) NOT NULL DEFAULT '1',
    `nome` VARCHAR(255) NOT NULL,
    `tipo` VARCHAR(50) NOT NULL DEFAULT 'usuario',
    `duracao_dias` INT(11) NOT NULL DEFAULT '30',
    `valor` DECIMAL(10,2) NOT NULL DEFAULT '0.00',
    `limite` INT(11) NOT NULL DEFAULT '1',
    `descricao` TEXT DEFAULT NULL,
    `status` TINYINT(1) NOT NULL DEFAULT '1',
    `destaque` TINYINT(1) NOT NULL DEFAULT '0',
    `data_criacao` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Coluna destaque
$check = $conn->query("SHOW COLUMNS FROM planos_pagamento LIKE 'destaque'");
if ($check && $check->num_rows == 0) $conn->query("ALTER TABLE planos_pagamento ADD COLUMN destaque TINYINT(1) DEFAULT '0'");

// ===== PROCESSAR AÇÕES =====
$msg_sucesso = ''; $msg_erro = '';
$show_success = false; $show_error = false;

if (isset($_POST['criar_plano'])) {
    $nome = anti_sql($_POST['nome']);
    $tipo = anti_sql($_POST['tipo']);
    $duracao = intval($_POST['duracao_dias']);
    $valor = floatval($_POST['valor']);
    $limite = intval($_POST['limite']);
    $descricao = mysqli_real_escape_string($conn, $_POST['descricao'] ?? '');
    $status = isset($_POST['status']) ? 1 : 0;
    $destaque = isset($_POST['destaque']) ? 1 : 0;

    if (empty($nome) || $duracao <= 0 || $valor <= 0 || $limite <= 0) {
        $msg_erro = "Preencha todos os campos corretamente!"; $show_error = true;
    } else {
        $r = $conn->query("INSERT INTO planos_pagamento (byid,nome,tipo,duracao_dias,valor,limite,descricao,status,destaque,data_criacao) VALUES ('$admin_id','$nome','$tipo','$duracao','$valor','$limite','$descricao','$status','$destaque',NOW())");
        if ($r) { $msg_sucesso = "Plano \"$nome\" criado com sucesso!"; $show_success = true; }
        else { $msg_erro = "Erro ao criar plano!"; $show_error = true; }
    }
}

if (isset($_POST['editar_plano'])) {
    $pid = intval($_POST['plano_id']);
    $nome = anti_sql($_POST['nome']);
    $tipo = anti_sql($_POST['tipo']);
    $duracao = intval($_POST['duracao_dias']);
    $valor = floatval($_POST['valor']);
    $limite = intval($_POST['limite']);
    $descricao = mysqli_real_escape_string($conn, $_POST['descricao'] ?? '');
    $status = isset($_POST['status']) ? 1 : 0;
    $destaque = isset($_POST['destaque']) ? 1 : 0;

    $r = $conn->query("UPDATE planos_pagamento SET nome='$nome',tipo='$tipo',duracao_dias='$duracao',valor='$valor',limite='$limite',descricao='$descricao',status='$status',destaque='$destaque' WHERE id='$pid' AND byid='$admin_id'");
    if ($r) { $msg_sucesso = "Plano atualizado!"; $show_success = true; }
    else { $msg_erro = "Erro ao atualizar!"; $show_error = true; }
}

if (isset($_POST['excluir_plano'])) {
    $pid = intval($_POST['plano_id']);
    $r = $conn->query("DELETE FROM planos_pagamento WHERE id='$pid' AND byid='$admin_id'");
    if ($r) { $msg_sucesso = "Plano excluído!"; $show_success = true; }
    else { $msg_erro = "Erro ao excluir!"; $show_error = true; }
}

if (isset($_POST['toggle_status'])) {
    $pid = intval($_POST['plano_id']);
    $ns = intval($_POST['novo_status']);
    $r = $conn->query("UPDATE planos_pagamento SET status='$ns' WHERE id='$pid' AND byid='$admin_id'");
    if ($r) { $msg_sucesso = $ns == 1 ? "Plano ativado!" : "Plano desativado!"; $show_success = true; }
    else { $msg_erro = "Erro ao alterar status!"; $show_error = true; }
}

// ===== BUSCAR PLANOS DO ADMIN =====
// Somente planos do admin (byid=1). Revendedores e usuários de outros revendedores NÃO vêem estes planos.
$result_planos = $conn->query("SELECT * FROM planos_pagamento WHERE byid='$admin_id' ORDER BY tipo ASC, destaque DESC, valor ASC");

$planos_usuario = []; $planos_revenda = [];
$total_ativos = 0; $total_inativos = 0; $total_usuario = 0; $total_revenda = 0;
$receita_potencial = 0;

while ($p = mysqli_fetch_assoc($result_planos)) {
    if ($p['status'] == 1) $total_ativos++; else $total_inativos++;
    if ($p['tipo'] == 'usuario') { $planos_usuario[] = $p; $total_usuario++; }
    else { $planos_revenda[] = $p; $total_revenda++; }
    if ($p['status'] == 1) $receita_potencial += $p['valor'];
}
$total_planos = $total_usuario + $total_revenda;

// Contar assinaturas ativas usando estes planos
$r_assinaturas = $conn->query("SELECT COUNT(*) as t FROM atribuidos WHERE id_plano IN (SELECT id FROM planos_pagamento WHERE byid='$admin_id') AND expira > NOW()");
$total_assinantes = ($r_assinaturas && $r_assinaturas->num_rows > 0) ? $r_assinaturas->fetch_assoc()['t'] : 0;

date_default_timezone_set('America/Sao_Paulo');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Planos de Pagamento - Admin</title>
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
.app-content{margin-left:0px!important;padding:0!important;}
.content-wrapper{max-width:1700px;margin:0 auto!important;padding:20px!important;}

/* Stats */
.stats-card{background:linear-gradient(135deg,var(--fundo_claro,#1e293b),var(--fundo,#0f172a));border-radius:20px;padding:20px 24px;margin-bottom:20px;border:1px solid rgba(255,255,255,0.08);display:flex;align-items:center;gap:20px;position:relative;overflow:hidden;transition:all .3s;}
.stats-card:hover{transform:translateY(-2px);border-color:var(--primaria,#4158D0);}
.stats-card-icon{width:60px;height:60px;background:linear-gradient(135deg,var(--primaria,#4158D0),var(--secundaria,#C850C0));border-radius:18px;display:flex;align-items:center;justify-content:center;font-size:32px;color:white;flex-shrink:0;}
.stats-card-content{flex:1;}
.stats-card-title{font-size:13px;font-weight:600;color:rgba(255,255,255,0.5);text-transform:uppercase;margin-bottom:5px;}
.stats-card-value{font-size:36px;font-weight:800;background:linear-gradient(135deg,#fff,var(--primaria,#4158D0));-webkit-background-clip:text;-webkit-text-fill-color:transparent;line-height:1;}
.stats-card-subtitle{font-size:12px;color:rgba(255,255,255,0.4);margin-top:4px;}
.stats-card-decoration{position:absolute;right:20px;top:50%;transform:translateY(-50%);font-size:80px;opacity:0.05;}

/* Mini Stats */
.mini-stats{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;}
.mini-stat{flex:1;min-width:80px;background:rgba(255,255,255,0.04);border-radius:12px;padding:12px;border:1px solid rgba(255,255,255,0.06);text-align:center;transition:all .2s;}
.mini-stat:hover{border-color:var(--primaria,#4158D0);transform:translateY(-2px);}
.mini-stat-val{font-size:18px;font-weight:800;}
.mini-stat-lbl{font-size:9px;color:rgba(255,255,255,0.35);text-transform:uppercase;margin-top:2px;}

/* Tabs */
.tabs-bar{display:flex;gap:6px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:12px;padding:4px;margin-bottom:16px;flex-wrap:wrap;}
.tab-btn{padding:8px 18px;border:none;background:transparent;color:rgba(255,255,255,0.5);font-weight:600;font-size:12px;border-radius:9px;cursor:pointer;transition:all .2s;display:flex;align-items:center;gap:6px;font-family:inherit;}
.tab-btn i{font-size:15px;}
.tab-btn.active{background:linear-gradient(135deg,var(--primaria,#4158D0),var(--secundaria,#C850C0));color:white;box-shadow:0 4px 12px rgba(65,88,208,0.3);}
.tab-btn:hover:not(.active){background:rgba(255,255,255,0.05);color:white;}
.tab-count{background:rgba(255,255,255,0.2);padding:1px 7px;border-radius:20px;font-size:9px;font-weight:700;}

/* Action bar */
.action-bar{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px;}
.btn-criar{padding:9px 18px;border:none;border-radius:10px;font-weight:700;font-size:12px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;color:white;transition:all .2s;font-family:inherit;background:linear-gradient(135deg,#10b981,#059669);}
.btn-criar:hover{transform:translateY(-1px);filter:brightness(1.08);box-shadow:0 6px 16px rgba(16,185,129,0.3);}

/* Info badge */
.info-notice{background:rgba(65,88,208,0.06);border:1px solid rgba(65,88,208,0.15);border-radius:10px;padding:10px 14px;display:flex;align-items:center;gap:8px;font-size:11px;color:rgba(255,255,255,0.5);margin-bottom:16px;}
.info-notice i{font-size:16px;color:#818cf8;flex-shrink:0;}

/* Grid */
.planos-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px;}

/* Plano Card */
.plano-card{background:var(--fundo_claro,#1e293b);border-radius:16px;overflow:hidden;border:1px solid rgba(255,255,255,0.08);transition:all .25s;position:relative;}
.plano-card:hover{transform:translateY(-3px);border-color:var(--primaria,#4158D0);box-shadow:0 8px 25px rgba(0,0,0,0.3);}
.plano-card.inativo{opacity:.55;}
.plano-card.destaque{border-color:rgba(255,204,112,0.3);}
.plano-card.destaque::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,#FFCC70,#f59e0b,#FFCC70);background-size:200% 100%;animation:shimmerGold 3s linear infinite;}
@keyframes shimmerGold{0%{background-position:200% 0;}100%{background-position:-200% 0;}}

.plano-header{padding:14px;display:flex;align-items:center;justify-content:space-between;}
.plano-header.usuario{background:linear-gradient(135deg,var(--primaria,#4158D0),var(--secundaria,#C850C0));}
.plano-header.revenda{background:linear-gradient(135deg,#10b981,#059669);}
.plano-header.inativo-header{background:linear-gradient(135deg,#475569,#334155)!important;}
.plano-info{display:flex;align-items:center;gap:10px;flex:1;min-width:0;}
.plano-icon{width:36px;height:36px;background:rgba(255,255,255,0.2);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;color:white;flex-shrink:0;}
.plano-text{flex:1;min-width:0;}
.plano-nome{font-size:14px;font-weight:700;color:white;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.plano-tipo-label{font-size:9px;color:rgba(255,255,255,0.7);margin-top:1px;}
.plano-badges{display:flex;gap:4px;flex-shrink:0;}
.badge-sm{padding:2px 7px;border-radius:16px;font-size:8px;font-weight:700;}
.badge-ativo{background:rgba(16,185,129,0.25);color:#34d399;border:1px solid rgba(16,185,129,0.3);}
.badge-inativo{background:rgba(100,116,139,0.25);color:#94a3b8;border:1px solid rgba(100,116,139,0.3);}
.badge-destaque{background:rgba(255,204,112,0.25);color:#FFCC70;border:1px solid rgba(255,204,112,0.3);}

.plano-body{padding:14px;}

/* Preço destaque */
.preco-box{background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:12px;padding:14px;text-align:center;margin-bottom:12px;}
.preco-valor{font-size:28px;font-weight:800;background:linear-gradient(135deg,#34d399,#10b981);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
.preco-periodo{font-size:11px;color:rgba(255,255,255,0.4);margin-top:2px;}
.preco-por-dia{font-size:10px;color:rgba(255,255,255,0.3);margin-top:4px;}

/* Info grid */
.plano-info-grid{display:grid;grid-template-columns:1fr 1fr;gap:5px;margin-bottom:8px;}
.plano-info-row{display:flex;align-items:center;gap:5px;padding:5px 7px;background:rgba(255,255,255,0.03);border-radius:7px;}
.plano-info-icon{width:22px;height:22px;background:rgba(255,255,255,0.05);border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0;}
.plano-info-content{flex:1;min-width:0;}
.plano-info-label{font-size:8px;color:rgba(255,255,255,0.4);font-weight:600;}
.plano-info-value{font-size:10px;font-weight:600;}

/* Descrição */
.plano-desc{background:rgba(255,255,255,0.03);border-radius:8px;padding:8px 10px;font-size:10px;color:rgba(255,255,255,0.5);line-height:1.5;margin-bottom:8px;border-left:2px solid rgba(255,255,255,0.08);}

/* Actions */
.plano-actions{display:flex;gap:5px;flex-wrap:wrap;}
.action-btn{flex:1;min-width:55px;padding:6px 6px;border:none;border-radius:8px;font-weight:600;font-size:9px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:3px;color:white;transition:all .2s;font-family:inherit;}
.action-btn:hover{transform:translateY(-1px);filter:brightness(1.08);}
.action-btn:active{transform:scale(0.95);}
.btn-edit{background:linear-gradient(135deg,#4158D0,#6366f1);}
.btn-toggle-act{background:linear-gradient(135deg,#f59e0b,#f97316);}
.btn-toggle-act.ativar{background:linear-gradient(135deg,#10b981,#059669);}
.btn-danger{background:linear-gradient(135deg,#dc2626,#b91c1c);}
.btn-duplicate{background:linear-gradient(135deg,#06b6d4,#0891b2);}

/* Tab content */
.tab-content{display:none;}.tab-content.active{display:block;}

/* Empty */
.empty-state{grid-column:1/-1;text-align:center;padding:50px 20px;background:var(--fundo_claro,#1e293b);border-radius:16px;border:1px solid rgba(255,255,255,0.08);}
.empty-state-icon{width:80px;height:80px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:40px;border:2px solid;}
.empty-state-icon.usuario{background:rgba(65,88,208,0.1);color:#818cf8;border-color:rgba(65,88,208,0.2);}
.empty-state-icon.revenda{background:rgba(16,185,129,0.1);color:#34d399;border-color:rgba(16,185,129,0.2);}
.empty-state h3{font-size:16px;margin-bottom:6px;}
.empty-state p{font-size:11px;color:rgba(255,255,255,0.3);}

/* Modal */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.85);display:none;align-items:center;justify-content:center;z-index:9999;backdrop-filter:blur(8px);padding:16px;}
.modal-overlay.show{display:flex;}
.modal-container{animation:modalIn .3s ease;max-width:520px;width:92%;}
@keyframes modalIn{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
.modal-content-custom{background:var(--fundo_claro,#1e293b);border-radius:20px;overflow:hidden;border:1px solid rgba(255,255,255,.1);box-shadow:0 25px 60px rgba(0,0,0,.5);}
.modal-header-custom{padding:14px 18px;display:flex;align-items:center;justify-content:space-between;}
.modal-header-custom h5{margin:0;display:flex;align-items:center;gap:8px;font-size:14px;font-weight:700;color:#fff;}
.modal-header-custom.purple{background:linear-gradient(135deg,var(--primaria,#4158D0),var(--secundaria,#C850C0));}
.modal-header-custom.green{background:linear-gradient(135deg,#10b981,#059669);}
.modal-header-custom.error{background:linear-gradient(135deg,#dc2626,#b91c1c);}
.modal-header-custom.warning{background:linear-gradient(135deg,#f59e0b,#f97316);}
.modal-header-custom.processing{background:linear-gradient(135deg,var(--primaria,#4158D0),var(--secundaria,#C850C0));}
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
.btn-modal-primary{background:linear-gradient(135deg,var(--primaria,#4158D0),var(--secundaria,#C850C0));}

.spinner-wrap{display:flex;flex-direction:column;align-items:center;gap:14px;padding:20px 0;}
.spinner-ring{width:44px;height:44px;border:3px solid rgba(255,255,255,.08);border-top-color:var(--primaria,#4158D0);border-right-color:var(--secundaria,#C850C0);border-radius:50%;animation:spin .8s linear infinite;}
@keyframes spin{to{transform:rotate(360deg)}}

/* Form */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.form-full{grid-column:1/-1;}
.form-group{margin-bottom:0;}
.form-label{display:flex;align-items:center;gap:4px;font-size:9px;font-weight:700;color:rgba(255,255,255,0.4);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;}
.form-label i{font-size:12px;}
.form-control{width:100%;padding:8px 12px;background:rgba(255,255,255,0.06);border:1.5px solid rgba(255,255,255,0.08);border-radius:9px;color:#fff;font-size:12px;font-family:inherit;outline:none;transition:all .25s;}
.form-control:focus{border-color:var(--primaria,#4158D0);background:rgba(255,255,255,0.09);}
.form-control::placeholder{color:rgba(255,255,255,0.2);}
select.form-control{cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%23ffffff' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;}
select.form-control option{background:#1e293b;color:#fff;}
textarea.form-control{resize:vertical;min-height:60px;}
.toggle-row{display:flex;align-items:center;gap:10px;padding:6px 0;}
.toggle-switch{position:relative;width:36px;height:20px;cursor:pointer;}
.toggle-switch input{opacity:0;width:0;height:0;}
.toggle-slider{position:absolute;inset:0;background:rgba(255,255,255,0.12);border-radius:20px;transition:all .3s;}
.toggle-slider::before{content:'';position:absolute;width:16px;height:16px;border-radius:50%;background:white;top:2px;left:2px;transition:all .3s;}
.toggle-switch input:checked+.toggle-slider{background:#10b981;}
.toggle-switch input:checked+.toggle-slider::before{transform:translateX(16px);}
.toggle-label{font-size:11px;color:rgba(255,255,255,0.5);}

/* Toast */
.toast-notification{position:fixed;bottom:20px;right:20px;color:#fff;padding:10px 16px;border-radius:10px;display:flex;align-items:center;gap:8px;z-index:10001;animation:toastIn .3s ease;font-weight:600;font-size:12px;box-shadow:0 8px 20px rgba(0,0,0,.3);}
.toast-notification.ok{background:linear-gradient(135deg,#10b981,#059669);}
.toast-notification.err{background:linear-gradient(135deg,#dc2626,#b91c1c);}
@keyframes toastIn{from{transform:translateX(110%);opacity:0}to{transform:translateX(0);opacity:1}}

@media(max-width:768px){
    .app-content{margin-left:0!important;}
    .content-wrapper{padding:10px!important;}
    .planos-grid{grid-template-columns:1fr;}
    .stats-card{padding:14px;gap:14px;}
    .stats-card-icon{width:48px;height:48px;font-size:24px;}
    .stats-card-value{font-size:28px;}
    .mini-stats{flex-wrap:wrap;}
    .mini-stat{min-width:70px;}
    .tabs-bar{flex-direction:column;}
    .action-bar{flex-direction:column;}
    .form-grid{grid-template-columns:1fr;}
    .plano-actions{display:grid;grid-template-columns:repeat(2,1fr);}
}
</style>
</head>
<body class="<?php echo htmlspecialchars(getBodyClass($temaAtual ?? ['classe'=>'theme-dark'])); ?>">
<div class="app-content content">
<div class="content-overlay"></div>
<div class="content-wrapper">

    <!-- Stats -->
    <div class="stats-card">
        <div class="stats-card-icon"><i class='bx bx-crown'></i></div>
        <div class="stats-card-content">
            <div class="stats-card-title">Planos de Pagamento</div>
            <div class="stats-card-value"><?php echo $total_planos; ?> Planos</div>
            <div class="stats-card-subtitle">Gerencie planos exclusivos do administrador</div>
        </div>
        <div class="stats-card-decoration"><i class='bx bx-crown'></i></div>
    </div>

    <!-- Mini Stats -->
    <div class="mini-stats">
        <div class="mini-stat"><div class="mini-stat-val" style="color:#818cf8;"><?php echo $total_planos; ?></div><div class="mini-stat-lbl">Total</div></div>
        <div class="mini-stat"><div class="mini-stat-val" style="color:#34d399;"><?php echo $total_ativos; ?></div><div class="mini-stat-lbl">Ativos</div></div>
        <div class="mini-stat"><div class="mini-stat-val" style="color:#f87171;"><?php echo $total_inativos; ?></div><div class="mini-stat-lbl">Inativos</div></div>
        <div class="mini-stat"><div class="mini-stat-val" style="color:#818cf8;"><?php echo $total_usuario; ?></div><div class="mini-stat-lbl">Usuário</div></div>
        <div class="mini-stat"><div class="mini-stat-val" style="color:#e879f9;"><?php echo $total_revenda; ?></div><div class="mini-stat-lbl">Revenda</div></div>
        <div class="mini-stat"><div class="mini-stat-val" style="color:#fbbf24;"><?php echo $total_assinantes; ?></div><div class="mini-stat-lbl">Assinantes</div></div>
    </div>

    <!-- Info -->
    <div class="info-notice">
        <i class='bx bx-lock-alt'></i>
        <span>Estes planos são <strong style="color:white;">exclusivos do administrador</strong>. Revendedores e seus usuários <strong style="color:#f87171;">NÃO vêem</strong> estes planos — cada revendedor gerencia os seus próprios planos separadamente.</span>
    </div>

    <!-- Action bar -->
    <div class="action-bar">
        <div class="tabs-bar">
            <button class="tab-btn active" onclick="mudarTab('usuarios',this)"><i class='bx bx-user'></i> Usuários <span class="tab-count"><?php echo $total_usuario; ?></span></button>
            <button class="tab-btn" onclick="mudarTab('revendas',this)"><i class='bx bx-store-alt'></i> Revendedores <span class="tab-count"><?php echo $total_revenda; ?></span></button>
        </div>
        <button class="btn-criar" onclick="abrirModalCriar()"><i class='bx bx-plus-circle'></i> Novo Plano</button>
    </div>

    <!-- Tab Usuários -->
    <div id="tab-usuarios" class="tab-content active">
    <?php if(count($planos_usuario) > 0): ?>
    <div class="planos-grid">
    <?php foreach($planos_usuario as $p):
        $is_inativo = $p['status'] == 0;
        $is_destaque = $p['destaque'] == 1;
        $por_dia = $p['duracao_dias'] > 0 ? $p['valor'] / $p['duracao_dias'] : 0;
    ?>
    <div class="plano-card <?php echo $is_inativo?'inativo':''; ?> <?php echo $is_destaque?'destaque':''; ?>">
        <div class="plano-header usuario <?php echo $is_inativo?'inativo-header':''; ?>">
            <div class="plano-info">
                <div class="plano-icon"><i class='bx bx-user'></i></div>
                <div class="plano-text">
                    <div class="plano-nome"><?php echo htmlspecialchars($p['nome']); ?></div>
                    <div class="plano-tipo-label">Plano para Usuário Final</div>
                </div>
            </div>
            <div class="plano-badges">
                <?php if($is_destaque): ?><span class="badge-sm badge-destaque">⭐ DESTAQUE</span><?php endif; ?>
                <span class="badge-sm <?php echo $is_inativo?'badge-inativo':'badge-ativo'; ?>"><?php echo $is_inativo?'INATIVO':'ATIVO'; ?></span>
            </div>
        </div>
        <div class="plano-body">
            <div class="preco-box">
                <div class="preco-valor">R$ <?php echo number_format($p['valor'], 2, ',', '.'); ?></div>
                <div class="preco-periodo">por <?php echo $p['duracao_dias']; ?> dias</div>
                <div class="preco-por-dia">≈ R$ <?php echo number_format($por_dia, 2, ',', '.'); ?>/dia</div>
            </div>
            <div class="plano-info-grid">
                <div class="plano-info-row"><div class="plano-info-icon"><i class='bx bx-wifi' style="color:#34d399;"></i></div><div class="plano-info-content"><div class="plano-info-label">LIMITE</div><div class="plano-info-value"><?php echo $p['limite']; ?> conexões</div></div></div>
                <div class="plano-info-row"><div class="plano-info-icon"><i class='bx bx-calendar' style="color:#fbbf24;"></i></div><div class="plano-info-content"><div class="plano-info-label">DURAÇÃO</div><div class="plano-info-value"><?php echo $p['duracao_dias']; ?> dias</div></div></div>
                <div class="plano-info-row"><div class="plano-info-icon"><i class='bx bx-category' style="color:#818cf8;"></i></div><div class="plano-info-content"><div class="plano-info-label">TIPO</div><div class="plano-info-value">Usuário</div></div></div>
                <div class="plano-info-row"><div class="plano-info-icon"><i class='bx bx-id-card' style="color:#60a5fa;"></i></div><div class="plano-info-content"><div class="plano-info-label">ID</div><div class="plano-info-value">#<?php echo $p['id']; ?></div></div></div>
            </div>
            <?php if(!empty($p['descricao'])): ?>
            <div class="plano-desc"><?php echo htmlspecialchars($p['descricao']); ?></div>
            <?php endif; ?>
            <div class="plano-actions">
                <button class="action-btn btn-edit" onclick='editarPlano(<?php echo json_encode($p); ?>)'><i class='bx bx-edit'></i> Editar</button>
                <button class="action-btn btn-toggle-act <?php echo $is_inativo?'ativar':''; ?>" onclick="confirmarToggle(<?php echo $p['id']; ?>,<?php echo $p['status']; ?>,'<?php echo addslashes($p['nome']); ?>')"><i class='bx bx-<?php echo $is_inativo?'check-circle':'x-circle'; ?>'></i> <?php echo $is_inativo?'Ativar':'Desativar'; ?></button>
                <button class="action-btn btn-duplicate" onclick="duplicarPlano(<?php echo json_encode($p); ?>)"><i class='bx bx-copy'></i> Duplicar</button>
                <button class="action-btn btn-danger" onclick="confirmarExcluir(<?php echo $p['id']; ?>,'<?php echo addslashes($p['nome']); ?>')"><i class='bx bx-trash'></i> Excluir</button>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state"><div class="empty-state-icon usuario"><i class='bx bx-user'></i></div><h3>Nenhum plano para usuários</h3><p>Clique em "Novo Plano" para criar o primeiro.</p></div>
    <?php endif; ?>
    </div>

    <!-- Tab Revendas -->
    <div id="tab-revendas" class="tab-content">
    <?php if(count($planos_revenda) > 0): ?>
    <div class="planos-grid">
    <?php foreach($planos_revenda as $p):
        $is_inativo = $p['status'] == 0;
        $is_destaque = $p['destaque'] == 1;
        $por_dia = $p['duracao_dias'] > 0 ? $p['valor'] / $p['duracao_dias'] : 0;
    ?>
    <div class="plano-card <?php echo $is_inativo?'inativo':''; ?> <?php echo $is_destaque?'destaque':''; ?>">
        <div class="plano-header revenda <?php echo $is_inativo?'inativo-header':''; ?>">
            <div class="plano-info">
                <div class="plano-icon"><i class='bx bx-store-alt'></i></div>
                <div class="plano-text">
                    <div class="plano-nome"><?php echo htmlspecialchars($p['nome']); ?></div>
                    <div class="plano-tipo-label">Plano para Revendedor</div>
                </div>
            </div>
            <div class="plano-badges">
                <?php if($is_destaque): ?><span class="badge-sm badge-destaque">⭐ DESTAQUE</span><?php endif; ?>
                <span class="badge-sm <?php echo $is_inativo?'badge-inativo':'badge-ativo'; ?>"><?php echo $is_inativo?'INATIVO':'ATIVO'; ?></span>
            </div>
        </div>
        <div class="plano-body">
            <div class="preco-box">
                <div class="preco-valor">R$ <?php echo number_format($p['valor'], 2, ',', '.'); ?></div>
                <div class="preco-periodo">por <?php echo $p['duracao_dias']; ?> dias</div>
                <div class="preco-por-dia">≈ R$ <?php echo number_format($por_dia, 2, ',', '.'); ?>/dia</div>
            </div>
            <div class="plano-info-grid">
                <div class="plano-info-row"><div class="plano-info-icon"><i class='bx bx-credit-card' style="color:#34d399;"></i></div><div class="plano-info-content"><div class="plano-info-label">CRÉDITOS</div><div class="plano-info-value"><?php echo $p['limite']; ?> créditos</div></div></div>
                <div class="plano-info-row"><div class="plano-info-icon"><i class='bx bx-calendar' style="color:#fbbf24;"></i></div><div class="plano-info-content"><div class="plano-info-label">DURAÇÃO</div><div class="plano-info-value"><?php echo $p['duracao_dias']; ?> dias</div></div></div>
                <div class="plano-info-row"><div class="plano-info-icon"><i class='bx bx-category' style="color:#e879f9;"></i></div><div class="plano-info-content"><div class="plano-info-label">TIPO</div><div class="plano-info-value">Revenda</div></div></div>
                <div class="plano-info-row"><div class="plano-info-icon"><i class='bx bx-id-card' style="color:#60a5fa;"></i></div><div class="plano-info-content"><div class="plano-info-label">ID</div><div class="plano-info-value">#<?php echo $p['id']; ?></div></div></div>
            </div>
            <?php if(!empty($p['descricao'])): ?>
            <div class="plano-desc"><?php echo htmlspecialchars($p['descricao']); ?></div>
            <?php endif; ?>
            <div class="plano-actions">
                <button class="action-btn btn-edit" onclick='editarPlano(<?php echo json_encode($p); ?>)'><i class='bx bx-edit'></i> Editar</button>
                <button class="action-btn btn-toggle-act <?php echo $is_inativo?'ativar':''; ?>" onclick="confirmarToggle(<?php echo $p['id']; ?>,<?php echo $p['status']; ?>,'<?php echo addslashes($p['nome']); ?>')"><i class='bx bx-<?php echo $is_inativo?'check-circle':'x-circle'; ?>'></i> <?php echo $is_inativo?'Ativar':'Desativar'; ?></button>
                <button class="action-btn btn-duplicate" onclick="duplicarPlano(<?php echo json_encode($p); ?>)"><i class='bx bx-copy'></i> Duplicar</button>
                <button class="action-btn btn-danger" onclick="confirmarExcluir(<?php echo $p['id']; ?>,'<?php echo addslashes($p['nome']); ?>')"><i class='bx bx-trash'></i> Excluir</button>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state"><div class="empty-state-icon revenda"><i class='bx bx-store-alt'></i></div><h3>Nenhum plano para revendedores</h3><p>Clique em "Novo Plano" para criar o primeiro.</p></div>
    <?php endif; ?>
    </div>

</div>
</div>

<!-- MODAIS -->

<!-- Criar/Editar -->
<div id="modalPlano" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom purple" id="modalPlanoHeader"><h5 id="modalPlanoTitulo"><i class='bx bx-plus'></i> Novo Plano</h5><button class="modal-close" onclick="fecharModal('modalPlano')"><i class='bx bx-x'></i></button></div>
    <div class="modal-body-custom">
        <form method="POST" id="formPlano">
        <input type="hidden" name="plano_id" id="f_id" value="">
        <div class="form-grid">
            <div class="form-full form-group">
                <label class="form-label"><i class='bx bx-tag' style="color:#818cf8;"></i> Nome do Plano</label>
                <input type="text" class="form-control" name="nome" id="f_nome" placeholder="Ex: Plano Premium" required>
            </div>
            <div class="form-group">
                <label class="form-label"><i class='bx bx-dollar' style="color:#34d399;"></i> Valor (R$)</label>
                <input type="number" class="form-control" name="valor" id="f_valor" step="0.01" min="0.01" placeholder="0,00" required>
            </div>
            <div class="form-group">
                <label class="form-label"><i class='bx bx-group' style="color:#e879f9;"></i> Limite / Créditos</label>
                <input type="number" class="form-control" name="limite" id="f_limite" min="1" value="1" required>
            </div>
            <div class="form-group">
                <label class="form-label"><i class='bx bx-category' style="color:#fbbf24;"></i> Tipo</label>
                <select class="form-control" name="tipo" id="f_tipo" required>
                    <option value="usuario">👤 Usuário Final</option>
                    <option value="revenda">🏪 Revendedor</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label"><i class='bx bx-calendar' style="color:#60a5fa;"></i> Duração (dias)</label>
                <input type="number" class="form-control" name="duracao_dias" id="f_duracao" min="1" value="30" required>
            </div>
            <div class="form-full form-group">
                <label class="form-label"><i class='bx bx-note' style="color:#a78bfa;"></i> Descrição</label>
                <textarea class="form-control" name="descricao" id="f_descricao" rows="3" placeholder="Descreva os benefícios do plano..."></textarea>
            </div>
            <div class="form-group">
                <label class="form-label"><i class='bx bx-check-circle' style="color:#34d399;"></i> Status</label>
                <div class="toggle-row">
                    <label class="toggle-switch"><input type="checkbox" name="status" id="f_status" value="1" checked><span class="toggle-slider"></span></label>
                    <span class="toggle-label">Ativo (visível para venda)</span>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label"><i class='bx bx-star' style="color:#FFCC70;"></i> Destaque</label>
                <div class="toggle-row">
                    <label class="toggle-switch"><input type="checkbox" name="destaque" id="f_destaque" value="1"><span class="toggle-slider"></span></label>
                    <span class="toggle-label">Plano em destaque</span>
                </div>
            </div>
        </div>
        <div style="display:flex;justify-content:center;gap:8px;margin-top:16px;">
            <button type="button" class="btn-modal btn-modal-cancel" onclick="fecharModal('modalPlano')"><i class='bx bx-x'></i> Cancelar</button>
            <button type="submit" class="btn-modal btn-modal-ok" id="f_submit" name="criar_plano"><i class='bx bx-save'></i> Salvar Plano</button>
        </div>
        </form>
    </div>
</div></div></div>

<!-- Confirmar Exclusão -->
<div id="modalExcluir" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom error"><h5><i class='bx bx-trash'></i> Excluir Plano</h5><button class="modal-close" onclick="fecharModal('modalExcluir')"><i class='bx bx-x'></i></button></div>
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

<!-- Confirmar Toggle -->
<div id="modalToggle" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom warning" id="toggleHeader"><h5 id="toggleTitulo"><i class='bx bx-refresh'></i> Confirmar</h5><button class="modal-close" onclick="fecharModal('modalToggle')"><i class='bx bx-x'></i></button></div>
    <div class="modal-body-custom">
        <div class="modal-ic warning" id="toggleIcon"><i class='bx bx-question-mark'></i></div>
        <div class="modal-info-card" id="toggleInfo"></div>
        <p style="text-align:center;font-size:11px;color:rgba(255,255,255,0.4);" id="toggleDesc"></p>
    </div>
    <div class="modal-footer-custom">
        <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalToggle')"><i class='bx bx-x'></i> Cancelar</button>
        <button class="btn-modal btn-modal-warning" id="btnConfToggle"><i class='bx bx-check'></i> Confirmar</button>
    </div>
</div></div></div>

<!-- Sucesso -->
<div id="modalSucesso" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom green"><h5><i class='bx bx-check-circle'></i> Sucesso!</h5><button class="modal-close" onclick="fecharModal('modalSucesso')"><i class='bx bx-x'></i></button></div>
    <div class="modal-body-custom">
        <div class="modal-ic success"><i class='bx bx-check-circle'></i></div>
        <p style="text-align:center;font-size:14px;font-weight:600;" id="sucessoMsg"><?php echo htmlspecialchars($msg_sucesso); ?></p>
    </div>
    <div class="modal-footer-custom"><button class="btn-modal btn-modal-ok" onclick="fecharModal('modalSucesso')"><i class='bx bx-check'></i> OK</button></div>
</div></div></div>

<!-- Erro -->
<div id="modalErro" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom error"><h5><i class='bx bx-error-circle'></i> Erro!</h5><button class="modal-close" onclick="fecharModal('modalErro')"><i class='bx bx-x'></i></button></div>
    <div class="modal-body-custom">
        <div class="modal-ic error"><i class='bx bx-error-circle'></i></div>
        <p style="text-align:center;font-size:14px;font-weight:600;" id="erroMsg"><?php echo htmlspecialchars($msg_erro); ?></p>
    </div>
    <div class="modal-footer-custom"><button class="btn-modal btn-modal-danger" onclick="fecharModal('modalErro')"><i class='bx bx-x'></i> Fechar</button></div>
</div></div></div>

<!-- Processando -->
<div id="modalProcessando" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom processing"><h5><i class='bx bx-loader-alt bx-spin'></i> Processando...</h5></div>
    <div class="modal-body-custom"><div class="spinner-wrap"><div class="spinner-ring"></div><p style="font-size:13px;color:rgba(255,255,255,.6);">Aguarde...</p></div></div>
</div></div></div>

<script>
// Modal utils
function abrirModal(id){document.getElementById(id).classList.add('show');}
function fecharModal(id){document.getElementById(id).classList.remove('show');}
document.querySelectorAll('.modal-overlay').forEach(function(o){o.addEventListener('click',function(e){if(e.target===o)o.classList.remove('show');});});
document.addEventListener('keydown',function(e){if(e.key==='Escape')document.querySelectorAll('.modal-overlay.show').forEach(function(m){m.classList.remove('show');});});

// Tabs
function mudarTab(tab,btn){
    document.querySelectorAll('.tab-btn').forEach(function(b){b.classList.remove('active');});
    document.querySelectorAll('.tab-content').forEach(function(c){c.classList.remove('active');});
    if(btn)btn.classList.add('active');
    else document.querySelector('.tab-btn').classList.add('active');
    document.getElementById('tab-'+tab).classList.add('active');
}

// Criar plano
function abrirModalCriar(){
    document.getElementById('modalPlanoTitulo').innerHTML='<i class="bx bx-plus"></i> Novo Plano';
    document.getElementById('formPlano').reset();
    document.getElementById('f_id').value='';
    document.getElementById('f_status').checked=true;
    document.getElementById('f_destaque').checked=false;
    document.getElementById('f_limite').value='1';
    document.getElementById('f_duracao').value='30';
    document.getElementById('f_submit').name='criar_plano';
    document.getElementById('f_submit').innerHTML='<i class="bx bx-save"></i> Salvar Plano';
    abrirModal('modalPlano');
}

// Editar plano
function editarPlano(p){
    document.getElementById('modalPlanoTitulo').innerHTML='<i class="bx bx-edit"></i> Editar Plano';
    document.getElementById('f_id').value=p.id;
    document.getElementById('f_nome').value=p.nome;
    document.getElementById('f_tipo').value=p.tipo;
    document.getElementById('f_duracao').value=p.duracao_dias;
    document.getElementById('f_valor').value=p.valor;
    document.getElementById('f_limite').value=p.limite;
    document.getElementById('f_descricao').value=p.descricao||'';
    document.getElementById('f_status').checked=(p.status==1);
    document.getElementById('f_destaque').checked=(p.destaque==1);
    document.getElementById('f_submit').name='editar_plano';
    document.getElementById('f_submit').innerHTML='<i class="bx bx-save"></i> Salvar Alterações';
    abrirModal('modalPlano');
}

// Duplicar plano
function duplicarPlano(p){
    document.getElementById('modalPlanoTitulo').innerHTML='<i class="bx bx-copy"></i> Duplicar Plano';
    document.getElementById('f_id').value='';
    document.getElementById('f_nome').value=p.nome+' (Cópia)';
    document.getElementById('f_tipo').value=p.tipo;
    document.getElementById('f_duracao').value=p.duracao_dias;
    document.getElementById('f_valor').value=p.valor;
    document.getElementById('f_limite').value=p.limite;
    document.getElementById('f_descricao').value=p.descricao||'';
    document.getElementById('f_status').checked=true;
    document.getElementById('f_destaque').checked=false;
    document.getElementById('f_submit').name='criar_plano';
    document.getElementById('f_submit').innerHTML='<i class="bx bx-copy"></i> Criar Cópia';
    abrirModal('modalPlano');
}

// Excluir
var _exId=null;
function confirmarExcluir(id,nome){
    _exId=id;
    document.getElementById('excluirInfo').innerHTML='<div class="modal-info-row"><i class="bx bx-crown" style="color:#818cf8;"></i> <span>Plano:</span> <strong>'+nome+'</strong></div><div class="modal-info-row"><i class="bx bx-id-card" style="color:#fbbf24;"></i> <span>ID:</span> <strong>#'+id+'</strong></div>';
    document.getElementById('btnConfExcluir').onclick=function(){executarExcluir();};
    abrirModal('modalExcluir');
}
function executarExcluir(){
    fecharModal('modalExcluir');abrirModal('modalProcessando');
    var form=document.createElement('form');form.method='POST';
    form.innerHTML='<input type="hidden" name="excluir_plano" value="1"><input type="hidden" name="plano_id" value="'+_exId+'">';
    document.body.appendChild(form);form.submit();
}

// Toggle status
var _tgId=null,_tgNovo=null;
function confirmarToggle(id,statusAtual,nome){
    _tgId=id;_tgNovo=statusAtual==1?0:1;
    var acao=_tgNovo==1?'Ativar':'Desativar';
    var icone=_tgNovo==1?'bx-check-circle':'bx-x-circle';
    var cor=_tgNovo==1?'#34d399':'#f87171';
    document.getElementById('toggleTitulo').innerHTML='<i class="bx '+icone+'"></i> '+acao+' Plano';
    document.getElementById('toggleIcon').innerHTML='<i class="bx '+icone+'" style="font-size:34px;"></i>';
    document.getElementById('toggleInfo').innerHTML='<div class="modal-info-row"><i class="bx bx-crown" style="color:#818cf8;"></i> <span>Plano:</span> <strong>'+nome+'</strong></div><div class="modal-info-row"><i class="bx bx-info-circle" style="color:#fbbf24;"></i> <span>Ação:</span> <strong style="color:'+cor+';">'+acao+'</strong></div>';
    document.getElementById('toggleDesc').textContent=_tgNovo==1?'O plano ficará visível e disponível para venda.':'O plano será ocultado e não ficará mais disponível.';
    document.getElementById('btnConfToggle').onclick=function(){executarToggle();};
    abrirModal('modalToggle');
}
function executarToggle(){
    fecharModal('modalToggle');abrirModal('modalProcessando');
    var form=document.createElement('form');form.method='POST';
    form.innerHTML='<input type="hidden" name="toggle_status" value="1"><input type="hidden" name="plano_id" value="'+_tgId+'"><input type="hidden" name="novo_status" value="'+_tgNovo+'">';
    document.body.appendChild(form);form.submit();
}

// Mostrar modais de resultado
<?php if($show_success): ?>
document.addEventListener('DOMContentLoaded',function(){abrirModal('modalSucesso');});
<?php endif; ?>
<?php if($show_error): ?>
document.addEventListener('DOMContentLoaded',function(){abrirModal('modalErro');});
<?php endif; ?>
</script>
</body>
</html>

