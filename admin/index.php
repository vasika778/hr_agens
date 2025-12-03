<?php
$pageTitle = 'Панель управления';
require_once 'includes/header.php';

// Получаем статистику
$totalCandidates = db()->fetch("SELECT COUNT(*) as count FROM candidates")['count'];
$newCandidates = db()->fetch("SELECT COUNT(*) as count FROM candidates WHERE status = 'new'")['count'];
$totalEmployees = db()->fetch("SELECT COUNT(*) as count FROM candidates WHERE group_id = 2")['count'];
$pendingTests = db()->fetch("SELECT COUNT(*) as count FROM candidates WHERE status = 'testing'")['count'];

// Последние кандидаты
$recentCandidates = db()->fetchAll(
    "SELECT c.*, p.name as position_name 
     FROM candidates c 
     LEFT JOIN positions p ON c.position_id = p.id 
     ORDER BY c.created_at DESC 
     LIMIT 5"
);

// Статистика по статусам
$statusStats = db()->fetchAll(
    "SELECT status, COUNT(*) as count FROM candidates GROUP BY status"
);
$statusData = [];
foreach ($statusStats as $stat) {
    $statusData[$stat['status']] = $stat['count'];
}
?>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon primary">👥</div>
        <div class="stat-content">
            <div class="stat-value"><?= $totalCandidates ?></div>
            <div class="stat-label">Всего кандидатов</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon warning">🆕</div>
        <div class="stat-content">
            <div class="stat-value"><?= $newCandidates ?></div>
            <div class="stat-label">Новых заявок</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon success">💼</div>
        <div class="stat-content">
            <div class="stat-value"><?= $totalEmployees ?></div>
            <div class="stat-label">Сотрудников</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon danger">📝</div>
        <div class="stat-content">
            <div class="stat-value"><?= $pendingTests ?></div>
            <div class="stat-label">На тестировании</div>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
    <!-- Recent Candidates -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <span>📋</span>
                Последние заявки
            </h3>
            <a href="candidates.php" class="btn btn-sm btn-secondary">Все заявки</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (empty($recentCandidates)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📭</div>
                    <div class="empty-title">Пока нет заявок</div>
                    <div class="empty-text">Новые заявки появятся здесь</div>
                </div>
            <?php else: ?>
                <table class="table">
                    <tbody>
                        <?php foreach ($recentCandidates as $candidate): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 500;"><?= sanitize($candidate['name']) ?></div>
                                    <div class="text-muted" style="font-size: 0.8125rem;">
                                        <?= sanitize($candidate['position_name'] ?? 'Не указано') ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-<?= $candidate['status'] ?>">
                                        <span class="status-dot"></span>
                                        <?= getStatusName($candidate['status']) ?>
                                    </span>
                                </td>
                                <td class="text-muted" style="font-size: 0.8125rem;">
                                    <?= formatDate($candidate['created_at'], 'd.m.Y') ?>
                                </td>
                                <td>
                                    <a href="candidate.php?id=<?= $candidate['id'] ?>" class="btn btn-sm btn-secondary">
                                        Открыть
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Status Distribution -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <span>📊</span>
                Распределение по статусам
            </h3>
        </div>
        <div class="card-body">
            <?php foreach (CANDIDATE_STATUSES as $status => $name): ?>
                <?php 
                $count = $statusData[$status] ?? 0;
                $percentage = $totalCandidates > 0 ? round(($count / $totalCandidates) * 100) : 0;
                ?>
                <div style="margin-bottom: 1rem;">
                    <div class="d-flex justify-between align-center mb-1">
                        <span style="font-size: 0.875rem;">
                            <span class="badge badge-<?= $status ?>" style="margin-right: 0.5rem;">
                                <span class="status-dot"></span>
                                <?= $name ?>
                            </span>
                        </span>
                        <span class="text-muted" style="font-size: 0.875rem;">
                            <?= $count ?> (<?= $percentage ?>%)
                        </span>
                    </div>
                    <div class="progress-bar" style="height: 6px;">
                        <div class="progress-fill" style="width: <?= $percentage ?>%;"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="card mt-3">
    <div class="card-header">
        <h3 class="card-title">
            <span>⚡</span>
            Быстрые действия
        </h3>
    </div>
    <div class="card-body">
        <div class="d-flex gap-2" style="flex-wrap: wrap;">
            <a href="candidates.php?status=new" class="btn btn-secondary">
                🆕 Новые заявки (<?= $newCandidates ?>)
            </a>
            <a href="candidates.php?status=interview" class="btn btn-secondary">
                📞 На собеседование
            </a>
            <a href="tests.php" class="btn btn-secondary">
                📝 Управление тестами
            </a>
            <a href="about.php" class="btn btn-secondary">
                ℹ️ Редактировать "О компании"
            </a>
            <a href="positions.php" class="btn btn-secondary">
                📋 Управление должностями
            </a>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
