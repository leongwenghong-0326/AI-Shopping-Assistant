<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/catalog.php';
require_once dirname(__DIR__) . '/includes/images.php';

require_admin();

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$errors = [];
$editProduct = null;

function validate_product_input(array $input): array
{
    $errors = [];
    $sku = normalize_sku((string) ($input['sku'] ?? ''));
    $barcode = normalize_barcode((string) ($input['barcode'] ?? ''));
    $name = trim((string) ($input['name'] ?? ''));
    $keywords = trim((string) ($input['detect_keywords'] ?? ''));
    $description = trim((string) ($input['description'] ?? ''));
    $priceRaw = trim((string) ($input['price'] ?? ''));

    if (!is_valid_sku($sku)) {
        $errors[] = 'SKU is required and may contain letters, numbers, hyphens, and underscores (2–40 chars).';
    }
    if ($barcode !== '' && !is_valid_barcode($barcode)) {
        $errors[] = 'Barcode contains invalid characters.';
    }
    if ($name === '' || mb_strlen($name) > 120) {
        $errors[] = 'Product name is required (max 120 characters).';
    }
    if ($keywords !== '' && mb_strlen($keywords) > 255) {
        $errors[] = 'Detection keywords must be 255 characters or fewer.';
    }
    if ($priceRaw === '' || !is_numeric($priceRaw) || (float) $priceRaw < 0) {
        $errors[] = 'Price must be a valid non-negative number.';
    }

    return [
        'errors' => $errors,
        'data' => [
            'sku' => $sku,
            'barcode' => $barcode !== '' ? $barcode : null,
            'name' => $name,
            'detect_keywords' => $keywords !== '' ? $keywords : null,
            'price' => number_format((float) $priceRaw, 2, '.', ''),
            'description' => $description !== '' ? $description : null,
        ],
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $postAction = (string) ($_POST['action'] ?? '');

    if ($postAction === 'delete') {
        $deleteId = (int) ($_POST['id'] ?? 0);
        $product = find_product_by_id($deleteId);
        if ($product) {
            delete_product_image($product['image_path'] ?? null);
            $stmt = db()->prepare('DELETE FROM products WHERE id = ?');
            $stmt->execute([$deleteId]);
            flash_set('success', 'Product deleted.');
        } else {
            flash_set('danger', 'Product not found.');
        }
        redirect('/admin/products.php');
    }

    if ($postAction === 'create' || $postAction === 'update') {
        $validated = validate_product_input($_POST);
        $errors = $validated['errors'];
        $data = $validated['data'];
        $editId = (int) ($_POST['id'] ?? 0);

        $imagePath = null;
        $oldPath = null;

        if ($postAction === 'update') {
            $existing = find_product_by_id($editId);
            if (!$existing) {
                flash_set('danger', 'Product not found.');
                redirect('/admin/products.php');
            }
            $oldPath = $existing['image_path'] ?? null;
            $imagePath = $oldPath;
        }

        if (!empty($_FILES['image']['name'])) {
            $upload = save_product_image($_FILES['image'], $postAction === 'update' ? $oldPath : null);
            if (!$upload['ok']) {
                $errors[] = $upload['error'] ?? 'Image upload failed.';
            } else {
                $imagePath = $upload['path'];
            }
        }

        if ($errors === []) {
            try {
                if ($postAction === 'create') {
                    $stmt = db()->prepare(
                        'INSERT INTO products (sku, barcode, name, detect_keywords, price, description, image_path)
                         VALUES (?, ?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([
                        $data['sku'],
                        $data['barcode'],
                        $data['name'],
                        $data['detect_keywords'],
                        $data['price'],
                        $data['description'],
                        $imagePath,
                    ]);
                    flash_set('success', 'Product created.');
                } else {
                    $stmt = db()->prepare(
                        'UPDATE products
                         SET sku = ?, barcode = ?, name = ?, detect_keywords = ?, price = ?, description = ?, image_path = ?
                         WHERE id = ?'
                    );
                    $stmt->execute([
                        $data['sku'],
                        $data['barcode'],
                        $data['name'],
                        $data['detect_keywords'],
                        $data['price'],
                        $data['description'],
                        $imagePath,
                        $editId,
                    ]);
                    flash_set('success', 'Product updated.');
                }
                redirect('/admin/products.php');
            } catch (PDOException $e) {
                error_log('Product save error: ' . $e->getMessage());
                if ((int) $e->errorInfo[1] === 1062) {
                    $errors[] = 'SKU or barcode already exists.';
                } else {
                    $errors[] = 'Unable to save product. Please try again.';
                }
            }
        }

        $action = $postAction === 'create' ? 'add' : 'edit';
        $id = $editId;
        $editProduct = array_merge($data, [
            'id' => $editId,
            'image_path' => $imagePath,
        ]);
    }
}

if (($action === 'edit' || $action === 'add') && $editProduct === null) {
    if ($action === 'edit') {
        $editProduct = find_product_by_id($id);
        if (!$editProduct) {
            flash_set('danger', 'Product not found.');
            redirect('/admin/products.php');
        }
    } else {
        $editProduct = [
            'id' => 0,
            'sku' => '',
            'barcode' => '',
            'name' => '',
            'detect_keywords' => '',
            'price' => '',
            'description' => '',
            'image_path' => null,
        ];
    }
}

$search = trim((string) ($_GET['q'] ?? ''));
$products = ($action === 'list') ? get_all_products($search !== '' ? $search : null) : [];

$pageTitle = 'Manage Products · ' . APP_NAME;
require dirname(__DIR__) . '/includes/header.php';
?>

<section class="container py-4 py-md-5">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="page-title mb-1">Manage Products</h1>
            <p class="text-muted mb-0">Add, edit, and remove catalog items used by barcode and AI matching.</p>
        </div>
        <?php if ($action === 'list'): ?>
            <a class="btn btn-primary" href="<?= e(url('admin/products.php?action=add')) ?>">
                <i class="fa-solid fa-plus me-2"></i>Add Product
            </a>
        <?php else: ?>
            <a class="btn btn-outline-secondary" href="<?= e(url('admin/products.php')) ?>">
                <i class="fa-solid fa-arrow-left me-2"></i>Back to list
            </a>
        <?php endif; ?>
    </div>

    <?php if ($action === 'add' || $action === 'edit'): ?>
        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="admin-card">
            <h2 class="h4 mb-3"><?= $action === 'add' ? 'Add Product' : 'Edit Product' ?></h2>
            <form method="post" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $action === 'add' ? 'create' : 'update' ?>">
                <input type="hidden" name="id" value="<?= (int) ($editProduct['id'] ?? 0) ?>">

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="sku">SKU *</label>
                        <input class="form-control" id="sku" name="sku" required maxlength="40"
                               value="<?= e((string) ($editProduct['sku'] ?? '')) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="barcode">Barcode</label>
                        <input class="form-control" id="barcode" name="barcode" maxlength="64"
                               value="<?= e((string) ($editProduct['barcode'] ?? '')) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="price">Price (RM) *</label>
                        <input class="form-control" id="price" name="price" type="number" step="0.01" min="0" required
                               value="<?= e((string) ($editProduct['price'] ?? '')) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="name">Product Name *</label>
                        <input class="form-control" id="name" name="name" required maxlength="120"
                               value="<?= e((string) ($editProduct['name'] ?? '')) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="detect_keywords">AI Detection Keywords</label>
                        <input class="form-control" id="detect_keywords" name="detect_keywords" maxlength="255"
                               value="<?= e((string) ($editProduct['detect_keywords'] ?? '')) ?>"
                               placeholder="comma,separated,keywords">
                        <div class="form-text">Used to help AI match this product from photos.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="4"><?= e((string) ($editProduct['description'] ?? '')) ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="image">Product Image</label>
                        <input class="form-control" type="file" id="image" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                        <div class="form-text">JPG, PNG, or WEBP. Max 5 MB.</div>
                    </div>
                    <div class="col-md-6">
                        <?php if (!empty($editProduct['image_path'])): ?>
                            <label class="form-label">Current image</label>
                            <div class="admin-thumb">
                                <img src="<?= e(product_image_url($editProduct['image_path'])) ?>" alt="Current product image">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fa-solid fa-floppy-disk me-2"></i>Save Product
                    </button>
                    <a href="<?= e(url('admin/products.php')) ?>" class="btn btn-outline-secondary btn-lg">Cancel</a>
                </div>
            </form>
        </div>
    <?php else: ?>
        <form class="search-form mb-4" method="get">
            <div class="input-group">
                <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="search" class="form-control" name="q" value="<?= e($search) ?>" placeholder="Search catalog...">
                <button class="btn btn-primary" type="submit">Search</button>
            </div>
        </form>

        <?php if ($products === []): ?>
            <div class="empty-state">
                <i class="fa-solid fa-box-open"></i>
                <h2 class="h4">No products yet</h2>
                <p class="text-muted">Add your first product to enable barcode and AI scanning.</p>
                <a class="btn btn-primary" href="<?= e(url('admin/products.php?action=add')) ?>">Add Product</a>
            </div>
        <?php else: ?>
            <div class="table-responsive admin-card p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Image</th>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Barcode</th>
                        <th>Price</th>
                        <th class="text-end">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td style="width:72px">
                                <img class="table-thumb" src="<?= e(product_image_url($product['image_path'] ?? null)) ?>" alt="">
                            </td>
                            <td>
                                <div class="fw-semibold"><?= e($product['name']) ?></div>
                                <div class="small text-muted"><?= e(mb_strimwidth((string) ($product['detect_keywords'] ?? ''), 0, 48, '…')) ?></div>
                            </td>
                            <td><code><?= e($product['sku']) ?></code></td>
                            <td><?= e((string) ($product['barcode'] ?? '—')) ?></td>
                            <td><?= e(format_price($product['price'])) ?></td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/products.php?action=edit&id=' . (int) $product['id'])) ?>">
                                    <i class="fa-solid fa-pen"></i>
                                </a>
                                <form method="post" class="d-inline" onsubmit="return confirm('Delete this product? This cannot be undone.');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
