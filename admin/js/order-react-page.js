(function () {
  "use strict";

  var root = document.getElementById("orders-react-root");
  if (!root || !window.React || !window.ReactDOM) {
    return;
  }

  var React = window.React;
  var h = React.createElement;
  var useEffect = React.useEffect;
  var useMemo = React.useMemo;
  var useState = React.useState;
  var $ = window.jQuery;

  var labels = {
    title: "\u0625\u062f\u0627\u0631\u0629 \u0627\u0644\u0637\u0644\u0628\u0627\u062a",
    subtitle: "\u0645\u0631\u0643\u0632 \u0639\u0645\u0644 \u0648\u0627\u062d\u062f \u0644\u0644\u0645\u062a\u0627\u0628\u0639\u0629\u060c \u0627\u0644\u062a\u0623\u0643\u064a\u062f\u060c \u0627\u0644\u062a\u0648\u0635\u064a\u0644\u060c \u0648\u0627\u0644\u0625\u062c\u0631\u0627\u0621\u0627\u062a \u0627\u0644\u062c\u0645\u0627\u0639\u064a\u0629.",
    search: "\u0627\u0628\u062d\u062b \u0641\u064a \u0627\u0644\u062a\u0628\u0648\u064a\u0628 \u0627\u0644\u062d\u0627\u0644\u064a...",
    selected: "\u0645\u062d\u062f\u062f",
    total: "\u0625\u062c\u0645\u0627\u0644\u064a \u0627\u0644\u0637\u0644\u0628\u0627\u062a",
    today: "\u0637\u0644\u0628\u0627\u062a \u0627\u0644\u064a\u0648\u0645",
    pending: "\u0628\u0627\u0646\u062a\u0638\u0627\u0631 \u0627\u0644\u062a\u0623\u0643\u064a\u062f",
    completedAmount: "\u0642\u064a\u0645\u0629 \u0645\u0624\u0643\u062f\u0629",
    noCalls: "\u0628\u062f\u0648\u0646 \u0627\u062a\u0635\u0627\u0644",
    ready: "\u062c\u0627\u0647\u0632\u0629 \u0644\u0644\u062a\u0623\u0643\u064a\u062f",
    followup: "\u062a\u062d\u062a\u0627\u062c \u0645\u062a\u0627\u0628\u0639\u0629",
    stats: "\u0627\u0644\u0625\u062d\u0635\u0627\u0626\u064a\u0627\u062a",
    incomplete: "\u063a\u064a\u0631 \u0645\u0643\u062a\u0645\u0644\u0629",
    shipping: "\u0627\u0644\u062a\u0648\u0635\u064a\u0644"
  };

  function injectStyles() {
    if (document.getElementById("orders-react-page-css")) {
      return;
    }
    var style = document.createElement("style");
    style.id = "orders-react-page-css";
    style.textContent = [
      "body.orders-react-ready .content-header,body.orders-react-ready .orders-page>.hero,body.orders-react-ready .orders-page>.stats,body.orders-react-ready .orders-page>.pending-cues,body.orders-react-ready #orderStatusTabs{display:none!important}",
      "body.orders-react-ready .orders-page{padding:16px 24px 34px;background:#f4f7fb;color:#111827;font-size:14px;line-height:1.65;-webkit-font-smoothing:antialiased;text-rendering:optimizeLegibility}",
      "body.orders-react-ready .orders-page .flash{margin:0 0 12px;border-radius:8px}",
      ".orders-react-workspace{direction:rtl;font-family:inherit;margin-bottom:14px;color:#132033}",
      ".orders-react-console{border:1px solid #d5e0ec;background:#fff;border-radius:8px;box-shadow:0 18px 44px rgba(15,23,42,.07);overflow:hidden}",
      ".orders-react-head{display:grid;grid-template-columns:minmax(260px,1fr) auto;gap:18px;align-items:center;padding:18px 18px 14px;background:linear-gradient(180deg,#ffffff 0%,#fbfdff 100%);border-bottom:1px solid #e3ebf5}",
      ".orders-react-kicker{display:inline-flex;align-items:center;gap:7px;font-size:12px;font-weight:900;color:#0f766e;margin-bottom:5px}",
      ".orders-react-kicker:before{content:'';width:7px;height:7px;border-radius:999px;background:#0f766e;box-shadow:0 0 0 4px rgba(15,118,110,.12)}",
      ".orders-react-head h1{margin:0;font-size:25px;line-height:1.25;font-weight:900;color:#0f172a;letter-spacing:0}",
      ".orders-react-head p{margin:6px 0 0;color:#64748b;font-size:13px;max-width:740px}",
      ".orders-react-links{display:flex;gap:7px;flex-wrap:wrap;justify-content:flex-end}",
      ".orders-react-link{display:inline-flex;align-items:center;justify-content:center;gap:7px;height:34px;padding:0 11px;border:1px solid #cbd8e6;border-radius:7px;background:#fff;color:#1f344d;font-weight:900;text-decoration:none;white-space:nowrap}",
      ".orders-react-link:hover{background:#f1f7ff;color:#0f4c81;text-decoration:none;border-color:#adc3dc}",
      ".orders-react-dashboard{display:grid;grid-template-columns:1fr;gap:0;border-bottom:1px solid #e3ebf5}",
      ".orders-react-metrics{display:grid;grid-template-columns:repeat(4,minmax(115px,1fr));min-width:0}",
      ".orders-react-followup{display:grid;grid-template-columns:repeat(4,minmax(115px,1fr));background:#fbfdff;border-top:1px solid #e3ebf5}",
      ".orders-react-metric{padding:13px 15px;border-left:1px solid #edf2f7;min-width:0}",
      ".orders-react-metric:last-child{border-left:0}",
      ".orders-react-metric small{display:block;font-size:11px;color:#64748b;font-weight:900;margin-bottom:5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}",
      ".orders-react-metric strong{display:block;font-size:22px;line-height:1;font-weight:900;color:#0f172a}",
      ".orders-react-metric.is-hot strong{color:#b45309}",
      ".orders-react-metric.is-good strong{color:#0f766e}",
      ".orders-react-metric.is-selected strong{color:#155e75}",
      ".orders-react-command{display:grid;grid-template-columns:1fr;gap:10px;align-items:center;padding:12px 14px;background:#fff}",
      ".orders-react-ops{display:grid;grid-template-columns:minmax(260px,1fr) auto;gap:10px;align-items:center}",
      ".orders-react-search{position:relative}",
      ".orders-react-search i{position:absolute;right:13px;top:50%;transform:translateY(-50%);color:#64748b}",
      ".orders-react-search input{width:100%;height:39px;border:1px solid #cbd8e6;border-radius:7px;background:#fff;padding:0 40px 0 12px;font-weight:800;color:#132033;outline:0}",
      ".orders-react-search input:focus{border-color:#0f766e;box-shadow:0 0 0 3px rgba(15,118,110,.12)}",
      ".orders-react-selected{height:39px;display:inline-flex;align-items:center;gap:8px;padding:0 13px;border:1px solid #cbd8e6;border-radius:7px;background:#f8fafc;font-weight:900;color:#0f172a;white-space:nowrap}",
      ".orders-react-selected b{color:#0f766e;font-size:18px}",
      ".orders-react-tabs{display:flex;gap:6px;overflow-x:auto;scrollbar-width:thin;justify-content:flex-start;min-width:0;padding-bottom:2px}",
      ".orders-react-tab{border:1px solid transparent;border-radius:7px;background:transparent;color:#475569;height:39px;padding:0 11px;display:inline-flex;align-items:center;gap:7px;font-weight:900;white-space:nowrap}",
      ".orders-react-tab .count{min-width:25px;height:25px;border-radius:999px;background:#edf2f7;color:#334155;display:inline-flex;align-items:center;justify-content:center;padding:0 8px;font-size:12px}",
      ".orders-react-tab:hover{background:#f1f5f9;border-color:#dbe3ef}",
      ".orders-react-tab.is-active{background:#0f766e;color:#fff;border-color:#0f766e;box-shadow:0 8px 18px rgba(15,118,110,.18)}",
      ".orders-react-tab.is-active .count{background:rgba(255,255,255,.2);color:#fff}",
      "body.orders-react-ready .orders-page .orders-tabs-custom{margin-top:0}",
      "body.orders-react-ready .orders-page .orders-status-tab-content{padding:0}",
      "body.orders-react-ready .orders-page .panel{border:1px solid #d5e0ec;border-radius:8px;background:#fff;box-shadow:0 14px 36px rgba(15,23,42,.05);margin:0;overflow:hidden}",
      "body.orders-react-ready .orders-page .box-header{display:none}",
      "body.orders-react-ready .orders-page .box-body{padding:0}",
      ".orders-react-list-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 16px;border-bottom:1px solid #e3ebf5;background:#fff}",
      ".orders-react-list-title{display:flex;align-items:center;gap:9px;min-width:0}",
      ".orders-react-list-title i{width:30px;height:30px;border-radius:7px;display:inline-flex;align-items:center;justify-content:center;background:#eef7f6;color:#0f766e}",
      ".orders-react-list-title strong{display:block;font-size:15px;font-weight:900;color:#0f172a;line-height:1.2}",
      ".orders-react-list-title span{display:block;margin-top:3px;font-size:12px;font-weight:800;color:#64748b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}",
      ".orders-react-list-count{height:30px;display:inline-flex;align-items:center;gap:7px;padding:0 11px;border-radius:999px;background:#f1f5f9;color:#334155;font-weight:900;white-space:nowrap}",
      ".orders-react-list-count b{color:#0f766e;font-size:16px}",
      "body.orders-react-ready .orders-page .bulkbar{margin:0;padding:12px 16px;border:0;border-bottom:1px solid #e3ebf5;border-radius:0;background:#fbfdff}",
      "body.orders-react-ready .orders-page .bulkbar-main{display:grid;grid-template-columns:minmax(210px,260px) minmax(220px,1fr) auto auto auto;gap:8px;align-items:center}",
      "body.orders-react-ready .orders-page .bulkbar .form-control{height:38px;border-radius:7px;border-color:#cbd8e6;box-shadow:none;font-weight:700}",
      "body.orders-react-ready .orders-page .bulkbar .btn{height:38px;border-radius:7px;font-weight:900;border:0;box-shadow:none;padding:0 14px}",
      "body.orders-react-ready .orders-page .bulkbar-note{display:none}",
      "body.orders-react-ready .orders-page .table-responsive{border:0;margin:0}",
      "body.orders-react-ready .orders-page table.orders-table{margin:0!important;border:0!important;background:#fff;width:100%!important;min-width:0;table-layout:fixed}",
      "body.orders-react-ready .orders-page table.orders-table thead th{position:sticky;top:0;z-index:1;background:#f8fafc;color:#1f2937;border-color:#e3ebf5!important;font-size:12px;font-weight:900;padding:12px 12px;white-space:nowrap;text-transform:none}",
      "body.orders-react-ready .orders-page table.orders-table thead th:nth-child(1){width:44px}",
      "body.orders-react-ready .orders-page table.orders-table thead th:nth-child(2){width:24%}",
      "body.orders-react-ready .orders-page table.orders-table thead th:nth-child(3){width:15%}",
      "body.orders-react-ready .orders-page table.orders-table thead th:nth-child(4){width:13%}",
      "body.orders-react-ready .orders-page table.orders-table thead th:nth-child(5){width:12%}",
      "body.orders-react-ready .orders-page table.orders-table thead th:nth-child(6){width:16%}",
      "body.orders-react-ready .orders-page table.orders-table thead th:nth-child(7){width:16%}",
      "body.orders-react-ready .orders-page table.orders-table tbody td{border-color:#eef2f7!important;vertical-align:top;padding:15px 12px;color:#1f2937;background:#fff;font-size:13px;font-weight:700}",
      "body.orders-react-ready .orders-page table.orders-table tbody td{overflow:hidden}",
      "body.orders-react-ready .orders-page table.orders-table tbody tr{transition:background .15s ease,box-shadow .15s ease}",
      "body.orders-react-ready .orders-page table.orders-table tbody tr:hover td{background:#fbfdff}",
      "body.orders-react-ready .orders-page table.orders-table tbody tr.is-selected-row td{background:#f0fdfa}",
      "body.orders-react-ready .orders-page .orders-table input[type='checkbox']{width:18px;height:18px;accent-color:#0f766e}",
      "body.orders-react-ready .orders-page .order-main strong{color:#0f172a;font-size:14px;line-height:1.6;font-weight:900}",
      "body.orders-react-ready .orders-page .order-meta,body.orders-react-ready .orders-page .muted{color:#4b5563;font-weight:700}",
      "body.orders-react-ready .orders-page .order-meta{gap:5px 7px;margin-top:7px}",
      "body.orders-react-ready .orders-page .tag{background:#f1f5f9;color:#475569;border-radius:6px;font-size:11px;padding:4px 7px}",
      "body.orders-react-ready .orders-page .label,body.orders-react-ready .orders-page .badge{border-radius:6px;font-weight:900}",
      "body.orders-react-ready .orders-page .pill{border-radius:7px;padding:6px 9px;font-size:12px;font-weight:900}",
      "body.orders-react-ready .orders-page .callbox,body.orders-react-ready .orders-page .statusbox{margin-top:0;padding:0;border:0;background:transparent}",
      "body.orders-react-ready .orders-page .callbox small,body.orders-react-ready .orders-page .statusbox small{color:#4b5563;font-size:12px;line-height:1.75;font-weight:700}",
      "body.orders-react-ready .orders-page .callbox .followup-hint{display:block;margin:7px 0 0;color:#334155;font-weight:900}",
      "body.orders-react-ready .orders-page .callbox .callbox-link{display:inline-flex;align-items:center;gap:5px;margin-top:7px;color:#0f766e;font-weight:900}",
      "body.orders-react-ready .orders-page .status-note{margin:8px 0 0;color:#374151;font-size:12px;line-height:1.75;font-weight:700}",
      "body.orders-react-ready .orders-page .row-actions{display:flex;flex-direction:column;gap:7px;min-width:0}",
      "body.orders-react-ready .orders-page .row-actions .btn{height:30px;display:inline-flex;align-items:center;justify-content:center;gap:6px;border-radius:7px;font-size:11px;font-weight:900;padding:0 8px;border:1px solid #d8e2ee;box-shadow:none;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}",
      "body.orders-react-ready .orders-page .row-actions .btn-info{background:#0f766e;border-color:#0f766e;color:#fff}",
      "body.orders-react-ready .orders-page .row-actions .btn-default{background:#fff;color:#334155}",
      "body.orders-react-ready .orders-page .row-actions .btn-success{background:#16a34a;border-color:#16a34a;color:#fff}",
      "body.orders-react-ready .orders-page .row-actions .btn-warning{background:#f59e0b;border-color:#f59e0b;color:#fff}",
      "body.orders-react-ready .orders-page .row-actions .btn-primary{background:#2563eb;border-color:#2563eb;color:#fff}",
      "body.orders-react-ready .orders-page .row-actions .btn-danger{background:#fff;border-color:#fecaca;color:#b91c1c}",
      ".orders-react-drawer-backdrop{position:fixed;inset:0;z-index:4050;background:rgba(15,23,42,.36);backdrop-filter:blur(2px)}",
      ".orders-react-drawer{position:fixed;top:0;bottom:0;right:0;z-index:4051;width:min(860px,calc(100vw - 28px));background:#fff;box-shadow:-24px 0 60px rgba(15,23,42,.28);display:flex;flex-direction:column;direction:rtl}",
      ".orders-react-drawer-head{height:72px;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:0 18px;border-bottom:1px solid #e3ebf5;background:#fbfdff}",
      ".orders-react-drawer-title{min-width:0}",
      ".orders-react-drawer-title strong{display:block;color:#0f172a;font-size:16px;font-weight:900;line-height:1.3}",
      ".orders-react-drawer-title span{display:block;margin-top:4px;color:#64748b;font-size:12px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}",
      ".orders-react-drawer-close{width:36px;height:36px;border:1px solid #cbd8e6;border-radius:8px;background:#fff;color:#334155;display:inline-flex;align-items:center;justify-content:center}",
      ".orders-react-drawer-body{flex:1;min-height:0;background:#f8fafc}",
      ".orders-react-drawer-frame{width:100%;height:100%;border:0;background:#fff}",
      ".orders-react-confirm{margin:24px;border:1px solid #e3ebf5;border-radius:8px;background:#fff;box-shadow:0 18px 44px rgba(15,23,42,.08);padding:18px}",
      ".orders-react-confirm-icon{width:42px;height:42px;border-radius:8px;background:#fef2f2;color:#b91c1c;display:inline-flex;align-items:center;justify-content:center;margin-bottom:12px}",
      ".orders-react-confirm h3{margin:0;color:#0f172a;font-size:18px;font-weight:900}",
      ".orders-react-confirm p{margin:8px 0 0;color:#64748b;line-height:1.8;font-weight:700}",
      ".orders-react-confirm-actions{display:flex;gap:8px;justify-content:flex-start;margin-top:18px}",
      ".orders-react-confirm-actions button,.orders-react-confirm-actions a{height:38px;border-radius:7px;border:0;padding:0 14px;font-weight:900;display:inline-flex;align-items:center;justify-content:center;text-decoration:none}",
      ".orders-react-confirm-cancel{background:#f1f5f9;color:#334155}",
      ".orders-react-confirm-danger{background:#dc2626;color:#fff}",
      "body.orders-react-ready .orders-page .dataTables_wrapper .row:first-child{display:none}",
      "body.orders-react-ready .orders-page .dataTables_wrapper .row:last-child{padding:10px 12px;margin:0;background:#fff;color:#64748b;font-weight:800}",
      "body.orders-react-ready .orders-page .dataTables_filter input,body.orders-react-ready .orders-page .dataTables_length select{border:1px solid #cbd8e6;border-radius:7px;height:34px;padding:4px 8px}",
      "@media (max-width:1200px){.orders-react-command{grid-template-columns:1fr}.orders-react-tabs{justify-content:flex-start}}",
      "@media (max-width:900px){.orders-react-head,.orders-react-ops{grid-template-columns:1fr}.orders-react-links{justify-content:flex-start}.orders-react-metrics,.orders-react-followup{grid-template-columns:repeat(2,minmax(130px,1fr))}body.orders-react-ready .orders-page .bulkbar-main{grid-template-columns:1fr 1fr}.orders-react-list-head{align-items:flex-start;flex-direction:column}}",
      "@media (max-width:680px){body.orders-react-ready .orders-page{padding:12px}.orders-react-head{padding:14px}.orders-react-head h1{font-size:22px}.orders-react-metrics,.orders-react-followup{grid-template-columns:1fr}.orders-react-metric{border-left:0;border-bottom:1px solid #e7edf5}body.orders-react-ready .orders-page .bulkbar-main{grid-template-columns:1fr}.orders-react-selected{justify-content:center}}"
    ].join("\n");
    document.head.appendChild(style);
  }

  function data(name, fallback) {
    var value = root.getAttribute("data-" + name);
    return value == null || value === "" ? fallback : value;
  }

  function readTabs() {
    return Array.prototype.slice.call(document.querySelectorAll("#orderStatusTabs a[data-toggle='tab']")).map(function (link) {
      var icon = link.querySelector("i");
      var count = link.querySelector(".count");
      var text = Array.prototype.slice.call(link.childNodes).map(function (node) {
        return node.nodeType === 3 ? node.textContent : "";
      }).join(" ").trim();
      var span = link.querySelector("span:not(.count)");
      return {
        hash: link.getAttribute("href") || "",
        title: (span ? span.textContent : text).trim(),
        icon: icon ? icon.className : "fa fa-circle-o",
        count: count ? count.textContent.trim() : "0",
        active: !!(link.parentNode && link.parentNode.classList.contains("active"))
      };
    });
  }

  function activeHashFromDom() {
    var active = document.querySelector("#orderStatusTabs li.active a[data-toggle='tab']");
    if (active) {
      return active.getAttribute("href") || "";
    }
    var pane = document.querySelector(".orders-status-tab-content .tab-pane.active");
    return pane && pane.id ? "#" + pane.id : "";
  }

  function selectedCount() {
    return document.querySelectorAll(".orders-status-tab-content .js-order-checkbox:checked").length;
  }

  function updateSelectedRows() {
    Array.prototype.slice.call(document.querySelectorAll(".orders-status-tab-content .js-order-checkbox")).forEach(function (box) {
      var row = box.closest ? box.closest("tr") : null;
      if (row) {
        row.classList.toggle("is-selected-row", box.checked);
      }
    });
  }

  function ensureListHeads() {
    Array.prototype.slice.call(document.querySelectorAll(".orders-status-tab-content .tab-pane")).forEach(function (pane) {
      var panel = pane.querySelector(".panel");
      var bulkbar = pane.querySelector(".bulkbar");
      if (!panel || !bulkbar || panel.querySelector(".orders-react-list-head")) {
        return;
      }
      var tab = document.querySelector("#orderStatusTabs a[href='#" + pane.id + "']");
      var titleNode = tab ? tab.querySelector("span:not(.count)") : null;
      var countNode = tab ? tab.querySelector(".count") : null;
      var title = titleNode ? titleNode.textContent.trim() : labels.title;
      var count = countNode ? countNode.textContent.trim() : String(pane.querySelectorAll("tbody tr").length);
      var head = document.createElement("div");
      head.className = "orders-react-list-head";
      head.innerHTML = [
        '<div class="orders-react-list-title">',
        '<i class="fa fa-list-ul"></i>',
        '<div><strong>\u0642\u0627\u0626\u0645\u0629 \u0627\u0644\u0637\u0644\u0628\u0627\u062a</strong><span></span></div>',
        '</div>',
        '<div class="orders-react-list-count"><b></b><span>\u0637\u0644\u0628</span></div>'
      ].join("");
      head.querySelector(".orders-react-list-title span").textContent = title;
      head.querySelector(".orders-react-list-count b").textContent = count;
      bulkbar.parentNode.insertBefore(head, bulkbar);
    });
  }

  function searchActiveTable(query) {
    if (!$ || !$.fn || !$.fn.DataTable) {
      return;
    }
    var activePane = document.querySelector(".orders-status-tab-content .tab-pane.active");
    var table = activePane ? activePane.querySelector("table.orders-table") : null;
    if (!table) {
      return;
    }
    try {
      $(table).DataTable().search(query).draw();
    } catch (error) {
      // DataTables may still be initializing on a slow refresh.
    }
  }

  function activateTab(hash) {
    if (!hash) {
      return;
    }
    if ($ && $.fn && $.fn.tab) {
      $("#orderStatusTabs a[href='" + hash.replace(/'/g, "\\'") + "']").tab("show");
      return;
    }
    var link = document.querySelector("#orderStatusTabs a[href='" + hash + "']");
    if (link) {
      link.click();
    }
  }

  function orderContextFromNode(node) {
    var row = node && node.closest ? node.closest("tr") : null;
    var box = row ? row.querySelector(".js-order-checkbox") : null;
    var data = box ? box.dataset : {};
    var titleNode = row ? row.querySelector(".order-main strong") : null;
    return {
      label: data.orderLabel || (titleNode ? titleNode.textContent.trim() : labels.title),
      customer: data.orderCustomer || "",
      phone: data.orderPhone || "",
      product: data.orderProduct || (titleNode ? titleNode.textContent.trim() : ""),
      total: data.orderTotal || "",
      status: data.orderStatus || ""
    };
  }

  function drawerSrc(href) {
    if (!href) {
      return "";
    }
    return href + (href.indexOf("?") === -1 ? "?" : "&") + "react_card=1";
  }

  function Metric(props) {
    return h("div", { className: "orders-react-metric" + (props.tone ? " is-" + props.tone : "") },
      h("small", null, props.label),
      h("strong", null, props.value)
    );
  }

  function App() {
    var initialTabs = useMemo(readTabs, []);
    var initialActive = activeHashFromDom() || (initialTabs[0] && initialTabs[0].hash) || "";
    var _active = useState(initialActive);
    var active = _active[0];
    var setActive = _active[1];
    var _tabs = useState(initialTabs);
    var tabs = _tabs[0];
    var setTabs = _tabs[1];
    var _selected = useState(selectedCount());
    var selected = _selected[0];
    var setSelected = _selected[1];
    var _query = useState("");
    var query = _query[0];
    var setQuery = _query[1];
    var _actionCard = useState(null);
    var actionCard = _actionCard[0];
    var setActionCard = _actionCard[1];

    useEffect(function () {
      injectStyles();
      document.body.classList.add("orders-react-ready");
      function handleActionClick(event) {
        var detailsLink = event.target && event.target.closest ? event.target.closest(".row-actions a[href*='order-details.php']") : null;
        var deleteLink = event.target && event.target.closest ? event.target.closest(".row-actions a[href*='order-delete.php']") : null;
        if (detailsLink) {
          event.preventDefault();
          event.stopPropagation();
          var detailsContext = orderContextFromNode(detailsLink);
          setActionCard({
            type: "details",
            href: detailsLink.getAttribute("href"),
            title: "\u0628\u0637\u0627\u0642\u0629 \u0627\u0644\u0637\u0644\u0628",
            subtitle: detailsContext.label + (detailsContext.customer ? " - " + detailsContext.customer : ""),
            context: detailsContext
          });
          return;
        }
        if (deleteLink) {
          event.preventDefault();
          event.stopPropagation();
          var deleteContext = orderContextFromNode(deleteLink);
          setActionCard({
            type: "delete",
            href: deleteLink.getAttribute("href"),
            title: "\u062d\u0630\u0641 \u0627\u0644\u0637\u0644\u0628",
            subtitle: deleteContext.label + (deleteContext.customer ? " - " + deleteContext.customer : ""),
            context: deleteContext
          });
        }
      }
      function syncTabs() {
        setTabs(readTabs());
        setActive(activeHashFromDom());
      }
      function syncSelected() {
        updateSelectedRows();
        setSelected(selectedCount());
      }
      if ($) {
        $("#orderStatusTabs a[data-toggle='tab']").on("shown.bs.tab.ordersReact", syncTabs);
      }
      document.addEventListener("click", handleActionClick, true);
      document.addEventListener("change", syncSelected);
      syncTabs();
      ensureListHeads();
      syncSelected();
      return function () {
        document.body.classList.remove("orders-react-ready");
        if ($) {
          $("#orderStatusTabs a[data-toggle='tab']").off(".ordersReact");
        }
        document.removeEventListener("click", handleActionClick, true);
        document.removeEventListener("change", syncSelected);
      };
    }, []);

    useEffect(function () {
      searchActiveTable(query);
    }, [query, active]);

    return h("div", { className: "orders-react-workspace" },
      h("div", { className: "orders-react-console" },
        h("div", { className: "orders-react-head" },
          h("div", null,
            h("div", { className: "orders-react-kicker" }, "\u0627\u0644\u0645\u0628\u064a\u0639\u0627\u062a"),
            h("h1", null, labels.title),
            h("p", null, labels.subtitle)
          ),
          h("div", { className: "orders-react-links" },
            h("a", { className: "orders-react-link", href: "order-statistics.php" }, h("i", { className: "fa fa-line-chart" }), labels.stats),
            h("a", { className: "orders-react-link", href: "incomplete-orders.php" }, h("i", { className: "fa fa-exclamation-circle" }), labels.incomplete),
            h("a", { className: "orders-react-link", href: "delivery-company.php" }, h("i", { className: "fa fa-truck" }), labels.shipping)
          )
        ),
        h("div", { className: "orders-react-dashboard" },
          h("div", { className: "orders-react-metrics" },
            h(Metric, { label: labels.total, value: data("total", "0") }),
            h(Metric, { label: labels.today, value: data("today", "0") }),
            h(Metric, { label: labels.pending, value: data("pending", "0"), tone: "hot" }),
            h(Metric, { label: labels.completedAmount, value: data("completed-amount", "0"), tone: "good" })
          ),
          h("div", { className: "orders-react-followup" },
            h(Metric, { label: labels.noCalls, value: data("pending-no-calls", "0"), tone: "hot" }),
            h(Metric, { label: labels.ready, value: data("pending-ready", "0"), tone: "good" }),
            h(Metric, { label: labels.followup, value: data("pending-followup", "0") }),
            h(Metric, { label: labels.selected, value: selected, tone: "selected" })
          )
        ),
        h("div", { className: "orders-react-command" },
          h("div", { className: "orders-react-ops" },
            h("label", { className: "orders-react-search" },
              h("i", { className: "fa fa-search" }),
              h("input", {
                type: "search",
                value: query,
                placeholder: labels.search,
                onChange: function (event) {
                  setQuery(event.target.value);
                }
              })
            ),
            h("div", { className: "orders-react-selected" },
              h("b", null, selected),
              h("span", null, labels.selected)
            )
          ),
          h("div", { className: "orders-react-tabs", role: "tablist" },
            tabs.map(function (tab) {
              return h("button", {
                key: tab.hash,
                type: "button",
                className: "orders-react-tab" + (tab.hash === active ? " is-active" : ""),
                onClick: function () {
                  activateTab(tab.hash);
                }
              },
                h("i", { className: tab.icon }),
                h("span", null, tab.title),
                h("span", { className: "count" }, tab.count)
              );
            })
          )
        )
      ),
      actionCard ? h("div", null,
        h("div", { className: "orders-react-drawer-backdrop", onClick: function () { setActionCard(null); } }),
        h("aside", { className: "orders-react-drawer", role: "dialog", "aria-modal": "true" },
          h("div", { className: "orders-react-drawer-head" },
            h("div", { className: "orders-react-drawer-title" },
              h("strong", null, actionCard.title),
              h("span", null, actionCard.subtitle)
            ),
            h("button", { type: "button", className: "orders-react-drawer-close", onClick: function () { setActionCard(null); } },
              h("i", { className: "fa fa-times" })
            )
          ),
          h("div", { className: "orders-react-drawer-body" },
            actionCard.type === "details"
              ? h("iframe", { className: "orders-react-drawer-frame", title: actionCard.title, src: drawerSrc(actionCard.href) })
              : h("div", { className: "orders-react-confirm" },
                  h("div", { className: "orders-react-confirm-icon" }, h("i", { className: "fa fa-trash" })),
                  h("h3", null, "\u062d\u0630\u0641 \u0646\u0647\u0627\u0626\u064a\u061f"),
                  h("p", null, "\u0633\u064a\u062a\u0645 \u062d\u0630\u0641 \u0647\u0630\u0627 \u0627\u0644\u0637\u0644\u0628 \u0645\u0646 \u0627\u0644\u0646\u0638\u0627\u0645. \u0647\u0630\u0627 \u0627\u0644\u0625\u062c\u0631\u0627\u0621 \u0644\u0627 \u064a\u062c\u0628 \u062a\u0646\u0641\u064a\u0630\u0647 \u0625\u0644\u0627 \u0639\u0646\u062f \u0627\u0644\u062a\u0623\u0643\u062f."),
                  h("p", null, actionCard.subtitle),
                  h("div", { className: "orders-react-confirm-actions" },
                    h("button", { type: "button", className: "orders-react-confirm-cancel", onClick: function () { setActionCard(null); } }, "\u0625\u0644\u063a\u0627\u0621"),
                    h("button", { type: "button", className: "orders-react-confirm-danger", onClick: function () { window.location.href = actionCard.href; } }, "\u062d\u0630\u0641 \u0627\u0644\u0637\u0644\u0628")
                  )
                )
          )
        )
      ) : null
    );
  }

  ReactDOM.createRoot(root).render(h(App));
})();
