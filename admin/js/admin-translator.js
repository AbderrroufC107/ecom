/**
 * Admin Panel — Universal Content Translator v3
 * Bidirectional: translates Arabic→FR/EN  AND  English→AR/FR
 * Covers: table headers, buttons, badges, labels, page titles, modals,
 *         DataTables UI, confirm/alert dialogs, placeholders.
 */
(function () {
  'use strict';

  // ─── Multilingual dictionary ──────────────────────────────────────────────────
  // Each entry: source_text → { ar, fr, en }
  // For Arabic pages the source text IS Arabic (ar value left blank/same).
  // For English pages the source text IS English (en value left blank/same).
  // Both sets are merged into a single lookup table keyed by the source text.

  const entries = [

    // ══ Page / Section Titles ════════════════════════════════════════════════
    { ar: 'لوحة الإدارة',               fr: 'Tableau de bord',              en: 'Admin Dashboard' },
    { ar: 'لوحة التحكم',               fr: 'Tableau de bord',              en: 'Control Panel' },
    { ar: 'لوحة المتابعة اليومية',      fr: 'Suivi quotidien',              en: 'Daily Overview' },
    { ar: 'لوحة المتجر',               fr: 'Tableau boutique',             en: 'Store Dashboard' },
    { ar: 'ملخص تنفيذي',              fr: 'Résumé exécutif',              en: 'Executive Summary' },
    { ar: 'إدارة المنتجات',            fr: 'Gestion des produits',         en: 'Manage Products' },
    { ar: 'عرض المنتجات',              fr: 'Liste des produits',           en: 'Products List' },
    { ar: 'لوحة متابعة الطلبات',      fr: 'Suivi des commandes',          en: 'Orders Dashboard' },
    { ar: 'إدارة الطلبات',             fr: 'Gestion des commandes',        en: 'Manage Orders' },
    { ar: 'إدارة العملاء',             fr: 'Gestion des clients',          en: 'Manage Customers' },
    { ar: 'عرض العملاء',               fr: 'Liste des clients',            en: 'Customers List' },
    { ar: 'إدارة الموظفين',            fr: 'Gestion des employés',         en: 'Manage Employees' },
    { ar: 'إعدادات المتجر',            fr: 'Paramètres boutique',          en: 'Store Settings' },
    { ar: 'إحصائيات الطلبات',          fr: 'Statistiques commandes',       en: 'Order Statistics' },
    { ar: 'الطلبات غير المكتملة',      fr: 'Commandes incomplètes',        en: 'Incomplete Orders' },
    { ar: 'طلبات الاستبدال',           fr: "Demandes d'échange",           en: 'Exchange Requests' },
    { ar: 'بكسلات التتبع',             fr: 'Pixels de suivi',              en: 'Tracking Pixels' },
    { ar: 'إعدادات الأحداث',           fr: 'Paramètres événements',        en: 'Event Settings' },
    { ar: 'تحليلات الذكاء الاصطناعي',  fr: 'Analyses IA',                  en: 'AI Insights' },
    { ar: 'أمان الموقع',               fr: 'Sécurité du site',             en: 'Site Security' },
    { ar: 'صحة النظام',                fr: 'État du système',              en: 'System Health' },
    { ar: 'سجل التدقيق',               fr: "Journal d'audit",              en: 'Audit Log' },
    { ar: 'العمولات',                  fr: 'Commissions',                  en: 'Commissions' },
    { ar: 'الفوترة',                   fr: 'Facturation',                  en: 'Billing' },
    { ar: 'التكاملات',                 fr: 'Intégrations',                 en: 'Integrations' },
    { ar: 'النسخ الاحتياطي',           fr: 'Sauvegarde',                   en: 'Backups' },
    { ar: 'شركات التوصيل',             fr: 'Transporteurs',                en: 'Delivery Companies' },
    { ar: 'قائمة التوصيل',             fr: 'Liste de livraison',           en: 'Delivery List' },
    { ar: 'مفاتيح API',                fr: 'Clés API',                     en: 'API Keys' },
    { ar: 'تفاصيل الموظف',             fr: "Détails de l'employé",        en: 'Employee Details' },
    { ar: 'ترتيب الموظفين',            fr: 'Classement employés',          en: 'Employee Ranking' },
    { ar: 'الأسئلة الشائعة',           fr: 'FAQ',                          en: 'FAQ' },
    { ar: 'إعدادات الشحن',             fr: 'Paramètres livraison',         en: 'Shipping Settings' },
    { ar: 'سلايدر الموقع',             fr: 'Slider du site',               en: 'Site Slider' },

    // ══ English-source pages (color, size, categories, country...) ════════════
    { en: 'View Colors',                ar: 'عرض الألوان',                  fr: 'Liste des couleurs' },
    { en: 'View Sizes',                 ar: 'عرض المقاسات',                 fr: 'Liste des tailles' },
    { en: 'View Products',              ar: 'قائمة المنتجات',              fr: 'Liste des produits' },
    { en: 'View Customers',             ar: 'قائمة العملاء',               fr: 'Liste des clients' },
    { en: 'View Top Level Categories',  ar: 'الفئات الرئيسية',             fr: 'Catégories principales' },
    { en: 'View Mid Level Categories',  ar: 'الفئات الوسطى',               fr: 'Catégories intermédiaires' },
    { en: 'View End Level Categories',  ar: 'الفئات النهائية',             fr: 'Catégories finales' },
    { en: 'View Countries',             ar: 'عرض الولايات',                fr: 'Liste des wilayas' },
    { en: 'View Sliders',               ar: 'الصور المتحركة',               fr: 'Sliders' },
    { en: 'View Services',              ar: 'عرض الخدمات',                 fr: 'Liste des services' },
    { en: 'View FAQ',                   ar: 'الأسئلة الشائعة',             fr: 'FAQ' },
    { en: 'Store Settings',             ar: 'إعدادات المتجر',              fr: 'Paramètres boutique' },
    { en: 'Order Details',              ar: 'تفاصيل الطلب',                fr: 'Détails commande' },
    { en: 'Incomplete Orders',          ar: 'الطلبات غير المكتملة',        fr: 'Commandes incomplètes' },
    { en: 'Exchange Requests',          ar: 'طلبات الاستبدال',             fr: "Demandes d'échange" },

    // ══ Table Column Headers (Arabic source) ═════════════════════════════════
    { ar: 'رقم',                        fr: 'N°',                           en: '#' },
    { ar: 'الصورة',                     fr: 'Photo',                        en: 'Photo' },
    { ar: 'الاسم واللقب',              fr: 'Nom & Prénom',                 en: 'Full Name' },
    { ar: 'الاسم الكامل',              fr: 'Nom complet',                  en: 'Full Name' },
    { ar: 'اسم المنتج',                fr: 'Nom du produit',               en: 'Product Name' },
    { ar: 'اسم الموظف',                fr: "Nom de l'employé",            en: 'Employee Name' },
    { ar: 'اسم العميل',                fr: 'Nom client',                   en: 'Customer Name' },
    { ar: 'البريد الإلكتروني',          fr: 'E-mail',                       en: 'Email' },
    { ar: 'رقم الهاتف',                fr: 'Téléphone',                    en: 'Phone' },
    { ar: 'العنوان الكامل',            fr: 'Adresse complète',             en: 'Full Address' },
    { ar: 'حالة الحساب',               fr: 'Statut du compte',             en: 'Account Status' },
    { ar: 'السعر القديم',              fr: 'Ancien prix',                  en: 'Old Price' },
    { ar: 'السعر الحالي',              fr: 'Prix actuel',                  en: 'Current Price' },
    { ar: 'السعر',                     fr: 'Prix',                         en: 'Price' },
    { ar: 'الكمية',                    fr: 'Quantité',                     en: 'Qty' },
    { ar: 'مميز؟',                     fr: 'En vedette ?',                 en: 'Featured?' },
    { ar: 'نشط؟',                      fr: 'Actif ?',                      en: 'Active?' },
    { ar: 'الحالة',                    fr: 'Statut',                       en: 'Status' },
    { ar: 'التاريخ',                   fr: 'Date',                         en: 'Date' },
    { ar: 'الإجراءات',                 fr: 'Actions',                      en: 'Actions' },
    { ar: 'إجراءات',                   fr: 'Actions',                      en: 'Actions' },
    { ar: 'الفئة',                     fr: 'Catégorie',                    en: 'Category' },
    { ar: 'الفئات الرئيسية',           fr: 'Catégories principales',       en: 'Main Categories' },
    { ar: 'المقاسات',                  fr: 'Tailles',                      en: 'Sizes' },
    { ar: 'الألوان',                   fr: 'Couleurs',                     en: 'Colors' },
    { ar: 'رقم الطلب',                 fr: 'N° Commande',                  en: 'Order #' },
    { ar: 'الطلب',                     fr: 'Commande',                     en: 'Order' },
    { ar: 'العميل',                    fr: 'Client',                       en: 'Customer' },
    { ar: 'الموظف',                    fr: 'Employé',                      en: 'Employee' },
    { ar: 'المنتج',                    fr: 'Produit',                      en: 'Product' },
    { ar: 'الولاية',                   fr: 'Wilaya',                       en: 'State' },
    { ar: 'التوصيل',                   fr: 'Livraison',                    en: 'Delivery' },
    { ar: 'المتابعة',                  fr: 'Suivi',                        en: 'Follow-up' },
    { ar: 'القيمة',                    fr: 'Valeur',                       en: 'Value' },
    { ar: 'إجمالي السعر',              fr: 'Prix total',                   en: 'Total Price' },
    { ar: 'المخزون',                   fr: 'Stock',                        en: 'Stock' },
    { ar: 'الترتيب',                   fr: 'Classement',                   en: 'Rank' },
    { ar: 'عدد الطلبات',               fr: 'Nbre commandes',               en: 'Orders Count' },
    { ar: 'تاريخ الإضافة',             fr: "Date d'ajout",                 en: 'Date Added' },
    { ar: 'تاريخ الإنشاء',             fr: 'Date de création',             en: 'Creation Date' },
    { ar: 'الإحصائيات',                fr: 'Statistiques',                 en: 'Statistics' },
    { ar: 'حالة التيليجرام',           fr: 'Statut Telegram',              en: 'Telegram Status' },

    // ══ Table Headers (English source — for legacy pages) ═════════════════════
    { en: 'Color Name',                 ar: 'اسم اللون',                   fr: 'Nom couleur' },
    { en: 'Size Name',                  ar: 'اسم المقاس',                  fr: 'Nom taille' },
    { en: 'Top Category Name',          ar: 'اسم الفئة الرئيسية',          fr: 'Nom catégorie' },
    { en: 'Mid Category Name',          ar: 'اسم الفئة الوسطى',            fr: 'Catégorie intermédiaire' },
    { en: 'End Category Name',          ar: 'اسم الفئة النهائية',          fr: 'Catégorie finale' },
    { en: 'Product Name',               ar: 'اسم المنتج',                  fr: 'Nom produit' },
    { en: 'Country Name',               ar: 'اسم الولاية',                 fr: 'Nom wilaya' },
    { en: 'Service Name',               ar: 'اسم الخدمة',                  fr: 'Nom service' },
    { en: 'Slider Image',               ar: 'صورة السلايدر',               fr: 'Image slider' },
    { en: 'Show on Menu?',              ar: 'إظهار في القائمة؟',           fr: 'Afficher au menu ?' },
    { en: 'Old Price',                  ar: 'السعر القديم',                fr: 'Ancien prix' },
    { en: '(C) Price',                  ar: 'السعر الحالي',                fr: 'Prix actuel' },
    { en: 'Quantity',                   ar: 'الكمية',                      fr: 'Quantité' },
    { en: 'Featured?',                  ar: 'مميز؟',                       fr: 'En vedette ?' },
    { en: 'Active?',                    ar: 'نشط؟',                        fr: 'Actif ?' },
    { en: 'Action',                     ar: 'الإجراءات',                   fr: 'Actions' },
    { en: 'Status',                     ar: 'الحالة',                      fr: 'Statut' },
    { en: 'Phone',                      ar: 'الهاتف',                      fr: 'Téléphone' },
    { en: 'Address',                    ar: 'العنوان',                      fr: 'Adresse' },
    { en: 'Date',                       ar: 'التاريخ',                     fr: 'Date' },
    { en: 'Photo',                      ar: 'الصورة',                      fr: 'Photo' },
    { en: 'Price',                      ar: 'السعر',                       fr: 'Prix' },
    { en: 'Total Price',                ar: 'إجمالي السعر',                fr: 'Prix total' },
    { en: 'Delivery',                   ar: 'التوصيل',                     fr: 'Livraison' },
    { en: 'Customer Name',              ar: 'اسم العميل',                  fr: 'Nom client' },
    { en: 'Customer Phone',             ar: 'هاتف العميل',                 fr: 'Tél. client' },

    // ══ Buttons (English source) ══════════════════════════════════════════════
    { en: 'Add New',                    ar: 'إضافة جديد',                  fr: 'Ajouter' },
    { en: 'Add Product',                ar: 'إضافة منتج',                  fr: 'Ajouter un produit' },
    { en: 'Add Employee',               ar: 'إضافة موظف',                  fr: 'Ajouter un employé' },
    { en: 'Add Color',                  ar: 'إضافة لون',                   fr: 'Ajouter couleur' },
    { en: 'Add Size',                   ar: 'إضافة مقاس',                  fr: 'Ajouter taille' },
    { en: 'Add Category',               ar: 'إضافة فئة',                   fr: 'Ajouter catégorie' },
    { en: 'Add Slider',                 ar: 'إضافة صورة',                  fr: 'Ajouter slider' },
    { en: 'Add Country',                ar: 'إضافة ولاية',                 fr: 'Ajouter wilaya' },
    { en: 'Add FAQ',                    ar: 'إضافة سؤال',                  fr: 'Ajouter FAQ' },
    { en: 'Save Settings',              ar: 'حفظ الإعدادات',               fr: 'Enregistrer' },
    { en: 'Save Changes',               ar: 'حفظ التعديلات',               fr: 'Enregistrer' },
    { en: 'Edit',                       ar: 'تعديل',                       fr: 'Modifier' },
    { en: 'Delete',                     ar: 'حذف',                         fr: 'Supprimer' },
    { en: 'Cancel',                     ar: 'إلغاء',                       fr: 'Annuler' },
    { en: 'Close',                      ar: 'إغلاق',                       fr: 'Fermer' },
    { en: 'Search',                     ar: 'بحث',                         fr: 'Rechercher' },
    { en: 'Back',                       ar: 'رجوع',                        fr: 'Retour' },
    { en: 'Confirm',                    ar: 'تأكيد',                       fr: 'Confirmer' },
    { en: 'Details',                    ar: 'تفاصيل',                      fr: 'Détails' },
    { en: 'View',                       ar: 'عرض',                         fr: 'Voir' },
    { en: 'Print',                      ar: 'طباعة',                       fr: 'Imprimer' },
    { en: 'Export',                     ar: 'تصدير',                       fr: 'Exporter' },
    { en: 'Convert to Order',           ar: 'تحويل لطلب',                  fr: 'Convertir' },
    { en: 'Change Status',              ar: 'تغيير الحالة',                fr: 'Changer statut' },

    // ══ Modal Titles (English source) ════════════════════════════════════════
    { en: 'Delete Confirmation',        ar: 'تأكيد الحذف',                 fr: 'Confirmer suppression' },
    { en: 'Are you sure want to delete this item?',
                                        ar: 'هل أنت متأكد من حذف هذا العنصر؟', fr: 'Supprimer cet élément ?' },
    { en: 'Are you sure want to delete this item',
                                        ar: 'هل أنت متأكد من الحذف؟',     fr: 'Confirmer la suppression ?' },
    { en: 'Be careful! This product will be deleted from the order table, payment table, size table, color table and rating table also.',
                                        ar: 'تحذير! سيُحذف المنتج نهائياً من كل الجداول.',
                                        fr: 'Attention ! Ce produit sera supprimé définitivement.' },
    { en: 'Be careful! All products, mid level categories and end level categories under this top lelvel category will be deleted from all the tables like order table, payment table, size table, color table, rating table etc.',
                                        ar: 'تحذير! ستُحذف جميع المنتجات والفئات الفرعية المرتبطة بهذه الفئة.',
                                        fr: 'Attention ! Tous les produits et sous-catégories seront supprimés.' },

    // ══ Status Badges (Arabic source) ════════════════════════════════════════
    { ar: 'نشط',                        fr: 'Actif',                        en: 'Active' },
    { ar: 'معطل',                       fr: 'Désactivé',                    en: 'Disabled' },
    { ar: 'غير نشط',                   fr: 'Inactif',                      en: 'Inactive' },
    { ar: 'معلّق',                      fr: 'En attente',                   en: 'Pending' },
    { ar: 'معلق',                       fr: 'En attente',                   en: 'Pending' },
    { ar: 'مؤكد',                       fr: 'Confirmé',                     en: 'Confirmed' },
    { ar: 'مكتمل',                      fr: 'Complété',                     en: 'Completed' },
    { ar: 'ملغي',                       fr: 'Annulé',                       en: 'Cancelled' },
    { ar: 'مرتجع',                      fr: 'Retourné',                     en: 'Returned' },
    { ar: 'مُسلّم',                     fr: 'Livré',                        en: 'Delivered' },
    { ar: 'غير محدد',                   fr: 'Non défini',                   en: 'Not set' },
    { ar: 'نعم',                        fr: 'Oui',                          en: 'Yes' },
    { ar: 'لا',                         fr: 'Non',                          en: 'No' },
    { ar: 'إلى المنزل',                 fr: 'À domicile',                   en: 'Home delivery' },
    { ar: 'إلى المكتب',                 fr: 'Au bureau',                    en: 'Office delivery' },
    { ar: 'توصيل مجاني',               fr: 'Livraison gratuite',           en: 'Free delivery' },
    { ar: 'عميل مسجل',                 fr: 'Client enregistré',            en: 'Registered customer' },
    { ar: 'طلب مباشر',                 fr: 'Commande directe',             en: 'Direct order' },
    { ar: 'غير موزع',                   fr: 'Non attribué',                 en: 'Unassigned' },

    // ══ Status (English source) ═══════════════════════════════════════════════
    { en: 'Yes',                        ar: 'نعم',                          fr: 'Oui' },
    { en: 'No',                         ar: 'لا',                           fr: 'Non' },
    { en: 'Active',                     ar: 'نشط',                          fr: 'Actif' },
    { en: 'Inactive',                   ar: 'غير نشط',                     fr: 'Inactif' },
    { en: 'Pending',                    ar: 'معلق',                         fr: 'En attente' },
    { en: 'Confirmed',                  ar: 'مؤكد',                         fr: 'Confirmé' },
    { en: 'Completed',                  ar: 'مكتمل',                        fr: 'Complété' },
    { en: 'Cancelled',                  ar: 'ملغي',                         fr: 'Annulé' },
    { en: 'Returned',                   ar: 'مرتجع',                        fr: 'Retourné' },
    { en: 'Delivered',                  ar: 'مُسلّم',                       fr: 'Livré' },

    // ══ Dashboard Stats Labels (Arabic) ══════════════════════════════════════
    { ar: 'إجمالي الطلبات',            fr: 'Total commandes',              en: 'Total Orders' },
    { ar: 'طلبات اليوم',               fr: 'Commandes du jour',            en: "Today's Orders" },
    { ar: 'الطلبات المعلقة',           fr: 'Commandes en attente',         en: 'Pending Orders' },
    { ar: 'الإيراد المكتمل',           fr: 'CA complété',                  en: 'Completed Revenue' },
    { ar: 'إيراد اليوم',               fr: 'CA du jour',                   en: "Today's Revenue" },
    { ar: 'إيراد الشهر',               fr: 'CA du mois',                   en: "Month's Revenue" },
    { ar: 'إجمالي المنتجات',           fr: 'Total produits',               en: 'Total Products' },
    { ar: 'المنتجات النشطة',           fr: 'Produits actifs',              en: 'Active Products' },
    { ar: 'مخزون منخفض',               fr: 'Stock faible',                 en: 'Low Stock' },
    { ar: 'إجمالي العملاء',            fr: 'Total clients',                en: 'Total Customers' },
    { ar: 'العملاء النشطون',           fr: 'Clients actifs',               en: 'Active Customers' },
    { ar: 'العملاء المسجلون',          fr: 'Clients enregistrés',          en: 'Registered Customers' },
    { ar: 'طلبات مكتملة',              fr: 'Commandes complétées',         en: 'Completed Orders' },
    { ar: 'طلبات مؤكدة',               fr: 'Commandes confirmées',         en: 'Confirmed Orders' },
    { ar: 'طلبات ملغاة',               fr: 'Commandes annulées',           en: 'Cancelled Orders' },
    { ar: 'طلبات مباشرة',              fr: 'Commandes directes',           en: 'Direct Orders' },
    { ar: 'طلبات غير مكتملة',          fr: 'Commandes incomplètes',        en: 'Incomplete Orders' },
    { ar: 'أحدث الطلبات',              fr: 'Dernières commandes',          en: 'Latest Orders' },
    { ar: 'آخر الطلبات',               fr: 'Dernières commandes',          en: 'Latest Orders' },
    { ar: 'النشاط الأخير',             fr: 'Activité récente',             en: 'Recent Activity' },
    { ar: 'الموظفون النشطون',           fr: 'Employés actifs',              en: 'Active Employees' },
    { ar: 'إجمالي الطلبات الموزعة',   fr: 'Commandes assignées',          en: 'Total Assigned Orders' },
    { ar: 'طلبات غير موزعة',           fr: 'Commandes non assignées',      en: 'Unassigned Orders' },
    { ar: 'قيد الانتظار',              fr: 'En attente',                   en: 'Pending' },
    { ar: 'مكتملة',                    fr: 'Complétées',                   en: 'Completed' },
    { ar: 'ملغاة',                     fr: 'Annulées',                     en: 'Cancelled' },

    // ══ Dashboard Sections ════════════════════════════════════════════════════
    { ar: 'لوحة الإدارة',              fr: 'Tableau de bord',              en: 'Admin Dashboard' },
    { ar: 'روابط مباشرة',              fr: 'Accès rapides',                en: 'Quick Links' },
    { ar: 'إجراءات سريعة',             fr: 'Actions rapides',              en: 'Quick Actions' },
    { ar: 'مؤشرات تنفيذية',            fr: 'Indicateurs clés',             en: 'KPI Metrics' },
    { ar: 'استرجاع العمليات',          fr: 'Récupération',                 en: 'Recovery' },
    { ar: 'الطلبات غير المكتملة الأخيرة', fr: 'Dernières commandes incomplètes', en: 'Recent Incomplete Orders' },
    { ar: 'تنبيهات المخزون',           fr: "Alertes de stock",             en: 'Stock Alerts' },
    { ar: 'منتجات تحتاج متابعة',       fr: 'Produits à surveiller',        en: 'Products Need Attention' },
    { ar: 'آخر تحديث',                 fr: 'Dernière mise à jour',         en: 'Last update' },
    { ar: 'أفضل موظف',                 fr: 'Meilleur employé',             en: 'Top Employee' },
    { ar: 'معدل الإنجاز',              fr: "Taux d'accomplissement",       en: 'Completion Rate' },
    { ar: 'معدل التوصيل الإجمالي',     fr: 'Taux de livraison global',     en: 'Overall Delivery Rate' },
    { ar: 'متوسط الطلب المكتمل',       fr: 'Panier moyen complété',        en: 'Avg Completed Order' },

    // ══ Empty States ══════════════════════════════════════════════════════════
    { ar: 'لا توجد بيانات',            fr: 'Aucune donnée',                en: 'No data' },
    { ar: 'لا توجد سجلات',             fr: 'Aucun enregistrement',         en: 'No records' },
    { ar: 'لا توجد نتائج',             fr: 'Aucun résultat',               en: 'No results' },
    { ar: 'لا توجد طلبات حتى الآن',   fr: "Aucune commande pour l'instant", en: 'No orders yet' },
    { ar: 'لا يوجد موظفون بعد.',       fr: "Aucun employé pour l'instant.", en: 'No employees yet.' },
    { ar: 'لا توجد طلبات غير مكتملة', fr: 'Aucune commande incomplète',   en: 'No incomplete orders' },
    { ar: 'المخزون في وضع جيد',        fr: 'Stock en bon état',            en: 'Stock is healthy' },
    { ar: 'بدون منتج محدد',            fr: 'Produit non spécifié',         en: 'No product specified' },
    { ar: 'منتج غير محدد',             fr: 'Produit non spécifié',         en: 'Product not specified' },
    { ar: 'عميل غير محدد',             fr: 'Client non spécifié',          en: 'Customer not specified' },
    { ar: 'رقم الهاتف غير متوفر',      fr: 'Téléphone non disponible',     en: 'Phone not available' },
    { ar: 'لا يوجد عنوان تفصيلي',      fr: 'Aucune adresse détaillée',     en: 'No detailed address' },
    { en: 'No data available',          ar: 'لا توجد بيانات',              fr: 'Aucune donnée' },
    { en: 'No matching records found',  ar: 'لا توجد نتائج مطابقة',        fr: 'Aucun résultat trouvé' },

    // ══ Modals (Arabic source) ════════════════════════════════════════════════
    { ar: 'تأكيد الحذف',               fr: 'Confirmer la suppression',     en: 'Delete Confirmation' },
    { ar: 'تأكيد العملية',             fr: "Confirmer l'opération",        en: 'Confirm Operation' },
    { ar: 'هل أنت متأكد من الحذف؟',   fr: 'Êtes-vous sûr de supprimer ?', en: 'Are you sure to delete?' },
    { ar: 'هل أنت متأكد من حذف هذا العنصر؟', fr: 'Supprimer cet élément ?', en: 'Delete this item?' },
    { ar: 'هل أنت متأكد من حذف هذا العميل؟', fr: 'Supprimer ce client ?',  en: 'Delete this customer?' },
    { ar: 'هل أنت متأكد؟',             fr: 'Êtes-vous sûr ?',              en: 'Are you sure?' },
    { ar: 'تعديل بيانات الموظف',       fr: "Modifier l'employé",           en: 'Edit Employee' },
    { ar: 'الحالة الحالية',            fr: 'Statut actuel',                en: 'Current Status' },
    { ar: 'الحالة الجديدة',            fr: 'Nouveau statut',               en: 'New Status' },
    { ar: 'اختر الحالة الجديدة',       fr: 'Choisir le nouveau statut',    en: 'Choose new status' },
    { ar: 'ملاحظة القرار',             fr: 'Note de décision',             en: 'Decision Note' },
    { ar: 'تحديث حالة الطلب',          fr: "Mettre à jour le statut",      en: 'Update Order Status' },

    // ══ Form Labels ═══════════════════════════════════════════════════════════
    { ar: 'الاسم الكامل',              fr: 'Nom complet',                  en: 'Full Name' },
    { ar: 'كلمة المرور',               fr: 'Mot de passe',                 en: 'Password' },
    { ar: 'تأكيد كلمة المرور',         fr: 'Confirmer le mot de passe',    en: 'Confirm Password' },
    { ar: 'رقم الواتساب',              fr: 'N° WhatsApp',                  en: 'WhatsApp Number' },
    { ar: 'معرّف تيليجرام',            fr: 'ID Telegram',                  en: 'Telegram ID' },
    { ar: 'إضافة وتعديل وتعطيل الموظفين وعرض إحصائيات التوزيع.', fr: 'Ajouter, modifier et gérer les employés.', en: 'Add, edit and manage employees.' },
    { ar: 'بحث بالاسم أو البريد...',   fr: 'Rechercher par nom ou e-mail...', en: 'Search by name or email...' },
    { ar: 'إرسال رسالة اختبار',        fr: 'Envoyer un message de test',  en: 'Send test message' },
    { ar: 'اختياري',                   fr: 'Optionnel',                    en: 'Optional' },
    { en: 'Store Name',                 ar: 'اسم المتجر',                   fr: 'Nom boutique' },
    { en: 'Store Email',                ar: 'بريد المتجر',                  fr: 'E-mail boutique' },
    { en: 'Store Phone',                ar: 'هاتف المتجر',                  fr: 'Tél boutique' },
    { en: 'Store Address',              ar: 'عنوان المتجر',                 fr: 'Adresse boutique' },

    // ══ Buttons (Arabic) ══════════════════════════════════════════════════════
    { ar: 'إضافة',                     fr: 'Ajouter',                      en: 'Add' },
    { ar: 'إضافة جديد',                fr: 'Ajouter',                      en: 'Add New' },
    { ar: 'إضافة منتج',                fr: 'Ajouter un produit',           en: 'Add Product' },
    { ar: 'إضافة موظف',                fr: 'Ajouter un employé',           en: 'Add Employee' },
    { ar: 'إضافة موظف جديد',           fr: "Ajouter un employé",           en: 'Add New Employee' },
    { ar: 'إضافة عميل',                fr: 'Ajouter un client',            en: 'Add Customer' },
    { ar: 'إضافة فئة',                 fr: 'Ajouter catégorie',            en: 'Add Category' },
    { ar: 'إضافة ولاية',               fr: 'Ajouter wilaya',               en: 'Add State' },
    { ar: 'إضافة لون',                 fr: 'Ajouter couleur',              en: 'Add Color' },
    { ar: 'إضافة مقاس',                fr: 'Ajouter taille',               en: 'Add Size' },
    { ar: 'إضافة صورة',                fr: 'Ajouter image',                en: 'Add Photo' },
    { ar: 'إضافة سلايدر',              fr: 'Ajouter slider',               en: 'Add Slider' },
    { ar: 'إضافة بكسل',                fr: 'Ajouter pixel',                en: 'Add Pixel' },
    { ar: 'حفظ',                       fr: 'Enregistrer',                  en: 'Save' },
    { ar: 'حفظ التعديلات',             fr: 'Enregistrer',                  en: 'Save Changes' },
    { ar: 'حفظ التغييرات',             fr: 'Enregistrer les modifications', en: 'Save Changes' },
    { ar: 'حفظ التغيير',               fr: 'Enregistrer',                  en: 'Save' },
    { ar: 'حفظ الإعدادات',             fr: 'Enregistrer les paramètres',   en: 'Save Settings' },
    { ar: 'تعديل',                     fr: 'Modifier',                     en: 'Edit' },
    { ar: 'حذف',                       fr: 'Supprimer',                    en: 'Delete' },
    { ar: 'إلغاء',                     fr: 'Annuler',                      en: 'Cancel' },
    { ar: 'إغلاق',                     fr: 'Fermer',                       en: 'Close' },
    { ar: 'بحث',                       fr: 'Rechercher',                   en: 'Search' },
    { ar: 'تصفية',                     fr: 'Filtrer',                      en: 'Filter' },
    { ar: 'تصدير',                     fr: 'Exporter',                     en: 'Export' },
    { ar: 'طباعة',                     fr: 'Imprimer',                     en: 'Print' },
    { ar: 'عرض الكل',                  fr: 'Voir tout',                    en: 'View All' },
    { ar: 'عرض جميع الطلبات',          fr: 'Voir toutes les commandes',    en: 'View All Orders' },
    { ar: 'رجوع',                      fr: 'Retour',                       en: 'Back' },
    { ar: 'تأكيد',                     fr: 'Confirmer',                    en: 'Confirm' },
    { ar: 'تغيير الحالة',              fr: 'Changer statut',               en: 'Change Status' },
    { ar: 'توزيع تلقائي',              fr: 'Distribution auto',            en: 'Auto Assign' },
    { ar: 'تفاصيل',                    fr: 'Détails',                      en: 'Details' },
    { ar: 'عرض',                       fr: 'Voir',                         en: 'View' },
    { ar: 'نسخ',                       fr: 'Copier',                       en: 'Copy' },
    { ar: 'تحديث',                     fr: 'Mettre à jour',                en: 'Update' },
    { ar: 'إرسال',                     fr: 'Envoyer',                      en: 'Send' },
    { ar: 'تحويل لطلب',                fr: 'Convertir en commande',        en: 'Convert to Order' },
    { ar: 'إلغاء الفلتر',              fr: 'Effacer le filtre',            en: 'Clear Filter' },
    { ar: 'تنفيذ على المحدد',          fr: 'Exécuter sur la sélection',    en: 'Execute on Selected' },
    { ar: 'حذف المحدد',                fr: 'Supprimer la sélection',       en: 'Delete Selected' },
    { ar: 'طباعة الليبل',              fr: "Imprimer l'étiquette",         en: 'Print Label' },
    { ar: 'خيارات الحالة',             fr: 'Options de statut',            en: 'Status Options' },

    // ══ Misc ══════════════════════════════════════════════════════════════════
    { ar: 'جميع الطلبات',              fr: 'Toutes les commandes',         en: 'All Orders' },
    { ar: 'جميع الموظفين',             fr: 'Tous les employés',            en: 'All Employees' },
    { ar: 'طلباتي',                    fr: 'Mes commandes',                en: 'My Orders' },
    { ar: 'غير موزعة',                 fr: 'Non assignées',                en: 'Unassigned' },
    { ar: 'مدير',                      fr: 'Administrateur',               en: 'Admin' },
    { ar: 'موظف',                      fr: 'Employé',                      en: 'Employee' },
    { ar: 'اختر',                      fr: 'Choisir',                      en: 'Choose' },
    { ar: 'اختر ولاية',                fr: 'Choisir une wilaya',           en: 'Choose State' },
    { ar: 'اختر الفئة',                fr: 'Choisir la catégorie',         en: 'Choose Category' },
    { ar: 'دج',                        fr: 'DA',                           en: 'DZD' },
    { ar: 'الكل',                      fr: 'Tous',                         en: 'All' },
    { en: 'All',                        ar: 'الكل',                         fr: 'Tous' },
    // == delivery_list.php ==
    { ar: 'إضافة شركة جديدة',          fr: 'Ajouter un transporteur',      en: 'Add New Company' },
    { ar: 'إضافة شركة توصيل',          fr: 'Ajouter transporteur',         en: 'Add Delivery Company' },
    { ar: 'إجمالي الشركات',            fr: 'Total transporteurs',          en: 'Total Companies' },
    { ar: 'الشركة النشطة',             fr: 'Transporteur actif',           en: 'Active Company' },
    { ar: 'تغطية الشركة النشطة',       fr: 'Couverture active',            en: 'Active Coverage' },
    { ar: 'التحكم السريع',             fr: 'Contrôle rapide',              en: 'Quick Control' },
    { ar: 'تفعيل',                     fr: 'Activer',                      en: 'Activate' },
    { ar: 'إدارة الأسعار',             fr: 'Gérer les tarifs',             en: 'Manage Prices' },
    { ar: 'تعديل الشركة النشطة',       fr: 'Modifier le transporteur',     en: 'Edit Active Company' },
    { ar: 'شركة جديدة',                fr: 'Nouveau transporteur',         en: 'New Company' },
    { ar: 'قائمة الشركات',             fr: 'Liste des transporteurs',      en: 'Companies List' },
    { ar: 'نشطة الآن',                 fr: 'Active maintenant',            en: 'Currently active' },
    { ar: 'سعر مسجل',                  fr: 'Tarif enregistré',             en: 'Registered price' },
    { ar: 'ولاية مغطاة',               fr: 'Wilaya couverte',              en: 'Covered wilaya' },
    { ar: 'توصيل منزل',                fr: 'Livraison domicile',           en: 'Home delivery price' },
    { ar: 'توصيل مكتب',                fr: 'Livraison bureau',             en: 'Office delivery price' },
    { ar: 'تعيين كنشطة',               fr: 'Définir comme actif',          en: 'Set as Active' },
    { ar: 'تفعيل وإدارة',              fr: 'Activer et gérer',             en: 'Activate and Manage' },
    { ar: 'كل الأنواع',                fr: 'Tous les types',               en: 'All types' },
    { ar: 'منزل',                      fr: 'Domicile',                     en: 'Home' },
    { ar: 'مكتب',                      fr: 'Bureau',                       en: 'Office' },
    { ar: 'تطبيق',                     fr: 'Appliquer',                    en: 'Apply' },
    { ar: 'إعادة ضبط',                 fr: 'Réinitialiser',                en: 'Reset' },
    { ar: 'إضافة أو تعديل الأسعار',   fr: 'Ajouter ou modifier les tarifs', en: 'Add or Edit Prices' },
    { ar: 'لا توجد نتائج مطابقة',      fr: 'Aucun résultat',               en: 'No matching results' },
    { ar: 'إضافة أول سعر',             fr: 'Ajouter le premier tarif',     en: 'Add first price' },
    { ar: 'إزالة الفلاتر',             fr: 'Supprimer les filtres',        en: 'Clear filters' },
    // == incomplete-orders.php ==
    { ar: 'إدارة الطلبات',             fr: 'Gérer les commandes',          en: 'Manage Orders' },
    { ar: 'الإجمالي',                  fr: 'Total',                        en: 'Total' },
    { ar: 'نوع التوصيل',               fr: 'Type livraison',               en: 'Delivery Type' },
    { ar: 'البلدية',                   fr: 'Commune',                      en: 'Municipality' },
    { ar: 'تحويل إلى طلب',             fr: 'Convertir en commande',        en: 'Convert to Order' },
    { ar: 'حذف المحدد',                fr: 'Supprimer la sélection',       en: 'Delete Selected' },
    // == exchange-requests.php ==
    { ar: 'الزبون',                    fr: 'Client',                       en: 'Customer' },
    { ar: 'السبب والصورة',             fr: 'Motif et photo',               en: 'Reason and Photo' },
    { ar: 'فتح الطلب',                 fr: 'Ouvrir la commande',           en: 'Open Order' },
    // == slider.php ==
    { en: 'View Sliders',               ar: 'الصور المتحركة',               fr: 'Sliders' },
    { en: 'Add Slider',                 ar: 'إضافة صورة متحركة',           fr: 'Ajouter slider' },
    { en: 'Heading',                    ar: 'العنوان',                      fr: 'Titre' },
    { en: 'Content',                    ar: 'المحتوى',                      fr: 'Contenu' },
    { en: 'Button Text',                ar: 'نص الزر',                      fr: 'Texte bouton' },
    { en: 'Button URL',                 ar: 'رابط الزر',                    fr: 'Lien bouton' },
    { en: 'Position',                   ar: 'الترتيب',                      fr: 'Position' },
    // == settings.php ==
    { en: 'Website Settings',           ar: 'إعدادات الموقع',               fr: 'Paramètres du site' },
    { en: 'Logo',                       ar: 'الشعار',                        fr: 'Logo' },
    { en: 'Favicon',                    ar: 'أيقونة الموقع',                fr: 'Favicon' },
    { en: 'Existing Photo',             ar: 'الصورة الحالية',               fr: 'Photo actuelle' },
    { en: 'New Photo',                  ar: 'صورة جديدة',                   fr: 'Nouvelle photo' },
    { en: 'Update Logo',                ar: 'تحديث الشعار',                 fr: 'Mettre à jour le logo' },
    { en: 'Update Favicon',             ar: 'تحديث الأيقونة',              fr: 'Mettre à jour le favicon' },
    { en: 'Save Settings',              ar: 'حفظ الإعدادات',               fr: 'Enregistrer les paramètres' },
    // == country.php ==
    { en: 'View Countries',             ar: 'عرض الولايات',                 fr: 'Liste des wilayas' },
    { en: 'Country Name',               ar: 'اسم الولاية',                  fr: 'Nom wilaya' },
    { en: 'Show on Menu?',              ar: 'إظهار في القائمة؟',            fr: 'Afficher au menu ?' },

    // == product-add.php / product-edit.php ==
    { ar: 'تعديل المنتج',               fr: 'Modifier le produit',          en: 'Edit Product' },
    { ar: 'إضافة المنتج',               fr: 'Ajouter le produit',           en: 'Add Product' },
    { ar: 'معاينة',                     fr: 'Aperçu',                       en: 'Preview' },
    { ar: 'القالب',                     fr: 'Modèle',                       en: 'Template' },
    { ar: 'الأساسيات',                  fr: 'Bases',                        en: 'Basics' },
    { ar: 'العروض',                     fr: 'Offres',                       en: 'Offers' },
    { ar: 'الوصف والصور',               fr: 'Description & images',         en: 'Description & Images' },
    { ar: 'الخيارات والحالة',           fr: 'Options & statut',             en: 'Options & Status' },
    { ar: 'شركة التوصيل',               fr: 'Transporteur',                 en: 'Delivery Company' },
    { ar: 'إعلان مختصر',                fr: 'Annonce courte',               en: 'Short Announcement' },
    { ar: 'سعر الشراء',                 fr: "Prix d'achat",                 en: 'Purchase Price' },
    { ar: 'أسعار عروض الكمية',          fr: 'Prix des offres de quantité',  en: 'Quantity Offer Prices' },
    { ar: 'العروض الخاصة',              fr: 'Offres spéciales',             en: 'Special Offers' },
    { ar: 'الوصف',                      fr: 'Description',                  en: 'Description' },
    { ar: 'وصف إضافي',                  fr: 'Description supplémentaire',   en: 'Additional Description' },
    { ar: 'الصورة الرئيسية',            fr: 'Image principale',             en: 'Main Image' },
    { ar: 'حذف الصورة الرئيسية',        fr: "Supprimer l'image principale", en: 'Delete Main Image' },
    { ar: 'أو رابط صورة',               fr: 'Ou lien image',                en: 'Or image URL' },
    { ar: 'الصور الإضافية',             fr: 'Images supplémentaires',       en: 'Additional Images' },
    { ar: 'الصور الإضافية الحالية',     fr: 'Images supplémentaires actuelles', en: 'Current Additional Images' },
    { ar: 'إضافة صور إضافية جديدة',     fr: 'Ajouter de nouvelles images',  en: 'Add New Additional Images' },
    { ar: 'روابط الصور الإضافية، كل رابط في سطر', fr: 'Liens des images supplémentaires, un lien par ligne', en: 'Additional image URLs, one per line' },
    { ar: 'بكسلات التتبع (Pixels)',     fr: 'Pixels de suivi',              en: 'Tracking Pixels' },
    { ar: 'منتج مميز؟',                 fr: 'Produit en vedette ?',         en: 'Featured Product?' },
    { ar: 'نشط؟',                       fr: 'Actif ?',                      en: 'Active?' },
    { ar: 'التالي',                     fr: 'Suivant',                      en: 'Next' },
    { ar: 'السابق',                     fr: 'Précédent',                   en: 'Previous' },
    { ar: 'حفظ التعديلات',              fr: 'Enregistrer modifications',    en: 'Save Changes' },
    { ar: 'هل تريد حذف الصورة الرئيسية؟', fr: "Supprimer l'image principale ?", en: 'Delete the main image?' },
    { ar: 'هل تريد حذف هذه الصورة؟',    fr: 'Supprimer cette image ?',      en: 'Delete this image?' },
  ];

  // ─── Build lookup maps per language ──────────────────────────────────────────
  // For each entry, build source→target mappings for all language pairs.
  // The admin contains both UTF-8 text and older mojibake text, so each source
  // string is indexed in normalized, decoded and mojibake forms.
  const maps = { ar: {}, fr: {}, en: {} };
  const supportedLangs = ['ar', 'fr', 'en'];

  function normalizeLang(lang) {
    return supportedLangs.indexOf(lang) >= 0 ? lang : 'ar';
  }

  function normalizeText(value) {
    return String(value || '').replace(/\s+/g, ' ').trim();
  }

  function cp1252Byte(character) {
    const code = character.charCodeAt(0);
    const map = {
      0x20ac: 0x80,
      0x201a: 0x82,
      0x0192: 0x83,
      0x201e: 0x84,
      0x2026: 0x85,
      0x2020: 0x86,
      0x2021: 0x87,
      0x02c6: 0x88,
      0x2030: 0x89,
      0x0160: 0x8a,
      0x2039: 0x8b,
      0x0152: 0x8c,
      0x017d: 0x8e,
      0x2018: 0x91,
      0x2019: 0x92,
      0x201c: 0x93,
      0x201d: 0x94,
      0x2022: 0x95,
      0x2013: 0x96,
      0x2014: 0x97,
      0x02dc: 0x98,
      0x2122: 0x99,
      0x0161: 0x9a,
      0x203a: 0x9b,
      0x0153: 0x9c,
      0x017e: 0x9e,
      0x0178: 0x9f,
    };
    return map[code] || (code <= 255 ? code : null);
  }

  function decodeMojibake(value) {
    const text = normalizeText(value);
    if (!/[ÃÂØÙ]/.test(text) || typeof TextDecoder === 'undefined') return text;

    let current = text;
    try {
      for (let pass = 0; pass < 4; pass += 1) {
        const bytes = [];
        for (let index = 0; index < current.length; index += 1) {
          const byte = cp1252Byte(current.charAt(index));
          if (byte === null) return current;
          bytes.push(byte);
        }
        const next = new TextDecoder('utf-8').decode(new Uint8Array(bytes));
        if (!next || next === current) break;
        current = normalizeText(next);
        if (!/[ÃÂØÙ]/.test(current)) break;
      }
    } catch (error) {
      return text;
    }

    return current;
  }

  function encodeMojibake(value) {
    const text = normalizeText(value);
    if (!text || typeof TextEncoder === 'undefined' || typeof TextDecoder === 'undefined') return '';
    try {
      return normalizeText(new TextDecoder('windows-1252').decode(new TextEncoder().encode(text)));
    } catch (error) {
      return '';
    }
  }

  function textVariants(value) {
    const variants = new Set();
    const base = normalizeText(value);
    const decoded = decodeMojibake(base);
    const encoded = encodeMojibake(base);
    const encodedDecoded = encodeMojibake(decoded);

    [base, decoded, encoded, encodedDecoded].forEach(function (item) {
      if (item) variants.add(item);
    });

    return Array.from(variants);
  }

  function registerTranslation(source, targetLang, targetText) {
    if (!source || !targetText) return;
    textVariants(source).forEach(function (sourceText) {
      if (!maps[sourceText]) maps[sourceText] = {};
      if (!maps[sourceText][targetLang]) maps[sourceText][targetLang] = targetText;
    });
  }

  entries.forEach(function (entry) {
    const langs = Object.keys(entry).filter(function (k) { return entry[k]; });
    langs.forEach(function (srcLang) {
      const srcText = entry[srcLang];
      langs.forEach(function (tgtLang) {
        if (tgtLang !== srcLang && entry[tgtLang]) {
          if (!maps[srcLang][srcText]) maps[srcLang][srcText] = {};
          maps[srcLang][srcText][tgtLang] = entry[tgtLang];
          registerTranslation(srcText, tgtLang, entry[tgtLang]);
        }
      });
    });
  });

  [
    { ar: 'حفظ', fr: 'Enregistrer', en: 'Save' },
    { ar: 'تعديل', fr: 'Modifier', en: 'Edit' },
    { ar: 'حذف', fr: 'Supprimer', en: 'Delete' },
    { ar: 'إضافة', fr: 'Ajouter', en: 'Add' },
    { ar: 'إلغاء', fr: 'Annuler', en: 'Cancel' },
    { ar: 'إغلاق', fr: 'Fermer', en: 'Close' },
    { ar: 'بحث', fr: 'Rechercher', en: 'Search' },
    { ar: 'رجوع', fr: 'Retour', en: 'Back' },
    { ar: 'عرض', fr: 'Voir', en: 'View' },
    { ar: 'تحديث', fr: 'Mettre à jour', en: 'Update' },
    { ar: 'تصفية', fr: 'Filtrer', en: 'Filter' },
    { ar: 'طباعة', fr: 'Imprimer', en: 'Print' },
    { ar: 'تصدير', fr: 'Exporter', en: 'Export' },
    { ar: 'المنتجات', fr: 'Produits', en: 'Products' },
    { ar: 'الطلبات', fr: 'Commandes', en: 'Orders' },
    { ar: 'العملاء', fr: 'Clients', en: 'Customers' },
    { ar: 'الموظفون', fr: 'Employés', en: 'Employees' },
    { ar: 'الإعدادات', fr: 'Paramètres', en: 'Settings' },
    { ar: 'النظام', fr: 'Système', en: 'System' },
  ].forEach(function (entry) {
    supportedLangs.forEach(function (srcLang) {
      supportedLangs.forEach(function (tgtLang) {
        if (srcLang === tgtLang) return;
        textVariants(entry[srcLang]).forEach(function (sourceText) {
          if (!maps[sourceText]) maps[sourceText] = {};
          maps[sourceText][tgtLang] = entry[tgtLang];
        });
      });
    });
  });

  // ─── Resolve current language ─────────────────────────────────────────────
  const shell = document.getElementById('admin-react-shell');
  const targetLang = normalizeLang((shell ? shell.getAttribute('data-current-lang') : null) ||
                     document.documentElement.getAttribute('lang') || 'ar');

  function translateDynamic(text) {
    const value = normalizeText(text);
    const rules = [
      {
        re: /^سعر عرض\s*(\d+)$/,
        fr: function (m) { return 'Prix offre ' + m[1]; },
        en: function (m) { return 'Offer price ' + m[1]; },
      },
      {
        re: /^سعر العرض الخاص\s*(\d+)$/,
        fr: function (m) { return 'Prix offre spéciale ' + m[1]; },
        en: function (m) { return 'Special offer price ' + m[1]; },
      },
      {
        re: /^وصف العرض الخاص\s*(\d+)$/,
        fr: function (m) { return 'Description offre spéciale ' + m[1]; },
        en: function (m) { return 'Special offer description ' + m[1]; },
      },
      {
        re: /^رابط صورة العرض الخاص\s*(\d+)$/,
        fr: function (m) { return 'Lien image offre spéciale ' + m[1]; },
        en: function (m) { return 'Special offer image URL ' + m[1]; },
      },
      {
        re: /^صورة صفحة الهبوط\s*(\d+)$/,
        fr: function (m) { return 'Image page landing ' + m[1]; },
        en: function (m) { return 'Landing page image ' + m[1]; },
      },
      {
        re: /^صورة اللون:\s*(.+)$/,
        fr: function (m) { return 'Image couleur : ' + m[1]; },
        en: function (m) { return 'Color image: ' + m[1]; },
      },
    ];

    for (let index = 0; index < rules.length; index += 1) {
      const match = value.match(rules[index].re);
      if (match && rules[index][targetLang]) {
        return rules[index][targetLang](match);
      }
    }

    return null;
  }

  // ─── Translate a single string ─────────────────────────────────────────────
  function translate(text) {
    const trimmed = normalizeText(text);
    if (!trimmed) return text;

    if (targetLang === 'ar') {
      const decodedArabic = decodeMojibake(trimmed);
      if (decodedArabic !== trimmed && /[\u0600-\u06ff]/.test(decodedArabic)) {
        return decodedArabic;
      }
    }

    const dynamic = translateDynamic(trimmed);
    if (dynamic) return dynamic;

    const candidates = textVariants(trimmed);
    for (let index = 0; index < candidates.length; index += 1) {
      const direct = maps[candidates[index]];
      if (direct && direct[targetLang]) return direct[targetLang];
    }

    const affixed = trimmed.match(/^([([{<"'«]*\s*)(.*?)(\s*[:：;؛،,.!?؟\])}>"'»]*)$/);
    if (affixed && affixed[2] && affixed[2] !== trimmed) {
      const inner = translate(affixed[2]);
      if (inner !== affixed[2]) return affixed[1] + inner + affixed[3];
    }

    return text;
  }

  // ─── Translate text nodes (tree walker) ──────────────────────────────────
  const SKIP_TAGS = new Set(['SCRIPT', 'STYLE', 'INPUT', 'TEXTAREA', 'PRE', 'CODE', 'SELECT', 'OPTION', 'NOSCRIPT']);

  function translateNode(root) {
    if (!root) return;
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
      acceptNode: function (node) {
        let p = node.parentElement;
        while (p) {
          if (SKIP_TAGS.has(p.tagName)) return NodeFilter.FILTER_REJECT;
          p = p.parentElement;
        }
        return node.textContent.trim() ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_SKIP;
      }
    });
    const nodes = [];
    let node;
    while ((node = walker.nextNode())) nodes.push(node);
    nodes.forEach(function (n) {
      const original = n.textContent;
      const trimmed = normalizeText(original);
      const translated = translate(trimmed);
      if (translated !== trimmed) {
        const leading = original.match(/^(\s*)/)[1];
        const trailing = original.match(/(\s*)$/)[1];
        n.textContent = leading + translated + trailing;
      }
    });
  }

  // ─── Translate placeholder/title/aria/value attributes ─────────────────────
  function translateAttrs() {
    ['placeholder', 'title', 'aria-label', 'alt', 'data-original-title', 'data-bs-original-title'].forEach(function (attr) {
      document.querySelectorAll('[' + attr + ']').forEach(function (el) {
        const orig = el.getAttribute(attr);
        const t = translate(orig);
        if (t !== orig) el.setAttribute(attr, t);
      });
    });

    document.querySelectorAll('input[type="button"], input[type="submit"], input[type="reset"], button[value]').forEach(function (el) {
      const orig = el.getAttribute('value');
      const t = translate(orig);
      if (t !== orig) el.setAttribute('value', t);
    });

    document.querySelectorAll('option').forEach(function (el) {
      const orig = normalizeText(el.textContent);
      const t = translate(orig);
      if (t !== orig) el.textContent = t;
    });
  }

  // ─── Patch confirm/alert dialogs ──────────────────────────────────────────
  const _confirm = window.confirm;
  window.confirm = function (msg) { return _confirm.call(window, translate(msg) || msg); };
  const _alert   = window.alert;
  window.alert   = function (msg) { return _alert.call(window, translate(msg) || msg); };

  // ─── Patch onclick confirm() strings ─────────────────────────────────────
  function patchOnclick() {
    document.querySelectorAll('[onclick]').forEach(function (el) {
      const oc = el.getAttribute('onclick') || '';
      const m = oc.match(/confirm\(['"](.+?)['"]\)/);
      if (m) {
        const t = translate(m[1]);
        if (t !== m[1]) {
          el.setAttribute('onclick', oc.replace(m[0], "confirm('" + t.replace(/'/g, "\\'") + "')"));
        }
      }
    });
  }

  // ─── DataTables language ─────────────────────────────────────────────────
  const dtLang = {
    fr: { search: 'Rechercher :', lengthMenu: 'Afficher _MENU_ entrées',
          info: 'Affichage de _START_ à _END_ sur _TOTAL_ entrées',
          infoEmpty: 'Aucune entrée disponible', infoFiltered: '(filtré de _MAX_ entrées au total)',
          paginate: { first: 'Premier', last: 'Dernier', next: 'Suivant', previous: 'Précédent' },
          emptyTable: 'Aucune donnée disponible', zeroRecords: 'Aucun résultat trouvé',
          loadingRecords: 'Chargement...', processing: 'Traitement...' },
    en: { search: 'Search:', lengthMenu: 'Show _MENU_ entries',
          info: 'Showing _START_ to _END_ of _TOTAL_ entries',
          infoEmpty: 'No entries available', infoFiltered: '(filtered from _MAX_ total entries)',
          paginate: { first: 'First', last: 'Last', next: 'Next', previous: 'Previous' },
          emptyTable: 'No data available', zeroRecords: 'No matching records found',
          loadingRecords: 'Loading...', processing: 'Processing...' }
  };

  if (typeof jQuery !== 'undefined') {
    function patchDT(fn) {
      return function (opts) {
        opts = opts || {};
        if (dtLang[targetLang]) opts.language = dtLang[targetLang];
        return fn.call(this, opts);
      };
    }
    if (jQuery.fn.DataTable)  jQuery.fn.DataTable  = patchDT(jQuery.fn.DataTable);
    if (jQuery.fn.dataTable)  jQuery.fn.dataTable  = patchDT(jQuery.fn.dataTable);
  }

  // ─── Run all translations ─────────────────────────────────────────────────
  let isTranslating = false;
  let scheduledRun = null;

  function runAll() {
    if (!document.body || isTranslating) return;
    isTranslating = true;
    try {
      translateNode(document.body);
      translateAttrs();
      patchOnclick();
    } finally {
      isTranslating = false;
    }
  }

  function scheduleRun() {
    if (scheduledRun) window.clearTimeout(scheduledRun);
    scheduledRun = window.setTimeout(function () {
      scheduledRun = null;
      runAll();
    }, 120);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', runAll);
  } else {
    runAll();
  }
  setTimeout(runAll, 500);
  setTimeout(runAll, 2000);
  setTimeout(runAll, 4000);

  if (window.MutationObserver && document.body) {
    const observer = new MutationObserver(function () {
      if (!isTranslating) scheduleRun();
    });
    observer.observe(document.body, {
      childList: true,
      subtree: true,
      characterData: true,
      attributes: true,
      attributeFilter: ['placeholder', 'title', 'aria-label', 'alt', 'value', 'data-original-title', 'data-bs-original-title'],
    });
  }

  document.addEventListener('spa:pageLoaded', scheduleRun);

  window.adminTranslate    = translate;
  window.adminRunTranslations = runAll;
  window.adminTranslationTarget = targetLang;

})();

