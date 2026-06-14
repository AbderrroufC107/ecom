import {
  IconAdjustments,
  IconArchive,
  IconBell,
  IconBox,
  IconBuildingStore,
  IconCashBanknote,
  IconCategory,
  IconChartBar,
  IconClipboardList,
  IconCreditCard,
  IconDatabase,
  IconFileAnalytics,
  IconFileText,
  IconHeartbeat,
  IconKey,
  IconLayoutDashboard,
  IconLifebuoy,
  IconMessage,
  IconPackage,
  IconPhoto,
  IconRobot,
  IconSettings,
  IconShieldCheck,
  IconShoppingBag,
  IconShoppingCart,
  IconSlideshow,
  IconTruck,
  IconUsers,
  IconUsersGroup,
  IconWorld,
} from '@tabler/icons-react'

export const sectionLabels = {
  overview: 'نظرة عامة',
  sales: 'المبيعات',
  catalog: 'المتجر والمنتجات',
  people: 'الفريق والعملاء',
  content: 'المحتوى',
  finance: 'الفوترة',
  automation: 'الأتمتة والذكاء',
  system: 'النظام',
}

export const sectionIcons = {
  overview: IconLayoutDashboard,
  sales: IconShoppingCart,
  catalog: IconShoppingBag,
  people: IconUsersGroup,
  content: IconFileText,
  finance: IconCreditCard,
  automation: IconRobot,
  system: IconSettings,
}

export const pageNames = {
  'index.php': 'لوحة الإدارة',
  'store.php': 'المتجر',
  'store-dashboard.php': 'لوحة المتجر',
  'settings.php': 'إعدادات المتجر',
  'profile-edit.php': 'الملف الشخصي',
  'site-security.php': 'أمان الموقع',
  'system-health.php': 'صحة النظام',
  'integrations.php': 'التكاملات',
  'performance-settings.php': 'الأداء',
  'users.php': 'المستخدمون',
  'product.php': 'المنتجات',
  'product-add.php': 'إضافة منتج',
  'product-edit.php': 'تعديل منتج',
  'size.php': 'المقاسات',
  'size-add.php': 'إضافة مقاس',
  'size-edit.php': 'تعديل مقاس',
  'color.php': 'الألوان',
  'country.php': 'الولايات والدول',
  'shipping-cost.php': 'تكاليف التوصيل',
  'delivery_list.php': 'قائمة التوصيل',
  'delivery-company.php': 'شركات التوصيل',
  'order.php': 'الطلبات',
  'order-statistics.php': 'إحصائيات الطلبات',
  'order-details.php': 'تفاصيل الطلب',
  'incomplete-orders.php': 'الطلبات غير المكتملة',
  'ecotrack-diagnostics.php': 'تشخيص Ecotrack',
  'customer.php': 'العملاء',
  'customer-message.php': 'رسائل العملاء',
  'slider.php': 'السلايدر',
  'slider-add.php': 'إضافة سلايدر',
  'slider-edit.php': 'تعديل سلايدر',
  'service.php': 'الخدمات',
  'service-add.php': 'إضافة خدمة',
  'service-edit.php': 'تعديل خدمة',
  'faq.php': 'الأسئلة الشائعة',
  'page.php': 'الصفحات',
  'photo.php': 'المعرض',
  'photo-add.php': 'إضافة صورة',
  'photo-edit.php': 'تعديل صورة',
  'social-media.php': 'وسائل التواصل',
  'top-category.php': 'الفئات الرئيسية',
  'mid-category.php': 'الفئات الفرعية',
  'end-category.php': 'الفئات النهائية',
  'billing.php': 'الفوترة',
  'commission-settings.php': 'العمولات',
  'disaster-recovery.php': 'التعافي من الكوارث',
  'queue-dashboard.php': 'الطوابير',
  'audit-log.php': 'سجل التدقيق',
  'api-keys.php': 'مفاتيح API',
  'backups.php': 'النسخ الاحتياطي',
  'pixel.php': 'بكسلات التتبع',
  'pixel-add.php': 'إضافة بكسل',
  'pixel-edit.php': 'تعديل بكسل',
  'language.php': 'اللغة',
}

export function getSectionForFile(file) {
  if (!file || file === 'index.php' || file === 'store.php' || file === 'store-dashboard.php') return 'overview'
  if (/^(order|incomplete|ecotrack|exchange)/.test(file)) return 'sales'
  if (/^(product|size|color|country|shipping|delivery|top-category|mid-category|end-category)/.test(file)) return 'catalog'
  if (/^(customer|users|employee|commission)/.test(file)) return 'people'
  if (/^(slider|service|faq|page|photo|social|language)/.test(file)) return 'content'
  if (/^(billing|payment|invoice)/.test(file)) return 'finance'
  if (/^(queue|ai|recovery|telegram|pixel|automation)/.test(file)) return 'automation'
  return 'system'
}

export function getPageTitle(file, fallback = '') {
  return pageNames[file] || fallback || 'لوحة التحكم'
}

export function getIconForFile(file, section) {
  if (/^order/.test(file)) return IconClipboardList
  if (/^incomplete/.test(file)) return IconBell
  if (/^ecotrack|^delivery/.test(file)) return IconTruck
  if (/^product/.test(file)) return IconPackage
  if (/category/.test(file)) return IconCategory
  if (/^size|^color|^settings|^performance/.test(file)) return IconAdjustments
  if (/^customer/.test(file)) return IconUsers
  if (/^users|employee/.test(file)) return IconUsersGroup
  if (/^slider/.test(file)) return IconSlideshow
  if (/^photo/.test(file)) return IconPhoto
  if (/^service|^page|^faq/.test(file)) return IconFileText
  if (/^store/.test(file)) return IconBuildingStore
  if (/^billing|payment|commission/.test(file)) return IconCashBanknote
  if (/^disaster|backup/.test(file)) return IconShieldCheck
  if (/^queue/.test(file)) return IconArchive
  if (/^audit|system-health/.test(file)) return IconFileAnalytics
  if (/^api/.test(file)) return IconKey
  if (/^integrations/.test(file)) return IconWorld
  if (/^pixel/.test(file)) return IconChartBar
  if (/^telegram|message/.test(file)) return IconMessage
  if (/health/.test(file)) return IconHeartbeat
  if (/database|sql/.test(file)) return IconDatabase
  if (/help|support/.test(file)) return IconLifebuoy
  if (/^index/.test(file)) return IconLayoutDashboard
  return sectionIcons[section] || IconBox
}

export const quickActions = [
  { href: 'order.php', label: 'الطلبات', icon: IconClipboardList },
  { href: 'product-add.php', label: 'منتج جديد', icon: IconPackage },
  { href: 'incomplete-orders.php', label: 'استرجاع الطلبات', icon: IconBell },
  { href: 'system-health.php', label: 'صحة النظام', icon: IconHeartbeat },
]
