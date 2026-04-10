<?php // @ioncube.dk $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf -> "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs" RANDOM
    $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf = "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs";
    function aleatorio123674($input)
    {
error_reporting(0);
if (!isset($_SESSION)) { session_start(); }

if (!isset($_SESSION['login']) || !isset($_SESSION['senha'])) {
    session_destroy(); header('location:index.php'); exit;
}

include('../AegisCore/conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) die("Connection failed: " . mysqli_connect_error());

if ($_SESSION['login'] != 'admin') { header('location:../index.php'); exit; }

if (!file_exists('suspenderrev.php')) { exit("<script>alert('Token Invalido!');</script>"); }
else { include_once 'suspenderrev.php'; }

if (!isset($_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe']) || !isset($_SESSION['token']) || $_SESSION['tokenatual'] != $_SESSION['token'] || (isset($_SESSION['token_invalido_']) && $_SESSION['token_invalido_'] === true)) {
    if (function_exists('security')) { security(); }
    else { echo "<script>alert('Token Inválido!');location.href='../index.php';</script>"; $_SESSION['token_invalido_'] = true; exit; }
}

function anti_sql($input) {
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function($m) { return ''; }, $input);
    return addslashes(strip_tags(trim($seg)));
}

// ========== HANDLER POST ==========
$msg_tipo = ''; $msg_titulo = ''; $msg_texto = '';

if (isset($_POST['editservidor'])) {
    $ipservidor = anti_sql($_POST['ipservidor'] ?? '');
    $nomeservidor = anti_sql($_POST['nomeservidor'] ?? '');
    $usuarioservidor = anti_sql($_POST['usuarioservidor'] ?? '');
    $senhaservidor = $_POST['senhaservidor'] ?? '';
    $categoriaservidor = anti_sql($_POST['categoriaservidor'] ?? '');
    $portaservidor = anti_sql($_POST['portaservidor'] ?? '');
    $ipedit = $_SESSION['ipedit'] ?? '';

    if (empty($ipservidor) || empty($nomeservidor)) {
        $msg_tipo = 'error'; $msg_titulo = 'Campos Obrigatórios!'; $msg_texto = 'Preencha pelo menos o nome e o IP do servidor.';
    } elseif (empty($ipedit)) {
        $msg_tipo = 'error'; $msg_titulo = 'Erro!'; $msg_texto = 'Sessão expirada. Recarregue a página.';
    } else {
        $sql4 = "UPDATE servidores SET nome = '$nomeservidor', ip = '$ipservidor', usuario = '$usuarioservidor', senha = '" . addslashes($senhaservidor) . "', porta = '$portaservidor', subid = '$categoriaservidor' WHERE ip = '$ipedit'";
        $result4 = mysqli_query($conn, $sql4);
        if ($result4) {
            $_SESSION['ipedit'] = $ipservidor;
            $msg_tipo = 'success'; $msg_titulo = 'Servidor Editado!'; $msg_texto = 'O servidor "' . $nomeservidor . '" foi atualizado com sucesso.';
        } else {
            $msg_tipo = 'error'; $msg_titulo = 'Erro!'; $msg_texto = 'Erro ao atualizar o servidor no banco de dados.';
        }
    }
}

include('headeradmin2.php');

// ========== SISTEMA DE TEMAS ==========
if (file_exists('../AegisCore/temas.php')) {
    include_once '../AegisCore/temas.php';
    $temaAtual = initTemas($conn);
} else {
    $temaAtual = [];
}

// ========== BUSCAR DADOS DO SERVIDOR ==========
$id = anti_sql($_GET['id'] ?? '0');
$ip = ''; $nome = ''; $porta = '22'; $usuario = 'root'; $senha = ''; $categoria = '';

if (!empty($id) && is_numeric($id)) {
    $sql = "SELECT * FROM servidores WHERE id = '$id'";
    $result = mysqli_query($conn, $sql);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $ip = $row['ip'];
        $_SESSION['ipedit'] = $ip;
        $nome = $row['nome'];
        $porta = $row['porta'];
        $usuario = $row['usuario'];
        $senha = $row['senha'];
        $categoria = $row['subid'];
    }
}

// Stats
$total_users_servidor = 0;
if (!empty($ip)) { $r = $conn->query("SELECT COUNT(*) as t FROM ssh_accounts WHERE servidor = '$ip'"); if($r){$rr=$r->fetch_assoc();$total_users_servidor=$rr['t'];} }
$total_onlines_servidor = 0;
if (!empty($ip)) { $r = $conn->query("SELECT COUNT(*) as t FROM ssh_accounts WHERE servidor = '$ip' AND status = 'Online'"); if($r){$rr=$r->fetch_assoc();$total_onlines_servidor=$rr['t'];} }

// Categoria nome
$categoria_nome = 'N/A';
if (!empty($categoria)) { $r_c = $conn->query("SELECT nome FROM categorias WHERE subid = '$categoria'"); if ($r_c && $r_c->num_rows > 0) { $c = $r_c->fetch_assoc(); $categoria_nome = $c['nome']; } }

// Todas as categorias
$categorias = [];
$r_cats = $conn->query("SELECT * FROM categorias ORDER BY nome ASC");
if ($r_cats) { while ($cc = $r_cats->fetch_assoc()) { $categorias[] = $cc; } }

date_default_timezone_set('America/Sao_Paulo');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Editar Servidor - <?php echo htmlspecialchars($nome ?: $ip); ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
<style>
<?php
if (function_exists('getCSSVariables')) { echo getCSSVariables($temaAtual); }
else { echo ':root{--primaria:#06b6d4;--secundaria:#8b5cf6;--fundo:#0f172a;--fundo_claro:#1e293b;--texto:#ffffff;--sucesso:#10b981;--erro:#dc2626;--aviso:#f59e0b;--info:#3b82f6;}'; }
?>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Inter',sans-serif;background:var(--fundo,#0f172a);color:var(--texto,#fff);min-height:100vh;}
.app-content{margin-left:-670px!important;padding:0!important;}
.content-wrapper{max-width:1000px;margin:0 auto!important;padding:20px!important;}
.content-body{padding:0!important;}

/* ========== STATS CARD (igual criar usuário) ========== */
.stats-card{
    background:linear-gradient(135deg,var(--fundo_claro,#1e293b),var(--fundo,#0f172a));
    border-radius:20px;padding:20px 24px;margin-bottom:24px;
    border:1px solid rgba(255,255,255,0.08);
    display:flex;align-items:center;gap:20px;
    position:relative;overflow:hidden;transition:all .3s ease;
}
.stats-card:hover{transform:translateY(-2px);border-color:var(--primaria,#06b6d4);}
.stats-card-icon{
    width:60px;height:60px;
    background:linear-gradient(135deg,#06b6d4,#8b5cf6);
    border-radius:18px;display:flex;align-items:center;justify-content:center;
    font-size:32px;color:white;
}
.stats-card-content{flex:1;}
.stats-card-title{font-size:13px;font-weight:600;color:rgba(255,255,255,0.5);text-transform:uppercase;margin-bottom:5px;}
.stats-card-value{
    font-size:36px;font-weight:800;
    background:linear-gradient(135deg,#fff,#06b6d4);
    -webkit-background-clip:text;-webkit-text-fill-color:transparent;line-height:1;
}
.stats-card-subtitle{font-size:12px;color:rgba(255,255,255,0.4);margin-top:4px;}
.stats-card-decoration{position:absolute;right:20px;top:50%;transform:translateY(-50%);font-size:80px;opacity:0.05;}

/* ========== MINI STATS ========== */
.mini-stats{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;}
.mini-stat{
    flex:1;min-width:120px;
    background:rgba(255,255,255,0.04);border-radius:12px;padding:12px;
    border:1px solid rgba(255,255,255,0.06);text-align:center;
    transition:all .2s;
}
.mini-stat:hover{border-color:var(--primaria,#06b6d4);transform:translateY(-2px);}
.mini-stat-val{font-size:20px;font-weight:800;}
.mini-stat-lbl{font-size:9px;color:rgba(255,255,255,0.35);text-transform:uppercase;margin-top:2px;}

/* ========== MODERN CARD (igual criar usuário) ========== */
.modern-card{
    background:var(--fundo_claro,#1e293b);
    border-radius:16px;border:1px solid rgba(255,255,255,0.08);
    overflow:hidden;margin-bottom:16px;transition:all .2s;
}
.modern-card:hover{border-color:var(--primaria,#06b6d4);}
.card-header-custom{padding:14px 18px;display:flex;align-items:center;gap:12px;}
.card-header-custom.primary{background:linear-gradient(135deg,#06b6d4,#8b5cf6);}
.card-header-custom.credentials{background:linear-gradient(135deg,#f59e0b,#f97316);}
.header-icon{
    width:36px;height:36px;background:rgba(255,255,255,0.2);
    border-radius:10px;display:flex;align-items:center;justify-content:center;
    font-size:18px;color:white;
}
.header-title{font-size:14px;font-weight:700;color:white;}
.header-subtitle{font-size:10px;color:rgba(255,255,255,0.7);}
.card-body{padding:16px;}

/* ========== FORM (igual criar usuário) ========== */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.form-field{display:flex;flex-direction:column;gap:4px;}
.form-field.full-width{grid-column:1/-1;}
.form-field label{
    font-size:9px;font-weight:700;color:rgba(255,255,255,.4);
    text-transform:uppercase;letter-spacing:.5px;
    display:flex;align-items:center;gap:4px;
}
.form-field label i{font-size:12px;}
.form-control{
    width:100%;padding:8px 12px;
    background:rgba(255,255,255,.06);border:1.5px solid rgba(255,255,255,.08);
    border-radius:9px;color:#fff;font-size:12px;font-family:inherit;
    outline:none;transition:all .25s;
}
.form-control:focus{border-color:var(--primaria,#06b6d4);background:rgba(255,255,255,.09);box-shadow:0 0 0 3px rgba(6,182,212,0.12);}
.form-control::placeholder{color:rgba(255,255,255,.2);}
.form-control-mono{font-family:'Courier New',monospace;font-size:13px;letter-spacing:.5px;}
select.form-control option{background:var(--fundo_claro,#1e293b);}
.form-hint{font-size:9px;color:rgba(255,255,255,.25);font-style:italic;margin-top:2px;}
.current-value{
    display:inline-flex;align-items:center;gap:4px;
    background:rgba(255,255,255,.05);padding:3px 8px;border-radius:6px;
    font-size:10px;color:rgba(255,255,255,.5);margin-top:3px;
}
.current-value i{font-size:11px;color:var(--primaria,#06b6d4);}

/* Password toggle */
.input-password-wrap{position:relative;}
.input-password-wrap .form-control{padding-right:40px;}
.toggle-pass{
    position:absolute;right:4px;top:50%;transform:translateY(-50%);
    background:rgba(255,255,255,.08);border:none;color:rgba(255,255,255,.5);
    width:32px;height:32px;border-radius:7px;cursor:pointer;
    display:flex;align-items:center;justify-content:center;font-size:15px;
    transition:all .2s;
}
.toggle-pass:hover{background:rgba(255,255,255,.15);color:white;}

/* Info inline */
.info-inline{
    display:flex;align-items:center;gap:8px;
    background:rgba(6,182,212,.08);border:1px solid rgba(6,182,212,.15);
    border-radius:8px;padding:8px 12px;margin-bottom:12px;
}
.info-inline i{font-size:16px;color:#22d3ee;flex-shrink:0;}
.info-inline span{font-size:11px;color:rgba(255,255,255,.6);}
.info-inline strong{color:#22d3ee;}

/* ========== BOTÕES (igual criar usuário) ========== */
.btn{
    padding:8px 16px;border:none;border-radius:10px;font-weight:600;font-size:12px;
    cursor:pointer;display:inline-flex;align-items:center;justify-content:center;
    gap:6px;color:white;transition:all .2s;text-decoration:none;font-family:inherit;
}
.btn:hover{transform:translateY(-1px);filter:brightness(1.08);}
.btn-success{background:linear-gradient(135deg,var(--sucesso,#10b981),#059669);}
.btn-danger{background:linear-gradient(135deg,var(--erro,#dc2626),#b91c1c);}
.btn-info{background:linear-gradient(135deg,#06b6d4,#0891b2);}
.btn-cancel{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);}
.btn-cancel:hover{background:rgba(255,255,255,.15);}
.action-buttons{display:flex;justify-content:flex-end;gap:8px;margin-top:18px;flex-wrap:wrap;}

/* ========== MODAIS ========== */
.modal-overlay{
    position:fixed;inset:0;background:rgba(0,0,0,.85);
    display:none;align-items:center;justify-content:center;
    z-index:9999;backdrop-filter:blur(8px);padding:16px;
}
.modal-overlay.show{display:flex;}
.modal-container{animation:modalIn .3s ease;max-width:480px;width:92%;}
@keyframes modalIn{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
.modal-content{
    background:var(--fundo_claro,#1e293b);
    border-radius:20px;overflow:hidden;border:1px solid rgba(255,255,255,.1);
    box-shadow:0 25px 60px rgba(0,0,0,.5);
}
.modal-header{padding:14px 18px;display:flex;align-items:center;justify-content:space-between;}
.modal-header h5{margin:0;display:flex;align-items:center;gap:8px;font-size:14px;font-weight:700;color:#fff;}
.modal-header.success{background:linear-gradient(135deg,var(--sucesso,#10b981),#059669);}
.modal-header.error{background:linear-gradient(135deg,var(--erro,#dc2626),#b91c1c);}
.modal-header.warning{background:linear-gradient(135deg,var(--aviso,#f59e0b),#f97316);}
.modal-header.info{background:linear-gradient(135deg,#06b6d4,#0891b2);}
.modal-header.confirm{background:linear-gradient(135deg,#3b82f6,#2563eb);}
.modal-close{background:rgba(255,255,255,.15);border:none;color:#fff;font-size:18px;cursor:pointer;width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;transition:all .2s;}
.modal-close:hover{background:rgba(255,255,255,.25);transform:rotate(90deg);}
.modal-body{padding:18px;}
.modal-footer{border-top:1px solid rgba(255,255,255,.07);padding:12px 18px;display:flex;justify-content:flex-end;gap:8px;flex-wrap:wrap;}

/* Modal info rows */
.modal-info-card{background:rgba(255,255,255,.04);border-radius:12px;padding:12px;margin-bottom:12px;border:1px solid rgba(255,255,255,.06);}
.modal-info-row{display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.04);}
.modal-info-row:last-child{border-bottom:none;}
.modal-info-label{font-size:11px;font-weight:600;color:rgba(255,255,255,.5);display:flex;align-items:center;gap:6px;}
.modal-info-label i{font-size:15px;}
.modal-info-value{font-size:12px;font-weight:700;color:white;}
.modal-info-value.mono{font-family:'Courier New',monospace;background:rgba(0,0,0,.3);padding:2px 8px;border-radius:6px;letter-spacing:.5px;}

/* Modal icon */
.modal-ic{width:70px;height:70px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:34px;animation:icPop .5s cubic-bezier(0.34,1.56,0.64,1) .15s both;}
@keyframes icPop{0%{transform:scale(0);opacity:0}100%{transform:scale(1);opacity:1}}
.modal-ic.success{background:rgba(16,185,129,.15);color:#34d399;border:2px solid rgba(16,185,129,.3);}
.modal-ic.error{background:rgba(239,68,68,.15);color:#f87171;border:2px solid rgba(239,68,68,.3);}
.modal-ic.warning{background:rgba(245,158,11,.15);color:#fbbf24;border:2px solid rgba(245,158,11,.3);}
.modal-ic.info{background:rgba(6,182,212,.15);color:#22d3ee;border:2px solid rgba(6,182,212,.3);}
.modal-ic.confirm{background:rgba(59,130,246,.15);color:#60a5fa;border:2px solid rgba(59,130,246,.3);}

/* Spinner */
.spinner-wrap{display:flex;flex-direction:column;align-items:center;gap:14px;padding:20px 0;}
.spinner-ring{width:44px;height:44px;border:3px solid rgba(255,255,255,.08);border-top-color:#22d3ee;border-right-color:#a78bfa;border-radius:50%;animation:spin .8s linear infinite;}
@keyframes spin{to{transform:rotate(360deg)}}

/* Toast */
.toast-notification{
    position:fixed;bottom:20px;right:20px;color:#fff;
    padding:10px 16px;border-radius:10px;display:flex;align-items:center;gap:8px;
    z-index:10000;animation:toastIn .3s ease;font-weight:600;font-size:12px;
    box-shadow:0 8px 20px rgba(0,0,0,.3);
}
.toast-notification.ok{background:linear-gradient(135deg,var(--sucesso,#10b981),#059669);}
.toast-notification.err{background:linear-gradient(135deg,var(--erro,#dc2626),#b91c1c);}
@keyframes toastIn{from{transform:translateX(110%);opacity:0}to{transform:translateX(0);opacity:1}}

@media(max-width:768px){
    .app-content{margin-left:0!important;}
    .content-wrapper{padding:10px!important;}
    .form-grid{grid-template-columns:1fr;}
    .action-buttons{flex-direction:column;}
    .btn{width:100%;}
    .stats-card{padding:14px;gap:14px;}
    .stats-card-icon{width:48px;height:48px;font-size:24px;}
    .stats-card-value{font-size:28px;}
    .mini-stats{flex-direction:row;flex-wrap:wrap;}
    .mini-stat{min-width:80px;}
}
</style>
</head>
<body class="<?php echo htmlspecialchars(getBodyClass($temaAtual ?? ['classe'=>'theme-dark'])); ?>">
<div class="app-content content">
<div class="content-overlay"></div>
<div class="content-wrapper">
<div class="content-body">

    <!-- ========== STATS CARD (cabeçalho igual criar usuário) ========== -->
    <div class="stats-card">
        <div class="stats-card-icon"><i class='bx bx-server'></i></div>
        <div class="stats-card-content">
            <div class="stats-card-title">Editar Servidor</div>
            <div class="stats-card-value"><?php echo htmlspecialchars($nome ?: 'Servidor'); ?></div>
            <div class="stats-card-subtitle"><?php echo htmlspecialchars($ip); ?> — Porta <?php echo htmlspecialchars($porta); ?></div>
        </div>
        <div class="stats-card-decoration"><i class='bx bx-server'></i></div>
    </div>

    <!-- ========== MINI STATS ========== -->
    <div class="mini-stats">
        <div class="mini-stat">
            <div class="mini-stat-val" style="color:#818cf8;"><?php echo $total_users_servidor; ?></div>
            <div class="mini-stat-lbl">Usuários</div>
        </div>
        <div class="mini-stat">
            <div class="mini-stat-val" style="color:#34d399;"><?php echo $total_onlines_servidor; ?></div>
            <div class="mini-stat-lbl">Onlines</div>
        </div>
        <div class="mini-stat">
            <div class="mini-stat-val" style="color:#22d3ee;"><?php echo htmlspecialchars($porta); ?></div>
            <div class="mini-stat-lbl">Porta SSH</div>
        </div>
        <div class="mini-stat">
            <div class="mini-stat-val" style="color:#a78bfa;">#<?php echo $id; ?></div>
            <div class="mini-stat-lbl">ID</div>
        </div>
        <div class="mini-stat">
            <div class="mini-stat-val" style="color:#f472b6;"><?php echo htmlspecialchars($categoria_nome); ?></div>
            <div class="mini-stat-lbl">Categoria</div>
        </div>
    </div>

    <!-- ========== CARD: IDENTIFICAÇÃO ========== -->
    <form method="POST" action="editarservidor.php?id=<?php echo $id; ?>" id="formEditar">
    <input type="hidden" name="editservidor" value="1">

    <div class="modern-card">
        <div class="card-header-custom primary">
            <div class="header-icon"><i class='bx bx-server'></i></div>
            <div>
                <div class="header-title">Identificação do Servidor</div>
                <div class="header-subtitle">Nome, IP, porta e categoria</div>
            </div>
        </div>
        <div class="card-body">
            <div class="form-grid">
                <div class="form-field">
                    <label><i class='bx bx-rename' style="color:#22d3ee;"></i> Nome do Servidor</label>
                    <input type="text" name="nomeservidor" class="form-control" value="<?php echo htmlspecialchars($nome); ?>" required placeholder="Ex: Servidor Principal">
                    <span class="form-hint">Nome para identificação no painel</span>
                </div>
                <div class="form-field">
                    <label><i class='bx bx-globe' style="color:#60a5fa;"></i> Endereço IP</label>
                    <input type="text" name="ipservidor" class="form-control form-control-mono" value="<?php echo htmlspecialchars($ip); ?>" required placeholder="Ex: 192.168.1.100">
                </div>
                <div class="form-field">
                    <label><i class='bx bx-terminal' style="color:#a78bfa;"></i> Porta SSH</label>
                    <input type="text" name="portaservidor" class="form-control form-control-mono" value="<?php echo htmlspecialchars($porta); ?>" placeholder="22">
                    <span class="form-hint">Padrão: 22</span>
                </div>
                <div class="form-field">
                    <label><i class='bx bx-category' style="color:#f472b6;"></i> Categoria</label>
                    <select name="categoriaservidor" class="form-control">
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['subid']); ?>" <?php echo ($cat['subid'] == $categoria) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="current-value"><i class='bx bx-info-circle'></i> Atual: <?php echo htmlspecialchars($categoria_nome); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- ========== CARD: CREDENCIAIS ========== -->
    <div class="modern-card">
        <div class="card-header-custom credentials">
            <div class="header-icon"><i class='bx bx-lock-alt'></i></div>
            <div>
                <div class="header-title">Credenciais de Acesso</div>
                <div class="header-subtitle">Usuário e senha SSH do servidor</div>
            </div>
        </div>
        <div class="card-body">
            <div class="info-inline">
                <i class='bx bx-shield'></i>
                <span>As credenciais são usadas para conexão SSH. Mantenha-as seguras.</span>
            </div>
            <div class="form-grid">
                <div class="form-field">
                    <label><i class='bx bx-user' style="color:#818cf8;"></i> Usuário SSH</label>
                    <input type="text" name="usuarioservidor" class="form-control form-control-mono" value="<?php echo htmlspecialchars($usuario); ?>" placeholder="root">
                </div>
                <div class="form-field">
                    <label><i class='bx bx-key' style="color:#fbbf24;"></i> Senha SSH</label>
                    <div class="input-password-wrap">
                        <input type="password" name="senhaservidor" id="senhaInput" class="form-control form-control-mono" value="<?php echo htmlspecialchars($senha); ?>" placeholder="••••••••">
                        <button type="button" class="toggle-pass" onclick="toggleSenha()"><i class='bx bx-hide' id="toggleIcon"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ========== AÇÕES ========== -->
    <div class="action-buttons">
        <a href="servidores.php" class="btn btn-cancel"><i class='bx bx-x'></i> Cancelar</a>
        <button type="button" class="btn btn-info" onclick="testarConexao()"><i class='bx bx-plug'></i> Testar Conexão</button>
        <button type="button" class="btn btn-success" onclick="confirmarEdicao()"><i class='bx bx-check'></i> Salvar Alterações</button>
    </div>

    </form>

</div></div></div>

<!-- ========== MODAL: CONFIRMAR EDIÇÃO ========== -->
<div id="modalConfirmar" class="modal-overlay">
<div class="modal-container"><div class="modal-content">
    <div class="modal-header confirm">
        <h5><i class='bx bx-edit'></i> Confirmar Edição</h5>
        <button class="modal-close" onclick="fecharModal('modalConfirmar')"><i class='bx bx-x'></i></button>
    </div>
    <div class="modal-body">
        <div class="modal-ic confirm"><i class='bx bx-server'></i></div>
        <p style="text-align:center;font-size:14px;font-weight:600;margin-bottom:4px;">Salvar Alterações?</p>
        <p style="text-align:center;font-size:12px;color:rgba(255,255,255,.5);margin-bottom:16px;">Confirme os dados do servidor:</p>
        <div class="modal-info-card" id="confirmDetails"></div>
    </div>
    <div class="modal-footer">
        <button class="btn btn-cancel" onclick="fecharModal('modalConfirmar')">Cancelar</button>
        <button class="btn btn-success" onclick="fecharModal('modalConfirmar');document.getElementById('formEditar').submit();"><i class='bx bx-check'></i> Confirmar</button>
    </div>
</div></div>
</div>

<!-- ========== MODAL: TESTAR CONEXÃO ========== -->
<div id="modalTestar" class="modal-overlay">
<div class="modal-container"><div class="modal-content">
    <div class="modal-header info">
        <h5><i class='bx bx-plug'></i> Testar Conexão</h5>
        <button class="modal-close" onclick="fecharModal('modalTestar')"><i class='bx bx-x'></i></button>
    </div>
    <div class="modal-body" id="testarBody">
        <div class="spinner-wrap">
            <div class="spinner-ring"></div>
            <p style="font-size:13px;color:rgba(255,255,255,.6);font-weight:500;">Testando conexão...</p>
        </div>
    </div>
</div></div>
</div>

<!-- ========== MODAL: RESULTADO ========== -->
<div id="modalResultado" class="modal-overlay">
<div class="modal-container"><div class="modal-content">
    <div class="modal-header" id="resultHdr">
        <h5 id="resultHdrTitle"></h5>
        <button class="modal-close" onclick="fecharResultado()"><i class='bx bx-x'></i></button>
    </div>
    <div class="modal-body" style="text-align:center;">
        <div class="modal-ic" id="resultIc"></div>
        <p style="font-size:16px;font-weight:700;margin-bottom:6px;" id="resultTitle"></p>
        <p style="font-size:12px;color:rgba(255,255,255,.6);line-height:1.7;" id="resultText"></p>
    </div>
    <div class="modal-footer">
        <button class="btn" id="resultBtn" onclick="fecharResultado()">OK</button>
    </div>
</div></div>
</div>

<script>
// ========== MODAIS ==========
function abrirModal(id){ document.getElementById(id).classList.add('show'); }
function fecharModal(id){ document.getElementById(id).classList.remove('show'); }
document.querySelectorAll('.modal-overlay').forEach(function(o){
    o.addEventListener('click',function(e){if(e.target===o)o.classList.remove('show');});
});
document.addEventListener('keydown',function(e){
    if(e.key==='Escape')document.querySelectorAll('.modal-overlay.show').forEach(function(m){m.classList.remove('show');});
});

// ========== TOGGLE SENHA ==========
function toggleSenha(){
    var inp=document.getElementById('senhaInput'),ico=document.getElementById('toggleIcon');
    if(inp.type==='password'){inp.type='text';ico.className='bx bx-show';}
    else{inp.type='password';ico.className='bx bx-hide';}
}

// ========== CONFIRMAR EDIÇÃO ==========
function confirmarEdicao(){
    var nome=document.querySelector('input[name="nomeservidor"]').value;
    var ip=document.querySelector('input[name="ipservidor"]').value;
    var porta=document.querySelector('input[name="portaservidor"]').value||'22';
    var usuario=document.querySelector('input[name="usuarioservidor"]').value;
    var catSel=document.querySelector('select[name="categoriaservidor"]');
    var catNome=catSel.options[catSel.selectedIndex].text;

    if(!nome.trim()||!ip.trim()){
        mostrarResultado('error','Campos Obrigatórios!','Preencha pelo menos o <strong>nome</strong> e o <strong>IP</strong> do servidor.');
        return;
    }

    var html='';
    html+='<div class="modal-info-row"><div class="modal-info-label"><i class="bx bx-rename" style="color:#22d3ee;"></i> Nome</div><div class="modal-info-value">'+esc(nome)+'</div></div>';
    html+='<div class="modal-info-row"><div class="modal-info-label"><i class="bx bx-globe" style="color:#60a5fa;"></i> IP</div><div class="modal-info-value mono">'+esc(ip)+'</div></div>';
    html+='<div class="modal-info-row"><div class="modal-info-label"><i class="bx bx-terminal" style="color:#a78bfa;"></i> Porta</div><div class="modal-info-value">'+esc(porta)+'</div></div>';
    html+='<div class="modal-info-row"><div class="modal-info-label"><i class="bx bx-user" style="color:#818cf8;"></i> Usuário</div><div class="modal-info-value mono">'+esc(usuario||'root')+'</div></div>';
    html+='<div class="modal-info-row"><div class="modal-info-label"><i class="bx bx-category" style="color:#f472b6;"></i> Categoria</div><div class="modal-info-value">'+esc(catNome)+'</div></div>';
    document.getElementById('confirmDetails').innerHTML=html;
    abrirModal('modalConfirmar');
}

// ========== TESTAR CONEXÃO ==========
function testarConexao(){
    var ip=document.querySelector('input[name="ipservidor"]').value;
    var porta=document.querySelector('input[name="portaservidor"]').value||'22';
    if(!ip.trim()){mostrarResultado('error','IP Obrigatório!','Preencha o <strong>IP</strong> para testar.');return;}

    document.getElementById('testarBody').innerHTML='<div class="spinner-wrap"><div class="spinner-ring"></div><p style="font-size:13px;color:rgba(255,255,255,.6);">Testando <strong style="color:#22d3ee;">'+esc(ip)+':'+esc(porta)+'</strong>...</p></div>';
    abrirModal('modalTestar');

    var start=Date.now();
    var timeout=setTimeout(function(){
        fecharModal('modalTestar');
        mostrarResultado('warning','Timeout!','Não foi possível conectar ao servidor <strong>'+esc(ip)+':'+esc(porta)+'</strong> em 5 segundos.');
    },5000);

    var xhr=new XMLHttpRequest();
    xhr.timeout=5000;
    xhr.open('HEAD','http://'+ip+':'+porta,true);
    xhr.onload=xhr.onerror=function(){
        clearTimeout(timeout);
        var elapsed=Date.now()-start;
        fecharModal('modalTestar');
        if(elapsed<4500) mostrarResultado('success','Servidor Respondeu!','O servidor <strong>'+esc(ip)+':'+esc(porta)+'</strong> respondeu em <strong>'+elapsed+'ms</strong>.<br><br><small style="color:rgba(255,255,255,.3);">Nota: Verifica resposta de rede, não autenticação SSH.</small>');
        else mostrarResultado('warning','Resposta Lenta','O servidor respondeu em <strong>'+elapsed+'ms</strong>. Conexão pode estar instável.');
    };
    xhr.ontimeout=function(){
        clearTimeout(timeout);
        fecharModal('modalTestar');
        mostrarResultado('warning','Timeout!','Não foi possível conectar ao servidor <strong>'+esc(ip)+':'+esc(porta)+'</strong>.');
    };
    try{xhr.send();}catch(e){
        clearTimeout(timeout);
        setTimeout(function(){
            fecharModal('modalTestar');
            mostrarResultado('info','Teste Parcial','Não foi possível testar via navegador. Servidor pode estar acessível apenas via SSH.<br><br>IP: <strong>'+esc(ip)+':'+esc(porta)+'</strong>');
        },1500);
    }
}

// ========== RESULTADO MODAL ==========
function mostrarResultado(tipo,titulo,texto){
    document.querySelectorAll('.modal-overlay.show').forEach(function(m){m.classList.remove('show');});
    var hdr=document.getElementById('resultHdr'),ht=document.getElementById('resultHdrTitle'),
        ic=document.getElementById('resultIc'),t=document.getElementById('resultTitle'),
        tx=document.getElementById('resultText'),btn=document.getElementById('resultBtn');
    var map={
        success:{hc:'modal-header success',ht:'<i class="bx bx-check-circle"></i> Sucesso',ic:'modal-ic success',i:'bx-check-circle',bc:'btn btn-success'},
        error:{hc:'modal-header error',ht:'<i class="bx bx-error-circle"></i> Erro',ic:'modal-ic error',i:'bx-error-circle',bc:'btn btn-danger'},
        warning:{hc:'modal-header warning',ht:'<i class="bx bx-error"></i> Aviso',ic:'modal-ic warning',i:'bx-error',bc:'btn btn-cancel'},
        info:{hc:'modal-header info',ht:'<i class="bx bx-info-circle"></i> Info',ic:'modal-ic info',i:'bx-info-circle',bc:'btn btn-info'},
    };
    var m=map[tipo]||map.info;
    hdr.className=m.hc;ht.innerHTML=m.ht;ic.className=m.ic;ic.innerHTML='<i class="bx '+m.i+'"></i>';btn.className=m.bc;
    t.textContent=titulo;tx.innerHTML=texto;
    ic.style.animation='none';ic.offsetHeight;ic.style.animation='';
    abrirModal('modalResultado');
}
function fecharResultado(){fecharModal('modalResultado');}
function esc(s){var d=document.createElement('div');d.textContent=s||'';return d.innerHTML;}

// ========== TOAST ==========
function mostrarToast(msg,tipo){
    var t=document.createElement('div');
    t.className='toast-notification '+(tipo||'ok');
    t.innerHTML='<i class="bx '+(tipo==='err'?'bx-error-circle':'bx-check-circle')+'"></i> '+msg;
    document.body.appendChild(t);setTimeout(function(){t.remove();},3500);
}

// ========== RESULTADO DO POST ==========
<?php if (!empty($msg_tipo)): ?>
document.addEventListener('DOMContentLoaded',function(){
    <?php if ($msg_tipo === 'success'): ?>
    mostrarResultado('success','<?php echo addslashes($msg_titulo); ?>','<?php echo addslashes($msg_texto); ?>');
    var _origFechar=fecharResultado;
    fecharResultado=function(){_origFechar();window.location.href='servidores.php';};
    <?php else: ?>
    mostrarResultado('<?php echo $msg_tipo; ?>','<?php echo addslashes($msg_titulo); ?>','<?php echo addslashes($msg_texto); ?>');
    <?php endif; ?>
});
<?php endif; ?>
</script>
</body>
</html>
<?php
    }
    aleatorio123674($kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf);
?>

