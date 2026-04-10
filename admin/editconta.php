<?php
error_reporting(0);
session_start();
date_default_timezone_set('America/Sao_Paulo');

if (!isset($_SESSION['login']) && !isset($_SESSION['senha'])) {
    session_destroy(); header('location:../index.php'); exit;
}

include('../AegisCore/conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) die("Connection failed: " . mysqli_connect_error());

$id = $_SESSION['iduser'];
include_once 'headeradmin2.php';

// ========== INCLUIR SISTEMA DE TEMAS ==========
if(file_exists('../AegisCore/temas.php')){
    include_once '../AegisCore/temas.php';
    $temaAtual = initTemas($conn);
} else {
    $temaAtual = [];
}

if (!file_exists('suspenderrev.php')) exit("<script>alert('Token Invalido!');</script>");
else include_once 'suspenderrev.php';

if (!isset($_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe']) || !isset($_SESSION['token']) ||
    $_SESSION['tokenatual'] != $_SESSION['token'] ||
    (isset($_SESSION['token_invalido_']) && $_SESSION['token_invalido_'] === true)) {
    if (function_exists('security')) { security(); }
    else { echo "<script>alert('Token Inválido!');location.href='../index.php';</script>"; $_SESSION['token_invalido_'] = true; exit; }
}

// Verificar/criar colunas
$col_check = mysqli_query($conn, "SHOW COLUMNS FROM accounts LIKE 'idtelegram'");
if (mysqli_num_rows($col_check) == 0) {
    mysqli_query($conn, "ALTER TABLE accounts ADD idtelegram TEXT");
    mysqli_query($conn, "ALTER TABLE accounts ADD tempo TEXT");
}

// Buscar dados
$sql = "SELECT login, senha, nome_completo, telefone, profile_image, email, contato, mb, token, idtelegram FROM accounts WHERE id = '$id'";
$result = $conn->query($sql);
$user_data = $result->fetch_assoc();

$sql_whatsapp = "SELECT * FROM mensagens WHERE byid = '$id'";
$result_whatsapp = mysqli_query($conn, $sql_whatsapp);
$whatsapp_mensagens = [];
if ($result_whatsapp && mysqli_num_rows($result_whatsapp) > 0)
    while ($row = mysqli_fetch_assoc($result_whatsapp)) $whatsapp_mensagens[$row['funcao']] = $row;

$sql_modal = "SELECT * FROM mensagens_modal WHERE byid = '$id'";
$result_modal = mysqli_query($conn, $sql_modal);
$modal_mensagens = [];
if ($result_modal && mysqli_num_rows($result_modal) > 0)
    while ($row = mysqli_fetch_assoc($result_modal)) $modal_mensagens[$row['funcao']] = $row['mensagem'];

function anti_sql($input) {
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function($m){ return ''; }, $input);
    return addslashes(strip_tags(trim($seg)));
}

$mensagem_erro = ''; $mensagem_sucesso = ''; $show_modal_fb = false; $modal_type = '';

// 1. Salvar conta
if (isset($_POST['salvar_conta'])) {
    $nome_completo = anti_sql($_POST['nome_completo']);
    $email         = anti_sql($_POST['email']);
    $telefone      = anti_sql($_POST['telefone']);
    if (mysqli_query($conn, "UPDATE accounts SET nome_completo='$nome_completo', email='$email', telefone='$telefone', contato='$telefone' WHERE id='$id'")) {
        $mensagem_sucesso = "Informações atualizadas com sucesso!"; $modal_type = 'success'; $show_modal_fb = true;
        $user_data['nome_completo'] = $nome_completo; $user_data['email'] = $email; $user_data['telefone'] = $telefone;
    } else { $mensagem_erro = "Erro ao atualizar."; $modal_type = 'error'; $show_modal_fb = true; }
}

// 2. Alterar senha
if (isset($_POST['alterar_senha'])) {
    $senha_atual     = trim($_POST['senha_atual']);
    $nova_senha      = trim($_POST['nova_senha']);
    $confirmar_senha = trim($_POST['confirmar_senha']);
    $r_s = $conn->query("SELECT senha FROM accounts WHERE id='$id'");
    $senha_banco = trim($r_s->fetch_assoc()['senha']);
    if ($senha_atual !== $senha_banco)             { $mensagem_erro = "Senha atual incorreta!"; $modal_type = 'error'; $show_modal_fb = true; }
    elseif (strlen($nova_senha) < 5)               { $mensagem_erro = "Mínimo 5 caracteres!"; $modal_type = 'error'; $show_modal_fb = true; }
    elseif (strlen($nova_senha) > 10)              { $mensagem_erro = "Máximo 10 caracteres!"; $modal_type = 'error'; $show_modal_fb = true; }
    elseif (preg_match('/[^a-z0-9]/i', $nova_senha)) { $mensagem_erro = "Sem caracteres especiais!"; $modal_type = 'error'; $show_modal_fb = true; }
    elseif ($nova_senha !== $confirmar_senha)       { $mensagem_erro = "Confirmação não coincide!"; $modal_type = 'error'; $show_modal_fb = true; }
    else {
        $ns = anti_sql($nova_senha);
        if (mysqli_query($conn, "UPDATE accounts SET senha='$ns' WHERE id='$id'")) {
            $_SESSION['senha'] = $ns; $mensagem_sucesso = "Senha alterada com sucesso!"; $modal_type = 'success'; $show_modal_fb = true;
        } else { $mensagem_erro = "Erro ao alterar senha."; $modal_type = 'error'; $show_modal_fb = true; }
    }
}

// 3. Upload foto
if (isset($_POST['upload_image'])) {
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            $dir = '../uploads/profiles/';
            if (!file_exists($dir)) mkdir($dir, 0777, true);
            $fname = 'profile_'.$id.'_'.time().'.'.$ext;
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $dir.$fname)) {
                if (!empty($user_data['profile_image']) && file_exists($dir.$user_data['profile_image'])) unlink($dir.$user_data['profile_image']);
                $conn->query("UPDATE accounts SET profile_image='$fname' WHERE id='$id'");
                $user_data['profile_image'] = $fname;
                $mensagem_sucesso = "Foto atualizada!"; $modal_type = 'success'; $show_modal_fb = true;
            } else { $mensagem_erro = "Erro no upload."; $modal_type = 'error'; $show_modal_fb = true; }
        } else { $mensagem_erro = "Formato inválido."; $modal_type = 'error'; $show_modal_fb = true; }
    } else { $mensagem_erro = "Nenhum arquivo selecionado."; $modal_type = 'error'; $show_modal_fb = true; }
}

// 4. Salvar Telegram
if (isset($_POST['salvar_telegram'])) {
    $bottoken    = anti_sql($_POST['tokenbot']    ?? '');
    $idtelegram  = anti_sql($_POST['idtelegram']  ?? '');
    $limitetest  = anti_sql($_POST['limitetest']  ?? '60');
    if (mysqli_query($conn, "UPDATE accounts SET token='$bottoken', idtelegram='$idtelegram', mb='$limitetest' WHERE id='$id'")) {
        $mensagem_sucesso = "Configurações do Telegram salvas! Acesse seu Bot e envie /start para ativar.";
        $modal_type = 'success'; $show_modal_fb = true;
        $user_data['token'] = $bottoken; $user_data['idtelegram'] = $idtelegram; $user_data['mb'] = $limitetest;
    } else { $mensagem_erro = "Erro ao salvar Telegram."; $modal_type = 'error'; $show_modal_fb = true; }
}

// 5. Salvar mensagens WhatsApp
if (isset($_POST['salvar_mensagens_whatsapp'])) {
    $funcoes = ['criarusuario','criarteste','criarrevenda','contaexpirada','revendaexpirada'];
    foreach ($funcoes as $funcao) {
        $mensagem = anti_sql($_POST['mensagem_whatsapp_'.$funcao] ?? '');
        if (!empty($mensagem)) {
            $chk = mysqli_query($conn, "SELECT id FROM mensagens WHERE funcao='$funcao' AND byid='$id'");
            if (mysqli_num_rows($chk) > 0) mysqli_query($conn, "UPDATE mensagens SET mensagem='$mensagem', ativo='ativada' WHERE funcao='$funcao' AND byid='$id'");
            else mysqli_query($conn, "INSERT INTO mensagens (funcao, mensagem, ativo, byid) VALUES ('$funcao','$mensagem','ativada','$id')");
        }
        $whatsapp_mensagens[$funcao]['mensagem'] = $_POST['mensagem_whatsapp_'.$funcao] ?? '';
    }
    $mensagem_sucesso = "Mensagens WhatsApp salvas!"; $modal_type = 'success'; $show_modal_fb = true;
}

// 6. Salvar mensagens Modal
if (isset($_POST['salvar_mensagens_modal'])) {
    $funcoes = ['criarusuario','criarteste','criarrevenda'];
    foreach ($funcoes as $funcao) {
        $mensagem = anti_sql($_POST['mensagem_modal_'.$funcao] ?? '');
        if (!empty($mensagem)) {
            $chk = mysqli_query($conn, "SELECT id FROM mensagens_modal WHERE funcao='$funcao' AND byid='$id'");
            if (mysqli_num_rows($chk) > 0) mysqli_query($conn, "UPDATE mensagens_modal SET mensagem='$mensagem', ativo='ativada' WHERE funcao='$funcao' AND byid='$id'");
            else mysqli_query($conn, "INSERT INTO mensagens_modal (funcao, mensagem, ativo, byid) VALUES ('$funcao','$mensagem','ativada','$id')");
        }
        $modal_mensagens[$funcao] = $_POST['mensagem_modal_'.$funcao] ?? '';
    }
    $mensagem_sucesso = "Mensagens Modal salvas!"; $modal_type = 'success'; $show_modal_fb = true;
}

// Stats rápidas
$total_users = 0; $r = $conn->query("SELECT COUNT(*) as t FROM ssh_accounts WHERE byid='$id'"); if($r) $total_users = $r->fetch_assoc()['t'];
$has_telegram = !empty($user_data['token']) && !empty($user_data['idtelegram']);
$has_photo = !empty($user_data['profile_image']);
$profile_complete = 0;
if(!empty($user_data['nome_completo'])) $profile_complete++;
if(!empty($user_data['email'])) $profile_complete++;
if(!empty($user_data['telefone'])) $profile_complete++;
if($has_photo) $profile_complete++;
if($has_telegram) $profile_complete++;
$profile_pct = round(($profile_complete / 5) * 100);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Editar Perfil</title>
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
.stats-card-right{display:flex;align-items:center;gap:12px;flex-shrink:0}
.stats-avatar{width:56px;height:56px;border-radius:50%;object-fit:cover;border:3px solid rgba(255,255,255,.15)}
.btn-back{background:linear-gradient(135deg,#f59e0b,#f97316);color:#fff;text-decoration:none;padding:8px 16px;border-radius:10px;font-weight:700;font-size:11px;display:flex;align-items:center;gap:6px;transition:all .2s;border:none;cursor:pointer}
.btn-back:hover{transform:translateY(-2px);filter:brightness(1.1)}

/* MINI STATS */
.mini-stats{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px}
.mini-stat{flex:1;min-width:90px;background:rgba(255,255,255,.04);border-radius:12px;padding:12px;border:1px solid rgba(255,255,255,.06);text-align:center;transition:all .2s}
.mini-stat:hover{border-color:var(--primaria,#10b981);transform:translateY(-2px)}
.mini-stat-ic{font-size:20px;margin-bottom:4px}
.mini-stat-val{font-size:16px;font-weight:800}
.mini-stat-lbl{font-size:9px;color:rgba(255,255,255,.35);text-transform:uppercase;margin-top:2px}

/* PROGRESS BAR */
.progress-bar-wrap{background:rgba(255,255,255,.06);border-radius:20px;height:6px;overflow:hidden;margin-top:8px}
.progress-bar-fill{height:100%;border-radius:20px;background:linear-gradient(135deg,var(--primaria,#10b981),var(--secundaria,#C850C0));transition:width .5s ease}

/* MODERN CARD */
.modern-card{background:var(--fundo_claro,#1e293b);border-radius:16px;border:1px solid rgba(255,255,255,.08);overflow:hidden;margin-bottom:16px;transition:all .2s}
.modern-card:hover{border-color:var(--primaria,#10b981)}
.card-header-custom{padding:14px 18px;display:flex;align-items:center;gap:12px}
.card-header-custom.gradient{background:linear-gradient(135deg,var(--primaria,#10b981),var(--secundaria,#C850C0))}
.card-header-custom.blue{background:linear-gradient(135deg,#3b82f6,#2563eb)}
.card-header-custom.purple{background:linear-gradient(135deg,#8b5cf6,#7c3aed)}
.card-header-custom.teal{background:linear-gradient(135deg,#0088cc,#005fa3)}
.card-header-custom.green{background:linear-gradient(135deg,#10b981,#059669)}
.card-header-custom.orange{background:linear-gradient(135deg,#f59e0b,#f97316)}
.header-icon{width:36px;height:36px;background:rgba(255,255,255,.2);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;color:#fff}
.header-title{font-size:14px;font-weight:700;color:#fff}
.header-subtitle{font-size:10px;color:rgba(255,255,255,.7)}
.card-body-custom{padding:16px}

/* TABS */
.tabs-container{margin-bottom:16px}
.tabs-buttons{display:flex;gap:4px;background:rgba(0,0,0,.3);padding:4px;border-radius:12px;flex-wrap:wrap}
.tab-btn{padding:8px 14px;border:none;background:transparent;color:rgba(255,255,255,.5);font-size:11px;font-weight:600;cursor:pointer;border-radius:10px;transition:all .3s;display:flex;align-items:center;gap:5px;font-family:inherit;flex:1;justify-content:center;min-width:0;white-space:nowrap}
.tab-btn i{font-size:14px}
.tab-btn.active{background:linear-gradient(135deg,var(--primaria,#10b981),var(--secundaria,#C850C0));color:#fff;box-shadow:0 4px 12px rgba(0,0,0,.3)}
.tab-btn.active.tg{background:linear-gradient(135deg,#0088cc,#005fa3)}
.tab-btn:hover:not(.active){background:rgba(255,255,255,.06);color:#fff}
.tab-content{display:none;animation:fadeInTab .3s ease}
.tab-content.active{display:block}
@keyframes fadeInTab{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}

/* FORM */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.form-field{display:flex;flex-direction:column;gap:4px}
.form-field.full-width{grid-column:1/-1}
.form-field label{font-size:9px;font-weight:700;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.5px;display:flex;align-items:center;gap:4px}
.form-field label i{font-size:12px}
.filter-input,.filter-select,.form-control{width:100%;padding:8px 12px;background:rgba(255,255,255,.06);border:1.5px solid rgba(255,255,255,.08);border-radius:9px;font-size:12px;color:#fff!important;transition:all .2s;font-family:inherit;outline:none}
.filter-input:focus,.filter-select:focus,.form-control:focus{border-color:var(--primaria,#10b981);background:rgba(255,255,255,.09)}
.form-control::placeholder{color:rgba(255,255,255,.25)}
.form-control:disabled,.form-control[readonly]{opacity:.5;cursor:not-allowed}
textarea.form-control{resize:vertical;min-height:90px}

.form-hint{font-size:9px;color:rgba(255,255,255,.3);display:flex;align-items:center;gap:3px;margin-top:2px}
.form-hint i{font-size:11px}

/* PROFILE UPLOAD */
.profile-upload{display:flex;align-items:center;gap:20px;padding:16px;flex-wrap:wrap}
.profile-preview{position:relative}
.profile-preview img{width:90px;height:90px;border-radius:50%;object-fit:cover;border:3px solid rgba(255,255,255,.1)}
.profile-preview .cam-overlay{position:absolute;bottom:0;right:0;width:28px;height:28px;background:linear-gradient(135deg,var(--primaria,#10b981),var(--secundaria,#C850C0));border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;color:#fff;border:2px solid var(--fundo_claro,#1e293b)}
.profile-actions{flex:1;min-width:200px}
.profile-actions p{font-size:12px;color:rgba(255,255,255,.5);margin-bottom:10px}
.upload-btns{display:flex;gap:8px;flex-wrap:wrap}

/* BUTTONS */
.action-btn{padding:8px 16px;border:none;border-radius:10px;font-weight:700;font-size:11px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:6px;color:#fff;transition:all .2s;font-family:inherit}
.action-btn:hover{transform:translateY(-1px);filter:brightness(1.1)}
.action-btn i{font-size:14px;pointer-events:none}
.btn-save{background:linear-gradient(135deg,#10b981,#059669)}
.btn-primary{background:linear-gradient(135deg,#3b82f6,#2563eb)}
.btn-purple{background:linear-gradient(135deg,#8b5cf6,#7c3aed)}
.btn-teal{background:linear-gradient(135deg,#0088cc,#005fa3)}
.btn-danger{background:linear-gradient(135deg,#dc2626,#b91c1c)}
.btn-orange{background:linear-gradient(135deg,#f59e0b,#f97316)}

.action-buttons{display:flex;justify-content:flex-end;gap:8px;margin-top:16px;flex-wrap:wrap}

/* PASSWORD REQ */
.password-req{background:rgba(0,0,0,.2);border-radius:10px;padding:10px;margin-top:8px;font-size:10px;color:rgba(255,255,255,.4)}
.password-req ul{margin:5px 0 0 20px;padding:0}

/* TELEGRAM INFO */
.tg-info{background:rgba(0,136,204,.08);border-left:3px solid #0088cc;border-radius:10px;padding:12px;margin-bottom:16px;font-size:11px;color:rgba(255,255,255,.7);line-height:1.7}
.tg-info strong{color:#54a9eb}
.tg-step{display:flex;align-items:flex-start;gap:8px;margin-bottom:5px}
.tg-step-num{background:linear-gradient(135deg,#0088cc,#005fa3);color:#fff;border-radius:50%;width:18px;height:18px;display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:700;flex-shrink:0;margin-top:1px}

/* MSG PREVIEW */
.msg-preview{background:rgba(255,255,255,.03);border-radius:8px;padding:8px;margin-top:5px;font-size:9px;color:rgba(255,255,255,.4);border-left:2px solid var(--primaria,#10b981)}
.msg-preview i{color:#fbbf24}

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
.modal-footer-custom{border-top:1px solid rgba(255,255,255,.07);padding:12px 18px;display:flex;justify-content:center;gap:8px}
.btn-modal{padding:8px 16px;border:none;border-radius:10px;font-weight:600;font-size:12px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;color:#fff;transition:all .2s;font-family:inherit}
.btn-modal:hover{transform:translateY(-1px);filter:brightness(1.08)}
.btn-modal-ok{background:linear-gradient(135deg,#10b981,#059669)}
.btn-modal-danger{background:linear-gradient(135deg,#dc2626,#b91c1c)}

/* TOAST */
.toast-notification{position:fixed;bottom:20px;right:20px;color:#fff;padding:10px 16px;border-radius:10px;display:flex;align-items:center;gap:8px;z-index:10001;animation:toastIn .3s ease;font-weight:600;font-size:12px;box-shadow:0 8px 20px rgba(0,0,0,.3)}
.toast-notification.ok{background:linear-gradient(135deg,#10b981,#059669)}
.toast-notification.err{background:linear-gradient(135deg,#dc2626,#b91c1c)}
@keyframes toastIn{from{transform:translateX(110%);opacity:0}to{transform:translateX(0);opacity:1}}

@media(max-width:768px){
    .content-wrapper{padding:10px!important}
    .form-grid{grid-template-columns:1fr}
    .profile-upload{flex-direction:column;text-align:center}
    .stats-card{flex-wrap:wrap;padding:14px;gap:14px}
    .stats-card-icon{width:48px;height:48px;font-size:24px}
    .stats-card-value{font-size:22px}
    .stats-card-right{width:100%;justify-content:center}
    .tabs-buttons{overflow-x:auto;flex-wrap:nowrap;gap:2px}
    .tab-btn{font-size:9px;padding:6px 8px;flex:none;min-width:auto}
    .mini-stats{flex-wrap:wrap}.mini-stat{min-width:70px}
    .action-buttons{flex-direction:column}
    .action-btn{width:100%;justify-content:center}
}
</style>
</head>
<body class="<?php echo htmlspecialchars(getBodyClass($temaAtual ?? ['classe'=>'theme-dark'])); ?>">
<div class="app-content content">
<div class="content-overlay"></div>
<div class="content-wrapper">

<?php
$avatar_url = !empty($user_data['profile_image'])
    ? '../uploads/profiles/'.$user_data['profile_image']
    : 'https://ui-avatars.com/api/?name='.urlencode($user_data['login']).'&size=100&background=4158D0&color=fff&bold=true&length=2';
?>

<!-- STATS CARD -->
<div class="stats-card">
<div class="stats-card-icon"><i class='bx bx-user-circle'></i></div>
<div class="stats-card-content">
<div class="stats-card-title">Editar Perfil</div>
<div class="stats-card-value"><?php echo htmlspecialchars($user_data['login']); ?></div>
<div class="stats-card-subtitle">Perfil <?php echo $profile_pct; ?>% completo • <?php echo $total_users; ?> usuários criados</div>
<div class="progress-bar-wrap"><div class="progress-bar-fill" style="width:<?php echo $profile_pct;?>%"></div></div>
</div>
<div class="stats-card-right">
<img src="<?php echo htmlspecialchars($avatar_url);?>" alt="" class="stats-avatar">
<a href="home.php" class="btn-back"><i class='bx bx-arrow-back'></i> Voltar</a>
</div>
<div class="stats-card-decoration"><i class='bx bx-user-circle'></i></div>
</div>

<!-- MINI STATS -->
<div class="mini-stats">
<div class="mini-stat"><div class="mini-stat-ic" style="color:#818cf8"><i class='bx bx-user'></i></div><div class="mini-stat-val" style="color:#818cf8"><?php echo $total_users; ?></div><div class="mini-stat-lbl">Usuários</div></div>
<div class="mini-stat"><div class="mini-stat-ic" style="color:<?php echo $has_photo?'#34d399':'#f87171';?>"><i class='bx <?php echo $has_photo?'bx-check-circle':'bx-x-circle';?>'></i></div><div class="mini-stat-val" style="color:<?php echo $has_photo?'#34d399':'#f87171';?>"><?php echo $has_photo?'Sim':'Não';?></div><div class="mini-stat-lbl">Foto</div></div>
<div class="mini-stat"><div class="mini-stat-ic" style="color:<?php echo $has_telegram?'#22d3ee':'#fbbf24';?>"><i class='bx bxl-telegram'></i></div><div class="mini-stat-val" style="color:<?php echo $has_telegram?'#22d3ee':'#fbbf24';?>"><?php echo $has_telegram?'Ativo':'Off';?></div><div class="mini-stat-lbl">Telegram</div></div>
<div class="mini-stat"><div class="mini-stat-ic" style="color:#e879f9"><i class='bx bx-check-shield'></i></div><div class="mini-stat-val" style="color:#e879f9"><?php echo $profile_pct;?>%</div><div class="mini-stat-lbl">Completo</div></div>
</div>

<!-- FOTO DE PERFIL -->
<div class="modern-card">
<div class="card-header-custom gradient">
<div class="header-icon"><i class='bx bx-camera'></i></div>
<div><div class="header-title">Foto de Perfil</div><div class="header-subtitle">Atualize sua imagem</div></div>
</div>
<div class="card-body-custom">
<form method="post" enctype="multipart/form-data">
<div class="profile-upload">
<div class="profile-preview">
<img src="<?php echo htmlspecialchars($avatar_url);?>" alt="" id="profile-avatar-preview">
<div class="cam-overlay"><i class='bx bx-camera'></i></div>
</div>
<div class="profile-actions">
<p>Escolha uma foto JPG, PNG, GIF ou WEBP</p>
<div class="upload-btns">
<label class="action-btn btn-primary" style="cursor:pointer">
<i class='bx bx-upload'></i> Escolher
<input type="file" name="profile_image" accept="image/*" style="display:none" onchange="previewImage(this)">
</label>
<button type="submit" name="upload_image" class="action-btn btn-save"><i class='bx bx-save'></i> Salvar Foto</button>
</div>
</div>
</div>
</form>
</div>
</div>

<!-- CONFIGURAÇÕES COM ABAS -->
<div class="modern-card">
<div class="card-header-custom purple">
<div class="header-icon"><i class='bx bx-cog'></i></div>
<div><div class="header-title">Configurações da Conta</div><div class="header-subtitle">Informações, segurança, Telegram e mensagens</div></div>
</div>
<div class="card-body-custom">

<div class="tabs-container">
<div class="tabs-buttons">
<button class="tab-btn active" onclick="switchTab('conta')"><i class='bx bx-user'></i> <span>Conta</span></button>
<button class="tab-btn" onclick="switchTab('seguranca')"><i class='bx bx-lock-alt'></i> <span>Segurança</span></button>
<button class="tab-btn" onclick="switchTab('telegram')"><i class='bx bxl-telegram'></i> <span>Telegram</span></button>
<button class="tab-btn" onclick="switchTab('mensagens')"><i class='bx bxl-whatsapp'></i> <span>WhatsApp</span></button>
<button class="tab-btn" onclick="switchTab('modal-msg')"><i class='bx bx-message-rounded-dots'></i> <span>Modal</span></button>
</div>
</div>

<!-- ABA CONTA -->
<div id="tab-conta" class="tab-content active">
<form method="POST">
<div class="form-grid">
<div class="form-field">
<label><i class='bx bx-user' style="color:#818cf8"></i> Nome Completo</label>
<input type="text" class="form-control" name="nome_completo" value="<?php echo htmlspecialchars($user_data['nome_completo'] ?? '');?>" placeholder="Seu nome completo">
</div>
<div class="form-field">
<label><i class='bx bx-envelope' style="color:#e879f9"></i> E-mail</label>
<input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user_data['email'] ?? '');?>" placeholder="seu@email.com">
</div>
<div class="form-field">
<label><i class='bx bx-id-card' style="color:#60a5fa"></i> Usuário</label>
<input type="text" class="form-control" value="<?php echo htmlspecialchars($user_data['login']);?>" disabled readonly>
<div class="form-hint"><i class='bx bx-info-circle'></i> Não pode ser alterado</div>
</div>
<div class="form-field">
<label><i class='bx bxl-whatsapp' style="color:#25D366"></i> Telefone</label>
<input type="tel" class="form-control" name="telefone" value="<?php echo htmlspecialchars($user_data['telefone'] ?? '');?>" placeholder="11999999999">
</div>
</div>
<div class="action-buttons">
<button type="submit" name="salvar_conta" class="action-btn btn-save"><i class='bx bx-save'></i> Salvar Alterações</button>
</div>
</form>
</div>

<!-- ABA SEGURANÇA -->
<div id="tab-seguranca" class="tab-content">
<form method="POST">
<div class="form-grid">
<div class="form-field full-width">
<label><i class='bx bx-key' style="color:#fbbf24"></i> Senha Atual</label>
<input type="password" class="form-control" name="senha_atual" placeholder="Digite sua senha atual" autocomplete="off">
</div>
<div class="form-field">
<label><i class='bx bx-lock-alt' style="color:#e879f9"></i> Nova Senha</label>
<input type="password" class="form-control" name="nova_senha" placeholder="Nova senha" autocomplete="off">
</div>
<div class="form-field">
<label><i class='bx bx-check-shield' style="color:#34d399"></i> Confirmar</label>
<input type="password" class="form-control" name="confirmar_senha" placeholder="Confirme" autocomplete="off">
</div>
</div>
<div class="password-req">
<i class='bx bx-info-circle'></i> <strong>Requisitos:</strong>
<ul><li>5 a 10 caracteres</li><li>Apenas letras e números</li></ul>
</div>
<div class="action-buttons">
<button type="submit" name="alterar_senha" class="action-btn btn-orange"><i class='bx bx-key'></i> Alterar Senha</button>
</div>
</form>
</div>

<!-- ABA TELEGRAM -->
<div id="tab-telegram" class="tab-content">
<div class="tg-info">
<strong><i class='bx bxl-telegram' style="color:#54a9eb"></i> Como configurar:</strong><br><br>
<div class="tg-step"><div class="tg-step-num">1</div><span>Procure <strong>@BotFather</strong> no Telegram</span></div>
<div class="tg-step"><div class="tg-step-num">2</div><span>Envie <strong>/newbot</strong> e siga as instruções</span></div>
<div class="tg-step"><div class="tg-step-num">3</div><span>Copie o <strong>Token</strong> e cole abaixo</span></div>
<div class="tg-step"><div class="tg-step-num">4</div><span>Para seu <strong>ID</strong>, use <strong>@userinfobot</strong></span></div>
<div class="tg-step"><div class="tg-step-num">5</div><span>Após salvar, envie <strong>/start</strong> no seu bot</span></div>
</div>
<form method="POST">
<div class="form-grid">
<div class="form-field full-width">
<label><i class='bx bxl-telegram' style="color:#54a9eb"></i> Token do Bot</label>
<input type="text" class="form-control" name="tokenbot" value="<?php echo htmlspecialchars($user_data['token'] ?? '');?>" placeholder="123456789:AABBccDDee...">
</div>
<div class="form-field">
<label><i class='bx bx-id-card' style="color:#54a9eb"></i> Seu ID Telegram</label>
<input type="number" class="form-control" name="idtelegram" value="<?php echo htmlspecialchars($user_data['idtelegram'] ?? '');?>" placeholder="123456789">
</div>
<div class="form-field">
<label><i class='bx bx-time' style="color:#fbbf24"></i> Limite Teste (min)</label>
<input type="number" class="form-control" name="limitetest" value="<?php echo htmlspecialchars($user_data['mb'] ?? '60');?>" placeholder="60" min="1">
</div>
</div>
<?php if($has_telegram):?>
<div style="margin-top:12px">
<button type="button" class="action-btn btn-teal" onclick="testarBot()"><i class='bx bxl-telegram'></i> Testar Bot</button>
<span id="tg-test-result" style="margin-left:10px;font-size:11px;color:rgba(255,255,255,.5)"></span>
</div>
<?php endif;?>
<div class="action-buttons">
<button type="submit" name="salvar_telegram" class="action-btn btn-teal"><i class='bx bx-save'></i> Salvar Telegram</button>
</div>
</form>
</div>

<!-- ABA WHATSAPP -->
<div id="tab-mensagens" class="tab-content">
<form method="POST">
<div class="form-grid">
<?php
$wpp_campos = [
    ['criarusuario','bx-user-plus','#818cf8','Criar Usuário'],
    ['criarteste','bx-test-tube','#34d399','Criar Teste'],
    ['criarrevenda','bx-store-alt','#fbbf24','Criar Revenda'],
    ['contaexpirada','bx-calendar-x','#f87171','Conta Expirada'],
    ['revendaexpirada','bx-store','#f59e0b','Revenda Expirada'],
];
foreach($wpp_campos as $c):
?>
<div class="form-field full-width">
<label><i class='bx <?php echo $c[1];?>' style="color:<?php echo $c[2];?>"></i> <?php echo $c[3];?> (WhatsApp)</label>
<textarea class="form-control" name="mensagem_whatsapp_<?php echo $c[0];?>" rows="3" placeholder="Mensagem ao <?php echo strtolower($c[3]);?>..."><?php echo htmlspecialchars($whatsapp_mensagens[$c[0]]['mensagem'] ?? '');?></textarea>
<div class="msg-preview"><i class='bx bx-info-circle'></i> Variáveis: {usuario}, {senha}, {validade}, {limite}, {dominio}</div>
</div>
<?php endforeach;?>
</div>
<div class="action-buttons">
<button type="submit" name="salvar_mensagens_whatsapp" class="action-btn btn-save"><i class='bx bx-save'></i> Salvar WhatsApp</button>
</div>
</form>
</div>

<!-- ABA MODAL -->
<div id="tab-modal-msg" class="tab-content">
<form method="POST">
<div class="form-grid">
<?php
$modal_campos = [
    ['criarusuario','bx-user-plus','#818cf8','Criar Usuário'],
    ['criarteste','bx-test-tube','#34d399','Criar Teste'],
    ['criarrevenda','bx-store-alt','#fbbf24','Criar Revenda'],
];
foreach($modal_campos as $c):
?>
<div class="form-field full-width">
<label><i class='bx <?php echo $c[1];?>' style="color:<?php echo $c[2];?>"></i> <?php echo $c[3];?> (Modal)</label>
<textarea class="form-control" name="mensagem_modal_<?php echo $c[0];?>" rows="3" placeholder="Texto no modal após <?php echo strtolower($c[3]);?>..."><?php echo htmlspecialchars($modal_mensagens[$c[0]] ?? '');?></textarea>
<div class="msg-preview"><i class='bx bx-info-circle'></i> Variáveis: {usuario}, {senha}, {validade}, {limite}, {dominio}</div>
</div>
<?php endforeach;?>
</div>
<div class="action-buttons">
<button type="submit" name="salvar_mensagens_modal" class="action-btn btn-save"><i class='bx bx-save'></i> Salvar Modal</button>
</div>
</form>
</div>

</div></div>

</div></div>

<!-- MODAL SUCESSO -->
<div id="modalSucesso" class="modal-overlay <?php echo ($show_modal_fb && $modal_type=='success')?'show':'';?>">
<div class="modal-container"><div class="modal-content-custom">
<div class="modal-header-custom success"><h5><i class='bx bx-check-circle'></i> Sucesso!</h5><button class="modal-close" onclick="fecharModal('modalSucesso')"><i class='bx bx-x'></i></button></div>
<div class="modal-body-custom">
<div class="modal-ic success"><i class='bx bx-check-circle'></i></div>
<p style="font-size:14px;font-weight:700;margin-bottom:6px">Operação realizada!</p>
<p style="font-size:12px;color:rgba(255,255,255,.6)"><?php echo $mensagem_sucesso;?></p>
</div>
<div class="modal-footer-custom">
<button class="btn-modal btn-modal-ok" onclick="fecharModal('modalSucesso')"><i class='bx bx-check'></i> OK</button>
</div>
</div></div>
</div>

<!-- MODAL ERRO -->
<div id="modalErro" class="modal-overlay <?php echo ($show_modal_fb && $modal_type=='error')?'show':'';?>">
<div class="modal-container"><div class="modal-content-custom">
<div class="modal-header-custom error"><h5><i class='bx bx-error-circle'></i> Erro!</h5><button class="modal-close" onclick="fecharModal('modalErro')"><i class='bx bx-x'></i></button></div>
<div class="modal-body-custom">
<div class="modal-ic error"><i class='bx bx-error-circle'></i></div>
<p style="font-size:14px;font-weight:700;margin-bottom:6px">Ops! Algo deu errado</p>
<p style="font-size:12px;color:rgba(255,255,255,.6)"><?php echo $mensagem_erro;?></p>
</div>
<div class="modal-footer-custom">
<button class="btn-modal btn-modal-danger" onclick="fecharModal('modalErro')"><i class='bx bx-check'></i> OK</button>
</div>
</div></div>
</div>

<script>
function previewImage(input){
    if(input.files&&input.files[0]){
        var r=new FileReader();
        r.onload=function(e){document.getElementById('profile-avatar-preview').src=e.target.result};
        r.readAsDataURL(input.files[0]);
    }
}

var TABS=['conta','seguranca','telegram','mensagens','modal-msg'];
function switchTab(tab){
    TABS.forEach(function(t){document.getElementById('tab-'+t).classList.remove('active')});
    document.querySelectorAll('.tab-btn').forEach(function(b){b.classList.remove('active','tg')});
    document.getElementById('tab-'+tab).classList.add('active');
    var idx={'conta':0,'seguranca':1,'telegram':2,'mensagens':3,'modal-msg':4}[tab];
    var btn=document.querySelectorAll('.tab-btn')[idx];
    btn.classList.add('active');
    if(tab==='telegram')btn.classList.add('tg');
}

function fecharModal(id){
    document.getElementById(id).classList.remove('show');
    if(window.history&&window.history.replaceState)
        window.history.replaceState({},document.title,window.location.href.split('?')[0]);
}

function testarBot(){
    var el=document.getElementById('tg-test-result');
    el.textContent='⏳ Testando...';el.style.color='rgba(255,255,255,.5)';
    fetch('ajax_test_telegram.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'test=1'})
        .then(function(r){return r.json()})
        .then(function(d){el.textContent=d.ok?'✅ '+d.msg:'❌ '+d.msg;el.style.color=d.ok?'#10b981':'#dc2626'})
        .catch(function(){el.textContent='❌ Erro de conexão';el.style.color='#dc2626'});
}

document.querySelectorAll('.modal-overlay').forEach(function(o){o.addEventListener('click',function(e){if(e.target===o)o.classList.remove('show')})});
document.addEventListener('keydown',function(e){if(e.key==='Escape'){fecharModal('modalSucesso');fecharModal('modalErro')}});

<?php if($show_modal_fb && $modal_type==='success' && isset($_POST['salvar_telegram'])):?>
document.addEventListener('DOMContentLoaded',function(){switchTab('telegram')});
<?php endif;?>
</script>
</body>
</html>

