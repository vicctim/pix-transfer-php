#!/bin/bash

# Script de Deploy - Pix Transfer
# Uso: ./deploy.sh [start|stop|restart|logs|backup]

set -e

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Função para log colorido
log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')] $1${NC}"
}

error() {
    echo -e "${RED}[ERRO] $1${NC}"
}

warning() {
    echo -e "${YELLOW}[AVISO] $1${NC}"
}

info() {
    echo -e "${BLUE}[INFO] $1${NC}"
}

# Verificar se Docker está instalado
check_docker() {
    if ! command -v docker &> /dev/null; then
        error "Docker não está instalado. Instale o Docker primeiro."
        exit 1
    fi
    
    if ! command -v docker-compose &> /dev/null; then
        error "Docker Compose não está instalado. Instale o Docker Compose primeiro."
        exit 1
    fi
}

# Criar volumes necessários
create_volumes() {
    log "Criando volumes Docker..."
    
    volumes=("pix-transfer-uploads" "pix-transfer-logs" "pix-transfer-db-data")
    
    for volume in "${volumes[@]}"; do
        if ! docker volume inspect "$volume" &> /dev/null; then
            log "Criando volume: $volume"
            docker volume create "$volume"
        else
            info "Volume $volume já existe"
        fi
    done
}

# Verificar rede
check_network() {
    if ! docker network inspect minha-rede &> /dev/null; then
        error "Rede 'minha-rede' não encontrada. Crie a rede primeiro:"
        error "docker network create minha-rede"
        exit 1
    fi
}

# Iniciar serviços
start() {
    log "Iniciando Pix Transfer..."
    check_docker
    check_network
    create_volumes
    
    log "Fazendo pull da imagem mais recente..."
    docker pull vicctim/pix-transfer-php:latest
    
    log "Iniciando serviços..."
    docker-compose -f docker-compose.prod.yml up -d
    
    log "Aguardando serviços inicializarem..."
    sleep 10
    
    log "Verificando status dos serviços..."
    docker-compose -f docker-compose.prod.yml ps
    
    log "✅ Pix Transfer iniciado com sucesso!"
    log "🌐 Aplicação: https://transfer.victorsamuel.com.br"
    log "📧 MailHog: https://mail.transfer.victorsamuel.com.br"
    log "👤 Admin: admin@transfer.com / password"
}

# Parar serviços
stop() {
    log "Parando Pix Transfer..."
    docker-compose -f docker-compose.prod.yml down
    log "✅ Serviços parados"
}

# Reiniciar serviços
restart() {
    log "Reiniciando Pix Transfer..."
    stop
    start
}

# Mostrar logs
logs() {
    log "Mostrando logs dos serviços..."
    docker-compose -f docker-compose.prod.yml logs -f
}

# Backup
backup() {
    log "Iniciando backup..."
    
    # Criar diretório de backup
    backup_dir="backup_$(date +%Y%m%d_%H%M%S)"
    mkdir -p "$backup_dir"
    
    # Backup do banco
    log "Fazendo backup do banco de dados..."
    docker exec pix-transfer-db mysqldump -u root -proot_password upload_system > "$backup_dir/database.sql"
    
    # Backup dos uploads
    log "Fazendo backup dos uploads..."
    docker run --rm -v pix-transfer-uploads:/data -v "$(pwd)/$backup_dir":/backup alpine tar czf /backup/uploads.tar.gz -C /data .
    
    log "✅ Backup concluído em: $backup_dir"
}

# Status
status() {
    log "Status dos serviços:"
    docker-compose -f docker-compose.prod.yml ps
    
    echo ""
    log "Logs recentes:"
    docker-compose -f docker-compose.prod.yml logs --tail=20
}

# Atualizar
update() {
    log "Atualizando Pix Transfer..."
    
    # Parar serviços
    stop
    
    # Pull da nova imagem
    log "Baixando nova versão..."
    docker pull vicctim/pix-transfer-php:latest
    
    # Iniciar serviços
    start
    
    log "✅ Atualização concluída!"
}

# Limpeza
cleanup() {
    warning "Esta operação irá remover TODOS os dados!"
    read -p "Tem certeza? (y/N): " -n 1 -r
    echo
    
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        log "Removendo containers e volumes..."
        docker-compose -f docker-compose.prod.yml down -v
        
        volumes=("pix-transfer-uploads" "pix-transfer-logs" "pix-transfer-db-data")
        for volume in "${volumes[@]}"; do
            docker volume rm "$volume" 2>/dev/null || true
        done
        
        log "✅ Limpeza concluída"
    else
        log "Operação cancelada"
    fi
}

# Menu de ajuda
help() {
    echo "Pix Transfer - Script de Deploy"
    echo ""
    echo "Uso: $0 [comando]"
    echo ""
    echo "Comandos:"
    echo "  start     - Iniciar serviços"
    echo "  stop      - Parar serviços"
    echo "  restart   - Reiniciar serviços"
    echo "  logs      - Mostrar logs"
    echo "  status    - Status dos serviços"
    echo "  backup    - Fazer backup"
    echo "  update    - Atualizar para nova versão"
    echo "  cleanup   - Remover tudo (CUIDADO!)"
    echo "  help      - Mostrar esta ajuda"
    echo ""
    echo "Exemplo:"
    echo "  $0 start"
}

# Main
case "${1:-help}" in
    start)
        start
        ;;
    stop)
        stop
        ;;
    restart)
        restart
        ;;
    logs)
        logs
        ;;
    status)
        status
        ;;
    backup)
        backup
        ;;
    update)
        update
        ;;
    cleanup)
        cleanup
        ;;
    help|--help|-h)
        help
        ;;
    *)
        error "Comando inválido: $1"
        help
        exit 1
        ;;
esac 