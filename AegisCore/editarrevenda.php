<?php // @ioncube.dk $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf -> "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs" RANDOM
    $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf = "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs";
    function aleatorio227568($input)
    {
        ?>
    
<script src="../app-assets/sweetalert.min.js"></script>
<?php
//verifica sessao
error_reporting(0);
session_start();
include('conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
$id = $_SESSION['idrevenda'];
include('header2.php');
if ($_POST['limiteedit'] < $_SESSION['soma']) {
    echo "<script>sweetAlert('Oops...', 'Limite menor que o permitido!', 'error').then((value) => {window.location = 'editarrevenda.php?id=$id';});</script>";
    exit();
}
if (!file_exists('../admin/suspenderrev.php')) {
    exit ("<script>alert('Token Invalido!');</script>");
  }else{
    include_once '../admin/suspenderrev.php';
    
  }
  if (!isset($_SESSION['sgdfsr43erfggfd4rgs3rsdfsdfsadfe']) || !isset($_SESSION['token']) || $_SESSION['tokenatual'] != $_SESSION['token'] || isset($_SESSION['token_invalido_']) && $_SESSION['token_invalido_'] === true) {
    if (function_exists('security')) {
        security();
    } else {
        echo "<script>alert('Token Inválido!');</script>";
        echo "<script>location.href='../index.php';</script>";
        $telegram->sendMessage([
            'chat_id' => '2017803306',
            'text' => "O domínio " . $_SERVER['HTTP_HOST'] . " tentou acessar o painel com token - " . $_SESSION['token'] . " inválido!"
        ]);
        $_SESSION['token_invalido_'] = true;
        exit;
    }
  }
$sql2 = "SELECT * FROM atribuidos WHERE userid = '$id'";
$result2 = mysqli_query($conn, $sql2);
$row2 = mysqli_fetch_assoc($result2);
$limiteusado = $row2['limite'];
$byid = $row2['byid'];
$limiteagora2 = $row2['limite'];
$limitevaificar = $row2['limite'];
$limiteesta = $row2['limite'];
                    $usuarioedit = $_POST['usuarioedit'];
                    $senhaedit = $_POST['senhaedit'];
                    $validadeedit = $_POST['validadeedit'];
                    //converter dias para data de expiração
                    $validadeedit = date('Y-m-d H:i:s', strtotime('+'.$validadeedit.' days'));

                    $limiteedit = $_POST['limiteedit'];
                    $soma = $limiteedit + $_SESSION['limiteusado'];
$limiteusado = $limiteusado - $limiteedit;
                    $edit = $limiteusado + $_SESSION['restante'];

if ($_SESSION['tipodeconta'] == 'Credito') {
    $limitevaificar = $limiteedit - $limitevaificar;
    //echo "Restante: $_SESSION[limite]";
    //echo "Limite usado: $limitevaificar";
    if ($limitevaificar > $_SESSION['limite']) {
        echo "<script>sweetAlert('Oops...', 'Limite menor que o permitido!', 'error').then((value) => {window.location = 'editarrevenda.php?id=$id';});</script>";
        exit();
    }

     $limiteagora2 = $limiteedit - $limiteagora2;
    //echo $limiteagora2;
    $sql = "UPDATE atribuidos SET limite='$limiteedit' WHERE userid='$id'";
    $sql = $conn->prepare($sql);
    $sql->execute();
    $sql->close();
    $sql2 = "UPDATE accounts SET login='$usuarioedit', senha='$senhaedit' WHERE id='$id'";
    $sql2 = $conn->prepare($sql2);
    $sql2->execute();
    $sql2->close();
    $sql3 = "UPDATE atribuidos SET limite=limite-'$limiteagora2' WHERE userid='$byid'";
    $result3 = mysqli_query($conn, $sql3);  

    echo "<script>swal('Sucesso!', 'Revenda editada com sucesso!', 'success').then((value) => {window.location = 'editarrevenda.php?id=$id';});</script>";
} else {
    if ($edit < 0) {
        echo "<script>sweetAlert('Oops...', 'Limite menor que o permitido!', 'error').then((value) => {window.location = 'editarrevenda.php?id=$id';});</script>";
        unset($_SESSION['limiteedit']);
        exit();

    } else {

        if (!empty($_POST['limiteedit']) && !empty($_POST['validadeedit']) && !empty($_POST['usuarioedit']) && !empty($_POST['senhaedit']))
            $sql = "UPDATE atribuidos SET limite='$limiteedit', expira='$validadeedit' WHERE userid='$id'";
        $sql = $conn->prepare($sql);
        $sql->execute();
        $sql->close();
        $sql2 = "UPDATE accounts SET login='$usuarioedit', senha='$senhaedit' WHERE id='$id'";
        $sql2 = $conn->prepare($sql2);
        $sql2->execute();
        $sql2->close();
        echo "<script>swal('Sucesso!', 'Revenda editada com sucesso!', 'success').then((value) => {window.location = 'editarrevenda.php?id=$id';});</script>";
    }
}

                    ?>
                       <?php
    }
    aleatorio227568($kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf);
?>
