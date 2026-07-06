import { BrowserRouter, Routes, Route, Navigate } from "react-router-dom";
import HomePage from "../pages/HomePage";
import CategoryPage from "../pages/CategoryPage";
import BuyNowPage from "../pages/BuyNowPage";
import LandingPage from "../pages/LandingPage";
import LandingPageTwo from "../pages/LandingPageTwo";

export default function AppRouter() {
  return (
    <BrowserRouter basename="/customer-store">
      <Routes>
        <Route path="/" element={<HomePage />} />
        <Route path="/category" element={<CategoryPage />} />
        <Route path="/buy-now" element={<BuyNowPage />} />
        <Route path="/landing_page" element={<LandingPage />} />
        <Route path="/landing_page_2" element={<LandingPageTwo />} />
        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </BrowserRouter>
  );
}
