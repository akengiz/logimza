<?php
/**
 * Syslog İmzalama Scripti Yapılandırma Dosyası
 * Bu dosyayı syslog_config.php olarak kaydedin
 */

return [
    // Ana klasör yolu
    'base_path' => '/loglama',
    
    // Alt klasör adları
    'active_folder' => 'aktif',     // Aktif log dosyalarının bulunduğu klasör
    'archive_folder' => 'arsiv',    // Arşiv klasörü
    'signed_folder' => 'imzali',    // İmzalanan dosyaların klasörü
    'result_log' => 'sonuc.log',    // Sonuç log dosyası
    
    // Zaman ayarları (dakika cinsinden)
    'interval_minutes' => 60,           // İmzalama aralığı (1 saat = 60 dakika)
    'retry_interval_minutes' => 5,      // Tekrar deneme aralığı (5 dakika)
    'max_retries' => 3,                 // Maksimum tekrar sayısı
    
    // Timestamp sunucusu (ücretsiz)
    'timestamp_server' => 'http://timestamp.digicert.com',
    
    // İşlenecek dosya uzantıları
    'log_extensions' => ['log', 'txt', 'syslog', 'out'],
    
    // Gelişmiş ayarlar
    'cleanup_old_signed' => true,       // Eski imzalı dosyaları temizle
    'signed_retention_days' => 30,      // İmzalı dosyaları kaç gün sakla
    'archive_retention_days' => 90,     // Arşiv dosyalarını kaç gün sakla
    
    // Log seviyeleri
    'log_level' => 'INFO',              // DEBUG, INFO, WARNING, ERROR
    'max_log_size' => 10485760,         // 10MB - log dosyası max boyutu
    
    // Performans ayarları
    'max_file_size' => 104857600,       // 100MB - işlenecek max dosya boyutu
    'parallel_processing' => false,      // Paralel işleme (gelişmiş)
];

?>