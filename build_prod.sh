#!/bin/bash

echo "🚀 Iniciando build da imagem de produção..."

# Verificar se o Docker está disponível
if ! command -v docker &> /dev/null; then
    echo "❌ Docker não está instalado ou não está no PATH"
    exit 1
fi

# Verificar se o docker-compose está disponível
if ! command -v docker-compose &> /dev/null; then
    echo "❌ Docker Compose não está instalado ou não está no PATH"
    exit 1
fi

echo "✅ Docker e Docker Compose encontrados"

# Fazer build da imagem de produção
echo "🔨 Fazendo build da imagem vicctim/pix-transfer-php:latest..."
docker build -f Dockerfile.prod -t vicctim/pix-transfer-php:latest . --no-cache

if [ $? -eq 0 ]; then
    echo "✅ Build da imagem concluído com sucesso!"
    
    # Fazer build dos serviços do docker-compose.prod.yml
    echo "🔨 Fazendo build dos serviços de produção..."
    docker-compose -f docker-compose.prod.yml build
    
    if [ $? -eq 0 ]; then
        echo "✅ Build dos serviços concluído com sucesso!"
        echo ""
        echo "📋 Próximos passos:"
        echo "1. Criar os volumes externos:"
        echo "   docker volume create pix-transfer-uploads"
        echo "   docker volume create pix-transfer-logs"
        echo "   docker volume create pix-transfer-db-data"
        echo ""
        echo "2. Criar a rede externa:"
        echo "   docker network create minha-rede"
        echo ""
        echo "3. Executar o ambiente de produção:"
        echo "   docker-compose -f docker-compose.prod.yml up -d"
        echo ""
        echo "4. Para fazer push para o Docker Hub:"
        echo "   docker push vicctim/pix-transfer-php:latest"
    else
        echo "❌ Erro ao fazer build dos serviços"
        exit 1
    fi
else
    echo "❌ Erro ao fazer build da imagem"
    exit 1
fi 