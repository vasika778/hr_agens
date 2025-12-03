<?php
$pageTitle = 'Документы';
require_once 'includes/header.php';

$currentAdmin = getCurrentAdmin();
$success = '';
$error = '';

// Обработка POST запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        // Создание категории
        case 'create_category':
            $name = sanitize($_POST['name'] ?? '');
            $description = sanitize($_POST['description'] ?? '');
            $color = sanitize($_POST['color'] ?? '#6366f1');
            
            if (empty($name)) {
                $error = 'Укажите название папки';
            } else {
                $maxOrder = db()->fetch("SELECT MAX(order_num) as max_order FROM hr_doc_categories WHERE admin_id = ?", [$currentAdmin['id']]);
                $orderNum = ($maxOrder['max_order'] ?? 0) + 1;
                
                db()->query(
                    "INSERT INTO hr_doc_categories (admin_id, name, description, color, order_num) VALUES (?, ?, ?, ?, ?)",
                    [$currentAdmin['id'], $name, $description, $color, $orderNum]
                );
                $success = 'Папка создана';
            }
            break;
            
        // Обновление категории
        case 'update_category':
            $categoryId = (int)($_POST['category_id'] ?? 0);
            $name = sanitize($_POST['name'] ?? '');
            $description = sanitize($_POST['description'] ?? '');
            $color = sanitize($_POST['color'] ?? '#6366f1');
            
            if ($categoryId && $name) {
                db()->query(
                    "UPDATE hr_doc_categories SET name = ?, description = ?, color = ? WHERE id = ? AND admin_id = ?",
                    [$name, $description, $color, $categoryId, $currentAdmin['id']]
                );
                $success = 'Папка обновлена';
            }
            break;
            
        // Удаление категории
        case 'delete_category':
            $categoryId = (int)($_POST['category_id'] ?? 0);
            if ($categoryId) {
                // Перемещаем документы в "Без категории"
                db()->query("UPDATE hr_documents SET category_id = NULL WHERE category_id = ? AND admin_id = ?", 
                    [$categoryId, $currentAdmin['id']]);
                db()->query("DELETE FROM hr_doc_categories WHERE id = ? AND admin_id = ?", 
                    [$categoryId, $currentAdmin['id']]);
                $success = 'Папка удалена. Документы перемещены в "Без категории"';
            }
            break;
            
        // Загрузка документа
        case 'upload_document':
            $title = sanitize($_POST['title'] ?? '');
            $description = sanitize($_POST['description'] ?? '');
            $categoryId = (int)($_POST['category_id'] ?? 0) ?: null;
            $candidateId = (int)($_POST['candidate_id'] ?? 0) ?: null;
            $isVisible = isset($_POST['is_visible_to_candidate']) ? 1 : 0;
            
            if (empty($title)) {
                $error = 'Укажите название документа';
            } elseif (!isset($_FILES['document']) || $_FILES['document']['error'] === UPLOAD_ERR_NO_FILE) {
                $error = 'Выберите файл для загрузки';
            } else {
                $file = $_FILES['document'];
                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                
                if (!in_array($extension, ALLOWED_HR_DOC_EXTENSIONS)) {
                    $error = 'Недопустимый формат файла. Разрешены: ' . implode(', ', ALLOWED_HR_DOC_EXTENSIONS);
                } elseif ($file['size'] > MAX_FILE_SIZE) {
                    $error = 'Файл слишком большой (максимум 10 МБ)';
                } else {
                    // Создаём директорию для HR
                    $uploadDir = 'hr_docs/' . $currentAdmin['id'];
                    $fullPath = UPLOAD_PATH . $uploadDir;
                    
                    if (!is_dir($fullPath)) {
                        mkdir($fullPath, 0755, true);
                    }
                    
                    $fileName = generateToken(16) . '.' . $extension;
                    $filePath = $fullPath . '/' . $fileName;
                    
                    if (move_uploaded_file($file['tmp_name'], $filePath)) {
                        db()->query(
                            "INSERT INTO hr_documents (admin_id, category_id, candidate_id, title, description, file_name, original_name, file_size, file_type, is_visible_to_candidate) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                            [$currentAdmin['id'], $categoryId, $candidateId, $title, $description, $fileName, $file['name'], $file['size'], $extension, $isVisible]
                        );
                        $success = 'Документ загружен';
                    } else {
                        $error = 'Ошибка при сохранении файла';
                    }
                }
            }
            break;
            
        // Обновление документа
        case 'update_document':
            $docId = (int)($_POST['doc_id'] ?? 0);
            $title = sanitize($_POST['title'] ?? '');
            $description = sanitize($_POST['description'] ?? '');
            $categoryId = (int)($_POST['category_id'] ?? 0) ?: null;
            $candidateId = (int)($_POST['candidate_id'] ?? 0) ?: null;
            $isVisible = isset($_POST['is_visible_to_candidate']) ? 1 : 0;
            
            if ($docId && $title) {
                db()->query(
                    "UPDATE hr_documents SET title = ?, description = ?, category_id = ?, candidate_id = ?, is_visible_to_candidate = ? 
                     WHERE id = ? AND admin_id = ?",
                    [$title, $description, $categoryId, $candidateId, $isVisible, $docId, $currentAdmin['id']]
                );
                $success = 'Документ обновлён';
            }
            break;
            
        // Удаление документа
        case 'delete_document':
            $docId = (int)($_POST['doc_id'] ?? 0);
            if ($docId) {
                $doc = db()->fetch("SELECT * FROM hr_documents WHERE id = ? AND admin_id = ?", [$docId, $currentAdmin['id']]);
                if ($doc) {
                    // Удаляем файл
                    $filePath = UPLOAD_PATH . 'hr_docs/' . $currentAdmin['id'] . '/' . $doc['file_name'];
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                    db()->query("DELETE FROM hr_documents WHERE id = ?", [$docId]);
                    $success = 'Документ удалён';
                }
            }
            break;
    }
}

// Параметры фильтрации
$categoryFilter = isset($_GET['category']) ? (int)$_GET['category'] : null;
$candidateFilter = isset($_GET['candidate']) ? (int)$_GET['candidate'] : null;
$search = sanitize($_GET['search'] ?? '');

// Получаем категории
$categories = db()->fetchAll(
    "SELECT c.*, COUNT(d.id) as docs_count 
     FROM hr_doc_categories c 
     LEFT JOIN hr_documents d ON d.category_id = c.id 
     WHERE c.admin_id = ? 
     GROUP BY c.id 
     ORDER BY c.order_num",
    [$currentAdmin['id']]
);

// Построение запроса для документов
$where = ['d.admin_id = ?'];
$params = [$currentAdmin['id']];

if ($categoryFilter !== null) {
    if ($categoryFilter === 0) {
        $where[] = 'd.category_id IS NULL';
    } else {
        $where[] = 'd.category_id = ?';
        $params[] = $categoryFilter;
    }
}

if ($candidateFilter) {
    $where[] = 'd.candidate_id = ?';
    $params[] = $candidateFilter;
}

if ($search) {
    $where[] = '(d.title LIKE ? OR d.original_name LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = implode(' AND ', $where);

// Получаем документы
$documents = db()->fetchAll(
    "SELECT d.*, c.name as category_name, c.color as category_color, 
            cand.name as candidate_name
     FROM hr_documents d 
     LEFT JOIN hr_doc_categories c ON d.category_id = c.id 
     LEFT JOIN candidates cand ON d.candidate_id = cand.id 
     WHERE $whereClause 
     ORDER BY d.created_at DESC",
    $params
);

// Получаем кандидатов для выпадающего списка
$candidates = db()->fetchAll("SELECT id, name FROM candidates ORDER BY name");

// Количество документов без категории
$uncategorizedCount = db()->fetch(
    "SELECT COUNT(*) as count FROM hr_documents WHERE admin_id = ? AND category_id IS NULL",
    [$currentAdmin['id']]
)['count'];

// Функция форматирования размера файла
function formatFileSize($bytes) {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' МБ';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' КБ';
    }
    return $bytes . ' байт';
}

// Иконки для типов файлов
function getFileIcon($type) {
    $icons = [
        'pdf' => '📄',
        'doc' => '📝', 'docx' => '📝',
        'xls' => '📊', 'xlsx' => '📊',
        'jpg' => '🖼️', 'jpeg' => '🖼️', 'png' => '🖼️', 'gif' => '🖼️',
        'ppt' => '📽️', 'pptx' => '📽️',
        'zip' => '📦', 'rar' => '📦',
        'txt' => '📃', 'rtf' => '📃'
    ];
    return $icons[$type] ?? '📎';
}
?>

<style>
.documents-layout {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 1.5rem;
}

@media (max-width: 1024px) {
    .documents-layout {
        grid-template-columns: 1fr;
    }
}

.folder-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    border-radius: var(--radius-md);
    cursor: pointer;
    transition: all var(--transition-fast);
    margin-bottom: 0.25rem;
    position: relative;
}

.folder-item:hover {
    background: var(--bg-hover);
}

.folder-item.active {
    background: var(--accent-primary);
    color: white;
}

.folder-icon {
    width: 32px;
    height: 32px;
    border-radius: var(--radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
}

.folder-item.active .folder-icon {
    background: rgba(255,255,255,0.2);
}

.folder-info {
    flex: 1;
    min-width: 0;
}

.folder-name {
    font-weight: 500;
    font-size: 0.9375rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.folder-count {
    font-size: 0.75rem;
    opacity: 0.7;
}

.doc-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1rem;
}

.doc-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    padding: 1.25rem;
    transition: all var(--transition-fast);
}

.doc-card:hover {
    border-color: var(--accent-primary);
    transform: translateY(-2px);
}

.doc-header {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1rem;
}

.doc-icon {
    width: 48px;
    height: 48px;
    background: var(--bg-secondary);
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.doc-title {
    font-weight: 600;
    font-size: 1rem;
    margin-bottom: 0.25rem;
    word-break: break-word;
}

.doc-meta {
    font-size: 0.8125rem;
    color: var(--text-muted);
}

.doc-category-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.5rem;
    font-size: 0.6875rem;
    font-weight: 500;
    border-radius: 9999px;
    background: var(--bg-secondary);
    margin-top: 0.5rem;
}

.doc-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border-color);
}
</style>

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
    <div></div>
    <div class="d-flex gap-2">
        <button class="btn btn-secondary" data-modal="createCategoryModal">
            📁 Новая папка
        </button>
        <button class="btn btn-primary" data-modal="uploadDocModal">
            ➕ Загрузить документ
        </button>
    </div>
</div>

<div class="documents-layout">
    <!-- Sidebar - Папки -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">📁 Папки</h3>
        </div>
        <div class="card-body" style="padding: 0.5rem;">
            <a href="documents.php" class="folder-item <?= $categoryFilter === null && !$candidateFilter ? 'active' : '' ?>">
                <div class="folder-icon" style="background: var(--bg-tertiary);">📂</div>
                <div class="folder-info">
                    <div class="folder-name">Все документы</div>
                    <div class="folder-count"><?= count($documents) ?> файлов</div>
                </div>
            </a>
            
            <?php foreach ($categories as $cat): ?>
                <div class="folder-item-wrapper" style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                    <a href="documents.php?category=<?= $cat['id'] ?>" 
                       class="folder-item <?= $categoryFilter === (int)$cat['id'] ? 'active' : '' ?>" style="flex: 1; margin-bottom: 0;">
                        <div class="folder-icon" style="background: <?= $cat['color'] ?>20; color: <?= $cat['color'] ?>;">📁</div>
                        <div class="folder-info">
                            <div class="folder-name"><?= sanitize($cat['name']) ?></div>
                            <div class="folder-count"><?= $cat['docs_count'] ?> файлов</div>
                        </div>
                    </a>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="editCategory(<?= htmlspecialchars(json_encode($cat)) ?>)" title="Редактировать" style="padding: 0.4rem 0.6rem;">
                        ✏️
                    </button>
                    <form action="" method="POST" style="margin: 0;" onsubmit="return confirm('Удалить папку?');">
                        <input type="hidden" name="action" value="delete_category">
                        <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger" title="Удалить" style="padding: 0.4rem 0.6rem;">
                            🗑️
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
            
            <?php if ($uncategorizedCount > 0): ?>
                <a href="documents.php?category=0" class="folder-item <?= $categoryFilter === 0 ? 'active' : '' ?>">
                    <div class="folder-icon" style="background: var(--bg-tertiary);">📄</div>
                    <div class="folder-info">
                        <div class="folder-name">Без категории</div>
                        <div class="folder-count"><?= $uncategorizedCount ?> файлов</div>
                    </div>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main Content - Документы -->
    <div>
        <!-- Фильтры -->
        <div class="card mb-3">
            <div class="card-body" style="padding: 1rem;">
                <form action="" method="GET" class="d-flex gap-2" style="flex-wrap: wrap;">
                    <?php if ($categoryFilter !== null): ?>
                        <input type="hidden" name="category" value="<?= $categoryFilter ?>">
                    <?php endif; ?>
                    
                    <div class="search-box" style="flex: 1; min-width: 200px;">
                        <span>🔍</span>
                        <input type="text" name="search" placeholder="Поиск документов..." value="<?= $search ?>">
                    </div>
                    
                    <select name="candidate" class="filter-select" onchange="this.form.submit()">
                        <option value="">Все сотрудники</option>
                        <?php foreach ($candidates as $cand): ?>
                            <option value="<?= $cand['id'] ?>" <?= $candidateFilter === (int)$cand['id'] ? 'selected' : '' ?>>
                                <?= sanitize($cand['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <button type="submit" class="btn btn-secondary">Найти</button>
                </form>
            </div>
        </div>

        <!-- Список документов -->
        <?php if (empty($documents)): ?>
            <div class="card">
                <div class="empty-state">
                    <div class="empty-icon">📂</div>
                    <div class="empty-title">Документы не найдены</div>
                    <div class="empty-text">Загрузите первый документ</div>
                    <button class="btn btn-primary mt-2" data-modal="uploadDocModal">
                        ➕ Загрузить документ
                    </button>
                </div>
            </div>
        <?php else: ?>
            <div class="doc-grid">
                <?php foreach ($documents as $doc): ?>
                    <div class="doc-card">
                        <div class="doc-header">
                            <div class="doc-icon"><?= getFileIcon($doc['file_type']) ?></div>
                            <div style="flex: 1; min-width: 0;">
                                <div class="doc-title"><?= sanitize($doc['title']) ?></div>
                                <div class="doc-meta">
                                    <?= strtoupper($doc['file_type']) ?> • <?= formatFileSize($doc['file_size']) ?>
                                </div>
                                <?php if ($doc['category_name']): ?>
                                    <div class="doc-category-badge" style="color: <?= $doc['category_color'] ?>;">
                                        <span style="width: 6px; height: 6px; border-radius: 50%; background: currentColor;"></span>
                                        <?= sanitize($doc['category_name']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if ($doc['candidate_name']): ?>
                            <div style="font-size: 0.8125rem; color: var(--text-secondary); margin-bottom: 0.5rem;">
                                👤 Привязан к: <a href="candidate.php?id=<?= $doc['candidate_id'] ?>"><?= sanitize($doc['candidate_name']) ?></a>
                                <?php if ($doc['is_visible_to_candidate']): ?>
                                    <span class="badge badge-approved" style="font-size: 0.625rem; padding: 0.125rem 0.375rem;">Виден сотруднику</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($doc['description']): ?>
                            <div style="font-size: 0.8125rem; color: var(--text-muted); margin-bottom: 0.5rem;">
                                <?= sanitize(mb_substr($doc['description'], 0, 100)) ?><?= mb_strlen($doc['description']) > 100 ? '...' : '' ?>
                            </div>
                        <?php endif; ?>
                        
                        <div style="font-size: 0.75rem; color: var(--text-muted);">
                            📅 <?= formatDate($doc['created_at'], 'd.m.Y') ?>
                            <?php if ($doc['download_count'] > 0): ?>
                                • ⬇️ <?= $doc['download_count'] ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="doc-actions">
                            <a href="download.php?id=<?= $doc['id'] ?>" class="btn btn-sm btn-primary" style="flex: 1;">
                                ⬇️ Скачать
                            </a>
                            <button class="btn btn-sm btn-secondary" onclick="editDocument(<?= htmlspecialchars(json_encode($doc)) ?>)">
                                ✏️
                            </button>
                            <form action="" method="POST" style="display: contents;" onsubmit="return confirm('Удалить документ?');">
                                <input type="hidden" name="action" value="delete_document">
                                <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Создание папки -->
<div class="modal-overlay" id="createCategoryModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Создать папку</h3>
            <button class="modal-close" data-modal-close>×</button>
        </div>
        <form action="" method="POST">
            <input type="hidden" name="action" value="create_category">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label required">Название</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Описание</label>
                    <textarea name="description" class="form-control" rows="2"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Цвет</label>
                    <input type="color" name="color" class="form-control" value="#6366f1" style="height: 50px; padding: 5px;">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Отмена</button>
                <button type="submit" class="btn btn-primary">Создать</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Редактирование папки -->
<div class="modal-overlay" id="editCategoryModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Редактировать папку</h3>
            <button class="modal-close" data-modal-close>×</button>
        </div>
        <form action="" method="POST">
            <input type="hidden" name="action" value="update_category">
            <input type="hidden" name="category_id" id="edit_cat_id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label required">Название</label>
                    <input type="text" name="name" id="edit_cat_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Описание</label>
                    <textarea name="description" id="edit_cat_description" class="form-control" rows="2"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Цвет</label>
                    <input type="color" name="color" id="edit_cat_color" class="form-control" style="height: 50px; padding: 5px;">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Отмена</button>
                <button type="submit" class="btn btn-primary">Сохранить</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Загрузка документа -->
<div class="modal-overlay" id="uploadDocModal">
    <div class="modal" style="max-width: 600px;">
        <div class="modal-header">
            <h3 class="modal-title">Загрузить документ</h3>
            <button class="modal-close" data-modal-close>×</button>
        </div>
        <form action="" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload_document">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label required">Название документа</label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Описание</label>
                    <textarea name="description" class="form-control" rows="2"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label required">Файл</label>
                    <input type="file" name="document" class="form-control" required>
                    <div class="form-text">Максимум 10 МБ. Форматы: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG и др.</div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Папка</label>
                        <select name="category_id" class="form-control">
                            <option value="">Без категории</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= sanitize($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Привязать к сотруднику</label>
                        <select name="candidate_id" class="form-control">
                            <option value="">Не привязывать</option>
                            <?php foreach ($candidates as $cand): ?>
                                <option value="<?= $cand['id'] ?>"><?= sanitize($cand['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="checkbox" name="is_visible_to_candidate" value="1">
                        <span>Сделать доступным для сотрудника</span>
                    </label>
                    <div class="form-text">Если отмечено, сотрудник сможет скачать этот документ в своём личном кабинете</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Отмена</button>
                <button type="submit" class="btn btn-primary">Загрузить</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Редактирование документа -->
<div class="modal-overlay" id="editDocModal">
    <div class="modal" style="max-width: 600px;">
        <div class="modal-header">
            <h3 class="modal-title">Редактировать документ</h3>
            <button class="modal-close" data-modal-close>×</button>
        </div>
        <form action="" method="POST">
            <input type="hidden" name="action" value="update_document">
            <input type="hidden" name="doc_id" id="edit_doc_id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label required">Название документа</label>
                    <input type="text" name="title" id="edit_doc_title" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Описание</label>
                    <textarea name="description" id="edit_doc_description" class="form-control" rows="2"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Папка</label>
                        <select name="category_id" id="edit_doc_category" class="form-control">
                            <option value="">Без категории</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= sanitize($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Привязать к сотруднику</label>
                        <select name="candidate_id" id="edit_doc_candidate" class="form-control">
                            <option value="">Не привязывать</option>
                            <?php foreach ($candidates as $cand): ?>
                                <option value="<?= $cand['id'] ?>"><?= sanitize($cand['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="checkbox" name="is_visible_to_candidate" id="edit_doc_visible" value="1">
                        <span>Сделать доступным для сотрудника</span>
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
function editCategory(cat) {
    document.getElementById('edit_cat_id').value = cat.id;
    document.getElementById('edit_cat_name').value = cat.name;
    document.getElementById('edit_cat_description').value = cat.description || '';
    document.getElementById('edit_cat_color').value = cat.color || '#6366f1';
    openModal(document.getElementById('editCategoryModal'));
}

function editDocument(doc) {
    document.getElementById('edit_doc_id').value = doc.id;
    document.getElementById('edit_doc_title').value = doc.title;
    document.getElementById('edit_doc_description').value = doc.description || '';
    document.getElementById('edit_doc_category').value = doc.category_id || '';
    document.getElementById('edit_doc_candidate').value = doc.candidate_id || '';
    document.getElementById('edit_doc_visible').checked = doc.is_visible_to_candidate == 1;
    openModal(document.getElementById('editDocModal'));
}
</script>

<?php require_once 'includes/footer.php'; ?>
