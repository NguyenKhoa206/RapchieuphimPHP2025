-- MySQL 8 / MariaDB 10.6+, UTF-8, InnoDB. Does not drop legacy tables.
CREATE TABLE IF NOT EXISTS cinema_meta (
    setting_key VARCHAR(80) PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cinema_users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    legacy_key VARCHAR(40) NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL UNIQUE,
    gender TINYINT NOT NULL DEFAULT 2,
    birthday DATE NULL,
    password_hash VARCHAR(255) NOT NULL,
    password_scheme VARCHAR(16) NOT NULL DEFAULT 'modern',
    auth_version INT NOT NULL DEFAULT 1,
    active TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cinema_admins (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    password_scheme VARCHAR(16) NOT NULL DEFAULT 'modern',
    auth_version INT NOT NULL DEFAULT 1,
    active TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cinema_login_attempts (
    attempt_key CHAR(64) PRIMARY KEY,
    failures INT NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cinema_movies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(190) NOT NULL,
    genre VARCHAR(120) NOT NULL,
    age_rating INT NOT NULL DEFAULT 0,
    duration INT NOT NULL,
    release_date DATE NOT NULL,
    end_date DATE NOT NULL,
    description TEXT NOT NULL,
    poster VARCHAR(255) NULL,
    trailer VARCHAR(255) NULL,
    active TINYINT NOT NULL DEFAULT 1,
    INDEX movie_dates (active, release_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cinema_cinemas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(190) NOT NULL,
    address VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    active TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cinema_rooms (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cinema_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    room_type VARCHAR(16) NOT NULL DEFAULT 'standard',
    active TINYINT NOT NULL DEFAULT 1,
    UNIQUE KEY room_name (cinema_id, name),
    FOREIGN KEY (cinema_id) REFERENCES cinema_cinemas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cinema_seats (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_id BIGINT UNSIGNED NOT NULL,
    row_label VARCHAR(2) NOT NULL,
    seat_number INT NOT NULL,
    price_extra INT NOT NULL DEFAULT 0,
    UNIQUE KEY seat_position (room_id, row_label, seat_number),
    FOREIGN KEY (room_id) REFERENCES cinema_rooms(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cinema_showtimes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    movie_id BIGINT UNSIGNED NOT NULL,
    room_id BIGINT UNSIGNED NOT NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    base_price INT NOT NULL,
    language VARCHAR(60) NOT NULL DEFAULT 'Phụ đề tiếng Việt',
    active TINYINT NOT NULL DEFAULT 1,
    INDEX schedule_room (room_id, active, starts_at, ends_at),
    INDEX schedule_movie (movie_id, active, starts_at),
    FOREIGN KEY (movie_id) REFERENCES cinema_movies(id),
    FOREIGN KEY (room_id) REFERENCES cinema_rooms(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cinema_combos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    price INT NOT NULL,
    active TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cinema_orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference CHAR(16) NOT NULL UNIQUE,
    request_key CHAR(64) NOT NULL UNIQUE,
    user_id BIGINT UNSIGNED NOT NULL,
    showtime_id BIGINT UNSIGNED NOT NULL,
    total INT NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'pending',
    payment_method VARCHAR(16) NOT NULL DEFAULT 'counter',
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    paid_at DATETIME NULL,
    paid_by BIGINT UNSIGNED NULL,
    INDEX orders_user (user_id, created_at),
    INDEX orders_revenue (status, paid_at, showtime_id),
    INDEX orders_expiry (status, expires_at),
    FOREIGN KEY (user_id) REFERENCES cinema_users(id),
    FOREIGN KEY (showtime_id) REFERENCES cinema_showtimes(id),
    FOREIGN KEY (paid_by) REFERENCES cinema_admins(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cinema_order_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    kind VARCHAR(16) NOT NULL,
    item_name VARCHAR(190) NOT NULL,
    quantity INT NOT NULL,
    unit_price INT NOT NULL,
    FOREIGN KEY (order_id) REFERENCES cinema_orders(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cinema_reservations (
    showtime_id BIGINT UNSIGNED NOT NULL,
    seat_id BIGINT UNSIGNED NOT NULL,
    order_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (showtime_id, seat_id),
    INDEX reservations_order (order_id),
    FOREIGN KEY (showtime_id) REFERENCES cinema_showtimes(id),
    FOREIGN KEY (seat_id) REFERENCES cinema_seats(id),
    FOREIGN KEY (order_id) REFERENCES cinema_orders(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
