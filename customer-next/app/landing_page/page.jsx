import { getProduct } from "../../lib/api";
import OrderPage from "../components/OrderPage";

export const dynamic = "force-dynamic";

export default async function LandingPage({ searchParams }) {
  const params = await searchParams;
  const data = await getProduct(params?.id, "landing_page");
  return <OrderPage data={data} variant="landing" />;
}
