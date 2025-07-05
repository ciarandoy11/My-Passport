// Tutorial System JavaScript

class TutorialOverlay {
    constructor() {
        this.currentStep = 0;
        this.isActive = false;
        this.steps = [];
        this.overlay = null;
        this.tooltip = null;
        this.backdrop = null;
        this.arrow = null;
        
        // Initialize tutorial steps
        this.initializeSteps();
        this.bindEvents();
    }

    initializeSteps() {
        // This will be overridden by tutorial-config.js
        this.steps = [];
    }

    bindEvents() {
        // Bind keyboard events
        document.addEventListener("keydown", (e) => {
            if (!this.isActive) return;
            
            switch(e.key) {
                case "Escape":
                    this.skipTutorial();
                    break;
                case "ArrowRight":
                    this.nextStep();
                    break;
                case "ArrowLeft":
                    this.previousStep();
                    break;
            }
        });

        // Prevent clicks on backdrop from closing tutorial
        document.addEventListener("click", (e) => {
            if (this.isActive && e.target === this.backdrop) {
                e.preventDefault();
                e.stopPropagation();
            }
        });
    }

    start() {
        if (this.isActive) return;
        
        this.isActive = true;
        this.currentStep = 0;
        this.createOverlay();
        this.showStep(this.currentStep);
        
        // Track tutorial start (you can send this to your analytics)
        this.trackEvent("tutorial_started");
    }

    createOverlay() {
        // Create overlay elements
        this.overlay = document.getElementById("tutorial-overlay");
        this.tooltip = this.overlay.querySelector(".tutorial-tooltip");
        this.backdrop = this.overlay.querySelector(".tutorial-backdrop");
        this.arrow = this.overlay.querySelector(".tutorial-arrow");
        
        // Show overlay
        this.overlay.classList.remove("hidden");
        
        // Trigger animation
        setTimeout(() => {
            this.overlay.classList.add("active");
        }, 10);
    }

    showStep(stepIndex) {
        if (stepIndex < 0 || stepIndex >= this.steps.length) return;
        
        const step = this.steps[stepIndex];
        const targetElement = document.querySelector(step.selector);
        
        if (!targetElement) {
            console.warn(`Tutorial step ${stepIndex}: Element not found for selector "${step.selector}"`);
            return;
        }

        // Remove previous highlights
        this.removeHighlights();
        
        // Highlight current element
        targetElement.classList.add("tutorial-highlight");
        
        // Update tooltip content
        document.getElementById("tutorial-title").textContent = step.title;
        document.getElementById("tutorial-description").textContent = step.description;
        document.getElementById("tutorial-progress").textContent = `${stepIndex + 1} / ${this.steps.length}`;
        
        // Update navigation buttons
        const prevBtn = document.getElementById("tutorial-prev");
        const nextBtn = document.getElementById("tutorial-next");
        
        prevBtn.disabled = stepIndex === 0;
        nextBtn.textContent = stepIndex === this.steps.length - 1 ? "Finish" : "Next";

        // Enable/disable next button based on validation
        this.validateCurrentStep();
        
        // Position tooltip
        this.positionTooltip(targetElement, step.position);
        
        // Scroll element into view if needed
        this.scrollToElement(targetElement);
        
        // Track step view
        this.trackEvent("tutorial_step_viewed", { step: stepIndex + 1 });
    }

    validateCurrentStep() {
        const nextBtn = document.getElementById("tutorial-next");
        const currentStepData = this.steps[this.currentStep];

        if (currentStepData && currentStepData.validation) {
            const isValid = currentStepData.validation();
            nextBtn.disabled = !isValid;
        } else {
            nextBtn.disabled = false; // No validation required, enable by default
        }
    }

    positionTooltip(targetElement, position = "bottom") {
        const rect = targetElement.getBoundingClientRect();
        const tooltipRect = this.tooltip.getBoundingClientRect();
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;
        
        let top, left;
        
        // Reset arrow classes
        this.arrow.className = "tutorial-arrow";
        
        switch(position) {
            case "top":
                top = rect.top - tooltipRect.height - 20;
                left = rect.left + (rect.width / 2) - (tooltipRect.width / 2);
                this.arrow.classList.add("bottom");
                break;
                
            case "bottom":
                top = rect.bottom + 20;
                left = rect.left + (rect.width / 2) - (tooltipRect.width / 2);
                this.arrow.classList.add("top");
                break;
                
            case "left":
                top = rect.top + (rect.height / 2) - (tooltipRect.height / 2);
                left = rect.left - tooltipRect.width - 20;
                this.arrow.classList.add("right");
                break;
                
            case "right":
                top = rect.top + (rect.height / 2) - (tooltipRect.height / 2);
                left = rect.right + 20;
                this.arrow.classList.add("left");
                break;
                
            default:
                top = rect.bottom + 20;
                left = rect.left + (rect.width / 2) - (tooltipRect.width / 2);
                this.arrow.classList.add("top");
        }
        
        // Ensure tooltip stays within viewport
        if (left < 10) {
            left = 10;
        } else if (left + tooltipRect.width > viewportWidth - 10) {
            left = viewportWidth - tooltipRect.width - 10;
        }
        
        if (top < 10) {
            top = 10;
        } else if (top + tooltipRect.height > viewportHeight - 10) {
            top = viewportHeight - tooltipRect.height - 10;
        }
        
        // Apply position
        this.tooltip.style.top = `${top}px`;
        this.tooltip.style.left = `${left}px`;
    }

    scrollToElement(element) {
        const rect = element.getBoundingClientRect();
        const viewportHeight = window.innerHeight;
        
        // Check if element is not fully visible
        if (rect.top < 100 || rect.bottom > viewportHeight - 100) {
            element.scrollIntoView({
                behavior: "smooth",
                block: "center"
            });
        }
    }

    nextStep() {
        const nextBtn = document.getElementById("tutorial-next");
        if (nextBtn.disabled) {
            return; // Prevent moving if button is disabled
        }

        if (this.currentStep < this.steps.length - 1) {
            this.currentStep++;
            this.showStep(this.currentStep);
        } else {
            this.finish();
        }
    }

    previousStep() {
        if (this.currentStep > 0) {
            this.currentStep--;
            this.showStep(this.currentStep);
        }
    }

    skipTutorial() {
        this.trackEvent("tutorial_skipped", { step: this.currentStep + 1 });
        this.finish();
    }

    finish() {
        this.trackEvent("tutorial_completed");
        this.close();
    }

    close() {
        if (!this.isActive) return;
        
        this.isActive = false;
        this.removeHighlights();
        
        // Animate out
        this.overlay.classList.remove("active");
        
        // Hide overlay after animation
        setTimeout(() => {
            this.overlay.classList.add("hidden");
        }, 300);
    }

    removeHighlights() {
        const highlighted = document.querySelectorAll(".tutorial-highlight");
        highlighted.forEach(el => el.classList.remove("tutorial-highlight"));
    }

    trackEvent(eventName, data = {}) {
        // You can integrate with your analytics service here
        console.log("Tutorial Event:", eventName, data);
        
        // Example: Send to Google Analytics
        // if (typeof gtag !== "undefined") {
        //     gtag("event", eventName, {
        //         event_category: "tutorial",
        //         ...data
        //     });
        // }
        
        // Example: Send to your backend
        // fetch("/api/tutorial-analytics", {
        //     method: "POST",
        //     headers: { "Content-Type": "application/json" },
        //     body: JSON.stringify({ event: eventName, ...data })
        // });
    }

    // Public methods for external control
    goToStep(stepIndex) {
        if (stepIndex >= 0 && stepIndex < this.steps.length) {
            this.currentStep = stepIndex;
            this.showStep(this.currentStep);
        }
    }

    addStep(step) {
        this.steps.push(step);
    }

    removeStep(index) {
        if (index >= 0 && index < this.steps.length) {
            this.steps.splice(index, 1);
        }
    }
}

// Global tutorial instance
let tutorialInstance = null;

// Initialize tutorial system
function initializeTutorial() {
    tutorialInstance = new TutorialOverlay();
    
    // Load steps from tutorial-config.js
    if (typeof TUTORIAL_CONFIG !== "undefined" && TUTORIAL_CONFIG.tutorials.main) {
        tutorialInstance.steps = TUTORIAL_CONFIG.tutorials.main.steps;
    }

    // Check if user has seen tutorial before
    const hasSeenTutorial = localStorage.getItem("tutorial_completed");
    
    // Optionally auto-start tutorial for new users
    // if (!hasSeenTutorial) {
    //     setTimeout(() => startTutorial(), 2000);
    // }
}

// Global functions for button controls
function startTutorial() {
    if (tutorialInstance) {
        tutorialInstance.start();
    }
}

function nextStep() {
    if (tutorialInstance) {
        tutorialInstance.nextStep();
    }
}

function previousStep() {
    if (tutorialInstance) {
        tutorialInstance.previousStep();
    }
}

function skipTutorial() {
    if (tutorialInstance) {
        tutorialInstance.skipTutorial();
        // Mark tutorial as completed
        localStorage.setItem("tutorial_completed", "true");
    }
}

// Utility functions for advanced usage
function showTutorialStep(stepIndex) {
    if (tutorialInstance) {
        tutorialInstance.goToStep(stepIndex);
    }
}

function addTutorialStep(step) {
    if (tutorialInstance) {
        tutorialInstance.addStep(step);
    }
}

// Example of how to add dynamic tutorial steps
function addDynamicTutorialStep() {
    const newStep = {
        selector: "#dynamic-element",
        title: "Dynamic Feature",
        description: "This is a dynamically added tutorial step.",
        position: "right"
    };
    
    addTutorialStep(newStep);
}

// Handle window resize
window.addEventListener("resize", () => {
    if (tutorialInstance && tutorialInstance.isActive) {
        // Reposition tooltip on resize
        setTimeout(() => {
            tutorialInstance.showStep(tutorialInstance.currentStep);
        }, 100);
    }
});

// Event listener for email input to enable/disable next button
document.addEventListener("input", (event) => {
    if (event.target.id === "newsletter-email" && tutorialInstance && tutorialInstance.isActive) {
        tutorialInstance.validateCurrentStep();
    }
});


