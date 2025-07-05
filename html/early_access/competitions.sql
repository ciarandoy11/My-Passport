-- Select the database
USE pod_rota;

-- Competitions database structure

-- Meets table
CREATE TABLE IF NOT EXISTS meets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    venue VARCHAR(255) NOT NULL,
    closing_date DATE NOT NULL,
    meet_file_path VARCHAR(255),
    meet_file_type ENUM('hyv', 'hy3', 'ev3', 'cl2'),
    status ENUM('upcoming', 'open', 'closed', 'completed') DEFAULT 'upcoming',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Events table
CREATE TABLE IF NOT EXISTS meet_events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    meet_id INT NOT NULL,
    event_number VARCHAR(10) NOT NULL,
    event_name VARCHAR(255) NOT NULL,
    age_group VARCHAR(50),
    gender ENUM('male', 'female', 'mixed'),
    distance INT,
    stroke VARCHAR(50),
    FOREIGN KEY (meet_id) REFERENCES meets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Athletes table (if it doesn't exist)
CREATE TABLE IF NOT EXISTS athletes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    date_of_birth DATE,
    gender ENUM('male', 'female'),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Entries table
CREATE TABLE IF NOT EXISTS meet_entries (
    id INT PRIMARY KEY AUTO_INCREMENT,
    meet_id INT NOT NULL,
    athlete_id INT NOT NULL,
    event_id INT NOT NULL,
    entry_time DECIMAL(10,2),
    seed_time DECIMAL(10,2),
    status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
    entry_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (meet_id) REFERENCES meets(id) ON DELETE CASCADE,
    FOREIGN KEY (athlete_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES meet_events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Results table
CREATE TABLE IF NOT EXISTS meet_results (
    id INT PRIMARY KEY AUTO_INCREMENT,
    entry_id INT NOT NULL,
    heat_number INT,
    lane_number INT,
    final_time DECIMAL(10,2),
    placement INT,
    dq_reason VARCHAR(255),
    result_type ENUM('heat', 'final') DEFAULT 'heat',
    result_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (entry_id) REFERENCES meet_entries(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Manual times table (for coaches/admins to input times)
CREATE TABLE IF NOT EXISTS manual_times (
    id INT PRIMARY KEY AUTO_INCREMENT,
    athlete_id INT NOT NULL,
    event_id INT NOT NULL,
    time DECIMAL(10,2) NOT NULL,
    meet_id INT,
    date_achieved DATE NOT NULL,
    verified_by INT UNSIGNED,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (athlete_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES meet_events(id) ON DELETE CASCADE,
    FOREIGN KEY (meet_id) REFERENCES meets(id) ON DELETE SET NULL,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4; 