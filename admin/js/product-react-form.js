(function () {
  "use strict";

  if (window.self !== window.top || !window.React || !window.ReactDOM) {
    return;
  }

  var page = (window.location.pathname.split("/").pop() || "").toLowerCase();
  if (page !== "product-add.php" && page !== "product-edit.php") {
    return;
  }

  var React = window.React;
  var h = React.createElement;
  var useEffect = React.useEffect;
  var useMemo = React.useMemo;
  var useRef = React.useRef;
  var useState = React.useState;

  var labels = {
    addTitle: "\u0625\u0636\u0627\u0641\u0629 \u0645\u0646\u062a\u062c",
    editTitle: "\u062a\u0639\u062f\u064a\u0644 \u0645\u0646\u062a\u062c",
    addAction: "\u0625\u0636\u0627\u0641\u0629 \u0627\u0644\u0645\u0646\u062a\u062c",
    editAction: "\u062d\u0641\u0638 \u0627\u0644\u062a\u0639\u062f\u064a\u0644\u0627\u062a",
    subtitle: "\u0648\u0627\u062c\u0647\u0629 \u0645\u0646\u0638\u0645\u0629 \u0644\u0625\u062f\u0627\u0631\u0629 \u0628\u064a\u0627\u0646\u0627\u062a \u0627\u0644\u0645\u0646\u062a\u062c \u0648\u0627\u0644\u0639\u0631\u0648\u0636 \u0648\u0627\u0644\u0635\u0648\u0631 \u0645\u0646 \u0646\u0641\u0633 \u0646\u0645\u0648\u0630\u062c \u0627\u0644\u062d\u0641\u0638.",
    back: "\u0643\u0644 \u0627\u0644\u0645\u0646\u062a\u062c\u0627\u062a",
    save: "\u062d\u0641\u0638",
    preview: "\u0645\u0644\u062e\u0635 \u0627\u0644\u0645\u0646\u062a\u062c",
    name: "\u0627\u0633\u0645 \u0627\u0644\u0645\u0646\u062a\u062c",
    price: "\u0627\u0644\u0633\u0639\u0631",
    template: "\u0627\u0644\u0642\u0627\u0644\u0628",
    status: "\u0627\u0644\u062d\u0627\u0644\u0629",
    empty: "\u0644\u0645 \u064a\u062a\u0645 \u062a\u0639\u0628\u0626\u062a\u0647",
    active: "\u0646\u0634\u0637",
    inactive: "\u063a\u064a\u0631 \u0646\u0634\u0637",
    complete: "\u0627\u0643\u062a\u0645\u0627\u0644 \u0627\u0644\u0623\u0633\u0627\u0633\u064a\u0627\u062a",
    basics: "\u0627\u0644\u0623\u0633\u0627\u0633\u064a\u0627\u062a",
    basicsHint: "\u0627\u0644\u0627\u0633\u0645\u060c \u0627\u0644\u062a\u0635\u0646\u064a\u0641\u060c \u0627\u0644\u0642\u0627\u0644\u0628 \u0648\u0627\u0644\u062a\u0648\u0635\u064a\u0644",
    pricing: "\u0627\u0644\u062a\u0633\u0639\u064a\u0631 \u0648\u0627\u0644\u0639\u0631\u0648\u0636",
    pricingHint: "\u0627\u0644\u0633\u0639\u0631\u060c \u0627\u0644\u0643\u0645\u064a\u0629 \u0648\u0639\u0631\u0648\u0636 \u0627\u0644\u0635\u0641\u062d\u0629",
    content: "\u0627\u0644\u0648\u0635\u0641 \u0648\u0627\u0644\u0645\u062d\u062a\u0648\u0649",
    contentHint: "\u0646\u0635\u0648\u0635 \u0627\u0644\u0645\u0646\u062a\u062c \u0648\u0627\u0644\u0625\u0639\u0644\u0627\u0646 \u0627\u0644\u0645\u062e\u062a\u0635\u0631",
    media: "\u0627\u0644\u0635\u0648\u0631 \u0648\u0627\u0644\u0648\u0633\u0627\u0626\u0637",
    mediaHint: "\u0627\u0644\u0635\u0648\u0631\u0629 \u0627\u0644\u0631\u0626\u064a\u0633\u064a\u0629\u060c \u0635\u0648\u0631 \u0627\u0644\u0647\u0628\u0648\u0637 \u0648\u0627\u0644\u0645\u0639\u0631\u0636",
    options: "\u0627\u0644\u062e\u064a\u0627\u0631\u0627\u062a",
    optionsHint: "\u0627\u0644\u0645\u0642\u0627\u0633\u0627\u062a\u060c \u0627\u0644\u0623\u0644\u0648\u0627\u0646\u060c \u0627\u0644\u0628\u0643\u0633\u0644\u0627\u062a \u0648\u0627\u0644\u0646\u0634\u0631",
    stepOne: "\u0627\u0644\u0645\u0639\u0644\u0648\u0645\u0627\u062a \u0648\u0627\u0644\u0639\u0631\u0648\u0636",
    stepOneHint: "\u0628\u064a\u0627\u0646\u0627\u062a \u0627\u0644\u0645\u0646\u062a\u062c\u060c \u0627\u0644\u062a\u0635\u0646\u064a\u0641\u060c \u0627\u0644\u0633\u0639\u0631 \u0648\u0627\u0644\u0639\u0631\u0648\u0636",
    stepTwo: "\u0627\u0644\u0645\u062d\u062a\u0648\u0649 \u0648\u0627\u0644\u0646\u0634\u0631",
    stepTwoHint: "\u0627\u0644\u0648\u0635\u0641\u060c \u0627\u0644\u0635\u0648\u0631\u060c \u0627\u0644\u062e\u064a\u0627\u0631\u0627\u062a \u0648\u062d\u0627\u0644\u0629 \u0627\u0644\u0646\u0634\u0631",
    next: "\u0627\u0644\u062a\u0627\u0644\u064a",
    previous: "\u0627\u0644\u0633\u0627\u0628\u0642"
  };

  function decodeMojibake(text) {
    text = String(text || "");
    if (!/[ÃØÙ]/.test(text) || !window.TextDecoder) {
      return text;
    }
    function cp1252Byte(character) {
      var code = character.charCodeAt(0);
      var map = {
        0x20ac: 0x80, 0x201a: 0x82, 0x0192: 0x83, 0x201e: 0x84,
        0x2026: 0x85, 0x2020: 0x86, 0x2021: 0x87, 0x02c6: 0x88,
        0x2030: 0x89, 0x0160: 0x8a, 0x2039: 0x8b, 0x0152: 0x8c,
        0x017d: 0x8e, 0x2018: 0x91, 0x2019: 0x92, 0x201c: 0x93,
        0x201d: 0x94, 0x2022: 0x95, 0x2013: 0x96, 0x2014: 0x97,
        0x02dc: 0x98, 0x2122: 0x99, 0x0161: 0x9a, 0x203a: 0x9b,
        0x0153: 0x9c, 0x017e: 0x9e, 0x0178: 0x9f
      };
      return map[code] || (code <= 255 ? code : 63);
    }
    try {
      var current = text;
      for (var pass = 0; pass < 3; pass += 1) {
        if (!/[ÃØÙ]/.test(current)) {
          break;
        }
        var bytes = new Uint8Array(current.length);
        for (var i = 0; i < current.length; i += 1) {
          bytes[i] = cp1252Byte(current.charAt(i));
        }
        var next = new TextDecoder("utf-8").decode(bytes);
        if (!next || next === current) {
          break;
        }
        current = next;
      }
      return current;
    } catch (error) {
      return text;
    }
  }

  function cleanTextNodeTree(root) {
    var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null);
    var nodes = [];
    while (walker.nextNode()) {
      nodes.push(walker.currentNode);
    }
    nodes.forEach(function (node) {
      var decoded = decodeMojibake(node.nodeValue);
      if (decoded !== node.nodeValue) {
        node.nodeValue = decoded;
      }
    });
    Array.prototype.forEach.call(root.querySelectorAll("[placeholder],[title],[value],[alt]"), function (node) {
      ["placeholder", "title", "value", "alt"].forEach(function (attribute) {
        if (!node.hasAttribute(attribute) || (attribute === "value" && /^(INPUT|TEXTAREA)$/.test(node.tagName))) {
          return;
        }
        var value = node.getAttribute(attribute);
        var decoded = decodeMojibake(value);
        if (decoded !== value) {
          node.setAttribute(attribute, decoded);
        }
      });
    });
  }

  function fieldNames(node) {
    return Array.prototype.map.call(node.querySelectorAll("[name]"), function (field) {
      return field.getAttribute("name") || "";
    }).join(" ");
  }

  function sectionFor(node) {
    var id = node.id || "";
    var names = fieldNames(node);
    var text = (id + " " + names).toLowerCase();
    if (/offer|price|qty|purchase|most_popular/.test(text)) {
      return "pricing";
    }
    if (/description|announcement/.test(text)) {
      return "content";
    }
    if (/photo|image|landing|additional/.test(text) || id === "landing-photos-section" || id === "additional-photos-section") {
      return "media";
    }
    if (/size|color|pixel|featured|active/.test(text) || id === "colorPhotoFields") {
      return "options";
    }
    return "basics";
  }

  function normalizeField(group) {
    if (!group || group.dataset.productReactField === "1") {
      return;
    }
    group.dataset.productReactField = "1";
    group.classList.add("product-react-field");
    var label = group.querySelector("label.control-label");
    if (label) {
      label.classList.add("product-react-label");
      label.classList.remove("col-sm-3", "control-label");
    }
    Array.prototype.forEach.call(group.querySelectorAll(".col-sm-4,.col-sm-6,.col-sm-8,.col-md-4,.col-md-6,.col-md-8"), function (column) {
      column.classList.add("product-react-control");
      column.classList.remove("col-sm-4", "col-sm-6", "col-sm-8", "col-md-4", "col-md-6", "col-md-8");
    });
    if (group.querySelector("textarea") || group.querySelector(".panel") || group.id === "landing-photos-section") {
      group.classList.add("product-react-field-wide");
    }
    if (group.querySelector('input[type="file"], img.admin-thumb')) {
      group.classList.add("product-react-media-field");
    }
    if (group.querySelector('[name="p_name"]')) {
      group.classList.add("product-react-field-focus");
    }
  }

  function enhanceForm(form) {
    if (!form || form.dataset.productReactEnhanced === "1") {
      return;
    }
    form.dataset.productReactEnhanced = "1";
    form.setAttribute("dir", "rtl");
    cleanTextNodeTree(form);

    var submitGroup = null;
    Array.prototype.some.call(form.children, function (child) {
      if (child.classList && child.classList.contains("form-group") && child.querySelector('[name="form1"]')) {
        submitGroup = child;
        return true;
      }
      return false;
    });

    var sections = {
      basics: createSection("basics"),
      pricing: createSection("pricing"),
      content: createSection("content"),
      media: createSection("media"),
      options: createSection("options")
    };
    var board = document.createElement("div");
    board.className = "product-react-sections";
    ["basics", "pricing", "content", "media", "options"].forEach(function (key) {
      board.appendChild(sections[key]);
    });

    var nodes = [];
    Array.prototype.forEach.call(form.querySelectorAll(".admin-product-card .box-body > .form-group, #landing-photos-section, #colorPhotoFields, #additional-photos-section .box-body > .form-group"), function (node) {
      if (nodes.indexOf(node) === -1) {
        nodes.push(node);
      }
    });

    nodes.forEach(function (node) {
      if (node.id === "colorPhotoFields") {
        node.classList.add("product-react-color-photos");
      } else {
        normalizeField(node);
      }
      var key = sectionFor(node);
      sections[key].querySelector(".product-react-section-body").appendChild(node);
    });

    Array.prototype.forEach.call(form.querySelectorAll(".admin-product-card"), function (card) {
      if (!card.querySelector(".form-group") && !card.querySelector("#landing-photos-section") && !card.querySelector("#colorPhotoFields")) {
        card.parentNode.removeChild(card);
      }
    });

    if (submitGroup) {
      submitGroup.classList.add("product-react-submit-row");
      normalizeField(submitGroup);
      form.insertBefore(board, submitGroup);
    } else {
      form.appendChild(board);
    }

    Array.prototype.forEach.call(form.querySelectorAll(".select2-container"), function (node) {
      node.style.width = "100%";
    });

    if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
      window.jQuery(form).find("select.select2").each(function () {
        try {
          window.jQuery(this).select2({ width: "100%" });
        } catch (error) {}
      });
    }
  }

  function createSection(key) {
    var section = document.createElement("section");
    section.className = "product-react-section product-react-section-" + key;
    section.id = "product-section-" + key;
    section.innerHTML =
      '<div class="product-react-section-head">' +
        '<div><h2>' + labels[key] + '</h2><p>' + labels[key + "Hint"] + '</p></div>' +
      '</div>' +
      '<div class="product-react-section-body"></div>';
    return section;
  }

  function readValue(form, selector) {
    var field = form ? form.querySelector(selector) : null;
    if (!field) {
      return "";
    }
    if (field.tagName === "SELECT") {
      var selected = field.options[field.selectedIndex];
      return selected ? selected.text : "";
    }
    return field.value || "";
  }

  function App(props) {
    var sourceForm = props.sourceForm;
    var isEdit = props.isEdit;
    var formMount = useRef(null);
    var messageMount = useRef(null);
    var title = isEdit ? labels.editTitle : labels.addTitle;
    var action = isEdit ? labels.editAction : labels.addAction;
    var initialSummary = useMemo(function () {
      return collectSummary(sourceForm);
    }, [sourceForm]);
    var summary = useState(initialSummary);
    var values = summary[0];
    var setValues = summary[1];
    var stepState = useState(1);
    var activeStep = stepState[0];
    var setActiveStep = stepState[1];

    useEffect(function () {
      if (!sourceForm || !formMount.current) {
        return;
      }
      if (messageMount.current) {
        var legacyPage = sourceForm.closest(".admin-product-page");
        Array.prototype.forEach.call(legacyPage ? legacyPage.querySelectorAll(".callout") : [], function (callout) {
          cleanTextNodeTree(callout);
          messageMount.current.appendChild(callout);
        });
      }
      formMount.current.appendChild(sourceForm);
      enhanceForm(sourceForm);
      document.body.classList.add("product-react-ready");

      function updateSummary() {
        setValues(collectSummary(sourceForm));
      }
      sourceForm.addEventListener("input", updateSummary);
      sourceForm.addEventListener("change", updateSummary);
      updateSummary();

      return function () {
        sourceForm.removeEventListener("input", updateSummary);
        sourceForm.removeEventListener("change", updateSummary);
      };
    }, [sourceForm, setValues]);

    function submitForm() {
      var submit = sourceForm.querySelector('button[name="form1"], input[name="form1"]');
      if (submit) {
        submit.click();
      } else if (sourceForm.requestSubmit) {
        sourceForm.requestSubmit();
      } else {
        sourceForm.submit();
      }
    }

    return h("div", { className: "product-react-workspace product-react-step-" + activeStep, dir: "rtl" },
      h("div", { className: "product-react-topbar" },
        h("div", null,
          h("span", { className: "product-react-kicker" }, "\u0627\u0644\u0645\u062a\u062c\u0631 \u0648\u0627\u0644\u0645\u0646\u062a\u062c\u0627\u062a"),
          h("h1", null, title),
          h("p", null, labels.subtitle)
        ),
        h("div", { className: "product-react-actions" },
          h("a", { className: "product-react-button product-react-button-light", href: "product.php" },
            h("i", { className: "fa fa-list" }),
            labels.back
          ),
          h("button", { className: "product-react-button product-react-button-primary", type: "button", onClick: submitForm },
            h("i", { className: "fa fa-check" }),
            action
          )
        )
      ),
      h("div", { className: "product-react-stepbar" },
        stepButton(1, activeStep, setActiveStep, labels.stepOne, labels.stepOneHint),
        stepButton(2, activeStep, setActiveStep, labels.stepTwo, labels.stepTwoHint)
      ),
      h("div", { className: "product-react-shell" },
        h("aside", { className: "product-react-summary" },
          h("div", { className: "product-react-summary-card" },
            h("span", { className: "product-react-kicker" }, labels.preview),
            h("h2", null, values.name || labels.empty),
            h("dl", null,
              h("div", null, h("dt", null, labels.price), h("dd", null, values.price || labels.empty)),
              h("div", null, h("dt", null, labels.template), h("dd", null, values.template || labels.empty)),
              h("div", null, h("dt", null, labels.status), h("dd", null, values.active ? labels.active : labels.inactive))
            ),
            h("div", { className: "product-react-progress" },
              h("span", { style: { width: values.progress + "%" } })
            ),
            h("strong", null, values.progress + "% " + labels.complete)
          ),
          h("nav", { className: "product-react-nav" },
            h("button", { type: "button", className: activeStep === 1 ? "is-active" : "", onClick: function () { setActiveStep(1); } },
              h("i", { className: "fa fa-pencil" }), labels.stepOne),
            h("button", { type: "button", className: activeStep === 2 ? "is-active" : "", onClick: function () { setActiveStep(2); } },
              h("i", { className: "fa fa-image" }), labels.stepTwo)
          )
        ),
        h("main", { className: "product-react-main" },
          h("div", { className: "product-react-messages", ref: messageMount }),
          h("div", { className: "product-react-form-mount", ref: formMount }),
          h("div", { className: "product-react-step-actions" },
            activeStep === 2 ? h("button", { type: "button", className: "product-react-button product-react-button-light", onClick: function () { setActiveStep(1); } }, h("i", { className: "fa fa-arrow-right" }), labels.previous) : null,
            activeStep === 1 ? h("button", { type: "button", className: "product-react-button product-react-button-primary", onClick: function () { setActiveStep(2); window.scrollTo({ top: 0, behavior: "smooth" }); } }, labels.next, h("i", { className: "fa fa-arrow-left" })) : null
          )
        )
      )
    );
  }

  function stepButton(step, activeStep, setActiveStep, title, hint) {
    return h("button", { type: "button", className: activeStep === step ? "is-active" : "", onClick: function () { setActiveStep(step); } },
      h("strong", null, step + ". " + title),
      h("span", null, hint)
    );
  }

  function navLink(key, text) {
    return h("a", { href: "#product-section-" + key }, h("i", { className: "fa fa-circle" }), text);
  }

  function collectSummary(form) {
    var name = readValue(form, '[name="p_name"]').trim();
    var price = readValue(form, '[name="p_current_price"]').trim();
    var template = readValue(form, '[name="product_template"]').trim();
    var active = readValue(form, '[name="p_is_active"]').trim();
    var required = [
      name,
      price,
      readValue(form, '[name="p_qty"]').trim(),
      readValue(form, '[name="ecat_id"]').trim()
    ];
    var filled = required.filter(Boolean).length;
    return {
      name: name,
      price: price,
      template: template,
      active: active !== "\u0644\u0627" && active !== "0",
      progress: Math.round((filled / required.length) * 100)
    };
  }

  function injectStyles() {
    if (document.getElementById("product-react-form-styles")) {
      return;
    }
    var style = document.createElement("style");
    style.id = "product-react-form-styles";
    style.textContent = [
      "body.product-react-ready .content-header,body.product-react-ready .admin-product-page{display:none!important}",
      "#product-react-root{direction:rtl}",
      ".product-react-workspace{min-height:calc(100vh - 76px);background:#f4f7fb;color:#0f172a;padding:20px 32px 52px;font-family:Tahoma,Arial,sans-serif}",
      ".product-react-topbar{position:sticky;top:0;z-index:30;display:flex;align-items:center;justify-content:space-between;gap:22px;margin:-20px -32px 16px;padding:14px 32px;background:rgba(244,247,251,.94);backdrop-filter:blur(14px);border-bottom:1px solid #dce6f2;box-shadow:0 12px 28px rgba(15,23,42,.06)}",
      ".product-react-kicker{display:inline-flex;align-items:center;gap:7px;color:#0f918a;font-weight:800;font-size:12px;margin-bottom:7px}",
      ".product-react-topbar h1{margin:0 0 4px;font-size:24px;line-height:1.25;font-weight:900;letter-spacing:0;color:#0b1220}",
      ".product-react-topbar p{margin:0;max-width:760px;color:#5b6b83;line-height:1.6;font-size:13px}",
      ".product-react-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap}",
      ".product-react-button{height:42px;border-radius:10px;padding:0 16px;display:inline-flex;align-items:center;justify-content:center;gap:8px;border:1px solid #d9e3ef;font-weight:900;text-decoration:none;box-shadow:0 10px 24px rgba(15,23,42,.06);cursor:pointer;white-space:nowrap}",
      ".product-react-button-light{background:#fff;color:#1f2a3a}",
      ".product-react-button-primary{background:#0f918a;color:#fff;border-color:#0f918a}",
      ".product-react-stepbar{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-bottom:16px}",
      ".product-react-stepbar button{border:1px solid #b8c7d9;background:#fff;border-radius:14px;padding:14px 16px;text-align:right;box-shadow:0 12px 28px rgba(15,23,42,.06);cursor:pointer}",
      ".product-react-stepbar button strong{display:block;color:#0b1220;font-size:14px;font-weight:950;margin-bottom:4px}",
      ".product-react-stepbar button span{display:block;color:#3f4f65;font-size:12px;font-weight:800;line-height:1.6}",
      ".product-react-stepbar button.is-active{border-color:#087f78;background:#dff8f5;box-shadow:0 14px 30px rgba(15,145,138,.16)}",
      ".product-react-shell{display:grid;grid-template-columns:minmax(250px,300px) minmax(0,1fr);gap:20px;align-items:start}",
      ".product-react-summary{position:sticky;top:96px;display:grid;gap:14px}",
      ".product-react-summary-card{background:#fff;border:1px solid #dce6f2;border-radius:16px;padding:20px;box-shadow:0 18px 42px rgba(15,23,42,.07)}",
      ".product-react-summary-card h2{margin:0 0 16px;font-size:19px;line-height:1.55;font-weight:900;color:#0b1220}",
      ".product-react-summary-card dl{margin:0;display:grid;gap:10px}",
      ".product-react-summary-card dl div{display:flex;align-items:center;justify-content:space-between;gap:12px;border-bottom:1px solid #edf2f7;padding-bottom:10px}",
      ".product-react-summary-card dt{color:#66758a;font-size:12px}.product-react-summary-card dd{margin:0;color:#0f172a;font-weight:900;text-align:left}",
      ".product-react-progress{height:8px;border-radius:999px;background:#e8eef6;overflow:hidden;margin:18px 0 9px}",
      ".product-react-progress span{display:block;height:100%;background:#0f918a;border-radius:inherit}",
      ".product-react-summary-card strong{font-size:12px;color:#526179}",
      ".product-react-nav{background:#fff;border:1px solid #dce6f2;border-radius:14px;padding:8px;display:grid;gap:4px}",
      ".product-react-nav button{height:42px;border:0;background:transparent;border-radius:10px;display:flex;align-items:center;gap:9px;padding:0 12px;color:#39485c;font-weight:800;text-decoration:none;cursor:pointer;text-align:right}",
      ".product-react-nav button:hover,.product-react-nav button.is-active{background:#edf9f8;color:#0f766e}",
      ".product-react-nav i{font-size:8px;color:#0f918a}",
      ".product-react-form-mount{min-width:0}",
      ".product-react-main{min-width:0;display:grid;gap:14px}",
      ".product-react-messages:empty{display:none}",
      ".product-react-messages .callout{margin:0;border:0;border-radius:14px;padding:14px 16px;font-weight:800;box-shadow:0 14px 32px rgba(15,23,42,.06)}",
      ".product-react-messages .callout-danger{background:#fff1f2;color:#991b1b}.product-react-messages .callout-success{background:#ecfdf5;color:#047857}.product-react-messages .callout-warning{background:#fffbeb;color:#92400e}",
      ".product-react-form-mount .admin-product-form{display:block;margin:0;padding:0}",
      ".product-react-sections{display:grid;gap:16px}",
      ".product-react-step-1 .product-react-section-content,.product-react-step-1 .product-react-section-media,.product-react-step-1 .product-react-section-options{display:none}",
      ".product-react-step-2 .product-react-section-basics,.product-react-step-2 .product-react-section-pricing{display:none}",
      ".product-react-section{background:#fff;border:1px solid #c8d6e7;border-radius:16px;box-shadow:0 18px 42px rgba(15,23,42,.07);overflow:hidden;scroll-margin-top:18px}",
      ".product-react-section-head{padding:18px 20px;border-bottom:1px solid #cfdbea;background:#eef4fb}",
      ".product-react-section-head h2{margin:0 0 6px;font-size:18px;line-height:1.35;font-weight:900;color:#0f172a}",
      ".product-react-section-head p{margin:0;color:#40506a;font-size:13px;line-height:1.7;font-weight:700}",
      ".product-react-section-body{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;padding:18px}",
      ".product-react-field{margin:0!important;background:#f7faff;border:1px solid #c7d6e8;border-radius:12px;padding:14px;min-width:0}",
      ".product-react-field-wide,.product-react-media-field,#landing-photos-section,.product-react-color-photos{grid-column:1/-1}",
      ".product-react-label{display:block!important;width:auto!important;text-align:right!important;margin:0 0 8px!important;padding:0!important;color:#101827;font-size:13px;font-weight:950;line-height:1.6}",
      ".product-react-control{display:block!important;width:100%!important;float:none!important;padding:0!important;min-width:0}",
      ".product-react-field .form-control{width:100%!important;height:42px;border:1.5px solid #9fb2c8;border-radius:9px;box-shadow:inset 0 1px 2px rgba(15,23,42,.04);background:#fdfefe;color:#0b1220;font-size:13px;font-weight:800}",
      ".product-react-field .form-control:focus{border-color:#087f78!important;box-shadow:0 0 0 3px rgba(8,127,120,.16),inset 0 1px 2px rgba(15,23,42,.04)!important;background:#fff}",
      ".product-react-field textarea.form-control{height:auto;min-height:112px;line-height:1.8;font-weight:600}",
      ".product-react-field .help-block{color:#43546b;font-size:12px;line-height:1.7;margin:8px 0 0;font-weight:700}",
      ".product-react-field .select2-container{width:100%!important;max-width:100%}",
      ".product-react-field .select2-selection{border:1.5px solid #9fb2c8!important;border-radius:9px!important;min-height:42px!important;background:#fdfefe!important}",
      ".product-react-field-focus{border-color:#b8eee9;background:#f3fffd}",
      ".product-react-field .panel{border:1px solid #b9c9dc;border-radius:12px;box-shadow:0 8px 18px rgba(15,23,42,.04);overflow:hidden;background:#fff}",
      ".product-react-field .panel-heading{background:#e8f0fa!important;border-bottom:1px solid #cbd8e8!important;color:#0b1220;padding:8px 10px!important;font-size:12px}",
      ".product-react-field .panel-body{display:block!important;padding:10px!important}",
      ".product-react-field br{display:none}",
      ".product-react-media-field .admin-thumb-wrap,.product-react-media-field img.admin-thumb{margin-bottom:10px}",
      ".product-react-form-mount .admin-thumb{border:1px solid #dfe7f1;border-radius:10px;background:#fff;object-fit:cover}",
      "#special-offer-fields{display:grid!important;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}",
      "#special-offer-fields>.panel{margin:0!important}",
      "#special-offer-fields>.help-block{grid-column:1/-1;margin:0!important}",
      "#special-offer-fields label{margin:0 0 8px!important;font-size:12px!important}",
      "#special-offer-fields .form-control{margin-bottom:8px!important}",
      "#special-offer-fields textarea.form-control{min-height:58px!important;height:58px!important;resize:vertical}",
      "#special-offer-fields input[type=file].form-control{padding:7px!important;font-size:12px;line-height:1.4}",
      "#special-offer-fields .admin-thumb-wrap{margin:0 0 8px!important}",
      "#special-offer-fields .js-url-preview-box{margin-top:6px!important}",
      "input[name='offer_price_1'],input[name='offer_price_2'],input[name='offer_price_3']{max-width:180px}",
      "#landing-photos-section{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;background:#f7faff;border:1px solid #c7d6e8;border-radius:12px;padding:14px}",
      "#landing-photos-section h4{grid-column:1/-1;margin:0;color:#0f172a;font-size:15px;font-weight:900}",
      "#landing-photos-section .form-group{margin:0!important;background:#fff;border:1px solid #e6eef8;border-radius:12px;padding:12px}",
      ".product-react-color-photos:not(:empty){grid-column:1/-1;background:#fbfdff;border:1px solid #e2eaf4;border-radius:12px;padding:14px}",
      ".product-react-submit-row{display:none!important}",
      ".product-react-submit-row label{display:none!important}.product-react-submit-row .product-react-control{display:flex!important;justify-content:flex-start!important}",
      ".product-react-submit-row .btn-success{height:44px;border-radius:10px;border:0;background:#0f918a!important;padding:0 22px;font-weight:900;box-shadow:0 14px 28px rgba(15,145,138,.22)}",
      ".product-react-form-mount input[type=file].form-control{height:auto;padding:9px;background:#fff}",
      ".product-react-form-mount .btn-danger{border-radius:9px;font-weight:800}",
      ".product-react-step-actions{display:flex;align-items:center;justify-content:flex-start;gap:10px;margin-top:14px}",
      "@media (max-width:1180px){.product-react-shell{grid-template-columns:1fr}.product-react-summary{position:static;grid-template-columns:1fr}.product-react-nav{grid-template-columns:repeat(2,minmax(0,1fr))}.product-react-nav button{justify-content:center}#special-offer-fields{grid-template-columns:1fr}.product-react-field .panel-body{grid-template-columns:1fr}}",
      "@media (max-width:760px){.product-react-workspace{padding:14px 14px 40px}.product-react-topbar{display:grid;margin:-14px -14px 14px;padding:12px 14px}.product-react-actions{width:100%}.product-react-button{flex:1}.product-react-section-body,#landing-photos-section,.product-react-stepbar{grid-template-columns:1fr}.product-react-nav{grid-template-columns:1fr}.product-react-topbar h1{font-size:22px}}"
    ].join("\n");
    document.head.appendChild(style);
  }

  function boot() {
    var sourceForm = document.querySelector(".admin-product-form");
    var contentWrapper = document.querySelector(".content-wrapper") || document.body;
    if (!sourceForm || document.getElementById("product-react-root")) {
      return;
    }
    injectStyles();
    var root = document.createElement("section");
    root.id = "product-react-root";
    contentWrapper.appendChild(root);
    ReactDOM.createRoot(root).render(h(App, {
      sourceForm: sourceForm,
      isEdit: page === "product-edit.php"
    }));
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
