<?php
require_once 'db.php';

// Forcer l'encodage UTF-8
header('Content-Type: text/html; charset=utf-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

$message = '';
$message_type = '';
$panier = [];
$derniere_vente_id = null;

// Traitement du formulaire de vente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'ajouter_panier') {
            // Ajouter un produit au panier
            $produit_id = intval($_POST['produit_id']);
            $quantite = floatval($_POST['quantite']);
            
            if ($quantite <= 0) {
                throw new Exception("La quantité doit être positive");
            }

            // Récupérer les infos du produit
            $stmt = $pdo->prepare("SELECT * FROM produits WHERE id = ?");
            $stmt->execute([$produit_id]);
            $produit = $stmt->fetch();

            if (!$produit) {
                throw new Exception("Produit non trouvé");
            }

            if ($produit['quantite_actuelle'] < $quantite) {
                throw new Exception("Stock insuffisant pour " . $produit['nom_article']);
            }

            // Vérifier si le produit est déjà dans le panier
            $existe = false;
            foreach ($panier as &$item) {
                if ($item['id'] == $produit_id) {
                    $item['quantite'] += $quantite;
                    $existe = true;
                    break;
                }
            }

            if (!$existe) {
                $panier[] = [
                    'id' => $produit['id'],
                    'nom_article' => $produit['nom_article'],
                    'prix_unitaire' => $produit['prix_unitaire'],
                    'quantite' => $quantite,
                    'unite_mesure' => $produit['unite_mesure']
                ];
            }

            $message = "Produit ajouté au panier";
            $message_type = "success";

        } elseif ($_POST['action'] === 'valider_vente') {
            // Valider la vente
            if (empty($_POST['panier'])) {
                throw new Exception("Le panier est vide");
            }

            $client_id = intval($_POST['client_id']);
            $panier_data = json_decode($_POST['panier'], true);
            $statut_paiement = $_POST['statut_paiement'];

            if (empty($panier_data)) {
                throw new Exception("Panier invalide");
            }

            // Calculer le total
            $montant_total = 0;
            foreach ($panier_data as $item) {
                $montant_total += $item['prix_unitaire'] * $item['quantite'];
            }

            $pdo->beginTransaction();

            // Créer la vente
            $stmt = $pdo->prepare("INSERT INTO ventes (client_id, montant_total, statut_paiement) VALUES (?, ?, ?)");
            $stmt->execute([$client_id, $montant_total, $statut_paiement]);
            $vente_id = $pdo->lastInsertId();

            // Ajouter les détails de vente et déduire le stock
            foreach ($panier_data as $item) {
                // Insérer dans details_vente
                $stmt = $pdo->prepare("INSERT INTO details_vente (vente_id, produit_id, quantite, prix_applique) VALUES (?, ?, ?, ?)");
                $stmt->execute([$vente_id, $item['id'], $item['quantite'], $item['prix_unitaire']]);

                // Déduire du stock
                $stmt = $pdo->prepare("UPDATE produits SET quantite_actuelle = quantite_actuelle - ? WHERE id = ?");
                $stmt->execute([$item['quantite'], $item['id']]);
            }

            $pdo->commit();
            $derniere_vente_id = $vente_id;
            $message = "Vente enregistrée avec succès ! Numéro de vente: #$vente_id";
            $message_type = "success";
            $panier = []; // Vider le panier

        } elseif ($_POST['action'] === 'vider_panier') {
            $panier = [];
            $message = "Panier vidé";
            $message_type = "info";
        }
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message = "Erreur: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Initialisation des variables
$clients = [];
$produits = [];
$ventes_recentes = [];
$erreur = '';

// Récupérer les clients et produits
try {
    $stmt = $pdo->query("SELECT * FROM clients ORDER BY nom ASC");
    $clients = $stmt->fetchAll();

    $stmt = $pdo->query("SELECT * FROM produits WHERE quantite_actuelle > 0 ORDER BY nom_article ASC");
    $produits = $stmt->fetchAll();

    // Récupérer les ventes récentes
    $stmt = $pdo->query("
        SELECT v.id, v.client_id, v.date_vente, v.montant_total, v.statut_paiement, 
               c.nom as client_nom, 
               COUNT(dv.id) as nb_articles
        FROM ventes v
        LEFT JOIN clients c ON v.client_id = c.id
        LEFT JOIN details_vente dv ON v.id = dv.vente_id
        GROUP BY v.id, v.client_id, v.date_vente, v.montant_total, v.statut_paiement, c.nom
        ORDER BY v.date_vente DESC
        LIMIT 10
    ");
    $ventes_recentes = $stmt->fetchAll();
} catch (PDOException $e) {
    $erreur = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Moses dépôt plastiques - Caisse / Ventes</title>
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
        .panier-item {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 10px;
            margin-bottom: 10px;
        }
        .total-display {
            font-size: 2rem;
            font-weight: bold;
            color: #28a745;
        }
        .statut-paye { background-color: #28a745; }
        .statut-attente { background-color: #ffc107; color: #000; }
        .statut-annule { background-color: #dc3545; }
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
                        <a class="nav-link active" href="ventes.php">
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
                <strong>Aucun produit disponible</strong> Vous devez d'abord ajouter des produits avant de pouvoir effectuer des ventes.
                <a href="produits.php" class="btn btn-sm btn-primary ms-2">Ajouter des produits</a>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (empty($clients)): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle"></i> 
                <strong>Aucun client disponible</strong> Vous devez d'abord ajouter des clients avant de pouvoir effectuer des ventes.
                <a href="clients.php" class="btn btn-sm btn-primary ms-2">Ajouter des clients</a>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?> alert-dismissible fade show d-flex align-items-center justify-content-between flex-wrap gap-2" role="alert">
                <div>
                    <i class="bi bi-<?= $message_type === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill' ?> me-2"></i> 
                    <?= htmlspecialchars($message) ?>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <?php if (!empty($derniere_vente_id)): ?>
                        <a href="facture.php?id=<?= $derniere_vente_id ?>&auto_print=1" target="_blank" class="btn btn-sm btn-dark">
                            <i class="bi bi-printer-fill me-1"></i> Imprimer la Facture
                        </a>
                    <?php endif; ?>
                    <button type="button" class="btn-close position-static" data-bs-dismiss="alert"></button>
                </div>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Formulaire de vente -->
            <div class="col-12 col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="bi bi-cart-plus"></i> Nouvelle Vente</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($produits) || empty($clients)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-cart-x" style="font-size: 3rem;"></i>
                                <p class="mt-3">
                                    <?php if (empty($produits) && empty($clients)): ?>
                                        Vous devez ajouter des produits et des clients avant de pouvoir effectuer des ventes.
                                    <?php elseif (empty($produits)): ?>
                                        Vous devez ajouter des produits avant de pouvoir effectuer des ventes.
                                    <?php else: ?>
                                        Vous devez ajouter des clients avant de pouvoir effectuer des ventes.
                                    <?php endif; ?>
                                </p>
                                <div class="d-flex justify-content-center gap-2">
                                    <?php if (empty($produits)): ?>
                                        <a href="produits.php" class="btn btn-primary">
                                            <i class="bi bi-plus-circle"></i> Ajouter des produits
                                        </a>
                                    <?php endif; ?>
                                    <?php if (empty($clients)): ?>
                                        <a href="clients.php" class="btn btn-primary">
                                            <i class="bi bi-plus-circle"></i> Ajouter des clients
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Sélection du client -->
                            <div class="mb-3">
                                <label for="client_id" class="form-label fw-bold">Client</label>
                                <select class="form-select form-select-lg" id="client_id" name="client_id" required>
                                    <option value="">-- Sélectionner un client --</option>
                                    <?php foreach ($clients as $client): ?>
                                        <option value="<?= $client['id'] ?>">
                                            <?= htmlspecialchars($client['nom']) ?> 
                                            (<?= htmlspecialchars($client['type_client']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Ajout de produit -->
                            <div class="mb-3">
                                <label for="produit_id" class="form-label fw-bold">Produit</label>
                                <select class="form-select form-select-lg" id="produit_id" name="produit_id" required>
                                    <option value="">-- Sélectionner un produit --</option>
                                    <?php foreach ($produits as $produit): ?>
                                        <option value="<?= $produit['id'] ?>" data-prix="<?= $produit['prix_unitaire'] ?>">
                                            <?= htmlspecialchars($produit['nom_article']) ?> 
                                            (<?= number_format($produit['quantite_actuelle'], 2) ?> <?= htmlspecialchars($produit['unite_mesure']) ?>)
                                            - <?= number_format($produit['prix_unitaire'], 2) ?> FC
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="quantite" class="form-label fw-bold">Quantité</label>
                                <input type="number" step="0.01" class="form-control form-control-lg" id="quantite" name="quantite" required min="0.01" placeholder="Ex: 10.5">
                            </div>

                            <div class="d-grid gap-2 mb-3">
                                <button type="button" class="btn btn-primary btn-lg" onclick="ajouterAuPanier()">
                                    <i class="bi bi-plus-circle"></i> Ajouter au Panier
                                </button>
                            </div>

                            <!-- Panier -->
                            <div id="panier_container" class="mb-3" style="display: none;">
                                <h6 class="fw-bold"><i class="bi bi-cart"></i> Panier</h6>
                                <div id="panier_items"></div>
                                <div class="text-end mt-3">
                                    <div class="total-display">
                                        Total: <span id="total_panier">0</span> FC
                                    </div>
                                </div>
                            </div>

                            <!-- Statut de paiement -->
                            <div class="mb-3">
                                <label for="statut_paiement" class="form-label fw-bold">Statut de Paiement</label>
                                <select class="form-select form-select-lg" id="statut_paiement" name="statut_paiement">
                                    <option value="paye">Payé</option>
                                    <option value="en_attente">En attente</option>
                                    <option value="annule">Annulé</option>
                                </select>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-success btn-lg" onclick="validerVente()" id="btn_valider" disabled>
                                    <i class="bi bi-check-circle"></i> Valider la Vente
                                </button>
                                <button type="button" class="btn btn-warning btn-lg" onclick="viderPanier()" id="btn_vider" style="display: none;">
                                    <i class="bi bi-trash"></i> Vider le Panier
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Ventes récentes -->
            <div class="col-12 col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="bi bi-receipt"></i> Ventes Récentes</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped align-middle">
                                <thead>
                                    <tr>
                                        <th>N°</th>
                                        <th>Client</th>
                                        <th>Montant</th>
                                        <th>Articles</th>
                                        <th>Statut</th>
                                        <th>Date</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ventes_recentes as $vente): ?>
                                        <tr>
                                            <td>#<?= $vente['id'] ?></td>
                                            <td><?= htmlspecialchars($vente['client_nom'] ?? 'Client supprimé') ?></td>
                                            <td class="fw-bold"><?= number_format($vente['montant_total'], 2) ?> FC</td>
                                            <td><?= $vente['nb_articles'] ?></td>
                                            <td>
                                                <span class="badge statut-<?= $vente['statut_paiement'] ?>">
                                                    <?= ucfirst(str_replace('_', ' ', $vente['statut_paiement'])) ?>
                                                </span>
                                            </td>
                                            <td><?= date('d/m/Y H:i', strtotime($vente['date_vente'])) ?></td>
                                            <td>
                                                <a href="facture.php?id=<?= $vente['id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Imprimer la facture">
                                                    <i class="bi bi-printer"></i> Facture
                                                </a>
                                            </td>
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

    <script>
        let panier = [];

        function ajouterAuPanier() {
            const produitId = document.getElementById('produit_id').value;
            const quantite = parseFloat(document.getElementById('quantite').value);
            const produitSelect = document.getElementById('produit_id');
            const produitText = produitSelect.options[produitSelect.selectedIndex].text;
            const prix = parseFloat(produitSelect.options[produitSelect.selectedIndex].dataset.prix);

            if (!produitId || !quantite || quantite <= 0) {
                alert('Veuillez sélectionner un produit et entrer une quantité valide');
                return;
            }

            // Vérifier si le produit est déjà dans le panier
            const existant = panier.find(item => item.id == produitId);
            if (existant) {
                existant.quantite += quantite;
            } else {
                panier.push({
                    id: produitId,
                    nom_article: produitText.split('(')[0].trim(),
                    prix_unitaire: prix,
                    quantite: quantite
                });
            }

            afficherPanier();
            document.getElementById('quantite').value = '';
        }

        function afficherPanier() {
            const container = document.getElementById('panier_container');
            const itemsContainer = document.getElementById('panier_items');
            const totalElement = document.getElementById('total_panier');
            const btnValider = document.getElementById('btn_valider');
            const btnVider = document.getElementById('btn_vider');

            if (panier.length === 0) {
                container.style.display = 'none';
                btnValider.disabled = true;
                btnVider.style.display = 'none';
                return;
            }

            container.style.display = 'block';
            btnValider.disabled = false;
            btnVider.style.display = 'block';

            let html = '';
            let total = 0;

            panier.forEach((item, index) => {
                const sousTotal = item.prix_unitaire * item.quantite;
                total += sousTotal;
                html += `
                    <div class="panier-item d-flex justify-content-between align-items-center">
                        <div>
                            <strong>${item.nom_article}</strong><br>
                            <small>${item.quantite} x ${item.prix_unitaire.toFixed(2)} FC = ${sousTotal.toFixed(2)} FC</small>
                        </div>
                        <button type="button" class="btn btn-sm btn-danger" onclick="retirerDuPanier(${index})">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                `;
            });

            itemsContainer.innerHTML = html;
            totalElement.textContent = total.toFixed(2);
        }

        function retirerDuPanier(index) {
            panier.splice(index, 1);
            afficherPanier();
        }

        function viderPanier() {
            panier = [];
            afficherPanier();
        }

        function validerVente() {
            const clientId = document.getElementById('client_id').value;
            const statutPaiement = document.getElementById('statut_paiement').value;

            if (!clientId) {
                alert('Veuillez sélectionner un client');
                return;
            }

            if (panier.length === 0) {
                alert('Le panier est vide');
                return;
            }

            // Créer un formulaire caché pour soumettre
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'valider_vente';
            form.appendChild(actionInput);

            const clientInput = document.createElement('input');
            clientInput.type = 'hidden';
            clientInput.name = 'client_id';
            clientInput.value = clientId;
            form.appendChild(clientInput);

            const panierInput = document.createElement('input');
            panierInput.type = 'hidden';
            panierInput.name = 'panier';
            panierInput.value = JSON.stringify(panier);
            form.appendChild(panierInput);

            const statutInput = document.createElement('input');
            statutInput.type = 'hidden';
            statutInput.name = 'statut_paiement';
            statutInput.value = statutPaiement;
            form.appendChild(statutInput);

            document.body.appendChild(form);
            form.submit();
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
