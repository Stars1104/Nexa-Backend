# Campaign Management API Documentation

## Overview

This API provides comprehensive campaign management functionality with a complete approval workflow, bidding system, and file upload capabilities.

## Authentication

All API endpoints require authentication using Bearer tokens obtained through the login endpoint.

## File Upload Support

The API supports multiple file uploads for campaigns:

-   **Main Image**: Campaign banner/hero image (image_url or image file)
-   **Logo**: Brand logo (logo file)
-   **Attach File**: Additional documents (attach_file - supports PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, ZIP, RAR)

## Campaign Endpoints

### 1. Create Campaign

**POST** `/api/campaigns`

**Authorization**: Brand users only

**Content-Type**: `multipart/form-data` (when uploading files) or `application/json`

**Request Body:**

```json
{
    "title": "Summer Fashion Campaign",
    "description": "Looking for fashion influencers to promote our summer collection",
    "budget": 5000.0,
    "location": "New York, NY",
    "requirements": "Must have 10K+ followers, fashion-focused content",
    "target_states": ["NY", "CA", "FL"],
    "category": "fashion",
    "campaign_type": "instagram",
    "image_url": "https://example.com/campaign-image.jpg", // Optional: use this OR upload image file
    "deadline": "2024-12-31",
    "max_bids": 20
}
```

**File Upload Fields:**

-   `image` (optional): Campaign banner image (JPEG, PNG, JPG, GIF, WebP, max 5MB)
-   `logo` (optional): Brand logo (JPEG, PNG, JPG, GIF, WebP, max 5MB)
-   `attach_file` (optional): Additional documents (PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, ZIP, RAR, max 10MB)

**cURL Example with File Upload:**

```bash
curl -X POST http://localhost:8000/api/campaigns \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "title=Summer Fashion Campaign" \
  -F "description=Looking for fashion influencers" \
  -F "budget=5000" \
  -F "category=fashion" \
  -F "campaign_type=instagram" \
  -F "deadline=2024-12-31" \
  -F "max_bids=20" \
  -F "logo=@/path/to/logo.jpg" \
  -F "attach_file=@/path/to/document.pdf"
```

**Response:**

```json
{
    "success": true,
    "data": {
        "id": 1,
        "brand_id": 2,
        "title": "Summer Fashion Campaign",
        "description": "Looking for fashion influencers to promote our summer collection",
        "budget": "5000.00",
        "final_price": null,
        "location": "New York, NY",
        "requirements": "Must have 10K+ followers, fashion-focused content",
        "target_states": ["NY", "CA", "FL"],
        "category": "fashion",
        "campaign_type": "instagram",
        "image_url": "https://example.com/campaign-image.jpg",
        "logo": "/storage/campaigns/1641234567_abc123.jpg",
        "attach_file": "/storage/campaigns/1641234567_def456.pdf",
        "status": "pending",
        "deadline": "2024-12-31",
        "approved_at": null,
        "approved_by": null,
        "rejection_reason": null,
        "max_bids": 20,
        "is_active": true,
        "created_at": "2024-01-15T10:30:00.000000Z",
        "updated_at": "2024-01-15T10:30:00.000000Z",
        "brand": {
            "id": 2,
            "name": "Brand Name",
            "email": "brand@example.com"
        },
        "bids": []
    },
    "message": "Campaign created successfully and is pending approval"
}
```

### 2. Update Campaign

**PUT** `/api/campaigns/{id}`

**Authorization**: Brand users (own campaigns only), cannot update approved campaigns

**Content-Type**: `multipart/form-data` (when uploading files) or `application/json`

**Request Body:** (All fields optional)

```json
{
    "title": "Updated Campaign Title",
    "description": "Updated description",
    "budget": 6000.0,
    "location": "Los Angeles, CA",
    "requirements": "Updated requirements",
    "target_states": ["CA", "TX"],
    "category": "lifestyle",
    "campaign_type": "tiktok",
    "image_url": "https://example.com/new-image.jpg",
    "deadline": "2024-12-31",
    "max_bids": 25
}
```

**File Upload Fields:**

-   `image` (optional): New campaign banner image (replaces existing)
-   `logo` (optional): New brand logo (replaces existing)
-   `attach_file` (optional): New additional document (replaces existing)

**cURL Example with File Update:**

```bash
curl -X PUT http://localhost:8000/api/campaigns/1 \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "title=Updated Campaign Title" \
  -F "logo=@/path/to/new-logo.jpg" \
  -F "attach_file=@/path/to/new-document.pdf"
```

**Response:**

```json
{
    "success": true,
    "data": {
        "id": 1,
        "title": "Updated Campaign Title",
        "logo": "/storage/campaigns/1641234567_xyz789.jpg",
        "attach_file": "/storage/campaigns/1641234567_abc123.pdf"
        // ... other fields
    },
    "message": "Campaign updated successfully"
}
```

### 3. Get All Campaigns

**GET** `/api/campaigns`

**Authorization**: All authenticated users

**Query Parameters:**

-   `status` (optional): Filter by status (pending, approved, rejected, completed, cancelled)
-   `category` (optional): Filter by category
-   `campaign_type` (optional): Filter by campaign type
-   `state` (optional): Filter by target state
-   `budget_min` (optional): Minimum budget filter
-   `budget_max` (optional): Maximum budget filter
-   `sort_by` (optional): Sort field (default: created_at)
-   `sort_order` (optional): Sort direction (asc/desc, default: desc)
-   `per_page` (optional): Items per page (default: 15)

**Response:**

```json
{
    "success": true,
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 1,
                "title": "Summer Fashion Campaign",
                "description": "Looking for fashion influencers",
                "budget": "5000.00",
                "category": "fashion",
                "campaign_type": "instagram",
                "image_url": "https://example.com/campaign-image.jpg",
                "logo": "/storage/campaigns/1641234567_abc123.jpg",
                "attach_file": "/storage/campaigns/1641234567_def456.pdf",
                "status": "approved",
                "deadline": "2024-12-31",
                "is_active": true,
                "brand": {
                    "id": 2,
                    "name": "Brand Name",
                    "email": "brand@example.com"
                },
                "total_bids": 5
            }
        ],
        "per_page": 15,
        "total": 1
    },
    "message": "Campaigns retrieved successfully"
}
```

### 4. Get Single Campaign

**GET** `/api/campaigns/{id}`

**Authorization**:

-   Creators: Only approved campaigns
-   Brands: Only own campaigns
-   Admins: All campaigns

**Response:**

```json
{
    "success": true,
    "data": {
        "id": 1,
        "title": "Summer Fashion Campaign",
        "description": "Looking for fashion influencers to promote our summer collection",
        "budget": "5000.00",
        "final_price": null,
        "location": "New York, NY",
        "requirements": "Must have 10K+ followers, fashion-focused content",
        "target_states": ["NY", "CA", "FL"],
        "category": "fashion",
        "campaign_type": "instagram",
        "image_url": "https://example.com/campaign-image.jpg",
        "logo": "/storage/campaigns/1641234567_abc123.jpg",
        "attach_file": "/storage/campaigns/1641234567_def456.pdf",
        "status": "approved",
        "deadline": "2024-12-31",
        "approved_at": "2024-01-16T09:00:00.000000Z",
        "approved_by": 1,
        "rejection_reason": null,
        "max_bids": 20,
        "is_active": true,
        "created_at": "2024-01-15T10:30:00.000000Z",
        "updated_at": "2024-01-16T09:00:00.000000Z",
        "brand": {
            "id": 2,
            "name": "Brand Name",
            "email": "brand@example.com"
        },
        "approved_by": {
            "id": 1,
            "name": "Admin User",
            "email": "admin@example.com"
        },
        "bids": [
            {
                "id": 1,
                "user_id": 3,
                "bid_amount": "1000.00",
                "proposal": "I can create amazing content for your campaign",
                "portfolio_links": ["https://instagram.com/creator1"],
                "estimated_delivery_days": 7,
                "status": "pending",
                "created_at": "2024-01-16T11:00:00.000000Z",
                "user": {
                    "id": 3,
                    "name": "Creator User",
                    "email": "creator@example.com"
                }
            }
        ]
    },
    "message": "Campaign retrieved successfully"
}
```

### 5. Delete Campaign

**DELETE** `/api/campaigns/{id}`

**Authorization**: Brand users (own campaigns only)

**Note**: Cannot delete approved campaigns with bids

**Response:**

```json
{
    "success": true,
    "message": "Campaign deleted successfully"
}
```

### 6. Approve Campaign (Admin Only)

**POST** `/api/campaigns/{id}/approve`

**Authorization**: Admin users only

**Response:**

```json
{
    "success": true,
    "data": {
        "id": 1,
        "status": "approved",
        "approved_at": "2024-01-16T09:00:00.000000Z",
        "approved_by": 1
        // ... other fields
    },
    "message": "Campaign approved successfully"
}
```

### 7. Reject Campaign (Admin Only)

**POST** `/api/campaigns/{id}/reject`

**Authorization**: Admin users only

**Request Body:**

```json
{
    "reason": "Campaign content does not meet our guidelines"
}
```

**Response:**

```json
{
    "success": true,
    "data": {
        "id": 1,
        "status": "rejected",
        "rejection_reason": "Campaign content does not meet our guidelines"
        // ... other fields
    },
    "message": "Campaign rejected successfully"
}
```

### 8. Toggle Campaign Active Status

**POST** `/api/campaigns/{id}/toggle-active`

**Authorization**: Brand users (own campaigns only)

**Response:**

```json
{
    "success": true,
    "data": {
        "id": 1,
        "is_active": false
        // ... other fields
    },
    "message": "Campaign status updated successfully"
}
```

### 9. Get Campaign Statistics

**GET** `/api/campaigns/statistics`

**Authorization**: All authenticated users

**Admin Response:**

```json
{
    "success": true,
    "data": {
        "total_campaigns": 50,
        "pending_campaigns": 5,
        "approved_campaigns": 40,
        "rejected_campaigns": 3,
        "completed_campaigns": 2,
        "total_bids": 200,
        "accepted_bids": 15
    },
    "message": "Statistics retrieved successfully"
}
```

**Brand Response:**

```json
{
    "success": true,
    "data": {
        "my_campaigns": 10,
        "pending_campaigns": 2,
        "approved_campaigns": 7,
        "rejected_campaigns": 1,
        "completed_campaigns": 0,
        "total_bids_received": 45
    },
    "message": "Statistics retrieved successfully"
}
```

**Creator Response:**

```json
{
    "success": true,
    "data": {
        "available_campaigns": 40,
        "my_bids": 15,
        "pending_bids": 8,
        "accepted_bids": 3,
        "rejected_bids": 4
    },
    "message": "Statistics retrieved successfully"
}
```

## Bid Endpoints

### 10. Get All Bids

**GET** `/api/bids`

**Authorization**: All authenticated users

**Query Parameters:**

-   `status` (optional): Filter by status (pending, accepted, rejected, withdrawn)
-   `campaign_id` (optional): Filter by campaign ID
-   `sort_by` (optional): Sort field (default: created_at)
-   `sort_order` (optional): Sort direction (asc/desc, default: desc)
-   `per_page` (optional): Items per page (default: 15)

**Response:**

```json
{
    "success": true,
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 1,
                "campaign_id": 1,
                "user_id": 3,
                "bid_amount": "1000.00",
                "proposal": "I can create amazing content for your campaign",
                "portfolio_links": ["https://instagram.com/creator1"],
                "estimated_delivery_days": 7,
                "status": "pending",
                "created_at": "2024-01-16T11:00:00.000000Z",
                "user": {
                    "id": 3,
                    "name": "Creator User",
                    "email": "creator@example.com"
                },
                "campaign": {
                    "id": 1,
                    "title": "Summer Fashion Campaign",
                    "budget": "5000.00",
                    "brand": {
                        "id": 2,
                        "name": "Brand Name"
                    }
                }
            }
        ],
        "per_page": 15,
        "total": 1
    },
    "message": "Bids retrieved successfully"
}
```

### 11. Create Bid

**POST** `/api/campaigns/{campaign_id}/bids`

**Authorization**: Creator users only

**Request Body:**

```json
{
    "bid_amount": 1000.0,
    "proposal": "I can create amazing content for your campaign with high engagement rates",
    "portfolio_links": [
        "https://instagram.com/creator1",
        "https://tiktok.com/@creator1"
    ],
    "estimated_delivery_days": 7
}
```

**Response:**

```json
{
    "success": true,
    "data": {
        "id": 1,
        "campaign_id": 1,
        "user_id": 3,
        "bid_amount": "1000.00",
        "proposal": "I can create amazing content for your campaign with high engagement rates",
        "portfolio_links": [
            "https://instagram.com/creator1",
            "https://tiktok.com/@creator1"
        ],
        "estimated_delivery_days": 7,
        "status": "pending",
        "created_at": "2024-01-16T11:00:00.000000Z",
        "updated_at": "2024-01-16T11:00:00.000000Z",
        "user": {
            "id": 3,
            "name": "Creator User",
            "email": "creator@example.com"
        },
        "campaign": {
            "id": 1,
            "title": "Summer Fashion Campaign",
            "budget": "5000.00"
        }
    },
    "message": "Bid created successfully"
}
```

### 12. Get Single Bid

**GET** `/api/bids/{id}`

**Authorization**:

-   Creators: Only own bids
-   Brands: Only bids on own campaigns
-   Admins: All bids

**Response:**

```json
{
    "success": true,
    "data": {
        "id": 1,
        "campaign_id": 1,
        "user_id": 3,
        "bid_amount": "1000.00",
        "proposal": "I can create amazing content for your campaign with high engagement rates",
        "portfolio_links": [
            "https://instagram.com/creator1",
            "https://tiktok.com/@creator1"
        ],
        "estimated_delivery_days": 7,
        "status": "pending",
        "accepted_at": null,
        "rejection_reason": null,
        "created_at": "2024-01-16T11:00:00.000000Z",
        "updated_at": "2024-01-16T11:00:00.000000Z",
        "user": {
            "id": 3,
            "name": "Creator User",
            "email": "creator@example.com"
        },
        "campaign": {
            "id": 1,
            "title": "Summer Fashion Campaign",
            "budget": "5000.00",
            "brand": {
                "id": 2,
                "name": "Brand Name"
            }
        }
    },
    "message": "Bid retrieved successfully"
}
```

### 13. Update Bid

**PUT** `/api/bids/{id}`

**Authorization**: Creator users (own bids only), only pending bids

**Request Body:** (All fields optional)

```json
{
    "bid_amount": 1200.0,
    "proposal": "Updated proposal with more details",
    "portfolio_links": [
        "https://instagram.com/creator1",
        "https://tiktok.com/@creator1",
        "https://youtube.com/creator1"
    ],
    "estimated_delivery_days": 5
}
```

**Response:**

```json
{
    "success": true,
    "data": {
        "id": 1,
        "bid_amount": "1200.00",
        "proposal": "Updated proposal with more details"
        // ... other fields
    },
    "message": "Bid updated successfully"
}
```

### 14. Delete Bid

**DELETE** `/api/bids/{id}`

**Authorization**: Creator users (own bids only)

**Note**: Cannot delete accepted bids

**Response:**

```json
{
    "success": true,
    "message": "Bid deleted successfully"
}
```

### 15. Accept Bid (Brand Only)

**POST** `/api/bids/{id}/accept`

**Authorization**: Brand users (own campaign bids only)

**Response:**

```json
{
    "success": true,
    "data": {
        "id": 1,
        "status": "accepted",
        "accepted_at": "2024-01-17T10:00:00.000000Z"
        // ... other fields
    },
    "message": "Bid accepted successfully"
}
```

### 16. Reject Bid (Brand Only)

**POST** `/api/bids/{id}/reject`

**Authorization**: Brand users (own campaign bids only)

**Request Body:**

```json
{
    "reason": "Budget exceeds our allocation for this campaign"
}
```

**Response:**

```json
{
    "success": true,
    "data": {
        "id": 1,
        "status": "rejected",
        "rejection_reason": "Budget exceeds our allocation for this campaign"
        // ... other fields
    },
    "message": "Bid rejected successfully"
}
```

### 17. Withdraw Bid (Creator Only)

**POST** `/api/bids/{id}/withdraw`

**Authorization**: Creator users (own bids only)

**Request Body:**

```json
{
    "reason": "No longer available for this timeframe"
}
```

**Response:**

```json
{
    "success": true,
    "data": {
        "id": 1,
        "status": "withdrawn",
        "rejection_reason": "No longer available for this timeframe"
        // ... other fields
    },
    "message": "Bid withdrawn successfully"
}
```

### 18. Get Campaign Bids

**GET** `/api/campaigns/{campaign_id}/bids`

**Authorization**:

-   Brands: Only own campaign bids
-   Admins: All campaign bids

**Query Parameters:**

-   `status` (optional): Filter by status (pending, accepted, rejected, withdrawn)
-   `sort_by` (optional): Sort field (default: created_at)
-   `sort_order` (optional): Sort direction (asc/desc, default: desc)
-   `per_page` (optional): Items per page (default: 15)

**Response:**

```json
{
    "success": true,
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 1,
                "user_id": 3,
                "bid_amount": "1000.00",
                "proposal": "I can create amazing content for your campaign",
                "portfolio_links": ["https://instagram.com/creator1"],
                "estimated_delivery_days": 7,
                "status": "pending",
                "created_at": "2024-01-16T11:00:00.000000Z",
                "user": {
                    "id": 3,
                    "name": "Creator User",
                    "email": "creator@example.com"
                }
            }
        ],
        "per_page": 15,
        "total": 1
    },
    "message": "Campaign bids retrieved successfully"
}
```

## Error Responses

### Validation Error (422)

```json
{
    "success": false,
    "message": "The given data was invalid.",
    "errors": {
        "title": ["The title field is required."],
        "budget": ["The budget must be at least 1."],
        "logo": ["The logo must be an image."],
        "attach_file": [
            "The attach file must be a file of type: pdf, doc, docx, xls, xlsx, ppt, pptx, txt, zip, rar."
        ]
    }
}
```

### Unauthorized (401)

```json
{
    "success": false,
    "message": "Unauthenticated."
}
```

### Forbidden (403)

```json
{
    "success": false,
    "error": "Unauthorized"
}
```

### Not Found (404)

```json
{
    "success": false,
    "error": "Campaign not found"
}
```

### Server Error (500)

```json
{
    "success": false,
    "error": "Internal server error"
}
```

## File Upload Guidelines

### Supported File Types

-   **Images (logo, image)**: JPEG, PNG, JPG, GIF, WebP
-   **Documents (attach_file)**: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, ZIP, RAR

### File Size Limits

-   **Images**: Maximum 5MB
-   **Documents**: Maximum 10MB

### File Storage

-   All uploaded files are stored in `/storage/campaigns/` directory
-   Files are automatically renamed with timestamp and unique ID for security
-   Old files are automatically deleted when replaced or when campaign is deleted

### File Access

-   Uploaded files are accessible via their returned URLs
-   Files are served through Laravel's storage system
-   Proper permissions are enforced through the application

## Usage Examples

### Frontend Integration

```javascript
// Create campaign with file uploads
const formData = new FormData();
formData.append("title", "My Campaign");
formData.append("description", "Campaign description");
formData.append("budget", "5000");
formData.append("category", "fashion");
formData.append("campaign_type", "instagram");
formData.append("deadline", "2024-12-31");

// Add files
const logoFile = document.getElementById("logo-input").files[0];
const attachFile = document.getElementById("attach-input").files[0];

if (logoFile) {
    formData.append("logo", logoFile);
}

if (attachFile) {
    formData.append("attach_file", attachFile);
}

fetch("/api/campaigns", {
    method: "POST",
    headers: {
        Authorization: `Bearer ${token}`,
    },
    body: formData,
})
    .then((response) => response.json())
    .then((data) => {
        console.log("Campaign created:", data);
    });
```

### File Upload Validation

```javascript
// Client-side validation
function validateFiles() {
    const logoFile = document.getElementById("logo-input").files[0];
    const attachFile = document.getElementById("attach-input").files[0];

    if (logoFile) {
        if (
            ![
                "image/jpeg",
                "image/png",
                "image/jpg",
                "image/gif",
                "image/webp",
            ].includes(logoFile.type)
        ) {
            alert("Logo must be an image file");
            return false;
        }
        if (logoFile.size > 5 * 1024 * 1024) {
            // 5MB
            alert("Logo file size must be under 5MB");
            return false;
        }
    }

    if (attachFile) {
        const allowedTypes = [
            "application/pdf",
            "application/msword",
            "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
            "application/vnd.ms-excel",
            "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
            "application/vnd.ms-powerpoint",
            "application/vnd.openxmlformats-officedocument.presentationml.presentation",
            "text/plain",
            "application/zip",
            "application/x-rar-compressed",
        ];

        if (!allowedTypes.includes(attachFile.type)) {
            alert("Attach file must be a document file");
            return false;
        }
        if (attachFile.size > 10 * 1024 * 1024) {
            // 10MB
            alert("Attach file size must be under 10MB");
            return false;
        }
    }

    return true;
}
```

This API provides a complete campaign management system with robust file upload capabilities, proper validation, and secure file handling.
