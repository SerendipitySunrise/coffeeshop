<?php
// handle_inventory_update.php
// Handles batch stock updates submitted from the inventory table.

include('../includes/db_connect.php'); 
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['updates'])) {
    
    if (!$conn || $conn->connect_error) {
        error_log("Batch update failed: Database connection is not available.");
        header("Location: inventory.php?status=error_db");
        exit();
    }

    // Start a transaction to ensure all updates succeed or fail together
    $conn->begin_transaction();
    $success = true;
    $updated_count = 0;

    try {
        // Prepare the base SQL statement once outside the loop
        $sql = "UPDATE ingredients SET quantity = ? WHERE ingredient_name = ?";
        $stmt = $conn->prepare($sql);
        
        // Loop through all updated items submitted from the form
        foreach ($_POST['updates'] as $ingredient_name => $new_quantity) {
            
            // 1. Sanitize and validate
            // FIX: Replaced deprecated FILTER_SANITIZE_STRING with strip_tags and trim
            $safe_name = trim(strip_tags($ingredient_name)); 
            
            // Ensure quantity is a non-negative float
            $safe_quantity = filter_var($new_quantity, FILTER_VALIDATE_FLOAT);

            if ($safe_quantity === false || $safe_quantity < 0 || !$safe_name) {
                // Log and skip invalid entries
                error_log("Skipping invalid update for item: {$ingredient_name}");
                continue; 
            }

            // 2. Bind parameters and execute
            // 'ds' binds a double/float (d) and a string (s)
            $stmt->bind_param("ds", $safe_quantity, $safe_name);
            
            if (!$stmt->execute()) {
                $success = false;
                error_log("Failed to update {$safe_name}. Error: " . $stmt->error);
                // Throw an exception to trigger rollback
                throw new Exception("Database update failed for one or more items.");
            }
            $updated_count++;
        }

        $stmt->close();
        
        // 3. Commit or Rollback
        if ($success) {
            $conn->commit();
            header("Location: inventory.php?status=success_batch&count={$updated_count}");
        } else {
            $conn->rollback();
            header("Location: inventory.php?status=error_general");
        }
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Transaction rolled back. Reason: " . $e->getMessage());
        header("Location: inventory.php?status=error_transaction");
        exit();
    }
} else {
    // If accessed directly or no updates were submitted
    header("Location: inventory.php");
    exit();
}
?>