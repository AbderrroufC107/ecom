/* ─── Checkout Shared JS ─── */
(function() {
'use strict';

const BASE_DELAY = 1000;
let isSubmitting = false;
let lastClickTime = 0;
let checkTimeout = null;
let orderCompleted = false;

/* ── Phone Validation ── */
window.validateAlgerianPhoneNumberJS = function(phone) {
  var p = phone.replace(/[\s\-\(\)\+]/g, '');
  if (!/^[0-9]+$/.test(p)) return false;
  if (p.length === 10) return /^0[5-7][0-9]{8}$/.test(p);
  if (p.length === 9) return /^[5-7][0-9]{8}$/.test(p);
  return false;
};

/* ── Name Validation ── */
window.validateCustomerNameJS = function(name) {
  name = name.trim();
  if (!name) return { valid: false, message: '\u0627\u0644\u0627\u0633\u0645 \u0645\u0637\u0644\u0648\u0628' };
  if (name.length < 3) return { valid: false, message: '\u064a\u062c\u0628 \u0623\u0646 \u064a\u062d\u062a\u0648\u064a \u0627\u0644\u0627\u0633\u0645 \u0639\u0644\u0649 3 \u0623\u062d\u0631\u0641 \u0639\u0644\u0649 \u0627\u0644\u0623\u0642\u0644' };
  if (name.length > 50) return { valid: false, message: '\u064a\u062c\u0628 \u0623\u0646 \u064a\u0643\u0648\u0646 \u0627\u0644\u0627\u0633\u0645 \u0623\u0642\u0644 \u0645\u0646 50 \u062d\u0631\u0641' };
  if (!/^[\u0600-\u06FF\u0750-\u077Fa-zA-Z\s]+$/.test(name)) return { valid: false, message: '\u064a\u062c\u0628 \u0623\u0646 \u064a\u062d\u062a\u0648\u064a \u0627\u0644\u0627\u0633\u0645 \u0639\u0644\u0649 \u062d\u0631\u0648\u0641 \u0639\u0631\u0628\u064a\u0629 \u0623\u0648 \u0644\u0627\u062a\u064a\u0646\u064a\u0629 \u0641\u0642\u0637' };
  if (/[0-9@#\$%\^&\*\(\)\+=\[\]\{\}\|\\:";'<>,\?\/~`!]/.test(name)) return { valid: false, message: '\u0644\u0627 \u064a\u064f\u0633\u0645\u062d \u0628\u0627\u0644\u0623\u0631\u0642\u0627\u0645 \u0623\u0648 \u0627\u0644\u0631\u0645\u0648\u0632 \u0627\u0644\u062e\u0627\u0635\u0629 \u0641\u064a \u0627\u0644\u0627\u0633\u0645' };
  if (/(.)\1{2,}/.test(name)) return { valid: false, message: '\u0644\u0627 \u064a\u064f\u0633\u0645\u062d \u0628\u062a\u0643\u0631\u0627\u0631 \u0646\u0641\u0633 \u0627\u0644\u062d\u0631\u0641 \u0623\u0643\u062b\u0631 \u0645\u0646 \u0645\u0631\u062a\u064a\u0646 \u0645\u062a\u062a\u0627\u0644\u064a\u062a\u064a\u0646' };
  var bl = ['test','testing','tester','user','client','customer','name','unknown','anonymous','aaa','bbb','ccc','ddd','eee','fff','ggg','hhh','iii','jjj','kkk','lll','mmm','nnn','ooo','ppp','qqq','rrr','sss','ttt','uuu','vvv','www','xxx','yyy','zzz','abc','xyz','qwe','asd','zxc','123','456','789','000','111','222','333','444','555','666','777','888','999','admin','administrator','root','guest','visitor','dummy','fake','spam','bot','robot','auto','automatic','system','server','api'];
  var nl = name.toLowerCase();
  for (var i = 0; i < bl.length; i++) {
    if (nl.indexOf(bl[i]) !== -1) return { valid: false, message: '\u0627\u0644\u0627\u0633\u0645 \u064a\u062d\u062a\u0648\u064a \u0639\u0644\u0649 \u0643\u0644\u0645\u0627\u062a \u063a\u064a\u0631 \u0645\u0633\u0645\u0648\u062d\u0629' };
  }
  if (/\s{2,}/.test(name)) return { valid: false, message: '\u0644\u0627 \u064a\u064f\u0633\u0645\u062d \u0628\u0648\u062c\u0648\u062f \u0645\u0633\u0627\u0641\u0627\u062a \u0645\u062a\u0639\u062f\u062f\u0629 \u0641\u064a \u0627\u0644\u0627\u0633\u0645' };
  return { valid: true, message: '' };
};

/* ── AJAX Existing Order Check ── */
window.checkExistingOrderJS = function(phone) {
  return fetch('check-existing-order.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'phone=' + encodeURIComponent(phone)
  }).then(function(r) { return r.json(); }).catch(function() { return { exists: false }; });
};

/* ── Delivery Price Format ── */
window.formatDeliveryPrice = function(price) {
  var n = Number(price || 0);
  return Number.isInteger(n) ? n + ' \u062f\u062c' : n.toFixed(2) + ' \u062f\u062c';
};

window.phoneticArabicLocationName = function(name) {
  var value = String(name || '').trim();
  if (!value || /[\u0600-\u06FF]/.test(value)) return '';
  var words = value.toLowerCase()
    .normalize ? value.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '') : value.toLowerCase();
  var dictionary = {
    'ain': 'عين', 'el': 'ال', 'al': 'ال', 'ouled': 'أولاد', 'oulad': 'أولاد',
    'beni': 'بني', 'ben': 'بن', 'sidi': 'سيدي', 'bir': 'بئر', 'bordj': 'برج',
    'dar': 'دار', 'hassi': 'حاسي', 'ras': 'راس', 'oued': 'واد', 'wadi': 'وادي'
  };
  return words.split(/[\s'-]+/).filter(Boolean).map(function(part) {
    if (dictionary[part]) return dictionary[part];
    var out = part
      .replace(/ch/g, 'ش').replace(/kh/g, 'خ').replace(/gh/g, 'غ').replace(/dj/g, 'ج')
      .replace(/ou/g, 'و').replace(/oo/g, 'و').replace(/ai/g, 'اي').replace(/ei/g, 'ي')
      .replace(/a/g, 'ا').replace(/b/g, 'ب').replace(/c/g, 'ك').replace(/d/g, 'د')
      .replace(/e/g, 'ي').replace(/f/g, 'ف').replace(/g/g, 'ق').replace(/h/g, 'ه')
      .replace(/i/g, 'ي').replace(/j/g, 'ج').replace(/k/g, 'ك').replace(/l/g, 'ل')
      .replace(/m/g, 'م').replace(/n/g, 'ن').replace(/o/g, 'و').replace(/p/g, 'ب')
      .replace(/q/g, 'ق').replace(/r/g, 'ر').replace(/s/g, 'س').replace(/t/g, 'ت')
      .replace(/u/g, 'و').replace(/v/g, 'ف').replace(/w/g, 'و').replace(/x/g, 'كس')
      .replace(/y/g, 'ي').replace(/z/g, 'ز');
    return out.replace(/[^\u0600-\u06FF]+/g, '');
  }).filter(Boolean).join(' ');
};

window.getDeliveryLocationLabel = function(entry, fallback) {
  if (entry && typeof entry === 'object') {
    var label = String(entry.label || '').trim();
    if (label && label.indexOf(' - ') !== -1) return label;
    var name = String(entry.name || entry.wilaya_name || fallback || '').trim();
    var nameAr = String(entry.name_ar || entry.ar || '').trim();
    if (!nameAr && entry.wilaya_id) nameAr = window.phoneticArabicLocationName(name);
    if (name && nameAr && name !== nameAr) return name + ' - ' + nameAr;
    return label || name || String(fallback || '').trim();
  }
  return String(fallback || entry || '').trim();
};

window.getDeliveryCacheWilayas = function() {
  var list = (window.deliveryCacheData && Array.isArray(window.deliveryCacheData.wilayas)) ? window.deliveryCacheData.wilayas : [];
  return list.map(function(w, index) {
    var entry = (w && typeof w === 'object') ? w : { name: String(w || '') };
    var rawId = parseInt(entry.id || entry.wilaya_id || 0, 10);
    var id = rawId > 0 ? rawId : index + 1;
    return {
      id: String(id),
      code: String(id).padStart(2, '0'),
      name: String(entry.name || entry.wilaya_name || '').trim(),
      name_ar: String(entry.name_ar || '').trim(),
      label: window.getDeliveryLocationLabel(entry, String(entry.name || entry.wilaya_name || '').trim())
    };
  }).filter(function(w) { return w.name; });
};

window.populateWilayaSelectFromCache = function(select) {
  if (!select || !window.deliveryCacheData || !window.deliveryCacheData.communes) return;
  var wilayas = window.getDeliveryCacheWilayas();
  if (!wilayas.length) return;
  var previous = String(select.value || '').trim();
  select.innerHTML = '<option value="">\u0627\u062e\u062a\u0631 \u0627\u0644\u0648\u0644\u0627\u064a\u0629</option>';
  wilayas.sort(function(a, b) { return parseInt(a.id, 10) - parseInt(b.id, 10); }).forEach(function(w) {
    var opt = document.createElement('option');
    opt.value = w.name;
    opt.textContent = w.id + ' - ' + (w.label || w.name);
    select.appendChild(opt);
  });
  if (previous && Array.from(select.options).some(function(opt) { return opt.value === previous; })) {
    select.value = previous;
  }
};

window.getSelectedWilayaName = function(select) {
  if (!select) return '';
  var value = String(select.value || '').trim();
  if (window.deliveryCacheData && window.deliveryCacheData.communes && window.deliveryCacheData.communes[value]) {
    return value;
  }
  var selectedText = select.options && select.selectedIndex >= 0 ? String(select.options[select.selectedIndex].textContent || '').trim() : '';
  selectedText = selectedText.replace(/^\d+\s*-\s*/, '').trim();
  selectedText = selectedText.split(' - ')[0].trim();
  if (window.deliveryCacheData && window.deliveryCacheData.communes && window.deliveryCacheData.communes[selectedText]) {
    return selectedText;
  }
  return value;
};

window.populateCommuneSelectFromCache = function(wilayaSelect, communeSelect) {
  if (!wilayaSelect || !communeSelect || !window.deliveryCacheData || !window.deliveryCacheData.communes) return 0;
  var wilaya = window.getSelectedWilayaName(wilayaSelect);
  var communes = wilaya ? (window.deliveryCacheData.communes[wilaya] || []) : [];
  var previous = String(communeSelect.value || '').trim();
  communeSelect.innerHTML = '<option value="">\u0627\u062e\u062a\u0631 \u0627\u0644\u0628\u0644\u062f\u064a\u0629</option>';
  communes.forEach(function(c) {
    var name = String((c && c.name) || '').trim();
    if (!name) return;
    var opt = document.createElement('option');
    opt.value = name;
    opt.textContent = window.getDeliveryLocationLabel(c, name);
    communeSelect.appendChild(opt);
  });
  if (previous && Array.from(communeSelect.options).some(function(opt) { return opt.value === previous; })) {
    communeSelect.value = previous;
  }
  return communes.length;
};

window.isDeskDeliveryType = function(type, element) {
  if (element && element.getAttribute) {
    var kind = String(element.getAttribute('data-kind') || '').toLowerCase();
    if (kind === 'office' || kind === 'desk' || kind === 'stopdesk') return true;
    if (kind === 'home' || kind === 'free') return false;
  }
  var value = String(type || '').toLowerCase();
  return value.indexOf('\u0645\u0643\u062a\u0628') !== -1 ||
    value.indexOf('office') !== -1 ||
    value.indexOf('desk') !== -1 ||
    value.indexOf('stop') !== -1;
};

/* ── Get Available Delivery Options ── */
window.getAvailableDeliveryOptions = function(wilaya) {
  if (!window.productDeliveryMode || !window.shippingFees) return {};
  if (window.productDeliveryMode === 'free') {
    var first = document.querySelector('.delivery-btn');
    return first ? (function(t){ return t ? { [t]: 0 } : {}; })((first.getAttribute('data-type') || '').trim()) : {};
  }
  var wSel = document.getElementById('wilaya');
  var cSel = document.getElementById('commune');
  var cacheWilaya = window.getSelectedWilayaName(wSel);
  var commune = cSel ? String(cSel.value || '').trim() : '';
  if (cacheWilaya && window.deliveryCacheData && window.deliveryCacheData.communes && window.deliveryCacheData.communes[cacheWilaya]) {
    var cData = null;
    if (commune) {
      cData = window.deliveryCacheData.communes[cacheWilaya].find(function(c) { return c.name === commune; });
    }
    if (!cData) cData = window.deliveryCacheData.communes[cacheWilaya][0];
    var cacheOptions = {};
    document.querySelectorAll('.delivery-btn').forEach(function(btn) {
      var type = (btn.getAttribute('data-type') || '').trim();
      var isDesk = window.isDeskDeliveryType(type, btn);
      if (!cData) return;
      if (isDesk && Number(cData.desk || 0) === 1 && Number(cData.desk_price || 0) > 0) {
        cacheOptions[type] = Number(cData.desk_price || 0);
      } else if (!isDesk && Number(cData.home || 0) === 1 && Number(cData.home_price || 0) > 0) {
        cacheOptions[type] = Number(cData.home_price || 0);
      }
    });
    return cacheOptions;
  }
  if (!wilaya || !window.shippingFees[wilaya]) return {};
  var raw = window.shippingFees[wilaya];
  if (raw && typeof raw === 'object' && !Array.isArray(raw)) {
    var avail = {};
    Object.keys(raw).forEach(function(t) {
      var p = Number(raw[t] || 0);
      if (p > 0) avail[t] = p;
    });
    return avail;
  }
  var fb = document.querySelector('.delivery-btn');
  if (!fb) return {};
  var ft = (fb.getAttribute('data-type') || '').trim();
  var fp = Number(raw || 0);
  return (ft && fp > 0) ? { [ft]: fp } : {};
};

/* ── Update Delivery Prices ── */
window.updateDeliveryPrices = function() {
  var wSel = document.getElementById('wilaya');
  var cSel = document.getElementById('commune');
  var wilaya = wSel ? wSel.value.trim() : '';
  var commune = cSel ? cSel.value.trim() : '';
  var buttons = Array.from(document.querySelectorAll('.delivery-btn'));

  buttons.forEach(function(btn) {
    var type = (btn.getAttribute('data-type') || '').trim();
    var isDesk = window.isDeskDeliveryType(type, btn);
    var price = 0;
    var cData = null;

    var cacheWilaya = window.getSelectedWilayaName(wSel);
    if (cacheWilaya && window.deliveryCacheData && window.deliveryCacheData.communes && window.deliveryCacheData.communes[cacheWilaya]) {
      if (commune) cData = window.deliveryCacheData.communes[cacheWilaya].find(function(c) { return c.name === commune; });
      if (!cData) cData = window.deliveryCacheData.communes[cacheWilaya][0];
      if (cData) price = isDesk ? (cData.desk_price || 0) : (cData.home_price || 0);
    } else if (wilaya && window.shippingFees && window.shippingFees[wilaya]) {
      var raw = window.shippingFees[wilaya];
      if (raw && typeof raw === 'object' && !Array.isArray(raw)) {
        price = Number(raw[type] || 0);
      } else {
        price = Number(raw || 0);
      }
    }

    var tag = btn.querySelector('.delivery-price-tag, .delivery-price');
    if (tag) tag.textContent = window.formatDeliveryPrice(price);
  });
};

/* ── Update Delivery Options State ── */
window.updateDeliveryOptionsState = function() {
  var buttons = Array.from(document.querySelectorAll('.delivery-btn'));
  var input = document.getElementById('deliveryTypeInput');
  var note = document.getElementById('deliveryOptionsNote');
  var wSel = document.getElementById('wilaya');
  var wilaya = wSel ? wSel.value.trim() : '';
  var avail = window.getAvailableDeliveryOptions(wilaya);
  var types = Object.keys(avail);

  if (!wilaya && window.productDeliveryMode !== 'free') {
    buttons.forEach(function(b) { b.classList.remove('is-hidden'); b.disabled = false; b.style.opacity = '1'; b.style.cursor = 'pointer'; });
    if (!buttons.some(function(b) { return b.classList.contains('selected'); }) && buttons.length) buttons[0].classList.add('selected');
    if (input && buttons[0]) input.value = (document.querySelector('.delivery-btn.selected') || buttons[0]).getAttribute('data-type') || '';
    if (note) { note.style.display = 'none'; }
    if (typeof updateDeliveryPrices === 'function') updateDeliveryPrices();
    if (typeof updateTotalPrice === 'function') updateTotalPrice();
    return;
  }

  var hasAvail = window.productDeliveryMode === 'free' || (!!wilaya && types.length > 0);

  buttons.forEach(function(b) {
    var type = (b.getAttribute('data-type') || '').trim();
    var isAvail = window.productDeliveryMode === 'free' ? true : (!!wilaya && types.indexOf(type) !== -1);
    b.classList.remove('is-hidden');
    b.disabled = !isAvail;
    b.style.opacity = isAvail ? '1' : '0.55';
    b.style.cursor = isAvail ? 'pointer' : 'not-allowed';
    if (!isAvail) { b.classList.remove('selected'); b.classList.add('disabled'); }
    else { b.classList.remove('disabled'); }
  });

  var vis = buttons.filter(function(b) { return !b.disabled; });
  if (!vis.length) {
    if (input) input.value = '';
    if (note) { note.style.display = 'block'; note.textContent = '\u0644\u0627 \u064a\u0648\u062c\u062f \u062a\u0648\u0635\u064a\u0644 \u0645\u062a\u0627\u062d \u0644\u0647\u0630\u0647 \u0627\u0644\u0648\u0644\u0627\u064a\u0629'; note.classList.add('is-error'); }
    if (typeof updateDeliveryPrices === 'function') updateDeliveryPrices();
    if (typeof updateTotalPrice === 'function') updateTotalPrice();
    return;
  }

  var sel = vis.find(function(b) { return b.classList.contains('selected'); });
  if (!sel) { buttons.forEach(function(b) { b.classList.remove('selected'); }); sel = vis[0]; sel.classList.add('selected'); }
  if (input) input.value = sel.getAttribute('data-type') || '';
  if (note) { note.style.display = 'none'; }
  if (typeof updateDeliveryPrices === 'function') updateDeliveryPrices();
  if (typeof updateTotalPrice === 'function') updateTotalPrice();
};

/* ── Update Total Price ── */
window.updateTotalPrice = function() {
  var qty = parseInt(document.getElementById('quantity') ? document.getElementById('quantity').value : 1) || 1;
  var price = parseFloat(window.basePrice || 0) * qty;
  var delivery = 0;
  var selected = document.querySelector('.delivery-btn.selected');
  if (selected) {
    var type = selected.getAttribute('data-type') || '';
    var wSel = document.getElementById('wilaya');
    var cSel = document.getElementById('commune');
    var cacheWilaya = window.getSelectedWilayaName(wSel);
    var commune = cSel ? String(cSel.value || '').trim() : '';
    var cData = null;
    if (cacheWilaya && window.deliveryCacheData && window.deliveryCacheData.communes && window.deliveryCacheData.communes[cacheWilaya]) {
      if (commune) cData = window.deliveryCacheData.communes[cacheWilaya].find(function(c) { return c.name === commune; });
      if (!cData) cData = window.deliveryCacheData.communes[cacheWilaya][0];
      if (cData) {
        var isDesk = window.isDeskDeliveryType(type, selected);
        delivery = Number(isDesk ? (cData.desk_price || 0) : (cData.home_price || 0));
      }
    } else if (window.shippingFees) {
      var wilaya = wSel ? wSel.value.trim() : '';
      if (wilaya && window.shippingFees[wilaya]) {
          var raw = window.shippingFees[wilaya];
          if (raw && typeof raw === 'object' && !Array.isArray(raw)) {
            delivery = Number(raw[type] || 0);
          } else {
            delivery = Number(raw || 0);
          }
      }
    }
  }
  var total = price + delivery;
  var hidden = document.getElementById('total_price_hidden');
  if (hidden) hidden.value = total.toFixed(2);
  var display = document.getElementById('total_price');
  if (display) display.textContent = total.toFixed(2) + ' \u062f\u062c';
  var submitTotal = document.getElementById('submitTotalPrice');
  if (submitTotal) submitTotal.textContent = total.toFixed(2) + ' \u062f\u062c';
};

/* ── Initialize Checkout ── */
window.initCheckoutForm = function() {
  /* ── Thumbnail Gallery ── */
  document.querySelectorAll('.thumb-img').forEach(function(img) {
    img.addEventListener('click', function() {
      var main = document.getElementById('main-product-image');
      if (main) main.src = typeof resolveFrontImageUrl === 'function' ? resolveFrontImageUrl(this.getAttribute('data-photo')) : this.getAttribute('data-photo');
    });
  });

  /* ── Color Buttons ── */
  document.querySelectorAll('.color-btn, .color-option').forEach(function(btn) {
    btn.addEventListener('click', function() {
      document.querySelectorAll('.color-btn, .color-option').forEach(function(b) { b.classList.remove('selected'); });
      this.classList.add('selected');
      var inp = document.getElementById('selected_color') || document.getElementById('selectedColorInput');
      if (inp) inp.value = this.getAttribute('data-value') || '';
      var err = document.getElementById('colorError');
      if (err) err.classList.remove('is-visible');
    });
  });

  /* ── Size Buttons ── */
  document.querySelectorAll('.size-btn, .size-option').forEach(function(btn) {
    btn.addEventListener('click', function() {
      document.querySelectorAll('.size-btn, .size-option').forEach(function(b) { b.classList.remove('selected'); });
      this.classList.add('selected');
      var inp = document.getElementById('selected_size') || document.getElementById('selectedSizeInput');
      if (inp) inp.value = this.getAttribute('data-value') || '';
    });
  });

  /* ── Delivery Buttons ── */
  document.querySelectorAll('.delivery-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      if (this.disabled) return;
      document.querySelectorAll('.delivery-btn').forEach(function(b) { b.classList.remove('selected'); });
      this.classList.add('selected');
      var inp = document.getElementById('deliveryTypeInput');
      if (inp) inp.value = this.getAttribute('data-type') || '';
      if (typeof updateTotalPrice === 'function') updateTotalPrice();
      if (typeof updateDeliveryPrices === 'function') updateDeliveryPrices();
      if (typeof refreshDeskOptions === 'function') refreshDeskOptions();
    });
  });

  /* ── Wilaya Select ── */
  var wSel = document.getElementById('wilaya');
  if (wSel) {
    window.populateWilayaSelectFromCache(wSel);
    var initialCommuneSelect = document.getElementById('commune');
    if (wSel.value && initialCommuneSelect) {
      window.populateCommuneSelectFromCache(wSel, initialCommuneSelect);
    }
    wSel.addEventListener('change', function() {
      var cSel = document.getElementById('commune');
      if (cSel) {
        window.populateCommuneSelectFromCache(this, cSel);
      }
      if (typeof updateDeliveryOptionsState === 'function') updateDeliveryOptionsState();
      if (typeof updateDeliveryPrices === 'function') updateDeliveryPrices();
      if (typeof updateTotalPrice === 'function') updateTotalPrice();
      if (typeof refreshDeskOptions === 'function') refreshDeskOptions();
    });
    wSel.addEventListener('input', function() {
      var cSel = document.getElementById('commune');
      if (cSel) window.populateCommuneSelectFromCache(this, cSel);
    });
  }

  /* ── Commune Select ── */
  var cSel = document.getElementById('commune');
  if (cSel) {
    cSel.addEventListener('focus', function() {
      var w = document.getElementById('wilaya');
      if (w && cSel.options.length <= 1) window.populateCommuneSelectFromCache(w, cSel);
    });
    cSel.addEventListener('click', function() {
      var w = document.getElementById('wilaya');
      if (w && cSel.options.length <= 1) window.populateCommuneSelectFromCache(w, cSel);
    });
    cSel.addEventListener('change', function() {
      if (typeof updateDeliveryOptionsState === 'function') updateDeliveryOptionsState();
      if (typeof updateDeliveryPrices === 'function') updateDeliveryPrices();
      if (typeof updateTotalPrice === 'function') updateTotalPrice();
      if (typeof refreshDeskOptions === 'function') refreshDeskOptions();
    });
  }

  /* ── Quantity Controls ── */
  var incBtn = document.getElementById('increaseQuantity');
  var decBtn = document.getElementById('decreaseQuantity');
  if (incBtn) {
    incBtn.addEventListener('click', function() {
      var q = document.getElementById('quantity');
      if (q) { q.value = (parseInt(q.value) || 1) + 1; if (typeof updateTotalPrice === 'function') updateTotalPrice(); }
    });
  }
  if (decBtn) {
    decBtn.addEventListener('click', function() {
      var q = document.getElementById('quantity');
      if (q && (parseInt(q.value) || 1) > 1) { q.value = (parseInt(q.value) || 1) - 1; if (typeof updateTotalPrice === 'function') updateTotalPrice(); }
    });
  }

  /* ── Name Validation ── */
  var nameInput = document.getElementById('customer_name');
  if (nameInput) {
    var nameError = document.getElementById('name-error');
    if (!nameError) {
      nameError = document.createElement('div');
      nameError.id = 'name-error';
      nameError.className = 'invalid-feedback';
      nameInput.parentNode.appendChild(nameError);
    }
    nameInput.addEventListener('input', function() {
      var v = window.validateCustomerNameJS(this.value);
      if (!this.value.trim()) { nameError.classList.remove('is-visible'); this.classList.remove('is-invalid'); return; }
      if (v.valid) { nameError.classList.remove('is-visible'); this.classList.remove('is-invalid'); }
      else { nameError.textContent = v.message; nameError.classList.add('is-visible'); this.classList.add('is-invalid'); }
    });
  }

  /* ── Phone Validation ── */
  var phoneInput = document.getElementById('customer_phone');
  var phoneError = document.getElementById('phone-error');
  if (phoneInput && phoneError) {
    phoneInput.addEventListener('input', function() {
      var phone = this.value.trim();
      if (checkTimeout) clearTimeout(checkTimeout);
      if (!phone) { phoneError.classList.remove('is-visible'); this.classList.remove('is-invalid'); return; }
      if (!window.validateAlgerianPhoneNumberJS(phone)) {
        phoneError.textContent = '\u0631\u0642\u0645 \u0627\u0644\u0647\u0627\u062a\u0641 \u063a\u064a\u0631 \u0635\u062d\u064a\u062d. \u064a\u0631\u062c\u0649 \u0625\u062f\u062e\u0627\u0644 \u0631\u0642\u0645 \u0647\u0627\u062a\u0641 \u062c\u0632\u0627\u0626\u0631\u064a \u0635\u062d\u064a\u062d';
        phoneError.classList.add('is-visible');
        this.classList.add('is-invalid');
        return;
      }
      phoneError.classList.remove('is-visible');
      this.classList.remove('is-invalid');
      checkTimeout = setTimeout(function() {
        window.checkExistingOrderJS(phone).then(function(result) {
          if (result.exists) {
            phoneError.textContent = result.message;
            phoneError.classList.add('is-visible');
            phoneInput.classList.add('is-invalid');
          }
        });
      }, 500);
    });
  }

  /* ── Form Submit ── */
  var form = document.getElementById('orderForm');
  if (form) {
    form.addEventListener('submit', function(e) {
      var now = Date.now();
      if (now - lastClickTime < BASE_DELAY) { e.preventDefault(); return false; }
      if (isSubmitting) { e.preventDefault(); return false; }

      var colorSection = document.getElementById('colorSelection');
      if (colorSection) {
        var colorVal = (document.getElementById('selected_color') || document.getElementById('selectedColorInput') || {}).value || '';
        if (!colorVal.trim()) {
          e.preventDefault();
          var cErr = document.getElementById('colorError');
          if (cErr) cErr.classList.add('is-visible');
          colorSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
          return false;
        }
      }

      lastClickTime = now;
      isSubmitting = true;
      var btn = this.querySelector('button[type="submit"]');
      if (btn) {
        btn.disabled = true;
        btn.classList.add('loading');
      }
    });
  }

  /* ── Init delivery state ── */
  if (typeof updateDeliveryOptionsState === 'function') updateDeliveryOptionsState();
  if (typeof updateTotalPrice === 'function') updateTotalPrice();
};

/* ── Auto-run unless page opts out ── */
if (!window.__skipCheckoutInit) {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', window.initCheckoutForm);
  } else {
    window.initCheckoutForm();
  }
}

})();
