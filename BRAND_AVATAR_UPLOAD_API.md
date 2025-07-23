# Brand Avatar Upload API Documentation

## Overview

The Brand Avatar Upload API provides functionality for brands to upload, update, and delete their profile avatars. The system supports multiple image formats and includes automatic image optimization.

## Features

-   **Multiple Image Formats**: Supports JPEG, PNG, GIF, and WebP
-   **Image Optimization**: Automatic resizing and compression
-   **File Size Limits**: Maximum 5MB per image
-   **Dimension Limits**: Maximum 2048x2048 pixels (automatically resized to 512x512 for avatars)
-   **Base64 Encoding**: Accepts base64 encoded images
-   **Automatic Cleanup**: Removes old avatars when new ones are uploaded

## API Endpoints

### 1. Upload Avatar (Dedicated Endpoint)

**Endpoint**: `POST /api/brand-profile/avatar`

**Description**: Upload a new avatar image for the authenticated brand user.

**Request Body**:

```json
{
    "avatar": "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD..."
}
```

**Response**:

```json
{
    "success": true,
    "message": "Avatar uploaded successfully",
    "data": {
        "avatar": "/storage/avatars/avatar_1_1234567890.jpg",
        "updated_at": "2024-01-15T10:30:00.000000Z"
    }
}
```

### 2. Update Profile with Avatar

**Endpoint**: `PUT /api/brand-profile`

**Description**: Update brand profile information including avatar.

**Request Body**:

```json
{
    "username": "Updated Brand Name",
    "company_name": "Test Company",
    "whatsapp_number": "+1234567890",
    "gender": "male",
    "state": "California",
    "avatar": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAA..."
}
```

**Response**:

```json
{
    "success": true,
    "message": "Profile updated successfully",
    "data": {
        "id": 1,
        "name": "Updated Brand Name",
        "email": "brand@example.com",
        "avatar": "/storage/avatars/avatar_1_1234567890.png",
        "company_name": "Test Company",
        "whatsapp_number": "+1234567890",
        "gender": "male",
        "state": "California",
        "role": "brand",
        "updated_at": "2024-01-15T10:30:00.000000Z"
    }
}
```

### 3. Delete Avatar

**Endpoint**: `DELETE /api/brand-profile/avatar`

**Description**: Remove the current avatar from the brand profile.

**Response**:

```json
{
    "success": true,
    "message": "Avatar deleted successfully"
}
```

### 4. Get Brand Profile

**Endpoint**: `GET /api/brand-profile`

**Description**: Retrieve the current brand profile including avatar URL.

**Response**:

```json
{
    "success": true,
    "data": {
        "id": 1,
        "name": "Brand Name",
        "email": "brand@example.com",
        "avatar": "/storage/avatars/avatar_1_1234567890.jpg",
        "company_name": "Company Name",
        "whatsapp_number": "+1234567890",
        "gender": "male",
        "state": "California",
        "role": "brand",
        "created_at": "2024-01-15T10:00:00.000000Z",
        "updated_at": "2024-01-15T10:30:00.000000Z"
    }
}
```

## Image Requirements

### Supported Formats

-   JPEG (.jpg, .jpeg)
-   PNG (.png)
-   GIF (.gif)
-   WebP (.webp)

### Size Limits

-   **File Size**: Maximum 5MB
-   **Dimensions**: Maximum 2048x2048 pixels
-   **Avatar Size**: Automatically resized to 512x512 pixels

### Base64 Format

Images must be provided as base64 encoded strings with the following format:

```
data:image/[format];base64,[encoded_data]
```

Example:

```
data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=
```

## Error Responses

### Validation Errors (422)

```json
{
    "success": false,
    "message": "Validation failed",
    "errors": {
        "avatar": ["The avatar field is required."]
    }
}
```

### Invalid Image Format (400)

```json
{
    "success": false,
    "message": "Invalid image format. Please provide a valid base64 encoded image."
}
```

### File Size Exceeded (400)

```json
{
    "success": false,
    "message": "Image size must be less than 5MB."
}
```

### Dimension Limit Exceeded (400)

```json
{
    "success": false,
    "message": "Image dimensions must be less than 2048x2048 pixels."
}
```

### Unsupported Format (400)

```json
{
    "success": false,
    "message": "Unsupported image format. Please use JPEG, PNG, or GIF."
}
```

### Server Error (500)

```json
{
    "success": false,
    "message": "Failed to upload avatar",
    "error": "Error details..."
}
```

## Authentication

All endpoints require authentication using Laravel Sanctum. Include the Bearer token in the Authorization header:

```
Authorization: Bearer {your_token}
```

## File Storage

-   **Storage Location**: `storage/app/public/avatars/`
-   **Public URL**: `/storage/avatars/`
-   **File Naming**: `avatar_{user_id}_{timestamp}.{extension}`
-   **Automatic Cleanup**: Old avatars are automatically deleted when new ones are uploaded

## Image Processing

The system automatically:

1. Validates the image format and size
2. Resizes images larger than 512x512 pixels
3. Optimizes JPEG quality to 85%
4. Maintains aspect ratio during resizing
5. Generates unique filenames to prevent conflicts

## Testing

Run the test suite to verify functionality:

```bash
php artisan test --filter=BrandProfileTest
```

## Frontend Integration Example

### JavaScript/TypeScript

```javascript
// Convert file to base64
function fileToBase64(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.readAsDataURL(file);
        reader.onload = () => resolve(reader.result);
        reader.onerror = (error) => reject(error);
    });
}

// Upload avatar
async function uploadAvatar(file) {
    try {
        const base64Image = await fileToBase64(file);

        const response = await fetch("/api/brand-profile/avatar", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Authorization: `Bearer ${token}`,
                Accept: "application/json",
            },
            body: JSON.stringify({
                avatar: base64Image,
            }),
        });

        const result = await response.json();

        if (result.success) {
            console.log("Avatar uploaded successfully:", result.data.avatar);
        } else {
            console.error("Upload failed:", result.message);
        }
    } catch (error) {
        console.error("Error uploading avatar:", error);
    }
}
```

### React Example

```jsx
import { useState } from "react";

function AvatarUpload() {
    const [avatar, setAvatar] = useState(null);
    const [loading, setLoading] = useState(false);

    const handleFileChange = async (event) => {
        const file = event.target.files[0];
        if (!file) return;

        setLoading(true);
        try {
            const base64Image = await fileToBase64(file);

            const response = await fetch("/api/brand-profile/avatar", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    Authorization: `Bearer ${token}`,
                    Accept: "application/json",
                },
                body: JSON.stringify({
                    avatar: base64Image,
                }),
            });

            const result = await response.json();

            if (result.success) {
                setAvatar(result.data.avatar);
            } else {
                alert(result.message);
            }
        } catch (error) {
            console.error("Error uploading avatar:", error);
            alert("Failed to upload avatar");
        } finally {
            setLoading(false);
        }
    };

    return (
        <div>
            <input
                type="file"
                accept="image/*"
                onChange={handleFileChange}
                disabled={loading}
            />
            {loading && <p>Uploading...</p>}
            {avatar && (
                <img
                    src={avatar}
                    alt="Avatar"
                    style={{ width: 100, height: 100, borderRadius: "50%" }}
                />
            )}
        </div>
    );
}
```

## Security Considerations

1. **File Type Validation**: Only image files are accepted
2. **Size Limits**: Prevents large file uploads
3. **Dimension Limits**: Prevents oversized images
4. **Unique Filenames**: Prevents filename conflicts
5. **Automatic Cleanup**: Removes old files to save storage
6. **Authentication Required**: All endpoints require valid authentication

## Performance Optimization

1. **Image Compression**: Automatic JPEG compression to 85% quality
2. **Resizing**: Large images are automatically resized to 512x512
3. **Caching**: Avatar URLs can be cached by the frontend
4. **CDN Ready**: File structure supports CDN integration
