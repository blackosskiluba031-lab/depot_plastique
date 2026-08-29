<?php
require_once 'db.php';

// Forcer l'encodage UTF-8
header('Content-Type: text/html; charset=utf-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

// Filtres
$filtre_type = $_GET['type'] ?? 'tout';
$filtre_date_debut = $_GET['date_debut'] ?? '';
$filtre_date_fin = $_GET['date_fin'] ?? '';

// Récupérer l'historique
try {
    $where_conditions = [];
    $params = [];

    if ($filtre_type !== 'tout') {
        if ($filtre_type === 'mouvements') {
            $where_conditions[] = "type = 'mouvement'";
        } elseif ($filtre_type === 'ventes') {
            $where_conditions[] = "type = 'vente'";
        }
    }

    if ($filtre_date_debut) {
        $where_conditions[] = "date >= ?";
        $params[] = $filtre_date_debut;
    }

    if ($filtre_date_fin) {
        $where_conditions[] = "date <= ?";
        $params[] = $filtre_date_fin;
    }

    $where_clause = '';
    if (!empty($where_conditions)) {
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    }

    // Requête combinée pour mouvements et ventes
    $sql = "
        SELECT 
            'mouvement' as type,
            ms.id,
            ms.date_mouvement as date,
            p.nom_article as produit_nom,
            ms.type_mouvement as sous_type,
            ms.quantite,
            ms.motif,
            NULL as client_nom,
            NULL as montant_total
        FROM mouvements_stock ms
        JOIN produits p ON ms.produit_id = p.id
        
        UNION ALL
        
        SELECT 
            'vente' as type,
            v.id,
            v.date_vente as date,
            GROUP_CONCAT(p.nom_article SEPARATOR ', ') as produit_nom,
            'vente' as sous_type,
            SUM(dv.quantite) as quantite,
            CONCAT('Vente #', v.id) as motif,
            c.nom as client_nom,
            v.montant_total
        FROM ventes v
        LEFT JOIN clients c ON v.client_id = c.id
        JOIN details_vente dv ON v.id = dv.vente_id
        JOIN produits p ON dv.produit_id = p.id
        GROUP BY v.id, v.date_vente, v.montant_total, c.nom
        
        ORDER BY date DESC
        LIMIT 100
    ";

    $stmt = $pdo->query($sql);
    $historique = $stmt->fetchAll();

} catch (PDOException $e) {
    $erreur = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Moses dépôt plastiques - Historique</title>
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
        .badge-mouvement-entree {
            background-color: #28a745;
        }
        .badge-mouvement-sortie {
            background-color: #dc3545;
        }
        .badge-vente {
            background-color: #007bff;
        }
        .historique-item {
            border-left: 4px solid #dee2e6;
            padding: 15px;
            margin-bottom: 10px;
            background-color: #fff;
            border-radius: 8px;
            transition: all 0.2s;
        }
        .historique-item:hover {
            transform: translateX(5px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .historique-item.mouvement-entree {
            border-left-color: #28a745;
        }
        .historique-item.mouvement-sortie {
            border-left-color: #dc3545;
        }
        .historique-item.vente {
            border-left-color: #007bff;
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
                        <a class="nav-link active" href="historique.php">
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

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="bi bi-clock-history"></i> Historique des Opérations</h5>
                    </div>
                    <div class="card-body">
                        <!-- Filtres -->
                        <form method="GET" action="" class="row g-3 mb-4">
                            <div class="col-md-3">
                                <label for="type" class="form-label fw-bold">Type d'opération</label>
                                <select class="form-select" id="type" name="type">
                                    <option value="tout" <?= $filtre_type === 'tout' ? 'selected' : '' ?>>Tout</option>
                                    <option value="mouvements" <?= $filtre_type === 'mouvements' ? 'selected' : '' ?>>Mouvements de stock</option>
                                    <option value="ventes" <?= $filtre_type === 'ventes' ? 'selected' : '' ?>>Ventes</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="date_debut" class="form-label fw-bold">Date début</label>
                                <input type="date" class="form-control" id="date_debut" name="date_debut" value="<?= htmlspecialchars($filtre_date_debut) ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="date_fin" class="form-label fw-bold">Date fin</label>
                                <input type="date" class="form-control" id="date_fin" name="date_fin" value="<?= htmlspecialchars($filtre_date_fin) ?>">
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-funnel"></i> Filtrer
                                </button>
                            </div>
                        </form>

                        <!-- Historique -->
                        <?php if (empty($historique)): ?>
                            <div class="text-center text-muted py-5">
                                <i class="bi bi-inbox" style="font-size: 4rem;"></i>
                                <h4 class="mt-3">Aucune opération enregistrée</h4>
                                <p class="mt-2">L'historique des opérations apparaîtra ici une fois que vous commencerez à utiliser l'application.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($historique as $item): ?>
                                <?php 
                                $item_class = '';
                                if ($item['type'] === 'mouvement') {
                                    $item_class = $item['sous_type'] === 'entree' ? 'mouvement-entree' : 'mouvement-sortie';
                                } else {
                                    $item_class = 'vente';
                                }
                                ?>
                                <div class="historique-item <?= $item_class ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <div class="d-flex align-items-center mb-2">
                                                <?php if ($item['type'] === 'mouvement'): ?>
                                                    <span class="badge badge-<?= $item_class ?> me-2">
                                                        <?= $item['sous_type'] === 'entree' ? 'Entrée' : 'Sortie' ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge badge-vente me-2">Vente</span>
                                                <?php endif; ?>
                                                
                                                <strong><?= htmlspecialchars($item['produit_nom']) ?></strong>
                                            </div>
                                            
                                            <p class="mb-1 text-muted">
                                                <small>
                                                    <i class="bi bi-calendar"></i> <?= date('d/m/Y H:i', strtotime($item['date'])) ?>
                                                    <?php if ($item['client_nom']): ?>
                                                        | <i class="bi bi-person"></i> <?= htmlspecialchars($item['client_nom']) ?>
                                                    <?php endif; ?>
                                                </small>
                                            </p>
                                            
                                            <p class="mb-0">
                                                <small>
                                                    <i class="bi bi-info-circle"></i> <?= htmlspecialchars($item['motif']) ?>
                                                </small>
                                            </p>
                                        </div>
                                        
                                        <div class="text-end">
                                            <?php if ($item['type'] === 'mouvement'): ?>
                                                <div class="fw-bold"><?= number_format($item['quantite'], 2) ?></div>
                                                <small class="text-muted">unités</small>
                                            <?php else: ?>
                                                <div class="fw-bold"><?= number_format($item['montant_total'], 2) ?> FC</div>
                                                <small class="text-muted"><?= number_format($item['quantite'], 2) ?> articles</small>
                                                <div class="mt-2">
                                                    <a href="facture.php?id=<?= $item['id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary py-1 px-2" style="font-size: 0.8rem;">
                                                        <i class="bi bi-printer"></i> Facture
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <div class="mt-3 text-muted">
                                <small><?= count($historique) ?> opération(s) affichée(s)</small>
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
