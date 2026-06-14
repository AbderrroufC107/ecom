import { getCatalog } from "../lib/api";
import { StoreHeader, ProductSection } from "../app/components/Storefront";

export default function CategoryPage({ data }) {
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

export async function getServerSideProps({ query }) {
  const data = await getCatalog({
    mode: "category",
    id: query?.id || "",
    type: query?.type || ""
  });
  return { props: { data } };
}
