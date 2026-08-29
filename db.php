<?php
// Configuration de la base de données (compatible Local Laragon & Render Cloud)

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$dbname = getenv('DB_NAME') ?: 'fetiprod_db';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '';

// Prise en charge d'une URL de base de données (ex: MYSQL_URL ou DATABASE_URL)
$db_url = getenv('MYSQL_URL') ?: getenv('DATABASE_URL');
if ($db_url) {
    $parsed_url = parse_url($db_url);
    if ($parsed_url) {
        $host = $parsed_url['host'] ?? $host;
        $port = $parsed_url['port'] ?? $port;
        $username = $parsed_url['user'] ?? $username;
        $password = $parsed_url['pass'] ?? $password;
        $dbname = isset($parsed_url['path']) ? ltrim($parsed_url['path'], '/') : $dbname;
    }
}

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ];

    // Activer SSL pour les bases cloud comme TiDB Cloud
    if ($host !== '127.0.0.1' && $host !== 'localhost') {
        if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }
        if (defined('PDO::MYSQL_ATTR_SSL_CA')) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = true;
        }
    }

    $pdo = new PDO($dsn, $username, $password, $options);

    // Auto-création des tables si elles n'existent pas encore (pour déploiement cloud fluide)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS clients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(255) NOT NULL,
            telephone VARCHAR(50),
            type_client ENUM('Particulier', 'Grossiste', 'Recycleur') DEFAULT 'Particulier',
            date_creation DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS produits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom_article VARCHAR(255) NOT NULL,
            categorie VARCHAR(100),
            unite_mesure VARCHAR(50) DEFAULT 'kg',
            prix_unitaire DECIMAL(10, 2) NOT NULL,
            quantite_actuelle DECIMAL(10, 2) DEFAULT 0,
            seuil_alerte DECIMAL(10, 2) DEFAULT 10
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS ventes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_id INT,
            date_vente DATETIME DEFAULT CURRENT_TIMESTAMP,
            montant_total DECIMAL(10, 2) NOT NULL,
            statut_paiement ENUM('paye', 'en_attente', 'annule') DEFAULT 'en_attente',
            FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS details_vente (
            id INT AUTO_INCREMENT PRIMARY KEY,
            vente_id INT NOT NULL,
            produit_id INT NOT NULL,
            quantite DECIMAL(10, 2) NOT NULL,
            prix_applique DECIMAL(10, 2) NOT NULL,
            FOREIGN KEY (vente_id) REFERENCES ventes(id) ON DELETE CASCADE,
            FOREIGN KEY (produit_id) REFERENCES produits(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS mouvements_stock (
            id INT AUTO_INCREMENT PRIMARY KEY,
            produit_id INT NOT NULL,
            type_mouvement ENUM('entree', 'sortie') NOT NULL,
            quantite DECIMAL(10, 2) NOT NULL,
            motif VARCHAR(255),
            date_mouvement DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (produit_id) REFERENCES produits(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

} catch (PDOException $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}
?>
