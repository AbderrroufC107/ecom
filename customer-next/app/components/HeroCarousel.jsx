"use client";
import { useState, useEffect, useCallback } from "react";

const FALLBACK_IMAGE = "https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?q=80&w=1200&auto=format&fit=crop";
const INTERVAL_MS = 4000;

export default function HeroCarousel({ products }) {
  const [currentIndex, setCurrentIndex] = useState(0);

  const goTo = useCallback((index) => {
    setCurrentIndex(index);
  }, []);

  useEffect(() => {
    if (!products || products.length <= 1) return;
    const interval = setInterval(() => {
      setCurrentIndex((prev) => (prev + 1) % products.length);
    }, INTERVAL_MS);
    return () => clearInterval(interval);
  }, [products]);

  if (!products || products.length === 0) {
    return <div style={{ width: "100%", height: "100%", background: "linear-gradient(135deg, var(--teal-light), #fff)" }} />;
  }

  return (
    <div
      className="heroCarouselTrack"
      role="region"
      aria-label="عرض المنتجات"
      aria-roledescription="carousel"
    >
      {products.map((p, i) => (
        <div
          key={i}
          className="heroCarouselSlide"
          role="group"
          aria-roledescription="slide"
          aria-label={`الشريحة ${i + 1} من ${products.length}`}
          aria-hidden={i !== currentIndex}
          style={{ opacity: i === currentIndex ? 1 : 0 }}
        >
          <img
            src={p.image || FALLBACK_IMAGE}
            alt={p.name}
            fetchpriority={i === 0 ? "high" : "auto"}
            loading={i === 0 ? "eager" : "lazy"}
            decoding="async"
            width={800}
            height={680}
            onError={(e) => { e.currentTarget.src = FALLBACK_IMAGE; }}
          />
          <div className="heroCarouselCaption">
            <span className="heroCarouselName">{p.name}</span>
            <span className="heroCarouselPrice">{p.price} دج</span>
          </div>
        </div>
      ))}
      {products.length > 1 && (
        <div className="heroCarouselDots" role="tablist" aria-label="الشرائح">
          {products.map((_, i) => (
            <button
              key={i}
              onClick={() => goTo(i)}
              className={`heroCarouselDot${i === currentIndex ? " isActive" : ""}`}
              role="tab"
              aria-selected={i === currentIndex}
              aria-label={`الشريحة ${i + 1}`}
            />
          ))}
        </div>
      )}
    </div>
  );
}
