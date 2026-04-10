<?php
session_start();
include('conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

echo "<h2>Informações do Token</h2>";
echo "Token da sessão: " . $_SESSION['token'] . "<br>";
echo "MD5 do token: " . md5($_SESSION['token']) . "<br>";

// Buscar servidores
$sql = "SELECT * FROM servidores";
$result = mysqli_query($conn, $sql);

while ($row = mysqli_fetch_assoc($result)) {
    echo "<hr>";
    echo "<strong>Servidor: " . $row['nome'] . " (" . $row['ip'] . ")</strong><br>";
    
    // Testar com token original
    echo "Testando com token original...<br>";
    $headers = array('Senha: ' . $_SESSION['token']);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $row['ip'] . ':6969');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "comando=echo 'teste'");
    $output = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo "HTTP Code: $http_code<br>";
    echo "Resposta: " . htmlspecialchars($output) . "<br>";
    
    if ($http_code == 200) {
        echo "✅ Token ORIGINAL funcionou!<br>";
    } else {
        // Testar com MD5
        echo "Testando com MD5...<br>";
        $headers = array('Senha: ' . md5($_SESSION['token']));
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $row['ip'] . ':6969');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "comando=echo 'teste'");
        $output = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        echo "HTTP Code: $http_code<br>";
        
        if ($http_code == 200) {
            echo "✅ Token MD5 funcionou!<br>";
        } else {
            echo "❌ Nenhum token funcionou!<br>";
        }
    }
}
?>