<?php

class Logger
{
    private static $logDir = __DIR__ . '/logs';
    private static $logFile = 'mercadopago_webhook.log';

    /**
     * Inicializa o diretório de logs se não existir
     */
    private static function initLogDir()
    {
        if (!is_dir(self::$logDir)) {
            mkdir(self::$logDir, 0755, true);
        }
    }

    /**
     * Escreve no arquivo de log
     */
    private static function writeLog($level, $message)
    {
        self::initLogDir();
        
        // Verifica se precisa rotacionar logs diariamente
        self::rotateDailyLogs();
        
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
        
        $logFilePath = self::$logDir . '/' . self::$logFile;
        
        // Escreve no arquivo de log
        file_put_contents($logFilePath, $logEntry, FILE_APPEND | LOCK_EX);
        
        // Também mantém o log no error_log padrão para compatibilidade
        error_log("[MercadoPago Webhook] {$level}: " . $message);
        
        // A cada 100 escritas, verifica se precisa limpar logs antigos (otimização)
        if (rand(1, 100) === 1) {
            self::cleanOldLogs();
        }
    }

    /**
     * Log de informações
     */
    public static function logInfo($message)
    {
        self::writeLog('INFO', $message);
    }

    /**
     * Log de erros
     */
    public static function logError($message)
    {
        self::writeLog('ERROR', $message);
    }

    /**
     * Log de debug
     */
    public static function logDebug($message)
    {
        self::writeLog('DEBUG', $message);
    }

    /**
     * Log de warning
     */
    public static function logWarning($message)
    {
        self::writeLog('WARNING', $message);
    }

    /**
     * Rotaciona logs com base no tamanho e limpa logs antigos
     */
    public static function cleanOldLogs()
    {
        self::initLogDir();
        
        $logFilePath = self::$logDir . '/' . self::$logFile;
        
        // Rotaciona o log se o arquivo principal for muito grande (5MB)
        if (file_exists($logFilePath) && filesize($logFilePath) > 5242880) {
            $timestamp = date('Y-m-d_H-i-s');
            $oldLogFile = self::$logDir . '/mercadopago_webhook_' . $timestamp . '.log';
            rename($logFilePath, $oldLogFile);
            
            self::writeLog('INFO', 'Log rotacionado. Novo arquivo iniciado.');
        }
        
        // Remove arquivos de log antigos (mais de 30 dias)
        $files = glob(self::$logDir . '/mercadopago_webhook_*.log');
        $thirtyDaysAgo = time() - (30 * 24 * 60 * 60); // 30 dias em segundos
        
        foreach ($files as $file) {
            if (filemtime($file) < $thirtyDaysAgo) {
                unlink($file);
                error_log("[MercadoPago Webhook] Log antigo removido: " . basename($file));
            }
        }
    }

    /**
     * Rotaciona logs diariamente baseado na data
     */
    public static function rotateDailyLogs()
    {
        self::initLogDir();
        
        $logFilePath = self::$logDir . '/' . self::$logFile;
        $today = date('Y-m-d');
        
        // Verifica se o arquivo existe e se não foi criado hoje
        if (file_exists($logFilePath)) {
            $fileDate = date('Y-m-d', filemtime($logFilePath));
            
            // Se o arquivo não é de hoje, rotaciona
            if ($fileDate !== $today) {
                $oldLogFile = self::$logDir . '/mercadopago_webhook_' . $fileDate . '.log';
                
                // Se já existe um arquivo com esta data, adiciona timestamp
                if (file_exists($oldLogFile)) {
                    $oldLogFile = self::$logDir . '/mercadopago_webhook_' . $fileDate . '_' . date('H-i-s', filemtime($logFilePath)) . '.log';
                }
                
                rename($logFilePath, $oldLogFile);
                self::writeLog('INFO', 'Novo dia iniciado. Log anterior arquivado como: ' . basename($oldLogFile));
            }
        }
    }

    /**
     * Retorna o caminho do arquivo de log atual
     */
    public static function getLogFilePath()
    {
        self::initLogDir();
        return self::$logDir . '/' . self::$logFile;
    }
}
