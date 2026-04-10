<?php // @ioncube.dk $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf -> "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs" RANDOM
    $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf = "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs";
    function aleatorio506098($input)
    {
        ?>
    
<?php

error_reporting(0);
session_start();
include('../AegisCore/conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Consulta todas categorias e quantidade de servidores
$sql = "SELECT * FROM servidores";
$result = $conn->query($sql);
$servidores = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $servidores[] = $row;
    }
}
$numServidores = $result->num_rows; // Número de servidores encontrados

// Consulta todas categorias e quantidade de servidores
$sql2 = "SELECT * FROM categorias";
$result2 = $conn->query($sql2);
$categorias = [];
if ($result2->num_rows > 0) {
    while ($row2 = $result2->fetch_assoc()) {
        $categorias[] = $row2;
    }
}
$numCategorias = $result2->num_rows; // Número de categorias encontradas

// Consulta a quantidade de contas SSH
$sql3 = "SELECT * FROM ssh_accounts";
$result3 = $conn->query($sql3);
$contas = [];
if ($result3->num_rows > 0) {
    while ($row3 = $result3->fetch_assoc()) {
        $contas[] = $row3;
    }
}
$numContasSSH = $result3->num_rows; // Número de contas SSH encontradas

// Agora você pode mostrar os números encontrados
echo "Número de servidores: " . $numServidores . "<br>";
echo "Número de categorias: " . $numCategorias . "<br><br>";
//mostrar o nome de todas as categorias
echo "Nome de todas as categorias: <br>";
foreach ($categorias as $categoria) {
    echo $categoria['nome'] . "<br>";
}
echo "<br>";
echo "Número de contas SSH: " . $numContasSSH . "<br>";
echo "Ta nova:";

?>
                       <?php
    }
    aleatorio506098($kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf);
?>
