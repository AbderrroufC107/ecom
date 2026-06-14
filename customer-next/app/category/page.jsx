import { getCatalog } from "../../lib/api";
import { StoreHeader, ProductSection } from "../components/Storefront";

export const dynamic = "force-dynamic";

export default async function CategoryPage({ searchParams }) {
  const params = await searchParams;
  const data = await getCatalog({
    mode: "category",
    id: params?.id || "",
    type: params?.type || ""
  });

  return (
    <main>
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
