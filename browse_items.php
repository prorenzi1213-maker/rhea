<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'search') {
    $search = $_GET['q'] ?? '';
    $query = "SELECT * FROM inventory WHERE stock > 0 AND tool_name LIKE ? ORDER BY category ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute(["%$search%"]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    echo json_encode($items);
    exit;
}

$query = "SELECT * FROM inventory WHERE stock > 0 ORDER BY category ASC";
$stmt = $pdo->query($query);
$allItems = $stmt->fetchAll();
$initial = array_slice($allItems, 0, 1);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Items | BorrowTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8fafc; font-family: 'Inter', sans-serif; }
        .sidebar { height: 100vh; background: #0f172a; color: white; position: fixed; width: 250px; z-index: 1000; }
        .main-content { margin-left: 250px; padding: 40px; }
        .nav-link { color: #94a3b8; transition: 0.3s; padding: 12px 20px; }
        .nav-link:hover, .nav-link.active { color: white; background: #1e293b; border-radius: 8px; }

        .carousel-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
            max-width: 500px;
            margin: 0 auto;
        }
        .carousel-img-wrap {
            height: 300px;
            background: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .carousel-img { width: 100%; height: 100%; object-fit: cover; }
        .carousel-placeholder { font-size: 4rem; color: #94a3b8; }
        .carousel-control-custom {
            width: 44px; height: 44px;
            background: white;
            border-radius: 50%;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            top: 50%; transform: translateY(-50%);
            position: absolute;
            z-index: 5;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1e293b;
            text-decoration: none;
            opacity: 0.9;
        }
        .carousel-control-custom:hover { opacity: 1; color: #0f172a; }
        .carousel-control-custom.prev { left: -22px; }
        .carousel-control-custom.next { right: -22px; }
        .carousel-indicators-custom {
            display: flex; justify-content: center; gap: 6px; margin-top: 16px;
        }
        .carousel-indicators-custom button {
            width: 10px; height: 10px; border-radius: 50%;
            border: 1px solid #cbd5e1; background: transparent; padding: 0;
            transition: 0.2s;
        }
        .carousel-indicators-custom button.active { background: #1e293b; border-color: #1e293b; }

        .search-box { max-width: 400px; }
        .search-box input { border-radius: 24px 0 0 24px; border-right: none; }
        .search-box button { border-radius: 0 24px 24px 0; }
        #searchClear { cursor: pointer; }
    </style>
</head>
<body>

<div class="sidebar p-3">
    <h4 class="text-center my-4 fw-bold text-success"><i class="fas fa-handshake me-2"></i>BorrowTrack</h4>
    <ul class="nav flex-column gap-1">
        <li class="nav-item"><a href="user_dashboard.php" class="nav-link"><i class="fas fa-home me-2"></i> Dashboard</a></li>
        <li class="nav-item"><a href="browse_items.php" class="nav-link active"><i class="fas fa-search me-2"></i> Browse Items</a></li>
        <li class="nav-item"><a href="my_history.php" class="nav-link"><i class="fas fa-history me-2"></i> My History</a></li>
        <li class="nav-item"><a href="notifications.php" class="nav-link"><i class="fas fa-bell me-2"></i> Notifications</a></li>
        <li class="nav-item"><a href="profile.php" class="nav-link"><i class="fas fa-user-circle me-2"></i> Profile</a></li>
    </ul>
    <div class="position-absolute bottom-0 start-0 w-100 p-3">
        <a href="logout.php" class="btn btn-outline-danger w-100 btn-sm">Logout</a>
    </div>
</div>

<main class="main-content">
    <header class="mb-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <h2 class="fw-bold mb-0">Available Equipment</h2>
        <div class="input-group search-box">
            <input type="text" id="searchInput" class="form-control" placeholder="Search tools..." autocomplete="off">
            <button class="btn btn-dark" id="searchBtn"><i class="fas fa-search"></i></button>
        </div>
    </header>

    <div id="carouselContainer">
        <div class="position-relative" style="padding: 0 40px;">
            <div id="itemCarousel" class="carousel slide" data-bs-wrap="true">
                <div class="carousel-inner" id="carouselInner">
                </div>

                <button class="carousel-control-custom prev" data-bs-target="#itemCarousel" data-bs-slide="prev">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button class="carousel-control-custom next" data-bs-target="#itemCarousel" data-bs-slide="next">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>

            <div class="carousel-indicators-custom" id="carouselIndicators"></div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function buildCarousel(items) {
    const inner = document.getElementById('carouselInner');
    const indicators = document.getElementById('carouselIndicators');
    const container = document.getElementById('carouselContainer');

    if (items.length === 0) {
        container.innerHTML = '<p class="text-muted text-center mt-5">No items match your search.</p>';
        return;
    }

    container.innerHTML = `<div class="position-relative" style="padding: 0 40px;">
        <div id="itemCarousel" class="carousel slide" data-bs-wrap="true">
            <div class="carousel-inner" id="carouselInner"></div>
            <button class="carousel-control-custom prev" data-bs-target="#itemCarousel" data-bs-slide="prev">
                <i class="fas fa-chevron-left"></i>
            </button>
            <button class="carousel-control-custom next" data-bs-target="#itemCarousel" data-bs-slide="next">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
        <div class="carousel-indicators-custom" id="carouselIndicators"></div>
    </div>`;

    const newInner = document.getElementById('carouselInner');
    const newIndicators = document.getElementById('carouselIndicators');

    items.forEach((item, i) => {
        const imgPath = 'uploads/items/' + (item.image || '');
        const hasImg = item.image && (imgPath);

        const slide = document.createElement('div');
        slide.className = 'carousel-item' + (i === 0 ? ' active' : '');
        slide.innerHTML = `<div class="carousel-card">
            <div class="carousel-img-wrap">
                ${hasImg
                    ? `<img src="${imgPath}" class="carousel-img" alt="Tool">`
                    : `<div class="text-center text-muted"><i class="fas fa-tools carousel-placeholder"></i><br><small>No Photo</small></div>`
                }
            </div>
            <div class="p-4">
                <span class="badge bg-primary mb-2">${item.category || ''}</span>
                <h4 class="fw-bold">${item.tool_name}</h4>
                <p class="text-muted small mb-3">Status: ${item.status || 'N/A'} &middot; Stock: ${item.stock || 0}</p>
                <button class="btn btn-primary w-100" onclick="location.href='request_tool.php?id=${item.id}'">Request This Item</button>
            </div>
        </div>`;
        newInner.appendChild(slide);

        const dot = document.createElement('button');
        dot.type = 'button';
        dot.className = i === 0 ? 'active' : '';
        dot.dataset.bsTarget = '#itemCarousel';
        dot.dataset.bsSlideTo = i;
        newIndicators.appendChild(dot);
    });

    const carouselEl = document.getElementById('itemCarousel');
    if (carouselEl) {
        const bsCarousel = new bootstrap.Carousel(carouselEl, { wrap: true });
        carouselEl.addEventListener('slide.bs.carousel', function(e) {
            document.querySelectorAll('#carouselIndicators button').forEach((btn, idx) => {
                btn.classList.toggle('active', idx === e.to);
            });
        });
    }
}

function fetchItems(q) {
    fetch('browse_items.php?ajax=search&q=' + encodeURIComponent(q))
        .then(r => r.json())
        .then(data => buildCarousel(data));
}

let debounceTimer;
const searchInput = document.getElementById('searchInput');
const searchBtn = document.getElementById('searchBtn');

searchInput.addEventListener('input', function() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchItems(this.value), 300);
});

searchBtn.addEventListener('click', function() {
    fetchItems(searchInput.value);
});

document.addEventListener('DOMContentLoaded', function() {
    fetchItems('');
});
</script>
</body>
</html>
