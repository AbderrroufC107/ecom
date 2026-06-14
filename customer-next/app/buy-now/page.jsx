import { getProduct } from "../../lib/api";
import OrderPage from "../components/OrderPage";

export const dynamic = "force-dynamic";

export default async function BuyNowPage({ searchParams }) {
  const params = await searchParams;
  const data = await getProduct(params?.id, "buy-now");
  return <OrderPage data={data} variant="compact" />;
}
