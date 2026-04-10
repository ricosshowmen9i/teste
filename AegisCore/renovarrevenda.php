<?php
error_reporting(0);
session_start();

// Configurar fuso horário para Brasília
date_default_timezone_set('America/Sao_Paulo');

// Incluir dependências no topo
require('../vendor/event/autoload.php');
use React\EventLoop\Factory;

// Verificar login
if(!isset($_SESSION['login']) and !isset($_SESSION['senha'])){
    session_destroy();
    unset($_SESSION['login']);
    unset($_SESSION['senha']);
    header('location:index.php');
    exit();
}

include('conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

include('header2.php');

if (!file_exists('../admin/suspenderrev.php')) {
    exit("<script>alert('Token Invalido!');</script>");
} else {
    include_once '../admin/suspenderrev.php';
}

if (!isset($_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe']) || !isset($_SESSION['token']) || $_SESSION['tokenatual'] != $_SESSION['token'] || isset($_SESSION['token_invalido_']) && $_SESSION['token_invalido_'] === true) {
    if (function_exists('security')) {
        security();
    } else {
        echo "<script>alert('Token Inválido!');</script>";
        echo "<script>location.href='../index.php';</script>";
        $_SESSION['token_invalido_'] = true;
        exit;
    }
}

// Função para buscar token do servidor
function getServidorToken($conn, $servidor_id) {
    $sql_token = "SELECT token FROM servidor_tokens WHERE servidor_id = '$servidor_id' AND status = 'ativo' ORDER BY id DESC LIMIT 1";
    $result_token = mysqli_query($conn, $sql_token);
    
    if ($result_token && mysqli_num_rows($result_token) > 0) {
        $row_token = mysqli_fetch_assoc($result_token);
        return $row_token['token'];
    }
    return md5($_SESSION['token']);
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

// Pegar ID do revendedor
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (empty($id)) {
    echo "<script>swal('Erro!', 'ID do revendedor não informado!', 'error').then(function() { window.location.href = 'listarrevendedores.php'; });</script>";
    exit();
}

// Buscar dados do revendedor
$sql = "SELECT * FROM atribuidos WHERE userid = '$id'";
$result = mysqli_query($conn, $sql);
if (!$result || mysqli_num_rows($result) == 0) {
    echo "<script>swal('Erro!', 'Revendedor não encontrado!', 'error').then(function() { window.location.href = 'listarrevendedores.php'; });</script>";
    exit();
}

$row = mysqli_fetch_assoc($result);
$expira = $row['expira'];
$limite = $row['limite'];
$categoria = $row['categoriaid'];
$tipo = $row['tipo'];
$suspenso = $row['suspenso'];

// Verificar se o revendedor estava suspenso (por vencimento ou por campo suspenso)
$estava_suspenso = false;
if ($suspenso == 1 || $suspenso == 'Suspenso' || ($expira < date('Y-m-d H:i:s'))) {
    $estava_suspenso = true;
}

// Calcular nova data de expiração
if ($expira < date('Y-m-d H:i:s')) {
    $expira = date('Y-m-d H:i:s');
}
$nova_expira = date('Y-m-d H:i:s', strtotime("+30 days", strtotime($expira)));

// Atualizar revendedor (remover suspensão e atualizar data)
$sql_update = "UPDATE atribuidos SET expira = '$nova_expira', suspenso = '0' WHERE userid = '$id'";
if (!mysqli_query($conn, $sql_update)) {
    echo "<script>swal('Erro!', 'Erro ao renovar revendedor: " . mysqli_error($conn) . "', 'error').then(function() { window.location.href = 'listarrevendedores.php'; });</script>";
    exit();
}

// Registrar log
$datahoje = date('d-m-Y H:i:s');
$sql_log = "INSERT INTO logs (revenda, validade, texto, userid) VALUES ('$_SESSION[login]', '$datahoje', 'Renovou revendedor ID $id', '$_SESSION[iduser]')";
mysqli_query($conn, $sql_log);

$usuarios_reativados = 0;
$revendas_reativadas = 0;
$usuarios_erro = 0;
$revendas_erro = 0;

if ($estava_suspenso) {
    // ==================== 1. REATIVAR OS USUÁRIOS DO REVENDEDOR ====================
    $sql_usuarios = "SELECT * FROM ssh_accounts WHERE byid = '$id' AND expira > NOW()";
    $result_usuarios = mysqli_query($conn, $sql_usuarios);
    
    if ($result_usuarios && mysqli_num_rows($result_usuarios) > 0) {
        // Buscar servidores da categoria
        $sql_servidores = "SELECT * FROM servidores WHERE subid = '$categoria'";
        $result_servidores = mysqli_query($conn, $sql_servidores);
        
        $loop = Factory::create();
        
        while ($usuario = mysqli_fetch_assoc($result_usuarios)) {
            $login = $usuario['login'];
            $senha = $usuario['senha'];
            $limite_user = $usuario['limite'];
            $uuid = $usuario['uuid'];
            $expira_user = $usuario['expira'];
            
            // Calcular dias restantes
            $data_atual = date('Y-m-d H:i:s');
            $diferenca = strtotime($expira_user) - strtotime($data_atual);
            $dias_restantes = floor($diferenca / (60 * 60 * 24));
            if ($dias_restantes < 1) {
                $dias_restantes = 1;
            }
            
            // Reativar em cada servidor
            $result_servidores->data_seek(0);
            while ($servidor = mysqli_fetch_assoc($result_servidores)) {
                $socket = @fsockopen($servidor['ip'], 6969, $errno, $errstr, 3);
                
                if ($socket) {
                    fclose($socket);
                    
                    $servidor_id = $servidor['id'];
                    $senha_token = getServidorToken($conn, $servidor_id);
                    
                    $loop->addTimer(0.001, function () use ($servidor, $login, $senha, $dias_restantes, $limite_user, $senha_token, $uuid) {
                        $headers = array('Senha: ' . $senha_token);
                        
                        if ($uuid != '' && $uuid != 'Não Gerado') {
                            // Com V2Ray
                            $comando_remover = 'sudo /etc/xis/rem.sh ' . $uuid . ' ' . $login;
                            $comando_adicionar = 'sudo /etc/xis/add.sh ' . $uuid . ' ' . $login . ' ' . $senha . ' ' . $dias_restantes . ' ' . $limite_user;
                            
                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, $servidor['ip'] . ':6969');
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                            curl_setopt($ch, CURLOPT_POST, 1);
                            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, "comando=$comando_remover");
                            curl_exec($ch);
                            curl_close($ch);
                            
                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, $servidor['ip'] . ':6969');
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                            curl_setopt($ch, CURLOPT_POST, 1);
                            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, "comando=$comando_adicionar");
                            curl_exec($ch);
                            curl_close($ch);
                        } else {
                            // Sem V2Ray
                            $comando_remover = 'sudo /etc/xis/atlasremove.sh ' . $login;
                            $comando_criar = 'sudo /etc/xis/atlascreate.sh ' . $login . ' ' . $senha . ' ' . $dias_restantes . ' ' . $limite_user;
                            
                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, $servidor['ip'] . ':6969');
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                            curl_setopt($ch, CURLOPT_POST, 1);
                            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, "comando=$comando_remover");
                            curl_exec($ch);
                            curl_close($ch);
                            
                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, $servidor['ip'] . ':6969');
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                            curl_setopt($ch, CURLOPT_POST, 1);
                            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, "comando=$comando_criar");
                            curl_exec($ch);
                            curl_close($ch);
                        }
                    });
                    
                    $usuarios_reativados++;
                } else {
                    $usuarios_erro++;
                }
            }
        }
        
        $loop->run();
        
        // Atualizar status dos usuários no banco (remover suspensão)
        $sql_reativar_usuarios = "UPDATE ssh_accounts SET mainid = '0' WHERE byid = '$id' AND expira > NOW()";
        mysqli_query($conn, $sql_reativar_usuarios);
    }
    
    // ==================== 2. REATIVAR OS SUB-REVENDEDORES ====================
    $sql_revendas = "SELECT * FROM atribuidos WHERE byid = '$id' AND expira > NOW()";
    $result_revendas = mysqli_query($conn, $sql_revendas);
    
    if ($result_revendas && mysqli_num_rows($result_revendas) > 0) {
        while ($revenda = mysqli_fetch_assoc($result_revendas)) {
            $revenda_id = $revenda['userid'];
            $revenda_expira = $revenda['expira'];
            $revenda_limite = $revenda['limite'];
            $revenda_categoria = $revenda['categoriaid'];
            
            // Calcular dias restantes para a revenda
            $diferenca_rev = strtotime($revenda_expira) - strtotime($data_atual);
            $dias_restantes_rev = floor($diferenca_rev / (60 * 60 * 24));
            if ($dias_restantes_rev < 1) {
                $dias_restantes_rev = 1;
            }
            
            // Atualizar a revenda no banco (remover suspensão)
            $sql_update_rev = "UPDATE atribuidos SET suspenso = '0' WHERE userid = '$revenda_id'";
            mysqli_query($conn, $sql_update_rev);
            
            // Reativar os usuários desta sub-revenda
            $sql_usuarios_sub = "SELECT * FROM ssh_accounts WHERE byid = '$revenda_id' AND expira > NOW()";
            $result_usuarios_sub = mysqli_query($conn, $sql_usuarios_sub);
            
            if ($result_usuarios_sub && mysqli_num_rows($result_usuarios_sub) > 0) {
                $sql_servidores_sub = "SELECT * FROM servidores WHERE subid = '$revenda_categoria'";
                $result_servidores_sub = mysqli_query($conn, $sql_servidores_sub);
                
                $loop_sub = Factory::create();
                
                while ($usuario_sub = mysqli_fetch_assoc($result_usuarios_sub)) {
                    $login_sub = $usuario_sub['login'];
                    $senha_sub = $usuario_sub['senha'];
                    $limite_sub = $usuario_sub['limite'];
                    $uuid_sub = $usuario_sub['uuid'];
                    $expira_sub = $usuario_sub['expira'];
                    
                    $diferenca_sub = strtotime($expira_sub) - strtotime($data_atual);
                    $dias_sub = floor($diferenca_sub / (60 * 60 * 24));
                    if ($dias_sub < 1) $dias_sub = 1;
                    
                    $result_servidores_sub->data_seek(0);
                    while ($servidor_sub = mysqli_fetch_assoc($result_servidores_sub)) {
                        $socket_sub = @fsockopen($servidor_sub['ip'], 6969, $errno, $errstr, 3);
                        
                        if ($socket_sub) {
                            fclose($socket_sub);
                            
                            $servidor_id_sub = $servidor_sub['id'];
                            $senha_token_sub = getServidorToken($conn, $servidor_id_sub);
                            
                            $loop_sub->addTimer(0.001, function () use ($servidor_sub, $login_sub, $senha_sub, $dias_sub, $limite_sub, $senha_token_sub, $uuid_sub) {
                                $headers_sub = array('Senha: ' . $senha_token_sub);
                                
                                if ($uuid_sub != '' && $uuid_sub != 'Não Gerado') {
                                    $comando_remover = 'sudo /etc/xis/rem.sh ' . $uuid_sub . ' ' . $login_sub;
                                    $comando_adicionar = 'sudo /etc/xis/add.sh ' . $uuid_sub . ' ' . $login_sub . ' ' . $senha_sub . ' ' . $dias_sub . ' ' . $limite_sub;
                                    
                                    $ch = curl_init();
                                    curl_setopt($ch, CURLOPT_URL, $servidor_sub['ip'] . ':6969');
                                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers_sub);
                                    curl_setopt($ch, CURLOPT_POST, 1);
                                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                                    curl_setopt($ch, CURLOPT_POSTFIELDS, "comando=$comando_remover");
                                    curl_exec($ch);
                                    curl_close($ch);
                                    
                                    $ch = curl_init();
                                    curl_setopt($ch, CURLOPT_URL, $servidor_sub['ip'] . ':6969');
                                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers_sub);
                                    curl_setopt($ch, CURLOPT_POST, 1);
                                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                                    curl_setopt($ch, CURLOPT_POSTFIELDS, "comando=$comando_adicionar");
                                    curl_exec($ch);
                                    curl_close($ch);
                                } else {
                                    $comando_remover = 'sudo /etc/xis/atlasremove.sh ' . $login_sub;
                                    $comando_criar = 'sudo /etc/xis/atlascreate.sh ' . $login_sub . ' ' . $senha_sub . ' ' . $dias_sub . ' ' . $limite_sub;
                                    
                                    $ch = curl_init();
                                    curl_setopt($ch, CURLOPT_URL, $servidor_sub['ip'] . ':6969');
                                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers_sub);
                                    curl_setopt($ch, CURLOPT_POST, 1);
                                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                                    curl_setopt($ch, CURLOPT_POSTFIELDS, "comando=$comando_remover");
                                    curl_exec($ch);
                                    curl_close($ch);
                                    
                                    $ch = curl_init();
                                    curl_setopt($ch, CURLOPT_URL, $servidor_sub['ip'] . ':6969');
                                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers_sub);
                                    curl_setopt($ch, CURLOPT_POST, 1);
                                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                                    curl_setopt($ch, CURLOPT_POSTFIELDS, "comando=$comando_criar");
                                    curl_exec($ch);
                                    curl_close($ch);
                                }
                            });
                            
                            $usuarios_reativados++;
                        } else {
                            $usuarios_erro++;
                        }
                    }
                }
                
                $loop_sub->run();
                
                // Atualizar status dos usuários da sub-revenda no banco
                $sql_reativar_sub = "UPDATE ssh_accounts SET mainid = '0' WHERE byid = '$revenda_id' AND expira > NOW()";
                mysqli_query($conn, $sql_reativar_sub);
            }
            
            $revendas_reativadas++;
        }
        
        // Registrar log das revendas reativadas
        if ($revendas_reativadas > 0) {
            $sql_log_rev = "INSERT INTO logs (revenda, validade, texto, userid) VALUES ('$_SESSION[login]', '$datahoje', 'Reativou $revendas_reativadas revendas do revendedor ID $id', '$_SESSION[iduser]')";
            mysqli_query($conn, $sql_log_rev);
        }
    }
    
    // Registrar log dos usuários reativados
    if ($usuarios_reativados > 0) {
        $sql_log_reativacao = "INSERT INTO logs (revenda, validade, texto, userid) VALUES ('$_SESSION[login]', '$datahoje', 'Reativou $usuarios_reativados usuários do revendedor ID $id', '$_SESSION[iduser]')";
        mysqli_query($conn, $sql_log_reativacao);
    }
}

// Mensagem de sucesso
$mensagem = "Revendedor renovado com sucesso!";
if ($estava_suspenso) {
    $mensagem .= "\n\n📊 Resumo da reativação:";
    $mensagem .= "\n✅ Revendas reativadas: $revendas_reativadas";
    $mensagem .= "\n✅ Usuários reativados: $usuarios_reativados";
    if ($usuarios_erro > 0 || $revendas_erro > 0) {
        $mensagem .= "\n⚠️ Erros: $usuarios_erro usuários e $revendas_erro revendas tiveram falha na reativação.";
    }
}

echo "<script>
    swal({
        title: 'Sucesso!',
        text: '$mensagem',
        icon: 'success',
        button: 'OK'
    }).then(function() {
        window.location.href = 'listarrevendedores.php';
    });
</script>";
?>