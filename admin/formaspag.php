<?php
session_start();
error_reporting(0);
include('../AegisCore/conexao.php');
include('headeradmin2.php');

$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) { die("Connection failed: " . mysqli_connect_error()); }

// Temas
if (file_exists('../AegisCore/temas.php')) {
    include_once '../AegisCore/temas.php';
    $temaAtual = initTemas($conn);
} else { $temaAtual = []; }

// Segurança
if (!file_exists('suspenderrev.php')) { exit("<script>alert('Token Invalido!');</script>"); }
else { include_once 'suspenderrev.php'; }

if (!isset($_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe']) || !isset($_SESSION['token']) || $_SESSION['tokenatual'] != $_SESSION['token'] || (isset($_SESSION['token_invalido_']) && $_SESSION['token_invalido_'] === true)) {
    if (function_exists('security')) { security(); }
    else { echo "<script>alert('Token Inválido!');location.href='../index.php';</script>"; $_SESSION['token_invalido_'] = true; exit; }
}

// Colunas necessárias
$colunas_necessarias = [
    'public_key' => "ALTER TABLE accounts ADD COLUMN public_key VARCHAR(255) DEFAULT NULL",
    'webhook_secret' => "ALTER TABLE accounts ADD COLUMN webhook_secret VARCHAR(255) DEFAULT NULL",
    'descricao_fatura' => "ALTER TABLE accounts ADD COLUMN descricao_fatura VARCHAR(22) DEFAULT 'PAINEL PRO'",
    'desconto_multiplo' => "ALTER TABLE accounts ADD COLUMN desconto_multiplo DECIMAL(5,2) DEFAULT '0.00'"
];
foreach ($colunas_necessarias as $coluna => $sql) {
    $check = $conn->query("SHOW COLUMNS FROM accounts LIKE '$coluna'");
    if ($check && $check->num_rows == 0) $conn->query($sql);
}

function anti_sql($input) {
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function($m){ return ''; }, $input);
    return addslashes(strip_tags(trim($seg)));
}

$iduser = $_SESSION['iduser'];
$result = mysqli_query($conn, "SELECT * FROM accounts WHERE id='$iduser'");
$user = mysqli_fetch_assoc($result);

$access_token = $user['accesstoken'] ?? '';
$public_key = $user['public_key'] ?? '';
$webhook_secret = $user['webhook_secret'] ?? '';
$descricao_fatura = $user['descricao_fatura'] ?? 'PAINEL PRO';
$multiplicador_revenda = $user['valorrevenda'] ?? 3.50;
$multiplicador_usuario = $user['valorusuario'] ?? 15.00;
$desconto_multiplo = $user['desconto_multiplo'] ?? 0;

$msg_sucesso = '';

if (isset($_POST['atualizar_usuarios'])) {
    $novo_mult = anti_sql($_POST['multiplicador_usuario']);
    $novo_desc = anti_sql($_POST['desconto_multiplo']);
    mysqli_query($conn, "UPDATE accounts SET valorusuario='$novo_mult', desconto_multiplo='$novo_desc' WHERE id='$iduser'");
    $multiplicador_usuario = $novo_mult; $desconto_multiplo = $novo_desc;
    $msg_sucesso = 'usuarios';
}
if (isset($_POST['atualizar_revendas'])) {
    $novo_mult = anti_sql($_POST['multiplicador_revenda']);
    mysqli_query($conn, "UPDATE accounts SET valorrevenda='$novo_mult' WHERE id='$iduser'");
    $multiplicador_revenda = $novo_mult;
    $msg_sucesso = 'revendas';
}
if (isset($_POST['atualizar_mercadopago'])) {
    $nat = anti_sql($_POST['access_token']); $npk = anti_sql($_POST['public_key']);
    $nws = anti_sql($_POST['webhook_secret']); $ndf = anti_sql($_POST['descricao_fatura']);
    mysqli_query($conn, "UPDATE accounts SET accesstoken='$nat', public_key='$npk', webhook_secret='$nws', descricao_fatura='$ndf' WHERE id='$iduser'");
    $access_token = $nat; $public_key = $npk; $webhook_secret = $nws; $descricao_fatura = $ndf;
    $msg_sucesso = 'mercadopago';
}

// Stats
$mp_configurado = !empty($access_token) && !empty($public_key);
$webhook_configurado = !empty($webhook_secret);
$dominio = $_SERVER['HTTP_HOST'];

date_default_timezone_set('America/Sao_Paulo');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Configurações de Pagamento</title>
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
.app-content{margin-left:-670px!important;padding:0!important;}
.content-wrapper{max-width:1000px;margin:0 auto!important;padding:20px!important;}

/* Stats */
.stats-card{background:linear-gradient(135deg,var(--fundo_claro,#1e293b),var(--fundo,#0f172a));border-radius:20px;padding:20px 24px;margin-bottom:20px;border:1px solid rgba(255,255,255,0.08);display:flex;align-items:center;gap:20px;position:relative;overflow:hidden;transition:all .3s;}
.stats-card:hover{transform:translateY(-2px);border-color:var(--primaria,#4158D0);}
.stats-card-icon{width:60px;height:60px;background:linear-gradient(135deg,var(--primaria,#4158D0),var(--secundaria,#C850C0));border-radius:18px;display:flex;align-items:center;justify-content:center;font-size:32px;color:white;flex-shrink:0;}
.stats-card-content{flex:1;}
.stats-card-title{font-size:13px;font-weight:600;color:rgba(255,255,255,0.5);text-transform:uppercase;margin-bottom:5px;}
.stats-card-value{font-size:28px;font-weight:800;background:linear-gradient(135deg,#fff,var(--primaria,#4158D0));-webkit-background-clip:text;-webkit-text-fill-color:transparent;line-height:1;}
.stats-card-subtitle{font-size:12px;color:rgba(255,255,255,0.4);margin-top:4px;}
.stats-card-decoration{position:absolute;right:20px;top:50%;transform:translateY(-50%);font-size:80px;opacity:0.05;}

/* Mini Stats */
.mini-stats{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;}
.mini-stat{flex:1;min-width:100px;background:rgba(255,255,255,0.04);border-radius:12px;padding:12px;border:1px solid rgba(255,255,255,0.06);text-align:center;transition:all .2s;}
.mini-stat:hover{border-color:var(--primaria,#4158D0);transform:translateY(-2px);}
.mini-stat-icon{font-size:20px;margin-bottom:4px;}
.mini-stat-val{font-size:11px;font-weight:700;}
.mini-stat-lbl{font-size:9px;color:rgba(255,255,255,0.35);text-transform:uppercase;margin-top:2px;}

/* Modern Card */
.modern-card{background:var(--fundo_claro,#1e293b);border-radius:16px;border:1px solid rgba(255,255,255,0.08);overflow:hidden;margin-bottom:16px;transition:all .2s;}
.modern-card:hover{border-color:var(--primaria,#4158D0);}
.card-header-custom{padding:14px 18px;display:flex;align-items:center;gap:12px;}
.card-header-custom.mp{background:linear-gradient(135deg,#009ee3,#00b1ea);}
.card-header-custom.values{background:linear-gradient(135deg,var(--primaria,#4158D0),var(--secundaria,#C850C0));}
.card-header-custom.webhook{background:linear-gradient(135deg,#10b981,#059669);}
.header-icon{width:36px;height:36px;background:rgba(255,255,255,0.2);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;color:white;}
.header-info{flex:1;}
.header-title{font-size:14px;font-weight:700;color:white;}
.header-subtitle{font-size:10px;color:rgba(255,255,255,0.7);}
.header-badge{padding:4px 10px;border-radius:20px;font-size:9px;font-weight:700;display:inline-flex;align-items:center;gap:4px;}
.badge-ok{background:rgba(16,185,129,0.25);color:#34d399;border:1px solid rgba(16,185,129,0.3);}
.badge-pending{background:rgba(245,158,11,0.25);color:#fbbf24;border:1px solid rgba(245,158,11,0.3);}
.card-body-custom{padding:18px;}

/* Form */
.form-group{margin-bottom:14px;}
.form-label{display:flex;align-items:center;gap:6px;font-size:11px;font-weight:700;color:rgba(255,255,255,0.6);text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px;}
.form-label i{font-size:14px;}
.form-control{width:100%;padding:9px 13px;background:rgba(255,255,255,0.06);border:1.5px solid rgba(255,255,255,0.08);border-radius:10px;color:#fff;font-size:12px;font-family:inherit;outline:none;transition:all .25s;}
.form-control:focus{border-color:var(--primaria,#4158D0);background:rgba(255,255,255,0.09);box-shadow:0 0 0 3px rgba(65,88,208,0.1);}
.form-control::placeholder{color:rgba(255,255,255,0.2);}
.form-control.mono{font-family:'Courier New',monospace;letter-spacing:.5px;font-size:11px;}
.helper-text{font-size:10px;color:rgba(255,255,255,0.3);margin-top:3px;display:flex;align-items:center;gap:4px;}
.helper-text i{font-size:11px;color:var(--primaria,#4158D0);}

/* Input group */
.input-group{display:flex;gap:0;align-items:stretch;}
.input-group .form-control{border-radius:0 10px 10px 0;flex:1;}
.input-prefix{background:linear-gradient(135deg,var(--primaria,#4158D0),var(--secundaria,#C850C0));color:white;padding:9px 14px;border-radius:10px 0 0 10px;font-weight:700;font-size:12px;display:flex;align-items:center;white-space:nowrap;}
.input-suffix{background:linear-gradient(135deg,var(--primaria,#4158D0),var(--secundaria,#C850C0));color:white;padding:9px 14px;border-radius:0 10px 10px 0;font-weight:700;font-size:12px;display:flex;align-items:center;white-space:nowrap;}
.input-group .input-suffix~.form-control{border-radius:10px 0 0 10px;}
.input-group-suffix{display:flex;gap:0;align-items:stretch;}
.input-group-suffix .form-control{border-radius:10px 0 0 10px;flex:1;}

/* Webhook URL */
.webhook-box{background:rgba(16,185,129,0.06);border:1px solid rgba(16,185,129,0.15);border-radius:12px;padding:12px;margin-bottom:14px;}
.webhook-label{font-size:9px;font-weight:700;color:#34d399;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;display:flex;align-items:center;gap:4px;}
.webhook-url-row{display:flex;align-items:center;gap:8px;}
.webhook-url-text{flex:1;font-family:'Courier New',monospace;font-size:11px;color:#34d399;word-break:break-all;background:rgba(0,0,0,0.2);padding:8px 10px;border-radius:8px;}
.btn-copy{background:rgba(16,185,129,0.15);border:1px solid rgba(16,185,129,0.25);border-radius:8px;padding:6px 12px;font-size:10px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:4px;color:#34d399;transition:all .2s;font-family:inherit;}
.btn-copy:hover{background:rgba(16,185,129,0.25);transform:translateY(-1px);}
.btn-copy.copied{background:#10b981;color:white;border-color:#10b981;}

/* Section divider */
.section-divider{display:flex;align-items:center;gap:10px;margin:18px 0 14px;}
.section-divider-line{flex:1;height:1px;background:rgba(255,255,255,0.06);}
.section-divider-text{font-size:10px;font-weight:700;color:rgba(255,255,255,0.3);text-transform:uppercase;letter-spacing:1px;display:flex;align-items:center;gap:5px;}
.section-divider-text i{font-size:14px;}

/* Preview card */
.preview-card{background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:10px;padding:10px 12px;margin-top:8px;}
.preview-label{font-size:8px;color:rgba(255,255,255,0.3);text-transform:uppercase;font-weight:600;margin-bottom:4px;}
.preview-row{display:flex;align-items:center;justify-content:space-between;padding:4px 0;}
.preview-desc{font-size:10px;color:rgba(255,255,255,0.5);}
.preview-value{font-size:12px;font-weight:700;color:#34d399;}

/* Buttons */
.btn-submit{width:100%;padding:10px 16px;border:none;border-radius:10px;font-weight:700;font-size:12px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;color:white;transition:all .2s;font-family:inherit;margin-top:6px;}
.btn-submit:hover{transform:translateY(-1px);filter:brightness(1.08);}
.btn-submit:active{transform:scale(0.98);}
.btn-mp{background:linear-gradient(135deg,#009ee3,#00b1ea);}
.btn-values{background:linear-gradient(135deg,var(--primaria,#4158D0),var(--secundaria,#C850C0));}
.btn-back-link{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);border-radius:10px;color:rgba(255,255,255,0.6);text-decoration:none;font-size:11px;font-weight:600;transition:all .2s;margin-top:10px;}
.btn-back-link:hover{background:rgba(255,255,255,0.1);color:white;transform:translateY(-1px);}

/* Toast */
.toast-notification{position:fixed;bottom:20px;right:20px;color:#fff;padding:10px 16px;border-radius:10px;display:flex;align-items:center;gap:8px;z-index:10001;animation:toastIn .3s ease;font-weight:600;font-size:12px;box-shadow:0 8px 20px rgba(0,0,0,.3);}
.toast-notification.ok{background:linear-gradient(135deg,#10b981,#059669);}
.toast-notification.err{background:linear-gradient(135deg,#dc2626,#b91c1c);}
@keyframes toastIn{from{transform:translateX(110%);opacity:0}to{transform:translateX(0);opacity:1}}

/* Token visibility */
.token-toggle{display:flex;align-items:center;gap:0;}
.token-toggle .form-control{border-radius:10px 0 0 10px;}
.btn-toggle-vis{background:rgba(255,255,255,0.08);border:1.5px solid rgba(255,255,255,0.08);border-left:none;border-radius:0 10px 10px 0;padding:9px 12px;cursor:pointer;color:rgba(255,255,255,0.5);font-size:14px;display:flex;align-items:center;transition:all .2s;}
.btn-toggle-vis:hover{background:rgba(255,255,255,0.12);color:white;}

@media(max-width:768px){
    .app-content{margin-left:0!important;}
    .content-wrapper{padding:10px!important;}
    .input-group,.input-group-suffix{flex-direction:column;}
    .input-prefix,.input-suffix{border-radius:10px;justify-content:center;}
    .input-group .form-control,.input-group-suffix .form-control{border-radius:10px;}
    .webhook-url-row{flex-direction:column;align-items:stretch;}
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
        <div class="stats-card-icon"><i class='bx bx-credit-card'></i></div>
        <div class="stats-card-content">
            <div class="stats-card-title">Configurações de Pagamento</div>
            <div class="stats-card-value">Mercado Pago</div>
            <div class="stats-card-subtitle">Gerencie suas credenciais e valores de cobrança</div>
        </div>
        <div class="stats-card-decoration"><i class='bx bx-credit-card'></i></div>
    </div>

    <!-- Mini Stats -->
    <div class="mini-stats">
        <div class="mini-stat">
            <div class="mini-stat-icon"><?php echo $mp_configurado ? '✅' : '⚠️'; ?></div>
            <div class="mini-stat-val" style="color:<?php echo $mp_configurado ? '#34d399' : '#fbbf24'; ?>;"><?php echo $mp_configurado ? 'Ativo' : 'Pendente'; ?></div>
            <div class="mini-stat-lbl">Mercado Pago</div>
        </div>
        <div class="mini-stat">
            <div class="mini-stat-icon"><?php echo $webhook_configurado ? '🔒' : '⚠️'; ?></div>
            <div class="mini-stat-val" style="color:<?php echo $webhook_configurado ? '#34d399' : '#fbbf24'; ?>;"><?php echo $webhook_configurado ? 'Seguro' : 'Pendente'; ?></div>
            <div class="mini-stat-lbl">Webhook</div>
        </div>
        <div class="mini-stat">
            <div class="mini-stat-icon">👤</div>
            <div class="mini-stat-val" style="color:#818cf8;">R$ <?php echo number_format($multiplicador_usuario, 2, ',', '.'); ?></div>
            <div class="mini-stat-lbl">Mult. Usuário</div>
        </div>
        <div class="mini-stat">
            <div class="mini-stat-icon">🏪</div>
            <div class="mini-stat-val" style="color:#e879f9;">R$ <?php echo number_format($multiplicador_revenda, 2, ',', '.'); ?></div>
            <div class="mini-stat-lbl">Mult. Revenda</div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════ -->
    <!-- MERCADO PAGO -->
    <!-- ═══════════════════════════════════════ -->
    <div class="modern-card">
        <div class="card-header-custom mp">
            <div class="header-icon"><i class='bx bx-credit-card-front'></i></div>
            <div class="header-info">
                <div class="header-title">Mercado Pago</div>
                <div class="header-subtitle">Credenciais de integração</div>
            </div>
            <?php if($mp_configurado): ?>
            <span class="header-badge badge-ok"><i class='bx bx-check-circle'></i> Configurado</span>
            <?php else: ?>
            <span class="header-badge badge-pending"><i class='bx bx-error'></i> Pendente</span>
            <?php endif; ?>
        </div>
        <div class="card-body-custom">
            <!-- Webhook URL -->
            <div class="webhook-box">
                <div class="webhook-label"><i class='bx bx-link'></i> URL do Webhook</div>
                <div class="webhook-url-row">
                    <div class="webhook-url-text" id="webhookUrl">https://<?php echo $dominio; ?>/api/webhooks/mercadopago.php</div>
                    <button class="btn-copy" id="btnCopyWebhook" onclick="copiarWebhook()"><i class='bx bx-copy'></i> Copiar</button>
                </div>
                <div class="helper-text" style="margin-top:6px;"><i class='bx bx-info-circle'></i> Configure no painel do Mercado Pago → Webhooks</div>
            </div>

            <form method="POST" id="formMP">
                <!-- Access Token -->
                <div class="form-group">
                    <label class="form-label"><i class='bx bx-key' style="color:#fbbf24;"></i> Access Token</label>
                    <div class="token-toggle">
                        <input type="password" class="form-control mono" name="access_token" id="accessToken" value="<?php echo htmlspecialchars($access_token); ?>" placeholder="APP_USR-0000000000000000-000000-00000000000000000000000000000000-000000000">
                        <button type="button" class="btn-toggle-vis" onclick="toggleVis('accessToken',this)"><i class='bx bx-hide'></i></button>
                    </div>
                    <div class="helper-text"><i class='bx bx-info-circle'></i> Mercado Pago → Seu negócio → Credenciais de produção</div>
                </div>

                <!-- Public Key -->
                <div class="form-group">
                    <label class="form-label"><i class='bx bx-credit-card-front' style="color:#818cf8;"></i> Public Key</label>
                    <div class="token-toggle">
                        <input type="password" class="form-control mono" name="public_key" id="publicKey" value="<?php echo htmlspecialchars($public_key); ?>" placeholder="APP_USR-00000000-0000-0000-0000-000000000000">
                        <button type="button" class="btn-toggle-vis" onclick="toggleVis('publicKey',this)"><i class='bx bx-hide'></i></button>
                    </div>
                    <div class="helper-text"><i class='bx bx-info-circle'></i> Chave pública para integração frontend</div>
                </div>

                <!-- Webhook Secret -->
                <div class="form-group">
                    <label class="form-label"><i class='bx bx-shield-quarter' style="color:#34d399;"></i> Chave de Assinatura do Webhook</label>
                    <div class="token-toggle">
                        <input type="password" class="form-control mono" name="webhook_secret" id="webhookSecret" value="<?php echo htmlspecialchars($webhook_secret); ?>" placeholder="Chave secreta do webhook">
                        <button type="button" class="btn-toggle-vis" onclick="toggleVis('webhookSecret',this)"><i class='bx bx-hide'></i></button>
                    </div>
                    <div class="helper-text"><i class='bx bx-info-circle'></i> Mercado Pago → Webhooks → Detalhes da assinatura</div>
                </div>

                <!-- Descrição Fatura -->
                <div class="form-group">
                    <label class="form-label"><i class='bx bx-receipt' style="color:#e879f9;"></i> Descrição na Fatura (max 22 caracteres)</label>
                    <input type="text" class="form-control" name="descricao_fatura" value="<?php echo htmlspecialchars($descricao_fatura); ?>" maxlength="22" placeholder="Ex: PAINEL PRO">
                    <div class="helper-text"><i class='bx bx-info-circle'></i> Nome que aparecerá na fatura do cartão do cliente</div>
                </div>

                <button type="submit" name="atualizar_mercadopago" class="btn-submit btn-mp"><i class='bx bx-save'></i> Salvar Credenciais do Mercado Pago</button>
            </form>
        </div>
    </div>

    <!-- ═══════════════════════════════════════ -->
    <!-- VALORES -->
    <!-- ═══════════════════════════════════════ -->
    <div class="modern-card">
        <div class="card-header-custom values">
            <div class="header-icon"><i class='bx bx-calculator'></i></div>
            <div class="header-info">
                <div class="header-title">Valores e Multiplicadores</div>
                <div class="header-subtitle">Configure preços automáticos para usuários e revendas</div>
            </div>
        </div>
        <div class="card-body-custom">

            <!-- USUÁRIOS SSH -->
            <div class="section-divider">
                <div class="section-divider-line"></div>
                <div class="section-divider-text"><i class='bx bx-user' style="color:#818cf8;"></i> Usuários SSH</div>
                <div class="section-divider-line"></div>
            </div>

            <form method="POST" id="formUsuarios">
                <div class="form-group">
                    <label class="form-label"><i class='bx bx-dollar' style="color:#34d399;"></i> Multiplicador por Limite</label>
                    <div class="input-group">
                        <span class="input-prefix">R$</span>
                        <input type="number" step="0.01" class="form-control" name="multiplicador_usuario" id="multUsuario" value="<?php echo $multiplicador_usuario; ?>" required oninput="atualizarPreview()">
                    </div>
                    <div class="helper-text"><i class='bx bx-info-circle'></i> Valor = limite × multiplicador</div>
                </div>

                <div class="form-group">
                    <label class="form-label"><i class='bx bx-purchase-tag' style="color:#fbbf24;"></i> Desconto para Múltiplos Limites</label>
                    <div class="input-group-suffix">
                        <input type="number" step="0.1" class="form-control" name="desconto_multiplo" id="descontoMult" value="<?php echo $desconto_multiplo; ?>" min="0" max="100" oninput="atualizarPreview()">
                        <span class="input-suffix">%</span>
                    </div>
                    <div class="helper-text"><i class='bx bx-info-circle'></i> Desconto aplicado quando limite > 1</div>
                </div>

                <!-- Preview -->
                <div class="preview-card" id="previewUsuarios">
                    <div class="preview-label">Simulação de Preços</div>
                    <div class="preview-row"><span class="preview-desc">Limite 1</span><span class="preview-value" id="pv1">—</span></div>
                    <div class="preview-row"><span class="preview-desc">Limite 2</span><span class="preview-value" id="pv2">—</span></div>
                    <div class="preview-row"><span class="preview-desc">Limite 5</span><span class="preview-value" id="pv5">—</span></div>
                    <div class="preview-row"><span class="preview-desc">Limite 10</span><span class="preview-value" id="pv10">—</span></div>
                </div>

                <button type="submit" name="atualizar_usuarios" class="btn-submit btn-values"><i class='bx bx-refresh'></i> Atualizar Valores de Usuários</button>
            </form>

            <!-- REVENDAS -->
            <div class="section-divider" style="margin-top:22px;">
                <div class="section-divider-line"></div>
                <div class="section-divider-text"><i class='bx bx-store' style="color:#e879f9;"></i> Revendas</div>
                <div class="section-divider-line"></div>
            </div>

            <form method="POST" id="formRevendas">
                <div class="form-group">
                    <label class="form-label"><i class='bx bx-dollar' style="color:#34d399;"></i> Multiplicador por Limite</label>
                    <div class="input-group">
                        <span class="input-prefix">R$</span>
                        <input type="number" step="0.01" class="form-control" name="multiplicador_revenda" id="multRevenda" value="<?php echo $multiplicador_revenda; ?>" required oninput="atualizarPreviewRevenda()">
                    </div>
                    <div class="helper-text"><i class='bx bx-info-circle'></i> Valor = limite × multiplicador</div>
                </div>

                <!-- Preview revenda -->
                <div class="preview-card" id="previewRevenda">
                    <div class="preview-label">Simulação de Preços Revenda</div>
                    <div class="preview-row"><span class="preview-desc">Limite 10</span><span class="preview-value" id="pvr10">—</span></div>
                    <div class="preview-row"><span class="preview-desc">Limite 15</span><span class="preview-value" id="pvr15">—</span></div>
                    <div class="preview-row"><span class="preview-desc">Limite 30</span><span class="preview-value" id="pvr30">—</span></div>
                    <div class="preview-row"><span class="preview-desc">Limite 50</span><span class="preview-value" id="pvr50">—</span></div>
                </div>

                <button type="submit" name="atualizar_revendas" class="btn-submit btn-values"><i class='bx bx-refresh'></i> Atualizar Valores de Revendas</button>
            </form>
        </div>
    </div>

    <!-- Voltar -->
    <div style="text-align:right;">
        <a href="home.php" class="btn-back-link"><i class='bx bx-arrow-back'></i> Voltar ao Painel</a>
    </div>

</div>
</div>

<script>
// Toggle visibility
function toggleVis(inputId, btn) {
    var inp = document.getElementById(inputId);
    var icon = btn.querySelector('i');
    if (inp.type === 'password') { inp.type = 'text'; icon.className = 'bx bx-show'; }
    else { inp.type = 'password'; icon.className = 'bx bx-hide'; }
}

// Copiar webhook
function copiarWebhook() {
    var url = document.getElementById('webhookUrl').textContent;
    var btn = document.getElementById('btnCopyWebhook');
    navigator.clipboard.writeText(url).then(function() {
        btn.classList.add('copied');
        btn.innerHTML = '<i class="bx bx-check"></i> Copiado!';
        toast('URL copiada!', 'ok');
        setTimeout(function() {
            btn.classList.remove('copied');
            btn.innerHTML = '<i class="bx bx-copy"></i> Copiar';
        }, 2000);
    }).catch(function() { toast('Erro ao copiar!', 'err'); });
}

// Toast
function toast(msg, tipo) {
    var t = document.createElement('div');
    t.className = 'toast-notification ' + (tipo === 'err' ? 'err' : 'ok');
    t.innerHTML = '<i class="bx ' + (tipo === 'err' ? 'bx-error-circle' : 'bx-check-circle') + '"></i> ' + msg;
    document.body.appendChild(t);
    setTimeout(function() { t.remove(); }, 3500);
}

// Preview usuários
function atualizarPreview() {
    var mult = parseFloat(document.getElementById('multUsuario').value) || 0;
    var desc = parseFloat(document.getElementById('descontoMult').value) || 0;
    [1, 2, 5, 10].forEach(function(lim) {
        var val = lim * mult;
        if (lim > 1 && desc > 0) val = val * (1 - desc / 100);
        document.getElementById('pv' + lim).textContent = 'R$ ' + val.toFixed(2).replace('.', ',');
    });
}

// Preview revenda
function atualizarPreviewRevenda() {
    var mult = parseFloat(document.getElementById('multRevenda').value) || 0;
    [10, 15, 30, 50].forEach(function(lim) {
        var val = lim * mult;
        document.getElementById('pvr' + lim).textContent = 'R$ ' + val.toFixed(2).replace('.', ',');
    });
}

// Inicializar previews
atualizarPreview();
atualizarPreviewRevenda();

// Mostrar toast de sucesso se houve update
<?php if ($msg_sucesso === 'mercadopago'): ?>
toast('Credenciais do Mercado Pago salvas!', 'ok');
<?php elseif ($msg_sucesso === 'usuarios'): ?>
toast('Valores de usuários atualizados!', 'ok');
<?php elseif ($msg_sucesso === 'revendas'): ?>
toast('Valores de revendas atualizados!', 'ok');
<?php endif; ?>
</script>
</body>
</html>

