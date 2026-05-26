<?php

class UsersController
{
    public function __construct()
    {
        if (!Session::isAdmin()) {
            http_response_code(403);
            echo '<h1>Access denied</h1>';
            exit;
        }
    }

    public function index(): void
    {
        $db    = Database::get();
        $users = $db->query('SELECT id, name, email, role, active, created_at FROM users ORDER BY name')->fetchAll();

        $pageTitle  = 'Users';
        $activePage = 'users';
        $success    = Session::getFlash('success');
        require ROOT . '/views/users/list.php';
    }

    public function create(): void
    {
        $pageTitle  = 'New User';
        $activePage = 'users';
        $user       = [];
        $error      = Session::getFlash('error');
        require ROOT . '/views/users/form.php';
    }

    public function store(): void
    {
        $data   = $this->formData();
        $errors = $this->validate($data, true);

        if ($errors) {
            Session::flash('error', implode(' ', $errors));
            header('Location: /users/new');
            exit;
        }

        $db = Database::get();
        $db->prepare("
            INSERT INTO users (name, email, password_hash, role)
            VALUES (?, ?, ?, ?)
        ")->execute([
            $data['name'],
            $data['email'],
            password_hash($data['password'], PASSWORD_BCRYPT),
            $data['role'],
        ]);

        Session::flash('success', 'User created successfully.');
        header('Location: /users');
        exit;
    }

    public function edit(int $id): void
    {
        $db   = Database::get();
        $stmt = $db->prepare('SELECT id, name, email, role, active FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $user = $stmt->fetch();

        if (!$user) { http_response_code(404); echo '<h1>Not found</h1>'; exit; }

        $pageTitle  = 'Edit User';
        $activePage = 'users';
        $error      = Session::getFlash('error');
        require ROOT . '/views/users/form.php';
    }

    public function update(int $id): void
    {
        $data   = $this->formData();
        $errors = $this->validate($data, false);

        if ($errors) {
            Session::flash('error', implode(' ', $errors));
            header("Location: /users/$id/edit");
            exit;
        }

        $db = Database::get();

        if (!empty($data['password'])) {
            $db->prepare("UPDATE users SET name=?, email=?, role=?, active=?, password_hash=?, updated_at=NOW() WHERE id=?")
               ->execute([$data['name'], $data['email'], $data['role'], $data['active'], password_hash($data['password'], PASSWORD_BCRYPT), $id]);
        } else {
            $db->prepare("UPDATE users SET name=?, email=?, role=?, active=?, updated_at=NOW() WHERE id=?")
               ->execute([$data['name'], $data['email'], $data['role'], $data['active'], $id]);
        }

        Session::flash('success', 'User updated.');
        header('Location: /users');
        exit;
    }

    private function formData(): array
    {
        return [
            'name'     => trim($_POST['name']     ?? ''),
            'email'    => trim($_POST['email']    ?? ''),
            'password' => $_POST['password'] ?? '',
            'role'     => in_array($_POST['role'] ?? '', ['admin', 'user']) ? $_POST['role'] : 'user',
            'active'   => isset($_POST['active']) ? 'true' : 'false',
        ];
    }

    private function validate(array $data, bool $isNew): array
    {
        $errors = [];
        if (empty($data['name']))  $errors[] = 'Name is required.';
        if (empty($data['email'])) $errors[] = 'Email is required.';
        if ($isNew && empty($data['password'])) $errors[] = 'Password is required.';
        if (!empty($data['password']) && strlen($data['password']) < 6) $errors[] = 'Password must be at least 6 characters.';
        return $errors;
    }
}
