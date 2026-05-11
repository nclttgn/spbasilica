<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/carousel_helpers.php';

$admin = require_admin_only();

function maintenance_collect_files(string $directory): array
{
    if (!is_dir($directory)) {
        return [];
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
    );

    $files = [];
    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $files[] = [
            'path' => $file->getPathname(),
            'name' => $file->getFilename(),
            'size' => (int)$file->getSize(),
            'modified_at' => $file->getMTime(),
        ];
    }

    usort($files, static function (array $left, array $right): int {
        return $right['modified_at'] <=> $left['modified_at'];
    });

    return $files;
}

function maintenance_format_bytes(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }

    $units = ['KB', 'MB', 'GB', 'TB'];
    $value = $bytes / 1024;
    foreach ($units as $unit) {
        if ($value < 1024 || $unit === 'TB') {
            return number_format($value, 2) . ' ' . $unit;
        }
        $value /= 1024;
    }

    return number_format($value, 2) . ' TB';
}

function maintenance_sum_sizes(array $files): int
{
    $total = 0;
    foreach ($files as $file) {
        $total += (int)($file['size'] ?? 0);
    }
    return $total;
}

function maintenance_relative_upload_path(string $absolutePath): string
{
    $normalizedBase = str_replace('\\', '/', realpath(__DIR__) ?: __DIR__);
    $normalizedPath = str_replace('\\', '/', $absolutePath);
    if (str_starts_with($normalizedPath, $normalizedBase . '/')) {
        return substr($normalizedPath, strlen($normalizedBase) + 1);
    }
    return basename($absolutePath);
}

function maintenance_referenced_avatar_paths(mysqli $conn): array
{
    $paths = [];
    $result = $conn->query('SELECT avatar_path FROM users WHERE avatar_path IS NOT NULL AND avatar_path <> ""');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $path = trim((string)($row['avatar_path'] ?? ''));
            if ($path !== '') {
                $paths[$path] = true;
            }
        }
        $result->free();
    }
    return $paths;
}

function maintenance_referenced_carousel_paths(mysqli $conn): array
{
    $paths = [];
    foreach (load_carousel_images($conn) as $path) {
        $path = trim((string)$path);
        if ($path !== '') {
            $paths[$path] = true;
        }
    }
    return $paths;
}

function maintenance_find_orphan_files(array $files, array $referencedPaths): array
{
    $orphans = [];
    foreach ($files as $file) {
        $relativePath = maintenance_relative_upload_path((string)$file['path']);
        if (!isset($referencedPaths[$relativePath])) {
            $file['relative_path'] = $relativePath;
            $orphans[] = $file;
        }
    }
    return $orphans;
}

function maintenance_delete_files(array $files): int
{
    $deleted = 0;
    foreach ($files as $file) {
        $path = (string)($file['path'] ?? '');
        if ($path !== '' && is_file($path) && @unlink($path)) {
            $deleted++;
        }
    }
    return $deleted;
}

$avatarDir = __DIR__ . '/uploads/avatars';
$carouselDir = __DIR__ . '/uploads/carousel';
$uploadDir = __DIR__ . '/uploads';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['maintenance_action'])) {
    $action = trim((string)($_POST['maintenance_action'] ?? ''));

    if ($action === 'cleanup_orphan_avatars') {
        $avatarFiles = maintenance_collect_files($avatarDir);
        $orphanAvatarFiles = maintenance_find_orphan_files($avatarFiles, maintenance_referenced_avatar_paths($conn));
        $deletedCount = maintenance_delete_files($orphanAvatarFiles);
        log_activity_entry((int)$admin['id'], 'File maintenance', 'Deleted ' . $deletedCount . ' orphan avatar file(s).');
        set_flash('success', 'Deleted ' . $deletedCount . ' orphan avatar file(s).');
        header('Location: admin_file_maintenance.php');
        exit();
    }

    if ($action === 'cleanup_orphan_carousel') {
        $carouselFiles = maintenance_collect_files($carouselDir);
        $orphanCarouselFiles = maintenance_find_orphan_files($carouselFiles, maintenance_referenced_carousel_paths($conn));
        $deletedCount = maintenance_delete_files($orphanCarouselFiles);
        log_activity_entry((int)$admin['id'], 'File maintenance', 'Deleted ' . $deletedCount . ' orphan carousel file(s).');
        set_flash('success', 'Deleted ' . $deletedCount . ' orphan carousel file(s).');
        header('Location: admin_file_maintenance.php');
        exit();
    }
}

$allUploadFiles = maintenance_collect_files($uploadDir);
$avatarFiles = maintenance_collect_files($avatarDir);
$carouselFiles = maintenance_collect_files($carouselDir);

$orphanAvatarFiles = maintenance_find_orphan_files($avatarFiles, maintenance_referenced_avatar_paths($conn));
$orphanCarouselFiles = maintenance_find_orphan_files($carouselFiles, maintenance_referenced_carousel_paths($conn));

$recentFiles = array_slice($allUploadFiles, 0, 8);

render_header('File Maintenance', 'admin_file_maintenance');
?>
<?php require __DIR__ . '/partials/admin_tools_nav.php'; ?>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card bg-dark border-warning-subtle h-100">
            <div class="card-body">
                <h5 class="text-warning">Uploads</h5>
                <div class="display-6"><?php echo count($allUploadFiles); ?></div>
                <div class="text-secondary">Total files</div>
                <div class="mt-2"><?php echo e(maintenance_format_bytes(maintenance_sum_sizes($allUploadFiles))); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-dark border-warning-subtle h-100">
            <div class="card-body">
                <h5 class="text-warning">Avatar Files</h5>
                <div class="display-6"><?php echo count($avatarFiles); ?></div>
                <div class="text-secondary"><?php echo count($orphanAvatarFiles); ?> orphan file(s)</div>
                <div class="mt-2"><?php echo e(maintenance_format_bytes(maintenance_sum_sizes($avatarFiles))); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-dark border-warning-subtle h-100">
            <div class="card-body">
                <h5 class="text-warning">Carousel Files</h5>
                <div class="display-6"><?php echo count($carouselFiles); ?></div>
                <div class="text-secondary"><?php echo count($orphanCarouselFiles); ?> orphan file(s)</div>
                <div class="mt-2"><?php echo e(maintenance_format_bytes(maintenance_sum_sizes($carouselFiles))); ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card bg-dark border-warning-subtle h-100">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <h4 class="text-warning mb-0">Avatar Maintenance</h4>
                    <form method="POST" class="m-0">
                        <input type="hidden" name="maintenance_action" value="cleanup_orphan_avatars">
                        <button class="btn btn-sm btn-warning" type="submit" <?php echo !$orphanAvatarFiles ? 'disabled' : ''; ?>>
                            Clean Orphan Avatars
                        </button>
                    </form>
                </div>
                <p class="text-secondary">Removes avatar image files from `uploads/avatars` that are no longer referenced by any user account.</p>
                <?php if (!$orphanAvatarFiles): ?>
                    <div class="alert alert-info mb-0">No orphan avatar files found.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>File</th>
                                    <th>Size</th>
                                    <th>Last Modified</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orphanAvatarFiles as $file): ?>
                                    <tr>
                                        <td><?php echo e($file['relative_path']); ?></td>
                                        <td><?php echo e(maintenance_format_bytes((int)$file['size'])); ?></td>
                                        <td><?php echo e(date('Y-m-d h:i A', (int)$file['modified_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card bg-dark border-warning-subtle h-100">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <h4 class="text-warning mb-0">Carousel Maintenance</h4>
                    <form method="POST" class="m-0">
                        <input type="hidden" name="maintenance_action" value="cleanup_orphan_carousel">
                        <button class="btn btn-sm btn-warning" type="submit" <?php echo !$orphanCarouselFiles ? 'disabled' : ''; ?>>
                            Clean Orphan Carousel Files
                        </button>
                    </form>
                </div>
                <p class="text-secondary">Removes carousel image files from `uploads/carousel` that are no longer assigned to any carousel slot.</p>
                <?php if (!$orphanCarouselFiles): ?>
                    <div class="alert alert-info mb-0">No orphan carousel files found.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>File</th>
                                    <th>Size</th>
                                    <th>Last Modified</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orphanCarouselFiles as $file): ?>
                                    <tr>
                                        <td><?php echo e($file['relative_path']); ?></td>
                                        <td><?php echo e(maintenance_format_bytes((int)$file['size'])); ?></td>
                                        <td><?php echo e(date('Y-m-d h:i A', (int)$file['modified_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card bg-dark border-warning-subtle">
            <div class="card-body">
                <h4 class="text-warning mb-3">Recent Upload Files</h4>
                <?php if (!$recentFiles): ?>
                    <div class="alert alert-info mb-0">No upload files found.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>File</th>
                                    <th>Size</th>
                                    <th>Last Modified</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentFiles as $file): ?>
                                    <tr>
                                        <td><?php echo e(maintenance_relative_upload_path((string)$file['path'])); ?></td>
                                        <td><?php echo e(maintenance_format_bytes((int)$file['size'])); ?></td>
                                        <td><?php echo e(date('Y-m-d h:i A', (int)$file['modified_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php render_footer(); ?>
