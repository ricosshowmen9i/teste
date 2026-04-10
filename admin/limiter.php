<?php // @ioncube.dk $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf -> "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs" RANDOM
    $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf = "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs";
    function aleatorio_limiter($input)
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
    $temaAtual  = initTemas($conn);
} else {
    $temaAtual  = [];
}

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

$slq = "SELECT * FROM limiter";
$result = mysqli_query($conn, $slq);
$total_limiter = $result ? mysqli_num_rows($result) : 0;

// Stats extras
$total_suspensos_limite = 0;
$total_ativos_limite = 0;
$dados_limiter = [];

if ($total_limiter > 0) {
    while ($rl = mysqli_fetch_assoc($result)) {
        $usuario = $rl['usuario'];
        $tempo = $rl['tempo'];

        $r_user = mysqli_query($conn, "SELECT * FROM ssh_accounts WHERE login='$usuario'");
        $user_data = $r_user ? mysqli_fetch_assoc($r_user) : null;
        if (!$user_data) continue;

        $byid = $user_data['byid'];
        $limite = $user_data['limite'];
        $senha = $user_data['senha'];
        $expira = $user_data['expira'];
        $uuid = $user_data['uuid'];
        $status = $user_data['status'];
        $id_user = $user_data['id'];
        $categoria = $user_data['categoriaid'];

        $r_owner = mysqli_query($conn, "SELECT login FROM accounts WHERE id='$byid'");
        $owner_data = $r_owner ? mysqli_fetch_assoc($r_owner) : null;
        $dono = $owner_data['login'] ?? 'N/A';

        $r_online = mysqli_query($conn, "SELECT quantidade FROM onlines WHERE usuario='$usuario'");
        $online_data = $r_online ? mysqli_fetch_assoc($r_online) : null;
        $quantidade = $online_data['quantidade'] ?? 0;

        $r_cat = mysqli_query($conn, "SELECT nome FROM categorias WHERE subid='$categoria'");
        $cat_nome = ($r_cat && mysqli_num_rows($r_cat) > 0) ? mysqli_fetch_assoc($r_cat)['nome'] : $categoria;

        if ($tempo == 'Deletado') $total_suspensos_limite++;
        else $total_ativos_limite++;

        $excedido = max(0, $quantidade - $limite);
        $pct = $limite > 0 ? round(($quantidade / $limite) * 100) : 0;

        $dados_limiter[] = [
            'id' => $id_user,
            'usuario' => $usuario,
            'senha' => $senha,
            'limite' => $limite,
            'quantidade' => $quantidade,
            'excedido' => $excedido,
            'pct' => $pct,
            'dono' => $dono,
            'tempo' => $tempo,
            'expira' => $expira,
            'cat_nome' => $cat_nome,
            'status' => $status,
            'uuid' => $uuid,
        ];
    }
}

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
<title>Limite Ultrapassado</title>
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
.stats-card{background:linear-gradient(135deg,var(--fundo_claro,#1e293b),var(--fundo,#0f172a));border-radius:20px;padding:20px 24px;margin-bottom:20px;border:1px solid rgba(245,158,11,0.15);display:flex;align-items:center;gap:20px;position:relative;overflow:hidden;transition:all .3s;}
.stats-card:hover{transform:translateY(-2px);border-color:#f59e0b;}
.stats-card-icon{width:60px;height:60px;background:linear-gradient(135deg,#f59e0b,#ef4444);border-radius:18px;display:flex;align-items:center;justify-content:center;font-size:32px;color:white;flex-shrink:0;position:relative;}
.stats-card-icon::after{content:'';position:absolute;inset:-3px;border-radius:21px;background:linear-gradient(135deg,#f59e0b,#ef4444);opacity:0.3;animation:warnPulse 2s ease-in-out infinite;z-index:-1;}
@keyframes warnPulse{0%,100%{opacity:.2;transform:scale(1);}50%{opacity:.4;transform:scale(1.05);}}
.stats-card-content{flex:1;}
.stats-card-title{font-size:13px;font-weight:600;color:rgba(255,255,255,0.5);text-transform:uppercase;margin-bottom:5px;}
.stats-card-value{font-size:36px;font-weight:800;background:linear-gradient(135deg,#fbbf24,#ef4444);-webkit-background-clip:text;-webkit-text-fill-color:transparent;line-height:1;}
.stats-card-subtitle{font-size:12px;color:rgba(255,255,255,0.4);margin-top:4px;display:flex;align-items:center;gap:6px;}
.warn-icon{color:#fbbf24;font-size:14px;animation:warnBlink 1.5s infinite;}
@keyframes warnBlink{0%,100%{opacity:1;}50%{opacity:.4;}}
.stats-card-decoration{position:absolute;right:20px;top:50%;transform:translateY(-50%);font-size:80px;opacity:0.05;}

/* Mini Stats */
.mini-stats{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;}
.mini-stat{flex:1;min-width:80px;background:rgba(255,255,255,0.04);border-radius:12px;padding:12px;border:1px solid rgba(255,255,255,0.06);text-align:center;transition:all .2s;}
.mini-stat:hover{border-color:#f59e0b;transform:translateY(-2px);}
.mini-stat-val{font-size:18px;font-weight:800;}
.mini-stat-lbl{font-size:9px;color:rgba(255,255,255,0.35);text-transform:uppercase;margin-top:2px;}

/* Modern Card */
.modern-card{background:var(--fundo_claro,#1e293b);border-radius:16px;border:1px solid rgba(255,255,255,0.08);overflow:hidden;margin-bottom:16px;transition:all .2s;}
.modern-card:hover{border-color:#f59e0b;}
.card-header-custom{padding:14px 18px;display:flex;align-items:center;gap:12px;}
.card-header-custom.orange{background:linear-gradient(135deg,#f59e0b,#f97316);}
.header-icon{width:36px;height:36px;background:rgba(255,255,255,0.2);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;color:white;}
.header-title{font-size:14px;font-weight:700;color:white;}
.header-subtitle{font-size:10px;color:rgba(255,255,255,0.7);}
.card-body-custom{padding:16px;}

/* Filters */
.filter-group{display:flex;gap:12px;flex-wrap:wrap;}
.filter-item{flex:1;min-width:130px;}
.filter-label{font-size:9px;font-weight:700;color:rgba(255,255,255,0.4);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;}
.form-control{width:100%;padding:8px 12px;background:rgba(255,255,255,0.06);border:1.5px solid rgba(255,255,255,0.08);border-radius:9px;color:#fff;font-size:12px;font-family:inherit;outline:none;transition:all .25s;}
.form-control:focus{border-color:#f59e0b;background:rgba(255,255,255,0.09);}
.form-control::placeholder{color:rgba(255,255,255,0.2);}
select.form-control{cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%23ffffff' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;}
select.form-control option{background:#1e293b;color:#fff;}

/* Grid */
.users-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px;}

/* Card */
.user-card{background:var(--fundo_claro,#1e293b);border-radius:14px;overflow:hidden;border:1px solid rgba(245,158,11,0.15);transition:all .2s;position:relative;}
.user-card:hover{transform:translateY(-2px);border-color:#f59e0b;box-shadow:0 4px 20px rgba(245,158,11,0.1);}
.user-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,#f59e0b,#ef4444,#f59e0b);background-size:200% 100%;animation:shimmerWarn 3s linear infinite;}
@keyframes shimmerWarn{0%{background-position:200% 0;}100%{background-position:-200% 0;}}

.user-header{background:linear-gradient(135deg,#f59e0b,#ef4444);padding:12px;display:flex;align-items:center;justify-content:space-between;}
.user-info{display:flex;align-items:center;gap:10px;flex:1;min-width:0;}
.user-avatar{width:36px;height:36px;background:rgba(255,255,255,0.2);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;position:relative;}
.warn-dot{position:absolute;top:-2px;right:-2px;width:10px;height:10px;background:#ef4444;border-radius:50%;border:2px solid #f59e0b;animation:warnBlink 1.5s infinite;}
.user-text{flex:1;min-width:0;}
.user-name{font-size:14px;font-weight:700;color:white;display:flex;align-items:center;gap:5px;word-break:break-all;}
.limit-badge{background:rgba(255,255,255,0.25);padding:2px 6px;border-radius:20px;font-size:7px;font-weight:700;letter-spacing:.5px;}
.user-senha{font-size:10px;color:rgba(255,255,255,0.7);margin-top:2px;display:flex;align-items:center;gap:4px;}
.btn-copy-card{background:rgba(255,255,255,0.15);border:none;border-radius:8px;padding:6px 10px;font-size:11px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:4px;color:white;flex-shrink:0;transition:all .2s;}
.btn-copy-card:hover{background:rgba(255,255,255,0.25);}
.btn-copy-card.copied{background:#10b981;}

.user-body{padding:12px;}

/* Barra de excedido */
.exceed-bar{display:flex;align-items:center;gap:10px;padding:10px 12px;background:rgba(239,68,68,0.08);border-radius:10px;margin-bottom:10px;border:1px solid rgba(239,68,68,0.15);}
.exceed-icon{width:36px;height:36px;border-radius:10px;background:rgba(239,68,68,0.15);color:#f87171;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;}
.exceed-info{flex:1;}
.exceed-label{font-size:8px;color:rgba(255,255,255,0.4);font-weight:600;text-transform:uppercase;}
.exceed-value{font-size:16px;font-weight:800;color:#f87171;}
.exceed-extra{font-size:10px;color:#fbbf24;font-weight:600;margin-top:1px;}
.exceed-progress{height:5px;background:rgba(255,255,255,0.08);border-radius:10px;overflow:hidden;margin-top:4px;}
.exceed-fill{height:100%;border-radius:10px;transition:width .6s ease;}

/* Status chip */
.status-chip{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:16px;font-size:9px;font-weight:600;}
.chip-suspended{background:rgba(239,68,68,0.15);color:#f87171;border:1px solid rgba(239,68,68,0.2);}
.chip-active{background:rgba(245,158,11,0.15);color:#fbbf24;border:1px solid rgba(245,158,11,0.2);}
.chip-online{background:rgba(16,185,129,0.15);color:#34d399;border:1px solid rgba(16,185,129,0.2);}

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
.btn-reactivate{background:linear-gradient(135deg,#10b981,#059669);}
.btn-device{background:linear-gradient(135deg,#8b5cf6,#7c3aed);}
.btn-danger{background:linear-gradient(135deg,#dc2626,#b91c1c);}

/* Pagination info */
.pagination-info{text-align:center;margin-top:16px;color:rgba(255,255,255,0.3);font-size:10px;}

/* Empty */
.empty-state{grid-column:1/-1;text-align:center;padding:50px 20px;background:var(--fundo_claro,#1e293b);border-radius:16px;border:1px solid rgba(255,255,255,0.08);}
.empty-state-icon{width:80px;height:80px;border-radius:50%;background:rgba(16,185,129,0.1);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:40px;color:#34d399;border:2px solid rgba(16,185,129,0.2);}
.empty-state h3{font-size:16px;margin-bottom:6px;color:#34d399;}
.empty-state p{font-size:11px;color:rgba(255,255,255,0.3);}

/* Modal */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.85);display:none;align-items:center;justify-content:center;z-index:9999;backdrop-filter:blur(8px);padding:16px;}
.modal-overlay.show{display:flex;}
.modal-container{animation:modalIn .3s ease;max-width:450px;width:92%;}
@keyframes modalIn{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
.modal-content-custom{background:var(--fundo_claro,#1e293b);border-radius:20px;overflow:hidden;border:1px solid rgba(255,255,255,.1);box-shadow:0 25px 60px rgba(0,0,0,.5);}
.modal-header-custom{padding:14px 18px;display:flex;align-items:center;justify-content:space-between;}
.modal-header-custom h5{margin:0;display:flex;align-items:center;gap:8px;font-size:14px;font-weight:700;color:#fff;}
.modal-header-custom.warning{background:linear-gradient(135deg,#f59e0b,#f97316);}
.modal-header-custom.error{background:linear-gradient(135deg,#dc2626,#b91c1c);}
.modal-header-custom.info{background:linear-gradient(135deg,#06b6d4,#0891b2);}
.modal-header-custom.processing{background:linear-gradient(135deg,#f59e0b,#ef4444);}
.modal-close{background:rgba(255,255,255,.15);border:none;color:#fff;font-size:18px;cursor:pointer;width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;transition:all .2s;}
.modal-close:hover{background:rgba(255,255,255,.25);transform:rotate(90deg);}
.modal-body-custom{padding:18px;}
.modal-footer-custom{border-top:1px solid rgba(255,255,255,.07);padding:12px 18px;display:flex;justify-content:center;gap:8px;flex-wrap:wrap;}

.modal-ic{width:70px;height:70px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:34px;animation:icPop .5s cubic-bezier(0.34,1.56,0.64,1) .15s both;}
@keyframes icPop{0%{transform:scale(0);opacity:0}100%{transform:scale(1);opacity:1}}
.modal-ic.warning{background:rgba(245,158,11,.15);color:#fbbf24;border:2px solid rgba(245,158,11,.3);}
.modal-ic.error{background:rgba(239,68,68,.15);color:#f87171;border:2px solid rgba(239,68,68,.3);}
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
.btn-modal-warning{background:linear-gradient(135deg,#f59e0b,#f97316);}
.btn-modal-danger{background:linear-gradient(135deg,#dc2626,#b91c1c);}
.btn-modal-ok{background:linear-gradient(135deg,#10b981,#059669);}
.btn-modal-info{background:linear-gradient(135deg,#06b6d4,#0891b2);}

.spinner-wrap{display:flex;flex-direction:column;align-items:center;gap:14px;padding:20px 0;}
.spinner-ring{width:44px;height:44px;border:3px solid rgba(255,255,255,.08);border-top-color:#f59e0b;border-right-color:#ef4444;border-radius:50%;animation:spin .8s linear infinite;}
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

    <!-- Stats -->
    <div class="stats-card">
        <div class="stats-card-icon"><i class='bx bx-error-circle'></i></div>
        <div class="stats-card-content">
            <div class="stats-card-title">Limite Ultrapassado</div>
            <div class="stats-card-value"><?php echo $total_limiter; ?></div>
            <div class="stats-card-subtitle"><i class='bx bx-error warn-icon'></i> usuários com conexões acima do permitido</div>
        </div>
        <div class="stats-card-decoration"><i class='bx bx-error-circle'></i></div>
    </div>

    <!-- Mini Stats -->
    <div class="mini-stats">
        <div class="mini-stat"><div class="mini-stat-val" style="color:#fbbf24;"><?php echo $total_limiter; ?></div><div class="mini-stat-lbl">Total</div></div>
        <div class="mini-stat"><div class="mini-stat-val" style="color:#fb923c;"><?php echo $total_ativos_limite; ?></div><div class="mini-stat-lbl">Ativos</div></div>
        <div class="mini-stat"><div class="mini-stat-val" style="color:#f87171;"><?php echo $total_suspensos_limite; ?></div><div class="mini-stat-lbl">Suspensos</div></div>
    </div>

    <!-- Filtros -->
    <div class="modern-card">
        <div class="card-header-custom orange">
            <div class="header-icon"><i class='bx bx-filter-alt'></i></div>
            <div><div class="header-title">Filtros</div><div class="header-subtitle">Busque usuários com limite excedido</div></div>
        </div>
        <div class="card-body-custom">
            <div class="filter-group">
                <div class="filter-item">
                    <div class="filter-label">Buscar por Usuário</div>
                    <input type="text" class="form-control" id="searchInput" placeholder="Digite o nome..." onkeyup="filtrarUsuarios()">
                </div>
                <div class="filter-item" style="max-width:140px;">
                    <div class="filter-label">Filtrar Status</div>
                    <select class="form-control" id="statusFilter" onchange="filtrarUsuarios()">
                        <option value="todos">Todos</option>
                        <option value="ativo">⚠️ Ativos</option>
                        <option value="suspenso">🔒 Suspensos</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Grid -->
    <div class="users-grid" id="usersGrid">
    <?php if(count($dados_limiter)>0): foreach($dados_limiter as $d):
        $is_suspenso = ($d['tempo'] == 'Deletado');
        $fill_color = $d['pct'] >= 150 ? '#ef4444' : ($d['pct'] >= 120 ? '#f97316' : '#fbbf24');
        $expira_fmt = date('d/m/Y', strtotime($d['expira']));
        $diff = strtotime($d['expira']) - time();
        $dias_rest = floor($diff / 86400);
        $val_class = $dias_rest < 0 ? 'danger' : ($dias_rest <= 5 ? 'warning' : '');
    ?>
    <div class="user-card" data-login="<?php echo strtolower(htmlspecialchars($d['usuario']));?>" data-status="<?php echo $is_suspenso?'suspenso':'ativo';?>" data-id="<?php echo $d['id'];?>" data-usuario="<?php echo htmlspecialchars($d['usuario']);?>" data-senha="<?php echo htmlspecialchars($d['senha']);?>" data-limite="<?php echo $d['limite'];?>" data-usando="<?php echo $d['quantidade'];?>" data-excedido="<?php echo $d['excedido'];?>" data-dono="<?php echo htmlspecialchars($d['dono']);?>">
        <div class="user-header">
            <div class="user-info">
                <div class="user-avatar"><i class='bx bx-error'></i><span class="warn-dot"></span></div>
                <div class="user-text">
                    <div class="user-name"><?php echo htmlspecialchars($d['usuario']);?> <span class="limit-badge">EXCEDIDO</span></div>
                    <div class="user-senha"><i class='bx bx-lock-alt'></i> <?php echo htmlspecialchars($d['senha']);?></div>
                </div>
            </div>
            <button class="btn-copy-card" onclick="copiar(this)"><i class='bx bx-copy'></i> Copiar</button>
        </div>
        <div class="user-body">
            <!-- Barra de excedido -->
            <div class="exceed-bar">
                <div class="exceed-icon"><i class='bx bx-error-circle'></i></div>
                <div class="exceed-info">
                    <div class="exceed-label">Conexões Ativas vs Limite</div>
                    <div class="exceed-value"><?php echo $d['quantidade'];?> / <?php echo $d['limite'];?></div>
                    <div class="exceed-extra">+<?php echo $d['excedido'];?> conexões acima do limite (<?php echo $d['pct'];?>%)</div>
                    <div class="exceed-progress"><div class="exceed-fill" style="width:<?php echo min(100,$d['pct']);?>%;background:<?php echo $fill_color;?>;"></div></div>
                </div>
            </div>

            <div class="info-grid">
                <div class="info-row"><div class="info-icon"><i class='bx bx-store' style="color:#f472b6;"></i></div><div class="info-content"><div class="info-label">REVENDEDOR</div><div class="info-value"><?php echo htmlspecialchars($d['dono']);?></div></div></div>
                <div class="info-row"><div class="info-icon"><i class='bx bx-info-circle' style="color:#fbbf24;"></i></div><div class="info-content"><div class="info-label">STATUS</div><div class="info-value"><?php
                    if($is_suspenso) echo '<span class="status-chip chip-suspended"><i class="bx bx-lock"></i> Suspenso</span>';
                    elseif($d['status']=='Online') echo '<span class="status-chip chip-online"><i class="bx bx-wifi"></i> Online</span>';
                    else echo '<span class="status-chip chip-active"><i class="bx bx-error"></i> Limite</span>';
                ?></div></div></div>
                <div class="info-row"><div class="info-icon"><i class='bx bx-calendar-check' style="color:#fbbf24;"></i></div><div class="info-content"><div class="info-label">EXPIRA</div><div class="info-value <?php echo $val_class;?>"><?php echo $expira_fmt;?></div></div></div>
                <div class="info-row"><div class="info-icon"><i class='bx bx-category' style="color:#60a5fa;"></i></div><div class="info-content"><div class="info-label">CATEGORIA</div><div class="info-value"><?php echo htmlspecialchars($d['cat_nome']);?></div></div></div>
            </div>

            <div class="user-actions">
                <button class="action-btn btn-edit" onclick="window.location.href='editarlogin.php?id=<?php echo $d['id'];?>'"><i class='bx bx-edit'></i> Editar</button>
                <?php if($is_suspenso):?>
                <button class="action-btn btn-reactivate" onclick="reativar(<?php echo $d['id'];?>)"><i class='bx bx-check-circle'></i> Ativar</button>
                <?php else:?>
                <button class="action-btn btn-warn" onclick="suspender(<?php echo $d['id'];?>)"><i class='bx bx-lock'></i> Suspender</button>
                <?php endif;?>
                <?php if($deviceativo=='ativo'||$deviceativo=='1'):?>
                <button class="action-btn btn-device" onclick="limparDevice(<?php echo $d['id'];?>)"><i class='bx bx-devices'></i> Device</button>
                <?php endif;?>
                <button class="action-btn btn-danger" onclick="excluir(<?php echo $d['id'];?>)"><i class='bx bx-trash'></i> Deletar</button>
            </div>
        </div>
    </div>
    <?php endforeach; else: ?>
    <div class="empty-state">
        <div class="empty-state-icon"><i class='bx bx-check-circle'></i></div>
        <h3>Tudo certo!</h3>
        <p>Nenhum usuário com limite ultrapassado no momento.</p>
    </div>
    <?php endif; ?>
    </div>

    <div class="pagination-info">Total: <?php echo $total_limiter;?> usuário(s) com limite ultrapassado — <?php echo date('d/m/Y H:i');?></div>

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

<!-- Reativar -->
<div id="modalReativar" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
    <div class="modal-header-custom info"><h5><i class='bx bx-check-circle'></i> Reativar Usuário</h5><button class="modal-close" onclick="fecharModal('modalReativar')"><i class='bx bx-x'></i></button></div>
    <div class="modal-body-custom">
        <div class="modal-ic info"><i class='bx bx-check-circle'></i></div>
        <div class="modal-user-info" id="reativarInfo"></div>
    </div>
    <div class="modal-footer-custom">
        <button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalReativar')"><i class='bx bx-x'></i> Cancelar</button>
        <button class="btn-modal btn-modal-ok" onclick="confirmarReativar()"><i class='bx bx-check'></i> Reativar</button>
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
    <div class="modal-header-custom warning"><h5><i class='bx bx-devices'></i> Limpar Device ID</h5><button class="modal-close" onclick="fecharModal('modalDevice')"><i class='bx bx-x'></i></button></div>
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
        +'<div class="modal-user-row"><i class="bx bx-error-circle" style="color:#f87171;"></i> <span>Conexões:</span> <strong style="color:#f87171;">'+(card.dataset.usando||0)+'/'+(card.dataset.limite||0)+' (+'+( card.dataset.excedido||0)+' excedido)</strong></div>'
        +'<div class="modal-user-row"><i class="bx bx-store" style="color:#f472b6;"></i> <span>Revendedor:</span> <strong>'+(card.dataset.dono||'N/A')+'</strong></div>';
}

// Filtrar
function filtrarUsuarios(){
    var busca=document.getElementById('searchInput').value.toLowerCase();
    var status=document.getElementById('statusFilter').value;
    document.querySelectorAll('.user-card').forEach(function(c){
        var login=c.getAttribute('data-login')||'';
        var st=c.getAttribute('data-status')||'';
        var mb=login.includes(busca);
        var ms=true;
        if(status==='ativo')ms=st==='ativo';
        else if(status==='suspenso')ms=st==='suspenso';
        c.style.display=(mb&&ms)?'':'none';
    });
}

// Copiar
function copiar(btn){
    var card=btn.closest('.user-card');
    var texto='⚠️ LIMITE ULTRAPASSADO\n━━━━━━━━━━━━━━━━\n👤 Login: '+card.dataset.usuario+'\n🔑 Senha: '+card.dataset.senha+'\n📡 Conexões: '+card.dataset.usando+'/'+card.dataset.limite+' (+'+card.dataset.excedido+' excedido)\n🏪 Revendedor: '+(card.dataset.dono||'N/A')+'\n━━━━━━━━━━━━━━━━';
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

// REATIVAR → reativar.php?id=X GET
function reativar(id){
    _id=id;_card=getCard(id);
    document.getElementById('reativarInfo').innerHTML=userInfoHTML(_card);
    abrirModal('modalReativar');
}
function confirmarReativar(){
    fecharModal('modalReativar');
    document.getElementById('processandoTexto').textContent='Reativando '+(_card?_card.dataset.usuario:'')+'...';
    abrirModal('modalProcessando');
    ajaxGET('reativar.php?id='+_id,function(resp){
        if(respOk(resp,['reativado','sucesso'])){toast('Usuário reativado!','ok');setTimeout(function(){location.reload();},1500);}
        else{toast('Erro ao reativar!','err');}
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
<?php
    }
    aleatorio_limiter($kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf);
?>

