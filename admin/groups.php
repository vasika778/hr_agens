<?php
$pageTitle = 'Группы кандидатов';
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
            $color = sanitize($_POST['color'] ?? '#6366f1');
            
            if (empty($name)) {
                $error = 'Укажите название группы';
            } else {
                db()->query(
                    "INSERT INTO candidate_groups (name, description, color, is_system) VALUES (?, ?, ?, 0)",
                    [$name, $description, $color]
                );
                $success = 'Группа создана';
            }
            break;
            
        case 'update':
            $id = (int)($_POST['id'] ?? 0);
            $name = sanitize($_POST['name'] ?? '');
            $description = sanitize($_POST['description'] ?? '');
            $color = sanitize($_POST['color'] ?? '#6366f1');
            
            if ($id && $name) {
                // Проверяем, не системная ли группа
                $group = db()->fetch("SELECT is_system FROM candidate_groups WHERE id = ?", [$id]);
                if ($group && !$group['is_system']) {
                    db()->query(
                        "UPDATE candidate_groups SET name = ?, description = ?, color = ? WHERE id = ?",
                        [$name, $description, $color, $id]
                    );
                    $success = 'Группа обновлена';
                } else {
                    // Для системных групп обновляем только описание и цвет
                    db()->query(
                        "UPDATE candidate_groups SET description = ?, color = ? WHERE id = ?",
                        [$description, $color, $id]
                    );
                    $success = 'Группа обновлена';
                }
            }
            break;
            
        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            if ($id) {
                $group = db()->fetch("SELECT is_system FROM candidate_groups WHERE id = ?", [$id]);
                if ($group && !$group['is_system']) {
                    // Перемещаем кандидатов в группу "Кандидаты"
                    db()->query("UPDATE candidates SET group_id = 1 WHERE group_id = ?", [$id]);
                    db()->query("DELETE FROM candidate_groups WHERE id = ?", [$id]);
                    $success = 'Группа удалена. Кандидаты перемещены в группу "Кандидаты"';
                } else {
                    $error = 'Нельзя удалить системную группу';
                }
            }
            break;
    }
}

// Получаем группы с количеством кандидатов
$groups = db()->fetchAll(
    "SELECT g.*, COUNT(c.id) as candidates_count 
     FROM candidate_groups g 
     LEFT JOIN candidates c ON c.group_id = g.id 
     GROUP BY g.id 
     ORDER BY g.is_system DESC, g.name"
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
    <p class="text-muted" style="margin: 0;">
        Системные группы нельзя удалить, но можно изменить их описание и цвет
    </p>
    <button class="btn btn-primary" data-modal="addModal">
        ➕ Создать группу
    </button>
</div>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <table class="table">
            <thead>
                <tr>
                    <th>Группа</th>
                    <th>Описание</th>
                    <th>Кандидатов</th>
                    <th>Тип</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($groups as $group): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-center gap-2">
                                <span style="width: 16px; height: 16px; border-radius: 50%; background: <?= $group['color'] ?>;"></span>
                                <span style="font-weight: 500;"><?= sanitize($group['name']) ?></span>
                            </div>
                        </td>
                        <td class="text-muted"><?= sanitize($group['description'] ?? '—') ?></td>
                        <td>
                            <a href="candidates.php?group_id=<?= $group['id'] ?>">
                                <?= $group['candidates_count'] ?>
                            </a>
                        </td>
                        <td>
                            <?php if ($group['is_system']): ?>
                                <span class="badge badge-reviewing">Системная</span>
                            <?php else: ?>
                                <span class="badge badge-new">Пользовательская</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <button class="btn btn-sm btn-secondary" 
                                        onclick="editItem(<?= htmlspecialchars(json_encode($group)) ?>)">
                                    ✏️
                                </button>
                                <?php if (!$group['is_system']): ?>
                                    <form action="" method="POST" style="display: inline;" 
                                          onsubmit="return confirm('Удалить группу? Кандидаты будут перемещены в группу Кандидаты.');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $group['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Создать группу</h3>
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

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Редактировать группу</h3>
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
                    <label class="form-label">Цвет</label>
                    <input type="color" name="color" id="edit_color" class="form-control" style="height: 50px; padding: 5px;">
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
    document.getElementById('edit_name').disabled = item.is_system == 1;
    document.getElementById('edit_description').value = item.description || '';
    document.getElementById('edit_color').value = item.color || '#6366f1';
    openModal(document.getElementById('editModal'));
}
</script>

<?php require_once 'includes/footer.php'; ?>
