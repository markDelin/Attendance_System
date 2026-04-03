<?php
// database/seed_schedule.php
require_once __DIR__ . '/../includes/db.php';

$semester = "2nd Sem 2025-2026"; // Default/Assumed

// 1. Define the Schedule Data
$scheduleData = [
    // Mon & Tue
    'IT Infrastructure & Network Technology' => [
        ['days' => ['Monday', 'Tuesday'], 'start' => '07:00', 'end' => '09:00']
    ],
    'Organization & Management Concepts' => [
        ['days' => ['Monday', 'Tuesday'], 'start' => '09:00', 'end' => '10:30']
    ],
    'Team Sports' => [
        ['days' => ['Monday', 'Tuesday'], 'start' => '13:00', 'end' => '14:00']
    ],
    'General Physics' => [
        ['days' => ['Monday', 'Tuesday'], 'start' => '16:00', 'end' => '17:30']
    ],
    'Social Science & Philosophy' => [
        ['days' => ['Monday', 'Tuesday'], 'start' => '17:30', 'end' => '19:00']
    ],

    // Saturday
    'Ethics' => [
        ['days' => ['Saturday'], 'start' => '07:00', 'end' => '09:00']
    ],
    'Math Science & Technology' => [
        ['days' => ['Saturday'], 'start' => '09:00', 'end' => '13:00']
    ],
    'Information Management' => [
        ['days' => ['Saturday'], 'start' => '13:00', 'end' => '16:00']
    ],
    'Art & Humanities' => [
        ['days' => ['Saturday'], 'start' => '16:00', 'end' => '19:00']
    ]
];

echo "Seeding Schedule...\n";

try {
    // Ensure tables exist (running the db script logic implicitly via require, but let's be safe)
    // Actually db.php doesn't run creation automatically unless visited or logic triggered, 
    // but our edits to db.php put the arrays in. We need to iterate the tables array if we want to ensure creation.
    // However, existing usage implies tables are created on connection or manually. 
    // Let's manually trigger the creation loop from db.php logic just in case, or just trust the previous migrations.
    // simpler:
    $pdo->exec("CREATE TABLE IF NOT EXISTS schedules (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            subject_id INTEGER NOT NULL,
            day_of_week TEXT NOT NULL, 
            start_time TEXT NOT NULL, 
            end_time TEXT NOT NULL
        )");

    // Clear existing schedules to avoid duplicates on re-run
    $pdo->exec("DELETE FROM schedules");
    
    foreach ($scheduleData as $subName => $times) {
        // 1. Find or Create Subject
        $stmt = $pdo->prepare("SELECT id FROM subjects WHERE name = ?");
        $stmt->execute([$subName]);
        $sub = $stmt->fetch();

        if ($sub) {
            $subId = $sub['id'];
            echo "Found Subject: $subName ($subId)\n";
        } else {
            $pdo->prepare("INSERT INTO subjects (name, semester) VALUES (?, ?)")->execute([$subName, $semester]);
            $subId = $pdo->lastInsertId();
            echo "Created Subject: $subName ($subId)\n";
        }

        // 2. Insert Schedules
        foreach ($times as $t) {
            foreach ($t['days'] as $day) {
                $pdo->prepare("INSERT INTO schedules (subject_id, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?)")
                    ->execute([$subId, $day, $t['start'], $t['end']]);
                echo "  -> Added Schedule: $day {$t['start']} - {$t['end']}\n";
            }
        }
    }

    echo "Done!\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
