## ADDED Requirements

### Requirement: Separate Wiki Admin Surface
The system SHALL provide a dedicated `wiki-admin` surface owned by `wiki-agent`, without using the shared `/admin` route.

#### Scenario: Admin logs into wiki-admin
- **WHEN** an operator opens `/wiki-admin/login`
- **THEN** the system shows a login form for `wiki-agent`

#### Scenario: Admin manages wiki pages
- **WHEN** an authenticated operator opens `/wiki-admin`
- **THEN** the system shows page list, create, edit, publish, unpublish, and delete actions

### Requirement: Visual Page Editor
The wiki admin SHALL provide a simple visual editor for page body content.

#### Scenario: Admin formats content visually
- **WHEN** the operator edits a wiki page body
- **THEN** the editor supports basic formatting actions such as bold text, lists, and headings
