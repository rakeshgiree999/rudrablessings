<?php
declare(strict_types=1);

$adminCurrent = 'products';
require __DIR__ . '/includes/admin_app.php';

$productId = isset($_GET['id']) ? max(0, (int)$_GET['id']) : 0;
$isEditing = $productId > 0;

$categories = $pdo->query('SELECT id, name, is_active FROM categories ORDER BY name')->fetchAll(PDO::FETCH_ASSOC) ?: [];
$categoryLookup = [];
foreach ($categories as $cat) {
    $categoryLookup[(int)$cat['id']] = $cat;
}
$selectedCategoryInactive = false;
const RB_MAX_PRODUCT_IMAGES = 5;
if (!defined('RB_PRODUCT_IMAGE_MAX_BYTES')) {
    define('RB_PRODUCT_IMAGE_MAX_BYTES', 4 * 1024 * 1024); // 4 MB
}
if (!defined('RB_PRODUCT_IMAGE_MAX_DIMENSION')) {
    define('RB_PRODUCT_IMAGE_MAX_DIMENSION', 2000);
}
if (!defined('RB_PRODUCT_IMAGE_ALLOWED_MIME')) {
    define('RB_PRODUCT_IMAGE_ALLOWED_MIME', [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ]);
}
if (!defined('RB_PRODUCT_IMAGE_JPEG_QUALITY')) {
    define('RB_PRODUCT_IMAGE_JPEG_QUALITY', 82);
}

if (!function_exists('rb_admin_process_product_image')) {
    /**
     * Validate, sanitise and persist an uploaded product image.
     *
     * @param array $file The single file payload (name, tmp_name, size, error).
     * @param string $slug Product slug used for filename generation.
     * @param int $position Zero-indexed slot for deterministic filenames.
     * @param string $destinationDir Absolute directory for storing images.
     * @return array{ok:bool,path?:string,error?:string}
     */
    function rb_admin_process_product_image(array $file, string $slug, int $position, string $destinationDir): array
    {
        $tmpName = $file['tmp_name'] ?? '';
        $size = (int)($file['size'] ?? 0);
        $originalName = (string)($file['name'] ?? '');

        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            return ['ok' => false, 'error' => 'Invalid file upload.'];
        }

        if ($size <= 0 || $size > RB_PRODUCT_IMAGE_MAX_BYTES) {
            return ['ok' => false, 'error' => 'Images must be 4MB or smaller.'];
        }

        $mime = '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = (string)finfo_file($finfo, $tmpName);
                finfo_close($finfo);
            }
        }
        if ($mime === '' && function_exists('mime_content_type')) {
            $mime = (string)@mime_content_type($tmpName);
        }
        if ($mime === '' || !isset(RB_PRODUCT_IMAGE_ALLOWED_MIME[$mime])) {
            return ['ok' => false, 'error' => 'Unsupported image format. Upload JPEG, PNG, or WebP files.'];
        }

        $imageInfo = @getimagesize($tmpName);
        if (!$imageInfo) {
            return ['ok' => false, 'error' => 'Unable to read image metadata.'];
        }

        [$width, $height] = $imageInfo;
        if ($width <= 0 || $height <= 0) {
            return ['ok' => false, 'error' => 'Image dimensions are invalid.'];
        }

        $targetWidth = $width;
        $targetHeight = $height;
        if ($width > RB_PRODUCT_IMAGE_MAX_DIMENSION || $height > RB_PRODUCT_IMAGE_MAX_DIMENSION) {
            $scale = RB_PRODUCT_IMAGE_MAX_DIMENSION / max($width, $height);
            $targetWidth = max(1, (int)round($width * $scale));
            $targetHeight = max(1, (int)round($height * $scale));
        }

        switch ($mime) {
            case 'image/jpeg':
                if (!function_exists('imagecreatefromjpeg')) {
                    return ['ok' => false, 'error' => 'JPEG support is not available on the server.'];
                }
                $image = @imagecreatefromjpeg($tmpName);
                break;
            case 'image/png':
                if (!function_exists('imagecreatefrompng')) {
                    return ['ok' => false, 'error' => 'PNG support is not available on the server.'];
                }
                $image = @imagecreatefrompng($tmpName);
                break;
            case 'image/webp':
                if (!function_exists('imagecreatefromwebp')) {
                    return ['ok' => false, 'error' => 'WebP support is not available on the server.'];
                }
                $image = @imagecreatefromwebp($tmpName);
                break;
            default:
                $image = false;
        }

        if (!$image) {
            return ['ok' => false, 'error' => 'Unable to read the uploaded image.'];
        }

        $processed = $image;
        if ($targetWidth !== $width || $targetHeight !== $height) {
            $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
            if ($mime === 'image/png' || $mime === 'image/webp') {
                imagealphablending($canvas, false);
                imagesavealpha($canvas, true);
            }
            imagecopyresampled($canvas, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
            imagedestroy($image);
            $processed = $canvas;
        }

        $extension = RB_PRODUCT_IMAGE_ALLOWED_MIME[$mime];
        $useWebp = $mime === 'image/webp' && function_exists('imagewebp');
        if ($mime === 'image/webp' && !$useWebp) {
            $extension = 'jpg';
        }

        $filename = sprintf('%s-%s-%d.%s', $slug, date('YmdHis'), $position + 1, $extension);
        $destination = rtrim($destinationDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

        $saved = false;
        switch ($mime) {
            case 'image/jpeg':
                imageinterlace($processed, true);
                $saved = imagejpeg($processed, $destination, RB_PRODUCT_IMAGE_JPEG_QUALITY);
                break;
            case 'image/png':
                imagealphablending($processed, false);
                imagesavealpha($processed, true);
                $saved = imagepng($processed, $destination, 6);
                break;
            case 'image/webp':
                if ($useWebp) {
                    $saved = imagewebp($processed, $destination, RB_PRODUCT_IMAGE_JPEG_QUALITY);
                } else {
                    $saved = imagejpeg($processed, $destination, RB_PRODUCT_IMAGE_JPEG_QUALITY);
                }
                break;
        }

        imagedestroy($processed);

        if (!$saved || !is_file($destination)) {
            return ['ok' => false, 'error' => 'Failed to save processed image.'];
        }

        @chmod($destination, 0644);

        $relative = str_replace('\\', '/', str_replace(dirname(__DIR__) . DIRECTORY_SEPARATOR, '', $destination));
        return ['ok' => true, 'path' => $relative];
    }
}

$form = [
    'name' => '',
    'slug' => '',
    'price' => '',
    'cost_price' => '',
    'stock' => '',
    'status' => 'active',
    'short_description' => '',
    'description' => '',
    'category_id' => '',
    'is_featured' => 0,
    'is_new_arrival' => 0,
    'images' => array_fill(0, RB_MAX_PRODUCT_IMAGES, ['path' => '', 'alt' => '']),
    'primary_image' => 0,
];
$errors = [];

if ($isEditing) {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $productId]);
    $product = $stmt->fetch();
    if (!$product) {
        header('Location: products.php?notfound=1');
        exit;
    }
    $form = array_merge($form, [
        'name' => $product['name'],
        'slug' => $product['slug'],
        'price' => number_format((float)$product['price'], 2, '.', ''),
        'cost_price' => number_format((float)$product['cost_price'], 2, '.', ''),
        'stock' => (string)$product['stock'],
        'status' => $product['status'],
        'short_description' => $product['short_description'],
        'description' => $product['description'],
        'is_featured' => (int)$product['is_featured'],
        'is_new_arrival' => (int)$product['is_new_arrival'],
    ]);
    $catStmt = $pdo->prepare('SELECT category_id FROM product_categories WHERE product_id = :id LIMIT 1');
    $catStmt->execute(['id' => $productId]);
    $form['category_id'] = (string)($catStmt->fetchColumn() ?: '');

    $imageStmt = $pdo->prepare('SELECT image_path, alt_text FROM product_images WHERE product_id = :id ORDER BY sort_order ASC, id ASC');
    $imageStmt->execute(['id' => $productId]);
    $images = $imageStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($images as $index => $row) {
        $form['images'][$index] = [
            'path' => (string)($row['image_path'] ?? ''),
            'alt' => (string)($row['alt_text'] ?? ''),
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !rb_csrf_validate_request()) {
    $errors['general'] = 'Your session expired. Please refresh and try again.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['name'] = trim((string)($_POST['name'] ?? ''));
    $form['slug'] = trim((string)($_POST['slug'] ?? ''));
    $form['price'] = trim((string)($_POST['price'] ?? ''));
    $form['cost_price'] = trim((string)($_POST['cost_price'] ?? ''));
    $form['stock'] = trim((string)($_POST['stock'] ?? ''));
    $form['status'] = in_array($_POST['status'] ?? 'active', ['active', 'inactive', 'archived'], true) ? $_POST['status'] : 'active';
    $form['short_description'] = trim((string)($_POST['short_description'] ?? ''));
    $form['description'] = trim((string)($_POST['description'] ?? ''));
    $form['category_id'] = trim((string)($_POST['category_id'] ?? ''));
    $form['is_featured'] = isset($_POST['is_featured']) ? 1 : 0;
    $form['is_new_arrival'] = isset($_POST['is_new_arrival']) ? 1 : 0;

    $rawImages = $_POST['images'] ?? [];
    $form['images'] = [];
    for ($i = 0; $i < RB_MAX_PRODUCT_IMAGES; $i++) {
        $existing = trim((string)($rawImages[$i]['existing'] ?? ''));
        $alt = trim((string)($rawImages[$i]['alt'] ?? ''));
        $delete = !empty($rawImages[$i]['delete']);
        $form['images'][$i] = [
            'path' => $delete ? '' : $existing,
            'alt' => $alt,
        ];
    }
    $form['primary_image'] = isset($_POST['primary_image']) ? max(0, min(RB_MAX_PRODUCT_IMAGES - 1, (int)$_POST['primary_image'])) : 0;

    if ($form['name'] === '') {
        $errors['name'] = 'Name is required.';
    }
    if ($form['price'] === '' || !is_numeric($form['price'])) {
        $errors['price'] = 'Enter a valid price.';
    }
    if ($form['cost_price'] === '' || !is_numeric($form['cost_price'])) {
        $errors['cost_price'] = 'Enter a valid cost price.';
    } elseif ((float)$form['cost_price'] < 0) {
        $errors['cost_price'] = 'Cost price must not be negative.';
    } elseif (is_numeric($form['price']) && (float)$form['cost_price'] > (float)$form['price']) {
        $errors['cost_price'] = 'Cost price cannot exceed the sell price.';
    }
    if ($form['stock'] === '' || !ctype_digit($form['stock'])) {
        $errors['stock'] = 'Stock must be a non-negative integer.';
    }
    if ($form['short_description'] === '') {
        $errors['short_description'] = 'Short description is required.';
    }
    if ($form['category_id'] === '') {
        $errors['category_id'] = 'Select a category.';
    }

    if ($form['slug'] === '') {
        $form['slug'] = rb_admin_slugify($form['name']);
    } else {
        $form['slug'] = rb_admin_slugify($form['slug']);
    }

    $slugCheckSql = 'SELECT id FROM products WHERE slug = :slug';
    $slugParams = ['slug' => $form['slug']];
    if ($isEditing) {
        $slugCheckSql .= ' AND id <> :id';
        $slugParams['id'] = $productId;
    }
    $slugStmt = $pdo->prepare($slugCheckSql);
    $slugStmt->execute($slugParams);
    if ($slugStmt->fetch()) {
        $errors['slug'] = 'This slug is already taken.';
    }

    $imagesForSave = [];
    if (!$errors) {
        $files = $_FILES['image_files'] ?? [
            'name' => [],
            'type' => [],
            'tmp_name' => [],
            'error' => [],
            'size' => [],
        ];
        $mediaRoot = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'media';
        $productDir = $mediaRoot . DIRECTORY_SEPARATOR . 'products';
        if (!is_dir($productDir)) {
            @mkdir($productDir, 0755, true);
        }

        $slugDir = $productDir . DIRECTORY_SEPARATOR . $form['slug'];
        if (!is_dir($slugDir)) {
            @mkdir($slugDir, 0755, true);
        }

        for ($i = 0; $i < RB_MAX_PRODUCT_IMAGES; $i++) {
            $existing = trim((string)($rawImages[$i]['existing'] ?? ''));
            $alt = trim((string)($rawImages[$i]['alt'] ?? ''));
            $delete = !empty($rawImages[$i]['delete']);
            $finalPath = '';
            $fileError = $files['error'][$i] ?? UPLOAD_ERR_NO_FILE;

            if ($fileError !== UPLOAD_ERR_NO_FILE) {
                if ($fileError === UPLOAD_ERR_OK) {
                    $uploadResult = rb_admin_process_product_image([
                        'name' => $files['name'][$i] ?? '',
                        'tmp_name' => $files['tmp_name'][$i] ?? '',
                        'size' => $files['size'][$i] ?? 0,
                        'error' => $fileError,
                    ], $form['slug'], $i, $slugDir);

                    if ($uploadResult['ok']) {
                        $finalPath = str_replace('\\', '/', (string)$uploadResult['path']);
                    } else {
                        $errors["image_$i"] = $uploadResult['error'] ?? 'Failed to process image.';
                    }
                } else {
                    $errors["image_$i"] = 'Failed to upload image.';
                }
            } elseif (!$delete && $existing !== '') {
                $finalPath = $existing;
            }

            if (($delete || ($finalPath !== '' && $existing !== '' && $finalPath !== $existing)) && $existing !== '') {
                $existingFull = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $existing);
                $mediaRootReal = realpath($mediaRoot);
                $existingReal = realpath($existingFull);
                if ($existingReal && $mediaRootReal && strpos($existingReal, $mediaRootReal) === 0 && is_file($existingReal)) {
                    @unlink($existingReal);
                }
            }

            if ($finalPath !== '') {
                $imagesForSave[] = ['path' => $finalPath, 'alt' => $alt];
                $form['images'][$i]['path'] = $finalPath;
            } else {
                $form['images'][$i]['path'] = $delete ? '' : $existing;
            }
        }

        if ($imagesForSave) {
            $primaryIndex = $form['primary_image'];
            if (!isset($imagesForSave[$primaryIndex])) {
                $primaryIndex = 0;
            }
            if ($primaryIndex > 0 && isset($imagesForSave[$primaryIndex])) {
                $primary = $imagesForSave[$primaryIndex];
                unset($imagesForSave[$primaryIndex]);
                array_unshift($imagesForSave, $primary);
                $imagesForSave = array_values($imagesForSave);
            }
            $form['primary_image'] = 0;
        }
    }

    if (!$errors) {
        $pdo->beginTransaction();
        try {

            if ($isEditing) {
                $updateStmt = $pdo->prepare('UPDATE products SET slug=:slug, name=:name, short_description=:short_desc, description=:description, price=:price, cost_price=:cost_price, stock=:stock, status=:status, is_featured=:featured, is_new_arrival=:new_arrival WHERE id=:id');
                $updateStmt->execute([
                    'slug' => $form['slug'],
                    'name' => $form['name'],
                    'short_desc' => $form['short_description'],
                    'description' => $form['description'],
                    'price' => number_format((float)$form['price'], 2, '.', ''),
                    'cost_price' => number_format((float)$form['cost_price'], 2, '.', ''),
                    'stock' => (int)$form['stock'],
                    'status' => $form['status'],
                    'featured' => $form['is_featured'],
                    'new_arrival' => $form['is_new_arrival'],
                    'id' => $productId,
                ]);
                $pdo->prepare('DELETE FROM product_categories WHERE product_id = :id')->execute(['id' => $productId]);
                $pdo->prepare('INSERT INTO product_categories (product_id, category_id) VALUES (:product_id, :category_id)')
                    ->execute(['product_id' => $productId, 'category_id' => (int)$form['category_id']]);
                $pdo->prepare('DELETE FROM product_images WHERE product_id = :id')->execute(['id' => $productId]);
            } else {
                $insertStmt = $pdo->prepare('INSERT INTO products (slug, name, short_description, description, price, cost_price, stock, status, is_featured, is_new_arrival) VALUES (:slug, :name, :short_desc, :description, :price, :cost_price, :stock, :status, :featured, :new_arrival)');
                $insertStmt->execute([
                    'slug' => $form['slug'],
                    'name' => $form['name'],
                    'short_desc' => $form['short_description'],
                    'description' => $form['description'],
                    'price' => number_format((float)$form['price'], 2, '.', ''),
                    'cost_price' => number_format((float)$form['cost_price'], 2, '.', ''),
                    'stock' => (int)$form['stock'],
                    'status' => $form['status'],
                    'featured' => $form['is_featured'],
                    'new_arrival' => $form['is_new_arrival'],
                ]);
                $productId = (int)$pdo->lastInsertId();
                $pdo->prepare('INSERT INTO product_categories (product_id, category_id) VALUES (:product_id, :category_id)')
                    ->execute(['product_id' => $productId, 'category_id' => (int)$form['category_id']]);
            }

            if ($imagesForSave) {
                $insertImage = $pdo->prepare('INSERT INTO product_images (product_id, image_path, alt_text, sort_order) VALUES (:product_id, :path, :alt, :sort_order)');
                foreach ($imagesForSave as $index => $image) {
                    $insertImage->execute([
                        'product_id' => $productId,
                        'path' => $image['path'],
                        'alt' => $image['alt'] !== '' ? $image['alt'] : $form['name'],
                        'sort_order' => $index + 1,
                    ]);
                }
            }

            $pdo->commit();
            header('Location: products.php?saved=1');
            exit;
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errors['general'] = 'Unable to save product. ' . $e->getMessage();
        }
    }
}

for ($i = 0; $i < RB_MAX_PRODUCT_IMAGES; $i++) {
    if (!isset($form['images'][$i])) {
        $form['images'][$i] = ['path' => '', 'alt' => ''];
    }
}

$primaryValid = isset($form['images'][$form['primary_image']]) && $form['images'][$form['primary_image']]['path'] !== '';
if (!$primaryValid) {
    foreach ($form['images'] as $index => $image) {
        if ($image['path'] !== '') {
            $form['primary_image'] = (int)$index;
            $primaryValid = true;
            break;
        }
    }
}

if ($form['category_id'] !== '') {
    $selectedId = (int)$form['category_id'];
    if (isset($categoryLookup[$selectedId]) && !(int)$categoryLookup[$selectedId]['is_active']) {
        $selectedCategoryInactive = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $isEditing ? 'Edit' : 'Add' ?> Product | <?= rb_escape($brandName) ?> Admin</title>
  <link rel="icon" href="<?= rb_asset('../media/logo.png') ?>" type="image/png">
  <link rel="apple-touch-icon" href="<?= rb_asset('../media/logo.png') ?>">
  <link rel="stylesheet" href="<?= rb_asset('../admin/css/admin.css') ?>">
  <style>
    .form-grid {
      display: grid;
      gap: 16px;
    }
    .form-grid.double {
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    }
    .form-grid .checkbox-row {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 14px;
    }
    .form-grid .checkbox-row input[type="checkbox"] {
      width: auto;
    }
    .form-grid label {
      display: flex;
      flex-direction: column;
      gap: 6px;
      font-size: 14px;
    }
    .form-grid input,
    .form-grid textarea,
    .form-grid select {
      padding: 10px 12px;
      border-radius: 10px;
      border: 1px solid #d1d5db;
      font-size: 14px;
    }
    .form-grid textarea {
      min-height: 120px;
      resize: vertical;
    }
    .image-grid {
      display: grid;
      gap: 12px;
    }
    .image-entry {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 12px;
      padding: 12px;
      border: 1px solid #e5e7eb;
      border-radius: 12px;
      background: #f9fafb;
    }
    .primary-radio {
      align-self: end;
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 13px;
    }
    .error-text {
      color: #b91c1c;
      font-size: 12px;
    }
  </style>
</head>
<body>
  <div class="admin-shell">
    <aside class="admin-sidebar">
      <a class="admin-brand" href="index.php">
        <img src="../media/logo.png" alt="<?= rb_escape($brandName) ?>" style="width:32px;height:32px;">
        <span><?= rb_escape($brandName) ?> Admin</span>
      </a>
      <nav class="admin-nav">
        <?php foreach ($adminNavItems as $item): ?>
          <?php $active = $item['key'] === $adminCurrent; ?>
          <a href="<?= rb_escape($item['href']) ?>"<?= $active ? ' class="active"' : '' ?>>
            <svg viewBox="0 0 24 24" aria-hidden="true"><?= rb_admin_icon($item['icon']) ?></svg>
            <span><?= rb_escape($item['label']) ?></span>
          </a>
        <?php endforeach; ?>
      </nav>
    </aside>

    <div class="admin-main">
      <header class="admin-topbar">
        <div>
          <h1 style="margin:0;font-size:22px;"><?= $isEditing ? 'Edit product' : 'Add product' ?></h1>
          <p style="margin:4px 0 0;color:var(--admin-muted);font-size:14px;">Update catalogue information and inventory.</p>
        </div>
        <div class="admin-actions">
          <a class="admin-btn secondary" href="products.php">Back to products</a>
        </div>
      </header>

      <main class="admin-content">
        <section class="admin-section">
          <?php if (isset($errors['general'])): ?>
            <div class="notice error" style="margin-bottom:16px;"><?= rb_escape($errors['general']) ?></div>
          <?php endif; ?>
          <form method="post" novalidate class="form-grid" enctype="multipart/form-data">
            <?= rb_csrf_input() ?>
            <div class="form-grid double">
              <label>
                Product name
                <input name="name" value="<?= rb_escape($form['name']) ?>" required>
                <?php if (isset($errors['name'])): ?><span class="error-text"><?= rb_escape($errors['name']) ?></span><?php endif; ?>
              </label>
              <label>
                Slug
                <input name="slug" value="<?= rb_escape($form['slug']) ?>" placeholder="auto-generated if left blank">
                <?php if (isset($errors['slug'])): ?><span class="error-text"><?= rb_escape($errors['slug']) ?></span><?php endif; ?>
              </label>
            </div>

            <div class="form-grid double">
              <label>
                Price
                <input name="price" value="<?= rb_escape($form['price']) ?>" required>
                <?php if (isset($errors['price'])): ?><span class="error-text"><?= rb_escape($errors['price']) ?></span><?php endif; ?>
              </label>
              <label>
                Cost price
                <input name="cost_price" value="<?= rb_escape($form['cost_price']) ?>" required>
                <?php if (isset($errors['cost_price'])): ?><span class="error-text"><?= rb_escape($errors['cost_price']) ?></span><?php endif; ?>
              </label>
            </div>

            <div class="form-grid double">
              <label>
                Stock
                <input name="stock" value="<?= rb_escape($form['stock']) ?>" required>
                <?php if (isset($errors['stock'])): ?><span class="error-text"><?= rb_escape($errors['stock']) ?></span><?php endif; ?>
              </label>
              <label>
                Status
                <select name="status">
                  <option value="active"<?= $form['status'] === 'active' ? ' selected' : '' ?>>Active</option>
                  <option value="inactive"<?= $form['status'] === 'inactive' ? ' selected' : '' ?>>Inactive</option>
                  <option value="archived"<?= $form['status'] === 'archived' ? ' selected' : '' ?>>Archived</option>
                </select>
              </label>
            </div>

            <label>
              Category
              <select name="category_id" required>
                <option value="">Select category</option>
                <?php foreach ($categories as $category): ?>
                  <?php $inactive = !(int)$category['is_active']; ?>
                  <option value="<?= (int)$category['id'] ?>"<?= $form['category_id'] == (string)$category['id'] ? ' selected' : '' ?><?= $inactive ? ' data-status="inactive"' : '' ?>>
                    <?= rb_escape($category['name']) ?><?= $inactive ? ' (inactive)' : '' ?>
                  </option>
              <?php endforeach; ?>
              </select>
              <?php if ($selectedCategoryInactive): ?>
                <small class="error-text">Selected category is inactive and hidden on the storefront.</small>
              <?php endif; ?>
              <?php if (isset($errors['category_id'])): ?><span class="error-text"><?= rb_escape($errors['category_id']) ?></span><?php endif; ?>
            </label>

            <div class="form-grid double">
              <label class="checkbox-row">
                <input type="checkbox" name="is_featured" value="1"<?= $form['is_featured'] ? ' checked' : '' ?>>
                <span>Feature on homepage</span>
              </label>
              <label class="checkbox-row">
                <input type="checkbox" name="is_new_arrival" value="1"<?= $form['is_new_arrival'] ? ' checked' : '' ?>>
                <span>Show in New Arrivals</span>
              </label>
            </div>

            <label>
              Short description
              <textarea name="short_description" required><?= rb_escape($form['short_description']) ?></textarea>
              <?php if (isset($errors['short_description'])): ?><span class="error-text"><?= rb_escape($errors['short_description']) ?></span><?php endif; ?>
            </label>

            <label>
              Full description
              <textarea name="description" rows="8"><?= rb_escape($form['description']) ?></textarea>
            </label>

            <div class="image-grid">
              <?php for ($i = 0; $i < RB_MAX_PRODUCT_IMAGES; $i++): $image = $form['images'][$i]; ?>
                <div class="image-entry">
                  <?php if ($image['path'] !== ''): ?>
                    <div class="image-preview" style="margin-bottom:8px;">
                      <img src="../<?= rb_escape($image['path']) ?>" alt="" style="width:100%;max-width:160px;height:auto;border-radius:10px;object-fit:cover;border:1px solid #e5e7eb;">
                    </div>
                  <?php endif; ?>
                  <label>
                    Upload image <?= $i + 1 ?>
                    <input type="file" name="image_files[<?= $i ?>]" accept="image/*">
                  </label>
                  <label>
                    Alt text
                    <input name="images[<?= $i ?>][alt]" value="<?= rb_escape($image['alt']) ?>">
                  </label>
                  <?php if ($image['path'] !== ''): ?>
                    <label class="checkbox-row">
                      <input type="checkbox" name="images[<?= $i ?>][delete]" value="1">
                      <span>Remove this image</span>
                    </label>
                  <?php endif; ?>
                  <input type="hidden" name="images[<?= $i ?>][existing]" value="<?= rb_escape($image['path']) ?>">
                  <label class="primary-radio">
                    <input type="radio" name="primary_image" value="<?= $i ?>"<?= $form['primary_image'] === $i ? ' checked' : '' ?>>
                    Primary
                  </label>
                  <?php if (isset($errors["image_$i"])): ?><span class="error-text"><?= rb_escape($errors["image_$i"]) ?></span><?php endif; ?>
                </div>
              <?php endfor; ?>
              <p class="muted" style="font-size:13px;">Upload up to five images (JPG, PNG, or WEBP). The primary image appears first on the storefront.</p>
            </div>

            <div class="admin-actions" style="justify-content:flex-end;">
              <button class="admin-btn" type="submit"><?= $isEditing ? 'Save changes' : 'Create product' ?></button>
            </div>
          </form>
        </section>
      </main>
    </div>
  </div>
</body>
</html>








