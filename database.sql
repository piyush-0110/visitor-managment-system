CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'security', 'host') NOT NULL
);

CREATE TABLE IF NOT EXISTS visitors (
    visitor_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100),
    organization VARCHAR(100),
    is_blacklisted TINYINT(1) DEFAULT 0
);

CREATE TABLE IF NOT EXISTS appointments (
    appointment_id INT AUTO_INCREMENT PRIMARY KEY,
    visitor_id INT NOT NULL,
    host_id INT NOT NULL,
    visit_date DATETIME NOT NULL,
    purpose VARCHAR(255) NOT NULL,
    status ENUM('scheduled', 'completed', 'cancelled') DEFAULT 'scheduled',
    FOREIGN KEY (visitor_id) REFERENCES visitors(visitor_id),
    FOREIGN KEY (host_id) REFERENCES users(user_id)
);

CREATE TABLE IF NOT EXISTS visits (
    visit_id INT AUTO_INCREMENT PRIMARY KEY,
    visitor_id INT NOT NULL,
    host_id INT NOT NULL,
    purpose VARCHAR(255) NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'checked_in', 'checked_out') DEFAULT 'pending',
    qr_code_path VARCHAR(255) NULL,
    check_in_time DATETIME NULL,
    check_out_time DATETIME NULL,
    approved_by INT NULL,
    FOREIGN KEY (visitor_id) REFERENCES visitors(visitor_id),
    FOREIGN KEY (host_id) REFERENCES users(user_id),
    FOREIGN KEY (approved_by) REFERENCES users(user_id)
);

CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

CREATE TABLE IF NOT EXISTS audit_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(255) NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Default accounts (Password for all: password)
INSERT IGNORE INTO users (name, email, password_hash, role) VALUES
('System Admin', 'admin@vms.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('Main Gate Security', 'security@vms.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'security'),
('Dr. R. Sharma', 'sharma@college.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'host');