# Campaign Management System Summary

## Overview

This is a comprehensive campaign management system built with Laravel that enables brands to create campaigns, get admin approval, and receive bids from creators. The system includes advanced file upload capabilities and a complete approval workflow.

## Key Features

### 1. **User Role System**

-   **Admin**: Can approve/reject campaigns, view all data, manage system
-   **Brand**: Can create campaigns, manage their campaigns, accept/reject bids
-   **Creator**: Can view approved campaigns, place bids, manage their bids

### 2. **Campaign Management**

-   Create campaigns with detailed information
-   **File Upload Support**:
    -   Main campaign image (banner/hero image)
    -   Brand logo upload
    -   Attach file for additional documents (PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, ZIP, RAR)
-   Pending approval workflow
-   Admin approval/rejection system
-   Campaign statistics and filtering

### 3. **Bidding System**

-   Creators bid on approved campaigns
-   Brands can accept/reject bids
-   Automatic bid rejection when one is accepted
-   Bid withdrawal functionality
-   Portfolio links and proposal system

### 4. **File Upload Features**

-   **Logo Upload**: Brand logo support (JPEG, PNG, JPG, GIF, WebP, max 5MB)
-   **Attach File**: Document uploads (PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, ZIP, RAR, max 10MB)
-   **Image Upload**: Campaign banner images (JPEG, PNG, JPG, GIF, WebP, max 5MB)
-   Automatic file cleanup when replaced or deleted
-   Secure file storage with unique naming
-   Proper file validation and error handling

## Database Schema

### Campaigns Table

-   `id` - Primary key
-   `brand_id` - Foreign key to users table
-   `title` - Campaign title
-   `description` - Campaign description
-   `budget` - Campaign budget
-   `final_price` - Final accepted bid amount
-   `location` - Campaign location
-   `requirements` - Campaign requirements
-   `target_states` - JSON array of target states
-   `category` - Campaign category
-   `campaign_type` - Type of campaign (instagram, tiktok, youtube, etc.)
-   `image_url` - Campaign image URL
-   `logo` - Brand logo file path
-   `attach_file` - Additional document file path
-   `status` - Campaign status (pending, approved, rejected, completed, cancelled)
-   `deadline` - Campaign deadline
-   `approved_at` - Approval timestamp
-   `approved_by` - Admin who approved
-   `rejection_reason` - Rejection reason if rejected
-   `max_bids` - Maximum number of bids allowed
-   `is_active` - Whether campaign is active

### Bids Table

-   `id` - Primary key
-   `campaign_id` - Foreign key to campaigns table
-   `user_id` - Foreign key to users table (creator)
-   `bid_amount` - Bid amount
-   `proposal` - Bid proposal text
-   `portfolio_links` - JSON array of portfolio links
-   `estimated_delivery_days` - Estimated delivery time
-   `status` - Bid status (pending, accepted, rejected, withdrawn)
-   `accepted_at` - Acceptance timestamp
-   `rejection_reason` - Rejection reason if rejected

## API Endpoints

### Campaign Endpoints (9 total)

1. `GET /api/campaigns` - List campaigns with filtering
2. `POST /api/campaigns` - Create new campaign (with file upload support)
3. `GET /api/campaigns/{id}` - Get single campaign
4. `PUT /api/campaigns/{id}` - Update campaign (with file upload support)
5. `DELETE /api/campaigns/{id}` - Delete campaign
6. `POST /api/campaigns/{id}/approve` - Approve campaign (Admin only)
7. `POST /api/campaigns/{id}/reject` - Reject campaign (Admin only)
8. `POST /api/campaigns/{id}/toggle-active` - Toggle campaign active status
9. `GET /api/campaigns/statistics` - Get campaign statistics

### Bid Endpoints (9 total)

1. `GET /api/bids` - List all bids
2. `POST /api/campaigns/{id}/bids` - Create bid for campaign
3. `GET /api/bids/{id}` - Get single bid
4. `PUT /api/bids/{id}` - Update bid
5. `DELETE /api/bids/{id}` - Delete bid
6. `POST /api/bids/{id}/accept` - Accept bid (Brand only)
7. `POST /api/bids/{id}/reject` - Reject bid (Brand only)
8. `POST /api/bids/{id}/withdraw` - Withdraw bid (Creator only)
9. `GET /api/campaigns/{id}/bids` - Get campaign bids

## Workflow

### Campaign Creation & Approval

1. **Brand creates campaign** with optional file uploads (logo, attach_file, image)
2. **Campaign status**: `pending`
3. **Admin reviews** and approves/rejects
4. **If approved**: Campaign becomes visible to creators
5. **If rejected**: Campaign remains hidden with rejection reason

### Bidding Process

1. **Creator views** approved active campaigns
2. **Creator places bid** with proposal and portfolio links
3. **Brand reviews bids** on their campaigns
4. **Brand accepts one bid** (others automatically rejected)
5. **Campaign status** can be updated to `completed`

### File Upload Process

1. **File validation** (type, size, format)
2. **Secure storage** in `/storage/campaigns/` directory
3. **Unique naming** with timestamp and UUID
4. **Automatic cleanup** when files are replaced or campaigns deleted
5. **URL generation** for file access

## Authorization Rules

### Campaign Access

-   **Admins**: Full access to all campaigns
-   **Brands**: Can only manage their own campaigns
-   **Creators**: Can only view approved, active campaigns

### Bid Access

-   **Admins**: Full access to all bids
-   **Brands**: Can only see bids on their campaigns
-   **Creators**: Can only see and manage their own bids

### File Upload Access

-   **Brands**: Can upload files for their campaigns
-   **File limits**: Images (5MB), Documents (10MB)
-   **Automatic validation**: File type and size checking

## File Upload Features

### Supported File Types

-   **Logo**: JPEG, PNG, JPG, GIF, WebP (max 5MB)
-   **Attach File**: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, ZIP, RAR (max 10MB)
-   **Campaign Image**: JPEG, PNG, JPG, GIF, WebP (max 5MB)

### File Management

-   **Automatic cleanup**: Old files deleted when replaced
-   **Secure storage**: Files stored with unique names
-   **Proper validation**: File type and size validation
-   **Error handling**: Comprehensive error messages

## Key Models

### Campaign Model

-   Relationships: belongsTo User (brand), belongsTo User (approvedBy), hasMany Bids
-   Scopes: approved(), pending(), active(), forState(), byCategory(), byType()
-   Methods: approve(), reject(), complete(), cancel(), canReceiveBids()

### Bid Model

-   Relationships: belongsTo Campaign, belongsTo User
-   Scopes: pending(), accepted(), rejected(), withdrawn()
-   Methods: accept(), reject(), withdraw()

### User Model

-   Relationships: hasMany Campaigns, hasMany Bids
-   Methods: isAdmin(), isBrand(), isCreator()

## Validation & Security

### Request Validation

-   **StoreCampaignRequest**: Validates campaign creation including file uploads
-   **UpdateCampaignRequest**: Validates campaign updates including file uploads
-   **StoreBidRequest**: Validates bid creation with authorization checks

### File Upload Security

-   **File type validation**: Strict MIME type checking
-   **File size limits**: 5MB for images, 10MB for documents
-   **Secure storage**: Files stored outside public directory
-   **Unique naming**: Prevents file name conflicts
-   **Automatic cleanup**: Prevents orphaned files

### Authorization

-   **Middleware**: Ensures proper user authentication
-   **Role-based access**: Different permissions for different user types
-   **Ownership checks**: Users can only modify their own resources

## Statistics & Reporting

### Admin Statistics

-   Total campaigns, pending campaigns, approved campaigns
-   Rejected campaigns, completed campaigns
-   Total bids, accepted bids

### Brand Statistics

-   My campaigns, pending campaigns, approved campaigns
-   Rejected campaigns, completed campaigns
-   Total bids received

### Creator Statistics

-   Available campaigns, my bids, pending bids
-   Accepted bids, rejected bids

## Testing Data

### Sample Users

-   Admin: admin@example.com / password123
-   Brand 1: brand1@example.com / password123
-   Brand 2: brand2@example.com / password123
-   Creator 1: creator1@example.com / password123
-   Creator 2: creator2@example.com / password123
-   Creator 3: creator3@example.com / password123

### Sample Campaigns

-   4 campaigns with different statuses
-   2 approved campaigns (visible to creators)
-   2 pending campaigns (waiting for approval)
-   File upload examples included

### Sample Bids

-   6 bids demonstrating the complete workflow
-   Different bid statuses (pending, accepted, rejected)
-   Portfolio links and proposals included

## File Storage Structure

```
storage/
├── app/
│   └── public/
│       └── campaigns/
│           ├── 1641234567_abc123.jpg (logo)
│           ├── 1641234567_def456.pdf (attach_file)
│           └── 1641234567_ghi789.jpg (image)
└── logs/
```

## Technical Features

### Advanced Query Features

-   **Pagination**: Efficient data loading
-   **Filtering**: Multiple filter options
-   **Sorting**: Flexible sorting capabilities
-   **Search**: Campaign search functionality

### Error Handling

-   **Validation errors**: Detailed field-specific errors
-   **Authorization errors**: Clear permission messages
-   **File upload errors**: Specific file-related error messages
-   **Business logic errors**: Contextual error responses

### Performance Optimizations

-   **Eager loading**: Relationships loaded efficiently
-   **Indexes**: Database indexes for common queries
-   **File storage**: Efficient file storage and retrieval
-   **Caching**: Prepared for caching implementation

This system provides a complete, production-ready campaign management solution with robust file upload capabilities, proper security measures, and comprehensive API documentation.
