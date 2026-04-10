<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Definir caminhos
define('BASE_PATH', dirname(__FILE__));

// Configurar include path para o SSH2
set_include_path(
    get_include_path() . 
    PATH_SEPARATOR . BASE_PATH . '/lib2' .
    PATH_SEPARATOR . BASE_PATH . '/vendor/phpseclib/phpseclib'
);

include(BASE_PATH . '/AegisCore/conexao.php');

// Verificar SSH2
$ssh2_path = BASE_PATH . '/lib2/Net/SSH2.php';
if (!file_exists($ssh2_path)) {
    $ssh2_path = BASE_PATH . '/vendor/phpseclib/phpseclib/Net/SSH2.php';
}
if (!file_exists($ssh2_path)) {
    die("❌ Net/SSH2.php não encontrado. Instale com: composer require phpseclib/phpseclib:~2.0");
}
require_once($ssh2_path);

$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("❌ Erro banco: " . mysqli_connect_error());
}

// Verificar autenticação
if (!isset($_SESSION['login']) || !isset($_SESSION['senha'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

if ($_SESSION['login'] !== 'admin') {
    die('❌ Sem permissão');
}

function anti_sql($input) {
    if (empty($input)) return '';
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function($match) {
        return '';
    }, $input);
    $seg = trim($seg);
    $seg = strip_tags($seg);
    $seg = addslashes($seg);
    return $seg;
}

$id = isset($_GET['id']) ? anti_sql($_GET['id']) : 0;
if ($id == 0) {
    die('❌ ID do servidor não fornecido');
}

$sql = "SELECT * FROM servidores WHERE id = '$id'";
$result = $conn->query($sql);
if ($result->num_rows === 0) {
    die('❌ Servidor não encontrado');
}
$row = $result->fetch_assoc();

// Buscar token ativo
$sql_token = "SELECT token FROM servidor_tokens WHERE servidor_id = '$id' AND status = 'ativo' ORDER BY id DESC LIMIT 1";
$result_token = $conn->query($sql_token);
if (!$result_token || $result_token->num_rows === 0) {
    die('❌ Nenhum token ativo encontrado!');
}
$row_token = $result_token->fetch_assoc();
$token_md5 = $row_token['token'];

echo "<!DOCTYPE html>
<html>
<head>
    <title>Instalando Módulos Atlas</title>
    <link rel='stylesheet' href='../app-assets/sweetalert.css'>
    <script src='../app-assets/sweetalert.min.js'></script>
    <style>
        body { font-family: monospace; padding: 20px; background: #1e1e2f; color: #fff; }
        .log { background: #2d2d3a; border-left: 4px solid #4CAF50; padding: 10px; margin: 5px 0; border-radius: 5px; }
        .error { border-left-color: #f44336; background: #3d2a2a; }
        .info { border-left-color: #2196F3; }
        .success { border-left-color: #4CAF50; background: #2a3d2a; }
        .container { max-width: 900px; margin: 0 auto; }
        h2 { color: #4CAF50; }
        pre { background: #000; padding: 10px; border-radius: 5px; overflow-x: auto; font-size: 11px; }
    </style>
</head>
<body>
<div class='container'>
    <h2>🚀 Instalando Módulos Atlas no Servidor</h2>
    <div id='logs'>";

function logMsg($msg, $type = 'info') {
    $class = $type == 'error' ? 'error' : ($type == 'success' ? 'success' : 'log');
    echo "<div class='log $class'>" . date('H:i:s') . " - $msg</div>";
    ob_flush();
    flush();
}

logMsg("Conectando ao servidor {$row['ip']}:{$row['porta']}...");

// Conectar SSH
$ssh = new Net_SSH2($row['ip'], $row['porta']);
if (!$ssh->login($row['usuario'], $row['senha'])) {
    logMsg("Falha na autenticação SSH", 'error');
    die('</div></div></body></html>');
}
logMsg("✅ Conectado com sucesso", 'success');

// Verificar SO
$os = $ssh->exec('uname -a 2>&1');
logMsg("Sistema: " . trim($os));

// ============================================
// 1. INSTALAR DEPENDÊNCIAS BÁSICAS
// ============================================
logMsg("📦 Instalando dependências do sistema...");

$deps = [
    'apt-get update -y 2>&1',
    'apt-get install -y python3 python3-pip wget curl dos2unix screen htop net-tools git 2>&1',
    'apt-get install -y apache2-utils nginx 2>&1',
    'pip3 install --upgrade pip 2>&1',
    'pip3 install requests paramiko cryptography flask 2>&1'
];

foreach ($deps as $cmd) {
    $output = $ssh->exec($cmd);
    if (strpos($output, 'error') !== false || strpos($output, 'failed') !== false) {
        logMsg("⚠️ Aviso em: $cmd", 'error');
    }
}
logMsg("✅ Dependências instaladas", 'success');

// ============================================
// 2. CRIAR DIRETÓRIOS NECESSÁRIOS
// ============================================
logMsg("📁 Criando diretórios...");

$dirs = [
    'mkdir -p /etc/SSHPlus/senha',
    'mkdir -p /opt/atlas',
    'mkdir -p /root/usuarios_db',
    'touch /root/usuarios.db',
    'chmod 755 /etc/SSHPlus',
    'chmod 755 /opt/atlas'
];

foreach ($dirs as $dir) {
    $ssh->exec($dir);
}
logMsg("✅ Diretórios criados", 'success');

// ============================================
// 3. BAIXAR TODOS OS SCRIPTS DO GITHUB
// ============================================
logMsg("📥 Baixando scripts do GitHub...");

$scripts = [
    'atlascreate.sh',
    'add.sh',
    'remsinc.sh',
    'addsinc.sh',
    'rem.sh',
    'atlasteste.sh',
    'addteste.sh',
    'atlasremove.sh',
    'delete.py',
    'atlasdata.sh',
    'sincronizar.py',
    'verificador.py'
];

$github_base = "https://raw.githubusercontent.com/atlaspaineL/atlasPainel/main/";

foreach ($scripts as $script) {
    logMsg("Baixando: $script");
    $cmd = "wget -O /opt/AegisCore/$script \"{$github_base}$script\" 2>&1";
    $output = $ssh->exec($cmd);
    if (strpos($output, 'saved') !== false || strpos($output, '100%') !== false) {
        logMsg("  ✅ $script baixado", 'success');
    } else {
        logMsg("  ⚠️ $script pode não ter sido baixado: " . substr($output, 0, 100), 'error');
    }
}

// Dar permissão de execução
logMsg("🔧 Configurando permissões...");
$ssh->exec("chmod +x /opt/AegisCore/*.sh /opt/AegisCore/*.py 2>&1");
$ssh->exec("dos2unix /opt/AegisCore/*.sh /opt/AegisCore/*.py 2>&1");
logMsg("✅ Scripts configurados", 'success');

// ============================================
// 4. CRIAR MÓDULO PYTHON COM O TOKEN (API)
// ============================================
logMsg("🐍 Criando módulo API Python com token...");

$modulo_python = <<<PYTHON
#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Módulo Atlas - API de Gerenciamento
Token: $token_md5
"""

import os
import sys
import json
import subprocess
import logging
import socket
import hashlib
from http.server import HTTPServer, BaseHTTPRequestHandler
from datetime import datetime

# Configuração
TOKEN = '$token_md5'
PORT = 6969
VERSION = '2.0.0'
SCRIPTS_DIR = '/opt/atlas'

# Configurar logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler('/var/log/atlas_api.log'),
        logging.StreamHandler()
    ]
)

class AtlasAPIHandler(BaseHTTPRequestHandler):
    """Handler para requisições HTTP da API"""
    
    def log_message(self, format, *args):
        logging.info(f"{self.address_string()} - {format % args}")
    
    def do_GET(self):
        """Status do servidor"""
        self.send_response(200)
        self.send_header('Content-type', 'application/json')
        self.end_headers()
        
        response = {
            'status': 'online',
            'version': VERSION,
            'server': socket.gethostname(),
            'scripts': os.listdir(SCRIPTS_DIR) if os.path.exists(SCRIPTS_DIR) else [],
            'timestamp': datetime.now().isoformat()
        }
        self.wfile.write(json.dumps(response).encode())
    
    def do_POST(self):
        """Executa comandos ou scripts"""
        # Verificar token
        auth = self.headers.get('Senha')
        if auth != TOKEN:
            self.send_response(401)
            self.end_headers()
            self.wfile.write(b'{"error": "Nao autorizado"}')
            logging.warning(f"Tentativa não autorizada de {self.client_address}")
            return
        
        content_length = int(self.headers.get('Content-Length', 0))
        post_data = self.rfile.read(content_length)
        
        try:
            data = json.loads(post_data.decode('utf-8'))
            acao = data.get('acao', 'comando')
            
            if acao == 'comando':
                comando = data.get('comando', '')
                if not comando:
                    raise ValueError("Comando nao fornecido")
                
                logging.info(f"Executando comando: {comando}")
                result = subprocess.run(comando, shell=True, capture_output=True, text=True, timeout=60)
                
                self.send_response(200)
                self.end_headers()
                response = {
                    'success': result.returncode == 0,
                    'output': result.stdout + result.stderr,
                    'exit_code': result.returncode
                }
                self.wfile.write(json.dumps(response).encode())
                
            elif acao == 'script':
                script = data.get('script', '')
                args = data.get('args', [])
                
                if not script:
                    raise ValueError("Script nao especificado")
                
                script_path = os.path.join(SCRIPTS_DIR, script)
                if not os.path.exists(script_path):
                    raise ValueError(f"Script nao encontrado: {script}")
                
                cmd = f"bash {script_path} " + " ".join(args)
                logging.info(f"Executando script: {cmd}")
                result = subprocess.run(cmd, shell=True, capture_output=True, text=True, timeout=120)
                
                self.send_response(200)
                self.end_headers()
                response = {
                    'success': result.returncode == 0,
                    'output': result.stdout + result.stderr,
                    'exit_code': result.returncode
                }
                self.wfile.write(json.dumps(response).encode())
                
            else:
                raise ValueError(f"Acao desconhecida: {acao}")
                
        except subprocess.TimeoutExpired:
            self.send_response(408)
            self.end_headers()
            self.wfile.write(b'{"error": "Tempo limite excedido"}')
        except Exception as e:
            logging.error(f"Erro: {e}")
            self.send_response(500)
            self.end_headers()
            self.wfile.write(f'{{"error": "{str(e)}"}}'.encode())
    
    def do_OPTIONS(self):
        self.send_response(200)
        self.send_header('Access-Control-Allow-Origin', '*')
        self.send_header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
        self.send_header('Access-Control-Allow-Headers', 'Senha, Content-Type')
        self.end_headers()

def main():
    logging.info(f"Iniciando Atlas API v{VERSION}")
    logging.info(f"Token configurado: {TOKEN[:10]}...")
    logging.info(f"Diretório de scripts: {SCRIPTS_DIR}")
    
    try:
        server = HTTPServer(('0.0.0.0', PORT), AtlasAPIHandler)
        logging.info(f"✅ API rodando em 0.0.0.0:{PORT}")
        logging.info(f"✅ Teste: curl -H 'Senha: {TOKEN}' http://localhost:{PORT}/")
        server.serve_forever()
    except Exception as e:
        logging.error(f"Erro: {e}")
        sys.exit(1)

if __name__ == '__main__':
    main()
PYTHON;

$ssh->exec('echo "' . addslashes($modulo_python) . '" > /opt/AegisCore/atlas_api.py');
$ssh->exec('chmod +x /opt/AegisCore/atlas_api.py');

// ============================================
// 5. CRIAR SCRIPT DE MONITORAMENTO
// ============================================
$monitor_script = <<<BASH
#!/bin/bash
# Monitor do Atlas API
PID=\$(pgrep -f "python3 /opt/AegisCore/atlas_api.py")
if [ -z "\$PID" ]; then
    echo "\$(date): API parada, reiniciando..." >> /var/log/atlas_monitor.log
    cd /opt/atlas && nohup python3 atlas_api.py >> /var/log/atlas_api.log 2>&1 &
fi
BASH;

$ssh->exec('echo "' . addslashes($monitor_script) . '" > /opt/AegisCore/monitor.sh');
$ssh->exec('chmod +x /opt/AegisCore/monitor.sh');

// ============================================
// 6. EXECUTAR VERIFICADOR.PY
// ============================================
logMsg("🔍 Executando verificador.py...");
$verificador = $ssh->exec('cd /opt/atlas && python3 verificador.py 2>&1');
logMsg("Saída do verificador: " . substr($verificador, 0, 500));

// ============================================
// 7. CONFIGURAR CRONTAB
// ============================================
logMsg("⏰ Configurando inicialização automática...");

// Parar processos antigos
$ssh->exec('pkill -f "atlas_api.py" 2>/dev/null || true');

// Configurar crontab
$cron_setup = <<<BASH
(crontab -l 2>/dev/null | grep -v "atlas_api.py" | grep -v "monitor.sh"; echo "@reboot sleep 30 && cd /opt/atlas && python3 atlas_api.py >> /var/log/atlas_api.log 2>&1") | crontab -
(crontab -l 2>/dev/null | grep -v "monitor.sh"; echo "*/5 * * * * /opt/AegisCore/monitor.sh") | crontab -
BASH;

$ssh->exec($cron_setup);

// ============================================
// 8. INICIAR API
// ============================================
logMsg("🚀 Iniciando Atlas API...");
$ssh->exec('cd /opt/atlas && nohup python3 atlas_api.py >> /var/log/atlas_api.log 2>&1 &');
sleep(3);

// ============================================
// 9. VERIFICAR INSTALAÇÃO
// ============================================
logMsg("🔍 Verificando instalação...");

// Verificar API rodando
$pid = $ssh->exec('pgrep -f "python3 /opt/AegisCore/atlas_api.py"');
if (trim($pid)) {
    logMsg("✅ Atlas API rodando! PID: " . trim($pid), 'success');
    
    // Testar API
    $test = $ssh->exec("curl -s -H 'Senha: $token_md5' http://localhost:6969/ 2>/dev/null");
    if ($test) {
        logMsg("✅ API respondendo: " . substr($test, 0, 100), 'success');
    }
} else {
    logMsg("❌ Atlas API NÃO está rodando!", 'error');
    $logs = $ssh->exec('tail -30 /var/log/atlas_api.log 2>/dev/null');
    echo "<pre style='background:#000; padding:10px; border-radius:5px;'>" . htmlspecialchars($logs) . "</pre>";
}

// Listar scripts instalados
logMsg("📋 Scripts instalados em /opt/atlas:");
$scripts_list = $ssh->exec('ls -la /opt/AegisCore/ 2>/dev/null | grep -E "\.(sh|py)$"');
echo "<pre style='background:#000; padding:10px; border-radius:5px; font-size:11px;'>" . htmlspecialchars($scripts_list) . "</pre>";

$ssh->disconnect();

// ============================================
// 10. FINALIZAR
// ============================================
echo "</div>
<script>
setTimeout(function() {
    swal({
        title: '✅ Instalação Concluída!',
        text: 'Todos os módulos foram instalados!\\nToken: " . substr($token_md5, 0, 10) . "...\\nAPI: porta 6969',
        icon: 'success',
        button: 'OK'
    }).then(function() {
        window.location.href = 'servidores.php';
    });
}, 4000);
</script>
</div>
</body>
</html>";
?>