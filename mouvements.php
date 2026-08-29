<?php
require_once 'db.php';

// Forcer l'encodage UTF-8
header('Content-Type: text/html; charset=utf-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

$message = '';
$message_type = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $produit_id = $_POST['produit_id'];
        $type_mouvement = $_POST['type_mouvement'];
        $quantite = floatval($_POST['quantite']);
        $motif = $_POST['motif'];

        if ($quantite <= 0) {
            throw new Exception("La quantité doit être positive");
        }

        $pdo->beginTransaction();

        // Vérifier le stock disponible pour les sorties
        if ($type_mouvement === 'sortie') {
            $stmt = $pdo->prepare("SELECT quantite_actuelle FROM produits WHERE id = ?");
            $stmt->execute([$produit_id]);
            $stock_actuel = $stmt->fetch()['quantite_actuelle'];
            
            if ($stock_actuel < $quantite) {
                throw new Exception("Stock insuffisant. Disponible: $stock_actuel, Demandé: $quantite");
            }
        }

        // Insérer le mouvement
        $stmt = $pdo->prepare("INSERT INTO mouvements_stock (produit_id, type_mouvement, quantite, motif) VALUES (?, ?, ?, ?)");
        $stmt->execute([$produit_id, $type_mouvement, $quantite, $motif]);

        // Mettre à jour la quantité actuelle
        if ($type_mouvement === 'entree') {
            $stmt = $pdo->prepare("UPDATE produits SET quantite_actuelle = quantite_actuelle + ? WHERE id = ?");
        } else {
            $stmt = $pdo->prepare("UPDATE produits SET quantite_actuelle = quantite_actuelle - ? WHERE id = ?");
        }
        $stmt->execute([$quantite, $produit_id]);

        $pdo->commit();
        $message = "Mouvement enregistré avec succès !";
        $message_type = "success";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Erreur: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Initialisation des variables
$produits = [];
$mouvements_recents = [];
$erreur = '';

// Récupérer les produits et les mouvements récents
try {
    $stmt = $pdo->query("SELECT * FROM produits ORDER BY nom_article ASC");
    $produits = $stmt->fetchAll();

    $stmt = $pdo->query("
        SELECT ms.*, p.nom_article 
        FROM mouvements_stock ms 
        JOIN produits p ON ms.produit_id = p.id 
        ORDER BY ms.date_mouvement DESC 
        LIMIT 20
    ");
    $mouvements_recents = $stmt->fetchAll();
} catch (PDOException $e) {
    $erreur = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Moses dépôt plastiques - Mouvements de Stock</title>
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
        .card-header {
            border-radius: 15px 15px 0 0 !important;
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .btn-lg {
            padding: 15px 30px;
            font-size: 1.2rem;
        }
        .form-control, .form-select {
            padding: 12px;
            font-size: 1.1rem;
        }
        .badge-entree {
            background-color: #28a745;
        }
        .badge-sortie {
            background-color: #dc3545;
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
                        <a class="nav-link" href="index.php">
                            <i class="bi bi-house"></i> Accueil
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="produits.php">
                            <i class="bi bi-box"></i> Produits
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="mouvements.php">
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
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle"></i> 
                <strong>Aucun produit disponible</strong> Vous devez d'abord ajouter des produits avant de pouvoir gérer les mouvements de stock.
                <a href="produits.php" class="btn btn-sm btn-primary ms-2">Ajouter des produits</a>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                <i class="bi bi-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i> 
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Formulaire de mouvement -->
            <div class="col-12 col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Nouveau Mouvement</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($produits)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-box-seam" style="font-size: 3rem;"></i>
                                <p class="mt-3">Aucun produit disponible</p>
                                <a href="produits.php" class="btn btn-primary">
                                    <i class="bi bi-plus-circle"></i> Ajouter des produits
                                </a>
                            </div>
                        <?php else: ?>
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="type_mouvement" class="form-label fw-bold">Type de Mouvement</label>
                                    <select class="form-select form-select-lg" id="type_mouvement" name="type_mouvement" required>
                                        <option value="">-- Sélectionner --</option>
                                        <option value="entree">Entrée (Achat/Réception)</option>
                                        <option value="sortie">Sortie (Vente/Utilisation)</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="produit_id" class="form-label fw-bold">Produit</label>
                                    <select class="form-select form-select-lg" id="produit_id" name="produit_id" required>
                                        <option value="">-- Sélectionner un produit --</option>
                                        <?php foreach ($produits as $produit): ?>
                                            <option value="<?= $produit['id'] ?>">
                                                <?= htmlspecialchars($produit['nom_article']) ?> 
                                                (Stock: <?= number_format($produit['quantite_actuelle'], 2) ?> <?= htmlspecialchars($produit['unite_mesure']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="quantite" class="form-label fw-bold">Quantité</label>
                                    <input type="number" step="0.01" class="form-control form-control-lg" id="quantite" name="quantite" required min="0.01" placeholder="Ex: 50.5">
                                </div>

                                <div class="mb-4">
                                    <label for="motif" class="form-label fw-bold">Motif</label>
                                    <textarea class="form-control" id="motif" name="motif" rows="3" placeholder="Description du mouvement..."></textarea>
                                </div>

                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="bi bi-save"></i> Enregistrer le Mouvement
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Mouvements récents -->
            <div class="col-12 col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="bi bi-clock-history"></i> Mouvements Récents</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Produit</th>
                                        <th>Type</th>
                                        <th>Quantité</th>
                                        <th>Motif</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($mouvements_recents as $mouvement): ?>
                                        <tr>
                                            <td><?= date('d/m/Y H:i', strtotime($mouvement['date_mouvement'])) ?></td>
                                            <td><?= htmlspecialchars($mouvement['nom_article']) ?></td>
                                            <td>
                                                <span class="badge <?= $mouvement['type_mouvement'] === 'entree' ? 'badge-entree' : 'badge-sortie' ?>">
                                                    <?= $mouvement['type_mouvement'] === 'entree' ? 'Entrée' : 'Sortie' ?>
                                                </span>
                                            </td>
                                            <td><?= number_format($mouvement['quantite'], 2) ?></td>
                                            <td><?= htmlspecialchars(substr($mouvement['motif'], 0, 20)) ?><?= strlen($mouvement['motif']) > 20 ? '...' : '' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
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
