<?php
error_reporting(0);
session_start();

// Configurar fuso horário para Brasília
date_default_timezone_set('America/Sao_Paulo');

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

function anti_sql($input) {
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function($match) {
        return '';
    }, $input);
    $seg = trim($seg);
    $seg = strip_tags($seg);
    $seg = addslashes($seg);
    return $seg;
}

/**
 * Envia comandos para múltiplos servidores em paralelo via API padrão (porta 9001)
 */
function enviarComandosServidoresParalelo($comandos) {
    $multiHandle = curl_multi_init();
    $curlHandles = [];
    
    foreach ($comandos as $key => $cmd) {
        $ch = curl_init($cmd['url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Expect: '
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $cmd['payload']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        $curlHandles[$key] = $ch;
        curl_multi_add_handle($multiHandle, $ch);
    }
    
    $running = null;
    do {
        curl_multi_exec($multiHandle, $running);
        curl_multi_select($multiHandle);
    } while ($running > 0);
    
    $responses = [];
    foreach ($curlHandles as $key => $ch) {
        $responses[$key] = [
            'resposta' => curl_multi_getcontent($ch),
            'erro' => curl_error($ch),
            'httpcode' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
            'nome' => $comandos[$key]['nome'] ?? 'Servidor'
        ];
        curl_multi_remove_handle($multiHandle, $ch);
        curl_close($ch);
    }
    
    curl_multi_close($multiHandle);
    return $responses;
}

/**
 * Busca o token do admin para autenticação na API dos servidores
 */
function getAdminToken($conn) {
    $stmt_token = mysqli_query($conn, "SELECT token FROM api WHERE byid = 1 LIMIT 1");
    if ($stmt_token && mysqli_num_rows($stmt_token) > 0) {
        $row = mysqli_fetch_assoc($stmt_token);
        return $row['token'];
    }
    return null;
}

/**
 * Processa a reativação de um usuário nos servidores
 */
function reativarUsuario($conn, $usuario, $servidores, $adminToken, $endpoint = '/criar') {
    $comandos = [];
    $login = $usuario['login'];
    $senha = $usuario['senha'];
    $limite = $usuario['limite'];
    $uuid = $usuario['uuid'] ?? '';
    $tipo = $usuario['tipo'] ?? 'ssh';
    $expira = $usuario['expira'];
    
    // Calcular dias restantes
    $dias_restantes = max(1, ceil((strtotime($expira) - time()) / (60 * 60 * 24)));
    
    $dadosPayload = [
        'login' => $login,
        'senha' => $senha,
        'dias' => (int)$dias_restantes,
        'limite' => (int)$limite,
        'uuid' => $uuid,
        'tipo' => $tipo
    ];
    
    $payload = json_encode([$adminToken, $dadosPayload]);
    
    foreach ($servidores as $srv) {
        $ipServidor = str_replace(['http://', 'https://'], '', $srv['dominio']);
        $url = "http://{$ipServidor}:9001{$endpoint}";
        
        $comandos[] = [
            'url' => $url,
            'payload' => $payload,
            'nome' => $srv['nome']
        ];
    }
    
    if (!empty($comandos)) {
        $resultados = enviarComandosServidoresParalelo($comandos);
        $sucesso = false;
        foreach ($resultados as $res) {
            if (empty($res['erro']) && $res['httpcode'] == 200) {
                $sucesso = true;
            }
        }
        return $sucesso;
    }
    
    return false;
}

/**
 * Coleta recursivamente todos os IDs de sub-revendas de um revendedor
 */
function coletarSubRevendas($conn, $revendedorId, &$todosIds = []) {
    $sql = "SELECT id FROM accounts WHERE byid = '$revendedorId'";
    $result = mysqli_query($conn, $sql);
    
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $todosIds[] = $row['id'];
            coletarSubRevendas($conn, $row['id'], $todosIds);
        }
    }
    
    return $todosIds;
}

/**
 * Coleta todos os usuários ativos de uma lista de revendedores
 */
function coletarUsuariosAtivos($conn, $revendedoresIds) {
    $usuarios = [];
    $idsStr = implode(',', array_map('intval', $revendedoresIds));
    
    if (empty($idsStr)) return $usuarios;
    
    $sql = "SELECT * FROM ssh_accounts WHERE byid IN ($idsStr) AND expira > NOW()";
    $result = mysqli_query($conn, $sql);
    
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $usuarios[] = $row;
        }
    }
    
    return $usuarios;
}

/**
 * Coleta todas as atribuições (revendas) ativas de uma lista de revendedores
 */
function coletarAtribuicoesAtivas($conn, $revendedoresIds) {
    $atribuicoes = [];
    $idsStr = implode(',', array_map('intval', $revendedoresIds));
    
    if (empty($idsStr)) return $atribuicoes;
    
    $sql = "SELECT * FROM atribuidos WHERE userid IN ($idsStr) AND expira > NOW()";
    $result = mysqli_query($conn, $sql);
    
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $atribuicoes[] = $row;
        }
    }
    
    return $atribuicoes;
}

/**
 * Obtém os servidores de uma categoria
 */
function getServidoresPorCategoria($conn, $categoriaId) {
    $servidores = [];
    $sql = "SELECT ip, nome FROM servidores WHERE subid = '$categoriaId'";
    $result = mysqli_query($conn, $sql);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $ipsProcessados = [];
        while ($row = mysqli_fetch_assoc($result)) {
            if (!in_array($row['ip'], $ipsProcessados)) {
                $servidores[] = [
                    'dominio' => $row['ip'],
                    'nome' => $row['nome']
                ];
                $ipsProcessados[] = $row['ip'];
            }
        }
    }
    
    return $servidores;
}

/**
 * Reativa uma revenda (atribuição) e todos os seus usuários
 */
function reativarRevenda($conn, $atribuicao, $adminToken) {
    $userid = $atribuicao['userid'];
    $categoriaid = $atribuicao['categoriaid'];
    $expira = $atribuicao['expira'];
    
    // Atualizar status da revenda no banco
    $sql_update = "UPDATE atribuidos SET suspenso = 0 WHERE userid = '$userid'";
    mysqli_query($conn, $sql_update);
    
    // Buscar servidores da categoria
    $servidores = getServidoresPorCategoria($conn, $categoriaid);
    if (empty($servidores)) {
        return ['usuarios_reativados' => 0, 'usuarios_erro' => 0];
    }
    
    // Coletar usuários ativos desta revenda
    $sql_usuarios = "SELECT * FROM ssh_accounts WHERE byid = '$userid' AND expira > NOW()";
    $result_usuarios = mysqli_query($conn, $sql_usuarios);
    
    $usuarios_reativados = 0;
    $usuarios_erro = 0;
    
    if ($result_usuarios && mysqli_num_rows($result_usuarios) > 0) {
        while ($usuario = mysqli_fetch_assoc($result_usuarios)) {
            if (reativarUsuario($conn, $usuario, $servidores, $adminToken, '/criar')) {
                $usuarios_reativados++;
            } else {
                $usuarios_erro++;
            }
        }
        
        // Atualizar status dos usuários no banco
        $sql_reativar_usuarios = "UPDATE ssh_accounts SET mainid = '0' WHERE byid = '$userid' AND expira > NOW()";
        mysqli_query($conn, $sql_reativar_usuarios);
    }
    
    return [
        'usuarios_reativados' => $usuarios_reativados,
        'usuarios_erro' => $usuarios_erro
    ];
}

// ==================== MAIN ====================

// Pegar ID do revendedor
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (empty($id)) {
    echo "<script>swal('Erro!', 'ID do revendedor não informado!', 'error').then(function() { window.location.href = 'listarrevendedores.php'; });</script>";
    exit();
}

// Buscar dados do revendedor (atribuição)
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

// Calcular nova data de expiração (30 dias)
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

// Buscar token do admin para API
$adminToken = getAdminToken($conn);
if (empty($adminToken)) {
    echo "<script>swal('Erro!', 'Token de API não encontrado! Configure o token na tabela api.', 'error').then(function() { window.location.href = 'listarrevendedores.php'; });</script>";
    exit();
}

$usuarios_reativados = 0;
$revendas_reativadas = 0;
$usuarios_erro = 0;
$revendas_erro = 0;

if ($estava_suspenso) {
    // ==================== COLETAR TODAS AS SUB-REVENDAS ====================
    $todosRevendedoresIds = [$id];
    coletarSubRevendas($conn, $id, $todosRevendedoresIds);
    
    // ==================== REATIVAR USUÁRIOS DO REVENDEDOR PRINCIPAL ====================
    $servidoresPrincipal = getServidoresPorCategoria($conn, $categoria);
    
    if (!empty($servidoresPrincipal)) {
        $usuariosPrincipal = coletarUsuariosAtivos($conn, [$id]);
        
        foreach ($usuariosPrincipal as $usuario) {
            if (reativarUsuario($conn, $usuario, $servidoresPrincipal, $adminToken, '/criar')) {
                $usuarios_reativados++;
            } else {
                $usuarios_erro++;
            }
        }
        
        // Atualizar status dos usuários no banco
        if (!empty($usuariosPrincipal)) {
            $sql_reativar_usuarios = "UPDATE ssh_accounts SET mainid = '0' WHERE byid = '$id' AND expira > NOW()";
            mysqli_query($conn, $sql_reativar_usuarios);
        }
    }
    
    // ==================== REATIVAR SUB-REVENDAS ====================
    $subRevendasIds = array_diff($todosRevendedoresIds, [$id]);
    
    if (!empty($subRevendasIds)) {
        $atribuicoesAtivas = coletarAtribuicoesAtivas($conn, $subRevendasIds);
        
        foreach ($atribuicoesAtivas as $atribuicao) {
            $resultado = reativarRevenda($conn, $atribuicao, $adminToken);
            $usuarios_reativados += $resultado['usuarios_reativados'];
            $usuarios_erro += $resultado['usuarios_erro'];
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

// ==================== ENVIAR NOTIFICAÇÃO WHATSAPP (OPCIONAL) ====================
// Buscar contato do revendedor para enviar notificação
$sql_contato = "SELECT login, contato FROM accounts WHERE id = '$id' LIMIT 1";
$result_contato = mysqli_query($conn, $sql_contato);
if ($result_contato && mysqli_num_rows($result_contato) > 0) {
    $revendaInfo = mysqli_fetch_assoc($result_contato);
    
    if (!empty($revendaInfo['contato'])) {
        try {
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
            $dominio = $_SERVER['HTTP_HOST'];
            $whatAtribuURL = $protocol . $dominio . "/pages/revendas/whatatribu.php";
            
            $postData = array(
                'userid' => $id,
                'byid' => 1,
                'contato' => $revendaInfo['contato'],
                'categoriaid' => $categoria,
                'limite' => $limite,
                'limitetest' => $row['limitetest'] ?? 0,
                'tipo' => $tipo,
                'expira' => $nova_expira
            );
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $whatAtribuURL);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_exec($ch);
            curl_close($ch);
        } catch (Exception $e) {
            // Não crítico, apenas log
            error_log("Erro ao enviar notificação WhatsApp: " . $e->getMessage());
        }
    }
}

// ==================== MENSAGEM DE SUCESSO ====================
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