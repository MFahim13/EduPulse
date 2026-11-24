<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="EduPulse is a learning management system for instructors and students: courses, materials, assignments, grading and progress tracking.">
    <title><?= isset($page_title) ? e($page_title) . ' · EduPulse' : 'EduPulse · Learning Management System' ?></title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='7' fill='%232a3fb0'/><path d='M9 11l7-4 7 4-7 4z M11 14v5c0 1.5 2.5 3 5 3s5-1.5 5-3v-5' fill='none' stroke='white' stroke-width='2' stroke-linejoin='round'/></svg>">

    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>
