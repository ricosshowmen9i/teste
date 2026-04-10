<?php
ob_start();
session_start();
error_reporting(0);

date_default_timezone_set('America/Sao_Paulo');

include('../AegisCore/conexao.php');
include('../AegisCore/functions.whatsapp.php'); // já contém enviarWhatsAppEvolution()

// React EventLoop — só inclui se existir
if (file_exists('../vendor/event/autoload.php')) {
    include('../vendor/event/autoload.php');
}

// Sistema de temas
if (file_exists('../AegisCore/temas.php')) {
    include_once '../AegisCore/temas.php';
    $temaAtual  = initTemas($conn ?? null);
    $listaTemas = getListaTemas($conn ?? null);
} else {
    $temaAtual  = [];
    $listaTemas = [];
}

set_time_limit(0);
ignore_user_abort(true);

$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) die("Connection failed: " . mysqli_connect_error());

if (isset($_SESSION['mensagem_enviada'])) unset($_SESSION['mensagem_enviada']);
$datahoje = date('d-m-Y H:i:s');
unset($_SESSION['whatsapp']);

function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff),
        mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000,
        mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff));
}

function getServidorToken($conn, $servidor_id) {
    $sql = "SELECT token FROM servidor_tokens WHERE servidor_id='$servidor_id' AND status='ativo' ORDER BY id DESC LIMIT 1";
    $r   = mysqli_query($conn, $sql);
    if ($r && mysqli_num_rows($r) > 0) return mysqli_fetch_assoc($r)['token'];
    return md5($_SESSION['token'] ?? '');
}

function anti_sql($input) {
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function($m){ return ''; }, $input);
    return addslashes(strip_tags(trim($seg)));
}

// ══════════════════════════════════════════════
// Variáveis de controle
// ══════════════════════════════════════════════
$show_modal          = false;
$show_error_modal    = false;
$error_message       = '';
$modal_usuario       = '';
$modal_senha         = '';
$modal_limite        = '';
$modal_validade      = '';
$modal_validade_data = '';
$modal_whatsapp      = '';
$modal_valor_revenda = '0';
$mensagem_final      = '';
$wpp_enviado         = false;

// ══════════════════════════════════════════════
// Segurança
// ══════════════════════════════════════════════
if (!file_exists('suspenderrev.php')) exit("<script>alert('Token Invalido!');</script>");
else include_once 'suspenderrev.php';

if (!isset($_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe']) || !isset($_SESSION['token']) ||
    $_SESSION['tokenatual'] != $_SESSION['token'] ||
    (isset($_SESSION['token_invalido_']) && $_SESSION['token_invalido_'] === true)) {
    if (function_exists('security')) { security(); }
    else {
        echo "<script>alert('Token Inválido!');location.href='../index.php';</script>";
        $_SESSION['token_invalido_'] = true; exit;
    }
}

// ══════════════════════════════════════════════
// Dados do admin logado (limite/crédito)
// ══════════════════════════════════════════════
$limiteatual = 0;
$r_lim = $conn->prepare("SELECT limite FROM atribuidos WHERE userid = ?");
$r_lim->bind_param("s", $_SESSION['iduser']);
$r_lim->execute();
$r_lim->bind_result($limiteatual);
$r_lim->fetch();
$r_lim->close();

$limiteusado = 0;
$r_lu = $conn->prepare("SELECT COALESCE(sum(limite),0) FROM atribuidos WHERE byid=?");
$r_lu->bind_param("s", $_SESSION['iduser']);
$r_lu->execute();
$r_lu->bind_result($limiteusado);
$r_lu->fetch();
$r_lu->close();

$r_nu = $conn->prepare("SELECT COUNT(*) FROM ssh_accounts WHERE byid=?");
$r_nu->bind_param("s", $_SESSION['iduser']);
$r_nu->execute();
$r_nu->bind_result($numusuarios);
$r_nu->fetch();
$r_nu->close();

$limiteusado = ($limiteusado ?? 0) + $numusuarios;
$restante = ($_SESSION['limite'] ?? 0) - $limiteusado;
$_SESSION['restante']    = $restante;
$_SESSION['limiteusado'] = $limiteusado;

$r_atrib   = $conn->query("SELECT * FROM atribuidos WHERE userid='{$_SESSION['iduser']}'");
$row_atrib = $r_atrib ? $r_atrib->fetch_assoc() : [];
$tipo_conta = $row_atrib['tipo'] ?? 'Validade';
$_SESSION['tipodeconta'] = $tipo_conta;
$_SESSION['limite']      = $row_atrib['limite'] ?? ($_SESSION['limite'] ?? 0);

if ($tipo_conta == 'Credito') {
    $tipo_txt = 'Restam ' . $_SESSION['limite'] . ' Créditos';
} else {
    $tipo_txt = 'Limite usado: ' . $limiteusado . ' de ' . $_SESSION['limite'];
}

$sem_limite = false;
if ($tipo_conta == 'Credito' && (int)$_SESSION['limite'] <= 0) {
    $sem_limite       = true;
    $error_message    = 'Você não tem créditos disponíveis!';
    $show_error_modal = true;
}

// ══════════════════════════════════════════════
// POST — Criar revendedor
// ══════════════════════════════════════════════
if (!$sem_limite && isset($_POST['submit'])) {
    $usuariorevenda  = anti_sql($_POST['usuariorevenda']  ?? '');
    $senharevenda    = anti_sql($_POST['senharevenda']    ?? '');
    $limiterevenda   = anti_sql($_POST['limiterevenda']   ?? '1');
    $validaderevenda = anti_sql($_POST['validaderevenda'] ?? '30');
    $credivalid      = anti_sql($_POST['credivalid']      ?? 'Validade');
    $categoria       = anti_sql($_POST['categoria']       ?? '');
    $whatsapp_dest   = preg_replace('/\D/', '', $_POST['whatsapp'] ?? '');
    $valor_revenda   = anti_sql($_POST['valor_revenda']   ?? '0');

    if      (empty($usuariorevenda))                        $error_message = 'Preencha todos os campos obrigatórios!';
    elseif  (empty($senharevenda))                          $error_message = 'Preencha todos os campos obrigatórios!';
    elseif  (empty($limiterevenda))                         $error_message = 'Preencha todos os campos obrigatórios!';
    elseif  (strlen($usuariorevenda) < 3)                   $error_message = 'Usuário deve ter no mínimo 3 caracteres!';
    elseif  (strlen($usuariorevenda) > 10)                  $error_message = 'Usuário deve ter no máximo 10 caracteres!';
    elseif  (strlen($senharevenda) < 3)                     $error_message = 'Senha deve ter no mínimo 3 caracteres!';
    elseif  (strlen($senharevenda) > 10)                    $error_message = 'Senha deve ter no máximo 10 caracteres!';
    elseif  (preg_match('/[^a-z0-9]/i', $usuariorevenda))  $error_message = 'Usuário não pode conter caracteres especiais!';
    elseif  (preg_match('/[^a-z0-9]/i', $senharevenda))    $error_message = 'Senha não pode conter caracteres especiais!';

    if (!$error_message) {
        $chk = $conn->query("SELECT id FROM accounts WHERE login='$usuariorevenda'");
        if ($chk && $chk->num_rows > 0) $error_message = 'Revendedor já existe!';
    }

    if ($error_message) {
        $show_error_modal = true;
    } else {
        $conn->query("INSERT INTO accounts (login, senha, byid, whatsapp, valorrevenda)
                      VALUES ('$usuariorevenda','$senharevenda','{$_SESSION['iduser']}','$whatsapp_dest','$valor_revenda')");

        $r_id      = $conn->query("SELECT id FROM accounts WHERE login='$usuariorevenda'");
        $idrevenda = $r_id->fetch_assoc()['id'];

        $validade_formatada = '';
        $data_validade      = '';

        if ($credivalid == 'Credito') {
            $validade_formatada = 'Nunca';
            $conn->query("INSERT INTO atribuidos (userid, byid, limite, categoriaid, tipo)
                          VALUES ('$idrevenda','{$_SESSION['iduser']}','$limiterevenda','$categoria','$credivalid')");
        } else {
            $validade_formatada = $validaderevenda . ' dias';
            $data_validade = date('Y-m-d H:i:s', strtotime("+$validaderevenda days"));
            $conn->query("INSERT INTO atribuidos (userid, byid, limite, expira, categoriaid, tipo)
                          VALUES ('$idrevenda','{$_SESSION['iduser']}','$limiterevenda','$data_validade','$categoria','$credivalid')");
        }

        $conn->query("INSERT INTO logs (revenda, validade, texto, userid)
                      VALUES ('{$_SESSION['login']}','$datahoje',
                      'Criou o Revendedor $usuariorevenda com $validade_formatada','{$_SESSION['iduser']}')");

        // WhatsApp
        if (!empty($whatsapp_dest)) {
            $sql_msg = "SELECT mensagem FROM mensagens WHERE funcao='criarrevenda'
                        AND (byid='{$_SESSION['iduser']}' OR byid='')
                        AND ativo='ativada' ORDER BY byid DESC LIMIT 1";
            $r_msg = mysqli_query($conn, $sql_msg);
            if ($r_msg && mysqli_num_rows($r_msg) > 0) {
                $msg_template = mysqli_fetch_assoc($r_msg)['mensagem'];
            } else {
                $msg_template = "🎉 Revendedor Criado!\n\n👤 Usuário: {usuario}\n🔑 Senha: {senha}\n📅 Validade: {validade}\n🔗 Limite: {limite}\n\n🌐 https://{dominio}/";
            }
            $msg_final = str_replace(
                ['{usuario}','{login}','{senha}','{validade}','{limite}','{dominio}'],
                [$usuariorevenda,$usuariorevenda,$senharevenda,$validade_formatada,$limiterevenda,$_SERVER['HTTP_HOST']],
                $msg_template
            );
            $wpp_enviado = enviarWhatsAppEvolution($conn, $_SESSION['iduser'], $whatsapp_dest, $msg_final);
        }

        // Mensagem modal personalizada
        $mensagem_modal = '';
        $r_mm = mysqli_query($conn, "SELECT mensagem FROM mensagens_modal
                                     WHERE funcao='criarrevenda' AND byid='{$_SESSION['iduser']}' AND ativo='ativada' LIMIT 1");
        if ($r_mm && mysqli_num_rows($r_mm) > 0) $mensagem_modal = mysqli_fetch_assoc($r_mm)['mensagem'];
        if (!empty($mensagem_modal)) {
            $mensagem_final = str_replace(
                ['{usuario}','{login}','{senha}','{validade}','{limite}','{valor}','{dominio}'],
                [$usuariorevenda,$usuariorevenda,$senharevenda,$validade_formatada,$limiterevenda,
                 number_format(floatval($valor_revenda),2,',','.'),$_SERVER['HTTP_HOST']],
                $mensagem_modal
            );
            $mensagem_final = nl2br(htmlspecialchars($mensagem_final));
        }

        $show_modal          = true;
        $modal_usuario       = $usuariorevenda;
        $modal_senha         = $senharevenda;
        $modal_limite        = $limiterevenda;
        $modal_validade      = $validade_formatada;
        $modal_validade_data = $data_validade;
        $modal_whatsapp      = $whatsapp_dest;
        $modal_valor_revenda = $valor_revenda;
    }
}

include('headeradmin2.php');
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Criar Revendedor</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
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
<style>
<?php
if (function_exists('getCSSVariables')) {
    echo getCSSVariables($temaAtual);
} else {
    echo ':root{--primaria:#4158D0;--secundaria:#C850C0;--fundo:#0f172a;--fundo_claro:#1e293b;--texto:#ffffff;--sucesso:#10b981;--erro:#dc2626;--aviso:#f59e0b;--info:#3b82f6;}';
}
?>

*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Inter',sans-serif;background:var(--fundo,#0f172a);color:var(--texto,#fff);min-height:100vh;}
.app-content{margin-left:-670px!important;padding:0!important;}
.content-wrapper{max-width:1000px;margin:0 auto!important;padding:20px!important;}

/* ── Stats Card (header) ── */
.stats-card{
    background:linear-gradient(135deg,var(--fundo_claro,#1e293b),var(--fundo,#0f172a));
    border-radius:20px;padding:20px 24px;margin-bottom:24px;
    border:1px solid rgba(255,255,255,0.08);
    display:flex;align-items:center;gap:20px;
    position:relative;overflow:hidden;transition:all .3s;
}
.stats-card:hover{transform:translateY(-2px);border-color:var(--secundaria,#C850C0);}
.stats-card-icon{
    width:60px;height:60px;
    background:linear-gradient(135deg,var(--secundaria,#C850C0),var(--primaria,#4158D0));
    border-radius:18px;display:flex;align-items:center;justify-content:center;
    font-size:32px;color:white;flex-shrink:0;
}
.stats-card-content{flex:1;}
.stats-card-title{font-size:13px;font-weight:600;color:rgba(255,255,255,.5);text-transform:uppercase;margin-bottom:5px;}
.stats-card-value{
    font-size:28px;font-weight:800;
    background:linear-gradient(135deg,#fff,var(--secundaria,#C850C0));
    -webkit-background-clip:text;-webkit-text-fill-color:transparent;line-height:1.2;
}
.stats-card-subtitle{font-size:12px;color:rgba(255,255,255,.4);margin-top:4px;}
.stats-card-decoration{position:absolute;right:20px;top:50%;transform:translateY(-50%);font-size:80px;opacity:.05;}

/* ── Modern Card ── */
.modern-card{
    background:var(--fundo_claro,#1e293b);
    border-radius:16px;border:1px solid rgba(255,255,255,.08);
    overflow:hidden;margin-bottom:16px;transition:all .2s;
}
.modern-card:hover{border-color:var(--secundaria,#C850C0);}
.card-header{
    padding:14px 18px;display:flex;align-items:center;gap:12px;
    background:linear-gradient(135deg,var(--secundaria,#C850C0),var(--primaria,#4158D0));
}
.header-icon{
    width:36px;height:36px;background:rgba(255,255,255,.2);
    border-radius:10px;display:flex;align-items:center;justify-content:center;
    font-size:18px;color:white;flex-shrink:0;
}
.header-title{font-size:14px;font-weight:700;color:white;}
.header-subtitle{font-size:10px;color:rgba(255,255,255,.7);}
.limite-badge{
    margin-left:auto;display:inline-flex;align-items:center;gap:5px;
    background:rgba(255,255,255,.15);border-radius:8px;padding:4px 10px;
    font-size:10px;font-weight:600;color:white;
}
.card-body{padding:20px;}

/* ── Botões ── */
.btn{
    padding:8px 16px;border:none;border-radius:10px;font-weight:600;font-size:12px;
    cursor:pointer;display:inline-flex;align-items:center;justify-content:center;
    gap:6px;color:white;transition:all .2s;font-family:inherit;
}
.btn:hover{transform:translateY(-1px);filter:brightness(1.08);}
.btn-primary{background:linear-gradient(135deg,var(--secundaria,#C850C0),var(--primaria,#4158D0));}
.btn-success{background:linear-gradient(135deg,var(--sucesso,#10b981),#059669);}
.btn-danger{background:linear-gradient(135deg,var(--erro,#dc2626),#b91c1c);}
.btn-gray{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.1);}
.btn-gerar{margin-bottom:16px;}

/* ── Formulário ── */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.form-field{display:flex;flex-direction:column;gap:4px;}
.form-field.full-width{grid-column:1/-1;}
.form-field label{
    font-size:9px;font-weight:700;color:rgba(255,255,255,.4);
    text-transform:uppercase;letter-spacing:.5px;
    display:flex;align-items:center;gap:4px;
}
.form-control{
    width:100%;padding:9px 12px;
    background:rgba(255,255,255,.06);border:1.5px solid rgba(255,255,255,.08);
    border-radius:9px;color:white;font-size:12px;font-family:inherit;outline:none;transition:all .25s;
}
.form-control:focus{border-color:var(--secundaria,#C850C0);background:rgba(255,255,255,.09);}
.form-control::placeholder{color:rgba(255,255,255,.2);}
select.form-control option{background:var(--fundo_claro,#1e293b);}

/* ── Seletor de dias ── */
.dias-select{display:grid;grid-template-columns:repeat(5,1fr);gap:6px;margin-top:4px;}
.dia-option{
    background:rgba(255,255,255,.06);border:1.5px solid rgba(255,255,255,.08);
    border-radius:8px;padding:8px 4px;text-align:center;cursor:pointer;transition:all .3s;
    font-size:11px;font-weight:600;color:rgba(255,255,255,.7);
}
.dia-option:hover{background:rgba(255,255,255,.1);border-color:var(--secundaria,#C850C0);}
.dia-option.active{
    background:linear-gradient(135deg,var(--secundaria,#C850C0),var(--primaria,#4158D0));
    color:white;border-color:transparent;
}

/* ── Modo toggle ── */
.modo-toggle{
    display:flex;gap:6px;
    background:rgba(255,255,255,.06);border:1.5px solid rgba(255,255,255,.08);
    border-radius:8px;padding:3px;
}
.modo-option{
    flex:1;padding:7px;text-align:center;border-radius:6px;cursor:pointer;transition:all .3s;
    display:flex;align-items:center;justify-content:center;gap:5px;
    font-weight:600;font-size:11px;color:rgba(255,255,255,.5);
}
.modo-option.active{
    background:linear-gradient(135deg,var(--secundaria,#C850C0),var(--primaria,#4158D0));
    color:white;
}
.modo-option:not(.active):hover{background:rgba(255,255,255,.1);}

.action-buttons{display:flex;justify-content:flex-end;gap:8px;margin-top:20px;flex-wrap:wrap;}

/* ── Modais ── */
.modal-overlay{
    position:fixed;inset:0;background:rgba(0,0,0,.85);
    display:none;align-items:center;justify-content:center;
    z-index:9999;backdrop-filter:blur(8px);padding:16px;
}
.modal-overlay.show{display:flex;}
.modal-container{animation:modalIn .35s cubic-bezier(.34,1.2,.64,1);max-width:520px;width:90%;}
@keyframes modalIn{from{opacity:0;transform:scale(.93) translateY(-20px)}to{opacity:1;transform:scale(1) translateY(0)}}
.modal-content{
    background:var(--fundo_claro,#1e293b);
    border-radius:20px;overflow:hidden;
    border:1px solid rgba(255,255,255,.12);
    box-shadow:0 25px 60px rgba(0,0,0,.6);
}
.modal-header{
    padding:16px 20px;display:flex;align-items:center;justify-content:space-between;
    border-bottom:1px solid rgba(255,255,255,.08);
}
.modal-header h5{margin:0;display:flex;align-items:center;gap:8px;font-size:15px;font-weight:700;color:white;}
.modal-header.success{background:linear-gradient(135deg,var(--sucesso,#10b981),#059669);}
.modal-header.error{background:linear-gradient(135deg,var(--erro,#dc2626),#b91c1c);}
.modal-header.info{background:linear-gradient(135deg,var(--secundaria,#C850C0),var(--primaria,#4158D0));}
.modal-close{background:none;border:none;color:white;font-size:22px;cursor:pointer;opacity:.8;line-height:1;}
.modal-close:hover{opacity:1;}
.modal-body{padding:20px;max-height:68vh;overflow-y:auto;}
.modal-footer{
    border-top:1px solid rgba(255,255,255,.08);
    padding:14px 20px;display:flex;justify-content:center;gap:10px;flex-wrap:wrap;
}
.modal-big-icon{text-align:center;margin-bottom:16px;}
.modal-big-icon i{font-size:60px;filter:drop-shadow(0 0 12px currentColor);}
.modal-big-icon.success i{color:var(--sucesso,#10b981);}
.modal-big-icon.error i{color:var(--erro,#dc2626);}
.modal-big-icon.info i{color:var(--secundaria,#C850C0);}

.modal-info-card{
    background:rgba(255,255,255,.05);border-radius:14px;padding:14px;
    margin-bottom:14px;border:1px solid rgba(255,255,255,.07);
}
.modal-info-row{
    display:flex;align-items:center;justify-content:space-between;
    padding:9px 0;border-bottom:1px solid rgba(255,255,255,.05);
}
.modal-info-row:last-child{border-bottom:none;}
.modal-info-label{font-size:11px;font-weight:600;color:rgba(255,255,255,.6);display:flex;align-items:center;gap:6px;}
.modal-info-label i{font-size:15px;}
.modal-info-value{font-size:12px;font-weight:700;color:white;}
.modal-info-value.credential{
    background:rgba(0,0,0,.35);padding:3px 9px;border-radius:7px;
    font-family:monospace;letter-spacing:.5px;
}
.modal-info-value.green{color:var(--sucesso,#10b981);}
.modal-info-value.orange{color:var(--aviso,#f59e0b);}

.mensagem-box{
    background:rgba(200,80,192,.1);border-left:3px solid var(--secundaria,#C850C0);
    border-radius:10px;padding:12px;margin-top:12px;font-size:12px;line-height:1.6;
    color:rgba(255,255,255,.85);
}
.wpp-badge{
    display:inline-flex;align-items:center;gap:6px;padding:4px 12px;
    border-radius:20px;font-size:11px;font-weight:700;margin-top:8px;
}
.wpp-badge.ok{background:rgba(37,211,102,.15);border:1px solid rgba(37,211,102,.3);color:#25D366;}
.wpp-badge.no{background:rgba(100,116,139,.15);border:1px solid rgba(100,116,139,.25);color:#94a3b8;}

/* ── Toast ── */
.toast-notif{
    position:fixed;bottom:20px;right:20px;color:white;
    padding:10px 18px;border-radius:12px;display:flex;align-items:center;gap:8px;
    z-index:10000;animation:slideIn .3s ease;font-weight:600;font-size:12px;
}
.toast-notif.ok{background:linear-gradient(135deg,var(--sucesso,#10b981),#059669);}
.toast-notif.err{background:linear-gradient(135deg,var(--erro,#dc2626),#b91c1c);}
@keyframes slideIn{from{transform:translateX(110%);opacity:0}to{transform:translateX(0);opacity:1}}

@media(max-width:768px){
    .app-content{margin-left:0!important;}
    .content-wrapper{padding:12px!important;}
    .form-grid{grid-template-columns:1fr;}
    .action-buttons{flex-direction:column;}
    .btn{width:100%;}
    .dias-select{grid-template-columns:repeat(3,1fr);}
    .modal-container{width:95%;}
    .modal-footer{flex-direction:column;}
}
</style>
</head>
<body class="<?php echo htmlspecialchars(getBodyClass($temaAtual ?? ['classe'=>'theme-dark'])); ?>">
<div class="app-content content">
<div class="content-overlay"></div>
<div class="content-wrapper">

<!-- Stats Card Header -->
<div class="stats-card">
    <div class="stats-card-icon"><i class='bx bx-store-alt'></i></div>
    <div class="stats-card-content">
        <div class="stats-card-title">Criar Revendedor</div>
        <div class="stats-card-value"><?php echo $tipo_txt; ?></div>
        <div class="stats-card-subtitle">Preencha os dados para criar um novo revendedor</div>
    </div>
    <div class="stats-card-decoration"><i class='bx bx-store-alt'></i></div>
</div>

<!-- Card do formulário -->
<div class="modern-card">
    <div class="card-header">
        <div class="header-icon"><i class='bx bx-store-alt'></i></div>
        <div style="flex:1;">
            <div class="header-title">Criar Revendedor</div>
            <div class="header-subtitle">Preencha os dados do revendedor</div>
        </div>
        <div class="limite-badge">
            <i class='bx bx-bar-chart-alt'></i>
            <?php echo $tipo_txt; ?>
        </div>
    </div>
    <div class="card-body">
        <?php if (!$sem_limite): ?>
        <button type="button" class="btn btn-primary btn-gerar" onclick="abrirModalGerar()">
            <i class='bx bx-shuffle'></i> Gerar Aleatório
        </button>
        <?php endif; ?>

        <form method="POST" action="criarrevenda.php">
            <div class="form-grid">

                <!-- Categoria -->
                <div class="form-field full-width">
                    <label><i class='bx bx-category' style="color:#FF6B6B;"></i> Categoria</label>
                    <select class="form-control" name="categoria" required <?php echo $sem_limite?'disabled':''; ?>>
                        <?php
                        $r_cat = $conn->query("SELECT * FROM categorias ORDER BY id ASC");
                        $first = true;
                        while ($rc = $r_cat->fetch_assoc()):
                            echo '<option value="'.$rc['subid'].'" '.($first?'selected':'').'>'.$rc['nome'].'</option>';
                            $first = false;
                        endwhile;
                        ?>
                    </select>
                </div>

                <!-- Usuário -->
                <div class="form-field">
                    <label><i class='bx bx-user' style="color:#818cf8;"></i> Usuário (3–10 caracteres)</label>
                    <input type="text" class="form-control" name="usuariorevenda" id="usuariorevenda"
                           placeholder="ex: revenda123" maxlength="10" required <?php echo $sem_limite?'disabled':''; ?>>
                </div>

                <!-- Senha -->
                <div class="form-field">
                    <label><i class='bx bx-lock-alt' style="color:#e879f9;"></i> Senha (3–10 caracteres)</label>
                    <input type="text" class="form-control" name="senharevenda" id="senharevenda"
                           placeholder="ex: senha123" maxlength="10" required <?php echo $sem_limite?'disabled':''; ?>>
                </div>

                <!-- Modo -->
                <div class="form-field">
                    <label><i class='bx bx-credit-card' style="color:#fbbf24;"></i> Modo</label>
                    <div class="modo-toggle">
                        <div class="modo-option active" onclick="selectModo('Validade')" id="modoValidade">
                            <i class='bx bx-calendar' style="color:#34d399;"></i> Validade
                        </div>
                        <div class="modo-option" onclick="selectModo('Credito')" id="modoCredito">
                            <i class='bx bx-coin-stack' style="color:#fbbf24;"></i> Crédito
                        </div>
                    </div>
                    <input type="hidden" name="credivalid" id="modoInput" value="Validade">
                </div>

                <!-- Limite -->
                <div class="form-field">
                    <label><i class='bx bx-layer' style="color:#34d399;"></i> Limite</label>
                    <input type="number" class="form-control" value="1" min="1" name="limiterevenda"
                           id="limiterevenda" required <?php echo $sem_limite?'disabled':''; ?>>
                </div>

                <!-- Dias -->
                <div class="form-field full-width" id="campoValidade">
                    <label><i class='bx bx-calendar' style="color:#fbbf24;"></i> Dias de Validade</label>
                    <input type="hidden" name="validaderevenda" id="validaderevenda" value="30">
                    <div class="dias-select">
                        <div class="dia-option" data-dias="1">1 dia</div>
                        <div class="dia-option" data-dias="7">7 dias</div>
                        <div class="dia-option active" data-dias="30">30 dias</div>
                        <div class="dia-option" data-dias="60">60 dias</div>
                        <div class="dia-option" data-dias="90">90 dias</div>
                    </div>
                </div>

                <!-- Valor do Plano -->
                <div class="form-field">
                    <label><i class='bx bx-dollar' style="color:#10b981;"></i> Valor do Plano (R$)</label>
                    <input type="number" class="form-control" step="0.01" min="0" name="valor_revenda"
                           id="valor_revenda" placeholder="0,00" value="0" <?php echo $sem_limite?'disabled':''; ?>>
                    <small style="color:rgba(255,255,255,.3);font-size:9px;margin-top:3px;display:block;">
                        <i class='bx bx-info-circle'></i> Para controle financeiro (0 = sem valor)
                    </small>
                </div>

                <!-- WhatsApp -->
                <div class="form-field full-width">
                    <label><i class='bx bxl-whatsapp' style="color:#25D366;"></i> WhatsApp do Revendedor</label>
                    <input type="text" class="form-control" name="whatsapp" placeholder="5511999999999"
                           <?php echo $sem_limite?'disabled':''; ?>>
                    <small style="color:rgba(255,255,255,.3);font-size:9px;margin-top:3px;display:block;">
                        <i class='bx bx-info-circle' style="color:#a78bfa;"></i>
                        Com DDI. Ex: 5511999999999 — mensagem enviada automaticamente ao criar
                    </small>
                </div>

            </div>

            <?php if (!$sem_limite): ?>
            <div class="action-buttons">
                <button type="reset" class="btn btn-danger">
                    <i class='bx bx-x'></i> Cancelar
                </button>
                <button type="submit" name="submit" class="btn btn-success">
                    <i class='bx bx-check'></i> Criar Revendedor
                </button>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

</div><!-- /content-wrapper -->
</div><!-- /app-content -->

<!-- ═══════════════════════════════════════
     MODAL: GERAR ALEATÓRIO
═══════════════════════════════════════ -->
<div id="modalGerar" class="modal-overlay">
<div class="modal-container"><div class="modal-content">
    <div class="modal-header info">
        <h5><i class='bx bx-shuffle'></i> Dados Gerados!</h5>
        <button class="modal-close" onclick="fecharModal('modalGerar')"><i class='bx bx-x'></i></button>
    </div>
    <div class="modal-body">
        <div class="modal-big-icon info"><i class='bx bx-shuffle'></i></div>
        <div class="modal-info-card">
            <div class="modal-info-row">
                <div class="modal-info-label"><i class='bx bx-store-alt' style="color:#818cf8;"></i> Login</div>
                <div class="modal-info-value credential" id="gerar-login-preview">—</div>
            </div>
            <div class="modal-info-row">
                <div class="modal-info-label"><i class='bx bx-lock-alt' style="color:#e879f9;"></i> Senha</div>
                <div class="modal-info-value credential" id="gerar-senha-preview">—</div>
            </div>
            <div class="modal-info-row">
                <div class="modal-info-label"><i class='bx bx-group' style="color:#34d399;"></i> Limite</div>
                <div class="modal-info-value">1 conexão</div>
            </div>
            <div class="modal-info-row">
                <div class="modal-info-label"><i class='bx bx-calendar' style="color:#fbbf24;"></i> Dias</div>
                <div class="modal-info-value">30 dias</div>
            </div>
        </div>
        <p style="text-align:center;color:rgba(255,255,255,.4);font-size:11px;">Campos preenchidos automaticamente.</p>
    </div>
    <div class="modal-footer">
        <button class="btn btn-success" onclick="fecharModal('modalGerar')">
            <i class='bx bx-check'></i> OK, usar esses dados
        </button>
        <button class="btn btn-gray" onclick="gerarNovamente()">
            <i class='bx bx-refresh'></i> Gerar outros
        </button>
    </div>
</div></div>
</div>

<!-- ═══════════════════════════════════════
     MODAL: SUCESSO
═══════════════════════════════════════ -->
<div id="modalSucesso" class="modal-overlay <?php echo $show_modal ? 'show' : ''; ?>">
<div class="modal-container"><div class="modal-content">
    <div class="modal-header success">
        <h5><i class='bx bx-check-circle'></i> Revendedor Criado!</h5>
        <button class="modal-close" onclick="fecharModal('modalSucesso')"><i class='bx bx-x'></i></button>
    </div>
    <div class="modal-body" id="divToCopy">
        <div class="modal-big-icon success"><i class='bx bx-check-circle'></i></div>
        <div class="modal-info-card">
            <div class="modal-info-row">
                <div class="modal-info-label"><i class='bx bx-store-alt' style="color:#818cf8;"></i> Usuário</div>
                <div class="modal-info-value credential"><?php echo $show_modal ? htmlspecialchars($modal_usuario) : ''; ?></div>
            </div>
            <div class="modal-info-row">
                <div class="modal-info-label"><i class='bx bx-lock-alt' style="color:#e879f9;"></i> Senha</div>
                <div class="modal-info-value credential"><?php echo $show_modal ? htmlspecialchars($modal_senha) : ''; ?></div>
            </div>
            <div class="modal-info-row">
                <div class="modal-info-label"><i class='bx bx-calendar' style="color:#fbbf24;"></i> Validade</div>
                <div class="modal-info-value orange"><?php echo $show_modal ? htmlspecialchars($modal_validade) : ''; ?></div>
            </div>
            <?php if ($show_modal && !empty($modal_validade_data) && $modal_validade !== 'Nunca'): ?>
            <div class="modal-info-row">
                <div class="modal-info-label"><i class='bx bx-calendar-check' style="color:#fbbf24;"></i> Vencimento</div>
                <div class="modal-info-value green"><?php echo date('d/m/Y', strtotime($modal_validade_data)); ?></div>
            </div>
            <?php endif; ?>
            <div class="modal-info-row">
                <div class="modal-info-label"><i class='bx bx-layer' style="color:#34d399;"></i> Limite</div>
                <div class="modal-info-value"><?php echo $show_modal ? htmlspecialchars($modal_limite) : ''; ?> conexões</div>
            </div>
            <?php if ($show_modal && floatval($modal_valor_revenda) > 0): ?>
            <div class="modal-info-row">
                <div class="modal-info-label"><i class='bx bx-dollar' style="color:#10b981;"></i> Valor do Plano</div>
                <div class="modal-info-value green">R$ <?php echo number_format(floatval($modal_valor_revenda),2,',','.'); ?></div>
            </div>
            <?php endif; ?>
            <?php if ($show_modal && !empty($modal_whatsapp)): ?>
            <div class="modal-info-row">
                <div class="modal-info-label"><i class='bx bxl-whatsapp' style="color:#25D366;"></i> WhatsApp</div>
                <div class="modal-info-value"><?php echo htmlspecialchars($modal_whatsapp); ?></div>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($show_modal && !empty($modal_whatsapp)): ?>
        <div style="text-align:center;">
            <?php if ($wpp_enviado): ?>
                <span class="wpp-badge ok"><i class='bx bxl-whatsapp'></i> WhatsApp enviado!</span>
            <?php else: ?>
                <span class="wpp-badge no"><i class='bx bx-x-circle'></i> WhatsApp não enviado</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($show_modal && !empty($mensagem_final)): ?>
        <div class="mensagem-box"><?php echo $mensagem_final; ?></div>
        <?php endif; ?>
    </div>
    <div class="modal-footer">
          <a href="listarrevendedores.php" class="btn btn-gray">   
            <i class='bx bx-list-ul'></i> Ver Lista</a>
        <button class="btn btn-danger" onclick="fecharModal('modalSucesso')">
            <i class='bx bx-x'></i> Fechar
        </button>
        <button class="btn btn-primary" onclick="copiarDados()">
            <i class='bx bx-copy'></i> Copiar Dados
        </button>
      
        </a>
    </div>
</div></div>
</div>

<!-- ══════���════════════════════════════════
     MODAL: ERRO
═══════════════════════════════════════ -->
<div id="modalErro" class="modal-overlay <?php echo $show_error_modal ? 'show' : ''; ?>">
<div class="modal-container"><div class="modal-content">
    <div class="modal-header error">
        <h5><i class='bx bx-error-circle'></i> Erro!</h5>
        <button class="modal-close" onclick="fecharModal('modalErro')"><i class='bx bx-x'></i></button>
    </div>
    <div class="modal-body">
        <div class="modal-big-icon error"><i class='bx bx-error-circle'></i></div>
        <p style="text-align:center;color:rgba(255,255,255,.85);font-size:13px;line-height:1.6;">
            <?php echo htmlspecialchars($error_message); ?>
        </p>
    </div>
    <div class="modal-footer">
        <button class="btn btn-danger" onclick="fecharModal('modalErro')">
            <i class='bx bx-check'></i> OK
        </button>
    </div>
</div></div>
</div>

<script>
// ── Dados PHP → JS ─────────────────────────────
var MODAL_USUARIO  = <?php echo json_encode($show_modal ? $modal_usuario       : ''); ?>;
var MODAL_SENHA    = <?php echo json_encode($show_modal ? $modal_senha         : ''); ?>;
var MODAL_VALIDADE = <?php echo json_encode($show_modal ? $modal_validade      : ''); ?>;
var MODAL_LIMITE   = <?php echo json_encode($show_modal ? $modal_limite        : ''); ?>;
var MODAL_VALOR    = <?php echo json_encode($show_modal ? $modal_valor_revenda : ''); ?>;
var DOMINIO        = <?php echo json_encode($_SERVER['HTTP_HOST']); ?>;

// ── Modais ──────────────────────────────────────
function abrirModal(id)  { document.getElementById(id).classList.add('show'); }
function fecharModal(id) { document.getElementById(id).classList.remove('show'); }

document.querySelectorAll('.modal-overlay').forEach(function(o){
    o.addEventListener('click', function(e){ if(e.target===o) o.classList.remove('show'); });
});
document.addEventListener('keydown', function(e){
    if(e.key==='Escape') document.querySelectorAll('.modal-overlay.show').forEach(function(m){ m.classList.remove('show'); });
});

// ── Modo Validade / Crédito ─────────────────────
function selectModo(modo) {
    document.getElementById('modoInput').value = modo;
    document.getElementById('modoValidade').classList.toggle('active', modo === 'Validade');
    document.getElementById('modoCredito').classList.toggle('active',  modo === 'Credito');
    document.getElementById('campoValidade').style.display = (modo === 'Validade') ? '' : 'none';
}

// ── Seletor de dias ─────────────────────────────
document.querySelectorAll('.dia-option').forEach(function(el){
    el.addEventListener('click', function(){
        document.querySelectorAll('.dia-option').forEach(function(d){ d.classList.remove('active'); });
        el.classList.add('active');
        document.getElementById('validaderevenda').value = el.getAttribute('data-dias');
    });
});

// ── Gerar aleatório ─────────────────────────────
function gerarStr(len) {
    var c = 'abcdefghijklmnopqrstuvwxyz0123456789', r = '';
    for (var i = 0; i < len; i++) r += c[Math.floor(Math.random() * c.length)];
    return r;
}
function abrirModalGerar() {
    var u = 'rev' + gerarStr(5);
    var s = gerarStr(7);
    document.getElementById('gerar-login-preview').textContent = u;
    document.getElementById('gerar-senha-preview').textContent = s;
    document.getElementById('usuariorevenda').value = u;
    document.getElementById('senharevenda').value   = s;
    document.getElementById('limiterevenda').value  = '1';
    abrirModal('modalGerar');
}
function gerarNovamente() {
    var u = 'rev' + gerarStr(5);
    var s = gerarStr(7);
    document.getElementById('gerar-login-preview').textContent = u;
    document.getElementById('gerar-senha-preview').textContent = s;
    document.getElementById('usuariorevenda').value = u;
    document.getElementById('senharevenda').value   = s;
}

// ── Copiar Dados ────────────────────────────────
function copiarDados() {
    var u = MODAL_USUARIO || document.querySelector('[name="usuariorevenda"]').value;
    var s = MODAL_SENHA   || document.querySelector('[name="senharevenda"]').value;
    var v = MODAL_VALIDADE|| document.querySelector('[name="validaderevenda"]').value + ' dias';
    var l = MODAL_LIMITE  || document.querySelector('[name="limiterevenda"]').value;

    var t = '🏪 REVENDEDOR CRIADO!\n'
          + '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n'
          + '👤 Usuário: ' + u + '\n'
          + '🔑 Senha: '   + s + '\n'
          + '📅 Validade: '+ v + '\n'
          + '🔗 Limite: '  + l + ' conexões\n'
          + '\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n'
          + '🌐 https://' + DOMINIO + '/\n'
          + '📆 ' + new Date().toLocaleString('pt-BR') + '\n';

    navigator.clipboard.writeText(t)
        .then(function(){ mostrarToast('Dados copiados!', false); })
        .catch(function(){ mostrarToast('Erro ao copiar!', true); });
}

// ── Toast ──────────────���────────────────────────
function mostrarToast(msg, erro) {
    var t = document.createElement('div');
    t.className = 'toast-notif ' + (erro ? 'err' : 'ok');
    t.innerHTML = '<i class="bx ' + (erro ? 'bx-error-circle' : 'bx-check-circle') + '"></i>' + msg;
    document.body.appendChild(t);
    setTimeout(function(){ t.remove(); }, 3000);
}
</script>
</body>
</html>
<?php

?>

