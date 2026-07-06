import { useState } from "react";
import { Link } from "react-router-dom";
import { money, phpBase } from "../services/api";
import CustomerServiceModal from "./CustomerServiceModal";
import { ProductGridSkeleton } from "./Skeleton";

function Icon({ name }) {
  const icons = {
    arrow: (
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={2.5} stroke="currentColor" style={{ width: "16px", height: "16px", transform: "scaleX(-1)" }}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
      </svg>
    ),
    grid: (
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor" style={{ width: "20px", height: "20px" }}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
      </svg>
    ),
    search: (
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={2.5} stroke="currentColor" style={{ width: "18px", height: "18px" }}>
        <path strokeLinecap="round" strokeLinejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.603 10.603Z" />
      </svg>
    ),
    menu: (
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={2.25} stroke="currentColor" style={{ width: "21px", height: "21px" }}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M4 7h16M4 12h16M4 17h16" />
      </svg>
    ),
    user: (
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={2.1} stroke="currentColor" style={{ width: "20px", height: "20px" }}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 7.5a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.25a7.5 7.5 0 0 1 15 0" />
      </svg>
    ),
    bag: (
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor" style={{ width: "22px", height: "22px" }}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
      </svg>
    ),
    star: (
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style={{ width: "15px", height: "15px", color: "var(--amber)" }}>
        <path fillRule="evenodd" d="M10.788 2.903a.75.75 0 0 1 1.424 0l2.082 5.006 5.404.434a.75.75 0 0 1 .416 1.328l-4.04 3.743 1.144 5.307a.75.75 0 0 1-1.157.84L12 18.232l-4.714 2.856a.75.75 0 0 1-1.157-.84l1.144-5.307-4.04-3.743a.75.75 0 0 1 .416-1.328l5.404-.434 2.082-5.005Z" clipRule="evenodd" />
      </svg>
    ),
    chevronDown: (
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor" style={{ width: "16px", height: "16px" }}>
        <path strokeLinecap="round" strokeLinejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
      </svg>
    )
  };

  return <span className="textIcon" aria-hidden="true">{icons[name] || null}</span>;
}

export function StoreHeader({ store, categories = [], actions = null }) {
  const visibleCategories = categories.slice(0, 6);
  const [isServiceModalOpen, setIsServiceModalOpen] = useState(false);
  const [initialServiceTab, setInitialServiceTab] = useState("track");

  return (
    <header className="storeHeader">
      <div className="headerControls">
        <details className="headerMenu">
          <summary className="headerIconButton" aria-label="القائمة" title="القائمة">
            <Icon name="menu" />
          </summary>
          <div className="headerMenuPanel">
            <Link to="/">الرئيسية</Link>
            {visibleCategories.map((category) => (
              <Link key={category.id} to={toSpaPath(category.url)}>{category.name}</Link>
            ))}
            <a href={`${phpBase}/customer-order.php`}>طلباتي</a>
            <a href={`${phpBase}/contact.php`}>الدعم</a>

            <div style={{ padding: "12px 0 4px", borderTop: "1px solid #e2e8f0", marginTop: "8px", display: "flex", flexDirection: "column", gap: "8px" }}>
              <button
                onClick={() => {
                  setInitialServiceTab("exchange");
                  setIsServiceModalOpen(true);
                  document.querySelector('.headerMenu').removeAttribute('open');
                }}
                style={{
                  textAlign: "right", background: "#f8fafc", border: "1px solid #e2e8f0", borderRadius: "10px",
                  padding: "12px 14px", color: "var(--teal)", fontWeight: "bold",
                  cursor: "pointer", fontFamily: "inherit", fontSize: "15px",
                  display: "flex", alignItems: "center", gap: "10px", transition: "all 0.2s"
                }}
              >
                <span style={{ fontSize: "18px" }}>🔄</span> طلب تبديل منتج
              </button>

              <button
                onClick={() => {
                  setInitialServiceTab("track");
                  setIsServiceModalOpen(true);
                  document.querySelector('.headerMenu').removeAttribute('open');
                }}
                style={{
                  textAlign: "right", background: "#f8fafc", border: "1px solid #e2e8f0", borderRadius: "10px",
                  padding: "12px 14px", color: "var(--teal)", fontWeight: "bold",
                  cursor: "pointer", fontFamily: "inherit", fontSize: "15px",
                  display: "flex", alignItems: "center", gap: "10px", transition: "all 0.2s"
                }}
              >
                <span style={{ fontSize: "18px" }}>📍</span> تتبع حالة طلب
              </button>
            </div>
          </div>
        </details>

        <a className="headerIconButton accountButton" href={`${phpBase}/customer-order.php`} aria-label="الحساب والطلبات" title="الحساب والطلبات">
          <Icon name="user" />
          <span className="accountPulse" aria-hidden="true" />
        </a>

        {actions ? <div className="headerActions">{actions}</div> : null}
      </div>

      <CustomerServiceModal isOpen={isServiceModalOpen} onClose={() => setIsServiceModalOpen(false)} initialTab={initialServiceTab} />

      <form className="searchBox" action={`${phpBase}/search-result.php`} method="get" role="search">
        <Icon name="search" />
        <input name="search_text" type="search" placeholder="ابحث عن منتج..." autoComplete="off" />
      </form>

      <nav>
        {visibleCategories.map((category) => (
          <Link key={category.id} to={toSpaPath(category.url)}>{category.name}</Link>
        ))}
      </nav>

      <Link className="brand" to="/">
        {store?.logo ? <img src={store.logo} alt="" width={42} height={42} /> : <span>GS</span>}
        <strong>{store?.name || "Golden Store DZ"}</strong>
      </Link>
    </header>
  );
}

function isInternalUrl(url) {
  if (!url || url.startsWith("http")) return false;
  if (url.startsWith("/") && !url.includes(".php")) return true;
  if (url.includes(".php")) return true;
  return false;
}

function toSpaPath(url) {
  if (!url) return "/";
  if (url.startsWith("http")) return url;
  let path = url;
  path = path.replace(/buy-now\.php/, "/buy-now");
  path = path.replace(/landing_page_2\.php/, "/landing_page_2");
  path = path.replace(/landing_page\.php/, "/landing_page");
  path = path.replace(/product-category\.php/, "/category");
  if (!path.startsWith("/")) path = "/" + path;
  return path;
}

function ProductCard({ product }) {
  const [imgLoaded, setImgLoaded] = useState(false);
  const [imgError, setImgError] = useState(false);
  const hasDiscountBadge = product.badge && product.badge !== "متاح" && product.badge !== "متوفر";

  const imgContent = (
    <>
      {!imgLoaded && !imgError && (
        <div className="skeleton" style={{ width: "100%", height: "100%", borderRadius: "0", position: "absolute", inset: 0 }} />
      )}
      {product.image && !imgError ? (
        <img
          src={product.image}
          alt={product.name}
          loading="lazy"
          decoding="async"
          width={400}
          height={400}
          onLoad={() => setImgLoaded(true)}
          onError={() => setImgError(true)}
          style={{ opacity: imgLoaded ? 1 : 0 }}
          itemProp="image"
        />
      ) : !imgError ? (
        <Icon name="bag" />
      ) : null}
      {hasDiscountBadge ? <span className="badge">{product.badge}</span> : null}
    </>
  );

  return (
    <article className="productTile" itemScope itemType="https://schema.org/Product">
      <Link className="productImage" to={toSpaPath(product.url)} tabIndex={-1}>{imgContent}</Link>
      <div className="productInfo">
        <h3 itemProp="name">{product.name}</h3>
        <div className="productMeta" itemProp="offers" itemScope itemType="https://schema.org/Offer">
          <strong itemProp="price" content={product.price}>{money(product.price)}</strong>
          {product.oldPrice > product.price ? <del itemProp="priceSpecification">{money(product.oldPrice)}</del> : null}
        </div>
        <div className="tileFooter">
          <small><Icon name="star" /> {product.views || 0}</small>
          <Link to={toSpaPath(product.url)} aria-label={`اطلب ${product.name}`}>
            اطلب الآن <Icon name="arrow" />
          </Link>
        </div>
      </div>
    </article>
  );
}

export function ProductSection({ title, products, loading }) {
  if (loading) {
    return (
      <section className="productSection">
        <div className="sectionTitle">
          <span><Icon name="grid" /> {title}</span>
        </div>
        <ProductGridSkeleton count={8} />
      </section>
    );
  }

  if (!products?.length) return null;

  return (
    <section className="productSection">
      <div className="sectionTitle">
        <span><Icon name="grid" /> {title}</span>
      </div>
      <div className="productGrid">
        {products.map((product) => (
          <ProductCard key={product.id} product={product} />
        ))}
      </div>
    </section>
  );
}
