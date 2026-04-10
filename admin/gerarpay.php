<?php // @ioncube.dk $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf -> "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs" RANDOM
    $kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf = "TvElGMdz8wa1V3uq3DRqlbtRKqz5MdNl8qoPWwiEr9uCK2Q8Gs";
    function aleatorio662608($input)
    {
        ?>
    
<script src="../app-assets/sweetalert.min.js"></script>
<?php 
error_reporting(0);
session_start();
//se a sessão não existir, redireciona para o login
if(!isset($_SESSION['login']) and !isset($_SESSION['senha'])){
    session_destroy();
    unset($_SESSION['login']);
    unset($_SESSION['senha']);
    header('location:../index.php');
}
include('../AegisCore/conexao.php');
$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
    }

$id = $_SESSION['iduser'];
include_once 'headeradmin2.php';

?>

<div class="app-content content">
        <div class="content-overlay"></div>
        <div class="content-wrapper">
        <p class="text-primary">Aqui você pode Gerar uma Payload.</p>
            <div class="content-header row">
            </div>
            <div class="content-body">
                <section id="dashboard-ecommerce">
                    <div class="row">
                <section id="basic-horizontal-layouts">
                    <div class="row match-height">
                        <div class="col-md-6 col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title">Gerador de Payload</h4>
                                </div>
                                <div class="card-content">
                                    <div class="card-body">
                                            <div class="form-body">
                                            <br>
                                            <br>
                                                <div class="row">
                                                    

                                                    <div class="col-md-4">
                                                        <label>SNI (Se for Direct Não Precisa)</label>
                                                    </div>
                                                    <div class="col-md-8 form-group">
                                                        <input type="text" class="form-control" id="sni" placeholder="Ex: google.com">
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label>Dominio Servidor</label>
                                                    </div>
                                                    <div class="col-md-8 form-group">
                                                        <input type="text" class="form-control" id="dominio" placeholder="Ex: meuservidor1.cloudflare.com">
                                                    </div>
                                                
                                                    <div class="col-md-4">
                                                <label>Selecione o Modo</label>
                                            </div>
                                            <div class="col-md-8 form-group">
                                                <select class="form-control select2-size-sm" id="modo" onchange="mostrar()">
                                                    <option value="Direct">Direct</option>
                                                    <option value="Ssl">Ssl</option>
                                                </select>
                                            </div>
                                                    <textarea id="payload" rows="3" class="form-control" style="overflow: hidden; overflow-wrap: break-word; resize: none; height: 80px;"></textarea>
                                                    <div class="col-12 col-md-8 offset-md-4 form-group">
                                                        <fieldset>
                                                            
                                                        </fieldset>
                                                    </div>
                                                    
                                                    <div class="col-sm-12 d-flex justify-content-end">
                                                        <button onclick="gerarPayload()" class="btn btn-primary mr-1 mb-1">Gerar</button>
                                                        <a href="home.php" class="btn btn-light-secondary mr-1 mb-1">Cancelar</a>
                                                    </div>
                                                </div>
                                            </div>
                                        
                                    </div>
                                </div>
                            </div>
                        </div>
                        <script>
 function gerarPayload() {
        var modo = document.getElementById("modo").value;
        var payload;

        if (modo === "Direct") {
            var dominio = document.getElementById("dominio").value;
            payload = "GET / HTTP/1.1[crlf]Host: " + dominio + "[crlf]Upgrade: Websocket[crlf]Connection: Upgrade[crlf][crlf]";
        } else if (modo === "Ssl") {
            var sni = document.getElementById("sni").value;
            var dominio = document.getElementById("dominio").value;
            payload = "CONNECT / HTTP/1.1[crlf]Host: " + sni + "[crlf][crlf][crlf][crlf]OPTIONS- // HTTP/1.1[crlf]Host: " + dominio + "[crlf]Connection: Upgrade[crlf]Upgrade: Websocket[crlf][crlf]";
        }

        document.getElementById("payload").textContent = payload;
    }
</script>
     <script src="../app-assets/js/scripts/forms/number-input.js"></script>
                       <?php
    }
    aleatorio662608($kOc5k3wJRKbpQVn4eFK5X2uqqpduW8WWcQVpavWeM9vGYzqzzf);
?>
