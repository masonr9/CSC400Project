-- CREATE TABLE reviews (
--   id INT AUTO_INCREMENT PRIMARY KEY,
--   book_title VARCHAR(100) NOT NULL,
--   rating INT NOT NULL,
--   review_text TEXT NOT NULL,
--   created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
-- );


-- CREATE TABLE IF NOT EXISTS website_reviews (
--   id INT AUTO_INCREMENT PRIMARY KEY,
--   rating INT NOT NULL,
--   review_text TEXT NOT NULL,
--   created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
-- );

-- USe this for sql --
CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    book_id INT NOT NULL,
    rating INT NOT NULL,
    review_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (book_id) REFERENCES books(book_id) ON DELETE CASCADE
);
