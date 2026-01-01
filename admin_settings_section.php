<?php
// admin_settings_section.php - SUPER-ADMIN ONLY
?>
<div class="admin-section">
    <h2 class="section-title">⚙️ System Settings (Super-Admin Only)</h2>
    
    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 30px;">
        <!-- Penalty Settings -->
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h3>💰 Penalty Settings</h3>
            <form action="update_settings.php" method="POST">
                <div style="margin-bottom: 15px;">
                    <label>Overdue Penalty per Day:</label>
                    <input type="number" step="0.01" name="penalty_per_day" value="100.00" style="width: 100%; padding: 8px;">
                </div>
                <div style="margin-bottom: 15px;">
                    <label>Grace Period (days):</label>
                    <input type="number" name="grace_period" value="1" min="0" style="width: 100%; padding: 8px;">
                </div>
                <button type="submit" name="update_penalty" style="background: #27ae60; color: white; padding: 10px 20px; border: none; border-radius: 5px;">
                    Update Penalty
                </button>
            </form>
        </div>
        
        <!-- Rental Limits -->
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h3>📅 Rental Limits</h3>
            <form action="update_settings.php" method="POST">
                <div style="margin-bottom: 15px;">
                    <label>Max Rental Days:</label>
                    <input type="number" name="max_rental_days" value="30" min="1" style="width: 100%; padding: 8px;">
                </div>
                <div style="margin-bottom: 15px;">
                    <label>Min Rental Days:</label>
                    <input type="number" name="min_rental_days" value="1" min="1" style="width: 100%; padding: 8px;">
                </div>
                <button type="submit" name="update_limits" style="background: #3498db; color: white; padding: 10px 20px; border: none; border-radius: 5px;">
                    Update Limits
                </button>
            </form>
        </div>
        
        <!-- System Controls -->
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); grid-column: span 2;">
            <h3>🔧 System Controls</h3>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 20px;">
                <div>
                    <h4>Maintenance Mode</h4>
                    <form action="update_settings.php" method="POST">
                        <label style="display: block; margin-bottom: 10px;">
                            <input type="checkbox" name="maintenance_mode" value="1">
                            Enable Maintenance Mode
                        </label>
                        <button type="submit" name="toggle_maintenance" style="background: #f39c12; color: white; padding: 8px 15px; border: none; border-radius: 5px;">
                            Update
                        </button>
                    </form>
                </div>
                
                <div>
                    <h4>New User Registration</h4>
                    <form action="update_settings.php" method="POST">
                        <label style="display: block; margin-bottom: 10px;">
                            <input type="checkbox" name="allow_registration" value="1" checked>
                            Allow New Registrations
                        </label>
                        <button type="submit" name="toggle_registration" style="background: #9b59b6; color: white; padding: 8px 15px; border: none; border-radius: 5px;">
                            Update
                        </button>
                    </form>
                </div>
                
                <div>
                    <h4>Owner Approvals</h4>
                    <form action="update_settings.php" method="POST">
                        <label style="display: block; margin-bottom: 10px;">
                            <input type="checkbox" name="require_owner_approval" value="1" checked>
                            Require Admin Approval for Owners
                        </label>
                        <button type="submit" name="toggle_owner_approval" style="background: #1abc9c; color: white; padding: 8px 15px; border: none; border-radius: 5px;">
                            Update
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Danger Zone -->
        <div style="background: #fff5f5; padding: 20px; border-radius: 8px; border: 2px solid #fc8181; grid-column: span 2;">
            <h3 style="color: #e53e3e;">⚠️ Danger Zone</h3>
            <p style="color: #742a2a;">These actions are irreversible. Use with extreme caution.</p>
            
            <div style="margin-top: 20px; display: flex; gap: 15px;">
                <form action="system_backup.php" method="POST" style="flex: 1;">
                    <button type="submit" style="background: #2c3e50; color: white; padding: 12px; border: none; border-radius: 5px; width: 100%;">
                        💾 Backup Database
                    </button>
                </form>
                
                <form action="clear_logs.php" method="POST" onsubmit="return confirm('Are you sure you want to clear all system logs?')" style="flex: 1;">
                    <button type="submit" style="background: #e67e22; color: white; padding: 12px; border: none; border-radius: 5px; width: 100%;">
                        🗑️ Clear System Logs
                    </button>
                </form>
                
                <form action="purge_inactive.php" method="POST" onsubmit="return confirm('This will delete all inactive users. Continue?')" style="flex: 1;">
                    <button type="submit" style="background: #e74c3c; color: white; padding: 12px; border: none; border-radius: 5px; width: 100%;">
                        🗑️ Purge Inactive Users
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>