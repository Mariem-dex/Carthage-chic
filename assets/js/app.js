// app.js - Storefront logic for Carthage Chic

document.addEventListener('DOMContentLoaded', () => {
    // --- CART STATE ---
    let cart = JSON.parse(localStorage.getItem('carthage_chic_cart')) || [];
    
    // --- ELEMENT REFERENCES ---
    const cartCountEl = document.querySelector('.cart-count');
    const cartDrawerOverlay = document.querySelector('.cart-drawer-overlay');
    const cartDrawerCloseBtn = document.querySelector('.cart-drawer-close');
    const cartBtn = document.querySelector('.cart-btn');
    const cartBody = document.querySelector('.cart-body');
    const cartTotalEl = document.querySelector('.cart-total-val');
    const cartDeliveryEl = document.querySelector('.cart-delivery-val');
    const cartGrandTotalEl = document.querySelector('.cart-grand-total-val');
    const checkoutBtn = document.querySelector('.checkout-btn');
    
    const productModalOverlay = document.querySelector('.product-modal-overlay');
    const modalCloseBtn = document.querySelector('.modal-close');
    let imageLightboxOverlay = null;
    let imageLightboxImages = [];
    let imageLightboxIndex = 0;
    
    let checkoutModalOverlay = document.querySelector('.checkout-modal-overlay');
    let checkoutCloseBtn = document.querySelector('.checkout-close');
    let checkoutForm = document.getElementById('checkout-form');
    const cartFooter = document.querySelector('.cart-footer');

    // Reset overlays on load to avoid invisible layers blocking clicks.
    [cartDrawerOverlay, productModalOverlay, checkoutModalOverlay].forEach((overlay) => {
        if (overlay) {
            overlay.classList.remove('active');
        }
    });
    
    const categoryBtns = document.querySelectorAll('.filter-btn');
    const productCards = document.querySelectorAll('.product-card');
    const searchInput = document.getElementById('search-input');
    const governorats = [
        'Ariana', 'Béja', 'Ben Arous', 'Bizerte', 'Gabès', 'Gafsa', 'Jendouba',
        'Kairouan', 'Kasserine', 'Kébili', 'Kef', 'Mahdia', 'Manouba', 'Médenine',
        'Monastir', 'Nabeul', 'Sfax', 'Sidi Bouzid', 'Siliana', 'Sousse', 'Tataouine',
        'Tozeur', 'Tunis', 'Zaghouan'
    ];

    // --- INITIALIZE ---
    updateCartUI();

    function ensureCheckoutModal() {
        if (checkoutModalOverlay) {
            return checkoutModalOverlay;
        }

        const modal = document.createElement('div');
        modal.className = 'checkout-modal-overlay modal-overlay';
        modal.innerHTML = `
            <div class="product-modal checkout-modal-card">
                <button class="modal-close checkout-close" aria-label="Fermer le checkout">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
                <div class="checkout-modal-header">
                    <h3 class="checkout-title">Finaliser votre Commande</h3>
                </div>
                <div class="checkout-modal-body">
                    <form id="checkout-form">
                        <div class="checkout-grid">
                            <div>
                                <div class="form-group">
                                    <label class="form-label" for="cust-name">Nom Complet *</label>
                                    <input type="text" class="form-control" id="cust-name" name="name" required placeholder="Ex: Fatma Ben Ali">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="cust-phone">Numéro de Téléphone *</label>
                                    <input type="tel" class="form-control" id="cust-phone" name="phone" required placeholder="Ex: 98 765 432">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="cust-gov">Gouvernorat *</label>
                                    <select class="form-control" id="cust-gov" name="governorat" required>
                                        <option value="" disabled selected>Sélectionner votre ville</option>
                                        ${governorats.map(gov => `<option value="${gov}">${gov}</option>`).join('')}
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="cust-addr">Adresse de Livraison complète *</label>
                                    <textarea class="form-control" id="cust-addr" name="address" required placeholder="Ex: Rue des Phéniciens, Imm Carthage, Appt 4"></textarea>
                                </div>
                            </div>
                            <div>
                                <div class="checkout-summary-box">
                                    <h4 class="checkout-summary-title">Récapitulatif</h4>
                                    <div class="checkout-mini-items"></div>
                                    <div class="cart-summary-row checkout-summary-row checkout-summary-row--muted">
                                                                <span>Sous-total</span>
                                                                <span><strong class="checkout-subtotal-val">0.00</strong> TND</span>
                                                            </div>
                                                            <div class="cart-summary-row checkout-summary-row checkout-summary-row--muted">
                                                                <span>Livraison</span>
                                                                <span><strong class="checkout-delivery-val">8.00</strong> TND</span>
                                                            </div>
                                                            <div class="cart-summary-row total checkout-summary-row checkout-summary-row--total">
                                                                <span>Total à Payer</span>
                                                                <span><strong class="checkout-total-val">0.00</strong> TND</span>
                                                            </div>
                                </div>
                                <button type="submit" class="confirm-order-btn">Valider la Commande</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        checkoutModalOverlay = modal;
        checkoutCloseBtn = modal.querySelector('.checkout-close');
        checkoutForm = modal.querySelector('#checkout-form');
        bindCheckoutModalEvents();
        bindCheckoutForm();
        return checkoutModalOverlay;
    }

    function ensureImageLightbox() {
        if (imageLightboxOverlay) {
            return imageLightboxOverlay;
        }

        const overlay = document.createElement('div');
        overlay.className = 'image-lightbox-overlay modal-overlay';
        overlay.innerHTML = `
            <div class="image-lightbox" role="dialog" aria-modal="true" aria-label="Aperçu image produit">
                <button class="modal-close image-lightbox-close" aria-label="Fermer l'aperçu image">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
                <button class="image-lightbox-nav image-lightbox-prev" type="button" aria-label="Image précédente">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                </button>
                <button class="image-lightbox-nav image-lightbox-next" type="button" aria-label="Image suivante">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                </button>
                <div class="image-lightbox-stage">
                    <img src="" alt="" class="image-lightbox-img">
                </div>
                <div class="image-lightbox-footer">
                    <div>
                        <h3 class="image-lightbox-title"></h3>
                        <p class="image-lightbox-caption">Cliquez sur une miniature pour changer l'image.</p>
                    </div>
                    <div class="image-lightbox-thumbs"></div>
                </div>
            </div>
        `;

        document.body.appendChild(overlay);
        imageLightboxOverlay = overlay;

        const closeBtn = overlay.querySelector('.image-lightbox-close');
        const prevBtn = overlay.querySelector('.image-lightbox-prev');
        const nextBtn = overlay.querySelector('.image-lightbox-next');

        const syncNavigation = () => {
            const hasMultiple = imageLightboxImages.length > 1;
            prevBtn.style.display = hasMultiple ? 'flex' : 'none';
            nextBtn.style.display = hasMultiple ? 'flex' : 'none';
        };

        const goToImage = (direction) => {
            if (imageLightboxImages.length === 0) return;
            imageLightboxIndex = (imageLightboxIndex + direction + imageLightboxImages.length) % imageLightboxImages.length;
            const nextSrc = imageLightboxImages[imageLightboxIndex];
            const img = overlay.querySelector('.image-lightbox-img');
            if (img) {
                img.src = nextSrc;
            }
            overlay.querySelectorAll('.image-lightbox-thumb').forEach((thumb) => {
                thumb.classList.toggle('active', thumb.getAttribute('data-src') === nextSrc);
            });
        };

        closeBtn.addEventListener('click', () => {
            overlay.classList.remove('active');
        });

        prevBtn.addEventListener('click', () => goToImage(-1));
        nextBtn.addEventListener('click', () => goToImage(1));

        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                overlay.classList.remove('active');
            }
        });

        document.addEventListener('keydown', (e) => {
            if (!overlay.classList.contains('active')) return;

            if (e.key === 'Escape') {
                overlay.classList.remove('active');
            }

            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                goToImage(-1);
            }

            if (e.key === 'ArrowRight') {
                e.preventDefault();
                goToImage(1);
            }
        });

        overlay._syncNavigation = syncNavigation;

        return imageLightboxOverlay;
    }

    function openImageLightbox(card) {
        const image = card.getAttribute('data-image');
        const imagesRaw = card.getAttribute('data-images') || '[]';
        const name = card.getAttribute('data-name') || 'Produit';

        let images = [];
        try {
            images = JSON.parse(imagesRaw);
        } catch (err) {
            images = image ? [image] : [];
        }

        if (!Array.isArray(images) || images.length === 0) {
            images = image ? [image] : [];
        }

        if (images.length === 0) {
            return;
        }

        const overlay = ensureImageLightbox();
        const lightboxImg = overlay.querySelector('.image-lightbox-img');
        const title = overlay.querySelector('.image-lightbox-title');
        const thumbsWrap = overlay.querySelector('.image-lightbox-thumbs');

        title.textContent = name;
        thumbsWrap.innerHTML = '';

        const setActiveImage = (src) => {
            lightboxImg.src = src;
            lightboxImg.alt = name;
            imageLightboxIndex = Math.max(0, images.indexOf(src));
            thumbsWrap.querySelectorAll('.image-lightbox-thumb').forEach((thumb) => {
                thumb.classList.toggle('active', thumb.getAttribute('data-src') === src);
            });
        };

        images.forEach((src, index) => {
            const thumbBtn = document.createElement('button');
            thumbBtn.type = 'button';
            thumbBtn.className = 'image-lightbox-thumb';
            thumbBtn.setAttribute('data-src', src);
            if (index === 0) {
                thumbBtn.classList.add('active');
            }

            const thumbImg = document.createElement('img');
            thumbImg.src = src;
            thumbImg.alt = `${name} miniature ${index + 1}`;
            thumbBtn.appendChild(thumbImg);

            thumbBtn.addEventListener('click', () => setActiveImage(src));
            thumbsWrap.appendChild(thumbBtn);
        });

        imageLightboxImages = images;
        setActiveImage(images[0]);
        overlay._syncNavigation?.();
        overlay.classList.add('active');
    }

    function bindCheckoutModalEvents() {
        if (checkoutCloseBtn) {
            checkoutCloseBtn.addEventListener('click', () => {
                checkoutModalOverlay?.classList.remove('active');
            });
        }

        if (checkoutModalOverlay) {
            checkoutModalOverlay.addEventListener('click', (e) => {
                if (e.target === checkoutModalOverlay) {
                    checkoutModalOverlay.classList.remove('active');
                }
            });
        }
    }

    function bindCheckoutForm() {
        if (!checkoutForm) return;

        checkoutForm.addEventListener('submit', handleCheckoutSubmit);
    }

    // Show a small inline error next to a control (container element) and remove after timeout
    function showFieldError(container, message) {
        if (!container) return;
        // remove existing
        const existing = container.querySelector('.inline-error');
        if (existing) existing.remove();
        const err = document.createElement('div');
        err.className = 'inline-error';
        err.textContent = message;
        container.appendChild(err);
        // accessibility: focus the first interactive inside container if possible
        const focusable = container.querySelector('button, input, select, textarea');
        if (focusable) focusable.focus();
        setTimeout(() => {
            err.classList.add('fade');
            setTimeout(() => err.remove(), 400);
        }, 2800);
    }

    function handleCheckoutSubmit(e) {
        e.preventDefault();

        const submitBtn = checkoutForm.querySelector('.confirm-order-btn');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Traitement en cours...';

        const formData = new FormData(checkoutForm);

        const payload = {
            customer_name: formData.get('name'),
            customer_phone: formData.get('phone'),
            customer_governorat: formData.get('governorat'),
            customer_address: formData.get('address'),
            items: cart,
            total_price: cart.reduce((sum, item) => sum + (item.price * item.qty), 0)
        };

        fetch('index.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ action: 'submit_order', order_data: payload })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showSuccessModal(payload, data.order_id);
            } else {
                showToast("Une erreur est survenue lors de la commande : " + data.message);
                submitBtn.disabled = false;
                submitBtn.textContent = 'Valider la Commande';
            }
        })
        .catch(() => {
            showToast("Erreur de connexion. Veuillez réessayer.");
            submitBtn.disabled = false;
            submitBtn.textContent = 'Valider la Commande';
        });
    }

    // --- FILTER AND SEARCH LOGIC ---
    function filterProducts() {
        const activeBtn = document.querySelector('.filter-btn.active');
        const category = activeBtn ? activeBtn.getAttribute('data-category') : 'all';
        const query = searchInput ? searchInput.value.toLowerCase().trim() : '';

        productCards.forEach(card => {
            const cardCategory = card.getAttribute('data-category');
            const name = card.getAttribute('data-name').toLowerCase();
            const desc = (card.getAttribute('data-desc') || '').toLowerCase();

            const matchesCategory = (category === 'all' || cardCategory === category);
            const matchesSearch = (name.includes(query) || desc.includes(query) || cardCategory.toLowerCase().includes(query));

            if (matchesCategory && matchesSearch) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    }

    // Category click listeners
    categoryBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            categoryBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            filterProducts();
        });
    });

    // Search typing listener
    if (searchInput) {
        searchInput.addEventListener('input', filterProducts);
    }

    // --- CART DRAWER OPEN/CLOSE ---
    const DELIVERY_FEE = 8.00; // fixed delivery fee in TND

    if (cartBtn) {
        cartBtn.addEventListener('click', () => {
            // Open the cart drawer (restore original behavior)
            renderCartItems();
            updateCartUI();
            cartDrawerOverlay.classList.add('active');
        });
    }

    // If there's a "Passer la commande" button in the cart drawer, open checkout centered
    if (checkoutBtn) {
        checkoutBtn.addEventListener('click', (e) => {
            e.preventDefault();
            if (cart.length === 0) {
                showToast("Votre panier est vide.");
                return;
            }
            ensureCheckoutModal();
            updateCheckoutSummary();
            // Close cart drawer if open
            cartDrawerOverlay.classList.remove('active');
            // Show checkout modal centered
            checkoutModalOverlay.classList.add('active');
            // Ensure focus on first input for accessibility
            const firstInput = checkoutModalOverlay.querySelector('.form-control');
            if (firstInput) firstInput.focus();
        });
    }

    if (cartDrawerCloseBtn) {
        cartDrawerCloseBtn.addEventListener('click', () => {
            cartDrawerOverlay.classList.remove('active');
        });
    }

    if (cartDrawerOverlay) {
        cartDrawerOverlay.addEventListener('click', (e) => {
            if (e.target === cartDrawerOverlay) {
                cartDrawerOverlay.classList.remove('active');
            }
        });
    }

    // --- PRODUCT MODAL DETAILS ---
    // Robust trigger handling for quickview and quick-add buttons.
    function findProductCard(el) {
        let current = el;
        while (current && current !== document.body) {
            if (current.classList && current.classList.contains('product-card')) {
                return current;
            }
            current = current.parentElement;
        }
        return null;
    }

    function handleProductTriggerClick(triggerEl) {
        if (!triggerEl) return;
        const card = findProductCard(triggerEl);
        if (card) {
            openProductModal(card);
        }
    }

    // Delegation (works for dynamically added cards)
    document.addEventListener('click', (e) => {
        const target = e.target;
        if (!target) return;
        const trigger = target.closest ? target.closest('.quickview-btn, .quick-add-btn, .product-image-container') : null;
        if (trigger) {
            if (trigger.classList.contains('product-image-container')) {
                const card = findProductCard(trigger);
                if (card) {
                    openImageLightbox(card);
                }
                return;
            }
            handleProductTriggerClick(trigger);
        }
    });

    // Direct binding fallback (helps in environments where delegation can be disrupted)
    document.querySelectorAll('.quickview-btn, .quick-add-btn, .product-image-container').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            if (btn.classList.contains('product-image-container') && e.target.closest('.quickview-btn, .quick-add-btn')) {
                return;
            }
            if (btn.classList.contains('product-image-container')) {
                const card = findProductCard(btn);
                if (card) {
                    openImageLightbox(card);
                }
                return;
            }
            handleProductTriggerClick(btn);
        });
    });

    if (modalCloseBtn) {
        modalCloseBtn.addEventListener('click', () => {
            productModalOverlay.classList.remove('active');
        });
    }

    if (productModalOverlay) {
        productModalOverlay.addEventListener('click', (e) => {
            if (e.target === productModalOverlay) {
                productModalOverlay.classList.remove('active');
            }
        });
    }

    // --- CHECKOUT MODAL OPEN/CLOSE ---
    if (checkoutBtn) {
        checkoutBtn.addEventListener('click', () => {
            if (cart.length === 0) {
                showToast("Votre panier est vide.");
                return;
            }
            ensureCheckoutModal();
            cartDrawerOverlay.classList.remove('active');
            checkoutModalOverlay.classList.add('active');
            updateCheckoutSummary();
        });
    }

    // --- SCROLL EFFECTS ---
    window.addEventListener('scroll', () => {
        const header = document.querySelector('.site-header');
        if (window.scrollY > 50) {
            header.classList.add('scroll-shadow');
        } else {
            header.classList.remove('scroll-shadow');
        }
    });

    // --- MAIN FUNCS: OPEN PRODUCT MODAL ---
    let selectedSizes = [];
    let selectedColors = [];

    function renderModalGallery(images, name) {
        const modalImg = productModalOverlay.querySelector('.modal-img');
        const thumbsWrap = productModalOverlay.querySelector('#modal-thumbs');

        const normalizedImages = Array.isArray(images) ? images.filter(Boolean) : [];
        thumbsWrap.innerHTML = '';

        if (normalizedImages.length > 0) {
            modalImg.src = normalizedImages[0];
            modalImg.style.display = 'block';

            normalizedImages.forEach((imgSrc, index) => {
                const thumbBtn = document.createElement('button');
                thumbBtn.type = 'button';
                thumbBtn.className = 'modal-thumb-btn';
                if (index === 0) {
                    thumbBtn.classList.add('active');
                }

                const thumbImg = document.createElement('img');
                thumbImg.src = imgSrc;
                thumbImg.alt = `${name} - vue ${index + 1}`;
                thumbBtn.appendChild(thumbImg);

                thumbBtn.addEventListener('click', () => {
                    modalImg.src = imgSrc;
                    thumbsWrap.querySelectorAll('.modal-thumb-btn').forEach((el) => el.classList.remove('active'));
                    thumbBtn.classList.add('active');
                });

                thumbsWrap.appendChild(thumbBtn);
            });

            const fallback = productModalOverlay.querySelector('.product-image-fallback');
            if (fallback) fallback.style.display = 'none';
        } else {
            modalImg.style.display = 'none';
            let fallback = productModalOverlay.querySelector('.product-image-fallback');
            if (!fallback) {
                fallback = document.createElement('div');
                fallback.className = 'product-image-fallback';
                productModalOverlay.querySelector('.modal-img-container').appendChild(fallback);
            }
            fallback.style.display = 'flex';
            fallback.innerHTML = `${name.substring(0, 2)}<span>CARTHAGE CHIC</span>`;
        }
    }

    function openProductModal(card) {
        const id = card.getAttribute('data-id');
        const name = card.getAttribute('data-name');
        const desc = card.getAttribute('data-desc');
        const price = parseFloat(card.getAttribute('data-price'));
        const image = card.getAttribute('data-image');
        const imagesRaw = card.getAttribute('data-images') || '[]';
        let images = [];
        try {
            images = JSON.parse(imagesRaw);
        } catch (err) {
            images = image ? [image] : [];
        }
        if (!Array.isArray(images) || images.length === 0) {
            images = image ? [image] : [];
        }
        const available = parseInt(card.getAttribute('data-available'));
        const sizesStr = card.getAttribute('data-sizes') || '';
        const colorsStr = card.getAttribute('data-colors') || '';
        
        // Reset selections
        selectedSizes = [];
        selectedColors = [];

        // Populate Modal Fields
        productModalOverlay.querySelector('.modal-title').textContent = name;
        productModalOverlay.querySelector('.modal-price-val').textContent = price.toFixed(2);
        productModalOverlay.querySelector('.modal-desc').textContent = desc || "Aucune description disponible.";

        renderModalGallery(images, name);

        // Render Sizes
        const sizePicker = productModalOverlay.querySelector('.size-picker');
        sizePicker.innerHTML = '';
        if (sizesStr) {
            const sizes = sizesStr.split(',').map(s => s.trim()).filter(s => s);
            sizes.forEach(size => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'size-option-btn';
                btn.textContent = size;
                btn.addEventListener('click', () => {
                    const quantity = parseInt(qtySelect.value, 10) || 1;
                    if (quantity > 1) {
                        btn.classList.toggle('active');
                        selectedSizes = Array.from(sizePicker.querySelectorAll('.size-option-btn.active')).map(b => b.textContent.trim());
                    } else {
                        sizePicker.querySelectorAll('.size-option-btn').forEach(b => b.classList.remove('active'));
                        btn.classList.add('active');
                        selectedSizes = [size];
                    }
                });
                sizePicker.appendChild(btn);
            });
        }

        // Render Colors
        const colorPicker = productModalOverlay.querySelector('.color-picker');
        colorPicker.innerHTML = '';
        if (colorsStr) {
            const colors = colorsStr.split(',').map(c => c.trim()).filter(c => c);
            colors.forEach(color => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'color-option-btn';
                btn.textContent = color;
                btn.addEventListener('click', () => {
                    const quantity = parseInt(qtySelect.value, 10) || 1;
                    if (quantity > 1) {
                        btn.classList.toggle('active');
                        selectedColors = Array.from(colorPicker.querySelectorAll('.color-option-btn.active')).map(b => b.textContent.trim());
                    } else {
                        colorPicker.querySelectorAll('.color-option-btn').forEach(b => b.classList.remove('active'));
                        btn.classList.add('active');
                        selectedColors = [color];
                    }
                });
                colorPicker.appendChild(btn);
            });
        }

        // Add to Cart Action
        const addToCartBtn = productModalOverlay.querySelector('.add-to-cart-btn');
        const qtySelect = productModalOverlay.querySelector('#modal-qty');
        qtySelect.value = "1"; // Reset quantity select to 1

        qtySelect.onchange = () => {
            const quantity = parseInt(qtySelect.value, 10) || 1;

            if (quantity <= 1) {
                if (selectedSizes.length > 1) {
                    selectedSizes = [selectedSizes[0]];
                }
                if (selectedColors.length > 1) {
                    selectedColors = [selectedColors[0]];
                }

                sizePicker.querySelectorAll('.size-option-btn').forEach(btn => {
                    btn.classList.toggle('active', selectedSizes.includes(btn.textContent.trim()));
                });
                colorPicker.querySelectorAll('.color-option-btn').forEach(btn => {
                    btn.classList.toggle('active', selectedColors.includes(btn.textContent.trim()));
                });
            }
        };

        if (available === 0) {
            addToCartBtn.disabled = true;
            addToCartBtn.textContent = 'En Rupture de Stock';
        } else {
            addToCartBtn.disabled = false;
            addToCartBtn.textContent = 'Ajouter au Panier';
            
            // Rebind click listener
            addToCartBtn.onclick = () => {
                const quantity = parseInt(qtySelect.value, 10) || 1;

                if (selectedSizes.length === 0) {
                    // show inline error near size picker
                    const sizePickerContainer = productModalOverlay.querySelector('.size-picker')?.parentElement || productModalOverlay.querySelector('.size-picker');
                    showFieldError(sizePickerContainer, 'Veuillez choisir une taille.');
                    showToast('Veuillez choisir une taille.');
                    return;
                }
                if (selectedColors.length === 0) {
                    const colorPickerContainer = productModalOverlay.querySelector('.color-picker')?.parentElement || productModalOverlay.querySelector('.color-picker');
                    showFieldError(colorPickerContainer, 'Veuillez choisir une couleur.');
                    showToast('Veuillez choisir une couleur.');
                    return;
                }

                const sizeValue = quantity > 1 ? selectedSizes.join(', ') : selectedSizes[0];
                const colorValue = quantity > 1 ? selectedColors.join(', ') : selectedColors[0];

                addToCart(id, name, price, image, sizeValue, colorValue, quantity);
                productModalOverlay.classList.remove('active');
            };
        }

        productModalOverlay.classList.add('active');
    }

    // --- CART LOGIC ---
    function addToCart(id, name, price, image, size, color, qty) {
        // Check if item with same size/color exists
        const existingIdx = cart.findIndex(item => item.id === id && item.size === size && item.color === color);
        if (existingIdx > -1) {
            cart[existingIdx].qty += qty;
        } else {
            cart.push({ id, name, price, image, size, color, qty });
        }
        
        saveCart();
        updateCartUI();
        showToast(`"${name}" ajouté au panier.`);
    }

    function saveCart() {
        localStorage.setItem('carthage_chic_cart', JSON.stringify(cart));
    }

    function updateCartUI() {
        const totalItems = cart.reduce((sum, item) => sum + item.qty, 0);
        if (cartCountEl) {
            cartCountEl.textContent = totalItems;
        }
        if (cartFooter) {
            cartFooter.classList.toggle('is-hidden', totalItems === 0);
        }
    }

    function renderCartItems() {
        if (!cartBody) return;
        
        if (cart.length === 0) {
            cartBody.innerHTML = `
                <div class="cart-empty-message">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                    <p>Votre panier est vide</p>
                </div>
            `;
            cartTotalEl.textContent = "0.00";
            return;
        }

        let cartHTML = '';
        let total = 0;

        cart.forEach((item, idx) => {
            const itemSubtotal = item.price * item.qty;
            total += itemSubtotal;
            
            const imageTag = item.image 
                ? `<img class="cart-item-img" src="${item.image}" alt="${item.name}">` 
                : `<div class="cart-item-img" style="background: linear-gradient(135deg, #f5f4f0, #e2e0d9); display:flex; align-items:center; justify-content:center; color:#c5a059; font-size:16px; font-weight:600;">${item.name.substring(0,2)}</div>`;

            cartHTML += `
                <div class="cart-item">
                    ${imageTag}
                    <div class="cart-item-details">
                        <h4 class="cart-item-name">${item.name}</h4>
                        <div class="cart-item-meta">Taille: ${item.size} | Couleur: ${item.color}</div>
                        <div class="cart-item-quantity-price">
                            <div class="quantity-controller">
                                <button class="qty-btn" onclick="adjustQty(${idx}, -1)">-</button>
                                <span class="qty-val">${item.qty}</span>
                                <button class="qty-btn" onclick="adjustQty(${idx}, 1)">+</button>
                            </div>
                            <span class="cart-item-price">${itemSubtotal.toFixed(2)} TND</span>
                        </div>
                        <button class="cart-item-remove" onclick="removeCartItem(${idx})">Supprimer</button>
                    </div>
                </div>
            `;
        });

        cartBody.innerHTML = cartHTML;
        // subtotal
        if (cartTotalEl) cartTotalEl.textContent = total.toFixed(2);
        // delivery and grand total
        if (cartDeliveryEl) cartDeliveryEl.textContent = DELIVERY_FEE.toFixed(2);
        if (cartGrandTotalEl) cartGrandTotalEl.textContent = (total + DELIVERY_FEE).toFixed(2);
    }

    // Expose helpers globally for inline onclick handlers
    window.adjustQty = function(idx, change) {
        cart[idx].qty += change;
        if (cart[idx].qty <= 0) {
            cart.splice(idx, 1);
        }
        saveCart();
        updateCartUI();
        renderCartItems();
    };

    window.removeCartItem = function(idx) {
        const removedName = cart[idx].name;
        cart.splice(idx, 1);
        saveCart();
        updateCartUI();
        renderCartItems();
        showToast(`"${removedName}" retiré du panier.`);
    };

    // --- CHECKOUT PROCESS ---
    function updateCheckoutSummary() {
        const miniGrid = document.querySelector('.checkout-mini-items');
        const checkoutTotal = document.querySelector('.checkout-total-val');
        const checkoutSubtotal = document.querySelector('.checkout-subtotal-val');
        const checkoutDelivery = document.querySelector('.checkout-delivery-val');
        
        if (!miniGrid) return;
        
        miniGrid.innerHTML = '';
        let subtotal = 0;
        
        cart.forEach(item => {
            const row = document.createElement('div');
            row.className = 'checkout-item-mini';
            const sub = item.price * item.qty;
            subtotal += sub;
            row.innerHTML = `
                <span>${item.name} (x${item.qty}) <small>Taille ${item.size}, ${item.color}</small></span>
                <span>${sub.toFixed(2)} TND</span>
            `;
            miniGrid.appendChild(row);
        });
        
        const total = subtotal + DELIVERY_FEE;
        if (checkoutSubtotal) checkoutSubtotal.textContent = subtotal.toFixed(2);
        if (checkoutDelivery) checkoutDelivery.textContent = DELIVERY_FEE.toFixed(2);
        if (checkoutTotal) checkoutTotal.textContent = total.toFixed(2);
    }

    if (checkoutForm) {
        checkoutForm.addEventListener('submit', (e) => {
            e.preventDefault();
            
            const submitBtn = checkoutForm.querySelector('.confirm-order-btn');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Traitement en cours...';

            const formData = new FormData(checkoutForm);
            
            // Build checkout payload
            const subtotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
            const payload = {
                customer_name: formData.get('name'),
                customer_phone: formData.get('phone'),
                customer_governorat: formData.get('governorat'),
                customer_address: formData.get('address'),
                items: cart,
                delivery_fee: DELIVERY_FEE,
                subtotal_price: subtotal,
                total_price: parseFloat((subtotal + DELIVERY_FEE).toFixed(2))
            };

            // Post order to index.php (self order submission)
            fetch('index.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ action: 'submit_order', order_data: payload })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showSuccessModal(payload, data.order_id);
                } else {
                    showToast("Une erreur est survenue lors de la commande : " + data.message);
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Valider la Commande';
                }
            })
            .catch(err => {
                showToast("Erreur de connexion. Veuillez réessayer.");
                submitBtn.disabled = false;
                submitBtn.textContent = 'Valider la Commande';
            });
        });
    }

    function showSuccessModal(orderData, orderId) {
        // Clear Cart
        cart = [];
        saveCart();
        updateCartUI();

        // Format order details for WhatsApp
        const shopPhone = '21626024279'; // Shop owner WhatsApp number (contact page number)
        let waMessage = `Bonjour Carthage Chic ! Je souhaite confirmer ma commande n°CC-${orderId}.\n\n`;
        waMessage += `*Coordonnées :*\n`;
        waMessage += `- Nom: ${orderData.customer_name}\n`;
        waMessage += `- Tél: ${orderData.customer_phone}\n`;
        waMessage += `- Ville: ${orderData.customer_governorat}\n`;
        waMessage += `- Adresse: ${orderData.customer_address}\n\n`;
        waMessage += `*Produits commandés :*\n`;
        
        orderData.items.forEach(item => {
            waMessage += `- ${item.name} (${item.qty}x) | Taille: ${item.size} | Col: ${item.color} - ${(item.price * item.qty).toFixed(2)} TND\n`;
        });
        
        waMessage += `\n*Montant Total:* ${orderData.total_price.toFixed(2)} TND\n`;
        waMessage += `Mode de paiement: Paiement à la livraison (Cash on Delivery)`;

        const encodedMessage = encodeURIComponent(waMessage);
        const waLink = `https://wa.me/${shopPhone}?text=${encodedMessage}`;

        // Swap Checkout Form with Success Screen
        const bodyContent = document.querySelector('.checkout-modal-body');
        bodyContent.innerHTML = `
            <div class="success-card">
                <div class="success-icon-wrapper">
                    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                </div>
                <h3 class="modal-title" style="font-size:24px; margin-bottom:12px;">Commande Enregistrée !</h3>
                <p style="color:var(--text-secondary); font-size:14px; max-width:400px; margin:0 auto 16px;">
                    Merci pour votre commande. Elle a été enregistrée avec succès sous la référence <strong>#CC-${orderId}</strong>.
                </p>
                <p style="color:var(--text-secondary); font-size:14px; max-width:400px; margin:0 auto 8px;">
                    Pour accélérer le traitement et la livraison, confirmez directement vos coordonnées et vos articles sur WhatsApp.
                </p>
                <a href="${waLink}" target="_blank" class="whatsapp-confirm-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16"><path d="M13.601 2.326A7.854 7.854 0 0 0 7.994 0C3.627 0 .068 3.558.064 7.926c0 1.399.366 2.76 1.057 3.965L0 16l4.204-1.102a7.933 7.933 0 0 0 3.79.977h.004c4.368 0 7.92-3.559 7.924-7.928a7.882 7.882 0 0 0-2.321-5.628zM8 12.871a6.621 6.621 0 0 1-3.36-.919l-.24-.144-2.5 1.1 1.12-2.4-.157-.251a6.621 6.621 0 0 1-1.007-3.505c0-3.626 2.957-6.584 6.591-6.584a6.56 6.56 0 0 1 4.66 1.931 6.557 6.557 0 0 1 1.928 4.66c-.004 3.639-2.961 6.592-6.592 6.592zm3.69-4.2a6.03 6.03 0 0 0-1.724-.852c-.189-.07-.324-.105-.459.105-.135.21-.521.657-.64 1.09-.12.433-.24.48-.43.375-.19-.105-.8-.294-1.524-.94-.562-.501-.94-1.17-.105-1.378-.19-.105-.324-.277-.459-.375-.134-.1-.184-.135-.205-.18a.302.302 0 0 1 0-.254c-.05-.105-.459-1.103-.63-1.513-.165-.398-.335-.344-.459-.35-.124-.005-.264-.005-.404-.005a.777.777 0 0 0-.565.25c-.19.21-.724.708-.724 1.73 0 1.02.744 2.005.845 2.14.1.135 1.464 2.239 3.548 3.14.495.213.882.34 1.184.436.497.157.949.135 1.305.08.397-.06.82-.336.935-.662.114-.327.114-.607.08-.662-.036-.055-.13-.086-.31-.177z"/></svg>
                    Confirmer sur WhatsApp
                </a>
                <div style="margin-top:20px;">
                    <a href="index.php" style="font-size:12px; color:var(--text-muted); text-decoration:underline;">Retour à la boutique</a>
                </div>
            </div>
        `;
    }

    // --- TOAST NOTIFICATIONS ---
    function showToast(message) {
        let container = document.querySelector('.toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container';
            document.body.appendChild(container);
        }
        
        const toast = document.createElement('div');
        toast.className = 'toast';
        
        // Simple SVG icon for toast
        toast.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            <span>${message}</span>
        `;
        
        container.appendChild(toast);
        
        // Remove toast after 3.5s
        setTimeout(() => {
            toast.style.animation = 'slideInUp 0.5s cubic-bezier(0.16, 1, 0.3, 1) reverse forwards';
            setTimeout(() => {
                toast.remove();
                if (container.children.length === 0) {
                    container.remove();
                }
            }, 500);
        }, 3000);
    }
});
