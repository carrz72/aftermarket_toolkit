<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/image_helper.php'; // Add this line
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

    // Handle main image upload - UPDATED
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
            
            // Get upload directory using helper function
            $uploadDir = getUploadDirectory();
            $destination = $uploadDir . $newFilename;
            
            // Move uploaded file
            if (move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
                // Store standardized path in database
                $mainImage = getUploadedImagePath($newFilename);
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
            
            // Process additional images - UPDATED
            if (isset($_FILES['additional_images']) && !empty($_FILES['additional_images']['name'][0])) {
                for ($i = 0; $i < count($_FILES['additional_images']['name']); $i++) {
                    if ($_FILES['additional_images']['error'][$i] === 0) {
                        $filename = $_FILES['additional_images']['name'][$i];
                        $filetype = pathinfo($filename, PATHINFO_EXTENSION);
                        
                        // Verify file extension
                        if (in_array(strtolower($filetype), $allowed)) {
                            $newFilename = uniqid() . '_' . $i . '.' . $filetype;
                            $uploadDir = getUploadDirectory();
                            $destination = $uploadDir . $newFilename;
                            
                            if (move_uploaded_file($_FILES['additional_images']['tmp_name'][$i], $destination)) {
                                // Store standardized path in database
                                $additionalImage = getUploadedImagePath($newFilename);
                                
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
            header('Location: view_listings.php');
            exit();
        } catch (Exception $e) {
            // Roll back transaction on error
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
    <title>Create Listing - Aftermarket Toolbox</title>
    <link rel="stylesheet" href="../../public/assets/css/create_listing.css">
    <style>
        .image-preview {
            max-width: 150px;
            max-height: 150px;
            width: 150px;
            height: 150px;
            object-fit: cover;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s ease;
        }

        .image-preview:hover {
            transform: scale(1.05);
        }

        .image-preview-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }

        #mainImagePreview {
            max-width: 300px;
            max-height: 200px;
            object-fit: contain;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-top: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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
                <input type="file" id="image" name="image" class="form-control" accept="image/*">
                <img id="mainImagePreview" style="display:none; max-width: 300px; margin-top: 10px;" class="image-preview">
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
            const preview = document.getElementById('mainImagePreview');
            const file = e.target.files[0];
            const reader = new FileReader();
            
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
                preview.style.maxWidth = '300px';
                preview.style.maxHeight = '200px';
                preview.style.objectFit = 'contain';
                preview.style.border = '1px solid #ddd';
                preview.style.borderRadius = '4px';
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

        document.getElementById('additional_images').addEventListener('change', function(e) {
            const previewContainer = document.getElementById('additionalImagesPreview');
            previewContainer.innerHTML = '';
            
            Array.from(this.files).forEach(file => {
                const reader = new FileReader();
                const preview = document.createElement('img');
                preview.className = 'image-preview';
                
                // Add specific size constraints
                preview.style.width = '150px';
                preview.style.height = '150px';
                preview.style.objectFit = 'cover';
                preview.style.margin = '5px';
                preview.style.border = '1px solid #ddd';
                preview.style.borderRadius = '4px';
                
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