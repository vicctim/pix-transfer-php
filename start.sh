#!/bin/bash

echo "🚀 Iniciando Upload System..."

# Verificar se Docker está instalado
if ! command -v docker &> /dev/null; then
    echo "❌ Docker não está instalado. Por favor, instale o Docker primeiro."
    exit 1
fi

# Verificar se Docker Compose está instalado
if ! command -v docker-compose &> /dev/null; then
    echo "❌ Docker Compose não está instalado. Por favor, instale o Docker Compose primeiro."
    exit 1
fi

# Criar diretórios necessários
echo "📁 Criando diretórios..."
mkdir -p uploads logs

# Parar containers existentes
echo "🛑 Parando containers existentes..."
docker-compose down

# Construir e iniciar containers
echo "🔨 Construindo e iniciando containers..."
docker-compose up -d --build

# Aguardar MySQL inicializar
echo "⏳ Aguardando MySQL inicializar..."
sleep 30

# Verificar se containers estão rodando
echo "🔍 Verificando status dos containers..."
docker-compose ps

echo ""
echo "✅ Upload System iniciado com sucesso!"
echo ""
echo "🌐 Acesse a aplicação em: http://localhost:3131"
echo "📧 Interface de email em: http://localhost:8025"
echo ""
echo "👤 Credenciais de teste:"
echo "   Usuário: admin"
echo "   Senha: password"
echo ""
echo "📋 Comandos úteis:"
echo "   docker-compose logs web    # Ver logs do PHP"
echo "   docker-compose logs db     # Ver logs do MySQL"
echo "   docker-compose down        # Parar containers"
echo "   docker-compose restart     # Reiniciar containers" 