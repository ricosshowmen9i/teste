<?php
error_reporting(0);
session_start();
if (!isset($_SESSION['login']) || !isset($_SESSION['senha'])) {
    session_destroy(); header('location:../index.php'); exit;
}
if ($_SESSION['login'] !== 'admin') {
    echo "<script>alert('Acesso negado!');window.location='home.php';</script>"; exit;
}
include('../AegisCore/conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) die("DB Error: " . mysqli_connect_error());
include_once 'headeradmin2.php';
if (!file_exists('suspenderrev.php')) exit("<script>alert('Token Invalido!');</script>");
else include_once 'suspenderrev.php';
if (!isset($_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe']) || !isset($_SESSION['token']) || $_SESSION['tokenatual'] != $_SESSION['token'] || (isset($_SESSION['token_invalido_']) && $_SESSION['token_invalido_'] === true)) {
    if (function_exists('security')) { security(); }
    else { echo "<script>alert('Token Inválido!');location.href='../index.php';</script>"; exit; }
}
include_once '../AegisCore/temas.php';
$temaAtual = initTemas($conn);
$listaTemas = getListaTemas($conn);

$total_temas = count($listaTemas);
$tema_ativo_nome = '';
$total_cores = 0;
foreach($listaTemas as $t){
    if($t['ativo']==1) $tema_ativo_nome = $t['nome'];
    $total_cores++;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Gerenciador de Temas</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
<script src="https://cdn.jsdelivr.net/npm/ace-builds@1.23.0/src-min-noconflict/ace.js"></script>
     <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <link rel="stylesheet" href="../AegisCore/temas_visual.css?v=<?php echo time(); ?>">
<style>
  body::before {
    content: 'Tema: <?php echo $temaAtual['classe']; ?>';
    position: fixed;
    top: 10px;
    right: 10px;
    background: red;
    color: white;
    padding: 10px;
    z-index: 99999;
    font-weight: bold;
  }
</style>

<style>


*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',sans-serif;background:var(--fundo,#0f172a);color:var(--texto,#fff);min-height:100vh}
.app-content{margin-left:0!important;padding:0!important}
.content-wrapper{max-width:1700px;margin:0 auto!important;padding:20px!important}

/* ========== STATS CARD ========== */
.stats-card{background:linear-gradient(135deg,var(--fundo_claro,#1e293b),var(--fundo,#0f172a));border-radius:20px;padding:20px 24px;margin-bottom:20px;border:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:20px;position:relative;overflow:hidden;transition:all .3s}
.stats-card:hover{transform:translateY(-2px);border-color:var(--primaria,#10b981)}
.stats-card-icon{width:60px;height:60px;background:linear-gradient(135deg,var(--primaria,#10b981),var(--secundaria,#C850C0));border-radius:18px;display:flex;align-items:center;justify-content:center;font-size:32px;color:#fff;flex-shrink:0}
.stats-card-content{flex:1}
.stats-card-title{font-size:13px;font-weight:600;color:rgba(255,255,255,.5);text-transform:uppercase;margin-bottom:5px}
.stats-card-value{font-size:36px;font-weight:800;background:linear-gradient(135deg,#fff,var(--primaria,#10b981));-webkit-background-clip:text;-webkit-text-fill-color:transparent;line-height:1}
.stats-card-subtitle{font-size:12px;color:rgba(255,255,255,.4);margin-top:4px}
.stats-card-decoration{position:absolute;right:20px;top:50%;transform:translateY(-50%);font-size:80px;opacity:.05}
.stats-card-right{display:flex;gap:8px;flex-shrink:0;flex-wrap:wrap}

/* ========== MINI STATS ========== */
.mini-stats{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px}
.mini-stat{flex:1;min-width:90px;background:rgba(255,255,255,.04);border-radius:12px;padding:12px;border:1px solid rgba(255,255,255,.06);text-align:center;transition:all .2s;cursor:pointer}
.mini-stat:hover{border-color:var(--primaria,#10b981);transform:translateY(-2px)}
.mini-stat.active{border-color:var(--primaria,#10b981);background:rgba(255,255,255,.08)}
.mini-stat-ic{font-size:20px;margin-bottom:4px}
.mini-stat-val{font-size:18px;font-weight:800}
.mini-stat-lbl{font-size:9px;color:rgba(255,255,255,.35);text-transform:uppercase;margin-top:2px}

/* ========== MODERN CARD ========== */
.modern-card{background:var(--fundo_claro,#1e293b);border-radius:16px;border:1px solid rgba(255,255,255,.08);overflow:hidden;margin-bottom:16px;transition:all .2s}
.modern-card:hover{border-color:var(--primaria,#10b981)}
.card-header-custom{padding:14px 18px;display:flex;align-items:center;gap:12px}
.card-header-custom.purple{background:linear-gradient(135deg,#8b5cf6,#7c3aed)}
.card-header-custom.blue{background:linear-gradient(135deg,#3b82f6,#2563eb)}
.card-header-custom.green{background:linear-gradient(135deg,#10b981,#059669)}
.card-header-custom.gradient{background:linear-gradient(135deg,var(--primaria,#10b981),var(--secundaria,#C850C0))}
.header-icon{width:36px;height:36px;background:rgba(255,255,255,.2);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;color:#fff}
.header-title{font-size:14px;font-weight:700;color:#fff}
.header-subtitle{font-size:10px;color:rgba(255,255,255,.7)}
.card-body-custom{padding:16px}

/* ========== FILTROS ========== */
.filter-group{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end}
.filter-item{flex:1;min-width:140px}
.filter-label{font-size:9px;font-weight:700;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px}
.filter-input{width:100%;padding:8px 12px;background:rgba(255,255,255,.06);border:1.5px solid rgba(255,255,255,.08);border-radius:9px;font-size:12px;color:#fff!important;transition:all .2s;font-family:inherit;outline:none}
.filter-input:focus{border-color:var(--primaria,#10b981);background:rgba(255,255,255,.09)}
.filter-input::placeholder{color:rgba(255,255,255,.3)}

/* ========== BOTÕES ========== */
.action-btn{padding:8px 16px;border:none;border-radius:10px;font-weight:700;font-size:11px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:6px;color:#fff;transition:all .2s;font-family:inherit}
.action-btn:hover{transform:translateY(-1px);filter:brightness(1.1)}
.action-btn:disabled{opacity:.4;cursor:not-allowed;transform:none!important}
.action-btn i{font-size:14px;pointer-events:none}
.btn-save{background:linear-gradient(135deg,#10b981,#059669)}
.btn-primary{background:linear-gradient(135deg,#3b82f6,#2563eb)}
.btn-purple{background:linear-gradient(135deg,#8b5cf6,#7c3aed)}
.btn-gradient{background:linear-gradient(135deg,var(--primaria,#10b981),var(--secundaria,#C850C0))}
.btn-orange{background:linear-gradient(135deg,#f59e0b,#f97316)}
.btn-danger{background:linear-gradient(135deg,#dc2626,#b91c1c)}
.btn-teal{background:linear-gradient(135deg,#06b6d4,#0891b2)}
.btn-export{background:rgba(139,92,246,.15);color:#a78bfa;border:1px solid rgba(139,92,246,.2)}
.btn-import{background:rgba(59,130,246,.15);color:#60a5fa;border:1px solid rgba(59,130,246,.3)}

/* ========== TABS ========== */
.tabs-container{margin-bottom:16px}
.tabs-buttons{display:flex;gap:4px;background:rgba(0,0,0,.3);padding:4px;border-radius:12px;width:fit-content;flex-wrap:wrap}
.tab-btn{padding:8px 16px;border:none;background:transparent;color:rgba(255,255,255,.5);font-size:11px;font-weight:600;cursor:pointer;border-radius:10px;transition:all .3s;display:flex;align-items:center;gap:5px;font-family:inherit}
.tab-btn i{font-size:14px}
.tab-btn.active{background:linear-gradient(135deg,var(--primaria,#10b981),var(--secundaria,#C850C0));color:#fff;box-shadow:0 4px 12px rgba(0,0,0,.3)}
.tab-btn:hover:not(.active){background:rgba(255,255,255,.06);color:#fff}
.tab-content{display:none;animation:fadeInTab .3s ease}
.tab-content.active{display:block}
@keyframes fadeInTab{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}

/* ========== GRID DE TEMAS (CARDS ESTILO USUÁRIOS) ========== */
.themes-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px}

.theme-card{background:var(--fundo_claro,#1e293b);border-radius:14px;overflow:hidden;transition:all .2s;border:1px solid rgba(255,255,255,.08);position:relative}
.theme-card:hover{transform:translateY(-2px);border-color:var(--primaria,#10b981)}
.theme-card.ativo{border-color:var(--primaria,#10b981)}

.theme-card-header{padding:12px 14px;display:flex;align-items:center;justify-content:space-between;gap:10px}
.theme-card-header.active-theme{background:linear-gradient(135deg,var(--primaria,#10b981),var(--secundaria,#C850C0))}
.theme-card-header.inactive-theme{background:linear-gradient(135deg,#475569,#334155)}

.theme-info{display:flex;align-items:center;gap:10px;flex:1;min-width:0}
.theme-avatar{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;color:#fff;overflow:hidden;position:relative}
.theme-avatar-preview{width:100%;height:100%;position:relative}
.theme-avatar-preview .bar{position:absolute;left:0;top:0;width:40%;height:100%}
.theme-avatar-preview .dot{position:absolute;right:4px;bottom:4px;width:10px;height:10px;border-radius:50%}
.theme-name{font-size:13px;font-weight:700;color:#fff}
.theme-status-text{font-size:9px;color:rgba(255,255,255,.7);display:flex;align-items:center;gap:4px;margin-top:1px}
.theme-badge{background:rgba(255,255,255,.2);padding:2px 8px;border-radius:20px;font-size:8px;font-weight:700;color:#fff;text-transform:uppercase;letter-spacing:.3px;flex-shrink:0}

.theme-card-body{padding:12px 14px}

/* Preview visual */
.theme-preview{height:50px;border-radius:10px;margin-bottom:10px;position:relative;overflow:hidden;display:flex}
.theme-preview-sidebar{width:28%;height:100%}
.theme-preview-content{flex:1;padding:8px;display:flex;flex-direction:column;gap:4px;justify-content:center}
.theme-preview-line{height:5px;border-radius:3px;background:rgba(255,255,255,.12)}
.theme-preview-btn{height:8px;width:50%;border-radius:4px}

/* Cores */
.theme-colors{display:flex;gap:5px;margin-bottom:10px}
.theme-colors span{flex:1;height:6px;border-radius:3px}

/* Info grid */
.theme-info-grid{display:grid;grid-template-columns:1fr 1fr;gap:5px;margin-bottom:8px}
.theme-info-row{display:flex;align-items:center;gap:5px;padding:5px 7px;background:rgba(255,255,255,.03);border-radius:7px}
.theme-info-icon{width:22px;height:22px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0}
.theme-info-label{font-size:8px;color:rgba(255,255,255,.4);font-weight:600}
.theme-info-value{font-size:10px;font-weight:600;word-break:break-all}

/* Ações */
.theme-actions{display:flex;flex-wrap:wrap;gap:5px;margin-top:8px}
.theme-actions .action-btn{flex:1;min-width:60px;padding:6px 8px;font-size:10px}

.theme-card-footer{padding:8px 14px;border-top:1px solid rgba(255,255,255,.04);display:flex;align-items:center;justify-content:space-between}
.theme-id{font-size:9px;color:rgba(255,255,255,.2);font-family:monospace}

/* ========== IMPORT ZONE ========== */
.import-zone{border:2px dashed rgba(255,255,255,.15);border-radius:16px;padding:40px;text-align:center;cursor:pointer;transition:.3s;background:rgba(255,255,255,.02)}
.import-zone:hover,.import-zone.drag{border-color:var(--primaria,#10b981);background:rgba(16,185,129,.05)}
.import-zone i{font-size:48px;color:rgba(255,255,255,.2);margin-bottom:12px;display:block}
.import-zone p{color:rgba(255,255,255,.5);font-size:14px}
.import-zone strong{color:var(--primaria,#10b981)}

/* ========== EMPTY STATE ========== */
.empty-state{grid-column:1/-1;text-align:center;padding:40px;background:var(--fundo_claro,#1e293b);border-radius:16px;border:1px solid rgba(255,255,255,.08)}
.empty-state i{font-size:48px;color:rgba(255,255,255,.15);margin-bottom:10px}
.empty-state h3{font-size:15px;margin-bottom:6px}
.empty-state p{font-size:11px;color:rgba(255,255,255,.3)}

/* ========== MODAL ========== */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.85);display:none;align-items:flex-start;justify-content:center;z-index:10000;backdrop-filter:blur(8px);padding:20px;overflow-y:auto}
.modal-overlay.show{display:flex}
.modal-container{animation:modalIn .3s ease;max-width:780px;width:100%;margin:auto}
.modal-container.small{max-width:420px}
@keyframes modalIn{from{opacity:0;transform:scale(.95) translateY(20px)}to{opacity:1;transform:scale(1) translateY(0)}}
.modal-content-custom{background:var(--fundo_claro,#1e293b);border-radius:20px;overflow:hidden;border:1px solid rgba(255,255,255,.1);box-shadow:0 25px 60px rgba(0,0,0,.5)}
.modal-header-custom{padding:14px 18px;display:flex;align-items:center;justify-content:space-between}
.modal-header-custom h5{margin:0;display:flex;align-items:center;gap:8px;font-size:14px;font-weight:700;color:#fff}
.modal-header-custom.gradient{background:linear-gradient(135deg,var(--primaria,#10b981),var(--secundaria,#C850C0))}
.modal-header-custom.danger{background:linear-gradient(135deg,#dc2626,#b91c1c)}
.modal-close{background:rgba(255,255,255,.15);border:none;color:#fff;font-size:18px;cursor:pointer;width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;transition:all .2s}
.modal-close:hover{background:rgba(255,255,255,.25);transform:rotate(90deg)}
.modal-body-custom{padding:18px}
.modal-footer-custom{border-top:1px solid rgba(255,255,255,.07);padding:12px 18px;display:flex;justify-content:flex-end;gap:8px}
.modal-ic{width:70px;height:70px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:34px;animation:icPop .5s cubic-bezier(.34,1.56,.64,1) .15s both}
@keyframes icPop{0%{transform:scale(0);opacity:0}100%{transform:scale(1);opacity:1}}
.modal-ic.error{background:rgba(239,68,68,.15);color:#f87171;border:2px solid rgba(239,68,68,.3)}

.btn-modal{padding:8px 16px;border:none;border-radius:10px;font-weight:600;font-size:12px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;color:#fff;transition:all .2s;font-family:inherit}
.btn-modal:hover{transform:translateY(-1px);filter:brightness(1.08)}
.btn-modal-cancel{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12)}
.btn-modal-danger{background:linear-gradient(135deg,#dc2626,#b91c1c)}
.btn-modal-ok{background:linear-gradient(135deg,#10b981,#059669)}

/* ========== FORM EDITOR ========== */
.f-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px}
.f-row.full{grid-template-columns:1fr}
.f-group{display:flex;flex-direction:column;gap:5px}
.f-label{font-size:9px;font-weight:700;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.5px}
.f-input{width:100%;padding:8px 12px;background:rgba(255,255,255,.06);border:1.5px solid rgba(255,255,255,.08);border-radius:9px;color:#fff;font-size:12px;outline:none;font-family:inherit;transition:.2s}
.f-input:focus{border-color:var(--primaria,#10b981);background:rgba(255,255,255,.09)}
.color-preview-wrap{position:relative;display:flex;align-items:center;gap:8px}
.color-dot{width:26px;height:26px;border-radius:7px;border:2px solid rgba(255,255,255,.1);cursor:pointer;flex-shrink:0;transition:.2s}
.color-dot:hover{transform:scale(1.1)}
input[type="color"]{position:absolute;opacity:0;width:0;height:0;pointer-events:none}
.colors-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(155px,1fr));gap:10px;margin-bottom:14px}

/* ACE EDITOR */
.ace-wrap{border:1px solid rgba(255,255,255,.1);border-radius:10px;overflow:hidden}
#cssEditor{height:200px;font-size:12px}

/* ========== TOAST ========== */
.toast-notification{position:fixed;bottom:20px;right:20px;color:#fff;padding:10px 16px;border-radius:10px;display:flex;align-items:center;gap:8px;z-index:10001;animation:toastIn .3s ease;font-weight:600;font-size:12px;box-shadow:0 8px 20px rgba(0,0,0,.3)}
.toast-notification.ok{background:linear-gradient(135deg,#10b981,#059669)}
.toast-notification.err{background:linear-gradient(135deg,#dc2626,#b91c1c)}
@keyframes toastIn{from{transform:translateX(110%);opacity:0}to{transform:translateX(0);opacity:1}}

/* ========== PAGINAÇÃO ========== */
.pagination-info{text-align:center;margin-top:10px;color:rgba(255,255,255,.3);font-size:10px}

@media(max-width:768px){
    .content-wrapper{padding:10px!important}
    .themes-grid{grid-template-columns:1fr}
    .stats-card{flex-wrap:wrap;padding:14px;gap:14px}
    .stats-card-icon{width:48px;height:48px;font-size:24px}
    .stats-card-value{font-size:28px}
    .stats-card-right{width:100%;justify-content:center}
    .mini-stats{flex-wrap:wrap}.mini-stat{min-width:70px}
    .filter-group{flex-direction:column}
    .f-row{grid-template-columns:1fr}
    .colors-grid{grid-template-columns:1fr 1fr}
    .tabs-buttons{width:100%}.tab-btn{flex:1;justify-content:center;font-size:9px;padding:6px 8px}
    .theme-actions{display:grid;grid-template-columns:repeat(3,1fr)}
}
</style>
</head>
<body class="<?php echo htmlspecialchars(getBodyClass($temaAtual ?? ['classe'=>'theme-dark'])); ?>">
<div class="app-content content">
<div class="content-overlay"></div>
<div class="content-wrapper">

<!-- STATS CARD -->
<div class="stats-card">
<div class="stats-card-icon"><i class='bx bx-palette'></i></div>
<div class="stats-card-content">
<div class="stats-card-title">Gerenciador de Temas</div>
<div class="stats-card-value"><?php echo $total_temas; ?></div>
<div class="stats-card-subtitle">temas cadastrados • Ativo: <strong style="color:var(--primaria,#10b981)"><?php echo htmlspecialchars($tema_ativo_nome ?: 'Nenhum'); ?></strong></div>
</div>
<div class="stats-card-right">
<button class="action-btn btn-import" onclick="showTab('import')"><i class='bx bx-import'></i> Importar</button>
<button class="action-btn btn-gradient" onclick="openEditor()"><i class='bx bx-plus'></i> Novo Tema</button>
</div>
<div class="stats-card-decoration"><i class='bx bx-palette'></i></div>
</div>

<!-- MINI STATS -->
<div class="mini-stats">
<div class="mini-stat active" onclick="showTab('lista')"><div class="mini-stat-ic" style="color:#818cf8"><i class='bx bx-grid-alt'></i></div><div class="mini-stat-val" style="color:#818cf8"><?php echo $total_temas; ?></div><div class="mini-stat-lbl">Total</div></div>
<div class="mini-stat"><div class="mini-stat-ic" style="color:#34d399"><i class='bx bx-check-circle'></i></div><div class="mini-stat-val" style="color:#34d399">1</div><div class="mini-stat-lbl">Ativo</div></div>
<div class="mini-stat"><div class="mini-stat-ic" style="color:#fbbf24"><i class='bx bx-moon'></i></div><div class="mini-stat-val" style="color:#fbbf24"><?php echo max(0,$total_temas-1); ?></div><div class="mini-stat-lbl">Inativos</div></div>
<div class="mini-stat"><div class="mini-stat-ic" style="color:#e879f9"><i class='bx bx-color'></i></div><div class="mini-stat-val" style="color:#e879f9">10</div><div class="mini-stat-lbl">Variáveis</div></div>
</div>

<!-- FILTROS + TABS -->
<div class="modern-card">
<div class="card-header-custom purple">
<div class="header-icon"><i class='bx bx-filter-alt'></i></div>
<div><div class="header-title">Filtros & Navegação</div><div class="header-subtitle">Busque temas e gerencie importações</div></div>
</div>
<div class="card-body-custom">
<div class="tabs-container">
<div class="tabs-buttons">
<button class="tab-btn active" id="tabBtnLista" onclick="showTab('lista')"><i class='bx bx-grid-alt'></i> Temas</button>
<button class="tab-btn" id="tabBtnImport" onclick="showTab('import')"><i class='bx bx-import'></i> Importar JSON</button>
</div>
</div>
<div class="filter-group">
<div class="filter-item" style="min-width:250px">
<div class="filter-label">Buscar Tema</div>
<input type="text" class="filter-input" id="searchInput" placeholder="Digite o nome do tema..." oninput="filterThemes(this.value)">
</div>
</div>
</div>
</div>

<!-- TAB: LISTA DE TEMAS -->
<div id="panel-lista" class="tab-content active">
<div class="themes-grid" id="temasGrid">
<?php if(!empty($listaTemas)): foreach($listaTemas as $t):
    $isAtivo = ($t['ativo']==1);
?>
<div class="theme-card <?php echo $isAtivo?'ativo':'';?>" data-nome="<?php echo strtolower(htmlspecialchars($t['nome']));?>" id="card-<?php echo $t['id'];?>">
<div class="theme-card-header <?php echo $isAtivo?'active-theme':'inactive-theme';?>">
<div class="theme-info">
<div class="theme-avatar">
<div class="theme-avatar-preview" style="background:<?php echo htmlspecialchars($t['cor_fundo']);?>">
<div class="bar" style="background:<?php echo htmlspecialchars($t['cor_menu_fundo']);?>"></div>
<div class="dot" style="background:<?php echo htmlspecialchars($t['cor_primaria']);?>"></div>
</div>
</div>
<div>
<div class="theme-name"><?php echo htmlspecialchars($t['nome']);?></div>
<div class="theme-status-text"><i class='bx <?php echo $isAtivo?'bx-check-circle':'bx-circle';?>' style="font-size:10px"></i> <?php echo $isAtivo?'Aplicado no sistema':'Inativo';?></div>
</div>
</div>
<span class="theme-badge"><?php echo $isAtivo?'✓ Ativo':'Inativo';?></span>
</div>
<div class="theme-card-body">
<!-- Preview -->
<div class="theme-preview" style="background:<?php echo htmlspecialchars($t['cor_fundo']);?>">
<div class="theme-preview-sidebar" style="background:<?php echo htmlspecialchars($t['cor_menu_fundo']);?>"></div>
<div class="theme-preview-content">
<div class="theme-preview-line" style="width:80%"></div>
<div class="theme-preview-line" style="width:55%"></div>
<div class="theme-preview-btn" style="background:<?php echo htmlspecialchars($t['cor_primaria']);?>"></div>
</div>
</div>
<!-- Paleta -->
<div class="theme-colors">
<span title="Primária" style="background:<?php echo htmlspecialchars($t['cor_primaria']);?>"></span>
<span title="Secundária" style="background:<?php echo htmlspecialchars($t['cor_secundaria']);?>"></span>
<span title="Fundo" style="background:<?php echo htmlspecialchars($t['cor_fundo']);?>"></span>
<span title="Sucesso" style="background:<?php echo htmlspecialchars($t['cor_sucesso']);?>"></span>
<span title="Erro" style="background:<?php echo htmlspecialchars($t['cor_erro']);?>"></span>
</div>
<!-- Info Grid -->
<div class="theme-info-grid">
<div class="theme-info-row"><div class="theme-info-icon" style="background:rgba(129,140,248,.1)"><i class='bx bx-palette' style="color:#818cf8"></i></div><div><div class="theme-info-label">PRIMÁRIA</div><div class="theme-info-value" style="color:<?php echo htmlspecialchars($t['cor_primaria']);?>"><?php echo htmlspecialchars($t['cor_primaria']);?></div></div></div>
<div class="theme-info-row"><div class="theme-info-icon" style="background:rgba(232,121,249,.1)"><i class='bx bx-color' style="color:#e879f9"></i></div><div><div class="theme-info-label">SECUNDÁRIA</div><div class="theme-info-value" style="color:<?php echo htmlspecialchars($t['cor_secundaria']);?>"><?php echo htmlspecialchars($t['cor_secundaria']);?></div></div></div>
<div class="theme-info-row"><div class="theme-info-icon" style="background:rgba(52,211,153,.1)"><i class='bx bx-check-circle' style="color:#34d399"></i></div><div><div class="theme-info-label">SUCESSO</div><div class="theme-info-value" style="color:<?php echo htmlspecialchars($t['cor_sucesso']);?>"><?php echo htmlspecialchars($t['cor_sucesso']);?></div></div></div>
<div class="theme-info-row"><div class="theme-info-icon" style="background:rgba(248,113,113,.1)"><i class='bx bx-x-circle' style="color:#f87171"></i></div><div><div class="theme-info-label">ERRO</div><div class="theme-info-value" style="color:<?php echo htmlspecialchars($t['cor_erro']);?>"><?php echo htmlspecialchars($t['cor_erro']);?></div></div></div>
</div>
<!-- Ações -->
<div class="theme-actions">
<?php if(!$isAtivo):?>
<button class="action-btn btn-save" onclick="activateTheme(<?php echo $t['id'];?>,event)"><i class='bx bx-check-circle'></i> Ativar</button>
<?php else:?>
<button class="action-btn btn-save" disabled><i class='bx bx-check-shield'></i> Ativo</button>
<?php endif;?>
<button class="action-btn btn-orange" onclick="openEditor(<?php echo $t['id'];?>,event)"><i class='bx bx-edit'></i> Editar</button>
<button class="action-btn btn-purple" onclick="exportTheme(<?php echo $t['id'];?>,'<?php echo htmlspecialchars($t['nome']);?>',event)"><i class='bx bx-export'></i> Exportar</button>
<?php if(!$isAtivo):?>
<button class="action-btn btn-danger" onclick="deleteTheme(<?php echo $t['id'];?>,'<?php echo htmlspecialchars($t['nome']);?>',event)"><i class='bx bx-trash'></i></button>
<?php endif;?>
</div>
</div>
<div class="theme-card-footer">
<div style="font-size:10px;color:rgba(255,255,255,.3)"><i class='bx bx-palette' style="font-size:12px"></i> <?php echo $isAtivo?'Tema principal':'Disponível';?></div>
<div class="theme-id">#<?php echo $t['id'];?></div>
</div>
</div>
<?php endforeach; else:?>
<div class="empty-state"><i class='bx bx-palette'></i><h3>Nenhum tema cadastrado</h3><p>Clique em "Novo Tema" para criar.</p></div>
<?php endif;?>
</div>
</div>

<!-- TAB: IMPORTAR -->
<div id="panel-import" class="tab-content">
<div style="max-width:540px;margin:0 auto">
<div class="import-zone" id="importZone" onclick="document.getElementById('jsonFile').click()" ondragover="dragOver(event)" ondragleave="dragLeave(event)" ondrop="dropFile(event)">
<input type="file" id="jsonFile" accept=".json" style="display:none" onchange="handleFile(this)">
<i class='bx bx-cloud-upload' id="importIcon"></i>
<p id="importText">Clique ou <strong>arraste um .json</strong> para importar</p>
</div>
<div id="importPreview" style="display:none;margin-top:20px">
<div style="background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.2);border-radius:14px;padding:20px">
<div style="display:flex;align-items:center;gap:10px;margin-bottom:14px">
<i class='bx bx-file-find' style="font-size:24px;color:#34d399"></i>
<div><div style="font-weight:700;font-size:14px" id="preview-nome">-</div><div style="font-size:11px;color:rgba(255,255,255,.4)">Pronto para importar</div></div>
</div>
<div style="display:flex;gap:8px;margin-bottom:14px" id="preview-colors"></div>
<div style="display:flex;gap:10px">
<button class="action-btn btn-save" style="flex:1" onclick="importTheme()"><i class='bx bx-import'></i> Importar</button>
<button class="action-btn btn-modal-cancel" onclick="clearImport()"><i class='bx bx-x'></i> Cancelar</button>
</div>
</div>
</div>
<div style="margin-top:24px;padding:14px;background:rgba(255,255,255,.03);border-radius:12px;border-left:3px solid var(--primaria,#10b981)">
<p style="font-size:11px;color:rgba(255,255,255,.4);line-height:1.7"><strong style="color:rgba(255,255,255,.7)">Formato:</strong> .json com campos <code style="color:#34d399">nome</code>, <code style="color:#34d399">cor_primaria</code>, etc.</p>
</div>
</div>
</div>

<div class="pagination-info">Total: <?php echo $total_temas;?> temas cadastrados</div>

</div></div>

<!-- ======== MODAL EDITOR ======== -->
<div class="modal-overlay" id="editorModal">
<div class="modal-container"><div class="modal-content-custom">
<div class="modal-header-custom gradient"><h5><i class='bx bx-palette'></i> <span id="modalTitle">Novo Tema</span></h5><button class="modal-close" onclick="closeEditor()"><i class='bx bx-x'></i></button></div>
<div class="modal-body-custom">
<input type="hidden" id="editId" value="">
<div class="f-row full"><div class="f-group"><label class="f-label">Nome do Tema</label><input type="text" class="f-input" id="editNome" placeholder="Ex: Tema Azul Noturno"></div></div>
<div class="f-label" style="margin-bottom:10px">🎨 Cores Principais</div>
<div class="colors-grid">
<?php
$campos_cores = [
    'cor_primaria'=>'Primária','cor_secundaria'=>'Secundária','cor_terciaria'=>'Terciária',
    'cor_fundo'=>'Fundo','cor_fundo_claro'=>'Fundo Claro','cor_texto'=>'Texto',
    'cor_sucesso'=>'Sucesso','cor_erro'=>'Erro','cor_aviso'=>'Aviso','cor_info'=>'Info',
];
foreach($campos_cores as $campo=>$label):
?>
<div class="f-group">
<label class="f-label"><?php echo $label;?></label>
<div class="color-preview-wrap">
<div class="color-dot" id="dot-<?php echo $campo;?>" onclick="triggerColor('<?php echo $campo;?>')"></div>
<input type="color" id="color-<?php echo $campo;?>" oninput="updateColor('<?php echo $campo;?>',this.value)" onchange="updateColor('<?php echo $campo;?>',this.value)">
<input type="text" class="f-input" id="text-<?php echo $campo;?>" placeholder="#10b981" oninput="updateColorFromText('<?php echo $campo;?>',this.value)" style="flex:1">
</div>
</div>
<?php endforeach;?>
</div>
<div class="f-row full"><div class="f-group"><label class="f-label">Fundo do Menu</label><input type="text" class="f-input" id="edit-cor_menu_fundo" placeholder="linear-gradient(180deg,#1a1f3a,#0f1429)"></div></div>
<div class="f-row full" style="margin-top:14px"><div class="f-group"><label class="f-label">CSS Customizado (opcional)</label><div class="ace-wrap"><div id="cssEditor"></div></div><p style="font-size:9px;color:rgba(255,255,255,.25);margin-top:4px">Injetado em todas as páginas</p></div></div>
</div>
<div class="modal-footer-custom">
<button class="btn-modal btn-modal-cancel" onclick="closeEditor()">Cancelar</button>
<button class="btn-modal btn-modal-ok" id="btnSalvar" onclick="saveTheme()"><i class='bx bx-save'></i> Salvar Tema</button>
</div>
</div></div>
</div>

<!-- MODAL EXCLUIR -->
<div class="modal-overlay" id="deleteModal">
<div class="modal-container small"><div class="modal-content-custom">
<div class="modal-header-custom danger"><h5><i class='bx bx-trash'></i> Excluir Tema</h5><button class="modal-close" onclick="fecharModal('deleteModal')"><i class='bx bx-x'></i></button></div>
<div class="modal-body-custom" style="text-align:center">
<div class="modal-ic error"><i class='bx bx-error-circle'></i></div>
<p style="font-size:14px;font-weight:700;margin-bottom:6px">Tem certeza?</p>
<p style="font-size:12px;color:rgba(255,255,255,.5)">O tema <strong id="deleteNome" style="color:#f87171"></strong> será excluído permanentemente.</p>
</div>
<div class="modal-footer-custom">
<button class="btn-modal btn-modal-cancel" onclick="fecharModal('deleteModal')"><i class='bx bx-x'></i> Cancelar</button>
<button class="btn-modal btn-modal-danger" onclick="confirmDelete()"><i class='bx bx-trash'></i> Excluir</button>
</div>
</div></div>
</div>

<script>
var pendingDeleteId=null,importData=null,aceEditor=null;

document.addEventListener('DOMContentLoaded',function(){
    aceEditor=ace.edit('cssEditor');
    aceEditor.setTheme('ace/theme/twilight');
    aceEditor.getSession().setMode('ace/mode/css');
    aceEditor.setOptions({fontSize:'12px',showPrintMargin:false});
});

// MODAIS
function abrirModal(id){document.getElementById(id).classList.add('show')}
function fecharModal(id){document.getElementById(id).classList.remove('show')}
document.querySelectorAll('.modal-overlay').forEach(function(o){o.addEventListener('click',function(e){if(e.target===o)o.classList.remove('show')})});
document.addEventListener('keydown',function(e){if(e.key==='Escape')document.querySelectorAll('.modal-overlay.show').forEach(function(m){m.classList.remove('show')})});

// TOAST
function showToast(msg,tipo){var t=document.createElement('div');t.className='toast-notification '+(tipo==='error'?'err':'ok');t.innerHTML='<i class="bx '+(tipo==='error'?'bx-error-circle':'bx-check-circle')+'"></i> '+msg;document.body.appendChild(t);setTimeout(function(){t.remove()},3500)}

// TABS
function showTab(tab){
    document.getElementById('panel-lista').classList.remove('active');
    document.getElementById('panel-import').classList.remove('active');
    document.getElementById('tabBtnLista').classList.remove('active');
    document.getElementById('tabBtnImport').classList.remove('active');
    document.getElementById('panel-'+tab).classList.add('active');
    document.getElementById('tabBtn'+tab.charAt(0).toUpperCase()+tab.slice(1)).classList.add('active');
}
function showTab(tab){
    ['lista','import'].forEach(function(t){
        var p=document.getElementById('panel-'+t);if(p)p.classList.remove('active');
    });
    document.getElementById('tabBtnLista').classList.remove('active');
    document.getElementById('tabBtnImport').classList.remove('active');
    var panel=document.getElementById('panel-'+tab);if(panel)panel.classList.add('active');
    if(tab==='lista')document.getElementById('tabBtnLista').classList.add('active');
    else document.getElementById('tabBtnImport').classList.add('active');
}

// BUSCA
function filterThemes(q){q=q.toLowerCase();document.querySelectorAll('.theme-card').forEach(function(c){c.style.display=c.dataset.nome.includes(q)?'':'none'})}

// EDITOR
var camposCores=['cor_primaria','cor_secundaria','cor_terciaria','cor_fundo','cor_fundo_claro','cor_texto','cor_sucesso','cor_erro','cor_aviso','cor_info'];
function openEditor(id,e){
    if(e)e.stopPropagation();
    document.getElementById('editId').value=id||'';
    document.getElementById('modalTitle').textContent=id?'Editar Tema':'Novo Tema';
    if(!id){
        document.getElementById('editNome').value='';
        document.getElementById('edit-cor_menu_fundo').value='linear-gradient(180deg,#1a1f3a 0%,#0f1429 100%)';
        if(aceEditor)aceEditor.setValue('');
        var d={cor_primaria:'#10b981',cor_secundaria:'#C850C0',cor_terciaria:'#FFCC70',cor_fundo:'#0f172a',cor_fundo_claro:'#1e293b',cor_texto:'#ffffff',cor_sucesso:'#10b981',cor_erro:'#dc2626',cor_aviso:'#f59e0b',cor_info:'#3b82f6'};
        camposCores.forEach(function(c){setColorField(c,d[c]||'#ffffff')});
    } else {
        fetch('../AegisCore/api_temas.php?action=listar').then(function(r){return r.json()}).then(function(data){
            var tema=data.temas.find(function(t){return t.id==id});
            if(!tema)return;
            document.getElementById('editNome').value=tema.nome;
            document.getElementById('edit-cor_menu_fundo').value=tema.cor_menu_fundo;
            if(aceEditor)aceEditor.setValue(tema.css_customizado||'',-1);
            camposCores.forEach(function(c){setColorField(c,tema[c]||'#ffffff')});
        });
    }
    abrirModal('editorModal');
}
function closeEditor(){fecharModal('editorModal')}
function setColorField(campo,valor){
    var isHex=/^#[0-9a-fA-F]{3,8}$/.test(valor);
    var dot=document.getElementById('dot-'+campo);
    var cp=document.getElementById('color-'+campo);
    var ti=document.getElementById('text-'+campo);
    if(dot)dot.style.background=isHex?valor:'#999';
    if(cp&&isHex)cp.value=valor;
    if(ti)ti.value=valor;
}
function triggerColor(campo){document.getElementById('color-'+campo).click()}
function updateColor(campo,valor){setColorField(campo,valor)}
function updateColorFromText(campo,valor){if(/^#[0-9a-fA-F]{3,8}$/.test(valor)){document.getElementById('dot-'+campo).style.background=valor;document.getElementById('color-'+campo).value=valor}}

function saveTheme(){
    var btn=document.getElementById('btnSalvar');
    btn.innerHTML='<i class="bx bx-loader-alt bx-spin"></i> Salvando...';btn.disabled=true;
    var dados={id:document.getElementById('editId').value,nome:document.getElementById('editNome').value.trim(),cor_menu_fundo:document.getElementById('edit-cor_menu_fundo').value,css_customizado:aceEditor?aceEditor.getValue():''};
    camposCores.forEach(function(c){dados[c]=document.getElementById('text-'+c).value||document.getElementById('color-'+c).value});
    if(!dados.nome){showToast('Digite um nome!','error');btn.innerHTML='<i class="bx bx-save"></i> Salvar Tema';btn.disabled=false;return}
    var action=dados.id?'salvar':'criar';
    fetch('../AegisCore/api_temas.php?action='+action,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(dados)})
    .then(function(r){return r.json()})
    .then(function(data){if(data.success){showToast(data.message,'success');closeEditor();setTimeout(function(){location.reload()},900)}else showToast(data.message||'Erro','error')})
    .catch(function(){showToast('Erro de conexão','error')})
    .finally(function(){btn.innerHTML='<i class="bx bx-save"></i> Salvar Tema';btn.disabled=false});
}

// ATIVAR
function activateTheme(id,e){
    if(e)e.stopPropagation();
    fetch('../AegisCore/api_temas.php?action=ativar',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:id})})
    .then(function(r){return r.json()})
    .then(function(data){if(data.success){showToast('Tema ativado!','success');setTimeout(function(){location.reload()},1200)}else showToast(data.message,'error')});
}

// EXPORTAR
function exportTheme(id,nome,e){
    if(e)e.stopPropagation();
    var link=document.createElement('a');
    link.href='../AegisCore/api_temas.php?action=exportar&id='+id;
    link.download='tema_'+nome.replace(/[^a-z0-9]/gi,'_')+'.json';
    link.click();showToast('Exportando...','success');
}

// EXCLUIR
function deleteTheme(id,nome,e){
    if(e)e.stopPropagation();
    pendingDeleteId=id;
    document.getElementById('deleteNome').textContent=nome;
    abrirModal('deleteModal');
}
function confirmDelete(){
    if(!pendingDeleteId)return;
    fetch('../AegisCore/api_temas.php?action=excluir',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:pendingDeleteId})})
    .then(function(r){return r.json()})
    .then(function(data){
        fecharModal('deleteModal');
        if(data.success){showToast(data.message,'success');var card=document.getElementById('card-'+pendingDeleteId);if(card){card.style.opacity='0';card.style.transform='scale(.9)';setTimeout(function(){card.remove()},300)}}
        else showToast(data.message,'error');
        pendingDeleteId=null;
    });
}

// IMPORTAR
function handleFile(input){
    var file=input.files[0];
    if(!file||!file.name.endsWith('.json')){showToast('Apenas .json!','error');return}
    var reader=new FileReader();
    reader.onload=function(e){try{importData=JSON.parse(e.target.result);showImportPreview(importData,file.name)}catch(err){showToast('JSON inválido!','error')}};
    reader.readAsText(file);
}
function dragOver(e){e.preventDefault();document.getElementById('importZone').classList.add('drag')}
function dragLeave(e){document.getElementById('importZone').classList.remove('drag')}
function dropFile(e){e.preventDefault();document.getElementById('importZone').classList.remove('drag');var file=e.dataTransfer.files[0];if(file){var dt=new DataTransfer();dt.items.add(file);document.getElementById('jsonFile').files=dt.files;handleFile(document.getElementById('jsonFile'))}}
function showImportPreview(dados,filename){
    document.getElementById('preview-nome').textContent=dados.nome||filename;
    var cols=[dados.cor_primaria,dados.cor_secundaria,dados.cor_terciaria,dados.cor_fundo,dados.cor_sucesso];
    document.getElementById('preview-colors').innerHTML=cols.filter(Boolean).map(function(c){return '<span style="display:inline-block;width:36px;height:14px;border-radius:6px;background:'+c+'"></span>'}).join('');
    document.getElementById('importPreview').style.display='block';
    document.getElementById('importIcon').className='bx bx-check-circle';
    document.getElementById('importIcon').style.color='#34d399';
    document.getElementById('importText').innerHTML='<strong style="color:#34d399">'+(dados.nome||'Tema')+'</strong> — pronto';
}
function clearImport(){
    importData=null;document.getElementById('jsonFile').value='';
    document.getElementById('importPreview').style.display='none';
    document.getElementById('importIcon').className='bx bx-cloud-upload';
    document.getElementById('importIcon').style.color='';
    document.getElementById('importText').innerHTML='Clique ou <strong>arraste um .json</strong> para importar';
}
function importTheme(){
    if(!importData){showToast('Nenhum arquivo!','error');return}
    var formData=new FormData();
    var blob=new Blob([JSON.stringify(importData)],{type:'application/json'});
    formData.append('arquivo_json',blob,(importData.nome||'tema')+'.json');
    fetch('../AegisCore/api_temas.php?action=importar',{method:'POST',body:formData})
    .then(function(r){return r.json()})
    .then(function(data){if(data.success){showToast(data.message,'success');clearImport();setTimeout(function(){location.reload()},1200)}else showToast(data.message,'error')})
    .catch(function(){showToast('Erro ao importar','error')});
}
</script>
</body>
</html>

