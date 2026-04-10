<?php
error_reporting(0);
session_start();

date_default_timezone_set('America/Sao_Paulo');

if(!isset($_SESSION['login']) and !isset($_SESSION['senha'])){
    session_destroy();
    header('location:../index.php');
    exit();
}

include('conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// ========== LOG ==========
function logDepuracao($mensagem) {
    $logFile = __DIR__ . '/renovar_plano_log.txt';
    $data = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$data] $mensagem\n", FILE_APPEND);
}

// ========== HELPER: SALVAR NA TABELA UNIFICADA ==========
function salvarUnificado($conn, $tipo, $user_id, $login, $payment_id, $valor, $status, $revendedor_id, $plano_id = null, $limite = null, $dias = null, $descricao = null, $data_pagamento = null) {
    $tipo          = mysqli_real_escape_string($conn, $tipo);
    $user_id       = intval($user_id);
    $login         = mysqli_real_escape_string($conn, $login);
    $payment_id    = mysqli_real_escape_string($conn, $payment_id);
    $valor         = floatval($valor);
    $status        = mysqli_real_escape_string($conn, $status);
    $revendedor_id = intval($revendedor_id);
    $plano_id_sql  = $plano_id  ? intval($plano_id)  : 'NULL';
    $limite_sql    = $limite    ? intval($limite)     : 'NULL';
    $dias_sql      = $dias      ? intval($dias)       : 'NULL';
    $descricao_sql = $descricao ? "'".mysqli_real_escape_string($conn, $descricao)."'" : 'NULL';
    $data_criacao  = date('Y-m-d H:i:s');
    $data_pag_sql  = $data_pagamento ? "'".mysqli_real_escape_string($conn, $data_pagamento)."'" : 'NULL';

    $sql = "INSERT INTO pagamentos_unificado
                (tipo, user_id, login, payment_id, valor, status, data_criacao, data_pagamento, revendedor_id, plano_id, limite_creditos, duracao_dias, descricao)
            VALUES
                ('$tipo', $user_id, '$login', '$payment_id', $valor, '$status', '$data_criacao', $data_pag_sql, $revendedor_id, $plano_id_sql, $limite_sql, $dias_sql, $descricao_sql)
            ON DUPLICATE KEY UPDATE
                status         = IF('$status' = 'approved', 'approved', status),
                data_pagamento = IF('$status' = 'approved' AND data_pagamento IS NULL, $data_pag_sql, data_pagamento)";

    $ok = mysqli_query($conn, $sql);
    logDepuracao("salvarUnificado: payment_id=$payment_id status=$status ok=" . ($ok ? 'SIM' : 'NAO - '.mysqli_error($conn)));
    return $ok;
}

// ========== HELPER: ENVIAR WHATSAPP ==========
function enviarWhatsAppRenovacao($conn, $byid, $numero, $texto) {
    $byid = intval($byid);

    // Busca config da API Evolution na tabela configs (centralizada)
    $r_cfg = $conn->query("SELECT evo_apiurl, evo_token FROM configs LIMIT 1");
    $api_base = ''; $tok = '';
    if ($r_cfg && $r_cfg->num_rows > 0) {
        $cfg = $r_cfg->fetch_assoc();
        $api_base = trim($cfg['evo_apiurl'] ?? '');
        $tok      = trim($cfg['evo_token']  ?? '');
    }

    // Fallback: busca na tabela whatsapp do revendedor pai
    if (empty($api_base) || empty($tok)) {
        $r_wpp = $conn->query("SELECT apiurl, token FROM whatsapp WHERE byid='$byid' LIMIT 1");
        if ($r_wpp && $r_wpp->num_rows > 0) {
            $wpp = $r_wpp->fetch_assoc();
            $api_base = trim($wpp['apiurl'] ?? '');
            $tok      = trim($wpp['token']  ?? '');
        }
    }

    if (empty($api_base) || empty($tok)) {
        logDepuracao("WhatsApp: API nÃ£o configurada para byid=$byid");
        return false;
    }

    if (!preg_match('#^https?://#i', $api_base)) $api_base = 'http://' . $api_base;

    // Busca sessÃ£o (instÃ¢ncia) do revendedor pai
    $r_sess = $conn->query("SELECT sessao FROM whatsapp WHERE byid='$byid' LIMIT 1");
    $inst = '';
    if ($r_sess && $r_sess->num_rows > 0) {
        $inst = trim($r_sess->fetch_assoc()['sessao'] ?? '');
    }

    if (empty($inst)) {
        logDepuracao("WhatsApp: InstÃ¢ncia nÃ£o encontrada para byid=$byid");
        return false;
    }

    $num = preg_replace('/\D/', '', $numero);
    if (empty($num)) return false;
    if (strlen($num) <= 11 && substr($num, 0, 2) !== '55') $num = '55' . $num;

    $url = rtrim($api_base, '/') . '/message/sendText/' . urlencode($inst);

    $payloads = [
        json_encode(['number' => $num, 'textMessage' => ['text' => $texto]]),
        json_encode(['number' => $num, 'text' => $texto, 'options' => ['delay' => 1200]]),
        json_encode(['number' => $num . '@s.whatsapp.net', 'textMessage' => ['text' => $texto]]),
    ];

    foreach ($payloads as $payload) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['apikey: '.$tok, 'Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        $body  = curl_exec($ch);
        $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);
        curl_close($ch);
        if ($errno === 0 && $code >= 200 && $code < 300) {
            logDepuracao("WhatsApp enviado para $num via instÃ¢ncia $inst");
            return true;
        }
    }
    logDepuracao("WhatsApp FALHOU para $num. Ãšltimo HTTP=$code");
    return false;
}

// ========== HELPER: DISPARAR MENSAGEM AUTOMÃTICA POR EVENTO ==========
function dispararMensagemRenovacao($conn, $byid, $dados) {
    $funcao = 'renovacaopag'; // evento de pagamento/renovaÃ§Ã£o aprovado
    $byid   = intval($byid);

    $r = $conn->query("SELECT * FROM mensagens WHERE funcao='$funcao' AND ativo='ativada' AND byid='$byid' LIMIT 1");
    if (!$r || $r->num_rows === 0) {
        // Tenta mensagem genÃ©rica sem byid especÃ­fico
        $r = $conn->query("SELECT * FROM mensagens WHERE funcao='$funcao' AND ativo='ativada' ORDER BY id ASC LIMIT 1");
    }

    $template = "âœ… *RenovaÃ§Ã£o Aprovada!*\n\nðŸ‘¤ Revenda: {usuario}\nðŸ“… Nova validade: {validade}\n\nObrigado! ðŸ™";
    if ($r && $r->num_rows > 0) {
        $row = $r->fetch_assoc();
        $template = $row['mensagem'];
    }

    $mensagem = str_replace(
        ['{usuario}', '{login}', '{senha}', '{validade}', '{limite}', '{dominio}'],
        [
            $dados['usuario']  ?? '',
            $dados['usuario']  ?? '',
            $dados['senha']    ?? '',
            $dados['validade'] ?? '',
            $dados['limite']   ?? '',
            $dados['dominio']  ?? $_SERVER['HTTP_HOST'] ?? '',
        ],
        $template
    );

    $numero = $dados['whatsapp'] ?? '';
    if (empty($numero)) {
        logDepuracao("dispararMensagemRenovacao: nÃºmero vazio para byid=$byid");
        return false;
    }

    return enviarWhatsAppRenovacao($conn, $byid, $numero, $mensagem);
}

// Detectar AJAX
$isPostCriar     = isset($_POST['criar_pagamento']);
$isPostVerificar = isset($_POST['verificar_pagamento']);

if (!$isPostCriar && !$isPostVerificar) {
    include('header2.php');
}

// Verificar token
if (!file_exists('../admin/suspenderrev.php')) {
    error_log("suspenderrev.php não encontrado");
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

$user_id = $_SESSION['iduser'];

// ========== BUSCAR DADOS DO REVENDEDOR ==========
$sql_atrib    = "SELECT * FROM atribuidos WHERE userid = '$user_id'";
$result_atrib = mysqli_query($conn, $sql_atrib);
$atribuicao   = mysqli_fetch_assoc($result_atrib);

if (!$atribuicao) {
    echo "<script>alert('Revendedor nÃ£o encontrado!'); window.location.href='../home.php';</script>";
    exit;
}

$limite_atual = $atribuicao['limite'];
$expira_atual = $atribuicao['expira'];
$categoria_id = $atribuicao['categoriaid'];
$tipo_revenda = $atribuicao['tipo'];
$byid         = $atribuicao['byid'];
$valor_revenda = isset($atribuicao['valor']) ? floatval($atribuicao['valor']) : 0;

$sql_account    = "SELECT * FROM accounts WHERE id = '$user_id'";
$result_account = mysqli_query($conn, $sql_account);
$revendedor     = mysqli_fetch_assoc($result_account);
$usuario_login  = $revendedor['login'];
$usuario_senha  = $revendedor['senha'];
$usuario_whatsapp = $revendedor['whatsapp'] ?? '';

// Dados do PAI (quem recebe o pagamento)
$sql_pai    = "SELECT * FROM accounts WHERE id = '$byid'";
$result_pai = mysqli_query($conn, $sql_pai);
$pai        = mysqli_fetch_assoc($result_pai);

$mp_access_token  = $pai['mp_access_token'] ?? '';
$mp_active        = $pai['mp_active']        ?? 0;
$revendedor_email = $pai['contato']          ?? $pai['email'] ?? '';

// Dias restantes
$data_atual    = new DateTime();
$data_validade = new DateTime($expira_atual);
$dias_restantes = (int)$data_atual->diff($data_validade)->days;
$conta_vencida  = ($data_validade < $data_atual);
if ($conta_vencida) $dias_restantes = -abs($dias_restantes);

$valor_invalido     = ($valor_revenda <= 0);
$mp_nao_configurado = ($mp_active != 1 || empty($mp_access_token));

// ========== CUPOM ==========
$cupom_aplicado           = false;
$cupom_desconto           = 0;
$cupom_valor_com_desconto = $valor_revenda;
$cupom_codigo             = '';
$msg_cupom                = '';

if (isset($_POST['aplicar_cupom']) && !empty($_POST['cupom'])) {
    $cupom        = anti_sql($_POST['cupom']);
    $sql_cupom    = "SELECT * FROM cupons WHERE codigo = '$cupom' AND status = 'ativo'";
    $result_cupom = mysqli_query($conn, $sql_cupom);
    $cupom_data   = mysqli_fetch_assoc($result_cupom);

    if ($cupom_data) {
        if ($cupom_data['tipo'] == 'percentual') {
            $cupom_desconto           = $cupom_data['valor'];
            $cupom_valor_com_desconto = $valor_revenda * (1 - ($cupom_desconto / 100));
        } else {
            $cupom_desconto           = $cupom_data['valor'];
            $cupom_valor_com_desconto = max(0, $valor_revenda - $cupom_desconto);
        }
        $cupom_aplicado = true;
        $cupom_codigo   = $cupom;
        $msg_cupom      = "âœ… Cupom aplicado! Desconto de R$ " . number_format($valor_revenda - $cupom_valor_com_desconto, 2, ',', '.');
    } else {
        $msg_cupom = "âŒ Cupom invÃ¡lido!";
    }
}

// ========== CRIAR PAGAMENTO ==========
if ($isPostCriar) {
    header('Content-Type: application/json');

    $valor_pagar = $cupom_aplicado ? $cupom_valor_com_desconto : $valor_revenda;

    if ($valor_pagar <= 0)       { echo json_encode(['status' => 'error', 'message' => 'Valor zero nÃ£o pode gerar pagamento!']); exit(); }
    if ($mp_nao_configurado)     { echo json_encode(['status' => 'error', 'message' => 'Revendedor nÃ£o configurou o Mercado Pago!']); exit(); }

    $external_id  = "renovacao_revenda_" . $user_id . "_" . time();
    $idem_key     = uniqid() . '_' . $user_id . '_' . time();
    $payment_data = [
        'transaction_amount' => floatval($valor_pagar),
        'description'        => "RenovaÃ§Ã£o de Revenda - " . $usuario_login,
        'payment_method_id'  => 'pix',
        'payer'              => [
            'email'          => $revendedor_email,
            'first_name'     => $usuario_login,
            'identification' => ['type' => 'CPF', 'number' => '00000000000']
        ],
        'external_reference' => $external_id,
        'notification_url'   => (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/api/webhooks/mercadopago.php'
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => 'https://api.mercadopago.com/v1/payments',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payment_data),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $mp_access_token,
            'Content-Type: application/json',
            'X-Idempotency-Key: ' . $idem_key
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 30
    ]);
    $response   = curl_exec($ch);
    $http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    logDepuracao("Criar pagamento: HTTP $http_code");

    if ($curl_error) { echo json_encode(['status' => 'error', 'message' => 'Erro cURL: ' . $curl_error]); exit(); }

    if ($http_code == 200 || $http_code == 201) {
        $result = json_decode($response, true);
        if (isset($result['point_of_interaction']['transaction_data']['qr_code_base64'])) {
            $payment_id     = $result['id'];
            $qr_code_base64 = $result['point_of_interaction']['transaction_data']['qr_code_base64'];
            $qr_code        = $result['point_of_interaction']['transaction_data']['qr_code'];
            $data_criacao   = date('Y-m-d H:i:s');
            $pid            = mysqli_real_escape_string($conn, $payment_id);

            // Tabela legada
            mysqli_query($conn, "INSERT INTO pagamentos (payment_id, valor, status, data_criacao, tipo_conta, iduser, byid, external_reference, origem)
                           VALUES ('$pid', '$valor_pagar', 'pending', '$data_criacao', 'revenda', '$user_id', '$byid', '$external_id', 'renovacao')");

            // SALVAR PENDING NA UNIFICADA
            salvarUnificado(
                $conn,
                'renovacao_revenda',
                $user_id,
                $usuario_login,
                $payment_id,
                $valor_pagar,
                'pending',
                $byid,
                null,
                $limite_atual,
                30,
                'RenovaÃ§Ã£o de Revenda - ' . $usuario_login,
                null
            );

            logDepuracao("Pagamento PENDING salvo: payment_id=$payment_id user=$usuario_login valor=$valor_pagar");

            $_SESSION['payment_id']      = $payment_id;
            $_SESSION['qr_code_base64']  = $qr_code_base64;
            $_SESSION['qr_code']         = $qr_code;
            $_SESSION['cupom_renovacao'] = $cupom_codigo;
            // Salvar whatsapp na sessÃ£o para uso posterior
            $_SESSION['renovacao_wpp']   = $usuario_whatsapp;

            echo json_encode([
                'status'         => 'success',
                'qr_code_base64' => $qr_code_base64,
                'qr_code'        => $qr_code,
                'payment_id'     => $payment_id
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Erro ao gerar QR Code']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Erro na API: ' . $response]);
    }
    exit();
}

// ========== VERIFICAR PAGAMENTO ==========
if ($isPostVerificar) {
    header('Content-Type: application/json');

    $payment_id = isset($_POST['payment_id']) ? mysqli_real_escape_string($conn, $_POST['payment_id']) : '';
    logDepuracao("Verificando pagamento: $payment_id");

    if (empty($payment_id)) { echo json_encode(['status' => 'error', 'message' => 'ID nÃ£o informado!']); exit(); }

    // CHECAR BANCO ANTES DE CONSULTAR MP (evita reprocessar)
    $check_aprovado = mysqli_query($conn, "SELECT status FROM pagamentos WHERE payment_id = '$payment_id' AND status = 'approved' LIMIT 1");
    if ($check_aprovado && mysqli_num_rows($check_aprovado) > 0) {
        $sql_val  = "SELECT expira FROM atribuidos WHERE userid = '$user_id'";
        $res_val  = mysqli_query($conn, $sql_val);
        $row_val  = mysqli_fetch_assoc($res_val);
        $val_fmt  = $row_val ? date('d/m/Y H:i:s', strtotime($row_val['expira'])) : 'N/A';
        logDepuracao("Pagamento $payment_id jÃ¡ aprovado no banco. Retornando sem reprocessar.");
        echo json_encode(['status' => 'approved', 'message' => 'Pagamento jÃ¡ aprovado! Revenda renovada!', 'nova_validade' => $val_fmt]);
        exit();
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => 'https://api.mercadopago.com/v1/payments/' . $payment_id,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $mp_access_token,
            'Content-Type: application/json'
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 30
    ]);
    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code != 200) {
        echo json_encode(['status' => 'pending', 'message' => 'Aguardando pagamento...']);
        exit();
    }

    $result    = json_decode($response, true);
    $status_mp = $result['status'] ?? 'unknown';
    logDepuracao("Status MP: $status_mp");

    if ($status_mp == 'approved') {
        $data_pagamento_now = date('Y-m-d H:i:s');

        // Calcular nova validade somando 30 dias Ã  validade atual
        $expira_ref    = (!empty($expira_atual) && $expira_atual > date('Y-m-d H:i:s')) ? $expira_atual : date('Y-m-d H:i:s');
        $nova_validade = date('Y-m-d H:i:s', strtotime('+30 days', strtotime($expira_ref)));

        // Atualizar revenda
        mysqli_query($conn, "UPDATE atribuidos SET expira = '$nova_validade', suspenso = 0 WHERE userid = '$user_id'");
        logDepuracao("Validade atualizada: $expira_ref â†’ $nova_validade");

        // Atualizar tabela legada
        mysqli_query($conn, "UPDATE pagamentos SET status = 'approved', data_pagamento = '$data_pagamento_now' WHERE payment_id = '$payment_id'");
        if (mysqli_affected_rows($conn) == 0) {
            $external_id = "renovacao_revenda_" . $user_id . "_" . time();
            mysqli_query($conn, "INSERT INTO pagamentos (payment_id, valor, status, data_criacao, data_pagamento, tipo_conta, iduser, byid, external_reference, origem)
                VALUES ('$payment_id', '$valor_revenda', 'approved', '$data_pagamento_now', '$data_pagamento_now', 'revenda', '$user_id', '$byid', '$external_id', 'renovacao')");
        }

        // ATUALIZAR PARA APPROVED NA UNIFICADA
        mysqli_query($conn, "UPDATE pagamentos_unificado SET status = 'approved', data_pagamento = '$data_pagamento_now' WHERE payment_id = '$payment_id'");
        if (mysqli_affected_rows($conn) == 0) {
            salvarUnificado(
                $conn,
                'renovacao_revenda',
                $user_id,
                $usuario_login,
                $payment_id,
                $valor_revenda,
                'approved',
                $byid,
                null,
                $limite_atual,
                30,
                'RenovaÃ§Ã£o de Revenda - ' . $usuario_login,
                $data_pagamento_now
            );
        }

        // Log
        $datahoje = date('d-m-Y H:i:s');
        mysqli_query($conn, "INSERT INTO logs (revenda, validade, texto, userid)
            VALUES ('$usuario_login', '$datahoje', 'Renovou 30 dias via Mercado Pago', '$user_id')");

        // Cupom
        if (!empty($_SESSION['cupom_renovacao'])) {
            $cup = mysqli_real_escape_string($conn, $_SESSION['cupom_renovacao']);
            mysqli_query($conn, "UPDATE cupons SET usado = usado + 1 WHERE codigo = '$cup'");
            unset($_SESSION['cupom_renovacao']);
        }

        // ========== ENVIAR WHATSAPP ==========
        // Buscar nÃºmero do whatsapp do revendedor (da tabela accounts)
        $wpp_numero = $usuario_whatsapp;
        if (empty($wpp_numero) && !empty($_SESSION['renovacao_wpp'])) {
            $wpp_numero = $_SESSION['renovacao_wpp'];
        }
        if (!empty($wpp_numero)) {
            $wpp_ok = dispararMensagemRenovacao($conn, $byid, [
                'usuario'  => $usuario_login,
                'senha'    => $usuario_senha,
                'validade' => date('d/m/Y', strtotime($nova_validade)),
                'limite'   => $limite_atual,
                'whatsapp' => $wpp_numero,
                'dominio'  => $_SERVER['HTTP_HOST'] ?? '',
            ]);
            logDepuracao("WhatsApp renovaÃ§Ã£o: " . ($wpp_ok ? "ENVIADO para $wpp_numero" : "FALHOU para $wpp_numero"));
        } else {
            logDepuracao("WhatsApp renovaÃ§Ã£o: nÃºmero nÃ£o cadastrado para user_id=$user_id");
        }
        // ========== FIM WHATSAPP ==========

        unset($_SESSION['qr_code_base64'], $_SESSION['qr_code'], $_SESSION['payment_id'], $_SESSION['renovacao_wpp']);

        logDepuracao("RenovaÃ§Ã£o aprovada! Nova validade: $nova_validade");

        echo json_encode([
            'status'        => 'approved',
            'message'       => 'Pagamento aprovado! Revenda renovada por mais 30 dias!',
            'nova_validade' => date('d/m/Y H:i:s', strtotime($nova_validade))
        ]);
    } else {
        echo json_encode([
            'status'  => $status_mp,
            'message' => 'Aguardando pagamento...'
        ]);
    }
    exit();
}

function anti_sql($input) {
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function($match) { return ''; }, $input);
    $seg = trim($seg); $seg = strip_tags($seg); $seg = addslashes($seg);
    return $seg;
}

$has_pending       = isset($_SESSION['qr_code_base64']) && !empty($_SESSION['qr_code_base64']);
$payment_id_sessao = $_SESSION['payment_id'] ?? '';
$qr_code_sessao    = $_SESSION['qr_code']    ?? '';

// Buscar informaÃ§Ãµes para exibiÃ§Ã£o
$sql5     = "SELECT * FROM atribuidos WHERE userid = '{$_SESSION['iduser']}'";
$res5     = $conn->query($sql5);
$row5     = $res5->fetch_assoc();
$validade = $row5['expira'];

$slq2 = "SELECT sum(limite) AS limiteusado FROM atribuidos WHERE byid='{$_SESSION['iduser']}'";
$res2 = $conn->prepare($slq2);
$res2->execute(); $res2->bind_result($limiteusado); $res2->fetch(); $res2->close();

$slq4 = "SELECT sum(limite) AS numusuarios FROM ssh_accounts WHERE byid='{$_SESSION['iduser']}'";
$res4 = $conn->prepare($slq4);
$res4->execute(); $res4->bind_result($numusuarios); $res4->fetch(); $res4->close();

$limiteusado_total = ($limiteusado ?? 0) + ($numusuarios ?? 0);

if ($tipo_revenda == 'Credito') {
    $tipo_txt = 'Restam ' . $_SESSION['limite'] . ' CrÃ©ditos';
} else {
    $tipo_txt = 'Limite usado: ' . $limiteusado_total . ' de ' . $_SESSION['limite'];
}

$avatar_url = !empty($revendedor['profile_image'])
    ? '../uploads/profiles/' . $revendedor['profile_image']
    : 'https://ui-avatars.com/api/?name=' . urlencode($_SESSION['login']) . '&size=100&background=4158D0&color=fff&bold=true';

$valor_formatado          = number_format($cupom_aplicado ? $cupom_valor_com_desconto : $valor_revenda, 2, ',', '.');
$valor_original_formatado = number_format($valor_revenda, 2, ',', '.');
?>
    <style>
        :root {
            --primary: #4158D0; --secondary: #C850C0; --tertiary: #FFCC70;
            --success: #10b981; --danger: #dc2626; --warning: #f59e0b;
            --info: #3b82f6;    --dark: #2c3e50;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Rubik', sans-serif; min-height: 100vh; background: linear-gradient(135deg, #0f172a, #1e1b4b); }

        .app-content { margin-left: 240px !important; padding: 0 !important; }
        .content-wrapper { max-width: 780px; margin: 0 auto 0 5px !important; padding: 0 !important; }
        .content-body { padding: 0 !important; margin: 0 !important; }
        .row, .match-height, [class*="col-"] { margin: 0 !important; padding: 0 !important; }
        .content-header { display: none !important; height: 0 !important; margin: 0 !important; padding: 0 !important; }

        .info-badge { display: inline-flex !important; align-items: center !important; gap: 8px !important; background: white !important; color: var(--dark) !important; padding: 8px 16px !important; border-radius: 30px !important; font-size: 13px !important; margin-top: 5px !important; margin-bottom: 15px !important; border-left: 4px solid var(--primary) !important; box-shadow: 0 3px 10px rgba(0,0,0,0.1) !important; }
        .info-badge i { font-size: 22px; color: var(--primary); }

        .status-info { background: linear-gradient(135deg, #1e293b, #0f172a); border-radius: 14px; padding: 12px 18px; margin-bottom: 15px; border: 1px solid rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; color: white; }
        .status-item { display: flex !important; align-items: center !important; gap: 6px !important; }
        .status-item i { font-size: 20px !important; color: var(--tertiary) !important; }
        .status-item span { font-size: 12px !important; font-weight: 500 !important; }

        .modern-card { background: linear-gradient(135deg, #1e293b, #0f172a) !important; border-radius: 20px !important; border: 1px solid rgba(255,255,255,0.08) !important; overflow: hidden !important; position: relative !important; box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important; width: 100% !important; animation: fadeIn 0.5s ease !important; margin-bottom: 8px !important; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .card-bg-shapes { position: absolute; inset: 0; pointer-events: none; z-index: 0; overflow: hidden; }
        .modern-card .card-header { padding: 16px 20px 12px !important; border-bottom: 1px solid rgba(255,255,255,0.07) !important; display: flex !important; align-items: center !important; gap: 10px !important; position: relative; z-index: 1; }
        .header-icon { width: 36px; height: 36px; border-radius: 10px; background: linear-gradient(135deg, #C850C0, #4158D0); display: flex; align-items: center; justify-content: center; font-size: 18px; color: white; flex-shrink: 0; }
        .header-title { font-size: 14px; font-weight: 700; color: white; }
        .header-subtitle { font-size: 10px; color: rgba(255,255,255,0.35); }
        .limite-badge { margin-left: auto; display: inline-flex; align-items: center; gap: 5px; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; padding: 4px 8px; font-size: 10px; font-weight: 600; color: rgba(255,255,255,0.5); }
        .modern-card .card-body { padding: 18px 20px !important; position: relative; z-index: 1; }

        .btn-action { padding: 8px 16px !important; border: none !important; border-radius: 8px !important; font-weight: 700 !important; font-size: 12px !important; cursor: pointer !important; transition: all 0.2s !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; gap: 6px !important; font-family: inherit !important; box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important; }
        .btn-success { background: linear-gradient(135deg, #10b981, #059669) !important; color: white !important; }
        .btn-success:hover { transform: translateY(-2px) !important; box-shadow: 0 6px 15px rgba(16,185,129,0.5) !important; }
        .btn-danger  { background: linear-gradient(135deg, #dc2626, #b91c1c) !important; color: white !important; }
        .btn-danger:hover  { transform: translateY(-2px) !important; box-shadow: 0 6px 15px rgba(220,38,38,0.5) !important; }

        .form-control { width: 100%; padding: 8px 12px; background: rgba(255,255,255,0.06); border: 1.5px solid rgba(255,255,255,0.08); border-radius: 9px; color: white; font-size: 12px; font-family: inherit; outline: none; transition: all 0.25s; }
        .form-control:focus { border-color: rgba(65,88,208,0.6); background: rgba(255,255,255,0.09); }
        .form-control::placeholder { color: rgba(255,255,255,0.2); }

        .action-buttons { display: flex; justify-content: flex-end; gap: 8px; margin-top: 18px; flex-wrap: wrap; }

        .icon-refresh  { color: #f59e0b; } .icon-group { color: #34d399; }
        .icon-calendar { color: #fbbf24; } .icon-money  { color: #10b981; }
        .icon-time     { color: #fbbf24; } .icon-credit { color: #3b82f6; }
        .icon-cupom    { color: #a78bfa; }

        .info-note { background: rgba(59,130,246,0.1); border-left: 3px solid #3b82f6; padding: 10px; border-radius: 8px; margin-top: 10px; }
        .info-note small { color: rgba(255,255,255,0.6); font-size: 10px; }
        .info-note i { color: #3b82f6; }

        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); display: none; align-items: center; justify-content: center; z-index: 9999; backdrop-filter: blur(8px); }
        .modal-overlay.show { display: flex; }
        .modal-container { animation: modalIn 0.4s cubic-bezier(0.34,1.2,0.64,1); max-width: 500px; width: 90%; }
        @keyframes modalIn { from { opacity:0; transform: scale(0.9) translateY(-30px); } to { opacity:1; transform: scale(1) translateY(0); } }
        .modal-content { background: linear-gradient(135deg, #1e293b, #0f172a); border-radius: 24px; overflow: hidden; border: 1px solid rgba(255,255,255,0.15); box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); }
        .modal-header { color: white; padding: 20px 24px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .modal-header h5 { margin:0; display:flex; align-items:center; gap:10px; font-size:18px; font-weight:600; }
        .modal-header.success { background: linear-gradient(135deg, #10b981, #059669); }
        .modal-header.error   { background: linear-gradient(135deg, #dc2626, #b91c1c); }
        .modal-close { background:none; border:none; color:white; font-size:24px; cursor:pointer; opacity:.8; transition:opacity .2s; }
        .modal-close:hover { opacity:1; }
        .modal-body { padding: 24px; color: white; max-height: 70vh; overflow-y: auto; text-align: center; }

        .qr-code-area { background: white; border-radius: 16px; padding: 20px; display: inline-block; margin-bottom: 20px; }
        .qr-code-area img { width: 200px; height: 200px; }
        .pix-code { background: rgba(0,0,0,0.3); border-radius: 12px; padding: 12px; margin: 16px 0; text-align: left; }
        .pix-code .label { font-size: 11px; color: #94a3b8; margin-bottom: 8px; }
        .pix-code .code { font-family: monospace; font-size: 10px; color: #60a5fa; word-break: break-all; }
        .btn-copiar, .btn-verificar { width: 100%; padding: 12px; border-radius: 12px; color: white; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 12px; border: none; }
        .btn-copiar    { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .btn-verificar { background: linear-gradient(135deg, #f59e0b, #f97316); }
        .status-message { background: rgba(245,158,11,0.15); border-radius: 12px; padding: 12px; margin-top: 16px; display: flex; align-items: center; gap: 10px; color: #fbbf24; font-size: 13px; }
        .spinner { display: inline-block; width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top-color: white; border-radius: 50%; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        @media (max-width:768px) {
            .app-content { margin-left:0 !important; }
            .content-wrapper { padding:5px !important; }
            .action-buttons { flex-direction:column !important; }
            .action-buttons .btn-danger, .action-buttons .btn-success { width:100% !important; margin:0 !important; }
            .btn-action { width:100%; }
            .modal-container { width:95%; }
        }
    </style>
<div class="app-content content">
    <div class="content-overlay"></div>
    <div class="content-wrapper">

        <div class="info-badge">
            <i class='bx bx-refresh icon-refresh'></i>
            <span>Renovar Plano</span>
        </div>

        <div class="status-info">
            <div class="status-item"><i class='bx bx-info-circle'></i><span><?php echo $tipo_txt; ?></span></div>
            <div class="status-item"><i class='bx bx-time icon-time'></i><span>Validade: <?php echo date('d/m/Y', strtotime($validade)); ?></span></div>
        </div>

        <div class="modern-card">
            <div class="card-bg-shapes">
                <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                    <circle cx="95%" cy="6%"  r="60" fill="rgba(200,80,192,0.07)"/>
                    <circle cx="88%" cy="92%" r="45" fill="rgba(65,88,208,0.07)"/>
                    <polygon points="85%,30% 98%,52% 72%,52%" fill="rgba(255,204,112,0.05)"/>
                    <polygon points="3%,8%  10%,22% -4%,22%"  fill="rgba(16,185,129,0.06)"/>
                    <rect x="78%" y="55%" width="38" height="38" rx="8" fill="rgba(59,130,246,0.05)" transform="rotate(25,97,74)"/>
                </svg>
            </div>

            <div class="card-header">
                <div class="header-icon"><i class='bx bx-refresh icon-refresh'></i></div>
                <div>
                    <div class="header-title">Renovar Plano</div>
                    <div class="header-subtitle">Renove seu plano e continue usando nossos serviÃ§os</div>
                </div>
                <div class="limite-badge">
                    <i class='bx bx-bar-chart-alt' style="color:#10b981;"></i>
                    <?php echo $tipo_txt; ?>
                </div>
            </div>

            <div class="card-body">
              <!-- Perfil -->
<div style="display:flex;align-items:center;gap:16px;background:rgba(255,255,255,0.04);padding:16px 18px;border-radius:16px;margin-bottom:20px;border:1px solid rgba(255,255,255,0.06);">
    <div style="position:relative;flex-shrink:0;">
        <img src="<?php echo htmlspecialchars($avatar_url); ?>" style="width:72px;height:72px;border-radius:16px;object-fit:cover;border:3px solid rgba(255,255,255,0.12);display:block;">
    </div>
    <div style="flex:1;min-width:0;">
        <div style="font-size:10px;color:rgba(255,255,255,0.35);text-transform:uppercase;letter-spacing:1px;font-weight:600;margin-bottom:2px;">Revendedor</div>
        <h4 style="color:white;margin:0 0 4px 0;font-size:17px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo htmlspecialchars($_SESSION['login']); ?></h4>
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <span style="font-size:11px;color:rgba(255,255,255,0.4);display:flex;align-items:center;gap:4px;">
                <i class='bx bx-id-card' style="font-size:14px;color:#818cf8;"></i> ID: <?php echo $user_id; ?>
            </span>
            <span style="font-size:11px;color:rgba(255,255,255,0.4);display:flex;align-items:center;gap:4px;">
                <i class='bx bx-layer' style="font-size:14px;color:#34d399;"></i> Limite: <?php echo number_format($limite_atual, 0, ',', '.'); ?>
            </span>
            <?php if (!empty($usuario_whatsapp)): ?>
            <span style="font-size:11px;color:rgba(255,255,255,0.4);display:flex;align-items:center;gap:4px;">
                <i class='bx bxl-whatsapp' style="font-size:14px;color:#25D366;"></i> <?php echo htmlspecialchars($usuario_whatsapp); ?>
            </span>
            <?php endif; ?>
        </div>
    </div>
</div>

                <!-- Status da conta -->
                <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:10px;background:rgba(255,255,255,0.03);padding:15px;border-radius:12px;margin-bottom:20px;color:white;font-size:13px;">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <span style="width:10px;height:10px;border-radius:50%;background:<?php echo $conta_vencida ? '#dc2626' : '#10b981'; ?>;"></span>
                        <span style="color:<?php echo $conta_vencida ? '#dc2626' : '#10b981'; ?>;"><?php echo $conta_vencida ? 'Conta Vencida' : 'Conta Ativa'; ?></span>
                    </div>
                    <div><i class='bx bx-calendar icon-calendar'></i> VÃ¡lido atÃ©: <?php echo date('d/m/Y', strtotime($validade)); ?></div>
                    <div><i class='bx bx-time icon-time'></i> <?php echo $conta_vencida ? 'Vencido hÃ¡ ' . abs($dias_restantes) . ' dias' : $dias_restantes . ' dias restantes'; ?></div>
                </div>

                <!-- InformaÃ§Ãµes do plano -->
                <div style="background:rgba(255,255,255,0.03);border-radius:12px;padding:15px;margin-bottom:20px;color:white;font-size:13px;">
                    <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid rgba(255,255,255,0.05);">
                        <div><i class='bx bx-group icon-group'></i> Seu Limite</div>
                        <div><strong><?php echo number_format($limite_atual, 0, ',', '.'); ?> crÃ©ditos</strong></div>
                    </div>
                    <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid rgba(255,255,255,0.05);">
                        <div><i class='bx bx-dollar icon-money'></i> Valor da RenovaÃ§Ã£o</div>
                        <div>
                            <?php if ($cupom_aplicado): ?>
                            <span style="text-decoration:line-through;color:rgba(255,255,255,0.4);font-size:14px;">R$ <?php echo $valor_original_formatado; ?></span>
                            <strong style="color:#f59e0b;font-size:20px;margin-left:8px;">R$ <?php echo $valor_formatado; ?></strong>
                            <?php else: ?>
                            <strong style="color:#f59e0b;font-size:20px;">R$ <?php echo $valor_formatado; ?></strong>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="display:flex;justify-content:space-between;padding:10px 0;">
                        <div><i class='bx bx-calendar-week icon-calendar'></i> PerÃ­odo</div>
                        <div><strong>30 dias</strong></div>
                    </div>
                </div>

                <?php if ($valor_revenda <= 0): ?>
                <div style="background:rgba(245,158,11,0.2);border:1px solid #f59e0b;border-radius:12px;padding:15px;text-align:center;margin-bottom:20px;color:white;">
                    <i class='bx bx-error-circle' style="font-size:24px;color:#f59e0b;"></i>
                    <p style="margin-top:10px;"><strong>Valor do revendedor nÃ£o configurado!</strong></p>
                    <p style="font-size:12px;">Entre em contato com o administrador para definir o valor de renovaÃ§Ã£o.</p>
                </div>
                <?php endif; ?>

                <form method="POST" id="formPagamento">
                    <!-- Cupom -->
                    <div style="margin-bottom:15px;">
                        <label style="font-size:9px;font-weight:700;color:rgba(255,255,255,0.4);text-transform:uppercase;display:flex;align-items:center;gap:4px;margin-bottom:5px;">
                            <i class='bx bx-tag icon-cupom'></i> Cupom de Desconto (opcional)
                        </label>
                        <div style="display:flex;gap:8px;">
                            <input type="text" name="cupom" class="form-control" placeholder="Digite seu cupom aqui" value="<?php echo htmlspecialchars($cupom_codigo); ?>">
                            <button type="submit" name="aplicar_cupom" class="btn-action btn-success" style="margin:0;white-space:nowrap;">Aplicar</button>
                        </div>
                        <?php if (!empty($msg_cupom)): ?>
                        <div style="font-size:11px;margin-top:8px;padding:6px;border-radius:6px;background:<?php echo strpos($msg_cupom,'âœ…') !== false ? 'rgba(16,185,129,0.2)' : 'rgba(220,38,38,0.2)'; ?>;color:<?php echo strpos($msg_cupom,'âœ…') !== false ? '#10b981' : '#f87171'; ?>;">
                            <?php echo $msg_cupom; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="info-note" style="margin-bottom:20px;">
                        <i class='bx bx-info-circle'></i>
                        <small>ApÃ³s o pagamento, seu plano serÃ¡ renovado por mais 30 dias automaticamente.</small>
                        <?php if (!empty($usuario_whatsapp)): ?>
                        <br><small><i class='bx bxl-whatsapp' style="color:#25D366;"></i> ConfirmaÃ§Ã£o serÃ¡ enviada para <?php echo htmlspecialchars($usuario_whatsapp); ?></small>
                        <?php endif; ?>
                    </div>

                    <div class="action-buttons">
                        <a href="../home.php" class="btn-action btn-danger"><i class='bx bx-x'></i> Cancelar</a>
                        <?php if (!$valor_invalido && !$mp_nao_configurado && $valor_revenda > 0): ?>
                        <button type="button" class="btn-action btn-success" onclick="gerarPix()">
                            <i class='bx bx-credit-card icon-credit'></i> Pagar R$ <?php echo $valor_formatado; ?> com PIX
                        </button>
                        <?php else: ?>
                        <button type="button" class="btn-action btn-success" disabled style="opacity:0.5;cursor:not-allowed;">
                            <i class='bx bx-credit-card icon-credit'></i> <?php echo $mp_nao_configurado ? 'Revendedor sem Mercado Pago' : 'Valor nÃ£o configurado'; ?>
                        </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal QR Code PIX -->
<div id="modalPix" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-content">
            <div class="modal-header success">
                <h5><i class='bx bx-check-circle'></i> Pagamento PIX Gerado!</h5>
                <button class="modal-close" onclick="fecharModal()"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body">
                <div class="qr-code-area"><img id="modalQrCode" src="" alt="QR Code PIX"></div>
                <div class="pix-code">
                    <div class="label">CÃ“DIGO PIX COPIA E COLA:</div>
                    <div class="code" id="modalPixCode"></div>
                </div>
                <button class="btn-copiar" onclick="copiarPixModal()"><i class='bx bx-copy'></i> Copiar CÃ³digo PIX</button>
                <div id="modalStatusArea" class="status-message"><i class='bx bx-time'></i> Aguardando Pagamento...</div>
                <button class="btn-verificar" onclick="verificarPagamentoModal()"><i class='bx bx-refresh'></i> Verificar Status</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
let verificacaoInterval = null;
let currentPaymentId    = '';
let pagamentoConfirmado = false;

function gerarPix() {
    if (pagamentoConfirmado) return;
    const btn = document.querySelector('.btn-success:not([disabled])');
    if (!btn) return;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<div class="spinner"></div> Gerando...';
    btn.disabled = true;
    $.ajax({
        url: window.location.href, type: 'POST', data: { criar_pagamento: 1 }, dataType: 'json',
        success: function(data) {
            if (data.status === 'success') {
                currentPaymentId = data.payment_id;
                document.getElementById('modalQrCode').src = 'data:image/png;base64,' + data.qr_code_base64;
                document.getElementById('modalPixCode').innerText = data.qr_code;
                document.getElementById('modalPix').classList.add('show');
                iniciarVerificacaoModal();
            } else { alert('Erro: ' + data.message); }
            btn.innerHTML = originalText; btn.disabled = false;
        },
        error: function(xhr, status, error) { alert('Erro ao conectar: ' + error); btn.innerHTML = originalText; btn.disabled = false; }
    });
}

function iniciarVerificacaoModal() {
    if (verificacaoInterval) clearInterval(verificacaoInterval);
    verificacaoInterval = setInterval(verificarPagamentoModal, 5000);
}

function verificarPagamentoModal() {
    if (!currentPaymentId || pagamentoConfirmado) return;
    const statusDiv = document.getElementById('modalStatusArea');
    statusDiv.innerHTML = '<div class="spinner"></div> Verificando pagamento...';
    $.ajax({
        url: window.location.href, type: 'POST',
        data: { verificar_pagamento: 1, payment_id: currentPaymentId }, dataType: 'json',
        success: function(data) {
            if (data.status === 'approved') {
                pagamentoConfirmado = true;
                if (verificacaoInterval) clearInterval(verificacaoInterval);
                statusDiv.innerHTML = '<i class="bx bx-check-circle"></i> âœ… ' + data.message + '<br>ðŸ“… Nova validade: ' + data.nova_validade;
                statusDiv.style.background = 'rgba(16,185,129,0.15)'; statusDiv.style.color = '#10b981';
                setTimeout(function() { window.location.href = '../home.php'; }, 3000);
            } else if (data.status === 'error') {
                statusDiv.innerHTML = '<i class="bx bx-error-circle"></i> âš ï¸ ' + data.message;
                statusDiv.style.background = 'rgba(220,38,38,0.15)'; statusDiv.style.color = '#f87171';
            } else {
                statusDiv.innerHTML = '<i class="bx bx-time"></i> â³ ' + data.message;
            }
        },
        error: function() { statusDiv.innerHTML = '<i class="bx bx-time"></i> â³ Aguardando pagamento...'; }
    });
}

function copiarPixModal() {
    const texto = document.getElementById('modalPixCode').innerText;
    navigator.clipboard.writeText(texto).then(function() { alert('âœ… CÃ³digo PIX copiado!'); });
}

function fecharModal() {
    document.getElementById('modalPix').classList.remove('show');
    if (verificacaoInterval) clearInterval(verificacaoInterval);
}

<?php if ($has_pending && !$mp_nao_configurado && !$valor_invalido): ?>
currentPaymentId = '<?php echo addslashes($payment_id_sessao); ?>';
document.getElementById('modalQrCode').src = 'data:image/png;base64,<?php echo $_SESSION['qr_code_base64']; ?>';
document.getElementById('modalPixCode').innerText = '<?php echo addslashes($qr_code_sessao); ?>';
document.getElementById('modalPix').classList.add('show');
iniciarVerificacaoModal();
<?php endif; ?>
</script>
</body>
</html>
