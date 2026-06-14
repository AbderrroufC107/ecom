import { useState, useRef, useEffect } from "react";
import { phpBase, postJson, normalizePhone } from "../services/api";

function OrderSummary({ order, children }) {
  return (
    <div className="serviceOrderSummary">
      <div>
        <strong>طلب #{order.publicId || order.id}</strong>
        <span>{order.statusText || order.status}</span>
        <span>{order.date}</span>
      </div>
      {children}
    </div>
  );
}

export default function CustomerServiceModal({ isOpen, onClose, initialTab = "track" }) {
  const modalRef = useRef(null);
  const [activeTab, setActiveTab] = useState(initialTab);
  const [trackPhone, setTrackPhone] = useState("");
  const [trackOrders, setTrackOrders] = useState([]);
  const [trackMessage, setTrackMessage] = useState("");
  const [trackLoading, setTrackLoading] = useState(false);

  const [exchangePhone, setExchangePhone] = useState("");
  const [exchangeOrders, setExchangeOrders] = useState([]);
  const [selectedOrderId, setSelectedOrderId] = useState("");
  const [exchangeReason, setExchangeReason] = useState("");
  const [exchangeImage, setExchangeImage] = useState(null);
  const [exchangeMessage, setExchangeMessage] = useState("");
  const [exchangeLoading, setExchangeLoading] = useState(false);

  useEffect(() => {
    if (isOpen) {
      setActiveTab(initialTab);
    }
  }, [isOpen, initialTab]);

  useEffect(() => {
    if (!isOpen) return;
    function handleKeyDown(event) {
      if (event.key === "Escape") onClose();
    }
    document.addEventListener("keydown", handleKeyDown);
    return () => document.removeEventListener("keydown", handleKeyDown);
  }, [isOpen, onClose]);

  if (!isOpen) return null;

  async function handleTrackSubmit(event) {
    event.preventDefault();
    setTrackLoading(true);
    setTrackMessage("");
    setTrackOrders([]);
    try {
      const data = await postJson("/api/order-lookup.php", { phone: normalizePhone(trackPhone) });
      setTrackOrders(data.orders || []);
      setTrackMessage((data.orders || []).length ? "" : "لا توجد طلبات مرتبطة بهذا الرقم.");
    } catch (error) {
      setTrackMessage(error.message);
    } finally {
      setTrackLoading(false);
    }
  }

  async function handleExchangeLookup(event) {
    event.preventDefault();
    setExchangeLoading(true);
    setExchangeMessage("");
    setExchangeOrders([]);
    setSelectedOrderId("");
    try {
      const data = await postJson("/api/exchange-request.php", {
        action: "lookup",
        phone: normalizePhone(exchangePhone),
      });
      setExchangeOrders(data.orders || []);
      setExchangeMessage((data.orders || []).length ? "" : "لا توجد طلبات مرتبطة بهذا الرقم.");
    } catch (error) {
      setExchangeMessage(error.message);
    } finally {
      setExchangeLoading(false);
    }
  }

  async function handleExchangeSubmit(event) {
    event.preventDefault();
    if (!selectedOrderId) {
      setExchangeMessage("اختر طلبا مؤهلا للتبديل أولا.");
      return;
    }
    if (!exchangeImage) {
      setExchangeMessage("ارفع صورة توضح سبب التبديل.");
      return;
    }

    setExchangeLoading(true);
    setExchangeMessage("");
    try {
      const form = new FormData();
      form.append("action", "submit");
      form.append("phone", normalizePhone(exchangePhone));
      form.append("order_id", selectedOrderId);
      form.append("reason", exchangeReason);
      form.append("proof_image", exchangeImage);
      const response = await fetch(`${phpBase}/api/exchange-request.php`, {
        method: "POST",
        body: form,
      });
      const data = await response.json().catch(() => ({}));
      if (!response.ok || data.success === false) {
        throw new Error(data.message || "تعذر إرسال طلب التبديل.");
      }
      setExchangeMessage(data.message || "تم إرسال طلب التبديل.");
      setExchangeReason("");
      setExchangeImage(null);
      setSelectedOrderId("");
      setExchangeOrders((orders) =>
        orders.map((order) =>
          String(order.id) === String(selectedOrderId)
            ? { ...order, exchangeEligible: false, exchangeReason: "يوجد طلب تبديل مفتوح لهذا الطلب." }
            : order
        )
      );
    } catch (error) {
      setExchangeMessage(error.message);
    } finally {
      setExchangeLoading(false);
    }
  }

  const selectedOrder = exchangeOrders.find((order) => String(order.id) === String(selectedOrderId));

  return (
    <div style={{
      position: "fixed", top: 0, left: 0, width: "100%", height: "100%",
      backgroundColor: "rgba(0,0,0,0.5)", zIndex: 9999, display: "flex",
      alignItems: "center", justifyContent: "center", backdropFilter: "blur(4px)"
    }}>
      <div style={{
        backgroundColor: "#fff", width: "100%", maxWidth: "500px", borderRadius: "24px",
        padding: "24px", maxHeight: "90vh", overflowY: "auto", position: "relative",
        boxShadow: "0 20px 40px rgba(0,0,0,0.2)"
      }} ref={modalRef} dir="rtl">
        <button onClick={onClose} style={{
          position: "absolute", top: "16px", left: "16px", background: "transparent",
          border: "none", fontSize: "24px", cursor: "pointer", color: "#64748b"
        }}>&times;</button>

        <div className="serviceMenuHead" style={{ marginBottom: "20px" }}>
          <span style={{ color: "var(--teal)", fontWeight: "bold" }}>خدمة الطلبات</span>
          <h2 style={{ margin: "8px 0 0", fontSize: "20px" }}>تتبع الطلب وطلب التبديل</h2>
        </div>
        <div className="serviceTabs" role="tablist" aria-label="خدمة الطلبات" style={{ display: "flex", gap: "8px", marginBottom: "20px" }}>
          <button type="button" style={{ flex: 1, padding: "10px", borderRadius: "8px", border: "1px solid #e2e8f0", background: activeTab === "track" ? "var(--teal)" : "#fff", color: activeTab === "track" ? "#fff" : "#0f172a", cursor: "pointer", fontWeight: "bold" }} onClick={() => setActiveTab("track")}>
            تتبع الطلب برقم الهاتف
          </button>
          <button type="button" style={{ flex: 1, padding: "10px", borderRadius: "8px", border: "1px solid #e2e8f0", background: activeTab === "exchange" ? "var(--teal)" : "#fff", color: activeTab === "exchange" ? "#fff" : "#0f172a", cursor: "pointer", fontWeight: "bold" }} onClick={() => setActiveTab("exchange")}>
            طلب تبديل / Echange
          </button>
        </div>

        {activeTab === "track" ? (
          <div className="serviceGrid">
            <form className="serviceForm" onSubmit={handleTrackSubmit}>
              <label htmlFor="trackPhone">رقم الهاتف</label>
              <div className="servicePhone" style={{ display: "flex", gap: "10px", marginBottom: "16px" }}>
                <span style={{ padding: "12px", background: "#f8fafc", border: "1px solid #e2e8f0", borderRadius: "8px" }}>+213</span>
                <input
                  id="trackPhone" type="tel" inputMode="numeric" dir="ltr" placeholder="06xxxxxxxx"
                  value={trackPhone} onChange={(event) => setTrackPhone(event.target.value)} required
                  style={{ flex: 1, padding: "12px", border: "1px solid #e2e8f0", borderRadius: "8px" }}
                />
              </div>
              <button type="submit" disabled={trackLoading} style={{ width: "100%", padding: "14px", background: "var(--teal)", color: "#fff", border: "none", borderRadius: "8px", fontWeight: "bold", cursor: "pointer" }}>
                {trackLoading ? "جاري البحث..." : "تتبع الطلب"}
              </button>
              {trackMessage ? <p className="serviceNotice" style={{ color: "var(--red)", marginTop: "12px", fontSize: "14px" }}>{trackMessage}</p> : null}
            </form>
            {trackOrders.length ? (
              <div className="serviceResults" style={{ marginTop: "24px" }}>
                {trackOrders.map((order) => (
                  <OrderSummary key={order.id} order={order} />
                ))}
              </div>
            ) : null}
          </div>
        ) : (
          <div className="serviceGrid">
            <form className="serviceForm" onSubmit={handleExchangeLookup}>
              <label htmlFor="exchangePhone">أدخل رقم الهاتف أولا</label>
              <div className="servicePhone" style={{ display: "flex", gap: "10px", marginBottom: "16px" }}>
                <span style={{ padding: "12px", background: "#f8fafc", border: "1px solid #e2e8f0", borderRadius: "8px" }}>+213</span>
                <input
                  id="exchangePhone" type="tel" inputMode="numeric" dir="ltr" placeholder="06xxxxxxxx"
                  value={exchangePhone} onChange={(event) => setExchangePhone(event.target.value)} required
                  style={{ flex: 1, padding: "12px", border: "1px solid #e2e8f0", borderRadius: "8px" }}
                />
              </div>
              <button type="submit" disabled={exchangeLoading} style={{ width: "100%", padding: "14px", background: "var(--teal)", color: "#fff", border: "none", borderRadius: "8px", fontWeight: "bold", cursor: "pointer" }}>
                {exchangeLoading ? "جاري الفحص..." : "فحص الطلبات المؤهلة"}
              </button>
              {exchangeMessage ? <p className="serviceNotice" style={{ color: "var(--red)", marginTop: "12px", fontSize: "14px" }}>{exchangeMessage}</p> : null}
            </form>
            {exchangeOrders.length || selectedOrder ? (
              <div className="serviceResults" style={{ marginTop: "24px" }}>
                {exchangeOrders.map((order) => (
                  <OrderSummary key={order.id} order={order}>
                    <button
                      type="button"
                      disabled={!order.exchangeEligible}
                      onClick={() => setSelectedOrderId(String(order.id))}
                      style={{ marginTop: "8px", padding: "8px 16px", borderRadius: "6px", border: "none", cursor: order.exchangeEligible ? "pointer" : "not-allowed", background: order.exchangeEligible ? (String(order.id) === String(selectedOrderId) ? "var(--teal)" : "#e2e8f0") : "#f1f5f9", color: order.exchangeEligible ? (String(order.id) === String(selectedOrderId) ? "#fff" : "#0f172a") : "#94a3b8", fontWeight: "bold" }}
                    >
                      {order.exchangeEligible ? (String(order.id) === String(selectedOrderId) ? "تم الاختيار" : "اختيار للتبديل") : order.exchangeReason}
                    </button>
                  </OrderSummary>
                ))}
                {selectedOrder ? (
                  <form className="exchangeDetailsForm" onSubmit={handleExchangeSubmit} style={{ marginTop: "24px", paddingTop: "24px", borderTop: "1px solid #e2e8f0" }}>
                    <h3 style={{ margin: "0 0 16px" }}>طلب تبديل #{selectedOrder.publicId || selectedOrder.id}</h3>
                    <label htmlFor="exchangeReason">سبب التبديل</label>
                    <textarea
                      id="exchangeReason" rows={4} value={exchangeReason} onChange={(event) => setExchangeReason(event.target.value)}
                      placeholder="اشرح سبب التبديل..." required
                      style={{ width: "100%", padding: "12px", border: "1px solid #e2e8f0", borderRadius: "8px", marginBottom: "16px" }}
                    />
                    <label htmlFor="exchangeImage">صورة توضح السبب</label>
                    <input
                      id="exchangeImage" type="file" accept="image/png,image/jpeg,image/webp,image/gif"
                      onChange={(event) => setExchangeImage(event.target.files?.[0] || null)} required
                      style={{ width: "100%", padding: "12px", border: "1px solid #e2e8f0", borderRadius: "8px", marginBottom: "16px" }}
                    />
                    <button type="submit" disabled={exchangeLoading} style={{ width: "100%", padding: "14px", background: "var(--teal)", color: "#fff", border: "none", borderRadius: "8px", fontWeight: "bold", cursor: "pointer" }}>
                      {exchangeLoading ? "جاري الإرسال..." : "إرسال طلب التبديل"}
                    </button>
                  </form>
                ) : null}
              </div>
            ) : null}
          </div>
        )}
      </div>
    </div>
  );
}
