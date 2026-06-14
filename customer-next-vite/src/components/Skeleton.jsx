export function Skeleton({ width, height, borderRadius = "8px", style }) {
  return (
    <div
      className="skeleton"
      style={{ width, height, borderRadius, ...style }}
      aria-hidden="true"
    />
  );
}

export function ProductCardSkeleton() {
  return (
    <article className="productTile" aria-hidden="true">
      <div className="productImage">
        <div className="skeleton" style={{ width: "100%", height: "100%", borderRadius: "0" }} />
      </div>
      <div className="productInfo">
        <div className="skeleton" style={{ width: "85%", height: "16px", marginBottom: "10px" }} />
        <div className="skeleton" style={{ width: "65%", height: "14px", marginBottom: "14px" }} />
        <div className="skeleton" style={{ width: "45%", height: "26px", marginBottom: "12px" }} />
        <div style={{ borderTop: "1px solid #f1f5f9", paddingTop: "12px", display: "flex", justifyContent: "space-between" }}>
          <div className="skeleton" style={{ width: "60px", height: "16px" }} />
          <div className="skeleton" style={{ width: "80px", height: "36px", borderRadius: "999px" }} />
        </div>
      </div>
    </article>
  );
}

export function ProductGridSkeleton({ count = 8 }) {
  return (
    <div className="productGrid">
      {Array.from({ length: count }, (_, i) => (
        <ProductCardSkeleton key={i} />
      ))}
    </div>
  );
}

export function HeroSkeleton() {
  return (
    <section className="hero" aria-hidden="true">
      <div className="heroText">
        <div className="skeleton" style={{ width: "140px", height: "32px", borderRadius: "999px", marginBottom: "16px" }} />
        <div className="skeleton" style={{ width: "80%", height: "56px", marginBottom: "16px" }} />
        <div className="skeleton" style={{ width: "60%", height: "18px", marginBottom: "8px" }} />
        <div className="skeleton" style={{ width: "45%", height: "18px", marginBottom: "28px" }} />
        <div className="skeleton" style={{ width: "200px", height: "48px", borderRadius: "999px" }} />
      </div>
      <div className="heroMedia">
        <div className="skeleton" style={{ width: "100%", height: "100%", borderRadius: "24px" }} />
      </div>
    </section>
  );
}

export function TestimonialSkeleton() {
  return (
    <div className="reviewCard" aria-hidden="true">
      <div className="skeleton" style={{ width: "120px", height: "18px", marginBottom: "12px" }} />
      <div className="skeleton" style={{ width: "100%", height: "14px", marginBottom: "6px" }} />
      <div className="skeleton" style={{ width: "100%", height: "14px", marginBottom: "6px" }} />
      <div className="skeleton" style={{ width: "70%", height: "14px", marginBottom: "16px" }} />
      <div style={{ display: "flex", alignItems: "center", gap: "12px" }}>
        <div className="skeleton" style={{ width: "40px", height: "40px", borderRadius: "50%" }} />
        <div>
          <div className="skeleton" style={{ width: "100px", height: "14px", marginBottom: "4px" }} />
          <div className="skeleton" style={{ width: "80px", height: "12px" }} />
        </div>
      </div>
    </div>
  );
}
