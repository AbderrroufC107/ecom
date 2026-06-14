import { getProduct } from "../lib/api";
import OrderPage from "../app/components/OrderPage";

export default function LandingPage({ data }) {
  return <OrderPage data={data} variant="landing" />;
}

export async function getServerSideProps({ query }) {
  const data = await getProduct(query?.id, "landing_page");
  return { props: { data } };
}
