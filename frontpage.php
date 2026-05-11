<?php
require_once 'config.php';

// Check registration status
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'user'");
$stmt->execute();
$total_users = $stmt->fetchColumn();
$registration_open = $total_users < 5;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BorrowTrack - Item Borrowing System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --dark-gradient: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
        }
        
        * {
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        .hero-section {
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
        }
        
        .hero-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 80%, rgba(120,119,198,0.3) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255,119,198,0.3) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(120,219,255,0.2) 0%, transparent 50%);
            animation: float 20s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            33% { transform: translateY(-20px) rotate(1deg); }
            66% { transform: translateY(-10px) rotate(-1deg); }
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
        }
        
        .hero-title {
            font-size: clamp(2.5rem, 5vw, 4.5rem);
            font-weight: 700;
            background: linear-gradient(135deg, #fff 0%, #f0f0f0 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1.5rem;
            animation: slideInUp 1s ease-out;
        }
        
        .hero-subtitle {
            font-size: clamp(1.1rem, 2.5vw, 1.5rem);
            color: rgba(255,255,255,0.95);
            font-weight: 300;
            margin-bottom: 2.5rem;
            max-width: 600px;
            line-height: 1.6;
            animation: slideInUp 1s ease-out 0.2s both;
        }
        
        .cta-buttons {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
            justify-content: center;
            animation: slideInUp 1s ease-out 0.4s both;
        }
        
        .btn-hero {
            padding: 16px 40px;
            font-size: 1.15rem;
            font-weight: 600;
            border-radius: 50px;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            min-height: 60px;
            min-width: 180px;
            justify-content: center;
        }
        
        .btn-login {
            background: rgba(255,255,255,0.95);
            color: #667eea;
            backdrop-filter: blur(20px);
        }
        
        .btn-login:hover {
            background: white;
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(102, 126, 234, 0.3);
            color: #667eea;
        }
        
        .btn-register {
            background: var(--primary-gradient);
            color: white;
            backdrop-filter: blur(20px);
        }
        
        .btn-register:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 20px 40px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .btn-register:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
            opacity: 0.7;
        }
        
        .features {
            padding: 80px 0;
            background: rgba(255,255,255,0.95);
        }
        
        .feature-card {
            background: white;
            border-radius: 20px;
            padding: 2.5rem 2rem;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border: none;
            height: 100%;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .feature-icon {
            width: 80px;
            height: 80px;
            background: var(--primary-gradient);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
            color: white;
        }
        
        .status-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 8px 16px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.85rem;
        }
        
        .status-open {
            background: rgba(40, 167, 69, 0.9);
            color: white;
        }
        
        .status-closed {
            background: rgba(220, 53, 69, 0.9);
            color: white;
        }
        
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @media (max-width: 768px) {
            .cta-buttons {
                flex-direction: column;
                align-items: center;
            }
            .btn-hero {
                width: 100%;
                max-width: 280px;
            }
        }
        
        .floating-shapes {
            position: absolute;
            width: 100%;
            height: 100%;
            pointer-events: none;
            overflow: hidden;
        }
        
        .shape {
            position: absolute;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            animation: float 25s infinite linear;
        }
        
        .shape:nth-child(1) { width: 80px; height: 80px; top: 20%; left: 10%; animation-delay: 0s; }
        .shape:nth-child(2) { width: 120px; height: 120px; top: 60%; right: 10%; animation-delay: 6s; }
        .shape:nth-child(3) { width: 60px; height: 60px; top: 80%; left: 20%; animation-delay: 12s; }
    </style>
</head>
<body>
    <!-- Floating Background Shapes -->
    <div class="floating-shapes">
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
    </div>
    
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-bg"></div>
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 col-md-8 mx-auto hero-content text-center">

                    
                    <h1 class="hero-title">
                        <i class="fas fa-handshake me-3"></i>
                        BorrowTrack
                    </h1>
                    <p class="hero-subtitle">
                        Effortlessly manage item borrowing and lending. 
                        Track requests, approvals, and returns in one secure platform.
                    </p>
                    
                    <div class="cta-buttons">
                        <a href="login.php" class="btn btn-hero btn-login">
                            <i class="fas fa-sign-in-alt"></i>
                            Sign In
                        </a>
                        
                        <a href="register.php" 
                           class="btn btn-hero btn-register <?php echo !$registration_open ? 'disabled' : ''; ?>"
                           <?php echo !$registration_open ? 'aria-disabled="true"' : ''; ?>>
                            <i class="fas fa-user-plus"></i>
                            <?php echo $registration_open ? 'Join Now' : 'Registration Full'; ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Features Section -->
    <section class="features">
        <div class="container">
            <div class="row g-4 text-center">
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card h-100">
                        <div class="feature-icon">
                            <i class="fas fa-search"></i>
                        </div>
                        <h4 class="h3 fw-bold mb-3">Easy Search</h4>
                        <p class="text-muted">Quickly find available items with powerful search and filters.</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card h-100">
                        <div class="feature-icon">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                        <h4 class="h3 fw-bold mb-3">Smart Tracking</h4>
                        <p class="text-muted">Real-time status updates for all borrowing requests and returns.</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card h-100">
                        <div class="feature-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h4 class="h3 fw-bold mb-3">Secure System</h4>
                        <p class="text-muted">Role-based access with admin approval and full audit logs.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Footer -->
    <footer class="bg-dark text-white py-5 mt-5">
        <div class="container text-center">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-handshake me-2"></i>BorrowTrack</h5>
                    <p class="text-muted">Your trusted item borrowing management system.</p>
                </div>
                <div class="col-md-6">
                    <p class="mb-2">
                        <a href="login.php" class="text-white-50 me-4"><i class="fas fa-sign-in-alt me-1"></i>Login</a>
                        <a href="register.php" class="text-white-50"><i class="fas fa-user-plus me-1"></i>Register</a>
                    </p>
                    <small>&copy; 2024 BorrowTrack. All rights reserved.</small>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Smooth scrolling and animations
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });

        // Parallax effect on scroll
        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            const heroBg = document.querySelector('.hero-bg');
            if (heroBg) {
                heroBg.style.transform = `translateY(${scrolled * 0.5}px)`;
            }
        });

        // Button hover effects
        document.querySelectorAll('.btn-hero').forEach(btn => {
            btn.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px) scale(1.02)';
            });
            btn.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });
    </script>
</body>
</html>