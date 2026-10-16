<?php
// index.php - Tunisian Brand by Sabri Storefront

// 1. Initialize Database
require_once __DIR__ . '/db.php';
// Notification helpers
require_once __DIR__ . '/admin_config.php';

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

// 2. Handle POST AJAX request for order submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw_input = file_get_contents('php://input');
    $input_data = json_decode($raw_input, true);

    if (isset($input_data['action']) && $input_data['action'] === 'submit_order') {
        header('Content-Type: application/json');
        
        $order = $input_data['order_data'] ?? null;
        if (!$order) {
            echo json_encode(['success' => false, 'message' => 'Données de commande invalides.']);
            exit;
        }

        // Validate basic inputs
        $name = trim($order['customer_name'] ?? '');
        $phone = trim($order['customer_phone'] ?? '');
        $governorat = trim($order['customer_governorat'] ?? '');
        $address = trim($order['customer_address'] ?? '');
        $items = $order['items'] ?? [];
        $total_price = floatval($order['total_price'] ?? 0);

        if (empty($name) || empty($phone) || empty($governorat) || empty($address) || empty($items)) {
            echo json_encode(['success' => false, 'message' => 'Veuillez remplir tous les champs obligatoires.']);
            exit;
        }

        try {
            // Start transaction
            $pdo->beginTransaction();

            // Insert into orders
            $stmt_order = $pdo->prepare("
                INSERT INTO orders (customer_name, customer_phone, customer_governorat, customer_address, total_price, status) 
                VALUES (?, ?, ?, ?, ?, 'pending')
            ");
            $stmt_order->execute([$name, $phone, $governorat, $address, $total_price]);
            $order_id = $pdo->lastInsertId();

            // Insert each item
            $stmt_item = $pdo->prepare("
                INSERT INTO order_items (order_id, product_id, product_name, size, color, quantity, price) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($items as $item) {
                $stmt_item->execute([
                    $order_id,
                    intval($item['id']),
                    $item['name'],
                    $item['size'],
                    $item['color'],
                    intval($item['qty']),
                    floatval($item['price'])
                ]);
            }

            // Commit
            $pdo->commit();
            // Send admin notification (best-effort)
            try {
                admin_notify_new_order($order_id, $name, $total_price);
            } catch (Exception $e) {
                // ignore
            }

            echo json_encode(['success' => true, 'order_id' => $order_id]);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la sauvegarde : ' . $e->getMessage()]);
            exit;
        }
    }
}

// 3. Fetch categories and products for display
try {
    // Get unique categories
    $stmt_cats = $pdo->query("SELECT DISTINCT category FROM products WHERE available = 1");
    $categories = $stmt_cats->fetchAll(PDO::FETCH_COLUMN);

    // Get all products: disponibles d'abord, puis plus récents
    $stmt_prods = $pdo->query("SELECT * FROM products ORDER BY available DESC, id DESC");
    $products = $stmt_prods->fetchAll();
    $product_images_map = get_product_images_map($pdo, $products);
} catch (PDOException $e) {
    die("Erreur de récupération des données : " . $e->getMessage());
}

// Tunisian Governorates
$governorats = [
    'Ariana', 'Béja', 'Ben Arous', 'Bizerte', 'Gabès', 'Gafsa', 'Jendouba', 
    'Kairouan', 'Kasserine', 'Kébili', 'Kef', 'Mahdia', 'Manouba', 'Médenine', 
    'Monastir', 'Nabeul', 'Sfax', 'Sidi Bouzid', 'Siliana', 'Sousse', 'Tataouine', 
    'Tozeur', 'Tunis', 'Zaghouan'
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tunisian Brand by Sabri | Livraison toute la Tunisie</title>
    <meta name="description" content="Tunisian Brand🇹🇳by Sabri - Instagram : ✨Carthage chic✨ | 📞 26024279 | Livraison toute la Tunisie.">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <!-- SITE HEADER -->
    <header class="site-header" id="main-header">
        <div class="container header-container">
            <!-- Logo Section -->
            <div class="logo-container">
                <a href="index.php" class="shop-logo-link" aria-label="Logo de la boutique">
                    <img src="assets/logo.png?v=<?= file_exists(__DIR__ . '/assets/logo.png') ? filemtime(__DIR__ . '/assets/logo.png') : (file_exists(__DIR__ . '/assets/logo.jpg') ? filemtime(__DIR__ . '/assets/logo.jpg') : time()) ?>" alt="Logo de la boutique" class="logo-img">
                </a>
            </div>

            <!-- Navigation menu -->
            <nav class="nav-menu">
                <a href="#store-section" class="nav-link active">Boutique</a>
                <a href="#about-section" class="nav-link">À Propos</a>
                <a href="#contact-section" class="nav-link">Contact</a>
            </nav>

            <!-- Actions (Cart) -->
            <div class="header-actions">
                <button class="action-btn cart-btn" aria-label="Ouvrir le panier">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                    <span class="cart-count">0</span>
                </button>
            </div>
        </div>
    </header>

    <!-- HERO SECTION -->
    <section class="hero-section">
        <div class="container hero-grid">
            <div class="hero-content">
                <span class="hero-tagline">Collection Premium</span>
                <h1 class="hero-title">
                    L'Élégance Tunisienne 
                    <span>Carthage chic</span>
                </h1>
                <p class="hero-desc">
                    Affirmez votre style avec des créations uniques et raffinées. Une collection exclusive de robes, jupes et ensembles conçus pour sublimer la femme moderne en toute simplicité.
                </p>
                <a href="#store-section" class="btn-primary">Découvrir la boutique</a>
            </div>
        </div>
    </section>

    <!-- STORE SECTION -->
    <section class="store-section" id="store-section">
        <div class="container">
            <!-- Search Bar Wrapper -->
            <div class="search-bar-wrapper">
                <div class="search-input-container">
                    <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" id="search-input" class="search-input" placeholder="Rechercher une robe, jupe, ensemble..." aria-label="Rechercher des articles">
                </div>
            </div>

            <div class="section-header">
                <h2 class="section-title">Notre Collection</h2>
            </div>

            <!-- Category Filters -->
            <div class="category-filter">
                <button class="filter-btn active" data-category="all">Toutes</button>
                <?php foreach ($categories as $cat): ?>
                    <button class="filter-btn" data-category="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></button>
                <?php endforeach; ?>
            </div>

            <!-- Products Catalog Grid -->
            <div class="product-grid" id="product-grid">
                <?php if (empty($products)): ?>
                    <p class="empty-state">
                        Aucun produit n'est disponible pour le moment.
                    </p>
                <?php else: ?>
                    <?php foreach ($products as $prod): 
                        $is_available = (int)$prod['available'] === 1;
                        $sizes_attr = htmlspecialchars($prod['sizes']);
                        $colors_attr = htmlspecialchars($prod['colors']);
                        $desc_attr = htmlspecialchars($prod['description'] ?? '');
                        $prod_images = $product_images_map[(int)$prod['id']] ?? [];
                        $primary_image = $prod_images[0] ?? '';
                        $has_image = !empty($primary_image);
                        $img_path = htmlspecialchars($primary_image);
                        $images_json_attr = htmlspecialchars(json_encode($prod_images), ENT_QUOTES, 'UTF-8');
                    ?>
                        <div class="product-card" 
                             data-id="<?= $prod['id'] ?>"
                             data-name="<?= htmlspecialchars($prod['name']) ?>"
                             data-desc="<?= $desc_attr ?>"
                             data-price="<?= $prod['price'] ?>"
                             data-category="<?= htmlspecialchars($prod['category']) ?>"
                             data-sizes="<?= $sizes_attr ?>"
                             data-colors="<?= $colors_attr ?>"
                             data-available="<?= $prod['available'] ?>"
                             data-image="<?= $has_image ? $img_path : '' ?>"
                             data-images="<?= $images_json_attr ?>">
                             
                            <div class="product-image-container">
                                <?php if ($has_image): ?>
                                    <img src="<?= $img_path ?>" alt="<?= htmlspecialchars($prod['name']) ?>">
                                <?php else: ?>
                                    <div class="product-image-fallback">
                                        <?= htmlspecialchars(substr($prod['name'], 0, 2)) ?>
                                        <span>Tunisian Brand</span>
                                    </div>
                                <?php endif; ?>
                                
                                <button type="button" class="quickview-btn">Aperçu rapide</button>
                            </div>

                            <div class="product-info">
                                <div class="product-info-details">
                                    <span class="product-category"><?= htmlspecialchars($prod['category']) ?></span>
                                    <h3 class="product-title"><?= htmlspecialchars($prod['name']) ?></h3>
                                    <div class="product-price">
                                        <?= number_format($prod['price'], 2, '.', ' ') ?> TND
                                        <?php if (!$is_available): ?>
                                            <span class="sold-out-indicator">(Épuisé)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <button type="button" class="quick-add-btn" aria-label="Ajouter au panier">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- ABOUT SECTION -->
    <section class="store-section info-section" id="about-section">
        <div class="container content-panel content-panel--center">
            <div class="section-header">
                <h2 class="section-title">À Propos de Tunisian Brand🇹🇳by Sabri</h2>
            </div>
            <p class="section-copy">
                Tunisian Brand🇹🇳by Sabri propose une mode féminine moderne, simple et facile à porter. Nous sélectionnons des pièces élégantes avec un style tunisien affirmé, pensées pour un usage quotidien et des sorties habillées.
            </p>
            <p class="section-copy">
               <br>Livraison toute la Tunisie.
            </p>
        </div>
    </section>

    <!-- CONTACT SECTION -->
    <section class="store-section info-section" id="contact-section">
        <div class="container content-panel content-panel--center">
            <div class="section-header">
                <h2 class="section-title">Contact & Suivi</h2>
            </div>
            <div class="contact-actions">
                <a href="https://wa.me/21626024279" target="_blank" rel="noopener noreferrer" class="social-button social-button--whatsapp">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.52 3.48A11.81 11.81 0 0 0 12.06 0C5.43 0 .04 5.39.04 12.02c0 2.11.55 4.16 1.59 5.97L0 24l6.16-1.61a11.94 11.94 0 0 0 5.92 1.51h.01c6.63 0 12.01-5.39 12.01-12.02 0-3.22-1.25-6.24-3.58-8.4Zm-8.43 18.37h-.01a9.9 9.9 0 0 1-5.05-1.38l-.36-.21-3.67.96.98-3.57-.24-.38a9.89 9.89 0 0 1-1.52-5.25c0-5.46 4.44-9.9 9.91-9.9 2.65 0 5.13 1.03 7.02 2.91a9.85 9.85 0 0 1 2.9 7c-.01 5.47-4.45 9.82-9.96 9.82Zm5.77-7.9c-.31-.16-1.82-.9-2.1-1s-.49-.16-.7.16-.8 1-1 .1-.75-.16-1.27-.48a5.2 5.2 0 0 1-1.5-1.84c-.16-.28-.02-.43.12-.58.12-.12.27-.32.4-.47.12-.16.16-.27.24-.45.08-.16.04-.31-.02-.43-.06-.16-.7-1.68-.96-2.3-.25-.58-.5-.5-.7-.5h-.6c-.2 0-.53.08-.8.4-.27.32-1 1-.98 2.45s1 2.84 1.15 3.03c.16.2 2.1 3.2 5.1 4.48.72.31 1.28.5 1.72.64.72.23 1.37.2 1.88.12.57-.09 1.82-.74 2.08-1.46.26-.72.26-1.34.18-1.46-.08-.12-.29-.2-.6-.36Z"/></svg>
                    <span>WhatsApp </span>
                </a>
                <a href="https://www.instagram.com/carthage_chic?igsh=bnFhMzJlanFtcDd0" target="_blank" rel="noopener noreferrer" class="social-button social-button--instagram">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7.5 2A5.5 5.5 0 0 0 2 7.5v9A5.5 5.5 0 0 0 7.5 22h9a5.5 5.5 0 0 0 5.5-5.5v-9A5.5 5.5 0 0 0 16.5 2h-9Zm0 2h9A3.5 3.5 0 0 1 20 7.5v9a3.5 3.5 0 0 1-3.5 3.5h-9A3.5 3.5 0 0 1 4 16.5v-9A3.5 3.5 0 0 1 7.5 4Zm10.25 1.25a1.25 1.25 0 1 0 0 2.5 1.25 1.25 0 0 0 0-2.5ZM12 7a5 5 0 1 0 0 10 5 5 0 0 0 0-10Zm0 2a3 3 0 1 1 0 6 3 3 0 0 1 0-6Z"/></svg>
                    <span>Instagram </span>
                </a>
            </div>
            <p class="section-copy section-copy--spaced">
                
            </p>
        </div>
    </section>
    <!-- FOOTER -->
    <footer class="site-footer">
            <div class="container footer-grid">
            <div>
                <h4 class="footer-title">Tunisian Brand by Sabri</h4>
                <p class="footer-desc">
                    Boutique tunisienne moderne et simple, pensée pour une expérience claire sur mobile.
                </p>
            </div>
            <div>
                <h4 class="footer-title">Navigation</h4>
                <ul class="footer-links">
                    <li><a href="#store-section">Boutique</a></li>
                    <li><a href="#about-section">À Propos</a></li>
                    <li><a href="#contact-section">Contact</a></li>
                </ul>
            </div>
            <div>
                <h4 class="footer-title">Contact</h4>
                <p class="footer-desc footer-desc--spaced">
                    <a href="https://wa.me/21626024279" target="_blank" rel="noopener noreferrer">WhatsApp : 26024279</a>
                </p>
                <p class="footer-desc">
                    <a href="https://www.instagram.com/carthage_chic?igsh=bnFhMzJlanFtcDd0" target="_blank" rel="noopener noreferrer">Instagram : ✨Carthage chic✨</a>
                </p>
            </div>
        </div>
        <div class="container footer-bottom">
            <div class="footer-logo-link">
                &copy; <?= date('Y') ?> Tunisian Brand🇹🇳by Sabri. Tous droits réservés.
            </div>
            <div class="footer-note">
                Livraison toute la Tunisie
            </div>
        </div>
    </footer>

    <!-- SIDE DRAWER: SHOPPING CART -->
    <div class="cart-drawer-overlay">
        <div class="cart-drawer">
            <div class="cart-header">
                <h3 class="cart-title">Votre Panier</h3>
                <button class="close-btn cart-drawer-close" aria-label="Fermer le panier">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            
            <div class="cart-body">
                <!-- Loaded via JS -->
            </div>

            <div class="cart-footer">
                <div class="cart-summary-row">
                    <span>Sous-total</span>
                    <span><strong class="cart-total-val">0.00</strong> TND</span>
                </div>
                <div class="cart-summary-row cart-summary-row--muted">
                    <span>Livraison (Frais fixes)</span>
                    <span><strong class="cart-delivery-val">8.00</strong> TND</span>
                </div>
                <div class="cart-summary-row total">
                    <span>Total (incl. livraison)</span>
                    <span><strong class="cart-grand-total-val">0.00</strong> TND</span>
                </div>
                <button class="checkout-btn">Passer la commande</button>
            </div>
        </div>
    </div>
    <!-- PRODUCT DETAIL MODAL -->
    <div class="product-modal-overlay">
        <div class="product-modal">
            <button class="modal-close" aria-label="Fermer la vue">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
            <div class="modal-img-container">
                <img src="" alt="" class="modal-img">
                <div class="modal-thumbs" id="modal-thumbs"></div>
            </div>
            <div class="modal-content">
                <h3 class="modal-title">Nom du produit</h3>
                <div class="modal-price"><span class="modal-price-val">0.00</span> TND</div>
                
                <p class="modal-desc">Description du produit</p>

                <!-- Taille option selection -->
                <div class="options-group">
                    <span class="options-label">Taille</span>
                    <div class="size-picker">
                        <!-- Loaded dynamically -->
                    </div>
                </div>
                <!-- Couleur option selection -->
                <div class="options-group">
                    <span class="options-label">Couleur</span>
                    <div class="color-picker">
                        <!-- Loaded dynamically -->
                    </div>
                </div>
                <!-- Qty and Add to Cart Button -->
                <div class="modal-action-row">
                    <div class="modal-qty-select">
                        <label for="modal-qty" class="options-label modal-qty-label">Qté</label>
                        <select id="modal-qty">
                            <option value="1">1</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                            <option value="4">4</option>
                            <option value="5">5</option>
                        </select>
                    </div>
                    <button class="add-to-cart-btn">Ajouter au Panier</button>
                </div>
            </div>
        </div>
    </div>
    <!-- SCRIPTS -->
    <script src="assets/js/app.js"></script>
</body>
</html>
