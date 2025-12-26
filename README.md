# Log Manager

Log Manager is a WordPress plugin designed to monitor, record, and display activity logs across a WordPress website. It enables administrators to track actions performed by both authenticated and anonymous users, providing visibility into user behavior, content changes, and authentication events.

The plugin is intended to help site owners, administrators, and developers audit activity, improve security monitoring, and maintain a reliable history of system events.

---

## Overview

Log Manager captures and stores detailed logs related to user actions and system events. These logs can be filtered, searched, exported, and managed through an intuitive administrative interface.

The plugin supports logging across default post types, custom post types, pages, and authentication workflows.

---

## Use Cases

- Monitoring user activity on posts, pages, and custom post types
- Tracking login, logout, and failed login attempts
- Auditing content changes made by specific users
- Reviewing site activity for security and compliance purposes
- Maintaining a historical record of administrative and user actions

---

## Features

### Activity Tracking

#### Post Types and Pages
- Detects actions such as:
  - Publish
  - Update
  - Restore
  - Trash
  - Delete
- Supports all default and custom post types

#### Authentication Activity
- Successful login attempts
- Failed login attempts
- User logout activity
- Logs both logged-in and anonymous user requests where applicable

---

### Log Management

#### Pagination and Record Count
- Logs are displayed using pagination for improved performance
- Total number of log records is shown for easy reference

#### Severity Levels
- Logs are categorized by severity level:
  - Low
  - Medium
  - High
- Logs can be filtered and sorted based on severity

---

### Filtering and Search

- Filter logs by:
  - Custom date range
  - Specific user roles
  - Individual users
- Combine multiple filters (e.g., user, role, and date range)
- Search logs using keyword-based queries

---

### Log Export

- Export log records in the following formats:
  - CSV
  - PDF
- Useful for audits, reports, and external analysis

---

### Housekeeping and Data Retention

- Supports scheduled cleanup using WordPress CRON
- Automatically removes outdated or unnecessary log data
- Helps maintain optimal database performance

---

## Installation

### Manual Installation

1. Download the plugin folder named `log-manager`
2. Upload the folder to the following directory `/wp-content/plugins`
3. Activate the plugin.

---

## Requirements

- WordPress version 6.0 or higher
- PHP version 7.2 or higher
- MySQL 5.6+

