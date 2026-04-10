<?php
session_start();
error_reporting(0);
header('Content-Type: application/json');

include('../AegisCore/conexao.php');
set_include_path(get_include_path() . PATH_SEPARATOR . '../lib2');
include('Net/SSH2.php');

$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

if (!isset($_SESSION['login']) || $_SESSION['login'] !== 'admin') {
    die(json_encode(['success' => false, 'error' => 'Acesso negado']));
}

$id = intval($_POST['id'] ?? 0);

// Buscar servidor e token
$sql = "SELECT s.*, st.token 
        FROM servidores s 
        LEFT JOIN servidor_tokens st ON s.id = st.servidor_id AND st.status = 'ativo'
        WHERE s.id = '$id'";
$result = mysqli_query($conn, $sql);
$servidor = mysqli_fetch_assoc($result);

if (!$servidor) {
    die(json_encode(['success' => false, 'error' => 'Servidor não encontrado']));
}

if (!$servidor['token']) {
    die(json_encode(['success' => false, 'error' => 'Servidor sem token ativo']));
}

$log = [];
$step = 0;

try {
    // Passo 1: Conexão SSH
    $step = 1;
    $log[] = "🔌 Conectando a {$servidor['ip']}:{$servidor['porta']}...";
    
    $ssh = new Net_SSH2($servidor['ip'], $servidor['porta']);
    if (!$ssh->login($servidor['usuario'], $servidor['senha'])) {
        throw new Exception("Falha na autenticação SSH");
    }
    $log[] = "✅ Conectado com sucesso";
    
    // Passo 2: Instalar Python e dependências
    $step = 2;
    $log[] = "📦 Verificando Python3...";
    $ssh->exec('apt-get update -qq');
    $ssh->exec('apt-get install -y python3 curl net-tools -qq');
    $log[] = "✅ Python3 instalado";
    
    // Passo 3: Criar módulo Python (LIMPO, sem scripts extras)
    $step = 3;
    $log[] = "🐍 Criando módulo Python...";
    
    $modulo = <<<PYTHON
# -*- coding: utf-8 -*-
import subprocess
import cgi
from http.server import HTTPServer, BaseHTTPRequestHandler

SENHA = '{$servidor['token']}'

class Handler(BaseHTTPRequestHandler):
    def do_POST(self):
        if self.headers.get('Senha') != SENHA:
            self.send_response(401)
            self.end_headers()
            self.wfile.write(b'Nao autorizado')
            return
            
        form = cgi.FieldStorage(
            fp=self.rfile,
            headers=self.headers,
            environ={'REQUEST_METHOD': 'POST'}
        )
        comando = form.getvalue('comando', '')
        
        try:
            resultado = subprocess.check_output(
                comando, 
                shell=True, 
                stderr=subprocess.STDOUT,
                timeout=30
            )
            self.send_response(200)
        except Exception as e:
            resultado = str(e).encode()
            self.send_response(500)
            
        self.end_headers()
        self.wfile.write(resultado)

print('🚀 Módulo iniciado na porta 6969')
HTTPServer(('0.0.0.0', 6969), Handler).serve_forever()
PYTHON;

    // Escapar para shell
    $modulo_escaped = addslashes($modulo);
    $ssh->exec("echo '{$modulo_escaped}' > /root/modulo.py");
    $log[] = "✅ Módulo criado em /root/modulo.py";
    
    // Passo 4: Iniciar módulo
    $step = 4;
    $log[] = "▶️ Iniciando módulo...";
    $ssh->exec('pkill -f modulo.py 2>/dev/null');
    $ssh->exec('cd /root && nohup python3 modulo.py > modulo.log 2>&1 &');
    sleep(2);
    $log[] = "✅ Módulo iniciado";
    
    // Passo 5: Configurar crontab
    $step = 5;
    $log[] = "🕒 Configurando inicialização automática...";
    $ssh->exec('(crontab -l 2>/dev/null; echo "@reboot cd /root && python3 modulo.py") | crontab -');
    $log[] = "✅ Crontab configurado";
    
    // Passo 6: Testar módulo
    $step = 6;
    $log[] = "🔍 Testando módulo...";
    
    $teste = $ssh->exec("curl -s -X POST http://localhost:6969 -H 'Senha: {$servidor['token']}' -d 'comando=echo funcionou'");
    
    if (trim($teste) == 'funcionou') {
        $log[] = "✅ Módulo respondendo corretamente";
    } else {
        $log[] = "⚠️ Resposta inesperada: " . trim($teste);
    }
    
    // Verificar porta
    $porta = $ssh->exec('netstat -tulpn | grep 6969 || echo "offline"');
    if (strpos($porta, '6969') !== false) {
        $log[] = "✅ Porta 6969 está ouvindo";
    } else {
        $log[] = "⚠️ Porta 6969 não detectada";
    }
    
    $ssh->disconnect();
    
    echo json_encode([
        'success' => true,
        'log' => implode("\n", $log),
        'step' => $step
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'step' => $step,
        'log' => implode("\n", $log)
    ]);
}
?>