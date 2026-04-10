<?php
session_start();
error_reporting(0);
include('../AegisCore/conexao.php');

set_time_limit(0);
ignore_user_abort(true);

$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) die("Connection failed: " . mysqli_connect_error());

if (file_exists('../AegisCore/temas.php')) {
    include_once '../AegisCore/temas.php';
    $temaAtual  = initTemas($conn);
    $listaTemas = getListaTemas($conn);
} else {
    $temaAtual  = [];
    $listaTemas = [];
}

date_default_timezone_set('America/Sao_Paulo');

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

function anti_sql($input) {
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function($m){ return ''; }, $input);
    return addslashes(strip_tags(trim($seg)));
}

function getServidorToken($conn, $servidor_id) {
    $sql_token = "SELECT token FROM servidor_tokens WHERE servidor_id = '$servidor_id' AND status = 'ativo' ORDER BY id DESC LIMIT 1";
    $res = mysqli_query($conn, $sql_token);
    if ($res && mysqli_num_rows($res) > 0) {
        $row = mysqli_fetch_assoc($res);
        return $row['token'];
    }
    return md5($_SESSION['token'] ?? '');
}

// ========== INICIALIZAR VARIÁVEIS ==========
$logineditar     = '';
$senhaeditar     = '';
$validadeeditar  = '';
$limiteeditar    = '';
$categoriaeditar = '';
$donodaconta     = '';
$notas           = '';
$valormensal     = '';
$whatsapp        = '';
$uuid            = '';
$dono            = 'Não Encontrado';
$dias            = 0;

// ✅ id vem do GET ou do hidden field no POST
$id_final = anti_sql($_GET['id'] ?? $_POST['edit_id'] ?? '');

if (!empty($id_final)) {
    $res_load = mysqli_query($conn, "SELECT * FROM ssh_accounts WHERE id = '$id_final'");
    $row_load = mysqli_fetch_assoc($res_load);

    if ($row_load) {
        $logineditar     = $row_load['login'];
        $senhaeditar     = $row_load['senha'];
        $validadeeditar  = $row_load['expira'];
        $limiteeditar    = $row_load['limite'];
        $categoriaeditar = $row_load['categoriaid'];
        $donodaconta     = $row_load['byid'];
        $notas           = $row_load['lastview'];
        $valormensal     = $row_load['valormensal'];
        $whatsapp        = $row_load['whatsapp'];
        $uuid            = $row_load['uuid'] ?: 'Não Gerado';

        $vfmt      = date('Y-m-d', strtotime($validadeeditar));
        $hoje      = date('Y-m-d');
        $diferenca = strtotime($vfmt) - strtotime($hoje);
        $dias      = max(0, (int)floor($diferenca / 86400));

        $stmt_dono = $conn->prepare("SELECT login FROM accounts WHERE id = ?");
        $stmt_dono->bind_param("s", $donodaconta);
        $stmt_dono->execute();
        $stmt_dono->bind_result($dono_tmp);
        $stmt_dono->fetch();
        $stmt_dono->close();
        $dono = $dono_tmp ?: 'Não Encontrado';
    }
}

// ========== CONTROLE DE MODAIS ==========
$show_modal       = false;
$show_error_modal = false;
$error_message    = '';
$sucess_servers   = [];
$failed_servers   = [];
$modal_usuario    = '';
$modal_senha      = '';
$modal_validade   = '';
$modal_limite     = '';

// ✅ USAR REQUEST_METHOD em vez de isset($_POST['editauser'])
//    porque .submit() via JS não inclui o name do botão
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($id_final)) {

    $logineditar_original = $logineditar;
    $categoriaeditar_post = $categoriaeditar;

    $usuarioedit      = anti_sql($_POST['usuarioedit']  ?? '');
    $senhaedit        = anti_sql($_POST['senhaedit']    ?? '');
    $validadeedit     = anti_sql($_POST['validadeedit'] ?? '30');
    $limiteedit       = anti_sql($_POST['limiteedit']   ?? '1');
    $notas_edit       = anti_sql($_POST['notas']        ?? '');
    $valormensal_edit = anti_sql($_POST['valormensal']  ?? '0');
    $whatsapp_edit    = anti_sql(preg_replace('/\D/', '', $_POST['whatsapp'] ?? ''));

    if ($valormensal_edit === '') $valormensal_edit = 0;

    // Validações
    if (empty($usuarioedit)) {
        $error_message    = 'Login não pode ser vazio!';
        $show_error_modal = true;
    } elseif (empty($senhaedit)) {
        $error_message    = 'Senha não pode ser vazia!';
        $show_error_modal = true;
    } elseif (empty($logineditar_original)) {
        $error_message    = 'Erro interno: usuário original não encontrado.';
        $show_error_modal = true;
    }

    // Verificar login duplicado
    if (!$show_error_modal && $usuarioedit !== $logineditar_original) {
        $chk = mysqli_query($conn, "SELECT id FROM ssh_accounts WHERE login='$usuarioedit'");
        if (mysqli_num_rows($chk) > 0) {
            $error_message    = 'Usuário já existe! Escolha outro login.';
            $show_error_modal = true;
        }
    }

    if (!$show_error_modal) {
        $validade_dias = intval($validadeedit);
        $expira        = date('Y-m-d H:i:s', strtotime("+$validade_dias days"));

        $sucess         = false;
        $sucess_servers = [];
        $failed_servers = [];

        $rows_serv = mysqli_query($conn, "SELECT * FROM servidores WHERE subid='$categoriaeditar_post'");

        while ($user_data = mysqli_fetch_assoc($rows_serv)) {
            $socket = @fsockopen($user_data['ip'], 6969, $errno, $errstr, 3);

            if ($socket) {
                fclose($socket);
                $senha_token = getServidorToken($conn, $user_data['id']);
                $headers = ['Senha: ' . $senha_token];

                // 1. Remove usuário antigo
                $cmd1 = 'sudo /etc/xis/atlasremove.sh ' . $logineditar_original;
                $ch = curl_init();
                curl_setopt_array($ch, [CURLOPT_URL => $user_data['ip'].':6969', CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_POST => 1, CURLOPT_TIMEOUT => 30, CURLOPT_POSTFIELDS => "comando=$cmd1"]);
                curl_exec($ch); curl_close($ch);

                // 2. Remove arquivo de teste
                $cmd2 = 'sudo rm -rf /etc/SSHPlus/userteste/' . $logineditar_original . '.sh';
                $ch = curl_init();
                curl_setopt_array($ch, [CURLOPT_URL => $user_data['ip'].':6969', CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_POST => 1, CURLOPT_TIMEOUT => 30, CURLOPT_POSTFIELDS => "comando=$cmd2"]);
                curl_exec($ch); curl_close($ch);

                // 3. Cria novo usuário
                $cmd3 = "sudo /etc/xis/atlascreate.sh $usuarioedit $senhaedit $validade_dias $limiteedit";
                $ch = curl_init();
                curl_setopt_array($ch, [CURLOPT_URL => $user_data['ip'].':6969', CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_POST => 1, CURLOPT_TIMEOUT => 30, CURLOPT_POSTFIELDS => "comando=$cmd3"]);
                curl_exec($ch); curl_close($ch);

                $sucess_servers[] = $user_data['nome'];
                $sucess           = true;
            } else {
                $failed_servers[] = $user_data['nome'] . ' (porta 6969 fechada)';
            }
        }

        if (!$sucess) {
            $error_message    = 'Nenhum servidor disponível. Verifique a conexão.';
            $show_error_modal = true;
        } else {
            $stmt_upd = $conn->prepare(
                "UPDATE ssh_accounts SET login=?, senha=?, expira=?, limite=?, mainid='', lastview=?, valormensal=?, whatsapp=? WHERE login=?"
            );
            $stmt_upd->bind_param(
                "ssssssss",
                $usuarioedit, $senhaedit, $expira, $limiteedit,
                $notas_edit, $valormensal_edit, $whatsapp_edit,
                $logineditar_original
            );
            $stmt_upd->execute();
            $stmt_upd->close();

            // Atualizar variáveis locais para exibição
            $logineditar    = $usuarioedit;
            $senhaeditar    = $senhaedit;
            $validadeeditar = $expira;
            $limiteeditar   = $limiteedit;
            $valormensal    = $valormensal_edit;
            $whatsapp       = $whatsapp_edit;
            $dias           = $validade_dias;

            $show_modal     = true;
            $modal_usuario  = $usuarioedit;
            $modal_senha    = $senhaedit;
            $modal_validade = date('d/m/Y', strtotime($expira));
            $modal_limite   = $limiteedit;
        }
    }
}

include('headeradmin2.php');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Editar Usuário</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
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
.content-body{padding:0!important;}
.stats-card{background:linear-gradient(135deg,var(--fundo_claro,#1e293b),var(--fundo,#0f172a));border-radius:20px;padding:20px 24px;margin-bottom:24px;border:1px solid rgba(255,255,255,0.08);display:flex;align-items:center;gap:20px;position:relative;overflow:hidden;transition:all .3s ease;}
.stats-card:hover{transform:translateY(-2px);border-color:var(--primaria,#4158D0);}
.stats-card-icon{width:60px;height:60px;background:linear-gradient(135deg,var(--primaria,#4158D0),var(--secundaria,#C850C0));border-radius:18px;display:flex;align-items:center;justify-content:center;font-size:32px;color:white;}
.stats-card-content{flex:1;}
.stats-card-title{font-size:13px;font-weight:600;color:rgba(255,255,255,0.5);text-transform:uppercase;margin-bottom:5px;}
.stats-card-value{font-size:32px;font-weight:800;background:linear-gradient(135deg,#fff,var(--primaria,#4158D0));-webkit-background-clip:text;-webkit-text-fill-color:transparent;line-height:1.1;}
.stats-card-subtitle{font-size:12px;color:rgba(255,255,255,0.4);}
.stats-card-decoration{position:absolute;right:20px;top:50%;transform:translateY(-50%);font-size:80px;opacity:0.05;}
.modern-card{background:var(--fundo_claro,#1e293b);border-radius:16px;border:1px solid rgba(255,255,255,0.08);overflow:hidden;margin-bottom:16px;transition:all .2s;}
.modern-card:hover{border-color:var(--primaria,#4158D0);}
.card-header{padding:14px 18px;display:flex;align-items:center;gap:12px;}
.card-header.primary{background:linear-gradient(135deg,var(--primaria,#4158D0),var(--secundaria,#C850C0));}
.header-icon{width:36px;height:36px;background:rgba(255,255,255,0.2);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;color:white;}
.header-title{font-size:14px;font-weight:700;color:white;}
.header-subtitle{font-size:10px;color:rgba(255,255,255,0.7);}
.card-body{padding:16px;}
.owner-badge{display:flex;align-items:center;gap:10px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.08);border-left:4px solid var(--primaria,#4158D0);border-radius:12px;padding:12px 16px;margin-bottom:18px;font-size:13px;font-weight:600;color:rgba(255,255,255,0.85);}
.owner-badge i{font-size:22px;color:var(--primaria,#4158D0);}
.uuid-badge{display:flex;align-items:center;gap:10px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.07);border-radius:10px;padding:10px 14px;font-family:monospace;font-size:11px;color:rgba(255,255,255,0.5);word-break:break-all;}
.uuid-badge i{font-size:18px;color:#DFAB8C;flex-shrink:0;}
.btn{padding:8px 16px;border:none;border-radius:10px;font-weight:600;font-size:12px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:6px;color:white;transition:all .2s;font-family:inherit;text-decoration:none;}
.btn:hover{transform:translateY(-1px);filter:brightness(1.08);}
.btn-primary{background:linear-gradient(135deg,var(--primaria,#4158D0),var(--secundaria,#C850C0));}
.btn-success{background:linear-gradient(135deg,var(--sucesso,#10b981),#059669);}
.btn-danger{background:linear-gradient(135deg,var(--erro,#dc2626),#b91c1c);}
.btn-back{display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.2);color:white;padding:7px 14px;border-radius:10px;font-size:12px;font-weight:600;cursor:pointer;transition:all .2s;font-family:inherit;}
.btn-back:hover{background:rgba(255,255,255,0.2);transform:translateX(-3px);}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.form-field{display:flex;flex-direction:column;gap:4px;}
.form-field.full-width{grid-column:1/-1;}
.form-field label{font-size:9px;font-weight:700;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.5px;display:flex;align-items:center;gap:4px;}
.form-field label i{font-size:12px;}
.form-control{width:100%;padding:8px 12px;background:rgba(255,255,255,.06);border:1.5px solid rgba(255,255,255,.08);border-radius:9px;color:#fff;font-size:12px;font-family:inherit;outline:none;transition:all .25s;}
.form-control:focus{border-color:var(--primaria,#4158D0);background:rgba(255,255,255,.09);}
.form-control::placeholder{color:rgba(255,255,255,.2);}
select.form-control option{background:var(--fundo_claro,#1e293b);}
.action-buttons{display:flex;justify-content:flex-end;gap:8px;margin-top:18px;flex-wrap:wrap;}
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.85);display:none;align-items:center;justify-content:center;z-index:9999;backdrop-filter:blur(8px);padding:16px;}
.modal-overlay.show{display:flex;}
.modal-container{animation:modalIn .3s ease;max-width:500px;width:90%;}
.modal-container.wide{max-width:620px;}
@keyframes modalIn{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
.modal-content{background:var(--fundo_claro,#1e293b);border-radius:20px;overflow:hidden;border:1px solid rgba(255,255,255,.1);}
.modal-header{padding:14px 18px;display:flex;align-items:center;justify-content:space-between;}
.modal-header h5{margin:0;display:flex;align-items:center;gap:8px;font-size:14px;font-weight:700;color:#fff;}
.modal-header.success{background:linear-gradient(135deg,var(--sucesso,#10b981),#059669);}
.modal-header.error{background:linear-gradient(135deg,var(--erro,#dc2626),#b91c1c);}
.modal-header.primary{background:linear-gradient(135deg,var(--primaria,#4158D0),var(--secundaria,#C850C0));}
.modal-close{background:none;border:none;color:#fff;font-size:20px;cursor:pointer;line-height:1;}
.modal-body{padding:18px;max-height:65vh;overflow-y:auto;}
.modal-footer{border-top:1px solid rgba(255,255,255,.07);padding:12px 18px;display:flex;justify-content:flex-end;gap:8px;flex-wrap:wrap;}
.modal-info-card{background:rgba(255,255,255,.05);border-radius:12px;padding:12px;margin-bottom:12px;}
.modal-info-row{display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.05);}
.modal-info-row:last-child{border-bottom:none;}
.modal-info-label{font-size:11px;font-weight:600;color:rgba(255,255,255,.6);display:flex;align-items:center;gap:6px;}
.modal-info-label i{font-size:15px;}
.modal-info-value{font-size:12px;font-weight:700;color:white;}
.modal-info-value.credential{background:rgba(0,0,0,.3);padding:2px 8px;border-radius:6px;font-family:monospace;}
.modal-info-value.green{color:var(--sucesso,#10b981);}
.modal-server-list{background:rgba(0,0,0,.3);border-radius:10px;padding:10px;margin-top:10px;}
.modal-server-badge{display:inline-block;background:rgba(16,185,129,.2);border:1px solid rgba(16,185,129,.3);color:#10b981;padding:3px 8px;border-radius:16px;font-size:10px;margin:3px;}
.modal-server-badge.fail{background:rgba(220,38,38,.2);border-color:rgba(220,38,38,.3);color:#dc2626;}
.toast-notification{position:fixed;bottom:20px;right:20px;color:#fff;padding:10px 16px;border-radius:10px;display:flex;align-items:center;gap:8px;z-index:10000;animation:toastIn .3s ease;font-weight:600;font-size:12px;}
.toast-notification.ok{background:linear-gradient(135deg,var(--sucesso,#10b981),#059669);}
.toast-notification.err{background:linear-gradient(135deg,var(--erro,#dc2626),#b91c1c);}
@keyframes toastIn{from{transform:translateX(110%);opacity:0}to{transform:translateX(0);opacity:1}}
@media(max-width:768px){
    .app-content{margin-left:0!important;}
    .content-wrapper{padding:10px!important;}
    .form-grid{grid-template-columns:1fr;}
    .action-buttons{flex-direction:column;}
    .btn{width:100%;}
}
</style>
</head>
<body class="<?php echo htmlspecialchars(getBodyClass($temaAtual ?? ['classe'=>'theme-dark'])); ?>">
<div class="app-content content">
<div class="content-overlay"></div>
<div class="content-wrapper">
<div class="content-body">

<div class="stats-card">
    <div class="stats-card-icon"><i class='bx bx-edit-alt'></i></div>
    <div class="stats-card-content">
        <div class="stats-card-title">Editar Usuário</div>
        <div class="stats-card-value"><?php echo htmlspecialchars($logineditar ?: '—'); ?></div>
        <div class="stats-card-subtitle">Modifique os dados do usuário abaixo</div>
    </div>
    <div class="stats-card-decoration"><i class='bx bx-edit-alt'></i></div>
</div>

<div class="modern-card">
    <div class="card-header primary">
        <div class="header-icon"><i class='bx bx-edit-alt'></i></div>
        <div style="flex:1;">
            <div class="header-title">Editar Usuário</div>
            <div class="header-subtitle">Altere os dados e salve</div>
        </div>
        <button type="button" class="btn-back" onclick="window.location.href='listarusuarios.php'">
            <i class='bx bx-arrow-back'></i> Voltar
        </button>
    </div>
    <div class="card-body">

        <div class="owner-badge">
            <i class='bx bx-user-circle'></i>
            <span>Dono da conta: <strong><?php echo htmlspecialchars($dono); ?></strong></span>
        </div>

        <!-- ✅ SEM modal de confirmação — submit direto, sem preventDefault -->
        <form method="POST" action="editarlogin.php?id=<?php echo urlencode($id_final); ?>" id="formEditar">
            <input type="hidden" name="edit_id" value="<?php echo htmlspecialchars($id_final); ?>">
            <!-- ✅ Campo hidden que confirma o POST no PHP -->
            <input type="hidden" name="editauser" value="1">

            <div class="form-grid">

                <div class="form-field">
                    <label><i class='bx bx-user' style="color:#4ECDC4;"></i> Login</label>
                    <input type="text" class="form-control" name="usuarioedit"
                           value="<?php echo htmlspecialchars($logineditar); ?>"
                           placeholder="Login do usuário" required>
                </div>

                <div class="form-field">
                    <label><i class='bx bx-lock-alt' style="color:#45B7D1;"></i> Senha</label>
                    <input type="text" class="form-control" name="senhaedit"
                           value="<?php echo htmlspecialchars($senhaeditar); ?>"
                           placeholder="Senha do usuário" required>
                </div>

                <div class="form-field">
                    <label><i class='bx bx-layer' style="color:#96CEB4;"></i> Limite</label>
                    <input type="number" class="form-control" name="limiteedit"
                           value="<?php echo intval($limiteeditar); ?>" min="1" required>
                </div>

                <div class="form-field">
                    <label><i class='bx bx-calendar' style="color:#FFE194;"></i> Dias (a adicionar)</label>
                    <input type="number" class="form-control" name="validadeedit"
                           value="<?php echo intval($dias); ?>" min="1" required>
                    <small style="color:rgba(255,255,255,.25);font-size:9px;margin-top:3px;">
                        <i class='bx bx-info-circle'></i>
                        Expira em: <?php echo !empty($validadeeditar) ? date('d/m/Y', strtotime($validadeeditar)) : '—'; ?>
                        (<?php echo intval($dias); ?> dias restantes)
                    </small>
                </div>

                <div class="form-field">
                    <label><i class='bx bx-dollar' style="color:#38A169;"></i> Valor Mensal (R$)</label>
                    <input type="number" class="form-control" step="0.01" min="0"
                           name="valormensal"
                           value="<?php echo htmlspecialchars($valormensal); ?>"
                           placeholder="0,00">
                </div>

                <div class="form-field">
                    <label><i class='bx bxl-whatsapp' style="color:#25D366;"></i> WhatsApp</label>
                    <input type="text" class="form-control" name="whatsapp"
                           value="<?php echo htmlspecialchars($whatsapp); ?>"
                           placeholder="5511999999999">
                    <small style="color:rgba(255,255,255,.25);font-size:9px;margin-top:3px;">
                        <i class='bx bx-info-circle'></i> Com DDI. Ex: 5511999999999
                    </small>
                </div>

                <div class="form-field full-width">
                    <label><i class='bx bx-shield-quarter' style="color:#DFAB8C;"></i> UUID V2Ray (somente leitura)</label>
                    <div class="uuid-badge">
                        <i class='bx bx-shield-quarter'></i>
                        <span><?php echo htmlspecialchars($uuid); ?></span>
                    </div>
                    <input type="hidden" name="uuid" value="<?php echo htmlspecialchars($uuid); ?>">
                </div>

                <input type="hidden" name="notas" value="<?php echo htmlspecialchars($notas); ?>">

            </div>
            <div class="action-buttons">
                <button type="button" class="btn btn-danger"
                        onclick="window.location.href='listarusuarios.php'">
                    <i class='bx bx-x'></i> Cancelar
                </button>
                <button type="button" class="btn btn-primary" onclick="copiarDados()">
                    <i class='bx bx-copy'></i> Copiar Dados
                </button>
                <!-- ✅ Botão abre modal de confirmação, NÃO submete o form diretamente -->
                <button type="button" class="btn btn-success" onclick="abrirModal('modalConfirmar')">
                    <i class='bx bx-save'></i> Salvar Alterações
                </button>
            </div>
        </form>
    </div>
</div>

</div></div></div>

<!-- MODAL: SUCESSO -->
<div id="modalSucesso" class="modal-overlay <?php echo $show_modal ? 'show' : ''; ?>">
<div class="modal-container wide"><div class="modal-content">
    <div class="modal-header success">
        <h5><i class='bx bx-check-circle'></i> Usuário Editado com Sucesso!</h5>
        <button class="modal-close" onclick="fecharModal('modalSucesso')"><i class='bx bx-x'></i></button>
    </div>
    <div class="modal-body">
        <div style="text-align:center;margin-bottom:16px;">
            <i class='bx bx-check-circle' style="font-size:54px;color:var(--sucesso,#10b981);filter:drop-shadow(0 0 12px rgba(16,185,129,.4));"></i>
        </div>
        <div class="modal-info-card">
            <div class="modal-info-row">
                <div class="modal-info-label"><i class='bx bx-user'></i> Usuário</div>
                <div class="modal-info-value credential"><?php echo $show_modal ? htmlspecialchars($modal_usuario) : ''; ?></div>
            </div>
            <div class="modal-info-row">
                <div class="modal-info-label"><i class='bx bx-lock-alt'></i> Senha</div>
                <div class="modal-info-value credential"><?php echo $show_modal ? htmlspecialchars($modal_senha) : ''; ?></div>
            </div>
            <div class="modal-info-row">
                <div class="modal-info-label"><i class='bx bx-calendar-check'></i> Validade</div>
                <div class="modal-info-value green"><?php echo $show_modal ? $modal_validade : ''; ?></div>
            </div>
            <div class="modal-info-row">
                <div class="modal-info-label"><i class='bx bx-group'></i> Limite</div>
                <div class="modal-info-value"><?php echo $show_modal ? $modal_limite . ' conexões' : ''; ?></div>
            </div>
        </div>
        <?php if (!empty($sucess_servers)): ?>
        <div class="modal-server-list">
            <div style="font-size:11px;margin-bottom:6px;color:rgba(255,255,255,.6);">
                <i class='bx bx-check-circle' style="color:#10b981;"></i> Atualizado nos servidores:
            </div>
            <?php foreach($sucess_servers as $s): ?>
            <span class="modal-server-badge"><i class='bx bx-server' style="font-size:10px;"></i> <?php echo htmlspecialchars($s); ?></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($failed_servers)): ?>
        <div class="modal-server-list" style="margin-top:8px;">
            <div style="font-size:11px;margin-bottom:6px;color:rgba(220,38,38,.8);">
                <i class='bx bx-error-circle'></i> Falha nos servidores:
            </div>
            <?php foreach($failed_servers as $s): ?>
            <span class="modal-server-badge fail"><?php echo htmlspecialchars($s); ?></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <div class="modal-footer">
        <a href="listarusuarios.php" class="btn btn-danger"><i class='bx bx-list-ul'></i> Ver Lista</a>
        <button class="btn btn-primary" onclick="copiarDados()"><i class='bx bx-copy'></i> Copiar Dados</button>
        <button class="btn btn-success" onclick="fecharModal('modalSucesso')"><i class='bx bx-check'></i> OK</button>
    </div>
</div></div>
</div>

<!-- MODAL: ERRO -->
<div id="modalErro" class="modal-overlay <?php echo $show_error_modal ? 'show' : ''; ?>">
<div class="modal-container"><div class="modal-content">
    <div class="modal-header error">
        <h5><i class='bx bx-error-circle'></i> Erro!</h5>
        <button class="modal-close" onclick="fecharModal('modalErro')"><i class='bx bx-x'></i></button>
    </div>
    <div class="modal-body">
        <div style="text-align:center;margin-bottom:16px;">
            <i class='bx bx-error-circle' style="font-size:54px;color:var(--erro,#dc2626);filter:drop-shadow(0 0 12px rgba(220,38,38,.4));"></i>
        </div>
        <p style="text-align:center;color:rgba(255,255,255,.85);font-size:13px;">
            <?php echo htmlspecialchars($error_message); ?>
        </p>
    </div>
    <div class="modal-footer">
        <button class="btn btn-danger" onclick="fecharModal('modalErro')"><i class='bx bx-check'></i> OK</button>
    </div>
</div></div>
</div>

<!-- MODAL: CONFIRMAÇÃO -->
<div id="modalConfirmar" class="modal-overlay">
<div class="modal-container"><div class="modal-content">
    <div class="modal-header primary">
        <h5><i class='bx bx-question-mark'></i> Confirmar Edição</h5>
        <button class="modal-close" onclick="fecharModal('modalConfirmar')"><i class='bx bx-x'></i></button>
    </div>
    <div class="modal-body">
        <div style="text-align:center;margin-bottom:16px;">
            <i class='bx bx-edit-alt' style="font-size:54px;color:var(--primaria,#4158D0);filter:drop-shadow(0 0 12px rgba(65,88,208,.4));"></i>
        </div>
        <p style="text-align:center;color:rgba(255,255,255,.85);font-size:13px;">
            Tem certeza que deseja salvar as alterações neste usuário?
        </p>
        <p style="text-align:center;color:rgba(255,255,255,.4);font-size:11px;margin-top:6px;">
            O usuário será removido e recriado em todos os servidores.
        </p>
    </div>
    <div class="modal-footer">
        <button class="btn btn-danger" onclick="fecharModal('modalConfirmar')"><i class='bx bx-x'></i> Cancelar</button>
        <!-- ✅ Este botão submete o form diretamente via JS — sem perder o name -->
        <button class="btn btn-success" onclick="confirmarEdicao()"><i class='bx bx-save'></i> Sim, Salvar</button>
    </div>
</div></div>
</div>

<script>
var MODAL_USUARIO  = <?php echo json_encode($show_modal ? $modal_usuario  : ''); ?>;
var MODAL_SENHA    = <?php echo json_encode($show_modal ? $modal_senha    : ''); ?>;
var MODAL_VALIDADE = <?php echo json_encode($show_modal ? $modal_validade : ''); ?>;
var MODAL_LIMITE   = <?php echo json_encode($show_modal ? $modal_limite   : ''); ?>;
var MODAL_DOMINIO  = <?php echo json_encode($_SERVER['HTTP_HOST']); ?>;

function abrirModal(id){ document.getElementById(id).classList.add('show'); }
function fecharModal(id){ document.getElementById(id).classList.remove('show'); }

document.querySelectorAll('.modal-overlay').forEach(function(o){
    o.addEventListener('click', function(e){ if(e.target === o) o.classList.remove('show'); });
});
document.addEventListener('keydown', function(e){
    if(e.key === 'Escape')
        document.querySelectorAll('.modal-overlay.show').forEach(function(m){ m.classList.remove('show'); });
});

// ✅ confirmarEdicao chama .submit() — funciona pois editauser está como hidden field
function confirmarEdicao(){
    fecharModal('modalConfirmar');
    document.getElementById('formEditar').submit();
}

function copiarDados(){
    var u = MODAL_USUARIO || document.querySelector('[name="usuarioedit"]').value;
    var s = MODAL_SENHA   || document.querySelector('[name="senhaedit"]').value;
    var v = MODAL_VALIDADE|| document.querySelector('[name="validadeedit"]').value + ' dias';
    var l = MODAL_LIMITE  || document.querySelector('[name="limiteedit"]').value;
    var t = '✏️ USUÁRIO EDITADO!\n'
          + '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n'
          + '👤 Login: '    + u + '\n'
          + '🔑 Senha: '    + s + '\n'
          + '📅 Validade: ' + v + '\n'
          + '🔗 Limite: '   + l + ' conexões\n'
          + '\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n'
          + '🌐 https://' + MODAL_DOMINIO + '/\n'
          + '📆 Data: ' + new Date().toLocaleString('pt-BR') + '\n';
    navigator.clipboard.writeText(t)
        .then(function(){ mostrarToast('Dados copiados!', 'ok'); })
        .catch(function(){ mostrarToast('Erro ao copiar!', 'err'); });
}

function mostrarToast(msg, tipo){
    var t = document.createElement('div');
    t.className = 'toast-notification ' + (tipo || 'ok');
    t.innerHTML = '<i class="bx ' + (tipo === 'err' ? 'bx-error-circle' : 'bx-check-circle') + '"></i> ' + msg;
    document.body.appendChild(t);
    setTimeout(function(){ t.remove(); }, 3500);
}
</script>
</body>
</html>

