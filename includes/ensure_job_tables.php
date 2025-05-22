<?php
/**
 * Create job-related tables if they don't exist
 */
require_once __DIR__ . '/../config/db.php';

// Check if jobs table exists and create it if it doesn't
try {
    $jobsCheckSql = "SHOW TABLES LIKE 'jobs'";
    $jobsResult = $conn->query($jobsCheckSql);
    
    if ($jobsResult->num_rows == 0) {
        // Table doesn't exist, create it
        $createJobsTable = "
        CREATE TABLE IF NOT EXISTS jobs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            requirements TEXT NOT NULL,
            location VARCHAR(255) NOT NULL,
            compensation VARCHAR(255) NOT NULL,
            category VARCHAR(100) NOT NULL,
            status ENUM('open', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'open',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        
        if ($conn->query($createJobsTable)) {
            error_log("Jobs table created successfully");
            
            // Create indexes for better performance
            $createJobsIndexes = "
            ALTER TABLE jobs 
            ADD INDEX idx_user_id (user_id),
            ADD INDEX idx_status (status),
            ADD INDEX idx_category (category),
            ADD INDEX idx_created (created_at),
            ADD INDEX idx_expires (expires_at);
            ";
            
            try {
                $conn->query($createJobsIndexes);
                error_log("Jobs indexes created successfully");
            } catch (Exception $e) {
                error_log("Error creating jobs indexes: " . $e->getMessage());
            }
        } else {
            error_log("Error creating jobs table: " . $conn->error);
        }
    }
} catch (Exception $e) {
    error_log("Error checking jobs table: " . $e->getMessage());
}

// Check if job_applications table exists and create it if it doesn't
try {
    $appsCheckSql = "SHOW TABLES LIKE 'job_applications'";
    $appsResult = $conn->query($appsCheckSql);
    
    if ($appsResult->num_rows == 0) {
        // Table doesn't exist, create it
        $createAppsTable = "
        CREATE TABLE IF NOT EXISTS job_applications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            job_id INT NOT NULL,
            user_id INT NOT NULL,
            cover_letter TEXT NOT NULL,
            bid_amount DECIMAL(10,2) NULL,
            status ENUM('pending', 'accepted', 'rejected', 'withdrawn') NOT NULL DEFAULT 'pending',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        
        if ($conn->query($createAppsTable)) {
            error_log("Job applications table created successfully");
            
            // Create indexes for better performance
            $createAppsIndexes = "
            ALTER TABLE job_applications 
            ADD INDEX idx_job_id (job_id),
            ADD INDEX idx_user_id (user_id),
            ADD INDEX idx_status (status),
            ADD INDEX idx_created (created_at),
            ADD UNIQUE INDEX idx_job_user (job_id, user_id);
            ";
            
            try {
                $conn->query($createAppsIndexes);
                error_log("Job applications indexes created successfully");
            } catch (Exception $e) {
                error_log("Error creating job applications indexes: " . $e->getMessage());
            }
        } else {
            error_log("Error creating job applications table: " . $conn->error);
        }
    }
} catch (Exception $e) {
    error_log("Error checking job applications table: " . $e->getMessage());
}

// Check if tradesperson_skills table exists and create it if it doesn't
try {
    $skillsCheckSql = "SHOW TABLES LIKE 'tradesperson_skills'";
    $skillsResult = $conn->query($skillsCheckSql);
    
    if ($skillsResult->num_rows == 0) {
        // Table doesn't exist, create it
        $createSkillsTable = "
        CREATE TABLE IF NOT EXISTS tradesperson_skills (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            skill_name VARCHAR(255) NOT NULL,
            experience_level VARCHAR(100) NOT NULL,
            certification_file VARCHAR(255) NULL,
            is_verified TINYINT(1) NOT NULL DEFAULT 0,
            verified_by INT NULL,
            verified_at TIMESTAMP NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        
        if ($conn->query($createSkillsTable)) {
            error_log("Tradesperson skills table created successfully");
            
            // Create indexes for better performance
            $createSkillsIndexes = "
            ALTER TABLE tradesperson_skills 
            ADD INDEX idx_user_id (user_id),
            ADD INDEX idx_skill_name (skill_name),
            ADD INDEX idx_verified (is_verified);
            ";
            
            try {
                $conn->query($createSkillsIndexes);
                error_log("Tradesperson skills indexes created successfully");
            } catch (Exception $e) {
                error_log("Error creating tradesperson skills indexes: " . $e->getMessage());
            }
        } else {
            error_log("Error creating tradesperson skills table: " . $conn->error);
        }
    }
} catch (Exception $e) {
    error_log("Error checking tradesperson skills table: " . $e->getMessage());
}

// Check if job_reviews table exists and create it if it doesn't
try {
    $reviewsCheckSql = "SHOW TABLES LIKE 'job_reviews'";
    $reviewsResult = $conn->query($reviewsCheckSql);
    
    if ($reviewsResult->num_rows == 0) {
        // Table doesn't exist, create it
        $createReviewsTable = "
        CREATE TABLE IF NOT EXISTS job_reviews (
            id INT AUTO_INCREMENT PRIMARY KEY,
            job_id INT NOT NULL,
            reviewer_id INT NOT NULL,
            reviewee_id INT NOT NULL,
            rating INT NOT NULL CHECK (rating BETWEEN 1 AND 5),
            review_text TEXT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
            FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (reviewee_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        
        if ($conn->query($createReviewsTable)) {
            error_log("Job reviews table created successfully");
            
            // Create indexes for better performance
            $createReviewsIndexes = "
            ALTER TABLE job_reviews 
            ADD INDEX idx_job_id (job_id),
            ADD INDEX idx_reviewer (reviewer_id),
            ADD INDEX idx_reviewee (reviewee_id),
            ADD UNIQUE INDEX idx_unique_review (job_id, reviewer_id, reviewee_id);
            ";
            
            try {
                $conn->query($createReviewsIndexes);
                error_log("Job reviews indexes created successfully");
            } catch (Exception $e) {
                error_log("Error creating job reviews indexes: " . $e->getMessage());
            }
        } else {
            error_log("Error creating job reviews table: " . $conn->error);
        }
    }
} catch (Exception $e) {
    error_log("Error checking job reviews table: " . $e->getMessage());
}
?>