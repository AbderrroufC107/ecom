(function () {
    'use strict';

    var rootSelector = '#site-page-content';
    var allowedFiles = {
        '': true,
        'index.php': true,
        'product-category.php': true,
        'search-result.php': true,
        'about.php': true,
        'faq.php': true,
        'contact.php': true
    };
    var blockedFragments = [
        '/admin/',
        '/api/',
        '/staff/',
        '/super-admin/',
        '/assets/',
        '/customer-next',
        'logout.php',
        'login.php',
        'registration.php',
        'forget-password.php',
        'reset-password.php',
        'buy-now.php',
        'landing_page.php',
        'landing_page_2.php',
        'payment-success.php',
        'order-confirmation.php',
        'dashboard.php',
        'customer-order.php',
        'edit-profile.php'
    ];
    var cache = new Map();
    var activeController = null;

    function fileName(pathname) {
        var parts = pathname.split('/');
        return parts[parts.length - 1] || '';
    }

    function isAllowedUrl(url) {
        if (url.origin !== window.location.origin) {
            return false;
        }
        var href = url.href;
        for (var i = 0; i < blockedFragments.length; i += 1) {
            if (href.indexOf(blockedFragments[i]) !== -1) {
                return false;
            }
        }
        return !!allowedFiles[fileName(url.pathname)];
    }

    function isSamePageHash(url) {
        return url.pathname === window.location.pathname &&
            url.search === window.location.search &&
            url.hash !== '';
    }

    function shouldHandleLink(anchor, event) {
        if (!anchor || event.defaultPrevented || event.button !== 0) {
            return false;
        }
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return false;
        }
        if (anchor.target && anchor.target !== '_self') {
            return false;
        }
        if (anchor.hasAttribute('download') || anchor.hasAttribute('data-no-pjax')) {
            return false;
        }
        var rawHref = anchor.getAttribute('href');
        if (!rawHref || rawHref === '#' || rawHref.indexOf('javascript:') === 0 || rawHref.indexOf('mailto:') === 0 || rawHref.indexOf('tel:') === 0) {
            return false;
        }
        var url = new URL(rawHref, window.location.href);
        if (isSamePageHash(url)) {
            return false;
        }
        return isAllowedUrl(url);
    }

    function parseHtml(html) {
        return new DOMParser().parseFromString(html, 'text/html');
    }

    function ensureStyles(doc) {
        var current = {};
        document.querySelectorAll('link[rel="stylesheet"]').forEach(function (link) {
            current[link.href] = true;
        });
        doc.querySelectorAll('link[rel="stylesheet"]').forEach(function (link) {
            var absolute = new URL(link.getAttribute('href'), window.location.href).href;
            if (!current[absolute]) {
                var clone = document.createElement('link');
                clone.rel = 'stylesheet';
                clone.href = absolute;
                document.head.appendChild(clone);
                current[absolute] = true;
            }
        });
    }

    function updateHead(doc) {
        if (doc.title) {
            document.title = doc.title;
        }
        ensureStyles(doc);
        if (doc.body) {
            document.body.className = doc.body.className || '';
        }
    }

    function executeScripts(container) {
        var scripts = Array.prototype.slice.call(container.querySelectorAll('script'));
        scripts.forEach(function (oldScript) {
            var script = document.createElement('script');
            Array.prototype.slice.call(oldScript.attributes).forEach(function (attr) {
                script.setAttribute(attr.name, attr.value);
            });
            if (!oldScript.src) {
                script.text = oldScript.textContent || '';
            }
            oldScript.parentNode.replaceChild(script, oldScript);
        });
    }

    function emitPageReady() {
        document.dispatchEvent(new CustomEvent('site:page-ready', {
            detail: { url: window.location.href }
        }));
        if (window.__metaPixelEnabled && typeof window.fbq === 'function') {
            window.fbq('track', 'PageView');
        }
        if (window.__tiktokPixelEnabled && window.ttq && typeof window.ttq.page === 'function') {
            window.ttq.page();
        }
    }

    function swapPage(doc, url, options) {
        var nextRoot = doc.querySelector(rootSelector);
        var currentRoot = document.querySelector(rootSelector);
        if (!nextRoot || !currentRoot) {
            window.location.href = url.href;
            return;
        }
        updateHead(doc);
        currentRoot.innerHTML = nextRoot.innerHTML;
        executeScripts(currentRoot);
        if (!options || !options.preserveScroll) {
            window.scrollTo({ top: 0, left: 0, behavior: 'auto' });
        }
        emitPageReady();
    }

    function loadPage(url, options) {
        var key = url.href;
        document.documentElement.classList.add('pjax-loading');
        if (activeController) {
            activeController.abort();
        }
        activeController = new AbortController();

        var cached = cache.get(key);
        if (cached) {
            swapPage(parseHtml(cached), url, options);
            document.documentElement.classList.remove('pjax-loading');
            return Promise.resolve();
        }

        return fetch(url.href, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-PJAX': 'true'
            },
            signal: activeController.signal
        }).then(function (response) {
            var contentType = response.headers.get('content-type') || '';
            if (!response.ok || contentType.indexOf('text/html') === -1) {
                throw new Error('Navigation response was not HTML.');
            }
            return response.text();
        }).then(function (html) {
            cache.set(key, html);
            swapPage(parseHtml(html), url, options);
        }).catch(function (error) {
            if (error && error.name === 'AbortError') {
                return;
            }
            window.location.href = url.href;
        }).finally(function () {
            document.documentElement.classList.remove('pjax-loading');
        });
    }

    document.addEventListener('click', function (event) {
        var anchor = event.target.closest ? event.target.closest('a[href]') : null;
        if (!shouldHandleLink(anchor, event)) {
            return;
        }
        var url = new URL(anchor.getAttribute('href'), window.location.href);
        event.preventDefault();
        history.pushState({ pjax: true }, '', url.href);
        loadPage(url);
    });

    window.addEventListener('popstate', function () {
        var url = new URL(window.location.href);
        if (!isAllowedUrl(url)) {
            window.location.reload();
            return;
        }
        loadPage(url, { preserveScroll: true });
    });

    if (document.querySelector(rootSelector)) {
        history.replaceState({ pjax: true }, '', window.location.href);
    }
})();
