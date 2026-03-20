## ADDED Requirements

### Requirement: Approval Queue Navigation from Admin
The admin sidebar SHALL include a navigation link to the approval queue page (`/admin/approvals`) in the main navigation section (above the `Інструменти` tools section), with a badge showing the count of pending items.

#### Scenario: Admin sees approval queue in sidebar
- **WHEN** an authenticated admin opens any `/admin/*` page
- **THEN** the sidebar SHALL display a "Черга модерації" link pointing to `/admin/approvals`

#### Scenario: Pending count badge is visible
- **WHEN** there are pending items in the approval queue
- **THEN** the sidebar link SHALL display a numeric badge with the pending count

#### Scenario: Badge is hidden when queue is empty
- **WHEN** there are zero pending items in the approval queue
- **THEN** no badge is displayed next to the approval queue link
