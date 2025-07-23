# Admin Notification System

## Overview

The Admin Notification System provides real-time notifications to administrators about all major events happening in the Nexa platform. This ensures that admins are always aware of user activities, system events, and important business operations.

## Features

-   **Real-time notifications** via Socket.IO
-   **Comprehensive event coverage** for all major system activities
-   **Detailed notification data** with relevant context
-   **Automatic notification delivery** to all admin users
-   **Persistent storage** in database for notification history

## Notification Types

### 1. User Management Notifications

#### New User Registration

-   **Trigger**: When a new user registers (via email or Google OAuth)
-   **Notification Type**: `new_user_registration`
-   **Message**: "New {role} registered: {user_name}"
-   **Data**: User ID, name, email, role, registration time

#### User Login Detection

-   **Trigger**: When any user logs in (except admins)
-   **Notification Type**: `login_detected`
-   **Message**: "New login detected on your account"
-   **Data**: User ID, name, email, role, IP address, user agent, login time

### 2. Campaign Management Notifications

#### New Campaign Creation

-   **Trigger**: When a brand creates a new campaign
-   **Notification Type**: `new_campaign`
-   **Message**: "{brand_name} posted new campaign: {campaign_title}"
-   **Data**: Campaign ID, title, brand info, budget, category, type

#### Campaign Approval

-   **Trigger**: When an admin approves a campaign
-   **Notification Type**: `campaign_approved`
-   **Message**: "Campaign approved: {campaign_title}"
-   **Data**: Campaign ID, title, brand name, approved by

#### Campaign Rejection

-   **Trigger**: When an admin rejects a campaign
-   **Notification Type**: `campaign_rejected`
-   **Message**: "Campaign rejected: {campaign_title}"
-   **Data**: Campaign ID, title, brand name, rejected by, reason

#### Campaign Status Changes

-   **Trigger**: When campaign status is toggled or archived
-   **Notification Type**: `campaign_status_toggled`, `campaign_archived`
-   **Message**: "Campaign {action}: {campaign_title}"
-   **Data**: Campaign ID, title, brand name, action, user

### 3. Application System Notifications

#### New Campaign Application

-   **Trigger**: When a creator applies to a campaign
-   **Notification Type**: `new_application`
-   **Message**: "{creator_name} applied to {brand_name}'s campaign: {campaign_title}"
-   **Data**: Application ID, campaign info, creator info, proposal amount

#### Application Approval

-   **Trigger**: When a brand approves an application
-   **Notification Type**: `application_approved`
-   **Message**: "Application approved for {campaign_title}"
-   **Data**: Application ID, campaign info, creator info, approved by

#### Application Rejection

-   **Trigger**: When a brand rejects an application
-   **Notification Type**: `application_rejected`
-   **Message**: "Application rejected for {campaign_title}"
-   **Data**: Application ID, campaign info, creator info, rejected by, reason

#### Application Withdrawal

-   **Trigger**: When a creator withdraws an application
-   **Notification Type**: `application_withdrawn`
-   **Message**: "Application withdrawn for {campaign_title}"
-   **Data**: Application ID, campaign info, creator info, withdrawn by

### 4. Bidding System Notifications

#### New Bid Submission

-   **Trigger**: When a creator submits a bid
-   **Notification Type**: `new_bid`
-   **Message**: "{creator_name} submitted a bid of R$ {amount} for {brand_name}'s campaign: {campaign_title}"
-   **Data**: Bid ID, campaign info, creator info, bid amount

#### Bid Acceptance

-   **Trigger**: When a brand accepts a bid
-   **Notification Type**: `bid_accepted`
-   **Message**: "Bid accepted for {campaign_title}"
-   **Data**: Bid ID, campaign info, creator info, accepted by

#### Bid Rejection

-   **Trigger**: When a brand rejects a bid
-   **Notification Type**: `bid_rejected`
-   **Message**: "Bid rejected for {campaign_title}"
-   **Data**: Bid ID, campaign info, creator info, rejected by, reason

#### Bid Withdrawal

-   **Trigger**: When a creator withdraws a bid
-   **Notification Type**: `bid_withdrawn`
-   **Message**: "Bid withdrawn for {campaign_title}"
-   **Data**: Bid ID, campaign info, creator info, withdrawn by

### 5. Payment System Notifications

#### Payment Method Creation

-   **Trigger**: When a user adds a payment method
-   **Notification Type**: `payment_method_created`
-   **Message**: "Payment activity: {user_name} - payment_method_created"
-   **Data**: User info, card ID, brand, last 4 digits

#### Payment Processing

-   **Trigger**: When a payment is processed
-   **Notification Type**: `payment_processed`
-   **Message**: "Payment activity: {user_name} - payment_processed (R$ {amount})"
-   **Data**: User info, payment ID, amount, status, campaign ID

#### Payment Method Deletion

-   **Trigger**: When a user deletes a payment method
-   **Notification Type**: `payment_method_deleted`
-   **Message**: "Payment activity: {user_name} - payment_method_deleted"
-   **Data**: User info, card ID

### 6. Portfolio Management Notifications

#### Portfolio Profile Update

-   **Trigger**: When a creator updates their portfolio profile
-   **Notification Type**: `portfolio_update`
-   **Message**: "{user_name} updated their portfolio: profile_update"
-   **Data**: User info, portfolio ID, title, bio, profile picture status

#### Portfolio Media Upload

-   **Trigger**: When a creator uploads media to their portfolio
-   **Notification Type**: `portfolio_update`
-   **Message**: "{user_name} updated their portfolio: media_upload"
-   **Data**: User info, portfolio ID, uploaded count, total items

### 7. System Activity Notifications

#### General System Activities

-   **Trigger**: Various system events
-   **Notification Type**: `system_activity`
-   **Message**: "System activity detected: {activity_type}"
-   **Data**: Activity type, timestamp, relevant data

## Implementation Details

### NotificationService Class

The `NotificationService` class provides static methods for sending admin notifications:

```php
// Send notification to admin about new user registration
NotificationService::notifyAdminOfNewRegistration($user);

// Send notification to admin about new campaign
NotificationService::notifyAdminOfNewCampaign($campaign);

// Send notification to admin about new application
NotificationService::notifyAdminOfNewApplication($application);

// Send notification to admin about new bid
NotificationService::notifyAdminOfNewBid($bid);

// Send notification to admin about payment activity
NotificationService::notifyAdminOfPaymentActivity($user, $paymentType, $paymentData);

// Send notification to admin about portfolio update
NotificationService::notifyAdminOfPortfolioUpdate($user, $updateType, $updateData);

// Send notification to admin about system activity
NotificationService::notifyAdminOfSystemActivity($activityType, $activityData);
```

### Notification Model

The `Notification` model provides static methods for creating different types of notifications:

```php
// Create new user registration notification
Notification::createNewUserRegistration($userId, $registrationData);

// Create new campaign notification
Notification::createNewCampaign($userId, $campaignData);

// Create new application notification
Notification::createNewApplication($userId, $applicationData);

// Create new bid notification
Notification::createNewBid($userId, $bidData);

// Create payment activity notification
Notification::createPaymentActivity($userId, $paymentData);

// Create portfolio update notification
Notification::createPortfolioUpdate($userId, $portfolioData);

// Create system activity notification
Notification::createSystemActivity($userId, $activityData);
```

### Real-time Delivery

All admin notifications are delivered in real-time via Socket.IO:

1. **Notification Creation**: Notification is created in the database
2. **Socket Emission**: Notification is sent to all admin users via Socket.IO
3. **Frontend Reception**: Admin dashboard receives and displays the notification
4. **Persistent Storage**: Notification is stored for later viewing

### Database Schema

```sql
notifications
├── id (Primary Key)
├── user_id (Foreign Key to users - admin recipient)
├── type (Notification type)
├── title (Notification title)
├── message (Notification message)
├── data (JSON data with additional context)
├── is_read (Boolean - read status)
├── read_at (Timestamp - when read)
├── created_at (Timestamp)
└── updated_at (Timestamp)
```

## Integration Points

### Controllers with Admin Notifications

1. **RegisteredUserController**: New user registration
2. **GoogleController**: Google OAuth registration
3. **CampaignController**: Campaign creation, approval, rejection, archiving
4. **CampaignApplicationController**: Application creation, approval, rejection, withdrawal
5. **BidController**: Bid creation, acceptance, rejection, withdrawal
6. **PaymentController**: Payment method creation, payment processing, deletion
7. **PortfolioController**: Portfolio profile updates, media uploads

### Automatic Triggers

The system automatically sends admin notifications for:

-   **User Registration**: Both email and Google OAuth registration
-   **Campaign Creation**: When brands create new campaigns
-   **Application Submission**: When creators apply to campaigns
-   **Bid Submission**: When creators submit bids
-   **Payment Activities**: When users manage payment methods or process payments
-   **Portfolio Updates**: When creators update their portfolios
-   **System Events**: Campaign approvals, bid acceptances, etc.

## Testing

Use the test script to verify all admin notifications are working:

```bash
php test-admin-notifications.php
```

This script will:

1. Create test users (admin, brand, creator)
2. Simulate all major system events
3. Verify notifications are created and delivered
4. Display all admin notifications with their data
5. Clean up test data

## Frontend Integration

The admin dashboard receives notifications via:

1. **Socket.IO Connection**: Real-time notification delivery
2. **Notification Bell**: Shows unread notification count
3. **Notification Panel**: Displays all notifications with details
4. **Mark as Read**: Allows admins to mark notifications as read

## Benefits

1. **Complete Visibility**: Admins see all platform activities in real-time
2. **Proactive Monitoring**: Early detection of issues or unusual activities
3. **Business Intelligence**: Insights into user behavior and platform usage
4. **Security**: Monitoring of login attempts and user activities
5. **Support**: Quick response to user issues and system events

## Configuration

The admin notification system requires:

1. **Admin Users**: Users with `role = 'admin'` in the database
2. **Socket.IO Server**: Running for real-time delivery
3. **Database**: Notifications table for persistent storage
4. **Frontend**: Admin dashboard to display notifications

## Future Enhancements

1. **Notification Filtering**: Allow admins to filter notifications by type
2. **Notification Preferences**: Let admins choose which notifications to receive
3. **Email Notifications**: Send important notifications via email
4. **Notification Analytics**: Track notification engagement and effectiveness
5. **Custom Notification Rules**: Allow admins to set custom notification triggers
