-- Script de création de la base de données fetiprod_db
CREATE DATABASE IF NOT EXISTS fetiprod_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fetiprod_db;

-- Table clients
CREATE TABLE IF NOT EXISTS clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(255) NOT NULL,
    telephone VARCHAR(50),
    type_client ENUM('Particulier', 'Grossiste', 'Recycleur') DEFAULT 'Particulier',
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table produits
CREATE TABLE IF NOT EXISTS produits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom_article VARCHAR(255) NOT NULL,
    categorie VARCHAR(100),
    unite_mesure VARCHAR(50) DEFAULT 'kg',
    prix_unitaire DECIMAL(10, 2) NOT NULL,
    quantite_actuelle DECIMAL(10, 2) DEFAULT 0,
    seuil_alerte DECIMAL(10, 2) DEFAULT 10
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table ventes
CREATE TABLE IF NOT EXISTS ventes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT,
    date_vente DATETIME DEFAULT CURRENT_TIMESTAMP,
    montant_total DECIMAL(10, 2) NOT NULL,
    statut_paiement ENUM('paye', 'en_attente', 'annule') DEFAULT 'en_attente',
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table details_vente
CREATE TABLE IF NOT EXISTS details_vente (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vente_id INT NOT NULL,
    produit_id INT NOT NULL,
    quantite DECIMAL(10, 2) NOT NULL,
    prix_applique DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (vente_id) REFERENCES ventes(id) ON DELETE CASCADE,
    FOREIGN KEY (produit_id) REFERENCES produits(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table mouvements_stock
CREATE TABLE IF NOT EXISTS mouvements_stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produit_id INT NOT NULL,
    type_mouvement ENUM('entree', 'sortie') NOT NULL,
    quantite DECIMAL(10, 2) NOT NULL,
    motif VARCHAR(255),
    date_mouvement DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (produit_id) REFERENCES produits(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
