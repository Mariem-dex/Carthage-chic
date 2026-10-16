<?php
// admin.php - Hidden Admin Panel Dashboard

session_start();
require_once __DIR__ . '/db.php';
// Optional protection: require admin_config and check IP/secret before showing admin
require_once __DIR__ . '/admin_config.php';

// If IP not allowed and no secret key, deny early
$remote = $_SERVER['REMOTE_ADDR'] ?? '';
// Enforce basic auth (unless IP allowed or secret key present)
admin_require_basic_auth();

// 1. Session Protection
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header('Location: admin_login.php');
    exit;
}

// 2. Active Tab selection
$tab = $_GET['tab'] ?? 'dashboard';

$message = '';
$error = '';

function get_product_images_map(PDO $pdo, array $products): array {
    if (empty($products)) {
        return [];
    }

    $product_ids = array_map(static fn($p) => (int)$p['id'], $products);
    $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
    $images_map = [];

    $stmt = $pdo->prepare("SELECT product_id, image_path FROM product_images WHERE product_id IN ($placeholders) ORDER BY sort_order ASC, id ASC");
    $stmt->execute($product_ids);

    foreach ($stmt->fetchAll() as $row) {
        $pid = (int)$row['product_id'];
        $path = trim((string)($row['image_path'] ?? ''));
        if ($path !== '' && file_exists(__DIR__ . '/' . $path)) {
            $images_map[$pid][] = $path;
        }
    }

    // Backward compatibility for products having only products.image_path.
    foreach ($products as $product) {
        $pid = (int)$product['id'];
        if (!isset($images_map[$pid]) || empty($images_map[$pid])) {
            $legacy_path = trim((string)($product['image_path'] ?? ''));
            if ($legacy_path !== '' && file_exists(__DIR__ . '/' . $legacy_path)) {
                $images_map[$pid] = [$legacy_path];
            } else {
                $images_map[$pid] = [];
            }
        }
    }

    return $images_map;
}

// 3. Process Actions (Add / Edit / Delete / Toggle / Status Update)

// A. Product Form Actions (Add & Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save_product') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $category = trim($_POST['category'] ?? '');
        
        // Sizes checkboxes array to string
        $sizes_arr = $_POST['sizes'] ?? [];
        $sizes = implode(',', array_map('trim', $sizes_arr));
        
        // Colors string (comma separated)
        $colors = implode(',', array_filter(array_map('trim', explode(',', $_POST['colors'] ?? ''))));
        
        $available = isset($_POST['available']) ? 1 : 0;

        if (empty($name) || empty($price) || empty($category)) {
            $error = 'Veuillez renseigner le nom, le prix et la catégorie du produit.';
        } else {
            $existing_image = trim((string)($_POST['existing_image'] ?? ''));
            $uploaded_images = [];
               $existing_images_raw = (string)($_POST['existing_images'] ?? '[]');
               $existing_images = json_decode($existing_images_raw, true);
               if (!is_array($existing_images)) {
                   $existing_images = [];
               }
               $existing_images = array_values(array_filter(array_map(static fn($path) => trim((string)$path), $existing_images)));
            $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];

            // Handle multiple file upload
            if (isset($_FILES['product_images']) && is_array($_FILES['product_images']['name'] ?? null)) {
                $upload_dir = __DIR__ . '/uploads/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $file_count = count($_FILES['product_images']['name']);
                for ($i = 0; $i < $file_count; $i++) {
                    $file_error = (int)($_FILES['product_images']['error'][$i] ?? UPLOAD_ERR_NO_FILE);
                    if ($file_error === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }
                    if ($file_error !== UPLOAD_ERR_OK) {
                        $error = 'Une ou plusieurs images n\'ont pas pu être transférées.';
                        break;
                    }

                    $file_name = (string)($_FILES['product_images']['name'][$i] ?? '');
                    $file_tmp = (string)($_FILES['product_images']['tmp_name'][$i] ?? '');
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                    if (!in_array($file_ext, $allowed_exts, true)) {
                        $error = 'Extension de fichier d\'image non autorisée (uniquement JPG, JPEG, PNG, WEBP).';
                        break;
                    }

                    $new_filename = 'prod_' . time() . '_' . rand(1000, 9999) . '_' . $i . '.' . $file_ext;
                    $dest_path = $upload_dir . $new_filename;
                    if (!move_uploaded_file($file_tmp, $dest_path)) {
                        $error = 'Échec du transfert d\'une image.';
                        break;
                    }

                    $uploaded_images[] = 'uploads/' . $new_filename;
                }
            }

            if (empty($error)) {
                try {
                    $pdo->beginTransaction();

                    $product_id = $id;
                    if ($id > 0) {
                        // Edit existing
                        $main_image = !empty($uploaded_images) ? $uploaded_images[0] : $existing_image;
                           $final_images = array_values(array_merge($existing_images, $uploaded_images));
                           $main_image = $final_images[0] ?? $existing_image;
                        $stmt = $pdo->prepare("
                            UPDATE products 
                            SET name = ?, description = ?, price = ?, category = ?, sizes = ?, colors = ?, image_path = ?, available = ? 
                            WHERE id = ?
                        ");
                        $stmt->execute([$name, $description, $price, $category, $sizes, $colors, $main_image, $available, $id]);

                           $stmt_del_imgs = $pdo->prepare("DELETE FROM product_images WHERE product_id = ?");
                           $stmt_del_imgs->execute([$id]);

                           if (!empty($final_images)) {
                               $stmt_img = $pdo->prepare("INSERT INTO product_images (product_id, image_path, sort_order) VALUES (?, ?, ?)");
                               $sort_order = 0;
                               foreach ($final_images as $img_path) {
                                   $sort_order++;
                                   $stmt_img->execute([$id, $img_path, $sort_order]);
                               }
                           }
                        $message = 'Produit mis à jour avec succès.';
                    } else {
                        // Create new
                        $main_image = !empty($uploaded_images) ? $uploaded_images[0] : '';
                        $stmt = $pdo->prepare("
                            INSERT INTO products (name, description, price, category, sizes, colors, image_path, available) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$name, $description, $price, $category, $sizes, $colors, $main_image, $available]);
                        $product_id = (int)$pdo->lastInsertId();
                        $message = 'Nouveau produit ajouté avec succès.';

                           if (!empty($uploaded_images) && $product_id > 0) {
                               $stmt_img = $pdo->prepare("INSERT INTO product_images (product_id, image_path, sort_order) VALUES (?, ?, ?)");
                               $sort_order = 0;
                               foreach ($uploaded_images as $img_path) {
                                   $sort_order++;
                                   $stmt_img->execute([$product_id, $img_path, $sort_order]);
                               }
                           }
                    }

                    $pdo->commit();
                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $error = 'Erreur lors de la sauvegarde : ' . $e->getMessage();
                }
            }
        }
    }
}

// B. Toggle availability (GET query)
if (isset($_GET['action']) && $_GET['action'] === 'toggle_avail' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    try {
        $stmt = $pdo->prepare("UPDATE products SET available = NOT available WHERE id = ?");
        $stmt->execute([$id]);
        $message = 'Disponibilité du produit modifiée.';
        // Keep active tab on reload
        header("Location: admin.php?tab=products&msg=" . urlencode($message));
        exit;
    } catch (PDOException $e) {
        $error = 'Erreur : ' . $e->getMessage();
    }
}

// C. Delete product (GET query)
if (isset($_GET['action']) && $_GET['action'] === 'delete_product' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    try {
        // Delete items or check constraints if any
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $message = 'Produit supprimé avec succès.';
        header("Location: admin.php?tab=products&msg=" . urlencode($message));
        exit;
    } catch (PDOException $e) {
        $error = 'Impossible de supprimer le produit : ' . $e->getMessage();
    }
}

// D. Update Order Status (POST query)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_order_status') {
    $order_id = intval($_POST['order_id'] ?? 0);
    $status = trim($_POST['status'] ?? '');
    
    $allowed_statuses = ['pending', 'confirmed', 'shipped', 'completed', 'cancelled'];
    if ($order_id > 0 && in_array($status, $allowed_statuses)) {
        try {
            $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $stmt->execute([$status, $order_id]);
            echo json_encode(['success' => true]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }
    echo json_encode(['success' => false, 'message' => 'Paramètres invalides.']);
    exit;
}

// E. AJAX Fetch Order Details
if (isset($_GET['action']) && $_GET['action'] === 'get_order_details' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $order_id = intval($_GET['id']);
    try {
        // Get order details
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch();

        if ($order) {
            // Get order items
            $stmt_items = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
            $stmt_items->execute([$order_id]);
            $order['items'] = $stmt_items->fetchAll();
            echo json_encode(['success' => true, 'order' => $order]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Commande introuvable.']);
        }
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// F. AJAX new orders count (for admin dashboard polling)
if (isset($_GET['action']) && $_GET['action'] === 'new_orders_count') {
    header('Content-Type: application/json');
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'");
        $count = (int)$stmt->fetchColumn();
        echo json_encode(['success' => true, 'count' => $count]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage() ]);
    }
    exit;
}

// Handle GET message query params (to display after redirects)
if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
}

// 4. Fetch dashboard stats & data lists
try {
    // Stats Calculations
    $stat_total_orders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $stat_pending_orders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
    $stat_total_revenue = $pdo->query("SELECT SUM(total_price) FROM orders WHERE status NOT IN ('cancelled')")->fetchColumn() ?: 0.00;
    $stat_total_products = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();

    // Data lists depending on tab
    if ($tab === 'products') {
        // Show available products first in admin list
        $stmt = $pdo->query("SELECT * FROM products ORDER BY available DESC, id DESC");
        $products = $stmt->fetchAll();
        $product_images_map = get_product_images_map($pdo, $products);
    } elseif ($tab === 'orders') {
        $stmt = $pdo->query("SELECT * FROM orders ORDER BY id DESC");
        $orders = $stmt->fetchAll();
    } else {
        // Dashboard Tab: Fetch top metrics and 5 recent orders
        $stmt_recent = $pdo->query("SELECT * FROM orders ORDER BY id DESC LIMIT 5");
        $recent_orders = $stmt_recent->fetchAll();
    }
} catch (PDOException $e) {
    die("Erreur de base de données : " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration Carthage Chic</title>
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>

    <div class="admin-wrapper">
        <!-- SIDEBAR -->
        <aside class="admin-sidebar">
            <div class="sidebar-brand">
                <img src="assets/logo.png?v=<?= file_exists(__DIR__ . '/assets/logo.png') ? filemtime(__DIR__ . '/assets/logo.png') : (file_exists(__DIR__ . '/assets/logo.jpg') ? filemtime(__DIR__ . '/assets/logo.jpg') : time()) ?>" alt="Carthage Chic Logo" class="sidebar-logo">
                <span class="sidebar-title">Carthage Chic</span>
            </div>

            <ul class="sidebar-menu">
                <li>
                    <a href="admin.php?tab=dashboard" class="sidebar-link <?= $tab === 'dashboard' ? 'active' : '' ?>">
                        <!-- SVG Dashboard Icon -->
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>
                        Tableau de bord
                    </a>
                </li>
                <li>
                    <a href="admin.php?tab=products" class="sidebar-link <?= $tab === 'products' ? 'active' : '' ?>">
                        <!-- SVG Hanger/Clothing Icon -->
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.38 3.46L16 2.06V2a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v.06L3.62 3.46a2 2 0 0 0-1.34 1.9v11.75a2 2 0 0 0 1.25 1.86l8.13 3.05a2 2 0 0 0 1.34 0l8.13-3.05a2 2 0 0 0 1.25-1.86V5.36a2 2 0 0 0-1.25-1.9zM12 2v6.5M10 8h4"/></svg>
                        Produits (Stock)
                    </a>
                </li>
                <li>
                    <a href="admin.php?tab=orders" class="sidebar-link <?= $tab === 'orders' ? 'active' : '' ?>">
                        <!-- SVG Orders Icon -->
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                        Commandes
                    </a>
                </li>
            </ul>

            <div class="sidebar-logout">
                <a href="admin_logout_action.php" class="logout-btn">
                    <!-- SVG Logout Icon -->
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    Se déconnecter
                </a>
            </div>
        </aside>

        <!-- MAIN CONTENT AREA -->
        <main class="admin-content">
            <!-- Header section of content -->
            <div class="content-header">
                <div>
                    <h2 class="content-title">
                        <?php 
                            if ($tab === 'products') echo 'Gestion des Produits';
                            elseif ($tab === 'orders') echo 'Suivi des Commandes';
                            else echo 'Tableau de bord';
                        ?>
                    </h2>
                </div>
                <div class="admin-navbar-actions">
                    <a href="index.php" class="btn-view-store" target="_blank">
                        <!-- SVG Link Icon -->
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                        Accéder au Site
                    </a>
                    
                    <?php if ($tab === 'products'): ?>
                        <button type="button" class="btn-admin-action" id="btn-add-product">
                            <!-- SVG Add Icon -->
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Ajouter un produit
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Messages display -->
            <?php if (!empty($message)): ?>
                <div class="badge success" style="margin-bottom: 24px; padding: 12px 20px; font-size: 13px; display: block; border-radius: 6px; text-transform: none;">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="badge danger" style="margin-bottom: 24px; padding: 12px 20px; font-size: 13px; display: block; border-radius: 6px; text-transform: none;">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- STATS CARDS -->
            <section class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon success">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    </div>
                    <div>
                        <div class="stat-info-num"><?= number_format($stat_total_revenue, 2, '.', ' ') ?> TND</div>
                        <div class="stat-info-label">Chiffre d'Affaires</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                    </div>
                    <div>
                        <div class="stat-info-num"><?= $stat_total_orders ?></div>
                        <div class="stat-info-label">Total Commandes</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon warning">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </div>
                    <div>
                        <div class="stat-info-num"><?= $stat_pending_orders ?></div>
                        <div class="stat-info-label">En Attente</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon info">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                    </div>
                    <div>
                        <div class="stat-info-num"><?= $stat_total_products ?></div>
                        <div class="stat-info-label">Variétés en Stock</div>
                    </div>
                </div>
            </section>

            <!-- TAB CONTENT -->

            <!-- 1. DASHBOARD VIEW -->
            <?php if ($tab === 'dashboard'): ?>
                <div class="admin-card">
                    <div class="card-header-admin">
                        <h3 class="card-title-admin">Dernières Commandes</h3>
                        <a href="admin.php?tab=orders" style="font-size: 12px; color: var(--admin-primary); text-decoration: underline;">Voir toutes</a>
                    </div>
                    <div class="card-body-admin table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Client</th>
                                    <th>Gouvernorat</th>
                                    <th>Montant</th>
                                    <th>Statut</th>
                                    <th>Date</th>
                                    <th>Détails</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_orders)): ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; color: var(--admin-text-muted);">
                                            Aucune commande enregistrée pour le moment.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recent_orders as $ord): 
                                        $status_class = [
                                            'pending' => 'warning',
                                            'confirmed' => 'info',
                                            'shipped' => 'secondary',
                                            'completed' => 'success',
                                            'cancelled' => 'danger'
                                        ][$ord['status']] ?? 'secondary';
                                    ?>
                                        <tr>
                                            <td><strong>#CC-<?= $ord['id'] ?></strong></td>
                                            <td><?= htmlspecialchars($ord['customer_name']) ?></td>
                                            <td><?= htmlspecialchars($ord['customer_governorat']) ?></td>
                                            <td><?= number_format($ord['total_price'], 2, '.', ' ') ?> TND</td>
                                            <td>
                                                <span class="badge <?= $status_class ?>"><?= htmlspecialchars($ord['status']) ?></span>
                                            </td>
                                            <td><?= date('d/m/Y H:i', strtotime($ord['created_at'])) ?></td>
                                            <td>
                                                <button type="button" class="btn-icon view-order-btn" data-id="<?= $ord['id'] ?>" title="Détails de la commande">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 2. PRODUCTS LIST VIEW -->
            <?php if ($tab === 'products'): ?>
                <div class="admin-card">
                    <div class="card-body-admin table-responsive">
                        <table class="admin-table" id="table-products">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Produit</th>
                                    <th>Catégorie</th>
                                    <th>Tailles</th>
                                    <th>Couleurs</th>
                                    <th>Prix</th>
                                    <th>Disponibilité</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($products)): ?>
                                    <tr>
                                        <td colspan="8" style="text-align: center; color: var(--admin-text-muted);">
                                                    Aucun produit dans le catalogue. Cliquez sur "Ajouter un produit" pour commencer.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($products as $prod): 
                                        $prod_images = $product_images_map[(int)$prod['id']] ?? [];
                                        $primary_image = $prod_images[0] ?? '';
                                        $has_img = !empty($primary_image);
                                    ?>
                                        <tr data-id="<?= $prod['id'] ?>" 
                                            data-name="<?= htmlspecialchars($prod['name']) ?>"
                                            data-desc="<?= htmlspecialchars($prod['description'] ?? '') ?>"
                                            data-price="<?= $prod['price'] ?>"
                                            data-category="<?= htmlspecialchars($prod['category']) ?>"
                                            data-sizes="<?= htmlspecialchars($prod['sizes']) ?>"
                                            data-colors="<?= htmlspecialchars($prod['colors']) ?>"
                                            data-available="<?= $prod['available'] ?>"
                                            data-image="<?= htmlspecialchars($primary_image) ?>"
                                            data-images="<?= htmlspecialchars(json_encode($prod_images), ENT_QUOTES, 'UTF-8') ?>">
                                            
                                            <td>
                                                <?php if ($has_img): ?>
                                                    <img src="<?= htmlspecialchars($primary_image) ?>" class="tbl-prod-img" alt="Photo">
                                                    <?php if (count($prod_images) > 1): ?>
                                                        <div class="tbl-img-count">+<?= count($prod_images) - 1 ?></div>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <div class="tbl-prod-img" style="background: linear-gradient(135deg, #f5f4f0, #e2e0d9); display:flex; align-items:center; justify-content:center; color:#c5a059; font-size:12px; font-weight:600;">
                                                        <?= htmlspecialchars(substr($prod['name'], 0, 2)) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <td>
                                                <div class="tbl-prod-name"><?= htmlspecialchars($prod['name']) ?></div>
                                            </td>
                                            
                                            <td><?= htmlspecialchars($prod['category']) ?></td>
                                            <td><small><?= htmlspecialchars($prod['sizes']) ?></small></td>
                                            <td><small><?= htmlspecialchars($prod['colors']) ?></small></td>
                                            <td><strong><?= number_format($prod['price'], 2, '.', ' ') ?> TND</strong></td>
                                            
                                            <td>
                                                <?php if ((int)$prod['available'] === 1): ?>
                                                    <a href="admin.php?action=toggle_avail&id=<?= $prod['id'] ?>" class="badge success" title="Cliquez pour changer la disponibilité">Disponible</a>
                                                <?php else: ?>
                                                    <a href="admin.php?action=toggle_avail&id=<?= $prod['id'] ?>" class="badge danger" title="Cliquez pour changer la disponibilité">Épuisé</a>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <td>
                                                <button type="button" class="btn-icon edit edit-product-btn" title="Modifier le produit">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                                </button>
                                                <a href="admin.php?action=delete_product&id=<?= $prod['id'] ?>" class="btn-icon delete" title="Supprimer le produit" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce produit ?');">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 3. ORDERS COMPLETE LIST VIEW -->
            <?php if ($tab === 'orders'): ?>
                <div class="admin-card">
                    <div class="card-body-admin table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Client / Téléphone</th>
                                    <th>Adresse de Livraison</th>
                                    <th>Montant Total</th>
                                    <th>Statut Commande</th>
                                    <th>Date Réception</th>
                                    <th>Détails</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($orders)): ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; color: var(--admin-text-muted);">
                                            Aucune commande passée sur la boutique.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($orders as $ord): ?>
                                        <tr data-order-id="<?= $ord['id'] ?>">
                                            <td><strong>#CC-<?= $ord['id'] ?></strong></td>
                                            <td>
                                                <strong><?= htmlspecialchars($ord['customer_name']) ?></strong><br>
                                                <small><?= htmlspecialchars($ord['customer_phone']) ?></small>
                                            </td>
                                            <td>
                                                <span class="badge secondary"><?= htmlspecialchars($ord['customer_governorat']) ?></span><br>
                                                <small style="color: var(--admin-text-muted);"><?= htmlspecialchars($ord['customer_address']) ?></small>
                                            </td>
                                            <td><strong><?= number_format($ord['total_price'], 2, '.', ' ') ?> TND</strong></td>
                                            <td>
                                                <!-- Dropdown to modify status via AJAX instantly -->
                                                <select class="admin-form-control order-status-select" style="padding: 6px 12px; font-size:12px; font-weight:600; width:auto; display:inline-block;" data-id="<?= $ord['id'] ?>">
                                                    <option value="pending" <?= $ord['status'] === 'pending' ? 'selected' : '' ?>>En Attente</option>
                                                    <option value="confirmed" <?= $ord['status'] === 'confirmed' ? 'selected' : '' ?>>Confirmée</option>
                                                    <option value="shipped" <?= $ord['status'] === 'shipped' ? 'selected' : '' ?>>Expédiée</option>
                                                    <option value="completed" <?= $ord['status'] === 'completed' ? 'selected' : '' ?>>Livrée</option>
                                                    <option value="cancelled" <?= $ord['status'] === 'cancelled' ? 'selected' : '' ?>>Annulée</option>
                                                </select>
                                            </td>
                                            <td><?= date('d/m/Y H:i', strtotime($ord['created_at'])) ?></td>
                                            <td>
                                                <button type="button" class="btn-icon view-order-btn" data-id="<?= $ord['id'] ?>" title="Détails de la commande">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- PRODUCT ADD / EDIT MODAL -->
    <div class="admin-modal-overlay" id="product-modal">
        <div class="admin-modal">
            <form action="admin.php?tab=products" method="POST" enctype="multipart/form-data">
                <div class="admin-modal-header">
                    <h3 class="admin-modal-title" id="product-modal-title">Ajouter un Produit</h3>
                    <span class="admin-modal-close" id="product-modal-close">&times;</span>
                </div>
                
                <div class="admin-modal-body">
                    <input type="hidden" name="action" value="save_product">
                    <input type="hidden" name="id" id="prod-id" value="0">
                    <input type="hidden" name="existing_image" id="prod-existing-image" value="">
                    <input type="hidden" name="existing_images" id="prod-existing-images" value="[]">

                    <div class="admin-form-group">
                        <label class="admin-form-label" for="prod-name">Nom du Produit *</label>
                        <input type="text" class="admin-form-control" name="name" id="prod-name" required placeholder="Ex: Robe Carthage">
                    </div>

                    <div class="admin-form-group">
                        <label class="admin-form-label" for="prod-desc">Description</label>
                        <textarea class="admin-form-control" name="description" id="prod-desc" placeholder="Décrivez le tissu, la coupe, etc."></textarea>
                    </div>

                    <div class="admin-form-row">
                        <div class="admin-form-group">
                            <label class="admin-form-label" for="prod-price">Prix (en TND) *</label>
                            <input type="number" step="0.01" class="admin-form-control" name="price" id="prod-price" required placeholder="0.00">
                        </div>
                        <div class="admin-form-group">
                            <label class="admin-form-label" for="prod-category">Catégorie *</label>
                            <select class="admin-form-control" name="category" id="prod-category" required>
                                <option value="" disabled selected>Choisir une catégorie</option>
                                <option value="Robes">Robes</option>
                                <option value="Jupes">Jupes</option>
                                <option value="Ensembles">Ensembles</option>
                                <option value="Blouses">Blouses</option>
                                <option value="Pantalons">Pantalons</option>
                            </select>
                        </div>
                    </div>

                    <div class="admin-form-group">
                        <label class="admin-form-label">Tailles Disponibles *</label>
                        <div class="size-checkbox-group">
                            <?php foreach (['S', 'M', 'L', 'XL', 'XXL', '3XL', '4XL', 'Unique'] as $sz): ?>
                                <div class="size-checkbox-item">
                                    <input type="checkbox" name="sizes[]" value="<?= $sz ?>" id="sz-<?= $sz ?>" class="prod-size-chk">
                                    <label for="sz-<?= $sz ?>" class="size-checkbox-label"><?= $sz ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="admin-form-group">
                        <label class="admin-form-label" for="prod-colors">Couleurs (séparées par des virgules) *</label>
                        <input type="text" class="admin-form-control" name="colors" id="prod-colors" required placeholder="Ex: Noir, Blanc, Rose Poudré">
                    </div>

                    <!-- Image Upload Widget -->
                    <div class="admin-form-group">
                        <label class="admin-form-label">Images du Produit</label>
                        <div class="image-upload-wrapper">
                            <div class="upload-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            </div>
                            <div class="upload-text">Déposez vos images ici ou <strong>cliquez pour parcourir</strong></div>
                            <input type="file" name="product_images[]" id="prod-image-file" accept="image/*" multiple>
                        </div>
                        <div class="image-preview-container" id="prod-image-preview-container">
                            <div class="image-preview-grid" id="prod-image-preview-grid"></div>
                        </div>
                    </div>

                    <div class="admin-form-group" style="display: flex; align-items: center; gap: 8px; margin-top: 10px;">
                        <input type="checkbox" name="available" id="prod-avail" value="1" checked style="width: auto; cursor:pointer;">
                        <label for="prod-avail" style="font-size:14px; font-weight:500; cursor:pointer;">Disponible à la vente immédiate</label>
                    </div>
                </div>

                <div class="admin-modal-footer">
                    <button type="button" class="btn-admin-secondary" id="btn-product-modal-cancel">Annuler</button>
                    <button type="submit" class="btn-admin-action">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ORDER DETAILS MODAL -->
    <div class="admin-modal-overlay" id="order-modal">
        <div class="admin-modal" style="max-width: 500px;">
                <div class="admin-modal-header">
                <h3 class="admin-modal-title">Détails de la Commande</h3>
                <span class="admin-modal-close" id="order-modal-close">&times;</span>
            </div>
            
            <div class="admin-modal-body">
                <div style="font-size:14px; line-height: 1.6;">
                    <p><strong>Commande :</strong> <span id="ord-id-txt"></span></p>
                    <p><strong>Date :</strong> <span id="ord-date-txt"></span></p>
                    
                    <div style="margin-top: 16px; border-top: 1px solid var(--admin-border); padding-top: 16px;">
                        <h4 style="font-size:14px; font-weight:600; margin-bottom: 8px;">Coordonnées Client</h4>
                        <p><strong>Nom :</strong> <span id="ord-name-txt"></span></p>
                        <p><strong>Téléphone :</strong> <span id="ord-phone-txt"></span></p>
                        <p><strong>Gouvernorat :</strong> <span id="ord-gov-txt"></span></p>
                        <p><strong>Adresse :</strong> <span id="ord-addr-txt"></span></p>
                    </div>

                    <!-- Items list -->
                    <div class="order-detail-items">
                        <h4 style="font-size:14px; font-weight:600; margin-bottom: 12px;">Articles Commandés</h4>
                        <div id="ord-items-list">
                            <!-- Populated dynamically via JS -->
                        </div>
                    </div>
                    
                    <div style="margin-top: 16px; border-top: 1px solid var(--admin-border); padding-top: 16px; display: flex; justify-content: space-between; font-size:16px; font-weight:700;">
                        <span>Montant Total</span>
                        <span><span id="ord-total-txt"></span> TND</span>
                    </div>
                </div>
            </div>
            
                <div class="admin-modal-footer">
                <button type="button" class="btn-admin-secondary" id="btn-order-modal-close">Fermer</button>
            </div>
        </div>
    </div>

    <!-- SCRIPTS -->
    <script src="assets/js/admin.js"></script>
</body>
</html>
