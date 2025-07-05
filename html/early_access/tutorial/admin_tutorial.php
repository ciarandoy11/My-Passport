<?php
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
                'main' => [
                    'id' => 'main',
                    'name' => 'Main Website Tutorial',
                    'description' => 'Learn how to navigate our website',
                    'steps' => [
                        [
                            'id' => 'step-1',
                            'selector' => '[data-tutorial="step-1"]',
                            'title' => 'Welcome to the Tutorial!',
                            'description' => 'This is your website logo. Users will click here to return to the homepage.',
                            'position' => 'bottom',
                            'delay' => 0
                        ],
                        [
                            'id' => 'step-2',
                            'selector' => '[data-tutorial="step-2"]',
                            'title' => 'Navigation Menu',
                            'description' => 'This is your main navigation menu. Users can access different sections of your website from here.',
                            'position' => 'bottom',
                            'delay' => 0
                        ],
                        [
                            'id' => 'step-3',
                            'selector' => '[data-tutorial="step-3"]',
                            'title' => 'Hero Section',
                            'description' => 'This is your hero section - the first thing visitors see. Make it compelling!',
                            'position' => 'bottom',
                            'delay' => 0
                        ],
                        [
                            'id' => 'step-4',
                            'selector' => '[data-tutorial="step-4"]',
                            'title' => 'Call-to-Action Button',
                            'description' => 'This is your main call-to-action button. It should encourage users to take the next step.',
                            'position' => 'top',
                            'delay' => 0
                        ],
                        [
                            'id' => 'step-5',
                            'selector' => '[data-tutorial="step-5"]',
                            'title' => 'Features Section',
                            'description' => 'This section showcases your key features or services. Keep it clear and concise.',
                            'position' => 'top',
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
                'auto_start' => false,
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
    public function getTutorial($tutorialId = 'main') {
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
                'main' => [
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
    public function generateJavaScriptConfig($tutorialId = 'main') {
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
        if (isset($userProgress['tutorials']['main']['status']) && 
            $userProgress['tutorials']['main']['status'] === 'completed') {
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
            $tutorialId = $_POST['tutorial_id'] ?? 'main';
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
            $tutorialId = $_POST['tutorial_id'] ?? 'main';
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
function includeTutorialSystem($tutorialId = 'main', $userId = null) {
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
function getTutorialJSON($tutorialId = 'main') {
    $tutorialManager = new TutorialManager();
    $tutorial = $tutorialManager->getTutorial($tutorialId);
    
    header('Content-Type: application/json');
    echo json_encode($tutorial);
}

// Example of how to use in your HTML pages:
/*
<?php
require_once 'tutorial-manager.php';
echo includeTutorialSystem('main', $current_user_id);
?>
*/
?>

