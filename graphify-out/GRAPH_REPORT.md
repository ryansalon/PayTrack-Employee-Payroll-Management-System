# Graph Report - PayTrack-Employee-Payroll-Management-System  (2026-11-25)

## Corpus Check
- Corpus is ~43,936 words - fits in a single context window. You may not need a graph.

## Summary
- 455 nodes · 603 edges · 66 communities (11 shown, 55 thin omitted)
- Extraction: 90% EXTRACTED · 10% INFERRED · 0% AMBIGUOUS · INFERRED: 61 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- Payroll & Employee Controllers
- Auth, Session & Dashboard
- Composer Package Configuration
- App Config, Seeders & Tests
- HTTP, Security & Mail Config
- Suggested PHP Extensions
- Project Docs & Directory Guards
- HTTP Request Filters Config
- Application Bootstrap & CLI Entry
- Cache Handlers Configuration
- Debug Toolbar Collectors Config
- Database Schema Migrations
- Payroll Index & Sheet View
- Exception Handling Configuration
- Validation Rules Configuration
- Deduction Management View
- HTML Error Debug Script
- Kint Debugger Configuration
- Employee Edit View
- Database Connection Config
- Response Formatters Config
- Image Processing Handlers
- Logging Handlers Configuration
- MIME Type Mapping Config
- Employee Create View
- Mahinog LGU Official Seal
- Autoload Configuration
- Cookie Policy Configuration
- Foreign Character Transliteration
- Module Discovery Configuration
- Asset Publisher Configuration
- Router Options Configuration
- Service Container Overrides
- Login Page View
- HTML DocTypes Config
- Framework Feature Flags
- Runtime Optimization Config

## God Nodes (most connected - your core abstractions)
1. `EmployeeModel` - 25 edges
2. `OfficeModel` - 23 edges
3. `suggest` - 20 edges
4. `PayrollModel` - 16 edges
5. `Employee` - 14 edges
6. `DeductionModel` - 13 edges
7. `BaseController` - 12 edges
8. `require-dev` - 9 edges
9. `Session` - 8 edges
10. `Payroll` - 8 edges

## Surprising Connections (you probably didn't know these)
- `403 Directory Access Guard (app/index.html)` --conceptually_related_to--> `Public Folder Entry Point (public/index.php)`  [INFERRED]
  app/index.html → README.md
- `Robots.txt Crawler Policy` --conceptually_related_to--> `Public Folder Entry Point (public/index.php)`  [INFERRED]
  public/robots.txt → README.md
- `403 Directory Access Guard (tests/index.html)` --conceptually_related_to--> `Public Folder Entry Point (public/index.php)`  [INFERRED]
  tests/index.html → README.md
- `403 Directory Access Guard (writable/index.html)` --conceptually_related_to--> `Public Folder Entry Point (public/index.php)`  [INFERRED]
  writable/index.html → README.md
- `ConfigReader` --inherits--> `App`  [EXTRACTED]
  tests/_support/Libraries/ConfigReader.php → app/Config/App.php

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **CodeIgniter 4 Directory Access Protection Pattern** — readme_public_folder_entry_point, app_index_directory_guard, tests_index_directory_guard, writable_index_directory_guard, writable_cache_index_directory_guard, writable_debugbar_index_directory_guard, writable_logs_index_directory_guard, writable_session_index_directory_guard, writable_uploads_index_directory_guard [INFERRED 0.95]
- **CodeIgniter 4 PHPUnit Testing and Coverage Workflow** — tests_readme_phpunit_testing, tests_readme_ci_unit_test_case, tests_readme_database_test_setup, tests_readme_xdebug_code_coverage, tests_readme_phpunit_xml_config [EXTRACTED 1.00]

## Communities (66 total, 55 thin omitted)

### Community 0 - "Payroll & Employee Controllers"
Cohesion: 0.07
Nodes (12): View, Deduction, Employee, Payroll, Payslip, Report, DeductionModel, EmployeeModel (+4 more)

### Community 1 - "Auth, Session & Dashboard"
Cohesion: 0.07
Nodes (9): Session, Auth, BaseController, Dashboard, Home, Office, User, AuthFilter (+1 more)

### Community 2 - "Composer Package Configuration"
Cohesion: 0.06
Nodes (35): autoload, exclude-from-classmap, psr-4, config, optimize-autoloader, preferred-install, sort-packages, description (+27 more)

### Community 3 - "App Config, Seeders & Tests"
Cohesion: 0.11
Nodes (8): App, UserSeeder, ExampleDatabaseTest, ExampleSessionTest, ExampleSeeder, ConfigReader, ExampleModel, HealthTest

### Community 4 - "HTTP, Security & Mail Config"
Cohesion: 0.13
Nodes (11): ContentSecurityPolicy, Cors, CURLRequest, Email, Encryption, Generators, Honeypot, Migrations (+3 more)

### Community 5 - "Suggested PHP Extensions"
Cohesion: 0.10
Nodes (20): suggest, ext-curl, ext-dom, ext-exif, ext-fileinfo, ext-gd, ext-imagick, ext-libxml (+12 more)

### Community 6 - "Project Docs & Directory Guards"
Cohesion: 0.15
Nodes (15): 403 Directory Access Guard (app/index.html), Robots.txt Crawler Policy, CodeIgniter 4 Framework, PHP 8.1+ Server Requirements, 403 Directory Access Guard (tests/index.html), CodeIgniter\Test\CIUnitTestCase, Test Database Configuration, CodeIgniter 4 PHPUnit Testing Guide (+7 more)

### Community 14 - "Payroll Index & Sheet View"
Cohesion: 0.36
Nodes (4): addCommas(), recalculateFromFirstQ(), unformatMoney(), updateRemaining()

### Community 19 - "Deduction Management View"
Cohesion: 0.80
Nodes (5): addCommas(), autoCalculateGSIS(), recalcNetPay(), unformatMoney(), updateEmployerShares()

### Community 20 - "HTML Error Debug Script"
Cohesion: 0.53
Nodes (4): getFirstChildWithTagName(), getHash(), init(), showTab()

### Community 32 - "Mahinog LGU Official Seal"
Cohesion: 0.83
Nodes (4): 13-Star Agricultural and Founding Year 1948 Motif, Coastal Terrain, Port Ferry, Sunrise, and Fishing Boat Motif, Municipality of Mahinog, Camiguin (Est. 1948), Municipality of Mahinog Official Seal (LGU Logo)

## Knowledge Gaps
- **8 isolated node(s):** `PHP 8.1+ Server Requirements`, `Robots.txt Crawler Policy`, `403 Directory Access Guard (app/index.html)`, `403 Directory Access Guard (writable/cache/index.html)`, `403 Directory Access Guard (writable/debugbar/index.html)` (+3 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 204 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **55 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Session` connect `Auth, Session & Dashboard` to `HTTP, Security & Mail Config`?**
  _High betweenness centrality (0.087) - this node is a cross-community bridge._
- **Are the 6 inferred relationships involving `OfficeModel` (e.g. with `.index()` and `.export()`) actually correct?**
  _`OfficeModel` has 6 INFERRED edges - model-reasoned connections that need verification._
- **What connects `PHP 8.1+ Server Requirements`, `Robots.txt Crawler Policy`, `403 Directory Access Guard (app/index.html)` to the rest of the system?**
  _8 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Payroll & Employee Controllers` be split into smaller, more focused modules?**
  _Cohesion score 0.06777493606138107 - nodes in this community are weakly interconnected._
- **Why does `View` connect `Payroll & Employee Controllers` to `Auth, Session & Dashboard`?**
  _High betweenness centrality (0.054) - this node is a cross-community bridge._
- **Are the 19 inferred relationships involving `View` (e.g. with `.login()` and `.index()`) actually correct?**
  _`View` has 19 INFERRED edges - model-reasoned connections that need verification._
- **Should `Auth, Session & Dashboard` be split into smaller, more focused modules?**
  _Cohesion score 0.07317073170731707 - nodes in this community are weakly interconnected._