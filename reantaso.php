<?php
/*
📘 Basic Borrow & Return System (PHP + SQLite)

This is a super simple demo system where:
- Students can be added (default type: "free").
- Items can be added to a catalog with available quantity.
- Students can borrow and return items.
- Everything is displayed in simple tables.

How to use:
1. Save this file as `borrow_return.php` in your PHP server directory.
2. Open in your browser: `http://localhost/borrow_return.php`
3. Start adding students, items, and try borrowing + returning.

⚠️ Notes:
- This is for learning/demo purposes only.
- No login or security features yet.
- Extend with authentication, permissions, and validation before real use.
*/

// --- Database setup (SQLite) ---
$dbFile = __DIR__ . '/borrow_return.sqlite';
$initNeeded = !file_exists($dbFile);
$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if ($initNeeded) {
    // Create tables for students, items, and borrow records
    $pdo->exec("CREATE TABLE students (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        type TEXT NOT NULL DEFAULT 'free',
        created_at TEXT DEFAULT (datetime('now'))
    )");

    $pdo->exec("CREATE TABLE items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        qty INTEGER NOT NULL DEFAULT 1,
        created_at TEXT DEFAULT (datetime('now'))
    )");

    $pdo->exec("CREATE TABLE borrows (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        student_id INTEGER NOT NULL,
        item_id INTEGER NOT NULL,
        borrow_date TEXT DEFAULT (datetime('now')),
        return_date TEXT NULL,
        returned INTEGER NOT NULL DEFAULT 0,
        FOREIGN KEY(student_id) REFERENCES students(id),
        FOREIGN KEY(item_id) REFERENCES items(id)
    )");

    // Add some sample data so you’re not starting from scratch
    $pdo->exec("INSERT INTO students (name, type) VALUES
        ('Alice Santos', 'free'),
        ('Bob Reyes', 'free'),
        ('Charlie Dela Cruz', 'premium')");

    $pdo->exec("INSERT INTO items (title, qty) VALUES
        ('Intro to PHP - Textbook', 3),
        ('Calculator', 5),
        ('Projector Remote', 1)");
}

// --- Helpers ---
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

function fetchAll($pdo, $sql, $params = []) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// --- Actions ---
$action = $_POST['action'] ?? null;

// Add student
if ($action === 'add_student') {
    $name = trim($_POST['name'] ?? '');
    $type = trim($_POST['type'] ?? 'free');
    if ($name !== '') {
        $stmt = $pdo->prepare('INSERT INTO students (name, type) VALUES (?, ?)');
        $stmt->execute([$name, $type]);
    }
    redirect($_SERVER['PHP_SELF']);
}

// Add item
if ($action === 'add_item') {
    $title = trim($_POST['title'] ?? '');
    $qty = max(0, (int)($_POST['qty'] ?? 1));
    if ($title !== '') {
        $stmt = $pdo->prepare('INSERT INTO items (title, qty) VALUES (?, ?)');
        $stmt->execute([$title, $qty]);
    }
    redirect($_SERVER['PHP_SELF']);
}

// Borrow item
if ($action === 'borrow') {
    $student_id = (int)($_POST['student_id'] ?? 0);
    $item_id = (int)($_POST['item_id'] ?? 0);
    if ($student_id && $item_id) {
        // check availability
        $stmt = $pdo->prepare('SELECT qty FROM items WHERE id = ?');
        $stmt->execute([$item_id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($item && $item['qty'] > 0) {
            $pdo->prepare('INSERT INTO borrows (student_id, item_id) VALUES (?, ?)')
                ->execute([$student_id, $item_id]);
            $pdo->prepare('UPDATE items SET qty = qty - 1 WHERE id = ?')
                ->execute([$item_id]);
        }
    }
    redirect($_SERVER['PHP_SELF']);
}

// Return item
if ($action === 'return') {
    $borrow_id = (int)($_POST['borrow_id'] ?? 0);
    if ($borrow_id) {
        $stmt = $pdo->prepare('SELECT item_id, returned FROM borrows WHERE id = ?');
        $stmt->execute([$borrow_id]);
        $b = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($b && !$b['returned']) {
            $pdo->prepare('UPDATE borrows SET returned = 1, return_date = datetime(\'now\') WHERE id = ?')
                ->execute([$borrow_id]);
            $pdo->prepare('UPDATE items SET qty = qty + 1 WHERE id = ?')
                ->execute([$b['item_id']]);
        }
    }
    redirect($_SERVER['PHP_SELF']);
}

// --- Fetch data for display ---
$students = fetchAll($pdo, 'SELECT * FROM students ORDER BY name');
$items = fetchAll($pdo, 'SELECT * FROM items ORDER BY title');
$borrows = fetchAll($pdo, 'SELECT b.*, s.name AS student_name, s.type AS student_type, i.title AS item_title
    FROM borrows b
    JOIN students s ON s.id = b.student_id
    JOIN items i ON i.id = b.item_id
    ORDER BY b.borrow_date DESC');

// --- HTML UI ---
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>📚 Borrow & Return System</title>
    <style>
        body{font-family:Arial, sans-serif;margin:20px;line-height:1.6}
        h1,h2,h3{color:#333}
        .card{border:1px solid #ddd;padding:12px;margin:8px;border-radius:6px;background:#fafafa}
        .row{display:flex;gap:12px;flex-wrap:wrap}
        table{width:100%;border-collapse:collapse;margin-top:10px}
        th,td{border:1px solid #eee;padding:8px;text-align:left}
        th{background:#f9f9f9}
        form{margin:0}
        .small{font-size:0.9em;color:#555}
        .badge{display:inline-block;padding:2px 6px;border-radius:4px;background:#eee;font-size:0.85em}
        button{cursor:pointer;padding:5px 10px;border:1px solid #ccc;background:#fff;border-radius:4px}
        button:hover{background:#f0f0f0}
    </style>
</head>
<body>
    <h1>📚 Borrow & Return</h1>
    <p class="small">Manage students, items, and their borrow/return records. Students are <strong>free</strong> type by default.</p>

    <div class="row">
        <div class="card" style="flex:1">
            <h3>➕ Add Student</h3>
            <form method="post">
                <input type="hidden" name="action" value="add_student">
                <label>Name<br><input name="name" required></label><br>
                <label>Type<br>
                    <select name="type">
                        <option value="free">free</option>
                        <option value="premium">premium</option>
                    </select>
                </label><br>
                <button type="submit">Add Student</button>
            </form>
        </div>

        <div class="card" style="flex:1">
            <h3>📦 Add Item</h3>
            <form method="post">
                <input type="hidden" name="action" value="add_item">
                <label>Title<br><input name="title" required></label><br>
                <label>Quantity<br><input name="qty" type="number" min="0" value="1" required></label><br>
                <button type="submit">Add Item</button>
            </form>
        </div>

        <div class="card" style="flex:1">
            <h3>📥 Borrow Item</h3>
            <form method="post">
                <input type="hidden" name="action" value="borrow">
                <label>Student<br>
                    <select name="student_id" required>
                        <option value="">-- choose student --</option>
                        <?php foreach($students as $s): ?>
                            <option value="<?=htmlspecialchars($s['id'])?>"><?=htmlspecialchars($s['name'])?> (<?=htmlspecialchars($s['type'])?>)</option>
                        <?php endforeach; ?>
                    </select>
                </label><br>
                <label>Item<br>
                    <select name="item_id" required>
                        <option value="">-- choose item --</option>
                        <?php foreach($items as $it): ?>
                            <option value="<?=htmlspecialchars($it['id'])?>"><?=htmlspecialchars($it['title'])?> — Qty: <?=htmlspecialchars($it['qty'])?></option>
                        <?php endforeach; ?>
                    </select>
                </label><br>
                <button type="submit">Borrow</button>
            </form>
        </div>
    </div>

    <h2>📑 Current Borrows</h2>
    <table>
        <thead>
            <tr><th>#</th><th>Student</th><th>Type</th><th>Item</th><th>Borrowed At</th><th>Returned</th><th>Action</th></tr>
        </thead>
        <tbody>
            <?php if (count($borrows) === 0): ?>
                <tr><td colspan="7" class="small">No borrow records yet. Try borrowing an item above!</td></tr>
            <?php else: foreach($borrows as $b): ?>
                <tr>
                    <td><?=htmlspecialchars($b['id'])?></td>
                    <td><?=htmlspecialchars($b['student_name'])?></td>
                    <td><span class="badge"><?=htmlspecialchars($b['student_type'])?></span></td>
                    <td><?=htmlspecialchars($b['item_title'])?></td>
                    <td><?=htmlspecialchars($b['borrow_date'])?></td>
                    <td><?= $b['returned'] ? htmlspecialchars($b['return_date']) : 'Not yet' ?></td>
                    <td>
                        <?php if (!$b['returned']): ?>
                            <form method="post" style="display:inline">
                                <input type="hidden" name="action" value="return">
                                <input type="hidden" name="borrow_id" value="<?=htmlspecialchars($b['id'])?>">
                                <button type="submit">✔ Return</button>
                            </form>
                        <?php else: ?>
                            <span class="small">✅ Returned</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>

    <h2>📦 Items</h2>
    <table>
        <thead><tr><th>ID</th><th>Title</th><th>Available Qty</th></tr></thead>
        <tbody>
            <?php foreach($items as $it): ?>
                <tr>
                    <td><?=htmlspecialchars($it['id'])?></td>
                    <td><?=htmlspecialchars($it['title'])?></td>
                    <td><?=htmlspecialchars($it['qty'])?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2>👩‍🎓 Students</h2>
    <table>
        <thead><tr><th>ID</th><th>Name</th><th>Type</th></tr></thead>
        <tbody>
            <?php foreach($students as $s): ?>
                <tr>
                    <td><?=htmlspecialchars($s['id'])?></td>
                    <td><?=htmlspecialchars($s['name'])?></td>
                    <td><?=htmlspecialchars($s['type'])?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <footer style="margin-top:24px;font-size:0.85em;color:#666">✨ Simple demo — improve it with login, validation, and better design for real-world use.</footer>
</body>
</html>
