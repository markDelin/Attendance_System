<?php
// orders.php - Order Management Dashboard
require_once 'includes/db.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Order Management | QR Tools</title>
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php include 'includes/theme_loader.php'; ?>
    <style>
        .order-table {
            width: 100%; border-collapse: separate; border-spacing: 0 1rem; margin-top: 1rem;
        }
        .order-row {
            background: var(--bg-card); border: none; 
            box-shadow: var(--shadow-neu-out-sm);
            transition: all 0.4s var(--ease-out-expo);
        }
        .order-row td {
            padding: 1.5rem 1.25rem; border: none;
        }
        .order-row td:first-child { border-top-left-radius: var(--radius-md); border-bottom-left-radius: var(--radius-md); }
        .order-row td:last-child { border-top-right-radius: var(--radius-md); border-bottom-right-radius: var(--radius-md); }
        
        .status-badge {
            padding: 0.5rem 1rem; border-radius: 50px; font-size: 0.7rem; font-weight: 800; 
            text-transform: uppercase; letter-spacing: 0.05em;
            box-shadow: var(--shadow-neu-out-sm);
        }
        .status-pending { background: #fffbeb; color: #b45309; }
        .status-completed { background: #f0fdf4; color: #166534; }
        .status-cancelled { background: #fef2f2; color: #991b1b; }

        .btn-action {
            width: 40px; height: 40px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center;
            border: none; background: var(--bg-card); color: var(--text-muted); 
            transition: all 0.2s; box-shadow: var(--shadow-neu-out-sm);
            cursor: pointer;
        }
        .btn-action:hover { box-shadow: var(--shadow-neu-in-sm); transform: scale(0.95); }
        .btn-complete:hover { color: #10b981; }
        .btn-cancel:hover { color: #ef4444; }
    </style>
</head>
<body>

    <?php include 'includes/navbar.php'; ?>

    <main class="container">
        <div style="margin-top: 2rem; margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h1 style="margin: 0; font-size: 2rem; font-weight: 800; letter-spacing: -0.04em;">Order Management</h1>
                <p style="color: var(--text-muted); font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">Transaction Log & Fulfillment</p>
            </div>
            <a href="manage_products.php" class="btn btn-primary" style="padding: 0.75rem 1.5rem; border-radius: 12px; font-weight: 800; font-size: 0.85rem;">
                <i class="bi bi-gear"></i> Manage Inventory
            </a>
        </div>

        <div class="animate-fade-up">
            <table class="order-table">
                <thead>
                    <tr style="text-align: left; font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.1em;">
                        <th style="padding: 0 1.25rem 0.75rem;">Date</th>
                        <th style="padding: 0 1.25rem 0.75rem;">Customer</th>
                        <th style="padding: 0 1.25rem 0.75rem;">Product</th>
                        <th style="padding: 0 1.25rem 0.75rem;">Total</th>
                        <th style="padding: 0 1.25rem 0.75rem;">Status</th>
                        <th style="padding: 0 1.25rem 0.75rem; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $pdo->query("
                        SELECT o.*, p.name as product_name 
                        FROM orders o 
                        LEFT JOIN products p ON o.product_id = p.id 
                        ORDER BY o.created_at DESC
                    ");
                    while ($order = $stmt->fetch()):
                        $date = date('M d, H:i', strtotime($order['created_at']));
                    ?>
                        <tr class="order-row hover-lift">
                            <td style="font-family: monospace; font-weight: 700; font-size: 0.85rem;"><?= $date ?></td>
                            <td>
                                <b style="display: block; font-size: 0.95rem;"><?= htmlspecialchars($order['customer_name']) ?></b>
                                <small style="color: var(--text-muted); font-weight: 600; font-size: 0.7rem;">ID: <?= $order['telegram_id'] ?></small>
                            </td>
                            <td>
                                <b style="color: var(--primary);"><?= htmlspecialchars($order['product_name'] ?? 'Deleted Product') ?></b>
                                <span style="color: var(--text-muted); font-size: 0.8rem;">x<?= $order['quantity'] ?></span>
                            </td>
                            <td style="font-weight: 800; color: var(--text-main);">₱<?= number_format($order['total_price'], 2) ?></td>
                            <td>
                                <span class="status-badge status-<?= $order['status'] ?>">
                                    <?= $order['status'] ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <?php if ($order['status'] === 'pending'): ?>
                                    <button onclick="updateOrderStatus(<?= $order['id'] ?>, 'completed')" class="btn-action btn-complete hover-press" title="Mark as Completed">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                    <button onclick="updateOrderStatus(<?= $order['id'] ?>, 'cancelled')" class="btn-action btn-cancel hover-press" title="Cancel Order">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                <?php endif; ?>
                                <button onclick="deleteOrder(<?= $order['id'] ?>)" class="btn-action btn-cancel hover-press" title="Delete Order Record" style="color: #ef4444;">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </main>
    <?php include 'includes/footer.php'; ?>

    <script>
        async function updateOrderStatus(orderId, newStatus) {
            const confirm = await Swal.fire({
                title: 'Confirm Update?',
                text: `Are you sure you want to mark this order as ${newStatus}?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, update',
                cancelButtonColor: '#d33',
                background: 'var(--bg-card)',
                color: 'var(--text-main)',
                customClass: {
                    confirmButton: 'btn btn-primary hover-lift',
                    cancelButton: 'btn btn-ghost hover-press'
                }
            });

            if (confirm.isConfirmed) {
                try {
                    const response = await fetch('api/manage_orders.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'update_status', order_id: orderId, status: newStatus })
                    });
                    const res = await response.json();
                    
                    if (res.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Status Updated',
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 3000,
                            background: 'var(--bg-card)',
                            color: 'var(--text-main)'
                        });
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        throw new Error(res.error);
                    }
                } catch (e) {
                    Swal.fire('Error', e.message, 'error');
                }
            }
        }

        async function deleteOrder(orderId) {
            const confirm = await Swal.fire({
                title: 'Delete Order Record?',
                text: "This will permanently remove this transaction from the log.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete',
                cancelButtonColor: '#d33',
                background: 'var(--bg-card)',
                color: 'var(--text-main)'
            });

            if (confirm.isConfirmed) {
                try {
                    const response = await fetch('api/manage_orders.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'delete_order', id: orderId })
                    });
                    const res = await response.json();
                    if (res.success) {
                        Swal.fire('Deleted!', 'Order record removed.', 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        throw new Error(res.error);
                    }
                } catch (e) {
                    Swal.fire('Error', e.message, 'error');
                }
            }
        }
    </script>
</body>
</html>
