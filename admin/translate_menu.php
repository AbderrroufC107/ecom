<?php
$file = 'header.php';
$content = file_get_contents($file);

// 1. Remove Tableau de bord (index.php)
// It looks like:
// <li class="treeview <?php if($cur_page == 'index.php') {echo 'active';} ? >">
//   <a href="index.php">
//     <i class="fa fa-dashboard"></i> <span>Tableau de bord</span>
//   </a>
// </li>
$content = preg_replace('/<li class="treeview <\?php if\(\$cur_page == \'index\.php\'\).*?<\/li>/s', '', $content);

// 2. Translate everything
$translations = [
    'Panneau d\'administration' => 'لوحة التحكم',
    'Profil' => 'الملف الشخصي',
    'Deconnexion' => 'تسجيل الخروج',
    'Parametres du site' => 'إعدادات الموقع',
    'Parametres de la boutique' => 'إعدادات المتجر',
    'Tailles' => 'المقاسات',
    'Couleurs' => 'الألوان',
    'Pays' => 'الولايات / الدول',
    'Frais de livraison' => 'تكاليف التوصيل',
    'Categorie principale' => 'الفئات الرئيسية',
    'Sous-categorie' => 'الفئات الفرعية',
    'Categorie finale' => 'الفئات النهائية',
    'Gestion des produits' => 'إدارة المنتجات',
    'Gestion des commandes' => 'إدارة الطلبات',
    'Statistiques des commandes' => 'إحصائيات الطلبات',
    'Suivi ECOTRACK' => 'تتبع الشحنات',
    'Commandes incompletes' => 'الطلبات المعلقة/الناقصة',
    'Gestion des sliders' => 'إدارة السلايدر',
    'Services' => 'الخدمات',
    'Clients enregistres' => 'العملاء المسجلين',
    'Parametres des pages' => 'إعدادات الصفحات',
    'Societes de livraison' => 'شركات التوصيل',
    'Pixels Tracking' => 'بكسلات التتبع',
    'Reseaux sociaux' => 'وسائل التواصل',
    'Gestion des utilisateurs' => 'إدارة المستخدمين',
    'Afficher ou masquer le menu' => 'إظهار أو إخفاء القائمة'
];

foreach ($translations as $fr => $ar) {
    // We only want to translate text within HTML tags or specific strings, to be safe.
    // Actually, simple str_replace is safe enough since these are exact UI strings.
    $content = str_replace($fr, $ar, $content);
}

// Ensure the Arabic is properly set and the menu is RTL-friendly if needed.
// Ecom dashboard is already RTL as seen in previous tasks, but just in case.

file_put_contents($file, $content);
echo "Menu translated and dashboard removed!";
