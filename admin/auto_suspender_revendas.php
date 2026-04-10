<?php
// auto_suspender_revendas.php - Verifica e suspende revendedores vencidos automaticamente
// CRON: 0 * * * * /usr/bin/php /caminho/para/auto_suspender_revendas.php

include_once '../AegisCore/conexao.php';
include('../vendor/event/autoload.php');
use React\EventLoop\Factory;

$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$token_sistema = md5('auto_suspender_revendas');

date_default_timezone_set('America/Sao_Paulo');
$data_atual = date('Y-m-d H:i:s');

echo "[" . date('H:i:s') . "] Iniciando verificação de revendas vencidas...\n";

// ============================================
// FUNÇÃO RECURSIVA PARA BUSCAR TODA A ÁRVORE
// ============================================
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

// ============================================
// PASSO 1: Buscar TODAS as revendas vencidas
// ============================================
$sql_revendas = "SELECT a.*, at.tipo, at.expira, at.categoriaid, at.limite 
                 FROM accounts a
                 INNER JOIN atribuidos at ON a.id = at.userid
                 WHERE at.expira < '$data_atual' 
                 AND at.expira IS NOT NULL 
                 AND at.expira != ''
                 AND at.tipo = 'Validade'
                 AND at.suspenso = 0";

$result_revendas = $conn->query($sql_revendas);
$total_revendas = $result_revendas->num_rows;

echo "[" . date('H:i:s') . "] Encontradas $total_revendas revendas vencidas\n";

if ($total_revendas == 0) {
    echo "[" . date('H:i:s') . "] Nenhuma revenda vencida encontrada. Finalizando.\n";
    exit;
}

// ============================================
// PASSO 2: Buscar TODA a árvore de cada revenda (CORREÇÃO: recursivo)
// ============================================
$todas_revendas = [];
$todos_ids_afetados = [];

while ($revenda = $result_revendas->fetch_assoc()) {
    $todas_revendas[] = $revenda;
    // CORREÇÃO: Busca recursiva em vez de apenas 1 nível
    buscarTodosRevendedores($conn, $revenda['id'], $todos_ids_afetados);
}

$todos_ids_afetados = array_unique($todos_ids_afetados);

echo "[" . date('H:i:s') . "] Total de IDs afetados (incluindo sub-revendedores): " . count($todos_ids_afetados) . "\n";

// ============================================
// PASSO 3: Buscar todas as contas SSH
// ============================================
$contas_para_suspender = [];

if (!empty($todos_ids_afetados)) {
    $ids_str = implode(",", $todos_ids_afetados);
    $sql_contas = "SELECT ssh.*, at.categoriaid 
                   FROM ssh_accounts ssh 
                   LEFT JOIN atribuidos at ON ssh.byid = at.userid
                   WHERE ssh.byid IN ($ids_str)";
    $result_contas = $conn->query($sql_contas);
    
    while ($conta = $result_contas->fetch_assoc()) {
        // Se não tem categoriaid via JOIN, tenta pegar da revenda principal
        if (empty($conta['categoriaid'])) {
            foreach ($todas_revendas as $rev) {
                if ($rev['id'] == $conta['byid']) {
                    $conta['categoriaid'] = $rev['categoriaid'];
                    break;
                }
            }
        }
        $contas_para_suspender[] = $conta;
    }
}

echo "[" . date('H:i:s') . "] Total de " . count($contas_para_suspender) . " contas SSH a suspender\n";

// ============================================
// PASSO 4: Agrupar contas por categoria
// ============================================
$contas_por_categoria = [];
foreach ($contas_para_suspender as $conta) {
    $categoria = $conta['categoriaid'] ?? 0;
    if (!isset($contas_por_categoria[$categoria])) {
        $contas_por_categoria[$categoria] = [];
    }
    $contas_por_categoria[$categoria][] = $conta;
}

// ============================================
// PASSO 5: Processar cada categoria
// ============================================
$loop = Factory::create();
$servidores_offline = [];
$contas_processadas = 0;
$contas_erro = 0;
$arquivos_temporarios = [];

foreach ($contas_por_categoria as $categoria => $contas) {
    
    $sql_serv = "SELECT * FROM servidores WHERE subid = '$categoria'";
    $result_serv = $conn->query($sql_serv);
    
    if ($result_serv->num_rows == 0) {
        echo "[" . date('H:i:s') . "] Aviso: Nenhum servidor encontrado para categoria $categoria\n";
        continue;
    }
    
    // Criar arquivo com as contas a suspender
    $nome_arquivo = md5(uniqid(rand(), true)) . ".txt";
    $caminho_completo = __DIR__ . '/' . $nome_arquivo;
    $file = fopen($caminho_completo, "w");
    
    foreach ($contas as $conta) {
        $login = $conta['login'];
        fwrite($file, $login . PHP_EOL);
    }
    fclose($file);
    
    // Guardar referência para limpar depois
    $arquivos_temporarios[] = $caminho_completo;
    
    // Enviar para cada servidor da categoria
    while ($servidor = $result_serv->fetch_assoc()) {
        $servidor_id = $servidor['id'];
        $senha_token = getServidorToken($conn, $servidor_id, $token_sistema);
        
        $timeout = 3;
        $socket = @fsockopen($servidor['ip'], 6969, $errno, $errstr, $timeout);
        
        if ($socket) {
            fclose($socket);
            
            // CORREÇÃO: Usar variável local no closure para evitar referência ao arquivo deletado
            $loop->addTimer(0.001, function () use ($servidor, $caminho_completo, $nome_arquivo, $senha_token) {
                // CORREÇÃO: Verificar se o arquivo ainda existe antes de ler
                if (!file_exists($caminho_completo)) {
                    return;
                }
                
                $conteudo = file_get_contents($caminho_completo);
                
                $headers = array('Senha: ' . $senha_token);
                
                // Enviar arquivo para /root/
                $ch1 = curl_init();
                curl_setopt($ch1, CURLOPT_URL, 'http://' . $servidor['ip'] . ':6969');
                curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch1, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch1, CURLOPT_POST, 1);
                curl_setopt($ch1, CURLOPT_POSTFIELDS, http_build_query(array('comando' => 'echo "' . addslashes($conteudo) . '" > /root/' . $nome_arquivo)));
                curl_setopt($ch1, CURLOPT_TIMEOUT, 30);
                curl_exec($ch1);
                curl_close($ch1);
                
                // CORREÇÃO: Usar suspend.py (BLOQUEIA) em vez de delete.py (EXCLUI)
                $ch2 = curl_init();
                curl_setopt($ch2, CURLOPT_URL, 'http://' . $servidor['ip'] . ':6969');
                curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch2, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch2, CURLOPT_POST, 1);
                curl_setopt($ch2, CURLOPT_POSTFIELDS, http_build_query(array('comando' => 'cd /etc/xis && sudo python3 /etc/xis/suspend.py ' . $nome_arquivo . ' > /dev/null 2>/dev/null &')));
                curl_setopt($ch2, CURLOPT_TIMEOUT, 10);
                curl_exec($ch2);
                curl_close($ch2);
            });
            
            $contas_processadas++;
        } else {
            $servidores_offline[] = $servidor['ip'];
            $contas_erro++;
        }
    }
    
    // CORREÇÃO CRÍTICA: NÃO deletar o arquivo aqui!
    // O arquivo será deletado DEPOIS do $loop->run()
}

// CORREÇÃO: Executar o loop ANTES de limpar os arquivos
$loop->run();

// CORREÇÃO: Agora sim, limpar os arquivos temporários
foreach ($arquivos_temporarios as $arq) {
    if (file_exists($arq)) {
        unlink($arq);
    }
}

// ============================================
// PASSO 6: Atualizar banco de dados
// ============================================
if ($contas_processadas > 0 || count($contas_para_suspender) > 0) {
    
    // Suspender as revendas principais
    foreach ($todos_ids_afetados as $rev_id) {
        $conn->query("UPDATE atribuidos SET suspenso = '1' WHERE userid = '$rev_id'");
        // CORREÇÃO: Também atualiza accounts.status
        $conn->query("UPDATE accounts SET status = 'Suspenso' WHERE id = '$rev_id'");
    }
    
    // Suspender todas as contas SSH
    foreach ($contas_para_suspender as $conta) {
        $login = $conta['login'];
        // CORREÇÃO: Também atualiza status = 'Offline'
        $conn->query("UPDATE ssh_accounts SET mainid = 'Suspenso', status = 'Offline' WHERE login = '$login'");
    }
    
    echo "[" . date('H:i:s') . "] Suspensão concluída!\n";
    echo "[" . date('H:i:s') . "] - $total_revendas revendas suspensas (+ sub-revendedores: " . count($todos_ids_afetados) . ")\n";
    echo "[" . date('H:i:s') . "] - " . count($contas_para_suspender) . " contas SSH suspensas\n";
    
    // Log da operação
    $data_log = date('d-m-Y H:i:s');
    $log_text = "Suspensão automática: $total_revendas revendas (" . count($todos_ids_afetados) . " total) e " . count($contas_para_suspender) . " contas";
    $conn->query("INSERT INTO logs (revenda, validade, texto, userid) VALUES ('SISTEMA', '$data_log', '$log_text', '0')");
    
} else {
    echo "[" . date('H:i:s') . "] Nenhuma conta foi suspensa. Servidores offline: " . implode(', ', $servidores_offline) . "\n";
}

mysqli_close($conn);
?>
