# Script PowerShell para build da imagem de produção
Write-Host "🚀 Iniciando build da imagem de produção..." -ForegroundColor Green

# Verificar se o Docker está disponível
try {
    $dockerVersion = docker --version
    Write-Host "✅ Docker encontrado: $dockerVersion" -ForegroundColor Green
} catch {
    Write-Host "❌ Docker não está instalado ou não está no PATH" -ForegroundColor Red
    exit 1
}

# Verificar se o docker-compose está disponível
try {
    $composeVersion = docker-compose --version
    Write-Host "✅ Docker Compose encontrado: $composeVersion" -ForegroundColor Green
} catch {
    Write-Host "❌ Docker Compose não está instalado ou não está no PATH" -ForegroundColor Red
    exit 1
}

# Fazer build da imagem de produção
Write-Host "🔨 Fazendo build da imagem vicctim/pix-transfer-php:latest..." -ForegroundColor Yellow
docker build -f Dockerfile.prod -t vicctim/pix-transfer-php:latest . --no-cache

if ($LASTEXITCODE -eq 0) {
    Write-Host "✅ Build da imagem concluído com sucesso!" -ForegroundColor Green
    
    # Fazer build dos serviços do docker-compose.prod.yml
    Write-Host "🔨 Fazendo build dos serviços de produção..." -ForegroundColor Yellow
    docker-compose -f docker-compose.prod.yml build
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host "✅ Build dos serviços concluído com sucesso!" -ForegroundColor Green
        Write-Host ""
        Write-Host "📋 Próximos passos:" -ForegroundColor Cyan
        Write-Host "1. Criar os volumes externos:" -ForegroundColor White
        Write-Host "   docker volume create pix-transfer-uploads" -ForegroundColor Gray
        Write-Host "   docker volume create pix-transfer-logs" -ForegroundColor Gray
        Write-Host "   docker volume create pix-transfer-db-data" -ForegroundColor Gray
        Write-Host ""
        Write-Host "2. Criar a rede externa:" -ForegroundColor White
        Write-Host "   docker network create minha-rede" -ForegroundColor Gray
        Write-Host ""
        Write-Host "3. Executar o ambiente de produção:" -ForegroundColor White
        Write-Host "   docker-compose -f docker-compose.prod.yml up -d" -ForegroundColor Gray
        Write-Host ""
        Write-Host "4. Para fazer push para o Docker Hub:" -ForegroundColor White
        Write-Host "   docker push vicctim/pix-transfer-php:latest" -ForegroundColor Gray
    } else {
        Write-Host "❌ Erro ao fazer build dos serviços" -ForegroundColor Red
        exit 1
    }
} else {
    Write-Host "❌ Erro ao fazer build da imagem" -ForegroundColor Red
    exit 1
} 