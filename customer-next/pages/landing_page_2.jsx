import { getProduct } from "../lib/api";
import OrderPage from "../app/components/OrderPage";

export default function LandingPageTwo({ data }) {
  return <OrderPage data={data} variant="split" />;
}

export async function getServerSideProps({ query }) {
  const data = await getProduct(query?.id, "landing_page_2");
  return { props: { data } };
}
