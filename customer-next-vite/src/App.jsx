import { HelmetProvider } from "react-helmet-async";
import AppRouter from "./routes";

export default function App() {
  return (
    <HelmetProvider>
      <AppRouter />
    </HelmetProvider>
  );
}
