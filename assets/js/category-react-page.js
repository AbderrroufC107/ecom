(function () {
  const root = document.getElementById('react-category-root');
  const dataNode = document.getElementById('react-category-data');
  if (!root || !dataNode || !window.React || !window.ReactDOM) return;

  const h = React.createElement;
  const data = JSON.parse(dataNode.textContent || '{}');
  const cartKey = 'ecom_cart_v1';

  function formatPrice(value) {
    return new Intl.NumberFormat('fr-DZ').format(Number(value) || 0);
  }

  function readCart() {
    try {
      return JSON.parse(localStorage.getItem(cartKey) || '[]');
    } catch (e) {
      return [];
    }
  }

  function saveCart(items) {
    localStorage.setItem(cartKey, JSON.stringify(items));
  }

  function Icon({ name }) {
    return h('i', { className: 'fa ' + name, 'aria-hidden': 'true' });
  }

  function Header({ store, categories, count, onOpenCart }) {
    return h('header', { className: 'cat-head' },
      h('div', { className: 'cat-container cat-head-grid' },
        h('a', { className: 'cat-brand', href: 'index.php' },
          store.logo ? h('img', { src: store.logo, alt: store.name }) : h('span', null, (store.fallbackLogo || 'MT').slice(0, 2)),
          h('strong', null, store.name || 'متجر الثقة')
        ),
        h('form', { className: 'cat-search', action: store.searchAction || 'search-result.php', method: 'get' },
          h(Icon, { name: 'fa-search' }),
          h('input', { type: 'search', name: 'search_text', placeholder: 'ابحث داخل المتجر' }),
          h('button', { type: 'submit' }, 'بحث')
        ),
        h('button', { type: 'button', className: 'cat-cart-btn', onClick: onOpenCart },
          h(Icon, { name: 'fa-shopping-cart' }),
          h('span', null, 'السلة'),
          h('b', null, count)
        )
      ),
      categories && categories.length ? h('nav', { className: 'cat-container cat-chip-row' },
        categories.map(cat => h('a', { key: cat.id, href: cat.url }, cat.name))
      ) : null
    );
  }

  function Hero({ category }) {
    const style = category.banner ? { backgroundImage: `linear-gradient(90deg, rgba(15,32,51,.84), rgba(15,32,51,.32)), url("${category.banner}")` } : {};
    return h('section', { className: 'cat-hero', style },
      h('div', { className: 'cat-container cat-hero-inner' },
        h('div', null,
          h('div', { className: 'cat-crumbs' },
            h('a', { href: 'index.php' }, 'الرئيسية'),
            (category.breadcrumb || []).map(item => h(React.Fragment, { key: item.url },
              h('span', null, '/'),
              h('a', { href: item.url }, item.label)
            ))
          ),
          h('span', { className: 'cat-kicker' }, 'تصنيف المنتجات'),
          h('h1', null, category.title || 'المنتجات'),
          h('p', null, 'تصفح المنتجات المتاحة في هذا التصنيف مع فرز سريع وسلة واضحة.'),
          h('div', { className: 'cat-hero-stats' },
            h('strong', null, formatPrice(category.count || 0)),
            h('span', null, 'منتج متاح')
          )
        )
      )
    );
  }

  function Sidebar({ groups }) {
    if (!groups || !groups.length) return null;
    return h('aside', { className: 'cat-side' },
      h('div', { className: 'cat-side-head' },
        h(Icon, { name: 'fa-list-ul' }),
        h('strong', null, 'التصنيفات')
      ),
      groups.map(group => h('div', { className: 'cat-side-group', key: group.id },
        h('a', { className: 'cat-side-main', href: group.url }, group.name),
        group.children && group.children.length ? h('div', { className: 'cat-side-children' },
          group.children.slice(0, 8).map(child => h('a', { key: child.id, href: child.url }, child.name))
        ) : null
      ))
    );
  }

  function Rating({ value }) {
    const rounded = Math.round(Number(value) || 0);
    return h('div', { className: 'cat-rating', title: `${value || 0}/5` },
      [1, 2, 3, 4, 5].map(star => h('i', { key: star, className: 'fa ' + (star <= rounded ? 'fa-star' : 'fa-star-o') }))
    );
  }

  function ProductCard({ product, onAdd }) {
    return h('article', { className: 'cat-product' },
      h('a', { className: 'cat-product-media', href: product.url },
        h('span', { className: product.soldOut ? 'cat-badge is-out' : 'cat-badge' }, product.badge),
        product.image ? h('img', { src: product.image, alt: product.name, loading: 'lazy' }) : h('div', { className: 'cat-empty-img' }, 'صورة المنتج')
      ),
      h('div', { className: 'cat-product-body' },
        h('h3', null, h('a', { href: product.url }, product.name)),
        h('div', { className: 'cat-price' },
          h('strong', null, formatPrice(product.price) + ' دج'),
          product.oldPrice > product.price ? h('del', null, formatPrice(product.oldPrice) + ' دج') : null
        ),
        h('div', { className: 'cat-meta-row' },
          h(Rating, { value: product.rating }),
          product.views > 0 ? h('span', null, h(Icon, { name: 'fa-eye' }), formatPrice(product.views)) : null
        ),
        h('div', { className: 'cat-actions' },
          h('a', { className: 'cat-btn cat-btn-dark', href: product.url }, 'عرض المنتج'),
          product.soldOut
            ? h('span', { className: 'cat-sold' }, 'غير متاح')
            : h('button', { type: 'button', className: 'cat-btn cat-btn-primary', onClick: () => onAdd(product) }, h(Icon, { name: 'fa-shopping-bag' }), 'أضف')
        )
      )
    );
  }

  function CartDrawer({ cart, open, onClose, onClear, onQty, onRemove }) {
    const total = cart.reduce((sum, item) => sum + (Number(item.price) || 0) * item.qty, 0);
    return h(React.Fragment, null,
      h('div', { className: open ? 'cat-cart-backdrop open' : 'cat-cart-backdrop', onClick: onClose }),
      h('aside', { className: open ? 'cat-cart open' : 'cat-cart' },
        h('div', { className: 'cat-cart-head' },
          h('div', null, h('span', null, 'السلة'), h('h2', null, 'المنتجات المختارة')),
          h('button', { type: 'button', className: 'cat-icon-btn', onClick: onClose }, h(Icon, { name: 'fa-times' }))
        ),
        h('div', { className: 'cat-cart-body' },
          cart.length ? cart.map(item => h('div', { className: 'cat-cart-item', key: item.id },
            item.image ? h('img', { src: item.image, alt: item.name }) : h('div', { className: 'cat-cart-thumb' }),
            h('div', null,
              h('h3', null, item.name),
              h('p', null, formatPrice(item.price) + ' دج'),
              h('div', { className: 'cat-qty' },
                h('button', { type: 'button', onClick: () => onQty(item.id, -1) }, '-'),
                h('span', null, item.qty),
                h('button', { type: 'button', onClick: () => onQty(item.id, 1) }, '+')
              ),
              h('button', { type: 'button', className: 'cat-link-btn', onClick: () => onRemove(item.id) }, 'حذف')
            )
          )) : h('div', { className: 'cat-cart-empty' }, 'السلة فارغة حاليا.')
        ),
        h('div', { className: 'cat-cart-foot' },
          h('div', { className: 'cat-total' }, h('span', null, 'المجموع'), h('strong', null, formatPrice(total) + ' دج')),
          h('div', { className: 'cat-cart-actions' },
            h('button', { type: 'button', className: 'cat-btn cat-btn-ghost', onClick: onClear }, 'إفراغ'),
            h('button', { type: 'button', className: 'cat-btn cat-btn-primary', onClick: onClose }, 'متابعة')
          )
        )
      )
    );
  }

  function Catalog() {
    const [cart, setCart] = React.useState(readCart);
    const [cartOpen, setCartOpen] = React.useState(false);
    const [sort, setSort] = React.useState('latest');
    const [query, setQuery] = React.useState('');
    const products = data.products || [];
    const count = cart.reduce((sum, item) => sum + item.qty, 0);

    React.useEffect(() => saveCart(cart), [cart]);

    function addProduct(product) {
      setCart(current => {
        const found = current.find(item => String(item.id) === String(product.id));
        if (found) {
          return current.map(item => String(item.id) === String(product.id) ? Object.assign({}, item, { qty: item.qty + 1 }) : item);
        }
        return current.concat([{ id: product.id, name: product.name, price: product.price, image: product.image, url: product.url, qty: 1 }]);
      });
      setCartOpen(true);
    }

    const filtered = products
      .filter(product => product.name.toLowerCase().includes(query.trim().toLowerCase()))
      .slice()
      .sort((a, b) => {
        if (sort === 'price-low') return a.price - b.price;
        if (sort === 'price-high') return b.price - a.price;
        if (sort === 'popular') return b.views - a.views;
        return b.id - a.id;
      });

    return h('main', { className: 'cat-page' },
      h(Header, { store: data.store || {}, categories: data.topCategories || [], count, onOpenCart: () => setCartOpen(true) }),
      h(Hero, { category: data.category || {} }),
      h('section', { className: 'cat-catalog' },
        h('div', { className: 'cat-container cat-layout' },
          h(Sidebar, { groups: data.sideCategories || [] }),
          h('div', { className: 'cat-main' },
            h('div', { className: 'cat-toolbar' },
              h('div', null,
                h('span', { className: 'cat-kicker' }, 'قائمة المنتجات'),
                h('h2', null, filtered.length + ' منتج')
              ),
              h('div', { className: 'cat-controls' },
                h('input', { value: query, onChange: e => setQuery(e.target.value), placeholder: 'بحث داخل التصنيف' }),
                h('select', { value: sort, onChange: e => setSort(e.target.value) },
                  h('option', { value: 'latest' }, 'الأحدث'),
                  h('option', { value: 'popular' }, 'الأكثر مشاهدة'),
                  h('option', { value: 'price-low' }, 'السعر الأقل'),
                  h('option', { value: 'price-high' }, 'السعر الأعلى')
                )
              )
            ),
            filtered.length
              ? h('div', { className: 'cat-grid' }, filtered.map(product => h(ProductCard, { key: product.id, product, onAdd: addProduct })))
              : h('div', { className: 'cat-empty' }, 'لا توجد منتجات مطابقة في هذا التصنيف.')
          )
        )
      ),
      h(CartDrawer, {
        cart,
        open: cartOpen,
        onClose: () => setCartOpen(false),
        onClear: () => setCart([]),
        onRemove: id => setCart(current => current.filter(item => String(item.id) !== String(id))),
        onQty: (id, step) => setCart(current => current.map(item => String(item.id) === String(id) ? Object.assign({}, item, { qty: Math.max(1, item.qty + step) }) : item))
      })
    );
  }

  root.setAttribute('data-react-ready', '1');
  ReactDOM.createRoot(root).render(h(Catalog));
})();
