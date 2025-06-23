<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'models/UploadSession.php';
require_once 'models/User.php';
require_once 'models/ShortUrl.php';

$uploadSession = new UploadSession();
$user = new User();
$userData = $user->getById($_SESSION['user_id']);
$shortUrl = new ShortUrl();

$uploads = $uploadSession->getByUserId($_SESSION['user_id']);

// Criar URLs curtas para uploads que ainda não têm
foreach ($uploads as &$upload) {
    $short_code = $shortUrl->create($upload['token'], $upload['expires_at']);
    $upload['short_code'] = $short_code;
}
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
            max-width: 1600px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: #7cb342;
            display: flex;
            align-items: center;
        }

        .logo img {
            height: 70px;
            width: auto;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-name {
            font-weight: 500;
        }

        .logout-btn {
            background: linear-gradient(135deg, #FFCDD2, #EF9A9A);
            color: #C62828;
            border: 1px solid #FFCDD2;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            text-decoration: none;
            transition: all 0.3s ease;
            margin-left: 8px;
        }

        .logout-btn:hover {
            background: linear-gradient(135deg, #EF9A9A, #E57373);
            transform: translateY(-1px);
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
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #fff;
            border-radius: 6px;
            margin-bottom: 4px;
            transition: all 0.2s ease;
            border: 1px solid #e9ecef;
        }

        .file-item-sm:hover {
            background: #f8f9fa;
            border-color: #dee2e6;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .file-item-sm:last-child {
            border-bottom: none;
        }

        .file-item-sm .remove-btn {
            background: linear-gradient(135deg, #FFCDD2, #EF9A9A);
            color: #C62828;
            border: 1px solid #FFCDD2;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            padding: 0;
            line-height: 1;
        }

        .file-item-sm .remove-btn:hover {
            background: linear-gradient(135deg, #EF9A9A, #E57373);
            transform: scale(1.1);
            box-shadow: 0 2px 8px rgba(239, 154, 154, 0.4);
        }

        .file-item-sm .file-info {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
        }

        .file-item-sm .file-icon {
            font-size: 18px;
            color: #4CAF50;
            margin-right: 4px;
        }

        .file-item-sm .file-name {
            font-weight: 500;
            color: #333;
        }

        .file-item-sm .file-size {
            color: #666;
            font-size: 0.85rem;
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
            gap: 6px;
            flex-wrap: wrap;
            align-items: center;
            justify-content: flex-start;
            max-width: 100%;
            overflow: hidden;
        }

        .action-btn {
            padding: 6px 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.72rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 3px;
            transition: all 0.3s ease;
            white-space: nowrap;
            min-width: auto;
            flex-shrink: 1;
            max-width: calc(25% - 5px);
        }

        .btn-primary {
            background: linear-gradient(135deg, #7cb342, #689f38);
            color: white;
            border: 1px solid #7cb342;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #689f38, #558b2f);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(124, 179, 66, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            border: 1px solid #4CAF50;
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #45a049, #3d8b40);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, #FFCDD2, #EF9A9A);
            color: #C62828;
            border: 1px solid #FFCDD2;
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #EF9A9A, #E57373);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(239, 154, 154, 0.3);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #78909C, #607D8B);
            color: white;
            border: 1px solid #78909C;
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, #607D8B, #546E7A);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(120, 144, 156, 0.3);
        }

        .btn-warning {
            background: linear-gradient(135deg, #FFB74D, #FFA726);
            color: white;
            border: 1px solid #FFB74D;
        }

        .btn-warning:hover {
            background: linear-gradient(135deg, #FFA726, #FF9800);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(255, 183, 77, 0.3);
        }

        /* Modal styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal-content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            transform: scale(0.8);
            transition: all 0.3s ease;
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal-overlay.show .modal-content {
            transform: scale(1);
        }

        .modal-header {
            display: flex;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .modal-icon {
            font-size: 32px;
            margin-right: 15px;
            color: #ffc107;
        }

        .modal-title {
            font-size: 24px;
            font-weight: 600;
            color: #333;
            margin: 0;
        }

        .form-row {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: #555;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }

        .current-expiration {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 12px 15px;
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
        }

        .modal-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 25px;
        }

        .modal-btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 120px;
        }

        .modal-btn-primary {
            background: #4CAF50;
            color: white;
        }

        .modal-btn-primary:hover {
            background: #45a049;
            transform: translateY(-2px);
        }

        .modal-btn-secondary {
            background: #6c757d;
            color: white;
        }

        .modal-btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .form-hint {
            font-size: 12px;
            color: #888;
            margin-top: 5px;
        }

        /* Toast notification styles */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 2000;
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-width: 400px;
        }

        .toast {
            padding: 16px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            min-width: 300px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transform: translateX(400px) scale(0.95);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            display: flex;
            align-items: center;
            gap: 10px;
            word-wrap: break-word;
        }

        .toast.show {
            transform: translateX(0) scale(1);
            opacity: 1;
        }

        .toast.success {
            background: linear-gradient(135deg, #4CAF50, #45a049);
        }

        .toast.error {
            background: linear-gradient(135deg, #EF5350, #E53935);
        }

        .toast.info {
            background: linear-gradient(135deg, #2196F3, #1976D2);
        }

        .toast-icon {
            font-size: 18px;
        }

        .toast-close {
            margin-left: auto;
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            font-size: 18px;
            opacity: 0.8;
            transition: opacity 0.3s ease;
        }

        .toast-close:hover {
            opacity: 1;
        }

        /* Confirm modal styles */
        .confirm-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1500;
        }

        .confirm-modal.show {
            display: flex;
        }

        .confirm-content {
            background: white;
            border-radius: 12px;
            padding: 30px;
            max-width: 450px;
            width: 90%;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            transform: scale(0.9);
            transition: all 0.3s ease;
        }

        .confirm-modal.show .confirm-content {
            transform: scale(1);
        }

        .confirm-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .confirm-icon {
            font-size: 32px;
            margin-right: 15px;
            color: #ff9800;
        }

        .confirm-title {
            font-size: 22px;
            font-weight: 600;
            color: #333;
            margin: 0;
        }

        .confirm-message {
            color: #666;
            font-size: 16px;
            line-height: 1.5;
            margin-bottom: 25px;
        }

        .confirm-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
        }

        .confirm-btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 100px;
        }

        .confirm-btn-danger {
            background: #EF5350;
            color: white;
        }

        .confirm-btn-danger:hover {
            background: #E53935;
            transform: translateY(-2px);
        }

        .confirm-btn-cancel {
            background: #6c757d;
            color: white;
        }

        .confirm-btn-cancel:hover {
            background: #5a6268;
            transform: translateY(-2px);
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
            
            .toast-container {
                left: 20px;
                right: 20px;
                top: 20px;
            }
            
            .toast {
                min-width: auto;
                width: 100%;
            }
            
            .confirm-content {
                margin: 20px;
                width: calc(100% - 40px);
                max-width: none;
            }
        }

        @media (min-width: 769px) {
            .upload-actions {
                flex-wrap: nowrap;
            }
            
            .action-btn {
                max-width: none;
                flex-shrink: 0;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }

            .upload-item {
                padding: 15px;
                gap: 12px;
            }

            .upload-info h3 {
                font-size: 16px;
            }

            .detail-item {
                font-size: 12px;
            }

            .upload-actions {
                gap: 4px;
                justify-content: flex-start;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                padding-bottom: 5px;
                flex-wrap: nowrap;
                max-width: 100%;
            }

            .action-btn {
                padding: 6px 8px;
                font-size: 0.7rem;
                gap: 3px;
                flex-shrink: 0;
                min-width: fit-content;
                max-width: none;
            }
        }

        @media (max-width: 480px) {
            .upload-item {
                padding: 12px;
                flex-direction: column;
                align-items: stretch;
            }

            .upload-info {
                margin-bottom: 15px;
            }

            .upload-actions {
                gap: 8px;
                flex-wrap: wrap;
                justify-content: center;
                margin-top: 10px;
            }

            .action-btn {
                padding: 8px 12px;
                font-size: 0.75rem;
                border-radius: 6px;
                flex: 1;
                min-width: calc(50% - 4px);
                max-width: 48%;
                justify-content: center;
                text-align: center;
            }

            .detail-grid {
                grid-template-columns: 1fr;
                gap: 8px;
            }
        }

        @media (max-width: 360px) {
            .action-btn {
                min-width: 100%;
                max-width: 100%;
                margin-bottom: 4px;
            }

            .upload-actions {
                flex-direction: column;
                gap: 6px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">
                <img src="src/img/logo.png" alt="Logo">
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
                                        <div class="detail-label">Expira em</div>
                                        <div class="detail-value"><?php echo date('d/m/Y H:i', strtotime($upload['expires_at'])); ?></div>
                                    </div>
                                </div>
                                
                                <div class="upload-actions">
                                    <a href="<?php echo $upload['short_code'] ? 's/' . $upload['short_code'] : 'download/' . $upload['token']; ?>" 
                                       class="action-btn btn-primary" target="_blank">
                                        📥 Ver arquivos
                                    </a>
                                    <button class="action-btn btn-success" onclick="copyShortLink('<?php echo $upload['short_code'] ?: $upload['token']; ?>', <?php echo $upload['short_code'] ? 'true' : 'false'; ?>)">
                                        📋 Copiar link
                                    </button>
                                    <button class="action-btn btn-warning" onclick="changeExpiration(<?php echo $upload['id']; ?>, '<?php echo $upload['expires_at']; ?>')">
                                        ⏰ Alterar expiração
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
                        <div class="file-info">
                            <span class="file-icon">📄</span>
                            <span class="file-name">${file.name}</span>
                            <span class="file-size">(${formatBytes(file.size)})</span>
                        </div>
                        <button type="button" class="remove-btn" onclick="removeFile(${index})" title="Remover arquivo">✕</button>
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
                            showToast(`Erro no upload: ${response.message}`, 'error');
                        }
                    } catch (err) {
                        progressText.textContent = 'Erro ao processar a resposta do servidor.';
                        showToast('Ocorreu um erro inesperado. Verifique o console para mais detalhes.', 'error');
                        console.error('Resposta inválida do servidor:', xhr.responseText);
                    }
                } else {
                    progressText.textContent = `Erro no servidor: ${xhr.statusText}`;
                    showToast(`Erro no upload: ${xhr.status} ${xhr.statusText}`, 'error');
                }
            };

            xhr.onerror = () => {
                uploadBtn.disabled = false;
                uploadBtn.textContent = 'Transferir';
                progressText.textContent = 'Erro de rede. Não foi possível conectar ao servidor.';
                showToast('Erro de rede ao tentar fazer o upload.', 'error');
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

        async function copyShortLink(code, isShort) {
            const link = isShort ? 
                `${window.location.origin}/s/${code}` : 
                `${window.location.origin}/download/${code}`;
            try {
                await navigator.clipboard.writeText(link);
                showToast(`Link ${isShort ? 'curto' : ''} copiado para a área de transferência!`, 'success');
            } catch (error) {
                console.error('Erro ao copiar o link:', error);
                showToast('Erro ao copiar o link para a área de transferência.', 'error');
            }
        }
        
        // Manter função antiga para compatibilidade
        async function copyLink(token) {
            return copyShortLink(token, false);
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
                    showToast('Email enviado com sucesso!', 'success');
                } else {
                    showToast('Erro ao enviar email: ' + data.message, 'error');
                }
            } catch (error) {
                console.error('Erro ao enviar email:', error);
                showToast('Erro ao enviar email: ' + error.message, 'error');
            }
        }

        async function deleteUpload(id) {
            showConfirmModal(
                'Confirmar Exclusão',
                'Tem certeza que deseja excluir este upload? Esta ação não pode ser desfeita.',
                'Excluir',
                'Cancelar',
                async () => {
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
                            showToast('Upload excluído com sucesso!', 'success');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showToast('Erro ao excluir: ' + data.message, 'error');
                        }
                    } catch (error) {
                        console.error('Erro ao excluir o upload:', error);
                        showToast('Erro ao excluir o upload: ' + error.message, 'error');
                    }
                }
            );
        }

        let currentUploadId = null;

        function changeExpiration(uploadId, currentExpiration) {
            currentUploadId = uploadId;
            
            // Converter data atual para formato de input datetime-local
            const currentDate = new Date(currentExpiration);
            const formattedDate = currentDate.toISOString().slice(0, 16);
            
            // Formatar data atual para exibição
            const displayDate = currentDate.toLocaleString('pt-BR', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            // Preencher modal
            document.getElementById('currentExpiration').textContent = displayDate;
            document.getElementById('newExpiration').value = formattedDate;
            
            // Mostrar modal
            const modal = document.getElementById('expirationModal');
            modal.classList.add('show');
            
            // Focar no input
            setTimeout(() => {
                document.getElementById('newExpiration').focus();
            }, 300);
        }

        function closeExpirationModal() {
            const modal = document.getElementById('expirationModal');
            modal.classList.remove('show');
            currentUploadId = null;
        }

        async function confirmExpirationChange() {
            const newExpiration = document.getElementById('newExpiration').value;
            
            if (!newExpiration) {
                showToast('Por favor, selecione uma data de expiração.', 'warning');
                return;
            }
            
            // Verificar se a data é no futuro
            const newDate = new Date(newExpiration);
            if (newDate <= new Date()) {
                showToast('A data de expiração deve ser no futuro.', 'warning');
                return;
            }
            
            try {
                const response = await fetch('update_expiration.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        upload_id: currentUploadId,
                        expires_at: newExpiration.replace('T', ' ')
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showToast('Data de expiração alterada com sucesso!', 'success');
                    closeExpirationModal();
                    setTimeout(() => location.reload(), 1500); // Recarregar página para mostrar nova data
                } else {
                    showToast('Erro ao alterar data: ' + data.message, 'error');
                }
            } catch (error) {
                console.error('Erro ao alterar data de expiração:', error);
                showToast('Erro ao alterar data de expiração: ' + error.message, 'error');
            }
        }

        // Fechar modal ao clicar fora
        document.addEventListener('click', function(event) {
            const modal = document.getElementById('expirationModal');
            if (event.target === modal) {
                closeExpirationModal();
            }
        });

        // Modern Toast Notification System
        function showToast(message, type = 'info', duration = 4000) {
            // Prevent duplicate toasts with same message
            const existingToasts = document.querySelectorAll('.toast .toast-message');
            for (let existingToast of existingToasts) {
                if (existingToast.textContent === message) {
                    return; // Don't show duplicate
                }
            }
            
            const toastContainer = getToastContainer();
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            
            const icon = getToastIcon(type);
            toast.innerHTML = `
                <span class="toast-icon">${icon}</span>
                <span class="toast-message">${message}</span>
                <button class="toast-close" onclick="closeToast(this)">&times;</button>
            `;
            
            toastContainer.appendChild(toast);
            
            // Trigger animation
            setTimeout(() => toast.classList.add('show'), 100);
            
            // Auto-remove after duration
            setTimeout(() => {
                closeToast(toast.querySelector('.toast-close'));
            }, duration);
        }

        function getToastContainer() {
            let container = document.querySelector('.toast-container');
            if (!container) {
                container = document.createElement('div');
                container.className = 'toast-container';
                document.body.appendChild(container);
            }
            return container;
        }

        function getToastIcon(type) {
            const icons = {
                'success': '✓',
                'error': '✗',
                'info': 'ℹ',
                'warning': '⚠'
            };
            return icons[type] || icons.info;
        }

        function closeToast(closeBtn) {
            const toast = closeBtn.closest('.toast');
            toast.classList.remove('show');
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        }

        // Modern Confirmation Modal System
        function showConfirmModal(title, message, confirmText = 'Confirmar', cancelText = 'Cancelar', onConfirm = null) {
            // Remove existing modal if any
            const existingModal = document.querySelector('.confirm-modal');
            if (existingModal) {
                existingModal.remove();
            }

            const modal = document.createElement('div');
            modal.className = 'confirm-modal';
            modal.innerHTML = `
                <div class="confirm-content">
                    <div class="confirm-header">
                        <div class="confirm-icon">⚠️</div>
                        <h3 class="confirm-title">${title}</h3>
                    </div>
                    <div class="confirm-message">${message}</div>
                    <div class="confirm-actions">
                        <button class="confirm-btn confirm-btn-cancel" onclick="closeConfirmModal()">${cancelText}</button>
                        <button class="confirm-btn confirm-btn-danger" onclick="confirmAction()">${confirmText}</button>
                    </div>
                </div>
            `;

            document.body.appendChild(modal);
            
            // Store the callback function
            modal._onConfirm = onConfirm;
            
            // Show modal with animation
            setTimeout(() => {
                modal.classList.add('show');
                // Focus on the confirm button for better accessibility
                const confirmBtn = modal.querySelector('.confirm-btn-danger');
                if (confirmBtn) {
                    confirmBtn.focus();
                }
            }, 50);
            
            // Close on background click
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    closeConfirmModal();
                }
            });
            
            // Keyboard support
            const handleKeyDown = (e) => {
                if (e.key === 'Escape') {
                    closeConfirmModal();
                } else if (e.key === 'Enter') {
                    confirmAction();
                }
            };
            
            document.addEventListener('keydown', handleKeyDown);
            modal._keyHandler = handleKeyDown;
        }

        function closeConfirmModal() {
            const modal = document.querySelector('.confirm-modal');
            if (modal) {
                // Remove keyboard event listener
                if (modal._keyHandler) {
                    document.removeEventListener('keydown', modal._keyHandler);
                }
                
                modal.classList.remove('show');
                setTimeout(() => {
                    if (modal.parentNode) {
                        modal.parentNode.removeChild(modal);
                    }
                }, 300);
            }
        }

        function confirmAction() {
            const modal = document.querySelector('.confirm-modal');
            if (modal && modal._onConfirm) {
                modal._onConfirm();
            }
            closeConfirmModal();
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('input[value="link"]').dispatchEvent(new Event('change'));
        });
    </script>

    <!-- Modal para alterar data de expiração -->
    <div id="expirationModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-icon">⏰</div>
                <h2 class="modal-title">Alterar Data de Expiração</h2>
            </div>
            
            <div class="form-row">
                <label class="form-label">Data de expiração atual:</label>
                <div id="currentExpiration" class="current-expiration"></div>
            </div>
            
            <div class="form-row">
                <label for="newExpiration" class="form-label">Nova data de expiração:</label>
                <input type="datetime-local" id="newExpiration" class="form-input" required>
                <div class="form-hint">Selecione uma data e hora no futuro</div>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="modal-btn modal-btn-secondary" onclick="closeExpirationModal()">
                    Cancelar
                </button>
                <button type="button" class="modal-btn modal-btn-primary" onclick="confirmExpirationChange()">
                    Alterar Data
                </button>
            </div>
        </div>
    </div>

</body>
</html> 