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

// Buscar configs
$sql = "SELECT * FROM configs";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $nomepainel = $row["nomepainel"];
        $logo = $row["logo"];
        $icon = $row["icon"];
        $imagelogin = $row["imglogin"];
        $linkapp = $row["cortextcard"];
        $tempolimiter = $row["corletranav"];
        $limiter = $row["corbarranav"];
    }
}

function anti_sql($input) {
    global $conn;
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function($m){ return ''; }, $input);
    return mysqli_real_escape_string($conn, strip_tags(trim($seg)));
}

function uploadImage($file, $oldPath = '') {
    $uploadDir = '../uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    if (empty($file['name'])) return $oldPath;
    $allowedTypes = ['image/jpeg','image/jpg','image/png','image/gif','image/webp'];
    if (!in_array($file['type'], $allowedTypes)) return ['error' => 'Tipo inválido! Use JPG, PNG, GIF ou WEBP.'];
    if ($file['size'] > 5242880) return ['error' => 'Máximo 5MB!'];
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newName = 'img_' . time() . '_' . uniqid() . '.' . $ext;
    $path = $uploadDir . $newName;
    if (move_uploaded_file($file['tmp_name'], $path)) {
        if (!empty($oldPath) && file_exists($oldPath) && strpos($oldPath, 'http') === false) @unlink($oldPath);
        return $path;
    }
    return ['error' => 'Erro no upload.'];
}

$msg_sucesso = ''; $msg_erro = ''; $show_modal = false; $modal_type = '';

// SALVAR
if (isset($_POST['salvar'])) {
    $nomepainel_new = anti_sql($_POST['nomepainel']);
    $applink = anti_sql($_POST['applink']);
    $suspenderauto = anti_sql($_POST['suspenderauto']);
    $limiter_new = anti_sql($_POST['limiter']);
    $tempolimiter_new = anti_sql($_POST['tempolimiter']);
    $errors = [];

    $newLogo = $logo;
    if (isset($_FILES['imagemlogo']) && !empty($_FILES['imagemlogo']['name'])) {
        $r = uploadImage($_FILES['imagemlogo'], $logo);
        if (is_array($r) && isset($r['error'])) $errors[] = "Logo: " . $r['error'];
        else $newLogo = $r;
    }
    $newIcon = $icon;
    if (isset($_FILES['icon']) && !empty($_FILES['icon']['name'])) {
        $r = uploadImage($_FILES['icon'], $icon);
        if (is_array($r) && isset($r['error'])) $errors[] = "Ícone: " . $r['error'];
        else $newIcon = $r;
    }

    if (empty($errors)) {
        $sql = "UPDATE configs SET nomepainel='$nomepainel_new', logo='$newLogo', icon='$newIcon', corbarranav='$limiter_new', cortextcard='$applink', corletranav='$tempolimiter_new', imglogin='$suspenderauto' WHERE id='1'";
        if (mysqli_query($conn, $sql)) {
            $msg_sucesso = "Configurações salvas com sucesso!"; $modal_type = 'success'; $show_modal = true;
            $nomepainel = $nomepainel_new; $logo = $newLogo; $icon = $newIcon; $limiter = $limiter_new; $linkapp = $applink; $tempolimiter = $tempolimiter_new; $imagelogin = $suspenderauto;
        } else { $msg_erro = "Erro ao salvar!"; $modal_type = 'error'; $show_modal = true; }
    } else { $msg_erro = implode(" | ", $errors); $modal_type = 'error'; $show_modal = true; }
}

// RESET
if (isset($_POST['reset'])) {
    if (file_exists($logo) && strpos($logo, 'http') === false) @unlink($logo);
    if (file_exists($icon) && strpos($icon, 'http') === false) @unlink($icon);
    $sql = "UPDATE configs SET nomepainel='Atlas Painel', logo='https://cdn.discordapp.com/attachments/1051302877987086437/1070581060821340250/logo.png', icon='https://cdn.discordapp.com/attachments/1051302877987086437/1070581061014274088/logo-mini.png', imglogin='0', corbarranav='0', cortextcard='', corletranav='5' WHERE id='1'";
    if (mysqli_query($conn, $sql)) { $msg_sucesso = "Configurações resetadas!"; $modal_type = 'success'; $show_modal = true;
        $nomepainel='Atlas Painel'; $logo='https://cdn.discordapp.com/attachments/1051302877987086437/1070581060821340250/logo.png'; $icon='https://cdn.discordapp.com/attachments/1051302877987086437/1070581061014274088/logo-mini.png'; $imagelogin='0'; $limiter='0'; $linkapp=''; $tempolimiter='5';
    }
}

// Stats
$config_complete = 0;
if(!empty($nomepainel)) $config_complete++;
if(!empty($logo)) $config_complete++;
if(!empty($icon)) $config_complete++;
if(!empty($linkapp)) $config_complete++;
$config_pct = round(($config_complete / 4) * 100);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Configurações do Painel</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
<style>
<?php if(function_exists('getCSSVariables')) echo getCSSVariables($temaAtual); ?>

*{margin:0;padding:0;box-sizing:border-box}


.app-content{margin-left:-650px!important;padding:0!important;}
.content-wrapper{max-width:1000px;margin:0 auto!important;padding:20px!important;}
.content-body{padding:0!important;}


/* STATS CARD */
.stats-card{background:linear-gradient(135deg,var(--fundo_claro,#1e293b),var(--fundo,#0f172a));border-radius:20px;padding:20px 24px;margin-bottom:20px;border:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:20px;position:relative;overflow:hidden;transition:all .3s}
.stats-card:hover{transform:translateY(-2px);border-color:var(--primaria,#10b981)}
.stats-card-icon{width:60px;height:60px;background:linear-gradient(135deg,var(--primaria,#10b981),var(--secundaria,#C850C0));border-radius:18px;display:flex;align-items:center;justify-content:center;font-size:32px;color:#fff;flex-shrink:0}
.stats-card-content{flex:1}
.stats-card-title{font-size:13px;font-weight:600;color:rgba(255,255,255,.5);text-transform:uppercase;margin-bottom:5px}
.stats-card-value{font-size:28px;font-weight:800;background:linear-gradient(135deg,#fff,var(--primaria,#10b981));-webkit-background-clip:text;-webkit-text-fill-color:transparent;line-height:1}
.stats-card-subtitle{font-size:12px;color:rgba(255,255,255,.4);margin-top:4px}
.stats-card-decoration{position:absolute;right:20px;top:50%;transform:translateY(-50%);font-size:80px;opacity:.05}
.stats-card-right{display:flex;gap:8px;flex-shrink:0;flex-wrap:wrap}
.progress-bar-wrap{background:rgba(255,255,255,.06);border-radius:20px;height:6px;overflow:hidden;margin-top:8px}
.progress-bar-fill{height:100%;border-radius:20px;background:linear-gradient(135deg,var(--primaria,#10b981),var(--secundaria,#C850C0));transition:width .5s}

/* MINI STATS */
.mini-stats{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px}
.mini-stat{flex:1;min-width:90px;background:rgba(255,255,255,.04);border-radius:12px;padding:12px;border:1px solid rgba(255,255,255,.06);text-align:center;transition:all .2s}
.mini-stat:hover{border-color:var(--primaria,#10b981);transform:translateY(-2px)}
.mini-stat-ic{font-size:20px;margin-bottom:4px}
.mini-stat-val{font-size:16px;font-weight:800}
.mini-stat-lbl{font-size:9px;color:rgba(255,255,255,.35);text-transform:uppercase;margin-top:2px}

/* MODERN CARD */
.modern-card{background:var(--fundo_claro,#1e293b);border-radius:16px;border:1px solid rgba(255,255,255,.08);overflow:hidden;margin-bottom:16px;transition:all .2s}
.modern-card:hover{border-color:var(--primaria,#10b981)}
.card-header-custom{padding:14px 18px;display:flex;align-items:center;gap:12px}
.card-header-custom.gradient{background:linear-gradient(135deg,var(--primaria,#10b981),var(--secundaria,#C850C0))}
.card-header-custom.orange{background:linear-gradient(135deg,#f59e0b,#f97316)}
.card-header-custom.purple{background:linear-gradient(135deg,#8b5cf6,#7c3aed)}
.card-header-custom.blue{background:linear-gradient(135deg,#3b82f6,#2563eb)}
.card-header-custom.green{background:linear-gradient(135deg,#10b981,#059669)}
.card-header-custom.red{background:linear-gradient(135deg,#dc2626,#b91c1c)}
.card-header-custom.teal{background:linear-gradient(135deg,#06b6d4,#0891b2)}
.card-header-custom.pink{background:linear-gradient(135deg,#ec4899,#db2777)}
.header-icon{width:36px;height:36px;background:rgba(255,255,255,.2);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;color:#fff}
.header-title{font-size:14px;font-weight:700;color:#fff}
.header-subtitle{font-size:10px;color:rgba(255,255,255,.7)}
.card-body-custom{padding:16px}

/* FORM */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.form-field{display:flex;flex-direction:column;gap:4px}
.form-field.full-width{grid-column:1/-1}
.form-field label{font-size:9px;font-weight:700;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.5px;display:flex;align-items:center;gap:4px}
.form-field label i{font-size:12px}
.form-control{width:100%;padding:8px 12px;background:rgba(255,255,255,.06);border:1.5px solid rgba(255,255,255,.08);border-radius:9px;font-size:12px;color:#fff!important;transition:all .2s;font-family:inherit;outline:none}
.form-control:focus{border-color:var(--primaria,#10b981);background:rgba(255,255,255,.09)}
.form-control::placeholder{color:rgba(255,255,255,.25)}
.form-hint{font-size:9px;color:rgba(255,255,255,.25);display:flex;align-items:center;gap:3px;margin-top:2px}
.form-hint i{font-size:11px}

/* UPLOAD */
.upload-area{display:flex;align-items:center;gap:16px;padding:14px;background:rgba(255,255,255,.03);border-radius:12px;border:1px dashed rgba(255,255,255,.1);transition:all .3s;flex-wrap:wrap}
.upload-area:hover{border-color:var(--primaria,#10b981);background:rgba(255,255,255,.05)}
.upload-preview{width:80px;height:50px;border-radius:8px;overflow:hidden;background:rgba(255,255,255,.05);display:flex;align-items:center;justify-content:center;border:1px solid rgba(255,255,255,.1);flex-shrink:0}
.upload-preview img{max-width:100%;max-height:100%;object-fit:contain}
.upload-actions{flex:1;min-width:180px}
.upload-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:linear-gradient(135deg,var(--primaria,#10b981),var(--secundaria,#C850C0));color:#fff;border:none;border-radius:10px;font-weight:700;font-size:11px;cursor:pointer;transition:all .2s;font-family:inherit}
.upload-btn:hover{transform:translateY(-1px);filter:brightness(1.1)}
.upload-btn input{display:none}
.upload-info{font-size:9px;color:rgba(255,255,255,.3);margin-top:6px}
.new-preview{margin-top:10px;padding:10px;background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.2);border-radius:8px;text-align:center;display:none}
.new-preview img{max-width:120px;max-height:60px;border-radius:6px;margin-bottom:4px}
.new-preview-text{font-size:10px;color:#34d399;font-weight:600}

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
.btn-reset{background:linear-gradient(135deg,#f59e0b,#f97316)}
.btn-back{background:linear-gradient(135deg,#f43f5e,#e11d48)}
.action-buttons{display:flex;justify-content:flex-end;gap:8px;margin-top:8px;flex-wrap:wrap}

/* SEPARATOR */
.section-sep{height:1px;background:linear-gradient(90deg,transparent,rgba(255,255,255,.06),transparent);margin:4px 0}

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
.modal-header-custom.warning{background:linear-gradient(135deg,#f59e0b,#f97316)}
.modal-close{background:rgba(255,255,255,.15);border:none;color:#fff;font-size:18px;cursor:pointer;width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;transition:all .2s}
.modal-close:hover{background:rgba(255,255,255,.25);transform:rotate(90deg)}
.modal-body-custom{padding:20px;text-align:center}
.modal-ic{width:70px;height:70px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:34px;animation:icPop .5s cubic-bezier(.34,1.56,.64,1) .15s both}
@keyframes icPop{0%{transform:scale(0);opacity:0}100%{transform:scale(1);opacity:1}}
.modal-ic.success{background:rgba(16,185,129,.15);color:#34d399;border:2px solid rgba(16,185,129,.3)}
.modal-ic.error{background:rgba(239,68,68,.15);color:#f87171;border:2px solid rgba(239,68,68,.3)}
.modal-ic.warning{background:rgba(245,158,11,.15);color:#fbbf24;border:2px solid rgba(245,158,11,.3)}
.modal-footer-custom{border-top:1px solid rgba(255,255,255,.07);padding:12px 18px;display:flex;justify-content:center;gap:8px}
.btn-modal{padding:8px 16px;border:none;border-radius:10px;font-weight:600;font-size:12px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;color:#fff;transition:all .2s;font-family:inherit}
.btn-modal:hover{transform:translateY(-1px);filter:brightness(1.08)}
.btn-modal-ok{background:linear-gradient(135deg,#10b981,#059669)}
.btn-modal-danger{background:linear-gradient(135deg,#dc2626,#b91c1c)}
.btn-modal-cancel{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12)}

/* TOAST */
.toast-notification{position:fixed;bottom:20px;right:20px;color:#fff;padding:10px 16px;border-radius:10px;display:flex;align-items:center;gap:8px;z-index:10001;animation:toastIn .3s ease;font-weight:600;font-size:12px;box-shadow:0 8px 20px rgba(0,0,0,.3)}
.toast-notification.ok{background:linear-gradient(135deg,#10b981,#059669)}
.toast-notification.err{background:linear-gradient(135deg,#dc2626,#b91c1c)}
@keyframes toastIn{from{transform:translateX(110%);opacity:0}to{transform:translateX(0);opacity:1}}

@media(max-width:768px){
    .content-wrapper{padding:10px!important}
    .form-grid{grid-template-columns:1fr}
    .stats-card{flex-wrap:wrap;padding:14px;gap:14px}
    .stats-card-icon{width:48px;height:48px;font-size:24px}
    .stats-card-value{font-size:22px}
    .stats-card-right{width:100%;justify-content:center}
    .mini-stats{flex-wrap:wrap}.mini-stat{min-width:70px}
    .toggle-group{flex-direction:column}
    .upload-area{flex-direction:column;text-align:center}
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
<div class="stats-card-icon"><i class='bx bx-cog'></i></div>
<div class="stats-card-content">
<div class="stats-card-title">Configurações do Painel</div>
<div class="stats-card-value"><?php echo htmlspecialchars($nomepainel);?></div>
<div class="stats-card-subtitle"><?php echo $config_pct;?>% configurado • Limiter: <?php echo $limiter=='1'?'Ativo':'Inativo';?></div>
<div class="progress-bar-wrap"><div class="progress-bar-fill" style="width:<?php echo $config_pct;?>%"></div></div>
</div>
<div class="stats-card-right">
<?php if(!empty($icon)):?><img src="<?php echo htmlspecialchars($icon);?>" style="width:40px;height:40px;border-radius:10px;object-fit:contain;background:rgba(255,255,255,.05);padding:4px"><?php endif;?>
<a href="home.php" class="action-btn btn-back"><i class='bx bx-arrow-back'></i> Voltar</a>
</div>
<div class="stats-card-decoration"><i class='bx bx-cog'></i></div>
</div>

<!-- MINI STATS -->
<div class="mini-stats">
<div class="mini-stat"><div class="mini-stat-ic" style="color:#f97316"><i class='bx bx-rename'></i></div><div class="mini-stat-val" style="color:#f97316"><?php echo strlen($nomepainel);?></div><div class="mini-stat-lbl">Caracteres</div></div>
<div class="mini-stat"><div class="mini-stat-ic" style="color:<?php echo !empty($logo)?'#34d399':'#f87171';?>"><i class='bx <?php echo !empty($logo)?'bx-check-circle':'bx-x-circle';?>'></i></div><div class="mini-stat-val" style="color:<?php echo !empty($logo)?'#34d399':'#f87171';?>"><?php echo !empty($logo)?'Sim':'Não';?></div><div class="mini-stat-lbl">Logo</div></div>
<div class="mini-stat"><div class="mini-stat-ic" style="color:<?php echo $limiter=='1'?'#34d399':'#fbbf24';?>"><i class='bx bx-shield'></i></div><div class="mini-stat-val" style="color:<?php echo $limiter=='1'?'#34d399':'#fbbf24';?>"><?php echo $limiter=='1'?'On':'Off';?></div><div class="mini-stat-lbl">Limiter</div></div>
<div class="mini-stat"><div class="mini-stat-ic" style="color:<?php echo $imagelogin=='1'?'#22d3ee':'#fbbf24';?>"><i class='bx bx-lock'></i></div><div class="mini-stat-val" style="color:<?php echo $imagelogin=='1'?'#22d3ee':'#fbbf24';?>"><?php echo $imagelogin=='1'?'On':'Off';?></div><div class="mini-stat-lbl">Suspender</div></div>
<div class="mini-stat"><div class="mini-stat-ic" style="color:#e879f9"><i class='bx bx-check-shield'></i></div><div class="mini-stat-val" style="color:#e879f9"><?php echo $config_pct;?>%</div><div class="mini-stat-lbl">Completo</div></div>
</div>

<form method="POST" action="configpainel.php" enctype="multipart/form-data">

<!-- IDENTIDADE -->
<div class="modern-card">
<div class="card-header-custom orange">
<div class="header-icon"><i class='bx bx-rename'></i></div>
<div><div class="header-title">Identidade do Painel</div><div class="header-subtitle">Nome e texto padrão</div></div>
</div>
<div class="card-body-custom">
<div class="form-grid">
<div class="form-field">
<label><i class='bx bx-rename' style="color:#f97316"></i> Nome do Painel</label>
<input type="text" class="form-control" name="nomepainel" value="<?php echo htmlspecialchars($nomepainel);?>" placeholder="Nome do painel" maxlength="12" required>
<div class="form-hint"><i class='bx bx-info-circle'></i> Máximo 12 caracteres</div>
</div>
<div class="form-field">
<label><i class='bx bx-message-square-detail' style="color:#8b5cf6"></i> Texto ao Criar Usuário</label>
<input type="text" class="form-control" name="applink" value="<?php echo htmlspecialchars($linkapp);?>" placeholder="Ex: Proibido Uso de Torrent">
</div>
</div>
</div>
</div>

<!-- IMAGENS -->
<div class="modern-card">
<div class="card-header-custom green">
<div class="header-icon"><i class='bx bx-image'></i></div>
<div><div class="header-title">Imagens</div><div class="header-subtitle">Logo e ícone do painel</div></div>
</div>
<div class="card-body-custom">

<!-- LOGO -->
<div class="form-field full-width" style="margin-bottom:14px">
<label><i class='bx bx-image' style="color:#10b981"></i> Logo da Página de Login <span style="font-size:8px;color:rgba(255,255,255,.3);margin-left:4px">488×113px</span></label>
<div class="upload-area">
<div class="upload-preview">
<?php if(!empty($logo)):?><img src="<?php echo htmlspecialchars($logo);?>" alt="Logo"><?php else:?><i class='bx bx-image' style="font-size:24px;color:rgba(255,255,255,.15)"></i><?php endif;?>
</div>
<div class="upload-actions">
<label class="upload-btn">
<i class='bx bx-cloud-upload'></i> Escolher Logo
<input type="file" name="imagemlogo" accept="image/*" onchange="previewImg(this,'preview-logo')">
</label>
<div class="upload-info"><i class='bx bx-info-circle'></i> JPG, PNG, GIF, WEBP • Máx 5MB</div>
</div>
</div>
<div class="new-preview" id="preview-logo"></div>
</div>

<div class="section-sep"></div>

<!-- ÍCONE -->
<div class="form-field full-width">
<label><i class='bx bx-star' style="color:#f59e0b"></i> Ícone do Painel <span style="font-size:8px;color:rgba(255,255,255,.3);margin-left:4px">372×362px</span></label>
<div class="upload-area">
<div class="upload-preview">
<?php if(!empty($icon)):?><img src="<?php echo htmlspecialchars($icon);?>" alt="Ícone"><?php else:?><i class='bx bx-star' style="font-size:24px;color:rgba(255,255,255,.15)"></i><?php endif;?>
</div>
<div class="upload-actions">
<label class="upload-btn">
<i class='bx bx-cloud-upload'></i> Escolher Ícone
<input type="file" name="icon" accept="image/*" onchange="previewImg(this,'preview-icon')">
</label>
<div class="upload-info"><i class='bx bx-info-circle'></i> JPG, PNG, GIF, WEBP • Máx 5MB</div>
</div>
</div>
<div class="new-preview" id="preview-icon"></div>
</div>

</div>
</div>

<!-- LIMITER -->
<div class="modern-card">
<div class="card-header-custom red">
<div class="header-icon"><i class='bx bx-shield'></i></div>
<div><div class="header-title">Limiter</div><div class="header-subtitle">Controle de limitação de conexões</div></div>
</div>
<div class="card-body-custom">
<input type="hidden" name="limiter" id="limiter-value" value="<?php echo $limiter;?>">
<div class="toggle-group">
<div class="toggle-card on <?php echo $limiter=='1'?'active-on':'';?>" onclick="selectToggle('limiter','1',this)">
<div class="toggle-icon"><i class='bx bx-check-shield'></i></div>
<div><div class="toggle-title">Ativado</div><div class="toggle-desc">Limitador ligado</div></div>
<div class="toggle-check"><i class='bx <?php echo $limiter=='1'?'bx-check':'';?>'></i></div>
</div>
<div class="toggle-card off <?php echo $limiter=='0'?'active-off':'';?>" onclick="selectToggle('limiter','0',this)">
<div class="toggle-icon"><i class='bx bx-shield-x'></i></div>
<div><div class="toggle-title">Desativado</div><div class="toggle-desc">Limitador desligado</div></div>
<div class="toggle-check"><i class='bx <?php echo $limiter=='0'?'bx-check':'';?>'></i></div>
</div>
</div>
<div class="status-badge <?php echo $limiter=='1'?'on':'off';?>" id="limiter-badge"><i class='bx <?php echo $limiter=='1'?'bx-check-circle':'bx-x-circle';?>'></i> <?php echo $limiter=='1'?'ATIVADO':'DESATIVADO';?></div>
</div>
</div>

<!-- TEMPO + SUSPENDER AUTO -->
<div class="modern-card">
<div class="card-header-custom teal">
<div class="header-icon"><i class='bx bx-time'></i></div>
<div><div class="header-title">Tempo & Suspensão</div><div class="header-subtitle">Tempo extra e suspensão automática</div></div>
</div>
<div class="card-body-custom">
<div class="form-grid">
<div class="form-field">
<label><i class='bx bx-time' style="color:#06b6d4"></i> Tempo Extra (dias)</label>
<input type="number" class="form-control" name="tempolimiter" value="<?php echo htmlspecialchars($tempolimiter);?>" placeholder="Ex: 10" min="1">
<div class="form-hint"><i class='bx bx-info-circle'></i> Dias extras após expirar</div>
</div>
</div>

<div style="margin-top:14px">
<label style="font-size:9px;font-weight:700;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.5px;display:flex;align-items:center;gap:4px;margin-bottom:6px"><i class='bx bx-block' style="font-size:12px;color:#ec4899"></i> Suspender Automático</label>
<input type="hidden" name="suspenderauto" id="suspenderauto-value" value="<?php echo $imagelogin;?>">
<div class="toggle-group">
<div class="toggle-card on <?php echo $imagelogin=='1'?'active-on':'';?>" onclick="selectToggle('suspenderauto','1',this)">
<div class="toggle-icon"><i class='bx bx-lock-alt'></i></div>
<div><div class="toggle-title">Ativado</div><div class="toggle-desc">Suspende ao expirar</div></div>
<div class="toggle-check"><i class='bx <?php echo $imagelogin=='1'?'bx-check':'';?>'></i></div>
</div>
<div class="toggle-card off <?php echo $imagelogin=='0'?'active-off':'';?>" onclick="selectToggle('suspenderauto','0',this)">
<div class="toggle-icon"><i class='bx bx-lock-open-alt'></i></div>
<div><div class="toggle-title">Desativado</div><div class="toggle-desc">Não suspende</div></div>
<div class="toggle-check"><i class='bx <?php echo $imagelogin=='0'?'bx-check':'';?>'></i></div>
</div>
</div>
<div class="status-badge <?php echo $imagelogin=='1'?'on':'off';?>" id="suspenderauto-badge"><i class='bx <?php echo $imagelogin=='1'?'bx-check-circle':'bx-x-circle';?>'></i> <?php echo $imagelogin=='1'?'ATIVADO':'DESATIVADO';?></div>
</div>
</div>
</div>

<!-- AÇÕES -->
<div class="modern-card">
<div class="card-header-custom purple">
<div class="header-icon"><i class='bx bx-save'></i></div>
<div><div class="header-title">Ações</div><div class="header-subtitle">Salvar ou resetar configurações</div></div>
</div>
<div class="card-body-custom">
<div class="action-buttons">
<a href="home.php" class="action-btn btn-back"><i class='bx bx-arrow-back'></i> Voltar</a>
<button type="button" class="action-btn btn-reset" onclick="confirmarReset()"><i class='bx bx-reset'></i> Resetar</button>
<button type="submit" name="salvar" class="action-btn btn-save"><i class='bx bx-save'></i> Salvar Configurações</button>
</div>
</div>
</div>

<!-- Botão reset oculto -->
<button type="submit" name="reset" id="btnReset" style="display:none"></button>
</form>

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

<!-- MODAL RESET -->
<div id="modalReset" class="modal-overlay">
<div class="modal-container"><div class="modal-content-custom">
<div class="modal-header-custom warning"><h5><i class='bx bx-reset'></i> Resetar</h5><button class="modal-close" onclick="fecharModal('modalReset')"><i class='bx bx-x'></i></button></div>
<div class="modal-body-custom">
<div class="modal-ic warning"><i class='bx bx-error'></i></div>
<p style="font-size:14px;font-weight:700;margin-bottom:6px">Tem certeza?</p>
<p style="font-size:12px;color:rgba(255,255,255,.5)">Todas as configurações voltarão ao padrão.</p>
</div>
<div class="modal-footer-custom">
<button class="btn-modal btn-modal-cancel" onclick="fecharModal('modalReset')"><i class='bx bx-x'></i> Cancelar</button>
<button class="btn-modal btn-modal-danger" onclick="executarReset()"><i class='bx bx-reset'></i> Resetar</button>
</div>
</div></div>
</div>

<script>
function abrirModal(id){document.getElementById(id).classList.add('show')}
function fecharModal(id){document.getElementById(id).classList.remove('show')}
document.querySelectorAll('.modal-overlay').forEach(function(o){o.addEventListener('click',function(e){if(e.target===o)o.classList.remove('show')})});
document.addEventListener('keydown',function(e){if(e.key==='Escape')document.querySelectorAll('.modal-overlay.show').forEach(function(m){m.classList.remove('show')})});

function selectToggle(name,value,el){
    document.getElementById(name+'-value').value=value;
    var group=el.closest('.toggle-group');
    group.querySelectorAll('.toggle-card').forEach(function(c){c.classList.remove('active-on','active-off');c.querySelector('.toggle-check i').className='bx'});
    el.classList.add(value==='1'?'active-on':'active-off');
    el.querySelector('.toggle-check i').className='bx bx-check';
    var badge=document.getElementById(name+'-badge');
    if(badge){
        if(value==='1'){badge.className='status-badge on';badge.innerHTML='<i class="bx bx-check-circle"></i> ATIVADO'}
        else{badge.className='status-badge off';badge.innerHTML='<i class="bx bx-x-circle"></i> DESATIVADO'}
    }
}

function previewImg(input,id){
    var container=document.getElementById(id);
    if(input.files&&input.files[0]){
        container.style.display='block';
        var reader=new FileReader();
        reader.onload=function(e){container.innerHTML='<img src="'+e.target.result+'" style="max-width:120px;max-height:60px;border-radius:6px;margin-bottom:4px"><div class="new-preview-text"><i class="bx bx-check-circle"></i> Nova imagem selecionada</div>'};
        reader.readAsDataURL(input.files[0]);
    } else container.style.display='none';
}

function confirmarReset(){abrirModal('modalReset')}
function executarReset(){fecharModal('modalReset');document.getElementById('btnReset').click()}
</script>
</body>
</html>
<?php mysqli_close($conn); ?>

