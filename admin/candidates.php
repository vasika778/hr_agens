<?php
$pageTitle = 'Кандидаты';
require_once 'includes/header.php';

// Параметры фильтрации
$status = $_GET['status'] ?? '';
$groupId = (int)($_GET['group_id'] ?? 0);
$search = sanitize($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

// Построение запроса
$where = ['1=1'];
$params = [];

if ($status && array_key_exists($status, CANDIDATE_STATUSES)) {
    $where[] = 'c.status = ?';
    $params[] = $status;
}

if ($groupId > 0) {
    $where[] = 'c.group_id = ?';
    $params[] = $groupId;
} else {
    // По умолчанию показываем только группу "Кандидаты"
    $where[] = 'c.group_id = 1';
}

if ($search) {
    $where[] = '(c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)';
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$whereClause = implode(' AND ', $where);

// Получаем общее количество
$totalCount = db()->fetch(
    "SELECT COUNT(*) as count FROM candidates c WHERE $whereClause",
    $params
)['count'];

$pagination = paginate($totalCount, $page, $perPage);

// Получаем кандидатов
$candidates = db()->fetchAll(
    "SELECT c.*, p.name as position_name, g.name as group_name, g.color as group_color
     FROM candidates c 
     LEFT JOIN positions p ON c.position_id = p.id 
     LEFT JOIN candidate_groups g ON c.group_id = g.id 
     WHERE $whereClause 
     ORDER BY c.created_at DESC 
     LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}",
    $params
);

$groups = getGroups();
?>

<!-- Filters -->
<div class="filters">
    <form action="" method="GET" class="d-flex gap-2" style="flex-wrap: wrap; width: 100%;">
        <div class="search-box">
            <span>🔍</span>
            <input type="text" 
                   name="search" 
                   placeholder="Поиск по имени, email, телефону..." 
                   value="<?= $search ?>">
        </div>

        <div class="filter-group">
            <select name="status" class="filter-select" onchange="this.form.submit()">
                <option value="">Все статусы</option>
                <?php foreach (CANDIDATE_STATUSES as $key => $name): ?>
                    <option value="<?= $key ?>" <?= $status === $key ? 'selected' : '' ?>>
                        <?= $name ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-group">
            <select name="group_id" class="filter-select" onchange="this.form.submit()">
                <option value="0">Все группы</option>
                <?php foreach ($groups as $group): ?>
                    <option value="<?= $group['id'] ?>" <?= $groupId === (int)$group['id'] ? 'selected' : '' ?>>
                        <?= sanitize($group['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="btn btn-secondary">
            Применить
        </button>

        <?php if ($search || $status || $groupId): ?>
            <a href="candidates.php" class="btn btn-secondary">
                Сбросить
            </a>
        <?php endif; ?>
    </form>
</div>

<!-- Candidates Table -->
<div class="card">
    <div class="card-header">
        <span>
            Найдено: <strong><?= $totalCount ?></strong> кандидатов
        </span>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($candidates)): ?>
            <div class="empty-state">
                <div class="empty-icon">📭</div>
                <div class="empty-title">Кандидаты не найдены</div>
                <div class="empty-text">Попробуйте изменить параметры фильтрации</div>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Кандидат</th>
                            <th>Контакты</th>
                            <th>Должность</th>
                            <th>Статус</th>
                            <th>Группа</th>
                            <th>Дата подачи</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($candidates as $candidate): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-center gap-2">
                                        <div style="width: 40px; height: 40px; background: var(--accent-gradient); border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; font-weight: 600; color: white; font-size: 0.875rem;">
                                            <?= mb_substr($candidate['name'], 0, 1) ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 500;">
                                                <a href="candidate.php?id=<?= $candidate['id'] ?>" style="color: inherit;">
                                                    <?= sanitize($candidate['name']) ?>
                                                </a>
                                            </div>
                                            <?php if ($candidate['test_passed']): ?>
                                                <span style="font-size: 0.75rem; color: var(--success);">✓ Тест пройден</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size: 0.875rem;">
                                        <div><?= sanitize($candidate['email']) ?></div>
                                        <div class="text-muted"><?= sanitize($candidate['phone']) ?></div>
                                    </div>
                                </td>
                                <td>
                                    <?= sanitize($candidate['position_name'] ?? '—') ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?= $candidate['status'] ?>">
                                        <span class="status-dot"></span>
                                        <?= getStatusName($candidate['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="display: inline-flex; align-items: center; gap: 0.375rem; font-size: 0.8125rem;">
                                        <span style="width: 8px; height: 8px; border-radius: 50%; background: <?= $candidate['group_color'] ?>;"></span>
                                        <?= sanitize($candidate['group_name']) ?>
                                    </span>
                                </td>
                                <td class="text-muted" style="font-size: 0.875rem;">
                                    <?= formatDate($candidate['created_at']) ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="candidate.php?id=<?= $candidate['id'] ?>" class="btn btn-sm btn-secondary" title="Просмотр">
                                            👁️
                                        </a>
                                        <?php if ($candidate['resume_file']): ?>
                                            <a href="../uploads/resumes/<?= $candidate['resume_file'] ?>" 
                                               class="btn btn-sm btn-secondary" 
                                               target="_blank"
                                               title="Скачать резюме">
                                                📄
                                            </a>
                                        <?php endif; ?>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-secondary dropdown-toggle" title="Действия">
                                                ⋮
                                            </button>
                                            <div class="dropdown-menu">
                                                <a href="candidate.php?id=<?= $candidate['id'] ?>&action=edit" class="dropdown-item">
                                                    ✏️ Редактировать
                                                </a>
                                                <a href="candidate.php?id=<?= $candidate['id'] ?>&action=status" class="dropdown-item">
                                                    🔄 Изменить статус
                                                </a>
                                                <a href="candidate.php?id=<?= $candidate['id'] ?>&action=group" class="dropdown-item">
                                                    🏷️ Изменить группу
                                                </a>
                                                <div class="dropdown-divider"></div>
                                                <a href="candidate.php?id=<?= $candidate['id'] ?>&action=link" class="dropdown-item">
                                                    🔗 Ссылка на ЛК
                                                </a>
                                                <div class="dropdown-divider"></div>
                                                <a href="candidate-delete.php?id=<?= $candidate['id'] ?>" 
                                                   class="dropdown-item danger"
                                                   data-confirm="Вы уверены, что хотите удалить этого кандидата?">
                                                    🗑️ Удалить
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($pagination['total_pages'] > 1): ?>
        <div class="card-footer">
            <div class="pagination">
                <?php if ($pagination['has_prev']): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="page-link">
                        ←
                    </a>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($pagination['total_pages'], $page + 2); $i++): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                       class="page-link <?= $i === $page ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($pagination['has_next']): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="page-link">
                        →
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
