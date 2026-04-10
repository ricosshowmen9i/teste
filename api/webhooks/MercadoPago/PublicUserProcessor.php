<?php
require_once __DIR__ . '/Logger.php';

/**
 * Processador de Usuários para Pagamentos Públicos
 * Cria automaticamente contas SSH quando o pagamento é aprovado
 * via página pública de vendas
 */
class PublicUserProcessor
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Envia comandos para múltiplos servidores em paralelo
     */
    private function enviarComandosServidoresParalelo($comandos)
    {
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
     * Cria o usuário nos servidores SSH
     */
    private function criarUsuarioNosServidores($login, $senha, $dias, $limite, $uuid, $tipo, $categoriaid)
    {
        try {
            // Buscar IPs e nomes dos servidores
            $sqlServidores = "SELECT ip, nome FROM servidores WHERE subid = :categoriaid";
            $stmtServ = $this->pdo->prepare($sqlServidores);
            $stmtServ->bindParam(':categoriaid', $categoriaid, PDO::PARAM_INT);
            $stmtServ->execute();
            $servidoresBanco = $stmtServ->fetchAll(PDO::FETCH_ASSOC);

            if (empty($servidoresBanco)) {
                Logger::logInfo("Nenhum servidor encontrado para categoriaid: {$categoriaid}");
                return true; // Continua mesmo sem servidores
            }

            // Buscar o token do admin (byid = 1)
            $stmt_token = $this->pdo->prepare("SELECT token FROM api WHERE byid = 1 LIMIT 1");
            $stmt_token->execute();
            $adminToken = $stmt_token->fetchColumn();

            if (!$adminToken) {
                Logger::logError("Token do admin não encontrado");
                return false;
            }

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

            // Montar dados de criação
            $dadosCriacao = [
                'login' => $login,
                'senha' => $senha,
                'dias' => $dias,
                'limite' => $limite,
                'uuid' => $uuid,
                'tipo' => $tipo,
                'is_teste' => 0
            ];

            // Montar comandos para todos os servidores
            $comandos = [];
            foreach ($servidores as $srv) {
                $ipServidor = str_replace(['http://', 'https://'], '', $srv['dominio']);
                $url = "http://{$ipServidor}:9001/criar";
                
                Logger::logDebug("Preparando criação para {$srv['nome']} ($url) | Login: {$login}");
                
                $payload = json_encode([$adminToken, $dadosCriacao]);
                $comandos[] = [
                    'url' => $url,
                    'payload' => $payload,
                    'nome' => $srv['nome']
                ];
            }

            // Enviar para os servidores
            $resultados = $this->enviarComandosServidoresParalelo($comandos);

            $sucessoEmAlgum = false;
            foreach ($resultados as $res) {
                $respostaJson = json_decode($res['resposta'], true);
                if (is_array($respostaJson) && isset($respostaJson['status']) && $respostaJson['status'] === 'success') {
                    $sucessoEmAlgum = true;
                    Logger::logInfo("Resposta Servidor {$res['nome']} (criar): HTTP {$res['httpcode']} | Resposta: {$res['resposta']}");
                } elseif (!$res['erro'] && $res['httpcode'] === 200) {
                    $sucessoEmAlgum = true;
                    Logger::logInfo("Resposta Servidor {$res['nome']} (criar): HTTP {$res['httpcode']} | Resposta: {$res['resposta']}");
                } else {
                    Logger::logError("Erro ao criar usuário no servidor {$res['nome']}: " . ($res['erro'] ?: "HTTP {$res['httpcode']} - Resposta: {$res['resposta']}"));
                }
            }

            return $sucessoEmAlgum;

        } catch (Exception $e) {
            Logger::logError("Erro ao criar usuário nos servidores: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Processa a criação de um novo usuário após pagamento aprovado
     */
    public function processNewUser($payment)
    {
        try {
            $paymentId = $payment['payment_id'];
            $byid = $payment['byid'];
            $limite = intval($payment['limite']);
            $id_plano = $payment['id_plano'];
            $cliente_nome = $payment['cliente_nome'] ?? 'Cliente';
            $cliente_email = $payment['cliente_email'] ?? '';
            $cliente_telefone = $payment['cliente_telefone'] ?? '';

            // Buscar dados do plano
            $stmt = $this->pdo->prepare("SELECT * FROM planos_pagamento WHERE id = ?");
            $stmt->execute([$id_plano]);
            $plano = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$plano) {
                Logger::logError("Plano não encontrado para pagamento público: {$paymentId}");
                return false;
            }

            // Verificar se é plano de revenda ou usuário
            $tipoPlano = $plano['tipo'] ?? 'usuario';
            
            if ($tipoPlano === 'revenda') {
                // Processar como revenda
                return $this->processNewRevenda($payment, $plano);
            }

            // Continuar processamento como usuário
            $duracao_dias = intval($plano['duracao_dias']);
            $valor = floatval($plano['valor']);

            // Buscar categoriaid da tabela api conforme byid
            $stmt = $this->pdo->prepare("SELECT categoriaid FROM api WHERE byid = ? LIMIT 1");
            $stmt->execute([$byid]);
            $categoriaid = $stmt->fetchColumn();
            
            // Se não encontrar na api, usar valor padrão 1
            if (!$categoriaid) {
                $categoriaid = 1;
                Logger::logInfo("Categoriaid não encontrado para byid {$byid}, usando padrão: 1");
            }

            // Gerar login e senha únicos
            $login = $this->generateUniqueLogin($cliente_nome);
            $senha = $this->generatePassword();

            // Calcular data de expiração
            $data_expiracao = new DateTime();
            $data_expiracao->add(new DateInterval("P{$duracao_dias}D"));
            $expira = $data_expiracao->format('Y-m-d H:i:s');

            // Gerar UUID para xray
            $uuid = $this->generateUUID();

            // Inserir usuário no banco
            $stmt = $this->pdo->prepare("
                INSERT INTO ssh_accounts 
                (login, senha, limite, byid, tipo, categoriaid, expira, nome, contato, uuid, nivel, status, valor, id_plano) 
                VALUES 
                (:login, :senha, :limite, :byid, :tipo, :categoriaid, :expira, :nome, :contato, :uuid, :nivel, :status, :valor, :id_plano)
            ");

            $userData = [
                ':login' => $login,
                ':senha' => $senha,
                ':limite' => $limite,
                ':byid' => $byid,
                ':tipo' => 'xray', // tipo padrão
                ':categoriaid' => $categoriaid,
                ':expira' => $expira,
                ':nome' => $cliente_nome,
                ':contato' => $cliente_telefone ?: $cliente_email,
                ':uuid' => $uuid,
                ':nivel' => 1, // nível usuário
                ':status' => 1, // ativo
                ':valor' => $valor,
                ':id_plano' => $id_plano
            ];

            if (!$stmt->execute($userData)) {
                Logger::logError("Erro ao criar usuário para pagamento: {$paymentId}");
                return false;
            }

            $newUserId = $this->pdo->lastInsertId();

            // Criar usuário nos servidores SSH
            $this->criarUsuarioNosServidores($login, $senha, $duracao_dias, $limite, $uuid, 'xray', $categoriaid);

            // Atualizar pagamento com o ID do usuário criado e o login
            $stmt = $this->pdo->prepare("UPDATE pagamentos SET iduser = ?, login = ? WHERE payment_id = ?");
            $stmt->execute([$newUserId, $login, $paymentId]);

            // Enviar credenciais por WhatsApp se tiver telefone
            if (!empty($cliente_telefone)) {
                $this->sendWhatsAppCredentials($byid, $login, $senha, $uuid, $cliente_nome, $cliente_telefone, $limite, $duracao_dias, $categoriaid);
            }

            // Enviar credenciais por e-mail ou Telegram (opcional)
            $this->sendCredentials($cliente_email, $cliente_nome, $login, $senha, $expira, $limite);

            Logger::logInfo("Usuário criado com sucesso via pagamento público: login={$login}, payment_id={$paymentId}, user_id={$newUserId}");

            return [
                'user_id' => $newUserId,
                'login' => $login,
                'senha' => $senha,
                'expira' => $expira,
                'limite' => $limite
            ];

        } catch (Exception $e) {
            Logger::logError("Erro ao processar novo usuário de pagamento público: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Gera um login único baseado no nome do cliente
     */
    private function generateUniqueLogin($nome)
    {
        // Limpar nome - remover caracteres especiais e espaços
        $login_base = preg_replace('/[^a-zA-Z0-9]/', '', $nome);
        $login_base = strtolower(substr($login_base, 0, 8)); // Limitar a 8 caracteres

        if (empty($login_base)) {
            $login_base = 'user';
        }

        // Adicionar número aleatório
        $login = $login_base . rand(100, 9999);

        // Verificar se já existe e gerar outro se necessário
        $tentativas = 0;
        while ($this->loginExists($login) && $tentativas < 10) {
            $login = $login_base . rand(1000, 99999);
            $tentativas++;
        }

        return $login;
    }

    /**
     * Verifica se um login já existe
     */
    private function loginExists($login)
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM ssh_accounts WHERE login = ?");
        $stmt->execute([$login]);
        $count = $stmt->fetchColumn();
        
        if ($count > 0) return true;

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM accounts WHERE login = ?");
        $stmt->execute([$login]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Gera uma senha aleatória
     */
    private function generatePassword($length = 8)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[rand(0, strlen($chars) - 1)];
        }
        return $password;
    }

    /**
     * Gera um UUID v4 para conexões xray
     */
    private function generateUUID()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * Envia as credenciais para o cliente por e-mail
     */
    private function sendCredentials($email, $nome, $login, $senha, $expira, $limite)
    {
        if (empty($email)) {
            Logger::logInfo("E-mail não fornecido, credenciais não enviadas para login: {$login}");
            return false;
        }

        try {
            // Buscar configurações do painel
            $stmt = $this->pdo->prepare("SELECT * FROM config WHERE byid = 1 LIMIT 1");
            $stmt->execute();
            $config = $stmt->fetch(PDO::FETCH_ASSOC);
            $titulo_painel = $config['title'] ?? 'Painel SSH';
            
            // Preparar e-mail
            $assunto = "Suas credenciais de acesso - {$titulo_painel}";
            
            $mensagem = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; background: #1a1a2e; color: #fff; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #16213e; border-radius: 10px; }
                    .header { text-align: center; padding: 20px; background: linear-gradient(135deg, #00d4ff, #7c3aed); border-radius: 10px 10px 0 0; }
                    .content { padding: 20px; }
                    .credential { background: #0f3460; padding: 15px; margin: 10px 0; border-radius: 8px; }
                    .credential label { color: #888; font-size: 12px; }
                    .credential value { font-size: 18px; font-weight: bold; color: #00d4ff; display: block; }
                    .footer { text-align: center; padding: 20px; color: #888; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>{$titulo_painel}</h1>
                    </div>
                    <div class='content'>
                        <p>Olá <strong>{$nome}</strong>,</p>
                        <p>Seu pagamento foi aprovado! Aqui estão suas credenciais de acesso:</p>
                        
                        <div class='credential'>
                            <label>Login:</label>
                            <value>{$login}</value>
                        </div>
                        
                        <div class='credential'>
                            <label>Senha:</label>
                            <value>{$senha}</value>
                        </div>
                        
                        <div class='credential'>
                            <label>Limite de Conexões:</label>
                            <value>{$limite}</value>
                        </div>
                        
                        <div class='credential'>
                            <label>Válido até:</label>
                            <value>" . date('d/m/Y H:i', strtotime($expira)) . "</value>
                        </div>
                        
                        <p>Guarde essas informações em local seguro.</p>
                    </div>
                    <div class='footer'>
                        <p>Este é um e-mail automático, por favor não responda.</p>
                    </div>
                </div>
            </body>
            </html>
            ";

            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type: text/html; charset=UTF-8\r\n";
            $headers .= "From: {$titulo_painel} <noreply@" . $_SERVER['HTTP_HOST'] . ">\r\n";

            // Tentar enviar e-mail
            if (function_exists('mail')) {
                mail($email, $assunto, $mensagem, $headers);
                Logger::logInfo("E-mail com credenciais enviado para: {$email}");
            } else {
                Logger::logInfo("Função mail() não disponível. Credenciais não enviadas para: {$email}");
            }

            return true;

        } catch (Exception $e) {
            Logger::logError("Erro ao enviar e-mail de credenciais: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Envia credenciais via WhatsApp
     */
    private function sendWhatsAppCredentials($byid, $login, $senha, $uuid, $nome, $telefone, $limite, $validade, $categoriaid)
    {
        try {
            // Normalizar número de telefone
            $telefoneNormalizado = $this->normalizarNumeroContato($telefone);
            if (!$telefoneNormalizado) {
                Logger::logInfo("Número de telefone inválido para WhatsApp: {$telefone}");
                return false;
            }

            // Buscar sessionId da tabela whatsapp
            $stmt = $this->pdo->prepare("SELECT sessionId FROM whatsapp WHERE byid = ?");
            $stmt->execute([$byid]);
            $whatsappData = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$whatsappData || empty($whatsappData['sessionId'])) {
                Logger::logInfo("WhatsApp não configurado para byid: {$byid}");
                return false;
            }

            $sessionId = $whatsappData['sessionId'];

            // Buscar mensagem personalizada
            $stmt = $this->pdo->prepare("SELECT mensagem, status FROM mensagens WHERE funcao = 'criarusuario' AND byid = ?");
            $stmt->execute([$byid]);
            $mensagemData = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$mensagemData || $mensagemData['status'] != 1) {
                Logger::logInfo("Mensagem WhatsApp desabilitada ou não encontrada para byid: {$byid}");
                return false;
            }

            // Substituir variáveis na mensagem
            $mensagem = $mensagemData['mensagem'];
            $dominio = $_SERVER['HTTP_HOST'] ?? 'sistema';
            
            $mensagem = str_replace('{login}', $login, $mensagem);
            $mensagem = str_replace('{senha}', $senha, $mensagem);
            $mensagem = str_replace('{uuid}', $uuid ?? '', $mensagem);
            $mensagem = str_replace('{nome}', $nome, $mensagem);
            $mensagem = str_replace('{limite}', $limite, $mensagem);
            $mensagem = str_replace('{validade}', $validade . ' dias', $mensagem);
            $mensagem = str_replace('{dominio}', $dominio, $mensagem);

            // Buscar URL e API key do admin
            $stmtAdmin = $this->pdo->prepare("SELECT url, api_key FROM whatsapp WHERE byid = 1 LIMIT 1");
            $stmtAdmin->execute();
            $adminData = $stmtAdmin->fetch(PDO::FETCH_ASSOC);
            
            $apikey = isset($adminData['api_key']) && $adminData['api_key'] 
                ? $adminData['api_key'] 
                : "a076c914af55d544fbf529032ed07197f91499008488c8af07550fe467376829";
            $apiurl = isset($adminData['url']) && $adminData['url'] 
                ? rtrim($adminData['url'], '/') 
                : "https://api-whats.painelwebpro.com.br";

            // Enviar via cURL
            $curl = curl_init();
            
            curl_setopt_array($curl, [
                CURLOPT_URL => "$apiurl/message/sendText/$sessionId",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => json_encode([
                    "number" => $telefoneNormalizado,
                    "text" => $mensagem,
                    "delay" => 0
                ]),
                CURLOPT_HTTPHEADER => [
                    "Content-Type: application/json",
                    "apikey: $apikey"
                ],
            ]);

            $response = curl_exec($curl);
            $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            if ($http_code >= 200 && $http_code < 300) {
                Logger::logInfo("WhatsApp enviado com sucesso para: {$telefoneNormalizado}");
                return true;
            } else {
                Logger::logError("Erro ao enviar WhatsApp. HTTP Code: {$http_code}, Response: {$response}");
                return false;
            }

        } catch (Exception $e) {
            Logger::logError("Erro ao enviar WhatsApp: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Normaliza números de telefone brasileiros para o formato 55DDDNXXXXXXXX
     */
    private function normalizarNumeroContato($numero)
    {
        $numero = trim((string) $numero);
        if ($numero === '') {
            return null;
        }

        // Remove todos os caracteres não numéricos
        $digitos = preg_replace('/\D+/', '', $numero);
        if ($digitos === '') {
            return null;
        }

        // Remove zeros à esquerda se houver mais de 13 dígitos
        $digitos = ltrim($digitos, '0');
        if ($digitos === '') {
            return null;
        }

        // Se tem 10 dígitos (DDD + telefone fixo), adiciona 55
        if (strlen($digitos) === 10) {
            $ddd = substr($digitos, 0, 2);
            if ($ddd >= 11 && $ddd <= 99) {
                $digitos = '55' . $digitos;
            } else {
                return null;
            }
        }

        // Se tem 11 dígitos (DDD + celular), adiciona 55
        if (strlen($digitos) === 11) {
            $ddd = substr($digitos, 0, 2);
            if ($ddd >= 11 && $ddd <= 99) {
                $digitos = '55' . $digitos;
            } else {
                return null;
            }
        }

        // Se tem 12 dígitos e começa com 55, valida o DDD
        if (strlen($digitos) === 12 && strpos($digitos, '55') === 0) {
            $ddd = substr($digitos, 2, 2);
            if ($ddd >= 11 && $ddd <= 99) {
                return $digitos;
            }
            return null;
        }

        // Se tem 13 dígitos e começa com 55, valida o DDD
        if (strlen($digitos) === 13 && strpos($digitos, '55') === 0) {
            $ddd = substr($digitos, 2, 2);
            if ($ddd >= 11 && $ddd <= 99) {
                return $digitos;
            }
            return null;
        }

        return null;
    }

    /**
     * Processa a criação de uma nova revenda após pagamento aprovado
     */
    private function processNewRevenda($payment, $plano)
    {
        try {
            $paymentId = $payment['payment_id'];
            $byid = $payment['byid'];
            $limite = intval($payment['limite']); // Créditos de usuário
            $id_plano = $payment['id_plano'];
            $cliente_nome = $payment['cliente_nome'] ?? 'Revenda';
            $cliente_email = $payment['cliente_email'] ?? '';
            $cliente_telefone = $payment['cliente_telefone'] ?? '';

            $duracao_dias = intval($plano['duracao_dias']);
            $valor = floatval($plano['valor']);

            // Buscar categoriaid da tabela api conforme byid
            $stmt = $this->pdo->prepare("SELECT categoriaid, tipo FROM api WHERE byid = ? LIMIT 1");
            $stmt->execute([$byid]);
            $apiData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $categoriaid = $apiData['categoriaid'] ?? 1;
            $tipoAtribuicao = $apiData['tipo'] ?? 'Credito';

            // Gerar login e senha únicos
            $login = $this->generateUniqueLogin($cliente_nome);
            $senha = $this->generatePassword();

            // Calcular data de expiração
            $data_expiracao = new DateTime();
            $data_expiracao->add(new DateInterval("P{$duracao_dias}D"));
            $expira = $data_expiracao->format('Y-m-d H:i:s');

            // Gerar mainid aleatório (usado para identificar a revenda)
            $mainid = mt_rand(100000, 999999);

            // Nota: Transação já foi iniciada pelo PaymentProcessor
            // Inserir na tabela accounts (revendas)
            $stmt = $this->pdo->prepare("
                INSERT INTO accounts (login, senha, nome, contato, email, byid, mainid, nivel) 
                VALUES (:login, :senha, :nome, :contato, :email, :byid, :mainid, :nivel)
            ");
            
            $stmt->execute([
                ':login' => $login,
                ':senha' => $senha,
                ':nome' => $cliente_nome,
                ':contato' => $cliente_telefone,
                ':email' => $cliente_email,
                ':byid' => $byid,
                ':mainid' => $mainid,
                ':nivel' => 2 // Nível de revenda
            ]);

            $revendaId = $this->pdo->lastInsertId();

            // Calcular limite de teste (10% do limite ou mínimo 1)
            $limitetest = max(1, intval($limite * 0.1));

            // Inserir atribuição na tabela atribuidos
            $stmt = $this->pdo->prepare("
                    INSERT INTO atribuidos (valor, userid, byid, limite, limitetest, tipo, expira, categoriaid, subrev, id_plano) 
                    VALUES (:valor, :userid, :byid, :limite, :limitetest, :tipo, :expira, :categoriaid, :subrev, :id_plano)
                ");
                
            $stmt->execute([
                ':valor' => $valor,
                ':userid' => $revendaId,
                ':byid' => $byid,
                ':limite' => $limite,
                ':limitetest' => $limitetest,
                ':tipo' => $tipoAtribuicao,
                ':expira' => $expira,
                ':categoriaid' => $categoriaid,
                ':subrev' => 0,
                ':id_plano' => $id_plano
            ]);

            // Atualizar pagamento com o ID da revenda criada e o login
            $stmt = $this->pdo->prepare("UPDATE pagamentos SET iduser = ?, login = ? WHERE payment_id = ?");
            $stmt->execute([$revendaId, $login, $paymentId]);

            // Enviar credenciais por WhatsApp se tiver telefone
            if (!empty($cliente_telefone)) {
                $this->sendWhatsAppRevendaCredentials($byid, $login, $senha, $cliente_nome, $cliente_telefone, $limite, $limitetest, $duracao_dias, $categoriaid, $cliente_email);
            }

            // Enviar credenciais por e-mail
            $this->sendRevendaCredentials($cliente_email, $cliente_nome, $login, $senha, $expira, $limite, $limitetest);

            Logger::logInfo("Revenda criada com sucesso via pagamento público: login={$login}, payment_id={$paymentId}, revenda_id={$revendaId}");

            return [
                'user_id' => $revendaId,
                'login' => $login,
                'senha' => $senha,
                'expira' => $expira,
                'limite' => $limite,
                'tipo' => 'revenda'
            ];

        } catch (Exception $e) {
            Logger::logError("Erro ao processar nova revenda de pagamento público: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Envia credenciais de revenda por WhatsApp (duas mensagens: criarrevenda + criaratribuicao)
     */
    private function sendWhatsAppRevendaCredentials($byid, $login, $senha, $nome, $telefone, $limite, $limitetest, $validade, $categoriaid, $email = '')
    {
        try {
            // Normalizar número de telefone
            $telefoneNormalizado = $this->normalizarNumeroContato($telefone);
            if (!$telefoneNormalizado) {
                Logger::logInfo("Número de telefone inválido para WhatsApp (revenda): {$telefone}");
                return false;
            }

            // Buscar sessionId da tabela whatsapp
            $stmt = $this->pdo->prepare("SELECT sessionId FROM whatsapp WHERE byid = ?");
            $stmt->execute([$byid]);
            $whatsappData = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$whatsappData || empty($whatsappData['sessionId'])) {
                Logger::logInfo("WhatsApp não configurado para byid (revenda): {$byid}");
                return false;
            }

            $sessionId = $whatsappData['sessionId'];
            $dominio = $_SERVER['HTTP_HOST'] ?? 'sistema';

            // Buscar nome do usuário que está vendendo (dono do byid)
            $stmt = $this->pdo->prepare("SELECT nome, login FROM accounts WHERE id = ?");
            $stmt->execute([$byid]);
            $vendedor = $stmt->fetch(PDO::FETCH_ASSOC);
            $nome_user = $vendedor['nome'] ?? $vendedor['login'] ?? 'Administrador';

            // Buscar nome da categoria
            $stmt = $this->pdo->prepare("SELECT nome FROM categorias WHERE subid = ?");
            $stmt->execute([$categoriaid]);
            $categoriaNome = $stmt->fetchColumn() ?: 'Padrão';

            // Buscar tipo de atribuição
            $stmt = $this->pdo->prepare("SELECT tipo FROM api WHERE byid = ? LIMIT 1");
            $stmt->execute([$byid]);
            $tipoAtribuicao = $stmt->fetchColumn() ?: 'Credito';

            // ========== MENSAGEM 1: CRIARREVENDA (dados de acesso) ==========
            $stmt = $this->pdo->prepare("SELECT mensagem, status FROM mensagens WHERE funcao = 'criarrevenda' AND byid = ?");
            $stmt->execute([$byid]);
            $mensagemRevendaData = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$mensagemRevendaData || $mensagemRevendaData['status'] != 1) {
                $mensagem1 = "🎉 *Revenda Criada*\n\n";
                $mensagem1 .= "*Bem-vindo* {nome}\n\n";
                $mensagem1 .= "*Segue abaixo seus dados de acesso ao painel.*\n\n";
                $mensagem1 .= "*Domínio:* https://{dominio}/\n";
                $mensagem1 .= "*E-mail:* {email}\n";
                $mensagem1 .= "*Login:* {login}\n";
                $mensagem1 .= "*Senha:* {senha}\n\n";
                $mensagem1 .= "*Att:* {nome_user}";
            } else {
                $mensagem1 = $mensagemRevendaData['mensagem'];
            }

            // Substituir variáveis na mensagem 1
            $mensagem1 = str_replace('{login}', $login, $mensagem1);
            $mensagem1 = str_replace('{senha}', $senha, $mensagem1);
            $mensagem1 = str_replace('{nome}', $nome, $mensagem1);
            $mensagem1 = str_replace('{email}', $email, $mensagem1);
            $mensagem1 = str_replace('{dominio}', $dominio, $mensagem1);
            $mensagem1 = str_replace('{nome_user}', $nome_user, $mensagem1);

            // ========== MENSAGEM 2: CRIARATRIBUICAO (dados da atribuição) ==========
            $stmt = $this->pdo->prepare("SELECT mensagem, status FROM mensagens WHERE funcao = 'criaratribuicao' AND byid = ?");
            $stmt->execute([$byid]);
            $mensagemAtribData = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$mensagemAtribData || $mensagemAtribData['status'] != 1) {
                $mensagem2 = "📋 *Nova atribuição foi adicionada*\n\n";
                $mensagem2 .= "*Categoria:* {categoria}\n";
                $mensagem2 .= "*Tipo de revenda:* {tipo}\n";
                $mensagem2 .= "*Sub-revenda:* {subrev}\n";
                $mensagem2 .= "*Validade:* {validade} dias\n";
                $mensagem2 .= "*Limite:* {limite}\n";
                $mensagem2 .= "*Limite de teste:* {limitetest}\n\n";
                $mensagem2 .= "*Domínio:* https://{dominio}/\n\n";
                $mensagem2 .= "Obrigado por usar nossos serviços!";
            } else {
                $mensagem2 = $mensagemAtribData['mensagem'];
            }

            // Substituir variáveis na mensagem 2
            $mensagem2 = str_replace('{categoria}', $categoriaNome, $mensagem2);
            $mensagem2 = str_replace('{tipo}', $tipoAtribuicao, $mensagem2);
            $mensagem2 = str_replace('{subrev}', 'Não', $mensagem2);
            $mensagem2 = str_replace('{validade}', $validade, $mensagem2);
            $mensagem2 = str_replace('{limite}', $limite, $mensagem2);
            $mensagem2 = str_replace('{limitetest}', $limitetest, $mensagem2);
            $mensagem2 = str_replace('{dominio}', $dominio, $mensagem2);
            
            // Buscar URL e API key do admin
            $stmtAdmin = $this->pdo->prepare("SELECT url, api_key FROM whatsapp WHERE byid = 1 LIMIT 1");
            $stmtAdmin->execute();
            $adminData = $stmtAdmin->fetch(PDO::FETCH_ASSOC);
            
            $apikey = isset($adminData['api_key']) && $adminData['api_key'] 
                ? $adminData['api_key'] 
                : "a076c914af55d544fbf529032ed07197f91499008488c8af07550fe467376829";
            $apiurl = isset($adminData['url']) && $adminData['url'] 
                ? rtrim($adminData['url'], '/') 
                : "https://api-whats.painelwebpro.com.br";

            // Enviar MENSAGEM 1 (dados de acesso)
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => "$apiurl/message/sendText/$sessionId",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => json_encode([
                    "number" => $telefoneNormalizado,
                    "text" => $mensagem1,
                    "delay" => 0
                ]),
                CURLOPT_HTTPHEADER => [
                    "Content-Type: application/json",
                    "apikey: $apikey"
                ],
            ]);
            $response1 = curl_exec($curl);
            $http_code1 = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            if ($http_code1 >= 200 && $http_code1 < 300) {
                Logger::logInfo("WhatsApp (criarrevenda) enviado com sucesso para: {$telefoneNormalizado}");
            } else {
                Logger::logError("Erro ao enviar WhatsApp (criarrevenda). HTTP Code: {$http_code1}");
            }

            // Aguardar 2 segundos antes de enviar a segunda mensagem
            sleep(2);

            // Enviar MENSAGEM 2 (dados da atribuição)
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => "$apiurl/message/sendText/$sessionId",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => json_encode([
                    "number" => $telefoneNormalizado,
                    "text" => $mensagem2,
                    "delay" => 0
                ]),
                CURLOPT_HTTPHEADER => [
                    "Content-Type: application/json",
                    "apikey: $apikey"
                ],
            ]);
            $response2 = curl_exec($curl);
            $http_code2 = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            if ($http_code2 >= 200 && $http_code2 < 300) {
                Logger::logInfo("WhatsApp (criaratribuicao) enviado com sucesso para: {$telefoneNormalizado}");
                return true;
            } else {
                Logger::logError("Erro ao enviar WhatsApp (criaratribuicao). HTTP Code: {$http_code2}");
                return false;
            }

        } catch (Exception $e) {
            Logger::logError("Erro ao enviar WhatsApp de revenda: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Envia credenciais de revenda por e-mail
     */
    private function sendRevendaCredentials($email, $nome, $login, $senha, $expira, $limite, $limitetest)
    {
        if (empty($email)) {
            Logger::logInfo("E-mail não fornecido, credenciais de revenda não enviadas para login: {$login}");
            return false;
        }

        try {
            // Buscar configurações do painel
            $stmt = $this->pdo->prepare("SELECT * FROM config WHERE byid = 1 LIMIT 1");
            $stmt->execute();
            $config = $stmt->fetch(PDO::FETCH_ASSOC);
            $titulo_painel = $config['title'] ?? 'Painel SSH';
            
            // Preparar e-mail
            $assunto = "Sua conta de revenda - {$titulo_painel}";
            
            $mensagem = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; background: #1a1a2e; color: #fff; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #16213e; border-radius: 10px; }
                    .header { text-align: center; padding: 20px; background: linear-gradient(135deg, #7c3aed, #5f61e6); border-radius: 10px 10px 0 0; }
                    .content { padding: 20px; }
                    .credential { background: #0f3460; padding: 15px; margin: 10px 0; border-radius: 8px; }
                    .credential label { color: #888; font-size: 12px; }
                    .credential value { font-size: 18px; font-weight: bold; color: #7c3aed; display: block; }
                    .footer { text-align: center; padding: 20px; color: #888; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>{$titulo_painel}</h1>
                        <p style='color: #fff;'>Conta de Revenda</p>
                    </div>
                    <div class='content'>
                        <p>Olá <strong>{$nome}</strong>,</p>
                        <p>Sua conta de revenda foi criada com sucesso! Aqui estão suas credenciais:</p>
                        
                        <div class='credential'>
                            <label>Login:</label>
                            <value>{$login}</value>
                        </div>
                        
                        <div class='credential'>
                            <label>Senha:</label>
                            <value>{$senha}</value>
                        </div>
                        
                        <div class='credential'>
                            <label>Créditos de Usuários:</label>
                            <value>{$limite}</value>
                        </div>
                        
                        <div class='credential'>
                            <label>Créditos de Teste:</label>
                            <value>{$limitetest}</value>
                        </div>
                        
                        <div class='credential'>
                            <label>Válido até:</label>
                            <value>" . date('d/m/Y H:i', strtotime($expira)) . "</value>
                        </div>
                        
                        <p>Acesse o painel para começar a gerenciar seus usuários!</p>
                    </div>
                    <div class='footer'>
                        <p>Este é um e-mail automático, por favor não responda.</p>
                    </div>
                </div>
            </body>
            </html>
            ";

            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type: text/html; charset=UTF-8\r\n";
            $headers .= "From: {$titulo_painel} <noreply@" . $_SERVER['HTTP_HOST'] . ">\r\n";

            // Tentar enviar e-mail
            if (function_exists('mail')) {
                mail($email, $assunto, $mensagem, $headers);
                Logger::logInfo("E-mail de revenda enviado para: {$email}");
            } else {
                Logger::logInfo("Função mail() não disponível. Credenciais de revenda não enviadas para: {$email}");
            }

            return true;

        } catch (Exception $e) {
            Logger::logError("Erro ao enviar e-mail de revenda: " . $e->getMessage());
            return false;
        }
    }
}
