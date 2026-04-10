<?php
error_reporting(0);
session_start();
include('../AegisCore/conexao.php');
include('headeradmin2.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

include_once '../AegisCore/temas.php';
$temaAtual = initTemas($conn);

if (!file_exists('suspenderrev.php')) { exit("<script>alert('Token Invalido!');</script>"); }
else { include_once 'suspenderrev.php'; }

if (!isset($_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe']) || !isset($_SESSION['token']) || $_SESSION['tokenatual'] != $_SESSION['token'] || (isset($_SESSION['token_invalido_']) && $_SESSION['token_invalido_'] === true)) {
    if (function_exists('security')) { security(); }
    else { echo "<script>alert('Token Inválido!');location.href='../index.php';</script>"; $_SESSION['token_invalido_'] = true; exit; }
}

// Paginação
$limite_por_pagina = isset($_GET['limite']) ? (int)$_GET['limite'] : 10;
$limite_por_pagina = in_array($limite_por_pagina, [10, 20, 50, 100]) ? $limite_por_pagina : 10;
$pagina_atual = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$offset = ($pagina_atual - 1) * $limite_por_pagina;

$sql_total = "SELECT COUNT(*) as total FROM ssh_accounts WHERE status='Online'";
$r_total = $conn->query($sql_total);
$total_registros = $r_total ? $r_total->fetch_assoc()['total'] : 0;
$total_paginas = max(1, ceil($total_registros / $limite_por_pagina));

// Stats extras
$total_limite_ultra = 0;
$r = $conn->query("SELECT COUNT(*) as t FROM ssh_accounts WHERE status='Online' AND mainid='Limite Ultrapassado'");
if ($r) $total_limite_ultra = $r->fetch_assoc()['t'];

$total_v2ray = 0;
$r = $conn->query("SELECT COUNT(*) as t FROM ssh_accounts WHERE status='Online' AND uuid != '' AND uuid IS NOT NULL");
if ($r) $total_v2ray = $r->fetch_assoc()['t'];

$total_ssh = $total_registros - $total_v2ray;

$sql = "SELECT ssh_accounts.*, accounts.login AS dono_login
        FROM ssh_accounts
        LEFT JOIN accounts ON accounts.id = ssh_accounts.byid
        WHERE ssh_accounts.status = 'Online'
        ORDER BY ssh_accounts.login ASC
        LIMIT $limite_por_pagina OFFSET $offset";
$result = $conn->query($sql);

$sql44 = "SELECT deviceativo FROM configs LIMIT 1";
$r44 = $conn->query($sql44);
$deviceativo = ($r44 && $r44->num_rows > 0) ? $r44->fetch_assoc()['deviceativo'] : '';

date_default_timezone_set('America/Sao_Paulo');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Usuários Online</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
<style>
<?php echo getCSSVariables($temaAtual); ?>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Inter',sans-serif;background:var(--fundo,#0f172a);color:var(--texto,#fff);min-height:100vh;}
.app-content{margin-left:0px!important;padding:0!important;}
.content-wrapper{max-width:1700px;margin:0 auto!important;padding:20px!important;}

/* Stats */
.stats-card{background:linear-gradient(135deg,var(--fundo_claro,#1e293b),var(--fundo,#0f172a));border-radius:20px;padding:20px 24px;margin-bottom:20px;border:1px solid rgba(255,255,255,0.08);display:flex;align-items:center;gap:20px;position:relative;overflow:hidden;transition:all .3s;}
.stats-card:hover{transform:translateY(-2px);border-color:var(--primaria,#10b981);}
.stats-card-icon{width:60px;height:60px;background:linear-gradient(135deg,var(--primaria,#10b981),var(--secundaria,#C850C0));border-radius:18px;display:flex;align-items:center;justify-content:center;font-size:32px;color:white;flex-shrink:0;position:relative;}
.stats-card-icon::after{content:'';position:absolute;inset:-3px;border-radius:21px;background:linear-gradient(135deg,var(--primaria,#10b981),var(--secundaria,#C850C0));opacity:0.3;animation:pulseGlow 2s ease-in-out infinite;z-index:-1;}
@keyframes pulseGlow{0%,100%{opacity:0.2;transform:scale(1);}50%{opacity:0.4;transform:scale(1.05);}}
.stats-card-content{flex:1;}
.stats-card-title{font-size:13px;font-weight:600;color:rgba(255,255,255,0.5);text-transform:uppercase;margin-bottom:5px;}
.stats-card-value{font-size:36px;font-weight:800;background:linear-gradient(135deg,#34d399,#10b981);-webkit-background-clip:text;-webkit-text-fill-color:transparent;line-height:1;}
.stats-card-subtitle{font-size:12px;color:rgba(255,255,255,0.4);margin-top:4px;display:flex;align-items:center;gap:6px;}
.live-dot{width:8px;height:8px;background:#10b981;border-radius:50%;animation:livePulse 1.5s infinite;}
@keyframes livePulse{0%,100%{opacity:1;transform:scale(1);}50%{opacity:.5;transform:scale(1.3);}}
.stats-card-decoration{position:absolute;right:20px;top:50%;transform:translateY(-50%);font-size:80px;opacity:0.05;}

/* Mini Stats */
.mini-stats{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;}
.mini-stat{flex:1;min-width:80px;background:rgba(255,255,255,0.04);border-radius:12px;padding:12px;border:1px solid rgba(255,255,255,0.06);text-align:center;transition:all .2s;}
.mini-stat:hover{border-color:var(--primaria,#10b981);transform:translateY(-2px);}
.mini-stat-val{font-size:18px;font-weight:800;}
.mini-stat-lbl{font-size:9px;color:rgba(255,255,255,0.35);text-transform:uppercase;margin-top:2px;}

/* Modern Card */
.modern-card{background:var(--fundo_claro,#1e293b);border-radius:16px;border:1px solid rgba(255,255,255,0.08);overflow:hidden;margin-bottom:16px;transition:all .2s;}
.modern-card:hover{border-color:var(--primaria,#10b981);}
.card-header-custom{padding:14px 18px;display:flex;align-items:center;gap:12px;}
.card-header-custom.green{background:linear-gradient(135deg,#10b981,#059669);}
.header-icon{width:36px;height:36px;background:rgba(255,255,255,0.2);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;color:white;}
.header-title{font-size:14px;font-weight:700;color:white;}
.header-subtitle{font-size:10px;color:rgba(255,255,255,0.7);}
.card-body-custom{padding:16px;}

/* Filters */
.filter-group{display:flex;gap:12px;flex-wrap:wrap;}
.filter-item{flex:1;min-width:130px;}
.filter-label{font-size:9px;font-weight:700;color:rgba(255,255,255,0.4);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;}
.form-control{width:100%;padding:8px 12px;background:rgba(255,255,255,0.06);border:1.5px solid rgba(255,255,255,0.08);border-radius:9px;color:#fff;font-size:12px;font-family:inherit;outline:none;transition:all .25s;}
.form-control:focus{border-color:var(--primaria,#10b981);background:rgba(255,255,255,0.09);}
.form-control::placeholder{color:rgba(255,255,255,0.2);}
select.form-control{cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%23ffffff' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;}
select.form-control option{background:#1e293b;color:#fff;}

/* Users Grid */
.users-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px;}

.user-card{background:var(--fundo_claro,#1e293b);border-radius:14px;overflow:hidden;border:1px solid rgba(16,185,129,0.15);transition:all .2s;position:relative;}
.user-card:hover{transform:translateY(-2px);border-color:#10b981;box-shadow:0 4px 20px rgba(16,185,129,0.1);}
.user-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,#10b981,#34d399,#10b981);background-size:200% 100%;animation:shimmer 3s linear infinite;}
@keyframes shimmer{0%{background-position:200% 0;}100%{background-position:-200% 0;}}

.user-header{background:linear-gradient(135deg,var(--primaria,#10b981),var(--secundaria,#C850C0));padding:12px;display:flex;align-items:center;justify-content:space-between;}
.user-info{display:flex;align-items:center;gap:10px;flex:1;min-width:0;}
.user-avatar{width:36px;height:36px;background:rgba(255,255,255,0.2);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;position:relative;}
.online-dot{position:absolute;bottom:-2px;right:-2px;width:10px;height:10px;background:#10b981;border-radius:50%;border:2px solid var(--primaria,#10b981);animation:livePulse 1.5s infinite;}
.user-text{flex:1;min-width:0;}
.user-name{font-size:14px;font-weight:700;color:white;display:flex;align-items:center;gap:5px;word-break:break-all;}
.v2ray-badge{background:rgba(255,255,255,0.2);padding:2px 6px;border-radius:20px;font-size:8px;font-weight:600;}
.user-senha{font-size:10px;color:rgba(255,255,255,0.7);margin-top:2px;display:flex;align-items:center;gap:4px;}
.btn-copy-card{background:rgba(255,255,255,0.15);border:none;border-radius:8px;padding:6px 10px;font-size:11px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:4px;color:white;flex-shrink:0;transition:all .2s;}
.btn-copy-card:hover{background:rgba(255,255,255,0.25);}
.btn-copy-card.copied{background:#10b981;}

.user-body{padding:12px;}

/* Conexões highlight */
.conn-bar{display:flex;align-items:center;gap:10px;padding:8px 10px;background:rgba(16,185,129,0.08);border-radius:10px;margin-bottom:10px;border:1px solid rgba(16,185,129,0.12);}
.conn-bar.warning{background:rgba(245,158,11,0.08);border-color:rgba(245,158,11,0.15);}
.conn-bar.danger{background:rgba(239,68,68,0.08);border-color:rgba(239,68,68,0.15);}
.conn-icon{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;}
.conn-icon.ok{background:rgba(16,185,129,0.15);color:#34d399;}
.conn-icon.warning{background:rgba(245,158,11,0.15);color:#fbbf24;}
.conn-icon.danger{background:rgba(239,68,68,0.15);color:#f87171;}
.conn-info{flex:1;}
.conn-label{font-size:8px;color:rgba(255,255,255,0.4);font-weight:600;text-transform:uppercase;}
.conn-value{font-size:14px;font-weight:700;}
.conn-progress{height:4px;background:rgba(255,255,255,0.08);border-radius:10px;overflow:hidden;margin-top:3px;}
.conn-fill{height:100%;border-radius:10px;transition:width .6s ease;}

.status-row{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px;}
.status-item-card{display:flex;align-items:center;gap:6px;padding:6px 8px;background:rgba(255,255,255,0.03);border-radius:8px;}
.status-icon{width:26px;height:26px;background:rgba(255,255,255,0.05);border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0;}
.status-content{flex:1;}
.status-label{font-size:8px;color:rgba(255,255,255,0.4);font-weight:600;margin-bottom:1px;}
.status-value{font-size:11px;font-weight:600;}

.status-badge{display:inline-flex;align-items:center;gap:3px;padding:2px 7px;border-radius:16px;font-size:9px;font-weight:600;}
.status-online{background:rgba(16,185,129,0.2);color:#10b981;}

.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:5px;margin-bottom:8px;}
.info-row{display:flex;align-items:center;gap:5px;padding:5px 7px;background:rgba(255,255,255,0.03);border-radius:7px;}
.info-icon{width:22px;height:22px;background:rgba(255,255,255,0.05);border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0;}
.info-content{flex:1;min-width:0;}
.info-label{font-size:8px;color:rgba(255,255,255,0.4);font-weight:600;}
.info-value{font-size:10px;font-weight:600;word-break:break-all;}
.info-value.warning{color:#fbbf24;} .info-value.danger{color:#f87171;}

/* Actions */
.user-actions{display:flex;flex-wrap:wrap;gap:5px;margin-top:8px;}
.action-btn{flex:1;min-width:55px;padding:6px 6px;border:none;border-radius:8px;font-weight:600;font-size:9px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:3px;color:white;transition:all .2s;font-family:inherit;}
.action-btn:hover{transform:translateY(-1px);filter:brightness(1.08);}
.action-btn:active{transform:scale(0.95);}
.btn-edit{background:linear-gradient(135deg,#4158D0,#6366f1);}
.btn-warn{background:linear-gradient(135deg,#f59e0b,#f97316);}
.btn-device{background:linear-gradient(135deg,#8b5cf6,#7c3aed);}
.btn-danger{background:linear-gradient(135deg,#dc2626,#b91c1c);}

/* Pagination */
.pagination-wrapper{display:flex;justify-content:center;align-items:center;gap:12px;flex-wrap:wrap;margin-top:20px;padding:10px 0;}
.pagination{display:flex;align-items:center;gap:5px;}
.pagination a,.pagination span{display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;padding:0 8px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:8px;color:#fff;text-decoration:none;font-size:11px;font-weight:500;transition:all .2s;}
.pagination a:hover{background:var(--primaria,#10b981);border-color:var(--primaria,#10b981);}
.pagination .active{background:var(--primaria,#10b981);border-color:var(--primaria,#10b981);}
.pagination .disabled{opacity:.4;cursor:not-allowed;}
.pagination-info{text-align:center;margin-top:10px;color:rgba(255,255,255,0.3);font-size:10px;}

/* Empty */
.empty-state{grid-column:1/-1;text-align:center;padding:40px;background:var(--fundo_claro,#1e293b);border-radius:16px;border:1px solid rgba(255,255,255,0.08);}
.empty-state i{font-size:48px;color:rgba(255,255,255,0.15);margin-bottom:10px;}
.empty-state h3{font-size:15px;margin-bottom:6px;}
.empty-state p{font-size:11px;color:rgba(255,255,255,0.3);}

/* Modal */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.85);display:none;align-items:center;justify-content:center;z-index:9999;backdrop-filter:blur(8px);padding:16px;}
.modal-overlay.show{display:flex;}
.modal-container{animation:modalIn .3s ease;max-width:450px;width:92%;}
@keyframes modalIn{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
.modal-content-custom{background:var(--fundo_claro,#1e293b);border-radius:20px;overflow:hidden;border:1px solid rgba(255,255,255,.1);box-shadow:0 25px 60px rgba(0,0,0,.5);}
.modal-header-custom{padding:14px 18px;display:flex;align-items:center;justify-content:space-between;}
.modal-header-custom h5{margin:0;display:flex;align-items:center;gap:8px;font-size:14px;font-weight:700;color:#fff;}
.modal-header-custom.success{background:linear-gradient(135deg,#10b981,#059669);}
.modal-header-custom.error{background:linear-gradient(135deg,#dc2626,#b91c1c);}
.modal-header-custom.warning{background:linear-gradient(135deg,#f59e0b,#f97316);}
.modal-header-custom.processing{background:linear-gradient(135deg,var(--primaria,#10b981),var(--secundaria,#C850C0));}
.modal-header-custom.info{background:linear-gradient(135deg,#06b6d4,#0891b2);}
.modal-close{background:rgba(255,255,255,.15);border:none;color:#fff;font-size:18px;cursor:pointer;width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;transition:all .2s;}
.modal-close:hover{background:rgba(255,255,255,.25);transform:rotate(90deg);}
.modal-body-custom{padding:18px;}
.modal-footer-custom{border-top:1px solid rgba(255,255,255,.07);padding:12px 18px;display:flex;justify-content:center;gap:8px;flex-wrap:wrap;}

.modal-ic{width:70px;height:70px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:34px;animation:icPop .5s cubic-bezier(0.34,1.56,0.64,1) .15s both;}
@keyframes icPop{0%{transform:scale(0);opacity:0}100%{transform:scale(1);opacity:1}}
.modal-ic.success{background:rgba(16,185,129,.15);color:#34d399;border:2px solid rgba(16,185,129,.3);}
.modal-ic.error{background:rgba(239,68,68,.15);color:#f87171;border:2px solid rgba(239,68,68,.3);}
.modal-ic.warning{background:rgba(245,158,11,.15);color:#fbbf24;border:2px solid rgba(245,158,11,.3);}
.modal-ic.info{background:rgba(6,182,212,.15);color:#22d3ee;border:2px solid rgba(6,182,212,.3);}

.modal-user-info{background:rgba(255,255,255,.04);border-radius:12px;padding:12px;margin-bottom:12px;border:1px solid rgba(255,255,255,.06);}
.modal-user-row{display:flex;align-items:center;gap:8px;padding:4px 0;}
.modal-user-row i{font-size:14px;width:18px;text-align:center;}
.modal-user-row span{font-size:12px;color:rgba(255,255,255,.7);}
.modal-user-row strong{font-size:12px;color:#fff;}

.btn-modal{padding:8px 16px;border:none;border-radius:10px;font-weight:600;font-size:12px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;color:white;transition:all .2s;font-family:inherit;}
.btn-modal:hover{transform:translateY(-1px);filter:brightness(1.08);}
.btn-modal-cancel{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);}
.btn-modal-cancel:hover{background:rgba(255,255,255,.15);}
.btn-modal-ok{background:linear-gradient(135deg,#10b981,#059669);}
.btn-modal-warning{background:linear-gradient(135deg,#f59e0b,#f97316);}
.btn-modal-danger{background:linear-gradient(135deg,#dc2626,#b91c1c);}
.btn-modal-info{background:linear-gradient(135deg,#06b6d4,#0891b2);}

.spinner-wrap{display:flex;flex-direction:column;align-items:center;gap:14px;padding:20px 0;}
.spinner-ring{width:44px;height:44px;border:3px solid rgba(255,255,255,.08);border-top-color:var(--primaria,#10b981);border-right-color:var(--secundaria,#C850C0);border-radius:50%;animation:spin .8s linear infinite;}
@keyframes spin{to{transform:rotate(360deg)}}

/* Toast */
.toast-notification{position:fixed;bottom:20px;right:20px;color:#fff;padding:10px 16px;border-radius:10px;display:flex;align-items:center;gap:8px;z-index:10001;animation:toastIn .3s ease;font-weight:600;font-size:12px;box-shadow:0 8px 20px rgba(0,0,0,.3);}
.toast-notification.ok{background:linear-gradient(135deg,#10b981,#059669);}
.toast-notification.err{background:linear-gradient(135deg,#dc2626,#b91c1c);}
@keyframes toastIn{from{transform:translateX(110%);opacity:0}to{transform:translateX(0);opacity:1}}

@media(max-width:768px){
    .app-content{margin-left:0!important;}
    .content-wrapper{padding:10px!important;}
    .users-grid{grid-template-columns:1fr;}
    .stats-card{padding:14px;gap:14px;}
    .stats-card-icon{width:48px;height:48px;font-size:24px;}
    .stats-card-value{font-size:28px;}
    .filter-group{flex-direction:column;}
    .user-actions{display:grid;grid-template-columns:repeat(2,1fr);}
    .mini-stats{flex-wrap:wrap;}
    .mini-stat{min-width:70px;}
}
</style>
</head>
<body class="<?php echo htmlspecialchars(getBodyClass($temaAtual ?? ['classe'=>'theme-dark'])); ?>">
<div class="app-content content">
<div class="content-overlay"></div>
<div class="content-wrapper">

    <!-- Stats Card -->
    <div class="stats-card">
        <div class="stats-card-icon"><i class='bx bx-wifi'></i></div>
        <div class="stats-card-content">
            <div class="stats-card-title">Usuários Online</div>
            <div class="stats-card-value"><?php echo $total_registros; ?></div>
            <div class="stats-card-subtitle"><span class="live-dot"></span> conectados agora — atualizado às <?php echo date('H:i:s'); ?></div>
        </div>
        <div class="stats-card-decoration"><i class='bx bx-wifi'></i></div>
    </div>

    <!-- Mini Stats -->
    <div class="mini-stats">
        <div class="mini-stat"><div class="mini-stat-val" style="color:#34d399;"><?php echo $total_registros; ?></div><div class="mini-stat-lbl">Online</div></div>
        <div class="mini-stat"><div class="mini-stat-val" style="color:#818cf8;"><?php echo $total_ssh; ?></div><div class="mini-stat-lbl">SSH</div></div>
        <div class="mini-stat"><div class="mini-stat-val" style="color:#a78bfa;"><?php echo $total_v2ray; ?></div><div class="mini-stat-lbl">V2Ray</div></div>
        <div class="mini-stat"><div class="mini-stat-val" style="color:#fb923c;"><?php echo $total_limite_ultra; ?></div><div class="mini-stat-lbl">Limite</div></div>
    </div>

    <!-- Filtros -->
    <div class="modern-card">
        <div class="card-header-custom green">
            <div class="header-icon"><i class='bx bx-search-alt'></i></div>
            <div><div class="header-title">Busca Rápida</div><div class="header-subtitle">Encontre usuários online</div></div>
        </div>
        <div class="card-body-custom">
            <div class="filter-group">
                <div class="filter-item">
                    <div class="filter-label">Buscar por Login</div>
                    <input type="text" class="form-control" id="searchInput" placeholder="Digite o nome..." onkeyup="filtrarUsuarios()">
                </div>
                <div class="filter-item" style="max-width:130px;">
                    <div class="filter-label">Filtrar Tipo</div>
                    <select class="form-control" id="tipoFilter" onchange="filtrarUsuarios()">
                        <option value="todos">Todos</option>
                        <option value="ssh">SSH</option>
                        <option value="v2ray">V2Ray</option>
                        <option value="limite">Limite+</option>
                    </select>
                </div>
                <div class="filter-item" style="max-width:110px;">
                    <div class="filter-label">Por Página</div>
                    <select class="form-control" id="limitSelect" onchange="mudarLimite()">
                        <option value="10" <?php echo $limite_por_pagina==10?'selected':''; ?>>10</option>
                        <option value="20" <?php echo $limite_por_pagina==20?'selected':''; ?>>20</option>
                        <option value="50" <?php echo $limite_por_pagina==50?'selected':''; ?>>50</option>
                        <option value="100" <?php echo $limite_por_pagina==100?'selected':''; ?>>100</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Grid -->
    <div class="users-grid" id="usersGrid">
    <?php
    if ($result && $result->num_rows > 0):
        while ($row = $result->fetch_assoc()):
            $id=$row['id'];$login=$row['login'];$senha=$row['senha'];
            $limite=$row['limite'];$expira=$row['expira'];$categoria=$row['categoriaid'];
            $suspenso=$row['mainid'];$notas=$row['lastview'];$uuid=$row['uuid'];
            $dono=$row['dono_login']??'N/A';
            $tem_v2ray=!empty($uuid);

            $expira_fmt=date('d/m/Y',strtotime($expira));
            $diff=strtotime($expira)-time();$dias_rest=floor($diff/86400);
            $val_class=$dias_rest<0?'danger':($dias_rest<=5?'warning':'');

            $r_cat=$conn->query("SELECT nome FROM categorias WHERE subid='$categoria'");
            $cat_nome=($r_cat&&$r_cat->num_rows>0)?$r_cat->fetch_assoc()['nome']:$categoria;

            $r_on=$conn->query("SELECT quantidade FROM onlines WHERE usuario='$login'");
            $usando=($r_on&&$r_on->num_rows>0)?$r_on->fetch_assoc()['quantidade']:0;

            $pct=$limite>0?round(($usando/$limite)*100):0;
            $conn_class='ok';$bar_class='';
            if($usando>=$limite){$conn_class='danger';$bar_class='danger';}
            elseif($pct>=70){$conn_class='warning';$bar_class='warning';}
            $fill_color=$conn_class=='danger'?'#ef4444':($conn_class=='warning'?'#f59e0b':'#10b981');

            $tipo_data=$tem_v2ray?'v2ray':'ssh';
            $limite_data=($usando>=$limite)?'limite':'ok';
    ?>
    <div class="user-card" data-login="<?php echo strtolower(htmlspecialchars($login));?>" data-id="<?php echo $id;?>" data-usuario="<?php echo htmlspecialchars($login);?>" data-senha="<?php echo htmlspecialchars($senha);?>" data-limite="<?php echo $limite;?>" data-usando="<?php echo $usando;?>" data-expira="<?php echo $expira_fmt;?>" data-dono="<?php echo htmlspecialchars($dono);?>" data-tipo="<?php echo $tipo_data;?>" data-limitestat="<?php echo $limite_data;?>">
        <div class="user-header">
            <div class="user-info">
                <div class="user-avatar"><i class='bx bx-user'></i><span class="online-dot"></span></div>
                <div class="user-text">
                    <div class="user-name"><?php echo htmlspecialchars($login);?><?php if($tem_v2ray):?> <span class="v2ray-badge">V2Ray</span><?php endif;?></div>
                    <div class="user-senha"><i class='bx bx-lock-alt'></i> <?php echo htmlspecialchars($senha);?></div>
                </div>
            </div>
            <button class="btn-copy-card" onclick="copiar(this)"><i class='bx bx-copy'></i> Copiar</button>
        </div>
        <div class="user-body">
            <!-- Barra de conexões -->
            <div class="conn-bar <?php echo $bar_class;?>">
                <div class="conn-icon <?php echo $conn_class;?>"><i class='bx bx-wifi'></i></div>
                <div class="conn-info">
                    <div class="conn-label">Conexões Ativas</div>
                    <div class="conn-value" style="color:<?php echo $fill_color;?>;"><?php echo $usando;?> / <?php echo $limite;?></div>
                    <div class="conn-progress"><div class="conn-fill" style="width:<?php echo min(100,$pct);?>%;background:<?php echo $fill_color;?>;"></div></div>
                </div>
            </div>

            <div class="info-grid">
                <div class="info-row"><div class="info-icon"><i class='bx bx-store' style="color:#f472b6;"></i></div><div class="info-content"><div class="info-label">REVENDEDOR</div><div class="info-value"><?php echo htmlspecialchars($dono);?></div></div></div>
                <div class="info-row"><div class="info-icon"><i class='bx bx-category' style="color:#60a5fa;"></i></div><div class="info-content"><div class="info-label">CATEGORIA</div><div class="info-value"><?php echo htmlspecialchars($cat_nome);?></div></div></div>
                <div class="info-row"><div class="info-icon"><i class='bx bx-calendar-check' style="color:#fbbf24;"></i></div><div class="info-content"><div class="info-label">EXPIRA</div><div class="info-value <?php echo $val_class;?>"><?php echo $expira_fmt;?></div></div></div>
                <div class="info-row"><div class="info-icon"><i class='bx bx-shield' style="color:#818cf8;"></i></div><div class="info-content"><div class="info-label">TIPO</div><div class="info-value"><?php echo $tem_v2ray?'V2Ray':'SSH';?></div></div></div>
            </div>
            <?php if(!empty($notas)):?>
            <div class="info-row" style="margin-bottom:6px;"><div class="info-icon"><i class='bx bx-note' style="color:#a78bfa;"></i></div><div class="info-content"><div class="info-label">NOTAS</div><div class="info-value"><?php echo htmlspecialchars($notas);?></div></div></div>
            <?php endif;?>

            <div class="user-actions">
                <button class="action-btn btn-edit" onclick="window.location.href='editarlogin.php?id=<?php echo $id;?>'"><i class='bx bx-edit'></i> Editar</button>
                <button class="action-btn btn-warn" onclick="suspender(<?php echo $id;?>)"><i class='bx bx-lock'></i> Suspender</button>
                <?php if($deviceativo=='ativo'||$deviceativo=='1'):?>
                <button class="action-btn btn-device" onclick="limparDevice(<?php echo $id;?>)"><i class='bx bx-devices'></i> Device</button>
                <?php endif;?>
                <button class="action-btn btn-danger" onclick="excluir(<?php echo $id;?>)"><i class='bx bx-trash'></i> Deletar</button>
            </div>
        </div>
    </div>
    <?php endwhile; else: ?>
    <div class="empty-state"><i class='bx bx-wifi-off'></i><h3>Nenhum usuário online</h3><p>Não há usuários conectados neste momento.</p></div>
    <?php endif; ?>
    </div>

    <!-- Paginação -->
    <?php if($total_paginas>1):?>
    <div class="pagination-wrapper">
        <div class="pagination">
            <?php if($pagina_atual>1):?><a href="?pagina=<?php echo $pagina_atual-1;?>&limite=<?php echo $limite_por_pagina;?>"><i class='bx bx-chevron-left'></i></a><?php else:?><span class="disabled"><i class='bx bx-chevron-left'></i></span><?php endif;?>
            <?php
            $max_p=5;$ini=max(1,$pagina_atual-floor($max_p/2));$fim=min($total_paginas,$ini+$max_p-1);
            if($ini>1){echo '<a href="?pagina=1&limite='.$limite_por_pagina.'">1</a>';if($ini>2)echo '<span class="disabled">…</span>';}
            for($i=$ini;$i<=$fim;$i++){echo($i==$pagina_atual)?'<span class="active">'.$i.'</span>':'<a href="?pagina='.$i.'&limite='.$limite_por_pagina.'">'.$i.'</a>';}
            if($fim<$total_paginas){if($fim<$total_paginas-1)echo '<span class="disabled">…</span>';echo '<a href="?pagina='.$total_paginas.'&limite='.$limite_por_pagina.'">'.$total_paginas.'</a>';}
            ?>
            <?php if($pagina_atual<$total_paginas):?><a href="?pagina=<?php echo $pagina_atual+1;?>&limite=<?php echo $limite_por_pagina;?>"><i class='bx bx-chevron-right'></i></a><?php else:?><span class="disabled"><i class='bx bx-chevron-right'></i></span><?php endif;?>
        </div>
    </div>
    <?php endif;?>
    <div class="pagination-info">Mostrando <?php echo min($offset+1,$total_registros);?>–<?php echo min($offset+$limite_por_pagina,$total_registros);?> de <?php echo $total_registros;?> online — Página <?php echo $pagina_atual;?>/<?php echo $total_paginas;?></div>

</div>
</div>

<!-- MODAIS -->

<!-- Suspender -->
<div id="modalSuspender" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom warning"><h5><i class='bx bx-lock'></i> Suspender Usuário</h5><button class="modal-close" onclick="fecharModal('modalSuspender')"><i class='bx bx-x'></i></button></div>
    <div class="modal-body-custom">
        <div class="modal-ic warning"><i class='bx bx-lock'></i></div>
        <div class="modal-user-info" id="suspenderInfo"></div>
        <p style="text-align:center;font-size:11px;color:rgba(255,255,255,.4);margin-top:6px;">O usuário será desconectado e suspenso.</p>
    </div>
    <div class="modal-footer-custom">
        <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalSuspender')"><i class='bx bx-x'></i> Cancelar</button>
        <button class="btn-modal btn-modal-warning" onclick="confirmarSuspender()"><i class='bx bx-check'></i> Suspender</button>
    </div>
</div></div></div>

<!-- Excluir -->
<div id="modalExcluir" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom error"><h5><i class='bx bx-trash'></i> Excluir Usuário</h5><button class="modal-close" onclick="fecharModal('modalExcluir')"><i class='bx bx-x'></i></button></div>
    <div class="modal-body-custom">
        <div class="modal-ic error"><i class='bx bx-error-circle'></i></div>
        <div class="modal-user-info" id="excluirInfo"></div>
        <p style="text-align:center;font-size:11px;color:#f87171;font-weight:600;margin-top:6px;">⚠️ Esta ação NÃO pode ser desfeita!</p>
    </div>
    <div class="modal-footer-custom">
        <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalExcluir')"><i class='bx bx-x'></i> Cancelar</button>
        <button class="btn-modal btn-modal-danger" onclick="confirmarExcluir()"><i class='bx bx-trash'></i> Excluir</button>
    </div>
</div></div></div>

<!-- Device -->
<div id="modalDevice" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom info"><h5><i class='bx bx-devices'></i> Limpar Device ID</h5><button class="modal-close" onclick="fecharModal('modalDevice')"><i class='bx bx-x'></i></button></div>
    <div class="modal-body-custom">
        <div class="modal-ic info"><i class='bx bx-devices'></i></div>
        <div class="modal-user-info" id="deviceInfo"></div>
    </div>
    <div class="modal-footer-custom">
        <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalDevice')"><i class='bx bx-x'></i> Cancelar</button>
        <button class="btn-modal btn-modal-info" onclick="confirmarDevice()"><i class='bx bx-check'></i> Limpar</button>
    </div>
</div></div></div>

<!-- Processando -->
<div id="modalProcessando" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom processing"><h5><i class='bx bx-loader-alt bx-spin'></i> Processando...</h5></div>
    <div class="modal-body-custom"><div class="spinner-wrap"><div class="spinner-ring"></div><p id="processandoTexto" style="font-size:13px;color:rgba(255,255,255,.6);">Aguarde...</p></div></div>
</div></div></div>

<script>
var _id=null,_card=null;

function abrirModal(id){document.getElementById(id).classList.add('show');}
function fecharModal(id){document.getElementById(id).classList.remove('show');}
document.querySelectorAll('.modal-overlay').forEach(function(o){o.addEventListener('click',function(e){if(e.target===o)o.classList.remove('show');});});
document.addEventListener('keydown',function(e){if(e.key==='Escape')document.querySelectorAll('.modal-overlay.show').forEach(function(m){m.classList.remove('show');});});

function toast(msg,tipo){var t=document.createElement('div');t.className='toast-notification '+(tipo==='err'?'err':'ok');t.innerHTML='<i class="bx '+(tipo==='err'?'bx-error-circle':'bx-check-circle')+'"></i> '+msg;document.body.appendChild(t);setTimeout(function(){t.remove();},3500);}

function getCard(id){return document.querySelector('.user-card[data-id="'+id+'"]');}

function userInfoHTML(card){
    return '<div class="modal-user-row"><i class="bx bx-user" style="color:#818cf8;"></i> <span>Usuário:</span> <strong>'+(card.dataset.usuario||'')+'</strong></div>'
        +'<div class="modal-user-row"><i class="bx bx-lock-alt" style="color:#e879f9;"></i> <span>Senha:</span> <strong>'+(card.dataset.senha||'')+'</strong></div>'
        +'<div class="modal-user-row"><i class="bx bx-wifi" style="color:#34d399;"></i> <span>Conexões:</span> <strong>'+(card.dataset.usando||0)+'/'+(card.dataset.limite||0)+'</strong></div>'
        +'<div class="modal-user-row"><i class="bx bx-store" style="color:#f472b6;"></i> <span>Revendedor:</span> <strong>'+(card.dataset.dono||'N/A')+'</strong></div>';
}

// Filtrar
function filtrarUsuarios(){
    var busca=document.getElementById('searchInput').value.toLowerCase();
    var tipo=document.getElementById('tipoFilter').value;
    document.querySelectorAll('.user-card').forEach(function(c){
        var login=c.getAttribute('data-login')||'';
        var tp=c.getAttribute('data-tipo')||'';
        var lm=c.getAttribute('data-limitestat')||'';
        var mb=login.includes(busca);
        var mt=true;
        if(tipo==='ssh')mt=tp==='ssh';
        else if(tipo==='v2ray')mt=tp==='v2ray';
        else if(tipo==='limite')mt=lm==='limite';
        c.style.display=(mb&&mt)?'':'none';
    });
}

function mudarLimite(){
    var url=new URL(window.location.href);
    url.searchParams.set('limite',document.getElementById('limitSelect').value);
    url.searchParams.set('pagina','1');
    window.location.href=url.toString();
}

// Copiar
function copiar(btn){
    var card=btn.closest('.user-card');
    var texto='✅ USUÁRIO ONLINE\n━━━━━━━━━━━━━━━━\n👤 Login: '+card.dataset.usuario+'\n🔑 Senha: '+card.dataset.senha+'\n📡 Conexões: '+card.dataset.usando+'/'+card.dataset.limite+'\n📅 Expira: '+card.dataset.expira+'\n🏪 Revendedor: '+(card.dataset.dono||'N/A')+'\n━━━━━━━━━━━━━━━━';
    navigator.clipboard.writeText(texto).then(function(){btn.classList.add('copied');btn.innerHTML='<i class="bx bx-check"></i> Copiado!';setTimeout(function(){btn.classList.remove('copied');btn.innerHTML='<i class="bx bx-copy"></i> Copiar';},2000);}).catch(function(){toast('Erro ao copiar!','err');});
}

// AJAX GET
function ajaxGET(url,onDone){
    var xhr=new XMLHttpRequest();
    xhr.open('GET',url,true);
    xhr.onload=function(){fecharModal('modalProcessando');onDone(xhr.responseText.trim());};
    xhr.onerror=function(){fecharModal('modalProcessando');toast('Erro de conexão!','err');};
    xhr.send();
}
function respOk(resp,keywords){
    var r=resp.toLowerCase();
    for(var i=0;i<keywords.length;i++){if(r.indexOf(keywords[i])!==-1)return true;}
    try{var j=JSON.parse(resp);if(j.sucesso===true||j.sucesso==='true')return true;}catch(e){}
    return false;
}

// SUSPENDER → suspender.php?id=X GET
function suspender(id){
    _id=id;_card=getCard(id);
    document.getElementById('suspenderInfo').innerHTML=userInfoHTML(_card);
    abrirModal('modalSuspender');
}
function confirmarSuspender(){
    fecharModal('modalSuspender');
    document.getElementById('processandoTexto').textContent='Suspendendo '+(_card?_card.dataset.usuario:'')+'...';
    abrirModal('modalProcessando');
    ajaxGET('suspender.php?id='+_id,function(resp){
        if(respOk(resp,['suspenso','sucesso'])){toast('Usuário suspenso!','ok');setTimeout(function(){location.reload();},1500);}
        else if(resp.toLowerCase().indexOf('erro no servidor')!==-1){toast('Erro no servidor!','err');}
        else{toast('Erro ao suspender!','err');}
    });
}

// EXCLUIR → excluiruser.php?id=X GET
function excluir(id){
    _id=id;_card=getCard(id);
    document.getElementById('excluirInfo').innerHTML=userInfoHTML(_card);
    abrirModal('modalExcluir');
}
function confirmarExcluir(){
    fecharModal('modalExcluir');
    document.getElementById('processandoTexto').textContent='Excluindo '+(_card?_card.dataset.usuario:'')+'...';
    abrirModal('modalProcessando');
    ajaxGET('excluiruser.php?id='+_id,function(resp){
        if(respOk(resp,['excluido','sucesso'])){toast('Usuário excluído!','ok');setTimeout(function(){location.reload();},1500);}
        else{toast('Erro ao excluir!','err');}
    });
}

// DEVICE → deviceid.php?id=X GET
function limparDevice(id){
    _id=id;_card=getCard(id);
    document.getElementById('deviceInfo').innerHTML=userInfoHTML(_card);
    abrirModal('modalDevice');
}
function confirmarDevice(){
    fecharModal('modalDevice');
    document.getElementById('processandoTexto').textContent='Limpando Device ID...';
    abrirModal('modalProcessando');
    ajaxGET('deviceid.php?id='+_id,function(resp){
        if(respOk(resp,['deletado','sucesso','limpo'])){toast('Device ID limpo!','ok');setTimeout(function(){location.reload();},1500);}
        else{toast('Erro ao limpar!','err');}
    });
}
</script>
</body>
</html>

