import { useEffect, useMemo, useState } from "react";
import { Link } from "react-router-dom";
import { money, phpBase } from "../services/api";
import { communesList, wilayasList } from "../services/wilayas-communes";
import { StoreHeader } from "./Storefront";

function stripHtml(text) {
  return String(text || "").replace(/<[^>]*>/g, " ").replace(/\s+/g, " ").trim();
}

function firstAvailableDelivery(prices, wilaya) {
  const entry = prices?.[wilaya] || {};
  return Object.keys(entry).find((type) => Number(entry[type] || 0) > 0) || "";
}

function makeDeviceId() {
  try {
    const key = "site_device_id";
    const oldKey = "ecom_customer_device_id";

    let existing = window.localStorage.getItem(key) || window.localStorage.getItem(oldKey);
    if (existing) {
      if (!window.localStorage.getItem(key)) {
        window.localStorage.setItem(key, existing);
      }
      return existing;
    }

    const cookieMatch = document.cookie.match(new RegExp('(?:^|; )' + key.replace(/[.$?*|{}()[\]\\/+^]/g, '\\$&') + '=([^;]*)'));
    if (cookieMatch) {
      const val = decodeURIComponent(cookieMatch[1]);
      if (val) {
        window.localStorage.setItem(key, val);
        return val;
      }
    }

    const value = window.crypto?.randomUUID?.() || `dev-${Date.now()}-${Math.random().toString(16).slice(2)}`;
    window.localStorage.setItem(key, value);
    window.localStorage.setItem(oldKey, value);

    const maxAge = 60 * 60 * 24 * 365 * 2;
    document.cookie = `${key}=${encodeURIComponent(value)}; path=/; max-age=${maxAge}; SameSite=Lax`;

    return value;
  } catch {
    return `dev-${Date.now()}`;
  }
}

function normalizePhone(value) {
  const digits = String(value || "").replace(/[^\d]/g, "").slice(0, 10);
  if (/^[567][0-9]{8}$/.test(digits)) {
    return `0${digits}`;
  }
  return digits;
}

function deliveryLabel(type) {
  if (String(type).includes("مكتب")) return "للمكتب (Stop Desk)";
  if (String(type).includes("منزل")) return "للمنزل";
  return type;
}

function deliveryHint(type) {
  if (String(type).includes("مكتب")) return "أرخص وأسرع";
  if (String(type).includes("منزل")) return "مريح";
  return "متاح";
}

function ProductImages({ product, photos, currentPhoto, setCurrentPhoto }) {
  return (
    <section className="landing-single-image">
      <div className="landing-container">
        <h1>{product.name}</h1>
        <div className="landing-main-image">
          {currentPhoto ? <img src={currentPhoto} alt={product.name} /> : null}
        </div>
      </div>
    </section>
  );
}

function LandingHeader({ store }) {
  return (
    <header className="landing-header">
      <div className="landing-container landing-header-inner">
        <Link className="landing-logo" to="/">
          {store?.logo ? <img src={store.logo} alt={store?.name || "Store"} /> : <span>{store?.name || "Store"}</span>}
        </Link>
        <nav className="landing-menu-inline" aria-label="القائمة الرئيسية">
          <a href="#top">الرئيسية</a>
          <a href="#orderFormSection">اطلب الآن</a>
          <a href="#landingPhotos">صور المنتج</a>
          <a href="#stepsSection">خطوات الطلب</a>
          <a href="#faqSection">الأسئلة الشائعة</a>
        </nav>
      </div>
    </header>
  );
}

function LandingCarousel({ product, photos, currentPhoto, setCurrentPhoto }) {
  if (!photos?.length) return null;

  return (
    <section className="landing-carousel">
      <div className="landing-container">
        <div className="landing-carousel-frame">
          <div className="landing-carousel-title">{product.name}</div>
          <div className="landing-carousel-main">
            <div className="landing-carousel-item">
              <img src={currentPhoto || photos[0]} alt={product.name} />
            </div>
          </div>
        </div>
        {photos.length > 1 ? (
          <div className="landing-carousel-thumbs">
            {photos.slice(0, 6).map((item) => (
              <button type="button" key={item} onClick={() => setCurrentPhoto(item)} className={item === currentPhoto ? "active" : ""}>
                <img src={item} alt="" />
              </button>
            ))}
          </div>
        ) : null}
      </div>
    </section>
  );
}

export default function OrderPage({ data, variant = "compact" }) {
  const product = data.product;
  const prices = data.delivery?.prices || {};
  const shippingWilayas = Object.keys(prices);
  const wilayas = wilayasList.filter((wilaya) => shippingWilayas.includes(wilaya.name));
  const popularOffer = data.offers?.find((offer) => offer.popular) || data.offers?.[0] || null;
  const [photo, setPhoto] = useState(data.photos?.[0] || "");
  const [offerId, setOfferId] = useState(popularOffer?.id || 0);
  const [deviceId, setDeviceId] = useState("");
  const [form, setForm] = useState({
    customer_name: "",
    customer_phone: "",
    wilaya: "",
    commune: "",
    selected_size: data.sizes?.[0]?.id || "",
    selected_color: data.colors?.[0]?.id || "",
    delivery_type: "",
    quantity: 1
  });
  const [status, setStatus] = useState({ type: "", message: "" });
  const [submitting, setSubmitting] = useState(false);
  const [createdOrder, setCreatedOrder] = useState(null);

  const selectedOffer = useMemo(
    () => data.offers?.find((offer) => Number(offer.id) === Number(offerId)) || null,
    [data.offers, offerId]
  );

  const quantity = selectedOffer ? Number(selectedOffer.qty || 1) : Number(form.quantity || 1);
  const unitPrice = selectedOffer ? Number(selectedOffer.unitPrice || 0) : Number(product.price || 0);
  const deliveryEntry = prices?.[form.wilaya] || {};
  const deliveryType = form.delivery_type || firstAvailableDelivery(prices, form.wilaya);
  const shippingFee = data.delivery?.mode === "free" ? 0 : Number(deliveryEntry?.[deliveryType] || 0);
  const subtotal = quantity * unitPrice;
  const total = subtotal + shippingFee;
  const description = stripHtml(product.moreDescription || product.description || product.shortDescription);
  const selectedWilaya = wilayasList.find((wilaya) => wilaya.name === form.wilaya);
  const communeOptions = selectedWilaya ? communesList[selectedWilaya.id] || [] : [];

  useEffect(() => {
    setDeviceId(makeDeviceId());
  }, []);

  useEffect(() => {
    if (!form.wilaya) return;
    const nextType = firstAvailableDelivery(prices, form.wilaya);
    setForm((current) => ({ ...current, delivery_type: nextType }));
  }, [form.wilaya, prices]);

  useEffect(() => {
    const color = data.colors?.find((item) => item.id === form.selected_color);
    if (color?.photo) setPhoto(color.photo);
  }, [form.selected_color, data.colors]);

  function updateField(name, value) {
    setForm((current) => ({
      ...current,
      [name]: value,
      ...(name === "wilaya" ? { commune: "" } : {})
    }));
  }

  function saveIncomplete() {
    if (!form.customer_name || !form.customer_phone) return;
    const body = new FormData();
    body.append("customer_name", form.customer_name);
    body.append("customer_phone", normalizePhone(form.customer_phone));
    body.append("product_id", product.id);
    body.append("product_name", product.name);
    body.append("quantity", quantity);
    body.append("unit_price", unitPrice);
    body.append("total_price", total);
    body.append("selected_size", form.selected_size);
    body.append("selected_color", form.selected_color);
    body.append("wilaya", form.wilaya);
    body.append("commune", form.commune);
    body.append("address", form.commune);
    body.append("delivery_type", deliveryType);
    body.append("device_id", deviceId);
    body.append("source", "next");

    try {
      navigator.sendBeacon?.(`${data.baseUrl || phpBase}/save-incomplete-order.php`, body);
    } catch {
    }
  }

  async function checkExistingPhone() {
    const phone = normalizePhone(form.customer_phone);
    if (!/^0[567][0-9]{8}$/.test(phone)) return;
    const body = new FormData();
    body.append("phone", phone);
    body.append("customer_name", form.customer_name);
    body.append("wilaya", form.wilaya);
    body.append("commune", form.commune);
    body.append("device_id", deviceId);

    try {
      const response = await fetch(`${data.baseUrl || phpBase}/check-existing-order.php`, { method: "POST", body });
      const payload = await response.json();
      if (payload.exists) {
        setStatus({ type: "warn", message: payload.message || "يوجد طلب سابق بنفس الرقم." });
      }
    } catch {
      setStatus({ type: "", message: "" });
    }
  }

  async function submitOrder(event) {
    event.preventDefault();
    setSubmitting(true);
    setStatus({ type: "", message: "" });

    try {
      const response = await fetch(`${data.baseUrl || phpBase}/api/next-order.php`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          product_id: product.secureId || product.id,
          offer_id: offerId,
          quantity,
          customer_name: form.customer_name,
          customer_phone: normalizePhone(form.customer_phone),
          wilaya: form.wilaya,
          commune: form.commune,
          address: form.commune,
          selected_size: form.selected_size,
          selected_color: form.selected_color,
          delivery_type: deliveryType,
          device_id: deviceId
        })
      });
      const payload = await response.json();
      if (!response.ok || payload.success === false) {
        throw new Error(payload.message || "تعذر إرسال الطلب.");
      }

      setCreatedOrder(payload.order);
      setStatus({ type: "success", message: payload.message || "تم تسجيل الطلب بنجاح." });

      if (window.fbq && payload.pixel) window.fbq("track", "Purchase", payload.pixel);
      if (window.ttq?.track && payload.pixel) window.ttq.track("Purchase", payload.pixel);
    } catch (error) {
      setStatus({ type: "error", message: error.message || "تعذر إرسال الطلب." });
    } finally {
      setSubmitting(false);
    }
  }

  if (variant === "compact") {
    return (
      <main className="buyNowPage" dir="rtl">
        <StoreHeader store={data.store} categories={[]} />
        <section className={`orderPage ${variant === "split" ? "splitVariant" : ""}`}>
          <div className="orderGallery">
            <div className="mainPhoto">{photo ? <img src={photo} alt={product.name} /> : null}</div>
            {data.photos?.length > 1 ? (
              <div className="thumbs">
                {data.photos.slice(0, 5).map((item) => (
                  <button type="button" key={item} onClick={() => setPhoto(item)} aria-label="عرض صورة المنتج">
                    <img src={item} alt="" />
                  </button>
                ))}
              </div>
            ) : null}
          </div>
          <div className="orderDetails">
            <div className="productIntro">
              <h1>{product.name}</h1>
              <p>{description.slice(0, 190)}</p>
              <div className="priceLine">
                <strong>{money(product.price)}</strong>
                {product.oldPrice > product.price ? <del>{money(product.oldPrice)}</del> : null}
              </div>
            </div>
            {product.announcement ? <div className="announcement">{product.announcement}</div> : null}

            <form className="orderForm buyNowForm" onSubmit={submitOrder}>
              <section>
                <h2>العرض والخيارات</h2>
                {data.offers?.length ? (
                  <div className="offerList">
                    {data.offers.map((offer) => (
                      <button type="button" key={offer.id} className={Number(offerId) === Number(offer.id) ? "selected" : ""} onClick={() => setOfferId(offer.id)}>
                        <span>{offer.label || `${offer.qty} قطع`}</span>
                        <strong>{money(offer.total)}</strong>
                        {offer.discount > 0 ? <small>خصم {offer.discount}%</small> : null}
                      </button>
                    ))}
                  </div>
                ) : (
                  <label className="field">
                    <span>الكمية</span>
                    <input type="number" min="1" value={form.quantity} onChange={(event) => updateField("quantity", event.target.value)} />
                  </label>
                )}

                <div className="choiceGrid">
                  {data.sizes?.length ? (
                    <label className="field">
                      <span>المقاس</span>
                      <select value={form.selected_size} onChange={(event) => updateField("selected_size", event.target.value)}>
                        {data.sizes.map((size) => <option key={size.id} value={size.id}>{size.name}</option>)}
                      </select>
                    </label>
                  ) : null}
                  {data.colors?.length ? (
                    <label className="field">
                      <span>اللون</span>
                      <select value={form.selected_color} onChange={(event) => updateField("selected_color", event.target.value)}>
                        {data.colors.map((color) => <option key={color.id} value={color.id}>{color.name}</option>)}
                      </select>
                    </label>
                  ) : null}
                </div>
              </section>

              <section>
                <h2>معلومات العميل</h2>
                <div className="choiceGrid">
                  <label className="field">
                    <span>الاسم الكامل</span>
                    <input required value={form.customer_name} onBlur={saveIncomplete} onChange={(event) => updateField("customer_name", event.target.value)} placeholder="اكتب اسمك هنا" />
                  </label>
                  <label className="field">
                    <span>رقم الهاتف</span>
                    <div className="phone-wrapper">
                      <span className="country-code">+213</span>
                      <input required inputMode="tel" maxLength={10} value={form.customer_phone} onBlur={() => { updateField("customer_phone", normalizePhone(form.customer_phone)); saveIncomplete(); checkExistingPhone(); }} onChange={(event) => updateField("customer_phone", event.target.value.replace(/[^\d]/g, "").slice(0, 10))} placeholder="06XXXXXXXX" />
                    </div>
                  </label>
                </div>
              </section>

              <section>
                <h2>التوصيل</h2>
                <div className="choiceGrid">
                  <label className="field">
                    <span>الولاية</span>
                    <select required value={form.wilaya} onChange={(event) => updateField("wilaya", event.target.value)}>
                      <option value="">اختر الولاية</option>
                      {wilayas.map((wilaya) => <option key={wilaya.id} value={wilaya.name}>{wilaya.code} - {wilaya.name}</option>)}
                    </select>
                  </label>
                  <label className="field">
                    <span>البلدية</span>
                    <select required value={form.commune} onBlur={saveIncomplete} onChange={(event) => updateField("commune", event.target.value)} disabled={!form.wilaya}>
                      <option value="">اختر البلدية</option>
                      {communeOptions.map((commune) => <option key={commune} value={commune}>{commune}</option>)}
                    </select>
                  </label>
                </div>

                {Object.entries(deliveryEntry).length ? (
                  <div className="deliveryOptions">
                    {Object.entries(deliveryEntry).filter(([, price]) => Number(price || 0) > 0).map(([type, price]) => (
                      <button type="button" key={type} className={deliveryType === type ? "selected" : ""} onClick={() => updateField("delivery_type", type)}>
                        <span>{type}</span>
                        <strong>{money(price)}</strong>
                      </button>
                    ))}
                  </div>
                ) : null}
              </section>

              <aside className="summaryBar">
                <div><span>المنتج</span><strong>{money(subtotal)}</strong></div>
                <div><span>التوصيل</span><strong>{money(shippingFee)}</strong></div>
                <div><span>الإجمالي</span><strong>{money(total)}</strong></div>
                <button type="submit" disabled={submitting || !form.wilaya || !form.commune || !deliveryType}>
                  <span>{money(total)}</span>
                  {submitting ? "جاري الإرسال..." : "اطلب الآن"}
                </button>
              </aside>

              {status.message ? <div className={`notice ${status.type}`}>{status.message}</div> : null}
              {createdOrder ? (
                <div className="successPanel">
                  <strong>تم حفظ الطلب #{createdOrder.id}</strong>
                  <p>سنتواصل معك لتأكيد الطلب قبل الشحن.</p>
                </div>
              ) : null}
            </form>
          </div>
        </section>
      </main>
    );
  }

  return (
    <main className={`landing-page ${variant === "split" ? "landing-page-2" : "landing-page-1"}`} dir="rtl" id="top">
      <div className="landing-shell">
        <div className="announcement-bar">
          <div className="announcement-track">
            <span>{product.announcement || `${product.name} - توصيل سريع والدفع عند الاستلام`}</span>
            <span>{product.announcement || `${product.name} - توصيل سريع والدفع عند الاستلام`}</span>
          </div>
        </div>

        {variant === "split" ? (
          <>
            <LandingHeader store={data.store} />
            <LandingCarousel product={product} photos={data.photos} currentPhoto={photo} setCurrentPhoto={setPhoto} />
          </>
        ) : (
          <ProductImages product={product} photos={data.photos} currentPhoto={photo} setCurrentPhoto={setPhoto} />
        )}

        <section className="landing-order">
          <div className="landing-container">
            {data.offers?.length ? (
              <div className="landing-offers">
                <div className="offer-title">عرض خاص لفترة محدودة</div>
                <div className="offer-grid">
                  {data.offers.map((offer) => (
                    <button
                      type="button"
                      key={offer.id}
                      className={`offer-card ${Number(offerId) === Number(offer.id) ? "selected" : ""} ${offer.popular ? "is-popular" : ""}`}
                      onClick={() => setOfferId(offer.id)}
                    >
                      {offer.popular ? <span className="offer-popular-badge">الأكثر طلبا</span> : null}
                      <span className="offer-select-dot" />
                      <span className="offer-content">
                        <span className="offer-details">
                          <strong>{offer.description || `${product.name} ${offer.qty}X`}</strong>
                          {offer.discount > 0 ? <small>تخفيض {offer.discount}%</small> : <small>تخفيض 0%</small>}
                        </span>
                        <span className="offer-price-stack">
                          {offer.baseTotal > offer.total ? <del>{money(offer.baseTotal)}</del> : null}
                          <b>{money(offer.total)}</b>
                        </span>
                      </span>
                    </button>
                  ))}
                </div>
              </div>
            ) : null}

            <div className="delivery-banner">
              <div>
                <strong>ملاحظة هامة حول التوصيل:</strong>
                <span>التوصيل متوفر حسب الولاية. اختر الولاية ليظهر سعر المنزل أو المكتب.</span>
              </div>
              <span className="delivery-banner-icon">🚚</span>
            </div>

            <div id="orderFormSection" className="landing-card">
              <h2>إتمام عملية الطلب 📦</h2>

              {status.message ? <div className={`notice ${status.type}`}>{status.message}</div> : null}
              {createdOrder ? (
                <div className="successPanel">
                  <strong>تم حفظ الطلب #{createdOrder.id}</strong>
                  <p>سنتواصل معك لتأكيد الطلب قبل الشحن.</p>
                </div>
              ) : null}

              <form className="modern-checkout-form" onSubmit={submitOrder}>
                <label className="form-field">
                  <span>الاسم الكامل *</span>
                  <input required value={form.customer_name} onBlur={saveIncomplete} onChange={(event) => updateField("customer_name", event.target.value)} placeholder="اكتب اسمك هنا..." />
                </label>

                <label className="form-field">
                  <span>رقم الهاتف *</span>
                  <div className="phone-wrapper">
                    <span className="country-code">+213</span>
                    <input required inputMode="tel" maxLength={10} value={form.customer_phone} onBlur={() => { updateField("customer_phone", normalizePhone(form.customer_phone)); saveIncomplete(); checkExistingPhone(); }} onChange={(event) => updateField("customer_phone", event.target.value.replace(/[^\d]/g, "").slice(0, 10))} placeholder="06XXXXXXXX" />
                  </div>
                </label>

                <div className="grid-2-cols">
                  <label className="form-field">
                    <span>الولاية *</span>
                    <select required value={form.wilaya} onChange={(event) => updateField("wilaya", event.target.value)}>
                      <option value="">اختر الولاية</option>
                      {wilayas.map((wilaya) => <option key={wilaya.id} value={wilaya.name}>{wilaya.code} - {wilaya.name}</option>)}
                    </select>
                  </label>
                  <label className="form-field">
                    <span>البلدية *</span>
                    <select required value={form.commune} onBlur={saveIncomplete} onChange={(event) => updateField("commune", event.target.value)} disabled={!form.wilaya}>
                      <option value="">اختر البلدية</option>
                      {communeOptions.map((commune) => <option key={commune} value={commune}>{commune}</option>)}
                    </select>
                  </label>
                </div>

                {data.colors?.length ? (
                  <label className="form-field">
                    <span>اللون</span>
                    <select value={form.selected_color} onChange={(event) => updateField("selected_color", event.target.value)}>
                      {data.colors.map((color) => <option key={color.id} value={color.id}>{color.name}</option>)}
                    </select>
                  </label>
                ) : null}

                {data.sizes?.length ? (
                  <label className="form-field">
                    <span>المقاس</span>
                    <select value={form.selected_size} onChange={(event) => updateField("selected_size", event.target.value)}>
                      {data.sizes.map((size) => <option key={size.id} value={size.id}>{size.name}</option>)}
                    </select>
                  </label>
                ) : null}

                <div className="form-field">
                  <span>نوع التوصيل *</span>
                  <div className="delivery-options">
                    {Object.entries(deliveryEntry).length ? (
                      Object.entries(deliveryEntry).filter(([, price]) => Number(price || 0) > 0).map(([type, price]) => (
                        <button type="button" key={type} className={`delivery-btn ${deliveryType === type ? "selected" : ""}`} onClick={() => updateField("delivery_type", type)}>
                          <span className="radio-icon" />
                          <strong>{deliveryLabel(type)}</strong>
                          <small>{deliveryHint(type)} - {money(price)}</small>
                        </button>
                      ))
                    ) : (
                      <p className="delivery-note">اختر الولاية لعرض خيارات التوصيل المتاحة.</p>
                    )}
                  </div>
                </div>

                {!data.offers?.length ? (
                  <label className="form-field">
                    <span>الكمية *</span>
                    <input type="number" min="1" value={form.quantity} onChange={(event) => updateField("quantity", event.target.value)} />
                  </label>
                ) : null}

                <div className={`delivery-info ${form.wilaya && deliveryType ? "" : "is-hidden"}`}>
                  <div>
                    <span>سعر التوصيل</span>
                    <strong>{money(shippingFee)}</strong>
                  </div>
                  <div className="total">
                    <span>المجموع الكلي</span>
                    <strong>{money(total)}</strong>
                  </div>
                </div>

                <button className="btn-buy-now" type="submit" disabled={submitting || !form.wilaya || !form.commune || !deliveryType}>
                  {submitting ? "جاري الإرسال..." : "🛒 تأكيد الطلب - اطلب الآن"}
                </button>
              </form>
            </div>
          </div>
        </section>

        {data.photos?.length ? (
          <section className="landing-photos" id="landingPhotos">
            <div className="landing-container">
              <div className="landing-photos-grid">
                {data.photos.slice(0, 6).map((item) => (
                  <button type="button" key={item} onClick={() => setPhoto(item)} className="landing-photo">
                    <img src={item} alt={product.name} />
                  </button>
                ))}
              </div>
            </div>
          </section>
        ) : null}

        <section className="trust-strip" id="stepsSection">
          <div className="landing-container trust-grid">
            <div>توصيل سريع 24-48 ساعة</div>
            <div>الدفع عند الاستلام</div>
            <div>إمكانية الاستبدال حسب الشروط</div>
            <div>فحص قبل الدفع</div>
          </div>
        </section>

        {description ? (
          <section className="landing-details">
            <div className="landing-container">
              <h2>تفاصيل إضافية</h2>
              <div className="details-card">{description}</div>
            </div>
          </section>
        ) : null}

        <section className="landing-faq">
          <div className="landing-container">
            <h2>أسئلة شائعة</h2>
            <details>
              <summary>هل الدفع عند الاستلام متاح؟</summary>
              <p>نعم، يمكنك الدفع عند استلام الطلب بعد التأكيد الهاتفي.</p>
            </details>
            <details>
              <summary>كم مدة التوصيل؟</summary>
              <p>عادة بين 24 و48 ساعة حسب الولاية والبلدية.</p>
            </details>
            <details>
              <summary>هل يمكن الاستبدال؟</summary>
              <p>نعم، يوجد إمكانية للاستبدال حسب شروط المتجر وحالة المنتج.</p>
            </details>
          </div>
        </section>
      </div>
    </main>
  );
}
