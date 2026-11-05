# Skyline Realty CRM (Release 1)

A real estate CRM for a small agency: clients and wishlists, properties with photos and matching, a deals board,
activities, viewings and tasks, a "My Day" page, a website inquiries inbox and an AI assistant. A public listings site at `/`
feeds website inquiries straight into the CRM (contact, deal, task and note are created for the listing agent).

Stack: Laravel 13, Inertia 3, Vue 3, shadcn-vue, Fortify, Pest, PostgreSQL 17 + pgvector.

## Setup

1. **PostgreSQL 17 + pgvector** come from Homebrew:

    ```bash
    brew install postgresql@17 pgvector
    brew services start postgresql@17
    export PATH="/opt/homebrew/opt/postgresql@17/bin:$PATH"   # psql, createdb, ...
    createdb crm && createdb crm_testing
    ```

2. **App**:

    ```bash
    composer install
    npm i
    cp .env.example .env && php artisan key:generate   # skip if .env exists
    php artisan migrate:fresh --seed                    # schema + demo data
    php artisan storage:link
    ```

3. **`.env`** (defaults work for a local Postgres without a password):
   `DB_DATABASE=crm`, `QUEUE_CONNECTION=database`, `MAIL_MAILER=log`. For the AI assistant add both keys:
   `ANTHROPIC_API_KEY=` and `VOYAGE_API_KEY=`. AI is on only when **both** are set; otherwise nothing is sent out
   and the UI says "AI is not configured".

4. **Run** (three processes):

    ```bash
    php artisan queue:work     # indexing, mail
    npm run dev                # Vite
    # the web server: Herd, or `php artisan serve`
    ```

    Open `http://crm.test` (Herd).

### Notes for this Mac

- Herd serves `http://crm.test`, but XAMPP's Apache holds port 80 here. Stop XAMPP, or use
  `php artisan serve` and open `http://127.0.0.1:8000`.
- Postgres 17 and pgvector live under `/opt/homebrew/opt/postgresql@17/bin`.
- There is no git repository in this project.
- **PHP upload limits.** Herd's defaults (`upload_max_filesize=2M`, `post_max_size=8M`, `memory_limit=128M`) are
  too small for property photos (up to 12 photos of 5 MB each, re-encoded through GD). Set these instead:

    ```ini
    upload_max_filesize=8M
    post_max_size=100M
    memory_limit=512M
    ```

    In Herd: Settings → PHP → your PHP version → "Open php.ini" (or edit
    `~/Library/Application Support/Herd/config/php/<version>/php.ini`), save, then restart Herd (`herd restart`).
    With `php artisan serve`, edit the file `php --ini` reports as "Loaded Configuration File".

- **After adding both API keys run `php artisan knowledge:rebuild`.** Seeding (and any save while AI is off) skips
  indexing, so the assistant knows nothing until the index is built.

### Tests and build

```bash
php artisan test      # Pest on the crm_testing Postgres database
npm test              # Vitest
npm run build
```

Never run artisan commands with `--env=testing` against the dev database.

## Demo logins

Password for all: `password`.

| Email             | Role  | Name       |
| ----------------- | ----- | ---------- |
| `admin@demo.test` | Admin | Alex Admin |
| `anna@demo.test`  | Agent | Anna Reyes |
| `ben@demo.test`   | Agent | Ben Carter |

`php artisan migrate:fresh --seed` rebuilds a repeatable data set (seeded random): 60 properties (40 sale, 20 rent, 50
published), 120 clients (anna 50, ben 50, admin 20) with wishlists, 70 deals across every stage of both pipelines,
400 activities (80 completed viewings), 10 upcoming viewings (2 today for Anna), 100 tasks and 25 website inquiries
(10 new). Seeding sends nothing out.

## Features (Release 1)

- Clients with roles, status, tags, bulk actions and one or more wishlists
- Properties: gallery with drag-to-reorder photos, owner details, status, public/private publishing, hide exact address
- Matching: properties for a wishlist, interested clients for a property
- Sales and Rentals deals boards (drag between stages, lost reasons, commission estimate)
- Activities (call, email, meeting, note), viewings with feedback, tasks, and My Day
- Public listings site with filters, gallery and an inquiry form
- Inquiries inbox and a bell with notifications
- AI assistant answering from your own visible records, with citations
- Light blue theme, dark mode, responsive layout (sidebar becomes a drawer on phones)

**Deferred to later releases:** companies, dashboard charts, calendar, CSV import/export, audit trail, Cmd+K search,
"Summarize this record", settings pages, user invites, streaming replies, email notifications to agents and the 10k-record
performance target.

## Architecture

### Visibility

Enforced on the server, never only in the UI:

- Every owned model (contacts, deals, activities, tasks, inquiries) has a `visibleTo(User)` query scope (`HasOwner`).
  Agents see only what they own; admins see everything. All staff see all properties.
- Policies guard every show/update/delete route (a foreign id gives 403). Only the listing agent or an admin can edit a
  property or see its owner's details. Bulk actions are all-or-nothing.
- The same scope filters matching results, shared counts, the AI tools and the search chunks.
- The public site uses `PublicPropertyResource`: only available + published properties, no owner data, area only when
  the address is hidden.

### Assistant (RAG) flow

```
 record saved ──> IndexableObserver ──> IndexRecordJob (queue)
                                           │ RecordDocumenter -> Chunker -> Voyage embeddings (document)
                                           ▼
                                   knowledge_chunks (pgvector + full-text, with owner_id)
 question ──> AssistantRunner ──> Claude tool-use loop
                  │  tools: search_crm_notes, search_properties, matching_*, list_deals, get_record (as the signed-in user)
                  ▼
        KnowledgeSearch: embed query (Voyage, query) -> hybrid pgvector + Postgres full-text
                         WHERE owner is visible to the user  ──> top chunks ──> answer + citations
```

Failures (missing keys, API error, refusal, too many tool rounds) are saved as a friendly assistant message, never a 500.

## Adding real photos

The seeder generates a soft sky-gradient placeholder per property type. To use real photos instead, drop JPEGs into
`database/seeders/photos/` (`*.jpg`) and re-run `php artisan migrate:fresh --seed`. Each property gets 3-6 random ones,
copied to `storage/app/public/properties/{id}/` (needs `php artisan storage:link`).

## Screenshots

_Placeholder: add screenshots of the board, My Day, a property page and the public listings here._
