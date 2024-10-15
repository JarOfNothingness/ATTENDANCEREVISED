<?php
// Connect to the database
include("../LoginRegisterAuthentication/connection.php");
include("../crud/header.php");

// Fetch available sections and subjects for the dropdowns
$sections_query = "SELECT DISTINCT `grade & section` FROM students";
$subjects_query = "SELECT DISTINCT subject FROM students";
$sections_result = mysqli_query($connection, $sections_query);
$subjects_result = mysqli_query($connection, $subjects_query);

// Check if section and subject have been selected
$section = $_POST['section'] ?? $_GET['section'] ?? '';
$subject_id = $_POST['subject_id'] ?? $_GET['subject_id'] ?? '';
$month = $_POST['month'] ?? $_GET['month'] ?? date('Y-m');
$saved = $_GET['saved'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' || ($section && $subject_id)) {
    // Show success message if attendance was saved
    if ($saved == 1) {
        echo "<p style='color: green;'>Attendance has been successfully saved!</p>";
    }

    // Query to get the students in the selected section and subject
    $query = "SELECT id, learners_name FROM students WHERE `grade & section` = ? AND subject = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("ss", $section, $subject_id);
    $stmt->execute();
    $students = $stmt->get_result();

    // Display the attendance form
    echo "<h2>Attendance Sheet for Section: " . htmlspecialchars($section) . " - Subject: " . htmlspecialchars($subject_id) . "</h2>";
    echo "<form method='post' action='save_attendance.php'>";
    echo "<table border='1' cellspacing='0' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
    echo "<thead><tr><th>Student Name</th>";

    // Get the total number of days in the month and iterate
    $numDays = date('t', strtotime($month));
    $days_in_month = [];
    
    for ($i = 1; $i <= $numDays; $i++) {
        $dayOfWeek = date('N', strtotime("$month-$i")); // Get the day of the week (1 = Monday, 7 = Sunday)
        
        if ($dayOfWeek < 6) { // Skip Saturdays (6) and Sundays (7)
            echo "<th>" . str_pad($i, 2, '0', STR_PAD_LEFT) . "</th>";
            $days_in_month[] = $i; // Store valid (non-weekend) days for later use
        }
    }
    echo "<th>Total Present</th><th>Total Absent</th><th>Total Points</th></tr></thead><tbody>";

    // Loop through students
    while ($student = $students->fetch_assoc()) {
        echo "<tr><td>" . htmlspecialchars($student['learners_name']) . "</td>";

        // Query to get attendance for the student
        $attendanceQuery = "SELECT * FROM attendance WHERE student_id = ? AND month = ? AND subject_id = ?";
        $attendanceStmt = $connection->prepare($attendanceQuery);
        $attendanceStmt->bind_param("iss", $student['id'], $month, $subject_id);
        $attendanceStmt->execute();
        $attendance = $attendanceStmt->get_result()->fetch_assoc();

        $totalPresent = 0;
        $totalAbsent = 0;
        $totalPoints = 0;

        // Generate attendance dropdown for each day, skipping weekends
        foreach ($days_in_month as $i) {
            $day = "day_" . str_pad($i, 2, '0', STR_PAD_LEFT);
            $attendanceStatus = $attendance[$day] ?? 'P'; // Default to "P" for present

            echo "<td>
                    <select name='attendance[" . $student['id'] . "][" . $day . "]'>
                        <option value='P'" . ($attendanceStatus == 'P' ? ' selected' : '') . ">P</option>
                        <option value='A'" . ($attendanceStatus == 'A' ? ' selected' : '') . ">A</option>
                        <option value='L'" . ($attendanceStatus == 'L' ? ' selected' : '') . ">L</option>
                        <option value='E'" . ($attendanceStatus == 'E' ? ' selected' : '') . ">E</option>
                    </select>
                </td>";

            // Count total present and absent days and calculate points
            if ($attendanceStatus == 'P') {
                $totalPresent++;
                $totalPoints += 10; // 10 points for present
            } elseif ($attendanceStatus == 'A') {
                $totalAbsent++;
                // 0 points for absent, so no change to totalPoints
            }
        }

        // Add total columns for Present, Absent, and Points
        echo "<td>$totalPresent</td><td>$totalAbsent</td><td>$totalPoints</td>";
        echo "</tr>";
    }

    echo "</tbody></table>";
    echo "<input type='hidden' name='section' value='" . htmlspecialchars($section) . "'>";
    echo "<input type='hidden' name='month' value='" . htmlspecialchars($month) . "'>";
    echo "<input type='hidden' name='subject_id' value='" . htmlspecialchars($subject_id) . "'>";
    echo "<button type='submit'>Save Attendance</button>";
    echo "</form>";
} else {
    // If no section or subject selected, show selection form
    ?>

    <h2>Select Section and Subject</h2>
    <form method="post" action="Attendance.php">
        <label for="section">Section:</label>
        <select name="section" id="section" required>
            <option value="">Select Section</option>
            <?php while ($row = mysqli_fetch_assoc($sections_result)) { ?>
                <option value="<?php echo htmlspecialchars($row['grade & section']); ?>">
                    <?php echo htmlspecialchars($row['grade & section']); ?>
                </option>
            <?php } ?>
        </select>

        <label for="subject_id">Subject:</label>
        <select name="subject_id" id="subject_id" required>
            <option value="">Select Subject</option>
            <?php while ($row = mysqli_fetch_assoc($subjects_result)) { ?>
                <option value="<?php echo htmlspecialchars($row['subject']); ?>">
                    <?php echo htmlspecialchars($row['subject']); ?>
                </option>
            <?php } ?>
        </select>

        <label for="month">Month:</label>
        <input type="month" name="month" id="month" value="<?php echo date('Y-m'); ?>">

        <button type="submit">Load Attendance</button>
    </form>

<?php
}

include("../crud/footer.php");
?>
