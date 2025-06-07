<?php
/**
 * Veritabanı bağlantısını Singleton deseni ile yönetecek sınıf.
 * Bu yapı, projenin tamamında sadece tek bir veritabanı bağlantı nesnesi
 * oluşturulmasını garanti eder, bu da performansı artırır ve kaynakları verimli kullanır.
 */
class Database {
    // Sınıfın tek örneğini (instance) tutacak olan statik değişken.
    private static $instance = null;
    private $connection;

    // Veritabanı bağlantı bilgileri (camelCase isimlendirme standardına güncellendi).
    private $host = 'localhost';
    private $dbName = 'eticaret';
    private $username = 'root';
    private $password = '';

    /**
     * Kurucu metodu (constructor) 'private' olarak tanımlıyoruz.
     * Bu, sınıfın dışarıdan "new Database()" komutuyla doğrudan çağrılmasını engeller.
     */
    private function __construct() {
        try {
            $this->connection = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->dbName . ";charset=utf8mb4",
                $this->username,
                $this->password
            );
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Veritabanı Bağlantı Hatası: " . $e->getMessage());
            die("Sistemde bir hata oluştu. Lütfen daha sonra tekrar deneyin.");
        }
    }

    /**
     * Bu statik metot, sınıfın tek örneğini oluşturan veya mevcut olanı döndüren metottur.
     */
    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    /**
     * PDO bağlantı nesnesini döndürür.
     */
    public function getConnection() {
        return $this->connection;
    }

    /**
     * Singleton deseninin bir parçası olarak, nesnenin kopyalanmasını (clone) engeller.
     * Bu metot kasıtlı olarak boştur çünkü bu sınıfın bir kopyasının oluşturulması istenmez.
     */
    private function __clone() {
        // Klonlamayı engellemek için boş bırakıldı.
    }

    /**
     * Singleton deseninin bir parçası olarak, nesnenin serileştirme (unserialize) işleminden
     * sonra yeniden oluşturulmasını engeller. Bu, desenin bütünlüğünü korur.
     */
    public function __wakeup() {
        // Serileştirmeyi engellemek için boş bırakıldı.
    }
}
