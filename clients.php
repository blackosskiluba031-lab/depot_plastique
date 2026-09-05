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

// Traitement de la suppression
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'supprimer') {
    try {
        $id = intval($_POST['client_id']);
        if ($id <= 0) throw new Exception("Identifiant invalide.");

        // Vérifier si le client a des ventes associées
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM ventes WHERE client_id = ?");
        $stmt->execute([$id]);
        $nb_ventes = $stmt->fetchColumn();

        if ($nb_ventes > 0) {
            throw new Exception("Impossible de supprimer ce client car il est lié à $nb_ventes vente(s). Supprimez d'abord ses ventes ou dissociez-le.");
        }

        $stmt = $pdo->prepare("DELETE FROM clients WHERE id = ?");
        $stmt->execute([$id]);

        $message = "Client supprimé avec succès !";
        $message_type = "success";
    } catch (Exception $e) {
        $message = "Erreur: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Traitement du remboursement d'une dette
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'rembourser') {
    try {
        $client_id = intval($_POST['client_id']);
        $montant = floatval($_POST['montant_remboursement']);
        $mode_paiement = trim($_POST['mode_paiement'] ?? 'Cash');
        $notes = trim($_POST['notes'] ?? '');

        if ($client_id <= 0) throw new Exception("Client invalide.");
        if ($montant <= 0) throw new Exception("Le montant du remboursement doit être supérieur à 0 FC.");

        // Récupérer la dette en cours
        $stmt_check = $pdo->prepare("SELECT COALESCE(SUM(reste_a_payer), 0) FROM ventes WHERE client_id = ? AND reste_a_payer > 0 AND statut_paiement != 'annule'");
        $stmt_check->execute([$client_id]);
        $dette_actuelle = floatval($stmt_check->fetchColumn() ?: 0);

        if ($dette_actuelle <= 0) {
            throw new Exception("Ce client n'a aucune dette impayée.");
        }

        $pdo->beginTransaction();

        // Enregistrer le règlement
        $stmt_pay = $pdo->prepare("INSERT INTO paiements_clients (client_id, montant, mode_paiement, notes) VALUES (?, ?, ?, ?)");
        $stmt_pay->execute([$client_id, $montant, $mode_paiement, $notes]);

        // Déduire le paiement des ventes à crédit du client (de la plus ancienne à la plus récente)
        $stmt_ventes = $pdo->prepare("
            SELECT id, montant_total, montant_paye, reste_a_payer 
            FROM ventes 
            WHERE client_id = ? AND reste_a_payer > 0 AND statut_paiement != 'annule' 
            ORDER BY date_vente ASC, id ASC
        ");
        $stmt_ventes->execute([$client_id]);
        $ventes_dettes = $stmt_ventes->fetchAll();

        $montant_restant_a_allouer = $montant;

        foreach ($ventes_dettes as $vd) {
            if ($montant_restant_a_allouer <= 0) break;

            $dette_vente = floatval($vd['reste_a_payer']);
            $alloc = min($montant_restant_a_allouer, $dette_vente);

            $nouveau_paye = floatval($vd['montant_paye']) + $alloc;
            $nouveau_reste = max(0, $dette_vente - $alloc);
            $nouveau_statut = ($nouveau_reste <= 0.001) ? 'cash' : 'partiel';

            $stmt_up = $pdo->prepare("UPDATE ventes SET montant_paye = ?, reste_a_payer = ?, statut_paiement = ? WHERE id = ?");
            $stmt_up->execute([$nouveau_paye, $nouveau_reste, $nouveau_statut, $vd['id']]);

            $montant_restant_a_allouer -= $alloc;
        }

        $pdo->commit();

        $message = "Remboursement de " . number_format($montant, 2, ',', ' ') . " FC enregistré avec succès ! Le solde de la dette a été mis à jour.";
        $message_type = "success";

    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
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
$paiements_recents = [];
$erreur = '';
$total_dette_globale = 0;
$nb_clients_endettes = 0;

// Récupérer les clients avec leur dette totale
try {
    if ($recherche) {
        $stmt = $pdo->prepare("
            SELECT c.*, 
                   COALESCE(SUM(CASE WHEN v.statut_paiement != 'annule' THEN v.reste_a_payer ELSE 0 END), 0) AS dette_totale,
                   COALESCE(SUM(CASE WHEN v.statut_paiement != 'annule' THEN v.montant_total ELSE 0 END), 0) AS total_achats,
                   COUNT(v.id) AS nb_ventes
            FROM clients c
            LEFT JOIN ventes v ON c.id = v.client_id
            WHERE c.nom LIKE ? OR c.telephone LIKE ?
            GROUP BY c.id
            ORDER BY dette_totale DESC, c.nom ASC
        ");
        $term = "%$recherche%";
        $stmt->execute([$term, $term]);
    } else {
        $stmt = $pdo->query("
            SELECT c.*, 
                   COALESCE(SUM(CASE WHEN v.statut_paiement != 'annule' THEN v.reste_a_payer ELSE 0 END), 0) AS dette_totale,
                   COALESCE(SUM(CASE WHEN v.statut_paiement != 'annule' THEN v.montant_total ELSE 0 END), 0) AS total_achats,
                   COUNT(v.id) AS nb_ventes
            FROM clients c
            LEFT JOIN ventes v ON c.id = v.client_id
            GROUP BY c.id
            ORDER BY dette_totale DESC, c.nom ASC
        ");
    }
    $clients = $stmt->fetchAll();

    foreach ($clients as $c) {
        if ($c['dette_totale'] > 0) {
            $nb_clients_endettes++;
            $total_dette_globale += $c['dette_totale'];
        }
    }

    // Récupérer l'historique des paiements de dettes
    $stmt_p = $pdo->query("
        SELECT pc.*, c.nom as client_nom 
        FROM paiements_clients pc
        JOIN clients c ON pc.client_id = c.id
        ORDER BY pc.date_paiement DESC
        LIMIT 100
    ");
    $paiements_recents = $stmt_p->fetchAll();

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
        .btn-delete {
            opacity: 0.7;
            transition: opacity 0.2s, transform 0.2s;
        }
        .btn-delete:hover {
            opacity: 1;
            transform: scale(1.1);
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
                        <!-- Résumé rapide des dettes -->
                        <div class="row g-2 mb-3">
                            <div class="col-4">
                                <div class="p-2 border rounded bg-light text-center">
                                    <small class="text-muted d-block">Clients</small>
                                    <strong class="fs-5"><?= count($clients) ?></strong>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-2 border rounded bg-light text-center">
                                    <small class="text-muted d-block">Avec dettes</small>
                                    <strong class="fs-5 text-warning"><?= $nb_clients_endettes ?></strong>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-2 border rounded bg-light text-center">
                                    <small class="text-muted d-block">Total Dettes</small>
                                    <strong class="fs-6 text-danger"><?= number_format($total_dette_globale, 2) ?> FC</strong>
                                </div>
                            </div>
                        </div>

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
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Nom</th>
                                        <th>Téléphone</th>
                                        <th>Type</th>
                                        <th>Dette Actuelle</th>
                                        <th>Date</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($clients)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">
                                                <i class="bi bi-inbox fs-3 d-block mb-2"></i> Aucun client trouvé
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($clients as $client): ?>
                                            <tr class="client-card">
                                                <td class="fw-bold"><?= htmlspecialchars($client['nom']) ?></td>
                                                <td><?= htmlspecialchars($client['telephone']) ?: '-' ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $client['type_client'] === 'Grossiste' ? 'primary' : ($client['type_client'] === 'Recycleur' ? 'warning text-dark' : 'secondary') ?>">
                                                        <?= htmlspecialchars($client['type_client']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($client['dette_totale'] > 0): ?>
                                                        <span class="badge bg-danger fs-6">
                                                            <i class="bi bi-exclamation-circle me-1"></i><?= number_format($client['dette_totale'], 2) ?> FC
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">
                                                            <i class="bi bi-check2-circle me-1"></i>À jour (0 FC)
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= date('d/m/Y', strtotime($client['date_creation'])) ?></td>
                                                <td class="text-end text-nowrap">
                                                    <?php if ($client['dette_totale'] > 0): ?>
                                                        <button type="button"
                                                            class="btn btn-sm btn-success me-1"
                                                            title="Enregistrer un remboursement"
                                                            onclick="ouvrirRemboursement(<?= $client['id'] ?>, '<?= htmlspecialchars(addslashes($client['nom'])) ?>', <?= $client['dette_totale'] ?>)"
                                                        >
                                                            <i class="bi bi-cash-coin me-1"></i>Rembourser
                                                        </button>
                                                    <?php endif; ?>
                                                    <button type="button"
                                                        class="btn btn-sm btn-outline-info me-1"
                                                        title="Historique des règlements"
                                                        onclick="ouvrirHistorique(<?= $client['id'] ?>, '<?= htmlspecialchars(addslashes($client['nom'])) ?>')"
                                                    >
                                                        <i class="bi bi-clock-history"></i>
                                                    </button>
                                                    <button type="button"
                                                        class="btn btn-sm btn-danger btn-delete"
                                                        title="Supprimer ce client"
                                                        onclick="confirmerSuppression(<?= $client['id'] ?>, '<?= htmlspecialchars(addslashes($client['nom'])) ?>')"
                                                    >
                                                        <i class="bi bi-trash3"></i>
                                                    </button>
                                                </td>
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

    <!-- Modal Enregistrer un Remboursement -->
    <div class="modal fade" id="modalRembourser" tabindex="-1" aria-labelledby="modalRembourserLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-success shadow">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="modalRembourserLabel">
                        <i class="bi bi-cash-coin me-2"></i>Enregistrer un Remboursement
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="rembourser">
                    <input type="hidden" name="client_id" id="remboursementClientId" value="">
                    <div class="modal-body">
                        <div class="mb-3 text-center bg-light p-3 rounded">
                            <span class="text-muted d-block small">Client</span>
                            <h5 class="fw-bold mb-1" id="remboursementClientNom"></h5>
                            <span class="text-muted d-block small mt-2">Dette actuelle impayée</span>
                            <h4 class="text-danger fw-bold mb-0" id="remboursementDetteActuelle">0 FC</h4>
                        </div>

                        <div class="mb-3">
                            <label for="montant_remboursement" class="form-label fw-bold">Montant remboursé (FC) *</label>
                            <input type="number" step="0.01" class="form-control form-control-lg" id="montant_remboursement" name="montant_remboursement" required min="1" placeholder="Ex: 5000">
                            <div class="mt-1 d-flex justify-content-end">
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" onclick="remplirDetteTotale()">
                                    <i class="bi bi-arrow-down-right-circle me-1"></i>Régler la totalité de la dette
                                </button>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="mode_paiement" class="form-label fw-bold">Mode de règlement</label>
                            <select class="form-select form-select-lg" id="mode_paiement" name="mode_paiement">
                                <option value="Cash">Cash / Espèces</option>
                                <option value="Airtel Money">Airtel Money</option>
                                <option value="M-Pesa">M-Pesa</option>
                                <option value="Orange Money">Orange Money</option>
                                <option value="Virement">Virement bancaire</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label fw-bold">Notes / Référence (Optionnel)</label>
                            <input type="text" class="form-control" id="notes" name="notes" placeholder="Ex: Reçu n° 45 ou virement reçu">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-1"></i>Annuler
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-circle me-1"></i>Valider le remboursement
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Historique des Règlements d'un client -->
    <div class="modal fade" id="modalHistorique" tabindex="-1" aria-labelledby="modalHistoriqueLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content shadow">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="modalHistoriqueLabel">
                        <i class="bi bi-clock-history me-2"></i>Historique des Règlements - <span id="nomClientHistorique"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="contenuHistoriquePaiements" class="table-responsive">
                        <!-- Injecté dynamiquement par JavaScript -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de confirmation de suppression -->
    <div class="modal fade" id="modalSupprimer" tabindex="-1" aria-labelledby="modalSupprimerLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-danger">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="modalSupprimerLabel">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Confirmer la suppression
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-1">Vous allez supprimer le client :</p>
                    <p class="fw-bold fs-5 text-danger" id="nomClientASupprimer"></p>
                    <div class="alert alert-warning mt-2 mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        Cette action est <strong>irréversible</strong>. La suppression échouera si le client est associé à des ventes existantes.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Annuler
                    </button>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="supprimer">
                        <input type="hidden" name="client_id" id="clientIdASupprimer" value="">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash3 me-1"></i>Oui, supprimer
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Enregistrement du Service Worker PWA -->
    <script src="pwa-install.js"></script>
    <script>
        // Données complètes des règlements pour affichage instantané
        const tousLesPaiements = <?= json_encode($paiements_recents) ?>;
        let detteSelectionnee = 0;

        function ouvrirRemboursement(id, nom, dette) {
            detteSelectionnee = parseFloat(dette);
            document.getElementById('remboursementClientId').value = id;
            document.getElementById('remboursementClientNom').textContent = nom;
            document.getElementById('remboursementDetteActuelle').textContent = Number(dette).toLocaleString('fr-FR', {minimumFractionDigits: 2}) + ' FC';
            document.getElementById('montant_remboursement').value = '';
            document.getElementById('montant_remboursement').max = dette;
            var modal = new bootstrap.Modal(document.getElementById('modalRembourser'));
            modal.show();
        }

        function remplirDetteTotale() {
            document.getElementById('montant_remboursement').value = detteSelectionnee;
        }

        function ouvrirHistorique(clientId, clientNom) {
            document.getElementById('nomClientHistorique').textContent = clientNom;
            const container = document.getElementById('contenuHistoriquePaiements');

            const paiementsClient = tousLesPaiements.filter(p => parseInt(p.client_id) === parseInt(clientId));

            if (paiementsClient.length === 0) {
                container.innerHTML = `
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                        Aucun règlement enregistré pour ce client pour le moment.
                    </div>
                `;
            } else {
                let html = `
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Date & Heure</th>
                                <th>Montant Remboursé</th>
                                <th>Mode</th>
                                <th>Notes / Réf</th>
                            </tr>
                        </thead>
                        <tbody>
                `;
                paiementsClient.forEach(p => {
                    const dateObj = new Date(p.date_paiement);
                    const dateFormatted = dateObj.toLocaleDateString('fr-FR') + ' ' + dateObj.toLocaleTimeString('fr-FR', {hour: '2-digit', minute:'2-digit'});
                    html += `
                        <tr>
                            <td><i class="bi bi-calendar-event me-1 text-muted"></i> ${dateFormatted}</td>
                            <td class="fw-bold text-success fs-6">+${Number(p.montant).toLocaleString('fr-FR', {minimumFractionDigits: 2})} FC</td>
                            <td><span class="badge bg-secondary">${p.mode_paiement || 'Cash'}</span></td>
                            <td>${p.notes ? p.notes : '<span class="text-muted">-</span>'}</td>
                        </tr>
                    `;
                });
                html += `
                        </tbody>
                    </table>
                `;
                container.innerHTML = html;
            }

            var modal = new bootstrap.Modal(document.getElementById('modalHistorique'));
            modal.show();
        }

        function confirmerSuppression(id, nom) {
            document.getElementById('clientIdASupprimer').value = id;
            document.getElementById('nomClientASupprimer').textContent = nom;
            var modal = new bootstrap.Modal(document.getElementById('modalSupprimer'));
            modal.show();
        }

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
