<?php
session_start();
include('../AegisCore/conexao.php');

// Verificar permissões
if(!isset($_SESSION['login'])) {
    die("Acesso negado!");
}

// Processar envio de aplicativo
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['enviar_app'])) {
    
    // Validar dados
    $nome = mysqli_real_escape_string($conn, $_POST['nome']);
    $descricao = mysqli_real_escape_string($conn, $_POST['descricao']);
    $categoria = mysqli_real_escape_string($conn, $_POST['categoria']);
    $versao = mysqli_real_escape_string($conn, $_POST['versao']);
    $desenvolvedor = mysqli_real_escape_string($conn, $_POST['desenvolvedor']);
    
    // Processar upload do APK
    $apk_name = $_FILES['arquivo_apk']['name'];
    $apk_tmp = $_FILES['arquivo_apk']['tmp_name'];
    $apk_ext = strtolower(pathinfo($apk_name, PATHINFO_EXTENSION));
    
    if($apk_ext != 'apk') {
        die("Erro: Apenas arquivos .apk são permitidos!");
    }
    
    $apk_new_name = uniqid('apk_') . '.apk';
    $apk_path = '../loja/apps/' . $apk_new_name;
    
    // Processar upload do ícone
    $icone_name = $_FILES['icone']['name'];
    $icone_tmp = $_FILES['icone']['tmp_name'];
    $icone_ext = strtolower(pathinfo($icone_name, PATHINFO_EXTENSION));
    
    if(!in_array($icone_ext, ['png', 'jpg', 'jpeg'])) {
        die("Erro: Apenas imagens PNG/JPG são permitidas para ícones!");
    }
    
    $icone_new_name = uniqid('icon_') . '.' . $icone_ext;
    $icone_path = '../loja/icones/' . $icone_new_name;
    
    // Processar upload da imagem (opcional)
    $imagem_new_name = '';
    if(!empty($_FILES['imagem']['name'])) {
        $imagem_name = $_FILES['imagem']['name'];
        $imagem_tmp = $_FILES['imagem']['tmp_name'];
        $imagem_ext = strtolower(pathinfo($imagem_name, PATHINFO_EXTENSION));
        
        if(!in_array($imagem_ext, ['png', 'jpg', 'jpeg'])) {
            die("Erro: Apenas imagens PNG/JPG são permitidas para imagens!");
        }
        
        $imagem_new_name = uniqid('img_') . '.' . $imagem_ext;
        $imagem_path = '../loja/imagens/' . $imagem_new_name;
    }
    
    // Mover arquivos para os diretórios
    if(move_uploaded_file($apk_tmp, $apk_path) && move_uploaded_file($icone_tmp, $icone_path)) {
        if(!empty($imagem_new_name)) {
            move_uploaded_file($_FILES['imagem']['tmp_name'], $imagem_path);
        }
        
        // Inserir no banco de dados
        $sql = "INSERT INTO loja_apps (nome, descricao, categoria, arquivo_apk, icone, imagem, versao, desenvolvedor) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssss", $nome, $descricao, $categoria, $apk_new_name, $icone_new_name, $imagem_new_name, $versao, $desenvolvedor);
        
        if($stmt->execute()) {
            echo "<script>alert('Aplicativo enviado com sucesso!'); window.location.href='app-upload.php';</script>";
        } else {
            echo "Erro: " . $conn->error;
        }
    } else {
        echo "Erro ao fazer upload dos arquivos!";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Enviar Aplicativo</title>
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
            color: #4285F4;
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
            border-color: #4285F4;
            background: #f8f9fa;
        }
        .preview-img {
            max-width: 100px;
            max-height: 100px;
            margin-top: 10px;
        }
    </style>
    <link rel="stylesheet" href="../AegisCore/temas_visual.css">
</head>
<body class="<?php echo htmlspecialchars(getBodyClass($temaAtual ?? ['classe'=>'theme-dark'])); ?>">
    <div class="container">
        <div class="upload-container">
            <h2 class="form-title">Enviar Novo Aplicativo</h2>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="form-label">Nome do Aplicativo</label>
                    <input type="text" name="nome" class="form-control" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Descrição</label>
                    <textarea name="descricao" class="form-control" rows="3" required></textarea>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Categoria</label>
                        <select name="categoria" class="form-select" required>
                            <option value="Internet VPN">Internet VPN</option>
                            <option value="Utilitários">Utilitários</option>
                            <option value="Jogos">Jogos</option>
                            <option value="Redes Sociais">Redes Sociais</option>
                            <option value="Ferramentas">Ferramentas</option>
                        </select>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Versão</label>
                        <input type="text" name="versao" class="form-control" required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Desenvolvedor</label>
                    <input type="text" name="desenvolvedor" class="form-control" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Arquivo APK</label>
                    <div class="file-upload">
                        <input type="file" name="arquivo_apk" id="apk-upload" accept=".apk" required>
                        <p>Clique para selecionar o arquivo .apk</p>
                        <small class="text-muted">Tamanho máximo: 100MB</small>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Ícone do Aplicativo</label>
                    <div class="file-upload">
                        <input type="file" name="icone" id="icon-upload" accept="image/png, image/jpeg" required>
                        <p>Clique para selecionar o ícone (PNG/JPG)</p>
                        <img id="icon-preview" class="preview-img" style="display:none;">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Imagem de Exibição (Opcional)</label>
                    <div class="file-upload">
                        <input type="file" name="imagem" id="img-upload" accept="image/png, image/jpeg">
                        <p>Clique para selecionar a imagem (PNG/JPG)</p>
                        <img id="img-preview" class="preview-img" style="display:none;">
                    </div>
                </div>
                
                <button type="submit" name="enviar_app" class="btn btn-primary w-100 py-2">
                    <i class="fas fa-upload"></i> Enviar Aplicativo
                </button>
            </form>
        </div>
    </div>

    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <script>
        // Preview de imagens
        document.getElementById('icon-upload').addEventListener('change', function(e) {
            const preview = document.getElementById('icon-preview');
            const file = e.target.files[0];
            if(file) {
                preview.src = URL.createObjectURL(file);
                preview.style.display = 'block';
            }
        });
        
        document.getElementById('img-upload').addEventListener('change', function(e) {
            const preview = document.getElementById('img-preview');
            const file = e.target.files[0];
            if(file) {
                preview.src = URL.createObjectURL(file);
                preview.style.display = 'block';
            }
        });
    </script>
</body>
</html>

