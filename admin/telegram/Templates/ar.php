<?php
/**
 * Arabic Telegram Templates Dictionary
 */

return [
    'task_assigned' => "📬 <b>مهمة جديدة مسندة إليك!</b>\n\n<b>رقم الطلب:</b> #{order_id}\n<b>المنتج:</b> {product_name}\n<b>العميل:</b> {customer_name}\n<b>الهاتف:</b> {customer_phone}\n<b>المنطقة:</b> {wilaya} - {commune}\n<b>السعر:</b> {total_price} دج\n\nيرجى قبول أو رفض المهمة باستخدام الأزرار أدناه.",
    'task_updated' => "🔄 <b>تحديث المهمة #{order_id}</b>\n\nتم تحديث تفاصيل الطلب الخاص بك. التفاصيل الجديدة:\n<b>العميل:</b> {customer_name}\n<b>الهاتف:</b> {customer_phone}\n<b>السعر:</b> {total_price} دج",
    'task_cancelled' => "❌ <b>إلغاء المهمة #{order_id}</b>\n\nتم إلغاء المهمة المسندة إليك من قبل الإدارة.",
    'task_reassigned' => "🔄 <b>إعادة تعيين المهمة #{order_id}</b>\n\nتم تحويل المهمة المسندة إليك إلى موظف آخر.",
    'deadline_reminder' => "⏳ <b>تذكير بقرب موعد انتهاء المهمة #{order_id}</b>\n\nالمهمة المسندة إليك لم تكتمل بعد وموعد التنفيذ قريباً. يرجى إنهائها في أسرع وقت.",
    'manager_message' => "💬 <b>رسالة جديدة من الإدارة:</b>\n\n{message}",
    'new_order' => "🆕 <b>طلب جديد في النظام!</b>\n\n<b>رقم الطلب:</b> #{order_id}\n<b>المنتج:</b> {product_name}\n<b>المبلغ الإجمالي:</b> {total_price} دج\n<b>العميل:</b> {customer_name}\n<b>الولاية:</b> {wilaya}",
    'new_complaint' => "⚠️ <b>شكوى جديدة مقدمة!</b>\n\n<b>الموظف:</b> {employee_name}\n<b>الموضوع:</b> {subject}\n<b>نص الشكوى:</b> {message}",
    'employee_status_change' => "📊 <b>تغيير حالة المهمة #{order_id}</b>\n\nقام الموظف <b>{employee_name}</b> بتغيير حالة المهمة إلى: <b>{status}</b>",
    'task_accepted' => "✅ <b>قبول المهمة #{order_id}</b>\n\nقام الموظف <b>{employee_name}</b> بقبول المهمة المسندة إليه.",
    'task_rejected' => "🚫 <b>رفض المهمة #{order_id}</b>\n\nقام الموظف <b>{employee_name}</b> برفض المهمة المسندة إليه.\n<b>السبب:</b> {reason}",
    'task_started' => "🚀 <b>بدء تنفيذ المهمة #{order_id}</b>\n\nقام الموظف <b>{employee_name}</b> ببدء العمل على المهمة.",
    'task_completed' => "🎉 <b>إكمال المهمة #{order_id}</b>\n\nقام الموظف <b>{employee_name}</b> بإكمال المهمة بنجاح.",
    'employee_registered' => "👤 <b>تسجيل موظف جديد!</b>\n\n<b>الاسم:</b> {employee_name}\n<b>البريد الإلكتروني:</b> {email}",
    'daily_summary' => "📈 <b>التقرير اليومي للمتجر</b>\n\n<b>التاريخ:</b> {date}\n<b>إجمالي طلبات اليوم:</b> {total_today}\n<b>الطلبات المؤكدة اليوم:</b> {confirmed_today}\n<b>الطلبات الملغاة اليوم:</b> {cancelled_today}\n<b>إجمالي الإيرادات اليومية:</b> {revenue_today} دج",
    'emergency_notification' => "🚨 <b>تنبيه عاجل من النظام!</b>\n\n{message}",
    'complaint_received' => "📥 <b>تم استلام شكواك</b>\n\nلقد تم تسجيل شكواك بنجاح وسيقوم المدير بمراجعتها قريباً.\n<b>الموضوع:</b> {subject}"
];
