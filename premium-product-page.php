<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>صفحة منتج احترافية عربية RTL</title>
    <meta name="description" content="صفحة منتج عربية فاخرة مع سلة جانبية، معرض صور، تقييمات، أسئلة شائعة، ومنتجات مرتبطة.">
    <style>
        :root{--bg:#f7f3ec;--bg2:#fff;--ink:#112031;--muted:#637282;--line:rgba(17,32,49,.1);--gold:#c89c2f;--gold2:#f4df9f;--accent:#0c6f86;--success:#12734f;--danger:#b3261e;--warning:#ff9e1b;--shadow:0 24px 56px rgba(9,20,31,.12);--shadow2:0 16px 34px rgba(9,20,31,.08);--radius:28px;--radius2:20px;--container:1240px}
        *{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;font-family:"Cairo","Tajawal","Segoe UI",sans-serif;color:var(--ink);background:radial-gradient(circle at top right,rgba(200,156,47,.18),transparent 22%),radial-gradient(circle at top left,rgba(12,111,134,.12),transparent 18%),linear-gradient(180deg,#f4efe4 0,#f7f3ec 32%,#fff 100%);line-height:1.7}img{max-width:100%;display:block}a{text-decoration:none;color:inherit}button,input{font:inherit}button{cursor:pointer}
        .container{width:min(var(--container),calc(100% - 32px));margin:auto}.card{background:rgba(255,255,255,.88);backdrop-filter:blur(12px);border:1px solid var(--line);border-radius:var(--radius);box-shadow:var(--shadow)}
        .site-header{position:sticky;top:0;z-index:60;padding:16px 0 10px;background:linear-gradient(180deg,rgba(247,243,236,.96),rgba(247,243,236,.72),transparent);backdrop-filter:blur(16px)}
        .header-shell{background:rgba(9,20,31,.88);color:#fff;border:1px solid rgba(255,255,255,.08);border-radius:30px;overflow:hidden;box-shadow:0 24px 52px rgba(9,20,31,.18)}
        .header-top{display:flex;align-items:center;gap:18px;padding:18px 22px}.brand{display:flex;align-items:center;gap:12px;min-width:max-content}.brand-mark{width:48px;height:48px;border-radius:16px;display:grid;place-items:center;background:linear-gradient(135deg,var(--gold2),var(--gold));color:#111;font-size:1.3rem;font-weight:900;box-shadow:0 14px 28px rgba(200,156,47,.24)}.brand-text strong{display:block;font-size:1.08rem;font-weight:900}.brand-text span{display:block;font-size:.88rem;color:rgba(255,255,255,.62)}
        .search-form{flex:1;display:flex;align-items:center;gap:10px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.1);border-radius:20px;padding:8px 10px 8px 14px}.search-form input{flex:1;border:0;outline:0;background:transparent;color:#fff;padding:6px 0}.search-form input::placeholder{color:rgba(255,255,255,.56)}.header-actions{display:flex;gap:10px;align-items:center}
        .primary-button,.secondary-button,.ghost-button,.icon-button{transition:transform .25s ease,box-shadow .25s ease,background-color .25s ease,border-color .25s ease}.primary-button,.secondary-button,.ghost-button{display:inline-flex;align-items:center;justify-content:center;gap:10px;min-height:50px;padding:12px 20px;border-radius:18px;font-weight:800;border:0}.primary-button{background:linear-gradient(135deg,var(--gold2),var(--gold));color:#111;box-shadow:0 16px 30px rgba(200,156,47,.22)}.primary-button:hover,.secondary-button:hover,.ghost-button:hover,.icon-button:hover,.floating-cart:hover{transform:translateY(-2px)}
        .secondary-button{background:#fff;color:var(--ink);border:1px solid var(--line);box-shadow:0 12px 24px rgba(9,20,31,.04)}.ghost-button{background:rgba(255,255,255,.08);color:#fff;border:1px solid rgba(255,255,255,.12)}.icon-button{width:48px;height:48px;border-radius:16px;border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.08);color:#fff;display:grid;place-items:center}
        .cart-trigger{position:relative}.cart-pill{position:absolute;top:-6px;left:-4px;min-width:22px;height:22px;padding:0 6px;border-radius:999px;display:grid;place-items:center;font-size:.75rem;font-weight:900;background:linear-gradient(135deg,var(--gold2),var(--gold));color:#111}
        .header-nav{display:flex;gap:10px;padding:0 22px 18px;overflow:auto;scrollbar-width:none}.header-nav::-webkit-scrollbar{display:none}.nav-link{white-space:nowrap;padding:10px 14px;border-radius:999px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.08);color:rgba(255,255,255,.88);font-weight:700}
        .floating-cart{position:fixed;left:20px;bottom:20px;z-index:70;display:inline-flex;align-items:center;gap:12px;padding:14px 18px;border:0;border-radius:20px;background:linear-gradient(135deg,#09141f,#123042);color:#fff;box-shadow:0 24px 48px rgba(9,20,31,.24)}.floating-cart span{min-width:30px;height:30px;padding:0 8px;border-radius:999px;display:grid;place-items:center;background:linear-gradient(135deg,var(--gold2),var(--gold));color:#111;font-weight:900}
        .cart-overlay{position:fixed;inset:0;background:rgba(3,10,16,.56);backdrop-filter:blur(6px);opacity:0;visibility:hidden;transition:.25s;z-index:80}.cart-overlay.show{opacity:1;visibility:visible}
        .cart-drawer{position:fixed;top:0;right:0;width:min(420px,100%);height:100vh;display:flex;flex-direction:column;background:rgba(255,255,255,.96);border-left:1px solid var(--line);box-shadow:-26px 0 56px rgba(9,20,31,.12);transform:translateX(100%);transition:transform .3s;z-index:90}.cart-drawer.open{transform:translateX(0)}
        .cart-head,.cart-foot{padding:22px 20px}.cart-head{display:flex;justify-content:space-between;align-items:center;gap:16px;border-bottom:1px solid var(--line)}.cart-head h3,.block-head h2,.cta-content h2,.summary-title,.summary-rating{margin:0;font-weight:900}
        .cart-body{flex:1;overflow:auto;padding:18px 20px;display:flex;flex-direction:column;gap:14px}.cart-empty{display:grid;gap:10px;padding:28px 20px;border-radius:24px;background:#f8fafb;border:1px dashed rgba(17,32,49,.18);text-align:center;color:var(--muted)}
        .cart-item{display:grid;grid-template-columns:84px 1fr;gap:12px;align-items:center;padding:12px;border-radius:22px;background:#fff;border:1px solid var(--line);box-shadow:0 14px 28px rgba(9,20,31,.05)}.cart-item-image{width:84px;height:84px;border-radius:18px;object-fit:cover;background:#eef3f6}.cart-item-title{margin:0 0 4px;font-size:.96rem;font-weight:800}.cart-item-meta,.meta-inline,.rating-row,.stock-row,.purchase-row,.sub-actions{display:flex;flex-wrap:wrap;gap:8px;align-items:center}.cart-item-meta{margin-bottom:8px;color:var(--muted);font-size:.84rem}.cart-item-price{font-weight:900}.cart-item-controls{display:flex;justify-content:space-between;gap:12px;align-items:center;margin-top:8px}
        .qty-box,.quantity-selector{display:inline-flex;align-items:center;gap:8px;padding:6px;border-radius:999px;background:#f6f8fb;border:1px solid var(--line)}.qty-box button,.quantity-selector button{width:40px;height:40px;border-radius:999px;border:0;background:#fff;box-shadow:0 10px 20px rgba(9,20,31,.06);color:var(--ink);font-size:1.05rem;font-weight:900}.qty-box input,.quantity-selector input{width:48px;border:0;background:transparent;text-align:center;font-weight:900;color:var(--ink)}.remove-button{border:0;background:transparent;color:var(--danger);font-weight:800}
        .cart-foot{border-top:1px solid var(--line);background:#fff}.subtotal-row{display:flex;justify-content:space-between;gap:16px;align-items:center;margin-bottom:14px;font-weight:900}.cart-actions{display:flex;flex-wrap:wrap;gap:10px}.cart-actions>*{flex:1 1 140px}
        .page-section{padding:28px 0}.breadcrumb{display:flex;flex-wrap:wrap;gap:8px;padding:14px 0 4px;color:var(--muted);font-size:.92rem}
        .hero-layout{display:grid;grid-template-columns:minmax(0,1.08fr) minmax(0,.92fr);gap:28px;align-items:start}.gallery-card,.summary-card,.content-card{padding:24px}
        .gallery-main{position:relative;overflow:hidden;border-radius:28px;background:linear-gradient(145deg,#f7f3ea,#eef4f6);min-height:520px;display:grid;place-items:center;box-shadow:inset 0 1px 0 rgba(255,255,255,.55)}.gallery-main::after{content:"";position:absolute;inset:auto 0 0 0;height:34%;background:linear-gradient(180deg,transparent,rgba(9,20,31,.08));pointer-events:none}
        .gallery-badge,.gallery-zoom,.eyebrow,.chip,.saving-chip,.card-badge{display:inline-flex;align-items:center;gap:8px;border-radius:999px;font-weight:900}
        .gallery-badge{position:absolute;top:16px;right:16px;z-index:2;padding:8px 12px;background:linear-gradient(135deg,#111,#293443);color:#fff;box-shadow:0 12px 26px rgba(9,20,31,.18)}.gallery-zoom{position:absolute;top:16px;left:16px;z-index:2;padding:10px 14px;background:rgba(255,255,255,.86);border:1px solid rgba(17,32,49,.08);color:var(--ink)}
        .gallery-main img{width:100%;height:100%;max-height:520px;object-fit:cover;transition:transform .35s}.gallery-main:hover img,.product-card:hover .product-media img{transform:scale(1.05)}.thumbnail-strip{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-top:16px}.thumbnail{padding:0;border:1px solid var(--line);border-radius:18px;overflow:hidden;background:#fff;box-shadow:0 10px 20px rgba(9,20,31,.06)}.thumbnail img{width:100%;aspect-ratio:1/1;object-fit:cover}.thumbnail.active{border-color:rgba(200,156,47,.5);box-shadow:0 16px 30px rgba(200,156,47,.16)}
        .summary-card{display:grid;gap:18px}.eyebrow{width:max-content;padding:7px 12px;background:rgba(200,156,47,.14);color:#755814;font-size:.82rem}.summary-title{font-size:clamp(2rem,3vw,3rem);line-height:1.1}.rating-stars{display:inline-flex;gap:4px;color:var(--warning);font-size:1.05rem}.chip{padding:8px 12px;background:#f5f8fa;color:var(--muted);font-size:.88rem;border:1px solid rgba(17,32,49,.08)}.price-block{display:flex;flex-wrap:wrap;align-items:end;gap:12px;padding:18px 20px;border-radius:24px;background:linear-gradient(135deg,rgba(255,255,255,.95),rgba(247,244,238,.96));border:1px solid var(--line)}.price-now{font-size:clamp(2rem,4vw,3rem);line-height:1;font-weight:900}.price-old{color:var(--muted);text-decoration:line-through;font-weight:700}.saving-chip{margin-inline-start:auto;padding:10px 12px;background:rgba(18,115,79,.12);color:var(--success);font-size:.9rem}.available{background:rgba(18,115,79,.12);color:var(--success)}
        .short-copy,.content-card p,.reviewer span,.footer-col p,.footer-col a,.footer-col li,.cta-content p{color:var(--muted)}.option-group{display:grid;gap:12px}.option-group h3,.trust-card strong,.footer-col h3,.footer-col h4{margin:0}.option-grid{display:flex;flex-wrap:wrap;gap:10px}.option-button{min-height:48px;padding:10px 14px;border-radius:16px;border:1px solid var(--line);background:#fff;color:var(--ink);font-weight:800;box-shadow:0 10px 20px rgba(9,20,31,.04)}.option-button.active{border-color:rgba(200,156,47,.46);background:rgba(200,156,47,.08);box-shadow:0 14px 26px rgba(200,156,47,.12)}.color-button{display:inline-flex;align-items:center;gap:10px;min-width:132px}.swatch{width:18px;height:18px;border-radius:999px;border:2px solid rgba(255,255,255,.82);box-shadow:0 0 0 1px rgba(17,32,49,.1)}
        .purchase-panel{display:grid;gap:14px;padding:18px;border-radius:24px;background:linear-gradient(145deg,#fff,#f8fbfc);border:1px solid var(--line)}.action-grid,.product-actions{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.wishlist-button.active{background:rgba(179,38,30,.1);border-color:rgba(179,38,30,.18);color:var(--danger)}
        .trust-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-top:8px}.trust-card{padding:18px;border-radius:22px;background:rgba(255,255,255,.92);border:1px solid var(--line);box-shadow:0 14px 32px rgba(9,20,31,.06)}.trust-card p{margin:6px 0 0;font-size:.9rem;color:var(--muted)}
        .section-block{margin-top:24px}.block-head{display:flex;justify-content:space-between;gap:16px;align-items:end;margin-bottom:18px}.block-head h2{font-size:clamp(1.45rem,2vw,2.3rem);line-height:1.15}.block-head p{margin:8px 0 0;color:var(--muted)}
        .detail-grid{display:grid;grid-template-columns:minmax(0,1.15fr) minmax(0,.85fr);gap:24px}.content-card{background:rgba(255,255,255,.88);border:1px solid var(--line);border-radius:var(--radius);box-shadow:var(--shadow2)}.feature-list,.spec-grid,.review-list,.faq-list{display:grid;gap:12px}.feature-item{display:flex;gap:12px;align-items:start;padding:14px;border-radius:20px;background:#fff;border:1px solid var(--line)}.feature-icon{width:42px;height:42px;border-radius:14px;display:grid;place-items:center;background:rgba(200,156,47,.14);color:#745811;font-weight:900}.spec-item{display:grid;grid-template-columns:1fr auto;gap:12px;padding:14px 16px;border-radius:18px;background:#fff;border:1px solid var(--line)}.spec-item span{color:var(--muted)}.spec-item strong{color:var(--ink)}
        .reviews-layout{display:grid;grid-template-columns:320px minmax(0,1fr);gap:22px}.reviews-summary{padding:24px;background:linear-gradient(145deg,#09141f,#123042);color:#fff;border-radius:var(--radius);box-shadow:var(--shadow)}.summary-rating{font-size:3rem;line-height:1;margin-bottom:10px}.summary-meta{color:rgba(255,255,255,.74);margin:10px 0 18px}.rating-bars{display:grid;gap:10px}.rating-bar{display:grid;grid-template-columns:50px 1fr 42px;gap:10px;align-items:center;color:rgba(255,255,255,.78);font-size:.9rem}.rating-track{height:8px;border-radius:999px;background:rgba(255,255,255,.12);overflow:hidden}.rating-fill{height:100%;border-radius:inherit;background:linear-gradient(135deg,var(--gold2),var(--gold))}
        .review-card,.faq-item,.product-card{background:rgba(255,255,255,.9);border:1px solid var(--line);box-shadow:var(--shadow2)}.review-card{padding:20px;border-radius:24px}.review-head{display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:10px}.reviewer{display:flex;align-items:center;gap:12px}.avatar{width:48px;height:48px;border-radius:16px;display:grid;place-items:center;background:linear-gradient(135deg,rgba(200,156,47,.18),rgba(12,111,134,.14));font-weight:900}.reviewer strong{display:block}
        .faq-item{overflow:hidden;border-radius:22px}.faq-question{width:100%;padding:18px 20px;display:flex;justify-content:space-between;gap:12px;align-items:center;border:0;background:transparent;color:var(--ink);text-align:start;font-weight:900}.faq-answer{max-height:0;overflow:hidden;transition:max-height .25s ease;padding:0 20px;color:var(--muted)}.faq-item.open .faq-answer{max-height:220px;padding-bottom:18px}.faq-toggle{width:34px;height:34px;border-radius:999px;display:grid;place-items:center;background:#f4f7f9;font-size:1.2rem}
        .products-section{padding:24px 0 6px}.product-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:18px}.product-card{display:flex;flex-direction:column;min-height:100%;overflow:hidden;border-radius:28px;transition:transform .3s ease,box-shadow .3s ease,border-color .3s ease}.product-card:hover{transform:translateY(-8px);border-color:rgba(200,156,47,.32);box-shadow:0 30px 56px rgba(9,20,31,.12)}.product-media{position:relative;overflow:hidden;background:linear-gradient(145deg,#f7f2e8,#eef4f6)}.product-media img{width:100%;aspect-ratio:1/1;object-fit:cover;transition:transform .45s ease}.card-badge{position:absolute;top:14px;right:14px;z-index:2;padding:8px 12px;color:#fff;font-size:.8rem;box-shadow:0 12px 22px rgba(9,20,31,.16)}.badge-sale{background:linear-gradient(135deg,#b3261e,#ef4444)}.badge-new{background:linear-gradient(135deg,var(--accent),#1d97b4)}.badge-popular{background:linear-gradient(135deg,var(--gold2),var(--gold));color:#111}
        .product-body{display:flex;flex-direction:column;gap:12px;padding:18px;flex:1}.product-title{margin:0;font-size:1rem;line-height:1.5;font-weight:800}.product-prices{display:flex;flex-wrap:wrap;align-items:baseline;gap:8px}.product-prices strong{font-size:1.18rem;font-weight:900}.product-prices del{color:var(--muted);font-size:.9rem}.product-extra{display:flex;flex-wrap:wrap;gap:8px}.product-extra span{display:inline-flex;align-items:center;gap:6px;padding:7px 10px;border-radius:999px;background:#f5f8fa;color:var(--muted);font-size:.8rem;font-weight:700}
        .cta-banner{position:relative;overflow:hidden;display:grid;grid-template-columns:minmax(0,.95fr) minmax(0,1.05fr);gap:24px;align-items:center;padding:30px;border-radius:36px;background:linear-gradient(135deg,#09141f,#123042 55%,#1b4b60);color:#fff;box-shadow:0 32px 72px rgba(9,20,31,.2)}.cta-banner::before{content:"";position:absolute;inset:auto -12% -34% auto;width:320px;height:320px;background:radial-gradient(circle,rgba(244,223,159,.24),transparent 64%);pointer-events:none}.cta-banner img{width:100%;height:100%;max-height:320px;object-fit:cover;border-radius:28px;box-shadow:0 22px 46px rgba(0,0,0,.2)}.cta-content{position:relative;z-index:1}.cta-content h2{font-size:clamp(1.9rem,3vw,3rem);line-height:1.1;margin-bottom:12px}.cta-content p{margin:0 0 20px;color:rgba(255,255,255,.76)}
        .site-footer{margin-top:34px;padding:0 0 26px}.footer-shell{padding:28px;border-radius:34px;background:#09141f;color:#fff;box-shadow:0 28px 60px rgba(9,20,31,.2)}.footer-grid{display:grid;grid-template-columns:1.2fr .9fr .9fr 1fr;gap:22px}.footer-col ul{margin:0;padding:0;list-style:none;display:grid;gap:8px}.footer-note{margin-top:24px;padding-top:18px;border-top:1px solid rgba(255,255,255,.1);display:flex;flex-wrap:wrap;justify-content:space-between;gap:12px;color:rgba(255,255,255,.6);font-size:.9rem}
        .lightbox{position:fixed;inset:0;background:rgba(3,10,16,.82);backdrop-filter:blur(8px);display:grid;place-items:center;opacity:0;visibility:hidden;transition:.28s;z-index:100;padding:20px}.lightbox.show{opacity:1;visibility:visible}.lightbox-shell{position:relative;width:min(980px,100%);background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:30px;overflow:hidden;box-shadow:0 30px 60px rgba(0,0,0,.24)}.lightbox-shell img{width:100%;max-height:82vh;object-fit:contain;background:linear-gradient(145deg,#f7f2e8,#eef4f6)}.lightbox-close,.lightbox-nav{position:absolute;z-index:2;border:0;width:52px;height:52px;border-radius:18px;background:rgba(9,20,31,.72);color:#fff;display:grid;place-items:center;font-size:1.3rem}.lightbox-close{top:16px;left:16px}.lightbox-nav.prev{right:16px;top:calc(50% - 26px)}.lightbox-nav.next{left:16px;top:calc(50% - 26px)}
        .toast{position:fixed;right:20px;bottom:96px;z-index:110;min-width:260px;padding:14px 16px;border-radius:18px;background:rgba(9,20,31,.94);color:#fff;box-shadow:0 22px 44px rgba(9,20,31,.22);opacity:0;visibility:hidden;transform:translateY(14px);transition:.25s}.toast.show{opacity:1;visibility:visible;transform:translateY(0)}
        @media(max-width:1160px){.hero-layout,.detail-grid,.reviews-layout,.cta-banner{grid-template-columns:1fr}.trust-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.product-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.footer-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:900px){.header-top{flex-wrap:wrap}.search-form{order:3;width:100%}.action-grid{grid-template-columns:1fr}.gallery-main{min-height:420px}.product-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:640px){.container{width:min(100% - 20px,var(--container))}.site-header{padding-top:10px}.header-top{padding:14px;gap:14px}.header-nav{padding:0 14px 14px}.brand-text span{display:none}.floating-cart{left:12px;bottom:12px;padding:12px 14px}.gallery-card,.summary-card,.content-card{padding:18px;border-radius:24px}.gallery-main{min-height:320px;border-radius:24px}.thumbnail-strip,.trust-grid,.product-grid,.footer-grid{grid-template-columns:1fr}.footer-shell,.cta-banner{padding:20px;border-radius:26px}}
    </style>
</head>
<body>
    <header class="site-header">
        <div class="container">
            <div class="header-shell">
                <div class="header-top">
                    <a class="brand" href="#">
                        <span class="brand-mark">N</span>
                        <span class="brand-text">
                            <strong>Noor Select</strong>
                            <span>منتجات مختارة بذوق عصري</span>
                        </span>
                    </a>
                    <form class="search-form">
                        <input type="search" placeholder="ابحث عن سماعات، ساعات، عطور، أو عروض حصرية">
                        <button class="primary-button" type="submit">بحث سريع</button>
                    </form>
                    <div class="header-actions">
                        <button class="icon-button cart-trigger" type="button" data-open-cart aria-label="فتح السلة">🛒<span class="cart-pill" data-cart-count>0</span></button>
                        <button class="icon-button" type="button" aria-label="الحساب">👤</button>
                    </div>
                </div>
                <nav class="header-nav">
                    <a class="nav-link" href="#product-main">المنتج</a>
                    <a class="nav-link" href="#details">الوصف الكامل</a>
                    <a class="nav-link" href="#specs">المواصفات</a>
                    <a class="nav-link" href="#reviews">التقييمات</a>
                    <a class="nav-link" href="#faq">الأسئلة الشائعة</a>
                    <a class="nav-link" href="#related">منتجات مرتبطة</a>
                    <a class="nav-link" href="#latest">أحدث المنتجات</a>
                    <a class="nav-link" href="#popular">الأكثر طلباً</a>
                </nav>
            </div>
        </div>
    </header>

    <button class="floating-cart" type="button" data-open-cart aria-label="السلة العائمة"><strong>السلة</strong><span data-cart-count>0</span></button>
    <div class="cart-overlay" id="cartOverlay"></div>
    <aside class="cart-drawer" id="cartDrawer" aria-hidden="true">
        <div class="cart-head">
            <div>
                <h3>سلة المشتريات</h3>
                <div class="meta-inline">
                    <span class="chip">إدارة محلية سريعة</span>
                    <span class="chip">جاهزة للشراء</span>
                </div>
            </div>
            <button class="icon-button" type="button" id="closeCart" aria-label="إغلاق السلة">✕</button>
        </div>
        <div class="cart-body" id="cartItems"></div>
        <div class="cart-foot">
            <div class="subtotal-row"><span>المجموع الفرعي</span><span id="cartSubtotal">0 دج</span></div>
            <div class="cart-actions">
                <button class="secondary-button" type="button" id="clearCart">إفراغ السلة</button>
                <button class="primary-button" type="button">إتمام الطلب</button>
            </div>
        </div>
    </aside>

    <main>
        <div class="container">
            <nav class="breadcrumb" aria-label="breadcrumb">
                <a href="#">الرئيسية</a><span>/</span><a href="#">إلكترونيات فاخرة</a><span>/</span><a href="#">سماعات لاسلكية</a><span>/</span><span>سماعة Noor Air Elite</span>
            </nav>
        </div>

        <section class="page-section" id="product-main">
            <div class="container">
                <div class="hero-layout">
                    <div class="card gallery-card">
                        <div class="gallery-main" id="galleryMain">
                            <span class="gallery-badge">الأكثر طلباً هذا الأسبوع</span>
                            <button class="gallery-zoom" type="button" id="openLightbox">تكبير الصورة</button>
                            <img id="mainProductImage" src="assets/uploads/product-featured-1766857605.webp" alt="سماعة Noor Air Elite" data-index="0">
                        </div>
                        <div class="thumbnail-strip" id="thumbnailStrip">
                            <button class="thumbnail active" type="button" data-image="assets/uploads/product-featured-1766857605.webp" data-index="0"><img src="assets/uploads/product-featured-1766857605-w480.webp" alt="زاوية أمامية"></button>
                            <button class="thumbnail" type="button" data-image="assets/uploads/product-additional-133-1769813433-0.webp" data-index="1"><img src="assets/uploads/product-additional-133-1769813433-0.webp" alt="تفصيل جانبي"></button>
                            <button class="thumbnail" type="button" data-image="assets/uploads/product-additional-133-1769813433-1.webp" data-index="2"><img src="assets/uploads/product-additional-133-1769813433-1.webp" alt="تفصيل الخامة"></button>
                            <button class="thumbnail" type="button" data-image="assets/uploads/product-additional-133-1769813433-2.webp" data-index="3"><img src="assets/uploads/product-additional-133-1769813433-2.webp" alt="علبة المنتج"></button>
                        </div>
                    </div>

                    <div class="card summary-card" id="productSummary" data-product-id="main-elite" data-product-title="سماعة Noor Air Elite اللاسلكية بعزل ضوضاء ذكي" data-product-price="24900" data-product-image="assets/uploads/product-featured-1766857605.webp" data-product-badge="عرض فاخر">
                        <span class="eyebrow">إصدار حصري 2026</span>
                        <h1 class="summary-title">سماعة Noor Air Elite اللاسلكية بعزل ضوضاء ذكي وصوت ثلاثي الأبعاد</h1>
                        <div class="rating-row">
                            <div class="rating-stars" aria-label="تقييم 4.9 من 5"><span>★</span><span>★</span><span>★</span><span>★</span><span>★</span></div>
                            <span class="chip">4.9 من 5</span>
                            <span class="chip">128 تقييم موثق</span>
                            <span class="chip">+1,420 مشاهدة هذا الشهر</span>
                        </div>
                        <div class="price-block">
                            <div class="price-now">24,900 دج</div>
                            <div class="price-old">31,500 دج</div>
                            <div class="saving-chip">وفّر 6,600 دج | خصم 21%</div>
                        </div>
                        <div class="stock-row">
                            <span class="chip available">متوفر في المخزون</span>
                            <span class="chip">يشحن خلال 24 ساعة</span>
                            <span class="chip">ضمان رسمي 12 شهر</span>
                        </div>
                        <p class="short-copy">سماعة مصممة لمن يريد صوتًا فخمًا واستخدامًا يوميًا مريحًا. تمنحك عزل ضوضاء فعلي، بطارية طويلة، وميكروفونات واضحة للمكالمات، مع خامات أنيقة تناسب الهدية والاستخدام الشخصي.</p>

                        <div class="option-group">
                            <h3>اختر اللون</h3>
                            <div class="option-grid" id="colorOptions">
                                <button class="option-button color-button active" type="button" data-color="أسود ليلي" data-image="assets/uploads/product-featured-1766857605.webp"><span class="swatch" style="background:#1b1f23"></span>أسود ليلي</button>
                                <button class="option-button color-button" type="button" data-color="فضي حريري" data-image="assets/uploads/product-additional-133-1769813433-0.webp"><span class="swatch" style="background:#ccd3da"></span>فضي حريري</button>
                                <button class="option-button color-button" type="button" data-color="أزرق داكن" data-image="assets/uploads/product-additional-133-1769813433-2.webp"><span class="swatch" style="background:#274964"></span>أزرق داكن</button>
                            </div>
                        </div>

                        <div class="option-group">
                            <h3>اختر الإصدار</h3>
                            <div class="option-grid" id="variantOptions">
                                <button class="option-button active" type="button" data-variant="قياسي">قياسي</button>
                                <button class="option-button" type="button" data-variant="مع حافظة جلدية">مع حافظة جلدية</button>
                                <button class="option-button" type="button" data-variant="نسخة هدايا فاخرة">نسخة هدايا فاخرة</button>
                            </div>
                        </div>

                        <div class="option-group">
                            <h3>اختر الحجم</h3>
                            <div class="option-grid" id="sizeOptions">
                                <button class="option-button" type="button" data-size="صغير">صغير</button>
                                <button class="option-button active" type="button" data-size="متوسط">متوسط</button>
                                <button class="option-button" type="button" data-size="كبير">كبير</button>
                            </div>
                        </div>

                        <div class="purchase-panel">
                            <div class="purchase-row">
                                <strong>الكمية</strong>
                                <div class="quantity-selector" id="mainQtySelector">
                                    <button type="button" data-qty-action="increase">+</button>
                                    <input type="number" id="mainQty" value="1" min="1">
                                    <button type="button" data-qty-action="decrease">−</button>
                                </div>
                            </div>
                            <div class="action-grid">
                                <button class="primary-button" type="button" id="addMainToCart">أضف إلى السلة</button>
                                <button class="secondary-button" type="button" id="buyNowButton">اشتر الآن</button>
                            </div>
                            <div class="sub-actions">
                                <button class="secondary-button wishlist-button" type="button" id="wishlistButton">إضافة إلى المفضلة</button>
                                <button class="secondary-button" type="button" id="shareButton">مشاركة المنتج</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="trust-grid section-block">
                    <article class="trust-card"><strong>شحن سريع ومنظم</strong><p>تجهيز الطلب خلال ساعات مع تغليف محكم وتتبع واضح حتى الاستلام.</p></article>
                    <article class="trust-card"><strong>دفع آمن</strong><p>خيارات دفع موثوقة مع معالجة آمنة ومعلومات محمية بالكامل.</p></article>
                    <article class="trust-card"><strong>إرجاع سهل</strong><p>يمكنك طلب الاستبدال أو الإرجاع بسهولة إذا لم تكن التجربة كما توقعت.</p></article>
                    <article class="trust-card"><strong>دعم سريع</strong><p>فريق خدمة عملاء يرد بسرعة لمساعدتك قبل الشراء وبعده.</p></article>
                </div>
            </div>
        </section>

        <section class="page-section" id="details">
            <div class="container">
                <div class="detail-grid">
                    <div class="content-card">
                        <div class="block-head">
                            <div>
                                <h2>وصف تفصيلي للمنتج</h2>
                                <p>واجهة عرض واضحة ومحتوى مقنع يرفع ثقة الزبون ويقلل التردد قبل الشراء.</p>
                            </div>
                        </div>
                        <p>تم تطوير سماعة Noor Air Elite لتقدم توازنًا فعليًا بين الشكل الراقي والأداء اليومي العملي. هي مناسبة للاستخدام في العمل، التنقل، أو التمرين الخفيف، وتمنحك صوتًا غنيًا مع طبقات واضحة في الباس والتفاصيل دون أن تصبح مرهقة للأذن.</p>
                        <p>وسائد الأذن ناعمة ومريحة للاستخدام الطويل، بينما يمنحك نظام العزل الذكي تجربة أكثر هدوءًا في الأماكن المزدحمة. كما تم ضبط الميكروفونات بحيث تبدو المكالمات أوضح في البيئات الصاخبة، مع إمكانية التبديل السريع بين الأجهزة الذكية.</p>
                        <div class="feature-list">
                            <div class="feature-item"><div class="feature-icon">01</div><div><strong>صوت متوازن بتفاصيل دقيقة</strong><div>تمت معايرة الترددات للحصول على صوت غني ومريح مع حضور واضح للصوت البشري.</div></div></div>
                            <div class="feature-item"><div class="feature-icon">02</div><div><strong>عزل ضوضاء ذكي</strong><div>يقلل الضجيج الخارجي تلقائيًا حتى تركز على الموسيقى أو المكالمات المهمة.</div></div></div>
                            <div class="feature-item"><div class="feature-icon">03</div><div><strong>تصميم أنيق جاهز للهدايا</strong><div>شكل فاخر بملمس ناعم وتفاصيل معدنية راقية تمنح انطباعًا احترافيًا عند الاستلام.</div></div></div>
                        </div>
                    </div>

                    <div class="content-card" id="specs">
                        <div class="block-head">
                            <div>
                                <h2>المواصفات</h2>
                                <p>عرض منظم وسريع القراءة يساعد العميل على اتخاذ قرار الشراء بثقة.</p>
                            </div>
                        </div>
                        <div class="spec-grid">
                            <div class="spec-item"><span>نوع الاتصال</span><strong>Bluetooth 5.3</strong></div>
                            <div class="spec-item"><span>مدة البطارية</span><strong>حتى 38 ساعة</strong></div>
                            <div class="spec-item"><span>زمن الشحن</span><strong>90 دقيقة</strong></div>
                            <div class="spec-item"><span>عزل الضوضاء</span><strong>نشط متعدد المستويات</strong></div>
                            <div class="spec-item"><span>المواد</span><strong>ألياف ناعمة + هيكل معدني خفيف</strong></div>
                            <div class="spec-item"><span>التوافق</span><strong>Android / iPhone / Laptop</strong></div>
                            <div class="spec-item"><span>المحتويات</span><strong>السماعة + كيبل شحن + حافظة + دليل</strong></div>
                            <div class="spec-item"><span>الضمان</span><strong>12 شهر</strong></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
