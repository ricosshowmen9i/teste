<?php
require_once __DIR__ . '/Logger.php';

class UserProcessor
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }



    /**
     * Processa compras de usuário (atualização de limite e validade)
     */
    public function processPurchase($payment)
    {
        try {
            Logger::logInfo("Processando compra para Usuário ID: {$payment['iduser']}, Valor: {$payment['valor']}, Limite: {$payment['limite']}");

            // Verificar se o usuário existe na tabela ssh_accounts
            $stmt = $this->pdo->prepare("SELECT * FROM ssh_accounts WHERE id = :byid");
            $stmt->bindParam(':byid', $payment['iduser'], PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $userData = $stmt->fetch(PDO::FETCH_ASSOC);
                $expira = $userData['expira'];
                $limiteAtual = $userData['limite'];
                $valorAtual = $userData['valor'];
                $idPlanoAtual = $userData['id_plano'];

                // Buscar dias e valor do plano
                $novoIdPlano = $payment['id_plano'];
                $diasPlano = 31; // Valor padrão de 31 dias
                $valorPlano = 0; // Valor padrão
                
                if (!empty($novoIdPlano)) {
                $stmt_plano = $this->pdo->prepare("SELECT duracao_dias, valor FROM planos_pagamento WHERE id = :id_plano LIMIT 1");
                $stmt_plano->bindParam(':id_plano', $novoIdPlano, PDO::PARAM_INT);
                $stmt_plano->execute();
                    $planoData = $stmt_plano->fetch(PDO::FETCH_ASSOC);
                    if ($planoData) {
                        $diasPlano = $planoData['duracao_dias'];
                        $valorPlano = $planoData['valor'];
                        Logger::logInfo("Plano encontrado para id_plano: $novoIdPlano - Dias: $diasPlano, Valor: $valorPlano");
                    } else {
                        Logger::logWarning("Plano não encontrado para id_plano: $novoIdPlano - Usando valores padrão");
                    }
                } else {
                    Logger::logWarning("ID do plano não fornecido - Usando valores padrão");
                }

                $agora = time();
                $expira_timestamp = strtotime($expira);
                $tempo_restante = $expira_timestamp - $agora;

                // Calcular nova data de expiração usando dias do plano
                if ($tempo_restante > 0) {
                    $expira_days_left = round($tempo_restante / (60 * 60 * 24));
                    $total_days = $expira_days_left + $diasPlano;
                    $data_validade = date('Y-m-d H:i:s', strtotime("+$total_days days"));
                } else {
                    $data_validade = date('Y-m-d H:i:s', strtotime("+$diasPlano days"));
                }

                // Substituir o limite atual pelo novo limite
                $novoLimite = $payment['limite'];
                // Valor vem do plano, não do pagamento
                // $valorPlano já definido acima
                // Substituir o id_plano atual pelo novo id_plano
                // $novoIdPlano já definido acima

                $nova_expira_timestamp = strtotime($data_validade);
                $dias_para_servidores = ceil(($nova_expira_timestamp - $agora) / (60 * 60 * 24));
                Logger::logInfo("Nova data de expiração: {$data_validade} - Dias calculados para servidores: {$dias_para_servidores} - Dias do plano: {$diasPlano} - Limite substituído: {$limiteAtual} → {$novoLimite} - Valor substituído: {$valorAtual} → {$valorPlano} - ID Plano substituído: {$idPlanoAtual} → {$novoIdPlano}");

                // Não inicia transação aqui pois já foi iniciada no PaymentProcessor
// Buscar dias e valor do plano
$novoIdPlano = $payment['id_plano'];
$diasPlano = 31; // Valor padrão de 31 dias
$valorPlano = 0; // Valor padrão

if (!empty($novoIdPlano)) {
    $stmt_plano = $this->pdo->prepare("SELECT duracao_dias, valor FROM planos_pagamento WHERE id = :id_plano LIMIT 1");
    $stmt_plano->bindParam(':id_plano', $novoIdPlano, PDO::PARAM_INT);
    $stmt_plano->execute();
    $planoData = $stmt_plano->fetch(PDO::FETCH_ASSOC);
    if ($planoData) {
        $diasPlano = $planoData['duracao_dias'];
        $valorPlano = $planoData['valor'];  // <-- ESTA LINHA JÁ EXISTE
        Logger::logInfo("Plano encontrado para id_plano: $novoIdPlano - Dias: $diasPlano, Valor: $valorPlano");
    } else {
        Logger::logWarning("Plano não encontrado para id_plano: $novoIdPlano - Usando valores padrão");
    }
} else {
    Logger::logWarning("ID do plano não fornecido - Usando valores padrão");
}
                // Atualiza a data de expiração, o limite, o valor e o id_plano
                $stmt = $this->pdo->prepare("UPDATE ssh_accounts SET expira = :expira, limite = :limite, valor = :valor, id_plano = :id_plano WHERE id = :byid");
                $stmt->bindParam(':expira', $data_validade, PDO::PARAM_STR);
                $stmt->bindParam(':limite', $novoLimite, PDO::PARAM_INT);
                $stmt->bindParam(':valor', $valorPlano, PDO::PARAM_STR);
                $stmt->bindParam(':id_plano', $novoIdPlano, PDO::PARAM_INT);
                $stmt->bindParam(':byid', $payment['iduser'], PDO::PARAM_INT);
                $result = $stmt->execute();

                if ($result === false) {
                    Logger::logError("Erro na atualização da data de expiração, limite, valor e id_plano para usuário: " . $this->pdo->errorInfo()[2]);
                    $this->pdo->rollBack();
                    return false;
                }

                // Buscar o token do admin (byid = 1)
                $stmt_token = $this->pdo->prepare("SELECT token FROM api WHERE byid = 1 LIMIT 1");
                $stmt_token->execute();
                $adminToken = $stmt_token->fetchColumn();

                if (empty($adminToken)) {
                    Logger::logError("FALHA CRÍTICA: adminToken está vazio em processPurchase. Verifique a tabela 'api' para byid=1.");
                    return false;
                }

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

                // Preparar payload conforme o status do usuário (Ativo vs Expirado/Suspenso)
                // Se $tempo_restante > 0, o usuário estava ativo -> Renovação (/editar)
                // Se $tempo_restante <= 0, o usuário estava expirado -> Reativação (/criar)
                $payload = '';
                $endpoint = '';
                
                if ($tempo_restante > 0) {
                    // Usuário ativo: Renovar (Editar)
                    $endpoint = '/editar';
                    $dadosPayload = [
                        'login_antigo' => $userData['login'],
                        'login_novo' => $userData['login'],
                        'senha' => $userData['senha'],
                        'dias' => (int)$dias_para_servidores, // Forçar inteiro
                        'limite' => (int)$novoLimite,        // Forçar inteiro
                        'uuid' => $userData['uuid'] ?? null,
                        'tipo' => $userData['tipo'] ?? 'ssh'
                    ];
                    Logger::logDebug("Usuário ativo (tempo restante > 0). Usando endpoint /editar.");
                } else {
                    // Usuário expirado: Reativar (Criar)
                    $endpoint = '/criar';
                    $dadosPayload = [
                        'login' => $userData['login'],
                        'senha' => $userData['senha'],
                        'dias' => (int)$dias_para_servidores, // Forçar inteiro
                        'limite' => (int)$novoLimite,        // Forçar inteiro
                        'uuid' => $userData['uuid'] ?? null,
                        'tipo' => $userData['tipo'] ?? 'ssh'
                    ];
                    Logger::logDebug("Usuário expirado (tempo restante <= 0). Usando endpoint /criar.");
                }

                $payload = json_encode([$adminToken, $dadosPayload]);

                // Montar comandos para todos os servidores
                $comandos = [];
                foreach ($servidores as $srv) {
                    $ipServidor = str_replace(['http://', 'https://'], '', $srv['dominio']);
                    $url = "http://{$ipServidor}:9001{$endpoint}";
                    
                    Logger::logDebug("Preparando requisição para {$srv['nome']} ($url) | Login: {$userData['login']}");
                    
                    $comandos[] = [
                        'url' => $url,
                        'payload' => $payload,
                        'nome' => $srv['nome']
                    ];
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
                foreach ($resultados as $resultado) {
                    if ($resultado['erro']) {
                        Logger::logError("Erro ao atualizar usuário no servidor {$resultado['nome']}: " . $resultado['erro']);
                    } else {
                        Logger::logInfo("Resposta Servidor {$resultado['nome']} (editar): HTTP {$resultado['httpcode']} | Resposta: " . ($resultado['resposta'] ?: 'VAZIA'));
                    }
                }

                // --- Chamada ao whatuser.php após compra do usuário (nova conta) ---
                // Envia notificação WhatsApp se o usuário tiver contato cadastrado
                if (!empty($userData['contato'])) {
                    try {
                        // Construir URL dinamicamente usando $_SERVER
                        $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
                            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
                        $whatUserURL = ($isHttps ? "https://" : "http://") . $_SERVER["HTTP_HOST"] . "/pages/usuarios/whatuser.php";
                        
                            // Calcular dias restantes para exibição
                            $dias_validade = ceil((strtotime($data_validade) - time()) / (60 * 60 * 24));
                            
                            // Montar dados para usuário FINAL (usa 'idusercriar' = função 'criarusuario')
                            $postData = array(
                                'idusercriar' => $userData['byid'], // 'idusercriar' para usuário final
                                'login' => $userData['login'],
                                'senha' => $userData['senha'],
                                'uuid' => $userData['uuid'] ?? null,
                                'nome' => $userData['nome'] ?? '',
                                'contato' => $userData['contato'],
                                'iduser' => $userData['byid'],
                                'limite' => $novoLimite,
                                'validade' => $dias_validade, // Em dias
                                'categoria' => $userData['categoriaid']
                            );

                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, $whatUserURL);
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
                                Logger::logError("Erro cURL ao enviar notificação WhatsApp (compra) para {$whatUserURL}: " . $curlError);
                            } else {
                                Logger::logInfo("Notificação WhatsApp enviada para compra via whatuser.php - URL: {$whatUserURL} - HTTP {$httpCode} - Resposta: " . substr($response, 0, 100));
                            }
                    } catch (Exception $e) {
                        Logger::logWarning("Erro ao enviar notificação WhatsApp (compra): " . $e->getMessage());
                    }
                }
                // --- Fim chamada whatuser.php ---

                Logger::logInfo("Compra de usuário processada com sucesso para usuário ID: " . $payment['iduser'] . " - Nova expiração: " . $data_validade . " - Limite substituído: " . $limiteAtual . " → " . $novoLimite . " - Valor substituído: " . $valorAtual . " → " . $valorPlano . " - ID Plano substituído: " . $idPlanoAtual . " → " . $novoIdPlano);
            } else {
                Logger::logInfo("Usuário não encontrado na tabela ssh_accounts para ID: {$payment['iduser']}. Nenhuma ação realizada.");
            }
        } catch (Exception $e) {
            Logger::logError("Erro ao processar compra de usuário: " . $e->getMessage());
            throw $e;
        }
    }
}
