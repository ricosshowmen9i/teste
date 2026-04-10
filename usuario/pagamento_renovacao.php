<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

function logDepuracao($mensagem) {
    $logFile = __DIR__ . '/renovacao_log.txt';
    $data = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$data] $mensagem\n", FILE_APPEND);
}

logDepuracao("=== INICIANDO PAGAMENTO_RENOVACAO ===");

if(!isset($_SESSION['usuario_renovacao']) || !isset($_SESSION['login'])){
    session_destroy();
    header('Location: ../index.php');
    exit();
}

include('../AegisCore/conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) die("Erro de conexão com o banco de dados!");

// ========== CRIAR TABELA SE NÃO EXISTIR ==========
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `pagamentos_unificado` (
    `id`             int(11) NOT NULL AUTO_INCREMENT,
    `tipo`           varchar(50) NOT NULL DEFAULT 'renovacao_usuario',
    `user_id`        int(11) NOT NULL DEFAULT 0,
    `login`          varchar(100) NOT NULL DEFAULT '',
    `payment_id`     varchar(100) NOT NULL,
    `valor`          decimal(10,2) NOT NULL DEFAULT 0.00,
    `status`         varchar(50) NOT NULL DEFAULT 'pending',
    `data_criacao`   datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `data_pagamento` datetime DEFAULT NULL,
    `revendedor_id`  int(11) NOT NULL DEFAULT 0,
    `plano_id`       int(11) DEFAULT NULL,
    `limite_creditos` int(11) DEFAULT NULL,
    `duracao_dias`   int(11) DEFAULT NULL,
    `descricao`      varchar(255) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_payment_id` (`payment_id`),
    KEY `idx_user_id`    (`user_id`),
    KEY `idx_revendedor` (`revendedor_id`),
    KEY `idx_status`     (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ========== HELPER: SALVAR NA TABELA UNIFICADA ==========
function salvarUnificado($conn, $tipo, $user_id, $login, $payment_id, $valor, $status, $revendedor_id, $data_pagamento = null) {
    $tipo         = mysqli_real_escape_string($conn, $tipo);
    $user_id      = intval($user_id);
    $login        = mysqli_real_escape_string($conn, $login);
    $payment_id   = mysqli_real_escape_string($conn, $payment_id);
    $valor        = floatval($valor);
    $status       = mysqli_real_escape_string($conn, $status);
    $revendedor_id = intval($revendedor_id);
    $data_criacao = date('Y-m-d H:i:s');
    $data_pag_sql = $data_pagamento ? "'".mysqli_real_escape_string($conn, $data_pagamento)."'" : 'NULL';

    $sql = "INSERT INTO pagamentos_unificado
                (tipo, user_id, login, payment_id, valor, status, data_criacao, data_pagamento, revendedor_id)
            VALUES
                ('$tipo', $user_id, '$login', '$payment_id', $valor, '$status', '$data_criacao', $data_pag_sql, $revendedor_id)
            ON DUPLICATE KEY UPDATE
                status         = IF('$status' = 'approved', 'approved', status),
                data_pagamento = IF('$status' = 'approved' AND data_pagamento IS NULL, $data_pag_sql, data_pagamento)";

    $ok = mysqli_query($conn, $sql);
    logDepuracao("salvarUnificado: payment_id=$payment_id status=$status ok=" . ($ok ? 'SIM' : 'NAO - '.mysqli_error($conn)));
    return $ok;
}

function getServidorToken($conn, $servidor_id) {
    $sql_token = "SELECT token FROM servidor_tokens WHERE servidor_id = '$servidor_id' AND status = 'ativo' ORDER BY id DESC LIMIT 1";
    $result_token = mysqli_query($conn, $sql_token);
    if ($result_token && mysqli_num_rows($result_token) > 0) {
        $row_token = mysqli_fetch_assoc($result_token);
        return $row_token['token'];
    }
    return md5($_SESSION['token'] ?? 'default');
}

function renovarUsuarioServidores($conn, $user_id, $login, $senha, $validade_atual, $limite) {
    logDepuracao("=== INICIANDO RENOVACAO NOS SERVIDORES ===");
    date_default_timezone_set('America/Sao_Paulo');
    $hoje = date('Y-m-d H:i:s');
    if ($validade_atual < $hoje) $validade_atual = $hoje;
    $nova_validade = date('Y-m-d H:i:s', strtotime("+30 days", strtotime($validade_atual)));
    $dias_restantes = 30;

    $sql_user = "SELECT * FROM ssh_accounts WHERE id = '".intval($user_id)."'";
    $result_user = mysqli_query($conn, $sql_user);
    $user_data = mysqli_fetch_assoc($result_user);
    $uuid = $user_data['uuid'];
    $categoria_user = $user_data['categoriaid'];
    $byid = $user_data['byid'];

    $datahoje = date('d-m-Y H:i:s');
    mysqli_query($conn, "INSERT INTO logs (revenda, validade, texto, userid) VALUES ('$login', '$datahoje', 'Renovou 30 dias via Mercado Pago', '$byid')");

    $sql_update = "UPDATE ssh_accounts SET expira = '$nova_validade', mainid = '' WHERE id = '".intval($user_id)."'";
    if (!mysqli_query($conn, $sql_update)) {
        logDepuracao("ERRO ao atualizar banco: " . mysqli_error($conn));
        return ['status' => 'error', 'message' => 'Erro ao atualizar banco de dados'];
    }

    $sql_servers = "SELECT * FROM servidores WHERE subid = '$categoria_user'";
    $result_servers = mysqli_query($conn, $sql_servers);

    if (!$result_servers || mysqli_num_rows($result_servers) == 0) {
        return ['status' => 'success', 'message' => 'Renovado no banco!', 'nova_validade' => date('d/m/Y H:i:s', strtotime($nova_validade)), 'servers_ok' => [], 'servers_fail' => []];
    }

    $sucess_servers = [];
    $failed_servers = [];

    while ($server = mysqli_fetch_assoc($result_servers)) {
        $socket_check = @fsockopen($server['ip'], 6969, $errno, $errstr, 3);
        if (!$socket_check) { $failed_servers[] = $server['nome'] . " (offline)"; continue; }
        fclose($socket_check);
        $token_srv = getServidorToken($conn, $server['id']);
        $headers = ['Senha: ' . $token_srv];
        if (!empty($uuid) && $uuid != 'Não Gerado') {
            $comando_remover   = 'sudo /etc/xis/rem.sh ' . $uuid . ' ' . $login;
            $comando_adicionar = 'sudo /etc/xis/add.sh ' . $uuid . ' ' . $login . ' ' . $senha . ' ' . $dias_restantes . ' ' . $limite;
        } else {
            $comando_remover   = 'sudo ./atlasremove.sh ' . $login;
            $comando_adicionar = 'sudo ./atlascreate.sh ' . $login . ' ' . $senha . ' ' . $dias_restantes . ' ' . $limite;
        }
        $ch = curl_init();
        curl_setopt_array($ch, [CURLOPT_URL => $server['ip'].':6969', CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_POST => 1, CURLOPT_POSTFIELDS => "comando=$comando_remover", CURLOPT_TIMEOUT => 10]);
        curl_exec($ch); curl_close($ch);
        $ch = curl_init();
        curl_setopt_array($ch, [CURLOPT_URL => $server['ip'].':6969', CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_POST => 1, CURLOPT_POSTFIELDS => "comando=$comando_adicionar", CURLOPT_TIMEOUT => 10]);
        curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($http_code == 200) $sucess_servers[] = $server['nome'];
        else $failed_servers[] = $server['nome'];
    }

    return ['status' => 'success', 'message' => 'Usuário renovado!', 'nova_validade' => date('d/m/Y H:i:s', strtotime($nova_validade)), 'servers_ok' => $sucess_servers, 'servers_fail' => $failed_servers];
}

// ========== DADOS DA SESSÃO ==========
$usuario_login        = $_SESSION['login'];
$usuario_senha        = $_SESSION['senha'];
$usuario_expira       = $_SESSION['expira'];
$usuario_limite       = $_SESSION['limite'];
$usuario_id           = intval($_SESSION['id']);
$revendedor_id        = intval($_SESSION['revendedor_id']);
$revendedor_email     = $_SESSION['revendedor_email'];
$revendedor_mp_token  = $_SESSION['revendedor_mp_token'];
$revendedor_mp_public_key = $_SESSION['revendedor_mp_public_key'] ?? '';
$valor_renovacao      = floatval($_SESSION['valor_renovacao']);

logDepuracao("Usuário: $usuario_login, ID: $usuario_id, Valor: $valor_renovacao, Revendedor: $revendedor_id");

$hoje = time();
$expiracao = strtotime($usuario_expira);
$dias_restantes = max(0, floor(($expiracao - $hoje) / (60 * 60 * 24)));

$valor_invalido    = ($valor_renovacao <= 0);
$mp_nao_configurado = empty($revendedor_mp_token);
if ($valor_invalido)     $erro = "Valor de renovação inválido (R$ 0,00). Entre em contato com o suporte.";
if ($mp_nao_configurado) $erro = "Revendedor não configurou o Mercado Pago. Contate o suporte.";

$pagamento_processado = false;

// ========== CONFIRMAÇÃO VIA GET ==========
if (isset($_GET['status'], $_GET['payment_id']) && $_GET['status'] == 'success' && !isset($_SESSION['pagamento_processado_'.$_GET['payment_id']])) {
    $payment_id = $_GET['payment_id'];
    $_SESSION['pagamento_processado_'.$payment_id] = true;

    $pid = mysqli_real_escape_string($conn, $payment_id);
    $check = mysqli_query($conn, "SELECT * FROM pagamentos_renovacao WHERE payment_id='$pid' AND user_id='$usuario_id'");

    if (mysqli_num_rows($check) == 0) {
        $now = date('Y-m-d H:i:s');
        mysqli_query($conn, "INSERT INTO pagamentos_renovacao (user_id,login,payment_id,valor,status,data_pagamento,revendedor_id) VALUES ('$usuario_id','$usuario_login','$pid','$valor_renovacao','approved','$now','$revendedor_id')");
        salvarUnificado($conn, 'renovacao_usuario', $usuario_id, $usuario_login, $payment_id, $valor_renovacao, 'approved', $revendedor_id, $now);

        $renovacao_result = renovarUsuarioServidores($conn, $usuario_id, $usuario_login, $usuario_senha, $usuario_expira, $usuario_limite);
        if ($renovacao_result['status'] == 'success') {
            $sucesso = true;
            $mensagem_sucesso = "Pagamento confirmado! Usuário renovado!";
            $nova_validade = $renovacao_result['nova_validade'];
            $pagamento_processado = true;
            $_SESSION['expira'] = date('Y-m-d H:i:s', strtotime($nova_validade));
            unset($_SESSION['qr_code_base64'], $_SESSION['qr_code'], $_SESSION['payment_id']);
        } else {
            $erro = "Pagamento confirmado, mas erro na renovação: " . $renovacao_result['message'];
        }
    } else {
        $row = mysqli_fetch_assoc($check);
        if ($row['status'] == 'approved') {
            $sucesso = true;
            $mensagem_sucesso = "Pagamento já processado!";
            $res_user = mysqli_query($conn, "SELECT expira FROM ssh_accounts WHERE id='$usuario_id'");
            $row_user = mysqli_fetch_assoc($res_user);
            $nova_validade = date('d/m/Y H:i:s', strtotime($row_user['expira']));
            $pagamento_processado = true;
        }
    }
}

// ========== CUPOM ==========
$cupom_aplicado = false;
$cupom_desconto = 0;
$cupom_valor_com_desconto = $valor_renovacao;
$cupom_codigo = '';
$msg_cupom = '';

if (isset($_POST['aplicar_cupom'], $_POST['cupom']) && !empty($_POST['cupom'])) {
    $cupom_codigo = anti_sql($_POST['cupom']);
    $rev = intval($revendedor_id);
    $result_cupom = mysqli_query($conn, "SELECT * FROM cupons WHERE cupom='$cupom_codigo' AND byid='$rev'");
    if (mysqli_num_rows($result_cupom) > 0) {
        $cupom_data = mysqli_fetch_assoc($result_cupom);
        if ($cupom_data['usado'] < $cupom_data['vezesuso']) {
            $cupom_aplicado = true;
            $cupom_desconto = floatval($cupom_data['desconto']);
            $cupom_valor_com_desconto = $valor_renovacao * (1 - ($cupom_desconto / 100));
            $_SESSION['cupom_aplicado'] = $cupom_codigo;
            $_SESSION['cupom_desconto'] = $cupom_desconto;
            $_SESSION['cupom_valor_final'] = $cupom_valor_com_desconto;
            $msg_cupom = "✅ Cupom aplicado! {$cupom_desconto}% de desconto.";
        } else {
            $msg_cupom = "❌ Este cupom já atingiu o limite de uso!";
        }
    } else {
        $msg_cupom = "❌ Cupom inválido!";
    }
} elseif (isset($_SESSION['cupom_aplicado'])) {
    $cupom_aplicado = true;
    $cupom_desconto = $_SESSION['cupom_desconto'];
    $cupom_valor_com_desconto = $_SESSION['cupom_valor_final'];
    $cupom_codigo = $_SESSION['cupom_aplicado'];
    $msg_cupom = "✅ Cupom aplicado! {$cupom_desconto}% de desconto.";
}

// ========== CRIAR PAGAMENTO ==========
if (isset($_POST['criar_pagamento'])) {
    ob_clean();
    header('Content-Type: application/json');

    $valor_pagar = $cupom_aplicado ? $cupom_valor_com_desconto : $valor_renovacao;

    if ($valor_pagar <= 0) { echo json_encode(['status'=>'error','message'=>'Valor zero não pode gerar pagamento!']); exit(); }
    if ($mp_nao_configurado) { echo json_encode(['status'=>'error','message'=>'Mercado Pago não configurado!']); exit(); }

    $idempotency_key = uniqid() . '_' . $usuario_id . '_' . time();
    $external_id     = "renovacao_" . $usuario_id . "_" . time();

    $payment_data = [
        'transaction_amount' => floatval($valor_pagar),
        'description'        => "Renovação - Usuário: $usuario_login",
        'payment_method_id'  => 'pix',
        'payer'              => ['email' => $revendedor_email, 'first_name' => $usuario_login, 'identification' => ['type' => 'CPF', 'number' => '00000000000']],
        'external_reference' => $external_id,
        'notification_url'   => (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/api/webhooks/mercadopago.php'
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => 'https://api.mercadopago.com/v1/payments',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payment_data),
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer '.$revendedor_mp_token, 'Content-Type: application/json', 'X-Idempotency-Key: '.$idempotency_key],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 30
    ]);
    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    logDepuracao("MP criar_pagamento HTTP: $http_code | Response: $response");

    if ($http_code == 200 || $http_code == 201) {
        $result = json_decode($response, true);

        if (isset($result['point_of_interaction']['transaction_data']['qr_code_base64'])) {
            $payment_id     = $result['id'];
            $qr_code_base64 = $result['point_of_interaction']['transaction_data']['qr_code_base64'];
            $qr_code        = $result['point_of_interaction']['transaction_data']['qr_code'];
            $data_criacao   = date('Y-m-d H:i:s');

            // Tabelas antigas (mantidas)
            mysqli_query($conn, "INSERT INTO pagamentos (payment_id,valor,status,data_pagamento,tipo_conta,iduser,byid,external_reference,origem)
                VALUES ('$payment_id','$valor_pagar','pending','$data_criacao','usuario','$usuario_id','$revendedor_id','$external_id','renovacao')");
            mysqli_query($conn, "INSERT INTO pagamentos_renovacao (user_id,login,payment_id,valor,status,data_pagamento,revendedor_id)
                VALUES ('$usuario_id','$usuario_login','$payment_id','$valor_pagar','pending','$data_criacao','$revendedor_id')");

            // ✅ TABELA UNIFICADA — salva como PENDING
            salvarUnificado($conn, 'renovacao_usuario', $usuario_id, $usuario_login, $payment_id, $valor_pagar, 'pending', $revendedor_id, null);

            $_SESSION['payment_id']     = $payment_id;
            $_SESSION['qr_code_base64'] = $qr_code_base64;
            $_SESSION['qr_code']        = $qr_code;
            $_SESSION['cupom_usado']    = $cupom_codigo ?: null;

            logDepuracao("Pagamento PENDING salvo: user_id=$usuario_id login=$usuario_login payment_id=$payment_id");

            echo json_encode(['status' => 'success', 'qr_code_base64' => $qr_code_base64, 'qr_code' => $qr_code, 'payment_id' => $payment_id]);
        } else {
            logDepuracao("Erro QR Code não retornado: $response");
            echo json_encode(['status' => 'error', 'message' => 'Erro ao gerar QR Code']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Erro na API MP: ' . $response]);
    }
    exit();
}

// ========== VERIFICAR PAGAMENTO ==========
if (isset($_POST['verificar_pagamento'])) {
    ob_clean();
    header('Content-Type: application/json');

    $payment_id = mysqli_real_escape_string($conn, $_POST['payment_id'] ?? '');
    logDepuracao("Verificando payment_id: $payment_id");

    if (empty($payment_id)) { echo json_encode(['status'=>'error','message'=>'ID inválido']); exit(); }

    // Checar se já está aprovado no banco (evita reprocessar)
    $check = mysqli_query($conn, "SELECT status FROM pagamentos_unificado WHERE payment_id='$payment_id' LIMIT 1");
    if ($check && mysqli_num_rows($check) > 0) {
        $row_check = mysqli_fetch_assoc($check);
        if ($row_check['status'] == 'approved') {
            $res_v = mysqli_query($conn, "SELECT expira FROM ssh_accounts WHERE id='$usuario_id'");
            $row_v = mysqli_fetch_assoc($res_v);
            $val_fmt = $row_v ? date('d/m/Y H:i:s', strtotime($row_v['expira'])) : 'N/A';
            echo json_encode(['status'=>'approved','message'=>'Já aprovado!','nova_validade'=>$val_fmt]);
            exit();
        }
    }

    // Consultar Mercado Pago
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => 'https://api.mercadopago.com/v1/payments/' . $payment_id,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer '.$revendedor_mp_token, 'Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 30
    ]);
    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result    = json_decode($response, true);
    $status_mp = $result['status'] ?? 'unknown';
    logDepuracao("Status MP: $status_mp");

    if ($status_mp == 'approved') {
        $now = date('Y-m-d H:i:s');

        // Atualizar tabelas antigas
        mysqli_query($conn, "UPDATE pagamentos SET status='approved', data_pagamento='$now' WHERE payment_id='$payment_id'");
        mysqli_query($conn, "UPDATE pagamentos_renovacao SET status='approved', data_pagamento='$now' WHERE payment_id='$payment_id'");

        // ✅ Atualizar tabela unificada — se não existir ainda, insere
        $upd = mysqli_query($conn, "UPDATE pagamentos_unificado SET status='approved', data_pagamento='$now' WHERE payment_id='$payment_id'");
        if (mysqli_affected_rows($conn) == 0) {
            salvarUnificado($conn, 'renovacao_usuario', $usuario_id, $usuario_login, $payment_id, $valor_renovacao, 'approved', $revendedor_id, $now);
        }

        // Cupom
        if (!empty($_SESSION['cupom_usado'])) {
            $cup = mysqli_real_escape_string($conn, $_SESSION['cupom_usado']);
            mysqli_query($conn, "UPDATE cupons SET usado=usado+1 WHERE cupom='$cup'");
            unset($_SESSION['cupom_usado'], $_SESSION['cupom_aplicado'], $_SESSION['cupom_desconto'], $_SESSION['cupom_valor_final']);
        }

        // Renovar servidores
        $renovacao_result = renovarUsuarioServidores($conn, $usuario_id, $usuario_login, $usuario_senha, $usuario_expira, $usuario_limite);

        if ($renovacao_result['status'] == 'success') {
            unset($_SESSION['qr_code_base64'], $_SESSION['qr_code'], $_SESSION['payment_id']);
            $sql_sync = "SELECT expira,limite,senha,valormensal FROM ssh_accounts WHERE id='$usuario_id'";
            $res_sync = mysqli_query($conn, $sql_sync);
            $row_sync = mysqli_fetch_assoc($res_sync);
            $_SESSION['expira']  = $row_sync['expira'];
            $_SESSION['limite']  = $row_sync['limite'];
            $_SESSION['senha']   = $row_sync['senha'];
            logDepuracao("Renovação aprovada! Nova validade: ".$renovacao_result['nova_validade']);
            echo json_encode(['status'=>'approved','message'=>'Pagamento aprovado! Usuário renovado!','nova_validade'=>$renovacao_result['nova_validade']]);
        } else {
            echo json_encode(['status'=>'error','message'=>'Erro ao renovar: '.$renovacao_result['message']]);
        }
    } else {
        echo json_encode(['status' => $status_mp, 'message' => 'Aguardando pagamento...']);
    }
    exit();
}

function anti_sql($input) {
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function($match) { return ''; }, $input);
    return addslashes(strip_tags(trim($seg)));
}

$has_pending = !empty($_SESSION['qr_code_base64']);
$payment_id  = $_SESSION['payment_id'] ?? '';
$qr_code     = $_SESSION['qr_code']    ?? '';

$result_cfg   = $conn->query("SELECT * FROM configs");
$cfg          = $result_cfg->fetch_assoc();
$nomepainel   = $cfg['nomepainel']   ?? 'Painel';
$logo         = $cfg['logo']         ?? '';
$icon         = $cfg['icon']         ?? '';
$csspersonali = $cfg['corfundologo'] ?? '';

include_once("../AegisCore/temas.php");
$temaUsuario = initTemas($conn);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?php echo htmlspecialchars($nomepainel); ?> - Pagamento</title>
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo $icon; ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        <?php echo $csspersonali; ?>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #0f0c29, #1e1b4b, #0f172a); min-height: 100vh; }
        .dashboard-container { display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background: rgba(15,25,35,0.92); backdrop-filter: blur(15px); border-radius: 24px; margin: 16px 0 16px 16px; padding: 18px 0; position: fixed; height: calc(100vh - 32px); overflow-y: auto; border: 1px solid rgba(255,255,255,0.1); box-shadow: 0 10px 30px rgba(0,0,0,0.3); transition: transform 0.3s ease; z-index: 1000; }
        .sidebar::-webkit-scrollbar { width: 4px; }
        .sidebar::-webkit-scrollbar-track { background: rgba(255,255,255,0.05); border-radius: 4px; }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(16,185,129,0.3); border-radius: 4px; }
        .sidebar-logo { text-align: center; padding: 0 20px 18px; border-bottom: 1px solid rgba(255,255,255,0.08); margin-bottom: 16px; }
        .sidebar-logo img { max-height: 45px; max-width: 160px; }
        .sidebar-nav { padding: 0 12px; }
        .nav-item { display: flex; align-items: center; gap: 12px; padding: 9px 14px; color: rgba(255,255,255,0.7); text-decoration: none; border-radius: 12px; margin-bottom: 4px; transition: all 0.3s; font-size: 13px; font-weight: 500; }
        .nav-item:hover { background: rgba(255,255,255,0.08); color: white; transform: translateX(4px); }
        .nav-item.active { background: linear-gradient(135deg, rgba(16,185,129,0.2), rgba(5,150,105,0.1)); color: #10b981; border-left: 3px solid #10b981; }
        .nav-item i { font-size: 18px; width: 22px; transition: all 0.3s; }
        .nav-item:nth-child(1) i { color: #3b82f6; }
        .nav-item:nth-child(2) i { color: #f59e0b; }
        .nav-item:nth-child(3) i { color: #ec489a; }
        .nav-item:nth-child(4) i { color: #8b5cf6; }
        .nav-item:nth-child(5) i { color: #ef4444; }
        .main-content { flex: 1; margin-left: 280px; padding: 20px 24px; }
        .payment-container { max-width: 1200px; margin: 0 auto; display: flex; flex-wrap: wrap; gap: 30px; }
        .payment-column { flex: 1.5; min-width: 300px; }
        .summary-column { flex: 1; min-width: 280px; }
        .card { background: rgba(255,255,255,0.05); backdrop-filter: blur(10px); border-radius: 24px; overflow: hidden; margin-bottom: 20px; border: 1px solid rgba(255,255,255,0.1); }
        .card-header { padding: 20px 24px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .card-header h2 { font-size: 20px; font-weight: 700; color: white; display: flex; align-items: center; gap: 8px; }
        .card-header h2 i { color: #10b981; font-size: 24px; }
        .card-body { padding: 24px; }
        .profile-card { background: linear-gradient(135deg, rgba(16,185,129,0.1), rgba(59,130,246,0.1)); border-radius: 20px; padding: 20px; margin-bottom: 20px; }
        .profile-title { font-size: 12px; color: rgba(255,255,255,0.5); margin-bottom: 15px; display: flex; align-items: center; gap: 8px; }
        .profile-info { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; }
        .info-item { display: flex; align-items: center; gap: 12px; }
        .info-icon { width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .info-icon.blue { background: rgba(59,130,246,0.2); color: #3b82f6; }
        .info-icon.green { background: rgba(16,185,129,0.2); color: #10b981; }
        .info-icon.purple { background: rgba(139,92,246,0.2); color: #8b5cf6; }
        .info-icon.orange { background: rgba(245,158,11,0.2); color: #f59e0b; }
        .info-text h4 { font-size: 14px; color: white; font-weight: 600; margin-bottom: 4px; }
        .info-text p { font-size: 11px; color: rgba(255,255,255,0.5); }
        .descricao-plano { background: rgba(255,255,255,0.03); border-radius: 16px; padding: 16px; margin: 16px 0; }
        .descricao-plano p { color: rgba(255,255,255,0.7); font-size: 13px; line-height: 1.5; }
        .vantagens { margin: 16px 0; }
        .vantagens h4 { color: white; font-size: 14px; margin-bottom: 12px; display: flex; align-items: center; gap: 6px; }
        .vantagens ul { list-style: none; }
        .vantagens li { color: rgba(255,255,255,0.7); font-size: 12px; padding: 6px 0; display: flex; align-items: center; gap: 8px; }
        .vantagens li i { color: #10b981; font-size: 14px; }
        .info-plano { background: rgba(255,255,255,0.03); border-radius: 16px; padding: 16px; margin: 16px 0; display: flex; justify-content: space-between; align-items: center; }
        .info-plano .label { color: rgba(255,255,255,0.5); font-size: 12px; }
        .info-plano .value { color: white; font-weight: 700; font-size: 18px; }
        .info-plano .value.green { color: #10b981; }
        .btn-gerar { width: 100%; background: linear-gradient(135deg, #10b981, #059669); border: none; padding: 14px; border-radius: 12px; color: white; font-weight: 700; font-size: 16px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; margin-top: 16px; }
        .btn-fechar { width: 100%; background: transparent; border: 1px solid rgba(255,255,255,0.2); padding: 12px; border-radius: 12px; color: #94a3b8; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 12px; text-decoration: none; }
        .cupom-area { background: rgba(255,255,255,0.03); border-radius: 12px; padding: 12px; margin-bottom: 20px; }
        .cupom-input { display: flex; gap: 8px; margin-top: 8px; }
        .cupom-input input { flex: 1; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); border-radius: 8px; padding: 10px; color: white; font-size: 12px; }
        .cupom-input button { background: rgba(255,255,255,0.2); border: none; padding: 10px 16px; border-radius: 8px; color: white; font-weight: 600; cursor: pointer; }
        .msg-cupom { font-size: 11px; margin-top: 8px; padding: 6px; border-radius: 6px; }
        .msg-cupom.success { background: rgba(16,185,129,0.2); color: #10b981; }
        .msg-cupom.error { background: rgba(220,38,38,0.2); color: #f87171; }
        .summary-card { background: rgba(255,255,255,0.05); backdrop-filter: blur(10px); border-radius: 24px; overflow: hidden; border: 1px solid rgba(255,255,255,0.1); }
        .summary-header { background: linear-gradient(135deg, #10b981, #059669); padding: 16px 20px; }
        .summary-header h3 { color: white; font-size: 18px; font-weight: 700; }
        .summary-body { padding: 20px; }
        .summary-row { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .summary-label { color: rgba(255,255,255,0.5); font-size: 13px; }
        .summary-value { color: white; font-weight: 600; }
        .summary-total { margin-top: 16px; padding-top: 16px; border-top: 2px solid rgba(255,255,255,0.1); display: flex; justify-content: space-between; }
        .summary-total .label { font-weight: 700; color: white; }
        .summary-total .value { font-weight: 800; font-size: 20px; color: #10b981; }
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); display: none; align-items: center; justify-content: center; z-index: 9999; backdrop-filter: blur(8px); }
        .modal-overlay.show { display: flex; }
        .modal-container { animation: modalFadeIn 0.3s ease; max-width: 480px; width: 90%; }
        @keyframes modalFadeIn { from { opacity: 0; transform: scale(0.95) translateY(-20px); } to { opacity: 1; transform: scale(1) translateY(0); } }
        .modal-content { background: linear-gradient(135deg, #1e293b, #0f172a); border-radius: 24px; overflow: hidden; border: 1px solid rgba(255,255,255,0.15); }
        .modal-header { background: linear-gradient(135deg, #10b981, #059669); padding: 20px 24px; color: white; display: flex; align-items: center; justify-content: space-between; }
        .modal-header h3 { font-size: 18px; font-weight: 700; display: flex; align-items: center; gap: 8px; }
        .modal-close { background: none; border: none; color: white; font-size: 24px; cursor: pointer; }
        .modal-body { padding: 24px; text-align: center; }
        .qr-code-area { background: white; border-radius: 16px; padding: 20px; display: inline-block; margin-bottom: 20px; }
        .qr-code-area img { width: 200px; height: 200px; }
        .pix-code { background: rgba(0,0,0,0.3); border-radius: 12px; padding: 12px; margin: 16px 0; }
        .pix-code .label { font-size: 11px; color: #94a3b8; margin-bottom: 8px; }
        .pix-code .code { font-family: monospace; font-size: 10px; color: #60a5fa; word-break: break-all; }
        .btn-copiar { width: 100%; background: linear-gradient(135deg, #3b82f6, #2563eb); border: none; padding: 12px; border-radius: 12px; color: white; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 12px; }
        .btn-verificar { width: 100%; background: linear-gradient(135deg, #f59e0b, #f97316); border: none; padding: 12px; border-radius: 12px; color: white; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 12px; }
        .status-message { background: rgba(245,158,11,0.15); border-radius: 12px; padding: 12px; margin-top: 16px; display: flex; align-items: center; gap: 10px; color: #fbbf24; font-size: 13px; }
        .spinner { display: inline-block; width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top-color: white; border-radius: 50%; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .footer { text-align: center; padding: 20px; border-top: 1px solid rgba(255,255,255,0.05); color: rgba(255,255,255,0.3); font-size: 12px; margin-top: 30px; }
        .menu-toggle { display: none; position: fixed; top: 16px; left: 16px; z-index: 1001; background: rgba(16,185,129,0.2); border: 1px solid rgba(16,185,129,0.3); border-radius: 14px; padding: 10px 12px; color: white; cursor: pointer; backdrop-filter: blur(8px); font-size: 20px; }
        @media (max-width: 768px) {
            .menu-toggle { display: block; }
            .sidebar { width: 260px; height: auto; max-height: 85vh; border-radius: 24px; margin: 0; position: fixed; top: 16px; left: 16px; transform: translateX(-120%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 16px; padding-top: 65px; }
            .payment-container { flex-direction: column; }
            .profile-info { grid-template-columns: 1fr; }
        }

    </style>
</head>
<body class="<?php echo htmlspecialchars(getBodyClass($temaUsuario)); ?>">
<?php echo getFundoPersonalizadoCSS($conn, $temaUsuario); ?>
<button class="menu-toggle" id="menuToggle" onclick="toggleMenu()"><i class='bx bx-menu'></i></button>
<div class="dashboard-container">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-logo">
            <?php if (!empty($logo)): ?><img src="<?php echo htmlspecialchars($logo); ?>" alt="logo">
            <?php else: ?><div style="color:white;font-size:16px;font-weight:700;"><?php echo htmlspecialchars($nomepainel); ?></div><?php endif; ?>
        </div>
        <nav class="sidebar-nav">
            <a href="index.php" class="nav-item"><i class='bx bx-home'></i><span>Página Inicial</span></a>
            <a href="historico.php" class="nav-item"><i class='bx bx-list-ul'></i><span>Listar pagamentos</span></a>
            <a href="planos_disponiveis.php" class="nav-item"><i class='bx bx-crown'></i><span>Planos</span></a>
            <a href="perfil.php" class="nav-item"><i class='bx bx-user'></i><span>Perfil</span></a>
            <a href="../logout_usuario.php" class="nav-item"><i class='bx bx-log-out'></i><span>Sair</span></a>
        </nav>
    </aside>
    <main class="main-content">
        <div class="payment-container">
            <div class="payment-column">
                <div class="card">
                    <div class="card-header"><h2><i class='bx bx-qr'></i> Pagamento PIX</h2></div>
                    <div class="card-body">
                        <div class="profile-card">
                            <div class="profile-title"><i class='bx bx-user'></i> Informações da Conta</div>
                            <div class="profile-info">
                                <div class="info-item"><div class="info-icon blue"><i class='bx bx-user'></i></div><div class="info-text"><h4><?php echo htmlspecialchars($usuario_login); ?></h4><p>Usuário</p></div></div>
                                <div class="info-item"><div class="info-icon green"><i class='bx bx-calendar-check'></i></div><div class="info-text"><h4><?php echo date('d/m/Y H:i:s', strtotime($usuario_expira)); ?></h4><p>Expira em</p></div></div>
                                <div class="info-item"><div class="info-icon purple"><i class='bx bx-wifi'></i></div><div class="info-text"><h4><?php echo $usuario_limite; ?> Conexões</h4><p>Limite Simultâneo</p></div></div>
                                <div class="info-item"><div class="info-icon orange"><i class='bx bx-time'></i></div><div class="info-text"><h4><?php echo $dias_restantes; ?> Dias</h4><p>Dias Restantes</p></div></div>
                            </div>
                        </div>
                        <div class="descricao-plano"><p>É obrigatório possuir 2 chips ativos no aparelho para o funcionamento correto do serviço, sendo recomendado 1 chip da TIM e 1 chip da VIVO.</p></div>
                        <div class="vantagens">
                            <h4><i class='bx bx-check-circle'></i> Vantagens Incluídas</h4>
                            <ul>
                                <li><i class='bx bx-check'></i> Ativação automática após pagamento</li>
                                <li><i class='bx bx-check'></i> Acesso a todos os servidores disponíveis</li>
                                <li><i class='bx bx-check'></i> Suporte técnico especializado</li>
                                <li><i class='bx bx-check'></i> Conexão estável e segura</li>
                                <li><i class='bx bx-check'></i> Sem taxa de cancelamento</li>
                            </ul>
                        </div>
                        <div class="info-plano">
                            <div><div class="label">Plano</div><div class="value">01 - Plano Básico</div></div>
                            <div><div class="label">Valor</div><div class="value green">R$ <?php echo number_format($cupom_aplicado ? $cupom_valor_com_desconto : $valor_renovacao, 2, ',', '.'); ?></div></div>
                        </div>
                        <?php if (!$has_pending && !$valor_invalido && !$mp_nao_configurado): ?>
                        <button class="btn-gerar" onclick="gerarPix()"><i class='bx bx-qr'></i> Processar Pagamento PIX</button>
                        <?php endif; ?>
                        <a href="index.php" class="btn-fechar"><i class='bx bx-x'></i> Cancelar</a>
                    </div>
                </div>
            </div>
            <div class="summary-column">
                <div class="summary-card">
                    <div class="summary-header"><h3>Finalizar Compra</h3></div>
                    <div class="summary-body">
                        <div class="cupom-area">
                            <div style="font-size:12px;color:rgba(255,255,255,0.5);">CUPOM DE DESCONTO</div>
                            <form method="POST" class="cupom-input">
                                <input type="text" name="cupom" placeholder="Digite seu cupom" value="<?php echo htmlspecialchars($cupom_codigo); ?>">
                                <button type="submit" name="aplicar_cupom">Aplicar</button>
                            </form>
                            <?php if (!empty($msg_cupom)): ?>
                            <div class="msg-cupom <?php echo strpos($msg_cupom,'✅') !== false ? 'success' : 'error'; ?>"><?php echo $msg_cupom; ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="summary-row"><span class="summary-label">Plano:</span><span class="summary-value">01 - Plano Básico</span></div>
                        <div class="summary-row"><span class="summary-label">Valor Original:</span><span class="summary-value">R$ <?php echo number_format($valor_renovacao, 2, ',', '.'); ?></span></div>
                        <?php if ($cupom_aplicado): ?>
                        <div class="summary-row"><span class="summary-label">Desconto (<?php echo $cupom_desconto; ?>%):</span><span class="summary-value" style="color:#10b981;">- R$ <?php echo number_format($valor_renovacao - $cupom_valor_com_desconto, 2, ',', '.'); ?></span></div>
                        <?php endif; ?>
                        <div class="summary-total"><span class="label">Total a Pagar:</span><span class="value">R$ <?php echo number_format($cupom_aplicado ? $cupom_valor_com_desconto : $valor_renovacao, 2, ',', '.'); ?></span></div>
                        <div style="margin-top:20px;">
                            <h4 style="color:white;font-size:14px;margin-bottom:12px;">Método de Pagamento</h4>
                            <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid rgba(255,255,255,0.1);">
                                <div style="display:flex;align-items:center;gap:8px;color:white;"><i class='bx bx-credit-card' style="color:#009ee3;"></i> MercadoPago</div>
                                <div style="font-size:10px;background:rgba(16,185,129,0.2);color:#10b981;padding:2px 8px;border-radius:20px;">PIX Instantâneo</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="footer"><?php echo htmlspecialchars($nomepainel); ?> &copy; <?php echo date('Y'); ?> - Todos os direitos reservados</div>
    </main>
</div>

<!-- Modal QR Code -->
<div id="modalPix" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-header"><h3><i class='bx bx-check-circle'></i> Pagamento PIX Gerado!</h3><button class="modal-close" onclick="fecharModal()"><i class='bx bx-x'></i></button></div>
            <div class="modal-body">
                <div class="qr-code-area"><img id="modalQrCode" src="" alt="QR Code PIX"></div>
                <div class="pix-code"><div class="label">CÓDIGO PIX COPIA E COLA:</div><div class="code" id="modalPixCode"></div></div>
                <button class="btn-copiar" onclick="copiarPixModal()"><i class='bx bx-copy'></i> Copiar Código PIX</button>
                <div id="modalStatusArea" class="status-message"><i class='bx bx-time'></i> Aguardando Pagamento...</div>
                <button class="btn-verificar" onclick="verificarPagamentoModal()"><i class='bx bx-refresh'></i> Verificar Status</button>
            </div>
        </div>
    </div>
</div>

<script>
var verificacaoInterval = null;
var currentPaymentId    = '';
var pagamentoConfirmado = false;

function toggleMenu() {
    var sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('open');
    document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
}
document.addEventListener('click', function(e) {
    var sidebar = document.getElementById('sidebar');
    var toggle  = document.getElementById('menuToggle');
    if (window.innerWidth <= 768 && sidebar.classList.contains('open')) {
        if (!sidebar.contains(e.target) && !toggle.contains(e.target)) toggleMenu();
    }
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.getElementById('sidebar').classList.contains('open')) toggleMenu();
});

function gerarPix() {
    if (pagamentoConfirmado) return;
    var btn = document.querySelector('.btn-gerar');
    btn.innerHTML = '<div class="spinner"></div> Gerando...';
    btn.disabled = true;
    $.ajax({
        url: window.location.href, type: 'POST',
        data: { criar_pagamento: 1 }, dataType: 'json',
        success: function(data) {
            if (data.status === 'success') {
                currentPaymentId = data.payment_id;
                document.getElementById('modalQrCode').src = 'data:image/png;base64,' + data.qr_code_base64;
                document.getElementById('modalPixCode').innerText = data.qr_code;
                document.getElementById('modalPix').classList.add('show');
                iniciarVerificacaoModal();
            } else { alert('Erro: ' + data.message); }
            btn.innerHTML = '<i class="bx bx-qr"></i> Processar Pagamento PIX';
            btn.disabled = false;
        },
        error: function() {
            alert('Erro ao conectar com o servidor');
            btn.innerHTML = '<i class="bx bx-qr"></i> Processar Pagamento PIX';
            btn.disabled = false;
        }
    });
}

function iniciarVerificacaoModal() {
    if (verificacaoInterval) clearInterval(verificacaoInterval);
    verificacaoInterval = setInterval(verificarPagamentoModal, 5000);
}

function verificarPagamentoModal() {
    if (!currentPaymentId || pagamentoConfirmado) return;
    var statusDiv = document.getElementById('modalStatusArea');
    statusDiv.innerHTML = '<div class="spinner"></div> Verificando...';
    $.ajax({
        url: window.location.href, type: 'POST',
        data: { verificar_pagamento: 1, payment_id: currentPaymentId }, dataType: 'json',
        success: function(data) {
            if (data.status === 'approved') {
                pagamentoConfirmado = true;
                clearInterval(verificacaoInterval);
                statusDiv.innerHTML = '<i class="bx bx-check-circle"></i> ✅ ' + data.message + '<br>📅 Nova validade: ' + data.nova_validade;
                statusDiv.style.background = 'rgba(16,185,129,0.15)';
                statusDiv.style.color = '#10b981';
                setTimeout(function() { window.location.href = 'index.php'; }, 3000);
            } else if (data.status === 'error') {
                statusDiv.innerHTML = '<i class="bx bx-error-circle"></i> ⚠️ ' + data.message;
                statusDiv.style.background = 'rgba(220,38,38,0.15)';
                statusDiv.style.color = '#f87171';
            } else {
                statusDiv.innerHTML = '<i class="bx bx-time"></i> ⏳ Aguardando pagamento...';
            }
        },
        error: function() { statusDiv.innerHTML = '<i class="bx bx-time"></i> ⏳ Aguardando pagamento...'; }
    });
}

function copiarPixModal() {
    navigator.clipboard.writeText(document.getElementById('modalPixCode').innerText)
        .then(function() { alert('✅ Código PIX copiado!'); });
}

function fecharModal() {
    document.getElementById('modalPix').classList.remove('show');
    if (verificacaoInterval) clearInterval(verificacaoInterval);
}

<?php if ($has_pending && !$mp_nao_configurado && !$valor_invalido && !$pagamento_processado): ?>
currentPaymentId = '<?php echo addslashes($payment_id); ?>';
document.getElementById('modalQrCode').src = 'data:image/png;base64,<?php echo $_SESSION['qr_code_base64']; ?>';
document.getElementById('modalPixCode').innerText = '<?php echo addslashes($qr_code); ?>';
document.getElementById('modalPix').classList.add('show');
iniciarVerificacaoModal();
<?php endif; ?>
<?php if ($pagamento_processado): ?>
setTimeout(function() { window.location.href = 'index.php'; }, 2000);
<?php endif; ?>
</script>
</body>
</html>