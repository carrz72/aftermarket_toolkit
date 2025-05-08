<?php
require_once __DIR__ . '/../../config/db.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../public/login.php');
    exit();
}

$userId = $_SESSION['user_id'];
$listingId = $_GET['id'] ?? 0;
$errors = [];
$success = false;


// Fetch the listing details
$query = "SELECT * FROM listings WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('ii', $listingId, $userId);
$stmt->execute();
$result = $stmt->get_result();

// Check if listing exists and belongs to current user
if ($result->num_rows === 0) {
    header('Location: ./view_listings.php');
    exit();
}

$listing = $result->fetch_assoc();

// Get categories for dropdown
$categoryQuery = "SELECT DISTINCT category FROM listings ORDER BY category ASC";
$categoryResult = $conn->query($categoryQuery);
$categories = [];
while ($row = $categoryResult->fetch_assoc()) {
    if (!empty($row['category'])) {
        $categories[] = $row['category'];
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $condition = trim($_POST['condition'] ?? '');
    $image = $listing['image']; // Default to existing image
    
    // Handle custom category if "other" is selected
    if ($category === 'other' && !empty($_POST['otherCategory'])) {
        $category = trim($_POST['otherCategory']);
    }
    
    // Validation
    if (empty($title)) {
        $errors[] = 'Title is required';
    }
    
    if (empty($description)) {
        $errors[] = 'Description is required';
    }
    
    if (empty($price) || !is_numeric($price) || $price <= 0) {
        $errors[] = 'Price must be a positive number';
    }
    
    if (empty($category)) {
        $errors[] = 'Category is required';
    }

    // Handle image upload if a new image is provided
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['image']['name'];
        $filetype = pathinfo($filename, PATHINFO_EXTENSION);
        
        // Verify file extension
        if (!in_array(strtolower($filetype), $allowed)) {
            $errors[] = 'Only JPG, JPEG, PNG, and GIF files are allowed';
        } else {
            // Create unique filename
            $newFilename = uniqid() . '.' . $filetype;
            $uploadDir = '../../public/assets/images/listings/';
            
            // Ensure directory exists
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $destination = $uploadDir . $newFilename;
            
            // Move uploaded file
            if (move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
                $image = './assets/images/listings/' . $newFilename;
            } else {
                $errors[] = 'Error uploading image';
            }
        }
    }

    // If no errors, update the listing
    if (empty($errors)) {
        $updateQuery = "UPDATE listings 
                      SET title = ?, description = ?, price = ?, category = ?, `condition` = ?, image = ? 
                      WHERE id = ? AND user_id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param('ssdsssii', $title, $description, $price, $category, $condition, $image, $listingId, $userId);
        
        if ($updateStmt->execute()) {
            $success = true;
            
            // Refresh listing data
            $stmt->execute();
            $result = $stmt->get_result();
            $listing = $result->fetch_assoc();
        } else {
            $errors[] = 'Failed to update listing: ' . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Listing - Aftermarket Toolbox</title>
    <link rel="stylesheet" href="../../public/assets/css/view_listings.css">
    <style>
        .edit-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .edit-container h1 {
            text-align: center;
            color: #333;
            margin-bottom: 20px;
            font-size: 24px;
            background-color: #189dc5;
            padding: 15px;
            border-radius: 10px;
            color: white;
            box-shadow: 0px 6px rgb(5, 5, 5);
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-control:focus {
            border-color: #189dc5;
            outline: none;
            box-shadow: 0 0 5px rgba(24, 157, 197, 0.3);
        }
        
        .btn {
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background-color: #189dc5;
            color: white;
            border: none;
        }
        
        .btn-primary:hover {
            background-color: #157a9e;
            transform: scale(1.05);
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
            border: none;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
            transform: scale(1.05);
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .image-preview {
            max-width: 200px;
            margin-top: 10px;
        }
        
        .buttons {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }
    </style>
</head>
<body>
    <div class="menu">
        <a href="../../index.php" class="link">
            <span class="link-icon">
                <img src="../../public/assets/images/home-icon.svg" alt="Home">
            </span>
            <span class="link-title">Home</span>
        </a>

        <a href="../../public/marketplace.php" class="link">
            <span class="link-icon">
                <img src="../../public/assets/images/market.svg" alt="Market">
            </span>
            <span class="link-title">Market</span>
        </a>
        
        <a href="../../public/forum.php" class="link">
            <span class="link-icon">
                <img src="../../public/assets/images/forum-icon.svg" alt="Forum">
            </span>
            <span class="link-title">Forum</span>
        </a>
        
        <a href="./view_listings.php" class="link">
            <span class="link-icon">
                <img src="../../public/assets/images/list-icon.svg" alt="My Listings">
            </span>
            <span class="link-title">My Listings</span>
        </a>
    </div>

    <div class="edit-container">
        <h1>Edit Listing</h1>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                Listing updated successfully!
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="title">Title*</label>
                <input type="text" id="title" name="title" class="form-control" value="<?= htmlspecialchars($listing['title']) ?>" required>
            </div>
            
            <div class="form-group">
                <label for="description">Description*</label>
                <textarea id="description" name="description" class="form-control" rows="6" required><?= htmlspecialchars($listing['description']) ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="price">Price (£)*</label>
                <input type="number" id="price" name="price" class="form-control" step="0.01" value="<?= htmlspecialchars($listing['price']) ?>" required min="0.01">
            </div>
            
            <div class="form-group">
                <label for="category">Category*</label>
                <select id="category" name="category" class="form-control" required>
                    <option value="">Select a category</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= htmlspecialchars($category) ?>" <?= $listing['category'] === $category ? 'selected' : '' ?>>
                            <?= htmlspecialchars($category) ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="other" <?= !in_array($listing['category'], $categories) ? 'selected' : '' ?>>Other</option>
                </select>
                <div id="otherCategoryContainer" style="display:<?= !in_array($listing['category'], $categories) ? 'block' : 'none' ?>; margin-top: 10px;">
                    <input type="text" id="otherCategory" name="otherCategory" class="form-control" placeholder="Specify category" value="<?= !in_array($listing['category'], $categories) ? htmlspecialchars($listing['category']) : '' ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="condition">Condition*</label>
                <select id="condition" name="condition" class="form-control" required>
                    <option value="">Select condition</option>
                    <option value="New" <?= $listing['condition'] === 'New' ? 'selected' : '' ?>>New</option>
                    <option value="Like New" <?= $listing['condition'] === 'Like New' ? 'selected' : '' ?>>Like New</option>
                    <option value="Good" <?= $listing['condition'] === 'Good' ? 'selected' : '' ?>>Good</option>
                    <option value="Fair" <?= $listing['condition'] === 'Fair' ? 'selected' : '' ?>>Fair</option>
                    <option value="Used" <?= $listing['condition'] === 'Used' ? 'selected' : '' ?>>Used</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="image">Image</label>
                <?php if (!empty($listing['image'])): ?>
                    <div>
                        <?php
                        // Handle different types of image paths
                        $imagePath = $listing['image'];
                        if (strpos($imagePath, '/') === 0) {
                            // Absolute path starting with /
                            $displayPath = $imagePath; // Use as is
                        } else if (strpos($imagePath, './assets/') === 0) {
                            // Relative path starting with ./assets/
                            $displayPath = '../../public/' . str_replace('./assets/', 'assets/', $imagePath);
                        } else {
                            // Any other format
                            $displayPath = '../../public/' . $imagePath;
                        }
                        ?>
                        <img src="<?= htmlspecialchars($displayPath) ?>" alt="Current Image" class="image-preview">
                        <p>Current image. Upload a new one to replace it.</p>
                    </div>
                <?php endif; ?>
                <input type="file" id="image" name="image" class="form-control" accept="image/*">
                <div id="newImagePreview" style="display:none; margin-top: 10px;">
                    <p>New image preview:</p>
                    <img id="imagePreview" src="#" alt="Preview" class="image-preview">
                </div>
            </div>
            
            <div class="buttons">
                <button type="submit" class="btn btn-primary">Update Listing</button>
                <a href="./view_listings.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

    <script>
        // Preview new image before upload
        document.getElementById('image').addEventListener('change', function(e) {
            const preview = document.getElementById('imagePreview');
            const previewContainer = document.getElementById('newImagePreview');
            const file = e.target.files[0];
            const reader = new FileReader();
            
            reader.onload = function(e) {
                preview.src = e.target.result;
                previewContainer.style.display = 'block';
            }
            
            if (file) {
                reader.readAsDataURL(file);
            }
        });
        
        // Handle "Other" category option
        document.getElementById('category').addEventListener('change', function(e) {
            const otherContainer = document.getElementById('otherCategoryContainer');
            if (e.target.value === 'other') {
                otherContainer.style.display = 'block';
            } else {
                otherContainer.style.display = 'none';
            }
        });
    </script>
</body>
</html>