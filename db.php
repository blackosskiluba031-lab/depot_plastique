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
    
    // Désactiver ONLY_FULL_GROUP_BY pour compatibilité totale MySQL 8 / TiDB Cloud
    try {
        $pdo->exec("SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'");
    } catch (Exception $e) {
        // Ignorer si le serveur ne supporte pas
    }

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
            prix_achat DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            quantite_actuelle DECIMAL(10, 2) DEFAULT 0,
            seuil_alerte DECIMAL(10, 2) DEFAULT 10
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS ventes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_id INT,
            date_vente DATETIME DEFAULT CURRENT_TIMESTAMP,
            montant_total DECIMAL(10, 2) NOT NULL,
            montant_paye DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            reste_a_payer DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            statut_paiement VARCHAR(30) DEFAULT 'cash',
            FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS details_vente (
            id INT AUTO_INCREMENT PRIMARY KEY,
            vente_id INT NOT NULL,
            produit_id INT NOT NULL,
            quantite DECIMAL(10, 2) NOT NULL,
            prix_applique DECIMAL(10, 2) NOT NULL,
            prix_achat DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
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

        CREATE TABLE IF NOT EXISTS paiements_clients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_id INT NOT NULL,
            vente_id INT NULL,
            montant DECIMAL(10, 2) NOT NULL,
            date_paiement DATETIME DEFAULT CURRENT_TIMESTAMP,
            mode_paiement VARCHAR(50) DEFAULT 'Cash',
            notes VARCHAR(255) NULL,
            FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
            FOREIGN KEY (vente_id) REFERENCES ventes(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // Migration non destructive des colonnes pour bases existantes
    try {
        // Colonne prix_achat dans produits
        $checkCol = $pdo->query("SHOW COLUMNS FROM produits LIKE 'prix_achat'")->fetch();
        if (!$checkCol) {
            $pdo->exec("ALTER TABLE produits ADD COLUMN prix_achat DECIMAL(10, 2) NOT NULL DEFAULT 0.00 AFTER prix_unitaire");
        }

        // Colonne prix_achat dans details_vente
        $checkCol = $pdo->query("SHOW COLUMNS FROM details_vente LIKE 'prix_achat'")->fetch();
        if (!$checkCol) {
            $pdo->exec("ALTER TABLE details_vente ADD COLUMN prix_achat DECIMAL(10, 2) NOT NULL DEFAULT 0.00 AFTER prix_applique");
        }

        // Colonnes montant_paye et reste_a_payer dans ventes
        $checkCol = $pdo->query("SHOW COLUMNS FROM ventes LIKE 'montant_paye'")->fetch();
        if (!$checkCol) {
            $pdo->exec("ALTER TABLE ventes ADD COLUMN montant_paye DECIMAL(10, 2) NOT NULL DEFAULT 0.00 AFTER montant_total");
        }
        $checkCol = $pdo->query("SHOW COLUMNS FROM ventes LIKE 'reste_a_payer'")->fetch();
        if (!$checkCol) {
            $pdo->exec("ALTER TABLE ventes ADD COLUMN reste_a_payer DECIMAL(10, 2) NOT NULL DEFAULT 0.00 AFTER montant_paye");
        }

        // Modification statut_paiement pour supporter cash, credit, partiel, etc.
        try {
            $pdo->exec("ALTER TABLE ventes MODIFY COLUMN statut_paiement VARCHAR(30) DEFAULT 'cash'");
        } catch (Exception $e) {
            // Ignorer si déjà configuré
        }

        // Rétrocompatibilité : initialiser montant_paye et reste_a_payer pour les anciennes ventes
        $pdo->exec("
            UPDATE ventes 
            SET montant_paye = montant_total, reste_a_payer = 0.00, statut_paiement = 'cash' 
            WHERE statut_paiement = 'paye' AND montant_paye = 0.00 AND montant_total > 0
        ");
        $pdo->exec("
            UPDATE ventes 
            SET montant_paye = 0.00, reste_a_payer = montant_total, statut_paiement = 'credit' 
            WHERE statut_paiement = 'en_attente' AND reste_a_payer = 0.00 AND montant_total > 0
        ");
        
    } catch (Exception $e) {
        // En cas d'erreur mineure de migration, continuer
    }

} catch (PDOException $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}
?>
