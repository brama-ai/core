## ADDED Requirements

### Requirement: Knowledge Tree Structure
The system SHALL expose a hierarchical knowledge tree built from the `tree_path` values of all indexed entries, representing the organization of knowledge by topic and category.

#### Scenario: Tree endpoint returns hierarchy
- **WHEN** a client sends GET `/api/v1/knowledge/tree`
- **THEN** the system returns a nested JSON structure grouping entries by `tree_path` segments

#### Scenario: Tree reflects indexed entries
- **WHEN** new entries are added or deleted in OpenSearch
- **THEN** the next call to `/api/v1/knowledge/tree` reflects the updated tree (no stale cache longer than 60 seconds)

#### Scenario: Empty index returns empty tree
- **WHEN** no entries exist in `knowledge_entries_v1`
- **THEN** the tree endpoint returns `{ "tree": [] }`

---

### Requirement: Tree Node Entry Count
Each tree node SHALL include the count of knowledge entries at that level and below.

#### Scenario: Node count displayed
- **WHEN** the tree is fetched
- **THEN** each node includes `count` representing the total entries within that subtree

#### Scenario: Leaf node has count 1 or more
- **WHEN** a leaf-level tree path has exactly one entry
- **THEN** that leaf node has `count: 1` and `children: []`

---

### Requirement: Tree Navigation to Entry List
The knowledge tree SHALL support drilling down to a filtered list of entries for any selected node.

#### Scenario: Navigate to subtree entries
- **WHEN** a client sends GET `/api/v1/knowledge/entries?tree_path=Technology/PHP`
- **THEN** the system returns all entries whose `tree_path` starts with `Technology/PHP`
