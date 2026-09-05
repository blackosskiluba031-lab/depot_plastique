<?php
require_once 'db.php';

// Forcer l'encodage UTF-8
header('Content-Type: text/html; charset=utf-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

// Paramètres de vue et filtres
$vue = $_GET['vue'] ?? 'journal';
$filtre_type = $_GET['type'] ?? 'tout';
$filtre_date_debut = $_GET['date_debut'] ?? '';
$filtre_date_fin = $_GET['date_fin'] ?? '';
$filtre_statut = $_GET['statut'] ?? 'tout';
$recherche_tableur = trim($_GET['q'] ?? '');

// 1. Récupération pour le Journal des opérations
$historique = [];
$lignes_tableur = [];
$total_ventes_fc = 0;
$total_benefice_net_fc = 0;
$total_dettes_fc = 0;

try {
    // Requête journal
    $sql_journal = "
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
    $stmt = $pdo->query($sql_journal);
    $historique = $stmt->fetchAll();

    // 2. Requête détaillée pour la Vue Tableur (Excel) & Bénéfices
    $t_conditions = [];
    $t_params = [];

    if ($filtre_date_debut) {
        $t_conditions[] = "v.date_vente >= ?";
        $t_params[] = $filtre_date_debut . " 00:00:00";
    }
    if ($filtre_date_fin) {
        $t_conditions[] = "v.date_vente <= ?";
        $t_params[] = $filtre_date_fin . " 23:59:59";
    }
    if ($filtre_statut !== 'tout') {
        if ($filtre_statut === 'cash') {
            $t_conditions[] = "v.statut_paiement IN ('cash', 'paye')";
        } elseif ($filtre_statut === 'credit') {
            $t_conditions[] = "v.statut_paiement IN ('credit', 'en_attente')";
        } elseif ($filtre_statut === 'partiel') {
            $t_conditions[] = "v.statut_paiement = 'partiel'";
        }
    }
    if ($recherche_tableur) {
        $t_conditions[] = "(c.nom LIKE ? OR p.nom_article LIKE ?)";
        $t_params[] = "%$recherche_tableur%";
        $t_params[] = "%$recherche_tableur%";
    }

    $t_where = !empty($t_conditions) ? "WHERE " . implode(" AND ", $t_conditions) : "";

    $sql_tableur = "
        SELECT 
            v.id as vente_id,
            v.date_vente,
            COALESCE(c.nom, 'Comptoir') as client_nom,
            p.nom_article,
            p.unite_mesure,
            dv.quantite,
            COALESCE(NULLIF(dv.prix_achat, 0), p.prix_achat, 0) as prix_achat,
            dv.prix_applique as prix_vente,
            (dv.quantite * dv.prix_applique) as total_ligne,
            ((dv.prix_applique - COALESCE(NULLIF(dv.prix_achat, 0), p.prix_achat, 0)) * dv.quantite) as benefice_net,
            v.statut_paiement,
            v.montant_total,
            v.montant_paye,
            v.reste_a_payer
        FROM details_vente dv
        JOIN ventes v ON dv.vente_id = v.id
        JOIN produits p ON dv.produit_id = p.id
        LEFT JOIN clients c ON v.client_id = c.id
        $t_where
        ORDER BY v.date_vente DESC, v.id DESC
    ";

    $stmt_t = $pdo->prepare($sql_tableur);
    $stmt_t->execute($t_params);
    $lignes_tableur = $stmt_t->fetchAll();

    // Calculs des totaux de synthèse en Franc Congolais (FC)
    $ventes_vues = [];
    foreach ($lignes_tableur as $lt) {
        $total_ventes_fc += floatval($lt['total_ligne']);
        $total_benefice_net_fc += floatval($lt['benefice_net']);
        if (!isset($ventes_vues[$lt['vente_id']])) {
            $ventes_vues[$lt['vente_id']] = true;
            $total_dettes_fc += floatval($lt['reste_a_payer']);
        }
    }

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
    <link rel="manifest" href="./manifest.json">
    <meta name="theme-color" content="#0d6efd">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Business Moses">
    <link rel="apple-touch-icon" href="./icons/icon-192x192.png">

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
        /* Styles Vue Tableur Excel */
        .excel-table {
            border: 1px solid #198754;
            font-size: 0.90rem;
        }
        .excel-table thead th {
            background-color: #107c41 !important;
            color: #ffffff !important;
            vertical-align: middle;
            text-align: center;
            padding: 10px 8px;
            font-weight: 700;
            border: 1px solid #0d6334;
        }
        .excel-table tbody td {
            vertical-align: middle;
            border: 1px solid #e0e0e0;
            padding: 8px 10px;
        }
        .excel-table tbody tr:hover {
            background-color: #f1f8f4;
        }
        .excel-table tfoot th, .excel-table tfoot td {
            background-color: #e8f5e9 !important;
            font-weight: 800;
            border-top: 2px solid #107c41;
            padding: 10px;
            font-size: 0.96rem;
        }
        .kpi-excel-card {
            border-radius: 12px;
            border-left: 5px solid #107c41;
            box-shadow: 0 2px 5px rgba(0,0,0,0.06);
            background: #ffffff;
        }
        .nav-pills .nav-link.active {
            background-color: #107c41;
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

        <!-- Onglets de sélection de vue -->
        <ul class="nav nav-pills mb-4" id="historiqueTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $vue !== 'tableur' ? 'active' : '' ?> fw-bold" id="journal-tab" data-bs-toggle="pill" data-bs-target="#journal-pane" type="button" role="tab">
                    <i class="bi bi-clock-history me-1"></i> Journal des Opérations
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $vue === 'tableur' ? 'active' : '' ?> fw-bold text-success border border-success ms-2" id="tableur-tab" data-bs-toggle="pill" data-bs-target="#tableur-pane" type="button" role="tab">
                    <i class="bi bi-file-earmark-spreadsheet-fill me-1"></i> Vue Tableur (Excel) & Bénéfices
                </button>
            </li>
        </ul>

        <div class="tab-content" id="historiqueTabsContent">
            <!-- ONGLET 1 : JOURNAL DES OPÉRATIONS (Timeline existante) -->
            <div class="tab-pane fade <?= $vue !== 'tableur' ? 'show active' : '' ?>" id="journal-pane" role="tabpanel">
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="bi bi-clock-history"></i> Flux des Mouvements & Ventes</h5>
                    </div>
                    <div class="card-body">
                        <!-- Filtres Journal -->
                        <form method="GET" action="" class="row g-3 mb-4">
                            <input type="hidden" name="vue" value="journal">
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

                        <!-- Historique Timeline -->
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

            <!-- ONGLET 2 : VUE TABLEUR (EXCEL) & BÉNÉFICES NET -->
            <div class="tab-pane fade <?= $vue === 'tableur' ? 'show active' : '' ?>" id="tableur-pane" role="tabpanel">
                <!-- Cartes KPI de synthèse -->
                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-4">
                        <div class="card kpi-excel-card p-3" style="border-left-color: #0d6efd;">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="text-muted small text-uppercase fw-bold">Total Ventes</span>
                                    <h3 class="fw-bold text-primary mb-0"><?= number_format($total_ventes_fc, 2, ',', ' ') ?> FC</h3>
                                </div>
                                <div class="bg-primary text-white rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                    <i class="bi bi-cart-check fs-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="card kpi-excel-card p-3" style="border-left-color: #107c41;">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="text-muted small text-uppercase fw-bold">Total Bénéfices Net</span>
                                    <h3 class="fw-bold text-success mb-0"><?= number_format($total_benefice_net_fc, 2, ',', ' ') ?> FC</h3>
                                </div>
                                <div class="bg-success text-white rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                    <i class="bi bi-graph-up-arrow fs-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="card kpi-excel-card p-3" style="border-left-color: #dc3545;">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="text-muted small text-uppercase fw-bold">Total Dettes Clients</span>
                                    <h3 class="fw-bold text-danger mb-0"><?= number_format($total_dettes_fc, 2, ',', ' ') ?> FC</h3>
                                </div>
                                <div class="bg-danger text-white rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                    <i class="bi bi-wallet2 fs-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="mb-0">
                            <i class="bi bi-file-earmark-spreadsheet me-2"></i>Tableur des Ventes, Prix Achat & Bénéfices Net
                        </h5>
                        <button type="button" class="btn btn-light text-success fw-bold shadow-sm" onclick="exporterTableauExcel()">
                            <i class="bi bi-download me-1"></i> Exporter en Excel / CSV
                        </button>
                    </div>
                    <div class="card-body">
                        <!-- Filtres du tableur -->
                        <form method="GET" action="" class="row g-3 mb-4 align-items-end">
                            <input type="hidden" name="vue" value="tableur">
                            <div class="col-12 col-md-3">
                                <label for="t_debut" class="form-label fw-bold small">Date début</label>
                                <input type="date" class="form-control" id="t_debut" name="date_debut" value="<?= htmlspecialchars($filtre_date_debut) ?>">
                            </div>
                            <div class="col-12 col-md-3">
                                <label for="t_fin" class="form-label fw-bold small">Date fin</label>
                                <input type="date" class="form-control" id="t_fin" name="date_fin" value="<?= htmlspecialchars($filtre_date_fin) ?>">
                            </div>
                            <div class="col-12 col-md-2">
                                <label for="t_statut" class="form-label fw-bold small">Statut Paiement</label>
                                <select class="form-select" id="t_statut" name="statut">
                                    <option value="tout" <?= $filtre_statut === 'tout' ? 'selected' : '' ?>>Tous statuts</option>
                                    <option value="cash" <?= $filtre_statut === 'cash' ? 'selected' : '' ?>>Cash / Payé</option>
                                    <option value="credit" <?= $filtre_statut === 'credit' ? 'selected' : '' ?>>Crédit</option>
                                    <option value="partiel" <?= $filtre_statut === 'partiel' ? 'selected' : '' ?>>Partiel</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-2">
                                <label for="t_q" class="form-label fw-bold small">Recherche</label>
                                <input type="text" class="form-control" id="t_q" name="q" placeholder="Client ou article..." value="<?= htmlspecialchars($recherche_tableur) ?>">
                            </div>
                            <div class="col-12 col-md-2 d-flex gap-2">
                                <button type="submit" class="btn btn-success w-100 fw-bold">
                                    <i class="bi bi-funnel"></i> Filtrer
                                </button>
                                <?php if ($filtre_date_debut || $filtre_date_fin || $filtre_statut !== 'tout' || $recherche_tableur): ?>
                                    <a href="historique.php?vue=tableur" class="btn btn-outline-secondary" title="Réinitialiser">
                                        <i class="bi bi-x-lg"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>

                        <!-- Tableau Style Excel -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle excel-table" id="tableurExcelTable">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Client</th>
                                        <th>Article</th>
                                        <th>Quantité</th>
                                        <th>Prix Achat</th>
                                        <th>Prix Vente</th>
                                        <th>Bénéfice Net</th>
                                        <th>Statut</th>
                                        <th>Montant Payé</th>
                                        <th>Reste à Payer (Dette)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($lignes_tableur)): ?>
                                        <tr>
                                            <td colspan="10" class="text-center text-muted py-5">
                                                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                                Aucune vente trouvée pour les critères sélectionnés.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($lignes_tableur as $ligne): ?>
                                            <tr>
                                                <td class="text-nowrap text-muted small">
                                                    <i class="bi bi-calendar3 me-1"></i>
                                                    <?= date('d/m/Y H:i', strtotime($ligne['date_vente'])) ?>
                                                </td>
                                                <td class="fw-bold"><?= htmlspecialchars($ligne['client_nom']) ?></td>
                                                <td><?= htmlspecialchars($ligne['nom_article']) ?></td>
                                                <td class="text-end fw-semibold">
                                                    <?= number_format($ligne['quantite'], 2) ?> <?= htmlspecialchars($ligne['unite_mesure']) ?>
                                                </td>
                                                <td class="text-end text-muted">
                                                    <?= number_format($ligne['prix_achat'], 2, ',', ' ') ?> FC
                                                </td>
                                                <td class="text-end fw-bold">
                                                    <?= number_format($ligne['prix_vente'], 2, ',', ' ') ?> FC
                                                </td>
                                                <td class="text-end text-success fw-bold bg-success-subtle">
                                                    +<?= number_format($ligne['benefice_net'], 2, ',', ' ') ?> FC
                                                </td>
                                                <td class="text-center">
                                                    <?php
                                                        $st = $ligne['statut_paiement'];
                                                        if ($st === 'cash' || $st === 'paye') {
                                                            echo '<span class="badge bg-success">Cash</span>';
                                                        } elseif ($st === 'credit' || $st === 'en_attente') {
                                                            echo '<span class="badge bg-danger">Crédit</span>';
                                                        } elseif ($st === 'partiel') {
                                                            echo '<span class="badge bg-warning text-dark">Partiel</span>';
                                                        } else {
                                                            echo '<span class="badge bg-secondary">' . htmlspecialchars(ucfirst($st)) . '</span>';
                                                        }
                                                    ?>
                                                </td>
                                                <td class="text-end text-success fw-bold">
                                                    <?= number_format($ligne['montant_paye'] ?? $ligne['total_ligne'], 2, ',', ' ') ?> FC
                                                </td>
                                                <td class="text-end">
                                                    <?php if (($ligne['reste_a_payer'] ?? 0) > 0): ?>
                                                        <span class="text-danger fw-bold">
                                                            <?= number_format($ligne['reste_a_payer'], 2, ',', ' ') ?> FC
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">0,00 FC</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="3" class="text-uppercase text-dark">
                                            <i class="bi bi-calculator me-1"></i> Résumé Général
                                        </th>
                                        <th colspan="2" class="text-end text-muted small">Total Ventes :</th>
                                        <th class="text-end text-primary fs-6">
                                            <?= number_format($total_ventes_fc, 2, ',', ' ') ?> FC
                                        </th>
                                        <th class="text-end text-success fs-6">
                                            <?= number_format($total_benefice_net_fc, 2, ',', ' ') ?> FC
                                        </th>
                                        <th class="text-end text-muted small">Dettes :</th>
                                        <th class="text-end text-success">
                                            <?= number_format(max(0, $total_ventes_fc - $total_dettes_fc), 2, ',', ' ') ?> FC
                                        </th>
                                        <th class="text-end text-danger fs-6">
                                            <?= number_format($total_dettes_fc, 2, ',', ' ') ?> FC
                                        </th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div class="mt-3 text-muted d-flex justify-content-between align-items-center flex-wrap">
                            <small><?= count($lignes_tableur) ?> ligne(s) d'articles affichée(s)</small>
                            <small class="text-success"><i class="bi bi-check-circle me-1"></i>Tous les montants sont calculés en Francs Congolais (FC)</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Script Export CSV / Excel -->
    <script>
        const tableurDonnees = <?= json_encode($lignes_tableur) ?>;

        function exporterTableauExcel() {
            if (!tableurDonnees || tableurDonnees.length === 0) {
                alert("Aucune donnée à exporter.");
                return;
            }

            // En-têtes CSV
            let csv = "\uFEFF"; // BOM UTF-8 pour ouverture correcte dans Excel
            csv += "Date;Client;Article;Quantité;Unité;Prix Achat (FC);Prix Vente (FC);Total Vente (FC);Bénéfice Net (FC);Statut;Montant Payé (FC);Reste à Payer Dette (FC)\r\n";

            tableurDonnees.forEach(row => {
                const dateClean = row.date_vente;
                const client = (row.client_nom || '').replace(/;/g, ' ');
                const article = (row.nom_article || '').replace(/;/g, ' ');
                const qte = parseFloat(row.quantite || 0).toFixed(2);
                const unite = row.unite_mesure || '';
                const prixAchat = parseFloat(row.prix_achat || 0).toFixed(2);
                const prixVente = parseFloat(row.prix_vente || 0).toFixed(2);
                const totalLigne = parseFloat(row.total_ligne || 0).toFixed(2);
                const beneficeNet = parseFloat(row.benefice_net || 0).toFixed(2);
                const statut = (row.statut_paiement || '').toUpperCase();
                const montantPaye = parseFloat(row.montant_paye || 0).toFixed(2);
                const restePayer = parseFloat(row.reste_a_payer || 0).toFixed(2);

                csv += `${dateClean};${client};${article};${qte};${unite};${prixAchat};${prixVente};${totalLigne};${beneficeNet};${statut};${montantPaye};${restePayer}\r\n`;
            });

            // Ligne de résumé
            const totVentes = <?= floatval($total_ventes_fc) ?>.toFixed(2);
            const totBenefices = <?= floatval($total_benefice_net_fc) ?>.toFixed(2);
            const totDettes = <?= floatval($total_dettes_fc) ?>.toFixed(2);
            csv += `TOTAL GÉNÉRAL;;;;;;;${totVentes};${totBenefices};;${(totVentes - totDettes).toFixed(2)};${totDettes}\r\n`;

            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement("a");
            const url = URL.createObjectURL(blob);
            link.setAttribute("href", url);
            link.setAttribute("download", "business_moses_ventes_benefices_" + new Date().toISOString().slice(0, 10) + ".csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>

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
