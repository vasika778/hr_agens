<?php
$pageTitle = 'О компании';
require_once 'includes/header.php';

$success = '';
$error = '';

// Обработка POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_material':
            $title = sanitize($_POST['title'] ?? '');
            $content = sanitize($_POST['content'] ?? '');
            $type = $_POST['type'] ?? 'text';
            $youtubeUrl = sanitize($_POST['youtube_url'] ?? '');
            
            if (empty($title)) {
                $error = 'Укажите заголовок';
            } else {
                $filePath = null;
                
                // Загрузка файла
                if ($type !== 'text' && $type !== 'youtube' && isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
                    $uploadResult = uploadFile($_FILES['file'], 'about', ALLOWED_ABOUT_EXTENSIONS);
                    if ($uploadResult['success']) {
                        $filePath = $uploadResult['filename'];
                    } else {
                        $error = $uploadResult['error'];
                    }
                }
                
                if (!$error) {
                    $maxOrder = db()->fetch("SELECT MAX(order_num) as max_order FROM about_materials");
                    $orderNum = ($maxOrder['max_order'] ?? 0) + 1;
                    
                    db()->query(
                        "INSERT INTO about_materials (title, content, type, file_path, youtube_url, order_num) VALUES (?, ?, ?, ?, ?, ?)",
                        [$title, $content, $type, $filePath, $youtubeUrl, $orderNum]
                    );
                    $success = 'Материал добавлен';
                }
            }
            break;
            
        case 'update_material':
            $materialId = (int)($_POST['material_id'] ?? 0);
            $title = sanitize($_POST['title'] ?? '');
            $content = sanitize($_POST['content'] ?? '');
            $youtubeUrl = sanitize($_POST['youtube_url'] ?? '');
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            
            if ($materialId && $title) {
                db()->query(
                    "UPDATE about_materials SET title = ?, content = ?, youtube_url = ?, is_active = ? WHERE id = ?",
                    [$title, $content, $youtubeUrl, $isActive, $materialId]
                );
                $success = 'Материал обновлён';
            }
            break;
            
        case 'delete_material':
            $materialId = (int)($_POST['material_id'] ?? 0);
            if ($materialId) {
                $material = db()->fetch("SELECT * FROM about_materials WHERE id = ?", [$materialId]);
                if ($material && $material['file_path']) {
                    deleteFile($material['file_path'], 'about');
                }
                db()->query("DELETE FROM about_materials WHERE id = ?", [$materialId]);
                $success = 'Материал удалён';
            }
            break;
            
        case 'toggle_material':
            $materialId = (int)($_POST['material_id'] ?? 0);
            if ($materialId) {
                db()->query("UPDATE about_materials SET is_active = NOT is_active WHERE id = ?", [$materialId]);
                $success = 'Статус изменён';
            }
            break;
    }
}

// Получаем материалы
$materials = db()->fetchAll("SELECT * FROM about_materials ORDER BY order_num");
?>

<?php if ($success): ?>
    <div class="alert alert-success" data-auto-hide="5000">
        <span>✅</span>
        <span><?= $success ?></span>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <span>⚠️</span>
        <span><?= $error ?></span>
    </div>
<?php endif; ?>

<div class="d-flex justify-between align-center mb-3">
    <p class="text-muted" style="margin: 0;">
        Эти материалы будут доступны кандидатам после успешного прохождения теста
    </p>
    <button class="btn btn-primary" data-modal="addMaterialModal">
        ➕ Добавить материал
    </button>
</div>

<!-- Materials List -->
<div class="card">
    <div class="card-body" style="padding: 0;">
        <?php if (empty($materials)): ?>
            <div class="empty-state">
                <div class="empty-icon">ℹ️</div>
                <div class="empty-title">Материалы не добавлены</div>
                <div class="empty-text">Добавьте информацию о компании</div>
            </div>
        <?php else: ?>
            <?php foreach ($materials as $material): ?>
                <div class="material-item" style="padding: 1.25rem; border-bottom: 1px solid var(--border-color); <?= !$material['is_active'] ? 'opacity: 0.5;' : '' ?>">
                    <div class="d-flex justify-between align-center">
                        <div class="d-flex align-center gap-2">
                            <span style="font-size: 1.5rem;">
                                <?php
                                $icons = [
                                    'text' => '📝',
                                    'image' => '🖼️',
                                    'video' => '🎬',
                                    'pdf' => '📄',
                                    'youtube' => '▶️'
                                ];
                                echo $icons[$material['type']] ?? '📋';
                                ?>
                            </span>
                            <div>
                                <div style="font-weight: 500;"><?= sanitize($material['title']) ?></div>
                                <div class="text-muted" style="font-size: 0.8125rem;">
                                    <?php
                                    $types = [
                                        'text' => 'Текст',
                                        'image' => 'Изображение',
                                        'video' => 'Видео',
                                        'pdf' => 'PDF документ',
                                        'youtube' => 'YouTube видео'
                                    ];
                                    echo $types[$material['type']] ?? $material['type'];
                                    ?>
                                </div>
                            </div>
                            <?php if (!$material['is_active']): ?>
                                <span class="badge badge-rejected">Скрыт</span>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex gap-1">
                            <?php if ($material['file_path']): ?>
                                <a href="../uploads/about/<?= $material['file_path'] ?>" 
                                   class="btn btn-sm btn-secondary" 
                                   target="_blank"
                                   title="Просмотр">
                                    👁️
                                </a>
                            <?php endif; ?>
                            <button class="btn btn-sm btn-secondary" 
                                    onclick="editMaterial(<?= htmlspecialchars(json_encode($material)) ?>)"
                                    title="Редактировать">
                                ✏️
                            </button>
                            <form action="" method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="toggle_material">
                                <input type="hidden" name="material_id" value="<?= $material['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-secondary" title="Переключить">
                                    <?= $material['is_active'] ? '🔴' : '🟢' ?>
                                </button>
                            </form>
                            <form action="" method="POST" style="display: inline;" onsubmit="return confirm('Удалить материал?');">
                                <input type="hidden" name="action" value="delete_material">
                                <input type="hidden" name="material_id" value="<?= $material['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger" title="Удалить">
                                    🗑️
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php if ($material['content']): ?>
                        <div style="margin-top: 0.75rem; padding: 0.75rem; background: var(--bg-secondary); border-radius: var(--radius-sm); font-size: 0.875rem; color: var(--text-secondary);">
                            <?= nl2br(sanitize(mb_substr($material['content'], 0, 200))) ?>
                            <?= mb_strlen($material['content']) > 200 ? '...' : '' ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Add Material Modal -->
<div class="modal-overlay" id="addMaterialModal">
    <div class="modal" style="max-width: 600px;">
        <div class="modal-header">
            <h3 class="modal-title">Добавить материал</h3>
            <button class="modal-close" data-modal-close>×</button>
        </div>
        <form action="" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create_material">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label required">Заголовок</label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Тип материала</label>
                    <select name="type" class="form-control" id="materialType" onchange="toggleFileInput()">
                        <option value="text">Текст</option>
                        <option value="image">Изображение</option>
                        <option value="video">Видео (файл)</option>
                        <option value="pdf">PDF документ</option>
                        <option value="youtube">YouTube видео</option>
                    </select>
                </div>
                <div class="form-group" id="contentGroup">
                    <label class="form-label">Содержание</label>
                    <textarea name="content" class="form-control" rows="4"></textarea>
                </div>
                <div class="form-group hidden" id="fileGroup">
                    <label class="form-label">Файл</label>
                    <input type="file" name="file" class="form-control">
                </div>
                <div class="form-group hidden" id="youtubeGroup">
                    <label class="form-label">Ссылка на YouTube</label>
                    <input type="url" name="youtube_url" class="form-control" placeholder="https://www.youtube.com/watch?v=...">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Отмена</button>
                <button type="submit" class="btn btn-primary">Добавить</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Material Modal -->
<div class="modal-overlay" id="editMaterialModal">
    <div class="modal" style="max-width: 600px;">
        <div class="modal-header">
            <h3 class="modal-title">Редактировать материал</h3>
            <button class="modal-close" data-modal-close>×</button>
        </div>
        <form action="" method="POST">
            <input type="hidden" name="action" value="update_material">
            <input type="hidden" name="material_id" id="edit_material_id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label required">Заголовок</label>
                    <input type="text" name="title" id="edit_title" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Содержание</label>
                    <textarea name="content" id="edit_content" class="form-control" rows="4"></textarea>
                </div>
                <div class="form-group" id="edit_youtube_group">
                    <label class="form-label">Ссылка на YouTube</label>
                    <input type="url" name="youtube_url" id="edit_youtube_url" class="form-control">
                </div>
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="checkbox" name="is_active" id="edit_is_active" value="1">
                        <span>Материал активен</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Отмена</button>
                <button type="submit" class="btn btn-primary">Сохранить</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleFileInput() {
    const type = document.getElementById('materialType').value;
    document.getElementById('fileGroup').classList.toggle('hidden', type === 'text' || type === 'youtube');
    document.getElementById('youtubeGroup').classList.toggle('hidden', type !== 'youtube');
}

function editMaterial(material) {
    document.getElementById('edit_material_id').value = material.id;
    document.getElementById('edit_title').value = material.title;
    document.getElementById('edit_content').value = material.content || '';
    document.getElementById('edit_youtube_url').value = material.youtube_url || '';
    document.getElementById('edit_is_active').checked = material.is_active == 1;
    document.getElementById('edit_youtube_group').classList.toggle('hidden', material.type !== 'youtube');
    
    openModal(document.getElementById('editMaterialModal'));
}
</script>

<?php require_once 'includes/footer.php'; ?>
