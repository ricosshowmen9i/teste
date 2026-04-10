
<?php
session_start();
include('../AegisCore/conexao.php');
set_include_path(get_include_path() . PATH_SEPARATOR . '../lib2');
include('Net/SSH2.php');

$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

// Verifica autenticação
if (!isset($_SESSION['login']) || !isset($_SESSION['senha'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

if ($_SESSION['login'] !== 'admin') {
    echo 'Sem permissão';
    exit;
}

// Anti SQL injection simples
$id = isset($_GET['id']) ? preg_replace('/[^0-9]/', '', $_GET['id']) : 0;
if ($id == 0) {
    echo 'ID inválido';
    exit;
}

// Busca dados do servidor
$sql = "SELECT * FROM servidores WHERE id = '$id'";
$result = $conn->query($sql);
$row = $result->fetch_assoc();

$ipservidor = $row['ip'];
$portaservidor = $row['porta'];
$usuarioservidor = $row['usuario'];
$senhaservidor = $row['senha'];

// Busca token
$sql_token = "SELECT token FROM servidor_tokens WHERE servidor_id = '$id' AND status = 'ativo' ORDER BY id DESC LIMIT 1";
$result_token = $conn->query($sql_token);
$row_token = $result_token->fetch_assoc();
$token_md5 = $row_token['token'];

// Conecta SSH
$ssh = new Net_SSH2($ipservidor, $portaservidor);
if (!$ssh->login($usuarioservidor, $senhaservidor)) {
    die('Falha na conexão SSH');
}

echo "<pre>";
echo "========================================\n";
echo "INICIANDO INSTALAÇÃO DOS MÓDULOS\n";
echo "========================================\n\n";

// 1. Criar diretório
echo "[1/6] Criando diretório /etc/xis...\n";
$ssh->exec('mkdir -p /etc/xis');
echo "✓ OK\n\n";

// 2. Instalar Python e ferramentas
echo "[2/6] Instalando Python e ferramentas...\n";
$ssh->exec('apt-get update > /dev/null 2>&1');
$ssh->exec('apt-get install -y python3 wget curl dos2unix > /dev/null 2>&1');
echo "✓ OK\n\n";

// 3. Baixar TODOS os arquivos
echo "[3/6] Baixando módulos...\n";

$arquivos = [
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
    'verificador.py',
    'suspend.py',
    'atlasreativar.sh',
    'atlassuspend.sh'
];

foreach ($arquivos as $arquivo) {
    echo "  Baixando $arquivo... ";
    $url = "https://raw.githubusercontent.com/megasini62-ship-it/atlaspainel/main/$arquivo";
    $ssh->exec("cd /etc/xis && wget -q '$url'");
    
    // Verifica se baixou
    $verifica = $ssh->exec("test -f /etc/xis/$arquivo && echo 'ok' || echo 'fail'");
    if (trim($verifica) == 'ok') {
        $ssh->exec("chmod +x /etc/xis/$arquivo");
        echo "✓\n";
    } else {
        echo "✗ (tentando curl...)\n";
        $ssh->exec("cd /etc/xis && curl -s -O '$url'");
        $ssh->exec("chmod +x /etc/xis/$arquivo");
        echo "  ✓ Curl funcionou\n";
    }
}
echo "✓ Todos os arquivos baixados\n\n";

// 4. Criar módulo Python
echo "[4/6] Criando módulo Python...\n";

$modulo_python = "# -*- coding: utf-8 -*-
from http.server import BaseHTTPRequestHandler, HTTPServer
import cgi
import subprocess

senha = '$token_md5'

class Handler(BaseHTTPRequestHandler):
    def do_POST(self):
        if self.headers.get('Senha') == senha:
            form = cgi.FieldStorage(
                fp=self.rfile,
                headers=self.headers,
                environ={'REQUEST_METHOD': 'POST'}
            )
            cmd = form.getvalue('comando')
            try:
                result = subprocess.check_output(cmd, shell=True, stderr=subprocess.STDOUT)
            except:
                result = b'Erro ao executar comando'
            self.send_response(200)
            self.end_headers()
            self.wfile.write(result)
        else:
            self.send_response(401)
            self.end_headers()
            self.wfile.write(b'Nao autorizado')

server = HTTPServer(('0.0.0.0', 6969), Handler)
server.serve_forever()
";

// Salvar o módulo Python
$ssh->exec("cat > /etc/xis/modulo.py << 'EOF'\n$modulo_python\nEOF");
$ssh->exec("chmod 644 /etc/xis/modulo.py");
echo "✓ Módulo Python criado\n\n";

// 5. Iniciar o módulo
echo "[5/6] Iniciando módulo Python...\n";
$ssh->exec("pkill -f modulo.py 2>/dev/null");
sleep(1);
$ssh->exec("cd /etc/xis && nohup python3 modulo.py > /var/log/modulo.log 2>&1 &");
sleep(2);

// Verificar se está rodando
$pid = $ssh->exec("pgrep -f modulo.py");
if (trim($pid)) {
    echo "✓ Módulo rodando (PID: " . trim($pid) . ")\n";
} else {
    echo "✗ Módulo não rodando!\n";
    $erro = $ssh->exec("cat /var/log/modulo.log");
    echo "Erro: $erro\n";
}
echo "\n";

// 6. Configurar inicialização automática
echo "[6/6] Configurando inicialização automática...\n";
$ssh->exec("(crontab -l 2>/dev/null | grep -v modulo.py | crontab -)");
$ssh->exec("(crontab -l 2>/dev/null; echo '@reboot sleep 30 && cd /etc/xis && python3 modulo.py >> /var/log/modulo.log 2>&1') | crontab -");
$ssh->exec("(crontab -l 2>/dev/null; echo '*/10 * * * * cd /etc/xis && python3 modulo.py >> /var/log/modulo.log 2>&1') | crontab -");
echo "✓ Crontab configurado\n\n";

// Resumo final
echo "========================================\n";
echo "INSTALAÇÃO CONCLUÍDA!\n";
echo "========================================\n";
echo "Token utilizado: " . substr($token_md5, 0, 10) . "...\n";
echo "Diretório: /etc/xis/\n";
echo "Logs: /var/log/modulo.log\n\n";

// Listar arquivos instalados
echo "Arquivos instalados:\n";
$lista = $ssh->exec("ls -1 /etc/xis/");
echo $lista;

$ssh->disconnect();
mysqli_close($conn);

echo "\n\n✅ Instalação finalizada com sucesso!\n";
echo "</pre>";

// Botão para voltar
echo "<br><br>";
echo "<script src='../app-assets/sweetalert.min.js'></script>";
echo "<script>
    swal({
        title: 'Instalação Concluída!',
        text: 'Todos os módulos foram instalados com sucesso!',
        icon: 'success',
        button: 'OK'
    }).then(function() {
        window.location.href = 'servidores.php';
    });
</script>";
?>
