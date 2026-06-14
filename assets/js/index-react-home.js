(function () {
  const root = document.getElementById('react-home-root');
  const dataNode = document.getElementById('react-home-data');
  if (!root || !dataNode || !window.React || !window.ReactDOM) {
    return;
  }

  const h = React.createElement;
  const data = JSON.parse(dataNode.textContent || '{}');
  const cartKey = 'ecom_cart_v1';

  function formatPrice(value) {
    return new Intl.NumberFormat('fr-DZ').format(Number(value) || 0);
  }

  function readCart() {
    try {
      return JSON.parse(localStorage.getItem(cartKey) || '[]');
    } catch (error) {
      return [];
    }
  }

  function writeCart(items) {
    localStorage.setItem(cartKey, JSON.stringify(items));
  }

  function Icon(props) {
    return h('i', { className: 'fa ' + props.name, 'aria-hidden': 'true' });
  }

  function ProductCard({ product, onAdd }) {
    if (!product) return null;
    return h('article', { className: 'react-product' },
      h('a', { className: 'react-product-media', href: product.url },
        product.badge ? h('span', { className: 'react-badge' }, product.badge) : null,
        product.image
          ? h('img', { src: product.image, alt: product.name, loading: 'lazy' })
          : h('div', { className: 'react-image-empty' }, 'صورة المنتج')
      ),
      h('div', { className: 'react-product-body' },
        h('h3', null, product.name),
        h('div', { className: 'react-price-row' },
          h('strong', null, formatPrice(product.price) + ' دج'),
          product.oldPrice > product.price ? h('del', null, formatPrice(product.oldPrice) + ' دج') : null
        ),
        h('div', { className: 'react-product-meta' },
          product.saving > 0 ? h('span', null, 'وفرت ' + formatPrice(product.saving) + ' دج') : null,
          product.views > 0 ? h('span', null, h(Icon, { name: 'fa-eye' }), formatPrice(product.views) + ' مشاهدة') : null
        ),
        h('div', { className: 'react-product-actions' },
          h('a', { className: 'react-btn react-btn-dark', href: product.url }, 'عرض المنتج'),
          h('button', { className: 'react-btn react-btn-primary', type: 'button', onClick: () => onAdd(product) },
            h(Icon, { name: 'fa-shopping-bag' }),
            'أضف'
          )
        )
      )
    );
  }

  function CartDrawer({ cart, open, onClose, onClear, onQty, onRemove }) {
    const total = cart.reduce((sum, item) => sum + (Number(item.price) || 0) * item.qty, 0);
    return h(React.Fragment, null,
      h('div', { className: open ? 'react-cart-backdrop is-open' : 'react-cart-backdrop', onClick: onClose }),
      h('aside', { className: open ? 'react-cart is-open' : 'react-cart', 'aria-hidden': open ? 'false' : 'true' },
        h('div', { className: 'react-cart-head' },
          h('div', null,
            h('span', null, 'السلة'),
            h('h2', null, 'طلباتك المختارة')
          ),
          h('button', { type: 'button', className: 'react-icon-btn', onClick: onClose, 'aria-label': 'إغلاق السلة' }, h(Icon, { name: 'fa-times' }))
        ),
        h('div', { className: 'react-cart-body' },
          cart.length === 0
            ? h('div', { className: 'react-cart-empty' }, 'السلة فارغة حاليا.')
            : cart.map(item => h('div', { className: 'react-cart-item', key: item.id },
              item.image ? h('img', { src: item.image, alt: item.name }) : h('div', { className: 'react-cart-thumb' }),
              h('div', null,
                h('h3', null, item.name),
                h('p', null, formatPrice(item.price) + ' دج'),
                h('div', { className: 'react-qty' },
                  h('button', { type: 'button', onClick: () => onQty(item.id, -1) }, '-'),
                  h('span', null, item.qty),
                  h('button', { type: 'button', onClick: () => onQty(item.id, 1) }, '+')
                ),
                h('button', { type: 'button', className: 'react-link-btn', onClick: () => onRemove(item.id) }, 'حذف')
              )
            ))
        ),
        h('div', { className: 'react-cart-foot' },
          h('div', { className: 'react-cart-total' },
            h('span', null, 'المجموع'),
            h('strong', null, formatPrice(total) + ' دج')
          ),
          h('div', { className: 'react-cart-foot-actions' },
            h('button', { type: 'button', className: 'react-btn react-btn-ghost', onClick: onClear }, 'إفراغ'),
            h('button', { type: 'button', className: 'react-btn react-btn-primary', onClick: onClose }, 'متابعة التسوق')
          )
        )
      )
    );
  }

  function Hero({ hero, stats, onAdd }) {
    let slides = Array.isArray(hero.slides) && hero.slides.length ? hero.slides : [];
    
    // Fallback to top products if no slides configured
    if (slides.length === 0) {
        const preloaded = window.__PRELOADED_STATE__ || {};
        const allProducts = (preloaded.sections || []).reduce(function(acc, s) {
            return acc.concat(s.products || []);
        }, []);
        slides = allProducts.filter(function(p) { return p.image; }).slice(0, 5).map(function(p) {
            return {
                image: p.image,
                heading: p.name,
                content: p.price + ' دج',
                product: p
            };
        });
    }

    const [slideIndex, setSlideIndex] = React.useState(0);
    const activeSlide = slides[slideIndex] || null;
    React.useEffect(() => {
      if (slides.length < 2) return undefined;
      const timer = window.setInterval(() => setSlideIndex(index => (index + 1) % slides.length), 4000);
      return () => window.clearInterval(timer);
    }, [slides.length]);

    const heroImage = activeSlide ? activeSlide.image : (hero.product ? hero.product.image : hero.ctaImage);
    return h('section', { className: 'react-hero' },
      h('div', { className: 'react-container react-hero-grid' },
        h('div', { className: 'react-hero-copy' },
          h('span', { className: 'react-kicker' }, 'تجربة تسوق حديثة'),
          h('h1', null, hero.title || 'متجر الثقة'),
          h('p', null, hero.subtitle || 'واجهة سريعة وواضحة لعرض المنتجات والطلب المباشر.'),
          h('div', { className: 'react-hero-actions' },
            h('a', { className: 'react-btn react-btn-primary', href: '#products' }, 'تصفح المنتجات'),
            hero.ctaText && hero.ctaUrl ? h('a', { className: 'react-btn react-btn-ghost', href: hero.ctaUrl }, hero.ctaText) : null
          ),
          h('div', { className: 'react-stat-strip' },
            (stats || []).map(item => h('div', { key: item.label },
              h('strong', null, Number(item.value) > 0 ? formatPrice(item.value) : 'جاهز'),
              h('span', null, item.label)
            ))
          )
        ),
        h('div', { className: 'react-hero-visual' },
          heroImage ? h('img', { src: heroImage, alt: activeSlide ? activeSlide.heading || hero.title : hero.title }) : null,
          h('div', { className: 'react-hero-panel' },
            h('span', null, activeSlide ? activeSlide.heading || 'عرض مميز' : 'منتج مميز'),
            h('h2', null, activeSlide ? activeSlide.content || hero.title : (hero.product ? hero.product.name : hero.title)),
            hero.product ? h('button', { className: 'react-btn react-btn-dark', type: 'button', onClick: () => onAdd(hero.product) }, 'أضف المنتج') : null
          ),
          slides.length > 1 ? h('div', { className: 'react-dots' },
            slides.map((slide, index) => h('button', {
              key: slide.image + index,
              type: 'button',
              className: index === slideIndex ? 'is-active' : '',
              onClick: () => setSlideIndex(index),
              'aria-label': 'عرض ' + (index + 1)
            }))
          ) : null
        )
      )
    );
  }

  function Header({ store, categories, cartCount, onOpenCart }) {
    return h('header', { className: 'react-store-head' },
      h('div', { className: 'react-container react-head-grid' },
        h('a', { className: 'react-brand', href: 'index.php' },
          store.logo ? h('img', { src: store.logo, alt: store.name }) : h('span', null, (store.fallbackLogo || 'MT').slice(0, 2)),
          h('strong', null, store.name || 'متجر الثقة')
        ),
        h('form', { className: 'react-search', action: store.searchAction || 'search-result.php', method: 'get' },
          h(Icon, { name: 'fa-search' }),
          h('input', { type: 'search', name: 'search_text', placeholder: 'ابحث عن منتج، فئة، أو عرض' }),
          h('button', { type: 'submit' }, 'بحث')
        ),
        h('button', { type: 'button', className: 'react-cart-btn', onClick: onOpenCart },
          h(Icon, { name: 'fa-shopping-cart' }),
          h('span', null, 'السلة'),
          h('b', null, cartCount)
        )
      ),
      categories && categories.length ? h('nav', { className: 'react-container react-category-row' },
        categories.map(cat => h('a', { key: cat.id, href: cat.url }, cat.name))
      ) : null
    );
  }

  function ProductSections({ sections, onAdd }) {
    const available = (sections || []).filter(section => section.products && section.products.length);
    const [active, setActive] = React.useState(available[0] ? available[0].key : '');
    const current = available.find(section => section.key === active) || available[0];
    if (!current) return null;

    return h('section', { className: 'react-products', id: 'products' },
      h('div', { className: 'react-container' },
        h('div', { className: 'react-section-head' },
          h('div', null,
            h('span', { className: 'react-kicker' }, current.eyebrow),
            h('h2', null, current.title),
            h('p', null, current.subtitle)
          ),
          h('div', { className: 'react-tabs' },
            available.map(section => h('button', {
              key: section.key,
              type: 'button',
              className: section.key === current.key ? 'is-active' : '',
              onClick: () => setActive(section.key)
            }, section.title))
          )
        ),
        h('div', { className: 'react-product-grid' },
          current.products.map(product => h(ProductCard, { key: product.id, product, onAdd }))
        )
      )
    );
  }

  function Services({ services }) {
    if (!services || !services.length) return null;
    return h('section', { className: 'react-services' },
      h('div', { className: 'react-container react-services-grid' },
        services.map((service, index) => h('div', { className: 'react-service', key: service.title || index },
          service.image ? h('img', { src: service.image, alt: service.title, loading: 'lazy' }) : h(Icon, { name: 'fa-check-circle' }),
          h('div', null,
            h('h3', null, service.title),
            service.content ? h('p', null, service.content) : null
          )
        ))
      )
    );
  }

  function Reviews() {
    return h('section', { className: 'react-reviews-sec react-container' },
      h('div', { className: 'react-section-head' },
        h('div', null,
          h('span', { className: 'react-kicker' }, '⭐ آراء وتجارب عملائنا الموثقة'),
          h('h2', null, 'ماذا يقولون عنا؟')
        )
      ),
      h('div', { className: 'react-reviews-grid' },
        h('div', { className: 'react-review-card' },
          h('div', { className: 'react-review-stars' }, '★★★★★'),
          h('p', { className: 'react-review-text' }, '"بصراحة منتج رائع جداً والتوصيل كان أسرع مما توقعت. تعامل راقٍ وخدمة ممتازة، سأشتري من هنا مجدداً بالتأكيد!"'),
          h('div', { className: 'react-review-user' },
            h('div', { className: 'react-review-avatar' }, 'أم'),
            h('div', { className: 'react-review-user-info' },
              h('h5', null, 'أحمد محمد'),
              h('span', null, '✓ مشترٍ مؤكد')
            )
          )
        ),
        h('div', { className: 'react-review-card' },
          h('div', { className: 'react-review-stars' }, '★★★★★'),
          h('p', { className: 'react-review-text' }, '"جودة المنتج ممتازة ومطابق تماماً للصور والوصف في الموقع. الدفع عند الاستلام منحني طمأنينة كبيرة. شكراً لكم."'),
          h('div', { className: 'react-review-user' },
            h('div', { className: 'react-review-avatar' }, 'يب'),
            h('div', { className: 'react-review-user-info' },
              h('h5', null, 'ياسين بوهالي'),
              h('span', null, '✓ مشترٍ مؤكد')
            )
          )
        ),
        h('div', { className: 'react-review-card' },
          h('div', { className: 'react-review-stars' }, '★★★★★'),
          h('p', { className: 'react-review-text' }, '"أفضل تجربة شراء إلكتروني خضتها في الجزائر. سرعة في التجاوب والتوصيل في غضون 48 ساعة فقط. أنصح الجميع بالتعامل معهم."'),
          h('div', { className: 'react-review-user' },
            h('div', { className: 'react-review-avatar' }, 'سر'),
            h('div', { className: 'react-review-user-info' },
              h('h5', null, 'سهام رابحي'),
              h('span', null, '✓ مشترٍ مؤكد')
            )
          )
        )
      )
    );
  }

  function Footer({ store }) {
    return h('footer', { className: 'react-premium-footer' },
      h('div', { className: 'react-footer-inner react-container' },
        h('div', { className: 'react-footer-brand' },
          h('h3', null,
            store.logo ? h('img', { src: store.logo, alt: store.name, style: { width: "30px", height: "30px", borderRadius: "6px", objectFit: "contain", display: "inline-block", marginLeft: "10px" } }) : h('span', { style: { display: "inline-block", width: "30px", height: "30px", background: "var(--rx-teal)", color: "#fff", borderRadius: "6px", textAlign: "center", lineHeight: "30px", marginLeft: "10px", fontWeight: "900" } }, 'MT'),
            store.name || "متجر الثقة"
          ),
          h('p', null, 'وجهتكم الأولى للتسوق الآمن والسريع في الجزائر. نوفر لكم تشكيلة مختارة من أرقى المنتجات بأفضل الأسعار مع ضمان الجودة والتوصيل السريع.'),
          h('div', { className: 'react-footer-socials' },
            h('a', { href: '#', 'aria-label': 'Facebook' }, 'FB'),
            h('a', { href: '#', 'aria-label': 'Instagram' }, 'IG'),
            h('a', { href: '#', 'aria-label': 'TikTok' }, 'TK')
          )
        ),
        h('div', { className: 'react-footer-links' },
          h('h4', null, 'روابط مساعدة'),
          h('ul', null,
            h('li', null, h('a', { href: '/' }, 'الصفحة الرئيسية')),
            h('li', null, h('a', { href: '/faq' }, 'الأسئلة الشائعة')),
            h('li', null, h('a', { href: '/about' }, 'من نحن')),
            h('li', null, h('a', { href: '/contact' }, 'اتصل بنا'))
          )
        ),
        h('div', { className: 'react-footer-newsletter' },
          h('h4', null, 'النشرة البريدية'),
          h('p', null, 'اشترك معنا للحصول على آخر العروض الحصرية والتخفيضات المميزة قبل الجميع.'),
          h('form', { className: 'react-newsletter-form', onSubmit: (e) => e.preventDefault() },
            h('input', { type: 'email', placeholder: 'بريدك الإلكتروني', required: true }),
            h('button', { type: 'submit' }, 'اشتراك')
          ),
          h('div', { className: 'react-trust-badges' },
            h('span', { className: 'react-trust-badge' }, 'الدفع عند الاستلام'),
            h('span', { className: 'react-trust-badge' }, 'شحن سريع 58 ولاية'),
            h('span', { className: 'react-trust-badge' }, 'ضمان 100%')
          )
        )
      ),
      h('div', { className: 'react-footer-bottom' },
        h('div', { className: 'react-container react-footer-bottom-flex' },
          h('div', null, '© ' + new Date().getFullYear() + ' ' + (store.name || "متجر الثقة") + '. جميع الحقوق محفوظة.'),
          h('div', null, 'صنع بكل حب لتجربة تسوق فريدة ومميزة')
        )
      )
    );
  }

  function App() {
    const [cart, setCart] = React.useState(readCart);
    const [cartOpen, setCartOpen] = React.useState(false);
    const cartCount = cart.reduce((sum, item) => sum + item.qty, 0);

    React.useEffect(() => {
      writeCart(cart);
    }, [cart]);

    function addProduct(product) {
      setCart(current => {
        const existing = current.find(item => String(item.id) === String(product.id));
        if (existing) {
          return current.map(item => String(item.id) === String(product.id) ? Object.assign({}, item, { qty: item.qty + 1 }) : item);
        }
        return current.concat([{
          id: product.id,
          name: product.name,
          price: product.price,
          image: product.image,
          url: product.url,
          qty: 1
        }]);
      });
      setCartOpen(true);
    }

    return h('main', { className: 'react-home' },
      h(Header, { store: data.store || {}, categories: data.categories || [], cartCount, onOpenCart: () => setCartOpen(true) }),
      h(Hero, { hero: data.hero || {}, stats: data.stats || [], onAdd: addProduct }),
      h(Services, { services: data.services || [] }),
      h(ProductSections, { sections: data.sections || [], onAdd: addProduct }),
      h(Reviews, null),
      h(CartDrawer, {
        cart,
        open: cartOpen,
        onClose: () => setCartOpen(false),
        onClear: () => setCart([]),
        onRemove: id => setCart(current => current.filter(item => String(item.id) !== String(id))),
        onQty: (id, step) => setCart(current => current.map(item => String(item.id) === String(id) ? Object.assign({}, item, { qty: Math.max(1, item.qty + step) }) : item))
      }),
      h(Footer, { store: data.store || {} })
    );
  }

  root.setAttribute('data-react-ready', '1');
  ReactDOM.createRoot(root).render(h(App));
})();
