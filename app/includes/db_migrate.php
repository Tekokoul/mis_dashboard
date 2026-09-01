<?php

// Connect to the databases
$db1 = new mysqli('host1', 'username1', 'password1', 'database1');
$db2 = new mysqli('host2', 'username2', 'password2', 'database2');

// Get a list of all tables in both databases
$tables1 = getTables($db1);
$tables2 = getTables($db2);

// Find tables that are present in one database but not the other
$tablesToCreate = array_diff($tables2, $tables1);
$tablesToDrop = array_diff($tables1, $tables2);

// Generate queries to create any missing tables
foreach ($tablesToCreate as $table) {
    $query = "CREATE TABLE $table (\n";
    $columns = getColumns($db2, $table);
    foreach ($columns as $column) {
        $query .= "  $column,\n";
    }
    $query .= ")";
    echo $query . ";\n";
}

// Generate queries to drop any excess tables
foreach ($tablesToDrop as $table) {
    $query = "DROP TABLE $table";
    echo $query . ";\n";
}

// Find tables that are present in both databases
$tablesToAlter = array_intersect($tables1, $tables2);

// Generate queries to alter tables to match the structure of the second database
foreach ($tablesToAlter as $table) {
    $columns1 = getColumns($db1, $table);
    $columns2 = getColumns($db2, $table);
    $columnsToAdd = array_diff($columns2, $columns1);
    $columnsToDrop = array_diff($columns1, $columns2);
    foreach ($columnsToAdd as $column) {
        $query = "ALTER TABLE $table ADD $column";
        echo $query . ";\n";
    }
    foreach ($columnsToDrop as $column) {
        $query = "ALTER TABLE $table DROP $column";
        echo $query . ";\n";
    }
}

// Close the connections
$db1->close();
$db2->close();

// Function to get a list of all tables in a database
function getTables($db) {
    $tables = array();
    $result = $db->query('SHOW TABLES');
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }
    return $tables;
}

// Function to get a list of all columns in a table
function getColumns($db, $table)
{
    $columns = array();
    $result = $db->query("DESCRIBE $table");
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    return $columns;
}
