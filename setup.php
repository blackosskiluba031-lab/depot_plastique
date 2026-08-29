<?php
// Script d'installation de la base de données
header('Content-Type: text/html; charset=utf-8');
mb_internal_encoding('UTF-8');

$host = '127.0.0.1';
$username = 'root';
$password = '';

try {
    // Connexion sans spécifier la base de données
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Connexion réussie à MySQL<br>";

    // Lire le script SQL
    $sql = file_get_contents('setup_database.sql');

    if ($sql === false) {
        die("Impossible de lire le fichier setup_database.sql");
    }

    // Exécuter le script SQL
    $pdo->exec($sql);

    echo "Base de données et tables créées avec succès !<br>";
    echo "Aucune donnée de test n'a été insérée.<br>";

    // Vérifier la création
    $pdo = new PDO("mysql:host=$host;dbname=fetiprod_db;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<br>Vérification des tables :<br>";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        echo "- $table<br>";
    }

    // Vérifier qu'il n'y a pas de données
    echo "<br>Vérification des données :<br>";
    $clients_count = $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
    $produits_count = $pdo->query("SELECT COUNT(*) FROM produits")->fetchColumn();
    echo "Clients: $clients_count<br>";
    echo "Produits: $produits_count<br>";

    echo "<br><strong>Installation terminée avec succès !</strong><br>";
    echo "La base de données est vide et prête pour vos données.<br>";
    echo "<a href='index.php'>Accéder à l'application</a>";

} catch (PDOException $e) {
    die("Erreur : " . $e->getMessage());
}
?>
