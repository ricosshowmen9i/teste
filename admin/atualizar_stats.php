<?php // @ioncube.dk $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf -> "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs" RANDOM
    $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf = "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs";
    function aleatorio_atualizar_stats($input)
    {
        ?>
<?php
error_reporting(0);
session_start();

include('../AegisCore/conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

if (!file_exists('suspenderrev.php')) {
    exit ("<script>alert('Token Invalido!');</script>");
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

$id = $_GET['id'];
$id = mysqli_real_escape_string($conn, $id);

$consulta  = "SELECT * FROM servidores WHERE id = '$id'";
$resultado = $conn->query($consulta);

if ($resultado->num_rows === 0) {
    echo json_encode(['cpu' => 'N/A', 'memoria' => 'N/A']);
    exit;
}

$row        = $resultado->fetch_assoc();
$ip         = $row['ip'];
$servidor_id = $row['id'];

// ✅ CPU — percentual de uso real
$command_cpu = "top -bn1 | awk '/Cpu/ { printf \"%.1f%%\", \$2 + \$4 }'";

// ✅ RAM — usado/total em MB com percentual (usando free -m que é confiável)
$command_ram = "free -m | awk 'NR==2 { printf \"%dMB/%dMB (%.0f%%)\", \$3, \$2, \$3*100/\$2 }'";

// ✅ Comando único: CPU na linha 1, RAM na linha 2
$command = $command_cpu . ' && echo "" && ' . $command_ram;

// Buscar token para este servidor
$sql_token    = "SELECT token FROM servidor_tokens WHERE servidor_id = '$servidor_id' AND status = 'ativo' ORDER BY id DESC LIMIT 1";
$result_token = mysqli_query($conn, $sql_token);

if ($result_token && mysqli_num_rows($result_token) > 0) {
    $row_token = mysqli_fetch_assoc($result_token);
    $senha     = $row_token['token'];
} else {
    $senha = md5($_SESSION['token']);
}

$headers = ['Senha: ' . $senha];

// ✅ Busca CPU
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://$ip:6969");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_TIMEOUT, 3);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['comando' => $command_cpu]));
$output_cpu  = curl_exec($ch);
$httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// ✅ Busca RAM
$ch2 = curl_init();
curl_setopt($ch2, CURLOPT_URL, "http://$ip:6969");
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch2, CURLOPT_POST, 1);
curl_setopt($ch2, CURLOPT_TIMEOUT, 3);
curl_setopt($ch2, CURLOPT_POSTFIELDS, http_build_query(['comando' => $command_ram]));
$output_ram  = curl_exec($ch2);
$httpCode2   = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

$cpu     = 'Offline';
$memoria = 'Offline';

if ($httpCode == 200 && $output_cpu) {
    $cpu = trim($output_cpu);
    if (empty($cpu)) $cpu = '0%';
}

if ($httpCode2 == 200 && $output_ram) {
    $memoria = trim($output_ram);
    if (empty($memoria)) $memoria = '0%';
}

echo json_encode(['cpu' => $cpu, 'memoria' => $memoria]);
?>
<?php
    }
    aleatorio_atualizar_stats($kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf);
?>