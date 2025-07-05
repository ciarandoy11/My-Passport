<?php
// Get the current page name
$current_page = basename($_SERVER['PHP_SELF']);
?>
<style>
    :root {
        --primary-blue: #3241FF;
        --dark-blue: #001B50;
        --white: #ffffff;
        --light-grey: #f5f5f5;
    }

    .nav-container {
        background: var(--dark-blue);
        padding: 1rem;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 1000;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .nav-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
        max-width: 1200px;
        margin: 0 auto;
    }

    .nav-logo {
        height: 40px;
        cursor: pointer;
    }

    .nav-links {
        display: flex;
        gap: 1rem;
        align-items: center;
        transform: translateX(-250px);
    }

    .nav-links a {
        color: var(--white);
        text-decoration: none;
        padding: 0.5rem 1rem;
        border-radius: 5px;
        transition: background-color 0.3s ease;
    }

    .nav-links a:hover,
    .nav-links a.active {
        background-color: var(--primary-blue);
    }

    .menu-toggle {
        display: none;
        background: none;
        border: none;
        color: var(--white);
        font-size: 1.5rem;
        cursor: pointer;
        padding: 0.5rem;
    }

    /* Main content styles */
    main {
        margin-top: 80px; /* Add space for the fixed navigation */
        padding: 20px;
        max-width: 1200px;
        margin-left: auto;
        margin-right: auto;
    }

    /* Mobile Navigation */
    @media (max-width: 768px) {
        .nav-container {
            padding: 0.5rem;
        }

        .nav-links {
            position: fixed;
            top: 0;
            left: -250px;
            height: 100vh;
            width: 250px;
            background: var(--dark-blue);
            flex-direction: column;
            padding: 2rem 1rem;
            transition: left 0.3s ease;
            z-index: 1001;
        }

        .nav-links.active {
            left: 0;
        }

        .menu-toggle {
            display: block;
        }

        .nav-links a {
            width: 100%;
            text-align: left;
        }

        main {
            margin-top: 60px;
            padding: 10px;
        }
    }
</style>

<!-- Navigation -->
<nav class="nav-container">
    <div class="nav-content">
        <img src='/images/logo-rectangle.png' alt="Logo" class="nav-logo" onclick='document.location="dashboard.php"'>
        <button class="menu-toggle" id="menuToggle">
            <i class="fa fa-bars"></i>
        </button>
        <div class="nav-links" id="navLinks">
            <a <?php echo $current_page == 'dashboard.php' ? 'class="active"' : ''; ?> href="dashboard.php">Home</a>
            <a <?php echo $current_page == 'timetable.php' ? 'class="active"' : ''; ?> href="timetable.php">Timetable</a>
            <a <?php echo $current_page == 'membership.php' ? 'class="active"' : ''; ?> href="membership.php">Finances</a>
            <a <?php echo $current_page == 'comingSoon.php' ? 'class="active"' : ''; ?> href="comingSoon.php">Fundraising</a>
            <a <?php echo $current_page == 'emails.php' ? 'class="active"' : ''; ?> href="emails.php">Emails Dashboard</a>
        </div>
    </div>
</nav>

<script>
    // Navigation Toggle
    const menuToggle = document.getElementById('menuToggle');
    const navLinks = document.getElementById('navLinks');
    const body = document.body;

    menuToggle.addEventListener('click', () => {
        navLinks.classList.toggle('active');
        menuToggle.querySelector('i').classList.toggle('fa-bars');
        menuToggle.querySelector('i').classList.toggle('fa-times');
    });

    // Close mobile menu when clicking outside
    document.addEventListener('click', (e) => {
        if (!navLinks.contains(e.target) && !menuToggle.contains(e.target)) {
            navLinks.classList.remove('active');
            menuToggle.querySelector('i').classList.add('fa-bars');
            menuToggle.querySelector('i').classList.remove('fa-times');
        }
    });
</script> 