<?php // @ioncube.dk $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf -> "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs" RANDOM
    $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf = "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs";
    function aleatorio_categorias($input)
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

$sql = "SELECT * FROM categorias ORDER BY id DESC";
$result = $conn->query($sql);
$total_categorias = $result->num_rows;

// Stats
$total_servidores = 0;
$r = $conn->query("SELECT COUNT(*) as t FROM servidores");
if ($r) $total_servidores = $r->fetch_assoc()['t'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
<title>Categorias - Admin</title>
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
.header-icon{width:36px;height:36px;background:rgba(255,255,255,0.2);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;color:white;}
.header-title{font-size:14px;font-weight:700;color:white;}
.header-subtitle{font-size:10px;color:rgba(255,255,255,0.7);}
.card-body-custom{padding:16px;}

/* Filtros */
.filter-group{display:flex;flex-wrap:wrap;gap:12px;}
.filter-item{flex:1;min-width:140px;}
.filter-label{font-size:9px;font-weight:700;color:rgba(255,255,255,0.4);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;}
.filter-input{width:100%;padding:8px 12px;background:rgba(255,255,255,0.06);border:1.5px solid rgba(255,255,255,0.08);border-radius:9px;font-size:12px;color:#ffffff!important;transition:all .2s;font-family:inherit;outline:none;}
.filter-input:focus{border-color:var(--primaria,#10b981);background:rgba(255,255,255,0.09);}
.filter-input::placeholder{color:rgba(255,255,255,0.3);}

/* Actions bar */
.actions-bar{background:rgba(255,255,255,0.04);border-radius:14px;padding:12px;margin-bottom:16px;border:1px solid rgba(255,255,255,0.06);display:flex;flex-wrap:wrap;gap:6px;align-items:center;}
.actions-bar-title{font-size:11px;font-weight:700;color:rgba(255,255,255,0.4);display:flex;align-items:center;gap:5px;margin-right:auto;}
.actions-bar-title i{font-size:14px;color:var(--primaria);}

.sv-btn{padding:8px 16px;border:none;border-radius:10px;font-weight:700;font-size:10px;cursor:pointer;display:inline-flex;align-items:center;gap:5px;color:#ffffff;transition:all .25s;font-family:'Inter',sans-serif;text-decoration:none;line-height:1.2;white-space:nowrap;outline:none;-webkit-appearance:none;appearance:none;}
.sv-btn:hover{transform:translateY(-2px);filter:brightness(1.1);box-shadow:0 4px 12px rgba(0,0,0,0.3);color:#ffffff;text-decoration:none;}
.sv-btn i{font-size:14px;flex-shrink:0;}
.sv-btn-roxo{background:linear-gradient(135deg,var(--primaria,#4158D0),var(--secundaria,#C850C0));box-shadow:0 2px 8px rgba(65,88,208,0.3);}
.sv-btn-verde{background:linear-gradient(135deg,#10b981,#059669);box-shadow:0 2px 8px rgba(16,185,129,0.3);}

/* Grid */
.cats-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px;}

/* Card categoria */
.cat-card{background:var(--fundo_claro,#1e293b);border-radius:14px;overflow:hidden;transition:all .2s;border:1px solid rgba(255,255,255,0.08);}
.cat-card:hover{transform:translateY(-2px);border-color:var(--primaria,#10b981);}
.cat-header{background:linear-gradient(135deg,var(--primaria,#10b981),var(--secundaria,#C850C0));padding:12px;display:flex;align-items:center;gap:10px;}
.cat-avatar{width:36px;height:36px;background:rgba(255,255,255,0.2);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;}
.cat-text{flex:1;min-width:0;}
.cat-name{font-size:14px;font-weight:700;color:white;}
.cat-sub{font-size:10px;color:rgba(255,255,255,0.7);margin-top:2px;}
.cat-body{padding:12px;}

/* Info grid */
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:5px;margin-bottom:8px;}
.info-row{display:flex;align-items:center;gap:5px;padding:5px 7px;background:rgba(255,255,255,0.03);border-radius:7px;}
.info-icon{width:22px;height:22px;background:rgba(255,255,255,0.05);border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0;}
.info-content{flex:1;min-width:0;}
.info-label{font-size:8px;color:rgba(255,255,255,0.4);font-weight:600;}
.info-value{font-size:10px;font-weight:600;color:var(--texto,#fff);}

/* Server count badge */
.server-count-badge{display:inline-flex;align-items:center;gap:3px;padding:2px 8px;border-radius:16px;font-size:9px;font-weight:600;}
.server-count-badge.has{background:rgba(16,185,129,0.15);color:#34d399;}
.server-count-badge.empty{background:rgba(255,255,255,0.06);color:rgba(255,255,255,0.35);}

/* Actions */
.cat-actions{display:flex;gap:5px;margin-top:8px;}
.action-btn{flex:1;min-width:60px;padding:6px 8px;border:none;border-radius:8px;font-weight:600;font-size:10px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:4px;color:white;transition:all .2s;font-family:inherit;outline:none;-webkit-appearance:none;appearance:none;}
.action-btn:hover{transform:translateY(-1px);filter:brightness(1.05);}
.action-btn i{font-size:13px;}
.sv-btn-vermelho{background:linear-gradient(135deg,#dc2626,#b91c1c);box-shadow:0 2px 8px rgba(220,38,38,0.3);}

/* Empty */
.empty-state{grid-column:1/-1;text-align:center;padding:40px;background:var(--fundo_claro,#1e293b);border-radius:16px;border:1px solid rgba(255,255,255,0.08);}
.empty-state i{font-size:48px;color:rgba(255,255,255,0.15);margin-bottom:10px;}
.empty-state h3{font-size:15px;margin-bottom:6px;}
.empty-state p{font-size:11px;color:rgba(255,255,255,0.3);}

.pagination-info{text-align:center;margin-top:10px;color:rgba(255,255,255,0.3);font-size:10px;}

/* ========== MODAIS ========== */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.85);display:none;align-items:center;justify-content:center;z-index:10000;backdrop-filter:blur(8px);padding:16px;}
.modal-overlay.show{display:flex;}
.modal-container{animation:modalIn .3s ease;max-width:450px;width:92%;}
@keyframes modalIn{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
.modal-content-custom{background:var(--fundo_claro,#1e293b);border-radius:20px;overflow:hidden;border:1px solid rgba(255,255,255,0.1);box-shadow:0 25px 60px rgba(0,0,0,0.5);}
.modal-header-custom{padding:14px 18px;display:flex;align-items:center;justify-content:space-between;}
.modal-header-custom h5{margin:0;display:flex;align-items:center;gap:8px;font-size:14px;font-weight:700;color:white;}
.modal-header-custom.success{background:linear-gradient(135deg,#10b981,#059669);}
.modal-header-custom.error{background:linear-gradient(135deg,#dc2626,#b91c1c);}
.modal-header-custom.processing{background:linear-gradient(135deg,var(--primaria,#10b981),var(--secundaria,#C850C0));}
.modal-close{background:rgba(255,255,255,0.15);border:none;color:#fff;font-size:18px;cursor:pointer;width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;transition:all .2s;}
.modal-close:hover{background:rgba(255,255,255,0.25);transform:rotate(90deg);}
.modal-body-custom{padding:18px;}
.modal-footer-custom{border-top:1px solid rgba(255,255,255,0.07);padding:12px 18px;display:flex;justify-content:center;gap:8px;}
.modal-ic{width:70px;height:70px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:34px;animation:icPop .5s cubic-bezier(0.34,1.56,0.64,1) .15s both;}
@keyframes icPop{0%{transform:scale(0);opacity:0}100%{transform:scale(1);opacity:1}}
.modal-ic.success{background:rgba(16,185,129,.15);color:#34d399;border:2px solid rgba(16,185,129,.3);}
.modal-ic.error{background:rgba(239,68,68,.15);color:#f87171;border:2px solid rgba(239,68,68,.3);}

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
.btn-modal-danger{background:linear-gradient(135deg,#dc2626,#b91c1c);}

.spinner-wrap{display:flex;flex-direction:column;align-items:center;gap:14px;padding:20px 0;}
.spinner-ring{width:44px;height:44px;border:3px solid rgba(255,255,255,.08);border-top-color:var(--primaria,#10b981);border-right-color:var(--secundaria,#C850C0);border-radius:50%;animation:spin .8s linear infinite;}
@keyframes spin{to{transform:rotate(360deg)}}

/* Toast */
.toast-notification{position:fixed;bottom:20px;right:20px;color:#fff;padding:10px 16px;border-radius:10px;display:flex;align-items:center;gap:8px;z-index:10001;animation:toastIn .3s ease;font-weight:600;font-size:12px;box-shadow:0 8px 20px rgba(0,0,0,0.3);}
.toast-notification.ok{background:linear-gradient(135deg,#10b981,#059669);}
.toast-notification.err{background:linear-gradient(135deg,#dc2626,#b91c1c);}
@keyframes toastIn{from{transform:translateX(110%);opacity:0}to{transform:translateX(0);opacity:1}}

@media(max-width:768px){
    .app-content{margin-left:0!important;}
    .content-wrapper{padding:10px!important;}
    .cats-grid{grid-template-columns:1fr;}
    .stats-card{padding:14px;gap:14px;}
    .stats-card-icon{width:48px;height:48px;font-size:24px;}
    .stats-card-value{font-size:28px;}
    .filter-group{flex-direction:column;}
    .actions-bar{flex-direction:column;gap:8px;}
    .actions-bar-title{margin-right:0;margin-bottom:4px;}
    .actions-bar .sv-btn{width:100%;justify-content:center;}
    .cat-actions{flex-direction:column;}
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
        <div class="stats-card-icon"><i class='bx bx-category'></i></div>
        <div class="stats-card-content">
            <div class="stats-card-title">Gerenciar Categorias</div>
            <div class="stats-card-value"><?php echo $total_categorias; ?> Categorias</div>
            <div class="stats-card-subtitle">Organize seus servidores por categorias</div>
        </div>
        <div class="stats-card-decoration"><i class='bx bx-category'></i></div>
    </div>

    <!-- Mini Stats -->
    <div class="mini-stats">
        <div class="mini-stat"><div class="mini-stat-val" style="color:#a78bfa;"><?php echo $total_categorias; ?></div><div class="mini-stat-lbl">Categorias</div></div>
        <div class="mini-stat"><div class="mini-stat-val" style="color:#818cf8;"><?php echo $total_servidores; ?></div><div class="mini-stat-lbl">Servidores</div></div>
    </div>

    <!-- Filtros -->
    <div class="modern-card">
        <div class="card-header-custom blue">
            <div class="header-icon"><i class='bx bx-filter-alt'></i></div>
            <div><div class="header-title">Filtros e Busca</div><div class="header-subtitle">Encontre categorias rapidamente</div></div>
        </div>
        <div class="card-body-custom">
            <div class="filter-group">
                <div class="filter-item">
                    <div class="filter-label">Buscar por Nome</div>
                    <input type="text" class="filter-input" id="searchInput" placeholder="🔍 Digite o nome da categoria..." onkeyup="filtrarCategorias()">
                </div>
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="actions-bar">
        <div class="actions-bar-title"><i class='bx bx-cog'></i> Ações Rápidas</div>
        <a href="adicionarcategoria.php" class="sv-btn sv-btn-roxo"><i class='bx bx-plus'></i> Nova Categoria</a>
        <a href="adicionarservidor.php" class="sv-btn sv-btn-verde"><i class='bx bx-server'></i> Novo Servidor</a>
    </div>

    <!-- Grid -->
    <div class="cats-grid" id="catsGrid">
    <?php
    if ($result->num_rows > 0) {
        while ($categoria = mysqli_fetch_assoc($result)) {
            $cat_id = $categoria['subid'];
            $sql_serv = "SELECT COUNT(*) as total FROM servidores WHERE subid = '$cat_id'";
            $result_serv = $conn->query($sql_serv);
            $serv_count = $result_serv->fetch_assoc();
            $total_servs = $serv_count['total'];
            $has_servers = $total_servs > 0;
    ?>
    <div class="cat-card" data-nome="<?php echo strtolower(htmlspecialchars($categoria['nome'])); ?>">
        <div class="cat-header">
            <div class="cat-avatar"><i class='bx bx-category'></i></div>
            <div class="cat-text">
                <div class="cat-name"><?php echo htmlspecialchars($categoria['nome']); ?></div>
                <div class="cat-sub">ID: <?php echo $categoria['subid']; ?></div>
            </div>
            <span class="server-count-badge <?php echo $has_servers?'has':'empty'; ?>">
                <i class='bx bx-server'></i> <?php echo $total_servs; ?>
            </span>
        </div>
        <div class="cat-body">
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-icon"><i class='bx bx-hash' style="color:#4ECDC4;"></i></div>
                    <div class="info-content"><div class="info-label">ID CATEGORIA</div><div class="info-value"><?php echo $categoria['subid']; ?></div></div>
                </div>
                <div class="info-row">
                    <div class="info-icon"><i class='bx bx-server' style="color:#FF6B6B;"></i></div>
                    <div class="info-content"><div class="info-label">SERVIDORES</div><div class="info-value"><?php echo $total_servs; ?> servidor(es)</div></div>
                </div>
                <div class="info-row">
                    <div class="info-icon"><i class='bx bx-id-card' style="color:#818cf8;"></i></div>
                    <div class="info-content"><div class="info-label">ID INTERNO</div><div class="info-value">#<?php echo $categoria['id']; ?></div></div>
                </div>
                <div class="info-row">
                    <div class="info-icon"><i class='bx bx-shield-quarter' style="color:<?php echo $has_servers?'#34d399':'#f87171'; ?>;"></i></div>
                    <div class="info-content"><div class="info-label">STATUS</div><div class="info-value" style="color:<?php echo $has_servers?'#34d399':'rgba(255,255,255,0.35)'; ?>;"><?php echo $has_servers?'Em uso':'Sem uso'; ?></div></div>
                </div>
            </div>
            <div class="cat-actions">
                <button class="action-btn sv-btn-vermelho" onclick="abrirModalDeletar(<?php echo $categoria['id']; ?>,'<?php echo htmlspecialchars(addslashes($categoria['nome'])); ?>',<?php echo $total_servs; ?>)"><i class='bx bx-trash'></i> Deletar</button>
            </div>
        </div>
    </div>
    <?php } } else { ?>
    <div class="empty-state">
        <i class='bx bx-category'></i>
        <h3>Nenhuma categoria encontrada</h3>
        <p>Crie uma nova categoria para organizar seus servidores</p>
        <a href="adicionarcategoria.php" class="sv-btn sv-btn-roxo" style="margin-top:12px;"><i class='bx bx-plus'></i> Criar Categoria</a>
    </div>
    <?php } ?>
    </div>

    <div class="pagination-info">Total de <?php echo $total_categorias; ?> categoria(s) · <?php echo $total_servidores; ?> servidor(es) · <?php echo date('d/m/Y H:i:s'); ?></div>

</div>
</div>

<!-- ========== MODAIS ========== -->

<!-- Modal Deletar -->
<div id="modalDeletar" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom error"><h5><i class='bx bx-trash'></i> Deletar Categoria</h5><button class="modal-close" onclick="fecharModal('modalDeletar')"><i class='bx bx-x'></i></button></div>
    <div class="modal-body-custom">
        <div class="modal-ic error"><i class='bx bx-error-circle'></i></div>
        <p style="text-align:center;font-size:13px;">Tem certeza que deseja deletar a categoria <strong id="deletarNome" style="color:#f87171;"></strong>?</p>
        <div class="modal-info-box" id="deletarInfo"></div>
        <p style="text-align:center;font-size:10px;color:rgba(255,255,255,.35);margin-top:4px;">⚠️ Esta ação não pode ser desfeita!</p>
    </div>
    <div class="modal-footer-custom">
        <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalDeletar')"><i class='bx bx-x'></i> Cancelar</button>
        <button class="btn-modal btn-modal-danger" id="btnConfirmarDeletar"><i class='bx bx-trash'></i> Deletar</button>
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
var _catId = null;
var _catName = null;

// ===== Modais =====
function abrirModal(id){document.getElementById(id).classList.add('show');}
function fecharModal(id){document.getElementById(id).classList.remove('show');}
document.querySelectorAll('.modal-overlay').forEach(function(o){o.addEventListener('click',function(e){if(e.target===o)o.classList.remove('show');});});
document.addEventListener('keydown',function(e){if(e.key==='Escape')document.querySelectorAll('.modal-overlay.show').forEach(function(m){m.classList.remove('show');});});

// ===== Toast =====
function mostrarToast(msg,tipo){var t=document.createElement('div');t.className='toast-notification '+(tipo==='err'?'err':'ok');t.innerHTML='<i class="bx '+(tipo==='err'?'bx-error-circle':'bx-check-circle')+'"></i> '+msg;document.body.appendChild(t);setTimeout(function(){t.remove();},3500);}

// ===== Filtrar =====
function filtrarCategorias(){
    var busca=document.getElementById('searchInput').value.toLowerCase();
    document.querySelectorAll('.cat-card').forEach(function(card){
        var nome=card.getAttribute('data-nome')||'';
        card.style.display=nome.includes(busca)?'':'none';
    });
}

// ===== Deletar =====
function abrirModalDeletar(id,nome,servCount){
    _catId=id;_catName=nome;
    document.getElementById('deletarNome').textContent=nome;
    var info='<div class="modal-info-row"><i class="bx bx-category" style="color:#f87171;"></i> <span>Categoria:</span> <strong>'+nome+'</strong></div>';
    info+='<div class="modal-info-row"><i class="bx bx-server" style="color:#fbbf24;"></i> <span>Servidores vinculados:</span> <strong>'+servCount+'</strong></div>';
    if(servCount>0){
        info+='<div class="modal-info-row"><i class="bx bx-error" style="color:#f87171;"></i> <span style="color:#f87171;font-weight:600;">Os servidores perderão a categoria!</span></div>';
    }
    document.getElementById('deletarInfo').innerHTML=info;
    document.getElementById('btnConfirmarDeletar').onclick=function(){confirmarDeletar();};
    abrirModal('modalDeletar');
}

function confirmarDeletar(){
    fecharModal('modalDeletar');
    document.getElementById('processandoTexto').textContent='Deletando "'+_catName+'"...';
    abrirModal('modalProcessando');

    // Usa fetch pra chamar dellcategoria.php
    fetch('dellcategoria.php?id='+_catId)
        .then(function(r){return r.text();})
        .then(function(data){
            fecharModal('modalProcessando');
            var resp=data.trim().toLowerCase();
            if(resp.indexOf('erro')!==-1||resp.indexOf('error')!==-1){
                document.getElementById('erroMsg').textContent='Erro ao deletar categoria!';
                abrirModal('modalErro');
            } else {
                document.getElementById('sucessoMsg').textContent='Categoria "'+_catName+'" deletada com sucesso!';
                abrirModal('modalSucesso');
            }
        })
        .catch(function(){
            fecharModal('modalProcessando');
            // Se dellcategoria.php redireciona, recarrega
            document.getElementById('sucessoMsg').textContent='Categoria "'+_catName+'" deletada com sucesso!';
            abrirModal('modalSucesso');
        });
}
</script>
</body>
</html>
<?php
    }
    aleatorio_categorias($kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf);
?>

