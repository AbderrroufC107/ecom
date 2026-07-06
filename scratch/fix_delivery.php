<?php
$file = 'c:/xampp/htdocs/ecom/admin/delivery_list.php';
$lines = file($file);

$replacement = <<<EOF
                <?php if ((int) \$active_company['price_count'] > 0): ?>
                    <p class="muted"><?= \$has_filters ? 'نتيجة التصفية الحالية: ' : 'المعروض حالياً: '; ?><strong><?= number_format(\$filtered_prices_count); ?></strong> من أصل <strong><?= number_format((int) \$active_company['price_count']); ?></strong> سعر.</p>
                    <?php if (\$filtered_prices_count > 0): ?>
                        <div class="row price-cards">
                            <?php foreach (\$filtered_prices as \$idx => \$price): ?>
                                <?php \$is_office = (\$price['delivery_type'] === 'مكتب'); ?>
                                <div class="col-lg-3 col-md-4 col-sm-6">
                                    <div class="price-card <?= \$is_office ? 'office' : 'home'; ?>">
                                        <div class="price-card-head">
                                            <span class="price-wilaya"><?= htmlspecialchars(\$price['wilaya'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span class="type-pill <?= \$is_office ? 'office' : 'home'; ?>"><?= \$is_office ? 'مكتب' : 'منزل'; ?></span>
                                        </div>
                                        <div class="price-amount"><?= htmlspecialchars(delivery_money(\$price['price']), ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="price-card-actions">
                                            <a href="add_edit_delivery.php?edit_price=<?= (int) \$price['id']; ?>&active_company=<?= \$active_company_id; ?>" class="btn btn-primary btn-xs"><i class="fa fa-pencil"></i></a>
                                            <a href="delivery_list.php?delete_price=<?= (int) \$price['id']; ?>" class="btn btn-danger btn-xs" onclick="return confirm('هل تريد حذف سعر هذه الولاية؟');"><i class="fa fa-trash"></i></a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>

EOF;

// Replace lines 401 to 414 (index 401 to 414 is actually lines 402-415 in normal 1-based indexing)
array_splice($lines, 401, 14, [$replacement]);

file_put_contents($file, implode("", $lines));
echo "Fixed by line replacement.\n";
