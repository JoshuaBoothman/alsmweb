<?php
// We need to access session variables, so we must start the session
// if it's not already started.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'ALSM' ?></title>
    <link rel="stylesheet" href="/alsmweb/public_html/assets/css/reset.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/alsmweb/public_html/assets/css/style.css">
</head>
<body>
    <header>
        <h1>Australian Large Scale Models</h1>
    </header>
    
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/alsmweb/public_html/index.php">ALSM</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <!-- Left-side navigation -->
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="/alsmweb/public_html/index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="/alsmweb/public_html/events.php">Events</a></li>
                    <li class="nav-item"><a class="nav-link" href="/alsmweb/public_html/merchandise.php">Merchandise</a></li>
                    <li class="nav-item"><a class="nav-link" href="/alsmweb/public_html/campsite_booking.php">Campsite Booking</a></li>
                    <li class="nav-item"><a class="nav-link" href="/alsmweb/public_html/about.php">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="/alsmweb/public_html/contact.php">Contact</a></li>
                </ul>
                
                <!-- Right-side navigation -->
                <ul class="navbar-nav">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        
                        <!-- Show Admin dropdown ONLY if user role is 'admin' -->
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    Admin
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="adminDropdown">
                                    <li><a class="dropdown-item" href="/alsmweb/admin/manage_events.php">Manage Events</a></li>
                                    <li><a class="dropdown-item" href="/alsmweb/admin/manage_products.php">Manage Products</a></li>
                                    <li><a class="dropdown-item" href="/alsmweb/admin/manage_attributes.php">Manage Attributes</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="#">View Users (Coming Soon)</a></li>
                                </ul>
                            </li>
                        <?php endif; ?>

                        <!-- Show for ALL logged-in users -->
                        <li class="nav-item">
                            <a class="nav-link" href="/alsmweb/public_html/profile.php">My Profile</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/alsmweb/public_html/logout.php">Logout</a>
                        </li>

                    <?php else: ?>
                        <!-- Show for logged-out users -->
                        <li class="nav-item">
                            <a class="nav-link" href="/alsmweb/public_html/login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/alsmweb/public_html/register.php">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
