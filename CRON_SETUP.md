# Configuração do Cron Job para Notificações de Expiração

## Configuração no Servidor

Para configurar as notificações automáticas de expiração, adicione o seguinte cron job:

```bash
# Editar crontab
crontab -e

# Adicionar linha para executar verificação diária às 9:00
0 9 * * * cd /caminho/para/seu/projeto/src && php check_expiration.php >> /var/log/expiration_check.log 2>&1
```

## Configuração no Docker

Se estiver usando Docker, você pode configurar da seguinte forma:

### Opção 1: Dentro do container
```bash
# Entrar no container
docker-compose exec web bash

# Instalar cron
apt-get update && apt-get install -y cron

# Configurar cron job
echo "0 9 * * * cd /var/www/html/src && php check_expiration.php >> /var/log/expiration_check.log 2>&1" | crontab -

# Iniciar serviço cron
service cron start
```

### Opção 2: Do host (recomendado)
```bash
# Criar script no host
#!/bin/bash
docker-compose exec -T web php /var/www/html/src/check_expiration.php

# Dar permissão de execução
chmod +x check_expiration.sh

# Adicionar ao cron do host
0 9 * * * /caminho/para/check_expiration.sh >> /var/log/expiration_check.log 2>&1
```

## Funcionalidade

O script `check_expiration.php`:

1. **Verifica uploads que expiram em 24 horas**
2. **Envia notificação para o usuário proprietário do upload**
3. **Marca o upload como notificado para evitar spam**
4. **Registra logs das notificações enviadas**

## Email de Notificação

O email enviado contém:
- Título do upload
- Data e hora de expiração
- Link direto para download
- Instruções para estender a expiração

## Personalização

Você pode personalizar os templates de email no painel administrativo em:
- **Admin > Templates de Email > Aviso de Expiração**

## Logs

Os logs das execuções ficam em:
- `/var/log/expiration_check.log` (configurável)
- Logs de email no banco de dados (tabela `email_logs`)