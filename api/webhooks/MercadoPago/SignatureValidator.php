<?php
require_once __DIR__ . '/Logger.php';

class SignatureValidator
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Analisa a assinatura do formato do Mercado Pago
     */
    public function parseSignature($signatureHeader)
    {
        $parts = explode(',', $signatureHeader);
        if (count($parts) !== 2) {
            throw new Exception('Formato de assinatura inválido');
        }

        $timestamp = '';
        $signature = '';

        foreach ($parts as $part) {
            $keyValue = explode('=', trim($part), 2);
            if (count($keyValue) === 2) {
                $key = trim($keyValue[0]);
                $value = trim($keyValue[1]);

                if ($key === 'ts') {
                    $timestamp = $value;
                } elseif ($key === 'v1') {
                    $signature = $value;
                }
            }
        }

        if (empty($timestamp) || empty($signature)) {
            throw new Exception('Assinatura ou timestamp ausente');
        }

        return [$timestamp, $signature];
    }

    /**
     * Verifica se a assinatura do webhook é válida
     * Retorna false se inválida, ou o ID da configuração válida se válida
     */
    public function verifySignature()
    {
        try {
            // Busca todas as chaves de assinatura ativas
            $stmt = $this->pdo->prepare("SELECT assinature_key, id FROM mercadopago_config WHERE status = 1 AND assinature_key IS NOT NULL AND assinature_key != ''");
            $stmt->execute();
            $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($configs)) {
                Logger::logError("Nenhuma chave de assinatura configurada");
                return false;
            }

            Logger::logInfo("Encontradas " . count($configs) . " chaves de assinatura para verificar");

            $signatureHeader = $this->getHeader('x-signature');
            if (empty($signatureHeader)) {
                Logger::logError("Cabeçalho x-signature não encontrado");
                $this->logHeaders();
                return false;
            }

            $xRequestId = $this->getHeader('x-request-id');
            if (empty($xRequestId)) {
                Logger::logError("Cabeçalho x-request-id não encontrado");
                return false;
            }

            // Aceita tanto data_id, data.id quanto id na query string
            $dataId = $_GET['data_id'] ?? $_GET['data.id'] ?? $_GET['id'] ?? '';
            if (empty($dataId)) {
                Logger::logError("data.id/data_id/id não encontrado na query string");
                return false;
            }

            list($timestamp, $hash) = $this->parseSignature($signatureHeader);

            $manifest = "id:{$dataId};request-id:{$xRequestId};ts:{$timestamp};";

            Logger::logInfo("Manifesto gerado: " . $manifest);
            Logger::logInfo("Assinatura recebida: " . $hash);

            // Testa cada chave de assinatura
            foreach ($configs as $config) {
                $expectedMAC = hash_hmac('sha256', $manifest, $config['assinature_key']);
                
                Logger::logInfo("Testando assinatura para config ID {$config['id']}: " . $expectedMAC);

                if (hash_equals($hash, $expectedMAC)) {
                    Logger::logInfo("Assinatura válida encontrada para config ID {$config['id']}");
                    return $config['id']; // Retorna o ID da configuração válida
                }
            }

            // Se chegou até aqui, nenhuma chave funcionou
            Logger::logError("Assinatura não corresponde a nenhuma das " . count($configs) . " chaves configuradas");
            return false;

        } catch (Exception $e) {
            Logger::logError("Erro na verificação de assinatura: " . $e->getMessage());
            return false;
        }
    }
    /**
     * Obtém um header HTTP de forma case-insensitive
     */
    private function getHeader($name)
    {
        $headers = [];
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
        } else {
            foreach ($_SERVER as $key => $value) {
                if (strpos($key, 'HTTP_') === 0) {
                    $headerName = str_replace('_', '-', strtolower(substr($key, 5)));
                    $headers[$headerName] = $value;
                }
            }
        }

        foreach ($headers as $key => $value) {
            if (strtolower($key) === strtolower($name)) {
                return $value;
            }
        }

        return '';
    }

    /**
     * Log de todos os headers para debug
     */
    private function logHeaders()
    {
        $headers = [];
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
        } else {
            foreach ($_SERVER as $key => $value) {
                if (strpos($key, 'HTTP_') === 0) {
                    $headerName = str_replace('_', '-', strtolower(substr($key, 5)));
                    $headers[$headerName] = $value;
                }
            }
        }

        if ($headers) {
            foreach ($headers as $key => $value) {
                Logger::logInfo("Header {$key}: {$value}");
            }
        }
    }
}
