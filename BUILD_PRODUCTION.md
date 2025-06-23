# 🚀 Build de Produção - Pix Transfer

Este documento explica como fazer o build e deploy da aplicação Pix Transfer em ambiente de produção.

## 📋 Pré-requisitos

- Docker Desktop instalado e rodando
- Docker Compose instalado
- Acesso ao Docker Hub (para push da imagem)

## 🔨 Build da Imagem de Produção

### Opção 1: Script Automatizado (Recomendado)

#### Linux/WSL:
```bash
chmod +x build_prod.sh
./build_prod.sh
```

#### Windows PowerShell:
```powershell
.\build_prod.ps1
```

### Opção 2: Comandos Manuais

1. **Build da imagem Docker:**
```bash
docker build -f Dockerfile.prod -t vicctim/pix-transfer-php:latest . --no-cache
```

2. **Build dos serviços:**
```bash
docker-compose -f docker-compose.prod.yml build
```

## 🏗️ Configuração do Ambiente de Produção

### 1. Criar Volumes Externos
```bash
docker volume create pix-transfer-uploads
docker volume create pix-transfer-logs
docker volume create pix-transfer-db-data
```

### 2. Criar Rede Externa
```bash
docker network create minha-rede
```

### 3. Configurar Variáveis de Ambiente
Copie o arquivo `src/env.php.example` para `src/env.php` e ajuste as configurações:

```php
<?php
// Database Configuration
define('DB_HOST', 'pix-transfer-db');
define('DB_NAME', 'upload_system');
define('DB_USER', 'upload_user');
define('DB_PASS', 'upload_password');

// SMTP Configuration
define('SMTP_HOST', 'pix-transfer-mailhog');
define('SMTP_PORT', 1025);
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('SMTP_FROM', 'noreply@pixfilmes.com');

// Admin Configuration
define('ADMIN_EMAIL', 'admin@transfer.com');
define('ADMIN_PASSWORD', 'password');
?>
```

## 🚀 Deploy em Produção

### 1. Executar o Ambiente
```bash
docker-compose -f docker-compose.prod.yml up -d
```

### 2. Verificar Status dos Containers
```bash
docker-compose -f docker-compose.prod.yml ps
```

### 3. Verificar Logs
```bash
docker-compose -f docker-compose.prod.yml logs -f
```

## 📤 Push para Docker Hub

Após o build bem-sucedido, faça o push da imagem:

```bash
docker push vicctim/pix-transfer-php:latest
```

## 🔧 Configurações de Produção

### Recursos Alocados
- **Aplicação PHP:** 2 CPUs, 2GB RAM
- **MySQL:** 1 CPU, 1GB RAM
- **MailHog:** 0.5 CPU, 512MB RAM

### Domínios Configurados
- **Aplicação:** `transfer.victorsamuel.com.br`
- **Email:** `mail.transfer.victorsamuel.com.br`

### Volumes Persistentes
- `pix-transfer-uploads`: Arquivos enviados pelos usuários
- `pix-transfer-logs`: Logs da aplicação
- `pix-transfer-db-data`: Dados do banco MySQL

## 🐛 Troubleshooting

### Problemas Comuns

1. **Docker não encontrado:**
   - Verifique se o Docker Desktop está rodando
   - Reinicie o Docker Desktop se necessário

2. **Erro de permissão:**
   - Execute os comandos como administrador (Windows)
   - Use `sudo` no Linux/WSL se necessário

3. **Porta já em uso:**
   - Verifique se não há outros serviços usando as portas 80, 3306, 8025
   - Pare containers conflitantes: `docker-compose down`

4. **Erro de rede:**
   - Verifique se a rede `minha-rede` foi criada
   - Recrie a rede: `docker network rm minha-rede && docker network create minha-rede`

### Logs de Debug
```bash
# Logs da aplicação
docker-compose -f docker-compose.prod.yml logs pix-transfer

# Logs do banco de dados
docker-compose -f docker-compose.prod.yml logs pix-transfer-db

# Logs do MailHog
docker-compose -f docker-compose.prod.yml logs pix-transfer-mailhog
```

## 📞 Suporte

Para problemas específicos, verifique:
1. Logs dos containers
2. Status do Docker Desktop
3. Configurações de rede e firewall
4. Permissões de arquivo e diretório 