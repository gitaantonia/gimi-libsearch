<?php
$conn = new mysqli("localhost", "root", "", "gimi");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$result = $conn->query("SHOW TABLES");
if ($result) {
    while ($row = $result->fetch_array()) {
        $table = $row[0];
        echo "TABLE: $table\n";
        $desc = $conn->query("DESCRIBE `$table`");
        while ($col = $desc->fetch_assoc()) {
            echo "  {$col['Field']} - {$col['Type']}\n";
        }
        echo "\n";
    }
}
$conn->close();
?>
