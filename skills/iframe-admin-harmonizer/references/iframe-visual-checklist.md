# Iframe Visual Checklist

## Layout

- `body` has embed flag (`data-embedded=1`) when rendered in iframe
- No clipped top area from fixed headers
- Main content has consistent horizontal padding
- No hardcoded bright page background

## Components

- Cards share one surface + border style
- Form controls are readable on dark background
- Tables do not render white rows by default
- Empty states and alerts have sufficient contrast
- Modals inherit same theme tokens

## Navigation

- Internal top nav is compact and non-intrusive in iframe mode
- Links remain usable without opening unnecessary new windows
- Standalone mode still has clear navigation

## Integration

- Core iframe URL can pass `embedded=1`
- Agent UI also detects iframe runtime as fallback
- Same template supports both embedded and standalone usage

