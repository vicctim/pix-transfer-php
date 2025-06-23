#!/bin/bash

echo "🚀 Iniciando deploy de produção..."

# Verificar se a imagem foi criada
if ! docker images | grep -q "vicctim/pix-transfer-php"; then
    echo "❌ Imagem vicctim/pix-transfer-php não encontrada. Execute o build primeiro."
    exit 1
fi

echo "✅ Imagem encontrada"

# Criar volumes externos
echo "📦 Criando volumes externos..."
docker volume create pix-transfer-uploads 2>/dev/null || echo "Volume pix-transfer-uploads já existe"
docker volume create pix-transfer-logs 2>/dev/null || echo "Volume pix-transfer-logs já existe"
docker volume create pix-transfer-db-data 2>/dev/null || echo "Volume pix-transfer-db-data já existe"

# Criar rede externa
echo "🌐 Criando rede externa..."
docker network create minha-rede 2>/dev/null || echo "Rede minha-rede já existe"

# Parar containers existentes se houver
echo "🛑 Parando containers existentes..."
docker-compose -f docker-compose.prod.yml down 2>/dev/null || true

# Executar ambiente de produção
echo "🚀 Iniciando ambiente de produção..."
docker-compose -f docker-compose.prod.yml up -d

# Aguardar um pouco para os containers inicializarem
echo "⏳ Aguardando inicialização dos containers..."
sleep 10

# Verificar status
echo "📊 Status dos containers:"
docker-compose -f docker-compose.prod.yml ps

echo ""
echo "✅ Deploy concluído!"
echo ""
echo "📋 Informações importantes:"
echo "- Aplicação: http://localhost (ou seu domínio configurado)"
echo "- MailHog: http://localhost:8025"
echo "- Logs: docker-compose -f docker-compose.prod.yml logs -f"
echo ""
echo "🔍 Para verificar logs em tempo real:"
echo "docker-compose -f docker-compose.prod.yml logs -f" 