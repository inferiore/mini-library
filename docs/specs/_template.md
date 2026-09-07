# <NNN> — <Feature Name>

Status: draft
Area: <backend | frontend | backend+frontend | infra>
Depends on: <list of spec files this one assumes are already implemented, or "none">

## Objective

One or two sentences: what this spec delivers and why it matters to the product.

## User Story

As a <role>, I want <capability>, so that <outcome>.

## Functional Requirements

Numbered list of concrete, testable behaviors the system must have.

## Non-Functional Requirements

Performance, security, reliability, or observability constraints that aren't a single
user-facing behavior (e.g. "checkout must be safe under concurrent requests for the
same book").

## User Flow

Step-by-step walkthrough of the primary path through this feature, as the user
experiences it.

## Database Changes

New tables/columns, constraints, indexes, and migrations required. Name them
concretely (table/column names) — this is what `senior-laravel-engineer` implements
against.

## API/Application Changes

Routes, controllers, Services, Jobs, Policies, Form Requests introduced or modified.

## UI Changes

Pages/components introduced or modified, and what the user sees/can do differently.

## Authorization

Which roles (`ADMIN`/`LIBRARIAN`/`MEMBER`) can do what. Call out anything that isn't
role-gated but arguably should be, so it's a decision rather than an oversight.

## Validation Rules

Field-level and cross-field validation the Form Requests must enforce.

## Edge Cases

Concrete scenarios that aren't the happy path, and the expected behavior for each.

## Acceptance Criteria

Bullet list of "done" conditions, phrased so `qa-validator` can check each one
independently against the implementation.

## Test Cases

Concrete test scenarios (not full test code) that `senior-laravel-engineer` must cover
and `qa-validator` will check for.

## Definition of Done

- Migrations run cleanly on both the fast local SQLite suite and (where Postgres-only
  behavior is involved) `phpunit.ci.xml`.
- All Acceptance Criteria met.
- All Test Cases covered by passing automated tests.
- `composer test` passes (pint, phpstan, PHPUnit).
- No hardcoded secrets introduced.
- `qa-validator` has returned `STATUS: PASSED`.
