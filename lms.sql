-- 1. Create Database
CREATE DATABASE IF NOT EXISTS library_management;
USE library_management;

-- 2. Users Table
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('Member','Librarian','Admin') DEFAULT 'Member'
);

-- 3. Books Table
CREATE TABLE IF NOT EXISTS books (
    book_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    author VARCHAR(100),
    genre VARCHAR(50),
    language VARCHAR(50),
    isbn VARCHAR(20) UNIQUE,
    publication_year INT,
    summary TEXT,
    available BOOLEAN DEFAULT TRUE
);

-- 4. Loans Table
CREATE TABLE IF NOT EXISTS loans (
    loan_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    book_id INT NOT NULL,
    borrow_date DATE,
    due_date DATE,
    return_date DATE,
    status ENUM('Active','Returned','Overdue') DEFAULT 'Active',
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (book_id) REFERENCES books(book_id)
);

-- 5. Reservations Table
CREATE TABLE IF NOT EXISTS reservations (
    reservation_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    book_id INT NOT NULL,
    reservation_date DATE,
    status ENUM('Pending','Approved','Fulfilled') DEFAULT 'Pending',
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (book_id) REFERENCES books(book_id)
);

-- 6. Fines Table
CREATE TABLE IF NOT EXISTS fines (
    fine_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(6,2),
    paid BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    CONSTRAINT fk_fines_loan FOREIGN KEY (loan_id) REFERENCES loans(loan_id)
);

-- 7. Logs Table
CREATE TABLE logs (
  log_id INT AUTO_INCREMENT PRIMARY KEY,
  user VARCHAR(100) NOT NULL,
  action TEXT NOT NULL,
  timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Insert sample users
INSERT INTO users (name, email, password, role) VALUES
('Admin User', 'admin@example.com', 'admin123', 'Admin'),
('John Doe', 'john@example.com', 'john123', 'Member'),
('Jane Librarian', 'jane@example.com', 'jane123', 'Librarian');

-- Insert sample books
INSERT INTO books (title, author, genre, language, isbn, publication_year, summary, available) VALUES
('The Great Gatsby', 'F. Scott Fitzgerald', 'Novel', 'English', '9780743273565', 1925, 'Classic American novel.', TRUE),
('1984', 'George Orwell', 'Dystopian', 'English', '9780451524935', 1949, 'A chilling novel of surveillance and control.', TRUE),
('To Kill a Mockingbird', 'Harper Lee', 'Novel', 'English', '9780061120084', 1960, 'A novel of racial injustice in the Deep South.', TRUE);

-- Insert sample loan
INSERT INTO loans (user_id, book_id, borrow_date, due_date, status) VALUES
(2, 1, '2025-09-01', '2025-09-15', 'Active');

-- Insert sample reservation
INSERT INTO reservations (user_id, book_id, reservation_date, status) VALUES
(2, 2, '2025-09-10', 'Pending');

-- Insert sample fine
INSERT INTO fines (user_id, amount, paid) VALUES
(2, 5.00, FALSE);