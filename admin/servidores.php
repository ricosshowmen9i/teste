<?php // @ioncube.dk $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf -> "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs" RANDOM
    $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf = "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs";
    function aleatorio_servidores($input)
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

if (!file_exists('suspenderrev.php')) {
    exit("<script>alert('Token Invalido!');</script>");
} else {
    include_once 'suspenderrev.php';
}

if (!isset($_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe']) || !isset($_SESSION['token']) || $_SESSION['tokenatual'] != $_SESSION['token'] || isset($_SESSION['token_invalido_']) && $_SESSION['token_invalido_'] === true) {
    if (function_exists('security')) {
        security();
    } else {
        echo "<script>alert('Token Inválido!');</script>";
        echo "<script>location.href='../index.php';</script>";
        $_SESSION['token_invalido_'] = true;
        exit;
    }
}

$sql = "SELECT * FROM servidores ORDER BY id DESC";
$result = $conn->query($sql);

$total_servidores = $result->num_rows;
$stats_res = $conn->query("SELECT COUNT(*) as total, SUM(onlines) as users_online FROM servidores");
$stats_row = $stats_res->fetch_assoc();
$total_usuarios_online = intval($stats_row['users_online'] ?? 0);

$total_categorias = 0;
$r = $conn->query("SELECT COUNT(*) as t FROM categorias");
if ($r) $total_categorias = $r->fetch_assoc()['t'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
<title>Servidores - Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
<style>
<?php if (function_exists('getCSSVariables')) echo getCSSVariables($temaAtual); else echo ':root{--primaria:#4158D0;--secundaria:#C850C0;--fundo:#0f172a;--fundo_claro:#1e293b;--texto:#ffffff;}'; ?>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Inter',sans-serif;background:var(--fundo,#0f172a);color:var(--texto,#fff);min-height:100vh;}
.app-content{margin-left:0px!important;padding:0!important;}
.content-wrapper{max-width:1700px;margin:0 auto!important;padding:20px!important;}

/* ========== STATS CARD ========== */
.stats-card{background:linear-gradient(135deg,var(--fundo_claro,#1e293b),var(--fundo,#0f172a));border-radius:20px;padding:20px 24px;margin-bottom:20px;border:1px solid rgba(255,255,255,0.08);display:flex;align-items:center;gap:20px;position:relative;overflow:hidden;transition:all .3s ease;}
.stats-card:hover{transform:translateY(-2px);border-color:var(--primaria,#10b981);}
.stats-card-icon{width:60px;height:60px;background:linear-gradient(135deg,var(--primaria,#10b981),var(--secundaria,#C850C0));border-radius:18px;display:flex;align-items:center;justify-content:center;font-size:32px;color:white;flex-shrink:0;}
.stats-card-content{flex:1;}
.stats-card-title{font-size:13px;font-weight:600;color:rgba(255,255,255,0.5);text-transform:uppercase;margin-bottom:5px;}
.stats-card-value{font-size:36px;font-weight:800;background:linear-gradient(135deg,#fff,var(--primaria,#10b981));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;line-height:1;}
.stats-card-subtitle{font-size:12px;color:rgba(255,255,255,0.4);margin-top:4px;}
.stats-card-decoration{position:absolute;right:20px;top:50%;transform:translateY(-50%);font-size:80px;opacity:0.05;}

/* Mini Stats */
.mini-stats{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;}
.mini-stat{flex:1;min-width:90px;background:rgba(255,255,255,0.04);border-radius:12px;padding:12px;border:1px solid rgba(255,255,255,0.06);text-align:center;transition:all .2s;}
.mini-stat:hover{border-color:var(--primaria,#10b981);transform:translateY(-2px);}
.mini-stat-val{font-size:18px;font-weight:800;}
.mini-stat-lbl{font-size:9px;color:rgba(255,255,255,0.35);text-transform:uppercase;margin-top:2px;}

/* Modern Card */
.modern-card{background:var(--fundo_claro,#1e293b);border-radius:16px;border:1px solid rgba(255,255,255,0.08);overflow:hidden;margin-bottom:16px;transition:all .2s;}
.modern-card:hover{border-color:var(--primaria,#10b981);}
.card-header-custom{padding:14px 18px;display:flex;align-items:center;gap:12px;}
.card-header-custom.blue{background:linear-gradient(135deg,#3b82f6,#2563eb);}
.card-header-custom.purple{background:linear-gradient(135deg,var(--primaria),var(--secundaria));}
.header-icon{width:36px;height:36px;background:rgba(255,255,255,0.2);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;color:white;}
.header-title{font-size:14px;font-weight:700;color:white;}
.header-subtitle{font-size:10px;color:rgba(255,255,255,0.7);}
.card-body-custom{padding:16px;}

/* Filtros */
.filter-group{display:flex;flex-wrap:wrap;gap:12px;}
.filter-item{flex:1;min-width:140px;}
.filter-label{font-size:9px;font-weight:700;color:rgba(255,255,255,0.4);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;}
.filter-input,.filter-select{width:100%;padding:8px 12px;background:rgba(255,255,255,0.06);border:1.5px solid rgba(255,255,255,0.08);border-radius:9px;font-size:12px;color:#ffffff!important;transition:all .2s;font-family:inherit;outline:none;}
.filter-input:focus,.filter-select:focus{border-color:var(--primaria,#10b981);background:rgba(255,255,255,0.09);}
.filter-input::placeholder{color:rgba(255,255,255,0.3);}
.filter-select{cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%23ffffff' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;}
.filter-select option{background:#1e293b;color:#ffffff!important;}

/* Actions bar */
.actions-bar{background:rgba(255,255,255,0.04);border-radius:14px;padding:12px;margin-bottom:16px;border:1px solid rgba(255,255,255,0.06);display:flex;flex-wrap:wrap;gap:6px;align-items:center;}
.actions-bar-title{font-size:11px;font-weight:700;color:rgba(255,255,255,0.4);display:flex;align-items:center;gap:5px;margin-right:auto;}
.actions-bar-title i{font-size:14px;color:var(--primaria);}

/* Botões */
.sv-btn{padding:8px 16px;border:none;border-radius:10px;font-weight:700;font-size:10px;cursor:pointer;display:inline-flex;align-items:center;gap:5px;color:#ffffff;transition:all .25s;font-family:'Inter',sans-serif;text-decoration:none;line-height:1.2;white-space:nowrap;outline:none;-webkit-appearance:none;-moz-appearance:none;appearance:none;}
.sv-btn:hover{transform:translateY(-2px);filter:brightness(1.1);box-shadow:0 4px 12px rgba(0,0,0,0.3);color:#ffffff;text-decoration:none;}
.sv-btn:focus{outline:none;box-shadow:none;}
.sv-btn:active{transform:translateY(0);filter:brightness(0.95);}
.sv-btn i{font-size:14px;flex-shrink:0;}
.sv-btn-roxo{background:linear-gradient(135deg,var(--primaria,#4158D0),var(--secundaria,#C850C0));box-shadow:0 2px 8px rgba(65,88,208,0.3);}
.sv-btn-azul{background:linear-gradient(135deg,#3b82f6,#2563eb);box-shadow:0 2px 8px rgba(59,130,246,0.3);}
.sv-btn-lilas{background:linear-gradient(135deg,#8b5cf6,#7c3aed);box-shadow:0 2px 8px rgba(139,92,246,0.3);}
.sv-btn-ciano{background:linear-gradient(135deg,#06b6d4,#0891b2);box-shadow:0 2px 8px rgba(6,182,212,0.3);}
.sv-btn-verde{background:linear-gradient(135deg,#10b981,#059669);box-shadow:0 2px 8px rgba(16,185,129,0.3);}
.sv-btn-amarelo{background:linear-gradient(135deg,#f59e0b,#d97706);box-shadow:0 2px 8px rgba(245,158,11,0.3);}
.sv-btn-vermelho{background:linear-gradient(135deg,#dc2626,#b91c1c);box-shadow:0 2px 8px rgba(220,38,38,0.3);}
.sv-btn-indigo{background:linear-gradient(135deg,#6366f1,#4f46e5);box-shadow:0 2px 8px rgba(99,102,241,0.3);}

/* Grid */
.servers-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px;}

/* Card */
.server-card{background:var(--fundo_claro,#1e293b);border-radius:14px;overflow:hidden;transition:all .2s;border:1px solid rgba(255,255,255,0.08);}
.server-card:hover{transform:translateY(-2px);border-color:var(--primaria,#10b981);}
.server-header{background:linear-gradient(135deg,var(--primaria,#10b981),var(--secundaria,#C850C0));padding:12px;display:flex;align-items:center;justify-content:space-between;}
.server-info{display:flex;align-items:center;gap:10px;flex:1;min-width:0;}
.server-avatar{width:36px;height:36px;background:rgba(255,255,255,0.2);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;}
.server-text{flex:1;min-width:0;}
.server-name{font-size:14px;font-weight:700;color:white;display:flex;align-items:center;gap:5px;}
.server-ip{font-size:10px;color:rgba(255,255,255,0.7);margin-top:2px;display:flex;align-items:center;gap:4px;}
.status-badge{display:inline-flex;align-items:center;gap:3px;padding:2px 7px;border-radius:16px;font-size:9px;font-weight:600;flex-shrink:0;}
.status-online{background:rgba(16,185,129,0.2);color:#10b981;}
.status-offline{background:rgba(239,68,68,0.2);color:#f87171;}

.server-body{padding:12px;}
.status-row{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px;}
.status-item-card{display:flex;align-items:center;gap:6px;padding:6px 8px;background:rgba(255,255,255,0.03);border-radius:8px;}
.status-icon{width:26px;height:26px;background:rgba(255,255,255,0.05);border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0;}
.status-content{flex:1;}
.status-label{font-size:8px;color:rgba(255,255,255,0.4);font-weight:600;margin-bottom:1px;}
.status-value{font-size:11px;font-weight:600;}

.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:5px;margin-bottom:8px;}
.info-row{display:flex;align-items:center;gap:5px;padding:5px 7px;background:rgba(255,255,255,0.03);border-radius:7px;}
.info-icon{width:22px;height:22px;background:rgba(255,255,255,0.05);border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0;}
.info-content{flex:1;min-width:0;}
.info-label{font-size:8px;color:rgba(255,255,255,0.4);font-weight:600;}
.info-value{font-size:10px;font-weight:600;word-break:break-all;color:var(--texto,#ffffff);}

.server-actions{display:flex;flex-wrap:wrap;gap:5px;margin-top:8px;}
.action-btn{flex:1;min-width:60px;padding:6px 8px;border:none;border-radius:8px;font-weight:600;font-size:10px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:4px;color:white;transition:all .2s;font-family:inherit;outline:none;-webkit-appearance:none;appearance:none;}
.action-btn:hover{transform:translateY(-1px);filter:brightness(1.05);}

.sv-spinner{display:inline-block;width:10px;height:10px;border:2px solid rgba(255,255,255,0.1);border-top-color:var(--primaria);border-radius:50%;animation:svSpin 1s linear infinite;}
@keyframes svSpin{to{transform:rotate(360deg)}}

.empty-state{grid-column:1/-1;text-align:center;padding:40px;background:var(--fundo_claro,#1e293b);border-radius:16px;border:1px solid rgba(255,255,255,0.08);}
.empty-state i{font-size:48px;color:rgba(255,255,255,0.15);margin-bottom:10px;}
.empty-state h3{font-size:15px;margin-bottom:6px;}
.empty-state p{font-size:11px;color:rgba(255,255,255,0.3);}
.pagination-info{text-align:center;margin-top:10px;color:rgba(255,255,255,0.3);font-size:10px;}

/* ========== MODAIS — mesmo estilo da lista de usuários ========== */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.85);display:none;align-items:center;justify-content:center;z-index:10000;backdrop-filter:blur(8px);padding:16px;}
.modal-overlay.show{display:flex;}
.modal-container{animation:modalIn .3s ease;max-width:450px;width:92%;}
@keyframes modalIn{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
.modal-content-custom{background:var(--fundo_claro,#1e293b);border-radius:20px;overflow:hidden;border:1px solid rgba(255,255,255,0.1);box-shadow:0 25px 60px rgba(0,0,0,0.5);}
.modal-header-custom{padding:14px 18px;display:flex;align-items:center;justify-content:space-between;}
.modal-header-custom h5{margin:0;display:flex;align-items:center;gap:8px;font-size:14px;font-weight:700;color:white;}
.modal-header-custom.success{background:linear-gradient(135deg,#10b981,#059669);}
.modal-header-custom.error{background:linear-gradient(135deg,#dc2626,#b91c1c);}
.modal-header-custom.warning{background:linear-gradient(135deg,#f59e0b,#f97316);}
.modal-header-custom.processing{background:linear-gradient(135deg,var(--primaria,#10b981),var(--secundaria,#C850C0));}
.modal-header-custom.info{background:linear-gradient(135deg,#06b6d4,#0891b2);}
.modal-header-custom.indigo{background:linear-gradient(135deg,#6366f1,#4f46e5);}
.modal-header-custom.lilas{background:linear-gradient(135deg,#8b5cf6,#7c3aed);}
.modal-close{background:rgba(255,255,255,0.15);border:none;color:#fff;font-size:18px;cursor:pointer;width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;transition:all .2s;}
.modal-close:hover{background:rgba(255,255,255,0.25);transform:rotate(90deg);}
.modal-body-custom{padding:18px;}
.modal-footer-custom{border-top:1px solid rgba(255,255,255,0.07);padding:12px 18px;display:flex;justify-content:center;gap:8px;flex-wrap:wrap;}

.modal-ic{width:70px;height:70px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:34px;animation:icPop .5s cubic-bezier(0.34,1.56,0.64,1) .15s both;}
@keyframes icPop{0%{transform:scale(0);opacity:0}100%{transform:scale(1);opacity:1}}
.modal-ic.success{background:rgba(16,185,129,.15);color:#34d399;border:2px solid rgba(16,185,129,.3);}
.modal-ic.error{background:rgba(239,68,68,.15);color:#f87171;border:2px solid rgba(239,68,68,.3);}
.modal-ic.warning{background:rgba(245,158,11,.15);color:#fbbf24;border:2px solid rgba(245,158,11,.3);}
.modal-ic.info{background:rgba(6,182,212,.15);color:#22d3ee;border:2px solid rgba(6,182,212,.3);}
.modal-ic.indigo{background:rgba(99,102,241,.15);color:#818cf8;border:2px solid rgba(99,102,241,.3);}
.modal-ic.lilas{background:rgba(139,92,246,.15);color:#a78bfa;border:2px solid rgba(139,92,246,.3);}

.modal-info-box{background:rgba(255,255,255,.04);border-radius:10px;padding:10px;margin-bottom:10px;border:1px solid rgba(255,255,255,.05);}
.modal-info-row{display:flex;align-items:center;gap:6px;padding:3px 0;}
.modal-info-row i{font-size:13px;width:16px;text-align:center;}
.modal-info-row span{font-size:11px;color:rgba(255,255,255,.6);}
.modal-info-row strong{font-size:11px;color:#fff;}

.btn-modal{padding:8px 16px;border:none;border-radius:10px;font-weight:600;font-size:12px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;color:white;transition:all .2s;font-family:inherit;}
.btn-modal:hover{transform:translateY(-1px);filter:brightness(1.08);}
.btn-modal-cancel{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);}
.btn-modal-cancel:hover{background:rgba(255,255,255,.15);}
.btn-modal-ok{background:linear-gradient(135deg,#10b981,#059669);}
.btn-modal-warning{background:linear-gradient(135deg,#f59e0b,#f97316);}
.btn-modal-danger{background:linear-gradient(135deg,#dc2626,#b91c1c);}
.btn-modal-info{background:linear-gradient(135deg,#06b6d4,#0891b2);}
.btn-modal-indigo{background:linear-gradient(135deg,#6366f1,#4f46e5);}
.btn-modal-lilas{background:linear-gradient(135deg,#8b5cf6,#7c3aed);}

.spinner-wrap{display:flex;flex-direction:column;align-items:center;gap:14px;padding:20px 0;}
.spinner-ring{width:44px;height:44px;border:3px solid rgba(255,255,255,.08);border-top-color:var(--primaria,#10b981);border-right-color:var(--secundaria,#C850C0);border-radius:50%;animation:spin .8s linear infinite;}
@keyframes spin{to{transform:rotate(360deg)}}

/* Toast */
.toast-notification{position:fixed;bottom:20px;right:20px;color:#fff;padding:10px 16px;border-radius:10px;display:flex;align-items:center;gap:8px;z-index:10001;animation:toastIn .3s ease;font-weight:600;font-size:12px;box-shadow:0 8px 20px rgba(0,0,0,0.3);}
.toast-notification.ok{background:linear-gradient(135deg,#10b981,#059669);}
.toast-notification.err{background:linear-gradient(135deg,#dc2626,#b91c1c);}
@keyframes toastIn{from{transform:translateX(110%);opacity:0}to{transform:translateX(0);opacity:1}}

#alerta .alert{padding:10px 16px;border-radius:10px;margin-bottom:12px;font-size:12px;font-weight:600;animation:slideDown .3s ease;}
@keyframes slideDown{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}
#alerta .alert-success{background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.25);color:#34d399;}

@media(max-width:768px){
    .app-content{margin-left:0!important;}
    .content-wrapper{padding:10px!important;}
    .servers-grid{grid-template-columns:1fr;}
    .stats-card{padding:14px;gap:14px;}
    .stats-card-icon{width:48px;height:48px;font-size:24px;}
    .stats-card-value{font-size:28px;}
    .filter-group{flex-direction:column;}
    .actions-bar{flex-direction:column;gap:8px;}
    .actions-bar-title{margin-right:0;margin-bottom:4px;}
    .actions-bar .sv-btn{width:100%;justify-content:center;}
    .server-actions{display:grid;grid-template-columns:repeat(3,1fr);}
    .mini-stats{flex-wrap:wrap;}
    .mini-stat{min-width:80px;}
}
</style>
</head>
<body class="<?php echo htmlspecialchars(getBodyClass($temaAtual ?? ['classe'=>'theme-dark'])); ?>">
<div class="app-content content">
<div class="content-overlay"></div>
<div class="content-wrapper">

    <!-- Stats Card -->
    <div class="stats-card">
        <div class="stats-card-icon"><i class='bx bx-server'></i></div>
        <div class="stats-card-content">
            <div class="stats-card-title">Gerenciar Servidores</div>
            <div class="stats-card-value"><?php echo $total_servidores; ?> Servidores</div>
            <div class="stats-card-subtitle">Gerencie todos os servidores do sistema · <?php echo $total_usuarios_online; ?> usuários online</div>
        </div>
        <div class="stats-card-decoration"><i class='bx bx-server'></i></div>
    </div>

    <!-- Mini Stats -->
    <div class="mini-stats">
        <div class="mini-stat"><div class="mini-stat-val" style="color:#818cf8;"><?php echo $total_servidores; ?></div><div class="mini-stat-lbl">Servidores</div></div>
        <div class="mini-stat"><div class="mini-stat-val" style="color:#34d399;" id="statOnline">—</div><div class="mini-stat-lbl">Online</div></div>
        <div class="mini-stat"><div class="mini-stat-val" style="color:#f87171;" id="statOffline">—</div><div class="mini-stat-lbl">Offline</div></div>
        <div class="mini-stat"><div class="mini-stat-val" style="color:#fbbf24;"><?php echo $total_usuarios_online; ?></div><div class="mini-stat-lbl">Usuários</div></div>
        <div class="mini-stat"><div class="mini-stat-val" style="color:#a78bfa;"><?php echo $total_categorias; ?></div><div class="mini-stat-lbl">Categorias</div></div>
    </div>

    <!-- Filtros -->
    <div class="modern-card">
        <div class="card-header-custom blue">
            <div class="header-icon"><i class='bx bx-filter-alt'></i></div>
            <div><div class="header-title">Filtros e Busca</div><div class="header-subtitle">Encontre servidores rapidamente</div></div>
        </div>
        <div class="card-body-custom">
            <div class="filter-group">
                <div class="filter-item">
                    <div class="filter-label">Buscar por Nome ou IP</div>
                    <input type="text" class="filter-input" id="searchInput" placeholder="🔍 Digite o nome ou IP..." onkeyup="filtrarServidores()">
                </div>
                <div class="filter-item" style="max-width:160px;">
                    <div class="filter-label">Filtrar por Status</div>
                    <select class="filter-select" id="statusFilter" onchange="filtrarServidores()">
                        <option value="todos">📋 Todos</option>
                        <option value="online">🟢 Online</option>
                        <option value="offline">🔴 Offline</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="actions-bar">
        <div class="actions-bar-title"><i class='bx bx-cog'></i> Ações Rápidas</div>
        <button class="sv-btn sv-btn-roxo" onclick="window.location.href='adicionarservidor.php'"><i class='bx bx-plus'></i> Adicionar</button>
        <button class="sv-btn sv-btn-azul" onclick="window.location.href='adicionarcategoria.php'"><i class='bx bx-category'></i> Categoria</button>
        <button class="sv-btn sv-btn-lilas" onclick="window.location.href='categorias.php'"><i class='bx bx-list-ul'></i> Categorias</button>
        <button class="sv-btn sv-btn-ciano" onclick="window.location.href='gerar_token.php'"><i class='bx bx-key'></i> Tokens</button>
        <button class="sv-btn sv-btn-verde" onclick="window.location.href='installmodtodos.php'"><i class='bx bx-package'></i> Módulos</button>
        <button class="sv-btn sv-btn-amarelo" onclick="window.location.href='limpezageral.php'"><i class='bx bx-brush'></i> Limpeza</button>
    </div>

    <div id="alerta"></div>

    <!-- Grid -->
    <div class="servers-grid" id="serversGrid">
    <?php
    set_include_path(get_include_path() . PATH_SEPARATOR . '../lib2');
    include('Net/SSH2.php');

    if ($result->num_rows > 0) {
        while ($user_data = mysqli_fetch_assoc($result)) {
            $ip = $user_data['ip'];
            $fp = @fsockopen($ip, '22', $errno, $errstr, 1);
            if (!$fp) { $status="Offline"; $status_classe="status-offline"; $status_icon="bx-power-off"; $status_data="offline"; }
            else { $status="Online"; $status_classe="status-online"; $status_icon="bx-wifi"; $status_data="online"; fclose($fp); }

            $sql_cat = "SELECT nome FROM categorias WHERE subid = '".$user_data['subid']."'";
            $result_cat = $conn->query($sql_cat);
            $cat_row = $result_cat->fetch_assoc();
            $categoria_nome = $cat_row['nome'] ?? 'N/A';
            $lastview = $user_data['lastview'] ? date('d/m H:i', strtotime($user_data['lastview'])) : 'Nunca';
    ?>
    <div class="server-card" data-nome="<?php echo strtolower($user_data['nome']); ?>" data-ip="<?php echo strtolower($user_data['ip']); ?>" data-status="<?php echo $status_data; ?>">
        <div class="server-header">
            <div class="server-info">
                <div class="server-avatar"><i class='bx bx-server'></i></div>
                <div class="server-text">
                    <div class="server-name"><?php echo htmlspecialchars($user_data['nome']); ?></div>
                    <div class="server-ip"><i class='bx bx-network-chart'></i> <?php echo $user_data['ip']; ?>:<?php echo $user_data['porta']; ?></div>
                </div>
            </div>
            <span class="status-badge <?php echo $status_classe; ?>"><i class='bx <?php echo $status_icon; ?>'></i> <?php echo $status; ?></span>
        </div>
        <div class="server-body">
            <div class="status-row">
                <div class="status-item-card">
                    <div class="status-icon"><i class='bx <?php echo $status_icon; ?>' style="color:<?php echo $status_data=='online'?'#10b981':'#f87171'; ?>;"></i></div>
                    <div class="status-content"><div class="status-label">STATUS</div><span class="status-badge <?php echo $status_classe; ?>"><?php echo $status; ?></span></div>
                </div>
                <div class="status-item-card">
                    <div class="status-icon"><i class='bx bx-group' style="color:#fbbf24;"></i></div>
                    <div class="status-content"><div class="status-label">ONLINE</div><div class="status-value"><?php echo $user_data['onlines']; ?> usuários</div></div>
                </div>
            </div>
            <div class="info-grid">
                <div class="info-row"><div class="info-icon"><i class='bx bx-category' style="color:#4ECDC4;"></i></div><div class="info-content"><div class="info-label">CATEGORIA</div><div class="info-value"><?php echo htmlspecialchars($categoria_nome); ?></div></div></div>
                <div class="info-row"><div class="info-icon"><i class='bx bx-network-chart' style="color:#818cf8;"></i></div><div class="info-content"><div class="info-label">IP</div><div class="info-value"><?php echo $user_data['ip']; ?></div></div></div>
                <div class="info-row"><div class="info-icon"><i class='bx bx-chip' style="color:#45B7D1;"></i></div><div class="info-content"><div class="info-label">CPU</div><div class="info-value" id="cpu-info-<?php echo $user_data['id']; ?>"><?php echo $user_data['servercpu']; ?> Cores</div></div></div>
                <div class="info-row"><div class="info-icon"><i class='bx bx-memory-card' style="color:#96CEB4;"></i></div><div class="info-content"><div class="info-label">RAM</div><div class="info-value" id="ram-info-<?php echo $user_data['id']; ?>"><?php echo $user_data['serverram']; ?></div></div></div>
                <div class="info-row"><div class="info-icon"><i class='bx bx-plug' style="color:#FFE194;"></i></div><div class="info-content"><div class="info-label">PORTA</div><div class="info-value"><?php echo $user_data['porta']; ?></div></div></div>
                <div class="info-row"><div class="info-icon"><i class='bx bx-time' style="color:#DFAB8C;"></i></div><div class="info-content"><div class="info-label">ATUALIZADO</div><div class="info-value" id="lastview-<?php echo $user_data['id']; ?>"><?php echo $lastview; ?></div></div></div>
            </div>
            <div class="server-actions">
                <button class="action-btn sv-btn-indigo" onclick="window.location.href='editarservidor.php?id=<?php echo $user_data['id']; ?>'"><i class='bx bx-edit'></i> Editar</button>
                <button class="action-btn sv-btn-amarelo" onclick="abrirModalReiniciar(<?php echo $user_data['id']; ?>,'<?php echo htmlspecialchars(addslashes($user_data['nome'])); ?>')"><i class='bx bx-refresh'></i> Reiniciar</button>
                <button class="action-btn sv-btn-verde" onclick="abrirModalModulos(<?php echo $user_data['id']; ?>,'<?php echo htmlspecialchars(addslashes($user_data['nome'])); ?>')"><i class='bx bx-package'></i> Módulos</button>
                <button class="action-btn sv-btn-azul" onclick="abrirModalSync(<?php echo $user_data['id']; ?>,'<?php echo htmlspecialchars(addslashes($user_data['nome'])); ?>')"><i class='bx bx-sync'></i> Sync</button>
                <button class="action-btn sv-btn-lilas" onclick="abrirModalLimpeza(<?php echo $user_data['id']; ?>,'<?php echo htmlspecialchars(addslashes($user_data['nome'])); ?>')"><i class='bx bx-brush'></i> Limpar</button>
                <button class="action-btn sv-btn-vermelho" onclick="abrirModalExcluir(<?php echo $user_data['id']; ?>,'<?php echo htmlspecialchars(addslashes($user_data['nome'])); ?>')"><i class='bx bx-trash'></i> Deletar</button>
            </div>
        </div>
    </div>
    <script>atualizarEstatisticas(<?php echo $user_data['id']; ?>);</script>
    <?php } } else { ?>
    <div class="empty-state"><i class='bx bx-server'></i><h3>Nenhum servidor encontrado</h3><p>Adicione um novo servidor para começar</p><button class="sv-btn sv-btn-roxo" onclick="window.location.href='adicionarservidor.php'" style="margin-top:12px;"><i class='bx bx-plus'></i> Adicionar</button></div>
    <?php } ?>
    </div>
    <div class="pagination-info">Total de <?php echo $total_servidores; ?> servidor(es) · <?php echo date('d/m/Y H:i:s'); ?></div>

</div>
</div>

<!-- ========== MODAIS ========== -->

<!-- Modal Reiniciar -->
<div id="modalReiniciar" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom warning"><h5><i class='bx bx-refresh'></i> Reiniciar Servidor</h5><button class="modal-close" onclick="fecharModal('modalReiniciar')"><i class='bx bx-x'></i></button></div>
    <div class="modal-body-custom">
        <div class="modal-ic warning"><i class='bx bx-refresh'></i></div>
        <p style="text-align:center;font-size:13px;margin-bottom:12px;">Deseja reiniciar o servidor <strong id="reiniciarNome" style="color:#fbbf24;"></strong>?</p>
        <div class="modal-info-box">
            <div class="modal-info-row"><i class='bx bx-info-circle' style="color:#fbbf24;"></i> <span>O servidor ficará indisponível por alguns instantes</span></div>
        </div>
    </div>
    <div class="modal-footer-custom">
        <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalReiniciar')"><i class='bx bx-x'></i> Cancelar</button>
        <button class="btn-modal btn-modal-warning" onclick="confirmarReiniciar()"><i class='bx bx-refresh'></i> Reiniciar</button>
    </div>
</div></div>
</div>

<!-- Modal Módulos -->
<div id="modalModulos" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom success"><h5><i class='bx bx-package'></i> Instalar Módulos</h5><button class="modal-close" onclick="fecharModal('modalModulos')"><i class='bx bx-x'></i></button></div>
    <div class="modal-body-custom">
        <div class="modal-ic success"><i class='bx bx-package'></i></div>
        <p style="text-align:center;font-size:13px;margin-bottom:12px;">Deseja instalar módulos no servidor <strong id="modulosNome" style="color:#34d399;"></strong>?</p>
        <div class="modal-info-box">
            <div class="modal-info-row"><i class='bx bx-info-circle' style="color:#34d399;"></i> <span>Os módulos necessários serão instalados automaticamente</span></div>
        </div>
    </div>
    <div class="modal-footer-custom">
        <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalModulos')"><i class='bx bx-x'></i> Cancelar</button>
        <button class="btn-modal btn-modal-ok" onclick="confirmarModulos()"><i class='bx bx-package'></i> Instalar</button>
    </div>
</div></div>
</div>

<!-- Modal Sincronizar -->
<div id="modalSync" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom info"><h5><i class='bx bx-sync'></i> Sincronizar Servidor</h5><button class="modal-close" onclick="fecharModal('modalSync')"><i class='bx bx-x'></i></button></div>
    <div class="modal-body-custom">
        <div class="modal-ic info"><i class='bx bx-sync'></i></div>
        <p style="text-align:center;font-size:13px;margin-bottom:12px;">Deseja sincronizar o servidor <strong id="syncNome" style="color:#22d3ee;"></strong>?</p>
        <div class="modal-info-box">
            <div class="modal-info-row"><i class='bx bx-info-circle' style="color:#22d3ee;"></i> <span>Os dados serão sincronizados com o painel</span></div>
        </div>
    </div>
    <div class="modal-footer-custom">
        <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalSync')"><i class='bx bx-x'></i> Cancelar</button>
        <button class="btn-modal btn-modal-info" onclick="confirmarSync()"><i class='bx bx-sync'></i> Sincronizar</button>
    </div>
</div></div>
</div>

<!-- Modal Limpeza -->
<div id="modalLimpeza" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom lilas"><h5><i class='bx bx-brush'></i> Limpar Servidor</h5><button class="modal-close" onclick="fecharModal('modalLimpeza')"><i class='bx bx-x'></i></button></div>
    <div class="modal-body-custom">
        <div class="modal-ic lilas"><i class='bx bx-brush'></i></div>
        <p style="text-align:center;font-size:13px;margin-bottom:12px;">Deseja limpar o servidor <strong id="limpezaNome" style="color:#a78bfa;"></strong>?</p>
        <div class="modal-info-box">
            <div class="modal-info-row"><i class='bx bx-info-circle' style="color:#a78bfa;"></i> <span>Arquivos temporários e cache serão removidos</span></div>
        </div>
    </div>
    <div class="modal-footer-custom">
        <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalLimpeza')"><i class='bx bx-x'></i> Cancelar</button>
        <button class="btn-modal btn-modal-lilas" onclick="confirmarLimpeza()"><i class='bx bx-brush'></i> Limpar</button>
    </div>
</div></div>
</div>

<!-- Modal Excluir -->
<div id="modalExcluir" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom error"><h5><i class='bx bx-trash'></i> Excluir Servidor</h5><button class="modal-close" onclick="fecharModal('modalExcluir')"><i class='bx bx-x'></i></button></div>
    <div class="modal-body-custom">
        <div class="modal-ic error"><i class='bx bx-error-circle'></i></div>
        <p style="text-align:center;font-size:13px;">Tem certeza que deseja excluir <strong id="excluirNome" style="color:#f87171;"></strong>?</p>
        <p style="text-align:center;font-size:10px;color:rgba(255,255,255,.35);margin-top:4px;">⚠️ Esta ação não pode ser desfeita!</p>
    </div>
    <div class="modal-footer-custom">
        <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalExcluir')"><i class='bx bx-x'></i> Cancelar</button>
        <button class="btn-modal btn-modal-danger" onclick="confirmarExcluir()"><i class='bx bx-trash'></i> Excluir</button>
    </div>
</div></div>
</div>

<!-- Modal Processando -->
<div id="modalProcessando" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom processing"><h5><i class='bx bx-loader-alt bx-spin'></i> Processando...</h5></div>
    <div class="modal-body-custom">
        <div class="spinner-wrap"><div class="spinner-ring"></div><p id="processandoTexto" style="font-size:13px;color:rgba(255,255,255,.6);">Aguarde...</p></div>
    </div>
</div></div>
</div>

<!-- Modal Sucesso -->
<div id="modalSucesso" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom success"><h5><i class='bx bx-check-circle'></i> Sucesso!</h5><button class="modal-close" onclick="fecharModal('modalSucesso');location.reload();"><i class='bx bx-x'></i></button></div>
    <div class="modal-body-custom">
        <div class="modal-ic success"><i class='bx bx-check-circle'></i></div>
        <p style="text-align:center;font-size:13px;font-weight:600;" id="sucessoMsg"></p>
    </div>
    <div class="modal-footer-custom">
        <button class="btn-modal btn-modal-ok" onclick="fecharModal('modalSucesso');location.reload();"><i class='bx bx-check'></i> OK</button>
    </div>
</div></div>
</div>

<!-- Modal Erro -->
<div id="modalErro" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom error"><h5><i class='bx bx-error-circle'></i> Erro!</h5><button class="modal-close" onclick="fecharModal('modalErro')"><i class='bx bx-x'></i></button></div>
    <div class="modal-body-custom">
        <div class="modal-ic error"><i class='bx bx-error-circle'></i></div>
        <p style="text-align:center;font-size:13px;font-weight:600;" id="erroMsg"></p>
    </div>
    <div class="modal-footer-custom">
        <button class="btn-modal btn-modal-danger" onclick="fecharModal('modalErro')"><i class='bx bx-x'></i> Fechar</button>
    </div>
</div></div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>

<script>
// ===== Estado =====
var _serverId = null;
var _serverName = null;

// ===== Modais =====
function abrirModal(id){document.getElementById(id).classList.add('show');}
function fecharModal(id){document.getElementById(id).classList.remove('show');}
document.querySelectorAll('.modal-overlay').forEach(function(o){o.addEventListener('click',function(e){if(e.target===o)o.classList.remove('show');});});
document.addEventListener('keydown',function(e){if(e.key==='Escape')document.querySelectorAll('.modal-overlay.show').forEach(function(m){m.classList.remove('show');});});

// ===== Toast =====
function mostrarToast(msg,tipo){var t=document.createElement('div');t.className='toast-notification '+(tipo==='err'?'err':'ok');t.innerHTML='<i class="bx '+(tipo==='err'?'bx-error-circle':'bx-check-circle')+'"></i> '+msg;document.body.appendChild(t);setTimeout(function(){t.remove();},3500);}

function mostrarSucesso(msg){document.getElementById('sucessoMsg').textContent=msg;abrirModal('modalSucesso');}
function mostrarErro(msg){document.getElementById('erroMsg').textContent=msg;abrirModal('modalErro');}

// ===== Stats =====
document.addEventListener('DOMContentLoaded',function(){
    var online=document.querySelectorAll('.status-online').length;
    var offline=document.querySelectorAll('.status-offline').length;
    document.getElementById('statOnline').textContent=online;
    document.getElementById('statOffline').textContent=offline;
});

function atualizarEstatisticas(id){
    $.ajax({url:'atualizar_stats.php?id='+id,type:'GET',dataType:'json',
        success:function(data){if(data.cpu)$('#cpu-info-'+id).html(data.cpu);if(data.memoria)$('#ram-info-'+id).html(data.memoria);if(data.lastview)$('#lastview-'+id).text(data.lastview);},
        error:function(){$('#cpu-info-'+id).html('<span style="color:#f87171;">Erro</span>');$('#ram-info-'+id).html('<span style="color:#f87171;">Erro</span>');}
    });
}
function iniciarAtualizacaoTempReal(){document.querySelectorAll('[id^="cpu-info-"]').forEach(function(el){var sid=el.id.replace('cpu-info-','');setInterval(function(){atualizarEstatisticas(sid);},10000);});}
document.addEventListener('DOMContentLoaded',iniciarAtualizacaoTempReal);

// ===== Filtro =====
function filtrarServidores(){
    var busca=document.getElementById('searchInput').value.toLowerCase();
    var status=document.getElementById('statusFilter').value;
    document.querySelectorAll('.server-card').forEach(function(card){
        var nome=card.getAttribute('data-nome')||'';var ip=card.getAttribute('data-ip')||'';var st=card.getAttribute('data-status')||'';
        var mb=nome.includes(busca)||ip.includes(busca);var ms=true;
        if(status==='online')ms=st==='online';else if(status==='offline')ms=st==='offline';
        card.style.display=(mb&&ms)?'':'none';
    });
}

// ══════════════════════════════════════════════════════════
// REINICIAR
// ══════════════════════════════════════════════════════════
function abrirModalReiniciar(id,nome){
    _serverId=id;_serverName=nome;
    document.getElementById('reiniciarNome').textContent=nome;
    abrirModal('modalReiniciar');
}
function confirmarReiniciar(){
    fecharModal('modalReiniciar');
    document.getElementById('processandoTexto').textContent='Reiniciando '+_serverName+'...';
    abrirModal('modalProcessando');
    $.ajax({url:'comandos.php?id='+_serverId+'&comando=reboot',type:'POST',data:{id:_serverId,comando:'reboot'},
        success:function(data){
            fecharModal('modalProcessando');
            data=data.replace(/\s+/g,'');
            if(data.includes('Não foi possível autenticar'))mostrarErro('Falha na autenticação com o servidor!');
            else if(data.includes('Não foi possível conectar'))mostrarErro('Não foi possível conectar ao servidor!');
            else mostrarSucesso('Servidor "'+_serverName+'" reiniciado com sucesso!');
        },
        error:function(){fecharModal('modalProcessando');mostrarErro('Erro de conexão!');}
    });
}

// ══════════════════════════════════════════════════════════
// MÓDULOS
// ══════════════════════════════════════════════════════════
function abrirModalModulos(id,nome){
    _serverId=id;_serverName=nome;
    document.getElementById('modulosNome').textContent=nome;
    abrirModal('modalModulos');
}
function confirmarModulos(){
    fecharModal('modalModulos');
    document.getElementById('processandoTexto').textContent='Instalando módulos em '+_serverName+'...';
    abrirModal('modalProcessando');
    $.ajax({url:'installmodul.php?id='+_serverId,type:'POST',data:{id:_serverId},
        success:function(data){
            fecharModal('modalProcessando');
            var d=data.replace(/\s+/g,'');
            if(d.includes('Não foi possível autenticar'))mostrarErro('Falha na autenticação com o servidor!');
            else mostrarSucesso('Módulos instalados com sucesso em "'+_serverName+'"!');
        },
        error:function(){fecharModal('modalProcessando');mostrarErro('Erro de conexão!');}
    });
}

// ══════════════════════════════════════════════════════════
// SINCRONIZAR
// ══════════════════════════════════════════════════════════
function abrirModalSync(id,nome){
    _serverId=id;_serverName=nome;
    document.getElementById('syncNome').textContent=nome;
    abrirModal('modalSync');
}
function confirmarSync(){
    fecharModal('modalSync');
    document.getElementById('processandoTexto').textContent='Sincronizando '+_serverName+'...';
    abrirModal('modalProcessando');
    $.ajax({url:'sincronizar.php?id='+_serverId,type:'POST',data:{id:_serverId},
        success:function(data){
            fecharModal('modalProcessando');
            data=data.replace(/\s+/g,'');
            if(data.includes('Não foi possível conectar'))mostrarErro('Falha na autenticação com o servidor!');
            else mostrarSucesso('Servidor "'+_serverName+'" sincronizado com sucesso!');
        },
        error:function(){fecharModal('modalProcessando');mostrarErro('Erro de conexão!');}
    });
}

// ══════════════════════════════════════════════════════════
// LIMPEZA
// ══════════════════════════════════════════════════════════
function abrirModalLimpeza(id,nome){
    _serverId=id;_serverName=nome;
    document.getElementById('limpezaNome').textContent=nome;
    abrirModal('modalLimpeza');
}
function confirmarLimpeza(){
    fecharModal('modalLimpeza');
    document.getElementById('processandoTexto').textContent='Limpando '+_serverName+'...';
    abrirModal('modalProcessando');
    $.ajax({url:'limpeza.php?id='+_serverId,type:'POST',data:{id:_serverId},
        success:function(data){
            fecharModal('modalProcessando');
            data=data.replace(/\s+/g,'');
            if(data.includes('LoginFailed'))mostrarErro('Falha na autenticação com o servidor!');
            else mostrarSucesso('Servidor "'+_serverName+'" limpo com sucesso!');
        },
        error:function(){fecharModal('modalProcessando');mostrarErro('Erro de conexão!');}
    });
}

// ══════════════════════════════════════════════════════════
// EXCLUIR
// ══════════════════════════════════════════════════════════
function abrirModalExcluir(id,nome){
    _serverId=id;_serverName=nome;
    document.getElementById('excluirNome').textContent=nome;
    abrirModal('modalExcluir');
}
function confirmarExcluir(){
    fecharModal('modalExcluir');
    document.getElementById('processandoTexto').textContent='Excluindo '+_serverName+'...';
    abrirModal('modalProcessando');
    $.ajax({url:'dellserv.php?id='+_serverId,type:'POST',data:{id:_serverId},
        success:function(){
            fecharModal('modalProcessando');
            mostrarSucesso('Servidor "'+_serverName+'" excluído com sucesso!');
        },
        error:function(){fecharModal('modalProcessando');mostrarErro('Erro de conexão!');}
    });
}
</script>
</body>
</html>
<?php
    }
    aleatorio_servidores($kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf);
?>

