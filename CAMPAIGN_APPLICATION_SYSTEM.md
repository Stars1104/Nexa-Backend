# Campaign Application System

## Overview

The Campaign Application System allows creators to apply directly to campaigns, and brands to approve or reject these applications. When a brand approves an application, a chat is automatically created between the creator and brand for further communication.

## System Flow

1. **Creator applies to Campaign** → Application is created with 'pending' status
2. **Brand reviews application** → Brand can approve or reject the application
3. **If approved** → Chat is automatically created between Creator and Brand
4. **Communication** → Creator and Brand can communicate through the chat system

## Database Structure

### Campaign Applications Table

```sql
campaign_applications
├── id (Primary Key)
├── campaign_id (Foreign Key)
├── creator_id (Foreign Key to users)
├── status (enum: 'pending', 'approved', 'rejected')
├── proposal (text) - Creator's proposal/cover letter
├── portfolio_links (json) - Array of portfolio links
├── estimated_delivery_days (integer)
├── proposed_budget (decimal)
├── rejection_reason (text) - Reason if rejected
├── reviewed_by (Foreign Key to users) - Brand who reviewed
├── reviewed_at (timestamp)
├── approved_at (timestamp)
├── created_at (timestamp)
└── updated_at (timestamp)
```

### Chats Table

```sql
chats
├── id (Primary Key)
├── campaign_id (Foreign Key)
├── brand_id (Foreign Key to users)
├── creator_id (Foreign Key to users)
├── campaign_application_id (Foreign Key, unique)
├── status (enum: 'active', 'completed', 'cancelled')
├── last_message_at (timestamp)
├── created_at (timestamp)
└── updated_at (timestamp)
```

### Chat Messages Table

```sql
chat_messages
├── id (Primary Key)
├── chat_id (Foreign Key)
├── sender_id (Foreign Key to users)
├── message (text)
├── message_type (enum: 'text', 'image', 'file', 'system')
├── file_url (string) - For image/file messages
├── file_name (string) - Original filename
├── is_read (boolean)
├── read_at (timestamp)
├── created_at (timestamp)
└── updated_at (timestamp)
```

## API Endpoints

### Campaign Applications

#### List Applications

```
GET /api/applications
```

-   **Description**: List applications based on user role
-   **Authentication**: Required
-   **Role-based filtering**:
    -   Creators: See their own applications
    -   Brands: See applications for their campaigns
    -   Admins: See all applications
-   **Query Parameters**:
    -   `status`: Filter by status (pending, approved, rejected)
    -   `campaign_id`: Filter by campaign ID
-   **Response**: Paginated list of applications

#### Create Application

```
POST /api/campaigns/{campaign}/applications
```

-   **Description**: Create a new application for a campaign
-   **Authentication**: Required
-   **Role**: Creator only
-   **Request Body**:
    ```json
    {
      "proposal": "string (required, 10-2000 chars)",
      "portfolio_links": ["url1", "url2"] (optional),
      "estimated_delivery_days": 30 (optional, 1-365),
      "proposed_budget": 1000.00 (optional, 0-999999.99)
    }
    ```
-   **Response**: Created application with campaign and creator details

#### View Application

```
GET /api/applications/{application}
```

-   **Description**: View specific application details
-   **Authentication**: Required
-   **Access Control**:
    -   Creator: Can view their own applications
    -   Brand: Can view applications for their campaigns
-   **Response**: Application with campaign, creator, reviewer, and chat details

#### Approve Application

```
POST /api/applications/{application}/approve
```

-   **Description**: Approve an application (creates chat automatically)
-   **Authentication**: Required
-   **Role**: Brand only (campaign owner)
-   **Response**: Approved application with chat details

#### Reject Application

```
POST /api/applications/{application}/reject
```

-   **Description**: Reject an application
-   **Authentication**: Required
-   **Role**: Brand only (campaign owner)
-   **Request Body**:
    ```json
    {
        "rejection_reason": "string (optional, max 500 chars)"
    }
    ```
-   **Response**: Rejected application

#### Withdraw Application

```
DELETE /api/applications/{application}/withdraw
```

-   **Description**: Withdraw an application
-   **Authentication**: Required
-   **Role**: Creator only (application owner)
-   **Response**: Success message

#### Get Campaign Applications

```
GET /api/campaigns/{campaign}/applications
```

-   **Description**: Get all applications for a specific campaign
-   **Authentication**: Required
-   **Role**: Brand (campaign owner) or Admin
-   **Response**: Paginated list of applications

#### Application Statistics

```
GET /api/applications/statistics
```

-   **Description**: Get application statistics
-   **Authentication**: Required
-   **Role-based**: Shows stats for user's role
-   **Response**: Counts of total, pending, approved, rejected applications

### Chat System

#### List Chats

```
GET /api/chats
```

-   **Description**: List chats for the authenticated user
-   **Authentication**: Required
-   **Query Parameters**:
    -   `status`: Filter by status (active, completed, cancelled)
-   **Response**: Paginated list of chats

#### View Chat with Messages

```
GET /api/chats/{chat}
```

-   **Description**: View chat details with messages
-   **Authentication**: Required
-   **Access Control**: Only chat participants
-   **Response**: Chat details with paginated messages

#### Send Message

```
POST /api/chats/{chat}/messages
```

-   **Description**: Send a message in a chat
-   **Authentication**: Required
-   **Access Control**: Only chat participants
-   **Request Body**:
    ```json
    {
        "message": "string (required, max 2000 chars)",
        "message_type": "text|image|file (optional, default: text)",
        "file": "file upload (optional, max 10MB)"
    }
    ```
-   **Response**: Created message with sender details

#### Mark Messages as Read

```
PATCH /api/chats/{chat}/read
```

-   **Description**: Mark all messages in chat as read
-   **Authentication**: Required
-   **Access Control**: Only chat participants
-   **Response**: Success message

#### Complete Chat

```
PATCH /api/chats/{chat}/complete
```

-   **Description**: Mark chat as completed
-   **Authentication**: Required
-   **Access Control**: Only chat participants
-   **Response**: Success message

#### Cancel Chat

```
PATCH /api/chats/{chat}/cancel
```

-   **Description**: Mark chat as cancelled
-   **Authentication**: Required
-   **Access Control**: Only chat participants
-   **Response**: Success message

#### Get Chat by Application

```
GET /api/applications/{application}/chat
```

-   **Description**: Get chat for a specific application
-   **Authentication**: Required
-   **Access Control**: Application participants only
-   **Response**: Chat details or 404 if no chat exists

#### Chat Statistics

```
GET /api/chats/statistics
```

-   **Description**: Get chat statistics
-   **Authentication**: Required
-   **Response**: Counts of total, active, completed, cancelled, and unread chats

## Models and Relationships

### CampaignApplication Model

-   **Relationships**:
    -   `campaign()`: Belongs to Campaign
    -   `creator()`: Belongs to User (creator)
    -   `reviewer()`: Belongs to User (brand who reviewed)
    -   `chat()`: Has one Chat (if approved)

### Chat Model

-   **Relationships**:
    -   `campaign()`: Belongs to Campaign
    -   `brand()`: Belongs to User (brand)
    -   `creator()`: Belongs to User (creator)
    -   `campaignApplication()`: Belongs to CampaignApplication
    -   `messages()`: Has many ChatMessage

### ChatMessage Model

-   **Relationships**:
    -   `chat()`: Belongs to Chat
    -   `sender()`: Belongs to User (message sender)

## Business Rules

### Application Rules

1. Only creators can apply to campaigns
2. Campaigns must be approved and active to receive applications
3. One application per creator per campaign
4. Only campaign owners (brands) can approve/reject applications
5. Applications can only be withdrawn if they are pending

### Chat Rules

1. Chats are automatically created when applications are approved
2. Only chat participants can send messages
3. Only active chats can receive messages
4. File uploads are supported (images and documents)
5. Messages can be marked as read/unread

### Security Rules

1. Users can only access their own applications (creators)
2. Brands can only access applications for their campaigns
3. Only chat participants can access chat messages
4. File uploads are validated and stored securely

## Usage Examples

### Creator Applying to Campaign

```javascript
// Create application
const response = await fetch("/api/campaigns/1/applications", {
    method: "POST",
    headers: {
        Authorization: "Bearer " + token,
        "Content-Type": "application/json",
    },
    body: JSON.stringify({
        proposal: "I'm excited to work on this campaign...",
        portfolio_links: ["https://example.com/portfolio"],
        estimated_delivery_days: 14,
        proposed_budget: 500.0,
    }),
});
```

### Brand Approving Application

```javascript
// Approve application
const response = await fetch("/api/applications/1/approve", {
    method: "POST",
    headers: {
        Authorization: "Bearer " + token,
    },
});
```

### Sending Chat Message

```javascript
// Send message
const response = await fetch("/api/chats/1/messages", {
    method: "POST",
    headers: {
        Authorization: "Bearer " + token,
    },
    body: JSON.stringify({
        message: "Hello! I'm excited to work with you on this campaign.",
    }),
});
```

## Error Handling

The system includes comprehensive error handling for:

-   Unauthorized access attempts
-   Invalid application states
-   File upload validation
-   Database constraint violations
-   Missing resources

All API responses follow a consistent format:

```json
{
  "success": true/false,
  "message": "Human readable message",
  "data": {...},
  "errors": {...} // Only present on validation errors
}
```

## Future Enhancements

Potential improvements for the system:

1. Real-time messaging with WebSockets
2. Message notifications
3. File sharing improvements
4. Chat search functionality
5. Message reactions
6. Chat templates for common messages
7. Application templates for creators
8. Bulk application management for brands
