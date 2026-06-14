export const phpBase =
  process.env.NEXT_PUBLIC_PHP_BASE_URL?.replace(/\/$/, "") || "http://localhost/ecom";

async function readJson(url) {
  const response = await fetch(url, { cache: "no-store" });
  const text = await response.text();

  let payload;
  try {
    payload = JSON.parse(text);
  } catch (error) {
    throw new Error(`رد غير صالح من الخادم: ${text.slice(0, 120)}`);
  }

  if (!response.ok || payload.success === false) {
    throw new Error(payload.message || "تعذر تحميل البيانات.");
  }

  return payload;
}

export function money(value) {
  return `${Number(value || 0).toLocaleString("fr-DZ")} دج`;
}

export function productUrl(product) {
  return product?.url || `/buy-now?id=${encodeURIComponent(product?.id || "")}`;
}

export async function getCatalog(params = {}) {
  const query = new URLSearchParams(params);
  return readJson(`${phpBase}/api/next-catalog.php?${query.toString()}`);
}

export async function getProduct(id, template) {
  const query = new URLSearchParams({ id: id || "", template: template || "" });
  return readJson(`${phpBase}/api/next-product.php?${query.toString()}`);
}

export async function postJson(url, data) {
  const response = await fetch(`${phpBase}${url}`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify(data),
  });
  
  const text = await response.text();
  let payload;
  try {
    payload = JSON.parse(text);
  } catch (error) {
    throw new Error(`رد غير صالح من الخادم: ${text.slice(0, 120)}`);
  }

  if (!response.ok || payload.success === false) {
    throw new Error(payload.message || "تعذر إرسال البيانات.");
  }

  return payload;
}

export function normalizePhone(phone) {
  const cleaned = String(phone).replace(/\D/g, "");
  // If it starts with 213, remove it
  if (cleaned.startsWith("213") && cleaned.length > 9) {
    return "0" + cleaned.slice(3);
  }
  // Make sure it starts with 0
  if (cleaned.length === 9 && !cleaned.startsWith("0")) {
    return "0" + cleaned;
  }
  return cleaned;
}
