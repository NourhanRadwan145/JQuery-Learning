<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'learn';

$conn = new mysqli($host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die(json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]));
}

function insertSampleData($conn) {
    $sql = "INSERT INTO employee (Name, Position, Office, Age, StartDate, Salary) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    for ($i = 0; $i < 1000; $i++) {
        $name = fakeName();
        $position = fakePosition();
        $office = fakeOffice();
        $age = rand(20, 65);
        $startDate = fakeStartDate();
        $salary = rand(40000, 150000);

        $stmt->bind_param("sssiis", $name, $position, $office, $age, $startDate, $salary);
        $stmt->execute();
    }

    $stmt->close();
}

function fakeName() {
    $firstNames = ['John', 'Jane', 'Alex', 'Emily', 'Michael', 'Sarah'];
    $lastNames = ['Doe', 'Smith', 'Johnson', 'Williams', 'Brown', 'Jones'];
    return $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)];
}

function fakePosition() {
    $positions = ['Software Engineer', 'Manager', 'Designer', 'Product Owner', 'QA Tester', 'Data Scientist'];
    return $positions[array_rand($positions)];
}

function fakeOffice() {
    $offices = ['New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix', 'Philadelphia'];
    return $offices[array_rand($offices)];
}

function fakeStartDate() {
    $timestamp = strtotime("-" . rand(0, 5) . " years");
    return date("Y-m-d", $timestamp);
}

if (isset($_GET['insert_sample_data'])) {
    insertSampleData($conn);
    echo json_encode(['message' => 'Sample data inserted']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $start = isset($_GET['start']) ? $_GET['start'] : 0;
    $length = isset($_GET['length']) ? $_GET['length'] : 10;
    $searchValue = isset($_GET['search']['value']) ? $_GET['search']['value'] : '';
    $email = isset($_GET['email']) ? $_GET['email'] : '';

    $searchTerm = '%' . $conn->real_escape_string($searchValue) . '%';

    // Get sorting parameters from DataTables
    $orderColumnIndex = isset($_GET['order'][0]['column']) ? $_GET['order'][0]['column'] : 0;
    $orderDir = isset($_GET['order'][0]['dir']) && in_array(strtoupper($_GET['order'][0]['dir']), ['ASC', 'DESC']) ? strtoupper($_GET['order'][0]['dir']) : 'ASC';

    // Define the allowed columns for ordering to prevent SQL injection
    $columns = ['Name', 'Position', 'Office', 'Age', 'StartDate', 'Salary'];
    if (isset($columns[$orderColumnIndex])) {
        $orderColumn = $columns[$orderColumnIndex];
    } else {
        $orderColumn = 'Name'; // Default column if index is out of bounds
    }

    // Prepare the SQL query with dynamic ORDER BY clause
    $sql = "SELECT SQL_CALC_FOUND_ROWS * FROM employee WHERE Name LIKE ? ORDER BY $orderColumn $orderDir LIMIT ?, ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sii', $searchTerm, $start, $length);
    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    $stmt->close();

    // Get the total filtered records count
    $result = $conn->query("SELECT FOUND_ROWS() AS totalFiltered");
    $filteredRecords = $result->fetch_assoc()['totalFiltered'];

    // Get the total records count
    $totalSql = 'SELECT COUNT(*) AS total FROM employee';
    $totalResult = $conn->query($totalSql);
    $totalRecords = $totalResult->fetch_assoc()['total'];

    // Output data as JSON
    echo json_encode([
        'start' => $start,
        'length' => $length,
        'recordsTotal' => $totalRecords,
        'recordsFiltered' => $filteredRecords,
        'data' => $data,
        'email' => $email
    ]);
}

$conn->close();
?>
