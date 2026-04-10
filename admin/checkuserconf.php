<?php // @ioncube.dk $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf -> "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs" RANDOM
    $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf = "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs";
    function aleatorio377360($input)
    {
        ?>

<?php
error_reporting(0);
session_start();
if(!isset($_SESSION['login']) and !isset($_SESSION['senha'])){
    session_destroy(); header('location:../index.php'); exit;
}
include('../AegisCore/conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) die("Connection failed: " . mysqli_connect_error());
include_once 'headeradmin2.php';

// ========== INCLUIR SISTEMA DE TEMAS ==========
if(file_exists('../AegisCore/temas.php')){
    include_once '../AegisCore/temas.php';
    $temaAtual = initTemas($conn);
} else { $temaAtual = []; }

// Buscar config device
$sql_cfg = "SELECT * FROM configs WHERE id = 1";
$result_cfg = $conn->query($sql_cfg);
$config_row = $result_cfg->fetch_assoc();
$deviceativo = $config_row['deviceativo'] ?? 0;

$server = $_SERVER['SERVER_NAME'];

// Links
$links = [
    ['nome'=>'AnyVpn','icone'=>'bx-globe','cor'=>'#3b82f6','url'=>"https://$server/checkuser",'protocolo'=>'HTTPS'],
    ['nome'=>'Conecta4g','icone'=>'bx-signal-5','cor'=>'#10b981','url'=>"http://$server/checkuser/conecta4g.php",'protocolo'=>'HTTP'],
    ['nome'=>'GLMOD','icone'=>'bx-game','cor'=>'#8b5cf6','url'=>"http://$server/checkuser/glmod.php?user=",'protocolo'=>'HTTP'],
    ['nome'=>'Dtunnel','icone'=>'bx-tunnel','cor'=>'#f59e0b','url'=>"https://$server/checkuser/dtunnel.php?user=",'protocolo'=>'HTTPS'],
    ['nome'=>'Dtunnel V2Ray','icone'=>'bx-rocket','cor'=>'#ec4899','url'=>"https://$server/checkuser/dtunnelv2ray.php?user=",'protocolo'=>'HTTPS'],
    ['nome'=>'Studio / M2','icone'=>'bx-movie','cor'=>'#06b6d4','url'=>"/checkuser/atlant.php",'protocolo'=>'PATH'],
    ['nome'=>'Miracle','icone'=>'bx-star','cor'=>'#f97316','url'=>"https://$server/checkuser",'protocolo'=>'HTTPS'],
    ['nome'=>'Atlas App','icone'=>'bx-planet','cor'=>'#818cf8','url'=>"https://$server",'protocolo'=>'HTTPS'],
    ['nome'=>'Rocket','icone'=>'bx-send','cor'=>'#ef4444','url'=>"https://$server/checkuser/rocket.php",'protocolo'=>'HTTPS'],
];

function anti_sql($input){
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function($m){ return ''; }, $input);
    return addslashes(strip_tags(trim($seg)));
}

$msg_sucesso = ''; $msg_erro = ''; $show_modal = false; $modal_type = '';

if (isset($_POST['salvar'])) {
    $deviceativo_new = anti_sql($_POST['deviceativo']);
    $sql = "UPDATE configs SET deviceativo = '$deviceativo_new' WHERE id = 1";
    if ($conn->query($sql)) {
        $msg_sucesso = "Configurações atualizadas com sucesso!"; $modal_type = 'success'; $show_modal = true;
        $deviceativo = $deviceativo_new;
    } else {
        $msg_erro = "Erro ao salvar!"; $modal_type = 'error'; $show_modal = true;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>CheckUser - Links</title>
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
.stats-card-right{display:flex;gap:8px;flex-shrink:0;flex-wrap:wrap}

/* MINI STATS */
.mini-stats{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px}
.mini-stat{flex:1;min-width:90px;background:rgba(255,255,255,.04);border-radius:12px;padding:12px;border:1px solid rgba(255,255,255,.06);text-align:center;transition:all .2s}
.mini-stat:hover{border-color:var(--primaria,#10b981);transform:translateY(-2px)}
.mini-stat-ic{font-size:20px;margin-bottom:4px}
.mini-stat-val{font-size:18px;font-weight:800}
.mini-stat-lbl{font-size:9px;color:rgba(255,255,255,.35);text-transform:uppercase;margin-top:2px}

/* MODERN CARD */
.modern-card{background:var(--fundo_claro,#1e293b);border-radius:16px;border:1px solid rgba(255,255,255,.08);overflow:hidden;margin-bottom:16px;transition:all .2s}
.modern-card:hover{border-color:var(--primaria,#10b981)}
.card-header-custom{padding:14px 18px;display:flex;align-items:center;gap:12px}
.card-header-custom.blue{background:linear-gradient(135deg,#3b82f6,#2563eb)}
.card-header-custom.purple{background:linear-gradient(135deg,#8b5cf6,#7c3aed)}
.card-header-custom.orange{background:linear-gradient(135deg,#f59e0b,#f97316)}
.card-header-custom.green{background:linear-gradient(135deg,#10b981,#059669)}
.card-header-custom.red{background:linear-gradient(135deg,#dc2626,#b91c1c)}
.card-header-custom.teal{background:linear-gradient(135deg,#06b6d4,#0891b2)}
.card-header-custom.gradient{background:linear-gradient(135deg,var(--primaria,#10b981),var(--secundaria,#C850C0))}
.header-icon{width:36px;height:36px;background:rgba(255,255,255,.2);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;color:#fff}
.header-title{font-size:14px;font-weight:700;color:#fff}
.header-subtitle{font-size:10px;color:rgba(255,255,255,.7)}
.card-body-custom{padding:16px}

/* LINKS GRID */
.links-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:14px}

/* LINK CARD */
.link-card{background:var(--fundo_claro,#1e293b);border-radius:14px;overflow:hidden;transition:all .2s;border:1px solid rgba(255,255,255,.08)}
.link-card:hover{transform:translateY(-2px);border-color:var(--primaria,#10b981)}
.link-card-header{padding:10px 14px;display:flex;align-items:center;gap:10px}
.link-icon{width:34px;height:34px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;color:#fff;flex-shrink:0}
.link-name{font-size:13px;font-weight:700;color:#fff}
.link-proto{font-size:8px;font-weight:700;padding:2px 6px;border-radius:20px;background:rgba(255,255,255,.15);color:#fff;text-transform:uppercase;letter-spacing:.3px;margin-left:auto;flex-shrink:0}
.link-card-body{padding:8px 14px 12px}
.link-url-wrap{display:flex;align-items:center;gap:6px}
.link-url{flex:1;padding:7px 10px;background:rgba(0,0,0,.2);border:1px solid rgba(255,255,255,.06);border-radius:8px;font-size:10px;color:#f87171;font-family:'JetBrains Mono','Fira Code',monospace;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;user-select:all}
.btn-copy{width:30px;height:30px;border:none;border-radius:8px;background:rgba(255,255,255,.06);color:rgba(255,255,255,.5);font-size:14px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .2s;flex-shrink:0}
.btn-copy:hover{background:var(--primaria,#10b981);color:#fff;transform:scale(1.05)}
.btn-copy.copied{background:#10b981;color:#fff}

/* ALERT BOX */
.alert-box{background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.2);border-radius:12px;padding:14px;margin-bottom:16px;display:flex;align-items:flex-start;gap:10px}
.alert-box-icon{width:32px;height:32px;background:rgba(245,158,11,.15);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:16px;color:#fbbf24;flex-shrink:0}
.alert-box-text{font-size:11px;color:rgba(255,255,255,.6);line-height:1.6}
.alert-box-text strong{color:#fbbf24}

/* TOGGLE CARDS */
.toggle-group{display:flex;gap:8px;flex-wrap:wrap}
.toggle-card{flex:1;min-width:130px;background:rgba(255,255,255,.04);border:1.5px solid rgba(255,255,255,.08);border-radius:12px;padding:12px;cursor:pointer;transition:all .2s;display:flex;align-items:center;gap:10px}
.toggle-card:hover{transform:translateY(-2px);border-color:rgba(255,255,255,.15)}
.toggle-card.active-on{border-color:#10b981;background:rgba(16,185,129,.08)}
.toggle-card.active-off{border-color:#f87171;background:rgba(248,113,113,.08)}
.toggle-icon{width:34px;height:34px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;color:#fff;flex-shrink:0}
.toggle-card.on .toggle-icon{background:linear-gradient(135deg,#10b981,#059669)}
.toggle-card.off .toggle-icon{background:linear-gradient(135deg,#ef4444,#dc2626)}
.toggle-title{font-size:12px;font-weight:700}
.toggle-card.on .toggle-title{color:#34d399}
.toggle-card.off .toggle-title{color:#f87171}
.toggle-desc{font-size:9px;color:rgba(255,255,255,.35);margin-top:1px}
.toggle-check{width:18px;height:18px;border-radius:50%;border:2px solid rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;font-size:10px;margin-left:auto;flex-shrink:0;transition:all .2s}
.toggle-card.active-on .toggle-check,.toggle-card.active-off .toggle-check{background:linear-gradient(135deg,var(--primaria,#10b981),var(--secundaria,#C850C0));border-color:transparent}
.toggle-card.active-on .toggle-check i,.toggle-card.active-off .toggle-check i{color:#fff}
.status-badge{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:20px;font-size:9px;font-weight:700;margin-top:6px}
.status-badge.on{background:rgba(16,185,129,.15);color:#34d399}
.status-badge.off{background:rgba(248,113,113,.15);color:#f87171}

/* BUTTONS */
.action-btn{padding:8px 16px;border:none;border-radius:10px;font-weight:700;font-size:11px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:6px;color:#fff;transition:all .2s;font-family:inherit}
.action-btn:hover{transform:translateY(-1px);filter:brightness(1.1)}
.action-btn i{font-size:14px}
.btn-save{background:linear-gradient(135deg,#10b981,#059669)}
.btn-back{background:linear-gradient(135deg,#f43f5e,#e11d48)}
.action-buttons{display:flex;justify-content:flex-end;gap:8px;margin-top:8px;flex-wrap:wrap}

/* IMAGE TUTORIAL */
.tutorial-img{max-width:100%;border-radius:10px;border:1px solid rgba(255,255,255,.08);margin-top:8px}

/* MODAL */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.85);display:none;align-items:center;justify-content:center;z-index:10000;backdrop-filter:blur(8px);padding:16px}
.modal-overlay.show{display:flex}
.modal-container{animation:modalIn .3s ease;max-width:420px;width:92%}
@keyframes modalIn{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
.modal-content-custom{background:var(--fundo_claro,#1e293b);border-radius:20px;overflow:hidden;border:1px solid rgba(255,255,255,.1);box-shadow:0 25px 60px rgba(0,0,0,.5)}
.modal-header-custom{padding:14px 18px;display:flex;align-items:center;justify-content:space-between}
.modal-header-custom h5{margin:0;display:flex;align-items:center;gap:8px;font-size:14px;font-weight:700;color:#fff}
.modal-header-custom.success{background:linear-gradient(135deg,#10b981,#059669)}
.modal-header-custom.error{background:linear-gradient(135deg,#dc2626,#b91c1c)}
.modal-close{background:rgba(255,255,255,.15);border:none;color:#fff;font-size:18px;cursor:pointer;width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;transition:all .2s}
.modal-close:hover{background:rgba(255,255,255,.25);transform:rotate(90deg)}
.modal-body-custom{padding:20px;text-align:center}
.modal-ic{width:70px;height:70px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:34px;animation:icPop .5s cubic-bezier(.34,1.56,.64,1) .15s both}
@keyframes icPop{0%{transform:scale(0);opacity:0}100%{transform:scale(1);opacity:1}}
.modal-ic.success{background:rgba(16,185,129,.15);color:#34d399;border:2px solid rgba(16,185,129,.3)}
.modal-ic.error{background:rgba(239,68,68,.15);color:#f87171;border:2px solid rgba(239,68,68,.3)}
.modal-footer-custom{border-top:1px solid rgba(255,255,255,.07);padding:12px 18px;display:flex;justify-content:center}
.btn-modal{padding:8px 16px;border:none;border-radius:10px;font-weight:600;font-size:12px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;color:#fff;transition:all .2s;font-family:inherit}
.btn-modal:hover{transform:translateY(-1px);filter:brightness(1.08)}
.btn-modal-ok{background:linear-gradient(135deg,#10b981,#059669)}
.btn-modal-danger{background:linear-gradient(135deg,#dc2626,#b91c1c)}

/* TOAST */
.toast-notification{position:fixed;bottom:20px;right:20px;color:#fff;padding:10px 16px;border-radius:10px;display:flex;align-items:center;gap:8px;z-index:10001;animation:toastIn .3s ease;font-weight:600;font-size:12px;box-shadow:0 8px 20px rgba(0,0,0,.3)}
.toast-notification.ok{background:linear-gradient(135deg,#10b981,#059669)}
@keyframes toastIn{from{transform:translateX(110%);opacity:0}to{transform:translateX(0);opacity:1}}

@media(max-width:768px){
    .content-wrapper{padding:10px!important}
    .links-grid{grid-template-columns:1fr}
    .stats-card{flex-wrap:wrap;padding:14px;gap:14px}
    .stats-card-icon{width:48px;height:48px;font-size:24px}
    .stats-card-value{font-size:28px}
    .stats-card-right{width:100%;justify-content:center}
    .mini-stats{flex-wrap:wrap}.mini-stat{min-width:70px}
    .toggle-group{flex-direction:column}
    .action-buttons{flex-direction:column}
    .action-btn{width:100%;justify-content:center}
}
</style>
</head>
<body class="<?php echo htmlspecialchars(getBodyClass($temaAtual ?? ['classe'=>'theme-dark'])); ?>">
<div class="app-content content">
<div class="content-overlay"></div>
<div class="content-wrapper">

<!-- STATS CARD -->
<div class="stats-card">
<div class="stats-card-icon"><i class='bx bx-link-alt'></i></div>
<div class="stats-card-content">
<div class="stats-card-title">CheckUser</div>
<div class="stats-card-value"><?php echo count($links);?></div>
<div class="stats-card-subtitle">links de integração disponíveis • <?php echo htmlspecialchars($server);?></div>
</div>
<div class="stats-card-right">
<a href="home.php" class="action-btn btn-back"><i class='bx bx-arrow-back'></i> Voltar</a>
</div>
<div class="stats-card-decoration"><i class='bx bx-link-alt'></i></div>
</div>

<!-- MINI STATS -->
<div class="mini-stats">
<div class="mini-stat"><div class="mini-stat-ic" style="color:#3b82f6"><i class='bx bx-link'></i></div><div class="mini-stat-val" style="color:#3b82f6"><?php echo count($links);?></div><div class="mini-stat-lbl">Links</div></div>
<div class="mini-stat"><div class="mini-stat-ic" style="color:#10b981"><i class='bx bx-lock-alt'></i></div><div class="mini-stat-val" style="color:#10b981">HTTPS</div><div class="mini-stat-lbl">Protocolo</div></div>
<div class="mini-stat"><div class="mini-stat-ic" style="color:<?php echo $deviceativo=='1'?'#34d399':'#fbbf24';?>"><i class='bx bx-devices'></i></div><div class="mini-stat-val" style="color:<?php echo $deviceativo=='1'?'#34d399':'#fbbf24';?>"><?php echo $deviceativo=='1'?'On':'Off';?></div><div class="mini-stat-lbl">Device ID</div></div>
<div class="mini-stat"><div class="mini-stat-ic" style="color:#e879f9"><i class='bx bx-server'></i></div><div class="mini-stat-val" style="color:#e879f9;font-size:11px"><?php echo htmlspecialchars($server);?></div><div class="mini-stat-lbl">Servidor</div></div>
</div>

<!-- AVISO SSL -->
<div class="modern-card">
<div class="card-header-custom orange">
<div class="header-icon"><i class='bx bx-error'></i></div>
<div><div class="header-title">Observação Importante</div><div class="header-subtitle">Configuração SSL necessária</div></div>
</div>
<div class="card-body-custom">
<div class="alert-box">
<div class="alert-box-icon"><i class='bx bx-shield-quarter'></i></div>
<div class="alert-box-text">
<strong>⚠️ ATENÇÃO:</strong> Entre em sua hospedagem, na seção <strong>SSL</strong>, defina para <strong>NÃO FORÇAR HTTPS</strong>. Isso é necessário para que os links HTTP funcionem corretamente com os aplicativos.
</div>
</div>

</div>
</div>

<!-- LINKS GRID -->
<div class="modern-card">
<div class="card-header-custom blue">
<div class="header-icon"><i class='bx bx-link-alt'></i></div>
<div><div class="header-title">Links do CheckUser</div><div class="header-subtitle">Clique no ícone para copiar o link</div></div>
</div>
<div class="card-body-custom">
<div class="links-grid">
<?php foreach($links as $i => $lk): ?>
<div class="link-card">
<div class="link-card-header" style="background:linear-gradient(135deg,<?php echo $lk['cor'];?>,<?php echo $lk['cor'];?>cc)">
<div class="link-icon" style="background:rgba(255,255,255,.2)"><i class='bx <?php echo $lk['icone'];?>'></i></div>
<div class="link-name"><?php echo $lk['nome'];?></div>
<span class="link-proto"><?php echo $lk['protocolo'];?></span>
</div>
<div class="link-card-body">
<div class="link-url-wrap">
<div class="link-url" id="link-<?php echo $i;?>"><?php echo htmlspecialchars($lk['url']);?></div>
<button class="btn-copy" onclick="copyLink(<?php echo $i;?>,this)" title="Copiar"><i class='bx bx-copy'></i></button>
</div>
</div>
</div>
<?php endforeach;?>
</div>
<!-- Studio M2 imagem -->

</div>
</div>

<!-- DEVICE ID -->
<div class="modern-card">
<div class="card-header-custom purple">
<div class="header-icon"><i class='bx bx-devices'></i></div>
<div><div class="header-title">Device ID</div><div class="header-subtitle">Controle de identificação por dispositivo</div></div>
</div>
<div class="card-body-custom">
<form method="POST" action="checkuserconf.php">
<input type="hidden" name="deviceativo" id="device-value" value="<?php echo $deviceativo;?>">
<div class="toggle-group">
<div class="toggle-card on <?php echo $deviceativo=='1'?'active-on':'';?>" onclick="selectToggle('device','1',this)">
<div class="toggle-icon"><i class='bx bx-check-shield'></i></div>
<div><div class="toggle-title">Ativado</div><div class="toggle-desc">Device ID ligado</div></div>
<div class="toggle-check"><i class='bx <?php echo $deviceativo=='1'?'bx-check':'';?>'></i></div>
</div>
<div class="toggle-card off <?php echo $deviceativo=='0'?'active-off':'';?>" onclick="selectToggle('device','0',this)">
<div class="toggle-icon"><i class='bx bx-shield-x'></i></div>
<div><div class="toggle-title">Desativado</div><div class="toggle-desc">Device ID desligado</div></div>
<div class="toggle-check"><i class='bx <?php echo $deviceativo=='0'?'bx-check':'';?>'></i></div>
</div>
</div>
<div class="status-badge <?php echo $deviceativo=='1'?'on':'off';?>" id="device-badge"><i class='bx <?php echo $deviceativo=='1'?'bx-check-circle':'bx-x-circle';?>'></i> <?php echo $deviceativo=='1'?'ATIVADO':'DESATIVADO';?></div>
<div class="action-buttons">
<a href="home.php" class="action-btn btn-back"><i class='bx bx-arrow-back'></i> Voltar</a>
<button type="submit" name="salvar" class="action-btn btn-save"><i class='bx bx-save'></i> Salvar</button>
</div>
</form>
</div>
</div>

</div></div>

<!-- MODAL SUCESSO -->
<div id="modalSucesso" class="modal-overlay <?php echo ($show_modal && $modal_type=='success')?'show':'';?>">
<div class="modal-container"><div class="modal-content-custom">
<div class="modal-header-custom success"><h5><i class='bx bx-check-circle'></i> Sucesso!</h5><button class="modal-close" onclick="fecharModal('modalSucesso')"><i class='bx bx-x'></i></button></div>
<div class="modal-body-custom">
<div class="modal-ic success"><i class='bx bx-check-circle'></i></div>
<p style="font-size:14px;font-weight:700;margin-bottom:6px">Tudo certo!</p>
<p style="font-size:12px;color:rgba(255,255,255,.6)"><?php echo $msg_sucesso;?></p>
</div>
<div class="modal-footer-custom"><button class="btn-modal btn-modal-ok" onclick="fecharModal('modalSucesso')"><i class='bx bx-check'></i> OK</button></div>
</div></div>
</div>

<!-- MODAL ERRO -->
<div id="modalErro" class="modal-overlay <?php echo ($show_modal && $modal_type=='error')?'show':'';?>">
<div class="modal-container"><div class="modal-content-custom">
<div class="modal-header-custom error"><h5><i class='bx bx-error-circle'></i> Erro!</h5><button class="modal-close" onclick="fecharModal('modalErro')"><i class='bx bx-x'></i></button></div>
<div class="modal-body-custom">
<div class="modal-ic error"><i class='bx bx-error-circle'></i></div>
<p style="font-size:14px;font-weight:700;margin-bottom:6px">Ops!</p>
<p style="font-size:12px;color:rgba(255,255,255,.6)"><?php echo $msg_erro;?></p>
</div>
<div class="modal-footer-custom"><button class="btn-modal btn-modal-danger" onclick="fecharModal('modalErro')"><i class='bx bx-check'></i> OK</button></div>
</div></div>
</div>

<script>
function fecharModal(id){document.getElementById(id).classList.remove('show')}
document.querySelectorAll('.modal-overlay').forEach(function(o){o.addEventListener('click',function(e){if(e.target===o)o.classList.remove('show')})});
document.addEventListener('keydown',function(e){if(e.key==='Escape')document.querySelectorAll('.modal-overlay.show').forEach(function(m){m.classList.remove('show')})});

function selectToggle(name,value,el){
    document.getElementById(name+'-value').value=value;
    var group=el.closest('.toggle-group');
    group.querySelectorAll('.toggle-card').forEach(function(c){c.classList.remove('active-on','active-off');var ci=c.querySelector('.toggle-check i');if(ci)ci.className='bx'});
    el.classList.add(value==='1'?'active-on':'active-off');
    var ci=el.querySelector('.toggle-check i');if(ci)ci.className='bx bx-check';
    var badge=document.getElementById(name+'-badge');
    if(badge){
        if(value==='1'){badge.className='status-badge on';badge.innerHTML='<i class="bx bx-check-circle"></i> ATIVADO'}
        else{badge.className='status-badge off';badge.innerHTML='<i class="bx bx-x-circle"></i> DESATIVADO'}
    }
}

function copyLink(index,btn){
    var el=document.getElementById('link-'+index);
    var text=el.textContent||el.innerText;
    if(navigator.clipboard){
        navigator.clipboard.writeText(text).then(function(){
            btn.classList.add('copied');
            btn.innerHTML='<i class="bx bx-check"></i>';
            showToast('Link copiado!');
            setTimeout(function(){btn.classList.remove('copied');btn.innerHTML='<i class="bx bx-copy"></i>'},2000);
        });
    } else {
        var range=document.createRange();range.selectNode(el);
        window.getSelection().removeAllRanges();window.getSelection().addRange(range);
        document.execCommand('copy');window.getSelection().removeAllRanges();
        btn.classList.add('copied');btn.innerHTML='<i class="bx bx-check"></i>';
        showToast('Link copiado!');
        setTimeout(function(){btn.classList.remove('copied');btn.innerHTML='<i class="bx bx-copy"></i>'},2000);
    }
}

function showToast(msg){
    var t=document.createElement('div');
    t.className='toast-notification ok';
    t.innerHTML='<i class="bx bx-check-circle"></i> '+msg;
    document.body.appendChild(t);
    setTimeout(function(){t.remove()},2500);
}
</script>
</body>
</html>

<?php
    }
    aleatorio377360($kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf);
?>

