# Dashboard API Integration

This document describes the implementation of the admin dashboard API integration that connects the frontend dashboard to the backend database.

## Overview

The dashboard now fetches real-time data from the backend API instead of using mock data. This includes:

1. **Dashboard Metrics** - Real-time statistics (excludes admin users from counts)
2. **Pending Campaigns** - Live campaign data with approve/reject functionality
3. **Recent Users** - Dynamic user information (excludes admin users)

## Backend Implementation

### New API Endpoints

#### 1. Dashboard Metrics

-   **Endpoint**: `GET /api/admin/dashboard-metrics`
-   **Description**: Returns dashboard statistics
-   **Response**:

```json
{
    "success": true,
    "data": {
        "pendingCampaignsCount": 12,
        "allActiveCampaignCount": 1234,
        "allRejectCampaignCount": 7,
        "allUserCount": 48
    }
}
```

#### 2. Pending Campaigns

-   **Endpoint**: `GET /api/admin/pending-campaigns`
-   **Description**: Returns recent pending campaigns
-   **Response**:

```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "title": "Campanha de Verão 1",
            "brand": "Marca 1",
            "type": "Vídeo",
            "value": 1000
        }
    ]
}
```

#### 3. Recent Users

-   **Endpoint**: `GET /api/admin/recent-users`
-   **Description**: Returns recent user registrations
-   **Response**:

```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "Usuário 1",
            "role": "Marca",
            "registeredDaysAgo": 1,
            "tag": "Pagante"
        }
    ]
}
```

### Campaign Actions

The existing campaign approve/reject endpoints are used:

-   **Approve**: `PATCH /api/campaigns/{id}/approve`
-   **Reject**: `PATCH /api/campaigns/{id}/reject`

### AdminController Methods

1. `getDashboardMetrics()` - Returns dashboard statistics (excludes admin users from user count)
2. `getPendingCampaigns()` - Returns pending campaigns for dashboard
3. `getRecentUsers()` - Returns recent users for dashboard (excludes admin users)

### User Filtering

-   **Admin Users Excluded**: All dashboard endpoints exclude admin users from results
-   **User Count**: Only counts creators, brands, and students (not admins)
-   **Recent Users**: Only shows non-admin users in the recent users list
-   **Role Mapping**: Properly maps database roles to display names (Marca, Criador)

## Frontend Implementation

### API Integration

#### New Interfaces

-   `DashboardMetrics` - Dashboard statistics structure
-   `PendingCampaign` - Campaign data structure
-   `RecentUser` - User data structure
-   `CampaignActionResponse` - Action response structure

#### New API Methods

-   `adminApi.getDashboardMetrics()` - Fetch dashboard metrics
-   `adminApi.getPendingCampaigns()` - Fetch pending campaigns
-   `adminApi.getRecentUsers()` - Fetch recent users
-   `adminApi.approveCampaign()` - Approve campaign
-   `adminApi.rejectCampaign()` - Reject campaign

### Dashboard Component Updates

#### State Management

-   `metrics` - Dashboard statistics
-   `pendingCampaigns` - Pending campaigns list
-   `recentUsers` - Recent users list
-   `loading` - Loading state for initial data fetch
-   `loadingCampaigns` - Loading state for campaign actions

#### Key Features

1. **Real-time Data**: All data is fetched from the backend API
2. **Loading States**: Shows loading spinners during API calls
3. **Error Handling**: Displays toast notifications for errors
4. **Campaign Actions**: Approve/reject buttons with loading states
5. **Auto-refresh**: Refreshes data after campaign actions
6. **Responsive Design**: Works on mobile and desktop

#### Data Flow

1. Component mounts → Fetches all dashboard data in parallel
2. User clicks approve/reject → Shows loading state
3. API call completes → Shows success/error toast
4. Data refreshes → Updates UI with new data

## Testing

### Backend Tests

-   `test_admin_can_get_dashboard_metrics()` - Tests metrics endpoint
-   `test_admin_can_get_pending_campaigns()` - Tests campaigns endpoint
-   `test_admin_can_get_recent_users()` - Tests users endpoint

### Campaign Factory

Created `CampaignFactory` for testing campaign-related functionality.

## Security

-   All endpoints are protected by `auth:sanctum` middleware
-   Admin endpoints require `admin` middleware
-   Only authenticated admin users can access dashboard data

## Error Handling

### Backend

-   Try-catch blocks in all controller methods
-   Proper HTTP status codes (200, 500)
-   Descriptive error messages

### Frontend

-   Toast notifications for success/error states
-   Console logging for debugging
-   Graceful fallbacks for missing data

## Performance

-   Parallel API calls for initial data fetch
-   Limited data sets (10 items max for lists)
-   Efficient database queries with proper relationships

## Future Enhancements

1. **Real-time Updates**: WebSocket integration for live updates
2. **Caching**: Redis caching for frequently accessed data
3. **Pagination**: Add pagination for large datasets
4. **Filtering**: Add date range and status filters
5. **Export**: Add data export functionality
6. **Analytics**: Add more detailed analytics and charts

## Usage

The dashboard automatically loads when an admin user visits the admin panel. All data is fetched from the backend and displayed in real-time. Campaign actions (approve/reject) are immediately reflected in the UI and database.
