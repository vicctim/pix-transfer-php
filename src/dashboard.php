<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'models/UploadSession.php';
require_once 'models/User.php';

$uploadSession = new UploadSession();
$user = new User();
$userData = $user->getById($_SESSION['user_id']);

$uploads = $uploadSession->getByUserId($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Upload System</title>
    <link rel="icon" type="image/png" href="src/img/favicon.png">
    <style>
        @import url('https://fonts.googleapis.com/css?family=Titillium+Web:400,600,700');
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Titillium Web', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f7fcf5;
            color: #333;
        }

        .header {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: #7cb342;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-name {
            font-weight: 500;
        }

        .logout-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            text-decoration: none;
        }

        .container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 20px;
        }

        .main-content {
            display: flex;
            gap: 30px;
            align-items: flex-start;
        }

        .upload-container {
            flex: 2;
        }

        .list-container {
            flex: 1;
            position: sticky;
            top: 90px;
            max-height: calc(100vh - 110px);
            overflow-y: auto;
        }

        .upload-section {
            max-width: 100%;
        }

        .file-drop-zone {
            flex: 1;
            padding: 30px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border-right: 1px solid #e1e5e9;
        }

        .file-drop-zone.has-files {
            justify-content: flex-start;
        }

        .file-drop-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #e8f5e9;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: #7cb342;
            margin-bottom: 20px;
            border: 2px dashed #c8e6c9;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .file-drop-icon:hover, .file-drop-zone.dragover .file-drop-icon {
            transform: scale(1.1);
            background: #c8e6c9;
        }

        .file-list {
            width: 100%;
            max-height: 200px;
            overflow-y: auto;
            margin-bottom: 20px;
        }

        .file-item-sm {
            font-size: 0.9rem;
            padding: 8px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .file-item-sm:last-child {
            border-bottom: none;
        }

        .upload-form-container {
            flex: 1;
            padding: 30px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #e1e5e9;
            border-radius: 8px;
            font-size: 1rem;
            background: #f8f9fa;
            font-family: 'Titillium Web', sans-serif;
        }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
            outline: none;
            border-color: #7cb342;
            background: white;
        }
        
        .radio-group {
            border: 1px solid #e1e5e9;
            border-radius: 8px;
            overflow: hidden;
            display: flex;
        }

        .radio-label {
            flex: 1;
            padding: 12px;
            text-align: center;
            cursor: pointer;
            transition: background 0.3s ease;
            position: relative;
        }
        .radio-label input {
            position: absolute;
            opacity: 0;
        }
        .radio-label:has(input:checked) {
            background: #e8f5e9;
            color: #7cb342;
            font-weight: 600;
        }
        
        .options-row {
            display: flex;
            justify-content: flex-start;
            align-items: center;
            margin-top: 20px;
        }

        .main-action-btn {
            width: 100%;
            padding: 15px;
            font-size: 1.1rem;
            margin-top: 20px;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
        }

        .upload-hint {
            font-size: 0.9rem;
            color: #999;
        }

        .file-input {
            display: none;
        }

        .upload-form {
            margin-top: 20px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .btn {
            background: #7cb342;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn:hover {
            background: #689f38;
            transform: translateY(-1px);
            box-shadow: 0 5px 15px rgba(124, 179, 66, 0.3);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .uploads-list {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .upload-item {
            border: 1px solid #e1e5e9;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }

        .upload-item:hover {
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-color: #c8e6c9;
        }

        .upload-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .upload-title {
            font-weight: 600;
            color: #333;
        }

        .upload-date {
            font-size: 0.9rem;
            color: #666;
        }

        .upload-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-size: 0.8rem;
            color: #999;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .detail-value {
            font-weight: 500;
            color: #333;
            word-break: break-all;
        }

        .upload-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #7cb342;
            color: white;
        }

        .btn-success {
            background: #558b2f;
            color: white;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-secondary {
            background: #9e9e9e;
            color: white;
        }

        .progress-container {
            margin-top: 20px;
            display: none;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e1e5e9;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 10px;
        }

        .progress-fill {
            height: 100%;
            background: #7cb342;
            width: 0%;
            transition: width 0.3s ease;
        }

        .progress-text {
            font-size: 0.9rem;
            color: #666;
            text-align: center;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .empty-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        @media (max-width: 1024px) {
            .main-content {
                flex-direction: column;
            }
            .list-container {
                position: static;
                top: auto;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">
                <img src="src/img/logo.png" alt="Logo" style="height: 40px; width: auto;">
            </div>
            <div class="user-info">
                <?php 
                // Assumindo que o admin tem ID 1. Isso será alterado para uma verificação de 'role' mais tarde.
                if ($_SESSION['user_id'] == 1) {
                    echo '<a href="admin.php" class="logout-btn" style="background-color: #558b2f; margin-right: 10px;">Painel Admin</a>';
                }
                ?>
                <span class="user-name">Olá, <?php echo htmlspecialchars($userData['username']); ?></span>
                <a href="logout.php" class="logout-btn">Sair</a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="main-content">

            <div class="upload-container">
                <div class="upload-section">
                    <div class="file-drop-zone" id="uploadArea">
                        <div id="fileList" class="file-list" style="display: none;"></div>
                        <div class="file-drop-icon" id="fileDropIcon">+</div>
                        <h3 id="uploadTitle">Adicionar arquivos</h3>
                        <p id="uploadHint" class="upload-hint">Ou arraste e solte aqui</p>
                    </div>
                    <div class="upload-form-container">
                        <form id="uploadForm">
                            <div class="form-group" id="emailToGroup">
                                <label for="email_to">Enviar para</label>
                                <input type="email" id="email_to" name="email_to" placeholder="Endereço de email">
                            </div>
                            <div class="form-group">
                                <label for="title">Título</label>
                                <input type="text" id="title" name="title" placeholder="Nome do arquivo">
                            </div>
                            
                            <div class="radio-group">
                                <label class="radio-label">
                                    <input type="radio" name="transfer_mode" value="email"> Enviar por email
                                </label>
                                <label class="radio-label">
                                    <input type="radio" name="transfer_mode" value="link" checked> Obter link
                                </label>
                            </div>

                            <div class="options-row">
                                <div class="form-group">
                                    <label for="expires_in">Expira em</label>
                                    <select id="expires_in" name="expires_in">
                                        <option value="1">1 Dia</option>
                                        <option value="7" selected>7 Dias</option>
                                        <option value="30">30 Dias</option>
                                    </select>
                                </div>
                            </div>

                            <button type="submit" class="btn main-action-btn" id="uploadBtn" disabled>Transferir</button>
                        </form>
                    </div>
                    <input type="file" id="fileInput" class="file-input" multiple>
                </div>

                <div class="progress-container" id="progressContainer">
                    <div class="progress-bar">
                        <div class="progress-fill" id="progressFill"></div>
                    </div>
                    <div class="progress-text" id="progressText">Preparando upload...</div>
                </div>
            </div>

            <div class="list-container">
                <div class="uploads-list">
                    <h2 class="section-title">Meus Uploads</h2>
                    
                    <?php if (empty($uploads)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">📤</div>
                            <h3>Nenhum upload encontrado</h3>
                            <p>Faça seu primeiro upload para começar a compartilhar arquivos</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($uploads as $upload): ?>
                            <div class="upload-item">
                                <div class="upload-header">
                                    <div class="upload-title">
                                        <?php echo htmlspecialchars($upload['title'] ?: 'Upload sem título'); ?>
                                    </div>
                                    <div class="upload-date">
                                        <?php echo date('d/m/Y H:i', strtotime($upload['created_at'])); ?>
                                    </div>
                                </div>
                                
                                <div class="upload-details">
                                    <div class="detail-item">
                                        <div class="detail-label">Token</div>
                                        <div class="detail-value" title="<?php echo htmlspecialchars($upload['token']); ?>">
                                            <?php echo substr(htmlspecialchars($upload['token']), 0, 32) . '...'; ?>
                                        </div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Expira em</div>
                                        <div class="detail-value"><?php echo date('d/m/Y H:i', strtotime($upload['expires_at'])); ?></div>
                                    </div>
                                </div>
                                
                                <div class="upload-actions">
                                    <a href="download.php?token=<?php echo $upload['token']; ?>" 
                                       class="action-btn btn-primary" target="_blank">
                                        📥 Ver arquivos
                                    </a>
                                    <button class="action-btn btn-success" onclick="copyLink('<?php echo $upload['token']; ?>')">
                                        📋 Copiar link
                                    </button>
                                    <button class="action-btn btn-secondary" onclick="sendEmail('<?php echo $upload['token']; ?>')">
                                        📧 Enviar email
                                    </button>
                                    <button class="action-btn btn-danger" onclick="deleteUpload(<?php echo $upload['id']; ?>)">
                                        🗑️ Excluir
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

    <script>
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('fileInput');
        const fileDropIcon = document.getElementById('fileDropIcon');
        const fileListDiv = document.getElementById('fileList');
        const uploadForm = document.getElementById('uploadForm');
        const uploadBtn = document.getElementById('uploadBtn');
        
        const emailToGroup = document.getElementById('emailToGroup');
        const transferModeRadios = document.querySelectorAll('input[name="transfer_mode"]');

        let selectedFiles = [];

        // Lógica do clique e drag-and-drop
        fileDropIcon.addEventListener('click', () => fileInput.click());
        uploadArea.addEventListener('click', (e) => {
            if (e.target === uploadArea) fileInput.click();
        });
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            uploadArea.addEventListener(eventName, () => uploadArea.classList.add('dragover'), false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, () => uploadArea.classList.remove('dragover'), false);
        });

        uploadArea.addEventListener('drop', (e) => handleFiles(e.dataTransfer.files), false);

        fileInput.addEventListener('change', (e) => handleFiles(e.target.files));

        function handleFiles(files) {
            for (let file of files) {
                selectedFiles.push(file);
            }
            updateFileList();
        }

        function updateFileList() {
            if (selectedFiles.length > 0) {
                fileListDiv.style.display = 'block';
                fileDropIcon.textContent = '+';
                document.getElementById('uploadTitle').textContent = 'Adicionar mais arquivos';
                document.getElementById('uploadHint').style.display = 'none';
                uploadArea.classList.add('has-files');

                fileListDiv.innerHTML = '';
                selectedFiles.forEach((file, index) => {
                    const fileItem = document.createElement('div');
                    fileItem.className = 'file-item-sm';
                    fileItem.innerHTML = `
                        <span>${file.name} (${formatBytes(file.size)})</span>
                        <button type="button" onclick="removeFile(${index})">&times;</button>
                    `;
                    fileListDiv.appendChild(fileItem);
                });
            } else {
                fileListDiv.style.display = 'none';
                fileDropIcon.textContent = '+';
                document.getElementById('uploadTitle').textContent = 'Adicionar arquivos';
                document.getElementById('uploadHint').style.display = 'inline';
                uploadArea.classList.remove('has-files');
            }
            uploadBtn.disabled = selectedFiles.length === 0;
        }

        function removeFile(index) {
            selectedFiles.splice(index, 1);
            updateFileList();
        }

        // Lógica do formulário dinâmico
        transferModeRadios.forEach(radio => {
            radio.addEventListener('change', (e) => {
                const mode = e.target.value;
                if (mode === 'email') {
                    emailToGroup.style.display = 'block';
                    uploadBtn.textContent = 'Transferir';
                } else {
                    emailToGroup.style.display = 'none';
                    uploadBtn.textContent = 'Obter link';
                }
            });
        });
        
        // Default para email
        document.querySelector('input[value="email"]').dispatchEvent(new Event('change'));

        // Lógica de submit
        uploadForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData();
            selectedFiles.forEach(file => formData.append('files[]', file));
            
            formData.append('title', document.getElementById('title').value);
            formData.append('expires_in', document.getElementById('expires_in').value);
            formData.append('transfer_mode', document.querySelector('input[name="transfer_mode"]:checked').value);
            formData.append('email_to', document.getElementById('email_to').value);

            uploadBtn.disabled = true;
            uploadBtn.textContent = 'Enviando...';

            const progressContainer = document.getElementById('progressContainer');
            const progressFill = document.getElementById('progressFill');
            const progressText = document.getElementById('progressText');
            progressContainer.style.display = 'block';

            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'upload_handler.php', true);

            xhr.upload.onprogress = (e) => {
                if (e.lengthComputable) {
                    const percentComplete = (e.loaded / e.total) * 100;
                    progressFill.style.width = percentComplete + '%';
                    progressText.textContent = `Enviando... ${Math.round(percentComplete)}%`;
                }
            };

            xhr.onload = () => {
                uploadBtn.disabled = false;
                uploadBtn.textContent = 'Transferir';
                
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            progressText.textContent = 'Upload concluído! Atualizando a lista...';
                            setTimeout(() => {
                               window.location.reload(); 
                            }, 1500);
                        } else {
                            progressText.textContent = `Erro: ${response.message}`;
                            alert(`Erro no upload: ${response.message}`);
                        }
                    } catch (err) {
                        progressText.textContent = 'Erro ao processar a resposta do servidor.';
                        alert('Ocorreu um erro inesperado. Verifique o console para mais detalhes.');
                        console.error('Resposta inválida do servidor:', xhr.responseText);
                    }
                } else {
                    progressText.textContent = `Erro no servidor: ${xhr.statusText}`;
                    alert(`Erro no upload: ${xhr.status} ${xhr.statusText}`);
                }
            };

            xhr.onerror = () => {
                uploadBtn.disabled = false;
                uploadBtn.textContent = 'Transferir';
                progressText.textContent = 'Erro de rede. Não foi possível conectar ao servidor.';
                alert('Erro de rede ao tentar fazer o upload.');
            };

            xhr.send(formData);
        });

        function formatBytes(bytes, decimals = 2) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const dm = decimals < 0 ? 0 : decimals;
            const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
        }

        async function copyLink(token) {
            const link = `${window.location.origin}/download.php?token=${token}`;
            try {
                await navigator.clipboard.writeText(link);
                alert('Link copiado para a área de transferência!');
            } catch (error) {
                console.error('Erro ao copiar o link:', error);
                alert('Erro ao copiar o link para a área de transferência.');
            }
        }

        async function sendEmail(token) {
            try {
                const response = await fetch('send_email.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ token: token })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('Email enviado com sucesso!');
                } else {
                    alert('Erro ao enviar email: ' + data.message);
                }
            } catch (error) {
                console.error('Erro ao enviar email:', error);
                alert('Erro ao enviar email: ' + error.message);
            }
        }

        async function deleteUpload(id) {
            if (confirm('Tem certeza que deseja excluir este upload?')) {
                try {
                    const response = await fetch('delete_upload.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ id: id })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        alert('Upload excluído com sucesso!');
                        location.reload();
                    } else {
                        alert('Erro ao excluir: ' + data.message);
                    }
                } catch (error) {
                    console.error('Erro ao excluir o upload:', error);
                    alert('Erro ao excluir o upload: ' + error.message);
                }
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('input[value="link"]').dispatchEvent(new Event('change'));
        });
    </script>
</body>
</html> 