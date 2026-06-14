import "./globals.css";

export const metadata = {
  title: "متجر الثقة | وجهة التسوق الآمنة في الجزائر",
  description: "تسوق بثقة وأمان في الجزائر. نقدم تشكيلة مميزة من المنتجات عالية الجودة مع خدمة توصيل سريع إلى جميع الولايات والدفع عند الاستلام.",
  viewport: "width=device-width, initial-scale=1, viewport-fit=cover",
  themeColor: "#0d9488",
  openGraph: {
    title: "متجر الثقة | وجهة التسوق الآمنة في الجزائر",
    description: "تسوق بثقة وأمان في الجزائر. نقدم تشكيلة مميزة من المنتجات عالية الجودة مع خدمة توصيل سريع إلى جميع الولايات والدفع عند الاستلام.",
    type: "website",
    locale: "ar_DZ",
    siteName: "متجر الثقة",
  },
  other: {
    "format-detection": "telephone=yes",
  },
};

export default function RootLayout({ children }) {
  return (
    <html lang="ar" dir="rtl">
      <head>
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="anonymous" />
        <link
          href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&display=swap"
          rel="stylesheet"
        />
      </head>
      <body>{children}</body>
    </html>
  );
}
