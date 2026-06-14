import { useSearchParams } from "react-router-dom";
import { useProduct } from "../hooks/useProduct";
import OrderPage from "../components/OrderPage";

export default function BuyNowPage() {
  const [searchParams] = useSearchParams();
  const id = searchParams.get("id") || "";
  const { data, loading } = useProduct(id, "buy-now");

  if (loading) {
    return <div style={{ padding: "40px", textAlign: "center" }}>جاري التحميل...</div>;
  }

  if (!data) {
    return <div style={{ padding: "40px", textAlign: "center" }}>المنتج غير متوفر</div>;
  }

  return <OrderPage data={data} variant="compact" />;
}
