<?php
session_start();
error_reporting(0);
date_default_timezone_set('America/Sao_Paulo');

include('../AegisCore/conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if ($conn) $conn->set_charset("utf8mb4");

// Garante que as colunas existem (auto-migração)
$conn->query("ALTER TABLE configs ADD COLUMN IF NOT EXISTS evo_apiurl VARCHAR(255) DEFAULT NULL");
$conn->query("ALTER TABLE configs ADD COLUMN IF NOT EXISTS evo_token  VARCHAR(255) DEFAULT NULL");

if (!file_exists('suspenderrev.php')) exit("<script>alert('Token Invalido!');</script>");
else include_once 'suspenderrev.php';

if (!isset($_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe']) || !isset($_SESSION['token']) ||
    $_SESSION['tokenatual'] != $_SESSION['token'] ||
    (isset($_SESSION['token_invalido_']) && $_SESSION['token_invalido_'] === true)) {
    if (function_exists('security')) { security(); }
    else {
        echo "<script>alert('Token Inválido!');location.href='../index.php';</script>";
        exit;
    }
}

$msg  = '';
$tipo = '';

// ── AJAX: testar conexão com a API ──────────────────────────────
if (isset($_GET['ajax']) && $_GET['ajax'] === 'testar') {
    header('Content-Type: application/json; charset=utf-8');
    $url = rtrim(trim($_POST['apiurl'] ?? ''), '/');
    $tok = trim($_POST['token'] ?? '');
    if (empty($url) || empty($tok)) { echo json_encode(['ok'=>false,'msg'=>'Preencha URL e Token!']); exit; }
    if (!preg_match('#^https?://#i', $url)) $url = 'http://' . $url;
    $ch = curl_init($url . '/instance/fetchInstances');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_HTTPHEADER     => ['apikey: '.$tok, 'Accept: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($err)             { echo json_encode(['ok'=>false, 'msg'=>'Erro cURL: '.$err]); exit; }
    if ($code >= 200 && $code < 300) {
        $d = json_decode($resp, true);
        $count = is_array($d) ? count($d) : '?';
        echo json_encode(['ok'=>true, 'msg'=>'✅ API conectada! '.$count.' instância(s) encontrada(s).', 'code'=>$code]);
    } else {
        echo json_encode(['ok'=>false, 'msg'=>'HTTP '.$code.'. Verifique URL e token.', 'raw'=>mb_substr($resp,0,200)]);
    }
    exit;
}

// ── SALVAR ───────────────────────────────────────────────────────
if (isset($_POST['salvar'])) {
    $apiurl = mysqli_real_escape_string($conn, trim($_POST['evo_apiurl'] ?? ''));
    $token  = mysqli_real_escape_string($conn, trim($_POST['evo_token']  ?? ''));

    // Remove trailing slash
    $apiurl = rtrim($apiurl, '/');

    $r = $conn->query("SELECT id FROM configs LIMIT 1");
    if ($r && $r->num_rows > 0) {
        $conn->query("UPDATE configs SET evo_apiurl='$apiurl', evo_token='$token' LIMIT 1");
    } else {
        $conn->query("INSERT INTO configs (evo_apiurl, evo_token) VALUES ('$apiurl','$token')");
    }
    $msg  = '✅ Configuração salva! Todos os revendedores já podem usar a API.';
    $tipo = 'ok';
}

// ── CARREGAR ATUAL ────────────────────────────────────────────────
$r      = $conn->query("SELECT evo_apiurl, evo_token FROM configs LIMIT 1");
$cfg    = ($r && $r->num_rows > 0) ? $r->fetch_assoc() : [];
$apiurl = $cfg['evo_apiurl'] ?? '';
$token  = $cfg['evo_token']  ?? '';
$tem    = !empty($apiurl) && !empty($token);

include('headeradmin2.php');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Configurar Evolution API</title>
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Rubik',sans-serif;min-height:100vh;}
.app-content{margin-left:10px!important;padding:0!important;}
.content-wrapper{max-width:780px;margin:0 auto 0 5px!important;padding:0!important;}
.content-header{display:none!important;}

.info-badge{display:inline-flex!important;align-items:center!important;gap:8px!important;background:white!important;color:#2c3e50!important;padding:8px 16px!important;border-radius:30px!important;font-size:13px!important;margin-top:5px!important;margin-bottom:15px!important;border-left:4px solid #25D366!important;box-shadow:0 3px 10px rgba(0,0,0,.1)!important;}
.info-badge i{font-size:22px;color:#25D366;}

.modern-card{background:linear-gradient(135deg,#1e293b,#0f172a)!important;border-radius:20px!important;border:1px solid rgba(255,255,255,.08)!important;overflow:hidden!important;position:relative!important;box-shadow:0 15px 40px rgba(0,0,0,.4)!important;width:100%!important;animation:fadeIn .5s ease!important;margin-bottom:20px!important;}
@keyframes fadeIn{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}

.card-header{padding:16px 20px 12px!important;border-bottom:1px solid rgba(255,255,255,.07)!important;display:flex!important;align-items:center!important;gap:10px!important;}
.header-icon{width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#25D366,#128C7E);display:flex;align-items:center;justify-content:center;font-size:18px;color:white;flex-shrink:0;}
.header-title{font-size:14px;font-weight:700;color:white;}
.header-subtitle{font-size:10px;color:rgba(255,255,255,.35);}
.card-body{padding:20px!important;}

.fg{margin-bottom:16px;}
.fg label{display:flex;align-items:center;gap:6px;font-size:9px;font-weight:700;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;}
.fc{width:100%;padding:10px 14px;background:rgba(255,255,255,.06);border:1.5px solid rgba(255,255,255,.1);border-radius:12px;color:#fff;font-size:13px;font-family:inherit;outline:none;transition:all .2s;}
.fc:focus{border-color:rgba(37,211,102,.6);background:rgba(255,255,255,.09);}
.fc::placeholder{color:rgba(255,255,255,.2);}

.btn-action{padding:10px 20px!important;border:none!important;border-radius:10px!important;font-weight:700!important;font-size:13px!important;cursor:pointer!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:8px!important;font-family:inherit!important;color:white!important;transition:all .2s!important;}
.btn-success{background:linear-gradient(135deg,#25D366,#128C7E)!important;}
.btn-success:hover{transform:translateY(-2px)!important;}
.btn-test{background:linear-gradient(135deg,#f59e0b,#f97316)!important;}
.btn-test:hover{transform:translateY(-2px)!important;}

.fb{padding:12px 16px;border-radius:12px;font-size:13px;font-weight:500;display:flex;align-items:center;gap:10px;margin-bottom:16px;}
.fb.ok{background:rgba(16,185,129,.12);border:1px solid rgba(16,185,129,.3);color:#10b981;}
.fb.err{background:rgba(220,38,38,.12);border:1px solid rgba(220,38,38,.3);color:#f87171;}

.status-box{display:flex;align-items:center;gap:12px;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:14px;padding:14px 18px;margin-bottom:20px;}
.status-dot{width:12px;height:12px;border-radius:50%;flex-shrink:0;}
.status-dot.on{background:#25D366;box-shadow:0 0 8px rgba(37,211,102,.6);}
.status-dot.off{background:#dc2626;box-shadow:0 0 8px rgba(220,38,38,.5);}
.status-text{font-size:13px;color:rgba(255,255,255,.7);}
.status-text b{color:white;}

.nota{background:rgba(37,211,102,.08);border-left:3px solid #25D366;padding:12px 16px;border-radius:10px;margin-bottom:20px;}
.nota small{color:rgba(255,255,255,.6);font-size:11px;line-height:1.6;}

.action-row{display:flex;gap:10px;flex-wrap:wrap;margin-top:20px;}

#test-res{margin-top:12px;}
.spinner{display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,.3);border-top-color:white;border-radius:50%;animation:spin .8s linear infinite;}
@keyframes spin{to{transform:rotate(360deg)}}

@media(max-width:768px){.app-content{margin-left:0!important;}.content-wrapper{padding:10px!important;}.action-row{flex-direction:column;}.btn-action{width:100%!important;}}
</style>
</head>
<body class="<?php echo htmlspecialchars(getBodyClass($temaAtual ?? ['classe'=>'theme-dark'])); ?>">
<div class="app-content content">
<div class="content-overlay"></div>
<div class="content-wrapper">

    <div class="info-badge">
        <i class='bx bxl-whatsapp'></i>
        <span>Evolution API — Configuração Global</span>
    </div>

    <?php if ($msg): ?>
    <div class="fb <?php echo $tipo; ?>">
        <i class='bx bx-<?php echo $tipo==='ok'?'check-circle':'error-circle'; ?>'></i>
        <?php echo htmlspecialchars($msg); ?>
    </div>
    <?php endif; ?>

    <!-- Status atual -->
    <div class="status-box">
        <div class="status-dot <?php echo $tem?'on':'off'; ?>"></div>
        <div class="status-text">
            <?php if ($tem): ?>
            API configurada: <b><?php echo htmlspecialchars($apiurl); ?></b> — todos os revendedores estão usando esta API.
            <?php else: ?>
            <b>API não configurada.</b> Preencha abaixo para habilitar o WhatsApp em todo o sistema.
            <?php endif; ?>
        </div>
    </div>

    <div class="modern-card">
        <div class="card-header">
            <div class="header-icon"><i class='bx bxl-whatsapp'></i></div>
            <div>
                <div class="header-title">Servidor Evolution API (Global)</div>
                <div class="header-subtitle">Todos os revendedores usam esta configuração automaticamente</div>
            </div>
        </div>
        <div class="card-body">

            <div class="nota">
                <small>
                    ℹ️ <strong>Como funciona:</strong> Você define aqui <b>uma única URL e token</b> da Evolution API.<br>
                    Cada revendedor cria sua própria <b>instância</b> no painel deles, mas todas se conectam a este mesmo servidor.<br>
                    Você não precisa compartilhar o token com ninguém — ele fica salvo aqui no banco.
                </small>
            </div>

            <form method="POST" id="formEvo">
                <div class="fg">
                    <label><i class='bx bx-server' style="color:#38bdf8;"></i> URL da Evolution API</label>
                    <input type="text" class="fc" name="evo_apiurl" id="inp_url"
                        value="<?php echo htmlspecialchars($apiurl); ?>"
                        placeholder="ex: meuservidor.com.br:8080">
                    <small style="color:rgba(255,255,255,.3);font-size:10px;margin-top:4px;display:block;">
                        Sem https:// — apenas o domínio/IP e porta. Ex: <code style="color:#a78bfa;">evolution.meupainel.com</code>
                    </small>
                </div>
                <div class="fg">
                    <label><i class='bx bx-key' style="color:#fbbf24;"></i> Token (Global API Key)</label>
                    <input type="text" class="fc" name="evo_token" id="inp_tok"
                        value="<?php echo htmlspecialchars($token); ?>"
                        placeholder="Seu token global da Evolution API">
                </div>

                <div id="test-res"></div>

                <div class="action-row">
                    <button type="button" class="btn-action btn-test" onclick="testarAPI()">
                        <i class='bx bx-wifi'></i> Testar Conexão
                    </button>
                    <button type="submit" name="salvar" class="btn-action btn-success">
                        <i class='bx bx-save'></i> Salvar para Todos
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
</div>

<script>
function testarAPI() {
    var url = document.getElementById('inp_url').value.trim();
    var tok = document.getElementById('inp_tok').value.trim();
    var res = document.getElementById('test-res');
    if (!url || !tok) { res.innerHTML = '<div class="fb err"><i class="bx bx-error-circle"></i> Preencha URL e Token antes de testar!</div>'; return; }
    res.innerHTML = '<div class="fb ok"><div class="spinner"></div> Testando conexão...</div>';
    var fd = new FormData();
    fd.append('apiurl', url);
    fd.append('token', tok);
    fetch('configevo.php?ajax=testar', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(r => r.json())
        .then(d => {
            res.innerHTML = '<div class="fb ' + (d.ok ? 'ok' : 'err') + '"><i class="bx bx-' + (d.ok ? 'check-circle' : 'error-circle') + '"></i>' + d.msg + '</div>';
        })
        .catch(e => { res.innerHTML = '<div class="fb err"><i class="bx bx-error-circle"></i>' + e.message + '</div>'; });
}
</script>
</body>
</html>

