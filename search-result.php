<?php
$hide_auth_links = true;
$body_class = 'catalog-pro';
$page_stylesheet = 'assets/css/catalog-pro.css';
require_once('header.php');
?>
<?php
// ØªØ¶Ù…ÙŠÙ† Ù…Ù„Ù Ø§Ù„ØªØ´ÙÙŠØ±
require_once('inc/encryption.php');

if(!isset($_REQUEST['search_text'])) {
    header('location: index.php');
    exit;
} else {
	if($_REQUEST['search_text']=='') {
		header('location: index.php');
    	exit;
	}
}
$search_raw = trim(strip_tags($_REQUEST['search_text']));
$search_display = htmlspecialchars($search_raw, ENT_QUOTES, 'UTF-8');
?>

<?php
$settings = front_get_settings($pdo);
$banner_search = $settings['banner_search'] ?? '';
$top_categories = front_get_top_categories($pdo, 6);
$header_logo_url = trim((string)get_front_image_url($logo));
$header_logo_fallback = trim((string)($meta_title_home ?? ''));
if ($header_logo_fallback === '') {
    $header_logo_fallback = 'Store';
}
$banner_search_url = trim((string)get_front_image_url($banner_search));
?>

<div class="catalog-pro-shell">
    <div class="cart-overlay" id="cartOverlay"></div>
    <aside class="cart-drawer" id="cartDrawer" aria-hidden="true">
        <div class="cart-head">
            <h3>&#1587;&#1604;&#1577; &#1575;&#1604;&#1605;&#1588;&#1578;&#1585;&#1610;&#1575;&#1578;</h3>
            <button type="button" class="cart-close" id="cartClose" aria-label="&#1573;&#1594;&#1604;&#1575;&#1602;">&times;</button>
        </div>
        <div class="cart-body" id="cartItems"></div>
        <div class="cart-footer">
            <div class="cart-total">&#1575;&#1604;&#1605;&#1580;&#1605;&#1608;&#1593;: <span id="cartTotal">0</span> &#1583;&#1580;</div>
            <div class="cart-actions">
                <button type="button" class="pro-btn-outline" id="cartClear">&#1573;&#1601;&#1585;&#1575;&#1594; &#1575;&#1604;&#1587;&#1604;&#1577;</button>
                <button type="button" class="pro-btn" data-close-cart>&#1605;&#1578;&#1575;&#1576;&#1593;&#1577; &#1575;&#1604;&#1578;&#1587;&#1608;&#1602;</button>
            </div>
        </div>
    </aside>
    <button class="cart-fab" type="button" data-open-cart aria-label="&#1587;&#1604;&#1577; &#1575;&#1604;&#1605;&#1588;&#1578;&#1585;&#1610;&#1575;&#1578;">
        <i class="fa fa-shopping-cart"></i>
        <span class="cart-count">0</span>
    </button>
    <header class="pro-header">
        <div class="container">
            <div class="pro-header-inner">
                <a class="pro-logo" href="index.php">
                    <?php if ($header_logo_url !== ''): ?>
                        <img src="<?php echo htmlspecialchars($header_logo_url, ENT_QUOTES, 'UTF-8'); ?>" alt="Logo">
                    <?php else: ?>
                        <span class="logo-text-fallback"><?php echo htmlspecialchars($header_logo_fallback, ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                </a>
                <form class="pro-search" action="search-result.php" method="get">
                    <?php $csrf->echoInputField(); ?>
                    <input type="text" name="search_text" placeholder="&#1575;&#1576;&#1581;&#1579; &#1593;&#1606; &#1605;&#1606;&#1578;&#1580;" value="<?php echo $search_display; ?>">
                    <button type="submit">&#1576;&#1581;&#1579;</button>
                </form>
                <div class="pro-header-actions">
                    <button type="button" class="pro-btn-outline pro-cart-btn" data-open-cart>
                        <i class="fa fa-shopping-cart"></i>
                        <span>&#1575;&#1604;&#1587;&#1604;&#1577;</span>
                        <span class="cart-count">0</span>
                    </button>
                </div>
            </div>
            <?php if (!empty($top_categories)): ?>
                <nav class="pro-chip-nav">
                    <?php foreach ($top_categories as $cat): ?>
                        <a class="pro-chip" href="product-category.php?id=<?php echo (int)$cat['tcat_id']; ?>&type=top-category">
                            <?php echo htmlspecialchars($cat['tcat_name']); ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>
        </div>
    </header>

<div class="page-banner"<?php if ($banner_search_url !== ''): ?> style="background-image: url('<?php echo htmlspecialchars($banner_search_url, ENT_QUOTES, 'UTF-8'); ?>');"<?php endif; ?>>
    <div class="overlay"></div>
    <div class="inner">
        <h1>Ù†ØªØ§Ø¦Ø¬ Ø§Ù„Ø¨Ø­Ø«: <?php echo $search_display; ?></h1>
    </div>
</div>

<div class="page catalog-page">
    <div class="container">
        <div class="catalog-content">
            <?php
                $search_like = '%'.$search_raw.'%';
            ?>

            <?php
            /* ===================== Pagination Code Starts ================== */
            $adjacents = 5;
            $statement = $pdo->prepare("SELECT COUNT(*) FROM tbl_product WHERE p_is_active=? AND p_name LIKE ?");
            $statement->execute(array(1,$search_like));
            $total_pages = (int)$statement->fetchColumn();

            $targetpage = BASE_URL.'search-result.php?search_text='.urlencode($search_raw);   //your file name  (the name of this file)
            $limit = 12;                                 //how many items to show per page
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            if($page) 
                $start = ($page - 1) * $limit;          //first item to display on this page
            else
                $start = 0;
            

            $statement = $pdo->prepare("SELECT * FROM tbl_product WHERE p_is_active=? AND p_name LIKE ? LIMIT $start, $limit");
            $statement->execute(array(1,$search_like));
            $result = $statement->fetchAll(PDO::FETCH_ASSOC);
            $rating_map = front_get_product_rating_map($pdo, array_column($result, 'p_id'));
           
            
            if ($page == 0) $page = 1;                  //if no page var is given, default to 1.
            $prev = $page - 1;                          //previous page is page - 1
            $next = $page + 1;                          //next page is page + 1
            $lastpage = ceil($total_pages/$limit);      //lastpage is = total pages / items per page, rounded up.
            $lpm1 = $lastpage - 1;   
            $pagination = "";
            if($lastpage > 1)
            {   
                $pagination .= "<div class='pagination'>";
                if ($page > 1) 
                    $pagination.= "<a href='$targetpage&page=$prev'>&#171; Ø§Ù„Ø³Ø§Ø¨Ù‚</a>";
                else
                    $pagination.= "<span class='disabled'>&#171; Ø§Ù„Ø³Ø§Ø¨Ù‚</span>";    
                if ($lastpage < 7 + ($adjacents * 2))   //not enough pages to bother breaking it up
                {   
                    for ($counter = 1; $counter <= $lastpage; $counter++)
                    {
                        if ($counter == $page)
                            $pagination.= "<span class='current'>$counter</span>";
                        else
                            $pagination.= "<a href='$targetpage&page=$counter'>$counter</a>";                 
                    }
                }
                elseif($lastpage > 5 + ($adjacents * 2))    //enough pages to hide some
                {
                    if($page < 1 + ($adjacents * 2))        
                    {
                        for ($counter = 1; $counter < 4 + ($adjacents * 2); $counter++)
                        {
                            if ($counter == $page)
                                $pagination.= "<span class='current'>$counter</span>";
                            else
                                $pagination.= "<a href='$targetpage&page=$counter'>$counter</a>";                 
                        }
                        $pagination.= "...";
                        $pagination.= "<a href='$targetpage&page=$lpm1'>$lpm1</a>";
                        $pagination.= "<a href='$targetpage&page=$lastpage'>$lastpage</a>";       
                    }
                    elseif($lastpage - ($adjacents * 2) > $page && $page > ($adjacents * 2))
                    {
                        $pagination.= "<a href='$targetpage&page=1'>1</a>";
                        $pagination.= "<a href='$targetpage&page=2'>2</a>";
                        $pagination.= "...";
                        for ($counter = $page - $adjacents; $counter <= $page + $adjacents; $counter++)
                        {
                            if ($counter == $page)
                                $pagination.= "<span class='current'>$counter</span>";
                            else
                                $pagination.= "<a href='$targetpage&page=$counter'>$counter</a>";                 
                        }
                        $pagination.= "...";
                        $pagination.= "<a href='$targetpage&page=$lpm1'>$lpm1</a>";
                        $pagination.= "<a href='$targetpage&page=$lastpage'>$lastpage</a>";       
                    }
                    else
                    {
                        $pagination.= "<a href='$targetpage&page=1'>1</a>";
                        $pagination.= "<a href='$targetpage&page=2'>2</a>";
                        $pagination.= "...";
                        for ($counter = $lastpage - (2 + ($adjacents * 2)); $counter <= $lastpage; $counter++)
                        {
                            if ($counter == $page)
                                $pagination.= "<span class='current'>$counter</span>";
                            else
                                $pagination.= "<a href='$targetpage&page=$counter'>$counter</a>";                 
                        }
                    }
                }
                if ($page < $counter - 1) 
                    $pagination.= "<a href='$targetpage&page=$next'>Ø§Ù„ØªØ§Ù„ÙŠ &#187;</a>";
                else
                    $pagination.= "<span class='disabled'>Ø§Ù„ØªØ§Ù„ÙŠ &#187;</span>";
                $pagination.= "</div>\n";       
            }
            /* ===================== Pagination Code Ends ================== */
            ?>

            <div class="catalog-header">
                <div>
                    <h3>Ù†ØªØ§Ø¦Ø¬ Ø§Ù„Ø¨Ø­Ø«: <?php echo $search_display; ?></h3>
                </div>
                <span class="catalog-count"><?php echo $total_pages; ?></span>
            </div>

            <?php if(!$total_pages): ?>
                <div class="catalog-empty">Ù„Ø§ ØªÙˆØ¬Ø¯ Ù†ØªØ§Ø¦Ø¬</div>
            <?php else: ?>
                <div class="product-grid">
                    <?php foreach ($result as $row): ?>
                        <?php
                        $product_url = create_secure_product_link($row['p_id'], $row['product_template']);
                        $sold_out = (int)$row['p_qty'] === 0;
                        $product_photo_url = trim((string)get_front_image_url($row['p_featured_photo'] ?? ''));
                        ?>
                        <div class="product-card">
                            <div class="product-media"<?php if ($product_photo_url !== ''): ?> style="background-image:url('<?php echo htmlspecialchars($product_photo_url, ENT_QUOTES, 'UTF-8'); ?>');"<?php endif; ?>>
                                <?php if ($sold_out): ?>
                                    <span class="product-badge out">Ù†ÙØ¯ Ø§Ù„Ù…Ø®Ø²ÙˆÙ†</span>
                                <?php endif; ?>
                            </div>
                            <div class="product-body">
                                <h3><a href="<?php echo htmlspecialchars($product_url); ?>"><?php echo htmlspecialchars($row['p_name']); ?></a></h3>
                                <div class="product-price">
                                    <span><?php echo LANG_VALUE_1; ?><?php echo $row['p_current_price']; ?></span>
                                    <?php if($row['p_old_price'] != ''): ?>
                                        <del><?php echo LANG_VALUE_1; ?><?php echo $row['p_old_price']; ?></del>
                                    <?php endif; ?>
                                </div>
                                <div class="rating">
                                    <?php echo front_render_rating_stars($rating_map[(int)$row['p_id']]['avg_rating'] ?? 0); ?>
                                </div>
                                <div class="product-actions">
                                    <?php if ($sold_out): ?>
                                        <span class="product-soldout">Ù†ÙØ¯ Ø§Ù„Ù…Ø®Ø²ÙˆÙ†</span>
                                    <?php else: ?>
                                        <a href="<?php echo htmlspecialchars($product_url); ?>" class="pro-btn">&#1593;&#1585;&#1590; &#1575;&#1604;&#1605;&#1606;&#1578;&#1580;</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php echo $pagination; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>
<script>
(function () {
    const cartKey = 'ecom_cart_v1';
    const cartDrawer = document.getElementById('cartDrawer');
    const cartOverlay = document.getElementById('cartOverlay');
    const cartItems = document.getElementById('cartItems');
    const cartTotal = document.getElementById('cartTotal');
    const cartCountEls = document.querySelectorAll('.cart-count');
    const openButtons = document.querySelectorAll('[data-open-cart]');
    const closeButtons = document.querySelectorAll('[data-close-cart]');
    const clearButton = document.getElementById('cartClear');
    const closeButton = document.getElementById('cartClose');

    function readCart() {
        try {
            const stored = localStorage.getItem(cartKey);
            return stored ? JSON.parse(stored) : [];
        } catch (e) {
            return [];
        }
    }

    function saveCart(items) {
        localStorage.setItem(cartKey, JSON.stringify(items));
    }

    function formatPrice(value) {
        const numberValue = Number(value) || 0;
        return new Intl.NumberFormat('fr-DZ').format(numberValue);
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function updateBadge(count) {
        cartCountEls.forEach(el => {
            el.textContent = count;
        });
    }

    function openCart() {
        if (!cartDrawer || !cartOverlay) {
            return;
        }
        cartDrawer.classList.add('open');
        cartOverlay.classList.add('show');
        document.body.classList.add('cart-open');
    }

    function closeCart() {
        if (!cartDrawer || !cartOverlay) {
            return;
        }
        cartDrawer.classList.remove('open');
        cartOverlay.classList.remove('show');
        document.body.classList.remove('cart-open');
    }

    function renderCart() {
        if (!cartItems || !cartTotal) {
            return;
        }
        const items = readCart();
        updateBadge(items.reduce((sum, item) => sum + item.qty, 0));
        if (!items.length) {
            cartItems.innerHTML = '<div class="cart-empty">&#1575;&#1604;&#1587;&#1604;&#1577; &#1601;&#1575;&#1585;&#1594;&#1577; &#1581;&#1575;&#1604;&#1610;&#1575;&#1611;&#46;</div>';
            cartTotal.textContent = '0';
            return;
        }
        let total = 0;
        cartItems.innerHTML = items.map(item => {
            const itemTotal = (Number(item.price) || 0) * item.qty;
            total += itemTotal;
            const safeName = escapeHtml(item.name);
            const safeUrl = item.url ? escapeHtml(item.url) : '';
            const safePhoto = item.photo ? escapeHtml(item.photo) : '';
            const itemPhotoHtml = safePhoto
                ? `<img src="${safePhoto}" alt="${safeName}">`
                : '<div class="cart-thumb-empty" aria-hidden="true"></div>';
            return `
                <div class="cart-item" data-id="${item.id}">
                    ${itemPhotoHtml}
                    <div>
                        <h4>${safeName}</h4>
                        <div class="cart-meta">${formatPrice(item.price)} &#1583;&#1580;</div>
                        <div class="cart-qty">
                            <button type="button" data-step="-1">-</button>
                            <input type="number" min="1" value="${item.qty}" class="cart-qty-input" />
                            <button type="button" data-step="1">+</button>
                        </div>
                        <button type="button" class="cart-remove" data-remove="${item.id}">&#1581;&#1584;&#1601;</button>
                        ${safeUrl ? `<div class="cart-meta"><a href="${safeUrl}">&#1593;&#1585;&#1590; &#1575;&#1604;&#1605;&#1606;&#1578;&#1580;</a></div>` : ''}
                    </div>
                </div>
            `;
        }).join('');
        cartTotal.textContent = formatPrice(total);
    }

    function addToCart(payload) {
        const items = readCart();
        const existing = items.find(item => item.id === payload.id);
        if (existing) {
            existing.qty += 1;
        } else {
            items.push({
                id: payload.id,
                name: payload.name,
                price: payload.price,
                photo: payload.photo,
                url: payload.url,
                qty: 1
            });
        }
        saveCart(items);
        renderCart();
        openCart();
    }

    document.querySelectorAll('[data-add-cart]').forEach(btn => {
        btn.addEventListener('click', function () {
            addToCart({
                id: this.getAttribute('data-id'),
                name: this.getAttribute('data-name'),
                price: this.getAttribute('data-price'),
                photo: this.getAttribute('data-photo'),
                url: this.getAttribute('data-url')
            });
        });
    });

    if (cartItems) {
        cartItems.addEventListener('click', function (event) {
            const removeButton = event.target.closest('[data-remove]');
            if (removeButton) {
                const items = readCart().filter(item => item.id !== removeButton.getAttribute('data-remove'));
                saveCart(items);
                renderCart();
                return;
            }
            const stepButton = event.target.closest('[data-step]');
            if (stepButton) {
                const parentItem = event.target.closest('.cart-item');
                if (!parentItem) {
                    return;
                }
                const id = parentItem.getAttribute('data-id');
                const step = parseInt(stepButton.getAttribute('data-step'), 10);
                const items = readCart();
                const target = items.find(item => item.id === id);
                if (target) {
                    target.qty = Math.max(1, target.qty + step);
                    saveCart(items);
                    renderCart();
                }
            }
        });

        cartItems.addEventListener('change', function (event) {
            if (!event.target.classList.contains('cart-qty-input')) {
                return;
            }
            const parentItem = event.target.closest('.cart-item');
            if (!parentItem) {
                return;
            }
            const id = parentItem.getAttribute('data-id');
            const value = Math.max(1, parseInt(event.target.value, 10) || 1);
            const items = readCart();
            const target = items.find(item => item.id === id);
            if (target) {
                target.qty = value;
                saveCart(items);
                renderCart();
            }
        });
    }

    openButtons.forEach(btn => btn.addEventListener('click', openCart));
    closeButtons.forEach(btn => btn.addEventListener('click', closeCart));
    if (cartOverlay) {
        cartOverlay.addEventListener('click', closeCart);
    }
    if (closeButton) {
        closeButton.addEventListener('click', closeCart);
    }
    if (clearButton) {
        clearButton.addEventListener('click', function () {
            saveCart([]);
            renderCart();
        });
    }

    renderCart();
})();
</script>
<?php require_once('footer.php'); ?>
