<?php
/**
 * Funções centralizadas de WhatsApp — Evolution API
 * apiurl e token = sempre do admin (byid=1)
 * sessao e ativo = do próprio revendedor (byid passado)
 */

function logWpp($msg) {
    file_put_contents(__DIR__ . '/whatsapp_log.txt',
        '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
}

/**
 * Busca config completa:
 * - apiurl + token do ADMIN (byid=1)
 * - sessao + ativo do REVENDEDOR ($byid)
 */
function getWhatsAppConfig($conn, $byid) {
    $byid = intval($byid);

    // 1. Busca apiurl e token do admin (byid=1)
    if (is_object($conn)) {
        $r_admin = $conn->query("SELECT apiurl, token FROM whatsapp WHERE byid='1' LIMIT 1");
        $admin   = ($r_admin && $r_admin->num_rows > 0) ? $r_admin->fetch_assoc() : null;
    } else {
        $r_admin = mysqli_query($conn, "SELECT apiurl, token FROM whatsapp WHERE byid='1' LIMIT 1");
        $admin   = ($r_admin && mysqli_num_rows($r_admin) > 0) ? mysqli_fetch_assoc($r_admin) : null;
    }

    if (!$admin || empty($admin['apiurl']) || empty($admin['token'])) {
        logWpp("getWhatsAppConfig: admin sem apiurl/token configurado");
        return null;
    }

    // 2. Busca sessao e ativo do próprio revendedor
    if (is_object($conn)) {
        $r_rev = $conn->query("SELECT sessao, ativo FROM whatsapp WHERE byid='$byid' LIMIT 1");
        $rev   = ($r_rev && $r_rev->num_rows > 0) ? $r_rev->fetch_assoc() : null;
    } else {
        $r_rev = mysqli_query($conn, "SELECT sessao, ativo FROM whatsapp WHERE byid='$byid' LIMIT 1");
        $rev   = ($r_rev && mysqli_num_rows($r_rev) > 0) ? mysqli_fetch_assoc($r_rev) : null;
    }

    if (!$rev || empty($rev['sessao'])) {
        logWpp("getWhatsAppConfig: revendedor byid=$byid sem sessao cadastrada");
        return null;
    }

    if ($rev['ativo'] != '1') {
        logWpp("getWhatsAppConfig: revendedor byid=$byid com ativo={$rev['ativo']}");
        return null;
    }

    $config = [
        'apiurl' => rtrim($admin['apiurl'], '/'),
        'token'  => $admin['token'],
        'sessao' => $rev['sessao'],
        'ativo'  => $rev['ativo'],
    ];

    logWpp("getWhatsAppConfig: byid=$byid apiurl={$config['apiurl']} sessao={$config['sessao']}");
    return $config;
}

/**
 * Envia mensagem via Evolution API
 */
function enviarWhatsApp($apiurl, $instancia, $token, $numero, $mensagem) {
    if (empty($apiurl) || empty($instancia) || empty($token) || empty($numero) || empty($mensagem)) {
        logWpp("enviarWhatsApp: parâmetros incompletos");
        return ['ok' => false, 'msg' => 'Parâmetros incompletos'];
    }

    $numero = preg_replace('/[^0-9]/', '', $numero);
    if (strlen($numero) < 10) return ['ok' => false, 'msg' => 'Número inválido'];
    if (substr($numero, 0, 2) !== '55') $numero = '55' . $numero;

    if (!preg_match('#^https?://#i', $apiurl)) $apiurl = 'http://' . $apiurl;

    $url = rtrim($apiurl, '/') . '/message/sendText/' . urlencode($instancia);

    $payloads = [
        json_encode(['number' => $numero,                      'text' => $mensagem]),
        json_encode(['number' => $numero,                      'textMessage' => ['text' => $mensagem]]),
        json_encode(['number' => $numero . '@s.whatsapp.net',  'text' => $mensagem]),
    ];

    foreach ($payloads as $i => $payload) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'apikey: ' . $token,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        $body  = curl_exec($ch);
        $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);
        $cerr  = curl_error($ch);
        curl_close($ch);

        logWpp("enviarWhatsApp tentativa " . ($i+1) . " num=$numero code=$code errno=$errno cerr=$cerr resp=$body");

        if ($errno === 0 && $code >= 200 && $code < 300) {
            logWpp("enviarWhatsApp: ENVIADO na tentativa " . ($i+1));
            return ['ok' => true, 'msg' => $body];
        }
    }

    logWpp("enviarWhatsApp: FALHOU todas tentativas para num=$numero");
    return ['ok' => false, 'msg' => 'Falha em todas as tentativas'];
}

/**
 * Envia mensagem usando config do revendedor (sessao) + admin (apiurl+token)
 */
function enviarWhatsAppEvolution($conn, $byid, $numero, $texto) {
    $cfg = getWhatsAppConfig($conn, $byid);
    if (!$cfg) return false;

    $result = enviarWhatsApp($cfg['apiurl'], $cfg['sessao'], $cfg['token'], $numero, $texto);
    return $result['ok'];
}

/**
 * Dispara mensagem automática pelo tipo de evento
 * $dados = ['usuario','senha','validade','limite','whatsapp','dominio',...]
 */
function dispararMensagemAutomatica($conn, $byid, $funcao, $dados) {
    $byid   = intval($byid);
    $funcao = is_object($conn)
        ? $conn->real_escape_string($funcao)
        : mysqli_real_escape_string($conn, $funcao);

    // Busca template de mensagem
    if (is_object($conn)) {
        $r = $conn->query("SELECT mensagem FROM mensagens WHERE funcao='$funcao' AND byid='$byid' AND ativo='ativada' LIMIT 1");
        $row = ($r && $r->num_rows > 0) ? $r->fetch_assoc() : null;
    } else {
        $r = mysqli_query($conn, "SELECT mensagem FROM mensagens WHERE funcao='$funcao' AND byid='$byid' AND ativo='ativada' LIMIT 1");
        $row = ($r && mysqli_num_rows($r) > 0) ? mysqli_fetch_assoc($r) : null;
    }

    if (!$row || empty($row['mensagem'])) {
        logWpp("dispararMensagemAutomatica: sem template para funcao=$funcao byid=$byid");
        return ['ok' => false, 'msg' => 'Template não configurado'];
    }

    $template = $row['mensagem'];
    $dominio  = $dados['dominio'] ?? $_SERVER['HTTP_HOST'] ?? '';

    // Substitui todas as variáveis possíveis
    $mensagem = str_replace(
        ['{usuario}', '{login}', '{senha}',    '{validade}',    '{limite}',
         '{dominio}', '{minutos}', '{duracao}', '{dias}',        '{valor}'],
        [$dados['usuario']  ?? '',
         $dados['usuario']  ?? '',
         $dados['senha']    ?? '',
         $dados['validade'] ?? '',
         $dados['limite']   ?? '',
         $dominio,
         $dados['minutos']  ?? '',
         $dados['duracao']  ?? '',
         $dados['dias']     ?? '',
         $dados['valor']    ?? ''],
        $template
    );

    $numero = $dados['whatsapp'] ?? '';
    if (empty($numero)) {
        logWpp("dispararMensagemAutomatica: número vazio para funcao=$funcao byid=$byid");
        return ['ok' => false, 'msg' => 'Número vazio'];
    }

    $cfg = getWhatsAppConfig($conn, $byid);
    if (!$cfg) return ['ok' => false, 'msg' => 'Config WhatsApp não encontrada'];

    $result = enviarWhatsApp($cfg['apiurl'], $cfg['sessao'], $cfg['token'], $numero, $mensagem);
    logWpp("dispararMensagemAutomatica: funcao=$funcao byid=$byid numero=$numero ok=" . ($result['ok'] ? 'SIM' : 'NAO'));
    return $result;
}

/**
 * Cria instância na Evolution API (usa config do admin)
 */
function criarInstanciaEvolution($conn, $instancia, $byid_revendedor = null) {
    // Sempre usa apiurl e token do admin
    if (is_object($conn)) {
        $r = $conn->query("SELECT apiurl, token FROM whatsapp WHERE byid='1' LIMIT 1");
        $admin = ($r && $r->num_rows > 0) ? $r->fetch_assoc() : null;
    } else {
        $r = mysqli_query($conn, "SELECT apiurl, token FROM whatsapp WHERE byid='1' LIMIT 1");
        $admin = ($r && mysqli_num_rows($r) > 0) ? mysqli_fetch_assoc($r) : null;
    }

    if (!$admin || empty($admin['apiurl']) || empty($admin['token'])) {
        return ['error' => 'Admin sem apiurl/token configurado'];
    }

    $apiurl = rtrim($admin['apiurl'], '/');
    $token  = $admin['token'];
    if (!preg_match('#^https?://#i', $apiurl)) $apiurl = 'http://' . $apiurl;

    $url  = $apiurl . '/instance/create';
    $body = json_encode([
        'instanceName' => $instancia,
        'token'        => $token,
        'qrcode'       => true,
        'integration'  => 'WHATSAPP-BAILEYS',
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'apikey: ' . $token,
        ],
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    return json_decode($resp, true);
}

/**
 * Conecta instância — usa admin apiurl+token
 */
function conectarInstanciaEvolution($conn, $instancia) {
    if (is_object($conn)) {
        $r = $conn->query("SELECT apiurl, token FROM whatsapp WHERE byid='1' LIMIT 1");
        $admin = ($r && $r->num_rows > 0) ? $r->fetch_assoc() : null;
    } else {
        $r = mysqli_query($conn, "SELECT apiurl, token FROM whatsapp WHERE byid='1' LIMIT 1");
        $admin = ($r && mysqli_num_rows($r) > 0) ? mysqli_fetch_assoc($r) : null;
    }

    if (!$admin || empty($admin['apiurl'])) return ['error' => 'Admin sem config'];

    $apiurl = rtrim($admin['apiurl'], '/');
    if (!preg_match('#^https?://#i', $apiurl)) $apiurl = 'http://' . $apiurl;

    $url = $apiurl . '/instance/connect/' . urlencode($instancia);
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => ['apikey: ' . $admin['token']],
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    return json_decode($resp, true);
}

/**
 * Status da instância — usa admin apiurl+token
 */
function statusInstanciaEvolution($conn, $instancia) {
    if (is_object($conn)) {
        $r = $conn->query("SELECT apiurl, token FROM whatsapp WHERE byid='1' LIMIT 1");
        $admin = ($r && $r->num_rows > 0) ? $r->fetch_assoc() : null;
    } else {
        $r = mysqli_query($conn, "SELECT apiurl, token FROM whatsapp WHERE byid='1' LIMIT 1");
        $admin = ($r && mysqli_num_rows($r) > 0) ? mysqli_fetch_assoc($r) : null;
    }

    if (!$admin || empty($admin['apiurl'])) return ['error' => 'Admin sem config'];

    $apiurl = rtrim($admin['apiurl'], '/');
    if (!preg_match('#^https?://#i', $apiurl)) $apiurl = 'http://' . $apiurl;

    $url = $apiurl . '/instance/connectionState/' . urlencode($instancia);
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => ['apikey: ' . $admin['token']],
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    return json_decode($resp, true);
}

/**
 * Logout da instância — usa admin apiurl+token
 */
function logoutInstanciaEvolution($conn, $instancia) {
    if (is_object($conn)) {
        $r = $conn->query("SELECT apiurl, token FROM whatsapp WHERE byid='1' LIMIT 1");
        $admin = ($r && $r->num_rows > 0) ? $r->fetch_assoc() : null;
    } else {
        $r = mysqli_query($conn, "SELECT apiurl, token FROM whatsapp WHERE byid='1' LIMIT 1");
        $admin = ($r && mysqli_num_rows($r) > 0) ? mysqli_fetch_assoc($r) : null;
    }

    if (!$admin || empty($admin['apiurl'])) return ['error' => 'Admin sem config'];

    $apiurl = rtrim($admin['apiurl'], '/');
    if (!preg_match('#^https?://#i', $apiurl)) $apiurl = 'http://' . $apiurl;

    $url = $apiurl . '/instance/logout/' . urlencode($instancia);
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'DELETE',
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => ['apikey: ' . $admin['token']],
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    return json_decode($resp, true);
}