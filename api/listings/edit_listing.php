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

// Get additional images for this listing
$additionalImagesQuery = "SELECT id, image_path FROM listing_images WHERE listing_id = ? ORDER BY display_order ASC";
$additionalImagesStmt = $conn->prepare($additionalImagesQuery);
$additionalImagesStmt->bind_param('i', $listingId);
$additionalImagesStmt->execute();
$additionalImagesResult = $additionalImagesStmt->get_result();
$additionalImages = [];
while ($img = $additionalImagesResult->fetch_assoc()) {
    $additionalImages[] = $img;
}

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

    // Handle main image upload if a new image is provided
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
            $uploadDir = '../../aftermarket_toolkit/uploads/';
            
            // Ensure directory exists
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $destination = $uploadDir . $newFilename;
            
            // Move uploaded file
            if (move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
                $image = "/aftermarket_toolkit/uploads/{$newFilename}";
            } else {
                $errors[] = 'Error uploading image';
            }
        }
    }

    // If no errors, update the listing
    if (empty($errors)) {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Update main listing details
            $updateQuery = "UPDATE listings 
                          SET title = ?, description = ?, price = ?, category = ?, `condition` = ?, image = ? 
                          WHERE id = ? AND user_id = ?";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bind_param('ssdsssii', $title, $description, $price, $category, $condition, $image, $listingId, $userId);
            $updateStmt->execute();
            
            // Process image removals if any
            if (isset($_POST['remove_images']) && !empty($_POST['remove_images'])) {
                foreach ($_POST['remove_images'] as $imageId) {
                    // First get the image path to delete the file
                    $imagePathQuery = "SELECT image_path FROM listing_images WHERE id = ? AND listing_id = ?";
                    $imagePathStmt = $conn->prepare($imagePathQuery);
                    $imagePathStmt->bind_param('ii', $imageId, $listingId);
                    $imagePathStmt->execute();
                    $pathResult = $imagePathStmt->get_result();
                    
                    if ($pathRow = $pathResult->fetch_assoc()) {
                        $imagePath = $pathRow['image_path'];
                        
                        // Convert DB path to filesystem path
                        if (strpos($imagePath, '/') === 0) {
                            $fileToDelete = '../../' . substr($imagePath, 1);
                            if (file_exists($fileToDelete)) {
                                unlink($fileToDelete);
                            }
                        }
                    }
                    
                    // Delete from database
                    $deleteImageQuery = "DELETE FROM listing_images WHERE id = ? AND listing_id = ?";
                    $deleteImageStmt = $conn->prepare($deleteImageQuery);
                    $deleteImageStmt->bind_param('ii', $imageId, $listingId);
                    $deleteImageStmt->execute();
                }
            }
            
            // Process additional images if any
            if (isset($_FILES['additional_images']) && !empty($_FILES['additional_images']['name'][0])) {
                // Get current highest display order
                $orderQuery = "SELECT MAX(display_order) as max_order FROM listing_images WHERE listing_id = ?";
                $orderStmt = $conn->prepare($orderQuery);
                $orderStmt->bind_param('i', $listingId);
                $orderStmt->execute();
                $orderResult = $orderStmt->get_result();
                $orderRow = $orderResult->fetch_assoc();
                $displayOrder = ($orderRow['max_order'] ?? 0) + 1;
                
                // Process new image uploads
                for ($i = 0; $i < count($_FILES['additional_images']['name']); $i++) {
                    if ($_FILES['additional_images']['error'][$i] === 0) {
                        $filename = $_FILES['additional_images']['name'][$i];
                        $filetype = pathinfo($filename, PATHINFO_EXTENSION);
                        
                        // Verify file extension
                        if (in_array(strtolower($filetype), $allowed)) {
                            $newFilename = uniqid() . '_' . $i . '.' . $filetype;
                            $uploadDir = '../../aftermarket_toolkit/uploads/';
                            $destination = $uploadDir . $newFilename;
                            
                            if (move_uploaded_file($_FILES['additional_images']['tmp_name'][$i], $destination)) {
                                $additionalImage = "/aftermarket_toolkit/uploads/{$newFilename}";
                                
                                // Insert additional image
                                $imageQuery = "INSERT INTO listing_images (listing_id, image_path, display_order) VALUES (?, ?, ?)";
                                $imageStmt = $conn->prepare($imageQuery);
                                $imageStmt->bind_param('isi', $listingId, $additionalImage, $displayOrder);
                                $imageStmt->execute();
                                $displayOrder++;
                            }
                        }
                    }
                }
            }
            
            // Commit all changes
            $conn->commit();
            $success = true;
            
            // Refresh listing data
            $stmt->execute();
            $result = $stmt->get_result();
            $listing = $result->fetch_assoc();
            
            // Refresh additional images data
            $additionalImagesStmt->execute();
            $additionalImagesResult = $additionalImagesStmt->get_result();
            $additionalImages = [];
            while ($img = $additionalImagesResult->fetch_assoc()) {
                $additionalImages[] = $img;
            }
            
        } catch (Exception $e) {
            // Roll back on error
            $conn->rollback();
            $errors[] = 'Database error: ' . $e->getMessage();
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
        
        .additional-images-container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin: 15px 0;
        }
        
        .additional-image {
            position: relative;
            width: 120px;
            text-align: center;
        }
        
        .additional-image img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 4px;
        }
        
        .additional-image label {
            font-size: 12px;
            display: block;
            margin-top: 5px;
        }
        
        .image-preview-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    

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
                <label for="image">Main Image</label>
                <?php if (!empty($listing['image'])): ?>
                    <div>
                        <?php
                        // Handle different types of image paths
                        $imagePath = $listing['image'];
                        if (strpos($imagePath, '/') === 0) {
                            // Absolute path starting with /
                            $displayPath = '../../' . substr($imagePath, 1); 
                        } else if (strpos($imagePath, './assets/') === 0) {
                            // Relative path starting with ./assets/
                            $displayPath = '../../public/' . str_replace('./assets/', 'assets/', $imagePath);
                        } else {
                            // Any other format
                            $displayPath = '../../public/' . $imagePath;
                        }
                        ?>
                        <img src="<?= htmlspecialchars($displayPath) ?>" alt="Current Image" class="image-preview">
                        <p>Current main image. Upload a new one to replace it.</p>
                    </div>
                <?php endif; ?>
                <input type="file" id="image" name="image" class="form-control" accept="image/*">
                <div id="newImagePreview" style="display:none; margin-top: 10px;">
                    <p>New image preview:</p>
                    <img id="imagePreview" src="#" alt="Preview" class="image-preview">
                </div>
            </div>
            
            <!-- Additional Images Section -->
            <div class="form-group">
                <label>Additional Images</label>
                <?php if (!empty($additionalImages)): ?>
                    <div class="additional-images-container">
                        <?php foreach ($additionalImages as $img): ?>
                            <div class="additional-image">
                                <?php
                                $imgPath = $img['image_path'];
                                if (strpos($imgPath, '/') === 0) {
                                    $displayPath = '../../' . substr($imgPath, 1);
                                } else if (strpos($imgPath, './assets/') === 0) {
                                    $displayPath = '../../public/' . str_replace('./assets/', 'assets/', $imgPath);
                                } else {
                                    $displayPath = "../../public/{$imgPath}";
                                }
                                ?>
                                <img src="<?= htmlspecialchars($displayPath) ?>" alt="Additional Image" class="image-preview">
                                <label><input type="checkbox" name="remove_images[]" value="<?= $img['id'] ?>"> Remove</label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <label for="additional_images">Add More Images</label>
                <input type="file" id="additional_images" name="additional_images[]" multiple class="form-control" accept="image/*">
                <div id="additionalImagesPreview" class="image-preview-container"></div>
            </div>
            
            <div class="buttons">
                <button type="submit" class="btn btn-primary">Update Listing</button>
                <a href="./view_listings.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

    <script>
        // Preview new main image before upload
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
        
        // Preview additional images
        document.getElementById('additional_images').addEventListener('change', function(e) {
            const previewContainer = document.getElementById('additionalImagesPreview');
            previewContainer.innerHTML = '';
            
            Array.from(this.files).forEach(file => {
                const reader = new FileReader();
                const preview = document.createElement('img');
                preview.className = 'image-preview';
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                }
                
                if (file) {
                    reader.readAsDataURL(file);
                    previewContainer.appendChild(preview);
                }
            });
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