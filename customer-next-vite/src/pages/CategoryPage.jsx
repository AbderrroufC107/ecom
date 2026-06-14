import { useSearchParams } from "react-router-dom";
import { useCatalog } from "../hooks/useCatalog";
import { StoreHeader, ProductSection } from "../components/Storefront";
import { ProductGridSkeleton } from "../components/Skeleton";

export default function CategoryPage() {
  const [searchParams] = useSearchParams();
  const id = searchParams.get("id") || "";
  const type = searchParams.get("type") || "";
  const { data, loading } = useCatalog({ mode: "category", id, type });

  if (loading) {
    return (
      <main dir="rtl">
        <StoreHeader store={null} categories={[]} />
        <section className="catalogHead">
          <span className="eyebrow">التصنيف</span>
          <h1>جاري التحميل...</h1>
          <p>يتم تحميل المنتجات...</p>
        </section>
        <div className="pageWrap">
          <ProductGridSkeleton count={8} />
        </div>
      </main>
    );
  }

  return (
    <main dir="rtl">
      <StoreHeader store={data.store} categories={data.categories} />
      <section className="catalogHead">
        <span className="eyebrow">التصنيف</span>
        <h1>{data.category?.title}</h1>
        <p>{data.category?.count || 0} منتج متاح داخل هذا التصنيف.</p>
      </section>
      <div className="pageWrap">
        <ProductSection title="المنتجات" products={data.products || []} />
      </div>
    </main>
  );
}
