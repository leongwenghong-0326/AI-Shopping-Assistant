<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/catalog.php';

$search = trim((string) ($_GET['q'] ?? ''));
$products = get_all_products($search !== '' ? $search : null);

$pageTitle = 'Product Catalog · ' . APP_NAME;
require __DIR__ . '/includes/header.php';
?>

<section class="container py-4 py-md-5">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-4">
        <div>
            <h1 class="page-title mb-1">Product Catalog</h1>
            <p class="text-muted mb-0">Browse store products with official prices from the catalog.</p>
        </div>
        <form class="search-form" method="get" action="">
            <div class="input-group input-group-lg">
                <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="search" class="form-control" name="q" value="<?= e($search) ?>" placeholder="Search products...">
                <button class="btn btn-primary" type="submit">Search</button>
            </div>
        </form>
    </div>

    <?php if ($products === []): ?>
        <div class="empty-state">
            <i class="fa-solid fa-box-open"></i>
            <h2 class="h4">No products found</h2>
            <p class="text-muted mb-0">
                <?= $search !== '' ? 'Try a different search term.' : 'Administrators can add products from Manage Products.' ?>
            </p>
        </div>
    <?php else: ?>
        <div class="row g-3 g-md-4">
            <?php foreach ($products as $product): ?>
                <div class="col-6 col-md-4 col-xl-3">
                    <article class="product-card h-100">
                        <div class="product-card-image">
                            <img src="<?= e(product_image_url($product['image_path'] ?? null)) ?>"
                                 alt="<?= e($product['name']) ?>" loading="lazy">
                        </div>
                        <div class="product-card-body">
                            <div class="product-price"><?= e(format_price($product['price'])) ?></div>
                            <h2 class="product-name"><?= e($product['name']) ?></h2>
                            <div class="product-meta">
                                <span>SKU: <?= e($product['sku']) ?></span>
                                <?php if (!empty($product['barcode'])): ?>
                                    <span>Barcode: <?= e($product['barcode']) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($product['description'])): ?>
                                <p class="product-desc"><?= e(mb_strimwidth((string) $product['description'], 0, 110, '…')) ?></p>
                            <?php endif; ?>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
