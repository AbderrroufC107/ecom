<?php
/**
 * French Telegram Templates Dictionary
 */

return [
    'task_assigned' => "📬 <b>Nouvelle tâche assignée !</b>\n\n<b>ID Commande:</b> #{order_id}\n<b>Produit:</b> {product_name}\n<b>Client:</b> {customer_name}\n<b>Tél:</b> {customer_phone}\n<b>Région:</b> {wilaya} - {commune}\n<b>Prix:</b> {total_price} DZD\n\nVeuillez accepter ou rejeter la tâche avec les boutons ci-dessous.",
    'task_updated' => "🔄 <b>Tâche #{order_id} mise à jour</b>\n\nLes détails de votre tâche ont été mis à jour :\n<b>Client:</b> {customer_name}\n<b>Tél:</b> {customer_phone}\n<b>Prix:</b> {total_price} DZD",
    'task_cancelled' => "❌ <b>Tâche #{order_id} annulée</b>\n\nLa tâche qui vous a été attribuée a été annulée par l'administration.",
    'task_reassigned' => "🔄 <b>Tâche #{order_id} réassignée</b>\n\nLa tâche qui vous a été attribuée a été transférée à un autre employé.",
    'deadline_reminder' => "⏳ <b>Rappel de délai pour la tâche #{order_id}</b>\n\nLa tâche qui vous est attribuée est toujours en attente et approche de son échéance. Veuillez la terminer dès que possible.",
    'manager_message' => "💬 <b>Nouveau message de l'Administration :</b>\n\n{message}",
    'new_order' => "🆕 <b>Nouvelle commande reçue !</b>\n\n<b>ID Commande:</b> #{order_id}\n<b>Produit:</b> {product_name}\n<b>Prix total:</b> {total_price} DZD\n<b>Client:</b> {customer_name}\n<b>Wilaya:</b> {wilaya}",
    'new_complaint' => "⚠️ <b>Nouvelle plainte déposée !</b>\n\n<b>Employé:</b> {employee_name}\n<b>Sujet:</b> {subject}\n<b>Message:</b> {message}",
    'employee_status_change' => "📊 <b>Changement d'état de la tâche #{order_id}</b>\n\nL'employé <b>{employee_name}</b> a changé l'état de la tâche à: <b>{status}</b>",
    'task_accepted' => "✅ <b>Tâche #{order_id} acceptée</b>\n\nL'employé <b>{employee_name}</b> a accepté la tâche attribuée.",
    'task_rejected' => "🚫 <b>Tâche #{order_id} rejetée</b>\n\nL'employé <b>{employee_name}</b> a rejeté la tâche attribuée.\n<b>Raison:</b> {reason}",
    'task_started' => "🚀 <b>Tâche #{order_id} démarrée</b>\n\nL'employé <b>{employee_name}</b> a commencé à travailler sur la tâche.",
    'task_completed' => "🎉 <b>Tâche #{order_id} terminée</b>\n\nL'employé <b>{employee_name}</b> a terminé avec succès la tâche.",
    'employee_registered' => "👤 <b>Nouvel employé enregistré !</b>\n\n<b>Nom:</b> {employee_name}\n<b>Email:</b> {email}",
    'daily_summary' => "📈 <b>Résumé quotidien de la boutique</b>\n\n<b>Date:</b> {date}\n<b>Total des commandes:</b> {total_today}\n<b>Confirmées:</b> {confirmed_today}\n<b>Annulées:</b> {cancelled_today}\n<b>Revenu total:</b> {revenue_today} DZD",
    'emergency_notification' => "🚨 <b>Alerte système urgente !</b>\n\n{message}",
    'complaint_received' => "📥 <b>Plainte reçue</b>\n\nVotre plainte a bien été enregistrée. Le manager l'examinera sous peu.\n<b>Sujet:</b> {subject}"
];
