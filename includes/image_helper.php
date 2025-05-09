<?php
/**
 * Convert database image path to display URL
 * 
 * @param string $imagePath The image path stored in database
 * @param bool $absolute Whether to return an absolute path
 * @return string The URL for display in HTML
 */
function getImageUrl($imagePath, $absolute = false) {
    if (empty($imagePath)) {
        return '/aftermarket_toolkit/public/assets/images/default-image.jpg';
    }
    
    // For paths starting with /aftermarket_toolkit/
    if (strpos($imagePath, '/aftermarket_toolkit/') === 0) {
        return $imagePath;
    }
    
    // For paths from listing_images and uploads folder
    if (strpos($imagePath, '/uploads/') === 0) {
        return '/aftermarket_toolkit' . $imagePath;
    }
    
    // For absolute paths starting with /
    if (strpos($imagePath, '/') === 0) {
        return '/aftermarket_toolkit' . $imagePath;
    }
    
    // For relative paths starting with ./
    if (strpos($imagePath, './') === 0) {
        return '/aftermarket_toolkit' . substr($imagePath, 1);
    }
    
    // For any other path format
    return '/aftermarket_toolkit/' . $imagePath;
}

/**
 * Convert database image path to filesystem path
 * 
 * @param string $imagePath The image path stored in database
 * @return string The path to the file on disk
 */
function getImageFilePath($imagePath) {
    $basePath = __DIR__ . '/../';
    
    // For paths starting with /aftermarket_toolkit/
    if (strpos($imagePath, '/aftermarket_toolkit/') === 0) {
        return $basePath . substr($imagePath, 18); // Remove /aftermarket_toolkit/
    }
    
    // For paths from uploads folder (most common for user uploads)
    if (strpos($imagePath, '/uploads/') === 0) {
        return $basePath . substr($imagePath, 1);
    }
    
    // For absolute paths starting with /
    if (strpos($imagePath, '/') === 0) {
        return $basePath . substr($imagePath, 1);
    }
    
    // For relative paths starting with ./
    if (strpos($imagePath, './') === 0) {
        return $basePath . substr($imagePath, 2);
    }
    
    // For any other path format
    return $basePath . $imagePath;
}

/**
 * Get image thumbnail URL (for listing cards)
 * 
 * @param string $imagePath The image path stored in database
 * @return string The URL for the thumbnail
 */
function getImageThumbnail($imagePath) {
    // For now, just return the normal image
    // You could implement thumbnail generation/caching later
    return getImageUrl($imagePath);
}

/**
 * Check if an image exists and return a valid path or default
 * 
 * @param string $imagePath The image path to check
 * @param string $default Default image to use if not found
 * @return string A valid image URL
 */
function getValidImageUrl($imagePath, $default = null) {
    if (empty($imagePath)) {
        return $default ?: '/aftermarket_toolkit/public/assets/images/default-image.jpg';
    }
    
    $filePath = getImageFilePath($imagePath);
    
    if (file_exists($filePath)) {
        return getImageUrl($imagePath);
    }
    
    return $default ?: '/aftermarket_toolkit/public/assets/images/default-image.jpg';
}

/**
 * Generate a standardized path for newly uploaded images
 * 
 * @param string $filename The name of the uploaded file
 * @param string $directory Optional subdirectory within uploads
 * @return string The standardized path for database storage
 */
function getUploadedImagePath($filename, $directory = '') {
    $subdir = empty($directory) ? '' : trim($directory, '/') . '/';
    return "/uploads/{$subdir}{$filename}";
}

/**
 * Get the physical upload directory path
 *
 * @param string $directory Optional subdirectory within uploads
 * @return string The filesystem path to the upload directory
 */
function getUploadDirectory($directory = '') {
    $basePath = __DIR__ . '/../uploads/';
    $subdir = empty($directory) ? '' : trim($directory, '/') . '/';
    $fullPath = $basePath . $subdir;
    
    // Ensure directory exists
    if (!file_exists($fullPath)) {
        mkdir($fullPath, 0777, true);
    }
    
    return $fullPath;
}
?>