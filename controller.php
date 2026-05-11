<?php
// Mock Class representing the Digitalized Borrowing List System
class ToolManagementSystem {

    // 1. Search Logic
    public function searchTool($query, $db) {
        $results = $db->query("SELECT * FROM technical_tools WHERE name LIKE '%$query%' OR category LIKE '%$query%'");
        
        if ($results->num_rows > 0) {
            return $this->displayTools($results); // "Match?" Path
        } else {
            return "Error: Tool Not Found"; // "Not Found" Path
        }
    }

    // 2. Request / Log Student Logic
    public function requestTool($data, $db) {
        // Logic for Add, Update, Edit, Remove
        $sql = "INSERT INTO borrowing_logs (student_name, tool_id, reason) VALUES (...)";
        return $db->execute($sql);
    }

    // 3. Condition & Inventory Logic (The Decision Diamonds)
    public function processToolStatus($toolID, $db) {
        $tool = $db->getTool($toolID);

        // Check: Expired Tools or Low Stock or Malfunction?
        if ($tool['condition_status'] != 'Good' || $tool['stock_count'] <= 5) {
            
            // Branch: Is it an Expired Tool?
            if ($tool['condition_status'] == 'Expired') {
                $this->printReport("Dispose or Replace Action Required");
            } 
            // Branch: Is it Low Stock?
            elseif ($tool['stock_count'] <= 5) {
                $this->printReport("Restock Tools: " . $tool['name']);
            }
            // Branch: Malfunction
            else {
                $this->printReport("Maintenance Required");
            }

        } else {
            // "NO" Path -> Return to Dashboard
            header("Location: dashboard.php");
        }
    }

    private function printReport($message) {
        echo "<h1>Generating Report...</h1>";
        echo "<p>Action: $message</p>";
        // Logic for window.print() would go here
    }
}
?>