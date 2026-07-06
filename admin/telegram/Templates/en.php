<?php
/**
 * English Telegram Templates Dictionary
 */

return [
    'task_assigned' => "📬 <b>New Task Assigned!</b>\n\n<b>Order ID:</b> #{order_id}\n<b>Product:</b> {product_name}\n<b>Customer:</b> {customer_name}\n<b>Phone:</b> {customer_phone}\n<b>Region:</b> {wilaya} - {commune}\n<b>Price:</b> {total_price} DZD\n\nPlease accept or reject the task using the buttons below.",
    'task_updated' => "🔄 <b>Task #{order_id} Updated</b>\n\nYour task details have been updated:\n<b>Customer:</b> {customer_name}\n<b>Phone:</b> {customer_phone}\n<b>Price:</b> {total_price} DZD",
    'task_cancelled' => "❌ <b>Task #{order_id} Cancelled</b>\n\nThe task assigned to you has been cancelled by the administration.",
    'task_reassigned' => "🔄 <b>Task #{order_id} Reassigned</b>\n\nThe task assigned to you has been reassigned to another employee.",
    'deadline_reminder' => "⏳ <b>Task #{order_id} Deadline Reminder</b>\n\nThe task assigned to you is still pending and near its deadline. Please complete it as soon as possible.",
    'manager_message' => "💬 <b>New message from Administration:</b>\n\n{message}",
    'new_order' => "🆕 <b>New Order Received!</b>\n\n<b>Order ID:</b> #{order_id}\n<b>Product:</b> {product_name}\n<b>Total Price:</b> {total_price} DZD\n<b>Customer:</b> {customer_name}\n<b>Wilaya:</b> {wilaya}",
    'new_complaint' => "⚠️ <b>New Complaint Submitted!</b>\n\n<b>Employee:</b> {employee_name}\n<b>Subject:</b> {subject}\n<b>Message:</b> {message}",
    'employee_status_change' => "📊 <b>Task #{order_id} Status Change</b>\n\nEmployee <b>{employee_name}</b> changed the task status to: <b>{status}</b>",
    'task_accepted' => "✅ <b>Task #{order_id} Accepted</b>\n\nEmployee <b>{employee_name}</b> accepted the assigned task.",
    'task_rejected' => "🚫 <b>Task #{order_id} Rejected</b>\n\nEmployee <b>{employee_name}</b> rejected the assigned task.\n<b>Reason:</b> {reason}",
    'task_started' => "🚀 <b>Task #{order_id} Started</b>\n\nEmployee <b>{employee_name}</b> started working on the task.",
    'task_completed' => "🎉 <b>Task #{order_id} Completed</b>\n\nEmployee <b>{employee_name}</b> successfully completed the task.",
    'employee_registered' => "👤 <b>New Employee Registered!</b>\n\n<b>Name:</b> {employee_name}\n<b>Email:</b> {email}",
    'daily_summary' => "📈 <b>Daily Store Summary</b>\n\n<b>Date:</b> {date}\n<b>Total Orders Today:</b> {total_today}\n<b>Confirmed Today:</b> {confirmed_today}\n<b>Cancelled Today:</b> {cancelled_today}\n<b>Total Revenue Today:</b> {revenue_today} DZD",
    'emergency_notification' => "🚨 <b>Emergency System Alert!</b>\n\n{message}",
    'complaint_received' => "📥 <b>Complaint Received</b>\n\nYour complaint has been registered. The manager will review it soon.\n<b>Subject:</b> {subject}"
];
