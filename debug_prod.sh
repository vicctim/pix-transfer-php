#!/bin/bash

echo "🔍 Debug do ambiente de produção..."
echo ""

echo "📊 Status dos containers:"
docker-compose -f docker-compose.prod.yml ps
echo ""

echo "🐳 Containers rodando:"
docker ps
echo ""

echo "🌐 Portas em uso:"
docker port pix-transfer 2>/dev/null || echo "Container pix-transfer não está rodando"
echo ""

echo "📝 Logs da aplicação:"
docker-compose -f docker-compose.prod.yml logs --tail=20 pix-transfer
echo ""

echo "🗄️ Logs do banco:"
docker-compose -f docker-compose.prod.yml logs --tail=10 pix-transfer-db
echo ""

echo "📧 Logs do MailHog:"
docker-compose -f docker-compose.prod.yml logs --tail=10 pix-transfer-mailhog
echo ""

echo "🌐 Rede:"
docker network ls | grep minha-rede
echo ""

echo "📦 Volumes:"
docker volume ls | grep pix-transfer
echo ""

echo "🔧 Tentando reiniciar..."
docker-compose -f docker-compose.prod.yml down
docker-compose -f docker-compose.prod.yml up -d

echo ""
echo "⏳ Aguardando 15 segundos..."
sleep 15

echo "📊 Status final:"
docker-compose -f docker-compose.prod.yml ps
echo ""

echo "🌐 URL da aplicação:"
echo "http://localhost"
echo "http://127.0.0.1"
echo ""
echo "📧 URL do MailHog:"
echo "http://localhost:8025" 