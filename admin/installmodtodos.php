<?php
// @ioncube.dk $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf -> "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs" RANDOM
$kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf = "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs";
function instalar_modulos_massa($input) {
?>
<script src="../app-assets/sweetalert.min.js"></script>
<?php
error_reporting(0);
session_start();
include('../AegisCore/conexao.php');
set_include_path(get_include_path() . PATH_SEPARATOR . '../lib2');

$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

// Verifica se o usuário está autenticado
if (!isset($_SESSION['login']) || !isset($_SESSION['senha'])) {
    session_destroy();
    unset($_SESSION['login']);
    unset($_SESSION['senha']);
    header('Location: index.php');
    exit;
}

if ($_SESSION['login'] !== 'admin') {
    echo 'Você não tem permissão para acessar essa página';
    exit;
}

include('Net/SSH2.php');

// Função para buscar token do servidor
function getTokenServidor($conn, $servidor_id) {
    $sql_token = "SELECT token FROM servidor_tokens WHERE servidor_id = '$servidor_id' AND status = 'ativo' ORDER BY id DESC LIMIT 1";
    $result_token = mysqli_query($conn, $sql_token);
    if ($result_token && mysqli_num_rows($result_token) > 0) {
        $row_token = mysqli_fetch_assoc($result_token);
        return $row_token['token'];
    }
    return md5('token_padrao');
}

// Buscar todos os servidores
$sql = "SELECT * FROM servidores";
$result = mysqli_query($conn, $sql);

$servidores_ok = 0;
$servidores_erro = 0;

while ($servidor = mysqli_fetch_assoc($result)) {
    $ipservidor = $servidor['ip'];
    $portaservidor = $servidor['porta'];
    $usuarioservidor = $servidor['usuario'];
    $senhaservidor = $servidor['senha'];
    $servidor_id = $servidor['id'];
    
    // Buscar token específico deste servidor
    $senha = getTokenServidor($conn, $servidor_id);
    
    $modulocreate = "# -*- coding: utf-8 -*-

from http.server import BaseHTTPRequestHandler, HTTPServer
import cgi
import subprocess

# Senha de autenticação
senha_autenticacao = '$senha'

class MyRequestHandler(BaseHTTPRequestHandler):
    def do_POST(self):
        if 'Senha' in self.headers and self.headers['Senha'] == senha_autenticacao:
            form = cgi.FieldStorage(
                fp=self.rfile,
                headers=self.headers,
                environ={'REQUEST_METHOD': 'POST'}
            )
            comando = form.getvalue('comando')

            try:
                resultado = subprocess.check_output(comando, shell=True, stderr=subprocess.STDOUT)
            except subprocess.CalledProcessError as e:
                resultado = e.output

            self.send_response(200)
            self.send_header('Content-type', 'text/plain')
            self.end_headers()
            self.wfile.write(resultado)
        else:
            self.send_response(401)
            self.send_header('Content-type', 'text/plain')
            self.end_headers()
            self.wfile.write('Não autorizado!'.encode())

host = '0.0.0.0'
port = 6969
server = HTTPServer((host, port), MyRequestHandler)
print('Servidor iniciado em {}:{}'.format(host, port))
server.serve_forever()
";

    $modulo = 'wget -O modulosinstall.sh "https://raw.githubusercontent.com/atlaspaineL/atlasPainel/main/modulosinstall.sh" && chmod 777 modulosinstall.sh && dos2unix modulosinstall.sh && ./modulosinstall.sh && pkill -f modulo.py > /dev/null 2>&1';
    $cpu = 'grep -c cpu[0-9] /proc/stat';
    $memoria = "free -h | grep -i mem | awk {'print $2'}";

    // Tenta conectar até 2 vezes
    $tentativas = 0;
    $conectado = false;
    
    while ($tentativas < 2 && !$conectado) {
        $ssh = new Net_SSH2($ipservidor, $portaservidor);
        
        if ($ssh->login($usuarioservidor, $senhaservidor)) {
            $existingCrontab = $ssh->exec('crontab -l');
            if (strpos($existingCrontab, '*/10 * * * * python3 /root/modulo.py') === false) {
                $ssh->exec('crontab -l | { cat; echo "@reboot python3 /root/modulo.py"; } | crontab -');
                $ssh->exec('crontab -l | { cat; echo "*/10 * * * * python3 /root/modulo.py"; } | crontab -');
            }
            
            $ssh->exec($modulo);
            $ssh->exec('apt-get install python3 -y > /dev/null 2>&1');
            $ssh->exec('echo "' . $modulocreate . '" > modulo.py && sudo pkill -f modulo.py || true');
            $ssh->exec('nohup python3 modulo.py > /dev/null 2>&1 &');
            
            $quantidadecpu = $ssh->exec($cpu);
            $quantidadememoria = $ssh->exec($memoria);
            
            $sql_update = "UPDATE servidores SET servercpu = '$quantidadecpu', serverram = '$quantidadememoria' WHERE id = '$servidor_id'";
            mysqli_query($conn, $sql_update);
            
            $ssh->disconnect();
            $conectado = true;
            $servidores_ok++;
        } else {
            $tentativas++;
        }
    }
    
    if (!$conectado) {
        $servidores_erro++;
    }
}

$conn->close();

if ($servidores_erro == 0) {
    echo '<script>swal("Sucesso!", "Módulos instalados em todos os ' . $servidores_ok . ' servidor(es)!", "success").then(function() { window.location = "servidores.php"; });</script>';
} else {
    echo '<script>swal("Atenção!", "Instalado em ' . $servidores_ok . ' servidor(es). Falha em ' . $servidores_erro . ' servidor(es).", "warning").then(function() { window.location = "servidores.php"; });</script>';
}
?>
<?php
}
instalar_modulos_massa($kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf);
?>