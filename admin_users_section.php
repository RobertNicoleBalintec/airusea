<?php
// admin_users_section.php - User Management Section
?>
<div class="admin-section">
    <h2 class="section-title">👥 User Management</h2>
    
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Joined</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $stmt = $pdo->query("
                SELECT UserID, Name, Email, role, status, created_at 
                FROM users 
                ORDER BY UserID DESC
                LIMIT 50
            ");
            
            while ($user = $stmt->fetch()):
            ?>
            <tr>
                <td>#<?= $user['UserID'] ?></td>
                <td><?= htmlspecialchars($user['Name'] ?? 'N/A') ?></td>
                <td><?= htmlspecialchars($user['Email']) ?></td>
                <td>
                    <span class="status-badge <?= $user['role'] ?>">
                        <?= ucfirst($user['role']) ?>
                    </span>
                </td>
                <td>
                    <span class="status-badge status-<?= $user['status'] ?>">
                        <?= ucfirst($user['status']) ?>
                    </span>
                </td>
                <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                <td>
                    <a href="manage_user.php?user_id=<?= $user['UserID'] ?>" class="btn btn-manage">
                        Manage
                    </a>
                    <?php if ($isSuperAdmin && $user['UserID'] != $_SESSION['UserID']): ?>
                    <a href="admin_promote.php?user_id=<?= $user['UserID'] ?>" 
                       class="btn" 
                       style="background: #8e44ad; color: white; margin-top: 5px;">
                       Promote
                    </a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>