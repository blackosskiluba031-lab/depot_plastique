<?php
require_once 'db.php';

// Forcer l'encodage UTF-8
header('Content-Type: text/html; charset=utf-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

$vente_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$vente = null;
$details = [];
$erreur = '';
$auto_print = isset($_GET['auto_print']) && $_GET['auto_print'] == '1';

if ($vente_id <= 0) {
    $erreur = "Numéro de reçu invalide.";
} else {
    try {
        // Récupérer les informations de la vente et du client
        $stmt = $pdo->prepare("
            SELECT v.*, 
                   c.nom AS client_nom, 
                   c.telephone AS client_telephone, 
                   c.type_client AS client_type
            FROM ventes v
            LEFT JOIN clients c ON v.client_id = c.id
            WHERE v.id = ?
        ");
        $stmt->execute([$vente_id]);
        $vente = $stmt->fetch();

        if (!$vente) {
            $erreur = "Reçu introuvable pour la référence #" . htmlspecialchars((string)$vente_id);
        } else {
            // Récupérer les détails des articles vendus
            $stmt_details = $pdo->prepare("
                SELECT dv.*, 
                       p.nom_article, 
                       p.categorie, 
                       p.unite_mesure
                FROM details_vente dv
                JOIN produits p ON dv.produit_id = p.id
                WHERE dv.vente_id = ?
            ");
            $stmt_details->execute([$vente_id]);
            $details = $stmt_details->fetchAll();
        }
    } catch (PDOException $e) {
        $erreur = "Erreur de base de données : " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #<?= $vente ? $vente['id'] : '' ?> - Business Moses dépôt plastiques</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #e9ecef;
            color: #111;
            font-family: 'Consolas', 'Courier New', Courier, monospace;
            min-height: 100vh;
        }

        /* Barre d'action sur écran */
        .action-bar {
            max-width: 420px;
            margin: 20px auto 10px auto;
        }

        /* Conteneur Ticket de Caisse format 80mm / POS */
        .ticket-wrapper {
            max-width: 380px;
            margin: 10px auto 40px auto;
            background: #ffffff;
            padding: 24px 20px;
            border-radius: 8px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
            position: relative;
            border: 1px solid #dee2e6;
        }

        /* Style spécifique texte ticket */
        .ticket-header {
            text-align: center;
            margin-bottom: 12px;
        }

        .ticket-title {
            font-size: 1.15rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
            color: #000;
        }

        .ticket-subtitle {
            font-size: 0.82rem;
            font-weight: bold;
            margin-bottom: 4px;
            color: #333;
        }

        .ticket-info {
            font-size: 0.8rem;
            color: #444;
            line-height: 1.35;
        }

        /* Lignes de séparation ticket de caisse */
        .ticket-separator {
            border-top: 1px dashed #000;
            margin: 10px 0;
        }

        .ticket-double-separator {
            border-top: 2px dashed #000;
            margin: 12px 0;
        }

        /* Tableau des articles format ticket */
        .ticket-table {
            width: 100%;
            font-size: 0.82rem;
            margin-bottom: 5px;
        }

        .ticket-table th {
            text-align: left;
            padding-bottom: 6px;
            border-bottom: 1px dashed #000;
            font-weight: bold;
            text-transform: uppercase;
        }

        .ticket-table td {
            padding: 4px 0;
            vertical-align: top;
        }

        .item-row {
            border-bottom: 1px dotted #ccc;
            padding-bottom: 4px;
            margin-bottom: 4px;
        }

        .item-name {
            font-weight: bold;
            display: block;
            color: #000;
        }

        .item-details {
            display: flex;
            justify-content: space-between;
            font-size: 0.8rem;
            color: #333;
        }

        /* Section Total */
        .ticket-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 1.15rem;
            font-weight: 900;
            margin: 8px 0;
            color: #000;
        }

        .ticket-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.82rem;
            margin-bottom: 3px;
        }

        .ticket-badge {
            display: inline-block;
            padding: 2px 6px;
            font-size: 0.75rem;
            font-weight: bold;
            border: 1px solid #000;
            border-radius: 3px;
        }

        /* Pied de ticket */
        .ticket-footer {
            text-align: center;
            font-size: 0.78rem;
            margin-top: 15px;
            color: #333;
            line-height: 1.35;
        }

        /* Code-barres simulé thermique */
        .ticket-barcode {
            text-align: center;
            font-size: 1.6rem;
            letter-spacing: 4px;
            font-family: monospace;
            margin-top: 10px;
            user-select: none;
        }

        /* ========================================================
           STYLES D'IMPRESSION DIRECTE POUR MACHINE À REÇU (POS 80mm)
           ======================================================== */
        @media print {
            @page {
                size: 80mm auto;
                margin: 0mm !important;
            }

            html, body {
                background-color: #ffffff !important;
                color: #000000 !important;
                margin: 0 !important;
                padding: 0 !important;
                width: 80mm !important;
                font-family: 'Consolas', 'Courier New', Courier, monospace !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .no-print,
            .navbar,
            .action-bar,
            .btn,
            .alert {
                display: none !important;
            }

            .ticket-wrapper {
                width: 76mm !important;
                max-width: 76mm !important;
                margin: 0 auto !important;
                padding: 3mm 2mm !important;
                box-shadow: none !important;
                border: none !important;
                border-radius: 0 !important;
            }

            .ticket-title {
                font-size: 1rem !important;
            }

            .ticket-total {
                font-size: 1.05rem !important;
            }

            .ticket-table, .ticket-row, .ticket-info, .ticket-footer {
                font-size: 0.75rem !important;
            }
        }
    </style>
</head>
<body>

    <!-- Barre d'actions en haut (Écran uniquement) -->
    <div class="no-print action-bar">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <a href="ventes.php" class="btn btn-outline-dark btn-sm">
                <i class="bi bi-arrow-left"></i> Retour Ventes
            </a>
            <a href="historique.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-clock-history"></i> Historique
            </a>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-house"></i> Accueil
            </a>
        </div>

        <div class="d-grid mb-3">
            <button onclick="window.print()" class="btn btn-primary btn-lg shadow fw-bold">
                <i class="bi bi-printer-fill me-2"></i> IMPRIMER LE TICKET
            </button>
        </div>
    </div>

    <!-- Conteneur Ticket Format Machine à Reçu (Supermarché) -->
    <div class="container-fluid">
        <?php if ($erreur): ?>
            <div class="ticket-wrapper text-center text-danger">
                <i class="bi bi-exclamation-triangle-fill fs-1"></i>
                <h5 class="mt-2"><?= htmlspecialchars($erreur) ?></h5>
                <a href="ventes.php" class="btn btn-primary btn-sm mt-3">Retour à la caisse</a>
            </div>
        <?php else: ?>
            <div class="ticket-wrapper">
                <!-- En-tête du Ticket -->
                <div class="ticket-header">
                    <div class="ticket-title">BUSINESS MOSES</div>
                    <div class="ticket-subtitle">DÉPÔT PLASTIQUES</div>
                    <div class="ticket-info">
                        Vente & Recyclage Articles Plastiques<br>
                        Service Caisse & Facturation
                    </div>
                </div>

                <div class="ticket-separator"></div>

                <!-- Métadonnées de la transaction -->
                <div class="ticket-info">
                    <div class="ticket-row">
                        <span>TICKET N° :</span>
                        <strong>#<?= str_pad((string)$vente['id'], 5, '0', STR_PAD_LEFT) ?></strong>
                    </div>
                    <div class="ticket-row">
                        <span>DATE :</span>
                        <span><?= date('d/m/Y H:i', strtotime($vente['date_vente'])) ?></span>
                    </div>
                    <div class="ticket-row">
                        <span>CLIENT :</span>
                        <strong><?= strtoupper(htmlspecialchars($vente['client_nom'] ?? 'COMPTOIR')) ?></strong>
                    </div>
                    <?php if (!empty($vente['client_telephone'])): ?>
                        <div class="ticket-row">
                            <span>TEL :</span>
                            <span><?= htmlspecialchars($vente['client_telephone']) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($vente['client_type'])): ?>
                        <div class="ticket-row">
                            <span>TYPE :</span>
                            <span><?= htmlspecialchars($vente['client_type']) ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="ticket-separator"></div>

                <!-- En-tête Articles -->
                <div class="ticket-row fw-bold text-uppercase" style="font-size: 0.78rem; border-bottom: 1px dashed #000; padding-bottom: 4px; margin-bottom: 6px;">
                    <span>ARTICLE / QTÉ</span>
                    <span>TOTAL</span>
                </div>

                <!-- Liste des articles -->
                <div class="ticket-items">
                    <?php foreach ($details as $item): 
                        $sous_total = $item['quantite'] * $item['prix_applique'];
                    ?>
                        <div class="item-row">
                            <span class="item-name"><?= htmlspecialchars($item['nom_article']) ?></span>
                            <div class="item-details">
                                <span><?= number_format($item['quantite'], 2) ?> <?= htmlspecialchars($item['unite_mesure']) ?> x <?= number_format($item['prix_applique'], 2, ',', ' ') ?> FC</span>
                                <strong class="ms-auto"><?= number_format($sous_total, 2, ',', ' ') ?> FC</strong>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="ticket-double-separator"></div>

                <!-- Totaux & Statut -->
                <div class="ticket-row">
                    <span>NOMBRE ARTICLES :</span>
                    <strong><?= count($details) ?></strong>
                </div>

                <div class="ticket-total">
                    <span>TOTAL :</span>
                    <span><?= number_format($vente['montant_total'], 2, ',', ' ') ?> FC</span>
                </div>

                <div class="ticket-separator"></div>

                <div class="ticket-row">
                    <span>STATUT PAIEMENT :</span>
                    <strong class="text-uppercase">
                        <?php if ($vente['statut_paiement'] === 'paye'): ?>
                            [ PAYÉ ]
                        <?php elseif ($vente['statut_paiement'] === 'en_attente'): ?>
                            [ EN ATTENTE ]
                        <?php else: ?>
                            [ <?= strtoupper(htmlspecialchars($vente['statut_paiement'])) ?> ]
                        <?php endif; ?>
                    </strong>
                </div>

                <div class="ticket-row">
                    <span>MODE :</span>
                    <span>ESPÈCES / CAISSE</span>
                </div>

                <div class="ticket-double-separator"></div>

                <!-- Message et Code de validation -->
                <div class="ticket-footer">
                    <strong>MERCI DE VOTRE VISITE !</strong><br>
                    Les articles vendus ne sont ni repris<br>
                    ni échangés après livraison.<br>
                    *** À BIENTÔT ***
                </div>

                <!-- Code barres ticket -->
                <div class="ticket-barcode">
                    ||| | |||| || | |||| |||
                </div>
                <div class="text-center" style="font-size: 0.7rem; color: #555;">
                    *FAC<?= str_pad((string)$vente['id'], 6, '0', STR_PAD_LEFT) ?>*
                </div>
            </div>

            <!-- Bouton inférieur pour écran -->
            <div class="no-print action-bar text-center mb-5">
                <button onclick="window.print()" class="btn btn-dark btn-lg w-100 shadow mb-2">
                    <i class="bi bi-printer-fill me-2"></i> Imprimer le Ticket
                </button>
                <a href="ventes.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-cart-plus"></i> Effectuer une autre vente
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Lancer l'impression automatiquement si auto_print=1
        <?php if ($auto_print && !$erreur): ?>
        window.addEventListener('load', function() {
            setTimeout(function() {
                window.print();
            }, 300);
        });
        <?php endif; ?>
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
