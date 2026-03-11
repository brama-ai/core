## ADDED Requirements

### Requirement: Extended Curated Item Status Model
The system SHALL support additional statuses for curated news items: `duplicate`, `moderated`, and `deleted`.

#### Scenario: Item marked as duplicate
- **WHEN** the deduplication service identifies a curated item as a duplicate
- **THEN** the item status is set to `duplicate` and the item is excluded from digest generation

#### Scenario: Item moderated by admin
- **WHEN** an admin sets a curated item's status to `moderated`
- **THEN** the item is eligible for digest generation if `moderated` is in the configured digest source statuses

#### Scenario: Item soft-deleted
- **WHEN** an admin sets a curated item's status to `deleted`
- **THEN** the item is excluded from all feeds, digest generation, and public views but remains in the database

### Requirement: Admin Curated News List
The system SHALL provide an admin page listing all curated news items with their current status.

#### Scenario: Admin views curated items
- **WHEN** an admin navigates to the news management page
- **THEN** the page displays curated items ordered by creation date (newest first) with status badges

#### Scenario: Admin filters by status
- **WHEN** an admin selects a status filter
- **THEN** only items matching the selected status are shown

### Requirement: Admin Status Transitions
The system SHALL allow admins to change the status of curated news items through the admin UI.

#### Scenario: Admin moderates an item
- **WHEN** an admin changes a `ready` item's status to `moderated`
- **THEN** the status is updated and the item becomes eligible for moderated-only digest runs

#### Scenario: Admin soft-deletes an item
- **WHEN** an admin changes any non-published item's status to `deleted`
- **THEN** the item is soft-deleted and hidden from public views

#### Scenario: Admin rejects an item
- **WHEN** an admin changes a curated item's status to `rejected`
- **THEN** the item is excluded from digest generation

#### Scenario: Published items cannot change status
- **WHEN** an admin attempts to change the status of a `published` item
- **THEN** the system rejects the transition (published is terminal except for soft-delete)
