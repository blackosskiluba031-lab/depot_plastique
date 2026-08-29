<?php
// Script de réinitialisation de la base de données
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

    // Supprimer la base de données existante
    $pdo->exec("DROP DATABASE IF EXISTS fetiprod_db");
    echo "Base de données supprimée<br>";

    // Recréer la base de données
    $pdo->exec("CREATE DATABASE fetiprod_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Base de données recréée<br>";

    // Lire et exécuter le script SQL
    $sql = file_get_contents('setup_database.sql');
    $pdo->exec("USE fetiprod_db");
    $pdo->exec($sql);

    echo "Tables créées avec succès (sans données de test) !<br>";

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

    echo "<br><strong>Réinitialisation terminée avec succès !</strong><br>";
    echo "La base de données est maintenant vide et prête pour vos données.<br>";
    echo "<a href='index.php'>Accéder à l'application</a>";

} catch (PDOException $e) {
    die("Erreur : " . $e->getMessage());
}
?>
