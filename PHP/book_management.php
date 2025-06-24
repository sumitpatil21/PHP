<?php
declare(strict_types=1);

namespace BookManagement;

use PDO;
use PDOException;
use Exception;

/**
 * Modern Book Management System
 * PHP 8.1+ Compatible with best practices
 */

// Autoloader for classes
spl_autoload_register(function ($class_name) {
    $class_name = str_replace('BookManagement\\', '', $class_name);
    $file = __DIR__ . '/classes/' . $class_name . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

/**
 * Database Configuration Class
 */
class DatabaseConfig
{
    private const HOST = 'localhost';
    private const DB_NAME = 'book_management';
    private const USERNAME = 'root';
    private const PASSWORD = '';
    
    public static function getConnection(): PDO
    {
        try {
            $dsn = "mysql:host=" . self::HOST . ";dbname=" . self::DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, self::USERNAME, self::PASSWORD, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            return $pdo;
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
}

/**
 * Book Entity Class with modern PHP features
 */
class Book
{
    public function __construct(
        private ?int $id = null,
        private string $title = '',
        private string $author = '',
        private string $isbn = '',
        private float $price = 0.0,
        private int $quantity = 0,
        private string $description = '',
        private ?string $createdAt = null,
        private ?string $updatedAt = null
    ) {}

    // Getters with return type declarations
    public function getId(): ?int { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function getAuthor(): string { return $this->author; }
    public function getIsbn(): string { return $this->isbn; }
    public function getPrice(): float { return $this->price; }
    public function getQuantity(): int { return $this->quantity; }
    public function getDescription(): string { return $this->description; }
    public function getCreatedAt(): ?string { return $this->createdAt; }
    public function getUpdatedAt(): ?string { return $this->updatedAt; }

    // Setters with parameter type declarations
    public function setId(?int $id): self { $this->id = $id; return $this; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }
    public function setAuthor(string $author): self { $this->author = $author; return $this; }
    public function setIsbn(string $isbn): self { $this->isbn = $isbn; return $this; }
    public function setPrice(float $price): self { $this->price = $price; return $this; }
    public function setQuantity(int $quantity): self { $this->quantity = $quantity; return $this; }
    public function setDescription(string $description): self { $this->description = $description; return $this; }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'author' => $this->author,
            'isbn' => $this->isbn,
            'price' => $this->price,
            'quantity' => $this->quantity,
            'description' => $this->description,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['id'] ?? null,
            $data['title'] ?? '',
            $data['author'] ?? '',
            $data['isbn'] ?? '',
            (float)($data['price'] ?? 0.0),
            (int)($data['quantity'] ?? 0),
            $data['description'] ?? '',
            $data['created_at'] ?? null,
            $data['updated_at'] ?? null
        );
    }
}

/**
 * Repository Pattern for Book operations
 */
class BookRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = DatabaseConfig::getConnection();
        $this->createTableIfNotExists();
    }

    private function createTableIfNotExists(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS books (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            author VARCHAR(255) NOT NULL,
            isbn VARCHAR(20) UNIQUE NOT NULL,
            price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            quantity INT NOT NULL DEFAULT 0,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_title (title),
            INDEX idx_author (author),
            INDEX idx_isbn (isbn)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->pdo->exec($sql);
    }

    public function create(Book $book): Book
    {
        $sql = "INSERT INTO books (title, author, isbn, price, quantity, description) 
                VALUES (:title, :author, :isbn, :price, :quantity, :description)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':title' => $book->getTitle(),
            ':author' => $book->getAuthor(),
            ':isbn' => $book->getIsbn(),
            ':price' => $book->getPrice(),
            ':quantity' => $book->getQuantity(),
            ':description' => $book->getDescription()
        ]);

        $book->setId((int)$this->pdo->lastInsertId());
        return $book;
    }

    public function findById(int $id): ?Book
    {
        $sql = "SELECT * FROM books WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        
        $data = $stmt->fetch();
        return $data ? Book::fromArray($data) : null;
    }

    public function findAll(int $limit = 100, int $offset = 0): array
    {
        $sql = "SELECT * FROM books ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return array_map(fn($data) => Book::fromArray($data), $stmt->fetchAll());
    }

    public function search(string $query): array
    {
        $sql = "SELECT * FROM books 
                WHERE title LIKE :query OR author LIKE :query OR isbn LIKE :query 
                ORDER BY title";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':query' => "%{$query}%"]);
        
        return array_map(fn($data) => Book::fromArray($data), $stmt->fetchAll());
    }

    public function update(Book $book): bool
    {
        $sql = "UPDATE books 
                SET title = :title, author = :author, isbn = :isbn, 
                    price = :price, quantity = :quantity, description = :description
                WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':id' => $book->getId(),
            ':title' => $book->getTitle(),
            ':author' => $book->getAuthor(),
            ':isbn' => $book->getIsbn(),
            ':price' => $book->getPrice(),
            ':quantity' => $book->getQuantity(),
            ':description' => $book->getDescription()
        ]);
    }

    public function delete(int $id): bool
    {
        $sql = "DELETE FROM books WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    public function getTotalCount(): int
    {
        $sql = "SELECT COUNT(*) FROM books";
        return (int)$this->pdo->query($sql)->fetchColumn();
    }
}

/**
 * Service Layer for Business Logic
 */
class BookService
{
    private BookRepository $repository;

    public function __construct()
    {
        $this->repository = new BookRepository();
    }

    public function createBook(array $data): Book
    {
        $this->validateBookData($data);
        
        $book = new Book(
            null,
            $data['title'],
            $data['author'],
            $data['isbn'],
            (float)$data['price'],
            (int)$data['quantity'],
            $data['description'] ?? ''
        );

        return $this->repository->create($book);
    }

    public function updateBook(int $id, array $data): bool
    {
        $book = $this->repository->findById($id);
        if (!$book) {
            throw new Exception("Book not found");
        }

        $this->validateBookData($data);

        $book->setTitle($data['title'])
             ->setAuthor($data['author'])
             ->setIsbn($data['isbn'])
             ->setPrice((float)$data['price'])
             ->setQuantity((int)$data['quantity'])
             ->setDescription($data['description'] ?? '');

        return $this->repository->update($book);
    }

    public function getBook(int $id): ?Book
    {
        return $this->repository->findById($id);
    }

    public function getAllBooks(int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        return $this->repository->findAll($perPage, $offset);
    }

    public function searchBooks(string $query): array
    {
        if (strlen(trim($query)) < 2) {
            throw new Exception("Search query must be at least 2 characters long");
        }
        return $this->repository->search($query);
    }

    public function deleteBook(int $id): bool
    {
        $book = $this->repository->findById($id);
        if (!$book) {
            throw new Exception("Book not found");
        }
        return $this->repository->delete($id);
    }

    public function getBookStats(): array
    {
        return [
            'total_books' => $this->repository->getTotalCount(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    private function validateBookData(array $data): void
    {
        $required = ['title', 'author', 'isbn', 'price', 'quantity'];
        
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty(trim($data[$field]))) {
                throw new Exception("Field '{$field}' is required");
            }
        }

        if (!is_numeric($data['price']) || $data['price'] < 0) {
            throw new Exception("Price must be a valid positive number");
        }

        if (!is_numeric($data['quantity']) || $data['quantity'] < 0) {
            throw new Exception("Quantity must be a valid positive integer");
        }

        if (!preg_match('/^[\d-]{10,20}$/', $data['isbn'])) {
            throw new Exception("ISBN format is invalid");
        }
    }
}

/**
 * API Controller for handling HTTP requests
 */
class BookController
{
    private BookService $service;

    public function __construct()
    {
        $this->service = new BookService();
        header('Content-Type: application/json');
    }

    public function handleRequest(): void
    {
        try {
            $method = $_SERVER['REQUEST_METHOD'];
            $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            $pathParts = explode('/', trim($path, '/'));

            match($method) {
                'GET' => $this->handleGet($pathParts),
                'POST' => $this->handlePost(),
                'PUT' => $this->handlePut($pathParts),
                'DELETE' => $this->handleDelete($pathParts),
                default => $this->sendError('Method not allowed', 405)
            };
        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 400);
        }
    }

    private function handleGet(array $pathParts): void
    {
        if (isset($pathParts[1]) && is_numeric($pathParts[1])) {
            // Get single book
            $book = $this->service->getBook((int)$pathParts[1]);
            if ($book) {
                $this->sendSuccess($book->toArray());
            } else {
                $this->sendError('Book not found', 404);
            }
        } elseif (isset($_GET['search'])) {
            // Search books
            $books = $this->service->searchBooks($_GET['search']);
            $this->sendSuccess(array_map(fn($book) => $book->toArray(), $books));
        } elseif (isset($_GET['stats'])) {
            // Get stats
            $this->sendSuccess($this->service->getBookStats());
        } else {
            // Get all books with pagination
            $page = (int)($_GET['page'] ?? 1);
            $perPage = (int)($_GET['per_page'] ?? 20);
            $books = $this->service->getAllBooks($page, $perPage);
            $this->sendSuccess(array_map(fn($book) => $book->toArray(), $books));
        }
    }

    private function handlePost(): void
    {
        $data = $this->getJsonInput();
        $book = $this->service->createBook($data);
        $this->sendSuccess($book->toArray(), 201);
    }

    private function handlePut(array $pathParts): void
    {
        if (!isset($pathParts[1]) || !is_numeric($pathParts[1])) {
            $this->sendError('Book ID required', 400);
            return;
        }

        $data = $this->getJsonInput();
        $success = $this->service->updateBook((int)$pathParts[1], $data);
        
        if ($success) {
            $this->sendSuccess(['message' => 'Book updated successfully']);
        } else {
            $this->sendError('Failed to update book', 500);
        }
    }

    private function handleDelete(array $pathParts): void
    {
        if (!isset($pathParts[1]) || !is_numeric($pathParts[1])) {
            $this->sendError('Book ID required', 400);
            return;
        }

        $success = $this->service->deleteBook((int)$pathParts[1]);
        
        if ($success) {
            $this->sendSuccess(['message' => 'Book deleted successfully']);
        } else {
            $this->sendError('Failed to delete book', 500);
        }
    }

    private function getJsonInput(): array
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON input');
        }
        
        return $data ?? [];
    }

    private function sendSuccess(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        echo json_encode([
            'success' => true,
            'data' => $data,
            'timestamp' => date('c')
        ], JSON_PRETTY_PRINT);
        exit;
    }

    private function sendError(string $message, int $statusCode = 400): void
    {
        http_response_code($statusCode);
        echo json_encode([
            'success' => false,
            'error' => $message,
            'timestamp' => date('c')
        ], JSON_PRETTY_PRINT);
        exit;
    }
}

// CLI Script for testing
if (php_sapi_name() === 'cli') {
    echo "=== Modern PHP Book Management System ===\n";
    
    try {
        $service = new BookService();
        
        // Example usage
        echo "Creating sample book...\n";
        $book = $service->createBook([
            'title' => 'Modern PHP Development',
            'author' => 'John Doe',
            'isbn' => '978-0123456789',
            'price' => 29.99,
            'quantity' => 10,
            'description' => 'A comprehensive guide to modern PHP development practices.'
        ]);
        
        echo "Book created with ID: " . $book->getId() . "\n";
        
        echo "Searching for books...\n";
        $books = $service->searchBooks('PHP');
        echo "Found " . count($books) . " books matching 'PHP'\n";
        
        echo "Book stats:\n";
        print_r($service->getBookStats());
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
} else {
    // Web API
    $controller = new BookController();
    $controller->handleRequest();
}
?>