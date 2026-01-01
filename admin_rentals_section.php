<?php
// admin_rentals_section.php
?>
<div class="admin-section">
    <h2 class="section-title">📋 Rentals Management</h2>
    
    <?php
    try {
        // Get rentals
        $stmt = $pdo->query("
            SELECT r.*, u.name as userName, u.email, d.name as droneName
            FROM rentals r
            JOIN users u ON r.userID = u.userID
            JOIN drones d ON r.droneID = d.droneID
            ORDER BY r.rentID DESC
            LIMIT 20
        ");
    ?>
    
    <div style="display: flex; gap: 15px; margin-bottom: 20px;">
        <button onclick="filterRentals('all')" style="padding: 8px 15px; background: #3498db; color: white; border: none; border-radius: 4px;">
            All Rentals
        </button>
        <button onclick="filterRentals('pending')" style="padding: 8px 15px; background: #f39c12; color: white; border: none; border-radius: 4px;">
            Pending
        </button>
        <button onclick="filterRentals('approved')" style="padding: 8px 15px; background: #27ae60; color: white; border: none; border-radius: 4px;">
            Active
        </button>
        <button onclick="filterRentals('overdue')" style="padding: 8px 15px; background: #e74c3c; color: white; border: none; border-radius: 4px;">
            Overdue
        </button>
    </div>
    
    <table style="width: 100%; border-collapse: collapse; background: white;">
        <thead>
            <tr style="background: #2c3e50; color: white;">
                <th style="padding: 10px;">Rental ID</th>
                <th style="padding: 10px;">User</th>
                <th style="padding: 10px;">Drone</th>
                <th style="padding: 10px;">Period</th>
                <th style="padding: 10px;">Total</th>
                <th style="padding: 10px;">Status</th>
                <th style="padding: 10px;">Actions</th>
            </tr>
        </thead>
        <tbody id="rentalsTable">
            <?php while ($rental = $stmt->fetch()): 
                $status_color = [
                    'pending' => '#f39c12',
                    'approved' => '#27ae60',
                    'overdue' => '#e74c3c',
                    'completed' => '#95a5a6'
                ][$rental['status']] ?? '#95a5a6';
            ?>
            <tr style="border-bottom: 1px solid #eee;" data-status="<?= $rental['status'] ?>">
                <td style="padding: 10px;">#<?= $rental['rentID'] ?></td>
                <td style="padding: 10px;">
                    <div><?= htmlspecialchars($rental['userName']) ?></div>
                    <small style="color: #7f8c8d;"><?= $rental['email'] ?></small>
                </td>
                <td style="padding: 10px;"><?= htmlspecialchars($rental['droneName']) ?></td>
                <td style="padding: 10px;">
                    <?= date('M d', strtotime($rental['rentstart'])) ?> - 
                    <?= date('M d, Y', strtotime($rental['rentdue'])) ?>
                </td>
                <td style="padding: 10px;">
                    <strong>$<?= number_format($rental['totalprice'], 2) ?></strong>
                    <?php if ($rental['penalty'] > 0): ?>
                        <br><small style="color: #e74c3c;">+$<?= number_format($rental['penalty'], 2) ?> penalty</small>
                    <?php endif; ?>
                </td>
                <td style="padding: 10px;">
                    <span style="padding: 4px 8px; border-radius: 12px; background: <?= $status_color ?>; color: white;">
                        <?= ucfirst($rental['status']) ?>
                    </span>
                </td>
                <td style="padding: 10px;">
                    <?php if ($rental['status'] == 'pending'): ?>
                        <a href="approve_rental.php?id=<?= $rental['rentID'] ?>" style="background: #27ae60; color: white; padding: 5px 10px; border-radius: 4px; text-decoration: none; margin-right: 5px;">
                            Approve
                        </a>
                        <a href="reject_rental.php?id=<?= $rental['rentID'] ?>" style="background: #e74c3c; color: white; padding: 5px 10px; border-radius: 4px; text-decoration: none;">
                            Reject
                        </a>
                    <?php elseif ($rental['status'] == 'approved'): ?>
                        <a href="mark_returned.php?id=<?= $rental['rentID'] ?>" style="background: #3498db; color: white; padding: 5px 10px; border-radius: 4px; text-decoration: none;">
                            Mark Returned
                        </a>
                    <?php else: ?>
                        <span style="color: #95a5a6;">No actions</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    
    <script>
    function filterRentals(status) {
        const rows = document.querySelectorAll('#rentalsTable tr');
        rows.forEach(row => {
            if (status === 'all' || row.getAttribute('data-status') === status) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }
    </script>
    
    <?php } catch (Exception $e) { ?>
    <div style="background: #f8d7da; color: #721c24; padding: 20px; border-radius: 8px;">
        <h3>❌ Error Loading Rentals</h3>
        <p><?= $e->getMessage() ?></p>
    </div>
    <?php } ?>
</div>