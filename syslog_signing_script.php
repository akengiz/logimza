<?php
/**
 * Syslog İmzalama Scripti
 * Syslog kayıtlarını zaman damgasıyla imzalar ve yedekler
 * 
 * @author Script Generator
 * @version 1.0
 */

class SyslogSigner {
    
    // Yapılandırma ayarları
    private $config = [
        'base_path' => '/loglama',
        'active_folder' => 'aktif',
        'archive_folder' => 'arsiv',
        'signed_folder' => 'imzali',
        'result_log' => 'sonuc.log',
        'interval_minutes' => 60,           // İmzalama aralığı (dakika)
        'retry_interval_minutes' => 5,     // Tekrar deneme aralığı (dakika)
        'max_retries' => 3,                // Maksimum tekrar sayısı
        'timestamp_server' => 'http://timestamp.digicert.com', // Ücretsiz timestamp sunucusu
        'log_extensions' => ['log', 'txt', 'syslog'], // İşlenecek dosya uzantıları
    ];
    
    private $paths = [];
    private $lockFile;
    
    public function __construct($customConfig = []) {
        // Özel yapılandırma varsa birleştir
        $this->config = array_merge($this->config, $customConfig);
        
        // Klasör yollarını hazırla
        $this->setupPaths();
        
        // Kilitleme dosyası
        $this->lockFile = $this->paths['base'] . '/.syslog_signer.lock';
        
        // Gerekli klasörleri oluştur
        $this->createDirectories();
    }
    
    private function setupPaths() {
        $base = $this->config['base_path'];
        $this->paths = [
            'base' => $base,
            'active' => $base . '/' . $this->config['active_folder'],
            'archive' => $base . '/' . $this->config['archive_folder'],
            'signed' => $base . '/' . $this->config['signed_folder'],
            'result_log' => $base . '/' . $this->config['result_log']
        ];
    }
    
    private function createDirectories() {
        foreach ($this->paths as $key => $path) {
            if ($key !== 'result_log' && !is_dir($path)) {
                if (!mkdir($path, 0755, true)) {
                    $this->logResult("HATA: $path klasörü oluşturulamadı", 'ERROR');
                    exit(1);
                }
            }
        }
    }
    
    public function run() {
        // Çoklu çalışmayı engelle
        if ($this->isRunning()) {
            $this->logResult("Script zaten çalışıyor, çıkılıyor.", 'INFO');
            return false;
        }
        
        $this->createLock();
        
        try {
            $this->logResult("Syslog imzalama işlemi başlatıldı", 'INFO');
            
            // Aktif klasördeki dosyaları bul
            $logFiles = $this->getLogFiles();
            
            if (empty($logFiles)) {
                $this->logResult("İmzalanacak log dosyası bulunamadı", 'INFO');
                return true;
            }
            
            foreach ($logFiles as $logFile) {
                $this->processLogFile($logFile);
            }
            
            $this->logResult("Syslog imzalama işlemi tamamlandı", 'INFO');
            
        } catch (Exception $e) {
            $this->logResult("Beklenmeyen hata: " . $e->getMessage(), 'ERROR');
        } finally {
            $this->removeLock();
        }
        
        return true;
    }
    
    private function getLogFiles() {
        $files = [];
        $extensions = $this->config['log_extensions'];
        
        if (!is_dir($this->paths['active'])) {
            return $files;
        }
        
        $iterator = new DirectoryIterator($this->paths['active']);
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $extension = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
                if (in_array($extension, $extensions)) {
                    $files[] = $file->getPathname();
                }
            }
        }
        
        return $files;
    }
    
    private function processLogFile($logFile) {
        $filename = basename($logFile);
        $this->logResult("İşleniyor: $filename", 'INFO');
        
        // 1. Dosyayı arşiv klasörüne kopyala
        if (!$this->backupFile($logFile)) {
            return false;
        }
        
        // 2. Dosyayı imzala (tekrar deneme ile)
        $signed = $this->signFileWithRetry($logFile);
        
        if ($signed) {
            // 3. İmzalanan dosyayı imzalı klasörüne taşı
            $signedPath = $this->paths['signed'] . '/' . $filename . '.signed';
            if (rename($signed, $signedPath)) {
                $this->logResult("$filename başarıyla imzalandı ve taşındı", 'SUCCESS');
                
                // Orijinal dosyayı sil
                unlink($logFile);
            } else {
                $this->logResult("$filename imzalandı ancak taşınamadı", 'WARNING');
            }
        }
        
        return true;
    }
    
    private function backupFile($sourceFile) {
        $filename = basename($sourceFile);
        $timestamp = date('Y-m-d_H-i-s');
        $backupPath = $this->paths['archive'] . '/' . $timestamp . '_' . $filename;
        
        if (copy($sourceFile, $backupPath)) {
            $this->logResult("$filename arşivlendi: $backupPath", 'INFO');
            return true;
        } else {
            $this->logResult("$filename arşivlenemedi", 'ERROR');
            return false;
        }
    }
    
    private function signFileWithRetry($logFile) {
        $maxRetries = $this->config['max_retries'];
        $retryInterval = $this->config['retry_interval_minutes'] * 60; // saniyeye çevir
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $this->logResult("İmzalama denemesi $attempt/" . $maxRetries . ": " . basename($logFile), 'INFO');
            
            $signedFile = $this->signFile($logFile);
            
            if ($signedFile) {
                $this->logResult("İmzalama başarılı: " . basename($logFile), 'SUCCESS');
                return $signedFile;
            }
            
            if ($attempt < $maxRetries) {
                $this->logResult("İmzalama başarısız, $retryInterval saniye beklenecek", 'WARNING');
                sleep($retryInterval);
            }
        }
        
        $this->logResult("İmzalama " . $maxRetries . " denemeden sonra başarısız: " . basename($logFile), 'ERROR');
        return false;
    }
    
    private function signFile($logFile) {
        try {
            $filename = basename($logFile);
            $timestamp = date('Y-m-d H:i:s O');
            $content = file_get_contents($logFile);
            
            // Basit imza oluştur (gerçek ortamda dijital imza kullanın)
            $signature = $this->createTimestampSignature($content, $timestamp);
            
            // İmzalı içerik oluştur
            $signedContent = "=== SYSLOG İMZA BAŞLANGICI ===\n";
            $signedContent .= "Dosya: $filename\n";
            $signedContent .= "İmzalama Zamanı: $timestamp\n";
            $signedContent .= "İmza: $signature\n";
            $signedContent .= "=== SYSLOG İÇERİK ===\n";
            $signedContent .= $content;
            $signedContent .= "\n=== SYSLOG İMZA SONU ===\n";
            $signedContent .= "Doğrulama İmzası: " . hash('sha256', $signature . $content) . "\n";
            
            // Geçici imzalı dosya oluştur
            $tempSignedFile = $this->paths['active'] . '/.' . $filename . '.signed.tmp';
            
            if (file_put_contents($tempSignedFile, $signedContent) !== false) {
                return $tempSignedFile;
            }
            
        } catch (Exception $e) {
            $this->logResult("İmzalama hatası: " . $e->getMessage(), 'ERROR');
        }
        
        return false;
    }
    
    private function createTimestampSignature($content, $timestamp) {
        // Basit timestamp imzası oluştur
        $hash = hash('sha256', $content);
        $timestampHash = hash('sha256', $timestamp);
        
        // RFC 3161 benzeri basit timestamp token (gerçek ortamda TSA kullanın)
        $signature = base64_encode($hash . ':' . $timestampHash . ':' . time());
        
        return $signature;
    }
    
    private function logResult($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;
        
        file_put_contents($this->paths['result_log'], $logEntry, FILE_APPEND | LOCK_EX);
        
        // Konsola da yazdır
        echo $logEntry;
    }
    
    private function isRunning() {
        return file_exists($this->lockFile);
    }
    
    private function createLock() {
        file_put_contents($this->lockFile, getmypid());
    }
    
    private function removeLock() {
        if (file_exists($this->lockFile)) {
            unlink($this->lockFile);
        }
    }
    
    // Yapılandırma ayarlarını güncelle
    public function updateConfig($newConfig) {
        $this->config = array_merge($this->config, $newConfig);
        $this->setupPaths();
    }
    
    // Mevcut yapılandırmayı göster
    public function getConfig() {
        return $this->config;
    }
    
    // Cron job kurulumu için yardımcı fonksiyon
    public function getCronCommand() {
        $scriptPath = __FILE__;
        $interval = $this->config['interval_minutes'];
        
        if ($interval == 60) {
            return "0 * * * * /usr/bin/php $scriptPath";
        } else {
            return "*/$interval * * * * /usr/bin/php $scriptPath";
        }
    }
}

// Script doğrudan çalıştırılıyorsa
if (php_sapi_name() === 'cli' && basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    
    // Özel yapılandırma dosyası varsa yükle
    $configFile = dirname(__FILE__) . '/syslog_config.php';
    $customConfig = [];
    
    if (file_exists($configFile)) {
        $customConfig = include $configFile;
    }
    
    // Syslog imzalayıcıyı başlat
    $signer = new SyslogSigner($customConfig);
    
    // Komut satırı argümanlarını kontrol et
    if (isset($argv[1])) {
        switch ($argv[1]) {
            case 'config':
                echo "Mevcut Yapılandırma:\n";
                print_r($signer->getConfig());
                break;
                
            case 'cron':
                echo "Cron Job Komutu:\n";
                echo $signer->getCronCommand() . "\n";
                break;
                
            case 'test':
                echo "Test modu - tek seferlik çalıştırma\n";
                $signer->run();
                break;
                
            default:
                echo "Kullanım: php " . basename(__FILE__) . " [config|cron|test]\n";
                break;
        }
    } else {
        // Normal çalıştırma
        $signer->run();
    }
}

?>