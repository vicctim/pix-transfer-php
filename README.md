# Pix Transfer - Sistema de Upload de Arquivos

Sistema de compartilhamento de arquivos desenvolvido em PHP com suporte a uploads de até 10GB, interface moderna inspirada no WeTransfer e painel administrativo.

## 🚀 Características

- **Upload de arquivos grandes**: Suporte a arquivos de até 10GB
- **Interface moderna**: Design responsivo inspirado no WeTransfer
- **Sistema de login**: Autenticação de usuários
- **Painel administrativo**: Gerenciamento de usuários e uploads
- **Notificações por email**: Envio automático de links de download
- **Expiração automática**: Links expiram automaticamente
- **Docker ready**: Containerização completa para produção

## 🐳 Deploy com Docker

### Pré-requisitos

- Docker e Docker Compose instalados
- Traefik configurado como reverse proxy
- Rede Docker `minha-rede` criada
- Volumes Docker criados

### 1. Criar Volumes

```bash
docker volume create pix-transfer-uploads
docker volume create pix-transfer-logs
docker volume create pix-transfer-db-data
```

### 2. Deploy com Traefik

```bash
docker-compose -f docker-compose.prod.yml up -d
```

### 3. Configuração de Domínio

O sistema estará disponível em:
- **Aplicação**: https://transfer.victorsamuel.com.br
- **MailHog**: https://mail.transfer.victorsamuel.com.br

## 🔧 Configuração

### Variáveis de Ambiente

| Variável | Descrição | Padrão |
|----------|-----------|--------|
| `DB_HOST` | Host do banco de dados | `pix-transfer-db` |
| `DB_NAME` | Nome do banco | `upload_system` |
| `DB_USER` | Usuário do banco | `upload_user` |
| `DB_PASS` | Senha do banco | `upload_password` |
| `SMTP_HOST` | Host SMTP | `pix-transfer-mailhog` |
| `SMTP_PORT` | Porta SMTP | `1025` |
| `SMTP_FROM` | Email remetente | `noreply@pixfilmes.com` |

### Credenciais Padrão

**Administrador:**
- Email: `admin@transfer.com`
- Senha: `password`

## 📁 Estrutura do Projeto

```
upload/
├── src/                    # Código fonte da aplicação
│   ├── config/            # Configurações
│   ├── models/            # Modelos de dados
│   ├── uploads/           # Arquivos enviados
│   └── img/               # Imagens (logo, favicon)
├── database/              # Scripts de banco de dados
├── Dockerfile.prod        # Dockerfile para produção
├── docker-compose.prod.yml # Compose para produção
└── README.md              # Este arquivo
```

## 🔒 Segurança

- **Altere as credenciais padrão** após o primeiro deploy
- **Configure HTTPS** através do Traefik
- **Monitore os logs** regularmente
- **Faça backup** dos volumes de dados

## 📧 Email

O sistema usa MailHog para desenvolvimento/teste. Para produção, configure um servidor SMTP real:

```yaml
environment:
  - SMTP_HOST=seu-smtp.com
  - SMTP_PORT=587
  - SMTP_USER=seu-email@dominio.com
  - SMTP_PASS=sua-senha
```

## 🛠️ Manutenção

### Logs
```bash
# Logs da aplicação
docker logs pix-transfer

# Logs do banco
docker logs pix-transfer-db

# Logs do MailHog
docker logs pix-transfer-mailhog
```

### Backup
```bash
# Backup do banco
docker exec pix-transfer-db mysqldump -u root -proot_password upload_system > backup.sql

# Backup dos uploads
docker run --rm -v pix-transfer-uploads:/data -v $(pwd):/backup alpine tar czf /backup/uploads-backup.tar.gz -C /data .
```

### Atualização
```bash
# Parar serviços
docker-compose -f docker-compose.prod.yml down

# Atualizar imagem
docker pull vicctim/pix-transfer-php:latest

# Reiniciar serviços
docker-compose -f docker-compose.prod.yml up -d
```

## 🐛 Troubleshooting

### Problema de Login
1. Verifique se o banco está rodando: `docker ps | grep pix-transfer-db`
2. Verifique os logs: `docker logs pix-transfer-db`
3. Teste a conexão: `docker exec pix-transfer-db mysql -u root -proot_password -e "SHOW DATABASES;"`

### Problema de Upload
1. Verifique permissões do volume: `docker exec pix-transfer ls -la /var/www/html/uploads`
2. Verifique espaço em disco: `df -h`
3. Verifique logs da aplicação: `docker logs pix-transfer`

### Problema de Email
1. Acesse o MailHog: https://mail.transfer.victorsamuel.com.br
2. Verifique logs: `docker logs pix-transfer-mailhog`
3. Teste configuração SMTP

## 📄 Licença

Este projeto é desenvolvido para uso interno da Pix Filmes.

## 🤝 Suporte

Para suporte técnico, entre em contato com a equipe de desenvolvimento. 