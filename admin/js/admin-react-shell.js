(function () {
  "use strict";

  if (window.self !== window.top || !window.React || !window.ReactDOM) {
    return;
  }

  var root = document.getElementById("admin-react-shell");
  var legacySidebar = document.querySelector(".main-sidebar .sidebar-menu");
  var contentWrapper = document.querySelector(".content-wrapper");

  if (!root || !legacySidebar || !contentWrapper) {
    return;
  }

  var React = window.React;
  var h = React.createElement;
  var currentPath = window.location.pathname.split("/").pop() || "index.php";

  var i18n = {
    brand: "\u0645\u062a\u062c\u0631 \u0627\u0644\u062b\u0642\u0629",
    admin: "\u0644\u0648\u062d\u0629 \u0627\u0644\u0645\u062f\u064a\u0631",
    search: "\u0627\u0628\u062d\u062b \u0639\u0646 \u0635\u0641\u062d\u0629 \u0623\u0648 \u0625\u062c\u0631\u0627\u0621",
    quick: "\u0625\u062c\u0631\u0627\u0621\u0627\u062a \u0633\u0631\u064a\u0639\u0629",
    viewStore: "\u0639\u0631\u0636 \u0627\u0644\u0645\u062a\u062c\u0631",
    profile: "\u0627\u0644\u0645\u0644\u0641 \u0627\u0644\u0634\u062e\u0635\u064a",
    logout: "\u062e\u0631\u0648\u062c",
    online: "\u0646\u0634\u0637",
    noResults: "\u0644\u0627 \u062a\u0648\u062c\u062f \u0646\u062a\u0627\u0626\u062c",
    collapse: "\u062a\u0635\u063a\u064a\u0631 \u0627\u0644\u0642\u0627\u0626\u0645\u0629",
    menu: "\u0641\u062a\u062d \u0627\u0644\u0642\u0627\u0626\u0645\u0629",
  };

  var sectionLabels = {
    overview: "\u0646\u0638\u0631\u0629 \u0639\u0627\u0645\u0629",
    sales: "\u0627\u0644\u0645\u0628\u064a\u0639\u0627\u062a",
    catalog: "\u0627\u0644\u0645\u062a\u062c\u0631 \u0648\u0627\u0644\u0645\u0646\u062a\u062c\u0627\u062a",
    content: "\u0627\u0644\u0645\u062d\u062a\u0648\u0649",
    customers: "\u0627\u0644\u0639\u0645\u0644\u0627\u0621",
    system: "\u0627\u0644\u0646\u0638\u0627\u0645",
  };

  var sectionIcons = {
    overview: "fa fa-dashboard",
    sales: "fa fa-credit-card",
    catalog: "fa fa-shopping-bag",
    content: "fa fa-file-text-o",
    customers: "fa fa-users",
    system: "fa fa-sliders",
  };

  var pageNames = {
    "index.php": "\u0644\u0648\u062d\u0629 \u0627\u0644\u0625\u062f\u0627\u0631\u0629",
    "store.php": "\u0627\u0644\u0645\u062a\u062c\u0631",
    "settings.php": "\u0625\u0639\u062f\u0627\u062f\u0627\u062a \u0627\u0644\u0645\u0648\u0642\u0639",
    "site-security.php": "\u0623\u0645\u0627\u0646 \u0627\u0644\u0645\u0648\u0642\u0639",
    "product.php": "\u0627\u0644\u0645\u0646\u062a\u062c\u0627\u062a",
    "product-add.php": "\u0625\u0636\u0627\u0641\u0629 \u0645\u0646\u062a\u062c",
    "product-edit.php": "\u062a\u0639\u062f\u064a\u0644 \u0645\u0646\u062a\u062c",
    "order.php": "\u0627\u0644\u0637\u0644\u0628\u0627\u062a",
    "order-statistics.php": "\u0625\u062d\u0635\u0627\u0626\u064a\u0627\u062a \u0627\u0644\u0637\u0644\u0628\u0627\u062a",
    "exchange-requests.php": "\u0637\u0644\u0628\u0627\u062a \u0627\u0644\u062a\u0628\u062f\u064a\u0644",
    "incomplete-orders.php": "\u0627\u0644\u0637\u0644\u0628\u0627\u062a \u063a\u064a\u0631 \u0627\u0644\u0645\u0643\u062a\u0645\u0644\u0629",
    "customer.php": "\u0627\u0644\u0639\u0645\u0644\u0627\u0621",
    "delivery_list.php": "\u0634\u0631\u0643\u0627\u062a \u0627\u0644\u062a\u0648\u0635\u064a\u0644",
    "users.php": "\u0627\u0644\u0645\u0633\u062a\u062e\u062f\u0645\u0648\u0646",
    "slider.php": "\u0625\u062f\u0627\u0631\u0629 \u0627\u0644\u0633\u0644\u0627\u064a\u062f\u0631",
    "service.php": "\u0627\u0644\u062e\u062f\u0645\u0627\u062a",
    "faq.php": "FAQ",
    "page.php": "\u0625\u0639\u062f\u0627\u062f\u0627\u062a \u0627\u0644\u0635\u0641\u062d\u0627\u062a",
    "photo.php": "\u0627\u0644\u0645\u0639\u0631\u0636",
    "social-media.php": "\u0648\u0633\u0627\u0626\u0644 \u0627\u0644\u062a\u0648\u0627\u0635\u0644",
    "size.php": "\u0627\u0644\u0645\u0642\u0627\u0633\u0627\u062a",
    "color.php": "\u0627\u0644\u0623\u0644\u0648\u0627\u0646",
    "country.php": "\u0627\u0644\u0648\u0644\u0627\u064a\u0627\u062a / \u0627\u0644\u062f\u0648\u0644",
    "shipping-cost.php": "\u062a\u0643\u0627\u0644\u064a\u0641 \u0627\u0644\u062a\u0648\u0635\u064a\u0644",
    "top-category.php": "\u0627\u0644\u0641\u0626\u0627\u062a \u0627\u0644\u0631\u0626\u064a\u0633\u064a\u0629",
    "mid-category.php": "\u0627\u0644\u0641\u0626\u0627\u062a \u0627\u0644\u0641\u0631\u0639\u064a\u0629",
    "end-category.php": "\u0627\u0644\u0641\u0626\u0627\u062a \u0627\u0644\u0646\u0647\u0627\u0626\u064a\u0629",
  };

  function cleanText(text) {
    return (text || "").replace(/\s+/g, " ").trim();
  }

  function decodeMojibake(text) {
    text = String(text || "");
    if (!/[ØÙÃÂ]/.test(text) || !window.TextDecoder) {
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

    function arabicScore(value) {
      var matches = String(value || "").match(/[\u0600-\u06ff]/g);
      return matches ? matches.length : 0;
    }

    try {
      var whole = text;
      var best = text;
      for (var wholePass = 0; wholePass < 5; wholePass += 1) {
        if (!/[Ã˜Ã™ÃƒÃ‚Â]/.test(whole)) {
          break;
        }

        var wholeBytes = new Uint8Array(whole.length);
        for (var wholeIndex = 0; wholeIndex < whole.length; wholeIndex += 1) {
          wholeBytes[wholeIndex] = cp1252Byte(whole.charAt(wholeIndex));
        }

        var wholeNext = new TextDecoder("utf-8").decode(wholeBytes);
        if (!wholeNext || wholeNext === whole) {
          break;
        }

        whole = wholeNext;
        if (arabicScore(whole) >= arabicScore(best)) {
          best = whole;
        }
      }
      if (best !== text) {
        return best;
      }
    } catch (error) {}

    return text.replace(/[À-ÿ][\u0080-\u00ff\s\-:؛،,.!?()\/0-9A-Za-z#%+]*[À-ÿ]/g, function (part) {
      try {
        var current = part;
        for (var pass = 0; pass < 3; pass += 1) {
          if (!/[ØÙÃÂ]/.test(current)) {
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
        return current || part;
      } catch (error) {
        return part;
      }
    });
  }

  function normalizedHref(href) {
    if (!href || href === "#") {
      return "#";
    }

    var parts = href.split("/");
    return parts[parts.length - 1].split("?")[0] || href;
  }

  function getIconClass(link) {
    var icon = link ? link.querySelector("i.fa") : null;
    return icon ? icon.className : "fa fa-circle-o";
  }

  function readableTitle(rawTitle, href) {
    var file = normalizedHref(href);
    return pageNames[file] || rawTitle || file || i18n.admin;
  }

  function readableParentTitle(rawTitle, href, children) {
    var childFiles = children.map(function (child) {
      return normalizedHref(child.getAttribute("href") || "#");
    });

    if (normalizedHref(href) === "#" && childFiles.some(function (file) {
      return ["size.php", "color.php", "country.php", "shipping-cost.php"].indexOf(file) > -1;
    })) {
      return "\u0625\u0639\u062f\u0627\u062f\u0627\u062a \u0627\u0644\u0645\u062a\u062c\u0631";
    }

    return readableTitle(rawTitle, href);
  }

  function getSection(href) {
    var file = normalizedHref(href);

    if (file === "index.php" || file === "store.php") return "overview";
    if (file.indexOf("order") === 0 || file.indexOf("incomplete") === 0 || file.indexOf("ecotrack") === 0 || file.indexOf("exchange") === 0) return "sales";
    if (
      file.indexOf("product") === 0 ||
      file.indexOf("size") === 0 ||
      file.indexOf("color") === 0 ||
      file.indexOf("country") === 0 ||
      file.indexOf("shipping") === 0 ||
      file.indexOf("delivery") === 0 ||
      file.indexOf("top-category") === 0 ||
      file.indexOf("mid-category") === 0 ||
      file.indexOf("end-category") === 0
    ) return "catalog";
    if (
      file.indexOf("slider") === 0 ||
      file.indexOf("service") === 0 ||
      file.indexOf("faq") === 0 ||
      file.indexOf("page") === 0 ||
      file.indexOf("photo") === 0 ||
      file.indexOf("social") === 0
    ) return "content";
    if (file.indexOf("customer") === 0) return "customers";
    return "system";
  }

  function readMenu() {
    return Array.prototype.slice.call(legacySidebar.children)
      .filter(function (item) {
        return item.tagName && item.tagName.toLowerCase() === "li";
      })
      .map(function (item, index) {
        var link = item.querySelector(":scope > a");
        var titleNode = link ? link.querySelector("span:not(.pull-right-container)") : null;
        var children = Array.prototype.slice.call(item.querySelectorAll(":scope > .treeview-menu > li > a"));
        var href = link ? link.getAttribute("href") || "#" : "#";
        var file = normalizedHref(href);

        return {
          id: "admin-nav-" + index,
          href: href,
          file: file,
          title: decodeMojibake(readableParentTitle(cleanText(titleNode ? titleNode.textContent : link ? link.textContent : ""), href, children)),
          icon: getIconClass(link),
          section: getSection(href),
          active: item.classList.contains("active") || file === currentPath,
          children: children.map(function (child, childIndex) {
            var childHref = child.getAttribute("href") || "#";
            var childFile = normalizedHref(childHref);

            return {
              id: "admin-nav-" + index + "-" + childIndex,
              href: childHref,
              file: childFile,
              title: decodeMojibake(readableTitle(cleanText(child.textContent), childHref)),
              icon: getIconClass(child),
              section: getSection(childHref),
              active: childFile === currentPath,
            };
          }),
        };
      })
      .filter(function (item) {
        return item && item.title;
      });
  }

  function flattenMenu(menu) {
    var items = [];
    menu.forEach(function (item) {
      if (item.href && item.href !== "#") {
        items.push(item);
      }
      item.children.forEach(function (child) {
        if (child.href && child.href !== "#") {
          items.push(child);
        }
      });
    });
    return items;
  }

  function groupMenu(menu) {
    var groups = {};

    menu.forEach(function (item) {
      var groupKey = item.section;
      if (!groups[groupKey]) {
        groups[groupKey] = [];
      }
      groups[groupKey].push(item);
    });

    return ["overview", "sales", "catalog", "content", "customers", "system"]
      .filter(function (key) { return groups[key] && groups[key].length; })
      .map(function (key) {
        return { id: key, label: sectionLabels[key], icon: sectionIcons[key], items: groups[key] };
      });
  }

  function getPageTitle() {
    var selectors = [".content-header h1", ".admin-dashboard-header h1", ".box-title", "h1"];

    for (var i = 0; i < selectors.length; i += 1) {
      var node = contentWrapper.querySelector(selectors[i]);
      var text = cleanText(node ? node.textContent : "");
      if (text) {
        return decodeMojibake(text);
      }
    }

    return pageNames[currentPath] || i18n.admin;
  }

  function QuickAction(props) {
    return h(
      "a",
      { className: "react-admin-quick-action", href: props.href },
      h("i", { className: props.icon, "aria-hidden": "true" }),
      h("span", null, props.label)
    );
  }

  function NavItem(props) {
    var item = props.item;
    var open = item.active || item.children.some(function (child) { return child.active; });

    return h(
      "li",
      { className: "react-admin-nav-item" + (open ? " is-active" : "") },
      h(
        "a",
        {
          className: "react-admin-nav-link",
          href: item.href,
          title: item.title,
          onClick: function (event) {
            if (item.href === "#" && item.children.length) {
              event.preventDefault();
            }
          },
        },
        h("i", { className: item.icon, "aria-hidden": "true" }),
        h("span", null, item.title),
        item.children.length ? h("i", { className: "fa fa-angle-down react-admin-nav-caret", "aria-hidden": "true" }) : null
      ),
      item.children.length
        ? h(
            "div",
            { className: "react-admin-subnav" },
            item.children.map(function (child) {
              return h(
                "a",
                {
                  key: child.id,
                  href: child.href,
                  title: child.title,
                  className: "react-admin-subnav-link" + (child.active ? " is-active" : ""),
                },
                h("i", { className: child.icon, "aria-hidden": "true" }),
                h("span", null, child.title)
              );
            })
          )
        : null
    );
  }

  function SearchBox(props) {
    var query = props.query;
    var setQuery = props.setQuery;
    var results = props.results;
    var showResults = query.length > 0;

    return h(
      "div",
      { className: "react-admin-search" },
      h("i", { className: "fa fa-search", "aria-hidden": "true" }),
      h("input", {
        type: "search",
        value: query,
        placeholder: i18n.search,
        onChange: function (event) { setQuery(event.target.value); },
      }),
      showResults
        ? h(
            "div",
            { className: "react-admin-search-panel" },
            results.length
              ? results.slice(0, 8).map(function (item) {
                  return h(
                    "a",
                    { key: item.id, href: item.href, className: "react-admin-search-result" },
                    h("i", { className: item.icon, "aria-hidden": "true" }),
                    h(
                      "span",
                      null,
                      h("strong", null, item.title),
                      h("small", null, sectionLabels[item.section] || i18n.admin)
                    )
                  );
                })
              : h("div", { className: "react-admin-search-empty" }, i18n.noResults)
          )
        : null
    );
  }

  function App() {
    var menu = React.useMemo(readMenu, []);
    var groupedMenu = React.useMemo(function () { return groupMenu(menu); }, [menu]);
    var flatMenu = React.useMemo(function () { return flattenMenu(menu); }, [menu]);
    var adminName = root.getAttribute("data-admin-name") || "Admin";
    var initialCollapsed = window.localStorage.getItem("adminReactCollapsed") === "1";
    var collapsedState = React.useState(initialCollapsed);
    var collapsed = collapsedState[0];
    var setCollapsed = collapsedState[1];
    var mobileState = React.useState(false);
    var mobileOpen = mobileState[0];
    var setMobileOpen = mobileState[1];
    var searchState = React.useState("");
    var query = searchState[0];
    var setQuery = searchState[1];
    var pageTitle = getPageTitle();
    var activeItem = flatMenu.filter(function (item) { return item.file === currentPath; })[0];
    var activeSection = activeItem ? sectionLabels[activeItem.section] : i18n.admin;
    var filteredResults = flatMenu.filter(function (item) {
      var value = (item.title + " " + item.file + " " + (sectionLabels[item.section] || "")).toLowerCase();
      return value.indexOf(query.toLowerCase()) > -1;
    });

    React.useEffect(function () {
      document.body.classList.remove("admin-react-pending");
      document.body.classList.add("admin-react-ready");
      document.body.classList.toggle("admin-react-collapsed", collapsed);
      window.localStorage.setItem("adminReactCollapsed", collapsed ? "1" : "0");
    }, [collapsed]);

    React.useEffect(function () {
      document.body.classList.toggle("admin-react-mobile-open", mobileOpen);
    }, [mobileOpen]);

    return h(
      "div",
      { className: "react-admin-shell" },
      h(
        "aside",
        { className: "react-admin-sidebar", "aria-label": "Admin navigation" },
        h(
          "a",
          { className: "react-admin-brand", href: "index.php" },
          h("span", { className: "react-admin-brand-mark" }, "MT"),
          h(
            "span",
            { className: "react-admin-brand-copy" },
            h("strong", null, i18n.brand),
            h("small", null, i18n.admin)
          )
        ),
        h(
          "div",
          { className: "react-admin-side-status" },
          h("span", { className: "react-admin-pulse" }),
          h("span", null, i18n.online)
        ),
        h(
          "nav",
          { className: "react-admin-nav-scroll" },
          groupedMenu.map(function (group) {
            return h(
              "section",
              { className: "react-admin-nav-group", key: group.id },
              h(
                "div",
                { className: "react-admin-nav-heading" },
                h("i", { className: group.icon, "aria-hidden": "true" }),
                h("span", null, group.label)
              ),
              h(
                "ul",
                { className: "react-admin-nav" },
                group.items.map(function (item) {
                  return h(NavItem, { key: item.id, item: item });
                })
              )
            );
          })
        )
      ),
      h("div", {
        className: "react-admin-backdrop",
        onClick: function () { setMobileOpen(false); },
      }),
      h(
        "header",
        { className: "react-admin-topbar" },
        h(
          "div",
          { className: "react-admin-topbar-left" },
          h(
            "button",
            {
              className: "react-admin-icon-button react-admin-mobile-toggle",
              type: "button",
              "aria-label": i18n.menu,
              onClick: function () { setMobileOpen(!mobileOpen); },
            },
            h("i", { className: "fa fa-bars", "aria-hidden": "true" })
          ),
          h(
            "button",
            {
              className: "react-admin-icon-button react-admin-collapse-toggle",
              type: "button",
              "aria-label": i18n.collapse,
              onClick: function () { setCollapsed(!collapsed); },
            },
            h("i", { className: collapsed ? "fa fa-indent" : "fa fa-outdent", "aria-hidden": "true" })
          ),
          h(
            "div",
            { className: "react-admin-page-title" },
            h("span", null, activeSection),
            h("strong", null, pageTitle)
          )
        ),
        h(SearchBox, { query: query, setQuery: setQuery, results: filteredResults }),
        h(
          "div",
          { className: "react-admin-actions" },
          h(
            "a",
            { className: "react-admin-action", href: "../index.php", target: "_blank", rel: "noopener" },
            h("i", { className: "fa fa-external-link", "aria-hidden": "true" }),
            h("span", null, i18n.viewStore)
          ),
          h(
            "a",
            { className: "react-admin-user", href: "profile-edit.php", title: i18n.profile },
            h("span", null, adminName),
            h("i", { className: "fa fa-user-circle-o", "aria-hidden": "true" })
          ),
          h(
            "a",
            { className: "react-admin-logout", href: "logout.php", "aria-label": i18n.logout },
            h("i", { className: "fa fa-sign-out", "aria-hidden": "true" })
          )
        )
      ),
      h(
        "div",
        { className: "react-admin-quickbar" },
        h("span", null, i18n.quick),
        h(QuickAction, { href: "order.php", icon: "fa fa-sticky-note", label: "\u0627\u0644\u0637\u0644\u0628\u0627\u062a" }),
        h(QuickAction, { href: "product-add.php", icon: "fa fa-plus", label: "\u0645\u0646\u062a\u062c \u062c\u062f\u064a\u062f" }),
        h(QuickAction, { href: "incomplete-orders.php", icon: "fa fa-exclamation-circle", label: "\u063a\u064a\u0631 \u0645\u0643\u062a\u0645\u0644\u0629" }),
        h(QuickAction, { href: "delivery_list.php", icon: "fa fa-truck", label: "\u0627\u0644\u062a\u0648\u0635\u064a\u0644" })
      )
    );
  }

  window.ReactDOM.createRoot(root).render(h(App));
})();
