# Syslog İmzalama Scripti - Kurulum ve Kullanım Kılavuzu

## Özellikler

✅ **Otomatik İmzalama**: Syslog kayıtlarını zaman damgasıyla imzalar  
✅ **Otomatik Yedekleme**: İmzalamadan önce dosyaları arşivler  
✅ **Tekrar Deneme**: Başarısız imzalama işlemlerini otomatik tekrarlar  
✅ **Yapılandırılabilir**: Tüm ayarlar kolayca değiştirilebilir  
✅ **Detaylı Loglama**: Tüm işlemler sonuc.log dosyasına kaydedilir  
✅ **Güvenli İşleme**: Çoklu çalışmayı engeller, kilit mekanizması kullanır  

## Kurulum

### 1. Dosyaları Yerleştirin

```bash
# Ana scripti kopyalayın
cp syslog_signer.php /usr/local/bin/
chmod +x /usr/local/bin/syslog_signer.php

# Yapılandırma dosyasını kopyalayın
cp syslog_config.php /usr/local/bin/
```

### 2. Klasör Yapısını Oluşturun

```bash
mkdir -p /loglama/{aktif,arsiv,imzali}
chown -R syslog:syslog /loglama
chmod -R 755 /loglama
```

### 3. Cron Job Kurun

```bash
# Crontab düzenleyin
crontab -e

# Saatlik çalıştırma için ekleyin:
0 * * * * /usr/bin/php /usr/local/bin/syslog_signer.php

# Veya scriptten otomatik cron komutu alın:
php /usr/local/bin/syslog_signer.php cron
```

## Kullanım

### Manuel Çalıştırma

```bash
# Normal çalıştırma
php syslog_signer.php

# Test modu
php syslog_signer.php test

# Yapılandırmayı görüntüle
php syslog_signer.php config

# Cron komutu al
php syslog_signer.php cron
```

### Yapılandırma Değiştirme

`syslog_config.php` dosyasını düzenleyin:

```php
return [
    'base_path' => '/loglama',
    'interval_minutes' => 30,        // 30 dakikada bir çalıştır
    'max_retries' => 5,              // 5 kez tekrar dene
    'retry_interval_minutes' => 3,   // 3 dakika bekle
    // ... diğer ayarlar
];
```

## Klasör Yapısı

```
/loglama/
├── aktif/          # İşlenecek log dosyaları
├── arsiv/          # Yedeklenen dosyalar
├── imzali/         # İmzalanmış dosyalar
└── sonuc.log       # İşlem sonuçları
```

## İşlem Akışı

1. **Tarama**: `/loglama/aktif` klasöründeki log dosyaları taranır
2. **Yedekleme**: Her dosya `/loglama/arsiv` klasörüne kopyalanır
3. **İmzalama**: Dosya zaman damgasıyla imzalanır
4. **Taşıma**: İmzalı dosya `/loglama/imzali` klasörüne taşınır  
5. **Temizleme**: Orijinal dosya silinir
6. **Loglama**: Tüm işlemler `sonuc.log`'a kaydedilir

## İmza Formatı

```
=== SYSLOG İMZA BAŞLANGICI ===
Dosya: example.log
İmzalama Zamanı: 2025-05-30 15:30:00 +0300
İmza: [BASE64_ENCODED_SIGNATURE]
=== SYSLOG İÇERİK ===
[ORIJINAL LOG İÇERİĞİ]
=== SYSLOG İMZA SONU ===
Doğrulama İmzası: [SHA256_HASH]
```

## Hata Ayıklama

### Log Dosyası Kontrol

```bash
tail -f /loglama/sonuc.log
```

### Yaygın Problemler

**Problem**: Dosyalar işlenmiyor  
**Çözüm**: Klasör izinlerini kontrol edin, syslog kullanıcısının yazma yetkisi olmalı

**Problem**: İmzalama başarısız  
**Çözüm**: PHP uzantılarını kontrol edin (hash, openssl)

**Problem**: Script çoklu çalışıyor  
**Çözüm**: Kilit dosyasını silin: `rm /loglama/.syslog_signer.lock`

## Güvenlik Notları

- Script root yetkisiyle çalışmamalı
- Klasör izinleri minimum gerekli seviyede tutulmalı  
- Üretim ortamında gerçek TSA (Time Stamping Authority) kullanın
- İmzalı dosyaları düzenli olarak yedekleyin

## Performans İpuçları

- Büyük log dosyaları için `max_file_size` ayarını artırın
- Çok fazla dosya varsa `interval_minutes` değerini düşürün
- SSD kullanımı I/O performansını artırır

## Destek

Sorunlar için log dosyasını kontrol edin:
```bash
grep ERROR /loglama/sonuc.log
```

## Lisans

Bu script ücretsiz kullanım için tasarlanmıştır.