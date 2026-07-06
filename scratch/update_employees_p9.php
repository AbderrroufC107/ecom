<?php
$file = 'c:/xampp/htdocs/ecom/admin/employees.php';
$content = file_get_contents($file);

// 1. Update POST data processing in create
$create_post_search = <<<EOF
        \$is_active = !empty(\$_POST['is_active']) ? 1 : 0;

        if (\$full_name === '') throw new Exception('الاسم الكامل مطلوب.');
EOF;
$create_post_replace = <<<EOF
        \$is_active = !empty(\$_POST['is_active']) ? 1 : 0;
        \$assignment_weight = max(1, (int)(\$_POST['assignment_weight'] ?? 1));
        \$availability_status = \$_POST['availability_status'] ?? 'Available';
        \$max_active_orders = max(1, (int)(\$_POST['max_active_orders'] ?? 50));

        if (\$full_name === '') throw new Exception('الاسم الكامل مطلوب.');
EOF;
$content = str_replace($create_post_search, $create_post_replace, $content);

// 2. Update employee_create array
$create_arr_search = <<<EOF
            'telegram_chat_id' => \$telegram_chat_id,
            'is_active' => \$is_active
        ]);
EOF;
$create_arr_replace = <<<EOF
            'telegram_chat_id' => \$telegram_chat_id,
            'is_active' => \$is_active,
            'assignment_weight' => \$assignment_weight,
            'availability_status' => \$availability_status,
            'max_active_orders' => \$max_active_orders
        ]);
EOF;
$content = str_replace($create_arr_search, $create_arr_replace, $content);

// 3. Update POST data processing in update
$update_post_search = <<<EOF
        \$is_active = !empty(\$_POST['is_active']) ? 1 : 0;

        if (\$full_name === '') throw new Exception('الاسم الكامل مطلوب.');
EOF;
$update_post_replace = <<<EOF
        \$is_active = !empty(\$_POST['is_active']) ? 1 : 0;
        \$assignment_weight = max(1, (int)(\$_POST['assignment_weight'] ?? 1));
        \$availability_status = \$_POST['availability_status'] ?? 'Available';
        \$max_active_orders = max(1, (int)(\$_POST['max_active_orders'] ?? 50));

        if (\$full_name === '') throw new Exception('الاسم الكامل مطلوب.');
EOF;
$content = str_replace($update_post_search, $update_post_replace, $content);

// 4. Update employee_update array
$update_arr_search = <<<EOF
            'telegram_chat_id' => \$telegram_chat_id,
            'is_active' => \$is_active
        ]);
EOF;
$update_arr_replace = <<<EOF
            'telegram_chat_id' => \$telegram_chat_id,
            'is_active' => \$is_active,
            'assignment_weight' => \$assignment_weight,
            'availability_status' => \$availability_status,
            'max_active_orders' => \$max_active_orders
        ]);
EOF;
$content = str_replace($update_arr_search, $update_arr_replace, $content);

// 5. Update Table Headers
$table_headers_search = <<<EOF
                        <th>حالة التيليجرام</th>
                        <th>الحالة</th>
                        <th>تاريخ الإضافة</th>
EOF;
$table_headers_replace = <<<EOF
                        <th>حالة التيليجرام</th>
                        <th>توزيع الطلبات</th>
                        <th>تاريخ الإضافة</th>
EOF;
$content = str_replace($table_headers_search, $table_headers_replace, $content);

// 6. Update Table Row
$table_row_search = <<<EOF
                            <td><?php echo telegram_get_status_html(\$emp); ?></td>
                            <td>
                                <span class="emp-badge <?php echo \$emp['is_active'] ? 'is-active' : 'is-inactive'; ?>">
                                    <?php echo \$emp['is_active'] ? 'نشط' : 'معطل'; ?>
                                </span>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime(\$emp['created_at'])); ?></td>
EOF;
$table_row_replace = <<<EOF
                            <td><?php echo telegram_get_status_html(\$emp); ?></td>
                            <td>
                                <div style="display:flex; flex-direction:column; gap:4px;">
                                    <span class="emp-badge <?php echo \$emp['is_active'] ? 'is-active' : 'is-inactive'; ?>">
                                        حساب: <?php echo \$emp['is_active'] ? 'نشط' : 'معطل'; ?>
                                    </span>
                                    <span class="emp-badge" style="background:#eee; color:#333;">
                                        الحالة: <?php echo \$emp['availability_status']; ?>
                                    </span>
                                    <span class="emp-badge" style="background:#e0f2fe; color:#0369a1;">
                                        الوزن: <?php echo \$emp['assignment_weight']; ?> | الحد: <?php echo \$emp['max_active_orders']; ?>
                                    </span>
                                </div>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime(\$emp['created_at'])); ?></td>
EOF;
$content = str_replace($table_row_search, $table_row_replace, $content);

// 7. Update Add Modal Inputs
$add_modal_search = <<<EOF
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" class="form-check-input" value="1" id="addIsActive" checked>
                            <label class="form-check-label" for="addIsActive">نشط</label>
                        </div>
                    </div>
EOF;
$add_modal_replace = <<<EOF
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">الوزن (الحصة)</label>
                            <input type="number" name="assignment_weight" class="form-control" value="1" min="1">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">السعة القصوى</label>
                            <input type="number" name="max_active_orders" class="form-control" value="50" min="1">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">التواجد</label>
                            <select name="availability_status" class="form-control">
                                <option value="Available">متاح</option>
                                <option value="Busy">مشغول</option>
                                <option value="Break">استراحة</option>
                                <option value="Vacation">إجازة</option>
                                <option value="Offline">غير متصل</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" class="form-check-input" value="1" id="addIsActive" checked>
                            <label class="form-check-label" for="addIsActive">الحساب نشط ويستطيع تسجيل الدخول</label>
                        </div>
                    </div>
EOF;
$content = str_replace($add_modal_search, $add_modal_replace, $content);

// 8. Update Edit Modal Inputs
$edit_modal_search = <<<EOF
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" class="form-check-input" value="1" id="editIsActive">
                            <label class="form-check-label" for="editIsActive">نشط</label>
                        </div>
                    </div>
EOF;
$edit_modal_replace = <<<EOF
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">الوزن (الحصة)</label>
                            <input type="number" name="assignment_weight" id="editAssignmentWeight" class="form-control" value="1" min="1">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">السعة القصوى</label>
                            <input type="number" name="max_active_orders" id="editMaxActiveOrders" class="form-control" value="50" min="1">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">التواجد</label>
                            <select name="availability_status" id="editAvailabilityStatus" class="form-control">
                                <option value="Available">متاح</option>
                                <option value="Busy">مشغول</option>
                                <option value="Break">استراحة</option>
                                <option value="Vacation">إجازة</option>
                                <option value="Offline">غير متصل</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" class="form-check-input" value="1" id="editIsActive">
                            <label class="form-check-label" for="editIsActive">الحساب نشط ويستطيع تسجيل الدخول</label>
                        </div>
                    </div>
EOF;
$content = str_replace($edit_modal_search, $edit_modal_replace, $content);

// 9. Update JavaScript payload building
$js_search = <<<EOF
        document.getElementById('editIsActive').checked = employee.is_active == 1;
        document.getElementById('editReassignRow').style.display = employee.is_active == 1 ? 'none' : 'flex';
EOF;
$js_replace = <<<EOF
        document.getElementById('editIsActive').checked = employee.is_active == 1;
        document.getElementById('editAssignmentWeight').value = employee.assignment_weight;
        document.getElementById('editMaxActiveOrders').value = employee.max_active_orders;
        document.getElementById('editAvailabilityStatus').value = employee.availability_status;
        document.getElementById('editReassignRow').style.display = employee.is_active == 1 ? 'none' : 'flex';
EOF;
$content = str_replace($js_search, $js_replace, $content);

// 10. Update employee array payload serialization in PHP
// Actually PHP encodes the whole associative array from DB in JSON! $employees array contains these new fields natively via SELECT *
// Let's verify that. employee_search and employee_get_all run SELECT *

file_put_contents($file, $content);
echo "employees.php updated.\n";
