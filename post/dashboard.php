<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_auth();

$conn = db();
$userId = (int) $_SESSION['user_id'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = (string) $_SESSION['csrf_token'];

function fetch_user(mysqli $conn, int $userId): ?array
{
    $stmt = $conn->prepare('SELECT id, full_name, email, phone, gender, course, address, about, profile_photo, resume_file, cover_letter_file, created_at FROM users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc() ?: null;
    $stmt->close();
    return $user;
}

function fetch_projects(mysqli $conn, int $userId): array
{
    $stmt = $conn->prepare(
        'SELECT p.id AS project_id, p.title, p.description, p.technologies, p.created_at,
                pf.id AS file_id, pf.original_name, pf.file_path
         FROM projects p
         LEFT JOIN project_files pf ON pf.project_id = p.id
         WHERE p.user_id = ?
         ORDER BY p.created_at DESC, pf.created_at ASC'
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $projects = [];
    while ($row = $result->fetch_assoc()) {
        $projectId = (int) $row['project_id'];
        if (!isset($projects[$projectId])) {
            $projects[$projectId] = [
                'id' => $projectId,
                'title' => $row['title'],
                'description' => $row['description'],
                'technologies' => $row['technologies'],
                'created_at' => $row['created_at'],
                'files' => [],
            ];
        }

        if (!empty($row['file_id'])) {
            $projects[$projectId]['files'][] = [
                'id' => (int) $row['file_id'],
                'original_name' => $row['original_name'],
                'file_path' => $row['file_path'],
            ];
        }
    }

    $stmt->close();
    return array_values($projects);
}

$errors = [];
$success = '';

if (isset($_GET['export_project'])) {
    $projectId = (int) $_GET['export_project'];

    $stmt = $conn->prepare('SELECT id, title, description, technologies, created_at FROM projects WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->bind_param('ii', $projectId, $userId);
    $stmt->execute();
    $project = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($project) {
        $stmt = $conn->prepare('SELECT original_name, file_path, created_at FROM project_files WHERE project_id = ? ORDER BY created_at ASC');
        $stmt->bind_param('i', $projectId);
        $stmt->execute();
        $files = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=project_' . $projectId . '_export.csv');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Project ID', 'Title', 'Description', 'Technologies', 'Project Created At']);
        fputcsv($output, [$project['id'], $project['title'], $project['description'], $project['technologies'], $project['created_at']]);
        fputcsv($output, []);
        fputcsv($output, ['File Name', 'File Path', 'File Created At']);

        foreach ($files as $file) {
            fputcsv($output, [$file['original_name'], $file['file_path'], $file['created_at']]);
        }

        fclose($output);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrfToken, $token)) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $action = clean_input($_POST['action'] ?? '');

        if ($action === 'update_profile') {
            $fullName = clean_input($_POST['full_name'] ?? '');
            $email = clean_input($_POST['email'] ?? '');
            $phone = clean_input($_POST['phone'] ?? '');
            $gender = clean_input($_POST['gender'] ?? '');
            $course = clean_input($_POST['course'] ?? '');
            $address = clean_input($_POST['address'] ?? '');
            $about = clean_input($_POST['about'] ?? '');

            if ($fullName === '' || strlen($fullName) < 3) {
                $errors[] = 'Full name must be at least 3 characters.';
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Please provide a valid email address.';
            }
            if (!preg_match('/^[0-9+\\-() ]{7,20}$/', $phone)) {
                $errors[] = 'Please provide a valid phone number.';
            }
            if (!in_array($gender, ['Male', 'Female', 'Other'], true)) {
                $errors[] = 'Please select a valid gender.';
            }

            $allowedCourses = ['PHP Full Stack', 'Frontend Development', 'Backend Development', 'Data Structures', 'UI/UX Basics'];
            if (!in_array($course, $allowedCourses, true)) {
                $errors[] = 'Please select a valid course.';
            }
            if ($address === '') {
                $errors[] = 'Address is required.';
            }
            if ($about === '') {
                $errors[] = 'About section is required.';
            }

            $stmt = $conn->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
            $stmt->bind_param('si', $email, $userId);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $errors[] = 'Email is already used by another account.';
            }
            $stmt->close();

            $currentUser = fetch_user($conn, $userId);
            if (!$currentUser) {
                $errors[] = 'User not found.';
            }

            $newPhoto = upload_file_optional(
                $_FILES['profile_photo'] ?? [],
                'uploads',
                ['jpg', 'jpeg', 'png', 'webp'],
                2 * 1024 * 1024,
                $errors,
                'Profile photo'
            );

            $newResume = upload_file_optional(
                $_FILES['resume'] ?? [],
                'uploads',
                ['pdf', 'doc', 'docx'],
                5 * 1024 * 1024,
                $errors,
                'Resume'
            );

            $newCover = upload_file_optional(
                $_FILES['cover_letter'] ?? [],
                'uploads',
                ['pdf', 'doc', 'docx'],
                5 * 1024 * 1024,
                $errors,
                'Cover letter'
            );

            if (empty($errors) && $currentUser) {
                $profilePhotoPath = $newPhoto['path'] ?? $currentUser['profile_photo'];
                $resumePath = $newResume['path'] ?? $currentUser['resume_file'];
                $coverPath = $newCover['path'] ?? $currentUser['cover_letter_file'];

                $stmt = $conn->prepare(
                    'UPDATE users SET full_name = ?, email = ?, phone = ?, gender = ?, course = ?, address = ?, about = ?, profile_photo = ?, resume_file = ?, cover_letter_file = ?, updated_at = NOW() WHERE id = ?'
                );

                $stmt->bind_param(
                    'ssssssssssi',
                    $fullName,
                    $email,
                    $phone,
                    $gender,
                    $course,
                    $address,
                    $about,
                    $profilePhotoPath,
                    $resumePath,
                    $coverPath,
                    $userId
                );

                if ($stmt->execute()) {
                    if ($newPhoto) {
                        remove_local_file((string) $currentUser['profile_photo']);
                    }
                    if ($newResume) {
                        remove_local_file((string) $currentUser['resume_file']);
                    }
                    if ($newCover) {
                        remove_local_file((string) $currentUser['cover_letter_file']);
                    }

                    $_SESSION['user_name'] = $fullName;
                    $_SESSION['user_email'] = $email;
                    $_SESSION['user_course'] = $course;
                    $success = 'Profile updated successfully.';
                } else {
                    $errors[] = 'Could not update profile.';
                    if ($newPhoto) {
                        remove_local_file($newPhoto['path']);
                    }
                    if ($newResume) {
                        remove_local_file($newResume['path']);
                    }
                    if ($newCover) {
                        remove_local_file($newCover['path']);
                    }
                }
                $stmt->close();
            } else {
                if ($newPhoto) {
                    remove_local_file($newPhoto['path']);
                }
                if ($newResume) {
                    remove_local_file($newResume['path']);
                }
                if ($newCover) {
                    remove_local_file($newCover['path']);
                }
            }
        }

        if ($action === 'create_project') {
            $title = clean_input($_POST['title'] ?? '');
            $description = clean_input($_POST['description'] ?? '');
            $technologies = clean_input($_POST['technologies'] ?? '');

            if ($title === '') {
                $errors[] = 'Project title is required.';
            }
            if ($description === '') {
                $errors[] = 'Project description is required.';
            }
            if ($technologies === '') {
                $errors[] = 'Please add technologies used.';
            }

            $filesInput = $_FILES['project_files'] ?? null;
            $fileCount = 0;
            if ($filesInput && is_array($filesInput['name'])) {
                foreach ($filesInput['name'] as $name) {
                    if ($name !== '') {
                        $fileCount++;
                    }
                }
            }
            if ($fileCount === 0) {
                $errors[] = 'Please upload at least one project file.';
            }

            if (empty($errors)) {
                $conn->begin_transaction();
                $uploadedPaths = [];
                try {
                    $stmt = $conn->prepare('INSERT INTO projects (user_id, title, description, technologies) VALUES (?, ?, ?, ?)');
                    $stmt->bind_param('isss', $userId, $title, $description, $technologies);
                    if (!$stmt->execute()) {
                        throw new RuntimeException('Project create failed.');
                    }
                    $projectId = (int) $conn->insert_id;
                    $stmt->close();

                    $allowed = ['pdf', 'doc', 'docx', 'txt', 'csv', 'png', 'jpg', 'jpeg', 'webp', 'zip'];
                    foreach ($filesInput['name'] as $idx => $name) {
                        if ($name === '') {
                            continue;
                        }
                        $single = [
                            'name' => $filesInput['name'][$idx],
                            'type' => $filesInput['type'][$idx],
                            'tmp_name' => $filesInput['tmp_name'][$idx],
                            'error' => $filesInput['error'][$idx],
                            'size' => $filesInput['size'][$idx],
                        ];

                        $fileErrors = [];
                        $uploaded = upload_file($single, 'uploads/projects', $allowed, 10 * 1024 * 1024, $fileErrors, 'Project file');

                        if (!$uploaded) {
                            throw new RuntimeException($fileErrors[0] ?? 'Invalid project file.');
                        }

                        $uploadedPaths[] = $uploaded['path'];

                        $stmt = $conn->prepare('INSERT INTO project_files (project_id, original_name, stored_name, file_path, mime_type, file_size) VALUES (?, ?, ?, ?, ?, ?)');
                        $stmt->bind_param(
                            'issssi',
                            $projectId,
                            $uploaded['original_name'],
                            $uploaded['stored_name'],
                            $uploaded['path'],
                            $uploaded['mime_type'],
                            $uploaded['size']
                        );
                        if (!$stmt->execute()) {
                            throw new RuntimeException('Could not save project file metadata.');
                        }
                        $stmt->close();
                    }

                    $conn->commit();
                    $success = 'Project created successfully.';
                } catch (Throwable $ex) {
                    $conn->rollback();
                    foreach ($uploadedPaths as $path) {
                        remove_local_file($path);
                    }
                    $errors[] = $ex->getMessage();
                }
            }
        }

        if ($action === 'update_project') {
            $projectId = (int) ($_POST['project_id'] ?? 0);
            $title = clean_input($_POST['title'] ?? '');
            $description = clean_input($_POST['description'] ?? '');
            $technologies = clean_input($_POST['technologies'] ?? '');

            if ($title === '') {
                $errors[] = 'Project title is required.';
            }
            if ($description === '') {
                $errors[] = 'Project description is required.';
            }
            if ($technologies === '') {
                $errors[] = 'Technologies are required.';
            }

            $stmt = $conn->prepare('SELECT id FROM projects WHERE id = ? AND user_id = ? LIMIT 1');
            $stmt->bind_param('ii', $projectId, $userId);
            $stmt->execute();
            $project = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$project) {
                $errors[] = 'Project not found.';
            }

            if (empty($errors)) {
                $conn->begin_transaction();
                $uploadedPaths = [];
                try {
                    $stmt = $conn->prepare('UPDATE projects SET title = ?, description = ?, technologies = ?, updated_at = NOW() WHERE id = ? AND user_id = ?');
                    $stmt->bind_param('sssii', $title, $description, $technologies, $projectId, $userId);
                    if (!$stmt->execute()) {
                        throw new RuntimeException('Could not update project.');
                    }
                    $stmt->close();

                    $filesInput = $_FILES['project_files_new'] ?? null;
                    if ($filesInput && is_array($filesInput['name'])) {
                        $allowed = ['pdf', 'doc', 'docx', 'txt', 'csv', 'png', 'jpg', 'jpeg', 'webp', 'zip'];
                        foreach ($filesInput['name'] as $idx => $name) {
                            if ($name === '') {
                                continue;
                            }

                            $single = [
                                'name' => $filesInput['name'][$idx],
                                'type' => $filesInput['type'][$idx],
                                'tmp_name' => $filesInput['tmp_name'][$idx],
                                'error' => $filesInput['error'][$idx],
                                'size' => $filesInput['size'][$idx],
                            ];

                            $fileErrors = [];
                            $uploaded = upload_file($single, 'uploads/projects', $allowed, 10 * 1024 * 1024, $fileErrors, 'Project file');
                            if (!$uploaded) {
                                throw new RuntimeException($fileErrors[0] ?? 'Invalid project file.');
                            }

                            $uploadedPaths[] = $uploaded['path'];

                            $stmt = $conn->prepare('INSERT INTO project_files (project_id, original_name, stored_name, file_path, mime_type, file_size) VALUES (?, ?, ?, ?, ?, ?)');
                            $stmt->bind_param(
                                'issssi',
                                $projectId,
                                $uploaded['original_name'],
                                $uploaded['stored_name'],
                                $uploaded['path'],
                                $uploaded['mime_type'],
                                $uploaded['size']
                            );
                            if (!$stmt->execute()) {
                                throw new RuntimeException('Could not save new project file.');
                            }
                            $stmt->close();
                        }
                    }

                    $conn->commit();
                    $success = 'Project updated successfully.';
                } catch (Throwable $ex) {
                    $conn->rollback();
                    foreach ($uploadedPaths as $path) {
                        remove_local_file($path);
                    }
                    $errors[] = $ex->getMessage();
                }
            }
        }

        if ($action === 'delete_project') {
            $projectId = (int) ($_POST['project_id'] ?? 0);

            $stmt = $conn->prepare('SELECT id FROM projects WHERE id = ? AND user_id = ? LIMIT 1');
            $stmt->bind_param('ii', $projectId, $userId);
            $stmt->execute();
            $project = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$project) {
                $errors[] = 'Project not found.';
            } else {
                $stmt = $conn->prepare('SELECT file_path FROM project_files WHERE project_id = ?');
                $stmt->bind_param('i', $projectId);
                $stmt->execute();
                $files = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();

                $stmt = $conn->prepare('DELETE FROM projects WHERE id = ? AND user_id = ?');
                $stmt->bind_param('ii', $projectId, $userId);
                if ($stmt->execute()) {
                    foreach ($files as $file) {
                        remove_local_file($file['file_path']);
                    }
                    $success = 'Project removed successfully.';
                } else {
                    $errors[] = 'Could not remove project.';
                }
                $stmt->close();
            }
        }

        if ($action === 'delete_project_file') {
            $fileId = (int) ($_POST['file_id'] ?? 0);

            $stmt = $conn->prepare(
                'SELECT pf.file_path
                 FROM project_files pf
                 INNER JOIN projects p ON p.id = pf.project_id
                 WHERE pf.id = ? AND p.user_id = ? LIMIT 1'
            );
            $stmt->bind_param('ii', $fileId, $userId);
            $stmt->execute();
            $file = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$file) {
                $errors[] = 'File not found.';
            } else {
                $stmt = $conn->prepare('DELETE pf FROM project_files pf INNER JOIN projects p ON p.id = pf.project_id WHERE pf.id = ? AND p.user_id = ?');
                $stmt->bind_param('ii', $fileId, $userId);
                if ($stmt->execute()) {
                    remove_local_file($file['file_path']);
                    $success = 'File removed successfully.';
                } else {
                    $errors[] = 'Could not remove file.';
                }
                $stmt->close();
            }
        }
    }
}

$user = fetch_user($conn, $userId);
if (!$user) {
    redirect('logout.php');
}

$projects = fetch_projects($conn, $userId);
$courseLinks = [
    'PHP Full Stack' => 'https://www.php.net/docs.php',
    'Frontend Development' => 'https://developer.mozilla.org/en-US/docs/Learn/Front-end_web_developer',
    'Backend Development' => 'https://roadmap.sh/backend',
    'Data Structures' => 'https://www.geeksforgeeks.org/data-structures/',
    'UI/UX Basics' => 'https://www.interaction-design.org/literature/topics/ui-design',
];
$courseLink = $courseLinks[$user['course']] ?? 'https://developer.mozilla.org/';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Course Enrollment</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
<header class="topbar">
    <div class="brand">Course Enrollment</div>
    <nav>
        <button type="button" class="nav-btn active" data-section="home">Home</button>
        <button type="button" class="nav-btn" data-section="profile">Profile</button>
        <button type="button" class="nav-btn" data-section="projects">Projects</button>
        <button type="button" class="nav-btn" data-section="course">Course</button>
        <a class="nav-link" href="logout.php">Logout</a>
    </nav>
</header>

<main class="dashboard-wrapper">
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

    <section class="dashboard-section active" id="section-home">
        <div class="panel">
            <h2>Welcome, <?= e((string) $user['full_name']) ?></h2>
            <p>You are enrolled in <strong><?= e((string) $user['course']) ?></strong>.</p>
        </div>
    </section>

    <section class="dashboard-section" id="section-profile">
        <div class="panel">
            <h2>Profile Details</h2>
            <div class="profile-layout">
                <div class="profile-display">
                    <img class="avatar" src="../<?= e((string) $user['profile_photo']) ?>" alt="Profile photo">
                    <p><strong>Name:</strong> <?= e((string) $user['full_name']) ?></p>
                    <p><strong>Email:</strong> <?= e((string) $user['email']) ?></p>
                    <p><strong>Phone:</strong> <?= e((string) $user['phone']) ?></p>
                    <p><strong>Gender:</strong> <?= e((string) $user['gender']) ?></p>
                    <p><strong>Course:</strong> <?= e((string) $user['course']) ?></p>
                    <p><strong>Address:</strong> <?= e((string) $user['address']) ?></p>
                    <p><strong>About:</strong> <?= e((string) $user['about']) ?></p>
                    <p><a href="../<?= e((string) $user['resume_file']) ?>" target="_blank" rel="noopener">View Resume</a></p>
                    <p><a href="../<?= e((string) $user['cover_letter_file']) ?>" target="_blank" rel="noopener">View Cover Letter</a></p>
                </div>

                <form class="profile-form" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="action" value="update_profile">

                    <label>Full Name
                        <input type="text" name="full_name" value="<?= e((string) $user['full_name']) ?>" required>
                    </label>
                    <label>Email
                        <input type="email" name="email" value="<?= e((string) $user['email']) ?>" required>
                    </label>
                    <label>Phone
                        <input type="text" name="phone" value="<?= e((string) $user['phone']) ?>" required>
                    </label>
                    <label>Gender
                        <select name="gender" required>
                            <?php foreach (['Male', 'Female', 'Other'] as $gender): ?>
                                <option value="<?= e($gender) ?>" <?= $user['gender'] === $gender ? 'selected' : '' ?>><?= e($gender) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Course
                        <select name="course" required>
                            <?php foreach (['PHP Full Stack', 'Frontend Development', 'Backend Development', 'Data Structures', 'UI/UX Basics'] as $course): ?>
                                <option value="<?= e($course) ?>" <?= $user['course'] === $course ? 'selected' : '' ?>><?= e($course) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Address
                        <textarea name="address" rows="2" required><?= e((string) $user['address']) ?></textarea>
                    </label>
                    <label>About
                        <textarea name="about" rows="3" required><?= e((string) $user['about']) ?></textarea>
                    </label>
                    <label>Replace Photo (optional)
                        <input type="file" name="profile_photo" accept=".jpg,.jpeg,.png,.webp">
                    </label>
                    <label>Replace Resume (optional)
                        <input type="file" name="resume" accept=".pdf,.doc,.docx">
                    </label>
                    <label>Replace Cover Letter (optional)
                        <input type="file" name="cover_letter" accept=".pdf,.doc,.docx">
                    </label>

                    <button type="submit" class="btn">Update Profile</button>
                </form>
            </div>
        </div>
    </section>

    <section class="dashboard-section" id="section-projects">
        <div class="panel">
            <h2>Create Project</h2>
            <form class="project-form" method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action" value="create_project">

                <label>Project Title
                    <input type="text" name="title" required>
                </label>
                <label>Description
                    <textarea name="description" rows="3" required></textarea>
                </label>
                <label>Technologies Used</label>
                <div class="tag-box" id="techTagBox">
                    <input type="text" id="techInput" placeholder="Type and press Enter (e.g. PHP, MySQL)" autocomplete="off">
                    <div id="techSuggestions" class="suggestions"></div>
                </div>
                <input type="hidden" name="technologies" id="technologiesField" required>

                <label>Import Files
                    <input type="file" name="project_files[]" multiple required>
                </label>

                <button type="submit" class="btn">Create Project</button>
            </form>
        </div>

        <div class="panel">
            <h2>Your Projects</h2>
            <?php if (empty($projects)): ?>
                <p class="muted">No projects yet.</p>
            <?php else: ?>
                <div class="project-list">
                    <?php foreach ($projects as $project): ?>
                        <article class="project-card">
                            <div class="project-header">
                                <h3><?= e((string) $project['title']) ?></h3>
                                <div class="project-actions">
                                    <button type="button" class="btn-inline edit-toggle" data-project-id="<?= (int) $project['id'] ?>">Edit</button>
                                    <a class="btn-inline" href="dashboard.php?export_project=<?= (int) $project['id'] ?>">Export</a>
                                    <form method="post" onsubmit="return confirm('Remove this full project?');">
                                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                        <input type="hidden" name="action" value="delete_project">
                                        <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">
                                        <button type="submit" class="btn-inline danger">Remove</button>
                                    </form>
                                </div>
                            </div>
                            <p><?= e((string) $project['description']) ?></p>
                            <p><strong>Technologies:</strong> <?= e((string) $project['technologies']) ?></p>

                            <div class="project-edit-wrap" id="edit-project-<?= (int) $project['id'] ?>" hidden>
                                <form method="post" enctype="multipart/form-data" class="project-edit-form">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                    <input type="hidden" name="action" value="update_project">
                                    <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">

                                    <label>Project Title
                                        <input type="text" name="title" value="<?= e((string) $project['title']) ?>" required>
                                    </label>
                                    <label>Description
                                        <textarea name="description" rows="3" required><?= e((string) $project['description']) ?></textarea>
                                    </label>
                                    <label>Technologies Used (comma separated)
                                        <input type="text" name="technologies" value="<?= e((string) $project['technologies']) ?>" required>
                                    </label>
                                    <label>Add More Files (optional)
                                        <input type="file" name="project_files_new[]" multiple>
                                    </label>

                                    <button type="submit" class="btn-inline">Save Changes</button>
                                </form>
                            </div>

                            <ul class="file-tree">
                                <?php foreach ($project['files'] as $file): ?>
                                    <li>
                                        <span><?= e((string) $file['original_name']) ?></span>
                                        <div class="inline-actions">
                                            <a class="icon-action" href="../<?= e((string) $file['file_path']) ?>" target="_blank" rel="noopener" title="View file" aria-label="View file">
                                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                                    <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12Z"></path>
                                                    <circle cx="12" cy="12" r="3"></circle>
                                                </svg>
                                            </a>
                                            <a class="icon-action" href="../<?= e((string) $file['file_path']) ?>" download title="Download file" aria-label="Download file">
                                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                                    <path d="M12 3v11"></path>
                                                    <path d="m7 10 5 5 5-5"></path>
                                                    <path d="M4 20h16"></path>
                                                </svg>
                                            </a>
                                            <form method="post" onsubmit="return confirm('Remove this file?');">
                                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                                <input type="hidden" name="action" value="delete_project_file">
                                                <input type="hidden" name="file_id" value="<?= (int) $file['id'] ?>">
                                                <button type="submit" class="icon-action icon-danger" title="Remove file" aria-label="Remove file">
                                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                                        <path d="M3 6h18"></path>
                                                        <path d="M8 6V4h8v2"></path>
                                                        <path d="M19 6l-1 14H6L5 6"></path>
                                                        <path d="M10 11v6"></path>
                                                        <path d="M14 11v6"></path>
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="dashboard-section" id="section-course">
        <div class="panel">
            <h2>My Course</h2>
            <p>You are currently learning: <strong><?= e((string) $user['course']) ?></strong></p>
            <a class="btn" href="<?= e($courseLink) ?>" target="_blank" rel="noopener">Start Learning</a>
        </div>
    </section>
</main>

<script>
    window.CSRF_TOKEN = <?= json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>
<script src="../assets/javascript/dashboard.js"></script>
</body>
</html>
