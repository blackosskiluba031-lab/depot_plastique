<?php
require_once 'db.php';

// Forcer l'encodage UTF-8
header('Content-Type: text/html; charset=utf-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

// Initialisation des variables
$valeur_totale = 0;
$alertes_stock = [];
$produits = [];
$erreur = '';

// Récupérer les statistiques
try {
    // Valeur totale du stock
    $stmt = $pdo->query("SELECT SUM(quantite_actuelle * prix_unitaire) as valeur_totale FROM produits");
    $valeur_totale = $stmt->fetch()['valeur_totale'] ?? 0;

    // Alertes de stock faible
    $stmt = $pdo->query("SELECT * FROM produits WHERE quantite_actuelle <= seuil_alerte ORDER BY quantite_actuelle ASC");
    $alertes_stock = $stmt->fetchAll();

    // Liste des produits
    $stmt = $pdo->query("SELECT * FROM produits ORDER BY nom_article ASC");
    $produits = $stmt->fetchAll();
} catch (PDOException $e) {
    $erreur = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Moses dépôt plastiques - Tableau de Bord</title>
    <!-- Configuration PWA -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#0d6efd">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Business Moses">
    <link rel="apple-touch-icon" href="icons/icon-192x192.png">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .card-stats {
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .stat-icon {
            font-size: 2.5rem;
        }
        .alert-card {
            border-left: 4px solid #dc3545;
        }
        .product-card {
            transition: transform 0.2s;
        }
        .product-card:hover {
            transform: translateY(-5px);
        }
        .navbar-brand {
            font-weight: bold;
        }
        .nav-link {
            font-size: 1.1rem;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-box-seam"></i> Business Moses dépôt plastiques
            </a>
            <button class="btn btn-sm btn-warning ms-2 pwa-install-btn align-items-center" style="display: none;" onclick="installerPWA()">
                <i class="bi bi-download me-1"></i> Installer
            </button>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">
                            <i class="bi bi-house"></i> Accueil
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="produits.php">
                            <i class="bi bi-box"></i> Produits
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="mouvements.php">
                            <i class="bi bi-arrow-left-right"></i> Mouvements
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="clients.php">
                            <i class="bi bi-people"></i> Clients
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="ventes.php">
                            <i class="bi bi-cash"></i> Ventes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="historique.php">
                            <i class="bi bi-clock-history"></i> Historique
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4 mb-5">
        <?php if (!empty($erreur)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle"></i> Erreur: <?= htmlspecialchars($erreur) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (empty($produits)): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="bi bi-info-circle"></i> 
                <strong>Bienvenue !</strong> Pour commencer, ajoutez vos premiers produits dans la base de données.
                <a href="produits.php" class="btn btn-sm btn-primary ms-2">Ajouter des produits</a>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistiques -->
        <div class="row mb-4">
            <div class="col-12 col-md-6 mb-3">
                <div class="card card-stats bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <i class="bi bi-cash-coin stat-icon"></i>
                            </div>
                            <div>
                                <h6 class="card-subtitle mb-1">Valeur Totale du Stock</h6>
                                <h3 class="card-title mb-0"><?= number_format($valeur_totale, 2, ',', ' ') ?> FC</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 mb-3">
                <div class="card card-stats bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <i class="bi bi-box stat-icon"></i>
                            </div>
                            <div>
                                <h6 class="card-subtitle mb-1">Total Produits</h6>
                                <h3 class="card-title mb-0"><?= count($produits) ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alertes de stock -->
        <?php if (!empty($alertes_stock)): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card alert-card">
                        <div class="card-header bg-danger text-white">
                            <i class="bi bi-exclamation-triangle"></i> Alertes Stock Faible
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Produit</th>
                                            <th>Quantité Actuelle</th>
                                            <th>Seuil Alerte</th>
                                            <th>Statut</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($alertes_stock as $alerte): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($alerte['nom_article']) ?></td>
                                                <td class="text-danger fw-bold"><?= number_format($alerte['quantite_actuelle'], 2) ?> <?= htmlspecialchars($alerte['unite_mesure']) ?></td>
                                                <td><?= number_format($alerte['seuil_alerte'], 2) ?> <?= htmlspecialchars($alerte['unite_mesure']) ?></td>
                                                <td><span class="badge bg-danger">Stock Critique</span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Liste des produits -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-boxes"></i> Liste des Produits Plastiques</h5>
                        <button class="btn btn-sm btn-light" onclick="window.location.href='produits.php'">
                            <i class="bi bi-plus-circle"></i> Ajouter Produit
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (empty($produits)): ?>
                            <div class="text-center text-muted py-5">
                                <i class="bi bi-box-seam" style="font-size: 4rem;"></i>
                                <h4 class="mt-3">Aucun produit enregistré</h4>
                                <p class="mt-2">Commencez par ajouter vos premiers produits pour gérer votre dépôt plastique.</p>
                                <a href="produits.php" class="btn btn-primary btn-lg">
                                    <i class="bi bi-plus-circle"></i> Ajouter mon premier produit
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Article</th>
                                            <th>Catégorie</th>
                                            <th>Quantité</th>
                                            <th>Prix Unitaire</th>
                                            <th>Valeur</th>
                                            <th>Statut</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($produits as $produit): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($produit['nom_article']) ?></td>
                                                <td><?= htmlspecialchars($produit['categorie']) ?></td>
                                                <td>
                                                    <span class="<?= $produit['quantite_actuelle'] <= $produit['seuil_alerte'] ? 'text-danger fw-bold' : '' ?>">
                                                        <?= number_format($produit['quantite_actuelle'], 2) ?> <?= htmlspecialchars($produit['unite_mesure']) ?>
                                                    </span>
                                                </td>
                                                <td><?= number_format($produit['prix_unitaire'], 2) ?> FC</td>
                                                <td><?= number_format($produit['quantite_actuelle'] * $produit['prix_unitaire'], 2) ?> FC</td>
                                                <td>
                                                    <?php if ($produit['quantite_actuelle'] <= $produit['seuil_alerte']): ?>
                                                        <span class="badge bg-danger">Faible</span>
                                                    <?php elseif ($produit['quantite_actuelle'] <= $produit['seuil_alerte'] * 2): ?>
                                                        <span class="badge bg-warning">Modéré</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">OK</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Enregistrement du Service Worker PWA -->
    <script src="pwa-install.js"></script>
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('service-worker.js')
                    .then(function(reg) {
                        console.log('Service Worker enregistré avec succès (Scope: ' + reg.scope + ')');
                    })
                    .catch(function(err) {
                        console.log('Erreur Service Worker:', err);
                    });
            });
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
