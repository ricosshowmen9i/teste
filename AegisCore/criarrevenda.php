<?php
error_reporting(0);
session_start();

// Configurar fuso horÃ¡rio para BrasÃ­lia
date_default_timezone_set('America/Sao_Paulo');

include('conexao.php');
include('functions.whatsapp.php');
include('header2.php');

$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

if (!file_exists('../admin/suspenderrev.php')) {
    exit("<script>alert('Token Invalido!');</script>");
} else {
    include_once '../admin/suspenderrev.php';
}

if (!isset($_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe']) || !isset($_SESSION['token']) || $_SESSION['tokenatual'] != $_SESSION['token'] || isset($_SESSION['token_invalido_']) && $_SESSION['token_invalido_'] === true) {
    if (function_exists('security')) {
        security();
    } else {
        echo "<script>alert('Token InvÃ¡lido!');</script>";
        echo "<script>location.href='../index.php';</script>";
        $_SESSION['token_invalido_'] = true;
        exit;
    }
}

// Buscar dados do usuÃ¡rio
$sql = "SELECT limite FROM atribuidos WHERE userid = '$_SESSION[iduser]'";
$result = $conn->prepare($sql);
$result->execute();
$result->bind_result($limiteatual);
$result->fetch();
$result->close();

$slq2 = "SELECT sum(limite) AS limiteusado FROM atribuidos where byid='" . $_SESSION['iduser'] . "' ";
$result = $conn->prepare($slq2);
$result->execute();
$result->bind_result($limiteusado);
$result->fetch();
$result->close();

$sql3 = "SELECT * FROM atribuidos WHERE byid = '$_SESSION[iduser]'";
$sql3 = $conn->prepare($sql3);
$sql3->execute();
$sql3->store_result();
$num_rows = $sql3->num_rows;

$slq4 = "SELECT sum(limite) AS numusuarios FROM ssh_accounts where byid='" . $_SESSION['iduser'] . "' ";
$result = $conn->prepare($slq4);
$result->execute();
$result->bind_result($numusuarios);
$result->fetch();
$result->close();

$limiteusado = $limiteusado + $numusuarios;
$restante = $_SESSION['limite'] - $limiteusado;
$_SESSION['restante'] = $restante;

// Consulta dados da conta
$sql5 = "SELECT * FROM atribuidos WHERE userid = '$_SESSION[iduser]'";
$sql5 = $conn->query($sql5);
$row = $sql5->fetch_assoc();
$validade = $row['expira'];
$categoria = $row['categoriaid'];
$tipo = $row['tipo'];
$_SESSION['tipodeconta'] = $row['tipo'];
$_SESSION['limite'] = $row['limite'];

if ($tipo == 'Credito') {
    $tipo_txt = 'Restam ' . $_SESSION['limite'] . ' CrÃ©ditos';
} else {
    $tipo_txt = 'Limite usado: ' . $limiteusado . ' de ' . $_SESSION['limite'];
}

$hoje = date('Y-m-d H:i:s');
$sem_limite = false;
$error_message = '';
$show_error_modal = false;

if ($_SESSION['tipodeconta'] == 'Credito') {
    if ($_SESSION['limite'] <= 0) {
        $sem_limite = true;
        $error_message = 'VocÃª nÃ£o tem crÃ©ditos disponÃ­veis!';
        $show_error_modal = true;
    }
} else {
    if ($validade < $hoje) {
        header('Location: ../home.php?vencido=1');
        exit();
    }
    if ($restante < 1) {
        $sem_limite = true;
        $error_message = 'VocÃª nÃ£o tem limite suficiente! Limite disponÃ­vel: ' . $restante . ' de ' . $_SESSION['limite'];
        $show_error_modal = true;
    }
}

function anti_sql($input) {
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function($match) {
        return '';
    }, $input);
    $seg = trim($seg);
    $seg = strip_tags($seg);
    $seg = addslashes($seg);
    return $seg;
}

// Buscar mensagem configurada para o MODAL
$mensagem_modal = '';
$sql_mensagem_modal = "SELECT mensagem FROM mensagens_modal WHERE funcao = 'criarrevenda' AND byid = '$_SESSION[iduser]' AND ativo = 'ativada'";
$result_mensagem_modal = mysqli_query($conn, $sql_mensagem_modal);
if ($result_mensagem_modal && mysqli_num_rows($result_mensagem_modal) > 0) {
    $row_mensagem_modal = mysqli_fetch_assoc($result_mensagem_modal);
    $mensagem_modal = $row_mensagem_modal['mensagem'];
}

// Processar criaÃ§Ã£o do revendedor
if (!$sem_limite && isset($_POST['submit'])) {
    $usuariorevenda  = anti_sql($_POST['usuariorevenda']);
    $senharevenda    = anti_sql($_POST['senharevenda']);
    $limiterevenda   = anti_sql($_POST['limiterevenda']);
    $validaderevenda = anti_sql($_POST['validaderevenda']);
    $whatsapp        = anti_sql($_POST['whatsapp']);
    $valor_revenda   = anti_sql($_POST['valor_revenda'] ?? '0');

    // ValidaÃ§Ãµes
    if (strlen($usuariorevenda) < 5) {
        $error_message = 'UsuÃ¡rio deve ter no mÃ­nimo 5 caracteres!';
        $show_error_modal = true;
    } elseif (strlen($usuariorevenda) > 10) {
        $error_message = 'UsuÃ¡rio deve ter no mÃ¡ximo 10 caracteres!';
        $show_error_modal = true;
    } elseif (strlen($senharevenda) < 5) {
        $error_message = 'Senha deve ter no mÃ­nimo 5 caracteres!';
        $show_error_modal = true;
    } elseif (strlen($senharevenda) > 10) {
        $error_message = 'Senha deve ter no mÃ¡ximo 10 caracteres!';
        $show_error_modal = true;
    } elseif (preg_match('/[^a-z0-9]/i', $usuariorevenda)) {
        $error_message = 'UsuÃ¡rio nÃ£o pode conter caracteres especiais!';
        $show_error_modal = true;
    } elseif (preg_match('/[^a-z0-9]/i', $senharevenda)) {
        $error_message = 'Senha nÃ£o pode conter caracteres especiais!';
        $show_error_modal = true;
    } elseif ($_SESSION['tipodeconta'] == 'Credito') {
        if ($limiterevenda > $_SESSION['limite']) {
            $error_message = 'VocÃª nÃ£o tem limite suficiente! Limite disponÃ­vel: ' . $_SESSION['limite'];
            $show_error_modal = true;
        }
    } else {
        if ($limiterevenda > $restante) {
            $error_message = 'VocÃª nÃ£o tem limite suficiente! Limite disponÃ­vel: ' . $restante;
            $show_error_modal = true;
        } elseif ($validaderevenda > 90) {
            $error_message = 'MÃ¡ximo permitido Ã© 90 dias!';
            $show_error_modal = true;
        } elseif ($validaderevenda < 1) {
            $error_message = 'Validade deve ser maior que 0 dias!';
            $show_error_modal = true;
        }
    }

    // Verificar se usuÃ¡rio jÃ¡ existe
    if (!$show_error_modal) {
        $sql_check = "SELECT * FROM accounts WHERE login = '$usuariorevenda'";
        $result_check = mysqli_query($conn, $sql_check);
        if (mysqli_num_rows($result_check) > 0) {
            $error_message = 'Revendedor jÃ¡ existe!';
            $show_error_modal = true;
        }
    }

    if (!$show_error_modal) {
        // Inserir na tabela accounts
        $sql_insert = "INSERT INTO accounts (login, senha, byid, whatsapp, valorrevenda) VALUES ('$usuariorevenda', '$senharevenda', '$_SESSION[iduser]', '$whatsapp', '$valor_revenda')";
        mysqli_query($conn, $sql_insert);

        // Pegar o ID do revendedor criado
        $sql_id = "SELECT id FROM accounts WHERE login = '$usuariorevenda'";
        $result_id = mysqli_query($conn, $sql_id);
        $row_id = mysqli_fetch_assoc($result_id);
        $idrevenda = $row_id['id'];

        $validade_formatada = '';
        $data_validade = '';

        // Inserir na tabela atribuidos
        if ($_SESSION['tipodeconta'] == 'Credito') {
            $credivalid = "Credito";
            $validade_formatada = "Nunca";
            $sql_atribuidos = "INSERT INTO atribuidos (userid, byid, limite, categoriaid, tipo, valor) VALUES ('$idrevenda', '$_SESSION[iduser]', '$limiterevenda', '$categoria', '$credivalid', '$valor_revenda')";
            mysqli_query($conn, $sql_atribuidos);
            $sql_update = "UPDATE atribuidos SET limite = limite - '$limiterevenda' WHERE userid = '$_SESSION[iduser]'";
            mysqli_query($conn, $sql_update);
        } else {
            $credivalid = "Validade";
            $validade_formatada = $validaderevenda . " dias";
            $data_validade = date('Y-m-d H:i:s', strtotime("+" . $validaderevenda . " days"));
            $sql_atribuidos = "INSERT INTO atribuidos (userid, byid, limite, expira, categoriaid, tipo, valor) VALUES ('$idrevenda', '$_SESSION[iduser]', '$limiterevenda', '$data_validade', '$categoria', '$credivalid', '$valor_revenda')";
            mysqli_query($conn, $sql_atribuidos);
        }

        // Registrar log
        $datahoje = date('d-m-Y H:i:s');
        $sql_log = "INSERT INTO logs (revenda, byid, validade, texto, userid) VALUES ('$_SESSION[login]', '$_SESSION[byid]', '$datahoje', 'Criou o Revendedor $usuariorevenda', '$_SESSION[iduser]')";
        mysqli_query($conn, $sql_log);
        
        // DISPARAR WHATSAPP VIA BACKEND
        if (!empty($whatsapp)) {
            $dados_msg = [
                'usuario'  => $usuariorevenda,
                'senha'    => $senharevenda,
                'validade' => $validade_formatada,
                'limite'   => $limiterevenda,
                'valor'    => $valor_revenda,
                'whatsapp' => $whatsapp
            ];
            dispararMensagemAutomatica($conn, $_SESSION['iduser'], 'criarrevenda', $dados_msg);
        }

        // Salvar dados na sessÃ£o para o modal
        $_SESSION['modal_usuario']       = $usuariorevenda;
        $_SESSION['modal_senha']         = $senharevenda;
        $_SESSION['modal_limite']        = $limiterevenda;
        $_SESSION['modal_validade']      = $validade_formatada;
        $_SESSION['modal_data_validade'] = $data_validade;
        $_SESSION['modal_whatsapp']      = $whatsapp;
        $_SESSION['modal_valor_revenda'] = $valor_revenda;
        $_SESSION['modal_mensagem']      = $mensagem_modal;
        $_SESSION['show_modal']          = true;

        // Redirecionar
        echo "<script>window.location.href = 'criarrevenda.php?modal=1';</script>";
        exit();
    }
}

// ========== VERIFICAR SE DEVE MOSTRAR O MODAL DE SUCESSO ==========
$show_modal = false;
$modal_usuario = '';
$modal_senha = '';
$modal_limite = '';
$modal_validade = '';
$modal_data_validade = '';
$modal_whatsapp = '';
$modal_valor_revenda = '0';
$modal_mensagem = '';
$mensagem_final = '';

if (isset($_GET['modal']) && $_GET['modal'] == 1 && isset($_SESSION['show_modal']) && $_SESSION['show_modal'] === true) {
    $show_modal          = true;
    $modal_usuario       = $_SESSION['modal_usuario'] ?? '';
    $modal_senha         = $_SESSION['modal_senha'] ?? '';
    $modal_limite        = $_SESSION['modal_limite'] ?? '';
    $modal_validade      = $_SESSION['modal_validade'] ?? '';
    $modal_data_validade = $_SESSION['modal_data_validade'] ?? '';
    $modal_whatsapp      = $_SESSION['modal_whatsapp'] ?? '';
    $modal_valor_revenda = isset($_SESSION['modal_valor_revenda']) ? $_SESSION['modal_valor_revenda'] : '0';
    $modal_mensagem      = $_SESSION['modal_mensagem'] ?? '';

    // Processar mensagem com variÃ¡veis
    $mensagem_final = $modal_mensagem;
    if (!empty($mensagem_final)) {
        $mensagem_final = str_replace('{usuario}', $modal_usuario, $mensagem_final);
        $mensagem_final = str_replace('{login}',   $modal_usuario, $mensagem_final);
        $mensagem_final = str_replace('{senha}',   $modal_senha,   $mensagem_final);
        $mensagem_final = str_replace('{validade}', $modal_validade, $mensagem_final);
        $mensagem_final = str_replace('{limite}',  $modal_limite,  $mensagem_final);
        $mensagem_final = str_replace('{valor}',   number_format(floatval($modal_valor_revenda), 2, ',', '.'), $mensagem_final);
        $mensagem_final = str_replace('{dominio}', $_SERVER['HTTP_HOST'], $mensagem_final);
        $mensagem_final = nl2br(htmlspecialchars($mensagem_final));
    }

    // Limpar sessÃ£o
    unset($_SESSION['modal_usuario'], $_SESSION['modal_senha'], $_SESSION['modal_limite'],
          $_SESSION['modal_validade'], $_SESSION['modal_data_validade'], $_SESSION['modal_whatsapp'],
          $_SESSION['modal_valor_revenda'], $_SESSION['modal_mensagem'], $_SESSION['show_modal']);
}
?>
<style>
    :root {
        --primary: #4158D0;
        --secondary: #C850C0;
        --tertiary: #FFCC70;
        --success: #10b981;
        --danger: #dc2626;
        --warning: #f59e0b;
        --info: #3b82f6;
        --dark: #2c3e50;
    }

    .info-badge {
        display: inline-flex !important; align-items: center !important; gap: 8px !important;
        background: white !important; color: var(--dark) !important;
        padding: 8px 16px !important; border-radius: 30px !important; font-size: 13px !important;
        margin-top: 5px !important; margin-bottom: 15px !important;
        border-left: 4px solid var(--primary) !important;
        box-shadow: 0 3px 10px rgba(0,0,0,0.1) !important;
    }
    .info-badge i { font-size: 22px; color: var(--primary); }

    .status-info {
        background: linear-gradient(135deg, #1e293b, #0f172a);
        border-radius: 14px; padding: 12px 18px; margin-bottom: 15px;
        border: 1px solid rgba(255,255,255,0.1);
        display: flex; align-items: center; justify-content: space-between;
        flex-wrap: wrap; gap: 10px; color: white;
    }
    .status-item { display: flex !important; align-items: center !important; gap: 6px !important; }
    .status-item i { font-size: 20px !important; color: var(--tertiary) !important; }
    .status-item span { font-size: 12px !important; font-weight: 500 !important; }

    .modern-card {
        background: linear-gradient(135deg, #1e293b, #0f172a) !important;
        border-radius: 20px !important; border: 1px solid rgba(255,255,255,0.08) !important;
        overflow: hidden !important; position: relative !important;
        box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important;
        width: 100% !important; animation: fadeIn 0.5s ease !important;
        margin-bottom: 8px !important; max-width: 100% !important;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .card-bg-shapes { position: absolute; inset: 0; pointer-events: none; z-index: 0; overflow: hidden; }
    .modern-card .card-header {
        padding: 16px 20px 12px !important; border-bottom: 1px solid rgba(255,255,255,0.07) !important;
        display: flex !important; align-items: center !important; gap: 10px !important;
        position: relative; z-index: 1;
    }
    .header-icon {
        width: 36px; height: 36px; border-radius: 10px;
        background: linear-gradient(135deg, #C850C0, #4158D0);
        display: flex; align-items: center; justify-content: center;
        font-size: 18px; color: white; flex-shrink: 0;
    }
    .header-title { font-size: 14px; font-weight: 700; color: white; }
    .header-subtitle { font-size: 10px; color: rgba(255,255,255,0.35); }
    .limite-badge {
        margin-left: auto; display: inline-flex; align-items: center; gap: 5px;
        background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.08);
        border-radius: 8px; padding: 4px 8px; font-size: 10px; font-weight: 600;
        color: rgba(255,255,255,0.5);
    }
    .modern-card .card-body { padding: 18px 20px !important; position: relative; z-index: 1; }

    .btn-action {
        padding: 8px 16px !important; border: none !important; border-radius: 8px !important;
        font-weight: 700 !important; font-size: 12px !important; cursor: pointer !important;
        transition: all 0.2s !important; display: inline-flex !important;
        align-items: center !important; justify-content: center !important;
        gap: 6px !important; font-family: inherit !important;
        box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important; margin-bottom: 15px !important;
    }
    .btn-primary { background: linear-gradient(135deg, #4158D0, #6366f1) !important; color: white !important; }
    .btn-primary:hover { transform: translateY(-2px) !important; box-shadow: 0 6px 15px rgba(65,88,208,0.5) !important; }
    .btn-success { background: linear-gradient(135deg, #10b981, #059669) !important; color: white !important; }
    .btn-success:hover { transform: translateY(-2px) !important; box-shadow: 0 6px 15px rgba(16,185,129,0.5) !important; }
    .btn-danger  { background: linear-gradient(135deg, #dc2626, #b91c1c) !important; color: white !important; }
    .btn-danger:hover  { transform: translateY(-2px) !important; box-shadow: 0 6px 15px rgba(220,38,38,0.5) !important; }

    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .form-field { display: flex; flex-direction: column; gap: 4px; }
    .form-field.full-width { grid-column: 1 / -1; }
    .form-field label {
        font-size: 9px; font-weight: 700; color: rgba(255,255,255,0.4);
        text-transform: uppercase; letter-spacing: 0.5px;
        display: flex; align-items: center; gap: 4px;
    }
    .form-field label i { font-size: 12px; }
    .form-control {
        width: 100%; padding: 8px 12px;
        background: rgba(255,255,255,0.06); border: 1.5px solid rgba(255,255,255,0.08);
        border-radius: 9px; color: white; font-size: 12px; font-family: inherit;
        outline: none; transition: all 0.25s;
    }
    .form-control:focus { border-color: rgba(65,88,208,0.6); background: rgba(255,255,255,0.09); }
    .form-control::placeholder { color: rgba(255,255,255,0.2); }

    .dias-select { display: grid; grid-template-columns: repeat(5, 1fr); gap: 6px; margin-top: 4px; }
    .dia-option {
        background: rgba(255,255,255,0.06); border: 1.5px solid rgba(255,255,255,0.08);
        border-radius: 8px; padding: 8px 4px; text-align: center; cursor: pointer;
        transition: all 0.3s; font-size: 12px; font-weight: 600; color: rgba(255,255,255,0.7);
    }
    .dia-option:hover { background: rgba(255,255,255,0.1); border-color: rgba(65,88,208,0.6); }
    .dia-option.active { background: linear-gradient(135deg, #4158D0, #C850C0); color: white; border-color: transparent; }

    .action-buttons { display: flex; justify-content: flex-end; gap: 8px; margin-top: 18px; flex-wrap: wrap; }
    .icon-user     { color: #818cf8; } .icon-lock     { color: #e879f9; }
    .icon-group    { color: #34d399; } .icon-calendar { color: #fbbf24; }
    .icon-whatsapp { color: #34d399; } .icon-money    { color: #10b981; }
    .icon-time     { color: #fbbf24; }

    /* MODAIS */
    .modal-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.85); display: none;
        align-items: center; justify-content: center;
        z-index: 9999; backdrop-filter: blur(8px);
    }
    .modal-overlay.show { display: flex; }

    .modal-container {
        animation: modalIn 0.4s cubic-bezier(0.34,1.2,0.64,1);
        max-width: 500px; width: 90%;
    }
    @keyframes modalIn {
        from { opacity:0; transform: scale(0.9) translateY(-30px); }
        to   { opacity:1; transform: scale(1)   translateY(0); }
    }

    .modal-content {
        background: linear-gradient(135deg, #1e293b, #0f172a);
        border-radius: 24px; overflow: hidden;
        border: 1px solid rgba(255,255,255,0.15);
        box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
    }

    .modal-header {
        color: white; padding: 20px 24px;
        display: flex; align-items: center; justify-content: space-between;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    .modal-header h5 { margin:0; display:flex; align-items:center; gap:10px; font-size:18px; font-weight:600; }
    .modal-header.success  { background: linear-gradient(135deg, #10b981, #059669); }
    .modal-header.error    { background: linear-gradient(135deg, #dc2626, #b91c1c); }
    .modal-header.info     { background: linear-gradient(135deg, #4158D0, #C850C0); }

    .modal-close { background:none; border:none; color:white; font-size:24px; cursor:pointer; opacity:.8; transition:opacity .2s; }
    .modal-close:hover { opacity:1; }

    .modal-body { padding: 24px; color: white; max-height: 70vh; overflow-y: auto; }
    .modal-footer {
        border-top: 1px solid rgba(255,255,255,0.1);
        padding: 16px 24px; display:flex; justify-content:center; gap:12px; flex-wrap:wrap;
    }

    .modal-big-icon { text-align:center; margin-bottom:20px; }
    .modal-big-icon i { font-size:70px; }
    .modal-big-icon.success i { color:#10b981; filter:drop-shadow(0 0 15px rgba(16,185,129,.5)); }
    .modal-big-icon.error   i { color:#dc2626; filter:drop-shadow(0 0 12px rgba(220,38,38,.5)); }
    .modal-big-icon.info    i { color:#818cf8; filter:drop-shadow(0 0 12px rgba(129,140,248,.4)); }

    .modal-info-card {
        background: rgba(255,255,255,0.05); border-radius:16px;
        padding:16px; margin-bottom:16px; border:1px solid rgba(255,255,255,0.08);
    }
    .modal-info-row {
        display:flex; align-items:center; justify-content:space-between;
        padding:10px 0; border-bottom:1px solid rgba(255,255,255,0.05);
    }
    .modal-info-row:last-child { border-bottom:none; }
    .modal-info-label { font-size:12px; font-weight:600; color:rgba(255,255,255,0.6); display:flex; align-items:center; gap:8px; }
    .modal-info-label i { font-size:18px; }
    .modal-info-value { font-size:13px; font-weight:700; color:white; }
    .modal-info-value.credential {
        background: rgba(0,0,0,0.3); padding:4px 10px;
        border-radius:8px; font-family:monospace; letter-spacing:.5px;
    }
    .modal-info-value.green { color:#10b981; }

    .modal-divider { border:none; border-top:1px solid rgba(255,255,255,0.1); margin:16px 0; }
    .modal-success-title { text-align:center; color:#10b981; font-weight:700; font-size:14px; margin-top:12px; }

    .mensagem-box {
        background: rgba(65,88,208,0.1); border-left:3px solid #4158D0;
        border-radius:10px; padding:12px; margin-top:10px; font-size:12px; line-height:1.5;
    }
    .mensagem-box p { margin:0; color:rgba(255,255,255,0.9); }

    .btn-modal {
        padding:9px 20px; border:none; border-radius:10px; font-weight:700; font-size:13px;
        cursor:pointer; transition:all .2s; display:inline-flex; align-items:center;
        gap:6px; font-family:inherit; box-shadow:0 3px 8px rgba(0,0,0,.2);
        color:white; text-decoration:none; justify-content:center;
    }
    .btn-modal.primary   { background:linear-gradient(135deg,#4158D0,#6366f1); }
    .btn-modal.primary:hover   { transform:translateY(-2px); box-shadow:0 6px 15px rgba(65,88,208,.5); color:white; }
    .btn-modal.success   { background:linear-gradient(135deg,#10b981,#059669); }
    .btn-modal.success:hover   { transform:translateY(-2px); box-shadow:0 6px 15px rgba(16,185,129,.5); color:white; }
    .btn-modal.danger    { background:linear-gradient(135deg,#dc2626,#b91c1c); }
    .btn-modal.danger:hover    { transform:translateY(-2px); box-shadow:0 6px 15px rgba(220,38,38,.5); color:white; }
    .btn-modal.whatsapp  { background:linear-gradient(135deg,#25D366,#128C7E); }
    .btn-modal.whatsapp:hover  { transform:translateY(-2px); box-shadow:0 6px 15px rgba(37,211,102,.5); color:white; }
    .btn-modal.gray      { background:linear-gradient(135deg,#64748b,#475569); }
    .btn-modal.gray:hover      { transform:translateY(-2px); box-shadow:0 6px 15px rgba(100,116,139,.5); color:white; }

    .toast-notification {
        position:fixed; bottom:24px; right:24px;
        background:linear-gradient(135deg,#10b981,#059669); color:white;
        padding:12px 20px; border-radius:12px; display:flex; align-items:center; gap:10px;
        z-index:10000; animation:slideIn .3s ease; box-shadow:0 4px 20px rgba(0,0,0,.4);
        font-weight:600; font-size:13px;
    }
    @keyframes slideIn {
        from { transform:translateX(110%); opacity:0; }
        to   { transform:translateX(0);    opacity:1; }
    }

    @media (max-width:768px) {
        .form-grid { grid-template-columns:1fr; }
        .modern-card .card-header { flex-direction:row !important; flex-wrap:wrap !important; justify-content:space-between !important; align-items:center !important; text-align:left !important; gap:5px !important; }
        .header-icon { width:32px !important; height:32px !important; font-size:16px !important; }
        .header-title { font-size:13px !important; white-space:nowrap !important; }
        .header-subtitle { font-size:9px !important; white-space:nowrap !important; }
        .limite-badge { margin-left:0 !important; order:3 !important; width:100% !important; justify-content:center !important; margin-top:5px !important; }
        .action-buttons { flex-direction:column !important; gap:8px !important; }
        .action-buttons .btn-danger, .action-buttons .btn-success { width:100% !important; margin:0 !important; }
        .btn-action { width:100%; }
        .dias-select { grid-template-columns:repeat(3,1fr); }
        .modal-container { width:95%; }
        .modal-info-row { flex-direction:column; align-items:flex-start; gap:6px; }
        .modal-footer { flex-direction:column; }
        .btn-modal { width:100%; }
    }
</style>

<div style="max-width: 780px; margin: 0 auto; padding: 0 16px;">

    <div class="info-badge">
        <i class='bx bx-store-alt'></i>
        <span>Criar Revendedor</span>
    </div>

    <div class="status-info">
        <div class="status-item">
            <i class='bx bx-info-circle'></i>
            <span><?php echo $tipo_txt; ?></span>
        </div>
        <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
        <div class="status-item">
            <i class='bx bx-time icon-time'></i>
            <span>Validade: <?php echo date('d/m/Y', strtotime($validade)); ?></span>
        </div>
        <?php endif; ?>
    </div>

    <div class="modern-card">
        <div class="card-bg-shapes">
            <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                <circle cx="95%" cy="6%"  r="60" fill="rgba(200,80,192,0.07)"/>
                <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                <polygon points="85%,30% 98%,52% 72%,52%" fill="rgba(255,204,112,0.05)"/>
                <polygon points="3%,8%  10%,22% -4%,22%"  fill="rgba(16,185,129,0.06)"/>
                <polygon points="8%,72%  2%,58%  16%,62%  13%,76%  4%,79%  1%,66%" fill="rgba(139,92,246,0.05)"/>
                <rect x="78%" y="55%" width="38" height="38" rx="8" fill="rgba(59,130,246,0.05)" transform="rotate(25,97,74)"/>
                <circle cx="50%" cy="2%"  r="20" fill="rgba(245,158,11,0.04)"/>
            </svg>
        </div>

        <div class="card-header">
            <div class="header-icon"><i class='bx bx-store-alt'></i></div>
            <div>
                <div class="header-title">Criar Revendedor</div>
                <div class="header-subtitle">Preencha os dados do revendedor</div>
            </div>
            <div class="limite-badge">
                <i class='bx bx-bar-chart-alt' style="color:#10b981;"></i>
                <?php echo $tipo_txt; ?>
            </div>
        </div>

        <div class="card-body">
            <?php if (!$sem_limite): ?>
            <button type="button" class="btn-action btn-primary" onclick="abrirModalGerar()">
                <i class='bx bx-shuffle'></i> Gerar Aleat&oacute;rio
            </button>
            <?php endif; ?>

            <form action="criarrevenda.php" method="POST">
                <div class="form-grid">
                    <div class="form-field">
                        <label><i class='bx bx-user icon-user'></i> Login (5 a 10 caracteres)</label>
                        <input type="text" class="form-control" name="usuariorevenda" placeholder="ex: revendedor123" minlength="5" maxlength="10" id="usuariorevenda" <?php echo $sem_limite ? 'disabled' : ''; ?> required>
                    </div>
                    <div class="form-field">
                        <label><i class='bx bx-lock-alt icon-lock'></i> Senha (5 a 10 caracteres)</label>
                        <input type="text" class="form-control" name="senharevenda" placeholder="ex: senha123" minlength="5" maxlength="10" id="senharevenda" <?php echo $sem_limite ? 'disabled' : ''; ?> required>
                    </div>
                    <div class="form-field">
                        <label><i class='bx bx-layer icon-group'></i> Limite (M&aacute;x. <?php echo $_SESSION['tipodeconta'] == 'Credito' ? $_SESSION['limite'] : $restante; ?>)</label>
                        <input type="number" class="form-control" value="1" min="1" max="<?php echo $_SESSION['tipodeconta'] == 'Credito' ? $_SESSION['limite'] : $restante; ?>" name="limiterevenda" id="limiterevenda" <?php echo $sem_limite ? 'disabled' : ''; ?> required>
                    </div>
                    <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
                    <div class="form-field full-width">
                        <label><i class='bx bx-calendar icon-calendar'></i> Dias (m&aacute;ximo 90 dias)</label>
                        <input type="hidden" name="validaderevenda" id="validaderevenda" value="30">
                        <div class="dias-select" id="diasSelector">
                            <div class="dia-option" data-dias="1">1 dia</div>
                            <div class="dia-option" data-dias="7">7 dias</div>
                            <div class="dia-option active" data-dias="30">30 dias</div>
                            <div class="dia-option" data-dias="60">60 dias</div>
                            <div class="dia-option" data-dias="90">90 dias</div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="form-field">
                        <label><i class='bx bx-dollar icon-money'></i> Valor do Revendedor (R$)</label>
                        <input type="number" class="form-control" step="0.01" min="0" name="valor_revenda" id="valor_revenda" placeholder="0,00" value="0" <?php echo $sem_limite ? 'disabled' : ''; ?>>
                        <small style="color:rgba(255,255,255,0.3);margin-top:3px;display:block;font-size:9px;">
                            <i class='bx bx-info-circle'></i> Valor para renova&ccedil;&atilde;o autom&aacute;tica (0 = desativado)
                        </small>
                    </div>
                    <div class="form-field full-width">
                        <label><i class='bx bxl-whatsapp icon-whatsapp'></i> WhatsApp do Revendedor</label>
                        <input type="text" class="form-control" name="whatsapp" placeholder="5511999999999" <?php echo $sem_limite ? 'disabled' : ''; ?>>
                        <small style="color:rgba(255,255,255,0.3);margin-top:3px;display:block;font-size:9px;">
                            <i class='bx bx-info-circle' style="color:#a78bfa;"></i> N&uacute;mero igual ao WhatsApp
                        </small>
                    </div>
                </div>
                <?php if (!$sem_limite): ?>
                <div class="action-buttons">
                    <button type="reset" class="btn-action btn-danger"><i class='bx bx-x'></i> Cancelar</button>
                    <button type="submit" class="btn-action btn-success" name="submit"><i class='bx bx-check'></i> Criar Revendedor</button>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

</div>

<!-- MODAL: GERAR ALEAT&Oacute;RIO -->
<div id="modalGerar" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-header info">
                <h5><i class='bx bx-shuffle'></i> Dados Gerados!</h5>
                <button class="modal-close" onclick="fecharModal('modalGerar')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body">
                <div class="modal-big-icon info"><i class='bx bx-shuffle'></i></div>
                <div class="modal-info-card">
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-store-alt' style="color:#818cf8;"></i> Login gerado</div>
                        <div class="modal-info-value credential" id="gerar-login-preview">&mdash;</div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-lock-alt' style="color:#e879f9;"></i> Senha gerada</div>
                        <div class="modal-info-value credential" id="gerar-senha-preview">&mdash;</div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-group' style="color:#34d399;"></i> Limite</div>
                        <div class="modal-info-value">1 conex&atilde;o</div>
                    </div>
                    <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-calendar' style="color:#fbbf24;"></i> Dias</div>
                        <div class="modal-info-value">30 dias</div>
                    </div>
                    <?php endif; ?>
                </div>
                <p style="text-align:center;color:rgba(255,255,255,0.5);font-size:12px;">
                    Os campos do formul&aacute;rio foram preenchidos automaticamente.
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn-modal success" onclick="fecharModal('modalGerar')">
                    <i class='bx bx-check'></i> OK, usar esses dados
                </button>
                <button class="btn-modal gray" onclick="gerarNovamente()">
                    <i class='bx bx-refresh'></i> Gerar outros
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: SUCESSO AO CRIAR -->
<div id="modalSucesso" class="modal-overlay <?php echo $show_modal ? 'show' : ''; ?>">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-header success">
                <h5><i class='bx bx-check-circle'></i> Revendedor Criado com Sucesso!</h5>
                <button class="modal-close" onclick="fecharModal('modalSucesso')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body" id="divToCopy">
                <div class="modal-big-icon success"><i class='bx bx-check-circle'></i></div>
                <div class="modal-info-card">
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-store-alt' style="color:#818cf8;"></i> Usu&aacute;rio</div>
                        <div class="modal-info-value credential"><?php echo $show_modal ? htmlspecialchars($modal_usuario) : ''; ?></div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-lock-alt' style="color:#e879f9;"></i> Senha</div>
                        <div class="modal-info-value credential"><?php echo $show_modal ? htmlspecialchars($modal_senha) : ''; ?></div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-calendar' style="color:#fbbf24;"></i> Validade</div>
                        <div class="modal-info-value green"><?php echo $show_modal ? $modal_validade : ''; ?></div>
                    </div>
                    <?php if ($show_modal && !empty($modal_data_validade) && $modal_validade != 'Nunca'): ?>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-calendar-check' style="color:#fbbf24;"></i> Vencimento</div>
                        <div class="modal-info-value green"><?php echo date('d/m/Y', strtotime($modal_data_validade)); ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-group' style="color:#34d399;"></i> Limite</div>
                        <div class="modal-info-value"><?php echo $show_modal ? $modal_limite . ' conex&otilde;es' : ''; ?></div>
                    </div>
                    <div class="modal-info-row">
                        <div class="modal-info-label"><i class='bx bx-dollar' style="color:#10b981;"></i> Valor do Revendedor</div>
                        <div class="modal-info-value">R$ <?php echo number_format(floatval($modal_valor_revenda), 2, ',', '.'); ?></div>
                    </div>
                </div>

                <?php if (!empty($mensagem_final)): ?>
                <hr class="modal-divider">
                <div class="mensagem-box"><?php echo $mensagem_final; ?></div>
                <?php endif; ?>

                <hr class="modal-divider">
                <p class="modal-success-title">&#10024; Revendedor criado com sucesso! &#10024;</p>
            </div>
            <div class="modal-footer">
                <a href="listarrevendedores.php" class="btn-modal danger"><i class='bx bx-list-ul'></i> Lista</a>
                <button class="btn-modal whatsapp" onclick="shareOnWhatsApp()"><i class='bx bxl-whatsapp'></i> WhatsApp</button>
                <button class="btn-modal primary" onclick="copiarDados()"><i class='bx bx-copy'></i> Copiar</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: ERRO -->
<div id="modalErro" class="modal-overlay <?php echo $show_error_modal ? 'show' : ''; ?>">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-header error">
                <h5><i class='bx bx-error-circle'></i> Erro!</h5>
                <button class="modal-close" onclick="fecharModalErro()"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body">
                <div class="modal-big-icon error"><i class='bx bx-error-circle'></i></div>
                <h3 style="color:white;text-align:center;margin-bottom:10px;">Ops! Algo deu errado</h3>
                <p style="color:rgba(255,255,255,0.8);text-align:center;"><?php echo $error_message; ?></p>
                <div style="margin-top: 20px; text-align: center;">
                    <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                        <button class="btn-modal success" onclick="fecharModalErro()">
                            <i class='bx bx-check'></i> OK
                        </button>
                        <a href="../home.php" class="btn-modal" style="background: linear-gradient(135deg, #10b981, #059669);">
                            <i class='bx bx-shopping-bag'></i> Comprar Mais
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="../app-assets/js/scripts/forms/number-input.js"></script>
<script>
    <?php if ($_SESSION['tipodeconta'] != 'Credito' && !$sem_limite): ?>
    document.querySelectorAll('.dia-option').forEach(function(opt) {
        opt.addEventListener('click', function() {
            document.querySelectorAll('.dia-option').forEach(function(o){ o.classList.remove('active'); });
            this.classList.add('active');
            document.getElementById('validaderevenda').value = this.dataset.dias;
        });
    });
    <?php endif; ?>

    /* ── GERAR ALEAT&Oacute;RIO ──────────────────────── */
    function gerarDados() {
        var letras  = "abcdefghijklmnopqrstuvwxyz";
        var numeros = "0123456789";

        var usuario = "rev" + Math.floor(Math.random() * 1000);

        var chars = letras + numeros;
        var senha = "";
        for (var i = 0; i < 8; i++) senha += chars.charAt(Math.floor(Math.random() * chars.length));

        document.getElementById('usuariorevenda').value = usuario;
        document.getElementById('senharevenda').value   = senha;
        document.getElementById('limiterevenda').value  = 1;
        document.getElementById('valor_revenda').value  = "0";

        <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
        document.querySelectorAll('.dia-option').forEach(function(o){ o.classList.remove('active'); });
        document.querySelectorAll('.dia-option')[2].classList.add('active');
        document.getElementById('validaderevenda').value = '30';
        <?php endif; ?>

        return { usuario: usuario, senha: senha };
    }

    function abrirModalGerar() {
        var dados = gerarDados();
        document.getElementById('gerar-login-preview').textContent = dados.usuario;
        document.getElementById('gerar-senha-preview').textContent = dados.senha;
        abrirModal('modalGerar');
    }

    function gerarNovamente() {
        var dados = gerarDados();
        document.getElementById('gerar-login-preview').textContent = dados.usuario;
        document.getElementById('gerar-senha-preview').textContent = dados.senha;
        mostrarToast('Novos dados gerados!');
    }

    /* ── HELPERS MODAIS ───────────────────────── */
    function abrirModal(id) { document.getElementById(id).classList.add('show'); }
    function fecharModal(id) { document.getElementById(id).classList.remove('show'); }

    function fecharModalErro() {
        document.getElementById('modalErro').classList.remove('show');
    }

    /* ── COPIAR ──────────────────────────────── */
    function copiarDados() {
        var u   = '<?php echo $show_modal ? addslashes($modal_usuario) : ""; ?>';
        var s   = '<?php echo $show_modal ? addslashes($modal_senha) : ""; ?>';
        var v   = '<?php echo $show_modal ? addslashes($modal_validade) : ""; ?>';
        var l   = '<?php echo $show_modal ? $modal_limite : ""; ?>';
        var val = 'R$ <?php echo number_format(floatval($modal_valor_revenda), 2, ",", "."); ?>';
        <?php if ($show_modal && !empty($modal_data_validade) && $modal_validade != 'Nunca'): ?>
        var venc = '<?php echo date("d/m/Y", strtotime($modal_data_validade)); ?>';
        <?php else: ?>
        var venc = 'Nunca';
        <?php endif; ?>

        var texto = "\u2705 REVENDEDOR CRIADO COM SUCESSO!\n";
        texto += "\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\n\n";
        texto += "\ud83d\udc64 Login: " + u + "\n";
        texto += "\ud83d\udd12 Senha: " + s + "\n";
        texto += "\ud83d\udcc5 Validade: " + v + "\n";
        texto += "\ud83d\uddd3\ufe0f Vencimento: " + venc + "\n";
        texto += "\ud83d\udd17 Limite: " + l + " conex\u00f5es\n";
        texto += "\ud83d\udcb0 Valor do Revendedor: " + val + "\n";
        texto += "\n\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\n";
        texto += "\ud83d\udcc6 Data: " + new Date().toLocaleString('pt-BR') + "\n";

        navigator.clipboard.writeText(texto).then(function(){
            mostrarToast('Informa\u00e7\u00f5es copiadas com sucesso!');
        }).catch(function(){
            mostrarToast('N\u00e3o foi poss\u00edvel copiar!', true);
        });
    }

    /* ── WHATSAPP ──────────────────────────────── */
    function shareOnWhatsApp() {
        var text = "\ud83c\udf89 Revendedor Criado! \ud83c\udf89\n"
            + "\ud83d\udd0e Usuario: <?php echo $show_modal ? addslashes($modal_usuario) : ''; ?>\n"
            + "\ud83d\udd12 Senha: <?php echo $show_modal ? addslashes($modal_senha) : ''; ?>\n"
            + "\ud83c\udfaf Validade: <?php echo $show_modal ? addslashes($modal_validade) : ''; ?>\n"
            + "\ud83d\udcc5 Vencimento: <?php echo $show_modal && !empty($modal_data_validade) && $modal_validade != 'Nunca' ? date('d/m/Y', strtotime($modal_data_validade)) : 'Nunca'; ?>\n"
            + "\ud83d\udd1f Limite: <?php echo $show_modal ? $modal_limite : ''; ?>\n"
            + "\ud83d\udcb0 Valor do Revendedor: R$ <?php echo number_format(floatval($modal_valor_revenda), 2, ',', '.'); ?>\n"
            + "\ud83d\udd17 Link: https://<?php echo $_SERVER['HTTP_HOST']; ?>/";
        window.open("https://api.whatsapp.com/send?text=" + encodeURIComponent(text));
    }

    /* ── TOAST ──────────────────────────────────── */
    function mostrarToast(msg, erro) {
        var t = document.createElement('div');
        t.className = 'toast-notification';
        if (erro) t.style.background = 'linear-gradient(135deg,#dc2626,#b91c1c)';
        t.innerHTML = '<i class="bx ' + (erro ? 'bx-error-circle' : 'bx-check-circle') + '" style="font-size:20px;"></i> ' + msg;
        document.body.appendChild(t);
        setTimeout(function(){ t.remove(); }, 3000);
    }

    /* ── FECHAR AO CLICAR FORA ──────────────────── */
    document.addEventListener('click', function(e) {
        if (!e.target.classList.contains('modal-overlay')) return;
        if (e.target.id === 'modalErro') { fecharModalErro(); return; }
        e.target.classList.remove('show');
    });

    /* ── ESC ──────────────────────────────────────── */
    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Escape') return;
        if (document.getElementById('modalErro').classList.contains('show')) {
            fecharModalErro(); return;
        }
        document.querySelectorAll('.modal-overlay.show').forEach(function(m){ m.classList.remove('show'); });
    });
</script>
    </div><!-- .page-body -->
</div><!-- .main-wrap -->
</body>
</html>
