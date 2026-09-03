<?php
/**
 * Database Configuration
 * 
 * @author Nico Roell D. Garce
 * @title UPHSL Web Administrator 2025
 * @description Database configuration and initialization for UPHSL website
 */

// Database configuration — COPY this file to database.php and fill real credentials
// Do NOT commit database.php (see .gitignore)
define('DB_HOST', 'localhost');
define('DB_CHARSET', 'utf8mb4');
    // Production database credentials — fill from .env or vault
    //define('DB_NAME', 'your_production_db');
    //define('DB_USER', 'your_production_user');
    //define('DB_PASS', 'your_production_password');
    // Local development database credentials — use example only
    define('DB_NAME', 'uphsledu_main');
    define('DB_USER', 'root');
    define('DB_PASS', ''); // <-- set your local password, never commit real password

// Online Payment Database configuration
define('ONLINE_PAYMENT_DB_HOST', 'localhost');
    // Production online payment database credentials
    //define('ONLINE_PAYMENT_DB_NAME', 'your_onlinepayment_db');
    //define('ONLINE_PAYMENT_DB_USER', 'your_onlinepayment_user');
    //define('ONLINE_PAYMENT_DB_PASS', 'your_onlinepayment_password');
    // Local development online payment database credentials
    define('ONLINE_PAYMENT_DB_NAME', 'uphsledu_onlinepayment');
    define('ONLINE_PAYMENT_DB_USER', 'root');
    define('ONLINE_PAYMENT_DB_PASS', ''); // <-- set your local password

// Create database connection
function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

// Initialize database tables
function initializeDatabase() {
    $pdo = getDBConnection();
    
    // Create users table
    $usersTable = "
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            first_name VARCHAR(50) NOT NULL,
            last_name VARCHAR(50) NOT NULL,
            role ENUM('super_admin', 'admin', 'author', 'hr') DEFAULT 'hr',
            avatar VARCHAR(255) DEFAULT NULL,
            bio TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    // Create posts table
    $postsTable = "
        CREATE TABLE IF NOT EXISTS posts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) UNIQUE NOT NULL,
            content TEXT NOT NULL,
            excerpt TEXT DEFAULT NULL,
            featured_image VARCHAR(255) DEFAULT NULL,
            status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
            author_id INT NOT NULL,
            category_id INT DEFAULT NULL,
            views INT DEFAULT 0,
            published_at TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
        )
    ";
    
    // Create post_images table
    $postImagesTable = "
        CREATE TABLE IF NOT EXISTS post_images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            post_id INT NOT NULL,
            image_path VARCHAR(255) NOT NULL,
            image_alt VARCHAR(255) DEFAULT NULL,
            sort_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
        )
    ";
    
    // Create categories table
    $categoriesTable = "
        CREATE TABLE IF NOT EXISTS categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(100) UNIQUE NOT NULL,
            description TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ";
    
    // Create sdg_initiatives_posts table
    $sdgInitiativesPostsTable = "
        CREATE TABLE IF NOT EXISTS sdg_initiatives_posts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) UNIQUE NOT NULL,
            content TEXT NOT NULL,
            excerpt TEXT DEFAULT NULL,
            featured_image VARCHAR(255) DEFAULT NULL,
            sdg_number INT NOT NULL,
            sdg_title VARCHAR(255) NOT NULL,
            status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
            author_id INT NOT NULL,
            views INT DEFAULT 0,
            published_at TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ";
    
    // Create sdg_initiatives_images table
    $sdgInitiativesImagesTable = "
        CREATE TABLE IF NOT EXISTS sdg_initiatives_images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            post_id INT NOT NULL,
            image_path VARCHAR(255) NOT NULL,
            image_alt VARCHAR(255) DEFAULT NULL,
            sort_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (post_id) REFERENCES sdg_initiatives_posts(id) ON DELETE CASCADE
        )
    ";
    
    // Create careers_postings table
    // First create table without foreign key, then add it separately
    $careersPostingsTable = "
        CREATE TABLE IF NOT EXISTS careers_postings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            position VARCHAR(255) NOT NULL,
            location VARCHAR(255) NOT NULL,
            employment_type VARCHAR(100) NOT NULL,
            job_description TEXT NOT NULL,
            requirements TEXT NOT NULL,
            application_details TEXT NOT NULL,
            status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
            author_id INT NOT NULL,
            views INT DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            published_at DATETIME DEFAULT NULL,
            slug VARCHAR(255) NOT NULL,
            UNIQUE KEY slug (slug),
            KEY author_id (author_id),
            KEY status (status),
            KEY published_at (published_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    try {
        // Ensure users table is created first with InnoDB
        $pdo->exec($usersTable);
        // Ensure users table uses InnoDB engine
        $pdo->exec("ALTER TABLE users ENGINE=InnoDB");
        
        $pdo->exec($postsTable);
        $pdo->exec($postImagesTable);
        $pdo->exec($categoriesTable);
        $pdo->exec($sdgInitiativesPostsTable);
        $pdo->exec($sdgInitiativesImagesTable);

        // Ensure library tables exist early
        // Create library_programs table (no longer creating library_program_pdfs table)
        $pdo->exec("CREATE TABLE IF NOT EXISTS library_programs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(100) UNIQUE NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            image VARCHAR(255) DEFAULT NULL,
            link VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // Create careers_postings table without foreign key first
        $pdo->exec($careersPostingsTable);
        
        // Add foreign key constraint separately
        try {
            // Check if foreign key already exists
            $stmt = $pdo->query("
                SELECT COUNT(*) as count 
                FROM information_schema.TABLE_CONSTRAINTS 
                WHERE CONSTRAINT_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'careers_postings' 
                AND CONSTRAINT_NAME = 'careers_postings_ibfk_1'
            ");
            $fkExists = $stmt->fetch()['count'] > 0;
            
            if (!$fkExists) {
                // Ensure both tables use InnoDB
                $pdo->exec("ALTER TABLE users ENGINE=InnoDB");
                $pdo->exec("ALTER TABLE careers_postings ENGINE=InnoDB");
                
                // Add foreign key constraint
                $pdo->exec("
                    ALTER TABLE careers_postings 
                    ADD CONSTRAINT careers_postings_ibfk_1 
                    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
                ");
            }
        } catch (PDOException $e) {
            // If foreign key creation fails, log but continue
            error_log("Warning: Could not add foreign key to careers_postings: " . $e->getMessage());
        }
        
        // Add published_at column if it doesn't exist
        $pdo->exec("ALTER TABLE posts ADD COLUMN IF NOT EXISTS published_at TIMESTAMP NULL DEFAULT NULL AFTER views");
        
        // Add category_id column if it doesn't exist
        $pdo->exec("ALTER TABLE posts ADD COLUMN IF NOT EXISTS category_id INT DEFAULT NULL AFTER author_id");
        
        // Add views column to careers_postings if it doesn't exist
        try {
            $pdo->exec("ALTER TABLE careers_postings ADD COLUMN IF NOT EXISTS views INT DEFAULT 0 AFTER author_id");
        } catch (PDOException $e) {
            // Column may already exist, continue
            error_log("Note: Could not add views column to careers_postings: " . $e->getMessage());
        }
        
        // Update users table role enum to include 'hr' and remove 'user' (if needed)
        // This handles existing databases that may have the old 'user' role
        try {
            // Check if 'hr' role exists in the enum
            $stmt = $pdo->query("
                SELECT COLUMN_TYPE 
                FROM information_schema.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'users' 
                AND COLUMN_NAME = 'role'
            ");
            $result = $stmt->fetch();
            if ($result && strpos($result['COLUMN_TYPE'], "'hr'") === false) {
                // Update role enum to include 'hr' and remove 'user'
                $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin', 'admin', 'author', 'hr') DEFAULT 'hr'");
            }
        } catch (PDOException $e) {
            // If the column doesn't exist or update fails, continue
            error_log("Note: Could not update users role enum: " . $e->getMessage());
        }
        
        // Create post_sdg_tags table separately to handle foreign key constraint properly
        // First, try to create the table without foreign key
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS post_sdg_tags (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    post_id INT NOT NULL,
                    sdg_number INT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_post_sdg (post_id, sdg_number),
                    KEY idx_post_id (post_id),
                    KEY idx_sdg_number (sdg_number)
                )
            ");
            
            // Then add the foreign key constraint if it doesn't exist
            // Check if foreign key already exists
            $stmt = $pdo->query("
                SELECT COUNT(*) as count 
                FROM information_schema.TABLE_CONSTRAINTS 
                WHERE CONSTRAINT_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'post_sdg_tags' 
                AND CONSTRAINT_NAME = 'post_sdg_tags_ibfk_1'
            ");
            $fkExists = $stmt->fetch()['count'] > 0;
            
            if (!$fkExists) {
                // Ensure posts table uses InnoDB
                $pdo->exec("ALTER TABLE posts ENGINE=InnoDB");
                // Add foreign key constraint
                $pdo->exec("
                    ALTER TABLE post_sdg_tags 
                    ADD CONSTRAINT post_sdg_tags_ibfk_1 
                    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
                ");
            }
        } catch (PDOException $e) {
            // If table creation fails, try alternative approach
            error_log("Warning: Could not create post_sdg_tags table with foreign key: " . $e->getMessage());
            // Try creating without foreign key (for compatibility)
            try {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS post_sdg_tags (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        post_id INT NOT NULL,
                        sdg_number INT NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE KEY unique_post_sdg (post_id, sdg_number),
                        KEY idx_post_id (post_id),
                        KEY idx_sdg_number (sdg_number)
                    ) ENGINE=InnoDB
                ");
            } catch (PDOException $e2) {
                error_log("Error creating post_sdg_tags table: " . $e2->getMessage());
            }
        
        // Create library_programs table
        $libraryProgramsTable = "
            CREATE TABLE IF NOT EXISTS library_programs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                slug VARCHAR(100) UNIQUE NOT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT DEFAULT NULL,
                image VARCHAR(255) DEFAULT NULL,
                link VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";

        // Note: PDF attachment table deprecated. We create only the programs table.
        $pdo->exec($libraryProgramsTable);

        // Seed default library programs if table empty
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM library_programs");
            $cnt = (int)$stmt->fetch()['cnt'];
            if ($cnt === 0) {
                $defaults = [
                    ['slug' => 'free-coffee', 'title' => 'Free Coffee', 'description' => 'The library offers complimentary coffee during study hours to foster a welcoming, focused atmosphere for students and staff. This small but meaningful amenity encourages longer study sessions, peer collaboration, and informal librarian-student interactions that increase resource discovery and support academic success across disciplines.', 'image' => 'assets/images/support-services/college-library/img/programs/free-coffee.jpg'],
                    ['slug' => 'seed-library', 'title' => 'Seed Library Program', 'description' => 'A curated collection of seeds available for students, faculty, and community members to borrow, plant, and return seeds from their harvests. The program promotes sustainable gardening, biodiversity awareness, and hands-on learning while supporting campus greening projects and offering workshops on seed saving and native planting techniques.', 'image' => 'assets/images/support-services/college-library/img/programs/seed-library.jpg'],
                    ['slug' => 'community-outreach', 'title' => 'Community Outreach Program', 'description' => 'Library staff partner with local schools, NGOs, and community groups to deliver mobile library services, literacy workshops, and tailored resource sessions. Outreach expands access to information, fosters lifelong learning, and strengthens university-community ties through collaborative events, volunteer opportunities, and shared educational resources.', 'image' => 'assets/images/support-services/college-library/img/programs/community-outreach.jpg'],
                    ['slug' => 'international-conference', 'title' => 'International Collaborative Conference', 'description' => 'The library organizes an annual conference bringing together international scholars, librarians, and students to exchange research, best practices, and innovations in information services. The event features keynote speakers, panels, and networking aimed at building research collaborations and elevating the library\'s global engagement.', 'image' => 'assets/images/support-services/college-library/img/programs/international-conference.jpg'],
                    ['slug' => 'newsletter-reports', 'title' => 'Library Newsletter and Annual Reports', 'description' => 'A periodic newsletter and comprehensive annual reports highlight library initiatives, program outcomes, acquisitions, and impact metrics. Distributed digitally and in print, these publications keep stakeholders informed, celebrate achievements, and guide strategic planning by presenting data-driven narratives about services and user engagement.', 'image' => 'assets/images/support-services/college-library/img/programs/newsletter-reports.jpg']
                ];
                $ins = $pdo->prepare("INSERT INTO library_programs (slug, title, description, image) VALUES (?, ?, ?, ?)");
                foreach ($defaults as $d) {
                    $ins->execute([$d['slug'], $d['title'], $d['description'], $d['image']]);
                }
            }
        } catch (Exception $e) {
            error_log('Could not seed library_programs: ' . $e->getMessage());
        }
        }
        
        // Create default users if no users exist
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        $result = $stmt->fetch();
        
        if ($result['count'] == 0) {
            // Create super admin account
            $superAdminPassword = password_hash('SuperAdmin@123', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password, first_name, last_name, role) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute(['web-admin', 'web-admin@uphsl.edu.ph', $superAdminPassword, 'Web', 'Administrator', 'super_admin']);
            
            // Create marketing staff as author
            $authorPassword = password_hash('Marketing@123', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password, first_name, last_name, role) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute(['marketing-author', 'marketing.author@uphsl.edu.ph', $authorPassword, 'Marketing', 'Author', 'author']);
            
            // Create marketing staff as admin
            $adminPassword = password_hash('MarketingAdmin@123', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password, first_name, last_name, role) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute(['marketing-admin', 'marketing.admin@uphsl.edu.ph', $adminPassword, 'Marketing', 'Admin', 'admin']);
            
            // Create some default categories
            $defaultCategories = [
                ['name' => 'News', 'slug' => 'news', 'description' => 'University news and announcements'],
                ['name' => 'Events', 'slug' => 'events', 'description' => 'University events and activities'],
                ['name' => 'Academics', 'slug' => 'academics', 'description' => 'Academic programs and updates'],
                ['name' => 'Student Life', 'slug' => 'student-life', 'description' => 'Student activities and achievements'],
                ['name' => 'Research', 'slug' => 'research', 'description' => 'Research projects and publications']
            ];
            
            foreach ($defaultCategories as $category) {
                $stmt = $pdo->prepare("
                    INSERT INTO categories (name, slug, description) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$category['name'], $category['slug'], $category['description']]);
            }
        }
        
        return true;
    } catch (PDOException $e) {
        die("Database initialization failed: " . $e->getMessage());
    }
}

// Initialize database on first run
initializeDatabase();
?>