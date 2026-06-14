import { getProduct } from "../lib/api";
import OrderPage from "../app/components/OrderPage";

export default function BuyNowPage({ data }) {
  return <OrderPage data={data} variant="compact" />;
}

export async function getServerSideProps({ query }) {
  const data = await getProduct(query?.id, "buy-now");
  return { props: { data } };
}
