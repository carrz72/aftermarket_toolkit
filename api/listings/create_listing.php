<?php
require_once __DIR__ . '/../../config/db.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../public/login.php');
    exit();
}

$userId = $_SESSION['user_id'];
$errors = [];
$success = false;

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
    $image = '';

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

    // Handle main image upload
    $mainImage = ''; // Path to main image
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
            
            $destination = "{$uploadDir}{$newFilename}";
            
            // Move uploaded file
            if (move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
                $mainImage = "./assets/images/listings/{$newFilename}";
            } else {
                $errors[] = 'Error uploading image';
            }
        }
    }

    // If no errors, save listing to database
    if (empty($errors)) {
        // Begin transaction
        $conn->begin_transaction();
        try {
            // Insert main listing details
            $insertQuery = "INSERT INTO listings (user_id, title, description, price, image, category, `condition`, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($insertQuery);
            $stmt->bind_param('issdsss', $userId, $title, $description, $price, $mainImage, $category, $condition);
            $stmt->execute();
            
            $listingId = $conn->insert_id;
            
            // Process additional images
            if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
                for ($i = 0; $i < count($_FILES['images']['name']); $i++) {
                    if ($_FILES['images']['error'][$i] === 0) {
                        $filename = $_FILES['images']['name'][$i];
                        $filetype = pathinfo($filename, PATHINFO_EXTENSION);
                        
                        // Verify file extension
                        if (in_array(strtolower($filetype), $allowed)) {
                            $newFilename = uniqid() . '_' . $i . '.' . $filetype;
                            $destination = $uploadDir . $newFilename;
                            
                            if (move_uploaded_file($_FILES['images']['tmp_name'][$i], $destination)) {
                                $additionalImage = "./assets/images/listings/{$newFilename}";
                                
                                // Insert additional image
                                $imageQuery = "INSERT INTO listing_images (listing_id, image_path, display_order) VALUES (?, ?, ?)";
                                $imageStmt = $conn->prepare($imageQuery);
                                $displayOrder = $i + 1; // Start from 1 since 0 is main image
                                $imageStmt->bind_param('isi', $listingId, $additionalImage, $displayOrder);
                                $imageStmt->execute();
                            }
                        }
                    }
                }
            }
            
            // Commit transaction
            $conn->commit();
            $success = true;
            // Redirect after successful creation
            header('Location: ../../public/marketplace.php');
            exit();
        } catch (Exception $e) {
            // Roll back transaction on error
            $conn->rollback();
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Begin transaction for database updates
$conn->begin_transaction();
try {
    // Update main listing info (as before)
    
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
                if (strpos($imagePath, './assets/') === 0) {
                    $fileToDelete = '../../public/' . str_replace('./assets/', 'assets/', $imagePath);
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
                    $uploadDir = '../../public/assets/images/listings/';
                    $destination = $uploadDir . $newFilename;
                    
                    if (move_uploaded_file($_FILES['additional_images']['tmp_name'][$i], $destination)) {
                        $additionalImage = './assets/images/listings/' . $newFilename;
                        
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
} catch (Exception $e) {
    // Roll back on error
    $conn->rollback();
    $errors[] = 'Database error: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Listing - Aftermarket Toolbox</title>
    <link rel="stylesheet" href="../../public/assets/css/create_listing.css">
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
    </div>

    <div class="create-listing-container">
        <h1>Create a New Listing</h1>
        
        <?php if (!empty($errors)): ?>
            <div class="error-messages">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success-message">
                Listing created successfully!
            </div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="title">Title*</label>
                <input type="text" id="title" name="title" required>
            </div>
            
            <div class="form-group">
                <label for="description">Description*</label>
                <textarea id="description" name="description" rows="6" required></textarea>
            </div>
            
            <div class="form-group">
                <label for="price">Price (£)*</label>
                <input type="number" id="price" name="price" step="0.01" required min="0.01">
            </div>
            
            <div class="form-group">
                <label for="category">Category*</label>
                <select id="category" name="category" required>
                    <option value="">Select a category</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= htmlspecialchars($category) ?>"><?= htmlspecialchars($category) ?></option>
                    <?php endforeach; ?>
                    <option value="other">Other</option>
                </select>
                <div id="otherCategoryContainer" style="display:none">
                    <input type="text" id="otherCategory" name="otherCategory" placeholder="Specify category">
                </div>
            </div>
            
            <div class="form-group">
                <label for="condition">Condition*</label>
                <select id="condition" name="condition" required>
                    <option value="">Select condition</option>
                    <option value="New">New</option>
                    <option value="Like New">Like New</option>
                    <option value="Good">Good</option>
                    <option value="Fair">Fair</option>
                    <option value="Poor">Poor</option>
                </select>
            </div>
            
            <!-- First get additional images -->
            <?php
            $additionalImagesQuery = "SELECT id, image_path FROM listing_images WHERE listing_id = ? ORDER BY display_order ASC";
            $additionalImagesStmt = $conn->prepare($additionalImagesQuery);
            $additionalImagesStmt->bind_param('i', $listingId);
            $additionalImagesStmt->execute();
            $additionalImagesResult = $additionalImagesStmt->get_result();
            $additionalImages = [];
            while ($img = $additionalImagesResult->fetch_assoc()) {
                $additionalImages[] = $img;
            }
            ?>

            <div class="form-group">
                <label for="image">Main Image</label>
                <?php if (!empty($listing['image'])): ?>
                    <!-- Current main image display (as before) -->
                <?php endif; ?>
                <input type="file" id="image" name="image" class="form-control" accept="image/*">
                <!-- Preview (as before) -->
            </div>

            <div class="form-group">
                <label>Additional Images</label>
                <div class="additional-images-container">
                    <?php foreach ($additionalImages as $img): ?>
                        <div class="additional-image">
                            <?php
                            $imgPath = $img['image_path'];
                            if (strpos($imgPath, '/') === 0) {
                                $displayPath = $imgPath;
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
                
                <label for="additional_images">Add More Images</label>
                <input type="file" id="additional_images" name="additional_images[]" multiple class="form-control" accept="image/*">
                <div id="additionalImagesPreview" class="image-preview-container"></div>
            </div>
            
            <div class="form-buttons">
                <button type="submit" class="submit-btn">Create Listing</button>
                <a href="view_listings.php" class="cancel-btn">Cancel</a>
            </div>
        </form>
    </div>

    <script>
        // Preview image before upload
        document.getElementById('image').addEventListener('change', function(e) {
            const preview = document.getElementById('imagePreview');
            const file = e.target.files[0];
            const reader = new FileReader();
            
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
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

        document.getElementById('images').addEventListener('change', function(e) {
            const previewContainer = document.getElementById('imagePreviewContainer');
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
    </script>
</body>
</html>