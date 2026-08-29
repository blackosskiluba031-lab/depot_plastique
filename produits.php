<?php
require_once 'db.php';

// Forcer l'encodage UTF-8
header('Content-Type: text/html; charset=utf-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

$message = '';
$message_type = '';

// Traitement du formulaire d'ajout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajouter') {
    try {
        $nom_article = trim($_POST['nom_article']);
        $categorie = trim($_POST['categorie']);
        $unite_mesure = trim($_POST['unite_mesure']);
        $prix_unitaire = floatval($_POST['prix_unitaire']);
        $quantite_actuelle = floatval($_POST['quantite_actuelle']);
        $seuil_alerte = floatval($_POST['seuil_alerte']);

        if (empty($nom_article)) {
            throw new Exception("Le nom de l'article est obligatoire");
        }

        if ($prix_unitaire <= 0) {
            throw new Exception("Le prix unitaire doit être positif");
        }

        $stmt = $pdo->prepare("INSERT INTO produits (nom_article, categorie, unite_mesure, prix_unitaire, quantite_actuelle, seuil_alerte) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$nom_article, $categorie, $unite_mesure, $prix_unitaire, $quantite_actuelle, $seuil_alerte]);

        $message = "Produit ajouté avec succès !";
        $message_type = "success";
    } catch (Exception $e) {
        $message = "Erreur: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Récupérer les produits
try {
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
    <title>Business Moses dépôt plastiques - Gestion Produits</title>
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
        .product-card {
            transition: transform 0.2s;
        }
        .product-card:hover {
            transform: translateY(-3px);
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
                        <a class="nav-link active" href="produits.php">
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

        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                <i class="bi bi-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i> 
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Formulaire d'ajout -->
            <div class="col-12 col-lg-5 mb-4">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Ajouter un Produit</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="ajouter">
                            
                            <div class="mb-3">
                                <label for="nom_article" class="form-label fw-bold">Nom de l'article *</label>
                                <input type="text" class="form-control form-control-lg" id="nom_article" name="nom_article" required placeholder="Ex: PET Bouteilles">
                            </div>

                            <div class="mb-3">
                                <label for="categorie" class="form-label fw-bold">Catégorie</label>
                                <input type="text" class="form-control form-control-lg" id="categorie" name="categorie" placeholder="Ex: Plastique PET">
                            </div>

                            <div class="mb-3">
                                <label for="unite_mesure" class="form-label fw-bold">Unité de mesure</label>
                                <select class="form-select form-select-lg" id="unite_mesure" name="unite_mesure">
                                    <option value="kg">Kilogramme (kg)</option>
                                    <option value="g">Gramme (g)</option>
                                    <option value="tonne">Tonne</option>
                                    <option value="unité">Unité</option>
                                    <option value="litre">Litre</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="prix_unitaire" class="form-label fw-bold">Prix unitaire (FC) *</label>
                                <input type="number" step="0.01" class="form-control form-control-lg" id="prix_unitaire" name="prix_unitaire" required min="0.01" placeholder="Ex: 1500">
                            </div>

                            <div class="mb-3">
                                <label for="quantite_actuelle" class="form-label fw-bold">Quantité initiale</label>
                                <input type="number" step="0.01" class="form-control form-control-lg" id="quantite_actuelle" name="quantite_actuelle" min="0" value="0" placeholder="Ex: 0">
                            </div>

                            <div class="mb-4">
                                <label for="seuil_alerte" class="form-label fw-bold">Seuil d'alerte</label>
                                <input type="number" step="0.01" class="form-control form-control-lg" id="seuil_alerte" name="seuil_alerte" min="0" value="10" placeholder="Ex: 10">
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="bi bi-plus-circle"></i> Ajouter le Produit
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Liste des produits -->
            <div class="col-12 col-lg-7 mb-4">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="bi bi-boxes"></i> Liste des Produits</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($produits)): ?>
                            <div class="text-center text-muted py-5">
                                <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                                <p class="mt-3">Aucun produit enregistré</p>
                                <p>Commencez par ajouter votre premier produit !</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Article</th>
                                            <th>Catégorie</th>
                                            <th>Stock</th>
                                            <th>Prix</th>
                                            <th>Seuil</th>
                                            <th>Statut</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($produits as $produit): ?>
                                            <tr class="product-card">
                                                <td class="fw-bold"><?= htmlspecialchars($produit['nom_article']) ?></td>
                                                <td><?= htmlspecialchars($produit['categorie']) ?: '-' ?></td>
                                                <td>
                                                    <span class="<?= $produit['quantite_actuelle'] <= $produit['seuil_alerte'] ? 'text-danger fw-bold' : '' ?>">
                                                        <?= number_format($produit['quantite_actuelle'], 2) ?> <?= htmlspecialchars($produit['unite_mesure']) ?>
                                                    </span>
                                                </td>
                                                <td><?= number_format($produit['prix_unitaire'], 2) ?> FC</td>
                                                <td><?= number_format($produit['seuil_alerte'], 2) ?></td>
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
                            <div class="mt-3 text-muted">
                                <small><?= count($produits) ?> produit(s) enregistré(s)</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Enregistrement du Service Worker PWA -->
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
