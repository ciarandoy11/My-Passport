/**
 * Tutorial Configuration File
 * 
 * This file contains the tutorial steps and settings that can be easily
 * customized without modifying the main tutorial script.
 */

// Tutorial configuration object
const TUTORIAL_CONFIG = {
    // Global tutorial settings
    settings: {
        autoStart: true,           // Automatically start tutorial for new users
        autoStartDelay: 2000,       // Delay before auto-starting (milliseconds)
        showProgress: true,         // Show step progress (1/5, 2/5, etc.)
        allowSkip: true,           // Allow users to skip the tutorial
        keyboardNavigation: true,   // Enable keyboard navigation (arrows, ESC)
        highlightPadding: 10,      // Padding around highlighted elements
        animationDuration: 300,    // Animation duration in milliseconds
        scrollOffset: 100,         // Offset when scrolling to elements
        tooltipOffset: 20,         // Distance between tooltip and target element
    },
    
    // Tutorial themes
    themes: {
        default: {
            primaryColor: '#3498db',
            backgroundColor: '#ffffff',
            textColor: '#333333',
            borderRadius: '10px',
            shadowColor: 'rgba(0, 0, 0, 0.3)'
        },
        dark: {
            primaryColor: '#e74c3c',
            backgroundColor: '#2c3e50',
            textColor: '#ffffff',
            borderRadius: '8px',
            shadowColor: 'rgba(0, 0, 0, 0.5)'
        },
        minimal: {
            primaryColor: '#95a5a6',
            backgroundColor: '#ffffff',
            textColor: '#2c3e50',
            borderRadius: '4px',
            shadowColor: 'rgba(0, 0, 0, 0.1)'
        }
    },
    
    // Tutorial definitions
    tutorials: {
        // Main website tutorial
        user: {
            id: 'user',
            name: 'User Tutorial',
            description: 'Learn how to navigate and use our website',
            theme: 'default',
            steps: [
                {
                    id: 'welcome',
                    selector: '[data-tutorial="step-1"]',
                    title: 'Welcome! 👋',
                    description: 'Welcome to our website! This quick tutorial will show you around. Click "Next" to continue or "Skip" to explore on your own.',
                    position: 'bottom',
                    showArrow: true,
                    highlightElement: true,
                    beforeShow: null,
                    afterShow: null
                },
                {
                    id: 'navigation',
                    selector: '[data-tutorial="step-2"]',
                    title: 'Navigation Menu',
                    description: 'Use this navigation menu to access different sections of our website. Each link takes you to a specific area with relevant content.',
                    position: 'bottom',
                    showArrow: true,
                    highlightElement: true,
                    beforeShow: null,
                    afterShow: null
                },
                {
                    id: 'hero-section',
                    selector: '[data-tutorial="step-3"]',
                    title: 'Main Content Area',
                    description: 'This is our main content area where we showcase important information and announcements. Check here regularly for updates!',
                    position: 'bottom',
                    showArrow: true,
                    highlightElement: true,
                    beforeShow: null,
                    afterShow: null
                },
                {
                    id: 'call-to-action',
                    selector: '[data-tutorial="step-4"]',
                    title: 'Get Started Button',
                    description: 'Ready to begin? Click this button to start using our services. It will guide you through the next steps of your journey.',
                    position: 'top',
                    showArrow: true,
                    highlightElement: true,
                    beforeShow: null,
                    afterShow: null
                },
                {
                    id: 'features',
                    selector: '[data-tutorial="step-5"]',
                    title: 'Features Overview',
                    description: 'Here you can explore our key features and services. Each card provides detailed information about what we offer.',
                    position: 'top',
                    showArrow: true,
                    highlightElement: true,
                    beforeShow: null,
                    afterShow: null
                },
                {
                    id: 'email-signup',
                    selector: '[data-tutorial="step-6"]',
                    title: 'Subscribe to Newsletter',
                    description: 'Enter your email address to subscribe to our newsletter and receive updates. You must enter a valid email to proceed.',
                    position: 'top',
                    showArrow: true,
                    highlightElement: true,
                    validation: function() {
                        const emailInput = document.getElementById('newsletter-email');
                        const email = emailInput ? emailInput.value : '';
                        // Basic email validation regex
                        const emailRegex = /^[\w-]+(?:\.[\w-]+)*@(?:[\w-]+\.)+[a-zA-Z]{2,7}$/;
                        return emailRegex.test(email);
                    },
                    beforeShow: function() {
                        const emailInput = document.getElementById('newsletter-email');
                        if (emailInput) {
                            emailInput.addEventListener('input', tutorialInstance.validateCurrentStep.bind(tutorialInstance));
                        }
                    },
                    afterShow: null
                }
            ]
        },
        
        // Admin tutorial for administrative features
        admin: {
            id: 'admin',
            name: 'Admin Panel Tour',
            description: 'Learn how to use the administrative features',
            theme: 'dark',
            steps: [
                {
                    id: 'admin-dashboard',
                    selector: '[data-tutorial="admin-dashboard"]',
                    title: 'Admin Dashboard',
                    description: 'This is your admin dashboard where you can monitor site activity and manage content.',
                    position: 'bottom',
                    showArrow: true,
                    highlightElement: true
                },
                {
                    id: 'user-management',
                    selector: '[data-tutorial="user-management"]',
                    title: 'User Management',
                    description: 'Manage user accounts, permissions, and access levels from this section.',
                    position: 'right',
                    showArrow: true,
                    highlightElement: true
                },
                {
                    id: 'content-editor',
                    selector: '[data-tutorial="content-editor"]',
                    title: 'Content Editor',
                    description: 'Create and edit website content using our powerful content management tools.',
                    position: 'left',
                    showArrow: true,
                    highlightElement: true
                }
            ]
        },
        
        // Mobile-specific tutorial
        mobile: {
            id: 'mobile',
            name: 'Mobile Experience',
            description: 'Learn how to use our website on mobile devices',
            theme: 'minimal',
            steps: [
                {
                    id: 'mobile-menu',
                    selector: '[data-tutorial="mobile-menu"]',
                    title: 'Mobile Menu',
                    description: 'Tap the menu icon to access navigation options on mobile devices.',
                    position: 'bottom',
                    showArrow: true,
                    highlightElement: true
                },
                {
                    id: 'touch-gestures',
                    selector: '[data-tutorial="touch-area"]',
                    title: 'Touch Interactions',
                    description: 'Use touch gestures like swipe and tap to interact with content on mobile.',
                    position: 'top',
                    showArrow: true,
                    highlightElement: true
                }
            ]
        },
        
        dashboard: {
            id: 'dashboard',
            name: 'Dashboard Tutorial',
            description: 'Learn how to use your dashboard',
            theme: 'default',
            steps: [
                {
                    id: 'dashboard-header',
                    selector: '[data-tutorial="step-1"]',
                    title: 'Info',
                    description: 'This is the info section of your dashboard.',
                    position: 'top'
                },
                {
                    id: 'dashboard-action',
                    selector: '[data-tutorial="step-2"]',
                    title: 'Edit Info',
                    description: 'This is the edit info section of your dashboard.',
                    position: 'top'
                },
                {
                    id: 'dashboard-widgets',
                    selector: '[data-tutorial="step-3"]',
                    title: 'Session Details',
                    description: 'This is the session details section of your dashboard.',
                    position: 'top'
                },
                {
                    id: 'dashboard-nav',
                    selector: '[data-tutorial="step-4"]',
                    title: 'Use Nav Bar',
                    description: 'This is the nav bar section of your dashboard.',
                    position: 'top'
                }
            ]
        }
    },
    
    // Custom messages and text
    messages: {
        skipConfirmation: 'Are you sure you want to skip the tutorial?',
        completionMessage: 'Congratulations! You\'ve completed the tutorial. 🎉',
        errorMessage: 'Oops! Something went wrong. Please try again.',
        nextButton: 'Next',
        previousButton: 'Previous',
        skipButton: 'Skip Tutorial',
        finishButton: 'Finish',
        closeButton: 'Close'
    },
    
    // Analytics and tracking configuration
    analytics: {
        enabled: true,
        trackingEvents: {
            tutorialStarted: 'tutorial_started',
            stepViewed: 'tutorial_step_viewed',
            stepCompleted: 'tutorial_step_completed',
            tutorialCompleted: 'tutorial_completed',
            tutorialSkipped: 'tutorial_skipped',
            tutorialError: 'tutorial_error'
        },
        // Custom tracking function
        trackEvent: function(eventName, data) {
            // Example: Google Analytics 4
            if (typeof gtag !== 'undefined') {
                gtag('event', eventName, {
                    event_category: 'tutorial',
                    event_label: data.tutorialId || 'unknown',
                    custom_parameter_1: data.stepId || '',
                    custom_parameter_2: data.action || ''
                });
            }
            
            // Example: Custom analytics endpoint
            if (this.enabled) {
                fetch('/api/tutorial-analytics', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        event: eventName,
                        timestamp: new Date().toISOString(),
                        ...data
                    })
                }).catch(error => {
                    console.warn('Analytics tracking failed:', error);
                });
            }
        }
    }
};

// Helper functions for tutorial configuration
const TutorialConfigHelper = {
    
    /**
     * Get tutorial by ID
     */
    getTutorial: function(tutorialId) {
        return TUTORIAL_CONFIG.tutorials[tutorialId] || null;
    },
    
    /**
     * Get tutorial for current device type
     */
    getTutorialForDevice: function() {
        const isMobile = window.innerWidth <= 768;
        return isMobile ? this.getTutorial('mobile') : this.getTutorial('main');
    },
    
    /**
     * Get tutorial based on user role
     */
    getTutorialForUser: function(userRole) {
        switch(userRole) {
            case 'admin':
                return this.getTutorial('admin');
            case 'user':
                return this.getTutorial('main');
            default:
                return this.getTutorial('main');
        }
    },
    
    /**
     * Apply theme to tutorial overlay
     */
    applyTheme: function(themeName) {
        const theme = TUTORIAL_CONFIG.themes[themeName];
        if (!theme) return;
        
        const overlay = document.getElementById('tutorial-overlay');
        if (!overlay) return;
        
        const tooltip = overlay.querySelector('.tutorial-tooltip');
        if (tooltip) {
            tooltip.style.backgroundColor = theme.backgroundColor;
            tooltip.style.color = theme.textColor;
            tooltip.style.borderRadius = theme.borderRadius;
            tooltip.style.boxShadow = `0 10px 30px ${theme.shadowColor}`;
        }
        
        // Apply theme to buttons
        const buttons = overlay.querySelectorAll('.tutorial-controls button');
        buttons.forEach(button => {
            if (button.id === 'tutorial-next') {
                button.style.backgroundColor = theme.primaryColor;
            }
        });
    },
    
    /**
     * Validate tutorial configuration
     */
    validateTutorial: function(tutorialId) {
        const tutorial = this.getTutorial(tutorialId);
        if (!tutorial) {
            console.error(`Tutorial "${tutorialId}" not found`);
            return false;
        }
        
        if (!tutorial.steps || tutorial.steps.length === 0) {
            console.error(`Tutorial "${tutorialId}" has no steps`);
            return false;
        }
        
        // Validate each step
        for (let i = 0; i < tutorial.steps.length; i++) {
            const step = tutorial.steps[i];
            if (!step.selector || !step.title || !step.description) {
                console.error(`Tutorial "${tutorialId}" step ${i} is missing required properties`);
                return false;
            }
        }
        
        return true;
    },
    
    /**
     * Create custom tutorial step
     */
    createStep: function(config) {
        return {
            id: config.id || 'custom-step',
            selector: config.selector,
            title: config.title,
            description: config.description,
            position: config.position || 'bottom',
            showArrow: config.showArrow !== false,
            highlightElement: config.highlightElement !== false,
            beforeShow: config.beforeShow || null,
            afterShow: config.afterShow || null
        };
    }
};

// Export configuration for use in other files
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { TUTORIAL_CONFIG, TutorialConfigHelper };
}

