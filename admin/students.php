<?php
/**
 * UPHS Student Management
 * 
 * @author Nico Roell D. Garce
 * @title UPHS Web Administrator 2025
 * @description Administrative interface for managing enrolled and temporary student data
 */

require_once __DIR__ . '/../includes/config.php';
paymentsRequireAdmin();

$user = getUserById($_SESSION['user_id']);
$userRole = $_SESSION['user_role'];

$page_title = 'Student Management - Payments Hub';

// Get database connection
$pdo = getDBConnection();

$error = '';
$success = '';

// Ensure campus tables exist on initial load
$table_result = ensureCampusTablesExist($con);
logTableCreation($con, $table_result);
$tmp_table_result = ensureTmpStudentTablesExist($con);
logTableCreation($con, $tmp_table_result);

// Handle Post/Redirect/Get token to avoid double-submit on refresh
if (!isset($_SESSION['import_csrf'])) {
    $_SESSION['import_csrf'] = bin2hex(random_bytes(16));
}

// Helper: clean a CSV/Excel field value
function cleanImportField($value, $fieldType = 'generic') {
    if (!is_string($value)) { $value = strval($value); }
    $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);
    $value = trim(str_replace("\xC2\xA0", ' ', $value));
    if ((strlen($value) >= 2) && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
        $value = substr($value, 1, -1);
    }
    $value = preg_replace('/\s+/', ' ', $value);
    if ($fieldType === 'course' || $fieldType === 'generic') {
        $upper = strtoupper($value);
        if ($upper === 'NULL' || $upper === 'N/A' || $upper === 'NONE') {
            $value = '';
        }
    }
    return $value;
}

// Handle Excel file upload and import for enrolled students
if (isset($_POST["btnsubmit"]) && isset($_FILES["excel_file"])) {
    $campid = $_POST["campid"];
    $table = mapCampusToTable($campid);
    
    if ($table === null) {
        $error = "Invalid campus selected.";
    } else if (!tableExists($con, $table)) {
        $error = "Campus database table not available.";
    } else {
        $file = $_FILES["excel_file"];
        
        if ($file["error"] == UPLOAD_ERR_OK) {
            $fileType = pathinfo($file["name"], PATHINFO_EXTENSION);
            
            if (strtolower($fileType) == 'csv') {
                $uploadDir = "../uploads/";
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $fileName = $uploadDir . uniqid() . "_" . $file["name"];
                if (move_uploaded_file($file["tmp_name"], $fileName)) {
                    $result = processExcelFile($con, $fileName, $table);
                    
                    if ($result['success']) {
                        $success = "Successfully imported " . $result['imported'] . " student records to " . strtoupper($table) . " table.";
                        
                        $duplicateCount = !empty($result['duplicateRecords']) ? count($result['duplicateRecords']) : 0;
                        $errorCount = !empty($result['skippedRecords']) ? count($result['skippedRecords']) : 0;
                        $totalSkipped = $duplicateCount + $errorCount;
                        
                        if ($totalSkipped > 0) {
                            $skipReasons = [];
                            if ($duplicateCount > 0) {
                                $skipReasons[] = $duplicateCount . " duplicate" . ($duplicateCount > 1 ? "s" : "");
                            }
                            if ($errorCount > 0) {
                                $skipReasons[] = $errorCount . " error" . ($errorCount > 1 ? "s" : "");
                            }
                            
                            if (!empty($skipReasons)) {
                                $success .= " " . $totalSkipped . " record" . ($totalSkipped > 1 ? "s were" : " was") . " skipped (" . implode(", ", $skipReasons) . ").";
                            }
                        }
                    } else {
                        $error = "Error processing CSV file: " . $result['message'];
                    }
                    
                    unlink($fileName);
                } else {
                    $error = "Failed to upload file.";
                }
            } else {
                $error = "Please upload a CSV file (.csv).";
            }
        } else {
            $error = "Error uploading file: " . $file["error"];
        }
    }
}

// Function to process Excel file with specific format
function processExcelFile($con, $filePath, $table) {
    ini_set('memory_limit', '512M');
    ini_set('max_execution_time', 300);
    
    $handle = fopen($filePath, "r");
    if (!$handle) {
        return ['success' => false, 'message' => 'Could not open file'];
    }
    
    $totalLines = 0;
    while (($data = fgetcsv($handle, 2000, ",")) !== FALSE) {
        $totalLines++;
    }
    rewind($handle);
    
    $imported = 0;
    $skipped = 0;
    $lineNumber = 0;
    $batchSize = 200;
    $batchData = [];
    $batchLineNumbers = [];
    $batchStudentNumbers = [];
    $skippedRecords = [];
    $duplicateRecords = [];
    
    $checkTableSQL = "SHOW TABLES LIKE '{$table}'";
    $result = mysqli_query($con, $checkTableSQL);
    
    if (!$result || mysqli_num_rows($result) == 0) {
        fclose($handle);
        return ['success' => false, 'message' => 'Table does not exist'];
    }
    
    mysqli_autocommit($con, FALSE);
    
    while (($data = fgetcsv($handle, 2000, ",")) !== FALSE) {
        $lineNumber++;
        
        $colCount = count($data);
        if ($colCount < 3) {
            $skippedRecords[] = "Row {$lineNumber}: Insufficient columns (found {$colCount}, expected 3-4)";
            $skipped++;
            continue;
        }
        
        $studNum = cleanImportField($data[0], 'generic');
        $lname = cleanImportField($data[1], 'name');
        if ($colCount == 3) {
            $fname = cleanImportField($data[2], 'name');
            $course = '';
        } else {
            $course = cleanImportField($data[$colCount - 1], 'course');
            $fnameParts = [];
            for ($i = 2; $i < $colCount - 1; $i++) {
                $p = cleanImportField($data[$i], 'name');
                if ($p !== '') { $fnameParts[] = $p; }
            }
            $fname = trim(implode(' ', $fnameParts));
        }
        
        if (empty($studNum) || empty($lname) || empty($fname)) {
            $emptyFields = [];
            if (empty($studNum)) $emptyFields[] = "Student Number";
            if (empty($lname)) $emptyFields[] = "Last Name";
            if (empty($fname)) $emptyFields[] = "First Name";
            $skippedRecords[] = "Row {$lineNumber}: Empty required fields - " . implode(", ", $emptyFields);
            $skipped++;
            continue;
        }
        
        if (mb_strlen($lname, 'UTF-8') > 2) {
            $lname = mb_convert_case($lname, MB_CASE_TITLE, 'UTF-8');
        }
        if (mb_strlen($fname, 'UTF-8') > 2) {
            $fname = mb_convert_case($fname, MB_CASE_TITLE, 'UTF-8');
        }
        if (!empty($course)) {
            $course = preg_replace('/\s+/', ' ', $course);
        } else {
            $course = null;
        }
        
        $studNum = mysqli_real_escape_string($con, $studNum);
        $lname = mysqli_real_escape_string($con, $lname);
        $fname = mysqli_real_escape_string($con, $fname);
        $course = ($course !== null) ? mysqli_real_escape_string($con, $course) : 'NULL';
        
        $courseValue = ($course === 'NULL') ? 'NULL' : "'{$course}'";
        $batchRecord = "('{$studNum}', '{$lname}', '{$fname}', {$courseValue})";
        $batchData[] = $batchRecord;
        $batchLineNumbers[] = $lineNumber;
        $batchStudentNumbers[] = $studNum;
        
        if (count($batchData) >= $batchSize) {
            $result = processBatch($con, $table, $batchData, $batchLineNumbers, $batchStudentNumbers);
            $imported += $result['imported'];
            $skipped += $result['skipped'];
            if (!empty($result['duplicateRecords'])) {
                $duplicateRecords = array_merge($duplicateRecords, $result['duplicateRecords']);
            }
            if (!empty($result['skippedRecords'])) {
                $skippedRecords = array_merge($skippedRecords, $result['skippedRecords']);
            }
            $batchData = [];
            $batchLineNumbers = [];
            $batchStudentNumbers = [];
        }
    }
    
    if (!empty($batchData)) {
        $result = processBatch($con, $table, $batchData, $batchLineNumbers, $batchStudentNumbers);
        $imported += $result['imported'];
        $skipped += $result['skipped'];
        if (!empty($result['duplicateRecords'])) {
            $duplicateRecords = array_merge($duplicateRecords, $result['duplicateRecords']);
        }
        if (!empty($result['skippedRecords'])) {
            $skippedRecords = array_merge($skippedRecords, $result['skippedRecords']);
        }
    }
    
    if (mysqli_commit($con)) {
        $success = true;
    } else {
        mysqli_rollback($con);
        $success = false;
    }
    
    mysqli_autocommit($con, TRUE);
    fclose($handle);
    
    return [
        'success' => $success,
        'imported' => $imported,
        'skipped' => $skipped,
        'skippedRecords' => $skippedRecords,
        'duplicateRecords' => $duplicateRecords
    ];
}

// Function to process batch of records
function processBatch($con, $table, $batchData, $lineNumbers = [], $studentNumbers = []) {
    $imported = 0;
    $skipped = 0;
    $duplicateRecords = [];
    $skippedRecords = [];
    
    if (empty($batchData)) {
        return ['imported' => 0, 'skipped' => 0, 'duplicateRecords' => [], 'skippedRecords' => []];
    }
    
    $studentNumsToCheck = array_filter($studentNumbers);
    if (!empty($studentNumsToCheck)) {
        $escapedNumbers = array_map(function($num) use ($con) {
            return "'" . mysqli_real_escape_string($con, $num) . "'";
        }, $studentNumsToCheck);
        
        $checkSQL = "SELECT `stud_num` FROM `{$table}` WHERE `stud_num` IN (" . implode(',', $escapedNumbers) . ")";
        $checkResult = mysqli_query($con, $checkSQL);
        
        $existingNumbers = [];
        if ($checkResult) {
            while ($row = mysqli_fetch_assoc($checkResult)) {
                $existingNumbers[$row['stud_num']] = true;
            }
        }
        
        $newBatchData = [];
        $newLineNumbers = [];
        $newStudentNumbers = [];
        
        foreach ($batchData as $index => $record) {
            $studentNumber = isset($studentNumbers[$index]) ? $studentNumbers[$index] : '';
            $lineNumber = isset($lineNumbers[$index]) ? $lineNumbers[$index] : 'Unknown';
            
            if (!empty($studentNumber) && isset($existingNumbers[$studentNumber])) {
                $duplicateRecords[] = "Row {$lineNumber}: Student Number '{$studentNumber}' (duplicate)";
                $skipped++;
            } else {
                $newBatchData[] = $record;
                $newLineNumbers[] = $lineNumber;
                $newStudentNumbers[] = $studentNumber;
            }
        }
        
        if (!empty($newBatchData)) {
            $values = implode(',', $newBatchData);
            $sql = "INSERT IGNORE INTO `{$table}` (`stud_num`, `lname`, `fname`, `course`) 
                    VALUES {$values}";
            
            if (mysqli_query($con, $sql)) {
                $imported = mysqli_affected_rows($con);
                $skipped = count($newBatchData) - $imported;
                
                // Track which student numbers were duplicates
                $existingStudentNumbers = array_diff($newStudentNumbers, array_slice($newStudentNumbers, 0, $imported));
                foreach ($existingStudentNumbers as $index => $studentNumber) {
                    $lineNumber = isset($newLineNumbers[$index]) ? $newLineNumbers[$index] : 'Unknown';
                    $duplicateRecords[] = "Row {$lineNumber}: Student Number '{$studentNumber}' (duplicate - skipped)";
                }
            } else {
                $dbError = mysqli_error($con);
                error_log("Batch insert failed for enrolled students: " . $dbError);
                foreach ($newBatchData as $index => $record) {
                    $sql = "INSERT IGNORE INTO `{$table}` (`stud_num`, `lname`, `fname`, `course`) 
                            VALUES {$record}";
                    
                    $lineNumber = isset($newLineNumbers[$index]) ? $newLineNumbers[$index] : 'Unknown';
                    $studentNumber = isset($newStudentNumbers[$index]) ? $newStudentNumbers[$index] : 'Unknown';
                    
                    if (mysqli_query($con, $sql)) {
                        if (mysqli_affected_rows($con) > 0) {
                            $imported++;
                        } else {
                            $skipped++;
                            $duplicateRecords[] = "Row {$lineNumber}: Student Number '{$studentNumber}' (duplicate - skipped)";
                        }
                    } else {
                        $skipped++;
                        $error = mysqli_error($con);
                        error_log("Individual insert failed for row {$lineNumber}: " . $error);
                        $skippedRecords[] = "Row {$lineNumber}: Student Number '{$studentNumber}' - Database error: " . htmlspecialchars($error);
                    }
                }
            }
        }
    } else {
        $values = implode(',', $batchData);
        $sql = "INSERT IGNORE INTO `{$table}` (`stud_num`, `lname`, `fname`, `course`) 
                VALUES {$values}";
        
        if (mysqli_query($con, $sql)) {
            $imported = mysqli_affected_rows($con);
            $skipped = count($batchData) - $imported;
        } else {
            $dbError = mysqli_error($con);
            error_log("Batch insert with IGNORE failed: " . $dbError);
            $skipped = count($batchData);
            $skippedRecords[] = "Batch insert failed: " . htmlspecialchars($dbError);
        }
    }
    
    return ['imported' => $imported, 'skipped' => $skipped, 'duplicateRecords' => $duplicateRecords, 'skippedRecords' => $skippedRecords];
}

// Temporary-students importer
$err_tmp = '';
$success_tmp = '';

function cleanField($value) {
    if (!is_string($value)) { $value = strval($value); }
    $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);
    $value = trim(str_replace("\xC2\xA0", ' ', $value));
    if ((strlen($value) >= 2) && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
        $value = substr($value, 1, -1);
    }
    $value = preg_replace('/\s+/', ' ', $value);
    return $value;
}

function processBatchTmp($con, $table, $batchValues, $batchLocators) {
    $inserted = 0;
    $skipped = 0;
    $duplicates = [];
    if (empty($batchValues)) return ['inserted'=>0,'skipped'=>0,'duplicates'=>[]];

    $escapedLocs = array_map(function($n) use ($con) { return "'" . mysqli_real_escape_string($con, $n) . "'"; }, $batchLocators);
    $exist = [];
    if (!empty($escapedLocs)) {
        $checkSQL = "SELECT `locator_num` FROM `{$table}` WHERE `locator_num` IN (" . implode(',', $escapedLocs) . ")";
        $res = @mysqli_query($con, $checkSQL);
        if ($res) {
            while ($r = mysqli_fetch_assoc($res)) { $exist[$r['locator_num']] = true; }
        }
    }

    $newValues = [];
    foreach ($batchValues as $idx => $val) {
        $locator = $batchLocators[$idx] ?? '';
        if ($locator !== '' && isset($exist[$locator])) {
            $duplicates[] = $locator;
            $skipped++;
        } else {
            $newValues[] = $val;
        }
    }

    if (!empty($newValues)) {
        $sql = "INSERT IGNORE INTO `{$table}` (`locator_num`,`stud_name`) VALUES " . implode(',', $newValues);
        if (@mysqli_query($con, $sql)) {
            $affected = mysqli_affected_rows($con);
            $inserted += $affected;
            $skipped += count($newValues) - $affected;
        } else {
            $dbError = mysqli_error($con);
            error_log("Batch insert failed for temporary students: " . $dbError);
            foreach ($newValues as $v) {
                $ins = "INSERT IGNORE INTO `{$table}` (`locator_num`,`stud_name`) VALUES {$v}";
                if (@mysqli_query($con, $ins)) { 
                    $inserted += mysqli_affected_rows($con); 
                } else { 
                    $error = mysqli_error($con);
                    error_log("Individual insert failed for temporary student: " . $error);
                    $skipped++; 
                }
            }
        }
    }

    return ['inserted'=>$inserted, 'skipped'=>$skipped, 'duplicates'=>$duplicates];
}

// Handle copy-paste import for temporary students
if (isset($_POST['btnsubmit_paste_tmp']) && isset($_POST['paste_data_tmp'])) {
    $campid_tmp = $_POST['campid_tmp'] ?? '';
    $table_tmp = mapCampusToTmpTable($campid_tmp);

    if ($table_tmp === null) {
        $err_tmp = 'Invalid campus selected.';
    } else if (!tableExists($con, $table_tmp)) {
        $err_tmp = 'Campus temporary table not available.';
    } else {
        $pasteData = trim($_POST['paste_data_tmp']);
        if (empty($pasteData)) {
            $err_tmp = "No data provided.";
        } else {
            $lines = explode("\n", $pasteData);
            $result = processPasteDataTemporary($con, $lines, $table_tmp);
            
            if ($result['success']) {
                $success_tmp = "Import completed. Approximately {$result['inserted']} rows inserted.";
                if (!empty($result['skippedRecords'])) {
                    $success_tmp .= ' ' . count($result['skippedRecords']) . ' lines skipped.';
                }
            } else {
                $err_tmp = 'Error processing pasted data: ' . $result['message'];
            }
        }
    }
}

// Function to process pasted data for temporary students
function processPasteDataTemporary($con, $lines, $table) {
    ini_set('memory_limit', '512M');
    ini_set('max_execution_time', 300);
    
    mysqli_autocommit($con, FALSE);
    $batchSize = 200;
    $batchValues = [];
    $batchLocators = [];
    $inserted = 0;
    $skipped = 0;
    $lineNum = 0;
    $duplicateRecords = [];
    $skippedRecords = [];

    foreach ($lines as $line) {
        $lineNum++;
        $line = trim($line);
        if (empty($line)) continue;
        
        // Split by tab (Excel copy-paste)
        $data = explode("\t", $line);
        
        if (count($data) < 3) {
            $skipped++;
            $skippedRecords[] = "Row {$lineNum}: Insufficient columns (need at least 3 columns)";
            continue;
        }
        
        // Format: Locator Number (col 0), Date (col 1), Student Name (col 2)
        $locator = cleanField($data[0]);
        $name = cleanField($data[2]);
        
        if ($locator === '' || $name === '') {
            $skipped++;
            $skippedRecords[] = "Row {$lineNum}: Empty fields";
            continue;
        }
        $escLocator = mysqli_real_escape_string($con, $locator);
        $escName = mysqli_real_escape_string($con, $name);
        $batchValues[] = "('{$escLocator}','{$escName}')";
        $batchLocators[] = $locator;

        if (count($batchValues) >= $batchSize) {
            $res = processBatchTmp($con, $table, $batchValues, $batchLocators);
            $inserted += $res['inserted'];
            $skipped += $res['skipped'];
            if (!empty($res['duplicates'])) $duplicateRecords = array_merge($duplicateRecords, $res['duplicates']);
            $batchValues = [];
            $batchLocators = [];
        }
    }

    if (!empty($batchValues)) {
        $res = processBatchTmp($con, $table, $batchValues, $batchLocators);
        $inserted += $res['inserted'];
        $skipped += $res['skipped'];
        if (!empty($res['duplicates'])) $duplicateRecords = array_merge($duplicateRecords, $res['duplicates']);
    }

    if (mysqli_commit($con)) {
        $success = true;
    } else {
        mysqli_rollback($con);
        $success = false;
    }

    mysqli_autocommit($con, TRUE);
    
    return [
        'success' => $success,
        'inserted' => $inserted,
        'skipped' => $skipped,
        'skippedRecords' => $skippedRecords,
        'duplicateRecords' => $duplicateRecords
    ];
}

// Handle temporary CSV upload and import
if (isset($_POST['btnsubmit_tmp']) && isset($_FILES['csv_file_tmp'])) {
    $campid_tmp = $_POST['campid_tmp'] ?? '';
    $table_tmp = mapCampusToTmpTable($campid_tmp);

    if ($table_tmp === null) {
        $err_tmp = 'Invalid campus selected.';
    } else if (!tableExists($con, $table_tmp)) {
        $err_tmp = 'Campus temporary table not available.';
    } else {
        $file = $_FILES['csv_file_tmp'];
        if ($file['error'] == UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($ext !== 'csv') {
                $err_tmp = 'Please upload a CSV file.';
            } else {
                $uploadDir = '../uploads/';
                if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
                $fileName = $uploadDir . uniqid('csvtmp_') . '_' . basename($file['name']);
                if (move_uploaded_file($file['tmp_name'], $fileName)) {
                    $handle = @fopen($fileName, 'r');
                    if (!$handle) {
                        $err_tmp = 'Failed to open uploaded CSV.';
                        @unlink($fileName);
                    } else {
                        mysqli_autocommit($con, FALSE);
                        $batchSize = 200;
                        $batchValues = [];
                        $batchLocators = [];
                        $inserted = 0;
                        $skipped = 0;
                        $lineNum = 0;
                        $duplicateRecords = [];
                        $skippedRecords = [];

                        while (($data = fgetcsv($handle, 2000, ',')) !== FALSE) {
                            $lineNum++;
                            if (count($data) < 3) {
                                $skipped++;
                                $skippedRecords[] = "Row {$lineNum}: Insufficient columns (need at least 3 columns)";
                                continue;
                            }
                            $locator = cleanField($data[0]);
                            $name = cleanField($data[2]);
                            if ($locator === '' || $name === '') {
                                $skipped++;
                                $skippedRecords[] = "Row {$lineNum}: Empty fields";
                                continue;
                            }
                            $escLocator = mysqli_real_escape_string($con, $locator);
                            $escName = mysqli_real_escape_string($con, $name);
                            $batchValues[] = "('{$escLocator}','{$escName}')";
                            $batchLocators[] = $locator;

                            if (count($batchValues) >= $batchSize) {
                                $res = processBatchTmp($con, $table_tmp, $batchValues, $batchLocators);
                                $inserted += $res['inserted'];
                                $skipped += $res['skipped'];
                                if (!empty($res['duplicates'])) $duplicateRecords = array_merge($duplicateRecords, $res['duplicates']);
                                $batchValues = [];
                                $batchLocators = [];
                            }
                        }

                        if (!empty($batchValues)) {
                            $res = processBatchTmp($con, $table_tmp, $batchValues, $batchLocators);
                            $inserted += $res['inserted'];
                            $skipped += $res['skipped'];
                            if (!empty($res['duplicates'])) $duplicateRecords = array_merge($duplicateRecords, $res['duplicates']);
                        }

                        if (mysqli_commit($con)) {
                            $success_tmp = "Import completed. Approximately {$inserted} rows inserted.";
                            if (!empty($skippedRecords)) {
                                $success_tmp .= ' ' . count($skippedRecords) . ' lines skipped.';
                            }
                        } else {
                            mysqli_rollback($con);
                            $err_tmp = 'Database commit failed.';
                        }

                        mysqli_autocommit($con, TRUE);
                        fclose($handle);
                        @unlink($fileName);
                    }
                } else {
                    $err_tmp = 'Failed to move uploaded file.';
                }
            }
        } else {
            $err_tmp = 'Error uploading file: ' . $file['error'];
        }
    }
}

// Handle single add, edit, delete operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_enrolled') {
        $campid = $_POST['campid'];
        $table = mapCampusToTable($campid);
        $studNum = mysqli_real_escape_string($con, $_POST['stud_num']);
        $lname = mysqli_real_escape_string($con, $_POST['lname']);
        $fname = mysqli_real_escape_string($con, $_POST['fname']);
        $course = !empty($_POST['course']) ? "'" . mysqli_real_escape_string($con, $_POST['course']) . "'" : 'NULL';
        
        if ($table && !empty($studNum) && !empty($lname) && !empty($fname)) {
            // Check if student number already exists
            $checkSql = "SELECT `stud_num` FROM `{$table}` WHERE `stud_num` = '{$studNum}' LIMIT 1";
            $checkResult = mysqli_query($con, $checkSql);
            
            if ($checkResult && mysqli_num_rows($checkResult) > 0) {
                $error = "Student number '{$studNum}' already exists in the database. Duplicate records are not allowed.";
            } else {
                $sql = "INSERT INTO `{$table}` (`stud_num`, `lname`, `fname`, `course`) VALUES ('{$studNum}', '{$lname}', '{$fname}', {$course})";
                if (mysqli_query($con, $sql)) {
                    $success = "Student added successfully!";
                } else {
                    $dbError = mysqli_error($con);
                    error_log("Failed to add enrolled student: " . $dbError);
                    $error = "Failed to add student: " . htmlspecialchars($dbError);
                }
            }
        } else {
            $error = "Please fill in all required fields.";
        }
    } elseif ($action === 'edit_enrolled') {
        $campid = $_POST['campid'];
        $table = mapCampusToTable($campid);
        $oldStudNum = mysqli_real_escape_string($con, $_POST['old_stud_num']);
        $studNum = mysqli_real_escape_string($con, $_POST['stud_num']);
        $lname = mysqli_real_escape_string($con, $_POST['lname']);
        $fname = mysqli_real_escape_string($con, $_POST['fname']);
        $course = !empty($_POST['course']) ? "'" . mysqli_real_escape_string($con, $_POST['course']) . "'" : 'NULL';
        
        if ($table && !empty($studNum) && !empty($lname) && !empty($fname)) {
            $sql = "UPDATE `{$table}` SET `stud_num` = '{$studNum}', `lname` = '{$lname}', `fname` = '{$fname}', `course` = {$course} WHERE `stud_num` = '{$oldStudNum}'";
            if (mysqli_query($con, $sql)) {
                $success = "Student updated successfully!";
            } else {
                $error = "Failed to update student: " . mysqli_error($con);
            }
        } else {
            $error = "Please fill in all required fields.";
        }
    } elseif ($action === 'delete_enrolled') {
        $campid = $_POST['campid'];
        $table = mapCampusToTable($campid);
        $studNum = mysqli_real_escape_string($con, $_POST['stud_num']);
        
        if ($table && !empty($studNum)) {
            $sql = "DELETE FROM `{$table}` WHERE `stud_num` = '{$studNum}'";
            if (mysqli_query($con, $sql)) {
                $success = "Student deleted successfully!";
            } else {
                $error = "Failed to delete student: " . mysqli_error($con);
            }
        } else {
            $error = "Invalid request.";
        }
    } elseif ($action === 'add_temporary') {
        $campid = $_POST['campid'];
        $table = mapCampusToTmpTable($campid);
        $locatorNum = mysqli_real_escape_string($con, $_POST['locator_num']);
        $studName = mysqli_real_escape_string($con, $_POST['stud_name']);
        
        if ($table && !empty($locatorNum) && !empty($studName)) {
            // Check if locator number already exists
            $checkSql = "SELECT `locator_num` FROM `{$table}` WHERE `locator_num` = '{$locatorNum}' LIMIT 1";
            $checkResult = mysqli_query($con, $checkSql);
            
            if ($checkResult && mysqli_num_rows($checkResult) > 0) {
                $error = "Locator number '{$locatorNum}' already exists in the database. Duplicate records are not allowed.";
            } else {
                $sql = "INSERT INTO `{$table}` (`locator_num`, `stud_name`) VALUES ('{$locatorNum}', '{$studName}')";
                if (mysqli_query($con, $sql)) {
                    $success = "Temporary student added successfully!";
                } else {
                    $dbError = mysqli_error($con);
                    error_log("Failed to add temporary student: " . $dbError);
                    $error = "Failed to add temporary student: " . htmlspecialchars($dbError);
                }
            }
        } else {
            $error = "Please fill in all required fields.";
        }
    } elseif ($action === 'edit_temporary') {
        $campid = $_POST['campid'];
        $table = mapCampusToTmpTable($campid);
        $oldLocatorNum = mysqli_real_escape_string($con, $_POST['old_locator_num']);
        $locatorNum = mysqli_real_escape_string($con, $_POST['locator_num']);
        $studName = mysqli_real_escape_string($con, $_POST['stud_name']);
        
        if ($table && !empty($locatorNum) && !empty($studName)) {
            $sql = "UPDATE `{$table}` SET `locator_num` = '{$locatorNum}', `stud_name` = '{$studName}' WHERE `locator_num` = '{$oldLocatorNum}'";
            if (mysqli_query($con, $sql)) {
                $success = "Temporary student updated successfully!";
            } else {
                $error = "Failed to update temporary student: " . mysqli_error($con);
            }
        } else {
            $error = "Please fill in all required fields.";
        }
    } elseif ($action === 'delete_temporary') {
        $campid = $_POST['campid'];
        $table = mapCampusToTmpTable($campid);
        $locatorNum = mysqli_real_escape_string($con, $_POST['locator_num']);
        
        if ($table && !empty($locatorNum)) {
            $sql = "DELETE FROM `{$table}` WHERE `locator_num` = '{$locatorNum}'";
            if (mysqli_query($con, $sql)) {
                $success = "Temporary student deleted successfully!";
            } else {
                $error = "Failed to delete temporary student: " . mysqli_error($con);
            }
        } else {
            $error = "Invalid request.";
        }
    }
}

// CSV Export handler — same filters as table, disabled when no campus
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $tab = $_GET['tab'] ?? 'enrolled';
    $campid = $_GET['campid'] ?? '';
    $search = $_GET['search'] ?? '';
    if (empty($campid)) { http_response_code(400); die("Select a campus first"); }
    if ($tab === 'enrolled') {
        $table = mapCampusToTable($campid);
        if (!$table || !tableExists($con, $table)) { http_response_code(400); die("Invalid campus"); }
        $where = '';
        if (!empty($search)) {
            $s = mysqli_real_escape_string($con, $search);
            $where = "WHERE `stud_num` LIKE '%{$s}%' OR `lname` LIKE '%{$s}%' OR `fname` LIKE '%{$s}%' OR `course` LIKE '%{$s}%'";
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="students_'.$table.'_'.date('Y-m-d_His').'.csv"');
        $out = fopen('php://output','w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out, ['Student Number','Last Name','First Name','Course']);
        $sql = "SELECT `stud_num`,`lname`,`fname`,`course` FROM `{$table}` {$where} ORDER BY `lname`,`fname`";
        $res = mysqli_query($con, $sql);
        while ($row = mysqli_fetch_assoc($res)) { fputcsv($out, [$row['stud_num'],$row['lname'],$row['fname'],$row['course']]); }
        fclose($out); exit;
    } elseif ($tab === 'temporary') {
        $table = mapCampusToTmpTable($campid);
        if (!$table || !tableExists($con, $table)) { http_response_code(400); die("Invalid campus"); }
        $where = '';
        if (!empty($search)) {
            $s = mysqli_real_escape_string($con, $search);
            $where = "WHERE `locator_num` LIKE '%{$s}%' OR `stud_name` LIKE '%{$s}%'";
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="students_'.$table.'_'.date('Y-m-d_His').'.csv"');
        $out = fopen('php://output','w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out, ['Locator Number','Student Name']);
        $sql = "SELECT `locator_num`,`stud_name` FROM `{$table}` {$where} ORDER BY `locator_num`";
        $res = mysqli_query($con, $sql);
        while ($row = mysqli_fetch_assoc($res)) { fputcsv($out, [$row['locator_num'],$row['stud_name']]); }
        fclose($out); exit;
    }
}

// AJAX handler for fetching table data
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    $tab = $_GET['tab'] ?? 'enrolled';
    $campid = $_GET['campid'] ?? '';
    $search = $_GET['search'] ?? '';
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $perPage = 50;
    $offset = ($page - 1) * $perPage;
    
    header('Content-Type: application/json; charset=utf-8');
    
    if ($tab === 'enrolled') {
        $table = mapCampusToTable($campid);
        if (!$table || !tableExists($con, $table)) {
            echo json_encode(['success' => false, 'error' => 'Invalid campus or table not found']);
            exit;
        }
        
        $where = '';
        $params = [];
        if (!empty($search)) {
            $search = mysqli_real_escape_string($con, $search);
            $where = "WHERE `stud_num` LIKE '%{$search}%' OR `lname` LIKE '%{$search}%' OR `fname` LIKE '%{$search}%' OR `course` LIKE '%{$search}%'";
        }
        
        $countSql = "SELECT COUNT(*) as total FROM `{$table}` {$where}";
        $countResult = mysqli_query($con, $countSql);
        $total = mysqli_fetch_assoc($countResult)['total'];
        
        $sql = "SELECT `stud_num`, `lname`, `fname`, `course` FROM `{$table}` {$where} ORDER BY `lname`, `fname` LIMIT {$perPage} OFFSET {$offset}";
        $result = mysqli_query($con, $sql);
        
        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => ceil($total / $perPage)
        ]);
    } elseif ($tab === 'temporary') {
        $table = mapCampusToTmpTable($campid);
        if (!$table || !tableExists($con, $table)) {
            echo json_encode(['success' => false, 'error' => 'Invalid campus or table not found']);
            exit;
        }
        
        $where = '';
        if (!empty($search)) {
            $search = mysqli_real_escape_string($con, $search);
            $where = "WHERE `locator_num` LIKE '%{$search}%' OR `stud_name` LIKE '%{$search}%'";
        }
        
        $countSql = "SELECT COUNT(*) as total FROM `{$table}` {$where}";
        $countResult = mysqli_query($con, $countSql);
        $total = mysqli_fetch_assoc($countResult)['total'];
        
        $sql = "SELECT `locator_num`, `stud_name` FROM `{$table}` {$where} ORDER BY `locator_num` LIMIT {$perPage} OFFSET {$offset}";
        $result = mysqli_query($con, $sql);
        
        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => ceil($total / $perPage)
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid tab']);
    }
    exit;
}
?>

<?php include __DIR__ . '/../includes/header.php'; ?>
<nav class="admin-tabs" aria-label="Admin sections">
  <a href="<?= $payments_base ?>admin/">Dashboard</a>
  <a href="<?= $payments_base ?>admin/users">Users</a>
  <a href="<?= $payments_base ?>admin/students" class="active">Student Management</a>
  <a href="<?= $payments_base ?>admin/monitoring">Payment Monitoring</a>
  <a href="<?= $payments_base ?>admin/qr">QR Codes</a>
  <a href="<?= $payments_base ?>admin/backup">Backup Portal</a>
  <a href="<?= $payments_base ?>admin/logout" class="admin-tab-logout">Logout</a>
</nav>
<div class="admin-content">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
      <h1 style="margin:0;font-size:22px">Student Management</h1>
    </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <!-- Tabs Navigation -->
        <div class="dashboard-section">
            <div class="tabs-navigation">
                <button class="tab-btn active" data-tab="enrolled">
                    <i class="fas fa-user-graduate"></i>
                    Enrolled Students
                </button>
                <button class="tab-btn" data-tab="temporary">
                    <i class="fas fa-user-clock"></i>
                    Temporary Students
                </button>
            </div>

            <!-- Enrolled Students Tab -->
            <div id="tab-enrolled" class="tab-content active">
                <?php if (!empty($err_tmp) && isset($_POST['btnsubmit'])): ?>
                    <div class="alert alert-error"><?php echo $err_tmp; ?></div>
                <?php endif; ?>

                <div class="table-controls">
                    <div class="control-group">
                        <label for="enrolled-campus">Campus:</label>
                        <select id="enrolled-campus" class="form-input">
                            <option value="">Select Campus</option>
                            <option value="UPHB">Binan Campus</option>
                            <option value="UPHMU">Medical University</option>
                            <option value="UPHG">GMA Campus</option>
                            <option value="UPHM">Manila Campus</option>
                            <option value="PHCP">Pangasinan Campus</option>
                            <option value="UPHI">Isabela Campus</option>
                            <option value="UPHR">Roxas Campus</option>
                        </select>
                    </div>
                    <div class="control-group">
                        <label for="enrolled-search">Search:</label>
                        <input type="text" id="enrolled-search" class="form-input" placeholder="Search">
                        <!--<button type="button" class="btn btn-sm btn-primary" onclick="performEnrolledSearch()" style="margin-top: 5px;">Search</button>-->
                    </div>
                    <button class="btn btn-primary" onclick="openAddEnrolledModal()">
                        Add Student
                    </button>
                    <button class="btn btn-secondary" onclick="openCSVImportModal()">
                        CSV Import
                    </button>
                    <a id="enrolled-export-btn" class="btn btn-secondary" href="#" style="opacity:.5; pointer-events:none" title="Select a campus first">
                        Export CSV
                    </a>
                </div>

                <div class="table-container">
                    <table class="data-table" id="enrolled-table">
                        <thead>
                            <tr>
                                <th>Student Number</th>
                                <th>Last Name</th>
                                <th>First Name</th>
                                <th>Course</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="enrolled-table-body">
                            <tr>
                                <td colspan="5" class="text-center">Please select a campus to view students</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="pagination" id="enrolled-pagination"></div>
            </div>

            <!-- Temporary Students Tab -->
            <div id="tab-temporary" class="tab-content">
                <?php if (!empty($err_tmp) && isset($_POST['btnsubmit_tmp'])): ?>
                    <div class="alert alert-error"><?php echo $err_tmp; ?></div>
                <?php endif; ?>

                <?php if (!empty($success_tmp)): ?>
                    <div class="alert alert-success"><?php echo $success_tmp; ?></div>
                <?php endif; ?>

                <div class="table-controls">
                    <div class="control-group">
                        <label for="temporary-campus">Campus:</label>
                        <select id="temporary-campus" class="form-input">
                            <option value="">Select Campus</option>
                            <option value="UPHB">Binan Campus</option>
                            <option value="UPHMU">Medical University</option>
                            <option value="UPHG">GMA Campus</option>
                            <option value="UPHM">Manila Campus</option>
                            <option value="PHCP">Pangasinan Campus</option>
                            <option value="UPHI">Isabela Campus</option>
                            <option value="UPHR">Roxas Campus</option>
                        </select>
                    </div>
                    <div class="control-group">
                        <label for="temporary-search">Search:</label>
                        <input type="text" id="temporary-search" class="form-input" placeholder="Search">
                        <!--<button type="button" class="btn btn-sm btn-primary" onclick="performTemporarySearch()" style="margin-top: 5px;">Search</button>-->
                    </div>
                    <button class="btn btn-primary" onclick="openAddTemporaryModal()">
                        <i class="fas fa-plus"></i>
                        Add Student
                    </button>
                    <button class="btn btn-secondary" onclick="openCSVNewEnrollModal()">
                        <i class="fas fa-file-csv"></i>
                        CSV New Enroll
                    </button>
                    <button class="btn btn-secondary" onclick="openPasteImportModalTmp()">
                        Copy-Paste Import
                    </button>
                    <a id="temporary-export-btn" class="btn btn-secondary" href="#" style="opacity:.5; pointer-events:none" title="Select a campus first">
                        Export CSV
                    </a>
                </div>

                <div class="table-container">
                    <table class="data-table" id="temporary-table">
                        <thead>
                            <tr>
                                <th>Locator Number</th>
                                <th>Student Name</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="temporary-table-body">
                            <tr>
                                <td colspan="3" class="text-center">Please select a campus to view students</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="pagination" id="temporary-pagination"></div>
            </div>
        </div>
    </div>

    <!-- Add Enrolled Student Modal -->
    <div id="addEnrolledModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add Enrolled Student</h3>
                <span class="close" onclick="closeAddEnrolledModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_enrolled">
                    <div class="form-group">
                        <label for="add_enrolled_campus">Campus</label>
                        <select name="campid" id="add_enrolled_campus" class="form-input" required>
                            <option value="">Select Campus</option>
                            <option value="UPHB">Binan Campus</option>
                            <option value="UPHMU">Medical University</option>
                            <option value="UPHG">GMA Campus</option>
                            <option value="UPHM">Manila Campus</option>
                            <option value="PHCP">Pangasinan Campus</option>
                            <option value="UPHI">Isabela Campus</option>
                            <option value="UPHR">Roxas Campus</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="add_stud_num">Student Number</label>
                        <input type="text" name="stud_num" id="add_stud_num" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label for="add_lname">Last Name</label>
                        <input type="text" name="lname" id="add_lname" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label for="add_fname">First Name</label>
                        <input type="text" name="fname" id="add_fname" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label for="add_course">Course (Optional)</label>
                        <input type="text" name="course" id="add_course" class="form-input">
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeAddEnrolledModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Student</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Enrolled Student Modal -->
    <div id="editEnrolledModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Enrolled Student</h3>
                <span class="close" onclick="closeEditEnrolledModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="edit_enrolled">
                    <input type="hidden" name="old_stud_num" id="edit_old_stud_num">
                    <input type="hidden" name="campid" id="edit_enrolled_campus">
                    <div class="form-group">
                        <label for="edit_stud_num">Student Number</label>
                        <input type="text" name="stud_num" id="edit_stud_num" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_lname">Last Name</label>
                        <input type="text" name="lname" id="edit_lname" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_fname">First Name</label>
                        <input type="text" name="fname" id="edit_fname" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_course">Course (Optional)</label>
                        <input type="text" name="course" id="edit_course" class="form-input">
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeEditEnrolledModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Student</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Enrolled Student Modal -->
    <div id="deleteEnrolledModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Delete Enrolled Student</h3>
                <span class="close" onclick="closeDeleteEnrolledModal()">&times;</span>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this student?</p>
                <p><strong>Student Number:</strong> <span id="delete_stud_num_display"></span></p>
                <p><strong>Name:</strong> <span id="delete_name_display"></span></p>
                <form method="POST">
                    <input type="hidden" name="action" value="delete_enrolled">
                    <input type="hidden" name="campid" id="delete_enrolled_campus">
                    <input type="hidden" name="stud_num" id="delete_stud_num">
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeDeleteEnrolledModal()">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Student</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Temporary Student Modal -->
    <div id="addTemporaryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add Temporary Student</h3>
                <span class="close" onclick="closeAddTemporaryModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_temporary">
                    <div class="form-group">
                        <label for="add_temporary_campus">Campus</label>
                        <select name="campid" id="add_temporary_campus" class="form-input" required>
                            <option value="">Select Campus</option>
                            <option value="UPHB">Binan Campus</option>
                            <option value="UPHMU">Medical University</option>
                            <option value="UPHG">GMA Campus</option>
                            <option value="UPHM">Manila Campus</option>
                            <option value="PHCP">Pangasinan Campus</option>
                            <option value="UPHI">Isabela Campus</option>
                            <option value="UPHR">Roxas Campus</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="add_locator_num">Locator Number</label>
                        <input type="text" name="locator_num" id="add_locator_num" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label for="add_stud_name">Student Name</label>
                        <input type="text" name="stud_name" id="add_stud_name" class="form-input" required>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeAddTemporaryModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Student</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Temporary Student Modal -->
    <div id="editTemporaryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Temporary Student</h3>
                <span class="close" onclick="closeEditTemporaryModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="edit_temporary">
                    <input type="hidden" name="old_locator_num" id="edit_old_locator_num">
                    <input type="hidden" name="campid" id="edit_temporary_campus">
                    <div class="form-group">
                        <label for="edit_locator_num">Locator Number</label>
                        <input type="text" name="locator_num" id="edit_locator_num" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_stud_name">Student Name</label>
                        <input type="text" name="stud_name" id="edit_stud_name" class="form-input" required>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeEditTemporaryModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Student</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Temporary Student Modal -->
    <div id="deleteTemporaryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Delete Temporary Student</h3>
                <span class="close" onclick="closeDeleteTemporaryModal()">&times;</span>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this temporary student?</p>
                <p><strong>Locator Number:</strong> <span id="delete_locator_num_display"></span></p>
                <p><strong>Name:</strong> <span id="delete_temp_name_display"></span></p>
                <form method="POST">
                    <input type="hidden" name="action" value="delete_temporary">
                    <input type="hidden" name="campid" id="delete_temporary_campus">
                    <input type="hidden" name="locator_num" id="delete_locator_num">
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeDeleteTemporaryModal()">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Student</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- CSV Import Modal (Enrolled) -->
    <div id="csvImportModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>CSV Import - Enrolled Students</h3>
                <span class="close" onclick="closeCSVImportModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="csv_campus">Select Campus</label>
                        <select name="campid" id="csv_campus" class="form-input" required>
                            <option value="">Choose your campus...</option>
                            <option value="UPHB">Binan Campus</option>
                            <option value="UPHMU">Medical University</option>
                            <option value="UPHG">GMA Campus</option>
                            <option value="UPHM">Manila Campus</option>
                            <option value="PHCP">Pangasinan Campus</option>
                            <option value="UPHI">Isabela Campus</option>
                            <option value="UPHR">Roxas Campus</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="csv_file">Select CSV File to Import</label>
                        <input type="file" name="excel_file" id="csv_file" accept=".csv" class="form-input" required>
                    </div>
                    <div class="info-section">
                        <h4>File Format Requirements</h4>
                        <ul>
                            <li>No header rows. Each row represents one student record.</li>
                            <li>Column 1: Student Number (must be unique)</li>
                            <li>Column 2: Last Name</li>
                            <li>Column 3: First Name</li>
                            <li>Column 4: Course (optional)</li>
                        </ul>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeCSVImportModal()">Cancel</button>
                        <button type="submit" name="btnsubmit" class="btn btn-primary">Import Data</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- CSV New Enroll Modal (Temporary) -->
    <div id="csvNewEnrollModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>CSV New Enroll - Temporary Students</h3>
                <span class="close" onclick="closeCSVNewEnrollModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="csv_tmp_campus">Select Campus</label>
                        <select name="campid_tmp" id="csv_tmp_campus" class="form-input" required>
                            <option value="">Choose your campus...</option>
                            <option value="UPHB">Binan Campus</option>
                            <option value="UPHMU">Medical University</option>
                            <option value="UPHG">GMA Campus</option>
                            <option value="UPHM">Manila Campus</option>
                            <option value="PHCP">Pangasinan Campus</option>
                            <option value="UPHI">Isabela Campus</option>
                            <option value="UPHR">Roxas Campus</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="csv_file_tmp">Select CSV File to Import</label>
                        <input type="file" name="csv_file_tmp" id="csv_file_tmp" accept=".csv" class="form-input" required>
                    </div>
                    <div class="info-section">
                        <h4>File Format Requirements</h4>
                        <ul>
                            <li>CSV with at least 3 columns per row (no header)</li>
                            <li>Column 1: Locator Number</li>
                            <li>Column 3: Student Name</li>
                        </ul>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeCSVNewEnrollModal()">Cancel</button>
                        <button type="submit" name="btnsubmit_tmp" class="btn btn-primary">Import Data</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Copy-Paste Import Modal (Temporary) -->
    <div id="pasteImportModalTmp" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Copy-Paste Import - Temporary Students</h3>
                <span class="close" onclick="closePasteImportModalTmp()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="post">
                    <div class="form-group">
                        <label for="paste_tmp_campus">Select Campus</label>
                        <select name="campid_tmp" id="paste_tmp_campus" class="form-input" required>
                            <option value="">Choose your campus...</option>
                            <option value="UPHB">Binan Campus</option>
                            <option value="UPHMU">Medical University</option>
                            <option value="UPHG">GMA Campus</option>
                            <option value="UPHM">Manila Campus</option>
                            <option value="PHCP">Pangasinan Campus</option>
                            <option value="UPHI">Isabela Campus</option>
                            <option value="UPHR">Roxas Campus</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="paste_data_tmp">Paste Student Data</label>
                        <textarea name="paste_data_tmp" id="paste_data_tmp" class="form-input" rows="15" placeholder="Copy data from Excel/Spreadsheet and paste here...
Format: Locator Number&#9;Date&#9;Student Name
Example:
26417244&#9;2026-07-15 00:00:00.0&#9;Santos, Manuel , O
26417245&#9;2026-07-15 00:00:00.0&#9;Dela Cruz, Juan
26417246&#9;2026-07-15 00:00:00.0&#9;Reyes, Jose" required></textarea>
                    </div>
                    <div class="info-section">
                        <h4>Instructions</h4>
                        <ul>
                            <li>Select cells in Excel/Spreadsheet and copy (Ctrl+C)</li>
                            <li>Paste directly into the text area above (Ctrl+V)</li>
                            <li>Each row represents one student record</li>
                            <li>Column 1: Locator Number (must be unique)</li>
                            <li>Column 2: Date (ignored)</li>
                            <li>Column 3: Student Name</li>
                            <li>Supports tab-separated data from Excel</li>
                        </ul>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closePasteImportModalTmp()">Cancel</button>
                        <button type="submit" name="btnsubmit_paste_tmp" class="btn btn-primary">Import Data</button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <script>
        // Tab switching
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                this.classList.add('active');
                document.getElementById('tab-' + this.dataset.tab).classList.add('active');
            });
        });

        // Export buttons — disabled until campus selected
        function updateEnrolledExportBtn(){
            const btn=document.getElementById('enrolled-export-btn');
            if(!btn) return;
            if(!enrolledCurrentCampus){
                btn.style.opacity='.5'; btn.style.pointerEvents='none'; btn.title='Select a campus first'; btn.href='#';
            } else {
                btn.style.opacity='1'; btn.style.pointerEvents='auto'; btn.title='Export CSV';
                const p=new URLSearchParams({export:'csv', tab:'enrolled', campid:enrolledCurrentCampus, search:enrolledCurrentSearch});
                btn.href='students?'+p.toString();
            }
        }
        function updateTemporaryExportBtn(){
            const btn=document.getElementById('temporary-export-btn');
            if(!btn) return;
            if(!temporaryCurrentCampus){
                btn.style.opacity='.5'; btn.style.pointerEvents='none'; btn.title='Select a campus first'; btn.href='#';
            } else {
                btn.style.opacity='1'; btn.style.pointerEvents='auto'; btn.title='Export CSV';
                const p=new URLSearchParams({export:'csv', tab:'temporary', campid:temporaryCurrentCampus, search:temporaryCurrentSearch});
                btn.href='students?'+p.toString();
            }
        }

        // Enrolled students table loading
        let enrolledCurrentPage = 1;
        let enrolledCurrentCampus = '';
        let enrolledCurrentSearch = '';

        document.getElementById('enrolled-campus').addEventListener('change', function() {
            enrolledCurrentCampus = this.value;
            enrolledCurrentPage = 1;
            updateEnrolledExportBtn();
            loadEnrolledStudents();
        });

        document.getElementById('enrolled-search').addEventListener('input', debounce(function(e) {
            enrolledCurrentSearch = e.target.value;
            enrolledCurrentPage = 1;
            updateEnrolledExportBtn();
            loadEnrolledStudents();
        }, 300));

        document.getElementById('enrolled-search').addEventListener('keyup', debounce(function(e) {
            enrolledCurrentSearch = e.target.value;
            enrolledCurrentPage = 1;
            updateEnrolledExportBtn();
            loadEnrolledStudents();
        }, 300));

        function performEnrolledSearch() {
            enrolledCurrentSearch = document.getElementById('enrolled-search').value;
            enrolledCurrentPage = 1;
            loadEnrolledStudents();
        }

        function loadEnrolledStudents() {
            if (!enrolledCurrentCampus) {
                document.getElementById('enrolled-table-body').innerHTML = '<tr><td colspan="5" class="text-center">Please select a campus to view students</td></tr>';
                document.getElementById('enrolled-pagination').innerHTML = '';
                return;
            }

            const params = new URLSearchParams({
                ajax: '1',
                tab: 'enrolled',
                campid: enrolledCurrentCampus,
                search: enrolledCurrentSearch,
                page: enrolledCurrentPage
            });

            fetch('students?' + params.toString())
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderEnrolledTable(data.data);
                        renderPagination('enrolled-pagination', data.totalPages, enrolledCurrentPage, (page) => {
                            enrolledCurrentPage = page;
                            loadEnrolledStudents();
                        });
                    } else {
                        document.getElementById('enrolled-table-body').innerHTML = '<tr><td colspan="5" class="text-center">Error: ' + data.error + '</td></tr>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('enrolled-table-body').innerHTML = '<tr><td colspan="5" class="text-center">Error loading data</td></tr>';
                });
        }

        function renderEnrolledTable(data) {
            const tbody = document.getElementById('enrolled-table-body');
            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center">No students found</td></tr>';
                return;
            }

            tbody.innerHTML = data.map(student => `
                <tr>
                    <td>${escapeHtml(student.stud_num)}</td>
                    <td>${escapeHtml(student.lname)}</td>
                    <td>${escapeHtml(student.fname)}</td>
                    <td>${escapeHtml(student.course || '')}</td>
                    <td>
                        <button class="btn btn-sm btn-secondary" onclick="openEditEnrolledModal('${escapeHtml(student.stud_num)}', '${escapeHtml(student.lname)}', '${escapeHtml(student.fname)}', '${escapeHtml(student.course || '')}')">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="openDeleteEnrolledModal('${escapeHtml(student.stud_num)}', '${escapeHtml(student.lname)}', '${escapeHtml(student.fname)}')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `).join('');
        }

        // Temporary students table loading
        let temporaryCurrentPage = 1;
        let temporaryCurrentCampus = '';
        let temporaryCurrentSearch = '';

        document.getElementById('temporary-campus').addEventListener('change', function() {
            temporaryCurrentCampus = this.value;
            temporaryCurrentPage = 1;
            updateTemporaryExportBtn();
            loadTemporaryStudents();
        });

        document.getElementById('temporary-search').addEventListener('input', debounce(function(e) {
            temporaryCurrentSearch = e.target.value;
            temporaryCurrentPage = 1;
            updateTemporaryExportBtn();
            loadTemporaryStudents();
        }, 300));

        document.getElementById('temporary-search').addEventListener('keyup', debounce(function(e) {
            temporaryCurrentSearch = e.target.value;
            temporaryCurrentPage = 1;
            updateTemporaryExportBtn();
            loadTemporaryStudents();
        }, 300));

        function performTemporarySearch() {
            temporaryCurrentSearch = document.getElementById('temporary-search').value;
            temporaryCurrentPage = 1;
            loadTemporaryStudents();
        }

        function loadTemporaryStudents() {
            if (!temporaryCurrentCampus) {
                document.getElementById('temporary-table-body').innerHTML = '<tr><td colspan="3" class="text-center">Please select a campus to view students</td></tr>';
                document.getElementById('temporary-pagination').innerHTML = '';
                return;
            }

            const params = new URLSearchParams({
                ajax: '1',
                tab: 'temporary',
                campid: temporaryCurrentCampus,
                search: temporaryCurrentSearch,
                page: temporaryCurrentPage
            });

            fetch('students?' + params.toString())
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderTemporaryTable(data.data);
                        renderPagination('temporary-pagination', data.totalPages, temporaryCurrentPage, (page) => {
                            temporaryCurrentPage = page;
                            loadTemporaryStudents();
                        });
                    } else {
                        document.getElementById('temporary-table-body').innerHTML = '<tr><td colspan="3" class="text-center">Error: ' + data.error + '</td></tr>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('temporary-table-body').innerHTML = '<tr><td colspan="3" class="text-center">Error loading data</td></tr>';
                });
        }

        function renderTemporaryTable(data) {
            const tbody = document.getElementById('temporary-table-body');
            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3" class="text-center">No students found</td></tr>';
                return;
            }

            tbody.innerHTML = data.map(student => `
                <tr>
                    <td>${escapeHtml(student.locator_num)}</td>
                    <td>${escapeHtml(student.stud_name)}</td>
                    <td>
                        <button class="btn btn-sm btn-secondary" onclick="openEditTemporaryModal('${escapeHtml(student.locator_num)}', '${escapeHtml(student.stud_name)}')">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="openDeleteTemporaryModal('${escapeHtml(student.locator_num)}', '${escapeHtml(student.stud_name)}')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `).join('');
        }

        function renderPagination(containerId, totalPages, currentPage, onPageChange) {
            const container = document.getElementById(containerId);
            if (totalPages <= 1) {
                container.innerHTML = '';
                return;
            }

            let html = '';
            
            // Previous button
            html += `<button ${currentPage === 1 ? 'disabled' : ''} data-page="${currentPage - 1}">Previous</button>`;
            
            // Page numbers
            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
                    html += `<button class="${i === currentPage ? 'active' : ''}" data-page="${i}">${i}</button>`;
                } else if (i === currentPage - 3 || i === currentPage + 3) {
                    html += `<button disabled>...</button>`;
                }
            }
            
            // Next button
            html += `<button ${currentPage === totalPages ? 'disabled' : ''} data-page="${currentPage + 1}">Next</button>`;
            
            container.innerHTML = html;

            // Add click event listeners to pagination buttons
            container.querySelectorAll('button[data-page]').forEach(button => {
                button.addEventListener('click', function() {
                    const page = parseInt(this.dataset.page);
                    if (!isNaN(page) && page !== currentPage) {
                        onPageChange(page);
                    }
                });
            });
        }

        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Modal functions for Enrolled Students
        function openAddEnrolledModal() {
            document.getElementById('addEnrolledModal').style.display = 'block';
            document.getElementById('add_enrolled_campus').value = enrolledCurrentCampus;
        }

        function closeAddEnrolledModal() {
            document.getElementById('addEnrolledModal').style.display = 'none';
        }

        function openEditEnrolledModal(studNum, lname, fname, course) {
            document.getElementById('editEnrolledModal').style.display = 'block';
            document.getElementById('edit_old_stud_num').value = studNum;
            document.getElementById('edit_stud_num').value = studNum;
            document.getElementById('edit_lname').value = lname;
            document.getElementById('edit_fname').value = fname;
            document.getElementById('edit_course').value = course;
            document.getElementById('edit_enrolled_campus').value = enrolledCurrentCampus;
        }

        function closeEditEnrolledModal() {
            document.getElementById('editEnrolledModal').style.display = 'none';
        }

        function openDeleteEnrolledModal(studNum, lname, fname) {
            document.getElementById('deleteEnrolledModal').style.display = 'block';
            document.getElementById('delete_stud_num_display').textContent = studNum;
            document.getElementById('delete_name_display').textContent = lname + ', ' + fname;
            document.getElementById('delete_stud_num').value = studNum;
            document.getElementById('delete_enrolled_campus').value = enrolledCurrentCampus;
        }

        function closeDeleteEnrolledModal() {
            document.getElementById('deleteEnrolledModal').style.display = 'none';
        }

        // Modal functions for Temporary Students
        function openAddTemporaryModal() {
            document.getElementById('addTemporaryModal').style.display = 'block';
            document.getElementById('add_temporary_campus').value = temporaryCurrentCampus;
        }

        function closeAddTemporaryModal() {
            document.getElementById('addTemporaryModal').style.display = 'none';
        }

        function openEditTemporaryModal(locatorNum, studName) {
            document.getElementById('editTemporaryModal').style.display = 'block';
            document.getElementById('edit_old_locator_num').value = locatorNum;
            document.getElementById('edit_locator_num').value = locatorNum;
            document.getElementById('edit_stud_name').value = studName;
            document.getElementById('edit_temporary_campus').value = temporaryCurrentCampus;
        }

        function closeEditTemporaryModal() {
            document.getElementById('editTemporaryModal').style.display = 'none';
        }

        function openDeleteTemporaryModal(locatorNum, studName) {
            document.getElementById('deleteTemporaryModal').style.display = 'block';
            document.getElementById('delete_locator_num_display').textContent = locatorNum;
            document.getElementById('delete_temp_name_display').textContent = studName;
            document.getElementById('delete_locator_num').value = locatorNum;
            document.getElementById('delete_temporary_campus').value = temporaryCurrentCampus;
        }

        function closeDeleteTemporaryModal() {
            document.getElementById('deleteTemporaryModal').style.display = 'none';
        }

        // CSV Import Modals
        function openCSVImportModal() {
            document.getElementById('csvImportModal').style.display = 'block';
            document.getElementById('csv_campus').value = enrolledCurrentCampus;
        }

        function closeCSVImportModal() {
            document.getElementById('csvImportModal').style.display = 'none';
        }

        function openCSVNewEnrollModal() {
            document.getElementById('csvNewEnrollModal').style.display = 'block';
            document.getElementById('csv_tmp_campus').value = temporaryCurrentCampus;
        }

        function closeCSVNewEnrollModal() {
            document.getElementById('csvNewEnrollModal').style.display = 'none';
        }

        // Copy-Paste Import Modal (Temporary Students)
        function openPasteImportModalTmp() {
            document.getElementById('pasteImportModalTmp').style.display = 'block';
            document.getElementById('paste_tmp_campus').value = temporaryCurrentCampus;
        }

        function closePasteImportModalTmp() {
            document.getElementById('pasteImportModalTmp').style.display = 'none';
        }

        // --- modal scroll lock helpers ---
        function lockBodyScroll(){ document.body.classList.add('modal-open'); }
        function unlockBodyScroll(){ if(!document.querySelector('.modal[style*="block"]')) document.body.classList.remove('modal-open'); }
        // wrap open/close to handle scroll lock
        const _modalIds = ['addEnrolledModal','editEnrolledModal','deleteEnrolledModal','addTemporaryModal','editTemporaryModal','deleteTemporaryModal','csvImportModal','csvNewEnrollModal','pasteImportModalTmp'];
        _modalIds.forEach(id=>{
            const el=document.getElementById(id);
            if(!el) return;
            const obs=new MutationObserver(()=>{ if(el.style.display==='block') lockBodyScroll(); else unlockBodyScroll(); });
            obs.observe(el,{attributes:true,attributeFilter:['style']});
            // wheel/touch should scroll modal-body, not backdrop
            el.addEventListener('wheel',e=>{
                const body=el.querySelector('.modal-body');
                if(!body) return;
                const atTop=body.scrollTop===0, atBottom=body.scrollTop+body.clientHeight>=body.scrollHeight-1;
                if((e.deltaY<0 && atTop) || (e.deltaY>0 && atBottom)) e.preventDefault();
            },{passive:false});
        });
        document.addEventListener('keydown',e=>{
            if(e.key==='Escape'){
                document.querySelectorAll('.modal').forEach(m=>m.style.display='none');
                document.body.classList.remove('modal-open');
            }
        });

        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
                unlockBodyScroll();
            }
        }
     </script>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>




