// admin.js - Interactivity for WAMP Admin Panel

document.addEventListener('DOMContentLoaded', () => {

    // --- POLL FOR NEW ORDERS AND UPDATE BADGE ---
    const ordersLink = document.querySelector('.sidebar-link[href*="tab=orders"]');
    if (ordersLink) {
        const badge = document.createElement('span');
        badge.className = 'orders-badge';
        badge.style.cssText = 'display:inline-block;margin-left:8px;background:#c99a3a;color:#fff;padding:2px 8px;border-radius:12px;font-size:12px;font-weight:700;';
        ordersLink.appendChild(badge);

        let lastOrdersCount = 0;

        // Small audio beep for notification (base64 short beep)
        const beepAudio = new Audio('data:audio/mp3;base64,//uQZAAAAAAAAAAAAAAAAAAAAAAAWGluZwAAAA8AAAACAAACcQCA' +
            'AAAAAA==');

        // Create a sticky banner element for visible notifications
        const banner = document.createElement('div');
        banner.className = 'new-order-banner';
        banner.style.display = 'none';
        banner.innerHTML = `<div class="banner-text"></div><div class="close-x" title="Fermer">×</div>`;
        document.body.appendChild(banner);
        const bannerText = banner.querySelector('.banner-text');
        const bannerClose = banner.querySelector('.close-x');
        bannerClose.addEventListener('click', (e) => {
            e.stopPropagation();
            hideBanner();
        });
        banner.addEventListener('click', () => {
            // open orders tab when clicked
            window.location.href = 'admin.php?tab=orders';
        });

        function showBanner(n) {
            bannerText.innerHTML = `${n} nouvelle(s) commande(s) — <span style="text-decoration:underline">Voir</span>`;
            banner.classList.add('show', 'pulse');
            banner.style.display = 'flex';
            // auto-hide after 12 seconds
            setTimeout(() => { banner.classList.remove('pulse'); }, 12000);
            setTimeout(hideBanner, 15000);
        }

        function hideBanner() {
            banner.classList.remove('show');
            setTimeout(() => { banner.style.display = 'none'; }, 360);
        }

        function flashTitle(newCount) {
            const original = document.title;
            let flashes = 0;
            const max = 6;
            const iv = setInterval(() => {
                document.title = flashes % 2 === 0 ? `(${newCount}) Nouvelle commande` : original;
                flashes++;
                if (flashes > max) {
                    clearInterval(iv);
                    document.title = original;
                }
            }, 800);
        }

        function notifyNewOrders(n) {
            // Play sound if possible
            try { beepAudio.play().catch(()=>{}); } catch (e) {}

            // Browser Notification API (works on localhost)
            if (window.Notification) {
                const notifIcon = 'assets/logo.png';
                if (Notification.permission === 'granted') {
                    new Notification('Carthage Chic', { body: `${n} nouvelle(s) commande(s) en attente.`, icon: notifIcon });
                } else if (Notification.permission !== 'denied') {
                    Notification.requestPermission().then(permission => {
                        if (permission === 'granted') {
                            new Notification('Carthage Chic', { body: `${n} nouvelle(s) commande(s) en attente.`, icon: notifIcon });
                        }
                    });
                }
            }

            flashTitle(n);
        }

        async function updateOrdersCount() {
            try {
                const res = await fetch('admin.php?action=new_orders_count');
                const json = await res.json();
                if (json.success) {
                    const n = parseInt(json.count, 10) || 0;
                    badge.textContent = n > 0 ? n : '';
                    badge.style.display = n > 0 ? 'inline-block' : 'none';

                    if (n > lastOrdersCount && lastOrdersCount !== 0) {
                        // New orders arrived — show visible banner (simple and reliable)
                        try { beepAudio.play().catch(()=>{}); } catch (e) {}
                        showBanner(n - lastOrdersCount);
                    }
                    lastOrdersCount = n;
                }
            } catch (e) {
                // ignore network errors
            }
        }

        updateOrdersCount();
        setInterval(updateOrdersCount, 15000);
    }

    // --- DOM REFERENCES: PRODUCT MODAL ---
    const productModal = document.getElementById('product-modal');
    const btnAddProduct = document.getElementById('btn-add-product');
    const productModalClose = document.getElementById('product-modal-close');
    const btnProductModalCancel = document.getElementById('btn-product-modal-cancel');
    const productForm = productModal ? productModal.querySelector('form') : null;
    
    // --- DOM REFERENCES: ORDER MODAL ---
    const orderModal = document.getElementById('order-modal');
    const orderModalClose = document.getElementById('order-modal-close');
    const btnOrderModalClose = document.getElementById('btn-order-modal-close');

    const productImageInput = document.getElementById('prod-image-file');
    const productPreviewContainer = document.getElementById('prod-image-preview-container');
    const productPreviewGrid = document.getElementById('prod-image-preview-grid');
    const prodExistingImagesInput = document.getElementById('prod-existing-images');
    const prodExistingImageInput = document.getElementById('prod-existing-image');

    const productImageState = {
        existingImages: [],
        selectedFiles: [],
    };

    function syncExistingImagesInput() {
        if (prodExistingImagesInput) {
            prodExistingImagesInput.value = JSON.stringify(productImageState.existingImages);
        }
    }

    function syncSelectedFilesInput() {
        if (!productImageInput) return;
        const dataTransfer = new DataTransfer();
        productImageState.selectedFiles.forEach((file) => dataTransfer.items.add(file));
        productImageInput.files = dataTransfer.files;
    }

    function renderProductImagePreviews() {
        if (!productPreviewContainer || !productPreviewGrid) return;

        productPreviewGrid.innerHTML = '';
        const allItems = [
            ...productImageState.existingImages.map((src) => ({ type: 'existing', src })),
            ...productImageState.selectedFiles.map((file) => ({ type: 'file', src: URL.createObjectURL(file), file })),
        ];

        if (allItems.length === 0) {
            productPreviewContainer.style.display = 'none';
            return;
        }

        allItems.forEach((item, index) => {
            const previewWrap = document.createElement('div');
            previewWrap.className = 'image-preview-item';
            previewWrap.style.position = 'relative';

            const img = document.createElement('img');
            img.src = item.src;
            img.alt = `Aperçu ${index + 1}`;
            img.className = 'image-preview';
            previewWrap.appendChild(img);

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'image-preview-remove';
            removeBtn.textContent = 'x';

            removeBtn.addEventListener('click', () => {
                if (item.type === 'existing') {
                    productImageState.existingImages = productImageState.existingImages.filter((src) => src !== item.src);
                    syncExistingImagesInput();
                } else {
                    const fileIndex = productImageState.selectedFiles.indexOf(item.file);
                    if (fileIndex > -1) {
                        productImageState.selectedFiles.splice(fileIndex, 1);
                        syncSelectedFilesInput();
                    }
                }
                renderProductImagePreviews();
            });

            previewWrap.appendChild(removeBtn);
            productPreviewGrid.appendChild(previewWrap);
        });

        productPreviewContainer.style.display = 'flex';
    }

    // --- PRODUCT MODAL FUNCTIONALITY ---
    if (btnAddProduct) {
        btnAddProduct.addEventListener('click', () => {
            resetProductForm();
            document.getElementById('product-modal-title').textContent = "Ajouter un Produit";
            productModal.classList.add('active');
        });
    }

    if (productModalClose) {
        productModalClose.addEventListener('click', () => {
            productModal.classList.remove('active');
        });
    }

    if (btnProductModalCancel) {
        btnProductModalCancel.addEventListener('click', () => {
            productModal.classList.remove('active');
        });
    }

    // Modal backdrop click
    if (productModal) {
        productModal.addEventListener('click', (e) => {
            if (e.target === productModal) {
                productModal.classList.remove('active');
            }
        });
    }

    function resetProductForm() {
        if (!productForm) return;
        productForm.reset();
        
        document.getElementById('prod-id').value = "0";
        document.getElementById('prod-existing-image').value = "";
        document.getElementById('prod-existing-images').value = "[]";
        productImageState.existingImages = [];
        productImageState.selectedFiles = [];
        syncSelectedFilesInput();
        
        // Clear sizes checkboxes
        productModal.querySelectorAll('.prod-size-chk').forEach(chk => {
            chk.checked = false;
        });

        // Reset image preview
        if (productPreviewGrid) productPreviewGrid.innerHTML = '';
        if (productPreviewContainer) productPreviewContainer.style.display = 'none';
    }

    // Edit Product Button Clicks
    document.addEventListener('click', (e) => {
        const editBtn = e.target.closest('.edit-product-btn');
        if (editBtn) {
            const row = editBtn.closest('tr');
            if (row) {
                openEditProductModal(row);
            }
        }
    });

    function openEditProductModal(row) {
        resetProductForm();

        const id = row.getAttribute('data-id');
        const name = row.getAttribute('data-name');
        const desc = row.getAttribute('data-desc');
        const price = row.getAttribute('data-price');
        const category = row.getAttribute('data-category');
        const sizesStr = row.getAttribute('data-sizes') || '';
        const colors = row.getAttribute('data-colors');
        const available = parseInt(row.getAttribute('data-available'));
        const imagesRaw = row.getAttribute('data-images') || '[]';
        let images = [];
        try {
            images = JSON.parse(imagesRaw);
        } catch (err) {
            images = [];
        }
        if (!Array.isArray(images)) {
            images = [];
        }

        // Set inputs
        document.getElementById('prod-id').value = id;
        document.getElementById('prod-name').value = name;
        document.getElementById('prod-desc').value = desc;
        document.getElementById('prod-price').value = price;
        document.getElementById('prod-category').value = category;
        document.getElementById('prod-colors').value = colors;
        document.getElementById('prod-avail').checked = (available === 1);
        productImageState.existingImages = images.slice();
        productImageState.selectedFiles = [];
        syncExistingImagesInput();
        syncSelectedFilesInput();

        // Check sizes
        const sizes = sizesStr.split(',').map(s => s.trim());
        sizes.forEach(size => {
            const chk = document.getElementById('sz-' + size);
            if (chk) chk.checked = true;
        });

        // Image Preview
        renderProductImagePreviews();

        document.getElementById('product-modal-title').textContent = "Modifier le Produit";
        productModal.classList.add('active');
    }

    // --- UPLOAD IMAGE INSTANT PREVIEW ---
    if (productImageInput) {
        productImageInput.addEventListener('change', () => {
            productImageState.selectedFiles = Array.from(productImageInput.files || []);
            syncSelectedFilesInput();
            renderProductImagePreviews();
        });
    }

    // --- ORDER DETAILS MODAL ---
    document.addEventListener('click', (e) => {
        const viewBtn = e.target.closest('.view-order-btn');
        if (viewBtn) {
            const id = viewBtn.getAttribute('data-id');
            openOrderDetailsModal(id);
        }
    });

    function openOrderDetailsModal(id) {
        // Fetch order details via AJAX
        fetch('admin.php?action=get_order_details&id=' + id)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    populateOrderModal(data.order);
                    orderModal.classList.add('active');
                } else {
                    alert("Erreur lors de la récupération des détails : " + data.message);
                }
            })
            .catch(err => {
                alert("Erreur réseau de communication.");
            });
    }

    function populateOrderModal(order) {
        document.getElementById('ord-id-txt').textContent = '#CC-' + order.id;
        
        // Format Date
        const dateObj = new Date(order.created_at);
        const dateStr = dateObj.toLocaleDateString('fr-FR') + ' à ' + dateObj.toLocaleTimeString('fr-FR', {hour: '2-digit', minute:'2-digit'});
        document.getElementById('ord-date-txt').textContent = dateStr;
        
        document.getElementById('ord-name-txt').textContent = order.customer_name;
        document.getElementById('ord-phone-txt').textContent = order.customer_phone;
        document.getElementById('ord-gov-txt').textContent = order.customer_governorat;
        document.getElementById('ord-addr-txt').textContent = order.customer_address;
        document.getElementById('ord-total-txt').textContent = parseFloat(order.total_price).toFixed(2);

        // Render items list
        const itemsList = document.getElementById('ord-items-list');
        itemsList.innerHTML = '';

        order.items.forEach(item => {
            const itemRow = document.createElement('div');
            itemRow.className = 'order-detail-item';
            itemRow.innerHTML = `
                <div>
                    <span class="order-detail-item-name">${item.product_name}</span> (x${item.quantity})
                    <div class="order-detail-item-meta">Taille: ${item.size} | Couleur: ${item.color}</div>
                </div>
                <strong>${(parseFloat(item.price) * parseInt(item.quantity)).toFixed(2)} TND</strong>
            `;
            itemsList.appendChild(itemRow);
        });
    }

    if (orderModalClose) {
        orderModalClose.addEventListener('click', () => {
            orderModal.classList.remove('active');
        });
    }

    if (btnOrderModalClose) {
        btnOrderModalClose.addEventListener('click', () => {
            orderModal.classList.remove('active');
        });
    }

    if (orderModal) {
        orderModal.addEventListener('click', (e) => {
            if (e.target === orderModal) {
                orderModal.classList.remove('active');
            }
        });
    }

    // --- UPDATE ORDER STATUS VIA AJAX INSTANTLY ---
    const statusSelects = document.querySelectorAll('.order-status-select');
    statusSelects.forEach(select => {
        // Save initial value to revert if error
        let previousValue = select.value;
        
        select.addEventListener('change', () => {
            const orderId = select.getAttribute('data-id');
            const newStatus = select.value;
            
            const formData = new FormData();
            formData.append('action', 'update_order_status');
            formData.append('order_id', orderId);
            formData.append('status', newStatus);

            fetch('admin.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    previousValue = newStatus;
                    // Flash the dropdown green temporarily as feedback
                    select.style.borderColor = 'var(--admin-success)';
                    setTimeout(() => {
                        select.style.borderColor = '';
                    }, 1000);
                } else {
                    alert("Impossible de changer le statut : " + data.message);
                    select.value = previousValue;
                }
            })
            .catch(err => {
                alert("Erreur de communication réseau.");
                select.value = previousValue;
            });
        });
    });
});
