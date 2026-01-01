<?php
// admin_drones_section.php
?>
<div class="admin-section">
    <h2 class="section-title">🚁 Drones Management</h2>
    
    <!-- Add New Drone -->
    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
        <h3>➕ Add New Drone</h3>
        <form action="add_drone.php" method="POST" enctype="multipart/form-data">
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                <div>
                    <label>Brand:</label>
                    <input type="text" name="brand" required style="width: 100%; padding: 8px;">
                </div>
                <div>
                    <label>Model:</label>
                    <input type="text" name="model" required style="width: 100%; padding: 8px;">
                </div>
                <div>
                    <label>Price per Day ($):</label>
                    <input type="number" step="0.01" name="price" required style="width: 100%; padding: 8px;">
                </div>
                <div>
                    <label>Quantity:</label>
                    <input type="number" name="quantity" min="1" value="1" style="width: 100%; padding: 8px;">
                </div>
                <div style="grid-column: span 2;">
                    <label>Description:</label>
                    <textarea name="description" rows="3" style="width: 100%; padding: 8px;"></textarea>
                </div>
                <div style="grid-column: span 2;">
                    <label>Image:</label>
                    <input type="file" name="image" accept="image/*" style="width: 100%; padding: 8px;">
                </div>
            </div>
            <button type="submit" style="background: #27ae60; color: white; padding: 10px 20px; border: none; border-radius: 5px; margin-top: 15px;">
                Add Drone
            </button>
        </form>
    </div>
    
    <!-- Drones List -->
    <h3>📋 Current Drones</h3>
    <table style="width: 100%; border-collapse: collapse; background: white;">
        <thead>
            <tr style="background: #2c3e50; color: white;">
                <th style="padding: 10px;">ID</th>
                <th style="padding: 10px;">Brand/Model</th>
                <th style="padding: 10px;">Price/Day</th>
                <th style="padding: 10px;">Status</th>
                <th style="padding: 10px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            try {
                $stmt = $pdo->query("SELECT * FROM drones ORDER BY droneID DESC LIMIT 10");
                while ($drone = $stmt->fetch()):
            ?>
            <tr style="border-bottom: 1px solid #eee;">
                <td style="padding: 10px;">#<?= $drone['droneID'] ?></td>
                <td style="padding: 10px;">
                    <strong><?= htmlspecialchars($drone['name'] ?? $drone['Brand'] . ' ' . $drone['Model']) ?></strong>
                </td>
                <td style="padding: 10px;">$<?= number_format($drone['price'] ?? $drone['PricePerDay'], 2) ?></td>
                <td style="padding: 10px;">
                    <span style="padding: 4px 8px; border-radius: 12px; background: #d4edda; color: #155724;">
                        <?= $drone['status'] ?? 'available' ?>
                    </span>
                </td>
                <td style="padding: 10px;">
                    <a href="edit_drone.php?id=<?= $drone['droneID'] ?>" style="background: #3498db; color: white; padding: 5px 10px; border-radius: 4px; text-decoration: none; margin-right: 5px;">
                        Edit
                    </a>
                    <a href="remove_drone.php?id=<?= $drone['droneID'] ?>" style="background: #e74c3c; color: white; padding: 5px 10px; border-radius: 4px; text-decoration: none;">
                        Remove
                    </a>
                </td>
            </tr>
            <?php endwhile; ?>
            <?php } catch (Exception $e) { ?>
            <tr>
                <td colspan="5" style="padding: 20px; text-align: center; color: #e74c3c;">
                    Error loading drones: <?= $e->getMessage() ?>
                </td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
</div>