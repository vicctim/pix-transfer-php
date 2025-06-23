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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f7fa;
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
            color: #667eea;
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .upload-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
        }

        .upload-area {
            border: 2px dashed #ddd;
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            margin-bottom: 20px;
        }

        .upload-area:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }

        .upload-area.dragover {
            border-color: #667eea;
            background: #f0f4ff;
        }

        .upload-icon {
            font-size: 3rem;
            color: #667eea;
            margin-bottom: 15px;
        }

        .upload-text {
            font-size: 1.1rem;
            color: #666;
            margin-bottom: 10px;
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

        .form-group {
            display: flex;
            flex-direction: column;
        }

        label {
            font-weight: 500;
            margin-bottom: 8px;
            color: #333;
        }

        input, textarea {
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        input:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            transform: translateY(-1px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
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
            background: #667eea;
            color: white;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .upload-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .upload-details {
                grid-template-columns: 1fr;
            }

            .upload-actions {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">📤 Upload System</div>
            <div class="user-info">
                <span class="user-name">Olá, <?php echo htmlspecialchars($userData['username']); ?></span>
                <a href="logout.php" class="logout-btn">Sair</a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="upload-section">
            <h2 class="section-title">Novo Upload</h2>
            
            <div class="upload-area" id="uploadArea">
                <div class="upload-icon">📁</div>
                <div class="upload-text">Arraste arquivos aqui ou clique para selecionar</div>
                <div class="upload-hint">Máximo 10GB por arquivo</div>
                <input type="file" id="fileInput" class="file-input" multiple>
            </div>

            <form class="upload-form" id="uploadForm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="title">Título (opcional)</label>
                        <input type="text" id="title" name="title" placeholder="Digite um título para o upload">
                    </div>
                    <div class="form-group">
                        <label for="description">Descrição (opcional)</label>
                        <input type="text" id="description" name="description" placeholder="Breve descrição dos arquivos">
                    </div>
                </div>
                
                <button type="submit" class="btn" id="uploadBtn" disabled>Iniciar Upload</button>
            </form>

            <div class="progress-container" id="progressContainer">
                <div class="progress-bar">
                    <div class="progress-fill" id="progressFill"></div>
                </div>
                <div class="progress-text" id="progressText">Preparando upload...</div>
            </div>
        </div>

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
                                <div class="detail-value"><?php echo htmlspecialchars($upload['session_token']); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Expira em</div>
                                <div class="detail-value"><?php echo date('d/m/Y H:i', strtotime($upload['expires_at'])); ?></div>
                            </div>
                            <?php if ($upload['description']): ?>
                                <div class="detail-item">
                                    <div class="detail-label">Descrição</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($upload['description']); ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="upload-actions">
                            <a href="download.php?token=<?php echo $upload['session_token']; ?>" 
                               class="action-btn btn-primary" target="_blank">
                                📥 Ver arquivos
                            </a>
                            <button class="action-btn btn-success" onclick="copyLink('<?php echo $upload['session_token']; ?>')">
                                📋 Copiar link
                            </button>
                            <button class="action-btn btn-secondary" onclick="sendEmail('<?php echo $upload['session_token']; ?>')">
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

    <script>
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('fileInput');
        const uploadForm = document.getElementById('uploadForm');
        const uploadBtn = document.getElementById('uploadBtn');
        const progressContainer = document.getElementById('progressContainer');
        const progressFill = document.getElementById('progressFill');
        const progressText = document.getElementById('progressText');

        let selectedFiles = [];

        // Drag and drop
        uploadArea.addEventListener('click', () => fileInput.click());
        
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            handleFiles(e.dataTransfer.files);
        });

        fileInput.addEventListener('change', (e) => {
            handleFiles(e.target.files);
        });

        function handleFiles(files) {
            selectedFiles = Array.from(files);
            uploadBtn.disabled = selectedFiles.length === 0;
            
            if (selectedFiles.length > 0) {
                const totalSize = selectedFiles.reduce((sum, file) => sum + file.size, 0);
                const sizeText = formatBytes(totalSize);
                uploadArea.querySelector('.upload-text').textContent = 
                    `${selectedFiles.length} arquivo(s) selecionado(s) - ${sizeText}`;
            }
        }

        uploadForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            if (selectedFiles.length === 0) return;
            
            const formData = new FormData();
            selectedFiles.forEach(file => formData.append('files[]', file));
            formData.append('title', document.getElementById('title').value);
            formData.append('description', document.getElementById('description').value);
            
            uploadBtn.disabled = true;
            progressContainer.style.display = 'block';
            progressText.textContent = 'Iniciando upload...';
            
            try {
                console.log('Iniciando upload...');
                const response = await fetch('upload_handler.php', {
                    method: 'POST',
                    body: formData
                });
                
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);
                
                // Verificar se a resposta é JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const textResponse = await response.text();
                    console.error('Resposta não é JSON:', textResponse);
                    throw new Error('Resposta do servidor não é JSON válido');
                }
                
                const result = await response.json();
                console.log('Resultado:', result);
                
                if (result.success) {
                    alert('Upload concluído com sucesso!');
                    location.reload();
                } else {
                    alert('Erro no upload: ' + result.message);
                }
            } catch (error) {
                console.error('Erro no upload:', error);
                alert('Erro no upload: ' + error.message);
            } finally {
                uploadBtn.disabled = false;
                progressContainer.style.display = 'none';
            }
        });

        function formatBytes(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        function copyLink(token) {
            const link = `${window.location.origin}/download.php?token=${token}`;
            navigator.clipboard.writeText(link).then(() => {
                alert('Link copiado para a área de transferência!');
            });
        }

        function sendEmail(token) {
            fetch('send_email.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ token: token })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Email enviado com sucesso!');
                } else {
                    alert('Erro ao enviar email: ' + data.message);
                }
            });
        }

        function deleteUpload(id) {
            if (confirm('Tem certeza que deseja excluir este upload?')) {
                fetch('delete_upload.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: id })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Erro ao excluir: ' + data.message);
                    }
                });
            }
        }
    </script>
</body>
</html> 