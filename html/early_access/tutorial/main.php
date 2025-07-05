<?php
    // tutorial/main.php
    // This file is for the tutorial

    // Connect to db (no login)
    include '../db.php';

    // Check tutorial progression
	$sql = "SELECT user_progress, coach_progress, admin_progress FROM tutorial WHERE user_id = ?";
	$stmt = $conn->prepare($sql);
	$stmt->bind_param("i", $userId);
	$stmt->execute();
	$result = $stmt->get_result();
	$tutorial = $result->fetch_assoc();

	if (!$tutorial) {
	    die(json_encode(["error" => "Tutorial User not found " . $userId]));
	}

    if ($typeAdmin === 1) {
        $progress = htmlspecialchars($tutorial['admin_progress']);
        $userType = 'admin';
    } elseif ($typeCoach === 1) {
        $progress = htmlspecialchars($tutorial['coach_progress']);
        $userType = 'coach';
    } else {
        $progress = htmlspecialchars($tutorial['user_progress']);
        $userType = 'user';
    }

	$stmt->close();

    // If the tutorial hasn't started create the example athletes, sessions, ect in a safe env (seperate from club)
    $listNames = ['Juniors', 'Intermediate', 'Senior'];
    $athletes = [
        'Juniors' => ['John Doe', 'Jane Smith', 'Mike Johnson'],
        'Intermediate' => ['Emily Davis', 'Ryan Thompson', 'Sarah Lee'],
        'Senior' => ['Michael Brown', 'Lily Chen', 'Kevin White']
    ];

    foreach ($listNames as $listName) {
        foreach ($athletes[$listName] as $athleteName) {
            // Check if athlete already exists for this user/club/list
            $checkSql = "SELECT 1 FROM groups WHERE user_id = ? AND item_name = ? AND list_name = ? AND club = ? LIMIT 1";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->bind_param("isss", $userId, $athleteName, $listName, $userName);
            $checkStmt->execute();
            $checkStmt->store_result();
            if ($checkStmt->num_rows === 0) {
                // Not found, insert
                $insertSql = "INSERT INTO groups (user_id, item_name, list_name, club) VALUES (?, ?, ?, ?)";
                $insertStmt = $conn->prepare($insertSql);
                $insertStmt->bind_param("isss", $userId, $athleteName, $listName, $userName);
                $insertStmt->execute();
                $insertStmt->close();
            }
            $checkStmt->close();
        }
    }

    $sessions = [
        date('l d/m/y') => [
            'Juniors' => [
                'time' => '05:00',
                'stime' => '05:25',
                'etime' => '07:30'
            ],
            'Intermediate' => [
                'time' => '08:15',
                'stime' => '08:40',
                'etime' => '10:45'
            ]
        ],
        date('l', strtotime('+1 day')) . ' ' . date('d/m/y', strtotime('+1 day')) => [
            'Senior' => [
                'time' => '13:00',
                'stime' => '13:25',
                'etime' => '15:30'
            ]
        ],
        date('l', strtotime('+2 days')) . ' ' . date('d/m/y', strtotime('+2 days')) => [
            'Juniors' => [
                'time' => '04:30',
                'stime' => '04:55',
                'etime' => '07:00'
            ],
            'Intermediate' => [
                'time' => '09:45',
                'stime' => '10:10',
                'etime' => '12:15'
            ]
        ],
        date('l', strtotime('+3 days')) . ' ' . date('d/m/y', strtotime('+3 days')) => [
            'Senior' => [
                'time' => '14:00',
                'stime' => '14:25',
                'etime' => '16:30'
            ]
        ],
        date('l', strtotime('+4 days')) . ' ' . date('d/m/y', strtotime('+4 days')) => [
            'Juniors' => [
                'time' => '05:15',
                'stime' => '05:40',
                'etime' => '07:45'
            ],
            'Intermediate' => [
                'time' => '10:00',
                'stime' => '10:25',
                'etime' => '12:30'
            ]
        ],
        date('l', strtotime('+5 days')) . ' ' . date('d/m/y', strtotime('+5 days')) => [
            'Senior' => [
                'time' => '17:00',
                'stime' => '17:25',
                'etime' => '19:30'
            ]
        ],
        date('l', strtotime('+6 days')) . ' ' . date('d/m/y', strtotime('+6 days')) => [
            'Juniors' => [
                'time' => '04:45',
                'stime' => '05:10',
                'etime' => '07:15'
            ],
            'Intermediate' => [
                'time' => '11:15',
                'stime' => '11:40',
                'etime' => '13:45'
            ]
        ]
    ];

    foreach ($sessions as $day => $sessionDetails) {
        foreach ($sessionDetails as $listName => $session) {
            $coach = 'Coach Doe';
            $location = 'Main Arena';

            // Check if the session already exists
            $checkSql = "SELECT COUNT(*) FROM timetable WHERE _day = ? AND _time = ? AND stime = ? AND etime = ? AND _group = ? AND _location = ? AND coach = ? AND club = ?";
            $checkStmt = $conn->prepare($checkSql);
            if ($checkStmt === false) {
                error_log("Prepare failed (check): " . $conn->error);
                continue;
            }
            if (!$checkStmt->bind_param("ssssssss", $day, $session['time'], $session['stime'], $session['etime'], $listName, $location, $coach, $userName)) {
                error_log("Bind param failed (check): " . $checkStmt->error);
                $checkStmt->close();
                continue;
            }
            if (!$checkStmt->execute()) {
                error_log("Execute failed (check): " . $checkStmt->error);
                $checkStmt->close();
                continue;
            }
            $checkStmt->bind_result($count);
            $checkStmt->fetch();
            $checkStmt->close();

            if ($count == 0) {
                $sql = "INSERT INTO timetable (_day, _time, stime, etime, _group, _location, coach, club) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                if ($stmt === false) {
                    error_log("Prepare failed: " . $conn->error);
                    continue;
                }
                if (!$stmt->bind_param("ssssssss", $day, $session['time'], $session['stime'], $session['etime'], $listName, $location, $coach, $userName)) {
                    error_log("Bind param failed: " . $stmt->error);
                    $stmt->close();
                    continue;
                }
                if (!$stmt->execute()) {
                    error_log("Execute failed: " . $stmt->error);
                }
                $stmt->close();
            }
        }
    }

    // Start the tutorial
/**
 * Tutorial Manager - PHP Backend for Tutorial Overlay System
 * 
 * This class manages tutorial data, user progress, and analytics
 */

class TutorialManager {
    private $db;
    private $tutorialData;
    
    public function __construct($database = null) {
        $this->db = $database;
        $this->loadTutorialData();
    }
    
    /**
     * Load tutorial configuration from database or file
     */
    private function loadTutorialData() {
        // Example tutorial data structure
        $this->tutorialData = [
            'tutorials' => [
                'dashboard' => [
                    'id' => 'dashboard',
                    'name' => 'Dashboard Tutorial',
                    'description' => 'Learn how to use your dashboard',
                    'steps' => [
                        [
                            'id' => 'step-1',
                            'selector' => '[data-tutorial="step-1"]',
                            'title' => 'Info',
                            'description' => 'This is the info section of your dashboard.',
                            'position' => 'left',
                            'delay' => 0
                        ],
                        [
                            'id' => 'step-2',
                            'selector' => '[data-tutorial="step-2"]',
                            'title' => 'Edit Info',
                            'description' => 'This is the edit info section of your dashboard.',
                            'position' => 'top',
                            'delay' => 0
                        ],
                        [
                            'id' => 'step-3',
                            'selector' => '[data-tutorial="step-3"]',
                            'title' => 'Session Details',
                            'description' => 'This is the session details section of your dashboard.',
                            'position' => 'top',
                            'delay' => 0
                        ],
                        [
                            'id' => 'step-4',
                            'selector' => '[data-tutorial="step-4"]',
                            'title' => 'Add Session',
                            'description' => 'This is the add session section of your dashboard.',
                            'position' => 'bottom',
                            'delay' => 0
                        ],
                        [
                            'id' => 'step-5',
                            'selector' => '[data-tutorial="step-5"]',
                            'title' => 'Add Athlete',
                            'description' => 'This is the add athlete section of your dashboard.',
                            'position' => 'bottom',
                            'delay' => 0
                        ]
                    ]
                ],
                'advanced' => [
                    'id' => 'advanced',
                    'name' => 'Advanced Features Tutorial',
                    'description' => 'Learn about advanced features',
                    'steps' => [
                        // Add more tutorial steps here
                    ]
                ]
            ],
            'settings' => [
                'auto_start' => true,
                'auto_start_delay' => 2000,
                'show_progress' => true,
                'allow_skip' => true,
                'keyboard_navigation' => true,
                'analytics_enabled' => true
            ]
        ];
    }
    
    /**
     * Get tutorial data for a specific tutorial
     */
    public function getTutorial($tutorialId = 'dashboard') {
        if (isset($this->tutorialData['tutorials'][$tutorialId])) {
            return $this->tutorialData['tutorials'][$tutorialId];
        }
        return null;
    }
    
    /**
     * Get all available tutorials
     */
    public function getAllTutorials() {
        return $this->tutorialData['tutorials'];
    }
    
    /**
     * Get tutorial settings
     */
    public function getSettings() {
        return $this->tutorialData['settings'];
    }
    
    /**
     * Update tutorial settings
     */
    public function updateSettings($newSettings) {
        $this->tutorialData['settings'] = array_merge($this->tutorialData['settings'], $newSettings);
        return $this->saveTutorialData();
    }
    
    /**
     * Add a new tutorial step
     */
    public function addTutorialStep($tutorialId, $step) {
        if (isset($this->tutorialData['tutorials'][$tutorialId])) {
            $this->tutorialData['tutorials'][$tutorialId]['steps'][] = $step;
            return $this->saveTutorialData();
        }
        return false;
    }
    
    /**
     * Update an existing tutorial step
     */
    public function updateTutorialStep($tutorialId, $stepIndex, $stepData) {
        if (isset($this->tutorialData['tutorials'][$tutorialId]['steps'][$stepIndex])) {
            $this->tutorialData['tutorials'][$tutorialId]['steps'][$stepIndex] = array_merge(
                $this->tutorialData['tutorials'][$tutorialId]['steps'][$stepIndex],
                $stepData
            );
            return $this->saveTutorialData();
        }
        return false;
    }
    
    /**
     * Remove a tutorial step
     */
    public function removeTutorialStep($tutorialId, $stepIndex) {
        if (isset($this->tutorialData['tutorials'][$tutorialId]['steps'][$stepIndex])) {
            array_splice($this->tutorialData['tutorials'][$tutorialId]['steps'], $stepIndex, 1);
            return $this->saveTutorialData();
        }
        return false;
    }
    
    /**
     * Track user tutorial progress
     */
    public function trackUserProgress($userId, $tutorialId, $stepId, $action) {
        $progressData = [
            'user_id' => $userId,
            'tutorial_id' => $tutorialId,
            'step_id' => $stepId,
            'action' => $action, // 'started', 'completed', 'skipped'
            'timestamp' => date('Y-m-d H:i:s'),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
        
        // Save to database or log file
        return $this->saveProgressData($progressData);
    }
    
    /**
     * Get user tutorial progress
     */
    public function getUserProgress($userId, $tutorialId = null) {
        // This would typically query a database
        // For demo purposes, we'll return sample data
        return [
            'user_id' => $userId,
            'tutorials' => [
                'dashboard' => [
                    'status' => 'completed',
                    'completed_steps' => 5,
                    'total_steps' => 5,
                    'last_step' => 'step-5',
                    'completion_date' => '2024-01-15 10:30:00'
                ]
            ]
        ];
    }
    
    /**
     * Get tutorial analytics
     */
    public function getTutorialAnalytics($tutorialId = null, $dateRange = null) {
        // This would typically query a database for analytics data
        return [
            'total_starts' => 1250,
            'total_completions' => 890,
            'completion_rate' => 71.2,
            'average_time' => 180, // seconds
            'drop_off_points' => [
                'step-2' => 15,
                'step-4' => 25
            ],
            'popular_tutorials' => [
                'main' => 890,
                'advanced' => 360
            ]
        ];
    }
    
    /**
     * Save tutorial data (to database or file)
     */
    private function saveTutorialData() {
        // In a real application, you would save to database
        // For demo, we'll save to a JSON file
        $jsonData = json_encode($this->tutorialData, JSON_PRETTY_PRINT);
        return file_put_contents('tutorial-data.json', $jsonData) !== false;
    }
    
    /**
     * Save progress data (to database or log file)
     */
    private function saveProgressData($progressData) {
        // In a real application, you would save to database
        // For demo, we'll append to a log file
        $logEntry = json_encode($progressData) . "\n";
        return file_put_contents('tutorial-progress.log', $logEntry, FILE_APPEND | LOCK_EX) !== false;
    }
    
    /**
     * Generate tutorial JavaScript configuration
     */
    public function generateJavaScriptConfig($tutorialId = 'dashboard') {
        $tutorial = $this->getTutorial($tutorialId);
        $settings = $this->getSettings();
        
        if (!$tutorial) {
            return '';
        }
        
        $config = [
            'tutorial' => $tutorial,
            'settings' => $settings
        ];
        
        return 'window.tutorialConfig = ' . json_encode($config) . ';';
    }
    
    /**
     * Check if user should see tutorial
     */
    public function shouldShowTutorial($userId) {
        $userProgress = $this->getUserProgress($userId);
        $settings = $this->getSettings();
        
        // Check if user has completed main tutorial
        if (isset($userProgress['tutorials']['dashboard']['status']) && 
            $userProgress['tutorials']['dashboard']['status'] === 'completed') {
            return false;
        }
        
        // Check if auto-start is enabled
        return $settings['auto_start'];
    }
}

/**
 * API Endpoints for AJAX requests
 */

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $tutorialManager = new TutorialManager();
    $response = ['success' => false, 'data' => null, 'message' => ''];
    
    switch ($_POST['action']) {
        case 'get_tutorial':
            $tutorialId = $_POST['tutorial_id'] ?? 'dashboard';
            $tutorial = $tutorialManager->getTutorial($tutorialId);
            if ($tutorial) {
                $response['success'] = true;
                $response['data'] = $tutorial;
            } else {
                $response['message'] = 'Tutorial not found';
            }
            break;
            
        case 'track_progress':
            $userId = $_POST['user_id'] ?? session_id();
            $tutorialId = $_POST['tutorial_id'] ?? 'dashboard';
            $stepId = $_POST['step_id'] ?? '';
            $action = $_POST['step_action'] ?? 'viewed';
            
            $result = $tutorialManager->trackUserProgress($userId, $tutorialId, $stepId, $action);
            $response['success'] = $result;
            break;
            
        case 'get_analytics':
            $tutorialId = $_POST['tutorial_id'] ?? null;
            $analytics = $tutorialManager->getTutorialAnalytics($tutorialId);
            $response['success'] = true;
            $response['data'] = $analytics;
            break;
            
        case 'update_settings':
            $settings = json_decode($_POST['settings'], true);
            $result = $tutorialManager->updateSettings($settings);
            $response['success'] = $result;
            break;
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Example usage in your main page
function includeTutorialSystem($tutorialId = 'dashboard', $userId = null) {
    $tutorialManager = new TutorialManager();
    $userId = $userId ?? session_id();
    
    // Check if user should see tutorial
    if (!$tutorialManager->shouldShowTutorial($userId)) {
        return '';
    }
    
    // Generate JavaScript configuration
    $jsConfig = $tutorialManager->generateJavaScriptConfig($tutorialId);
    
    return "
    <script>
    {$jsConfig}
    
    // Override tutorial steps with server data
    if (window.tutorialConfig && window.tutorialInstance) {
        window.tutorialInstance.steps = window.tutorialConfig.tutorial.steps;
    }
    </script>
    ";
}

// Helper function to get tutorial data as JSON (for AJAX)
function getTutorialJSON($tutorialId = 'dashboard') {
    $tutorialManager = new TutorialManager();
    $tutorial = $tutorialManager->getTutorial($tutorialId);
    
    header('Content-Type: application/json');
    echo json_encode($tutorial);
}

// Example of how to use in your HTML pages:
/*
<?php
require_once 'tutorial-manager.php';
echo includeTutorialSystem('dashboard', $current_user_id);
?>
*/
?>