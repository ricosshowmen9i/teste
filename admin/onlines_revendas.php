<?php
error_reporting(0);
session_start();
date_default_timezone_set('America/Sao_Paulo');

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

// ===== PROCESSAR AÇÕES =====
$msg = ''; $msg_type = ''; $show_modal = false;

// Excluir cliente via AJAX
if (isset($_POST['excluir_cliente_ajax'])) {
    $usuario = mysqli_real_escape_string($conn, $_POST['usuario']);
    // Remover da tabela ssh_accounts
    $r1 = $conn->query("DELETE FROM ssh_accounts WHERE login='$usuario'");
    // Remover da tabela onlines
    $r2 = $conn->query("DELETE FROM onlines WHERE usuario='$usuario'");
    // Tentar matar no servidor (se existir função)
    @shell_exec("pkill -u " . escapeshellarg($usuario) . " 2>/dev/null");
    @shell_exec("userdel -f " . escapeshellarg($usuario) . " 2>/dev/null");

    if ($r1) { echo 'ok'; } else { echo 'erro'; }
    exit;
}

// Desconectar cliente via AJAX
if (isset($_POST['desconectar_cliente_ajax'])) {
    $usuario = mysqli_real_escape_string($conn, $_POST['usuario']);
    $conn->query("DELETE FROM onlines WHERE usuario='$usuario'");
    @shell_exec("pkill -u " . escapeshellarg($usuario) . " 2>/dev/null");
    echo 'ok';
    exit;
}

// Desconectar todos de um revendedor via AJAX
if (isset($_POST['desconectar_todos_rev_ajax'])) {
    $rev_id = intval($_POST['rev_id']);
    $r = $conn->query("SELECT s.login FROM ssh_accounts s INNER JOIN onlines o ON o.usuario = s.login WHERE s.byid='$rev_id'");
    $count = 0;
    while ($row = $r->fetch_assoc()) {
        $conn->query("DELETE FROM onlines WHERE usuario='" . $row['login'] . "'");
        @shell_exec("pkill -u " . escapeshellarg($row['login']) . " 2>/dev/null");
        $count++;
    }
    echo $count;
    exit;
}

// ===== BUSCAR DADOS =====

// Clientes online de revendedores
$sql_clientes = "SELECT s.login, s.senha, s.limite, s.expira, s.status, s.byid, s.mainid,
                        o.quantidade as conexoes,
                        acc.id as rev_id,
                        acc.login as rev_nome
                 FROM ssh_accounts s
                 INNER JOIN onlines o ON o.usuario = s.login
                 LEFT JOIN accounts acc ON acc.id = s.byid
                 WHERE s.byid != '0' AND s.byid IS NOT NULL AND s.byid != ''
                 ORDER BY acc.login ASC, s.login ASC";
$result_clientes = mysqli_query($conn, $sql_clientes);

$clientes = [];
$revendedores = [];
$total_online = 0;
$total_conexoes = 0;

if ($result_clientes) {
    while ($row = mysqli_fetch_assoc($result_clientes)) {
        $clientes[] = $row;
        $total_online++;
        $total_conexoes += intval($row['conexoes']);

        $rev_id = $row['rev_id'] ?? $row['byid'];
        $rev_nome = !empty($row['rev_nome']) ? $row['rev_nome'] : 'Rev #' . $rev_id;

        if (!isset($revendedores[$rev_id])) {
            $revendedores[$rev_id] = [
                'nome' => $rev_nome,
                'id' => $rev_id,
                'clientes' => 0,
                'conexoes' => 0
            ];
        }
        $revendedores[$rev_id]['clientes']++;
        $revendedores[$rev_id]['conexoes'] += intval($row['conexoes']);
    }
}

$total_revendedores = count($revendedores);

// Top revendedores por clientes online
usort($revendedores, function($a, $b) { return $b['clientes'] - $a['clientes']; });
$top_revs = array_slice($revendedores, 0, 5);

// Revendedores para filtro (ordenar por nome)
$revs_filtro = $revendedores;
usort($revs_filtro, function($a, $b) { return strcasecmp($a['nome'], $b['nome']); });
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Onlines de Revendedores - Admin</title>
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
.stats-card{background:linear-gradient(135deg,var(--fundo_claro,#1e293b),var(--fundo,#0f172a));border-radius:20px;padding:20px 24px;margin-bottom:20px;border:1px solid rgba(16,185,129,0.15);display:flex;align-items:center;gap:20px;position:relative;overflow:hidden;transition:all .3s;}
.stats-card:hover{transform:translateY(-2px);border-color:#10b981;}
.stats-card-icon{width:60px;height:60px;background:linear-gradient(135deg,#10b981,#059669);border-radius:18px;display:flex;align-items:center;justify-content:center;font-size:32px;color:white;flex-shrink:0;position:relative;}
.stats-card-icon .pulse-ring{position:absolute;inset:-4px;border:2px solid rgba(16,185,129,0.4);border-radius:22px;animation:pulseRing 2s ease-in-out infinite;}
@keyframes pulseRing{0%,100%{transform:scale(1);opacity:1;}50%{transform:scale(1.1);opacity:0.3;}}
.stats-card-content{flex:1;}
.stats-card-title{font-size:13px;font-weight:600;color:rgba(255,255,255,0.5);text-transform:uppercase;margin-bottom:5px;}
.stats-card-value{font-size:36px;font-weight:800;background:linear-gradient(135deg,#34d399,#10b981);-webkit-background-clip:text;-webkit-text-fill-color:transparent;line-height:1;}
.stats-card-subtitle{font-size:12px;color:rgba(255,255,255,0.4);margin-top:4px;}
.stats-card-decoration{position:absolute;right:20px;top:50%;transform:translateY(-50%);font-size:80px;opacity:0.05;}

/* Mini Stats */
.mini-stats{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;}
.mini-stat{flex:1;min-width:80px;background:rgba(255,255,255,0.04);border-radius:12px;padding:12px;border:1px solid rgba(255,255,255,0.06);text-align:center;transition:all .2s;}
.mini-stat:hover{border-color:#10b981;transform:translateY(-2px);}
.mini-stat-val{font-size:18px;font-weight:800;}
.mini-stat-lbl{font-size:9px;color:rgba(255,255,255,0.35);text-transform:uppercase;margin-top:2px;}

/* Top Revendedores */
.top-revs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;}
.top-rev{background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.06);border-radius:10px;padding:8px 12px;display:flex;align-items:center;gap:8px;flex:1;min-width:160px;transition:all .2s;}
.top-rev:hover{border-color:var(--primaria,#4158D0);transform:translateY(-1px);}
.top-rev-rank{width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:800;flex-shrink:0;}
.top-rev-rank.r1{background:linear-gradient(135deg,#fbbf24,#f59e0b);color:#92400e;}
.top-rev-rank.r2{background:linear-gradient(135deg,#94a3b8,#64748b);color:#1e293b;}
.top-rev-rank.r3{background:linear-gradient(135deg,#d97706,#b45309);color:#fff;}
.top-rev-rank.rn{background:rgba(255,255,255,0.1);color:rgba(255,255,255,0.5);}
.top-rev-info{flex:1;min-width:0;}
.top-rev-name{font-size:11px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.top-rev-count{font-size:9px;color:rgba(255,255,255,0.4);}

/* Filtros */
.filters-row{display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;}
.filter-input{flex:1;min-width:150px;padding:8px 12px;background:rgba(255,255,255,0.06);border:1.5px solid rgba(255,255,255,0.08);border-radius:10px;color:#fff;font-size:12px;font-family:inherit;outline:none;transition:all .25s;}
.filter-input:focus{border-color:#10b981;background:rgba(255,255,255,0.09);}
.filter-input::placeholder{color:rgba(255,255,255,0.25);}
select.filter-input{cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%23ffffff' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;}
select.filter-input option{background:#1e293b;color:#fff;}
.btn-filter{padding:8px 16px;border:none;border-radius:10px;font-weight:700;font-size:11px;cursor:pointer;display:flex;align-items:center;gap:5px;color:white;transition:all .2s;font-family:inherit;}
.btn-filter:hover{transform:translateY(-1px);filter:brightness(1.08);}
.btn-refresh{background:linear-gradient(135deg,#10b981,#059669);}
.btn-disconnect-all{background:linear-gradient(135deg,#f59e0b,#d97706);}

/* Clientes Grid */
.clientes-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px;}

/* Separator por revendedor */
.rev-separator{grid-column:1/-1;display:flex;align-items:center;gap:10px;padding:10px 14px;background:rgba(255,255,255,0.03);border-radius:12px;border:1px solid rgba(255,255,255,0.06);margin-top:4px;}
.rev-separator-icon{width:32px;height:32px;background:linear-gradient(135deg,var(--primaria,#4158D0),var(--secundaria,#C850C0));border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:15px;color:white;flex-shrink:0;}
.rev-separator-text{flex:1;}
.rev-separator-name{font-size:12px;font-weight:700;}
.rev-separator-meta{font-size:9px;color:rgba(255,255,255,0.4);}
.rev-separator-actions{display:flex;gap:5px;}
.btn-rev-action{padding:5px 10px;border:none;border-radius:7px;font-size:9px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:3px;color:white;transition:all .2s;font-family:inherit;}
.btn-rev-action:hover{transform:translateY(-1px);filter:brightness(1.08);}
.btn-rev-disconnect{background:linear-gradient(135deg,#f59e0b,#d97706);}
.btn-rev-view{background:linear-gradient(135deg,#3b82f6,#2563eb);}

/* Cliente Card */
.cliente-card{background:var(--fundo_claro,#1e293b);border-radius:14px;overflow:hidden;border:1px solid rgba(16,185,129,0.12);transition:all .25s;position:relative;}
.cliente-card:hover{transform:translateY(-3px);border-color:#10b981;box-shadow:0 8px 25px rgba(16,185,129,0.12);}
.cliente-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,#10b981,#34d399,#6ee7b7,#34d399,#10b981);background-size:200% 100%;animation:shimmerGreen 3s linear infinite;}
@keyframes shimmerGreen{0%{background-position:200% 0}100%{background-position:-200% 0}}

.cliente-header{background:linear-gradient(135deg,var(--primaria,#4158D0),var(--secundaria,#C850C0));padding:10px 12px;display:flex;align-items:center;gap:10px;}
.cliente-avatar{width:36px;height:36px;background:rgba(255,255,255,0.2);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:17px;color:white;flex-shrink:0;position:relative;}
.online-dot{position:absolute;bottom:-2px;right:-2px;width:10px;height:10px;background:#10b981;border-radius:50%;border:2px solid var(--primaria,#4158D0);}
.online-dot::after{content:'';position:absolute;inset:-3px;border-radius:50%;background:#10b981;animation:pulseDot 2s ease-in-out infinite;opacity:0;}
@keyframes pulseDot{0%,100%{transform:scale(1);opacity:0.4;}50%{transform:scale(2);opacity:0;}}
.cliente-text{flex:1;min-width:0;}
.cliente-nome{font-size:13px;font-weight:700;color:white;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.cliente-rev-tag{display:inline-flex;align-items:center;gap:3px;font-size:8px;background:rgba(255,255,255,0.2);padding:2px 7px;border-radius:12px;color:rgba(255,255,255,0.9);margin-top:2px;}
.btn-copy-card{background:rgba(255,255,255,0.15);border:none;border-radius:8px;padding:5px 10px;color:white;font-size:10px;font-weight:600;cursor:pointer;transition:all .2s;display:flex;align-items:center;gap:3px;flex-shrink:0;font-family:inherit;}
.btn-copy-card:hover{background:rgba(255,255,255,0.25);}
.btn-copy-card.copied{background:#10b981;}

.cliente-body{padding:10px;}

/* Status cards */
.status-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:8px;}
.status-mini{background:rgba(255,255,255,0.03);border-radius:8px;padding:7px;text-align:center;border:1px solid rgba(255,255,255,0.04);transition:all .2s;}
.status-mini:hover{border-color:rgba(16,185,129,0.2);background:rgba(255,255,255,0.05);}
.status-mini-icon{font-size:14px;margin-bottom:2px;}
.status-mini-label{font-size:7px;color:rgba(255,255,255,0.35);text-transform:uppercase;font-weight:600;letter-spacing:.3px;}
.status-mini-value{font-size:11px;font-weight:700;margin-top:1px;}

/* Usage bar */
.usage-bar{margin-bottom:8px;}
.usage-header{display:flex;justify-content:space-between;margin-bottom:3px;}
.usage-label{font-size:8px;color:rgba(255,255,255,0.35);font-weight:600;}
.usage-count{font-size:9px;font-weight:700;}
.usage-count.ok{color:#34d399;} .usage-count.warn{color:#fbbf24;} .usage-count.full{color:#f87171;}
.usage-track{height:4px;background:rgba(255,255,255,0.06);border-radius:10px;overflow:hidden;}
.usage-fill{height:100%;border-radius:10px;transition:width .6s ease;}

/* Info rows */
.info-row{display:flex;align-items:center;justify-content:space-between;padding:5px 8px;background:rgba(255,255,255,0.03);border-radius:7px;margin-bottom:5px;}
.info-label{font-size:9px;color:rgba(255,255,255,0.4);display:flex;align-items:center;gap:4px;}
.info-label i{font-size:11px;}
.info-value{font-size:10px;font-weight:600;}
.expiry-ok{color:#34d399;} .expiry-warn{color:#fbbf24;} .expiry-danger{color:#f87171;}

/* Actions */
.cliente-actions{display:flex;gap:4px;margin-top:6px;}
.action-btn{flex:1;padding:6px;border:none;border-radius:7px;font-weight:600;font-size:9px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:3px;color:white;transition:all .2s;font-family:inherit;}
.action-btn:hover{transform:translateY(-1px);filter:brightness(1.08);}
.btn-disconnect{background:linear-gradient(135deg,#f59e0b,#d97706);}
.btn-delete{background:linear-gradient(135deg,#dc2626,#b91c1c);}
.btn-info{background:linear-gradient(135deg,#3b82f6,#2563eb);}

/* Empty */
.empty-state{grid-column:1/-1;text-align:center;padding:50px 20px;background:var(--fundo_claro,#1e293b);border-radius:16px;border:1px solid rgba(255,255,255,0.08);}
.empty-state-icon{width:70px;height:70px;border-radius:50%;background:rgba(16,185,129,0.1);display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:32px;color:#34d399;border:2px solid rgba(16,185,129,0.2);}
.empty-state h3{font-size:15px;margin-bottom:5px;}
.empty-state p{font-size:10px;color:rgba(255,255,255,0.3);}

/* Modal */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.85);display:none;align-items:center;justify-content:center;z-index:9999;backdrop-filter:blur(8px);padding:16px;}
.modal-overlay.show{display:flex;}
.modal-container{animation:modalIn .3s ease;max-width:480px;width:92%;}
@keyframes modalIn{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
.modal-content-custom{background:var(--fundo_claro,#1e293b);border-radius:20px;overflow:hidden;border:1px solid rgba(255,255,255,.1);box-shadow:0 25px 60px rgba(0,0,0,.5);}
.modal-header-custom{padding:14px 18px;display:flex;align-items:center;justify-content:space-between;}
.modal-header-custom h5{margin:0;display:flex;align-items:center;gap:8px;font-size:14px;font-weight:700;color:#fff;}
.modal-header-custom.green{background:linear-gradient(135deg,#10b981,#059669);}
.modal-header-custom.error{background:linear-gradient(135deg,#dc2626,#b91c1c);}
.modal-header-custom.warning{background:linear-gradient(135deg,#f59e0b,#d97706);}
.modal-close{background:rgba(255,255,255,.15);border:none;color:#fff;font-size:18px;cursor:pointer;width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;transition:all .2s;}
.modal-close:hover{background:rgba(255,255,255,.25);transform:rotate(90deg);}
.modal-body-custom{padding:18px;}
.modal-footer-custom{border-top:1px solid rgba(255,255,255,.07);padding:12px 18px;display:flex;justify-content:center;gap:8px;}
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
.btn-modal-warning{background:linear-gradient(135deg,#f59e0b,#d97706);}
.spinner-wrap{display:flex;flex-direction:column;align-items:center;gap:14px;padding:20px 0;}
.spinner-ring{width:44px;height:44px;border:3px solid rgba(255,255,255,.08);border-top-color:#10b981;border-right-color:#34d399;border-radius:50%;animation:spin .8s linear infinite;}
@keyframes spin{to{transform:rotate(360deg)}}

/* Toast */
.toast-notification{position:fixed;bottom:20px;right:20px;color:#fff;padding:10px 16px;border-radius:10px;display:flex;align-items:center;gap:8px;z-index:10001;animation:toastIn .3s ease;font-weight:600;font-size:12px;box-shadow:0 8px 20px rgba(0,0,0,.3);}
.toast-notification.ok{background:linear-gradient(135deg,#10b981,#059669);}
.toast-notification.err{background:linear-gradient(135deg,#dc2626,#b91c1c);}
.toast-notification.warn{background:linear-gradient(135deg,#f59e0b,#d97706);}
@keyframes toastIn{from{transform:translateX(110%);opacity:0}to{transform:translateX(0);opacity:1}}

.pagination-info{text-align:center;margin-top:16px;color:rgba(255,255,255,0.3);font-size:10px;}

/* Auto refresh indicator */
.auto-refresh{display:flex;align-items:center;gap:5px;font-size:9px;color:rgba(255,255,255,0.3);margin-bottom:10px;}
.auto-refresh-dot{width:6px;height:6px;background:#10b981;border-radius:50%;animation:blink 2s infinite;}
@keyframes blink{0%,100%{opacity:1;}50%{opacity:0.3;}}

@media(max-width:768px){
    .app-content{margin-left:0!important;}
    .content-wrapper{padding:10px!important;}
    .clientes-grid{grid-template-columns:1fr;}
    .stats-card{padding:14px;gap:14px;}
    .stats-card-icon{width:48px;height:48px;font-size:24px;}
    .stats-card-value{font-size:28px;}
    .mini-stats{flex-wrap:wrap;}
    .mini-stat{min-width:70px;}
    .top-revs{flex-direction:column;}
    .filters-row{flex-direction:column;}
    .rev-separator{flex-wrap:wrap;}
    .rev-separator-actions{width:100%;justify-content:flex-end;}
    .cliente-actions{display:grid;grid-template-columns:1fr 1fr 1fr;}
}
</style>
</head>
<body class="<?php echo htmlspecialchars(getBodyClass($temaAtual ?? ['classe'=>'theme-dark'])); ?>">
<div class="app-content content">
<div class="content-overlay"></div>
<div class="content-wrapper">

    <!-- Stats -->
    <div class="stats-card">
        <div class="stats-card-icon"><div class="pulse-ring"></div><i class='bx bx-group'></i></div>
        <div class="stats-card-content">
            <div class="stats-card-title">Clientes Online de Revendedores</div>
            <div class="stats-card-value"><?php echo $total_online; ?> Online</div>
            <div class="stats-card-subtitle">Monitoramento em tempo real de todos os revendedores</div>
        </div>
        <div class="stats-card-decoration"><i class='bx bx-group'></i></div>
    </div>

    <!-- Mini Stats -->
    <div class="mini-stats">
        <div class="mini-stat"><div class="mini-stat-val" style="color:#34d399;"><?php echo $total_online; ?></div><div class="mini-stat-lbl">Clientes Online</div></div>
        <div class="mini-stat"><div class="mini-stat-val" style="color:#60a5fa;"><?php echo $total_revendedores; ?></div><div class="mini-stat-lbl">Revendedores</div></div>
        <div class="mini-stat"><div class="mini-stat-val" style="color:#fbbf24;"><?php echo $total_conexoes; ?></div><div class="mini-stat-lbl">Conexões Totais</div></div>
        <div class="mini-stat"><div class="mini-stat-val" style="color:#a78bfa;"><?php echo $total_revendedores > 0 ? round($total_online / $total_revendedores, 1) : 0; ?></div><div class="mini-stat-lbl">Média/Rev</div></div>
    </div>

    <!-- Top Revendedores -->
    <?php if (!empty($top_revs)): ?>
    <div class="top-revs">
    <?php foreach ($top_revs as $i => $rev):
        $rank_class = $i == 0 ? 'r1' : ($i == 1 ? 'r2' : ($i == 2 ? 'r3' : 'rn'));
    ?>
    <div class="top-rev">
        <div class="top-rev-rank <?php echo $rank_class; ?>"><?php echo $i + 1; ?></div>
        <div class="top-rev-info">
            <div class="top-rev-name"><?php echo htmlspecialchars($rev['nome']); ?></div>
            <div class="top-rev-count"><?php echo $rev['clientes']; ?> clientes · <?php echo $rev['conexoes']; ?> conexões</div>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Filtros -->
    <div class="filters-row">
        <input type="text" class="filter-input" id="searchCliente" placeholder="🔍 Buscar cliente..." oninput="filtrarClientes()">
        <select class="filter-input" id="filterRev" style="max-width:200px;" onchange="filtrarClientes()">
            <option value="todos">Todos os Revendedores</option>
            <?php foreach ($revs_filtro as $rev): ?>
            <option value="<?php echo $rev['id']; ?>"><?php echo htmlspecialchars($rev['nome']); ?> (<?php echo $rev['clientes']; ?>)</option>
            <?php endforeach; ?>
        </select>
        <select class="filter-input" id="filterExpiry" style="max-width:160px;" onchange="filtrarClientes()">
            <option value="todos">Todos Status</option>
            <option value="ok">✅ Normal</option>
            <option value="expirando">⚠️ Expirando</option>
            <option value="expirado">❌ Expirado</option>
        </select>
        <button class="btn-filter btn-refresh" onclick="location.reload()"><i class='bx bx-refresh'></i> Atualizar</button>
    </div>

    <div class="auto-refresh"><span class="auto-refresh-dot"></span> Atualiza automaticamente a cada 30 segundos · <?php echo date('d/m/Y H:i:s'); ?></div>

    <!-- Grid de Clientes -->
    <div class="clientes-grid" id="clientesGrid">
    <?php if (!empty($clientes)):
        $current_rev = null;
        foreach ($clientes as $c):
            $rev_id = $c['rev_id'] ?? $c['byid'];
            $rev_nome = !empty($c['rev_nome']) ? $c['rev_nome'] : 'Rev #' . $rev_id;

            // Separator por revendedor
            if ($current_rev !== $rev_id):
                $current_rev = $rev_id;
                $rev_count = $revendedores[$rev_id]['clientes'] ?? 0;
                $rev_conex = $revendedores[$rev_id]['conexoes'] ?? 0;
    ?>
    <div class="rev-separator" data-rev-id="<?php echo $rev_id; ?>">
        <div class="rev-separator-icon"><i class='bx bx-store'></i></div>
        <div class="rev-separator-text">
            <div class="rev-separator-name"><?php echo htmlspecialchars($rev_nome); ?></div>
            <div class="rev-separator-meta"><?php echo $rev_count; ?> clientes online · <?php echo $rev_conex; ?> conexões</div>
        </div>
        <div class="rev-separator-actions">
            <button class="btn-rev-action btn-rev-disconnect" onclick="desconectarTodosRev(<?php echo $rev_id; ?>,'<?php echo addslashes($rev_nome); ?>')"><i class='bx bx-power-off'></i> Desconectar Todos</button>
            <a href="visualizar.php?id=<?php echo $rev_id; ?>" class="btn-rev-action btn-rev-view"><i class='bx bx-show'></i> Ver Revenda</a>
        </div>
    </div>
    <?php endif;

            $expira = strtotime($c['expira']);
            $hoje = time();
            $dias = floor(($expira - $hoje) / 86400);
            $expiry_class = $dias <= 0 ? 'expiry-danger' : ($dias <= 5 ? 'expiry-warn' : 'expiry-ok');
            $expiry_filter = $dias <= 0 ? 'expirado' : ($dias <= 5 ? 'expirando' : 'ok');
            $expira_fmt = date('d/m/Y', $expira);

            $conexoes = intval($c['conexoes']);
            $limite = intval($c['limite']);
            $pct = $limite > 0 ? round(($conexoes / $limite) * 100) : 0;
            $fill_color = $pct >= 100 ? '#ef4444' : ($pct >= 70 ? '#f97316' : ($pct >= 40 ? '#fbbf24' : '#34d399'));
            $usage_class = $pct >= 100 ? 'full' : ($pct >= 70 ? 'warn' : 'ok');

            $suspenso = ($c['mainid'] == 'Suspenso');
    ?>
    <div class="cliente-card" data-nome="<?php echo strtolower($c['login']); ?>" data-rev="<?php echo $rev_id; ?>" data-expiry="<?php echo $expiry_filter; ?>">
        <div class="cliente-header">
            <div class="cliente-avatar">
                <i class='bx bx-user'></i>
                <div class="online-dot"></div>
            </div>
            <div class="cliente-text">
                <div class="cliente-nome"><?php echo htmlspecialchars($c['login']); ?></div>
                <div class="cliente-rev-tag"><i class='bx bx-store'></i> <?php echo htmlspecialchars($rev_nome); ?></div>
            </div>
            <button class="btn-copy-card" onclick="copiarInfo(this,'<?php echo addslashes($c['login']); ?>','<?php echo addslashes($c['senha']); ?>','<?php echo $expira_fmt; ?>','<?php echo $limite; ?>','<?php echo addslashes($rev_nome); ?>')"><i class='bx bx-copy'></i> Copiar</button>
        </div>
        <div class="cliente-body">
            <div class="status-grid">
                <div class="status-mini">
                    <div class="status-mini-icon"><i class='bx bx-wifi' style="color:#34d399;"></i></div>
                    <div class="status-mini-label">STATUS</div>
                    <div class="status-mini-value" style="color:<?php echo $suspenso?'#f87171':'#34d399'; ?>;"><?php echo $suspenso?'Suspenso':'Online'; ?></div>
                </div>
                <div class="status-mini">
                    <div class="status-mini-icon"><i class='bx bx-link' style="color:#fbbf24;"></i></div>
                    <div class="status-mini-label">CONEXÕES</div>
                    <div class="status-mini-value"><?php echo $conexoes; ?>/<?php echo $limite; ?></div>
                </div>
            </div>

            <div class="usage-bar">
                <div class="usage-header">
                    <span class="usage-label">USO DE CONEXÕES</span>
                    <span class="usage-count <?php echo $usage_class; ?>"><?php echo $pct; ?>%</span>
                </div>
                <div class="usage-track"><div class="usage-fill" style="width:<?php echo min(100,$pct); ?>%;background:<?php echo $fill_color; ?>;"></div></div>
            </div>

            <div class="info-row">
                <div class="info-label"><i class='bx bx-lock-alt'></i> Senha</div>
                <div class="info-value" style="font-family:monospace;letter-spacing:0.5px;"><?php echo htmlspecialchars($c['senha']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label"><i class='bx bx-calendar'></i> Expira</div>
                <div class="info-value <?php echo $expiry_class; ?>">
                    <?php echo $expira_fmt; ?>
                    <?php if ($dias > 0): ?>(<?php echo $dias; ?>d)<?php elseif($dias == 0): ?>(Hoje)<?php else: ?>(Expirado)<?php endif; ?>
                </div>
            </div>

            <div class="cliente-actions">
                <button class="action-btn btn-info" onclick="copiarInfo(document.querySelector('[data-nome=\'<?php echo strtolower($c['login']); ?>\'] .btn-copy-card'),'<?php echo addslashes($c['login']); ?>','<?php echo addslashes($c['senha']); ?>','<?php echo $expira_fmt; ?>','<?php echo $limite; ?>','<?php echo addslashes($rev_nome); ?>')"><i class='bx bx-copy'></i> Copiar</button>
                <button class="action-btn btn-disconnect" onclick="desconectarCliente('<?php echo addslashes($c['login']); ?>')"><i class='bx bx-power-off'></i> Desconectar</button>
                <button class="action-btn btn-delete" onclick="excluirCliente('<?php echo addslashes($c['login']); ?>')"><i class='bx bx-trash'></i> Excluir</button>
            </div>
        </div>
    </div>
    <?php endforeach; else: ?>
    <div class="empty-state">
        <div class="empty-state-icon"><i class='bx bx-user-x'></i></div>
        <h3>Nenhum cliente online</h3>
        <p>No momento não há clientes online de revendedores</p>
    </div>
    <?php endif; ?>
    </div>

    <div class="pagination-info">Total: <?php echo $total_online; ?> cliente(s) online de <?php echo $total_revendedores; ?> revendedor(es) — <?php echo date('d/m/Y H:i:s'); ?></div>

</div>
</div>

<!-- Modais -->

<!-- Confirmar exclusão -->
<div id="modalExcluir" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom error"><h5><i class='bx bx-trash'></i> Excluir Cliente</h5><button class="modal-close" onclick="fecharModal('modalExcluir')"><i class='bx bx-x'></i></button></div>
    <div class="modal-body-custom">
        <div class="modal-ic error"><i class='bx bx-error-circle'></i></div>
        <div class="modal-info-card" id="excluirInfo"></div>
        <p style="text-align:center;font-size:11px;color:#f87171;font-weight:600;">⚠️ O cliente será removido permanentemente e desconectado!</p>
    </div>
    <div class="modal-footer-custom">
        <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalExcluir')"><i class='bx bx-x'></i> Cancelar</button>
        <button class="btn-modal btn-modal-danger" id="btnConfExcluir"><i class='bx bx-trash'></i> Excluir</button>
    </div>
</div></div></div>

<!-- Confirmar desconectar -->
<div id="modalDesconectar" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom warning"><h5><i class='bx bx-power-off'></i> Desconectar</h5><button class="modal-close" onclick="fecharModal('modalDesconectar')"><i class='bx bx-x'></i></button></div>
    <div class="modal-body-custom">
        <div class="modal-ic warning"><i class='bx bx-power-off'></i></div>
        <div class="modal-info-card" id="desconectarInfo"></div>
        <p style="text-align:center;font-size:11px;color:rgba(255,255,255,0.4);">O cliente será desconectado mas a conta permanece ativa.</p>
    </div>
    <div class="modal-footer-custom">
        <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalDesconectar')"><i class='bx bx-x'></i> Cancelar</button>
        <button class="btn-modal btn-modal-warning" id="btnConfDesconectar"><i class='bx bx-power-off'></i> Desconectar</button>
    </div>
</div></div></div>

<!-- Confirmar desconectar todos -->
<div id="modalDesconectarTodos" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom warning"><h5><i class='bx bx-power-off'></i> Desconectar Todos</h5><button class="modal-close" onclick="fecharModal('modalDesconectarTodos')"><i class='bx bx-x'></i></button></div>
    <div class="modal-body-custom">
        <div class="modal-ic warning"><i class='bx bx-group'></i></div>
        <div class="modal-info-card" id="desconectarTodosInfo"></div>
        <p style="text-align:center;font-size:11px;color:rgba(255,255,255,0.4);">Todos os clientes deste revendedor serão desconectados.</p>
    </div>
    <div class="modal-footer-custom">
        <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalDesconectarTodos')"><i class='bx bx-x'></i> Cancelar</button>
        <button class="btn-modal btn-modal-warning" id="btnConfDesconectarTodos"><i class='bx bx-power-off'></i> Desconectar Todos</button>
    </div>
</div></div></div>

<!-- Processando -->
<div id="modalProcessando" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom green"><h5><i class='bx bx-loader-alt bx-spin'></i> Processando...</h5></div>
    <div class="modal-body-custom"><div class="spinner-wrap"><div class="spinner-ring"></div><p style="font-size:13px;color:rgba(255,255,255,.6);">Aguarde...</p></div></div>
</div></div></div>

<!-- Sucesso -->
<div id="modalSucesso" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom green"><h5><i class='bx bx-check-circle'></i> Sucesso!</h5><button class="modal-close" onclick="fecharModal('modalSucesso');location.reload();"><i class='bx bx-x'></i></button></div>
    <div class="modal-body-custom"><div class="modal-ic success"><i class='bx bx-check-circle'></i></div><p style="text-align:center;font-size:14px;font-weight:600;" id="sucessoMsg"></p></div>
    <div class="modal-footer-custom"><button class="btn-modal btn-modal-ok" onclick="fecharModal('modalSucesso');location.reload();"><i class='bx bx-check'></i> OK</button></div>
</div></div></div>

<script>
// Modal utils
function abrirModal(id){document.getElementById(id).classList.add('show');}
function fecharModal(id){document.getElementById(id).classList.remove('show');}
document.querySelectorAll('.modal-overlay').forEach(function(o){o.addEventListener('click',function(e){if(e.target===o)o.classList.remove('show');});});
document.addEventListener('keydown',function(e){if(e.key==='Escape')document.querySelectorAll('.modal-overlay.show').forEach(function(m){m.classList.remove('show');});});

function toast(msg,tipo){var t=document.createElement('div');t.className='toast-notification '+(tipo||'ok');t.innerHTML='<i class="bx '+(tipo==='err'?'bx-error-circle':tipo==='warn'?'bx-info-circle':'bx-check-circle')+'"></i> '+msg;document.body.appendChild(t);setTimeout(function(){t.remove();},3500);}

// Filtros
function filtrarClientes(){
    var busca=document.getElementById('searchCliente').value.toLowerCase();
    var rev=document.getElementById('filterRev').value;
    var expiry=document.getElementById('filterExpiry').value;

    document.querySelectorAll('.cliente-card').forEach(function(c){
        var nome=c.dataset.nome||'';
        var cRev=c.dataset.rev||'';
        var cExp=c.dataset.expiry||'';
        var mb=nome.includes(busca);
        var mr=rev==='todos'||cRev===rev;
        var me=expiry==='todos'||cExp===expiry;
        c.style.display=(mb&&mr&&me)?'':'none';
    });

    // Mostrar/esconder separadores
    document.querySelectorAll('.rev-separator').forEach(function(s){
        var rid=s.dataset.revId;
        if(rev!=='todos'&&rid!==rev){s.style.display='none';return;}
        // Verificar se tem cards visíveis deste rev
        var cards=document.querySelectorAll('.cliente-card[data-rev="'+rid+'"]');
        var anyVisible=false;
        cards.forEach(function(c){if(c.style.display!=='none')anyVisible=true;});
        s.style.display=anyVisible?'':'none';
    });
}

// Copiar info
function copiarInfo(btn,usuario,senha,expira,limite,revendedor){
    var texto='📋 INFORMAÇÕES DO CLIENTE\n━━━━━━━━━━━━━━━━━━━━━\n';
    texto+='👤 Login: '+usuario+'\n';
    texto+='🔑 Senha: '+senha+'\n';
    texto+='🔗 Limite: '+limite+' conexões\n';
    texto+='📅 Expira: '+expira+'\n';
    texto+='✅ Status: Online\n';
    texto+='🏪 Revendedor: '+revendedor+'\n';
    texto+='━━━━━━━━━━━━━━━━━━━━━\n';
    texto+='📆 '+new Date().toLocaleString('pt-BR');

    navigator.clipboard.writeText(texto).then(function(){
        if(btn){
            var orig=btn.innerHTML;
            btn.classList.add('copied');
            btn.innerHTML='<i class="bx bx-check"></i> Copiado!';
            setTimeout(function(){btn.classList.remove('copied');btn.innerHTML=orig;},2000);
        }
        toast('Informações copiadas!','ok');
    });
}

// Excluir
function excluirCliente(usuario){
    document.getElementById('excluirInfo').innerHTML='<div class="modal-info-row"><i class="bx bx-user" style="color:#60a5fa;"></i> <span>Cliente:</span> <strong>'+usuario+'</strong></div>';
    document.getElementById('btnConfExcluir').onclick=function(){executarExcluir(usuario);};
    abrirModal('modalExcluir');
}
function executarExcluir(usuario){
    fecharModal('modalExcluir');abrirModal('modalProcessando');
    fetch(window.location.pathname,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'excluir_cliente_ajax=1&usuario='+encodeURIComponent(usuario)}).then(function(r){return r.text();}).then(function(data){
        fecharModal('modalProcessando');
        if(data.trim()==='ok'){
            document.getElementById('sucessoMsg').textContent='Cliente "'+usuario+'" excluído com sucesso!';
            abrirModal('modalSucesso');
        }else{toast('Erro ao excluir!','err');}
    }).catch(function(){fecharModal('modalProcessando');toast('Erro de conexão!','err');});
}

// Desconectar
function desconectarCliente(usuario){
    document.getElementById('desconectarInfo').innerHTML='<div class="modal-info-row"><i class="bx bx-user" style="color:#fbbf24;"></i> <span>Cliente:</span> <strong>'+usuario+'</strong></div><div class="modal-info-row"><i class="bx bx-power-off" style="color:#f97316;"></i> <span>Ação:</span> <strong style="color:#fbbf24;">Desconectar</strong></div>';
    document.getElementById('btnConfDesconectar').onclick=function(){executarDesconectar(usuario);};
    abrirModal('modalDesconectar');
}
function executarDesconectar(usuario){
    fecharModal('modalDesconectar');abrirModal('modalProcessando');
    fetch(window.location.pathname,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'desconectar_cliente_ajax=1&usuario='+encodeURIComponent(usuario)}).then(function(r){return r.text();}).then(function(data){
        fecharModal('modalProcessando');
        if(data.trim()==='ok'){
            document.getElementById('sucessoMsg').textContent='Cliente "'+usuario+'" desconectado!';
            abrirModal('modalSucesso');
        }else{toast('Erro!','err');}
    }).catch(function(){fecharModal('modalProcessando');toast('Erro de conexão!','err');});
}

// Desconectar todos de um revendedor
function desconectarTodosRev(revId,revNome){
    document.getElementById('desconectarTodosInfo').innerHTML='<div class="modal-info-row"><i class="bx bx-store" style="color:#a78bfa;"></i> <span>Revendedor:</span> <strong>'+revNome+'</strong></div><div class="modal-info-row"><i class="bx bx-power-off" style="color:#f97316;"></i> <span>Ação:</span> <strong style="color:#fbbf24;">Desconectar TODOS os clientes</strong></div>';
    document.getElementById('btnConfDesconectarTodos').onclick=function(){executarDesconectarTodos(revId,revNome);};
    abrirModal('modalDesconectarTodos');
}
function executarDesconectarTodos(revId,revNome){
    fecharModal('modalDesconectarTodos');abrirModal('modalProcessando');
    fetch(window.location.pathname,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'desconectar_todos_rev_ajax=1&rev_id='+revId}).then(function(r){return r.text();}).then(function(data){
        fecharModal('modalProcessando');
        var count=parseInt(data)||0;
        document.getElementById('sucessoMsg').textContent=count+' cliente(s) de "'+revNome+'" desconectado(s)!';
        abrirModal('modalSucesso');
    }).catch(function(){fecharModal('modalProcessando');toast('Erro de conexão!','err');});
}

// Auto refresh a cada 30s
setTimeout(function(){location.reload();},30000);
</script>
</body>
</html>

