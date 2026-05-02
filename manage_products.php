<?php
// manage_products.php - Product & Stock Management
require_once 'includes/db.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Manage Inventory | QR Tools</title>
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php include 'includes/theme_loader.php'; ?>
    <style>
        .product-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 2rem; margin-top: 1rem;
        }
        .product-card {
            background: var(--bg-card); border: none; border-radius: 20px; padding: 2rem;
            position: relative; overflow: hidden; transition: all 0.4s var(--ease-out-expo);
            box-shadow: var(--shadow-neu-out);
        }
        
        .stock-indicator {
            position: absolute; top: 1.5rem; right: 1.5rem; font-size: 1.25rem; font-weight: 900; 
            color: var(--primary); background: var(--bg-main); padding: 5px 12px; border-radius: 10px;
            box-shadow: var(--shadow-neu-in-sm);
        }
        .input-group-custom {
            margin-bottom: 1.5rem;
        }
        .input-group-custom label {
            display: block; font-size: 0.7rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 0.75rem;
        }
        .input-group-custom input {
            width: 100%; background: var(--bg-main); border: none; border-radius: 12px; padding: 0.85rem 1rem; color: var(--text-main); font-weight: 600;
            box-shadow: var(--shadow-neu-in-sm); transition: all 0.3s;
        }
        .input-group-custom input:focus { box-shadow: var(--shadow-neu-in); outline: none; }

        .btn-action {
            width: 40px; height: 40px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center;
            border: none; background: var(--bg-card); color: var(--text-muted); 
            transition: all 0.2s; box-shadow: var(--shadow-neu-out-sm);
            cursor: pointer;
        }
        .btn-action:hover { box-shadow: var(--shadow-neu-in-sm); transform: scale(0.95); }
    </style>
</head>
<body>

    <?php include 'includes/navbar.php'; ?>

    <main class="container">
        <div style="margin-top: 2rem; margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h1 style="margin: 0; font-size: 2rem; font-weight: 800; letter-spacing: -0.04em;">Inventory Control</h1>
                <p style="color: var(--text-muted); font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">Manage Stocks & Pricing</p>
            </div>
            <div style="display: flex; gap: 10px;">
                <button onclick="addNewProduct()" class="btn btn-primary hover-lift" style="border-radius: 12px; font-weight: 800; padding: 0.75rem 1.5rem;">
                    <i class="bi bi-plus-lg"></i> Add New Product
                </button>
                <a href="orders.php" class="btn btn-ghost hover-press" style="border-radius: 12px; padding: 0.75rem 1.5rem;">
                    <i class="bi bi-cart"></i> View Orders
                </a>
            </div>
        </div>

        <div class="product-grid animate-fade-up">
            <?php
            $stmt = $pdo->query("SELECT * FROM products ORDER BY id ASC");
            while ($p = $stmt->fetch()):
            ?>
                <div class="product-card hover-lift">
                    <div class="stock-indicator"><?= $p['stock'] ?></div>
                    <button onclick="deleteProduct(<?= $p['id'] ?>, '<?= htmlspecialchars($p['name']) ?>')" class="btn-action hover-press" style="position: absolute; bottom: 2rem; right: 2rem; color: #ef4444;" title="Delete Product">
                        <i class="bi bi-trash"></i>
                    </button>
                    <h3 style="margin: 0 0 1.5rem 0; font-size: 1.25rem; font-weight: 800; letter-spacing: -0.02em; padding-right: 3rem;"><?= htmlspecialchars($p['name']) ?></h3>
                    
                    <form onsubmit="saveProduct(event, <?= $p['id'] ?>)">
                        <div class="input-group-custom">
                            <label>Product Name</label>
                            <input type="text" name="name" value="<?= htmlspecialchars($p['name']) ?>" required>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="input-group-custom">
                                <label>Price (PHP)</label>
                                <input type="number" step="0.01" name="price" value="<?= $p['price'] ?>" required>
                            </div>
                            <div class="input-group-custom">
                                <label>Current Stock</label>
                                <input type="number" name="stock" value="<?= $p['stock'] ?>" required>
                            </div>
                        </div>
                        <div class="input-group-custom" style="display: flex; align-items: center; justify-content: center; gap: 12px; margin-bottom: 2rem; background: var(--bg-main); padding: 12px; border-radius: 12px; box-shadow: var(--shadow-neu-in-sm);">
                            <input type="checkbox" name="is_even_only" style="width: 20px; height: 20px; cursor: pointer; box-shadow: none;" <?= $p['is_even_only'] ? 'checked' : '' ?>>
                            <label style="margin: 0; font-size: 0.75rem; cursor: pointer;">Enforce Even Quantity Rule</label>
                        </div>
                        <button type="submit" class="btn btn-primary hover-press" style="width: calc(100% - 50px); border-radius: 12px; font-weight: 800; margin-top: 0.5rem; padding: 0.85rem;">
                            <i class="bi bi-save2"></i> Update Product
                        </button>
                    </form>
                </div>
            <?php endwhile; ?>
        </div>
    </main>
    <?php include 'includes/footer.php'; ?>

    <script>
        async function saveProduct(e, id) {
            e.preventDefault();
            const form = e.target;
            const data = {
                action: 'update_product',
                id: id,
                name: form.name.value,
                price: parseFloat(form.price.value),
                stock: parseInt(form.stock.value),
                is_even_only: form.is_even_only.checked ? 1 : 0
            };

            try {
                const response = await fetch('api/manage_orders.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const res = await response.json();
                if (res.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Stock Updated',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000,
                        background: 'var(--bg-card)',
                        color: 'var(--text-main)'
                    });
                    // Simple UI update
                    form.closest('.product-card').querySelector('.stock-indicator').innerText = data.stock;
                } else {
                    throw new Error(res.error);
                }
            } catch (e) {
                Swal.fire('Error', e.message, 'error');
            }
        }

        async function addNewProduct() {
            const { value: formValues } = await Swal.fire({
                title: 'Add New Product',
                html:
                    '<input id="swal-name" class="swal2-input" placeholder="Product Name">' +
                    '<input id="swal-price" class="swal2-input" type="number" step="0.01" placeholder="Price (PHP)">' +
                    '<input id="swal-stock" class="swal2-input" type="number" placeholder="Initial Stock">' +
                    '<div style="margin-top: 1rem; display: flex; align-items: center; justify-content: center; gap: 10px;">' +
                    '<input type="checkbox" id="swal-even" style="width: 20px; height: 20px;">' +
                    '<label for="swal-even" style="font-weight: 700; font-size: 0.9rem;">Even Quantity Only</label>' +
                    '</div>',
                focusConfirm: false,
                background: 'var(--bg-card)',
                color: 'var(--text-main)',
                showCancelButton: true,
                confirmButtonText: 'Create Product',
                preConfirm: () => {
                    return {
                        name: document.getElementById('swal-name').value,
                        price: document.getElementById('swal-price').value,
                        stock: document.getElementById('swal-stock').value,
                        is_even_only: document.getElementById('swal-even').checked ? 1 : 0
                    }
                }
            });

            if (formValues) {
                if (!formValues.name) {
                    Swal.fire('Error', 'Product name is required', 'error');
                    return;
                }
                
                try {
                    const response = await fetch('api/manage_orders.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'add_product', ...formValues })
                    });
                    const res = await response.json();
                    if (res.success) {
                        Swal.fire('Success', 'Product added successfully!', 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        throw new Error(res.error);
                    }
                } catch (e) {
                    Swal.fire('Error', e.message, 'error');
                }
            }
        }

        async function deleteProduct(id, name) {
            const confirm = await Swal.fire({
                title: 'Delete Product?',
                text: `Are you sure you want to permanently delete "${name}"?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, DELETE',
                cancelButtonColor: '#d33',
                background: 'var(--bg-card)',
                color: 'var(--text-main)'
            });

            if (confirm.isConfirmed) {
                try {
                    const response = await fetch('api/manage_orders.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'delete_product', id: id })
                    });
                    const res = await response.json();
                    if (res.success) {
                        Swal.fire('Deleted!', 'Product removed.', 'success');
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
