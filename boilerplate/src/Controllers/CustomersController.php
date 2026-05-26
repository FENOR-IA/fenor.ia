<?php

class CustomersController
{
    public function index(): void
    {
        $db     = Database::get();
        $search = trim($_GET['q'] ?? '');

        if ($search) {
            $stmt = $db->prepare("
                SELECT * FROM customers
                WHERE active = true AND (name ILIKE ? OR email ILIKE ? OR phone ILIKE ? OR document ILIKE ?)
                ORDER BY name
            ");
            $like = "%$search%";
            $stmt->execute([$like, $like, $like, $like]);
        } else {
            $stmt = $db->query('SELECT * FROM customers WHERE active = true ORDER BY name');
        }
        $customers = $stmt->fetchAll();

        $pageTitle  = 'Customers';
        $activePage = 'customers';
        require ROOT . '/views/customers/list.php';
    }

    public function create(): void
    {
        $pageTitle  = 'New Customer';
        $activePage = 'customers';
        $customer   = [];
        $error      = Session::getFlash('error');
        require ROOT . '/views/customers/form.php';
    }

    public function store(): void
    {
        $data   = $this->formData();
        $errors = $this->validate($data);

        if ($errors) {
            Session::flash('error', implode(' ', $errors));
            header('Location: /customers/new');
            exit;
        }

        $db = Database::get();
        $db->prepare("
            INSERT INTO customers (name, email, phone, document, address, notes)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([
            $data['name'], $data['email'], $data['phone'],
            $data['document'], $data['address'], $data['notes'],
        ]);

        $id = $db->lastInsertId() ?: $db->query("SELECT currval(pg_get_serial_sequence('customers','id'))")->fetchColumn();
        Session::flash('success', 'Customer saved successfully.');
        header("Location: /customers/$id");
        exit;
    }

    public function show(int $id): void
    {
        $db   = Database::get();
        $stmt = $db->prepare('SELECT * FROM customers WHERE id = ?');
        $stmt->execute([$id]);
        $customer = $stmt->fetch();

        if (!$customer) {
            http_response_code(404);
            echo '<h1>Customer not found</h1>';
            exit;
        }

        $stmt = $db->prepare(
            'SELECT * FROM transactions WHERE customer_id = ? ORDER BY entry_date DESC LIMIT 20'
        );
        $stmt->execute([$id]);
        $transactions = $stmt->fetchAll();

        $success    = Session::getFlash('success');
        $pageTitle  = $customer['name'];
        $activePage = 'customers';
        require ROOT . '/views/customers/view.php';
    }

    public function edit(int $id): void
    {
        $db   = Database::get();
        $stmt = $db->prepare('SELECT * FROM customers WHERE id = ?');
        $stmt->execute([$id]);
        $customer = $stmt->fetch();

        if (!$customer) { http_response_code(404); echo '<h1>Not found</h1>'; exit; }

        $pageTitle  = 'Edit Customer';
        $activePage = 'customers';
        $error      = Session::getFlash('error');
        require ROOT . '/views/customers/form.php';
    }

    public function update(int $id): void
    {
        $data   = $this->formData();
        $errors = $this->validate($data);

        if ($errors) {
            Session::flash('error', implode(' ', $errors));
            header("Location: /customers/$id/edit");
            exit;
        }

        $db = Database::get();
        $db->prepare("
            UPDATE customers SET name=?, email=?, phone=?, document=?, address=?, notes=?, updated_at=NOW()
            WHERE id=?
        ")->execute([
            $data['name'], $data['email'], $data['phone'],
            $data['document'], $data['address'], $data['notes'], $id,
        ]);

        Session::flash('success', 'Customer updated.');
        header("Location: /customers/$id");
        exit;
    }

    public function delete(int $id): void
    {
        $db = Database::get();
        $db->prepare('UPDATE customers SET active = false WHERE id = ?')->execute([$id]);
        Session::flash('success', 'Customer removed.');
        header('Location: /customers');
        exit;
    }

    private function formData(): array
    {
        return [
            'name'     => trim($_POST['name']     ?? ''),
            'email'    => trim($_POST['email']    ?? ''),
            'phone'    => trim($_POST['phone']    ?? ''),
            'document' => trim($_POST['document'] ?? ''),
            'address'  => trim($_POST['address']  ?? ''),
            'notes'    => trim($_POST['notes']    ?? ''),
        ];
    }

    private function validate(array $data): array
    {
        $errors = [];
        if (empty($data['name'])) $errors[] = 'Name is required.';
        return $errors;
    }
}
