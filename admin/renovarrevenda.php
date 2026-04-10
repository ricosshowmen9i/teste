<?php // @ioncube.dk $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf -> "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs" RANDOM
    $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf = "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs";

// ✅ CORREÇÃO: include e use FORA da função, antes de tudo
include_once('../vendor/event/autoload.php');
use React\EventLoop\Factory;

    function aleatorio718784($input)
    {
        ?>

<script src="../app-assets/sweetalert.min.js"></script>
<?php
if (!isset($_SESSION)){
    error_reporting(0);
    session_start();
}

if(!isset($_SESSION['login']) and !isset($_SESSION['senha'])){
    session_destroy();
    unset($_SESSION['login']);
    unset($_SESSION['senha']);
    header('location:index.php');
    exit();
}

include('headeradmin2.php');
include('../AegisCore/conexao.php');

$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

if (!file_exists('suspenderrev.php')) {
    exit("<script>alert('Token Invalido!');</script>");
} else {
    include_once 'suspenderrev.php';
}

if (!isset($_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe']) || !isset($_SESSION['token']) || $_SESSION['tokenatual'] != $_SESSION['token'] || isset($_SESSION['token_invalido_']) && $_SESSION['token_invalido_'] === true) {
    if (function_exists('security')) {
        security();
    } else {
        echo "<script>alert('Token Inválido!');</script>";
        echo "<script>location.href='../index.php';</script>";
        $_SESSION['token_invalido_'] = true;
        exit;
    }
}

date_default_timezone_set('America/Sao_Paulo');

function anti_sql($input) {
    $seg = preg_replace_callback("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/i", function($match) {
        return '';
    }, $input);
    $seg = trim($seg);
    $seg = strip_tags($seg);
    $seg = addslashes($seg);
    return $seg;
}

function getServidorToken($conn, $servidor_id) {
    $sql_token = "SELECT token FROM servidor_tokens WHERE servidor_id = '$servidor_id' AND status = 'ativo' ORDER BY id DESC LIMIT 1";
    $result_token = mysqli_query($conn, $sql_token);
    if ($result_token && mysqli_num_rows($result_token) > 0) {
        $row_token = mysqli_fetch_assoc($result_token);
        return $row_token['token'];
    }
    return md5($_SESSION['token']);
}

function buscarTodosRevendedores($conn, $id_pai, &$todos_ids = []) {
    if (!in_array($id_pai, $todos_ids)) {
        $todos_ids[] = $id_pai;
    }
    $sql    = "SELECT id FROM accounts WHERE byid = '$id_pai'";
    $result = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        buscarTodosRevendedores($conn, $row['id'], $todos_ids);
    }
    return $todos_ids;
}

function calcularDiasRestantes($data_expira) {
    if (empty($data_expira)) return 30;
    $hoje   = new DateTime();
    $expira = new DateTime($data_expira);
    if ($expira < $hoje) return 1;
    $diferenca = $hoje->diff($expira);
    return max(1, $diferenca->days);
}

$_GET['id'] = anti_sql($_GET['id'] ?? '');

if (empty($_GET['id'])) {
    echo "<script>alert('ID não fornecido!'); window.location.href='listarrevendedores.php';</script>";
    exit();
}

$id = $_GET['id'];

// ─── BUSCA DADOS DO REVENDEDOR ────────────────────────────────────────────────
$sql    = "SELECT * FROM atribuidos WHERE userid = '$id'";
$result = mysqli_query($conn, $sql);
$row    = mysqli_fetch_assoc($result);

if (!$row) {
    echo "<script>alert('Revendedor não encontrado!'); window.location.href='listarrevendedores.php';</script>";
    exit();
}

$expira    = $row['expira'];
$categoria = $row['categoriaid'];
$suspenso  = $row['suspenso'];

// ─── CALCULA NOVA DATA ────────────────────────────────────────────────────────
if ($expira < date('Y-m-d H:i:s')) {
    $expira = date('Y-m-d H:i:s');
}
$nova_expira = date('Y-m-d H:i:s', strtotime("+30 days", strtotime($expira)));

// ─── ATUALIZA VENCIMENTO ──────────────────────────────────────────────────────
$conn->query("UPDATE atribuidos SET expira = '$nova_expira' WHERE userid = '$id'");

// ─── SE ESTAVA SUSPENSO, REATIVA TUDO ────────────────────────────────────────
if ($suspenso == '1') {

    $todos_revendedores = [];
    buscarTodosRevendedores($conn, $id, $todos_revendedores);
    $todos_revendedores = array_unique($todos_revendedores);

    $ssh_accounts = [];
    if (!empty($todos_revendedores)) {
        $ids_str    = implode(",", $todos_revendedores);
        $sql_ssh    = "SELECT login, senha, expira, limite, uuid FROM ssh_accounts WHERE byid IN ($ids_str)";
        $result_ssh = $conn->query($sql_ssh);
        while ($row_ssh = $result_ssh->fetch_assoc()) {
            $ssh_accounts[] = $row_ssh;
        }
    }

    // ── Prepara arquivo ───────────────────────────────────────────────────────
    $nome_arquivo     = substr(md5(uniqid(rand(), true)), 0, 10) . ".txt";
    $caminho_completo = __DIR__ . '/' . $nome_arquivo;

    if (!empty($ssh_accounts)) {
        $file = fopen($caminho_completo, "w");
        foreach ($ssh_accounts as $ssh_account) {
            $login  = $ssh_account['login'];
            $senha  = $ssh_account['senha'];
            $dias   = calcularDiasRestantes($ssh_account['expira']);
            $limite = $ssh_account['limite'] ?? 1;
            $uuid   = $ssh_account['uuid'] ?? '';
            fwrite($file, "$login $senha $dias $limite $uuid\n");
        }
        fclose($file);
    }

    // ── Envia para servidores ─────────────────────────────────────────────────
    $sql_servidores    = "SELECT * FROM servidores WHERE subid = '$categoria'";
    $result_servidores = $conn->query($sql_servidores);

    if ($result_servidores->num_rows > 0) {
        $loop = Factory::create();

        while ($servidor = mysqli_fetch_assoc($result_servidores)) {
            $ip             = $servidor['ip'];
            $servidor_id    = $servidor['id'];
            $token_servidor = getServidorToken($conn, $servidor_id);

            $socket = @fsockopen($ip, 6969, $errno, $errstr, 5);
            if ($socket) {
                fclose($socket);

                $loop->addTimer(0.1, function () use ($ip, $caminho_completo, $nome_arquivo, $token_servidor) {
                    if (!file_exists($caminho_completo) || filesize($caminho_completo) == 0) return;

                    $limiter_content = file_get_contents($caminho_completo);
                    $headers = [
                        'Senha: ' . $token_servidor,
                        'User-Agent: Atlas-Renovar-Revenda/1.0'
                    ];

                    $ch1 = curl_init();
                    curl_setopt($ch1, CURLOPT_URL, 'http://' . $ip . ':6969');
                    curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch1, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch1, CURLOPT_POST, 1);
                    curl_setopt($ch1, CURLOPT_POSTFIELDS, http_build_query([
                        'comando' => 'echo "' . addslashes($limiter_content) . '" > /root/' . $nome_arquivo
                    ]));
                    curl_setopt($ch1, CURLOPT_TIMEOUT, 30);
                    $output1   = curl_exec($ch1);
                    $httpCode1 = curl_getinfo($ch1, CURLINFO_HTTP_CODE);
                    curl_close($ch1);

                    if ($httpCode1 == 200) {
                        $ch2 = curl_init();
                        curl_setopt($ch2, CURLOPT_URL, 'http://' . $ip . ':6969');
                        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch2, CURLOPT_HTTPHEADER, $headers);
                        curl_setopt($ch2, CURLOPT_POST, 1);
                        curl_setopt($ch2, CURLOPT_POSTFIELDS, http_build_query([
                            'comando' => 'sudo python3 /etc/xis/sincronizar.py ' . $nome_arquivo . ' > /dev/null 2>/dev/null &'
                        ]));
                        curl_setopt($ch2, CURLOPT_TIMEOUT, 10);
                        curl_exec($ch2);
                        curl_close($ch2);
                    }
                });
            }
        }

        $loop->run();
    }

    // ── Atualiza banco — remove suspensão ────────────────────────────────────
    foreach ($todos_revendedores as $rev_id) {
        $conn->query("UPDATE atribuidos SET suspenso = '0' WHERE userid = '$rev_id'");
        $conn->query("UPDATE accounts SET status = 'Ativo' WHERE id = '$rev_id'");
    }

    if (!empty($todos_revendedores)) {
        $ids_str = implode(",", $todos_revendedores);
        $conn->query("UPDATE ssh_accounts SET mainid = '', status = 'Offline' WHERE byid IN ($ids_str)");
    }

    if (file_exists($caminho_completo)) {
        unlink($caminho_completo);
    }

    $data_log  = date('d/m/Y H:i:s');
    $total_rev = count($todos_revendedores);
    $total_usr = count($ssh_accounts);
    $log_texto = "Admin renovou e reativou revendedor ID $id + " . ($total_rev - 1) . " sub-revendedores e $total_usr usuarios";
    $conn->query("INSERT INTO logs (revenda, validade, texto, userid) VALUES ('{$_SESSION['login']}', '$data_log', '$log_texto', '{$_SESSION['iduser']}')");

    echo '<script>sweetAlert("Sucesso!", "Renovacao e reativacao realizadas!\n\nRevendedores reativados: ' . $total_rev . '\nUsuarios reativados: ' . $total_usr . '", "success").then(function() {
        window.location.href = "listarrevendedores.php";
    });</script>';

} else {
    // Apenas renovou — não estava suspenso
    $data_log  = date('d/m/Y H:i:s');
    $log_texto = "Admin renovou revendedor ID $id por mais 30 dias";
    $conn->query("INSERT INTO logs (revenda, validade, texto, userid) VALUES ('{$_SESSION['login']}', '$data_log', '$log_texto', '{$_SESSION['iduser']}')");

    echo '<script>sweetAlert("Sucesso!", "Renovacao realizada com sucesso!\n\nNova validade: ' . date('d/m/Y', strtotime($nova_expira)) . '", "success").then(function() {
        window.location.href = "listarrevendedores.php";
    });</script>';
}

mysqli_close($conn);
?>
                       <?php
    }
    aleatorio718784($kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf);
?>