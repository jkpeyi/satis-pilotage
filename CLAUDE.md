# Satis Pilotage

Internal platform used by SATISWORLD's IT department to map and steer the refactoring of the Satis products (Satis Macro, Satis Pro, Satis Assur). It records which customer versions exist, which modules and features each version implements, in what state, with which deviations, business rules and issues.

The functional spec (cahier des charges MVP) and the architecture note are written in French and live outside the repo. The rules below are the parts of them that code must follow.

## Stack

- Laravel 13, PHP 8.3+, MySQL 8.4 (MySQL only: the schema relies on `CHECK` constraints and `FULLTEXT` indexes).
- Livewire 4 + Alpine.js + Tailwind CSS 4, built with Vite. UI components are our own Blade components in `resources/views/components/ui`.
- Rich screens: Alpine for the comparison matrix and the catalog tree, SortableJS for drag and drop, Monaco Editor for the code explorer and file diff, Chart.js for reports. Everything is bundled by Vite: never load anything from a CDN.
- Long texts (deviations, descriptions, rules, solutions) are Markdown, rendered with `league/commonmark` in safe mode.
- Queue and cache on the database driver. No Redis.
- Tests: Pest 4 on MySQL. Static analysis: Larastan. Formatting: Pint.
- Deployment: Docker Compose on a Satisworld server.

Not used, do not add: Filament, roles or permission packages, activity log packages, GitLab CI or GitHub Actions. Do not add any Composer or npm package without the user's approval.

## Commands

```bash
composer install && npm install
php artisan migrate
composer dev          # local server, queue, Vite
php artisan test      # Pest, needs the MySQL test database
vendor/bin/pint       # formatting
vendor/bin/phpstan analyse
```

## Naming

All code is in English: classes, methods, variables, tables, columns, enum values, routes, config keys, test names and comments. Only text shown to users is in French. Every enum exposes `label(): string` returning its French label.

| Business term (UI, French) | Code |
|---|---|
| Produit | `Product` |
| Client | `Customer` (avoids confusion with `GitLabClient` / HTTP client) |
| Version | `Version` |
| Version de référence | `Product::referenceVersion` |
| Dépôt | `Repository` |
| Environnement | `Environment` |
| Release (tag d'un dépôt) | `Release` |
| Déploiement | `Deployment` (an environment's dated deployment of a release or commit) |
| Cycle de vie | `VersionStatus` (`future` = perspective such as Satis_SaaS, never counted in production) |
| Pivot historique | `Version::is_historical_pivot` (point of comparison, never a target) |
| Responsable technique | `Version::technicalOwner` |
| Statut de vérification | `VerificationStatus` (`Verifiable` concern: records who decided and when) |
| Technologie, stack | `Technology`, `stack`. Cycles used by repositories, components and environments register themselves (`RegisterTechnologies`, without end-of-support date); `technologies:sync` brings in older entries |
| Module, sous-module | `Module` (a sub-module is a `Module` with a `parent_id`) |
| Fonctionnalité | `Feature` (`module_id` is its main module; `otherModules()` through `module_feature`, RG-16) |
| Élément (generic) | `CatalogItem` interface, implemented by `Module` and `Feature` (not a table) |
| Rattachement | `Implementation` |
| État | `ImplementationStatus`: one value per valid (presence, operation) pair of the README d'intégration |
| Présence, fonctionnement | `FeaturePresence`, `FeatureOperation`, derived with `presence()` / `operation()`, combined with `fromAxes()` |
| Historique d'état | `ImplementationStatusChange` |
| Écart | `deviation` |
| Description standard | `standard_description` |
| Règle métier | `BusinessRule` (displayed `RM-0042`) |
| Composant, intégration | `Component` (displayed `CMP-0001`, a technical part of a version), `Integration` (`INT-0001`, from a component to another component or to an `external_system`: exactly one target, CHECK) |
| Workflow, étape | `Workflow` (displayed `WF-0003`, belongs to a product), `WorkflowStep` (ordered, realized by a catalog `Feature`, linked to many `BusinessRule`s; `kind` action, decision or end, RG-17) |
| Sortie (si / sinon) | `WorkflowTransition` (from a step to a step of the same workflow, with its condition `label`); `WorkflowGraph` builds the graph, checks and diagram layout |
| Problème | `Issue` (displayed `PB-0042`) |
| Extrait de code, ancrage | `CodeSnippet` (`repository_id`, `commit_sha`, `path`, `line_start`, `line_end`) |
| Cartographie des écarts | `ComparisonMatrix` |
| Explorateur de code | `CodeExplorer` |

## Architecture rules

- All business logic lives in `app/Actions`, one class per use case with a `handle()` method (e.g. `ChangeImplementationStatus`, `AttachCatalogItems`, `ConvertFeatureToSubmodule`). Livewire components only display data and call Actions.
- Nothing is hard-deleted from the UI: business records are archived through `archived_at`.
- Every business table has `created_by` and `updated_by`, filled automatically from the authenticated user.
- No polymorphic relations. When a row can point to one of two tables, use two nullable foreign keys and a `CHECK` constraint ensuring exactly one is set (see `implementations`, `code_snippets`). Columns used in a `CHECK` must not have `ON DELETE` actions (MySQL restriction).
- No roles: every authenticated user can do everything.
- A column with a database default must declare the same default in the model's `$attributes`, so that a model is complete right after `create()`.
- Enums implement `App\Contracts\HasLabel`; build select options with the `App\Livewire\Concerns\EnumOptions` trait.
- Authentication phase 1: local accounts (email + password), created with `php artisan user:create`, no public registration. Phase 2: GitLab OAuth through Socialite; users are matched by `gitlab_id`, then by email.
- GitLab integration is behind `GITLAB_ENABLED` (false for now). While disabled, repositories and stacks are entered manually and the code explorer is hidden. When enabled, files are always fetched by commit SHA and cached forever.

## Business rules

Each rule must be covered by at least one Pest test whose name starts with its code, e.g. `it('RG-03: rejects an implementation with both a module and a feature')`.

- **RG-01** A module may have a parent module (it is then shown as a sub-module); depth is unlimited and a module cannot be its own ancestor. A feature always belongs to a module.
- **RG-02** The hierarchy belongs to the catalog and is the same for every version.
- **RG-03** An implementation targets exactly one module or one feature, and is unique per (version, module) or (version, feature).
- **RG-04** Attaching an item also attaches its ancestors; descendants are offered by default. New implementations start as `unknown` (presence unknown, not tested).
- **RG-05** No implementation means "out of scope for this customer". `absent` means "expected but absent". Only `absent` counts as a gap in reports (`ImplementationStatus::isGap()`).
- **RG-06** Every status change writes one `ImplementationStatusChange` row. That history is append-only.
- **RG-07** A deviation is described relative to the catalog `standard_description`, never relative to another version.
- **RG-08** A product's reference version is the default pivot of the comparison matrix. It may be a baseline version with no customer.
- **RG-09** An issue concerns at least one implementation, possibly in several versions.
- **RG-10** A code anchor coming from GitLab always stores the commit SHA and a copy of the snippet.
- **RG-11** A business rule copied to another version keeps a link to its origin rule (`origin_rule_id`).
- **RG-12** Records are archived, not deleted. Detaching an item that has rules or issues requires confirmation.
- **RG-13** `RM-` and `PB-` codes are derived from the auto-increment id and never reused.
- **RG-14** Converting a feature to a sub-module (or the reverse) keeps the existing implementations with their rules, issues and history.
- **RG-15** A name is unique across the whole catalog, modules, sub-modules and features together, among active items; case, accents and punctuation are ignored (`CatalogName::key`, `EnsureUniqueCatalogName`). Every name entry suggests existing items so that they are reused (moved here, attached) rather than duplicated. Duplicates are merged with `MergeCatalogItems` (everything moves to the kept item, the duplicate is deleted and logged in `catalog_merges`), from the « Doublons » screen or `php artisan catalog:merge-duplicates`.
- **RG-16** A feature exists once in the catalog but may appear under several modules: `features.module_id` is its main module (its path in reports, the branch attached with it, RG-04), `module_feature` lists the other modules. It keeps one implementation per version wherever it is shown. Use `LinkFeatureToModule` rather than creating a copy; a sub-module and a feature describing the same thing are combined with `ReuniteSubmoduleWithFeature`. `CatalogTree::featureIds()` returns a module's own features only (each feature counted once in the matrix and reports); `sharedFeatureIds()` the features shared into it.
- **RG-17** A workflow step is an action, a decision or an end. A decision has labelled exits (« Oui », « Non », possibly more, possibly back to an earlier step); an action has at most one exit and otherwise goes on to the next step in the order; an end has none. Exits lead to active steps of the same workflow. Incomplete decisions, unreachable steps and exits to archived steps are reported, not blocked. The diagram is drawn by the application (SVG on the page, PNG with GD and the bundled DejaVu font in L2): no diagram library.

## Reports (SPEC-001 deliverables)

- Deliverables L1–L8 are declared in `config/reports.php`. Templates are the DTI Word files in `resources/report-templates` (`{{…}}` variables); bump `template_version` when a template changes.
- One builder per deliverable in `app/Reports/Builders`, implementing `ReportBuilder::build(ReportScope): ReportContent`: a `TextSection` or `TableSection` for every template variable. Builders only read data; `GenerateReport` freezes the snapshot, renders the DOCX and the XLSX annex and records the `ReportGeneration`.
- Never fabricate data: a value the application does not hold stays empty and is shown "À confirmer". No secrets, banking identifiers or personal data in exports.
- A `ReportGeneration` is immutable once its files are written; a new export is a new row.
- The XLSX annex is written by `XlsxStreamWriter` (streamed, constant memory), not by Laravel Excel: a report can hold tens of thousands of rows (ENF-06).

## UI conventions

- Full-page Livewire components are classes in `app/Livewire/<Area>/` (e.g. `Products\Index`, `Products\Form`, `Products\Show`), registered with `Route::livewire()`. Layout: `resources/views/layouts/app.blade.php` (`layouts::app`).
- Form state and validation live in Livewire Form objects (`app/Livewire/Forms`); their `data()` method validates and returns the typed array passed to the Action.
- Use the Blade components in `components/ui` (`x-ui.input`, `x-ui.select`, `x-ui.modal`, `x-ui.table`, `x-ui.badge`, `x-ui.markdown`, `x-ui.audit`…) rather than raw markup.
- After a redirect, use `session()->flash('status', …)`. For an action that stays on the page, use `$this->dispatch('notify', message: …)`.
- Render user Markdown only through `x-ui.markdown` (safe mode), and code through `x-ui.code` (escaped, line numbers).
- The comparison matrix is rendered in the browser (`resources/js/comparison-matrix.js`) from a compact JSON payload, with row virtualization: only rows in view are in the DOM. Keep it that way; a Blade table does not scale to thousands of rows × 20 versions (ENF-06).
- Never name a Blade variable `$component` (reserved by Laravel's component system), and never give a Livewire Form a `$component` property (Livewire uses it).

## Working method

- Deliver in small increments, one requirement at a time, and keep the tests green.
- Run Pint, Larastan and the tests before declaring work done.
