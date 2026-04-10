<?php // @ioncube.dk $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf -> "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs" RANDOM
    $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf = "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs";
    function aleatorio234339($input)
    {
        ?>
    
<script src="../app-assets/sweetalert.min.js"></script>

<?php

session_start();
include('../AegisCore/conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
include('headeradmin2.php');
//limpar tabela deviceid
if ($_SESSION['login'] !== 'admin') {
    // Se não for, destrói a sessão e redireciona para a página de login
    session_destroy();
    header('Location: index.php');
    exit();
}
$sql = "DELETE FROM atlasdeviceid";
$result = $conn -> query($sql);
//limpar tabela userlimiter
$sql2 = "DELETE FROM userlimiter";
$result2 = $conn -> query($sql2);
echo "<script>swal('Sucesso!', 'Todos os DeviceIDs foram resetados com sucesso!', 'success');</script>";
echo "<script>setTimeout(\"location.href = 'listarusuarios.php';\",1500);</script>";

?>
                       <?php
    }
    aleatorio234339($kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf);
?>
