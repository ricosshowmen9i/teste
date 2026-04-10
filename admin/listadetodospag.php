<?php // @ioncube.dk $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf -> "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs" RANDOM
    $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf = "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs";
    function aleatorio_listar_todos_pagamentos($input)
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

// Verificar colunas
$c = $conn->query("SHOW COLUMNS FROM pagamentos LIKE 'userid'");
if ($c->num_rows == 0) $conn->query("ALTER TABLE pagamentos ADD COLUMN userid INT(11) DEFAULT NULL");
$c = $conn->query("SHOW COLUMNS FROM pagamentos LIKE 'byid'");
if ($c->num_rows == 0) $conn->query("ALTER TABLE pagamentos ADD COLUMN byid INT(11) DEFAULT NULL");

// AJAX: Excluir individual
if (isset($_POST['excluir_pagamento_ajax'])) {
    $id = intval($_POST['id']);
    $conn->query("DELETE FROM pagamentos WHERE id=$id");
    echo 'ok'; exit;
}

// AJAX: Limpar todos
if (isset($_POST['limpar_todos_ajax'])) {
    $conn->query("DELETE FROM pagamentos");
    echo 'ok'; exit;
}

// Buscar pagamentos
$sql = "SELECT * FROM pagamentos ORDER BY id DESC";
$result = $conn->query($sql);

// Stats
$total_aprovados = 0; $total_pendentes = 0; $total_recebido = 0; $total_cancelados = 0;
$sql_stats = "SELECT status, SUM(valor) as total, COUNT(*) as count FROM pagamentos GROUP BY status";
$result_stats = $conn->query($sql_stats);
while ($row = $result_stats->fetch_assoc()) {
    if ($row['status'] == 'Aprovado') { $total_aprovados = $row['count']; $total_recebido = $row['total']; }
    elseif ($row['status'] == 'Pendente') { $total_pendentes = $row['count']; }
    else { $total_cancelados = ($total_cancelados ?? 0) + $row['count']; }
}
$total_geral = $result->num_rows;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
<title>Pagamentos - Admin</title>
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
.mini-stat{flex:1;min-width:100px;background:rgba(255,255,255,0.04);border-radius:12px;padding:12px;border:1px solid rgba(255,255,255,0.06);text-align:center;transition:all .2s;}
.mini-stat:hover{border-color:var(--primaria,#10b981);transform:translateY(-2px);}
.mini-stat-val{font-size:18px;font-weight:800;}
.mini-stat-lbl{font-size:9px;color:rgba(255,255,255,0.35);text-transform:uppercase;margin-top:2px;}

/* Modern Card */
.modern-card{background:var(--fundo_claro,#1e293b);border-radius:16px;border:1px solid rgba(255,255,255,0.08);overflow:hidden;margin-bottom:16px;transition:all .2s;}
.modern-card:hover{border-color:var(--primaria,#10b981);}
.card-header-custom{padding:14px 18px;display:flex;align-items:center;gap:12px;}
.card-header-custom.blue{background:linear-gradient(135deg,#3b82f6,#2563eb);}
.header-icon{width:36px;height:36px;background:rgba(255,255,255,0.2);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;color:white;}
.header-title{font-size:14px;font-weight:700;color:white;}
.header-subtitle{font-size:10px;color:rgba(255,255,255,0.7);}
.card-body-custom{padding:16px;}

/* Filtros */
.filter-group{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;}
.filter-item{flex:1;min-width:120px;}
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

.sv-btn{padding:8px 16px;border:none;border-radius:10px;font-weight:700;font-size:10px;cursor:pointer;display:inline-flex;align-items:center;gap:5px;color:#ffffff;transition:all .25s;font-family:'Inter',sans-serif;text-decoration:none;line-height:1.2;white-space:nowrap;outline:none;-webkit-appearance:none;appearance:none;}
.sv-btn:hover{transform:translateY(-2px);filter:brightness(1.1);box-shadow:0 4px 12px rgba(0,0,0,0.3);color:#ffffff;text-decoration:none;}
.sv-btn i{font-size:14px;flex-shrink:0;}
.sv-btn-vermelho{background:linear-gradient(135deg,#dc2626,#b91c1c);box-shadow:0 2px 8px rgba(220,38,38,0.3);}

/* Grid */
.payments-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px;}

/* Card pagamento */
.pay-card{background:var(--fundo_claro,#1e293b);border-radius:14px;overflow:hidden;transition:all .2s;border:1px solid rgba(255,255,255,0.08);}
.pay-card:hover{transform:translateY(-2px);border-color:var(--primaria,#10b981);}
.pay-header{padding:12px;display:flex;align-items:center;justify-content:space-between;}
.pay-header.aprovado{background:linear-gradient(135deg,#10b981,#059669);}
.pay-header.pendente{background:linear-gradient(135deg,#f59e0b,#d97706);}
.pay-header.cancelado{background:linear-gradient(135deg,#dc2626,#b91c1c);}
.pay-header.outro{background:linear-gradient(135deg,var(--primaria),var(--secundaria));}
.pay-info{display:flex;align-items:center;gap:10px;flex:1;min-width:0;}
.pay-avatar{width:36px;height:36px;background:rgba(255,255,255,0.2);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;}
.pay-text{flex:1;min-width:0;}
.pay-id{font-size:12px;font-weight:700;color:white;font-family:monospace;}
.pay-status-sm{display:inline-flex;align-items:center;gap:3px;font-size:8px;font-weight:700;color:rgba(255,255,255,0.8);margin-top:2px;}
.pay-valor{font-size:18px;font-weight:800;color:white;flex-shrink:0;}
.pay-body{padding:12px;}

/* Info grid */
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:5px;margin-bottom:8px;}
.info-row{display:flex;align-items:center;gap:5px;padding:5px 7px;background:rgba(255,255,255,0.03);border-radius:7px;}
.info-icon{width:22px;height:22px;background:rgba(255,255,255,0.05);border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0;}
.info-content{flex:1;min-width:0;}
.info-label{font-size:8px;color:rgba(255,255,255,0.4);font-weight:600;}
.info-value{font-size:10px;font-weight:600;color:var(--texto,#fff);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}

.status-badge{display:inline-flex;align-items:center;gap:3px;padding:2px 7px;border-radius:16px;font-size:9px;font-weight:600;}
.status-aprovado{background:rgba(16,185,129,0.2);color:#34d399;}
.status-pendente{background:rgba(245,158,11,0.2);color:#fbbf24;}
.status-cancelado{background:rgba(239,68,68,0.2);color:#f87171;}

.rev-tag{display:inline-flex;align-items:center;gap:2px;background:rgba(139,92,246,0.15);color:#a78bfa;padding:1px 6px;border-radius:10px;font-size:8px;font-weight:700;margin-left:3px;}

/* Descrição row full width */
.desc-row{display:flex;align-items:center;gap:5px;padding:5px 7px;background:rgba(255,255,255,0.03);border-radius:7px;margin-bottom:5px;}

/* Actions */
.pay-actions{display:flex;gap:5px;margin-top:8px;}
.action-btn{flex:1;min-width:60px;padding:6px 8px;border:none;border-radius:8px;font-weight:600;font-size:10px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:4px;color:white;transition:all .2s;font-family:inherit;outline:none;-webkit-appearance:none;appearance:none;}
.action-btn:hover{transform:translateY(-1px);filter:brightness(1.05);}
.action-btn i{font-size:13px;}
.sv-btn-roxo{background:linear-gradient(135deg,var(--primaria,#4158D0),var(--secundaria,#C850C0));}
.sv-btn-vermelho2{background:linear-gradient(135deg,#dc2626,#b91c1c);}

/* Empty */
.empty-state{grid-column:1/-1;text-align:center;padding:40px;background:var(--fundo_claro,#1e293b);border-radius:16px;border:1px solid rgba(255,255,255,0.08);}
.empty-state i{font-size:48px;color:rgba(255,255,255,0.15);margin-bottom:10px;}
.empty-state h3{font-size:15px;margin-bottom:6px;}
.empty-state p{font-size:11px;color:rgba(255,255,255,0.3);}

.pagination-info{text-align:center;margin-top:10px;color:rgba(255,255,255,0.3);font-size:10px;}

/* ========== MODAIS ========== */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.85);display:none;align-items:center;justify-content:center;z-index:10000;backdrop-filter:blur(8px);padding:16px;}
.modal-overlay.show{display:flex;}
.modal-container{animation:modalIn .3s ease;max-width:480px;width:92%;}
@keyframes modalIn{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
.modal-content-custom{background:var(--fundo_claro,#1e293b);border-radius:20px;overflow:hidden;border:1px solid rgba(255,255,255,0.1);box-shadow:0 25px 60px rgba(0,0,0,0.5);}
.modal-header-custom{padding:14px 18px;display:flex;align-items:center;justify-content:space-between;}
.modal-header-custom h5{margin:0;display:flex;align-items:center;gap:8px;font-size:14px;font-weight:700;color:white;}
.modal-header-custom.success{background:linear-gradient(135deg,#10b981,#059669);}
.modal-header-custom.error{background:linear-gradient(135deg,#dc2626,#b91c1c);}
.modal-header-custom.warning{background:linear-gradient(135deg,#f59e0b,#f97316);}
.modal-header-custom.processing{background:linear-gradient(135deg,var(--primaria),var(--secundaria));}
.modal-header-custom.info{background:linear-gradient(135deg,#3b82f6,#2563eb);}
.modal-close{background:rgba(255,255,255,0.15);border:none;color:#fff;font-size:18px;cursor:pointer;width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;transition:all .2s;}
.modal-close:hover{background:rgba(255,255,255,0.25);transform:rotate(90deg);}
.modal-body-custom{padding:18px;}
.modal-footer-custom{border-top:1px solid rgba(255,255,255,0.07);padding:12px 18px;display:flex;justify-content:center;gap:8px;flex-wrap:wrap;}

.modal-ic{width:70px;height:70px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:34px;animation:icPop .5s cubic-bezier(0.34,1.56,0.64,1) .15s both;}
@keyframes icPop{0%{transform:scale(0);opacity:0}100%{transform:scale(1);opacity:1}}
.modal-ic.success{background:rgba(16,185,129,.15);color:#34d399;border:2px solid rgba(16,185,129,.3);}
.modal-ic.error{background:rgba(239,68,68,.15);color:#f87171;border:2px solid rgba(239,68,68,.3);}
.modal-ic.warning{background:rgba(245,158,11,.15);color:#fbbf24;border:2px solid rgba(245,158,11,.3);}
.modal-ic.info{background:rgba(59,130,246,.15);color:#60a5fa;border:2px solid rgba(59,130,246,.3);}

.modal-info-box{background:rgba(255,255,255,.04);border-radius:10px;padding:10px;margin-bottom:10px;border:1px solid rgba(255,255,255,.05);}
.modal-info-row{display:flex;align-items:center;gap:6px;padding:3px 0;}
.modal-info-row i{font-size:13px;width:16px;text-align:center;}
.modal-info-row span{font-size:11px;color:rgba(255,255,255,.6);}
.modal-info-row strong{font-size:11px;color:#fff;}

/* Detail rows no modal */
.modal-detail-row{display:flex;align-items:center;padding:6px 0;border-bottom:1px solid rgba(255,255,255,0.05);}
.modal-detail-row:last-child{border-bottom:none;}
.modal-detail-label{width:100px;font-size:9px;color:rgba(255,255,255,.4);font-weight:700;text-transform:uppercase;flex-shrink:0;}
.modal-detail-value{flex:1;font-size:12px;font-weight:600;color:var(--texto);}

.modal-separator{height:1px;background:rgba(255,255,255,0.06);margin:12px 0;}
.modal-section-title{font-size:10px;font-weight:700;color:rgba(255,255,255,0.4);text-transform:uppercase;margin-bottom:8px;display:flex;align-items:center;gap:5px;}
.modal-section-title i{font-size:13px;color:var(--primaria);}

.btn-modal{padding:8px 16px;border:none;border-radius:10px;font-weight:600;font-size:12px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;color:white;transition:all .2s;font-family:inherit;}
.btn-modal:hover{transform:translateY(-1px);filter:brightness(1.08);}
.btn-modal-cancel{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);}
.btn-modal-cancel:hover{background:rgba(255,255,255,.15);}
.btn-modal-ok{background:linear-gradient(135deg,#10b981,#059669);}
.btn-modal-danger{background:linear-gradient(135deg,#dc2626,#b91c1c);}

.spinner-wrap{display:flex;flex-direction:column;align-items:center;gap:14px;padding:20px 0;}
.spinner-ring{width:44px;height:44px;border:3px solid rgba(255,255,255,.08);border-top-color:var(--primaria,#10b981);border-right-color:var(--secundaria,#C850C0);border-radius:50%;animation:spin .8s linear infinite;}
@keyframes spin{to{transform:rotate(360deg)}}

.toast-notification{position:fixed;bottom:20px;right:20px;color:#fff;padding:10px 16px;border-radius:10px;display:flex;align-items:center;gap:8px;z-index:10001;animation:toastIn .3s ease;font-weight:600;font-size:12px;box-shadow:0 8px 20px rgba(0,0,0,0.3);}
.toast-notification.ok{background:linear-gradient(135deg,#10b981,#059669);}
.toast-notification.err{background:linear-gradient(135deg,#dc2626,#b91c1c);}
@keyframes toastIn{from{transform:translateX(110%);opacity:0}to{transform:translateX(0);opacity:1}}

@media(max-width:768px){
    .app-content{margin-left:0!important;}
    .content-wrapper{padding:10px!important;}
    .payments-grid{grid-template-columns:1fr;}
    .stats-card{padding:14px;gap:14px;}
    .stats-card-icon{width:48px;height:48px;font-size:24px;}
    .stats-card-value{font-size:28px;}
    .filter-group{flex-direction:column;}
    .actions-bar{flex-direction:column;gap:8px;}
    .actions-bar-title{margin-right:0;margin-bottom:4px;}
    .mini-stats{flex-wrap:wrap;}
    .mini-stat{min-width:80px;}
    .pay-actions{flex-direction:column;}
}
</style>
</head>
<body class="<?php echo htmlspecialchars(getBodyClass($temaAtual ?? ['classe'=>'theme-dark'])); ?>">
<div class="app-content content">
<div class="content-overlay"></div>
<div class="content-wrapper">

    <!-- Stats Card -->
    <div class="stats-card">
        <div class="stats-card-icon"><i class='bx bx-credit-card'></i></div>
        <div class="stats-card-content">
            <div class="stats-card-title">Todos os Pagamentos</div>
            <div class="stats-card-value"><?php echo $total_geral; ?> Pagamentos</div>
            <div class="stats-card-subtitle">R$ <?php echo number_format($total_recebido, 2, ',', '.'); ?> recebidos no total</div>
        </div>
        <div class="stats-card-decoration"><i class='bx bx-credit-card'></i></div>
    </div>

    <!-- Mini Stats -->
    <div class="mini-stats">
        <div class="mini-stat"><div class="mini-stat-val" style="color:#34d399;"><?php echo $total_aprovados; ?></div><div class="mini-stat-lbl">Aprovados</div></div>
        <div class="mini-stat"><div class="mini-stat-val" style="color:#fbbf24;"><?php echo $total_pendentes; ?></div><div class="mini-stat-lbl">Pendentes</div></div>
        <div class="mini-stat"><div class="mini-stat-val" style="color:#f87171;"><?php echo $total_cancelados; ?></div><div class="mini-stat-lbl">Cancelados</div></div>
        <div class="mini-stat"><div class="mini-stat-val" style="color:#a78bfa;"><?php echo $total_geral; ?></div><div class="mini-stat-lbl">Total</div></div>
        <div class="mini-stat"><div class="mini-stat-val" style="color:#60a5fa;">R$ <?php echo number_format($total_recebido, 2, ',', '.'); ?></div><div class="mini-stat-lbl">Recebido</div></div>
    </div>

    <!-- Filtros -->
    <div class="modern-card">
        <div class="card-header-custom blue">
            <div class="header-icon"><i class='bx bx-filter-alt'></i></div>
            <div><div class="header-title">Filtros e Busca</div><div class="header-subtitle">Encontre pagamentos rapidamente</div></div>
        </div>
        <div class="card-body-custom">
            <div class="filter-group">
                <div class="filter-item">
                    <div class="filter-label">Buscar por Login</div>
                    <input type="text" class="filter-input" id="searchInput" placeholder="🔍 Digite o login..." onkeyup="filtrarPagamentos()">
                </div>
                <div class="filter-item" style="max-width:140px;">
                    <div class="filter-label">Status</div>
                    <select class="filter-select" id="statusFilter" onchange="filtrarPagamentos()">
                        <option value="todos">📋 Todos</option>
                        <option value="Aprovado">🟢 Aprovados</option>
                        <option value="Pendente">🟡 Pendentes</option>
                        <option value="Cancelado">🔴 Cancelados</option>
                    </select>
                </div>
                <div class="filter-item" style="max-width:120px;">
                    <div class="filter-label">Exibir</div>
                    <select class="filter-select" id="perPage" onchange="filtrarPagamentos()">
                        <option value="15">15</option>
                        <option value="30">30</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="actions-bar">
        <div class="actions-bar-title"><i class='bx bx-cog'></i> Ações</div>
        <?php if ($total_geral > 0): ?>
        <button class="sv-btn sv-btn-vermelho" onclick="abrirModalLimparTodos()"><i class='bx bx-trash'></i> Limpar Todos (<?php echo $total_geral; ?>)</button>
        <?php endif; ?>
    </div>

    <!-- Grid -->
    <div class="payments-grid" id="paymentsGrid">
    <?php
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $id = $row['id'];
            $idpagamento = $row['idpagamento'];
            $login = $row['login'];
            $valor = $row['valor'];
            $texto = $row['texto'];
            $data = date('d/m/Y H:i', strtotime($row['data']));
            $status = $row['status'];
            $userid = $row['userid'] ?? 0;
            $byid = $row['byid'] ?? 0;

            $revendedor_login = 'N/A';
            if ($byid > 0) {
                $rr = $conn->query("SELECT login FROM accounts WHERE id = '$byid'");
                if ($rr && $rr->num_rows > 0) $revendedor_login = $rr->fetch_assoc()['login'];
            }

            $limite_plano = 1; $duracao = '30 dias';
            if ($userid > 0) {
                $ru = $conn->query("SELECT limite, expira FROM ssh_accounts WHERE id = '$userid'");
                if ($ru && $ru->num_rows > 0) {
                    $ud = $ru->fetch_assoc();
                    $limite_plano = $ud['limite'] ?? 1;
                    $dr = floor((strtotime($ud['expira']) - time()) / 86400);
                    $duracao = $dr > 0 ? "$dr dias" : 'Expirado';
                }
            }

            $hclass = 'outro'; $sclass = ''; $sicon = '';
            if ($status == 'Aprovado') { $hclass='aprovado'; $sclass='status-aprovado'; $sicon='bx-check-circle'; }
            elseif ($status == 'Pendente') { $hclass='pendente'; $sclass='status-pendente'; $sicon='bx-time'; }
            else { $hclass='cancelado'; $sclass='status-cancelado'; $sicon='bx-x-circle'; }
    ?>
    <div class="pay-card" data-status="<?php echo $status; ?>" data-login="<?php echo strtolower($login); ?>">
        <div class="pay-header <?php echo $hclass; ?>">
            <div class="pay-info">
                <div class="pay-avatar"><i class='bx bx-credit-card'></i></div>
                <div class="pay-text">
                    <div class="pay-id">#<?php echo $idpagamento; ?></div>
                    <div class="pay-status-sm"><i class='bx <?php echo $sicon; ?>'></i> <?php echo $status; ?></div>
                </div>
            </div>
            <div class="pay-valor">R$ <?php echo number_format($valor, 2, ',', '.'); ?></div>
        </div>
        <div class="pay-body">
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-icon"><i class='bx bx-user' style="color:#60a5fa;"></i></div>
                    <div class="info-content"><div class="info-label">USUÁRIO</div><div class="info-value"><?php echo htmlspecialchars($login); ?></div></div>
                </div>
                <div class="info-row">
                    <div class="info-icon"><i class='bx bx-store' style="color:#a78bfa;"></i></div>
                    <div class="info-content"><div class="info-label">REVENDEDOR</div><div class="info-value"><?php echo htmlspecialchars($revendedor_login); ?><?php if($byid==1): ?><span class="rev-tag">ADMIN</span><?php endif; ?></div></div>
                </div>
                <div class="info-row">
                    <div class="info-icon"><i class='bx bx-calendar' style="color:#fbbf24;"></i></div>
                    <div class="info-content"><div class="info-label">DATA</div><div class="info-value"><?php echo $data; ?></div></div>
                </div>
                <div class="info-row">
                    <div class="info-icon"><i class='bx bx-check-shield' style="color:<?php echo $status=='Aprovado'?'#34d399':($status=='Pendente'?'#fbbf24':'#f87171'); ?>;"></i></div>
                    <div class="info-content"><div class="info-label">STATUS</div><div class="info-value"><span class="status-badge <?php echo $sclass; ?>"><i class='bx <?php echo $sicon; ?>'></i> <?php echo $status; ?></span></div></div>
                </div>
            </div>
            <!-- Descrição -->
            <div class="desc-row">
                <div class="info-icon"><i class='bx bx-detail' style="color:#4ECDC4;"></i></div>
                <div class="info-content"><div class="info-label">DESCRIÇÃO</div><div class="info-value"><?php echo htmlspecialchars($texto); ?></div></div>
            </div>
            <!-- Ações -->
            <div class="pay-actions">
                <button class="action-btn sv-btn-roxo" onclick="abrirModalDetalhes('<?php echo $idpagamento; ?>','<?php echo htmlspecialchars(addslashes($login)); ?>',<?php echo $valor; ?>,'<?php echo $status; ?>','<?php echo $data; ?>','<?php echo htmlspecialchars(addslashes($revendedor_login)); ?>',<?php echo $limite_plano; ?>,'<?php echo addslashes($duracao); ?>','<?php echo htmlspecialchars(addslashes($texto)); ?>')"><i class='bx bx-detail'></i> Detalhes</button>
                <button class="action-btn sv-btn-vermelho2" onclick="abrirModalExcluir(<?php echo $id; ?>,'<?php echo $idpagamento; ?>','<?php echo htmlspecialchars(addslashes($login)); ?>',<?php echo $valor; ?>)"><i class='bx bx-trash'></i> Excluir</button>
            </div>
        </div>
    </div>
    <?php } } else { ?>
    <div class="empty-state">
        <i class='bx bx-credit-card'></i>
        <h3>Nenhum pagamento encontrado</h3>
        <p>Ainda não há pagamentos registrados no sistema</p>
    </div>
    <?php } ?>
    </div>

    <div class="pagination-info" id="paginationInfo">Exibindo <?php echo $result->num_rows; ?> registro(s) · <?php echo date('d/m/Y H:i:s'); ?></div>

</div>
</div>

<!-- ========== MODAIS ========== -->

<!-- Modal Detalhes -->
<div id="modalDetalhes" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom info"><h5><i class='bx bx-detail'></i> Detalhes do Pagamento</h5><button class="modal-close" onclick="fecharModal('modalDetalhes')"><i class='bx bx-x'></i></button></div>
    <div class="modal-body-custom" id="detalhesBody"></div>
    <div class="modal-footer-custom">
        <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalDetalhes')"><i class='bx bx-x'></i> Fechar</button>
    </div>
</div></div>
</div>

<!-- Modal Excluir -->
<div id="modalExcluir" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom error"><h5><i class='bx bx-trash'></i> Excluir Pagamento</h5><button class="modal-close" onclick="fecharModal('modalExcluir')"><i class='bx bx-x'></i></button></div>
    <div class="modal-body-custom">
        <div class="modal-ic error"><i class='bx bx-error-circle'></i></div>
        <p style="text-align:center;font-size:13px;">Tem certeza que deseja excluir este pagamento?</p>
        <div class="modal-info-box" id="excluirInfo"></div>
        <p style="text-align:center;font-size:10px;color:rgba(255,255,255,.35);margin-top:4px;">⚠️ Esta ação não pode ser desfeita!</p>
    </div>
    <div class="modal-footer-custom">
        <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalExcluir')"><i class='bx bx-x'></i> Cancelar</button>
        <button class="btn-modal btn-modal-danger" id="btnConfirmarExcluir"><i class='bx bx-trash'></i> Excluir</button>
    </div>
</div></div>
</div>

<!-- Modal Limpar Todos -->
<div id="modalLimparTodos" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom error"><h5><i class='bx bx-trash'></i> Limpar Todos Pagamentos</h5><button class="modal-close" onclick="fecharModal('modalLimparTodos')"><i class='bx bx-x'></i></button></div>
    <div class="modal-body-custom">
        <div class="modal-ic error"><i class='bx bx-error-circle'></i></div>
        <p style="text-align:center;font-size:13px;font-weight:600;">Deseja excluir TODOS os <?php echo $total_geral; ?> pagamentos?</p>
        <div class="modal-info-box">
            <div class="modal-info-row"><i class='bx bx-list-ul' style="color:#a78bfa;"></i> <span>Total:</span> <strong><?php echo $total_geral; ?> pagamentos</strong></div>
            <div class="modal-info-row"><i class='bx bx-dollar' style="color:#60a5fa;"></i> <span>Valor total:</span> <strong>R$ <?php echo number_format($total_recebido, 2, ',', '.'); ?></strong></div>
        </div>
        <p style="text-align:center;font-size:10px;color:#f87171;margin-top:6px;">⚠️ Todos os registros de pagamentos serão removidos permanentemente!</p>
    </div>
    <div class="modal-footer-custom">
        <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalLimparTodos')"><i class='bx bx-x'></i> Cancelar</button>
        <button class="btn-modal btn-modal-danger" onclick="confirmarLimparTodos()"><i class='bx bx-trash'></i> Limpar Todos</button>
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

<script>
// ===== Estado =====
var _payId = null;

// ===== Modais =====
function abrirModal(id){document.getElementById(id).classList.add('show');}
function fecharModal(id){document.getElementById(id).classList.remove('show');}
document.querySelectorAll('.modal-overlay').forEach(function(o){o.addEventListener('click',function(e){if(e.target===o)o.classList.remove('show');});});
document.addEventListener('keydown',function(e){if(e.key==='Escape')document.querySelectorAll('.modal-overlay.show').forEach(function(m){m.classList.remove('show');});});

// ===== Toast =====
function mostrarToast(msg,tipo){var t=document.createElement('div');t.className='toast-notification '+(tipo==='err'?'err':'ok');t.innerHTML='<i class="bx '+(tipo==='err'?'bx-error-circle':'bx-check-circle')+'"></i> '+msg;document.body.appendChild(t);setTimeout(function(){t.remove();},3500);}

// ===== AJAX =====
function post(data,cb){
    fetch(window.location.pathname,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:data})
    .then(function(r){return r.text();})
    .then(cb)
    .catch(function(){fecharModal('modalProcessando');mostrarToast('Erro de conexão!','err');});
}

// ===== Filtros =====
function filtrarPagamentos(){
    var busca=document.getElementById('searchInput').value.toLowerCase();
    var status=document.getElementById('statusFilter').value;
    var perPage=parseInt(document.getElementById('perPage').value);
    var cards=document.querySelectorAll('.pay-card');
    var count=0;var shown=0;
    cards.forEach(function(card){
        var st=card.getAttribute('data-status')||'';
        var login=card.getAttribute('data-login')||'';
        var mb=login.includes(busca);
        var ms=status==='todos'||st===status;
        if(mb&&ms){count++;if(count<=perPage){card.style.display='';shown++;}else{card.style.display='none';}}
        else{card.style.display='none';}
    });
    document.getElementById('paginationInfo').textContent='Exibindo '+shown+' de '+count+' registro(s)';
}

// ===== Modal Detalhes =====
function abrirModalDetalhes(idpag,login,valor,status,data,rev,limite,duracao,texto){
    var sc=status==='Aprovado'?'status-aprovado':(status==='Pendente'?'status-pendente':'status-cancelado');
    var si=status==='Aprovado'?'bx-check-circle':(status==='Pendente'?'bx-time':'bx-x-circle');
    var h='<div class="modal-ic info"><i class="bx bx-credit-card"></i></div>';
    h+='<div class="modal-info-box">';
    h+='<div class="modal-detail-row"><div class="modal-detail-label">ID</div><div class="modal-detail-value" style="font-family:monospace;">#'+idpag+'</div></div>';
    h+='<div class="modal-detail-row"><div class="modal-detail-label">Valor</div><div class="modal-detail-value" style="color:#60a5fa;font-size:16px;font-weight:800;">R$ '+valor.toFixed(2).replace(".",",")+'</div></div>';
    h+='<div class="modal-detail-row"><div class="modal-detail-label">Status</div><div class="modal-detail-value"><span class="status-badge '+sc+'"><i class="bx '+si+'"></i> '+status+'</span></div></div>';
    h+='<div class="modal-detail-row"><div class="modal-detail-label">Usuário</div><div class="modal-detail-value">'+login+'</div></div>';
    h+='<div class="modal-detail-row"><div class="modal-detail-label">Revendedor</div><div class="modal-detail-value">'+rev+'</div></div>';
    h+='<div class="modal-detail-row"><div class="modal-detail-label">Data</div><div class="modal-detail-value">'+data+'</div></div>';
    h+='<div class="modal-detail-row"><div class="modal-detail-label">Descrição</div><div class="modal-detail-value">'+texto+'</div></div>';
    h+='</div>';
    h+='<div class="modal-separator"></div>';
    h+='<div class="modal-section-title"><i class="bx bx-package"></i> Detalhes do Plano</div>';
    h+='<div class="modal-info-box">';
    h+='<div class="modal-detail-row"><div class="modal-detail-label">Duração</div><div class="modal-detail-value">'+duracao+'</div></div>';
    h+='<div class="modal-detail-row"><div class="modal-detail-label">Limite</div><div class="modal-detail-value">'+limite+' conexões</div></div>';
    h+='</div>';
    document.getElementById('detalhesBody').innerHTML=h;
    abrirModal('modalDetalhes');
}

// ===== Modal Excluir =====
function abrirModalExcluir(id,idpag,login,valor){
    _payId=id;
    var h='<div class="modal-info-row"><i class="bx bx-hash" style="color:#f87171;"></i> <span>ID:</span> <strong>#'+idpag+'</strong></div>';
    h+='<div class="modal-info-row"><i class="bx bx-user" style="color:#60a5fa;"></i> <span>Usuário:</span> <strong>'+login+'</strong></div>';
    h+='<div class="modal-info-row"><i class="bx bx-dollar" style="color:#fbbf24;"></i> <span>Valor:</span> <strong>R$ '+valor.toFixed(2).replace(".",",")+'</strong></div>';
    document.getElementById('excluirInfo').innerHTML=h;
    document.getElementById('btnConfirmarExcluir').onclick=function(){confirmarExcluir();};
    abrirModal('modalExcluir');
}

function confirmarExcluir(){
    fecharModal('modalExcluir');
    document.getElementById('processandoTexto').textContent='Excluindo pagamento...';
    abrirModal('modalProcessando');
    post('excluir_pagamento_ajax=1&id='+_payId,function(d){
        fecharModal('modalProcessando');
        if(d.trim()==='ok'){
            document.getElementById('sucessoMsg').textContent='Pagamento excluído com sucesso!';
            abrirModal('modalSucesso');
        } else {
            document.getElementById('erroMsg').textContent='Erro ao excluir pagamento!';
            abrirModal('modalErro');
        }
    });
}

// ===== Modal Limpar Todos =====
function abrirModalLimparTodos(){
    abrirModal('modalLimparTodos');
}

function confirmarLimparTodos(){
    fecharModal('modalLimparTodos');
    document.getElementById('processandoTexto').textContent='Removendo todos os pagamentos...';
    abrirModal('modalProcessando');
    post('limpar_todos_ajax=1',function(d){
        fecharModal('modalProcessando');
        if(d.trim()==='ok'){
            document.getElementById('sucessoMsg').textContent='Todos os pagamentos foram removidos!';
            abrirModal('modalSucesso');
        } else {
            document.getElementById('erroMsg').textContent='Erro ao limpar pagamentos!';
            abrirModal('modalErro');
        }
    });
}
</script>
</body>
</html>
<?php
    }
    aleatorio_listar_todos_pagamentos($kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf);
?>

