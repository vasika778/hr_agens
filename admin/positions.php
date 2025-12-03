<?php
$pageTitle = 'Должности';
require_once 'includes/header.php';

$success = '';
$error = '';

// Обработка POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
            $name = sanitize($_POST['name'] ?? '');
            $description = sanitize($_POST['description'] ?? '');
            
            if (empty($name)) {
                $error = 'Укажите название должности';
            } else {
                db()->query(
                    "INSERT INTO positions (name, description) VALUES (?, ?)",
                    [$name, $description]
                );
                $success = 'Должность добавлена';
            }
            break;
            
        case 'update':
            $id = (int)($_POST['id'] ?? 0);
            $name = sanitize($_POST['name'] ?? '');
            $description = sanitize($_POST['description'] ?? '');
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            
            if ($id && $name) {
                db()->query(
                    "UPDATE positions SET name = ?, description = ?, is_active = ? WHERE id = ?",
                    [$name, $description, $isActive, $id]
                );
                $success = 'Должность обновлена';
            }
            break;
            
        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            if ($id) {
                db()->query("DELETE FROM positions WHERE id = ?", [$id]);
                $success = 'Должность удалена';
            }
            break;
            
        case 'toggle':
            $id = (int)($_POST['id'] ?? 0);
            if ($id) {
                db()->query("UPDATE positions SET is_active = NOT is_active WHERE id = ?", [$id]);
                $success = 'Статус изменён';
            }
            break;
    }
}

// Получаем должности с количеством кандидатов
$positions = db()->fetchAll(
    "SELECT p.*, COUNT(c.id) as candidates_count 
     FROM positions p 
     LEFT JOIN candidates c ON c.position_id = p.id 
     GROUP BY p.id 
     ORDER BY p.name"
);
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
    <div></div>
    <button class="btn btn-primary" data-modal="addModal">
        ➕ Добавить должность
    </button>
</div>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <?php if (empty($positions)): ?>
            <div class="empty-state">
                <div class="empty-icon">📋</div>
                <div class="empty-title">Должности не добавлены</div>
            </div>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Название</th>
                        <th>Описание</th>
                        <th>Кандидатов</th>
                        <th>Статус</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($positions as $position): ?>
                        <tr style="<?= !$position['is_active'] ? 'opacity: 0.5;' : '' ?>">
                            <td style="font-weight: 500;"><?= sanitize($position['name']) ?></td>
                            <td class="text-muted"><?= sanitize($position['description'] ?? '—') ?></td>
                            <td>
                                <a href="candidates.php?position_id=<?= $position['id'] ?>">
                                    <?= $position['candidates_count'] ?>
                                </a>
                            </td>
                            <td>
                                <?php if ($position['is_active']): ?>
                                    <span class="badge badge-approved">Активна</span>
                                <?php else: ?>
                                    <span class="badge badge-rejected">Скрыта</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <button class="btn btn-sm btn-secondary" 
                                            onclick="editItem(<?= htmlspecialchars(json_encode($position)) ?>)">
                                        ✏️
                                    </button>
                                    <form action="" method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?= $position['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-secondary">
                                            <?= $position['is_active'] ? '🔴' : '🟢' ?>
                                        </button>
                                    </form>
                                    <?php if ($position['candidates_count'] == 0): ?>
                                        <form action="" method="POST" style="display: inline;" onsubmit="return confirm('Удалить должность?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $position['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Add Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Добавить должность</h3>
            <button class="modal-close" data-modal-close>×</button>
        </div>
        <form action="" method="POST">
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label required">Название</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Описание</label>
                    <textarea name="description" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Отмена</button>
                <button type="submit" class="btn btn-primary">Добавить</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Редактировать должность</h3>
            <button class="modal-close" data-modal-close>×</button>
        </div>
        <form action="" method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label required">Название</label>
                    <input type="text" name="name" id="edit_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Описание</label>
                    <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="checkbox" name="is_active" id="edit_is_active" value="1">
                        <span>Должность активна</span>
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
function editItem(item) {
    document.getElementById('edit_id').value = item.id;
    document.getElementById('edit_name').value = item.name;
    document.getElementById('edit_description').value = item.description || '';
    document.getElementById('edit_is_active').checked = item.is_active == 1;
    openModal(document.getElementById('editModal'));
}
</script>

<?php require_once 'includes/footer.php'; ?>
