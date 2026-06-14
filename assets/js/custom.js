(function ($) {
    "use strict";

    // التحقق من وجود jQuery
    if (typeof jQuery === 'undefined') {
        console.error('jQuery غير محمل. لا يمكن تنفيذ custom.js');
        return;
    }

    // متغيرات عامة
    var mainWindow = $(window),
        mainDocument = $(document),
        youtubeThumb = $('.youtube-thumbnail'),
        productCarousel = $('.product-carousel'),
        testimonialCarousel = $('.testimonial-carousel'),
        mainSlider = $('.main-slider'),
        prodSlider = $('.prod-slider'),
        scrollUp = $('.scrollup'),
        popup = $('.popup'),
        ratingSection = $('.rating-section'),
        bootstrapTouchSlider = $('#bootstrap-touch-slider');

    // دالة للتحقق من تحميل المكتبات
    function isLibraryLoaded(libraryName) {
        switch(libraryName) {
            case 'swiper':
                return typeof Swiper === 'function';
            case 'owlCarousel':
                return typeof $.fn.owlCarousel === 'function';
            case 'magnificPopup':
                return typeof $.fn.magnificPopup === 'function';
            case 'rating':
                return typeof $.fn.rating === 'function';
            case 'select2':
                return typeof $.fn.select2 === 'function';
            case 'bsTouchSlider':
                return typeof $.fn.bsTouchSlider === 'function';
            default:
                return false;
        }
    }

    // دالة لتهيئة Swiper
    function initSwiper(element, options) {
        if (!element || !element.length) {
            return; // إرجاع صامت بدون رسالة
        }

        if (!isLibraryLoaded('swiper')) {
            console.warn('مكتبة Swiper غير محملة');
            return;
        }

        try {
            new Swiper(element[0], options);
        } catch (e) {
            console.warn('خطأ في تهيئة Swiper:', e);
        }
    }

    // عند تحميل الصفحة بالكامل
    mainWindow.on("load", function () {
        // إخفاء شاشة التحميل
        $('#status').fadeOut();
        $('#preloader').delay(350).fadeOut('slow');
        $('body').delay(350).css({ 'overflow': 'visible' });

        // تهيئة السلايدر الرئيسي
        initSwiper(mainSlider, {
            effect: 'fade',
            loop: true,
            autoplay: {
                delay: 5000,
                disableOnInteraction: false,
            },
            pagination: {
                el: '.swiper-pagination',
                clickable: true,
            },
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },
        });

        // تهيئة سلايدر المنتجات
        initSwiper(prodSlider, {
            slidesPerView: 1,
            spaceBetween: 30,
            loop: true,
            pagination: {
                el: '#prod-pager',
                clickable: true,
            },
            breakpoints: {
                640: {
                    slidesPerView: 2,
                },
                768: {
                    slidesPerView: 3,
                },
                1024: {
                    slidesPerView: 4,
                },
            },
        });

        // Owl Carousel - المنتجات
        if (productCarousel.length) {
            if (isLibraryLoaded('owlCarousel')) {
                try {
                    productCarousel.owlCarousel({
                        loop: true,
                        autoplay: true,
                        margin: 15,
                        dots: false,
                        nav: true,
                        navText: [
                            '<i class="fa fa-angle-left"></i>',
                            '<i class="fa fa-angle-right"></i>'
                        ],
                        responsive: {
                            0: { items: 1 },
                            600: { items: 3 },
                            1000: { items: 4 }
                        }
                    });
                } catch (e) {
                    console.warn('خطأ في تهيئة Owl للمنتجات:', e);
                }
            } else {
                console.warn('مكتبة Owl Carousel غير محملة');
            }
        }

        // Owl Carousel - الشهادات
        if (testimonialCarousel.length) {
            if (isLibraryLoaded('owlCarousel')) {
                try {
                    testimonialCarousel.owlCarousel({
                        loop: true,
                        autoplay: true,
                        margin: 15,
                        dots: false,
                        nav: true,
                        navText: [
                            '<i class="fa fa-angle-left"></i>',
                            '<i class="fa fa-angle-right"></i>'
                        ],
                        responsive: {
                            0: { items: 1 },
                            600: { items: 1 },
                            1000: { items: 1 }
                        }
                    });
                } catch (e) {
                    console.warn('خطأ في تهيئة Owl للشهادات:', e);
                }
            } else {
                console.warn('مكتبة Owl Carousel غير محملة');
            }
        }

        // Magnific Popup - الفيديو
        if (youtubeThumb.length) {
            if (isLibraryLoaded('magnificPopup')) {
                try {
                    youtubeThumb.magnificPopup({
                        disableOn: 700,
                        type: 'iframe',
                        mainClass: 'mfp-fade',
                        removalDelay: 160,
                        preloader: false,
                        fixedContentPos: false
                    });
                } catch (e) {
                    console.warn('خطأ في تهيئة magnificPopup:', e);
                }
            } else {
                console.warn('مكتبة Magnific Popup غير محملة');
            }
        }

        // زر العودة إلى الأعلى
        mainWindow.on("scroll", function () {
            if ($(this).scrollTop() > 98) {
                scrollUp.show();
            } else {
                scrollUp.hide();
            }
        });

        scrollUp.on("click", function () {
            $('html, body').animate({ scrollTop: 0 }, 800);
            return false;
        });
    });

    // عند جاهزية المستند
    mainDocument.ready(function () {
        // تهيئة Bootstrap Touch Slider
        if (bootstrapTouchSlider.length) {
            if (isLibraryLoaded('bsTouchSlider')) {
                try {
                    bootstrapTouchSlider.bsTouchSlider();
                } catch (e) {
                    console.warn('خطأ في تهيئة Bootstrap Touch Slider:', e);
                }
            } else {
                console.warn('مكتبة Bootstrap Touch Slider غير محملة');
            }
        }

        // تهيئة التقييم
        if (ratingSection.length) {
            if (isLibraryLoaded('rating')) {
                try {
                    ratingSection.rating();
                } catch (e) {
                    console.warn('خطأ في تهيئة التقييم:', e);
                }
            } else {
                console.warn('مكتبة Rating غير محملة');
            }
        }

        // تهيئة Select2
        if ($.fn.select2) {
            try {
                $('.select2').select2();
            } catch (e) {
                console.warn('خطأ في تهيئة Select2:', e);
            }
        }

        // القائمة الجانبية
        $(document).on("click", "#left ul.nav li.parent > a > span.sign", function () {
            $(this).find('i:first').toggleClass("fa-minus");
        });

        $("#left ul.nav li.parent.active > a > span.sign").find('i:first').addClass("fa-minus");
        $("#left ul.nav li.current").parents('ul.children').addClass("in");
    });

})(jQuery);