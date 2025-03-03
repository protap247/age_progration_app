<?php
// process.php - Handles the initial image upload
header('Content-Type: application/json');

// Set upload directory
$uploadDir = 'uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$response = ['success' => false, 'message' => ''];

// Check if image file was uploaded
if (isset($_FILES['imageFile']) && $_FILES['imageFile']['error'] === UPLOAD_ERR_OK) {
    $tempFile = $_FILES['imageFile']['tmp_name'];
    $fileType = exif_imagetype($tempFile);
    
    // Validate file type
    $allowedTypes = [
        IMAGETYPE_JPEG,
        IMAGETYPE_PNG,
        IMAGETYPE_GIF
    ];
    
    if (!in_array($fileType, $allowedTypes)) {
        $response['message'] = 'Invalid file type. Please upload a JPG, PNG, or GIF image.';
        echo json_encode($response);
        exit;
    }
    
    // Generate unique filename
    $originalFilename = basename($_FILES['imageFile']['name']);
    $fileExtension = pathinfo($originalFilename, PATHINFO_EXTENSION);
    $uniqueId = uniqid();
    $originalImagePath = $uploadDir . $uniqueId . '_original.' . $fileExtension;
    
    // Save original image
    if (move_uploaded_file($tempFile, $originalImagePath)) {
        // Create initial aged image (no aging effect yet)
        $agedImagePath = $uploadDir . $uniqueId . '_aged.' . $fileExtension;
        copy($originalImagePath, $agedImagePath);
        
        // Store unique ID in session for later use
        session_start();
        $_SESSION['current_image_id'] = $uniqueId;
        $_SESSION['current_image_ext'] = $fileExtension;
        
        $response = [
            'success' => true,
            'originalImageUrl' => $originalImagePath,
            'agedImageUrl' => $agedImagePath
        ];
    } else {
        $response['message'] = 'Failed to save the uploaded image.';
    }
} else {
    $response['message'] = 'No image file uploaded or an error occurred.';
}

echo json_encode($response);
?>

<?php
// age.php - Processes the aging effect on the image
header('Content-Type: application/json');

// Start session to access the image ID
session_start();

$response = ['success' => false, 'message' => ''];

// Check if required parameters exist
if (isset($_POST['imageUrl']) && isset($_POST['ageAmount'])) {
    $originalImageUrl = $_POST['imageUrl'];
    $ageAmount = intval($_POST['ageAmount']);
    
    // Validate age amount
    if ($ageAmount < 0 || $ageAmount > 60) {
        $response['message'] = 'Invalid age amount.';
        echo json_encode($response);
        exit;
    }
    
    // Create aged image path
    $uniqueId = $_SESSION['current_image_id'] ?? uniqid();
    $fileExtension = $_SESSION['current_image_ext'] ?? 'jpg';
    $uploadDir = 'uploads/';
    $agedImagePath = $uploadDir . $uniqueId . '_aged.' . $fileExtension;
    
    // Check if original image exists
    if (!file_exists($originalImageUrl)) {
        $response['message'] = 'Original image not found.';
        echo json_encode($response);
        exit;
    }
    
    // Apply aging effect based on image type
    $imageInfo = getimagesize($originalImageUrl);
    $imageType = $imageInfo[2];
    
    switch ($imageType) {
        case IMAGETYPE_JPEG:
            $image = imagecreatefromjpeg($originalImageUrl);
            break;
        case IMAGETYPE_PNG:
            $image = imagecreatefrompng($originalImageUrl);
            break;
        case IMAGETYPE_GIF:
            $image = imagecreatefromgif($originalImageUrl);
            break;
        default:
            $response['message'] = 'Unsupported image type.';
            echo json_encode($response);
            exit;
    }
    
    // Make sure alpha channel is preserved for PNG
    if ($imageType === IMAGETYPE_PNG) {
        imagealphablending($image, true);
        imagesavealpha($image, true);
    }
    
    // Apply aging effects based on age amount
    // This is a simplified simulation using filters
    // Real aging would require ML algorithms
    
    // Increase contrast slightly for wrinkles simulation
    if ($ageAmount > 10) {
        imagefilter($image, IMG_FILTER_CONTRAST, -5 * ($ageAmount / 20));
    }
    
    // Add sepia tone for yellowing effect
    if ($ageAmount > 20) {
        imagefilter($image, IMG_FILTER_GRAYSCALE);
        imagefilter($image, IMG_FILTER_COLORIZE, 30, 20, 0, ($ageAmount - 20) * 2);
    }
    
    // Add brightness reduction for aging effect
    imagefilter($image, IMG_FILTER_BRIGHTNESS, -($ageAmount / 2));
    
    // Add grayscale effect for hair graying
    if ($ageAmount > 30) {
        // Partial grayscale effect (controlled by age amount)
        $grayEffect = ($ageAmount - 30) * 3;
        if ($grayEffect > 100) $grayEffect = 100;
        
        // Create a grayscale version
        $grayImage = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagecopy($grayImage, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
        imagefilter($grayImage, IMG_FILTER_GRAYSCALE);
        
        // Blend original and grayscale based on age amount
        imagecopymerge($image, $grayImage, 0, 0, 0, 0, imagesx($image), imagesy($image), $grayEffect);
        imagedestroy($grayImage);
    }
    
    // Save the aged image
    switch ($imageType) {
        case IMAGETYPE_JPEG:
            imagejpeg($image, $agedImagePath, 90);
            break;
        case IMAGETYPE_PNG:
            imagepng($image, $agedImagePath, 9);
            break;
        case IMAGETYPE_GIF:
            imagegif($image, $agedImagePath);
            break;
    }
    
    imagedestroy($image);
    
    $response = [
        'success' => true,
        'agedImageUrl' => $agedImagePath
    ];
} else {
    $response['message'] = 'Missing required parameters.';
}

echo json_encode($response);
?>
