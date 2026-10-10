<?php

declare(strict_types=1);

namespace App\Http;

use App\Domain\Exception\BusinessException;
use App\Domain\Exception\ForbiddenRoleException;
use App\Domain\Exception\InvalidCredentialsException;
use App\Domain\ValueObject\Role;
use App\Infrastructure\Composition\Container;
use Throwable;

final class Router
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function handle(string $method, string $uri): void
    {
        // Handle CORS
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

        if ($method === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        $parsedPath = parse_url($uri, PHP_URL_PATH) ?? '/';
        $normalizedPath = rtrim($parsedPath, '/');
        if ($normalizedPath === '') {
            $normalizedPath = '/';
        }

        // Support both with and without /api/v1 prefix
        $apiPrefix = '/api/v1';
        if (str_starts_with($normalizedPath, $apiPrefix)) {
            $path = substr($normalizedPath, strlen($apiPrefix));
            if ($path === '' || !str_starts_with($path, '/')) {
                $path = '/' . $path;
            }
        } else {
            $path = $normalizedPath;
        }

        try {
            $this->dispatch($method, $path);
        } catch (BusinessException $e) {
            $this->respondError($e->getHttpStatus(), $e->getErrorCode(), $e->getMessage(), $e->getDetails());
        } catch (Throwable $e) {
            $this->respondError(500, 'INTERNAL_SERVER_ERROR', 'Error interno en el servidor.');
        }
    }

    private function dispatch(string $method, string $path): void
    {
        // 1. Health
        if ($method === 'GET' && ($path === '/health' || $path === '')) {
            $this->respondJson(200, ['status' => 'ok']);
            return;
        }

        // 2. Auth Login
        if ($method === 'POST' && $path === '/auth/login') {
            $body = $this->getJsonBody();
            $username = (string)($body['username'] ?? '');
            $password = (string)($body['password'] ?? '');
            $tokenData = $this->container->loginService->execute($username, $password);
            $this->respondJson(200, $tokenData);
            return;
        }

        // Authenticated routes beyond this point
        $auth = $this->authenticate();

        // 3. Users (admin only)
        if ($method === 'POST' && $path === '/users') {
            $body = $this->getJsonBody();
            $username = (string)($body['username'] ?? '');
            $password = (string)($body['password'] ?? '');
            $role = (string)($body['role'] ?? '');
            $created = $this->container->createLibrarianService->execute($username, $password, $role, $auth['role']);
            $this->respondJson(201, $created);
            return;
        }

        // 4. Categories (authenticated)
        if ($method === 'GET' && $path === '/categories') {
            $categories = $this->container->listCategoriesService->execute();
            $this->respondJson(200, $categories);
            return;
        }

        // 5. Books
        if ($method === 'GET' && $path === '/books') {
            $q = isset($_GET['q']) ? (string)$_GET['q'] : null;
            $catId = isset($_GET['category_id']) ? (string)$_GET['category_id'] : null;
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $size = isset($_GET['size']) ? (int)$_GET['size'] : 20;
            $result = $this->container->bookService->search($q, $catId, $page, $size);
            $this->respondJson(200, $result);
            return;
        }

        if ($method === 'POST' && $path === '/books') {
            $body = $this->getJsonBody();
            $title = (string)($body['title'] ?? '');
            $author = (string)($body['author'] ?? '');
            $isbn = (string)($body['isbn'] ?? '');
            $catId = (string)($body['category_id'] ?? '');
            $total = (int)($body['total_copies'] ?? 1);
            $book = $this->container->bookService->create($title, $author, $isbn, $catId, $total, $auth['role']);
            $this->respondJson(201, $book);
            return;
        }

        // Match /books/{id}/cover
        if (preg_match('#^/books/([0-9a-fA-F-]+)/cover$#', $path, $matches)) {
            $bookId = $matches[1];
            if ($method === 'GET') {
                $cover = $this->container->bookService->getCover($bookId);
                header('Content-Type: ' . $cover['mime_type']);
                echo $cover['contents'];
                exit;
            }
            if ($method === 'PUT') {
                $fileContent = '';
                $mimeType = 'image/jpeg';
                if (isset($_FILES['file']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
                    $fileContent = (string)file_get_contents($_FILES['file']['tmp_name']);
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mimeType = finfo_file($finfo, $_FILES['file']['tmp_name']) ?: 'image/jpeg';
                    finfo_close($finfo);
                } else {
                    $fileContent = (string)file_get_contents('php://input');
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mimeType = finfo_buffer($finfo, $fileContent) ?: 'image/jpeg';
                    finfo_close($finfo);
                }
                $this->container->bookService->uploadCover($bookId, $fileContent, $mimeType, $auth['role']);
                http_response_code(200);
                echo json_encode(['status' => 'ok']);
                exit;
            }
            if ($method === 'DELETE') {
                $this->container->bookService->deleteCover($bookId, $auth['role']);
                http_response_code(204);
                exit;
            }
        }

        // Match /books/{id}
        if (preg_match('#^/books/([0-9a-fA-F-]+)$#', $path, $matches)) {
            $bookId = $matches[1];
            if ($method === 'GET') {
                $book = $this->container->bookService->get($bookId);
                $this->respondJson(200, $book);
                return;
            }
            if ($method === 'PUT') {
                $body = $this->getJsonBody();
                $title = (string)($body['title'] ?? '');
                $author = (string)($body['author'] ?? '');
                $catId = (string)($body['category_id'] ?? '');
                $total = (int)($body['total_copies'] ?? 1);
                $book = $this->container->bookService->update($bookId, $title, $author, $catId, $total, $auth['role']);
                $this->respondJson(200, $book);
                return;
            }
            if ($method === 'DELETE') {
                $this->container->bookService->softDelete($bookId, $auth['role']);
                http_response_code(204);
                exit;
            }
        }

        // 6. Members
        if ($method === 'GET' && $path === '/members') {
            $q = isset($_GET['q']) ? (string)$_GET['q'] : null;
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $size = isset($_GET['size']) ? (int)$_GET['size'] : 20;
            $result = $this->container->memberService->search($q, $page, $size);
            $this->respondJson(200, $result);
            return;
        }

        if ($method === 'POST' && $path === '/members') {
            $body = $this->getJsonBody();
            $fullName = (string)($body['full_name'] ?? '');
            $doc = (string)($body['document_number'] ?? '');
            $member = $this->container->memberService->create($fullName, $doc);
            $this->respondJson(201, $member);
            return;
        }

        if (preg_match('#^/members/([0-9a-fA-F-]+)$#', $path, $matches)) {
            $memberId = $matches[1];
            if ($method === 'GET') {
                $member = $this->container->memberService->get($memberId);
                $this->respondJson(200, $member);
                return;
            }
        }

        // 7. Loans
        if ($method === 'POST' && $path === '/loans') {
            $body = $this->getJsonBody();
            $memberId = (string)($body['member_id'] ?? '');
            $items = (array)($body['items'] ?? []);
            $loan = $this->container->lendBooksService->execute($memberId, $auth['user_id'], $items);
            $this->respondJson(201, $loan);
            return;
        }

        if ($method === 'GET' && $path === '/loans/overdue') {
            $result = $this->container->loanQueryService->listOverdue();
            $this->respondJson(200, $result);
            return;
        }

        if ($method === 'GET' && $path === '/loans') {
            $from = isset($_GET['from']) ? (string)$_GET['from'] : null;
            $to = isset($_GET['to']) ? (string)$_GET['to'] : null;
            $status = isset($_GET['status']) ? (string)$_GET['status'] : null;
            $memberId = isset($_GET['member_id']) ? (string)$_GET['member_id'] : null;
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $size = isset($_GET['size']) ? (int)$_GET['size'] : 20;
            $result = $this->container->loanQueryService->listLoans($from, $to, $status, $memberId, $page, $size);
            $this->respondJson(200, $result);
            return;
        }

        // Match /loans/{loanId}/items/{itemId}/return
        if (preg_match('#^/loans/([0-9a-fA-F-]+)/items/([0-9a-fA-F-]+)/return$#', $path, $matches)) {
            $loanId = $matches[1];
            $itemId = $matches[2];
            if ($method === 'POST') {
                $result = $this->container->returnItemService->execute($loanId, $itemId);
                $this->respondJson(200, $result);
                return;
            }
        }

        // Match /loans/{id}
        if (preg_match('#^/loans/([0-9a-fA-F-]+)$#', $path, $matches)) {
            $loanId = $matches[1];
            if ($method === 'GET') {
                $loan = $this->container->loanQueryService->getLoan($loanId);
                $this->respondJson(200, $loan);
                return;
            }
        }

        // 8. Reports (admin only)
        if ($method === 'GET' && $path === '/reports/most-borrowed') {
            $from = (string)($_GET['from'] ?? '');
            $to = (string)($_GET['to'] ?? '');
            $report = $this->container->reportService->execute($from, $to, $auth['role']);
            $this->respondJson(200, $report);
            return;
        }

        // Not Found
        $this->respondError(404, 'NOT_FOUND', 'Ruta no encontrada.');
    }

    /**
     * @return array{user_id: string, role: Role}
     */
    private function authenticate(): array
    {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (!preg_match('/^Bearer\s+(.*)$/i', $authHeader, $matches)) {
            throw new InvalidCredentialsException('Token de autenticación requerido.');
        }

        $token = trim($matches[1]);
        $verified = $this->container->tokenIssuer->verify($token);

        return [
            'user_id' => $verified['user_id'],
            'role' => new Role($verified['role']),
        ];
    }

    private function getJsonBody(): array
    {
        $input = file_get_contents('php://input');
        if (!$input) {
            return [];
        }
        $data = json_decode($input, true);
        return is_array($data) ? $data : [];
    }

    private function respondJson(int $statusCode, array $data): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * @param array<int, array{field: string, issue: string}> $details
     */
    private function respondError(int $statusCode, string $code, string $message, array $details = []): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'code' => $code,
            'message' => $message,
            'details' => $details,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}
