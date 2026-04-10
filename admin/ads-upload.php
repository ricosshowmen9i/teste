<?php
session_start();
include('../AegisCore/conexao.php');

// Verificar permissões
if(!isset($_SESSION['login'])) {
    die("Acesso negado!");
}

// Processar envio de anúncio
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['enviar_anuncio'])) {
    
    // Validar dados
    $titulo = mysqli_real_escape_string($conn, $_POST['titulo']);
    $descricao = mysqli_real_escape_string($conn, $_POST['descricao']);
    $link = mysqli_real_escape_string($conn, $_POST['link']);
    $data_inicio = mysqli_real_escape_string($conn, $_POST['data_inicio']);
    $data_fim = mysqli_real_escape_string($conn, $_POST['data_fim']);
    
    // Processar upload da imagem
    $imagem_name = $_FILES['imagem']['name'];
    $imagem_tmp = $_FILES['imagem']['tmp_name'];
    $imagem_ext = strtolower(pathinfo($imagem_name, PATHINFO_EXTENSION));
    
    if(!in_array($imagem_ext, ['png', 'jpg', 'jpeg'])) {
        die("Erro: Apenas imagens PNG/JPG são permitidas!");
    }
    
    $imagem_new_name = uniqid('ads_') . '.' . $imagem_ext;
    $imagem_path = '../loja/anuncios/' . $imagem_new_name;
    
    // Mover arquivo para o diretório
    if(move_uploaded_file($imagem_tmp, $imagem_path)) {
        // Inserir no banco de dados
        $sql = "INSERT INTO loja_anuncios (titulo, descricao, imagem, link, data_inicio, data_fim) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssss", $titulo, $descricao, $imagem_new_name, $link, $data_inicio, $data_fim);
        
        if($stmt->execute()) {
            echo "<script>alert('Anúncio enviado com sucesso!'); window.location.href='ads-upload.php';</script>";
        } else {
            echo "Erro: " . $conn->error;
        }
    } else {
        echo "Erro ao fazer upload da imagem!";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Enviar Anúncio</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .upload-container {
            max-width: 800px;
            margin: 30px auto;
            padding: 20px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .form-title {
            color: #EA4335;
            margin-bottom: 30px;
            text-align: center;
        }
        .file-upload {
            border: 2px dashed #ddd;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .file-upload:hover {
            border-color: #EA4335;
            background: #f8f9fa;
        }
        .preview-img {
            max-width: 100%;
            max-height: 200px;
            margin-top: 10px;
            border-radius: 5px;
        }
    </style>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
</head>
<body class="<?php echo htmlspecialchars(getBodyClass($temaAtual ?? ['classe'=>'theme-dark'])); ?>">
    <div class="container">
        <div class="upload-container">
            <h2 class="form-title">Enviar Novo Anúncio</h2>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="form-label">Título do Anúncio</label>
                    <input type="text" name="titulo" class="form-control" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Descrição</label>
                    <textarea name="descricao" class="form-control" rows="3" required></textarea>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Link (Opcional)</label>
                    <input type="url" name="link" class="form-control" placeholder="https://">
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Data de Início</label>
                        <input type="date" name="data_inicio" class="form-control" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Data de Término</label>
                        <input type="date" name="data_fim" class="form-control" required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Imagem do Anúncio</label>
                    <div class="file-upload">
                        <input type="file" name="imagem" id="img-upload" accept="image/png, image/jpeg" required>
                        <p>Clique para selecionar a imagem (PNG/JPG)</p>
                        <img id="img-preview" class="preview-img" style="display:none;">
                        <small class="text-muted">Recomendado: 1200x600px</small>
                    </div>
                </div>
                
                <button type="submit" name="enviar_anuncio" class="btn btn-danger w-100 py-2">
                    <i class="fas fa-bullhorn"></i> Publicar Anúncio
                </button>
            </form>
        </div>
    </div>

    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <script>
        // Preview da imagem
        document.getElementById('img-upload').addEventListener('change', function(e) {
            const preview = document.getElementById('img-preview');
            const file = e.target.files[0];
            if(file) {
                preview.src = URL.createObjectURL(file);
                preview.style.display = 'block';
            }
        });
        
        // Definir data mínima (hoje) para data de início
        document.querySelector('input[name="data_inicio"]').min = new Date().toISOString().split('T')[0];
        
        // Quando data de início muda, atualizar data mínima de término
        document.querySelector('input[name="data_inicio"]').addEventListener('change', function() {
            document.querySelector('input[name="data_fim"]').min = this.value;
        });
    </script>
</body>
</html>

