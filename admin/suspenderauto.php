<?php
// ATENÇÃO: Este script deve ser executado por CRON a cada 1 hora
// Exemplo de CRON: 0 * * * * /usr/bin/php /caminho/para/suspenderauto.php

error_reporting(0);

include('../vendor/event/autoload.php');
use React\EventLoop\Factory;

include('../AegisCore/conexao.php');

$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

date_default_timezone_set('America/Sao_Paulo');

$token_sistema = md5('token_sistema_interno');

// FUNÇÃO PARA BUSCAR TOKEN DO SERVIDOR
function getServidorToken($conn, $servidor_id, $token_fallback) {
    $sql_token = "SELECT token FROM servidor_tokens WHERE servidor_id = '$servidor_id' AND status = 'ativo' ORDER BY id DESC LIMIT 1";
    $result_token = mysqli_query($conn, $sql_token);
    
    if ($result_token && mysqli_num_rows($result_token) > 0) {
        $row_token = mysqli_fetch_assoc($result_token);
        return $row_token['token'];
    }
    return $token_fallback;
}

// Função para buscar TODOS os revendedores (incluindo sub-níveis)
function buscarTodosRevendedores($conn, $id_pai, &$todos_ids = []) {
    if (!in_array($id_pai, $todos_ids)) {
        $todos_ids[] = $id_pai;
    }
    
    $sql = "SELECT id FROM accounts WHERE byid = '$id_pai'";
    $result = mysqli_query($conn, $sql);
    
    while ($row = mysqli_fetch_assoc($result)) {
        buscarTodosRevendedores($conn, $row['id'], $todos_ids);
    }
    
    return $todos_ids;
}

// ============================================
// PASSO 1: IDENTIFICAR REVENDEDORES VENCIDOS
// ============================================
$data_agora = date('Y-m-d H:i:s');

// Deletar contas com login/senha vazio
$conn->query("DELETE FROM ssh_accounts WHERE login = ''");
$conn->query("DELETE FROM ssh_accounts WHERE senha = ''");

// Usuários SSH vencidos individualmente
$ontem = date('Y-m-d', strtotime('-1 day'));
$sql_ssh_vencidos = "SELECT * FROM ssh_accounts 
                     WHERE expira >= '$ontem' 
                     AND expira < '$data_agora' 
                     AND mainid != 'Suspenso' 
                     LIMIT 3";
$result_ssh_vencidos = $conn->query($sql_ssh_vencidos);
$ssh_individuais = [];
while ($row = $result_ssh_vencidos->fetch_assoc()) {
    $ssh_individuais[] = $row;
}

// CORREÇÃO: Revendedores vencidos - NÃO marcar como suspenso, apenas vencido
// Usaremos um campo "vencido" ou manteremos "suspenso" mas com diferenciação
$sql_rev_vencidos = "SELECT userid, categoriaid, expira 
                     FROM atribuidos 
                     WHERE expira < '$data_agora' 
                     AND tipo != 'Credito' 
                     AND (suspenso != '1' OR suspenso IS NULL)";
$result_vencidos = $conn->query($sql_rev_vencidos);

$todos_afetados = [];
$revendedores_vencidos_detalhes = [];

if ($result_vencidos->num_rows > 0) {
    while ($row = $result_vencidos->fetch_assoc()) {
        $rev_id = $row['userid'];
        $revendedores_vencidos_detalhes[$rev_id] = [
            'userid' => $rev_id,
            'expira' => $row['expira'],
            'categoriaid' => $row['categoriaid']
        ];
        
        $arvore = [];
        buscarTodosRevendedores($conn, $rev_id, $arvore);
        foreach ($arvore as $id_afetado) {
            if (!in_array($id_afetado, $todos_afetados)) {
                $todos_afetados[] = $id_afetado;
            }
        }
    }
}

// ============================================
// PASSO 2: BUSCAR USUÁRIOS SSH
// ============================================
$ssh_accounts        = [];
$categorias_envolvidas = [];

// SSH de revendedores vencidos
if (!empty($todos_afetados)) {
    $ids_str  = implode(",", $todos_afetados);
    $sql_ssh  = "SELECT login, uuid, categoriaid FROM ssh_accounts WHERE byid IN ($ids_str)";
    $result_ssh = $conn->query($sql_ssh);
    while ($row_ssh = $result_ssh->fetch_assoc()) {
        $ssh_accounts[]          = $row_ssh;
        $categorias_envolvidas[] = $row_ssh['categoriaid'];
    }
}

// SSH individuais vencidos
foreach ($ssh_individuais as $ssh_ind) {
    $ssh_accounts[]          = $ssh_ind;
    $categorias_envolvidas[] = $ssh_ind['categoriaid'];
}

$total_revendedores = count($revendedores_vencidos_detalhes);
$total_usuarios     = count($ssh_accounts);

if ($total_revendedores == 0 && count($ssh_individuais) == 0) {
    mysqli_close($conn);
    exit();
}

// ============================================
// PASSO 3: ATUALIZAR BANCO DE DADOS
// ============================================
// CORREÇÃO: Marcar revendedores como VENCIDOS, não como SUSPENSOS
// Usamos um campo 'vencido' na tabela atribuidos (ou podemos usar suspenso = '2' para diferenciar)
// Vou usar suspenso = '2' para indicar VENCIDO (diferente de suspenso manual = '1')
foreach ($revendedores_vencidos_detalhes as $rev_id => $detalhes) {
    // Marca como vencido (usando suspenso = '2' para indicar vencimento)
    $conn->query("UPDATE atribuidos SET suspenso = '2' WHERE userid = '$rev_id'");
    // Atualiza status da conta
    $conn->query("UPDATE accounts SET status = 'Vencido' WHERE id = '$rev_id'");
}

if (!empty($todos_afetados)) {
    $ids_str = implode(",", $todos_afetados);
    $conn->query("UPDATE ssh_accounts SET mainid = 'Vencido', status = 'Offline' WHERE byid IN ($ids_str)");
}

// SSH individuais vencidos - marcar como vencido
foreach ($ssh_individuais as $ssh_ind) {
    $login_ind = $ssh_ind['login'];
    $conn->query("UPDATE ssh_accounts SET mainid = 'Vencido', status = 'Offline' WHERE login = '$login_ind'");
}

// ============================================
// PASSO 4: PREPARAR ARQUIVO PARA SERVIDORES
// ============================================
$nome_arquivo    = substr(md5(uniqid(rand(), true)), 0, 10) . ".txt";
$caminho_completo = __DIR__ . '/' . $nome_arquivo;

if (!empty($ssh_accounts)) {
    $file = fopen($caminho_completo, "w");
    foreach ($ssh_accounts as $ssh_account) {
        fwrite($file, $ssh_account['login'] . "\n");
    }
    fclose($file);
}

// ============================================
// PASSO 5: ENVIAR PARA SERVIDORES
// ============================================
$categorias_envolvidas = array_unique($categorias_envolvidas);

if (!empty($categorias_envolvidas) && !empty($ssh_accounts)) {
    $categorias_str  = implode(",", $categorias_envolvidas);
    $sql_servidores  = "SELECT * FROM servidores WHERE subid IN ($categorias_str)";
    $result_servidores = $conn->query($sql_servidores);
    
    $loop = Factory::create();
    
    while ($servidor = mysqli_fetch_assoc($result_servidores)) {
        $ip          = $servidor['ip'];
        $servidor_id = $servidor['id'];

        $token_servidor = getServidorToken($conn, $servidor_id, $token_sistema);
        
        $socket = @fsockopen($ip, 6969, $errno, $errstr, 5);

        if ($socket) {
            fclose($socket);
            
            $loop->addTimer(0.1, function () use ($ip, $caminho_completo, $nome_arquivo, $token_servidor) {
                if (!file_exists($caminho_completo) || filesize($caminho_completo) == 0) {
                    return;
                }
                
                $limiter_content = file_get_contents($caminho_completo);
                $headers = [
                    'Senha: ' . $token_servidor,
                    'User-Agent: Atlas-Suspender-Auto/1.0'
                ];
                
                // Envia arquivo para /root/
                $ch1 = curl_init();
                curl_setopt($ch1, CURLOPT_URL, 'http://' . $ip . ':6969');
                curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch1, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch1, CURLOPT_POST, 1);
                curl_setopt($ch1, CURLOPT_POSTFIELDS, http_build_query([
                    'comando' => 'echo "' . addslashes($limiter_content) . '" > /root/' . $nome_arquivo
                ]));
                curl_setopt($ch1, CURLOPT_TIMEOUT, 30);
                $output1   = curl_exec($ch1);
                $httpCode1 = curl_getinfo($ch1, CURLINFO_HTTP_CODE);
                curl_close($ch1);
                
                // Suspende os usuários no servidor
                if ($httpCode1 == 200) {
                    $ch2 = curl_init();
                    curl_setopt($ch2, CURLOPT_URL, 'http://' . $ip . ':6969');
                    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch2, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch2, CURLOPT_POST, 1);
                    curl_setopt($ch2, CURLOPT_POSTFIELDS, http_build_query([
                        'comando' => 'cd /etc/xis && sudo python3 /etc/xis/suspend.py ' . $nome_arquivo . ' > /dev/null 2>/dev/null &'
                    ]));
                    curl_setopt($ch2, CURLOPT_TIMEOUT, 10);
                    curl_exec($ch2);
                    curl_close($ch2);
                }
            });
        }
    }
    
    $loop->run();
}

// ============================================
// PASSO 6: LOG
// ============================================
$data_log  = date('d/m/Y H:i:s');
$log_texto = "SISTEMA: Venceu $total_revendedores revendedores e $total_usuarios usuários (suspensão automática)";
$conn->query("INSERT INTO logs (revenda, validade, texto, userid) VALUES ('Sistema', '$data_log', '$log_texto', '1')");

// Deleta o arquivo DEPOIS do loop
if (file_exists($caminho_completo)) {
    unlink($caminho_completo);
}

$log_message = date('Y-m-d H:i:s') . " - Vencidos: $total_revendedores revendedores, $total_usuarios usuários\n";
file_put_contents(__DIR__ . '/suspender_auto.log', $log_message, FILE_APPEND);

mysqli_close($conn);
?>