<?php
declare(strict_types=1);

$footerMenus = $footerMenus ?? [];
$footerBlock = $footerBlock ?? null;
$brandName = $brandName ?? 'RudraBlessings';
$year = (int)date('Y');
$defaultFooter = sprintf('&copy; %d %s - Crafted with love in Australia.', $year, rb_escape($brandName));
?>
<footer class="rb-footer">
  <div class="container footer-grid">
    <?php foreach ($footerMenus as $menu): ?>
      <?php
      $title = trim((string)($menu['title'] ?? ''));
      $items = is_array($menu['items'] ?? null) ? $menu['items'] : [];
      if ($title === '' && empty($items)) {
          continue;
      }
      ?>
      <div>
        <?php if ($title !== ''): ?>
          <h4><?= rb_escape($title) ?></h4>
        <?php endif; ?>
        <?php if (!empty($items)): ?>
          <ul>
            <?php foreach ($items as $item): ?>
              <?php
              $label = trim((string)($item['label'] ?? ''));
              if ($label === '') {
                  continue;
              }
              $url = (string)($item['url'] ?? '#');
              ?>
              <li><a href="<?= rb_escape($url) ?>"><?= rb_escape($label) ?></a></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="copyright">
    <?= rb_trusted_html($footerBlock['body'] ?? $defaultFooter) ?>
  </div>
</footer>
<script>
  window.__RB_CSRF_COOKIE__ = <?= json_encode(rb_csrf_cookie_name(), JSON_UNESCAPED_SLASHES) ?>;
</script>
