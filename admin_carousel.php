<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/carousel_helpers.php';

require_admin_only();
$carouselImages = load_carousel_images($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_carousel_images'])) {
    require __DIR__ . '/carousel_admin_save.php';
}

render_header('Admin Carousel', 'admin');
?>
<?php require __DIR__ . '/partials/admin_tools_nav.php'; ?>
<h2 class="mb-0">Edit Carousel Photos</h2>
<?php require __DIR__ . '/partials/carousel_admin.php'; ?>
<?php render_footer(); ?>
