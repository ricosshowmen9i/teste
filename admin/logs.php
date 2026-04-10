<?php
error_reporting(0);
session_start();
include('../AegisCore/conexao.php');
include('headeradmin2.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) die("Connection failed: " . mysqli_connect_error());

// ========== INCLUIR SISTEMA DE TEMAS ==========
if(file_exists('../AegisCore/temas.php')){
    include_once '../AegisCore/temas.php';
    $temaAtual = initTemas($conn);
} else {
    $temaAtual = [];
}

date_default_timezone_set('America/Sao_Paulo');

// ========== PAGINAÇÃO ==========
$limite_por_pagina = isset($_GET['limite']) ? (int)$_GET['limite'] : 20;
$limite_por_pagina = in_array($limite_por_pagina, [10, 20, 50, 100]) ? $limite_por_pagina : 20;
$pagina_atual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_atual - 1) * $limite_por_pagina;

// Busca
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$filtro_tipo = isset($_GET['tipo']) ? mysqli_real_escape_string($conn, $_GET['tipo']) : '';

// Construir WHERE
$where = "WHERE revenda != 'Servidor'";
if (!empty($search)) $where .= " AND (texto LIKE '%$search%' OR revenda LIKE '%$search%')";
if (!empty($filtro_tipo)) {
    if ($filtro_tipo === 'criou') $where .= " AND texto LIKE '%Criou%'";
    elseif ($filtro_tipo === 'editou') $where .= " AND texto LIKE '%Editou%'";
    elseif ($filtro_tipo === 'excluiu') $where .= " AND texto LIKE '%Excluiu%'";
    elseif ($filtro_tipo === 'suspendeu') $where .= " AND texto LIKE '%Suspendeu%'";
    elseif ($filtro_tipo === 'pagamento') $where .= " AND texto LIKE '%Pagamento%'";
    elseif ($filtro_tipo === 'reativou') $where .= " AND texto LIKE '%Reativ%'";
    elseif ($filtro_tipo === 'renovar') $where .= " AND texto LIKE '%Renov%'";
}

// Total
$r = $conn->query("SELECT COUNT(*) as total FROM logs $where");
$total_registros = ($r) ? $r->fetch_assoc()['total'] : 0;
$total_paginas = ceil($total_registros / $limite_por_pagina);

// Stats
$total_criou = 0; $r = $conn->query("SELECT COUNT(*) as t FROM logs WHERE revenda != 'Servidor' AND texto LIKE '%Criou%'"); if($r) $total_criou = $r->fetch_assoc()['t'];
$total_editou = 0; $r = $conn->query("SELECT COUNT(*) as t FROM logs WHERE revenda != 'Servidor' AND texto LIKE '%Editou%'"); if($r) $total_editou = $r->fetch_assoc()['t'];
$total_excluiu = 0; $r = $conn->query("SELECT COUNT(*) as t FROM logs WHERE revenda != 'Servidor' AND texto LIKE '%Excluiu%'"); if($r) $total_excluiu = $r->fetch_assoc()['t'];
$total_suspenso = 0; $r = $conn->query("SELECT COUNT(*) as t FROM logs WHERE revenda != 'Servidor' AND texto LIKE '%Suspendeu%'"); if($r) $total_suspenso = $r->fetch_assoc()['t'];

// Buscar logs
$sql = "SELECT * FROM logs $where ORDER BY id DESC LIMIT $limite_por_pagina OFFSET $offset";
$result = $conn->query($sql);

// Limpar logs
if (isset($_POST['limparlogs'])) {
    $conn->query("TRUNCATE TABLE logs");
    echo "<script>window.location.href='logs.php';</script>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Logs do Sistema</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
<style>
<?php if(function_exists('getCSSVariables')) echo getCSSVariables($temaAtual); ?>

*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',sans-serif;background:var(--fundo,#0f172a);color:var(--texto,#fff);min-height:100vh}
.app-content{margin-left:0!important;padding:0!important}
.content-wrapper{max-width:1700px;margin:0 auto!important;padding:20px!important}

/* STATS CARD */
.stats-card{background:linear-gradient(135deg,var(--fundo_claro,#1e293b),var(--fundo,#0f172a));border-radius:20px;padding:20px 24px;margin-bottom:20px;border:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:20px;position:relative;overflow:hidden;transition:all .3s}
.stats-card:hover{transform:translateY(-2px);border-color:var(--primaria,#10b981)}
.stats-card-icon{width:60px;height:60px;background:linear-gradient(135deg,var(--primaria,#10b981),var(--secundaria,#C850C0));border-radius:18px;display:flex;align-items:center;justify-content:center;font-size:32px;color:#fff;flex-shrink:0}
.stats-card-content{flex:1}
.stats-card-title{font-size:13px;font-weight:600;color:rgba(255,255,255,.5);text-transform:uppercase;margin-bottom:5px}
.stats-card-value{font-size:36px;font-weight:800;background:linear-gradient(135deg,#fff,var(--primaria,#10b981));-webkit-background-clip:text;-webkit-text-fill-color:transparent;line-height:1}
.stats-card-subtitle{font-size:12px;color:rgba(255,255,255,.4);margin-top:4px}
.stats-card-decoration{position:absolute;right:20px;top:50%;transform:translateY(-50%);font-size:80px;opacity:.05}

/* MINI STATS */
.mini-stats{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px}
.mini-stat{flex:1;min-width:90px;background:rgba(255,255,255,.04);border-radius:12px;padding:12px;border:1px solid rgba(255,255,255,.06);text-align:center;transition:all .2s;cursor:pointer}
.mini-stat:hover{border-color:var(--primaria,#10b981);transform:translateY(-2px)}
.mini-stat.active{border-color:var(--primaria,#10b981);background:rgba(255,255,255,.08)}
.mini-stat-val{font-size:18px;font-weight:800}
.mini-stat-lbl{font-size:9px;color:rgba(255,255,255,.35);text-transform:uppercase;margin-top:2px}
.mini-stat-ic{font-size:20px;margin-bottom:4px}

/* MODERN CARD */
.modern-card{background:var(--fundo_claro,#1e293b);border-radius:16px;border:1px solid rgba(255,255,255,.08);overflow:hidden;margin-bottom:16px;transition:all .2s}
.modern-card:hover{border-color:var(--primaria,#10b981)}
.card-header-custom{padding:14px 18px;display:flex;align-items:center;gap:12px}
.card-header-custom.purple{background:linear-gradient(135deg,#8b5cf6,#7c3aed)}
.card-header-custom.red{background:linear-gradient(135deg,#dc2626,#b91c1c)}
.header-icon{width:36px;height:36px;background:rgba(255,255,255,.2);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;color:#fff}
.header-title{font-size:14px;font-weight:700;color:#fff}
.header-subtitle{font-size:10px;color:rgba(255,255,255,.7)}
.card-body-custom{padding:16px}

/* FILTROS */
.filter-group{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end}
.filter-item{flex:1;min-width:140px}
.filter-label{font-size:9px;font-weight:700;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px}
.filter-input,.filter-select{width:100%;padding:8px 12px;background:rgba(255,255,255,.06);border:1.5px solid rgba(255,255,255,.08);border-radius:9px;font-size:12px;color:#fff!important;transition:all .2s;font-family:inherit;outline:none}
.filter-input:focus,.filter-select:focus{border-color:var(--primaria,#10b981);background:rgba(255,255,255,.09)}
.filter-input::placeholder{color:rgba(255,255,255,.3)}
.filter-select{cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%23ffffff' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center}
.filter-select option{background:#1e293b;color:#fff!important}
.filter-btn{padding:8px 18px;border:none;border-radius:9px;font-weight:700;font-size:11px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;color:#fff;transition:all .2s;font-family:inherit;height:35px}
.filter-btn:hover{transform:translateY(-1px);filter:brightness(1.1)}
.filter-btn-search{background:linear-gradient(135deg,#3b82f6,#2563eb)}
.filter-btn-clear{background:linear-gradient(135deg,#dc2626,#b91c1c)}

/* GRID LOGS */
.logs-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:14px}

/* LOG CARD */
.log-card{background:var(--fundo_claro,#1e293b);border-radius:14px;overflow:hidden;transition:all .2s;border:1px solid rgba(255,255,255,.08)}
.log-card:hover{transform:translateY(-2px);border-color:var(--primaria,#10b981)}
.log-card-header{padding:12px 14px;display:flex;align-items:center;justify-content:space-between;gap:10px}
.log-card-header.criou{background:linear-gradient(135deg,#10b981,#059669)}
.log-card-header.editou{background:linear-gradient(135deg,#8b5cf6,#7c3aed)}
.log-card-header.excluiu{background:linear-gradient(135deg,#dc2626,#b91c1c)}
.log-card-header.suspendeu{background:linear-gradient(135deg,#f59e0b,#f97316)}
.log-card-header.pagamento{background:linear-gradient(135deg,#3b82f6,#2563eb)}
.log-card-header.reativou{background:linear-gradient(135deg,#06b6d4,#0891b2)}
.log-card-header.renovar{background:linear-gradient(135deg,#22c55e,#16a34a)}
.log-card-header.outro{background:linear-gradient(135deg,#64748b,#475569)}

.log-user-info{display:flex;align-items:center;gap:10px;flex:1;min-width:0}
.log-avatar{width:36px;height:36px;background:rgba(255,255,255,.2);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;color:#fff}
.log-user-name{font-size:13px;font-weight:700;color:#fff}
.log-user-tipo{font-size:9px;color:rgba(255,255,255,.7);display:flex;align-items:center;gap:4px;margin-top:1px}
.log-tipo-badge{background:rgba(255,255,255,.2);padding:2px 8px;border-radius:20px;font-size:8px;font-weight:700;color:#fff;text-transform:uppercase;letter-spacing:.3px;flex-shrink:0}

.log-card-body{padding:12px 14px}
.log-texto{font-size:12px;color:rgba(255,255,255,.75);line-height:1.6;display:flex;align-items:flex-start;gap:8px}
.log-texto-icon{width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0}
.log-texto-icon.criou{background:rgba(16,185,129,.15);color:#34d399}
.log-texto-icon.editou{background:rgba(139,92,246,.15);color:#a78bfa}
.log-texto-icon.excluiu{background:rgba(239,68,68,.15);color:#f87171}
.log-texto-icon.suspendeu{background:rgba(245,158,11,.15);color:#fbbf24}
.log-texto-icon.pagamento{background:rgba(59,130,246,.15);color:#60a5fa}
.log-texto-icon.reativou{background:rgba(6,182,212,.15);color:#22d3ee}
.log-texto-icon.renovar{background:rgba(34,197,94,.15);color:#4ade80}
.log-texto-icon.outro{background:rgba(100,116,139,.15);color:#94a3b8}

.log-card-footer{padding:8px 14px;border-top:1px solid rgba(255,255,255,.04);display:flex;align-items:center;justify-content:space-between}
.log-data{display:flex;align-items:center;gap:5px;font-size:10px;color:rgba(255,255,255,.35)}
.log-data i{font-size:13px;color:rgba(255,255,255,.25)}
.log-id{font-size:9px;color:rgba(255,255,255,.2);font-family:monospace}

/* PAGINAÇÃO */
.pagination-wrapper{display:flex;justify-content:center;align-items:center;gap:12px;flex-wrap:wrap;margin-top:20px;padding:10px 0}
.pagination{display:flex;align-items:center;gap:5px}
.pagination a,.pagination span{display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;padding:0 8px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);border-radius:8px;color:#fff;text-decoration:none;font-size:11px;font-weight:500;transition:all .2s}
.pagination a:hover{background:var(--primaria,#10b981);border-color:var(--primaria,#10b981)}
.pagination .active{background:var(--primaria,#10b981);border-color:var(--primaria,#10b981)}
.pagination .disabled{opacity:.4;cursor:not-allowed}
.pagination-info{text-align:center;margin-top:10px;color:rgba(255,255,255,.3);font-size:10px}

/* EMPTY */
.empty-state{grid-column:1/-1;text-align:center;padding:40px;background:var(--fundo_claro,#1e293b);border-radius:16px;border:1px solid rgba(255,255,255,.08)}
.empty-state i{font-size:48px;color:rgba(255,255,255,.15);margin-bottom:10px}
.empty-state h3{font-size:15px;margin-bottom:6px}
.empty-state p{font-size:11px;color:rgba(255,255,255,.3)}

/* MODAL */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.85);display:none;align-items:center;justify-content:center;z-index:10000;backdrop-filter:blur(8px);padding:16px}
.modal-overlay.show{display:flex}
.modal-container{animation:modalIn .3s ease;max-width:420px;width:92%}
@keyframes modalIn{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
.modal-content-custom{background:var(--fundo_claro,#1e293b);border-radius:20px;overflow:hidden;border:1px solid rgba(255,255,255,.1);box-shadow:0 25px 60px rgba(0,0,0,.5)}
.modal-header-custom{padding:14px 18px;display:flex;align-items:center;justify-content:space-between}
.modal-header-custom h5{margin:0;display:flex;align-items:center;gap:8px;font-size:14px;font-weight:700;color:#fff}
.modal-header-custom.danger{background:linear-gradient(135deg,#dc2626,#b91c1c)}
.modal-close{background:rgba(255,255,255,.15);border:none;color:#fff;font-size:18px;cursor:pointer;width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;transition:all .2s}
.modal-close:hover{background:rgba(255,255,255,.25);transform:rotate(90deg)}
.modal-body-custom{padding:18px;text-align:center}
.modal-ic{width:70px;height:70px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:34px;animation:icPop .5s cubic-bezier(.34,1.56,.64,1) .15s both}
@keyframes icPop{0%{transform:scale(0);opacity:0}100%{transform:scale(1);opacity:1}}
.modal-ic.error{background:rgba(239,68,68,.15);color:#f87171;border:2px solid rgba(239,68,68,.3)}
.modal-footer-custom{border-top:1px solid rgba(255,255,255,.07);padding:12px 18px;display:flex;justify-content:center;gap:8px}
.btn-modal{padding:8px 16px;border:none;border-radius:10px;font-weight:600;font-size:12px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;color:#fff;transition:all .2s;font-family:inherit}
.btn-modal:hover{transform:translateY(-1px);filter:brightness(1.08)}
.btn-modal-cancel{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12)}
.btn-modal-danger{background:linear-gradient(135deg,#dc2626,#b91c1c)}

/* TOAST */
.toast-notification{position:fixed;bottom:20px;right:20px;color:#fff;padding:10px 16px;border-radius:10px;display:flex;align-items:center;gap:8px;z-index:10001;animation:toastIn .3s ease;font-weight:600;font-size:12px;box-shadow:0 8px 20px rgba(0,0,0,.3)}
.toast-notification.ok{background:linear-gradient(135deg,#10b981,#059669)}
.toast-notification.err{background:linear-gradient(135deg,#dc2626,#b91c1c)}
@keyframes toastIn{from{transform:translateX(110%);opacity:0}to{transform:translateX(0);opacity:1}}

@media(max-width:768px){
    .content-wrapper{padding:10px!important}
    .logs-grid{grid-template-columns:1fr}
    .stats-card{padding:14px;gap:14px}
    .stats-card-icon{width:48px;height:48px;font-size:24px}
    .stats-card-value{font-size:28px}
    .filter-group{flex-direction:column}
    .mini-stats{flex-wrap:wrap}
    .mini-stat{min-width:70px}
}
</style>
</head>
<body class="<?php echo htmlspecialchars(getBodyClass($temaAtual ?? ['classe'=>'theme-dark'])); ?>">
<div class="app-content content">
<div class="content-overlay"></div>
<div class="content-wrapper">

<!-- STATS -->
<div class="stats-card">
<div class="stats-card-icon"><i class='bx bx-history'></i></div>
<div class="stats-card-content">
<div class="stats-card-title">Logs do Sistema</div>
<div class="stats-card-value"><?php echo $total_registros; ?></div>
<div class="stats-card-subtitle">registros de atividades dos revendedores</div>
</div>
<div class="stats-card-decoration"><i class='bx bx-history'></i></div>
</div>

<!-- MINI STATS -->
<div class="mini-stats">
<a href="?tipo=&search=<?php echo urlencode($search);?>" class="mini-stat <?php echo empty($filtro_tipo)?'active':'';?>" style="text-decoration:none;color:inherit"><div class="mini-stat-ic" style="color:#818cf8;"><i class='bx bx-list-ul'></i></div><div class="mini-stat-val" style="color:#818cf8;"><?php echo $total_registros; ?></div><div class="mini-stat-lbl">Total</div></a>
<a href="?tipo=criou&search=<?php echo urlencode($search);?>" class="mini-stat <?php echo $filtro_tipo==='criou'?'active':'';?>" style="text-decoration:none;color:inherit"><div class="mini-stat-ic" style="color:#34d399;"><i class='bx bx-user-plus'></i></div><div class="mini-stat-val" style="color:#34d399;"><?php echo $total_criou; ?></div><div class="mini-stat-lbl">Criações</div></a>
<a href="?tipo=editou&search=<?php echo urlencode($search);?>" class="mini-stat <?php echo $filtro_tipo==='editou'?'active':'';?>" style="text-decoration:none;color:inherit"><div class="mini-stat-ic" style="color:#a78bfa;"><i class='bx bx-edit'></i></div><div class="mini-stat-val" style="color:#a78bfa;"><?php echo $total_editou; ?></div><div class="mini-stat-lbl">Edições</div></a>
<a href="?tipo=excluiu&search=<?php echo urlencode($search);?>" class="mini-stat <?php echo $filtro_tipo==='excluiu'?'active':'';?>" style="text-decoration:none;color:inherit"><div class="mini-stat-ic" style="color:#f87171;"><i class='bx bx-trash'></i></div><div class="mini-stat-val" style="color:#f87171;"><?php echo $total_excluiu; ?></div><div class="mini-stat-lbl">Exclusões</div></a>
<a href="?tipo=suspendeu&search=<?php echo urlencode($search);?>" class="mini-stat <?php echo $filtro_tipo==='suspendeu'?'active':'';?>" style="text-decoration:none;color:inherit"><div class="mini-stat-ic" style="color:#fbbf24;"><i class='bx bx-lock'></i></div><div class="mini-stat-val" style="color:#fbbf24;"><?php echo $total_suspenso; ?></div><div class="mini-stat-lbl">Suspensões</div></a>
</div>

<!-- FILTROS + LIMPAR -->
<div class="modern-card">
<div class="card-header-custom purple">
<div class="header-icon"><i class='bx bx-filter-alt'></i></div>
<div><div class="header-title">Filtros & Ações</div><div class="header-subtitle">Busque e gerencie os logs</div></div>
</div>
<div class="card-body-custom">
<form method="GET" style="margin:0">
<div class="filter-group">
<div class="filter-item" style="min-width:200px">
<div class="filter-label">Buscar</div>
<input type="text" name="search" class="filter-input" placeholder="Buscar por texto ou revendedor..." value="<?php echo htmlspecialchars($search);?>">
</div>
<div class="filter-item" style="max-width:160px">
<div class="filter-label">Tipo de Ação</div>
<select name="tipo" class="filter-select">
<option value="">📋 Todos</option>
<option value="criou" <?php echo $filtro_tipo==='criou'?'selected':'';?>>🟢 Criações</option>
<option value="editou" <?php echo $filtro_tipo==='editou'?'selected':'';?>>🟣 Edições</option>
<option value="excluiu" <?php echo $filtro_tipo==='excluiu'?'selected':'';?>>🔴 Exclusões</option>
<option value="suspendeu" <?php echo $filtro_tipo==='suspendeu'?'selected':'';?>>🟡 Suspensões</option>
<option value="reativou" <?php echo $filtro_tipo==='reativou'?'selected':'';?>>🔵 Reativações</option>
<option value="renovar" <?php echo $filtro_tipo==='renovar'?'selected':'';?>>🟢 Renovações</option>
<option value="pagamento" <?php echo $filtro_tipo==='pagamento'?'selected':'';?>>💳 Pagamentos</option>
</select>
</div>
<div class="filter-item" style="max-width:100px">
<div class="filter-label">Por Página</div>
<select name="limite" class="filter-select">
<option value="10" <?php echo $limite_por_pagina==10?'selected':'';?>>10</option>
<option value="20" <?php echo $limite_por_pagina==20?'selected':'';?>>20</option>
<option value="50" <?php echo $limite_por_pagina==50?'selected':'';?>>50</option>
<option value="100" <?php echo $limite_por_pagina==100?'selected':'';?>>100</option>
</select>
</div>
<div class="filter-item" style="max-width:fit-content;display:flex;gap:6px;align-items:flex-end">
<button type="submit" class="filter-btn filter-btn-search"><i class='bx bx-search'></i> Buscar</button>
<a href="logs.php" class="filter-btn filter-btn-clear" style="text-decoration:none"><i class='bx bx-x'></i> Limpar</a>
</div>
</div>
</form>
<div style="margin-top:12px;padding-top:12px;border-top:1px solid rgba(255,255,255,.06)">
<form method="POST" style="margin:0;display:inline">
<button type="button" class="filter-btn filter-btn-clear" onclick="confirmarLimpar()"><i class='bx bx-trash'></i> Limpar Todos os Logs</button>
<button type="submit" name="limparlogs" id="btnLimparLogs" style="display:none"></button>
</form>
</div>
</div>
</div>

<!-- GRID DE LOGS -->
<div class="logs-grid" id="logsGrid">
<?php
if ($result && $result->num_rows > 0):
    while ($log = $result->fetch_assoc()):
        $texto = $log['texto'] ?? '';
        $revenda = $log['revenda'] ?? 'Desconhecido';

        // Detectar tipo
        $tipo = 'outro'; $icone = 'bx-info-circle'; $tipo_label = 'Ação';
        if (stripos($texto,'Criou')!==false) { $tipo='criou'; $icone='bx-user-plus'; $tipo_label='Criação'; }
        elseif (stripos($texto,'Editou')!==false) { $tipo='editou'; $icone='bx-edit'; $tipo_label='Edição'; }
        elseif (stripos($texto,'Excluiu')!==false||stripos($texto,'Deletou')!==false) { $tipo='excluiu'; $icone='bx-trash'; $tipo_label='Exclusão'; }
        elseif (stripos($texto,'Suspendeu')!==false) { $tipo='suspendeu'; $icone='bx-lock'; $tipo_label='Suspensão'; }
        elseif (stripos($texto,'Pagamento')!==false||stripos($texto,'Pago')!==false) { $tipo='pagamento'; $icone='bx-credit-card'; $tipo_label='Pagamento'; }
        elseif (stripos($texto,'Reativ')!==false) { $tipo='reativou'; $icone='bx-check-circle'; $tipo_label='Reativação'; }
        elseif (stripos($texto,'Renov')!==false) { $tipo='renovar'; $icone='bx-refresh'; $tipo_label='Renovação'; }

        $data_fmt = !empty($log['validade']) ? date('d/m/Y H:i', strtotime($log['validade'])) : date('d/m/Y H:i');
        $data_rel = !empty($log['validade']) ? date('d/m/Y', strtotime($log['validade'])) : '';
?>
<div class="log-card" data-texto="<?php echo strtolower(htmlspecialchars($texto));?>" data-revenda="<?php echo strtolower(htmlspecialchars($revenda));?>" data-tipo="<?php echo $tipo;?>">
<div class="log-card-header <?php echo $tipo;?>">
<div class="log-user-info">
<div class="log-avatar"><i class='bx bx-user-circle'></i></div>
<div>
<div class="log-user-name"><?php echo htmlspecialchars($revenda);?></div>
<div class="log-user-tipo"><i class='bx bx-time' style="font-size:10px"></i> <?php echo $data_fmt;?></div>
</div>
</div>
<span class="log-tipo-badge"><?php echo $tipo_label;?></span>
</div>
<div class="log-card-body">
<div class="log-texto">
<div class="log-texto-icon <?php echo $tipo;?>"><i class='bx <?php echo $icone;?>'></i></div>
<span><?php echo htmlspecialchars($texto);?></span>
</div>
</div>
<div class="log-card-footer">
<div class="log-data"><i class='bx bx-calendar'></i> <?php echo $data_rel;?></div>
<div class="log-id">#<?php echo $log['id'];?></div>
</div>
</div>
<?php endwhile; else: ?>
<div class="empty-state">
<i class='bx bx-history'></i>
<h3>Nenhum log encontrado</h3>
<p><?php echo !empty($search)||!empty($filtro_tipo)?'Tente alterar os filtros de busca.':'Os logs aparecerão aqui quando houver atividades.';?></p>
</div>
<?php endif; ?>
</div>

<!-- PAGINAÇÃO -->
<?php if($total_paginas > 1): ?>
<div class="pagination-wrapper">
<div class="pagination">
<?php if($pagina_atual>1):?><a href="?pagina=<?php echo $pagina_atual-1;?>&limite=<?php echo $limite_por_pagina;?>&search=<?php echo urlencode($search);?>&tipo=<?php echo urlencode($filtro_tipo);?>"><i class='bx bx-chevron-left'></i></a><?php else:?><span class="disabled"><i class='bx bx-chevron-left'></i></span><?php endif;?>
<?php
$max_p=5;$ini=max(1,$pagina_atual-floor($max_p/2));$fim=min($total_paginas,$ini+$max_p-1);
if($ini>1){echo '<a href="?pagina=1&limite='.$limite_por_pagina.'&search='.urlencode($search).'&tipo='.urlencode($filtro_tipo).'">1</a>';if($ini>2)echo '<span class="disabled">…</span>';}
for($i=$ini;$i<=$fim;$i++){echo($i==$pagina_atual)?'<span class="active">'.$i.'</span>':'<a href="?pagina='.$i.'&limite='.$limite_por_pagina.'&search='.urlencode($search).'&tipo='.urlencode($filtro_tipo).'">'.$i.'</a>';}
if($fim<$total_paginas){if($fim<$total_paginas-1)echo '<span class="disabled">…</span>';echo '<a href="?pagina='.$total_paginas.'&limite='.$limite_por_pagina.'&search='.urlencode($search).'&tipo='.urlencode($filtro_tipo).'">'.$total_paginas.'</a>';}
?>
<?php if($pagina_atual<$total_paginas):?><a href="?pagina=<?php echo $pagina_atual+1;?>&limite=<?php echo $limite_por_pagina;?>&search=<?php echo urlencode($search);?>&tipo=<?php echo urlencode($filtro_tipo);?>"><i class='bx bx-chevron-right'></i></a><?php else:?><span class="disabled"><i class='bx bx-chevron-right'></i></span><?php endif;?>
</div>
</div>
<?php endif;?>
<div class="pagination-info">Mostrando <?php echo min($offset+1,$total_registros);?>–<?php echo min($offset+$limite_por_pagina,$total_registros);?> de <?php echo $total_registros;?> logs</div>

</div></div>

<!-- MODAL LIMPAR -->
<div id="modalLimpar" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
<div class="modal-header-custom danger"><h5><i class='bx bx-trash'></i> Limpar Logs</h5><button class="modal-close" onclick="fecharModal('modalLimpar')"><i class='bx bx-x'></i></button></div>
<div class="modal-body-custom">
<div class="modal-ic error"><i class='bx bx-error-circle'></i></div>
<p style="font-size:14px;font-weight:700;margin-bottom:6px">Tem certeza?</p>
<p style="font-size:12px;color:rgba(255,255,255,.5)">Todos os <strong style="color:#f87171"><?php echo $total_registros;?></strong> logs serão apagados permanentemente.</p>
<p style="font-size:10px;color:rgba(255,255,255,.3);margin-top:6px">Esta ação não pode ser desfeita.</p>
</div>
<div class="modal-footer-custom">
<button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalLimpar')"><i class='bx bx-x'></i> Cancelar</button>
<button class="btn-modal btn-modal-danger" onclick="executarLimpar()"><i class='bx bx-trash'></i> Limpar Tudo</button>
</div>
</div></div>
</div>

<script>
function abrirModal(id){document.getElementById(id).classList.add('show')}
function fecharModal(id){document.getElementById(id).classList.remove('show')}
document.querySelectorAll('.modal-overlay').forEach(function(o){o.addEventListener('click',function(e){if(e.target===o)o.classList.remove('show')})});
document.addEventListener('keydown',function(e){if(e.key==='Escape')document.querySelectorAll('.modal-overlay.show').forEach(function(m){m.classList.remove('show')})});

function confirmarLimpar(){abrirModal('modalLimpar')}
function executarLimpar(){fecharModal('modalLimpar');document.getElementById('btnLimparLogs').click()}

function mostrarToast(msg,tipo){var t=document.createElement('div');t.className='toast-notification '+(tipo==='err'?'err':'ok');t.innerHTML='<i class="bx '+(tipo==='err'?'bx-error-circle':'bx-check-circle')+'"></i> '+msg;document.body.appendChild(t);setTimeout(function(){t.remove()},3500)}
</script>
</body>
</html>

