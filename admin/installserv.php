<?php
// @ioncube.dk $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf -> "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs" RANDOM
$kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf = "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs";
function install_servidor($input) {
?>
<script src="../app-assets/sweetalert.min.js"></script>
<?php

error_reporting(0);
session_start();
include('../AegisCore/conexao.php');

set_time_limit(0);
ignore_user_abort(true);
set_include_path(get_include_path() . PATH_SEPARATOR . '../lib2');

if ($_SESSION['login'] !== 'admin') {
    session_destroy();
    header('Location: index.php');
    exit();
}

include('Net/SSH2.php');
include('headeradmin2.php');

// Buscar token específico do servidor
function getTokenServidor($conn, $servidor_id) {
    $sql_token = "SELECT token FROM servidor_tokens WHERE servidor_id = '$servidor_id' AND status = 'ativo' ORDER BY id DESC LIMIT 1";
    $result_token = mysqli_query($conn, $sql_token);
    if ($result_token && mysqli_num_rows($result_token) > 0) {
        $row_token = mysqli_fetch_assoc($result_token);
        return $row_token['token'];
    }
    return md5('token_padrao'); // Token padrão caso não exista
}

if (isset($_SESSION['ipservidor']) && isset($_SESSION['portaservidor']) && isset($_SESSION['usuarioservidor']) && isset($_SESSION['senhaservidor'])) {
    $ipservidor = $_SESSION['ipservidor'];
    $portaservidor = $_SESSION['portaservidor'];
    $usuarioservidor = $_SESSION['usuarioservidor'];
    $senhaservidor = $_SESSION['senhaservidor'];
    $nomeservidor = $_SESSION['nomeservidor'];
    $categoriaservidor = $_SESSION['categoriaservidor'] ?? '1';
    
    // Buscar o ID do servidor recém-inserido
    $sql_id = "SELECT id FROM servidores WHERE ip = '$ipservidor' ORDER BY id DESC LIMIT 1";
    $result_id = mysqli_query($conn, $sql_id);
    $row_id = mysqli_fetch_assoc($result_id);
    $servidor_id = $row_id['id'];
    
    // Obter token específico para este servidor
    $senha = getTokenServidor($conn, $servidor_id);
    
    $modulocreate = "# -*- coding: utf-8 -*-

from http.server import BaseHTTPRequestHandler, HTTPServer
import cgi
import subprocess

# Senha de autenticação
senha_autenticacao = '$senha'

# Classe de manipulador de solicitações
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
    
    $ssh = new Net_SSH2($ipservidor, $portaservidor);
    if (!$ssh->login($usuarioservidor, $senhaservidor)) {
        echo '<script>swal("Erro!", "Falha na autenticação do servidor!", "error").then(function() { window.location = "adicionarservidor.php"; });</script>';
        $sql = $conn->query("DELETE FROM servidores WHERE ip = '$ipservidor'");
        exit();
    }
    
    $existingCrontab = $ssh->exec('crontab -l');
    if (strpos($existingCrontab, '*/10 * * * * python3 /etc/xis/modulo.py') === false) {
        $ssh->exec('crontab -l | { cat; echo "@reboot python3 /etc/xis/modulo.py"; } | crontab -');
        $ssh->exec('crontab -l | { cat; echo "*/10 * * * * python3 /etc/xis/modulo.py"; } | crontab -');
    }
    
    $ssh->exec($modulo);
    $ssh->exec('apt-get install python3 -y > /dev/null 2>&1');
    $ssh->exec('echo "' . $modulocreate . '" > modulo.py && sudo pkill -f modulo.py || true');
    $ssh->exec('nohup python3 modulo.py > /dev/null 2>&1 &');
    
    $quantidadecpu = $ssh->exec($cpu);
    $quantidadememoria = $ssh->exec($memoria);
    
    $sql = "UPDATE servidores SET servercpu = '$quantidadecpu', serverram = '$quantidadememoria' WHERE ip = '$ipservidor'";
    $result = $conn->query($sql);
    
    $ssh->disconnect();

    unset($_SESSION['ipservidor']);
    unset($_SESSION['portaservidor']);
    unset($_SESSION['usuarioservidor']);
    unset($_SESSION['senhaservidor']);
    unset($_SESSION['nomeservidor']);
    unset($_SESSION['categoriaservidor']);
    
    echo '<script>swal("Servidor Adicionado com Sucesso!", "Token configurado automaticamente", "success").then(function() { window.location = "servidores.php"; });</script>';
}
?>
<?php
}
install_servidor($kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf);
?>