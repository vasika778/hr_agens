<?php
$pageTitle = 'Пользователи';
require_once 'includes/header.php';

// Только админ может управлять пользователями
if (!hasRole('admin')) {
    header('Location: index.php');
    exit;
}

$success = '';
$error = '';

// Обработка POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
            $username = sanitize($_POST['username'] ?? '');
            $email = sanitize($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $fullName = sanitize($_POST['full_name'] ?? '');
            $role = $_POST['role'] ?? 'recruiter';
            
            if (empty($username) || empty($email) || empty($password) || empty($fullName)) {
                $error = 'Заполните все обязательные поля';
            } elseif (strlen($password) < 6) {
                $error = 'Пароль должен быть не менее 6 символов';
            } elseif (!isValidEmail($email)) {
                $error = 'Некорректный email';
            } else {
                // Проверяем уникальность
                $exists = db()->fetch(
                    "SELECT id FROM admins WHERE username = ? OR email = ?",
                    [$username, $email]
                );
                
                if ($exists) {
                    $error = 'Пользователь с таким логином или email уже существует';
                } else {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    db()->query(
                        "INSERT INTO admins (username, email, password, full_name, role) VALUES (?, ?, ?, ?, ?)",
                        [$username, $email, $hashedPassword, $fullName, $role]
                    );
                    $success = 'Пользователь создан';
                }
            }
            break;
            
        case 'update':
            $userId = (int)($_POST['user_id'] ?? 0);
            $email = sanitize($_POST['email'] ?? '');
            $fullName = sanitize($_POST['full_name'] ?? '');
            $role = $_POST['role'] ?? 'recruiter';
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $newPassword = $_POST['new_password'] ?? '';
            
            if ($userId && $fullName && $email) {
                // Нельзя изменить свою роль или деактивировать себя
                if ($userId === (int)$currentAdmin['id'] && ($role !== $currentAdmin['role'] || !$isActive)) {
                    $error = 'Вы не можете изменить свою роль или деактивировать себя';
                } else {
                    // Проверяем уникальность email
                    $exists = db()->fetch(
                        "SELECT id FROM admins WHERE email = ? AND id != ?",
                        [$email, $userId]
                    );
                    
                    if ($exists) {
                        $error = 'Email уже используется другим пользователем';
                    } else {
                        db()->query(
                            "UPDATE admins SET email = ?, full_name = ?, role = ?, is_active = ? WHERE id = ?",
                            [$email, $fullName, $role, $isActive, $userId]
                        );
                        
                        // Обновляем пароль если указан
                        if (!empty($newPassword)) {
                            if (strlen($newPassword) < 6) {
                                $error = 'Пароль должен быть не менее 6 символов';
                            } else {
                                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                                db()->query("UPDATE admins SET password = ? WHERE id = ?", [$hashedPassword, $userId]);
                            }
                        }
                        
                        if (!$error) {
                            $success = 'Пользователь обновлён';
                        }
                    }
                }
            }
            break;
            
        case 'delete':
            $userId = (int)($_POST['user_id'] ?? 0);
            
            if ($userId === (int)$currentAdmin['id']) {
                $error = 'Вы не можете удалить себя';
            } elseif ($userId) {
                db()->query("DELETE FROM admins WHERE id = ?", [$userId]);
                $success = 'Пользователь удалён';
            }
            break;
    }
}

// Получаем список пользователей
$users = db()->fetchAll("SELECT * FROM admins ORDER BY role, full_name");
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
    <div>
        <p class="text-muted" style="margin: 0;">
            Управление пользователями системы и их правами доступа
        </p>
    </div>
    <button class="btn btn-primary" data-modal="createUserModal">
        ➕ Добавить пользователя
    </button>
</div>

<!-- Roles Info -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
    <?php foreach (USER_ROLES as $roleKey => $roleData): ?>
        <div class="card" style="border-left: 4px solid <?= $roleData['color'] ?>;">
            <div class="card-body" style="padding: 1rem;">
                <div style="font-weight: 600; color: <?= $roleData['color'] ?>; margin-bottom: 0.25rem;">
                    <?= $roleData['name'] ?>
                </div>
                <div style="font-size: 0.8125rem; color: var(--text-muted);">
                    <?= $roleData['description'] ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Users Table -->
<div class="card">
    <div class="card-body" style="padding: 0;">
        <table class="table">
            <thead>
                <tr>
                    <th>Пользователь</th>
                    <th>Логин</th>
                    <th>Email</th>
                    <th>Роль</th>
                    <th>Статус</th>
                    <th>Последний вход</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr style="<?= !$user['is_active'] ? 'opacity: 0.5;' : '' ?>">
                        <td>
                            <div class="d-flex align-center gap-2">
                                <div style="width: 36px; height: 36px; background: <?= getRoleColor($user['role']) ?>; border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; font-weight: 600; color: white; font-size: 0.875rem;">
                                    <?= mb_substr($user['full_name'], 0, 1) ?>
                                </div>
                                <div>
                                    <div style="font-weight: 500;">
                                        <?= sanitize($user['full_name']) ?>
                                        <?php if ($user['id'] === (int)$currentAdmin['id']): ?>
                                            <span style="font-size: 0.75rem; color: var(--text-muted);">(вы)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="text-muted"><?= sanitize($user['username']) ?></td>
                        <td class="text-muted"><?= sanitize($user['email']) ?></td>
                        <td>
                            <span style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.25rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; background: <?= getRoleColor($user['role']) ?>20; color: <?= getRoleColor($user['role']) ?>;">
                                <?= getRoleName($user['role']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($user['is_active']): ?>
                                <span class="badge badge-approved">Активен</span>
                            <?php else: ?>
                                <span class="badge badge-rejected">Отключён</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted" style="font-size: 0.875rem;">
                            <?= $user['last_login'] ? formatDate($user['last_login']) : '—' ?>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <button class="btn btn-sm btn-secondary" 
                                        onclick="editUser(<?= htmlspecialchars(json_encode($user)) ?>)"
                                        title="Редактировать">
                                    ✏️
                                </button>
                                <?php if ($user['id'] !== (int)$currentAdmin['id']): ?>
                                    <form action="" method="POST" style="display: contents;" onsubmit="return confirm('Удалить пользователя?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Удалить">🗑️</button>
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

<!-- Create User Modal -->
<div class="modal-overlay" id="createUserModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Добавить пользователя</h3>
            <button class="modal-close" data-modal-close>×</button>
        </div>
        <form action="" method="POST">
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label required">ФИО</label>
                    <input type="text" name="full_name" class="form-control" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">Логин</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">Пароль</label>
                        <input type="password" name="password" class="form-control" required minlength="6">
                        <div class="form-text">Минимум 6 символов</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">Роль</label>
                        <select name="role" class="form-control" required>
                            <?php foreach (USER_ROLES as $roleKey => $roleData): ?>
                                <option value="<?= $roleKey ?>"><?= $roleData['name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Отмена</button>
                <button type="submit" class="btn btn-primary">Создать</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal-overlay" id="editUserModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Редактировать пользователя</h3>
            <button class="modal-close" data-modal-close>×</button>
        </div>
        <form action="" method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="user_id" id="edit_user_id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label required">ФИО</label>
                    <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Логин</label>
                        <input type="text" id="edit_username" class="form-control" disabled>
                        <div class="form-text">Логин нельзя изменить</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">Email</label>
                        <input type="email" name="email" id="edit_email" class="form-control" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Новый пароль</label>
                        <input type="password" name="new_password" class="form-control" minlength="6">
                        <div class="form-text">Оставьте пустым, чтобы не менять</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">Роль</label>
                        <select name="role" id="edit_role" class="form-control" required>
                            <?php foreach (USER_ROLES as $roleKey => $roleData): ?>
                                <option value="<?= $roleKey ?>"><?= $roleData['name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="checkbox" name="is_active" id="edit_is_active" value="1">
                        <span>Пользователь активен</span>
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
function editUser(user) {
    document.getElementById('edit_user_id').value = user.id;
    document.getElementById('edit_full_name').value = user.full_name;
    document.getElementById('edit_username').value = user.username;
    document.getElementById('edit_email').value = user.email;
    document.getElementById('edit_role').value = user.role;
    document.getElementById('edit_is_active').checked = user.is_active == 1;
    openModal(document.getElementById('editUserModal'));
}
</script>

<?php require_once 'includes/footer.php'; ?>
