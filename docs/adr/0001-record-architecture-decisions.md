# ADR-0001: Record architecture decisions

- Status: accepted
- Date: 2026-11-12

## Context
The plugin is developed alongside a real application and its sibling packages (`mtubis/html-translator`,
`mtubis/html-translator-laravel`), and decisions are made across several sessions. They need to stay findable.

## Decision
Significant decisions are recorded as short ADRs in `docs/adr/` (context, decision, consequences). Accepted ADRs are
not edited. A new ADR supersedes an old one, and the old one gets a "superseded by" status line.
`docs/ARCHITECTURE.md` describes the current design and links the ADRs.

## Consequences
Changing an accepted decision costs a new ADR, which is intended. The public API in `docs/ARCHITECTURE.md` only
changes together with an ADR.
