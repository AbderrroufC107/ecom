import { getProduct } from "../../lib/api";
import OrderPage from "../components/OrderPage";

export const dynamic = "force-dynamic";

export default async function LandingPageTwo({ searchParams }) {
  const params = await searchParams;
  const data = await getProduct(params?.id, "landing_page_2");
  return <OrderPage data={data} variant="split" />;
}
