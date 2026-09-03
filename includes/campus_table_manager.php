<?php
/**
 * Campus Table Manager
 * Handles creation and management of campus-specific tables
 */

function mapCampusToTable($campid) {
    $map = [
        "UPHB" => "binan",
        "UPHMU" => "medical_university", 
        "UPHG" => "gma",
        "UPHM" => "manila",
        "PHCP" => "pangasinan",
        "UPHI" => "isabela",
        "UPHR" => "roxas"
    ];
    return isset($map[$campid]) ? $map[$campid] : null;
}

function tableExists($con, $tableName) {
    if (trim($tableName) === '') { return false; }
    $t = mysqli_real_escape_string($con, $tableName);
    $sql = "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name='".$t."' LIMIT 1";
    $res = @mysqli_query($con, $sql);
    if ($res && mysqli_fetch_row($res)) { return true; }
    return false;
}

function createCampusTable($con, $tableName) {
    if (trim($tableName) === '') { return false; }
    
    $t = mysqli_real_escape_string($con, $tableName);
    
    // Create the campus table with essential student information structure
    $sql = "CREATE TABLE IF NOT EXISTS `{$t}` (
        `stud_num` varchar(255) NOT NULL,
        `lname` varchar(255) NOT NULL,
        `fname` varchar(255) NOT NULL,
        `course` varchar(255) NULL,
        PRIMARY KEY (`stud_num`),
        KEY `idx_name` (`lname`, `fname`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $result = @mysqli_query($con, $sql);
    return $result !== false;
}

function ensureCampusTablesExist($con) {
    $campuses = ["UPHB", "UPHMU", "UPHG", "UPHM", "PHCP", "UPHI", "UPHR"];
    $created_tables = [];
    $existing_tables = [];
    
    foreach ($campuses as $campus) {
        $tableName = mapCampusToTable($campus);
        if ($tableName) {
            if (!tableExists($con, $tableName)) {
                if (createCampusTable($con, $tableName)) {
                    $created_tables[] = $tableName;
                }
            } else {
                $existing_tables[] = $tableName;
            }
        }
    }
    
    return [
        'created' => $created_tables,
        'existing' => $existing_tables,
        'total_created' => count($created_tables),
        'total_existing' => count($existing_tables)
    ];
}

/**
 * Temporary student tables (locator-based) mapping and creation
 */
function mapCampusToTmpTable($campid) {
    $map = [
        "UPHB" => "binan_tmp_stud",
        "UPHMU" => "mu_tmp_stud",
        "UPHG" => "gma_tmp_stud",
        "UPHM" => "manila_tmp_stud",
        "PHCP" => "pangasinan_tmp_stud",
        "UPHI" => "isabela_tmp_stud",
        "UPHR" => "roxas_tmp_stud"
    ];
    return isset($map[$campid]) ? $map[$campid] : null;
}

function createTmpStudentTable($con, $tableName) {
    if (trim($tableName) === '') { return false; }
    $t = mysqli_real_escape_string($con, $tableName);
    $sql = "CREATE TABLE IF NOT EXISTS `{$t}` (
        `locator_num` varchar(255) NOT NULL,
        `stud_name` varchar(255) NOT NULL,
        PRIMARY KEY (`locator_num`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $result = @mysqli_query($con, $sql);
    return $result !== false;
}

function ensureTmpStudentTablesExist($con) {
    $campuses = ["UPHB", "UPHMU", "UPHG", "UPHM", "PHCP", "UPHI", "UPHR"];
    $created_tables = [];
    $existing_tables = [];
    foreach ($campuses as $campus) {
        $tableName = mapCampusToTmpTable($campus);
        if ($tableName) {
            if (!tableExists($con, $tableName)) {
                if (createTmpStudentTable($con, $tableName)) {
                    $created_tables[] = $tableName;
                }
            } else {
                $existing_tables[] = $tableName;
            }
        }
    }
    return [
        'created' => $created_tables,
        'existing' => $existing_tables,
        'total_created' => count($created_tables),
        'total_existing' => count($existing_tables)
    ];
}

function logTableCreation($con, $result) {
    // Optional: Log table creation for debugging
    if ($result['total_created'] > 0) {
        $log_message = "Campus tables created: " . implode(', ', $result['created']);
        error_log("UPHS Payment System: " . $log_message);
    }
}

