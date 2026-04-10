<?php
error_reporting(0);
ini_set('display_errors', 0);
session_start();

// ========== LOG DE DEPURAÇÃO ==========
function logDepuracao($mensagem) {
    $logFile = __DIR__ . '/pagamento_revenda_log.txt';
    $data = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$data] $mensagem\n", FILE_APPEND);
}

logDepuracao("=== INICIANDO PAGAMENTO_REVENDA ===");
logDepuracao("SESSION login: " . (isset($_SESSION['login']) ? $_SESSION['login'] : 'N/A'));
logDepuracao("SESSION iduser: " . (isset($_SESSION['iduser']) ? $_SESSION['iduser'] : 'N/A'));

if(!isset($_SESSION['login']) and !isset($_SESSION['senha'])){
    session_destroy();
    header('location:../index.php');
    exit();
}

include('../AegisCore/conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Erro de conexão com o banco de dados!");
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
function enviarWhatsAppPagamento($conn, $byid, $numero, $texto) {
    $byid = intval($byid);

    // Busca config da API Evolution centralizada na tabela configs
    $api_base = ''; $tok = '';
    $r_cfg = $conn->query("SELECT evo_apiurl, evo_token FROM configs LIMIT 1");
    if ($r_cfg && $r_cfg->num_rows > 0) {
        $cfg = $r_cfg->fetch_assoc();
        $api_base = trim($cfg['evo_apiurl'] ?? '');
        $tok      = trim($cfg['evo_token']  ?? '');
    }

    // Fallback: busca na tabela whatsapp do byid
    if (empty($api_base) || empty($tok)) {
        $r_wpp = $conn->query("SELECT apiurl, token FROM whatsapp WHERE byid='$byid' LIMIT 1");
        if ($r_wpp && $r_wpp->num_rows > 0) {
            $wpp = $r_wpp->fetch_assoc();
            $api_base = trim($wpp['apiurl'] ?? '');
            $tok      = trim($wpp['token']  ?? '');
        }
    }

    if (empty($api_base) || empty($tok)) {
        logDepuracao("WhatsApp: API não configurada para byid=$byid");
        return false;
    }
    if (!preg_match('#^https?://#i', $api_base)) $api_base = 'http://' . $api_base;

    // Busca instância do byid
    $r_sess = $conn->query("SELECT sessao FROM whatsapp WHERE byid='$byid' LIMIT 1");
    $inst = '';
    if ($r_sess && $r_sess->num_rows > 0) {
        $inst = trim($r_sess->fetch_assoc()['sessao'] ?? '');
    }

    if (empty($inst)) {
        logDepuracao("WhatsApp: Instância não encontrada para byid=$byid");
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
            logDepuracao("WhatsApp enviado para $num via $inst");
            return true;
        }
    }
    logDepuracao("WhatsApp FALHOU para $num. Último HTTP=$code");
    return false;
}

// ========== HELPER: DISPARAR MENSAGEM POR EVENTO ==========
function dispararMensagemPlano($conn, $byid, $funcao, $dados) {
    $byid = intval($byid);

    $funcao_esc = mysqli_real_escape_string($conn, $funcao);
    $r = $conn->query("SELECT * FROM mensagens WHERE funcao='$funcao_esc' AND ativo='ativada' AND byid='$byid' LIMIT 1");
    if (!$r || $r->num_rows === 0) {
        $r = $conn->query("SELECT * FROM mensagens WHERE funcao='$funcao_esc' AND ativo='ativada' ORDER BY id ASC LIMIT 1");
    }

    // Templates padrão por evento
    $templates_padrao = [
        'planoaprovado'  => "✅ *Plano Aprovado!*\n\n👤 Revenda: {usuario}\n📅 Nova validade: {validade}\n👥 Limite: {limite}\n\nBem-vindo! 🚀",
        'renovacaopag'   => "✅ *Renovação Aprovada!*\n\n👤 Revenda: {usuario}\n📅 Nova validade: {validade}\n\nObrigado! 🙏",
    ];

    $template = $templates_padrao[$funcao] ?? "✅ *Pagamento Aprovado!*\n\n👤 {usuario}\n📅 {validade}";
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
        logDepuracao("dispararMensagemPlano ($funcao): número vazio para byid=$byid");
        return false;
    }

    return enviarWhatsAppPagamento($conn, $byid, $numero, $mensagem);
}

// Detectar requisições AJAX
$isPostCriar     = isset($_POST['criar_pagamento']);
$isPostVerificar = isset($_POST['verificar_pagamento']);

if (!$isPostCriar && !$isPostVerificar) {
    include('header2.php');
}

$comprador_id    = $_SESSION['iduser'];
$comprador_login = $_SESSION['login'];

// ========== BUSCAR DADOS DO COMPRADOR (whatsapp) ==========
$r_comprador = $conn->query("SELECT whatsapp FROM accounts WHERE id='$comprador_id' LIMIT 1");
$comprador_wpp = '';
if ($r_comprador && $r_comprador->num_rows > 0) {
    $comprador_wpp = $r_comprador->fetch_assoc()['whatsapp'] ?? '';
}

// ========== BUSCAR DADOS DO PLANO ==========
// BUG FIX: garantir que plano_id é sempre buscado corretamente inclusive em AJAX POST
$plano_id = 0;
if (isset($_GET['plano_id']) && intval($_GET['plano_id']) > 0) {
    $plano_id = intval($_GET['plano_id']);
} elseif (isset($_SESSION['plano_compra']['id']) && intval($_SESSION['plano_compra']['id']) > 0) {
    $plano_id = intval($_SESSION['plano_compra']['id']);
}

if ($plano_id <= 0 && !$isPostCriar && !$isPostVerificar) {
    echo "<script>alert('Plano não selecionado!'); window.location.href='planos_revenda.php';</script>";
    exit;
}

// Inicializar variáveis do plano com valores padrão (evita erros se plano não carregado)
$plano        = null;
$valor_plano  = 0;
$limite_plano = 0;
$duracao_dias = 30;
$vendedor_id  = 0;
$mp_access_token = '';
$mp_active    = 0;
$vendedor_email  = '';
$vendedor_nome   = 'Revendedor';
$limite_vendedor_total = 0;
$tem_limite_suficiente = false;

if ($plano_id > 0) {
    $sql_plano    = "SELECT * FROM planos_pagamento WHERE id = '$plano_id' AND status = 1 AND tipo = 'revenda'";
    $result_plano = mysqli_query($conn, $sql_plano);
    $plano        = mysqli_fetch_assoc($result_plano);

    if ($plano) {
        $valor_plano  = floatval($plano['valor']);
        $limite_plano = intval($plano['limite']);
        $duracao_dias = intval($plano['duracao_dias']);
        $vendedor_id  = $plano['byid'];

        $sql_vendedor    = "SELECT * FROM accounts WHERE id = '$vendedor_id'";
        $result_vendedor = mysqli_query($conn, $sql_vendedor);
        $vendedor        = mysqli_fetch_assoc($result_vendedor);

        if ($vendedor) {
            $mp_access_token = $vendedor['mp_access_token'] ?? '';
            $mp_active       = $vendedor['mp_active']       ?? 0;
            $vendedor_email  = $vendedor['contato']         ?? ($vendedor['email'] ?? '');
            $vendedor_nome   = $vendedor['nome']            ?? ($vendedor['login'] ?? 'Revendedor');
        }

        $sql_limite_vendedor    = "SELECT limite FROM atribuidos WHERE userid = '$vendedor_id'";
        $result_limite_vendedor = mysqli_query($conn, $sql_limite_vendedor);
        $vendedor_limite        = mysqli_fetch_assoc($result_limite_vendedor);
        $limite_vendedor_total  = isset($vendedor_limite['limite']) ? intval($vendedor_limite['limite']) : 0;
        $tem_limite_suficiente  = ($limite_vendedor_total >= $limite_plano);

        logDepuracao("Plano carregado: ID=$plano_id, valor=$valor_plano, limite=$limite_plano, vendedor=$vendedor_id");
        logDepuracao("Vendedor: limite_total=$limite_vendedor_total, tem_suficiente=" . ($tem_limite_suficiente ? 'SIM' : 'NAO'));
    } else {
        logDepuracao("ERRO: Plano ID=$plano_id não encontrado!");
        if (!$isPostCriar && !$isPostVerificar) {
            echo "<script>alert('Plano não encontrado ou indisponível!'); window.location.href='planos_revenda.php';</script>";
            exit;
        }
    }
} elseif ($isPostCriar || $isPostVerificar) {
    // Tentar recuperar dados do plano da sessão para POST AJAX
    if (isset($_SESSION['plano_compra'])) {
        $pc = $_SESSION['plano_compra'];
        $plano_id     = intval($pc['id']           ?? 0);
        $valor_plano  = floatval($pc['valor']       ?? 0);
        $limite_plano = intval($pc['limite']        ?? 0);
        $duracao_dias = intval($pc['duracao_dias']  ?? 30);
        $vendedor_id  = intval($pc['vendedor_id']   ?? 0);

        if ($vendedor_id > 0) {
            $sql_vendedor    = "SELECT * FROM accounts WHERE id = '$vendedor_id'";
            $result_vendedor = mysqli_query($conn, $sql_vendedor);
            $vendedor        = mysqli_fetch_assoc($result_vendedor);
            if ($vendedor) {
                $mp_access_token = $vendedor['mp_access_token'] ?? '';
                $mp_active       = $vendedor['mp_active']       ?? 0;
                $vendedor_email  = $vendedor['contato']         ?? ($vendedor['email'] ?? '');
                $vendedor_nome   = $vendedor['nome']            ?? ($vendedor['login'] ?? 'Revendedor');
            }
            $sql_lv = "SELECT limite FROM atribuidos WHERE userid = '$vendedor_id'";
            $r_lv   = mysqli_query($conn, $sql_lv);
            $vl     = mysqli_fetch_assoc($r_lv);
            $limite_vendedor_total = intval($vl['limite'] ?? 0);
            $tem_limite_suficiente = ($limite_vendedor_total >= $limite_plano);
        }
        // Reconstruir plano array para compatibilidade
        $plano = ['id' => $plano_id, 'nome' => $pc['nome'] ?? '', 'valor' => $valor_plano, 'limite' => $limite_plano, 'duracao_dias' => $duracao_dias, 'byid' => $vendedor_id];
        logDepuracao("Plano recuperado da sessão: ID=$plano_id");
    } else {
        logDepuracao("ERRO: plano_id=0 e sem sessão plano_compra no POST AJAX!");
        if ($isPostCriar) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Sessão expirada! Recarregue a página e tente novamente.']);
            exit();
        }
    }
}

$pagamento_processado = false;

// ========== FUNÇÕES AUXILIARES ==========
function buscarPaiOriginal($conn, $comprador_id) {
    $sql    = "SELECT byid FROM accounts WHERE id = '$comprador_id'";
    $result = mysqli_query($conn, $sql);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        return intval($row['byid']);
    }
    return 0;
}

function buscarLimiteAtualComprador($conn, $comprador_id) {
    $sql    = "SELECT limite, byid, valor, id_plano, expira FROM atribuidos WHERE userid = '$comprador_id'";
    $result = mysqli_query($conn, $sql);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        return array(
            'limite'   => intval($row['limite']),
            'byid'     => intval($row['byid']),
            'valor'    => floatval($row['valor']    ?? 0),
            'id_plano' => intval($row['id_plano']   ?? 0),
            'expira'   => $row['expira']
        );
    }
    return array('limite' => 0, 'byid' => 0, 'valor' => 0, 'id_plano' => 0, 'expira' => '');
}

// ========== CONFIRMAÇÃO VIA GET ==========
if (isset($_GET['status']) && $_GET['status'] == 'success' && isset($_GET['payment_id']) && !isset($_SESSION['pagamento_processado_' . $_GET['payment_id']])) {
    $payment_id = $_GET['payment_id'];
    logDepuracao("Pagamento confirmado via GET: $payment_id");

    $_SESSION['pagamento_processado_' . $payment_id] = true;

    $check_sql    = "SELECT * FROM pagamentos_revenda WHERE payment_id = '$payment_id' AND user_id = '$comprador_id'";
    $check_result = mysqli_query($conn, $check_sql);

    if (mysqli_num_rows($check_result) == 0) {
        $data_pagamento = date('Y-m-d H:i:s');
        $sql_insert = "INSERT INTO pagamentos_revenda (user_id, login, payment_id, valor, status, data_pagamento, revendedor_id, plano_id, limite_creditos, duracao_dias)
                       VALUES ('$comprador_id', '$comprador_login', '$payment_id', '$valor_plano', 'approved', '$data_pagamento', '$vendedor_id', '$plano_id', '$limite_plano', '$duracao_dias')";

        if (mysqli_query($conn, $sql_insert)) {
            logDepuracao("Pagamento registrado na tabela pagamentos_revenda");

            salvarUnificado(
                $conn, 'compra_plano_revenda', $comprador_id, $comprador_login,
                $payment_id, $valor_plano, 'approved', $vendedor_id,
                $plano_id, $limite_plano, $duracao_dias, $plano['nome'] ?? '', $data_pagamento
            );

            $pai_original    = buscarPaiOriginal($conn, $comprador_id);
            $dados_comprador = buscarLimiteAtualComprador($conn, $comprador_id);
            $limite_antigo   = $dados_comprador['limite'];
            $valor_antigo    = $dados_comprador['valor'];
            $plano_anterior_id = $dados_comprador['id_plano'];
            $validade_atual  = $dados_comprador['expira'];

            if (empty($validade_atual) || $validade_atual < date('Y-m-d H:i:s')) {
                $validade_atual = date('Y-m-d H:i:s');
            }
            $nova_validade = date('Y-m-d H:i:s', strtotime("+$duracao_dias days", strtotime($validade_atual)));

            $sql_check_atrib    = "SELECT * FROM atribuidos WHERE userid = '$comprador_id'";
            $result_check_atrib = mysqli_query($conn, $sql_check_atrib);

            if (mysqli_num_rows($result_check_atrib) > 0) {
                $sql_update = "UPDATE atribuidos SET limite='$limite_plano', expira='$nova_validade', valor='$valor_plano', id_plano='$plano_id', byid='$pai_original', suspenso=0 WHERE userid='$comprador_id'";
                mysqli_query($conn, $sql_update);
            } else {
                $sql_cat = "SELECT categoriaid FROM atribuidos WHERE userid = '$vendedor_id'";
                $result_cat = mysqli_query($conn, $sql_cat);
                $cat = mysqli_fetch_assoc($result_cat);
                $categoriaid = isset($cat['categoriaid']) ? $cat['categoriaid'] : 1;
                mysqli_query($conn, "INSERT INTO atribuidos (userid, byid, limite, categoriaid, tipo, expira, valor, id_plano) VALUES ('$comprador_id', '$pai_original', '$limite_plano', '$categoriaid', 'Validade', '$nova_validade', '$valor_plano', '$plano_id')");
            }

            mysqli_query($conn, "INSERT INTO historico_planos_revenda (revenda_id, plano_anterior_id, plano_novo_id, limite_anterior, limite_novo, valor_anterior, valor_novo, data_alteracao, tipo_alteracao) VALUES ('$comprador_id', '$plano_anterior_id', '$plano_id', '$limite_antigo', '$limite_plano', '$valor_antigo', '$valor_plano', NOW(), 'compra_substituicao')");

            $datahoje = date('d-m-Y H:i:s');
            mysqli_query($conn, "INSERT INTO logs (revenda, validade, texto, userid) VALUES ('{$comprador_login}', '$datahoje', 'Comprou plano: ".($plano['nome']??'')." - {$limite_plano} créditos', '$comprador_id')");
            mysqli_query($conn, "INSERT INTO logs (revenda, validade, texto, userid) VALUES ('$vendedor_nome', '$datahoje', 'Vendeu plano: ".($plano['nome']??'')." para {$comprador_login}', '$vendedor_id')");

            $pagamento_processado = true;
            unset($_SESSION['qr_code_base64'], $_SESSION['qr_code'], $_SESSION['payment_id'], $_SESSION['plano_compra']);

            $sucesso          = true;
            $mensagem_sucesso = "✅ Pagamento confirmado! Plano ativado!\n\n📊 NOVO PLANO:\n🔹 Limite: " . number_format($limite_plano, 0, ',', '.') . " créditos\n🔹 Valor: R$ " . number_format($valor_plano, 2, ',', '.') . "\n🔹 Validade: " . date('d/m/Y', strtotime($nova_validade));
        } else {
            logDepuracao("ERRO ao inserir pagamento: " . mysqli_error($conn));
        }
    } else {
        $row = mysqli_fetch_assoc($check_result);
        if ($row['status'] == 'approved') {
            $sucesso          = true;
            $mensagem_sucesso = "Pagamento já processado anteriormente! Plano ativado!";
            $pagamento_processado = true;
        }
    }
}

// ========== CUPOM ==========
$cupom_aplicado           = false;
$cupom_desconto           = 0;
$cupom_valor_com_desconto = $valor_plano;
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
            $cupom_valor_com_desconto = $valor_plano * (1 - ($cupom_desconto / 100));
        } else {
            $cupom_desconto           = $cupom_data['valor'];
            $cupom_valor_com_desconto = max(0, $valor_plano - $cupom_desconto);
        }
        $cupom_aplicado              = true;
        $cupom_codigo                = $cupom;
        $msg_cupom                   = "✅ Cupom aplicado! Desconto de R$ " . number_format($valor_plano - $cupom_valor_com_desconto, 2, ',', '.');
        $_SESSION['cupom_aplicado']  = $cupom_codigo;
        $_SESSION['cupom_desconto']  = $cupom_desconto;
        $_SESSION['cupom_valor_final'] = $cupom_valor_com_desconto;
    } else {
        $msg_cupom = "❌ Cupom inválido!";
    }
} elseif (isset($_SESSION['cupom_aplicado'])) {
    $cupom_aplicado           = true;
    $cupom_desconto           = $_SESSION['cupom_desconto'];
    $cupom_valor_com_desconto = $_SESSION['cupom_valor_final'];
    $cupom_codigo             = $_SESSION['cupom_aplicado'];
    $msg_cupom                = "✅ Cupom aplicado! {$cupom_desconto}% de desconto.";
}

// ========== CRIAR PAGAMENTO ==========
if ($isPostCriar) {
    header('Content-Type: application/json');
    logDepuracao("=== PROCESSANDO CRIAÇÃO DE PAGAMENTO ===");
    logDepuracao("plano_id=$plano_id, valor_plano=$valor_plano, vendedor_id=$vendedor_id");

    if ($plano_id <= 0 || !$plano) { echo json_encode(array('status' => 'error', 'message' => 'Sessão do plano expirada! Recarregue a página.')); exit(); }

    $valor_pagar = $cupom_aplicado ? $cupom_valor_com_desconto : $valor_plano;

    if ($valor_pagar <= 0)       { echo json_encode(array('status' => 'error', 'message' => 'Valor zero não pode gerar pagamento!')); exit(); }
    if (empty($mp_access_token)) { echo json_encode(array('status' => 'error', 'message' => 'Vendedor não configurou o Mercado Pago!')); exit(); }
    if (!$tem_limite_suficiente) { echo json_encode(array('status' => 'error', 'message' => 'Vendedor não tem limite suficiente para vender!')); exit(); }

    $idempotency_key = uniqid() . '_' . $comprador_id . '_' . time();
    $external_id     = "compra_revenda_" . $comprador_id . "_" . $plano_id . "_" . time();

    $payment_data = array(
        'transaction_amount' => floatval($valor_pagar),
        'description'        => "Compra de Plano de Revenda - " . $plano['nome'],
        'payment_method_id'  => 'pix',
        'payer'              => array(
            'email'          => $vendedor_email,
            'first_name'     => $comprador_login,
            'identification' => array('type' => 'CPF', 'number' => '00000000000')
        ),
        'external_reference' => $external_id,
        'notification_url'   => (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/api/webhooks/mercadopago.php'
    );

    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL            => 'https://api.mercadopago.com/v1/payments',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payment_data),
        CURLOPT_HTTPHEADER     => array(
            'Authorization: Bearer ' . $mp_access_token,
            'Content-Type: application/json',
            'X-Idempotency-Key: ' . $idempotency_key
        ),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 30
    ));
    $response   = curl_exec($ch);
    $http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    logDepuracao("API MP Response: HTTP $http_code");

    if ($curl_error) { echo json_encode(array('status' => 'error', 'message' => 'Erro cURL: ' . $curl_error)); exit(); }

    if ($http_code == 200 || $http_code == 201) {
        $result = json_decode($response, true);

        if (isset($result['point_of_interaction']['transaction_data']['qr_code_base64'])) {
            $payment_id     = $result['id'];
            $qr_code_base64 = $result['point_of_interaction']['transaction_data']['qr_code_base64'];
            $qr_code        = $result['point_of_interaction']['transaction_data']['qr_code'];
            $data_criacao   = date('Y-m-d H:i:s');
            $pid            = mysqli_real_escape_string($conn, $payment_id);

            mysqli_query($conn, "INSERT INTO pagamentos (payment_id, valor, status, data_criacao, tipo_conta, iduser, byid, external_reference, origem, id_plano, limite, duracao_dias)
                           VALUES ('$pid', '$valor_pagar', 'pending', '$data_criacao', 'revenda_compra', '$comprador_id', '$vendedor_id', '$external_id', 'compra_revenda', '$plano_id', '$limite_plano', '$duracao_dias')");

            mysqli_query($conn, "INSERT INTO pagamentos_revenda (user_id, login, payment_id, valor, status, data_pagamento, revendedor_id, plano_id, limite_creditos, duracao_dias)
                                   VALUES ('$comprador_id', '$comprador_login', '$pid', '$valor_pagar', 'pending', '$data_criacao', '$vendedor_id', '$plano_id', '$limite_plano', '$duracao_dias')");

            salvarUnificado(
                $conn, 'compra_plano_revenda', $comprador_id, $comprador_login,
                $payment_id, $valor_pagar, 'pending', $vendedor_id,
                $plano_id, $limite_plano, $duracao_dias, $plano['nome'], null
            );

            logDepuracao("Pagamento PENDING salvo: payment_id=$payment_id user=$comprador_login plano={$plano['nome']}");

            $_SESSION['payment_id']     = $payment_id;
            $_SESSION['qr_code_base64'] = $qr_code_base64;
            $_SESSION['qr_code']        = $qr_code;
            $_SESSION['plano_compra']   = array(
                'id'          => $plano_id,
                'nome'        => $plano['nome'],
                'valor'       => $valor_pagar,
                'limite'      => $limite_plano,
                'duracao_dias'=> $duracao_dias,
                'vendedor_id' => $vendedor_id
            );
            $_SESSION['cupom_usado']    = $cupom_codigo;
            $_SESSION['comprador_wpp']  = $comprador_wpp;

            echo json_encode(array(
                'status'         => 'success',
                'qr_code_base64' => $qr_code_base64,
                'qr_code'        => $qr_code,
                'payment_id'     => $payment_id
            ));
        } else {
            echo json_encode(array('status' => 'error', 'message' => 'Erro ao gerar QR Code'));
        }
    } else {
        echo json_encode(array('status' => 'error', 'message' => 'Erro na API: HTTP ' . $http_code . ' - ' . $response));
    }
    exit();
}

// ========== VERIFICAR PAGAMENTO ==========
if ($isPostVerificar) {
    header('Content-Type: application/json');

    $payment_id = isset($_POST['payment_id']) ? mysqli_real_escape_string($conn, $_POST['payment_id']) : '';
    logDepuracao("=== VERIFICANDO PAGAMENTO: $payment_id ===");

    if (empty($payment_id)) { echo json_encode(array('status' => 'error', 'message' => 'ID do pagamento não informado!')); exit(); }

    $sql_pagamento    = "SELECT * FROM pagamentos_revenda WHERE payment_id = '$payment_id' AND user_id = '$comprador_id'";
    $result_pagamento = mysqli_query($conn, $sql_pagamento);
    $pagamento        = mysqli_fetch_assoc($result_pagamento);

    if (!$pagamento) {
        logDepuracao("Pagamento NÃO encontrado no banco para ID: $payment_id");
        if (isset($_SESSION['payment_id']) && $_SESSION['payment_id'] == $payment_id) {
            echo json_encode(array('status' => 'pending', 'message' => 'Aguardando processamento...'));
        } else {
            echo json_encode(array('status' => 'error', 'message' => 'Pagamento não encontrado!'));
        }
        exit();
    }

    logDepuracao("Pagamento encontrado - Status atual: " . $pagamento['status']);

    if ($pagamento['status'] == 'approved') {
        echo json_encode(array(
            'status'      => 'approved',
            'message'     => 'Pagamento já aprovado! Plano ativado!',
            'novo_limite' => $pagamento['limite_creditos']
        ));
        exit();
    }

    // Consultar API MP
    // Usar mp_access_token do vendedor do pagamento
    $vid_pag = intval($pagamento['revendedor_id']);
    $r_vend_mp = $conn->query("SELECT mp_access_token, mp_active FROM accounts WHERE id='$vid_pag' LIMIT 1");
    $mp_token_uso = $mp_access_token; // fallback
    if ($r_vend_mp && $r_vend_mp->num_rows > 0) {
        $v_mp = $r_vend_mp->fetch_assoc();
        if (!empty($v_mp['mp_access_token'])) $mp_token_uso = $v_mp['mp_access_token'];
    }

    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL            => 'https://api.mercadopago.com/v1/payments/' . $payment_id,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => array(
            'Authorization: Bearer ' . $mp_token_uso,
            'Content-Type: application/json'
        ),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 30
    ));
    $response   = curl_exec($ch);
    $http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code != 200) { echo json_encode(array('status' => 'pending', 'message' => 'Aguardando pagamento...')); exit(); }

    $result    = json_decode($response, true);
    $status_mp = isset($result['status']) ? $result['status'] : 'unknown';
    logDepuracao("Status MP: $status_mp");

    if ($status_mp == 'approved') {
        $data_pagamento_now = date('Y-m-d H:i:s');

        mysqli_query($conn, "UPDATE pagamentos SET status = 'approved', data_pagamento = NOW() WHERE payment_id = '$payment_id'");
        mysqli_query($conn, "UPDATE pagamentos_revenda SET status = 'approved', data_pagamento = NOW() WHERE payment_id = '$payment_id'");

        mysqli_query($conn, "UPDATE pagamentos_unificado SET status = 'approved', data_pagamento = '$data_pagamento_now' WHERE payment_id = '$payment_id'");
        if (mysqli_affected_rows($conn) == 0) {
            salvarUnificado(
                $conn, 'compra_plano_revenda', $comprador_id, $comprador_login,
                $payment_id, floatval($pagamento['valor']), 'approved',
                intval($pagamento['revendedor_id']), intval($pagamento['plano_id']),
                intval($pagamento['limite_creditos']), intval($pagamento['duracao_dias']),
                null, $data_pagamento_now
            );
        }

        if (!empty($_SESSION['cupom_usado'])) {
            $cup = mysqli_real_escape_string($conn, $_SESSION['cupom_usado']);
            mysqli_query($conn, "UPDATE cupons SET usado = usado + 1 WHERE codigo = '$cup'");
            unset($_SESSION['cupom_usado'], $_SESSION['cupom_aplicado'], $_SESSION['cupom_desconto'], $_SESSION['cupom_valor_final']);
        }

        $limite_plano_pag  = intval($pagamento['limite_creditos']);
        $duracao_dias_pag  = intval($pagamento['duracao_dias']);
        $vendedor_id_pag   = intval($pagamento['revendedor_id']);
        $valor_plano_pag   = floatval($pagamento['valor']);
        $plano_id_pag      = intval($pagamento['plano_id']);

        $pai_original      = buscarPaiOriginal($conn, $comprador_id);
        $dados_comprador   = buscarLimiteAtualComprador($conn, $comprador_id);
        $limite_antigo     = $dados_comprador['limite'];
        $valor_antigo      = $dados_comprador['valor'];
        $plano_anterior_id = $dados_comprador['id_plano'];
        $validade_atual    = $dados_comprador['expira'];

        if (empty($validade_atual) || $validade_atual < date('Y-m-d H:i:s')) {
            $validade_atual = date('Y-m-d H:i:s');
        }
        $nova_validade = date('Y-m-d H:i:s', strtotime("+$duracao_dias_pag days", strtotime($validade_atual)));

        logDepuracao("AJAX - limite antigo=$limite_antigo, pai=$pai_original, nova validade=$nova_validade");

        $sql_check    = "SELECT * FROM atribuidos WHERE userid = '$comprador_id'";
        $result_check = mysqli_query($conn, $sql_check);

        if (mysqli_num_rows($result_check) > 0) {
            mysqli_query($conn, "UPDATE atribuidos SET limite='$limite_plano_pag', expira='$nova_validade', valor='$valor_plano_pag', id_plano='$plano_id_pag', byid='$pai_original', suspenso=0 WHERE userid='$comprador_id'");
            logDepuracao("Comprador atualizado: novo limite $limite_plano_pag, nova validade $nova_validade");
        } else {
            $sql_cat     = "SELECT categoriaid FROM atribuidos WHERE userid = '$vendedor_id_pag'";
            $result_cat  = mysqli_query($conn, $sql_cat);
            $cat         = mysqli_fetch_assoc($result_cat);
            $categoriaid = isset($cat['categoriaid']) ? $cat['categoriaid'] : 1;
            mysqli_query($conn, "INSERT INTO atribuidos (userid, byid, limite, categoriaid, tipo, expira, valor, id_plano) VALUES ('$comprador_id', '$pai_original', '$limite_plano_pag', '$categoriaid', 'Validade', '$nova_validade', '$valor_plano_pag', '$plano_id_pag')");
            logDepuracao("Novo comprador criado: pai=$pai_original");
        }

        mysqli_query($conn, "INSERT INTO historico_planos_revenda (revenda_id, plano_anterior_id, plano_novo_id, limite_anterior, limite_novo, valor_anterior, valor_novo, data_alteracao, tipo_alteracao) VALUES ('$comprador_id', '$plano_anterior_id', '$plano_id_pag', '$limite_antigo', '$limite_plano_pag', '$valor_antigo', '$valor_plano_pag', NOW(), 'compra_substituicao')");

        $datahoje = date('d-m-Y H:i:s');
        mysqli_query($conn, "INSERT INTO logs (revenda, validade, texto, userid) VALUES ('{$comprador_login}', '$datahoje', 'Comprou plano de {$limite_plano_pag} créditos', '$comprador_id')");

        // ========== ENVIAR WHATSAPP ==========
        $wpp_numero = $comprador_wpp;
        if (empty($wpp_numero) && !empty($_SESSION['comprador_wpp'])) {
            $wpp_numero = $_SESSION['comprador_wpp'];
        }
        if (!empty($wpp_numero)) {
            $wpp_ok = dispararMensagemPlano($conn, $vendedor_id_pag, 'planoaprovado', [
                'usuario'  => $comprador_login,
                'senha'    => '',
                'validade' => date('d/m/Y', strtotime($nova_validade)),
                'limite'   => $limite_plano_pag,
                'whatsapp' => $wpp_numero,
                'dominio'  => $_SERVER['HTTP_HOST'] ?? '',
            ]);
            logDepuracao("WhatsApp plano aprovado: " . ($wpp_ok ? "ENVIADO para $wpp_numero" : "FALHOU para $wpp_numero"));
        } else {
            logDepuracao("WhatsApp plano: número não cadastrado para comprador_id=$comprador_id");
        }
        // ========== FIM WHATSAPP ==========

        unset($_SESSION['qr_code_base64'], $_SESSION['qr_code'], $_SESSION['payment_id'], $_SESSION['plano_compra'], $_SESSION['comprador_wpp']);

        $mensagem_final = "✅ Pagamento aprovado! Plano ativado!\n\n📊 NOVO PLANO:\n🔹 Limite: " . number_format($limite_plano_pag, 0, ',', '.') . " créditos\n🔹 Valor: R$ " . number_format($valor_plano_pag, 2, ',', '.') . "\n🔹 Validade: " . date('d/m/Y', strtotime($nova_validade));

        echo json_encode(array(
            'status'      => 'approved',
            'message'     => $mensagem_final,
            'novo_limite' => $limite_plano_pag
        ));
    } else {
        echo json_encode(array('status' => $status_mp, 'message' => 'Aguardando pagamento...'));
    }
    exit();
}

function anti_sql($input) {
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function($match) { return ''; }, $input);
    $seg = trim($seg); $seg = strip_tags($seg); $seg = addslashes($seg);
    return $seg;
}

$has_pending = isset($_SESSION['qr_code_base64']) && !empty($_SESSION['qr_code_base64']);
$payment_id  = isset($_SESSION['payment_id']) ? $_SESSION['payment_id'] : '';
$qr_code     = isset($_SESSION['qr_code'])    ? $_SESSION['qr_code']    : '';

$result_cfg   = $conn->query("SELECT * FROM configs");
$cfg          = $result_cfg ? $result_cfg->fetch_assoc() : [];
$nomepainel   = isset($cfg['nomepainel'])   ? $cfg['nomepainel']   : 'Painel';
$csspersonali = isset($cfg['corfundologo']) ? $cfg['corfundologo'] : '';

$valor_formatado          = number_format($cupom_aplicado ? $cupom_valor_com_desconto : $valor_plano, 2, ',', '.');
$valor_original_formatado = number_format($valor_plano, 2, ',', '.');
$error_message            = '';

if (!$tem_limite_suficiente && $plano) {
    $error_message = "❌ O vendedor '" . htmlspecialchars($vendedor_nome) . "' não tem limite suficiente para vender este plano!<br>Precisa de " . number_format($limite_plano, 0, ',', '.') . " créditos, mas tem apenas " . number_format($limite_vendedor_total, 0, ',', '.') . ".";
}

$mp_nao_configurado = ($mp_active != 1 || empty($mp_access_token));

if ($isPostCriar || $isPostVerificar) exit();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($nomepainel); ?> - Pagamento Revenda</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        <?php echo $csspersonali; ?>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Rubik', sans-serif; background: linear-gradient(135deg, #0f172a, #1e1b4b); min-height: 100vh; }

        .app-content { margin-left: 240px !important; padding: 0 !important; }
        .content-wrapper { max-width: 800px; margin: 0 auto 0 5px !important; padding: 0px !important; }

        .info-badge { display: inline-flex !important; align-items: center !important; gap: 8px !important; background: white !important; color: #2c3e50 !important; padding: 8px 16px !important; border-radius: 30px !important; font-size: 13px !important; margin-top: 5px !important; margin-bottom: 15px !important; border-left: 4px solid #4158D0 !important; box-shadow: 0 3px 10px rgba(0,0,0,0.1) !important; }
        .info-badge i { font-size: 22px; color: #4158D0; }

        .status-info { background: linear-gradient(135deg, #1e293b, #0f172a); border-radius: 14px; padding: 12px 18px; margin-bottom: 15px; border: 1px solid rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; color: white; }
        .status-item { display: flex; align-items: center; gap: 6px; }
        .status-item i { font-size: 20px; color: #FFCC70; }
        .status-item span { font-size: 12px; font-weight: 500; }

        .modern-card { background: linear-gradient(135deg, #1e293b, #0f172a) !important; border-radius: 20px !important; border: 1px solid rgba(255,255,255,0.08) !important; overflow: hidden !important; position: relative !important; box-shadow: 0 15px 40px rgba(0,0,0,0.4) !important; width: 100% !important; margin-bottom: 20px; }
        .card-header { padding: 16px 20px 12px !important; border-bottom: 1px solid rgba(255,255,255,0.07) !important; display: flex !important; align-items: center !important; gap: 10px !important; }
        .header-icon { width: 36px; height: 36px; border-radius: 10px; background: linear-gradient(135deg, #10b981, #059669); display: flex; align-items: center; justify-content: center; font-size: 18px; color: white; }
        .header-title { font-size: 14px; font-weight: 700; color: white; }
        .header-subtitle { font-size: 10px; color: rgba(255,255,255,0.35); }
        .card-body { padding: 18px 20px !important; }

        .info-plano { background: rgba(16,185,129,0.1); border-radius: 16px; padding: 16px; margin-bottom: 20px; }
        .info-plano-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .info-plano-row:last-child { border-bottom: none; }
        .info-plano-label { font-size: 13px; color: rgba(255,255,255,0.6); display: flex; align-items: center; gap: 8px; }
        .info-plano-value { font-size: 16px; font-weight: 700; color: white; }
        .info-plano-value.price { color: #f59e0b; font-size: 24px; }

        .cupom-area { background: rgba(255,255,255,0.03); border-radius: 12px; padding: 12px; margin-bottom: 20px; }
        .cupom-input { display: flex; gap: 8px; margin-top: 8px; }
        .cupom-input input { flex: 1; background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 10px; color: white; font-size: 12px; }
        .cupom-input button { background: rgba(16,185,129,0.2); border: 1px solid rgba(16,185,129,0.3); padding: 10px 16px; border-radius: 8px; color: #10b981; font-weight: 600; cursor: pointer; }

        .btn-pagar { width: 100%; background: linear-gradient(135deg, #10b981, #059669); border: none; padding: 14px; border-radius: 12px; color: white; font-weight: 700; font-size: 16px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; margin-top: 16px; }
        .btn-pagar:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-voltar { width: 100%; background: transparent; border: 1px solid rgba(255,255,255,0.2); padding: 12px; border-radius: 12px; color: #94a3b8; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 12px; text-decoration: none; }

        .info-note { background: rgba(59,130,246,0.1); border-left: 3px solid #3b82f6; padding: 10px; border-radius: 8px; margin-top: 16px; }
        .info-note small { color: rgba(255,255,255,0.6); font-size: 10px; }
        .warning-note { background: rgba(245,158,11,0.1); border-left: 3px solid #f59e0b; padding: 10px; border-radius: 8px; margin-top: 16px; }
        .warning-note small { color: rgba(255,255,255,0.6); font-size: 10px; }

        .alert-danger { background: rgba(220,38,38,0.2); border: 1px solid #dc2626; border-radius: 12px; padding: 15px; text-align: center; margin-bottom: 20px; }

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
        .pix-code { background: rgba(0,0,0,0.3); border-radius: 12px; padding: 12px; margin: 16px 0; text-align: left; }
        .pix-code .label { font-size: 11px; color: #94a3b8; margin-bottom: 8px; }
        .pix-code .code { font-family: monospace; font-size: 10px; color: #60a5fa; word-break: break-all; }
        .btn-copiar, .btn-verificar { width: 100%; padding: 12px; border-radius: 12px; color: white; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 12px; border: none; }
        .btn-copiar { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .btn-verificar { background: linear-gradient(135deg, #f59e0b, #f97316); }
        .status-message { background: rgba(245,158,11,0.15); border-radius: 12px; padding: 12px; margin-top: 16px; display: flex; align-items: center; gap: 10px; color: #fbbf24; font-size: 13px; }
        .spinner { display: inline-block; width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top-color: white; border-radius: 50%; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        @media (max-width: 768px) {
            .app-content { margin-left: 0 !important; }
            .content-wrapper { padding: 10px !important; }
            .status-info { flex-direction: column; text-align: center; }
            .info-plano-row { flex-direction: column; align-items: flex-start; gap: 5px; }
            .modal-container { width: 95%; }
        }
    </style>
</head>
<body>
<div class="app-content content">
    <div class="h2_tema ?? ['classe'=>'theme-dark'])); ?>">
<div class="app-content content">
    <div class="

