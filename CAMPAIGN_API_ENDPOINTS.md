# Campaign API Endpoints Documentation

This document describes all the available campaign API endpoints in the backend.

## Base URL

All endpoints are prefixed with `/api/campaigns`

## Authentication

All endpoints require authentication via Bearer token in the Authorization header:

```
Authorization: Bearer YOUR_TOKEN_HERE
```

## Endpoints Overview

### 1. Get All Campaigns

**GET** `/api/campaigns/` or `/api/campaigns/get-campaigns`

Returns a paginated list of campaigns with advanced filtering, sorting, and role-based access control.

**Query Parameters:**

-   `search` (string) - Search in title, description, or requirements
-   `status` (string) - Filter by status: pending, approved, rejected, completed, cancelled
-   `is_active` (boolean) - Filter by active status
-   `category` (string) - Filter by category
-   `campaign_type` (string) - Filter by campaign type
-   `state` (string) - Filter by target state
-   `budget_min` (number) - Minimum budget filter
-   `budget_max` (number) - Maximum budget filter
-   `created_from` (date) - Filter campaigns created from this date
-   `created_to` (date) - Filter campaigns created until this date
-   `deadline_from` (date) - Filter campaigns with deadline from this date
-   `deadline_to` (date) - Filter campaigns with deadline until this date
-   `has_bids` (boolean) - Filter campaigns that have/don't have bids
-   `brand_id` (number) - Filter by brand ID (admin only)
-   `sort_by` (string) - Sort field: created_at, updated_at, title, budget, deadline, status, is_active, total_bids
-   `sort_order` (string) - Sort order: asc, desc
-   `per_page` (number) - Items per page (1-100, default: 15)

**Response:**

```json
{
  "success": true,
  "data": {
    "data": [...],
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 75
  },
  "message": "Campaigns retrieved successfully",
  "meta": {
    "total": 75,
    "per_page": 15,
    "current_page": 1,
    "last_page": 5,
    "from": 1,
    "to": 15,
    "filters_applied": {...}
  }
}
```

### 2. Get Pending Campaigns

**GET** `/api/campaigns/pending`

Returns campaigns with "pending" status. Only admins and brand owners can access this endpoint.

**Query Parameters:**

-   `sort_by` (string) - Sort field
-   `sort_order` (string) - Sort order: asc, desc
-   `per_page` (number) - Items per page

**Response:**

```json
{
  "success": true,
  "data": {...},
  "message": "Pending campaigns retrieved successfully",
  "meta": {...}
}
```

### 3. Get Campaigns by User

**GET** `/api/campaigns/user/{userId}`

Returns campaigns created by a specific user. Admins can view any user's campaigns, brands can only view their own, creators cannot view other users' campaigns.

**Path Parameters:**

-   `userId` (number) - The ID of the user whose campaigns to retrieve

**Query Parameters:**

-   `status` (string) - Filter by status
-   `is_active` (boolean) - Filter by active status
-   `sort_by` (string) - Sort field
-   `sort_order` (string) - Sort order
-   `per_page` (number) - Items per page

**Response:**

```json
{
  "success": true,
  "data": {...},
  "message": "User campaigns retrieved successfully",
  "meta": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "role": "brand"
    },
    ...
  }
}
```

### 4. Get Campaigns by Status

**GET** `/api/campaigns/status/{status}`

Returns campaigns filtered by a specific status.

**Path Parameters:**

-   `status` (string) - Campaign status: pending, approved, rejected, completed, cancelled

**Query Parameters:**

-   `category` (string) - Filter by category
-   `campaign_type` (string) - Filter by campaign type
-   `budget_min` (number) - Minimum budget filter
-   `budget_max` (number) - Maximum budget filter
-   `sort_by` (string) - Sort field
-   `sort_order` (string) - Sort order
-   `per_page` (number) - Items per page

**Response:**

```json
{
  "success": true,
  "data": {...},
  "message": "Campaigns with status 'approved' retrieved successfully",
  "meta": {
    "status": "approved",
    ...
  }
}
```

### 5. Get Single Campaign

**GET** `/api/campaigns/{campaignId}`

Returns detailed information about a specific campaign.

**Path Parameters:**

-   `campaignId` (number) - The ID of the campaign to retrieve

**Response:**

```json
{
  "success": true,
  "data": {
    "id": 1,
    "title": "Campaign Title",
    "description": "Campaign description",
    "budget": "1000.00",
    "final_price": null,
    "location": "New York",
    "requirements": "Requirements text",
    "target_states": ["NY", "CA"],
    "category": "technology",
    "campaign_type": "social_media",
    "image_url": "https://...",
    "logo": "https://...",
    "attach_file": "https://...",
    "status": "approved",
    "deadline": "2024-12-31",
    "is_active": true,
    "max_bids": 10,
    "created_at": "2024-01-01T00:00:00.000000Z",
    "updated_at": "2024-01-01T00:00:00.000000Z",
    "approved_at": "2024-01-02T00:00:00.000000Z",
    "rejection_reason": null,
    "brand": {
      "id": 1,
      "name": "Brand Name",
      "email": "brand@example.com",
      "avatar": "https://..."
    },
    "approved_by": {
      "id": 2,
      "name": "Admin Name",
      "email": "admin@example.com"
    },
    "bids": [...],
    "total_bids": 5,
    "can_receive_bids": true,
    "has_accepted_bid": false,
    "accepted_bid": null
  },
  "message": "Campaign retrieved successfully"
}
```

### 6. Create Campaign

**POST** `/api/campaigns/`

Creates a new campaign. Only brands can create campaigns.

**Request Body:**

```json
{
    "title": "Campaign Title",
    "description": "Campaign description",
    "budget": 1000.0,
    "location": "New York",
    "requirements": "Requirements text",
    "target_states": ["NY", "CA"],
    "category": "technology",
    "campaign_type": "social_media",
    "deadline": "2024-12-31",
    "max_bids": 10,
    "logo": "file",
    "attach_file": "file"
}
```

**Response:**

```json
{
  "success": true,
  "data": {...},
  "message": "Campaign created successfully and is pending approval"
}
```

### 7. Update Campaign

**PUT** `/api/campaigns/{campaignId}`

Updates an existing campaign. Only the brand owner can update their campaigns.

**Path Parameters:**

-   `campaignId` (number) - The ID of the campaign to update

**Request Body:** Same as create campaign

**Response:**

```json
{
  "success": true,
  "data": {...},
  "message": "Campaign updated successfully"
}
```

### 8. Delete Campaign

**DELETE** `/api/campaigns/{campaignId}`

Deletes a campaign. Only the brand owner can delete their campaigns, and only if they don't have accepted bids.

**Path Parameters:**

-   `campaignId` (number) - The ID of the campaign to delete

**Response:**

```json
{
    "success": true,
    "message": "Campaign deleted successfully"
}
```

### 9. Approve Campaign

**PATCH** `/api/campaigns/{campaignId}/approve`

Approves a pending campaign. Only admins can approve campaigns.

**Path Parameters:**

-   `campaignId` (number) - The ID of the campaign to approve

**Response:**

```json
{
  "success": true,
  "data": {...},
  "message": "Campaign approved successfully"
}
```

### 10. Reject Campaign

**PATCH** `/api/campaigns/{campaignId}/reject`

Rejects a pending campaign. Only admins can reject campaigns.

**Path Parameters:**

-   `campaignId` (number) - The ID of the campaign to reject

**Request Body:**

```json
{
    "reason": "Rejection reason"
}
```

**Response:**

```json
{
  "success": true,
  "data": {...},
  "message": "Campaign rejected successfully"
}
```

### 11. Archive Campaign

**PATCH** `/api/campaigns/{campaignId}/archive`

Archives a campaign by setting it to cancelled status. Admins and brand owners can archive campaigns.

**Path Parameters:**

-   `campaignId` (number) - The ID of the campaign to archive

**Response:**

```json
{
  "success": true,
  "data": {...},
  "message": "Campaign archived successfully"
}
```

### 12. Toggle Campaign Active Status

**POST** `/api/campaigns/{campaignId}/toggle-active`

Toggles the active status of a campaign. Only brand owners can toggle their campaigns.

**Path Parameters:**

-   `campaignId` (number) - The ID of the campaign to toggle

**Response:**

```json
{
  "success": true,
  "data": {...},
  "message": "Campaign status updated successfully"
}
```

### 13. Get Campaign Statistics

**GET** `/api/campaigns/statistics`

Returns campaign statistics based on user role.

**Response (Admin):**

```json
{
    "success": true,
    "data": {
        "total_campaigns": 100,
        "pending_campaigns": 15,
        "approved_campaigns": 60,
        "rejected_campaigns": 10,
        "completed_campaigns": 15,
        "total_bids": 250,
        "accepted_bids": 45
    },
    "message": "Statistics retrieved successfully"
}
```

**Response (Brand):**

```json
{
    "success": true,
    "data": {
        "my_campaigns": 25,
        "pending_campaigns": 5,
        "approved_campaigns": 15,
        "rejected_campaigns": 2,
        "completed_campaigns": 3,
        "total_bids_received": 50
    },
    "message": "Statistics retrieved successfully"
}
```

**Response (Creator):**

```json
{
    "success": true,
    "data": {
        "available_campaigns": 60,
        "my_bids": 15,
        "pending_bids": 8,
        "accepted_bids": 3,
        "rejected_bids": 4
    },
    "message": "Statistics retrieved successfully"
}
```

## Error Responses

All endpoints return consistent error responses:

**401 Unauthorized:**

```json
{
    "success": false,
    "error": "Authentication required"
}
```

**403 Forbidden:**

```json
{
    "success": false,
    "error": "Unauthorized to perform this action"
}
```

**404 Not Found:**

```json
{
    "success": false,
    "error": "Campaign not found"
}
```

**422 Validation Error:**

```json
{
    "success": false,
    "error": "Validation failed",
    "errors": {
        "title": ["The title field is required."]
    }
}
```

**500 Server Error:**

```json
{
    "success": false,
    "error": "Failed to retrieve campaigns",
    "message": "An error occurred while retrieving campaigns"
}
```

## Role-Based Access Control

### Admin

-   Can view all campaigns
-   Can approve/reject pending campaigns
-   Can archive any campaign
-   Can view all statistics

### Brand

-   Can view only their own campaigns
-   Can create, update, delete their campaigns
-   Can archive their own campaigns
-   Can toggle active status of their campaigns
-   Can view their own statistics

### Creator

-   Can view only approved and active campaigns
-   Cannot view pending, rejected, or other users' campaigns
-   Can view their own bid statistics

## Notes

1. All endpoints include comprehensive error handling and logging
2. Pagination is implemented with configurable page sizes (1-100 items)
3. Advanced filtering and sorting options are available
4. Eager loading is used to optimize database queries
5. File uploads are supported for logos and attachments
6. All responses follow a consistent JSON structure
7. Role-based access control is enforced at the API level
