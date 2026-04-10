<?php
error_reporting(0);
session_start();
include('../AegisCore/conexao.php');
include('headeradmin2.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

include_once '../AegisCore/temas.php';
$temaAtual = initTemas($conn);

function anti_sql($input) {
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function($m){ return ''; }, $input);
    return addslashes(strip_tags(trim($seg)));
}

if (!file_exists('suspenderrev.php')) { exit("<script>alert('Token Invalido!');</script>"); }
else { include_once 'suspenderrev.php'; }

if (!isset($_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe']) || !isset($_SESSION['token']) || $_SESSION['tokenatual'] != $_SESSION['token'] || (isset($_SESSION['token_invalido_']) && $_SESSION['token_invalido_'] === true)) {
    if (function_exists('security')) { security(); }
    else { echo "<script>alert('Token Inválido!');location.href='../index.php';</script>"; $_SESSION['token_invalido_'] = true; exit; }
}

date_default_timezone_set('America/Sao_Paulo');
$data_agora = date('Y-m-d H:i:s');

// Paginação
$limite_por_pagina = isset($_GET['limite']) ? (int)$_GET['limite'] : 10;
$limite_por_pagina = in_array($limite_por_pagina, [10, 20, 50, 100]) ? $limite_por_pagina : 10;
$pagina_atual = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$offset = ($pagina_atual - 1) * $limite_por_pagina;

$search = anti_sql($_GET['search'] ?? '');
$where_base = "byid = '{$_SESSION['iduser']}' AND expira < '$data_agora'";
if (!empty($search)) { $where_base .= " AND login LIKE '%$search%'"; }

$r_total = $conn->query("SELECT COUNT(*) as total FROM ssh_accounts WHERE $where_base");
$total_registros = $r_total ? $r_total->fetch_assoc()['total'] : 0;
$total_paginas = max(1, ceil($total_registros / $limite_por_pagina));

$result = $conn->query("SELECT * FROM ssh_accounts WHERE $where_base ORDER BY expira ASC LIMIT $limite_por_pagina OFFSET $offset");

// Stats rápidas
$total_hoje = 0; $r = $conn->query("SELECT COUNT(*) as t FROM ssh_accounts WHERE byid='{$_SESSION['iduser']}' AND expira < '$data_agora' AND expira >= '".date('Y-m-d')."'"); if($r){$total_hoje=$r->fetch_assoc()['t'];}
$total_semana = 0; $r = $conn->query("SELECT COUNT(*) as t FROM ssh_accounts WHERE byid='{$_SESSION['iduser']}' AND expira < '$data_agora' AND expira >= '".date('Y-m-d', strtotime('-7 days'))."'"); if($r){$total_semana=$r->fetch_assoc()['t'];}
$total_antigo = 0; $r = $conn->query("SELECT COUNT(*) as t FROM ssh_accounts WHERE byid='{$_SESSION['iduser']}' AND expira < '".date('Y-m-d', strtotime('-7 days'))."'"); if($r){$total_antigo=$r->fetch_assoc()['t'];}

$sql_config = "SELECT nomepainel, logo, icon FROM configs LIMIT 1";
$r_config = $conn->query($sql_config);
$row_config = $r_config ? $r_config->fetch_assoc() : [];
$nomepainel = $row_config['nomepainel'] ?? '';
$logo = $row_config['logo'] ?? '';
$icon = $row_config['icon'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Usuários Expirados</title>
<link rel="shortcut icon" href="<?php echo $icon; ?>">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
<style>
<?php echo getCSSVariables($temaAtual); ?>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Inter',sans-serif;background:var(--fundo,#0f172a);color:var(--texto,#fff);min-height:100vh;}
.app-content{margin-left:-0px!important;padding:0!important;}
.content-wrapper{max-width:1700px;margin:0 auto!important;padding:20px!important;}

/* Stats Card */
.stats-card{background:linear-gradient(135deg,var(--fundo_claro,#1e293b),var(--fundo,#0f172a));border-radius:20px;padding:20px 24px;margin-bottom:20px;border:1px solid rgba(255,255,255,0.08);display:flex;align-items:center;gap:20px;position:relative;overflow:hidden;transition:all .3s;}
.stats-card:hover{transform:translateY(-2px);border-color:#dc2626;}
.stats-card-icon{width:60px;height:60px;background:linear-gradient(135deg,#dc2626,#b91c1c);border-radius:18px;display:flex;align-items:center;justify-content:center;font-size:32px;color:white;flex-shrink:0;}
.stats-card-content{flex:1;}
.stats-card-title{font-size:13px;font-weight:600;color:rgba(255,255,255,0.5);text-transform:uppercase;margin-bottom:5px;}
.stats-card-value{font-size:36px;font-weight:800;background:linear-gradient(135deg,#fff,#f87171);-webkit-background-clip:text;-webkit-text-fill-color:transparent;line-height:1;}
.stats-card-subtitle{font-size:12px;color:rgba(255,255,255,0.4);margin-top:4px;}
.stats-card-decoration{position:absolute;right:20px;top:50%;transform:translateY(-50%);font-size:80px;opacity:0.05;}
.stats-card-action{flex-shrink:0;}

.btn-delete-all{background:linear-gradient(135deg,#dc2626,#b91c1c);border:none;border-radius:12px;padding:10px 20px;color:white;font-weight:700;font-size:12px;cursor:pointer;display:inline-flex;align-items:center;gap:8px;transition:all .2s;font-family:inherit;}
.btn-delete-all:hover{transform:translateY(-2px);filter:brightness(1.08);box-shadow:0 5px 15px rgba(220,38,38,0.35);}

/* Mini Stats */
.mini-stats{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;}
.mini-stat{flex:1;min-width:80px;background:rgba(255,255,255,0.04);border-radius:12px;padding:12px;border:1px solid rgba(255,255,255,0.06);text-align:center;transition:all .2s;}
.mini-stat:hover{border-color:#f87171;transform:translateY(-2px);}
.mini-stat-val{font-size:18px;font-weight:800;}
.mini-stat-lbl{font-size:9px;color:rgba(255,255,255,0.35);text-transform:uppercase;margin-top:2px;}

/* Modern Card */
.modern-card{background:var(--fundo_claro,#1e293b);border-radius:16px;border:1px solid rgba(255,255,255,0.08);overflow:hidden;margin-bottom:16px;transition:all .2s;}
.modern-card:hover{border-color:var(--primaria,#10b981);}
.card-header-custom{padding:14px 18px;display:flex;align-items:center;gap:12px;}
.card-header-custom.red{background:linear-gradient(135deg,#dc2626,#b91c1c);}
.header-icon{width:36px;height:36px;background:rgba(255,255,255,0.2);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;color:white;}
.header-title{font-size:14px;font-weight:700;color:white;}
.header-subtitle{font-size:10px;color:rgba(255,255,255,0.7);}
.card-body-custom{padding:16px;}

/* Filter */
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

.user-card{background:var(--fundo_claro,#1e293b);border-radius:14px;overflow:hidden;border:1px solid rgba(255,255,255,0.08);transition:all .2s;}
.user-card:hover{transform:translateY(-2px);border-color:#f87171;}

.user-header{background:linear-gradient(135deg,#dc2626,#991b1b);padding:12px;display:flex;align-items:center;justify-content:space-between;}
.user-info{display:flex;align-items:center;gap:10px;flex:1;min-width:0;}
.user-avatar{width:36px;height:36px;background:rgba(255,255,255,0.2);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;}
.user-text{flex:1;min-width:0;}
.user-name{font-size:14px;font-weight:700;color:white;display:flex;align-items:center;gap:5px;word-break:break-all;}
.v2ray-badge{background:rgba(255,255,255,0.2);padding:2px 6px;border-radius:20px;font-size:8px;font-weight:600;}
.user-senha{font-size:10px;color:rgba(255,255,255,0.7);margin-top:2px;display:flex;align-items:center;gap:4px;}
.btn-copy-card{background:rgba(255,255,255,0.15);border:none;border-radius:8px;padding:6px 10px;font-size:11px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:4px;color:white;flex-shrink:0;transition:all .2s;}
.btn-copy-card:hover{background:rgba(255,255,255,0.25);}
.btn-copy-card.copied{background:#10b981;}

.user-body{padding:12px;}

.expired-badge{display:inline-flex;align-items:center;gap:4px;background:rgba(220,38,38,0.2);color:#f87171;border:1px solid rgba(220,38,38,0.3);padding:3px 10px;border-radius:20px;font-size:10px;font-weight:700;margin-bottom:10px;}

.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:5px;margin-bottom:8px;}
.info-row{display:flex;align-items:center;gap:5px;padding:5px 7px;background:rgba(255,255,255,0.03);border-radius:7px;}
.info-row.full{grid-column:1/-1;}
.info-icon{width:22px;height:22px;background:rgba(255,255,255,0.05);border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0;}
.info-content{flex:1;min-width:0;}
.info-label{font-size:8px;color:rgba(255,255,255,0.4);font-weight:600;}
.info-value{font-size:10px;font-weight:600;word-break:break-all;}
.info-value.danger{color:#f87171;}

/* Action Buttons */
.user-actions{display:flex;flex-wrap:wrap;gap:5px;margin-top:8px;}
.action-btn{flex:1;min-width:60px;padding:6px 8px;border:none;border-radius:8px;font-weight:600;font-size:10px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:4px;color:white;transition:all .2s;font-family:inherit;}
.action-btn:hover{transform:translateY(-1px);filter:brightness(1.08);}
.action-btn:active{transform:scale(0.95);}
.btn-copy-action{background:linear-gradient(135deg,#3b82f6,#2563eb);}
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
.empty-state i{font-size:48px;color:rgba(16,185,129,0.3);margin-bottom:10px;}
.empty-state h3{font-size:15px;margin-bottom:6px;color:#34d399;}
.empty-state p{font-size:11px;color:rgba(255,255,255,0.3);}

/* Modal */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.85);display:none;align-items:center;justify-content:center;z-index:9999;backdrop-filter:blur(8px);padding:16px;}
.modal-overlay.show{display:flex;}
.modal-container{animation:modalIn .3s ease;max-width:450px;width:92%;}
@keyframes modalIn{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
.modal-content-custom{background:var(--fundo_claro,#1e293b);border-radius:20px;overflow:hidden;border:1px solid rgba(255,255,255,.1);box-shadow:0 25px 60px rgba(0,0,0,.5);}
.modal-header-custom{padding:14px 18px;display:flex;align-items:center;justify-content:space-between;}
.modal-header-custom h5{margin:0;display:flex;align-items:center;gap:8px;font-size:14px;font-weight:700;color:#fff;}
.modal-header-custom.error{background:linear-gradient(135deg,#dc2626,#b91c1c);}
.modal-header-custom.success{background:linear-gradient(135deg,#10b981,#059669);}
.modal-header-custom.processing{background:linear-gradient(135deg,var(--primaria,#10b981),var(--secundaria,#C850C0));}
.modal-close{background:rgba(255,255,255,.15);border:none;color:#fff;font-size:18px;cursor:pointer;width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;transition:all .2s;}
.modal-close:hover{background:rgba(255,255,255,.25);transform:rotate(90deg);}
.modal-body-custom{padding:18px;}
.modal-footer-custom{border-top:1px solid rgba(255,255,255,.07);padding:12px 18px;display:flex;justify-content:center;gap:8px;flex-wrap:wrap;}

.modal-ic{width:70px;height:70px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:34px;animation:icPop .5s cubic-bezier(0.34,1.56,0.64,1) .15s both;}
@keyframes icPop{0%{transform:scale(0);opacity:0}100%{transform:scale(1);opacity:1}}
.modal-ic.error{background:rgba(239,68,68,.15);color:#f87171;border:2px solid rgba(239,68,68,.3);}
.modal-ic.success{background:rgba(16,185,129,.15);color:#34d399;border:2px solid rgba(16,185,129,.3);}

.modal-user-info{background:rgba(255,255,255,.04);border-radius:12px;padding:12px;margin-bottom:12px;border:1px solid rgba(255,255,255,.06);}
.modal-user-row{display:flex;align-items:center;gap:8px;padding:4px 0;}
.modal-user-row i{font-size:14px;width:18px;text-align:center;}
.modal-user-row span{font-size:12px;color:rgba(255,255,255,.7);}
.modal-user-row strong{font-size:12px;color:#fff;}

.btn-modal{padding:8px 16px;border:none;border-radius:10px;font-weight:600;font-size:12px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;color:white;transition:all .2s;font-family:inherit;}
.btn-modal:hover{transform:translateY(-1px);filter:brightness(1.08);}
.btn-modal-cancel{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);}
.btn-modal-cancel:hover{background:rgba(255,255,255,.15);}
.btn-modal-danger{background:linear-gradient(135deg,#dc2626,#b91c1c);}
.btn-modal-ok{background:linear-gradient(135deg,#10b981,#059669);}

.spinner-wrap{display:flex;flex-direction:column;align-items:center;gap:14px;padding:20px 0;}
.spinner-ring{width:44px;height:44px;border:3px solid rgba(255,255,255,.08);border-top-color:#f87171;border-right-color:#dc2626;border-radius:50%;animation:spin .8s linear infinite;}
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
    .stats-card{flex-wrap:wrap;padding:14px;gap:14px;}
    .stats-card-icon{width:48px;height:48px;font-size:24px;}
    .stats-card-value{font-size:28px;}
    .stats-card-action{width:100%;}
    .btn-delete-all{width:100%;justify-content:center;}
    .filter-group{flex-direction:column;}
    .user-actions{display:grid;grid-template-columns:1fr 1fr;}
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
        <div class="stats-card-icon"><i class='bx bx-calendar-x'></i></div>
        <div class="stats-card-content">
            <div class="stats-card-title">Usuários Expirados</div>
            <div class="stats-card-value"><?php echo $total_registros; ?></div>
            <div class="stats-card-subtitle">contas com prazo vencido</div>
        </div>
        <div class="stats-card-action">
            <?php if ($total_registros > 0): ?>
            <button class="btn-delete-all" onclick="excluirTodos()"><i class='bx bx-trash'></i> Excluir Todos (<?php echo $total_registros; ?>)</button>
            <?php endif; ?>
        </div>
        <div class="stats-card-decoration"><i class='bx bx-calendar-x'></i></div>
    </div>

    <!-- Mini Stats -->
    <div class="mini-stats">
        <div class="mini-stat"><div class="mini-stat-val" style="color:#f87171;"><?php echo $total_registros; ?></div><div class="mini-stat-lbl">Total Expirados</div></div>
        <div class="mini-stat"><div class="mini-stat-val" style="color:#fbbf24;"><?php echo $total_hoje; ?></div><div class="mini-stat-lbl">Expirou Hoje</div></div>
        <div class="mini-stat"><div class="mini-stat-val" style="color:#fb923c;"><?php echo $total_semana; ?></div><div class="mini-stat-lbl">Últimos 7 dias</div></div>
        <div class="mini-stat"><div class="mini-stat-val" style="color:#818cf8;"><?php echo $total_antigo; ?></div><div class="mini-stat-lbl">+7 dias atrás</div></div>
    </div>

    <!-- Filtros -->
    <div class="modern-card">
        <div class="card-header-custom red">
            <div class="header-icon"><i class='bx bx-filter-alt'></i></div>
            <div><div class="header-title">Filtros de Busca</div><div class="header-subtitle">Buscar entre expirados</div></div>
        </div>
        <div class="card-body-custom">
            <div class="filter-group">
                <div class="filter-item">
                    <div class="filter-label">Buscar por Login</div>
                    <input type="text" class="form-control" id="searchInput" placeholder="Digite o nome..." value="<?php echo htmlspecialchars($search); ?>" onkeyup="filtrarUsuarios()">
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
            $id = $row['id']; $login = $row['login']; $senha = $row['senha'];
            $limite = $row['limite']; $expira = $row['expira'];
            $status = $row['status']; $categoria = $row['categoriaid'];
            $suspenso = $row['mainid']; $notas = $row['lastview']; $uuid = $row['uuid'];

            $expira_fmt = date('d/m/Y H:i', strtotime($expira));
            $dias_vencido = floor((time() - strtotime($expira)) / 86400);
            $dias_label = $dias_vencido == 0 ? 'Hoje' : ($dias_vencido == 1 ? 'Ontem' : 'Há '.$dias_vencido.' dias');

            $r_cat = $conn->query("SELECT nome FROM categorias WHERE subid='$categoria'");
            $cat_nome = ($r_cat && $r_cat->num_rows > 0) ? $r_cat->fetch_assoc()['nome'] : $categoria;

            $r_on = $conn->query("SELECT quantidade FROM onlines WHERE usuario='$login'");
            $usando = ($r_on && $r_on->num_rows > 0) ? $r_on->fetch_assoc()['quantidade'] : 0;
    ?>
    <div class="user-card" data-login="<?php echo strtolower(htmlspecialchars($login)); ?>" data-id="<?php echo $id; ?>" data-usuario="<?php echo htmlspecialchars($login); ?>" data-senha="<?php echo htmlspecialchars($senha); ?>" data-expira="<?php echo $expira_fmt; ?>" data-limite="<?php echo $limite; ?>">
        <div class="user-header">
            <div class="user-info">
                <div class="user-avatar"><i class='bx bx-user'></i></div>
                <div class="user-text">
                    <div class="user-name"><?php echo htmlspecialchars($login); ?><?php if(!empty($uuid)):?> <span class="v2ray-badge">V2Ray</span><?php endif;?></div>
                    <div class="user-senha"><i class='bx bx-lock-alt'></i> <?php echo htmlspecialchars($senha); ?></div>
                </div>
            </div>
            <button class="btn-copy-card" onclick="copiar(this)"><i class='bx bx-copy'></i> Copiar</button>
        </div>
        <div class="user-body">
            <div class="expired-badge"><i class='bx bx-time-five'></i> Expirado — <?php echo $dias_label; ?></div>
            <div class="info-grid">
                <div class="info-row"><div class="info-icon"><i class='bx bx-user' style="color:#818cf8;"></i></div><div class="info-content"><div class="info-label">LOGIN</div><div class="info-value"><?php echo htmlspecialchars($login); ?></div></div></div>
                <div class="info-row"><div class="info-icon"><i class='bx bx-lock-alt' style="color:#e879f9;"></i></div><div class="info-content"><div class="info-label">SENHA</div><div class="info-value"><?php echo htmlspecialchars($senha); ?></div></div></div>
                <div class="info-row"><div class="info-icon"><i class='bx bx-group' style="color:#34d399;"></i></div><div class="info-content"><div class="info-label">LIMITE</div><div class="info-value"><?php echo ($usando > 0 ? $usando.'/' : '') . $limite; ?></div></div></div>
                <div class="info-row"><div class="info-icon"><i class='bx bx-category' style="color:#60a5fa;"></i></div><div class="info-content"><div class="info-label">CATEGORIA</div><div class="info-value"><?php echo htmlspecialchars($cat_nome); ?></div></div></div>
                <div class="info-row full"><div class="info-icon"><i class='bx bx-calendar-x' style="color:#f87171;"></i></div><div class="info-content"><div class="info-label">EXPIROU EM</div><div class="info-value danger"><?php echo $expira_fmt; ?></div></div></div>
                <?php if(!empty($notas)):?>
                <div class="info-row full"><div class="info-icon"><i class='bx bx-note' style="color:#a78bfa;"></i></div><div class="info-content"><div class="info-label">NOTAS</div><div class="info-value"><?php echo htmlspecialchars($notas); ?></div></div></div>
                <?php endif;?>
            </div>
            <div class="user-actions">
                <button class="action-btn btn-copy-action" onclick="copiar(this.closest('.user-card').querySelector('.btn-copy-card'))"><i class='bx bx-copy'></i> Copiar</button>
                <button class="action-btn btn-danger" onclick="excluir(<?php echo $id; ?>)"><i class='bx bx-trash'></i> Excluir</button>
            </div>
        </div>
    </div>
    <?php endwhile; else: ?>
    <div class="empty-state"><i class='bx bx-check-circle'></i><h3>Nenhum usuário expirado!</h3><p>Todos os usuários estão dentro do prazo de validade.</p></div>
    <?php endif; ?>
    </div>

    <!-- Paginação -->
    <?php if ($total_paginas > 1): ?>
    <div class="pagination-wrapper">
        <div class="pagination">
            <?php if($pagina_atual>1):?><a href="?pagina=<?php echo $pagina_atual-1;?>&limite=<?php echo $limite_por_pagina;?>&search=<?php echo urlencode($search);?>"><i class='bx bx-chevron-left'></i></a><?php else:?><span class="disabled"><i class='bx bx-chevron-left'></i></span><?php endif;?>
            <?php
            $max_p=5;$ini=max(1,$pagina_atual-floor($max_p/2));$fim=min($total_paginas,$ini+$max_p-1);
            if($ini>1){echo '<a href="?pagina=1&limite='.$limite_por_pagina.'&search='.urlencode($search).'">1</a>';if($ini>2)echo '<span class="disabled">…</span>';}
            for($i=$ini;$i<=$fim;$i++){echo($i==$pagina_atual)?'<span class="active">'.$i.'</span>':'<a href="?pagina='.$i.'&limite='.$limite_por_pagina.'&search='.urlencode($search).'">'.$i.'</a>';}
            if($fim<$total_paginas){if($fim<$total_paginas-1)echo '<span class="disabled">…</span>';echo '<a href="?pagina='.$total_paginas.'&limite='.$limite_por_pagina.'&search='.urlencode($search).'">'.$total_paginas.'</a>';}
            ?>
            <?php if($pagina_atual<$total_paginas):?><a href="?pagina=<?php echo $pagina_atual+1;?>&limite=<?php echo $limite_por_pagina;?>&search=<?php echo urlencode($search);?>"><i class='bx bx-chevron-right'></i></a><?php else:?><span class="disabled"><i class='bx bx-chevron-right'></i></span><?php endif;?>
        </div>
    </div>
    <?php endif;?>
    <div class="pagination-info">Mostrando <?php echo min($offset+1,$total_registros);?>–<?php echo min($offset+$limite_por_pagina,$total_registros);?> de <?php echo $total_registros;?> expirados — Página <?php echo $pagina_atual;?>/<?php echo $total_paginas;?></div>

</div>
</div>

<!-- ========== MODAIS ========== -->

<!-- Excluir Individual -->
<div id="modalExcluir" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom error"><h5><i class='bx bx-trash'></i> Excluir Usuário</h5><button class="modal-close" onclick="fecharModal('modalExcluir')"><i class='bx bx-x'></i></button></div>
    <div class="modal-body-custom">
        <div class="modal-ic error"><i class='bx bx-trash'></i></div>
        <div class="modal-user-info" id="excluirInfo"></div>
        <p style="text-align:center;font-size:11px;color:#f87171;font-weight:600;margin-top:6px;">⚠️ Esta ação NÃO pode ser desfeita!</p>
    </div>
    <div class="modal-footer-custom">
        <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalExcluir')"><i class='bx bx-x'></i> Cancelar</button>
        <button class="btn-modal btn-modal-danger" onclick="confirmarExcluir()"><i class='bx bx-trash'></i> Excluir</button>
    </div>
</div></div></div>

<!-- Excluir Todos -->
<div id="modalExcluirTodos" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom error"><h5><i class='bx bx-error-circle'></i> Excluir Todos os Expirados</h5><button class="modal-close" onclick="fecharModal('modalExcluirTodos')"><i class='bx bx-x'></i></button></div>
    <div class="modal-body-custom">
        <div class="modal-ic error"><i class='bx bx-error-circle'></i></div>
        <div class="modal-user-info">
            <div class="modal-user-row"><i class="bx bx-user" style="color:#f87171;"></i> <span>Total a excluir:</span> <strong style="color:#f87171;"><?php echo $total_registros; ?> usuários</strong></div>
        </div>
        <p style="text-align:center;font-size:11px;color:#f87171;font-weight:600;margin-top:6px;">⚠️ TODOS os <?php echo $total_registros; ?> usuários expirados serão excluídos permanentemente!</p>
    </div>
    <div class="modal-footer-custom">
        <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalExcluirTodos')"><i class='bx bx-x'></i> Cancelar</button>
        <button class="btn-modal btn-modal-danger" onclick="confirmarExcluirTodos()"><i class='bx bx-trash'></i> Excluir Todos</button>
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

// Modal helpers
function abrirModal(id){document.getElementById(id).classList.add('show');}
function fecharModal(id){document.getElementById(id).classList.remove('show');}
document.querySelectorAll('.modal-overlay').forEach(function(o){o.addEventListener('click',function(e){if(e.target===o)o.classList.remove('show');});});
document.addEventListener('keydown',function(e){if(e.key==='Escape')document.querySelectorAll('.modal-overlay.show').forEach(function(m){m.classList.remove('show');});});

function toast(msg,tipo){var t=document.createElement('div');t.className='toast-notification '+(tipo==='err'?'err':'ok');t.innerHTML='<i class="bx '+(tipo==='err'?'bx-error-circle':'bx-check-circle')+'"></i> '+msg;document.body.appendChild(t);setTimeout(function(){t.remove();},3500);}

function getCard(id){return document.querySelector('.user-card[data-id="'+id+'"]');}

// AJAX GET helper
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
    return false;
}

// Filtrar
function filtrarUsuarios(){
    var busca=document.getElementById('searchInput').value.toLowerCase();
    document.querySelectorAll('.user-card').forEach(function(c){
        c.style.display=(c.getAttribute('data-login')||'').includes(busca)?'':'none';
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
    var texto='👤 USUÁRIO EXPIRADO\n━━━━━━━━━━━━━━━━\n🔑 Login: '+card.dataset.usuario+'\n🔒 Senha: '+card.dataset.senha+'\n📅 Expirou: '+card.dataset.expira+'\n🔗 Limite: '+card.dataset.limite+'\n━━━━━━━━━━━━━━━━';
    navigator.clipboard.writeText(texto).then(function(){btn.classList.add('copied');btn.innerHTML='<i class="bx bx-check"></i> Copiado!';setTimeout(function(){btn.classList.remove('copied');btn.innerHTML='<i class="bx bx-copy"></i> Copiar';},2000);toast('Informações copiadas!','ok');}).catch(function(){toast('Erro ao copiar!','err');});
}

// ══════════════════════════════════════════════════════════
// EXCLUIR INDIVIDUAL → excluiruser.php?id=X via GET
// ══════════════════════════════════════════════════════════
function excluir(id){
    _id=id;_card=getCard(id);
    var html='<div class="modal-user-row"><i class="bx bx-user" style="color:#818cf8;"></i> <span>Usuário:</span> <strong>'+(_card?_card.dataset.usuario:'')+'</strong></div>'
        +'<div class="modal-user-row"><i class="bx bx-lock-alt" style="color:#e879f9;"></i> <span>Senha:</span> <strong>'+(_card?_card.dataset.senha:'')+'</strong></div>'
        +'<div class="modal-user-row"><i class="bx bx-calendar-x" style="color:#f87171;"></i> <span>Expirou:</span> <strong style="color:#f87171;">'+(_card?_card.dataset.expira:'')+'</strong></div>';
    document.getElementById('excluirInfo').innerHTML=html;
    abrirModal('modalExcluir');
}

function confirmarExcluir(){
    fecharModal('modalExcluir');
    document.getElementById('processandoTexto').textContent='Excluindo '+(_card?_card.dataset.usuario:'')+'...';
    abrirModal('modalProcessando');

    ajaxGET('excluiruser.php?id='+_id,function(resp){
        if(respOk(resp,['excluido','sucesso','deletado'])){
            toast('Usuário excluído com sucesso!','ok');
            setTimeout(function(){location.reload();},1500);
        }else{
            toast('Erro ao excluir usuário!','err');
        }
    });
}

// ══════════════════════════════════════════════════════════
// EXCLUIR TODOS → deleteexpirados.php via GET
// ══════════════════════════════════════════════════════════
function excluirTodos(){
    abrirModal('modalExcluirTodos');
}

function confirmarExcluirTodos(){
    fecharModal('modalExcluirTodos');
    document.getElementById('processandoTexto').textContent='Excluindo todos os expirados...';
    abrirModal('modalProcessando');

    ajaxGET('deleteexpirados.php',function(resp){
        if(respOk(resp,['sucesso','excluido','deletado','ok'])){
            toast('Todos os expirados foram excluídos!','ok');
            setTimeout(function(){location.reload();},1500);
        }else{
            // Mesmo que não retorne texto esperado, o arquivo pode ter funcionado
            // Se não deu erro de rede, recarrega
            toast('Processado! Recarregando...','ok');
            setTimeout(function(){location.reload();},1500);
        }
    });
}
</script>
</body>
</html>

