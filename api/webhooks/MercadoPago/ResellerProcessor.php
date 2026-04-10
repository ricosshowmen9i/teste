<?php
require_once __DIR__ . '/Logger.php';
class ResellerProcessor
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Processa compras de revenda - adiciona valor ao limite existente
     */
    public function processPurchase($payment)
    {
        try {
            // Primeiro verificar se está suspenso e reativar se necessário
            $stmt_verifica_suspenso = $this->pdo->prepare("SELECT id, suspenso, limite, expira FROM atribuidos WHERE userid = :userid");
            $stmt_verifica_suspenso->bindParam(':userid', $payment['iduser'], PDO::PARAM_INT);
            $stmt_verifica_suspenso->execute();

            if ($stmt_verifica_suspenso->rowCount() > 0) {
                $row = $stmt_verifica_suspenso->fetch(PDO::FETCH_ASSOC);
                $suspenso = $row['suspenso'];
                $limiteAnterior = $row['limite'];
                $expiraAnterior = $row['expira'];
                
                Logger::logInfo("Compra de revenda - Usuário ID: {$payment['iduser']}, Suspenso: " . ($suspenso ?? 'NULL'));
                
                // Processo de reativação se estiver suspenso
                if ($suspenso == 1) {
                    Logger::logInfo("Atribuição está suspensa. Iniciando processo de reativação...");
                    
                    // CRÍTICO: Marca como ativo NO BANCO PRIMEIRO, antes de tentar servidores
                    try {
                        $stmt_reativar = $this->pdo->prepare("UPDATE atribuidos SET suspenso = 0 WHERE userid = ?");
                        $resultReativar = $stmt_reativar->execute([$payment['iduser']]);
                        
                        if ($resultReativar) {
                            $linhasAfetadas = $stmt_reativar->rowCount();
                            Logger::logInfo("✓ ATRIBUIÇÃO REATIVADA NO BANCO (suspenso = 0) - Linhas afetadas: $linhasAfetadas - Usuário ID: {$payment['iduser']}");
                        } else {
                            Logger::logError("✗ FALHA CRÍTICA ao reativar atribuição no banco - Erro: " . implode(' ', $this->pdo->errorInfo()));
                            throw new Exception("Falha ao reativar atribuição no banco de dados");
                        }
                    } catch (Exception $e) {
                        Logger::logError("✗ ERRO CRÍTICO ao reativar atribuição: " . $e->getMessage());
                        throw $e;
                    }
                    
                    // Agora tenta reativar nos servidores (não-crítico, pode falhar sem afetar o banco)
                    try {
                        Logger::logInfo("Iniciando reativação de contas nos servidores...");
                        
                        $accounts = [];
                        $revendasToProcess = [$payment['iduser']];
                        $allRevendasProcessed = [];
                        
                        while (!empty($revendasToProcess)) {
                            $currentUserId = array_shift($revendasToProcess);
                            if (!in_array($currentUserId, $allRevendasProcessed)) {
                                $allRevendasProcessed[] = $currentUserId;

                                // Pegar as contas associadas ao revendedor/sub-revendedor atual
                                $stmt = $this->pdo->prepare('SELECT login, senha, expira, limite, tipo, uuid FROM ssh_accounts WHERE byid = :byid');
                                $stmt->bindParam(':byid', $currentUserId, PDO::PARAM_INT);
                                $stmt->execute();
                                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                    $login = $row['login'];
                                    $senha = $row['senha'];
                                    $expira = $row['expira'];
                                    $limite = $row['limite'];
                                    $tipo = $row['tipo'];
                                    $uuid = $row['uuid'];
                                    $dias_restantes = floor((strtotime($expira) - time()) / (60 * 60 * 24)) + 2;

                                    // Adiciona dados da conta diretamente ao array
                                    $accounts[] = [
                                        'login' => $login,
                                        'senha' => $senha,
                                        'dias' => $dias_restantes,
                                        'limite' => $limite,
                                        'uuid' => $uuid ?? 'null',
                                        'tipo' => $tipo ?? 'ssh'
                                    ];
                                }

                                // Pegar os sub-revendedores do revendedor/sub-revendedor atual
                                $stmtSubRevendas = $this->pdo->prepare("SELECT id FROM accounts WHERE byid = :byid");
                                $stmtSubRevendas->bindParam(':byid', $currentUserId, PDO::PARAM_INT);
                                $stmtSubRevendas->execute();
                                while ($row = $stmtSubRevendas->fetch(PDO::FETCH_ASSOC)) {
                                    $revendasToProcess[] = $row['id'];
                                }
                            }
                        }

                        // Buscar o token do admin (byid = 1)
                        $stmt_token = $this->pdo->prepare("SELECT token FROM api WHERE byid = 1 LIMIT 1");
                        $stmt_token->execute();
                        $adminToken = $stmt_token->fetchColumn();

                        // Buscar servidores do banco
                        $stmt_servidor = $this->pdo->query('SELECT ip, nome FROM servidores');
                        $servidoresBanco = $stmt_servidor->fetchAll(PDO::FETCH_ASSOC);

                        $servidores = [];
                        $ipsProcessados = [];
                        foreach ($servidoresBanco as $servidor) {
                            if (in_array($servidor['ip'], $ipsProcessados)) {
                                continue;
                            }
                            $servidores[] = [
                                'dominio' => $servidor['ip'],
                                'nome' => $servidor['nome']
                            ];
                            $ipsProcessados[] = $servidor['ip'];
                        }

                        // Preparar comandos para criação das contas
                        $comandos = [];
                        foreach ($accounts as $dadosCriacao) {
                            // Montar comandos para todos os servidores
                            foreach ($servidores as $srv) {
                                $ipServidor = str_replace(['http://', 'https://'], '', $srv['dominio']);
                                $url = "http://{$ipServidor}:9001/criar";
                                
                                Logger::logDebug("Preparando reativação (criar) para {$srv['nome']} ($url) | Login: {$dadosCriacao['login']}");
                                
                                $payload = json_encode([$adminToken, $dadosCriacao]);
                                $comandos[] = [
                                    'url' => $url,
                                    'payload' => $payload,
                                    'nome' => $srv['nome']
                                ];
                            }
                        }

                        // Função para enviar múltiplos POSTs em paralelo usando curl_multi_*
                        $enviarComandosServidoresParalelo = function ($comandos) {
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
                            // Executa todas as requisições em paralelo
                            $running = null;
                            do {
                                curl_multi_exec($multiHandle, $running);
                                curl_multi_select($multiHandle);
                            } while ($running > 0);
                            // Coleta as respostas
                            $responses = [];
                            foreach ($curlHandles as $key => $ch) {
                                $responses[$key] = [
                                    'resposta' => curl_multi_getcontent($ch),
                                    'erro' => curl_error($ch),
                                    'httpcode' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
                                    'nome' => $comandos[$key]['nome']
                                ];
                                curl_multi_remove_handle($multiHandle, $ch);
                                curl_close($ch);
                            }
                            curl_multi_close($multiHandle);
                            return $responses;
                        };

                        $resultados = $enviarComandosServidoresParalelo($comandos);

                        // Log dos resultados
                        $sucessos = 0;
                        $falhas = 0;
                        foreach ($resultados as $resultado) {
                            if ($resultado['erro']) {
                                $falhas++;
                                Logger::logError("✗ Erro ao reativar no servidor {$resultado['nome']}: " . $resultado['erro']);
                            } else {
                                $sucessos++;
                                Logger::logInfo("Resposta Servidor {$resultado['nome']} (reativar/criar): HTTP {$resultado['httpcode']} | Resposta: " . ($resultado['resposta'] ?: 'VAZIA'));
                            }
                        }
                        
                        Logger::logInfo("Resultado da reativação nos servidores: $sucessos sucessos, $falhas falhas");
                        
                    } catch (Exception $e) {
                        // Erro ao reativar nos servidores NÃO é crítico
                        // A atribuição já foi marcada como ativa no banco
                        Logger::logWarning("Erro ao reativar contas nos servidores (não-crítico): " . $e->getMessage());
                    }
                }
            } else {
                Logger::logInfo("Nenhuma atribuição encontrada para usuário ID: {$payment['iduser']}. Nenhuma ação realizada.");
                return;
            }

            $novoLimite = $payment['limite'];
            
            // Agora processar a atualização do limite e validade
            // As variáveis $limiteAnterior e $expiraAnterior já foram definidas acima
                // Buscar dias e valor do plano
$id_plano = $payment['id_plano'];
$diasPlano = 31; // Valor padrão de 31 dias
$valorPlano = 0; // Valor padrão

if (!empty($id_plano)) {
    $stmt_plano = $this->pdo->prepare("SELECT duracao_dias, valor FROM planos_pagamento WHERE id = :id_plano LIMIT 1");
    $stmt_plano->bindParam(':id_plano', $id_plano, PDO::PARAM_INT);
    $stmt_plano->execute();
    $planoData = $stmt_plano->fetch(PDO::FETCH_ASSOC);
    if ($planoData) {
        $diasPlano = $planoData['duracao_dias'];
        $valorPlano = $planoData['valor'];  // <-- GARANTA QUE ESTA LINHA EXISTA
        Logger::logInfo("Plano encontrado para id_plano: $id_plano - Dias: $diasPlano, Valor: $valorPlano");
    } else {
        Logger::logWarning("Plano não encontrado para id_plano: $id_plano - Usando valores padrão");
    }
} else {
    Logger::logWarning("ID do plano não fornecido - Usando valores padrão");
}
                // Buscar dias e valor do plano
                $id_plano = $payment['id_plano'];
                $diasPlano = 31; // Valor padrão de 31 dias
                $valorPlano = 0; // Valor padrão
                
                if (!empty($id_plano)) {
                $stmt_plano = $this->pdo->prepare("SELECT duracao_dias, valor FROM planos_pagamento WHERE id = :id_plano LIMIT 1");
                $stmt_plano->bindParam(':id_plano', $id_plano, PDO::PARAM_INT);
                $stmt_plano->execute();
                    $planoData = $stmt_plano->fetch(PDO::FETCH_ASSOC);
                    if ($planoData) {
                        $diasPlano = $planoData['duracao_dias'];
                        $valorPlano = $planoData['valor'];
                        Logger::logInfo("Plano encontrado para id_plano: $id_plano - Dias: $diasPlano, Valor: $valorPlano");
                    } else {
                        Logger::logWarning("Plano não encontrado para id_plano: $id_plano - Usando valores padrão");
                    }
                } else {
                    Logger::logWarning("ID do plano não fornecido - Usando valores padrão");
                }

                // Calcular nova data de validade usando dias do plano
                $agora = time();
                $expira_timestamp = strtotime($expiraAnterior);
                $tempo_restante = $expira_timestamp - $agora;
                if ($tempo_restante > 0) {
                    $expira_days_left = round($tempo_restante / (60 * 60 * 24));
                    $total = $expira_days_left + $diasPlano;
                    $nova_validade = date('Y-m-d H:i:s', strtotime("+$total days", $agora));
                } else {
                    $nova_validade = date('Y-m-d H:i:s', strtotime("+$diasPlano days", $agora));
                }

                $stmt = $this->pdo->prepare("
                    UPDATE atribuidos 
                    SET limite = ?, expira = ?, valor = ? 
                    WHERE userid = ?
                ");
                $stmt->execute([$novoLimite, $nova_validade, $valorPlano, $payment['iduser']]);

                // --- Chamada ao whatatribu.php após compra da revenda (criação) ---
                try {
                    $stmt_revenda = $this->pdo->prepare("SELECT login, contato FROM accounts WHERE id = ? LIMIT 1");
                    $stmt_revenda->execute([$payment['iduser']]);
                    $revendaInfo = $stmt_revenda->fetch(PDO::FETCH_ASSOC);
                    
                    if ($revendaInfo && !empty($revendaInfo['contato'])) {
                        // Construir URL dinamicamente usando $_SERVER
                        $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
                            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
                        $whatAtribuURL = ($isHttps ? "https://" : "http://") . $_SERVER["HTTP_HOST"] . "/pages/revendas/whatatribu.php";

                        // Buscar dados da atribuição atualizada
                        $stmt_atrib = $this->pdo->prepare("SELECT * FROM atribuidos WHERE userid = ? LIMIT 1");
                        $stmt_atrib->execute([$payment['iduser']]);
                        $atribData = $stmt_atrib->fetch(PDO::FETCH_ASSOC);
                            
                            // Parâmetros como no criaratribuicao.php (linhas 19-28)
                            $postData = array(
                                'userid' => $payment['iduser'],
                                'byid' => 1, // Sistema
                                'contato' => $revendaInfo['contato'],
                                'categoriaid' => $atribData['categoriaid'] ?? 0,
                                'limite' => $novoLimite,
                                'limitetest' => $atribData['limitetest'] ?? 0,
                                'tipo' => $atribData['tipo'] ?? 'Validade',
                                'expira' => $nova_validade
                            );

                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, $whatAtribuURL);
                            curl_setopt($ch, CURLOPT_POST, 1);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

                            $response = curl_exec($ch);
                            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                            $curlError = curl_error($ch);
                            curl_close($ch);
                            
                            if ($curlError) {
                                Logger::logError("Erro cURL ao enviar notificação WhatsApp (atribuição revenda) para {$whatAtribuURL}: " . $curlError);
                            } else {
                                Logger::logInfo("Notificação WhatsApp enviada para compra de revenda via whatatribu.php - URL: {$whatAtribuURL} - HTTP {$httpCode} - Resposta: " . substr($response, 0, 100));
                            }
                    }
                } catch (Exception $e) {
                    Logger::logWarning("Erro ao enviar notificação WhatsApp (compra revenda): " . $e->getMessage());
                }
                // --- Fim chamada whatatribu.php ---

                Logger::logInfo("Compra de revenda processada - Usuário: {$payment['iduser']}, Limite anterior: {$limiteAnterior}, Novo limite: {$novoLimite}, Validade anterior: {$expiraAnterior}, Nova validade: {$nova_validade}, Valor: {$valorPlano}");
        } catch (Exception $e) {
            Logger::logError("Erro ao processar compra de revenda: " . $e->getMessage());
            throw $e;
        }
    }


}
