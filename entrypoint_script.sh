#!/bin/bash
# Log Processor Entrypoint Script
# /log-processor/entrypoint.sh

set -e

# Renk kodları
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Log fonksiyonu
log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')] ✓${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[$(date +'%Y-%m-%d %H:%M:%S')] ⚠${NC} $1"
}

log_error() {
    echo -e "${RED}[$(date +'%Y-%m-%d %H:%M:%S')] ✗${NC} $1"
}

# Başlangıç
log "Log Processor Container başlatılıyor..."

# Ortam değişkenlerini kontrol et
LOG_LEVEL=${LOG_LEVEL:-INFO}
CRON_SCHEDULE=${CRON_SCHEDULE:-"0 * * * *"}
TZ=${TZ:-"Europe/Istanbul"}

log "Zaman dilimi: $TZ"
log "Log seviyesi: $LOG_LEVEL"
log "Cron zamanlaması: $CRON_SCHEDULE"

# Zaman dilimini ayarla
ln -sf /usr/share/zoneinfo/$TZ /etc/localtime
echo $TZ > /etc/timezone

# Gerekli dizinleri oluştur
mkdir -p /loglama/{aktif,arsiv,imzali}
mkdir -p /app/logs
mkdir -p /var/log/supervisor

# İzinleri ayarla
chown -R syslog:syslog /loglama
chown -R syslog:syslog /app
chmod 755 /loglama/{aktif,arsiv,imzali}

log_success "Dizinler oluşturuldu ve izinler ayarlandı"

# Yapılandırma dosyasını kontrol et
if [ -f "/app/syslog_config.php" ]; then
    log_success "Yapılandırma dosyası bulundu"
else
    log_warning "Yapılandırma dosyası bulunamadı, varsayılan ayarlar kullanılacak"
fi

# PHP uzantılarını kontrol et
php -m | grep -q hash && log_success "PHP hash uzantısı aktif" || log_error "PHP hash uzantısı bulunamadı"
php -m | grep -q openssl && log_success "PHP openssl uzantısı aktif" || log_warning "PHP openssl uzantısı bulunamadı"

# Syslog signer scriptini test et
if php /app/syslog_signer.php --version > /dev/null 2>&1; then
    log_success "Syslog signer scripti test edildi"
else
    log_warning "Syslog signer scripti test edilemedi"
fi

# Cron job'ını güncelle
echo "$CRON_SCHEDULE /usr/local/bin/php /app/syslog_signer.php >> /app/logs/cron.log 2>&1" > /var/spool/cron/crontabs/root
chmod 0644 /var/spool/cron/crontabs/root

log_success "Cron job güncellendi: $CRON_SCHEDULE"

# Log dosyalarını oluştur
touch /app/logs/cron.log
touch /app/logs/processor.log
touch /loglama/sonuc.log

chown syslog:syslog /app/logs/*.log
chown syslog:syslog /loglama/sonuc.log

# Sistem bilgilerini logla
log "Container bilgileri:"
echo "  - Hostname: $(hostname)"
echo "  - PHP Version: $(php -v | head -n1)"
echo "  - Disk kullanımı:"
df -h /loglama
echo "  - Bellek kullanımı:"
free -m