import { Link } from "react-router-dom";
import { useCatalog } from "../hooks/useCatalog";
import { phpBase } from "../services/api";
import { StoreHeader, ProductSection } from "../components/Storefront";
import HeroCarousel from "../components/HeroCarousel";
import { HeroSkeleton } from "../components/Skeleton";

function isInternalUrl(url) {
  return url && url.startsWith("/") && !url.includes(".php") && !url.startsWith("http");
}

export default function HomePage() {
  const { data, loading } = useCatalog({ mode: "home" });

  if (loading) {
    return (
      <main>
        <HeroSkeleton />
        <div className="pageWrap">
          <ProductSection title="قيد التحميل..." loading />
        </div>
      </main>
    );
  }

  const store = data?.store;
  const sections = data?.sections || [];
  const featuredSection = sections?.[0];
  const heroProduct = featuredSection?.products?.[0];

  const carouselProducts = sections
    .flatMap(s => s.products)
    .filter(p => p.image)
    .slice(0, 5);

  const remainingSections = sections.slice(1);

  return (
    <>
      <a href="#main-content" className="skipLink">تخطى إلى المحتوى الرئيسي</a>
      <StoreHeader store={store} categories={data?.categories || []} />

      <main id="main-content" dir="rtl">
        <section className="hero" aria-label="القسم الرئيسي">
          <div className="heroText">
            <span className="eyebrow">✦ تجربة تسوق فريدة</span>
            <h1>{data?.hero?.title || store?.name || "متجر الثقة"}</h1>
            <p className="heroSubtitle">
              {data?.hero?.subtitle || "نقدم لكم أفضل المنتجات الحصرية بجودة عالية وخدمة توصيل سريعة ودفع آمن عند الاستلام."}
            </p>
            {heroProduct ? (
              isInternalUrl(heroProduct.url) ? (
                <Link className="primaryLink" to={heroProduct.url}>
                  اكتشف المنتجات الآن
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={2.5} stroke="currentColor" width="18" height="18" style={{ marginRight: "8px", transform: "scaleX(-1)" }}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                  </svg>
                </Link>
              ) : (
                <a className="primaryLink" href={heroProduct.url}>
                  اكتشف المنتجات الآن
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={2.5} stroke="currentColor" width="18" height="18" style={{ marginRight: "8px", transform: "scaleX(-1)" }}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                  </svg>
                </a>
              )
            ) : null}
          </div>
          <div className="heroMedia">
            <HeroCarousel products={carouselProducts.length > 0 ? carouselProducts : (heroProduct ? [heroProduct] : [])} />
          </div>
        </section>

        <div className="sectionDivider" aria-hidden="true" />

        <div className="pageWrap">
          {featuredSection && (
            <ProductSection title={featuredSection.title} products={featuredSection.products || []} />
          )}

          {remainingSections.map((section) => (
            <ProductSection key={section.key} title={section.title} products={section.products || []} />
          ))}
        </div>

        <section className="featuresSec" aria-label="مميزاتنا">
          <div className="sectionDivider" aria-hidden="true" />
          <div className="featuresGrid">
            <div className="featureCard">
              <div className="featureIcon">🚚</div>
              <div>
                <h4>توصيل سريع وموثوق</h4>
                <p>توصيل سريع ومباشر إلى باب منزلك في 58 ولاية</p>
              </div>
            </div>
            <div className="featureCard">
              <div className="featureIcon">💵</div>
              <div>
                <h4>الدفع عند الاستلام</h4>
                <p>لا داعي للدفع مسبقاً، ادفع نقداً فقط عندما تستلم طلبك</p>
              </div>
            </div>
            <div className="featureCard">
              <div className="featureIcon">🛡️</div>
              <div>
                <h4>ضمان الجودة والرضا</h4>
                <p>جميع منتجاتنا أصلية وتخضع لمراقبة جودة صارمة</p>
              </div>
            </div>
            <div className="featureCard">
              <div className="featureIcon">📞</div>
              <div>
                <h4>دعم متواصل 24/7</h4>
                <p>فريق خدمة العملاء لدينا جاهز لمساعدتك في أي وقت</p>
              </div>
            </div>
          </div>
        </section>

        <section className="reviewsSec" aria-label="آراء العملاء">
          <div className="sectionDivider" aria-hidden="true" />
          <div className="pageWrap">
            <div className="sectionTitle">
              <span>⭐ آراء وتجارب عملائنا الموثقة</span>
            </div>
            <div className="reviewsGrid">
              <div className="reviewCard">
                <div className="reviewStars" aria-label="5 نجوم من 5">★★★★★</div>
                <p className="reviewText">&ldquo;بصراحة منتج رائع جداً والتوصيل كان أسرع مما توقعت. تعامل راقٍ وخدمة ممتازة، سأشتري من هنا مجدداً بالتأكيد!&rdquo;</p>
                <div className="reviewUser">
                  <div className="reviewAvatar" aria-hidden="true">أم</div>
                  <div>
                    <h5>أحمد محمد</h5>
                    <span className="verifiedBadge">✓ مشترٍ مؤكد</span>
                  </div>
                </div>
              </div>
              <div className="reviewCard">
                <div className="reviewStars" aria-label="5 نجوم من 5">★★★★★</div>
                <p className="reviewText">&ldquo;جودة المنتج ممتازة ومطابق تماماً للصور والوصف في الموقع. الدفع عند الاستلام منحني طمأنينة كبيرة. شكراً لكم.&rdquo;</p>
                <div className="reviewUser">
                  <div className="reviewAvatar" aria-hidden="true">يب</div>
                  <div>
                    <h5>ياسين بوهالي</h5>
                    <span className="verifiedBadge">✓ مشترٍ مؤكد</span>
                  </div>
                </div>
              </div>
              <div className="reviewCard">
                <div className="reviewStars" aria-label="5 نجوم من 5">★★★★★</div>
                <p className="reviewText">&ldquo;أفضل تجربة شراء إلكتروني خضتها في الجزائر. سرعة في التجاوب والتوصيل في غضون 48 ساعة فقط. أنصح الجميع بالتعامل معهم.&rdquo;</p>
                <div className="reviewUser">
                  <div className="reviewAvatar" aria-hidden="true">سر</div>
                  <div>
                    <h5>سهام رابحي</h5>
                    <span className="verifiedBadge">✓ مشترٍ مؤكد</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>

        <footer className="premiumFooter" aria-label="تذييل الموقع">
          <div className="sectionDivider" aria-hidden="true" />
          <div className="footerInner">
            <div className="footerBrand">
              <h3>
                {store?.logo ? (
                  <img src={store.logo} alt="" width={30} height={30} />
                ) : (
                  <span aria-hidden="true">MT</span>
                )}
                {store?.name || "متجر الثقة"}
              </h3>
              <p>وجهتكم الأولى للتسوق الآمن والسريع في الجزائر. نوفر لكم تشكيلة مختارة من أرقى المنتجات بأفضل الأسعار مع ضمان الجودة والتوصيل السريع.</p>
              <div className="footerSocials">
                <a href="#" aria-label="فيسبوك" title="فيسبوك">FB</a>
                <a href="#" aria-label="إنستغرام" title="إنستغرام">IG</a>
                <a href="#" aria-label="تيك توك" title="تيك توك">TK</a>
                <a href="#" aria-label="يوتيوب" title="يوتيوب">YT</a>
              </div>
            </div>
            <div className="footerLinks">
              <h4>روابط مساعدة</h4>
              <ul>
                <li><Link to="/">الصفحة الرئيسية</Link></li>
                <li><a href={`${phpBase}/faq.php`}>الأسئلة الشائعة</a></li>
                <li><a href={`${phpBase}/about.php`}>من نحن</a></li>
                <li><a href={`${phpBase}/contact.php`}>اتصل بنا</a></li>
              </ul>
            </div>
            <div className="footerNewsletter">
              <h4>النشرة البريدية</h4>
              <p>اشترك معنا للحصول على آخر العروض الحصرية والتخفيضات المميزة قبل الجميع.</p>
              <form className="newsletterForm" onSubmit={(e) => e.preventDefault()}>
                <input type="email" placeholder="بريدك الإلكتروني" required aria-label="البريد الإلكتروني" />
                <button type="submit">اشتراك</button>
              </form>
              <div className="trustBadges">
                <span className="trustBadge">الدفع عند الاستلام</span>
                <span className="trustBadge">شحن سريع 58 ولاية</span>
                <span className="trustBadge">ضمان 100%</span>
              </div>
            </div>
          </div>
          <div className="footerBottom">
            <span>© {new Date().getFullYear()} {store?.name || "متجر الثقة"}. جميع الحقوق محفوظة.</span>
            <span>صنع بكل حب لتجربة تسوق فريدة ومميزة</span>
          </div>
        </footer>
      </main>
    </>
  );
}
