<?php
$pageTitle = 'Сотрудники';
require_once 'includes/header.php';

// Параметры фильтрации
$search = sanitize($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

// Группа "Постоянные сотрудники" имеет id = 2
$groupId = 2;

// Построение запроса
$where = ['c.group_id = ?'];
$params = [$groupId];

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

// Получаем сотрудников
$employees = db()->fetchAll(
    "SELECT c.*, p.name as position_name
     FROM candidates c 
     LEFT JOIN positions p ON c.position_id = p.id 
     WHERE $whereClause 
     ORDER BY c.updated_at DESC 
     LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}",
    $params
);
?>

<!-- Filters -->
<div class="filters">
    <form action="" method="GET" class="d-flex gap-2">
        <div class="search-box">
            <span>🔍</span>
            <input type="text" 
                   name="search" 
                   placeholder="Поиск по имени, email, телефону..." 
                   value="<?= $search ?>">
        </div>
        <button type="submit" class="btn btn-secondary">
            Найти
        </button>
        <?php if ($search): ?>
            <a href="employees.php" class="btn btn-secondary">
                Сбросить
            </a>
        <?php endif; ?>
    </form>
</div>

<!-- Employees Table -->
<div class="card">
    <div class="card-header">
        <span>
            Всего сотрудников: <strong><?= $totalCount ?></strong>
        </span>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($employees)): ?>
            <div class="empty-state">
                <div class="empty-icon">💼</div>
                <div class="empty-title">Сотрудники не найдены</div>
                <div class="empty-text">Переведите кандидатов в группу "Постоянные сотрудники"</div>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Сотрудник</th>
                            <th>Контакты</th>
                            <th>Должность</th>
                            <th>Статус</th>
                            <th>Результат теста</th>
                            <th>Дата оформления</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $employee): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-center gap-2">
                                        <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #10b981, #059669); border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; font-weight: 600; color: white; font-size: 0.875rem;">
                                            <?= mb_substr($employee['name'], 0, 1) ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 500;">
                                                <a href="candidate.php?id=<?= $employee['id'] ?>" style="color: inherit;">
                                                    <?= sanitize($employee['name']) ?>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size: 0.875rem;">
                                        <div><?= sanitize($employee['email']) ?></div>
                                        <div class="text-muted"><?= sanitize($employee['phone']) ?></div>
                                    </div>
                                </td>
                                <td>
                                    <?= sanitize($employee['position_name'] ?? '—') ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?= $employee['status'] ?>">
                                        <span class="status-dot"></span>
                                        <?= getStatusName($employee['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($employee['test_passed']): ?>
                                        <span class="text-success">✓ Пройден (<?= $employee['test_score'] ?> б.)</span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted" style="font-size: 0.875rem;">
                                    <?= formatDate($employee['updated_at']) ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="candidate.php?id=<?= $employee['id'] ?>" class="btn btn-sm btn-secondary" title="Просмотр">
                                            👁️
                                        </a>
                                        <?php if ($employee['resume_file']): ?>
                                            <a href="../uploads/resumes/<?= $employee['resume_file'] ?>" 
                                               class="btn btn-sm btn-secondary" 
                                               target="_blank"
                                               title="Скачать резюме">
                                                📄
                                            </a>
                                        <?php endif; ?>
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
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="page-link">←</a>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($pagination['total_pages'], $page + 2); $i++): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                       class="page-link <?= $i === $page ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($pagination['has_next']): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="page-link">→</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
