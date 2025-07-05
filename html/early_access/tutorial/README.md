# Website Tutorial Overlay System

A comprehensive tutorial overlay system built with PHP, HTML, CSS, and JavaScript that guides users through your website features with interactive step-by-step tutorials.

## Features

- **Interactive Step-by-Step Tutorials**: Guide users through your website with highlighted elements and informative tooltips
- **Responsive Design**: Works seamlessly on desktop and mobile devices
- **Keyboard Navigation**: Support for arrow keys and ESC key
- **Progress Tracking**: Visual progress indicator and backend analytics
- **Customizable Positioning**: Smart tooltip positioning that adapts to viewport
- **PHP Backend Management**: Server-side tutorial configuration and user progress tracking
- **Analytics Integration**: Track tutorial completion rates and user behavior
- **Flexible Configuration**: Easy to customize and extend

## Files Overview

- `index.html` - Demo page showing the tutorial system in action
- `tutorial-styles.css` - Complete CSS styling for the overlay system
- `tutorial-script.js` - JavaScript functionality and tutorial management
- `tutorial-manager.php` - PHP backend for tutorial data and analytics
- `README.md` - This documentation file

## Quick Start

1. **Include the files in your project:**
   ```html
   <link rel="stylesheet" href="tutorial-styles.css">
   <script src="tutorial-script.js"></script>
   ```

2. **Add tutorial data attributes to your HTML elements:**
   ```html
   <div data-tutorial="step-1">Your content here</div>
   <button data-tutorial="step-2">Important button</button>
   ```

3. **Add the tutorial overlay HTML:**
   ```html
   <div id="tutorial-overlay" class="tutorial-overlay hidden">
       <div class="tutorial-backdrop"></div>
       <div class="tutorial-tooltip">
           <div class="tutorial-content">
               <h4 id="tutorial-title">Tutorial Step</h4>
               <p id="tutorial-description">Tutorial description goes here.</p>
               <div class="tutorial-controls">
                   <button id="tutorial-prev" onclick="previousStep()">Previous</button>
                   <span id="tutorial-progress">1 / 5</span>
                   <button id="tutorial-next" onclick="nextStep()">Next</button>
                   <button id="tutorial-skip" onclick="skipTutorial()">Skip Tutorial</button>
               </div>
           </div>
           <div class="tutorial-arrow"></div>
       </div>
   </div>
   ```

4. **Initialize the tutorial system:**
   ```javascript
   document.addEventListener('DOMContentLoaded', function() {
       initializeTutorial();
   });
   ```

5. **Start the tutorial:**
   ```javascript
   startTutorial();
   ```

## Detailed Integration Guide

### 1. HTML Setup

Add `data-tutorial` attributes to elements you want to highlight:

```html
<header data-tutorial="step-1">
    <nav data-tutorial="step-2">
        <ul>
            <li><a href="#home">Home</a></li>
            <li><a href="#about">About</a></li>
        </ul>
    </nav>
</header>

<main>
    <section data-tutorial="step-3">
        <h1>Welcome</h1>
        <button data-tutorial="step-4">Get Started</button>
    </section>
</main>
```

### 2. JavaScript Configuration

Customize tutorial steps in the JavaScript:

```javascript
// In tutorial-script.js, modify the initializeSteps() method:
initializeSteps() {
    this.steps = [
        {
            selector: '[data-tutorial="step-1"]',
            title: 'Website Header',
            description: 'This is your main header area.',
            position: 'bottom'
        },
        {
            selector: '[data-tutorial="step-2"]',
            title: 'Navigation Menu',
            description: 'Use this menu to navigate the site.',
            position: 'bottom'
        },
        // Add more steps...
    ];
}
```

### 3. PHP Backend Integration

Include the PHP tutorial manager in your application:

```php
<?php
require_once 'tutorial-manager.php';

// Initialize tutorial manager
$tutorialManager = new TutorialManager();

// Check if user should see tutorial
$userId = $_SESSION['user_id'] ?? session_id();
if ($tutorialManager->shouldShowTutorial($userId)) {
    echo $tutorialManager->generateJavaScriptConfig('main');
}
?>
```

### 4. AJAX Integration

Track tutorial progress with AJAX:

```javascript
// Track when user completes a step
function trackTutorialProgress(stepId, action) {
    fetch('tutorial-manager.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'track_progress',
            user_id: getCurrentUserId(),
            tutorial_id: 'main',
            step_id: stepId,
            step_action: action
        })
    });
}
```

## Customization Options

### CSS Customization

Modify the appearance by updating `tutorial-styles.css`:

```css
/* Change tooltip colors */
.tutorial-tooltip {
    background: #your-color;
    border: 2px solid #your-border-color;
}

/* Customize highlight effect */
.tutorial-highlight {
    box-shadow: 0 0 0 4px #your-highlight-color;
}

/* Adjust button styles */
.tutorial-controls button {
    background: #your-button-color;
    color: #your-text-color;
}
```

### JavaScript Customization

Add custom functionality:

```javascript
// Add custom event tracking
tutorialInstance.trackEvent = function(eventName, data) {
    // Your analytics code here
    gtag('event', eventName, data);
};

// Add custom step validation
tutorialInstance.validateStep = function(stepIndex) {
    // Return true if step should be shown
    return true;
};

// Add custom animations
tutorialInstance.showCustomAnimation = function() {
    // Your animation code here
};
```

### PHP Customization

Extend the tutorial manager:

```php
class CustomTutorialManager extends TutorialManager {
    
    public function getPersonalizedTutorial($userId) {
        // Return personalized tutorial based on user data
        $userProfile = $this->getUserProfile($userId);
        return $this->customizeTutorialForUser($userProfile);
    }
    
    public function sendTutorialReminder($userId) {
        // Send email reminder to complete tutorial
        $this->emailService->sendTutorialReminder($userId);
    }
}
```

## Advanced Features

### Dynamic Tutorial Steps

Add steps dynamically based on page content:

```javascript
// Add a step for dynamically loaded content
function addDynamicStep(selector, title, description) {
    const newStep = {
        selector: selector,
        title: title,
        description: description,
        position: 'auto'
    };
    
    if (tutorialInstance) {
        tutorialInstance.addStep(newStep);
    }
}
```

### Conditional Steps

Show different steps based on user conditions:

```javascript
// Show different tutorials for different user types
function initializeConditionalTutorial() {
    const userType = getUserType(); // admin, user, guest
    
    let tutorialSteps = [];
    
    if (userType === 'admin') {
        tutorialSteps = getAdminTutorialSteps();
    } else if (userType === 'user') {
        tutorialSteps = getUserTutorialSteps();
    } else {
        tutorialSteps = getGuestTutorialSteps();
    }
    
    tutorialInstance.steps = tutorialSteps;
}
```

### Multi-language Support

Add internationalization:

```php
class MultiLanguageTutorialManager extends TutorialManager {
    
    public function getTutorial($tutorialId = 'main', $language = 'en') {
        $tutorial = parent::getTutorial($tutorialId);
        
        if ($tutorial && $language !== 'en') {
            $tutorial = $this->translateTutorial($tutorial, $language);
        }
        
        return $tutorial;
    }
    
    private function translateTutorial($tutorial, $language) {
        // Load translations and apply them
        $translations = $this->loadTranslations($language);
        
        foreach ($tutorial['steps'] as &$step) {
            $step['title'] = $translations[$step['id']]['title'] ?? $step['title'];
            $step['description'] = $translations[$step['id']]['description'] ?? $step['description'];
        }
        
        return $tutorial;
    }
}
```

## API Reference

### JavaScript Methods

- `startTutorial()` - Start the tutorial from the beginning
- `nextStep()` - Move to the next tutorial step
- `previousStep()` - Move to the previous tutorial step
- `skipTutorial()` - Skip the entire tutorial
- `showTutorialStep(index)` - Jump to a specific step
- `addTutorialStep(step)` - Add a new tutorial step

### PHP Methods

- `getTutorial($id)` - Get tutorial data by ID
- `trackUserProgress($userId, $tutorialId, $stepId, $action)` - Track user progress
- `getTutorialAnalytics($tutorialId)` - Get tutorial analytics
- `shouldShowTutorial($userId)` - Check if user should see tutorial

### CSS Classes

- `.tutorial-overlay` - Main overlay container
- `.tutorial-tooltip` - Tooltip container
- `.tutorial-highlight` - Highlighted element style
- `.tutorial-controls` - Button controls container

## Browser Support

- Chrome 60+
- Firefox 55+
- Safari 12+
- Edge 79+
- Mobile browsers (iOS Safari, Chrome Mobile)

## Performance Considerations

- Tutorial overlay is hidden by default and only activated when needed
- CSS animations use GPU acceleration for smooth performance
- JavaScript uses event delegation to minimize memory usage
- PHP backend can be cached for better performance

## Security Notes

- Always validate and sanitize user input in PHP backend
- Use CSRF protection for tutorial management endpoints
- Implement rate limiting for analytics tracking
- Sanitize tutorial content to prevent XSS attacks

## Troubleshooting

### Common Issues

1. **Tutorial not starting**: Check that `initializeTutorial()` is called after DOM is loaded
2. **Elements not highlighting**: Verify `data-tutorial` attributes are correctly set
3. **Tooltip positioning issues**: Ensure target elements are visible and have proper dimensions
4. **PHP errors**: Check file permissions and PHP version compatibility

### Debug Mode

Enable debug mode for troubleshooting:

```javascript
// Add to tutorial-script.js
const DEBUG_MODE = true;

if (DEBUG_MODE) {
    console.log('Tutorial Debug Mode Enabled');
    // Add debug logging throughout the code
}
```

## License

This tutorial overlay system is provided as example code. Feel free to modify and use it in your projects.

## Contributing

To improve this tutorial system:

1. Add new positioning algorithms for better tooltip placement
2. Implement more animation effects
3. Add accessibility features (ARIA labels, screen reader support)
4. Create additional themes and styling options
5. Add integration examples for popular frameworks

## Support

For questions and support:

1. Check the troubleshooting section above
2. Review the example implementation in `index.html`
3. Test with the provided demo files
4. Customize based on your specific needs

---

**Happy tutorializing!** 🎓

