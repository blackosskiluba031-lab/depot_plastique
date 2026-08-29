<?php
require_once 'db.php';

// Forcer l'encodage UTF-8
header('Content-Type: text/html; charset=utf-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

$message = '';
$message_type = '';
$recherche = '';

// Traitement du formulaire d'ajout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajouter') {
    try {
        $nom = trim($_POST['nom']);
        $telephone = trim($_POST['telephone']);
        $type_client = $_POST['type_client'];

        if (empty($nom)) {
            throw new Exception("Le nom du client est obligatoire");
        }

        $stmt = $pdo->prepare("INSERT INTO clients (nom, telephone, type_client) VALUES (?, ?, ?)");
        $stmt->execute([$nom, $telephone, $type_client]);

        $message = "Client ajouté avec succès !";
        $message_type = "success";
    } catch (Exception $e) {
        $message = "Erreur: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Recherche
if (isset($_GET['recherche'])) {
    $recherche = trim($_GET['recherche']);
}

// Initialisation des variables
$clients = [];
$erreur = '';

// Récupérer les clients
try {
    if ($recherche) {
        $stmt = $pdo->prepare("SELECT * FROM clients WHERE nom LIKE ? OR telephone LIKE ? ORDER BY nom ASC");
        $term = "%$recherche%";
        $stmt->execute([$term, $term]);
    } else {
        $stmt = $pdo->query("SELECT * FROM clients ORDER BY nom ASC");
    }
    $clients = $stmt->fetchAll();
} catch (PDOException $e) {
    $erreur = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Moses dépôt plastiques - Annuaire Clients</title>
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
        .client-card {
            transition: transform 0.2s;
        }
        .client-card:hover {
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
                        <a class="nav-link" href="mouvements.php">
                            <i class="bi bi-arrow-left-right"></i> Mouvements
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="clients.php">
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
                        <h5 class="mb-0"><i class="bi bi-person-plus"></i> Ajouter un Client</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="ajouter">
                            
                            <div class="mb-3">
                                <label for="nom" class="form-label fw-bold">Nom du Client *</label>
                                <input type="text" class="form-control form-control-lg" id="nom" name="nom" required placeholder="Nom complet ou raison sociale">
                            </div>

                            <div class="mb-3">
                                <label for="telephone" class="form-label fw-bold">Téléphone</label>
                                <input type="tel" class="form-control form-control-lg" id="telephone" name="telephone" placeholder="Ex: 0612345678">
                            </div>

                            <div class="mb-4">
                                <label for="type_client" class="form-label fw-bold">Type de Client</label>
                                <select class="form-select form-select-lg" id="type_client" name="type_client">
                                    <option value="Particulier">Particulier</option>
                                    <option value="Grossiste">Grossiste</option>
                                    <option value="Recycleur">Recycleur</option>
                                </select>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="bi bi-plus-circle"></i> Ajouter le Client
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Liste des clients -->
            <div class="col-12 col-lg-7 mb-4">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="bi bi-people"></i> Annuaire Clients</h5>
                    </div>
                    <div class="card-body">
                        <!-- Formulaire de recherche -->
                        <form method="GET" action="" class="mb-3">
                            <div class="input-group input-group-lg">
                                <input type="text" class="form-control" name="recherche" placeholder="Rechercher un client..." value="<?= htmlspecialchars($recherche) ?>">
                                <button class="btn btn-outline-secondary" type="submit">
                                    <i class="bi bi-search"></i>
                                </button>
                                <?php if ($recherche): ?>
                                    <a href="clients.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-x"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Nom</th>
                                        <th>Téléphone</th>
                                        <th>Type</th>
                                        <th>Date Création</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($clients)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">
                                                <i class="bi bi-inbox"></i> Aucun client trouvé
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($clients as $client): ?>
                                            <tr class="client-card">
                                                <td class="fw-bold"><?= htmlspecialchars($client['nom']) ?></td>
                                                <td><?= htmlspecialchars($client['telephone']) ?: '-' ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $client['type_client'] === 'Grossiste' ? 'primary' : ($client['type_client'] === 'Recycleur' ? 'warning' : 'secondary') ?>">
                                                        <?= htmlspecialchars($client['type_client']) ?>
                                                    </span>
                                                </td>
                                                <td><?= date('d/m/Y', strtotime($client['date_creation'])) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3 text-muted">
                            <small><?= count($clients) ?> client(s) affiché(s)</small>
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
