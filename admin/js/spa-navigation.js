/**
 * SPA Navigation for Admin Panel
 * Prevents full page reloads, keeps sidebar/header fixed
 * Only replaces the content area
 */
(function() {
    'use strict';

    var SPA = {
        enabled: true,
        loading: false,
        currentUrl: window.location.href,
        contentSelector: '.content-wrapper',
        sidebarSelector: '.main-sidebar',
        headerSelector: '.main-header',
        footerSelector: '.main-footer',

        init: function() {
            if (!this.enabled) return;

            this.bindLinks();
            this.bindPopState();
            this.highlightCurrentMenu();
        },

        bindLinks: function() {
            var self = this;

            // Intercept sidebar menu clicks
            document.addEventListener('click', function(e) {
                var link = self.findSPAlink(e.target);
                if (!link) return;

                // Don't intercept: external links, modals, forms, right-click
                if (e.button !== 0) return;
                if (e.ctrlKey || e.metaKey || e.shiftKey) return;
                if (link.target === '_blank') return;
                if (link.hasAttribute('data-spa-disable')) return;
                if (link.getAttribute('data-toggle')) return; // Bootstrap dropdown/modal
                if (link.closest('.modal')) return;
                if (link.closest('.dropdown-menu')) return;

                var href = link.getAttribute('href');
                if (!href || href === '#' || href === 'javascript:void(0)') return;

                // Don't intercept treeview parent links (they toggle submenus)
                if (link.closest('.treeview') && link.querySelector('.pull-right-container')) return;

                // Only intercept same-origin admin pages
                var url = new URL(href, window.location.origin);
                if (url.origin !== window.location.origin) return;

                // Skip non-PHP pages, login, logout, etc.
                var path = url.pathname;
                if (!path.match(/\.php$/)) return;
                if (path.indexOf('/admin/') === -1 && path.indexOf('admin/') === -1) return;
                if (path.indexOf('login.php') !== -1 || path.indexOf('logout.php') !== -1) return;
                if (path.indexOf('react-src') !== -1) return;

                e.preventDefault();
                self.navigate(url.href);
            });

            // Handle popstate (back/forward buttons)
            window.addEventListener('popstate', function(e) {
                if (e.state && e.state.spa) {
                    self.loadPage(window.location.href, false);
                }
            });
        },

        findSPAlink: function(el) {
            while (el && el !== document) {
                if (el.tagName === 'A') return el;
                el = el.parentElement;
            }
            return null;
        },

        navigate: function(url, pushState) {
            if (this.loading) return;
            if (url === this.currentUrl) return;

            pushState = pushState !== false;
            this.loadPage(url, pushState);
        },

        loadPage: function(url, pushState) {
            var self = this;
            this.loading = true;
            this.showLoading();

            fetch(url, {
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'text/html'
                }
            })
            .then(function(response) {
                if (!response.ok) throw new Error('HTTP ' + response.status);
                return response.text();
            })
            .then(function(html) {
                self.applyPage(html, url, pushState);
                self.loading = false;
                self.hideLoading();
            })
            .catch(function(err) {
                console.log('[SPA] Load error:', err);
                // Fallback to normal navigation
                window.location.href = url;
            });
        },

        applyPage: function(html, url, pushState) {
            var parser = new DOMParser();
            var doc = parser.parseFromString(html, 'text/html');

            // Extract content
            var newContent = doc.querySelector(this.contentSelector);
            var oldContent = document.querySelector(this.contentSelector);

            if (!newContent || !oldContent) {
                window.location.href = url;
                return;
            }

            // Update title
            var newTitle = doc.querySelector('title');
            if (newTitle) {
                document.title = newTitle.textContent;
            }

            // Replace content
            oldContent.innerHTML = newContent.innerHTML;

            // Update sidebar active state
            this.updateSidebar(doc);

            // Update URL
            if (pushState) {
                history.pushState({ spa: true }, '', url);
            }

            this.currentUrl = url;

            // Re-initialize page-specific JS
            this.reinitPage();
        },

        updateSidebar: function(doc) {
            var newSidebar = doc.querySelector(this.sidebarSelector);
            var oldSidebar = document.querySelector(this.sidebarSelector);

            if (newSidebar && oldSidebar) {
                // Update active menu items
                var newActive = newSidebar.querySelectorAll('.treeview.active, .active');
                var oldActive = oldSidebar.querySelectorAll('.treeview.active, .active');

                // Remove old active
                for (var i = 0; i < oldActive.length; i++) {
                    oldActive[i].classList.remove('active');
                }

                // Add new active
                for (var i = 0; i < newActive.length; i++) {
                    var item = newActive[i];
                    var matching = this.findMatchingSidebarItem(oldSidebar, item);
                    if (matching) {
                        matching.classList.add('active');
                        // Open parent treeview
                        var parent = matching.closest('.treeview');
                        if (parent) parent.classList.add('active');
                    }
                }
            }
        },

        findMatchingSidebarItem: function(oldSidebar, newItem) {
            var newLink = newItem.querySelector('a');
            if (!newLink) return null;

            var newHref = newLink.getAttribute('href');
            var oldLinks = oldSidebar.querySelectorAll('a');

            for (var i = 0; i < oldLinks.length; i++) {
                if (oldLinks[i].getAttribute('href') === newHref) {
                    return oldLinks[i].closest('li');
                }
            }
            return null;
        },

        reinitPage: function() {
            var self = this;

            // Re-initialize summernote editors
            if (typeof $ !== 'undefined' && $.fn.summernote) {
                $('.editor1, .editor2, .editor3, .editor4, .editor5').summernote({ height: 300 });
            }

            // Re-initialize select2
            if (typeof $ !== 'undefined' && $.fn.select2) {
                $('.select2').select2();
            }

            // Re-initialize dataTables
            if (typeof $ !== 'undefined' && $.fn.dataTable) {
                $('.dataTable').DataTable();
            }

            // Re-initialize inputmask
            if (typeof $ !== 'undefined' && $.fn.inputmask) {
                $('[data-mask]').inputmask();
            }

            // Re-initialize datepicker
            if (typeof $ !== 'undefined' && $.fn.datepicker) {
                $('.datepicker').datepicker();
            }

            // Re-initialize icheck
            if (typeof $ !== 'undefined' && $.fn.iCheck) {
                $('.icheck').iCheck({ checkboxClass: 'icheckbox_minimal-blue', radioClass: 'iradio_minimal-blue' });
            }

            // Trigger custom event for React admin
            document.dispatchEvent(new CustomEvent('spa:pageLoaded'));

            // Scroll to top
            window.scrollTo(0, 0);

            // Re-run any inline scripts
            var scripts = document.querySelectorAll('.content-wrapper script');
            for (var i = 0; i < scripts.length; i++) {
                try {
                    eval(scripts[i].textContent);
                } catch(e) {}
            }
        },

        showLoading: function() {
            var overlay = document.getElementById('spa-loading-overlay');
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.id = 'spa-loading-overlay';
                overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(236,240,245,0.7);z-index:9999;display:flex;align-items:center;justify-content:center;transition:opacity 0.2s;';
                overlay.innerHTML = '<div style="text-align:center"><div style="width:40px;height:40px;border:4px solid #3c8dbc;border-top-color:transparent;border-radius:50%;animation:spin 0.8s linear infinite;margin:0 auto 10px"></div><div style="color:#666;font-size:14px">جاري التحميل...</div></div>';
                var style = document.createElement('style');
                style.textContent = '@keyframes spin{to{transform:rotate(360deg)}}';
                document.head.appendChild(style);
                document.body.appendChild(overlay);
            }
            overlay.style.display = 'flex';
            overlay.style.opacity = '1';
        },

        hideLoading: function() {
            var overlay = document.getElementById('spa-loading-overlay');
            if (overlay) {
                overlay.style.opacity = '0';
                setTimeout(function() { overlay.style.display = 'none'; }, 200);
            }
        },

        highlightCurrentMenu: function() {
            // Already handled by PHP, but we can enhance
        }
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() { SPA.init(); });
    } else {
        SPA.init();
    }

    // Expose globally
    window.AdminSPA = SPA;
})();
