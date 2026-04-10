<?php
error_reporting(0);
session_start();
date_default_timezone_set('America/Sao_Paulo');

// â”€â”€ Verificar login â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if (!isset($_SESSION['login']) and !isset($_SESSION['senha'])) {
    session_destroy(); unset($_SESSION['login']); unset($_SESSION['senha']); header('location:index.php'); exit;
}

include 'header2.php';
include('conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) { die("Connection failed: " . mysqli_connect_error()); }

function anti_sql($input) {
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function($match) { return ''; }, $input);
    return addslashes(strip_tags(trim($seg)));
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!empty($id)) {
    $sql    = "SELECT * FROM accounts WHERE id = '$id'";
    $result = mysqli_query($conn, $sql);
    if ($result && mysqli_num_rows($result) > 0) {
        $row          = mysqli_fetch_assoc($result);
        $login        = $row['login'];
        $senha        = $row['senha'];
        $byid         = $row['byid'];
        $whatsapp     = $row['whatsapp']     ?? '';
        $valorrevenda = $row['valorrevenda'] ?? '0';
    } else {
        echo "<script>alert('Revendedor nÃ£o encontrado!');window.location.href='listarrevendedores.php';</script>"; exit();
    }
} else {
    echo "<script>alert('ID invÃ¡lido!');window.location.href='listarrevendedores.php';</script>"; exit();
}

if ($byid != $_SESSION['iduser']) {
    echo "<script>alert('VocÃª nÃ£o tem permissÃ£o para editar este Revendedor!');window.location.href='../home.php';</script>"; exit();
}

// â”€â”€ Dados do revendedor (atribuidos) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$sql    = "SELECT * FROM atribuidos WHERE userid = '$id'";
$result = mysqli_query($conn, $sql);
if ($result && mysqli_num_rows($result) > 0) {
    $row         = mysqli_fetch_assoc($result);
    $limite      = $row['limite'];
    $validade    = $row['expira'];
    $valor_revenda = isset($row['valor']) ? floatval($row['valor']) : 0;  // <-- VALOR DE RENOVAÃ‡ÃƒO
    $id_plano_atual = $row['id_plano'] ?? 0;
    $_SESSION['idrevenda'] = $id;
} else {
    echo "<script>alert('Dados do revendedor nÃ£o encontrados!');window.location.href='listarrevendedores.php';</script>"; exit();
}

$validade_date = date('Y-m-d', strtotime($validade));
$data_atual    = date('Y-m-d');
$diferenca     = strtotime($validade_date) - strtotime($data_atual);
$dias          = floor($diferenca / (60 * 60 * 24));
if ($dias < 0) $dias = 0;

// Buscar limites usados
$r = $conn->prepare("SELECT sum(limite) AS l FROM ssh_accounts where byid='" . $_SESSION['iduser'] . "'");
$r->execute(); $r->bind_result($limiteatual); $r->fetch(); $r->close();

$r = $conn->prepare("SELECT sum(limite) AS l FROM atribuidos where byid='" . $_SESSION['iduser'] . "'");
$r->execute(); $r->bind_result($limiteusado); $r->fetch(); $r->close();

$r = $conn->prepare("SELECT sum(limite) AS l FROM atribuidos where byid='" . $_SESSION['idrevenda'] . "'");
$r->execute(); $r->bind_result($limiterevenda); $r->fetch(); $r->close();

$r = $conn->prepare("SELECT sum(limite) AS l FROM ssh_accounts where byid='" . $_SESSION['idrevenda'] . "'");
$r->execute(); $r->bind_result($usadousuarios); $r->fetch(); $r->close();

$soma     = $usadousuarios + $limiterevenda;
$restante = $_SESSION['limite'] - $limiteusado - $limiteatual;
$_SESSION['restante'] = $restante;

// Dados do revendedor logado
$sql5         = $conn->query("SELECT * FROM atribuidos WHERE userid = '$_SESSION[iduser]'");
$row5         = $sql5->fetch_assoc();
$validade_rev = $row5['expira'];
$tipo_pai     = $row5['tipo'];
$_SESSION['limite']      = $row5['limite'];
$_SESSION['tipodeconta'] = $row5['tipo'];

if ($tipo_pai == 'Credito') { 
    $tipo_txt = 'Restam ' . $limite . ' CrÃ©ditos desse Revendedor'; 
    $modo = 'CrÃ©ditos'; 
    $minimo = 1; 
} else { 
    $tipo_txt = 'Este revendedor usou ' . $soma . ' logins de ' . $limite; 
    $modo = 'Limite'; 
    $minimo = $soma; 
    $_SESSION['soma'] = $soma; 
}

$hoje = date('Y-m-d H:i:s');
if ($_SESSION['tipodeconta'] != 'Credito' && $validade_rev < $hoje) {
    echo "<script>alert('Sua conta estÃ¡ vencida!');window.location.href='../home.php';</script>"; exit();
}

// VerificaÃ§Ã£o de token
if (!file_exists('../admin/suspenderrev.php')) { 
    exit("<script>alert('Token Invalido!');</script>"); 
} else { 
    include_once '../admin/suspenderrev.php'; 
}

if (!isset($_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe']) || !isset($_SESSION['token']) || $_SESSION['tokenatual'] != $_SESSION['token'] || isset($_SESSION['token_invalido_']) && $_SESSION['token_invalido_'] === true) {
    if (function_exists('security')) { security(); }
    else { echo "<script>alert('Token InvÃ¡lido!');location.href='../index.php';</script>"; $_SESSION['token_invalido_'] = true; exit; }
}

// â”€â”€ PROCESSAR EDIÃ‡ÃƒO â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$error_message      = '';
$show_error_modal   = false;
$show_success_modal = false;
$dados_salvos       = [];

if (isset($_POST['editarrev'])) {
    $usuarioedit      = anti_sql($_POST['usuarioedit']);
    $senhaedit        = anti_sql($_POST['senhaedit']);
    $limiteedit       = intval($_POST['limiteedit']);
    $validadeedit     = intval($_POST['validadeedit'] ?? 0);
    $whatsappedit     = anti_sql($_POST['whatsapp']      ?? '');
    $valorrevvendaedit = anti_sql($_POST['valor_revenda'] ?? '0');
    
    // ValidaÃ§Ãµes
    if (strlen($usuarioedit) < 5)       { $error_message = 'UsuÃ¡rio deve ter no mÃ­nimo 5 caracteres!';   $show_error_modal = true; }
    elseif (strlen($usuarioedit) > 10)  { $error_message = 'UsuÃ¡rio deve ter no mÃ¡ximo 10 caracteres!';  $show_error_modal = true; }
    elseif (strlen($senhaedit) < 5)     { $error_message = 'Senha deve ter no mÃ­nimo 5 caracteres!';     $show_error_modal = true; }
    elseif (strlen($senhaedit) > 10)    { $error_message = 'Senha deve ter no mÃ¡ximo 10 caracteres!';    $show_error_modal = true; }
    elseif (preg_match('/[^a-z0-9]/i', $usuarioedit)) { $error_message = 'UsuÃ¡rio nÃ£o pode conter caracteres especiais!'; $show_error_modal = true; }
    elseif (preg_match('/[^a-z0-9]/i', $senhaedit))   { $error_message = 'Senha nÃ£o pode conter caracteres especiais!';   $show_error_modal = true; }
    elseif ($limiteedit < $minimo)      { $error_message = 'Limite mÃ­nimo Ã© ' . $minimo . '!'; $show_error_modal = true; }
    elseif ($_SESSION['tipodeconta'] != 'Credito' && $validadeedit > 365) { $error_message = 'MÃ¡ximo permitido Ã© 365 dias!'; $show_error_modal = true; }

    if (!$show_error_modal && $validadeedit < 1 && $_SESSION['tipodeconta'] != 'Credito') $validadeedit = 1;

    // Verificar se usuÃ¡rio jÃ¡ existe
    if (!$show_error_modal) {
        $chk = mysqli_query($conn, "SELECT * FROM accounts WHERE login = '$usuarioedit' AND id != '$id'");
        if ($chk && mysqli_num_rows($chk) > 0) { $error_message = 'UsuÃ¡rio jÃ¡ existe!'; $show_error_modal = true; }
    }

    if (!$show_error_modal) {
        // Buscar dados atuais antes da ediÃ§Ã£o (para histÃ³rico)
        $sql_atual = "SELECT limite, valor, id_plano FROM atribuidos WHERE userid = '$id'";
        $result_atual = mysqli_query($conn, $sql_atual);
        $dados_atual = mysqli_fetch_assoc($result_atual);
        $limite_anterior = $dados_atual['limite'] ?? 0;
        $valor_anterior = $dados_atual['valor'] ?? 0;
        $plano_anterior_id = $dados_atual['id_plano'] ?? 0;
        
        // Atualizar tabela accounts
        $sql_update_account = "UPDATE accounts SET login = '$usuarioedit', senha = '$senhaedit', whatsapp = '$whatsappedit', valorrevenda = '$valorrevvendaedit' WHERE id = '$id'";
        mysqli_query($conn, $sql_update_account);
        
        // Atualizar tabela atribuidos
        if ($_SESSION['tipodeconta'] != 'Credito') {
            $nova_data = date('Y-m-d H:i:s', strtotime("+" . $validadeedit . " days", strtotime($validade)));
            $upd = "UPDATE atribuidos SET limite = '$limiteedit', expira = '$nova_data', valor = '$valorrevvendaedit' WHERE userid = '$id'";
        } else {
            $upd = "UPDATE atribuidos SET limite = '$limiteedit', valor = '$valorrevvendaedit' WHERE userid = '$id'";
        }
        
        if (mysqli_query($conn, $upd)) {
            // Registrar histÃ³rico de alteraÃ§Ã£o
            $sql_historico = "INSERT INTO historico_planos_revenda (revenda_id, plano_anterior_id, plano_novo_id, limite_anterior, limite_novo, valor_anterior, valor_novo, data_alteracao, tipo_alteracao) 
                              VALUES ('$id', '$plano_anterior_id', NULL, '$limite_anterior', '$limiteedit', '$valor_anterior', '$valorrevvendaedit', NOW(), 'edicao')";
            mysqli_query($conn, $sql_historico);
            
            // Registrar log
            $datahoje = date('d-m-Y H:i:s');
            mysqli_query($conn, "INSERT INTO logs (revenda, validade, texto, userid) VALUES ('$_SESSION[login]', '$datahoje', 'Editou o revendedor $usuarioedit', '$_SESSION[iduser]')");
            
            $show_success_modal = true;
            $dados_salvos = [
                'login'      => $usuarioedit,
                'senha'      => $senhaedit,
                'limite'     => $limiteedit,
                'tipo'       => $_SESSION['tipodeconta'],
                'validade'   => ($_SESSION['tipodeconta'] != 'Credito')
                                ? date('d/m/Y H:i:s', strtotime($nova_data))
                                : 'Conta CrÃ©dito',
                'whatsapp'   => $whatsappedit,
                'valor'      => $valorrevvendaedit,
                'valor_renovacao' => $valorrevvendaedit
            ];
            $login = $usuarioedit; 
            $senha = $senhaedit; 
            $limite = $limiteedit;
            $whatsapp = $whatsappedit; 
            $valor_revenda = $valorrevvendaedit;
        } else {
            $error_message = 'Erro ao atualizar revendedor: ' . mysqli_error($conn);
            $show_error_modal = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Revendedor</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <style>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Revendedor</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <style>
        :root {
            --primary: #4158D0; --secondary: #C850C0; --tertiary: #FFCC70;
            --success: #10b981; --danger: #dc2626; --warning: #f59e0b;
            --info: #3b82f6; --dark: #2c3e50;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Rubik', sans-serif; min-height: 100vh; background: linear-gradient(135deg, #0f172a, #1e1b4b); }
        .app-content { margin-left: 240px !important; padding: 0 !important; }
        .content-wrapper { max-width: 780px; margin: 0 auto 0 5px !important; padding: 0px !important; }
        .content-body, .row, .match-height, [class*="col-"] { margin: 0 !important; padding: 0 !important; }
        .content-header { display: none !important; height: 0 !important; margin: 0 !important; padding: 0 !important; }

        .info-badge { display: inline-flex !important; align-items: center !important; gap: 8px !important; background: white !important; color: var(--dark) !important; padding: 8px 16px !important; border-radius: 30px !important; font-size: 13px !important; margin-top: 5px !important; margin-bottom: 15px !important; border-left: 4px solid var(--primary) !important; box-shadow: 0 3px 10px rgba(0,0,0,0.1) !important; }
        .info-badge i { font-size: 22px; color: var(--primary); }

        .status-info { background: linear-gradient(135deg, #1e293b, #0f172a); border-radius: 14px; padding: 12px 18px; margin-bottom: 15px; border: 1px solid rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; color: white; }
        .status-item { display: flex !important; align-items: center !important; gap: 6px !important; }
        .status-item i { font-size: 20px !important; color: var(--tertiary) !important; }
        .status-item span { font-size: 12px !important; font-weight: 500 !important; }

        .modern-card { background: linear-gradient(135deg, #1e293b, #0f172a) !important; border-radius: 20px !important; border: 1px solid rgba(255,255,255,0.08) !important; overflow: hidden !important; position: relative !important; box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important; width: 100% !important; animation: fadeIn 0.5s ease !important; margin-bottom: 8px !important; max-width: 100% !important; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .card-bg-shapes { position: absolute; inset: 0; pointer-events: none; z-index: 0; overflow: hidden; }
        .modern-card .card-header { padding: 16px 20px 12px !important; border-bottom: 1px solid rgba(255,255,255,0.07) !important; display: flex !important; align-items: center !important; gap: 10px !important; position: relative; z-index: 1; }
        .header-icon { width: 36px; height: 36px; border-radius: 10px; background: linear-gradient(135deg, #C850C0, #4158D0); display: flex; align-items: center; justify-content: center; font-size: 18px; color: white; flex-shrink: 0; }
        .header-title { font-size: 14px; font-weight: 700; color: white; }
        .header-subtitle { font-size: 10px; color: rgba(255,255,255,0.35); }
        .limite-badge { margin-left: auto; display: inline-flex; align-items: center; gap: 5px; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; padding: 4px 8px; font-size: 10px; font-weight: 600; color: rgba(255,255,255,0.5); }
        .modern-card .card-body { padding: 18px 20px !important; position: relative; z-index: 1; }

        .btn-back { background: linear-gradient(135deg, #f59e0b, #f97316) !important; color: white !important; text-decoration: none !important; padding: 6px 14px !important; border-radius: 30px !important; font-weight: 600 !important; font-size: 12px !important; display: flex !important; align-items: center !important; gap: 6px !important; transition: all 0.3s !important; margin-left: 10px !important; box-shadow: 0 4px 8px rgba(245,158,11,0.3) !important; }
        .btn-back:hover { transform: translateY(-2px) !important; box-shadow: 0 6px 15px rgba(245,158,11,0.5) !important; }

        .btn-action { padding: 8px 16px !important; border: none !important; border-radius: 8px !important; font-weight: 700 !important; font-size: 12px !important; cursor: pointer !important; transition: all 0.2s !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; gap: 6px !important; font-family: inherit !important; box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important; text-decoration: none !important; }
        .btn-action-success { background: linear-gradient(135deg, #10b981, #059669) !important; color: white !important; }
        .btn-action-success:hover { transform: translateY(-2px) !important; box-shadow: 0 6px 15px rgba(16,185,129,0.5) !important; }
        .btn-action-danger { background: linear-gradient(135deg, #dc2626, #b91c1c) !important; color: white !important; }
        .btn-action-danger:hover { transform: translateY(-2px) !important; box-shadow: 0 6px 15px rgba(220,38,38,0.5) !important; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .form-field { display: flex; flex-direction: column; gap: 4px; }
        .form-field.full-width { grid-column: 1 / -1; }
        .form-field label { font-size: 9px; font-weight: 700; color: rgba(255,255,255,0.4); text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 4px; }
        .form-field label i { font-size: 12px; }
        .form-control { width: 100%; padding: 8px 12px; background: rgba(255,255,255,0.06); border: 1.5px solid rgba(255,255,255,0.08); border-radius: 9px; color: white; font-size: 12px; font-family: inherit; outline: none; transition: all 0.25s; }
        .form-control:focus { border-color: rgba(65,88,208,0.6); background: rgba(255,255,255,0.09); }
        .form-control::placeholder { color: rgba(255,255,255,0.2); }

        .dias-select { display: grid; grid-template-columns: repeat(5, 1fr); gap: 6px; margin-top: 4px; }
        .dia-option { background: rgba(255,255,255,0.06); border: 1.5px solid rgba(255,255,255,0.08); border-radius: 8px; padding: 8px 4px; text-align: center; cursor: pointer; transition: all 0.3s; font-size: 12px; font-weight: 600; color: rgba(255,255,255,0.7); }
        .dia-option:hover { background: rgba(255,255,255,0.1); border-color: rgba(65,88,208,0.6); }
        .dia-option.active { background: linear-gradient(135deg, #4158D0, #C850C0); color: white; border-color: transparent; }

        .action-buttons { display: flex; justify-content: flex-end; gap: 8px; margin-top: 18px; flex-wrap: wrap; }

        .icon-user { color: #818cf8; } .icon-lock { color: #e879f9; } .icon-group { color: #34d399; } .icon-calendar { color: #fbbf24; } .icon-money { color: #10b981; }

        /* MODAIS */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); display: none; align-items: center; justify-content: center; z-index: 9999; backdrop-filter: blur(8px); }
        .modal-overlay.show { display: flex; }
        .modal-container { animation: modalFadeIn 0.4s cubic-bezier(0.34,1.2,0.64,1); max-width: 500px; width: 90%; }
        @keyframes modalFadeIn { from { opacity: 0; transform: scale(0.9) translateY(-30px); } to { opacity: 1; transform: scale(1) translateY(0); } }
        .modal-content-custom { background: linear-gradient(135deg, #1e293b, #0f172a); border-radius: 24px; overflow: hidden; border: 1px solid rgba(255,255,255,0.15); box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); }
        .modal-header-custom { color: white; padding: 20px 24px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .modal-header-custom.success { background: linear-gradient(135deg, #10b981, #059669); }
        .modal-header-custom.error   { background: linear-gradient(135deg, #dc2626, #b91c1c); }
        .modal-header-custom h5 { margin: 0; display: flex; align-items: center; gap: 10px; font-size: 18px; font-weight: 600; }
        .modal-close { background: none; border: none; color: white; font-size: 24px; cursor: pointer; opacity: 0.8; transition: opacity 0.2s; }
        .modal-close:hover { opacity: 1; }
        .modal-body-custom { padding: 24px; color: white; }
        .modal-footer-custom { border-top: 1px solid rgba(255,255,255,0.1); padding: 16px 24px; display: flex; justify-content: center; gap: 12px; flex-wrap: wrap; }
        .modal-success-icon { text-align: center; margin-bottom: 20px; }
        .modal-success-icon i { font-size: 70px; }
        .modal-info-card { background: rgba(255,255,255,0.05); border-radius: 16px; padding: 16px; margin-bottom: 16px; border: 1px solid rgba(255,255,255,0.08); }
        .modal-info-row { display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .modal-info-row:last-child { border-bottom: none; }
        .modal-info-label { font-size: 12px; font-weight: 600; color: rgba(255,255,255,0.6); display: flex; align-items: center; gap: 8px; }
        .modal-info-label i { font-size: 18px; }
        .modal-info-value { font-size: 13px; font-weight: 700; color: white; }
        .modal-info-value.credential { background: rgba(0,0,0,0.3); padding: 4px 10px; border-radius: 8px; font-family: monospace; letter-spacing: 0.5px; }
        .modal-info-value.highlight-green { color: #10b981; }
        .modal-divider { border: none; border-top: 1px solid rgba(255,255,255,0.1); margin: 16px 0; }
        .modal-success-title { text-align: center; color: #10b981; font-weight: 700; font-size: 14px; margin-top: 12px; }
        .btn-modal { padding: 9px 20px; border: none; border-radius: 10px; font-weight: 700; font-size: 13px; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px; font-family: inherit; box-shadow: 0 3px 8px rgba(0,0,0,0.2); color: white; }
        .btn-modal-copy { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .btn-modal-copy:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(59,130,246,0.5); }
        .btn-modal-ok { background: linear-gradient(135deg, #10b981, #059669); }
        .btn-modal-ok:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(16,185,129,0.5); }
        .toast-notification { position: fixed; bottom: 24px; right: 24px; background: linear-gradient(135deg, #10b981, #059669); color: white; padding: 12px 20px; border-radius: 12px; display: flex; align-items: center; gap: 10px; z-index: 10000; animation: slideIn 0.3s ease; box-shadow: 0 4px 20px rgba(0,0,0,0.4); font-weight: 600; font-size: 13px; }
        @keyframes slideIn { from { transform: translateX(110%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        @media (max-width: 768px) {
            .app-content { margin-left: 0 !important; }
            .content-wrapper { margin: 0 auto !important; padding: 5px !important; }
            .form-grid { grid-template-columns: 1fr; }
            .modern-card .card-header { flex-direction: row !important; flex-wrap: wrap !important; }
            .limite-badge { margin-left: 0 !important; order: 3 !important; width: 100% !important; justify-content: center !important; margin-top: 5px !important; }
            .action-buttons { flex-direction: column !important; }
            .btn-action { width: 100%; }
            .dias-select { grid-template-columns: repeat(3, 1fr); }
            .modal-container { width: 95%; }
            .modal-info-row { flex-direction: column; align-items: flex-start; gap: 6px; }
            .modal-footer-custom { flex-direction: column; }
            .btn-modal { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>
    <div class="app-content content">
    <div class="content-overlay"></div>
    <div class="content-wrapper">

        <div class="info-badge"><i class='bx bx-store-alt'></i><span>Editar Revendedor</span></div>
        <div class="status-info">
            <div class="status-item"><i class='bx bx-info-circle'></i><span>Revendedor: <?php echo htmlspecialchars($login); ?></span></div>
            <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
            <div class="status-item"><i class='bx bx-time'></i><span>Validade: <?php echo date('d/m/Y', strtotime($validade_rev)); ?></span></div>
            <div class="status-item"><i class='bx bx-bar-chart-alt'></i><span>Restante: <?php echo $restante; ?></span></div>
            <?php endif; ?>
        </div>

        <div class="modern-card">
            <div class="card-bg-shapes">
                <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                    <circle cx="95%" cy="6%" r="60" fill="rgba(200,80,192,0.07)"/>
                    <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                    <polygon points="85%,30% 98%,52% 72%,52%" fill="rgba(255,204,112,0.05)"/>
                    <polygon points="3%,8% 10%,22% -4%,22%" fill="rgba(16,185,129,0.06)"/>
                    <rect x="78%" y="55%" width="38" height="38" rx="8" fill="rgba(59,130,246,0.05)" transform="rotate(25,97,74)"/>
                </svg>
            </div>
            <div class="card-header">
                <div class="header-icon"><i class='bx bx-store-alt'></i></div>
                <div>
                    <div class="header-title">Editar Revendedor</div>
                    <div class="header-subtitle">Modifique as informaÃ§Ãµes do revendedor</div>
                </div>
                <div class="limite-badge">
                    <i class='bx bx-bar-chart-alt' style="color:#10b981;"></i>
                    <?php echo $tipo_txt; ?>
                </div>
            </div>
            <div class="card-body">
                <form action="editarrev.php?id=<?php echo $id; ?>" method="POST">
                    <div class="form-grid">
                        <div class="form-field">
                            <label><i class='bx bx-user icon-user'></i> Login (5 a 10 caracteres)</label>
                            <input type="text" class="form-control" name="usuarioedit" placeholder="Login" value="<?php echo htmlspecialchars($login); ?>" minlength="5" maxlength="10" required>
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-lock-alt icon-lock'></i> Senha (5 a 10 caracteres)</label>
                            <input type="text" class="form-control" name="senhaedit" placeholder="Senha" value="<?php echo htmlspecialchars($senha); ?>" minlength="5" maxlength="10" required>
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-layer icon-group'></i> <?php echo $modo; ?> (MÃ­n. <?php echo $minimo; ?>)</label>
                            <input type="number" class="form-control" min="<?php echo $minimo; ?>" name="limiteedit" value="<?php echo $limite; ?>" required>
                        </div>
                        
                        <!-- CAMPO DE VALOR DE RENOVAÃ‡ÃƒO - NOVO -->
                        <div class="form-field">
                            <label><i class='bx bx-dollar icon-money'></i> Valor de RenovaÃ§Ã£o (R$)</label>
                            <input type="number" class="form-control" step="0.01" min="0" name="valor_revenda" value="<?php echo number_format($valor_revenda, 2, '.', ''); ?>" placeholder="0,00">
                            <small style="color:rgba(255,255,255,0.3);margin-top:3px;display:block;font-size:9px;">
                                <i class='bx bx-info-circle'></i> Valor que serÃ¡ cobrado na renovaÃ§Ã£o (0 = desativado)
                            </small>
                        </div>
                        
                        <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
                        <div class="form-field full-width">
                            <label><i class='bx bx-calendar icon-calendar'></i> Dias de Validade (mÃ¡ximo 365 dias)</label>
                            <input type="number" class="form-control" name="validadeedit" id="validadeedit" value="<?php echo $dias; ?>" min="1" max="365" required>
                        </div>
                        <?php endif; ?>

                        <div class="form-field full-width">
                            <label><i class='bx bxl-whatsapp' style="color:#25D366;"></i> WhatsApp do Revendedor</label>
                            <input type="text" class="form-control" name="whatsapp" placeholder="5511999999999" value="<?php echo htmlspecialchars($whatsapp); ?>">
                            <small style="color:rgba(255,255,255,0.3);margin-top:3px;display:block;font-size:9px;"><i class='bx bx-info-circle' style="color:#a78bfa;"></i> NÃºmero igual ao WhatsApp</small>
                        </div>
                    </div><!-- end form-grid -->

                    <div class="action-buttons">
                        <a href="listarrevendedores.php" class="btn-action btn-action-danger"><i class='bx bx-x'></i> Cancelar</a>
                        <button type="submit" class="btn-action btn-action-success" name="editarrev"><i class='bx bx-check'></i> Salvar AlteraÃ§Ãµes</button>
                    </div>
                </form>
            </div>
        </div>

    </div>
    </div>

    <!-- MODAL SUCESSO EDIÃ‡ÃƒO -->
    <div id="modalSucessoEdicao" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom success">
                    <h5><i class='bx bx-check-circle'></i> Revendedor Editado com Sucesso!</h5>
                    <button type="button" class="modal-close" onclick="fecharSucesso()"><i class='bx bx-x'></i></button>
                </div>
                <div class="modal-body-custom">
                    <div class="modal-success-icon">
                        <i class='bx bx-check-circle' style="color:#10b981; filter: drop-shadow(0 0 15px rgba(16,185,129,0.5));"></i>
                    </div>
                    <div class="modal-info-card">
                        <div class="modal-info-row">
                            <div class="modal-info-label"><i class='bx bx-user' style="color:#818cf8;"></i> Login</div>
                            <div class="modal-info-value credential"><?php echo htmlspecialchars($dados_salvos['login'] ?? $login); ?></div>
                        </div>
                        <div class="modal-info-row">
                            <div class="modal-info-label"><i class='bx bx-lock-alt' style="color:#e879f9;"></i> Senha</div>
                            <div class="modal-info-value credential"><?php echo htmlspecialchars($dados_salvos['senha'] ?? $senha); ?></div>
                        </div>
                        <div class="modal-info-row">
                            <div class="modal-info-label"><i class='bx bx-layer' style="color:#34d399;"></i> Limite</div>
                            <div class="modal-info-value"><?php echo htmlspecialchars($dados_salvos['limite'] ?? $limite); ?> conexÃµes</div>
                        </div>
                        <div class="modal-info-row">
                            <div class="modal-info-label"><i class='bx bx-dollar' style="color:#10b981;"></i> Valor de RenovaÃ§Ã£o</div>
                            <div class="modal-info-value highlight-green">R$ <?php echo number_format(floatval($dados_salvos['valor_renovacao'] ?? $valor_revenda), 2, ',', '.'); ?></div>
                        </div>
                        <div class="modal-info-row">
                            <div class="modal-info-label"><i class='bx bx-category' style="color:#818cf8;"></i> Tipo</div>
                            <div class="modal-info-value"><?php echo htmlspecialchars($dados_salvos['tipo'] ?? $_SESSION['tipodeconta'] ?? ''); ?></div>
                        </div>
                        <?php if (!empty($dados_salvos['validade'])): ?>
                        <div class="modal-info-row">
                            <div class="modal-info-label"><i class='bx bx-calendar-check' style="color:#10b981;"></i> Validade</div>
                            <div class="modal-info-value highlight-green"><?php echo htmlspecialchars($dados_salvos['validade']); ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($dados_salvos['whatsapp'])): ?>
                        <div class="modal-info-row">
                            <div class="modal-info-label"><i class='bx bxl-whatsapp' style="color:#25D366;"></i> WhatsApp</div>
                            <div class="modal-info-value"><?php echo htmlspecialchars($dados_salvos['whatsapp']); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <hr class="modal-divider">
                    <p class="modal-success-title">âœ¨ Revendedor editado com sucesso! âœ¨</p>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-modal btn-modal-copy" onclick="copiarEdicao()">
                        <i class='bx bx-copy'></i> Copiar InformaÃ§Ãµes
                    </button>
                    <button type="button" class="btn-modal btn-modal-ok" onclick="fecharSucesso()">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL ERRO VALIDAÃ‡ÃƒO -->
    <div id="modalErroValidacao" class="modal-overlay <?php echo $show_error_modal ? 'show' : ''; ?>">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom error">
                    <h5><i class='bx bx-error-circle'></i> Erro!</h5>
                    <button type="button" class="modal-close" onclick="fecharModal('modalErroValidacao')"><i class='bx bx-x'></i></button>
                </div>
                <div class="modal-body-custom">
                    <div style="text-align:center; margin-bottom: 20px;">
                        <i class='bx bx-error-circle' style="font-size:70px; color:#dc2626; filter: drop-shadow(0 0 10px rgba(220,38,38,0.5));"></i>
                    </div>
                    <h3 style="color:white; margin-bottom:10px; text-align:center;">Ops! Algo deu errado</h3>
                    <p style="color:rgba(255,255,255,0.8); text-align:center;"><?php echo htmlspecialchars($error_message); ?></p>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-modal" style="background:linear-gradient(135deg,#dc2626,#b91c1c);" onclick="fecharModal('modalErroValidacao')">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../app-assets/sweetalert.min.js"></script>
    <script>
        function fecharModal(id)  { document.getElementById(id).classList.remove('show'); }
        function fecharSucesso()  { fecharModal('modalSucessoEdicao'); window.location.href = 'listarrevendedores.php'; }

        // Fechar ao clicar fora
        document.addEventListener('click', function(e) {
            if (!e.target.classList.contains('modal-overlay')) return;
            const id = e.target.id;
            if (id === 'modalSucessoEdicao') fecharSucesso();
            else fecharModal(id);
        });

        document.addEventListener('keydown', function(e) {
            if (e.key !== 'Escape') return;
            if (document.getElementById('modalSucessoEdicao').classList.contains('show')) fecharSucesso();
            fecharModal('modalErroValidacao');
        });

        function copiarEdicao() {
            const login    = <?php echo json_encode($dados_salvos['login']    ?? $login); ?>;
            const senha    = <?php echo json_encode($dados_salvos['senha']    ?? $senha); ?>;
            const limite   = <?php echo json_encode(($dados_salvos['limite']  ?? $limite) . ' conexÃµes'); ?>;
            const valor    = <?php echo json_encode('R$ ' . number_format(floatval($dados_salvos['valor_renovacao'] ?? $valor_revenda), 2, ',', '.')); ?>;
            const tipo     = <?php echo json_encode($dados_salvos['tipo']     ?? ($_SESSION['tipodeconta'] ?? '')); ?>;
            const validade  = <?php echo json_encode($dados_salvos['validade']  ?? 'â€”'); ?>;
            const whatsapp  = <?php echo json_encode($dados_salvos['whatsapp']  ?? ''); ?>;
            
            let txt = `âœ… REVENDEDOR EDITADO!\nâ”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\nðŸ‘¤ Login: ${login}\nðŸ”‘ Senha: ${senha}\nðŸ”— Limite: ${limite}\nðŸ’° Valor RenovaÃ§Ã£o: ${valor}\nðŸ“‹ Tipo: ${tipo}\nðŸ“… Validade: ${validade}`;
            if (whatsapp) txt += `\nðŸ“± WhatsApp: ${whatsapp}`;
            txt += `\nðŸ“† Data: ${new Date().toLocaleString('pt-BR')}`;
            navigator.clipboard.writeText(txt).then(() => {
                const t = document.createElement('div');
                t.className = 'toast-notification';
                t.innerHTML = '<i class="bx bx-check-circle" style="font-size:20px"></i> InformaÃ§Ãµes copiadas!';
                document.body.appendChild(t);
                setTimeout(() => t.remove(), 3000);
            });
        }

        <?php if ($show_success_modal): ?>
        document.addEventListener('DOMContentLoaded', () => { document.getElementById('modalSucessoEdicao').classList.add('show'); });
        <?php endif; ?>
    </script>
    <script src="../app-assets/js/scripts/forms/number-input.js"></script>
</body>
</html>

h2_tema ?? ['classe'=>'theme-dark'])); ?>">
    <div class="app-content content">
    <div class="content-overlay"></div>
    <div class="content-wrapper">

        <div class="info-badge"><i class='bx bx-store-alt'></i><span>Editar Revendedor</span></div>
        <div class="status-info">
            <div class="status-item"><i class='bx bx-info-circle'></i><span>Revendedor: <?php echo htmlspecialchars($login); ?></span></div>
            <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
            <div class="status-item"><i class='bx bx-time'></i><span>Validade: <?php echo date('d/m/Y', strtotime($validade_rev)); ?></span></div>
            <div class="status-item"><i class='bx bx-bar-chart-alt'></i><span>Restante: <?php echo $restante; ?></span></div>
            <?php endif; ?>
        </div>

        <div class="modern-card">
            <div class="card-bg-shapes">
                <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                    <circle cx="95%" cy="6%" r="60" fill="rgba(200,80,192,0.07)"/>
                    <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                    <polygon points="85%,30% 98%,52% 72%,52%" fill="rgba(255,204,112,0.05)"/>
                    <polygon points="3%,8% 10%,22% -4%,22%" fill="rgba(16,185,129,0.06)"/>
                    <rect x="78%" y="55%" width="38" height="38" rx="8" fill="rgba(59,130,246,0.05)" transform="rotate(25,97,74)"/>
                </svg>
            </div>
            <div class="card-header">
                <div class="header-icon"><i class='bx bx-store-alt'></i></div>
                <div>
                    <div class="header-title">Editar Revendedor</div>
                    <div class="header-subtitle">Modifique as informaÃ§Ãµes do revendedor</div>
                </div>
                <div class="limite-badge">
                    <i class='bx bx-bar-chart-alt' style="color:#10b981;"></i>
                    <?php echo $tipo_txt; ?>
                </div>
            </div>
            <div class="card-body">
                <form action="editarrev.php?id=<?php echo $id; ?>" method="POST">
                    <div class="form-grid">
                        <div class="form-field">
                            <label><i class='bx bx-user icon-user'></i> Login (5 a 10 caracteres)</label>
                            <input type="text" class="form-control" name="usuarioedit" placeholder="Login" value="<?php echo htmlspecialchars($login); ?>" minlength="5" maxlength="10" required>
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-lock-alt icon-lock'></i> Senha (5 a 10 caracteres)</label>
                            <input type="text" class="form-control" name="senhaedit" placeholder="Senha" value="<?php echo htmlspecialchars($senha); ?>" minlength="5" maxlength="10" required>
                        </div>
                        <div class="form-field">
                            <label><i class='bx bx-layer icon-group'></i> <?php echo $modo; ?> (MÃ­n. <?php echo $minimo; ?>)</label>
                            <input type="number" class="form-control" min="<?php echo $minimo; ?>" name="limiteedit" value="<?php echo $limite; ?>" required>
                        </div>
                        
                        <!-- CAMPO DE VALOR DE RENOVAÃ‡ÃƒO - NOVO -->
                        <div class="form-field">
                            <label><i class='bx bx-dollar icon-money'></i> Valor de RenovaÃ§Ã£o (R$)</label>
                            <input type="number" class="form-control" step="0.01" min="0" name="valor_revenda" value="<?php echo number_format($valor_revenda, 2, '.', ''); ?>" placeholder="0,00">
                            <small style="color:rgba(255,255,255,0.3);margin-top:3px;display:block;font-size:9px;">
                                <i class='bx bx-info-circle'></i> Valor que serÃ¡ cobrado na renovaÃ§Ã£o (0 = desativado)
                            </small>
                        </div>
                        
                        <?php if ($_SESSION['tipodeconta'] != 'Credito'): ?>
                        <div class="form-field full-width">
                            <label><i class='bx bx-calendar icon-calendar'></i> Dias de Validade (mÃ¡ximo 365 dias)</label>
                            <input type="number" class="form-control" name="validadeedit" id="validadeedit" value="<?php echo $dias; ?>" min="1" max="365" required>
                        </div>
                        <?php endif; ?>

                        <div class="form-field full-width">
                            <label><i class='bx bxl-whatsapp' style="color:#25D366;"></i> WhatsApp do Revendedor</label>
                            <input type="text" class="form-control" name="whatsapp" placeholder="5511999999999" value="<?php echo htmlspecialchars($whatsapp); ?>">
                            <small style="color:rgba(255,255,255,0.3);margin-top:3px;display:block;font-size:9px;"><i class='bx bx-info-circle' style="color:#a78bfa;"></i> NÃºmero igual ao WhatsApp</small>
                        </div>
                    </div><!-- end form-grid -->

                    <div class="action-buttons">
                        <a href="listarrevendedores.php" class="btn-action btn-action-danger"><i class='bx bx-x'></i> Cancelar</a>
                        <button type="submit" class="btn-action btn-action-success" name="editarrev"><i class='bx bx-check'></i> Salvar AlteraÃ§Ãµes</button>
                    </div>
                </form>
            </div>
        </div>

    </div>
    </div>

    <!-- MODAL SUCESSO EDIÃ‡ÃƒO -->
    <div id="modalSucessoEdicao" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom success">
                    <h5><i class='bx bx-check-circle'></i> Revendedor Editado com Sucesso!</h5>
                    <button type="button" class="modal-close" onclick="fecharSucesso()"><i class='bx bx-x'></i></button>
                </div>
                <div class="modal-body-custom">
                    <div class="modal-success-icon">
                        <i class='bx bx-check-circle' style="color:#10b981; filter: drop-shadow(0 0 15px rgba(16,185,129,0.5));"></i>
                    </div>
                    <div class="modal-info-card">
                        <div class="modal-info-row">
                            <div class="modal-info-label"><i class='bx bx-user' style="color:#818cf8;"></i> Login</div>
                            <div class="modal-info-value credential"><?php echo htmlspecialchars($dados_salvos['login'] ?? $login); ?></div>
                        </div>
                        <div class="modal-info-row">
                            <div class="modal-info-label"><i class='bx bx-lock-alt' style="color:#e879f9;"></i> Senha</div>
                            <div class="modal-info-value credential"><?php echo htmlspecialchars($dados_salvos['senha'] ?? $senha); ?></div>
                        </div>
                        <div class="modal-info-row">
                            <div class="modal-info-label"><i class='bx bx-layer' style="color:#34d399;"></i> Limite</div>
                            <div class="modal-info-value"><?php echo htmlspecialchars($dados_salvos['limite'] ?? $limite); ?> conexÃµes</div>
                        </div>
                        <div class="modal-info-row">
                            <div class="modal-info-label"><i class='bx bx-dollar' style="color:#10b981;"></i> Valor de RenovaÃ§Ã£o</div>
                            <div class="modal-info-value highlight-green">R$ <?php echo number_format(floatval($dados_salvos['valor_renovacao'] ?? $valor_revenda), 2, ',', '.'); ?></div>
                        </div>
                        <div class="modal-info-row">
                            <div class="modal-info-label"><i class='bx bx-category' style="color:#818cf8;"></i> Tipo</div>
                            <div class="modal-info-value"><?php echo htmlspecialchars($dados_salvos['tipo'] ?? $_SESSION['tipodeconta'] ?? ''); ?></div>
                        </div>
                        <?php if (!empty($dados_salvos['validade'])): ?>
                        <div class="modal-info-row">
                            <div class="modal-info-label"><i class='bx bx-calendar-check' style="color:#10b981;"></i> Validade</div>
                            <div class="modal-info-value highlight-green"><?php echo htmlspecialchars($dados_salvos['validade']); ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($dados_salvos['whatsapp'])): ?>
                        <div class="modal-info-row">
                            <div class="modal-info-label"><i class='bx bxl-whatsapp' style="color:#25D366;"></i> WhatsApp</div>
                            <div class="modal-info-value"><?php echo htmlspecialchars($dados_salvos['whatsapp']); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <hr class="modal-divider">
                    <p class="modal-success-title">âœ¨ Revendedor editado com sucesso! âœ¨</p>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-modal btn-modal-copy" onclick="copiarEdicao()">
                        <i class='bx bx-copy'></i> Copiar InformaÃ§Ãµes
                    </button>
                    <button type="button" class="btn-modal btn-modal-ok" onclick="fecharSucesso()">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL ERRO VALIDAÃ‡ÃƒO -->
    <div id="modalErroValidacao" class="modal-overlay <?php echo $show_error_modal ? 'show' : ''; ?>">
        <div class="modal-container">
            <div class="modal-content-custom">
                <div class="modal-header-custom error">
                    <h5><i class='bx bx-error-circle'></i> Erro!</h5>
                    <button type="button" class="modal-close" onclick="fecharModal('modalErroValidacao')"><i class='bx bx-x'></i></button>
                </div>
                <div class="modal-body-custom">
                    <div style="text-align:center; margin-bottom: 20px;">
                        <i class='bx bx-error-circle' style="font-size:70px; color:#dc2626; filter: drop-shadow(0 0 10px rgba(220,38,38,0.5));"></i>
                    </div>
                    <h3 style="color:white; margin-bottom:10px; text-align:center;">Ops! Algo deu errado</h3>
                    <p style="color:rgba(255,255,255,0.8); text-align:center;"><?php echo htmlspecialchars($error_message); ?></p>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-modal" style="background:linear-gradient(135deg,#dc2626,#b91c1c);" onclick="fecharModal('modalErroValidacao')">
                        <i class='bx bx-check'></i> OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../app-assets/sweetalert.min.js"></script>
    <script>
        function fecharModal(id)  { document.getElementById(id).classList.remove('show'); }
        function fecharSucesso()  { fecharModal('modalSucessoEdicao'); window.location.href = 'listarrevendedores.php'; }

        // Fechar ao clicar fora
        document.addEventListener('click', function(e) {
            if (!e.target.classList.contains('modal-overlay')) return;
            const id = e.target.id;
            if (id === 'modalSucessoEdicao') fecharSucesso();
            else fecharModal(id);
        });

        document.addEventListener('keydown', function(e) {
            if (e.key !== 'Escape') return;
            if (document.getElementById('modalSucessoEdicao').classList.contains('show')) fecharSucesso();
            fecharModal('modalErroValidacao');
        });

        function copiarEdicao() {
            const login    = <?php echo json_encode($dados_salvos['login']    ?? $login); ?>;
            const senha    = <?php echo json_encode($dados_salvos['senha']    ?? $senha); ?>;
            const limite   = <?php echo json_encode(($dados_salvos['limite']  ?? $limite) . ' conexÃµes'); ?>;
            const valor    = <?php echo json_encode('R$ ' . number_format(floatval($dados_salvos['valor_renovacao'] ?? $valor_revenda), 2, ',', '.')); ?>;
            const tipo     = <?php echo json_encode($dados_salvos['tipo']     ?? ($_SESSION['tipodeconta'] ?? '')); ?>;
            const validade  = <?php echo json_encode($dados_salvos['validade']  ?? 'â€”'); ?>;
            const whatsapp  = <?php echo json_encode($dados_salvos['whatsapp']  ?? ''); ?>;
            
            let txt = `âœ… REVENDEDOR EDITADO!\nâ”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\nðŸ‘¤ Login: ${login}\nðŸ”‘ Senha: ${senha}\nðŸ”— Limite: ${limite}\nðŸ’° Valor RenovaÃ§Ã£o: ${valor}\nðŸ“‹ Tipo: ${tipo}\nðŸ“… Validade: ${validade}`;
            if (whatsapp) txt += `\nðŸ“± WhatsApp: ${whatsapp}`;
            txt += `\nðŸ“† Data: ${new Date().toLocaleString('pt-BR')}`;
            navigator.clipboard.writeText(txt).then(() => {
                const t = document.createElement('div');
                t.className = 'toast-notification';
                t.innerHTML = '<i class="bx bx-check-circle" style="font-size:20px"></i> InformaÃ§Ãµes copiadas!';
                document.body.appendChild(t);
                setTimeout(() => t.remove(), 3000);
            });
        }

        <?php if ($show_success_modal): ?>
        document.addEventListener('DOMContentLoaded', () => { document.getElementById('modalSucessoEdicao').classList.add('show'); });
        <?php endif; ?>
    </script>
    <script src="../app-assets/js/scripts/forms/number-input.js"></script>
</body>
</html>



