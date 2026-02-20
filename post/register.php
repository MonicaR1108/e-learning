<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

if (!empty($_SESSION['user_id'])) {
    redirect('dashboard.php');
}

$errors = [];
$success = '';

$form = [
    'full_name' => '',
    'email' => '',
    'phone' => '',
    'gender' => '',
    'course' => '',
    'address' => '',
    'about' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($form as $key => $value) {
        $form[$key] = clean_input($_POST[$key] ?? '');
    }

    $password = (string) ($_POST['password'] ?? '');

    if ($form['full_name'] === '' || strlen($form['full_name']) < 3) {
        $errors[] = 'Full name must be at least 3 characters.';
    }

    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please provide a valid email address.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if (!preg_match('/^[0-9+\\-() ]{7,20}$/', $form['phone'])) {
        $errors[] = 'Please provide a valid phone number.';
    }

    if (!in_array($form['gender'], ['Male', 'Female', 'Other'], true)) {
        $errors[] = 'Please select a valid gender.';
    }

    $allowedCourses = ['PHP Full Stack', 'Frontend Development', 'Backend Development', 'Data Structures', 'UI/UX Basics'];
    if (!in_array($form['course'], $allowedCourses, true)) {
        $errors[] = 'Please select a valid course.';
    }

    if ($form['address'] === '') {
        $errors[] = 'Address is required.';
    }

    if ($form['about'] === '') {
        $errors[] = 'About section is required.';
    }

    $conn = db();
    if ($form['email'] !== '') {
        $stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $form['email']);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = 'Email address is already registered.';
        }
        $stmt->close();
    }

    $photo = upload_file(
        $_FILES['profile_photo'] ?? [],
        'uploads',
        ['jpg', 'jpeg', 'png', 'webp'],
        2 * 1024 * 1024,
        $errors,
        'Profile photo'
    );

    $resume = upload_file(
        $_FILES['resume'] ?? [],
        'uploads',
        ['pdf', 'doc', 'docx'],
        5 * 1024 * 1024,
        $errors,
        'Resume'
    );

    $cover = upload_file(
        $_FILES['cover_letter'] ?? [],
        'uploads',
        ['pdf', 'doc', 'docx'],
        5 * 1024 * 1024,
        $errors,
        'Cover letter'
    );

    if (empty($errors) && $photo && $resume && $cover) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare(
            'INSERT INTO users (full_name, email, password_hash, phone, gender, course, address, about, profile_photo, resume_file, cover_letter_file)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->bind_param(
            'sssssssssss',
            $form['full_name'],
            $form['email'],
            $passwordHash,
            $form['phone'],
            $form['gender'],
            $form['course'],
            $form['address'],
            $form['about'],
            $photo['path'],
            $resume['path'],
            $cover['path']
        );

        if ($stmt->execute()) {
            $success = 'Registration successful. You can now log in.';
            $form = array_map(static fn () => '', $form);
        } else {
            $errors[] = 'Registration failed. Please try again.';
            remove_local_file($photo['path']);
            remove_local_file($resume['path']);
            remove_local_file($cover['path']);
        }

        $stmt->close();
    } else {
        if ($photo && !empty($errors)) {
            remove_local_file($photo['path']);
        }
        if ($resume && !empty($errors)) {
            remove_local_file($resume['path']);
        }
        if ($cover && !empty($errors)) {
            remove_local_file($cover['path']);
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Course Enrollment</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="auth-wrapper">
    <form id="registerForm" class="card" method="post" enctype="multipart/form-data" novalidate>
        <h1>Create Account</h1>
        <p class="sub">Register for your learning dashboard</p>

        <?php if (!empty($errors)): ?>
            <div class="alert error">
                <?php foreach ($errors as $error): ?>
                    <p><?= e($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($success !== ''): ?>
            <div class="alert success"><p><?= e($success) ?></p></div>
        <?php endif; ?>

        <div class="grid-2">
            <label>Full Name
                <input type="text" name="full_name" id="full_name" value="<?= e($form['full_name']) ?>" required>
            </label>
            <label>Email
                <input type="email" name="email" id="email" value="<?= e($form['email']) ?>" required>
                <small id="emailStatus" class="muted"></small>
            </label>
        </div>

        <div class="grid-2">
            <label>Password
                <input type="password" name="password" id="password" required minlength="8">
            </label>
            <label>Phone
                <input type="text" name="phone" id="phone" value="<?= e($form['phone']) ?>" required>
            </label>
        </div>

        <div class="grid-2">
            <label>Gender
                <select name="gender" id="gender" required>
                    <option value="">Select</option>
                    <?php foreach (['Male', 'Female', 'Other'] as $gender): ?>
                        <option value="<?= e($gender) ?>" <?= $form['gender'] === $gender ? 'selected' : '' ?>><?= e($gender) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Course
                <select name="course" id="course" required>
                    <option value="">Select Course</option>
                    <?php foreach (['PHP Full Stack', 'Frontend Development', 'Backend Development', 'Data Structures', 'UI/UX Basics'] as $course): ?>
                        <option value="<?= e($course) ?>" <?= $form['course'] === $course ? 'selected' : '' ?>><?= e($course) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <label>Address
            <textarea name="address" id="address" rows="2" required><?= e($form['address']) ?></textarea>
        </label>

        <label>About
            <textarea name="about" id="about" rows="3" required><?= e($form['about']) ?></textarea>
        </label>

        <div class="grid-3">
            <label>Profile Photo
                <input type="file" name="profile_photo" id="profile_photo" accept=".jpg,.jpeg,.png,.webp" required>
            </label>
            <label>Resume
                <input type="file" name="resume" id="resume" accept=".pdf,.doc,.docx" required>
            </label>
            <label>Cover Letter
                <input type="file" name="cover_letter" id="cover_letter" accept=".pdf,.doc,.docx" required>
            </label>
        </div>

        <button type="submit" class="btn">Register</button>
        <p class="muted">Already have an account? <a href="login.php">Login</a></p>
    </form>
</div>
<script src="../assets/javascript/register.js"></script>
</body>
</html>