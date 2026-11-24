# Northwind Demo

A production-grade WordPress marketing site for a fictional technology consultancy, built the way a real client project would be: Composer-managed with [Roots Bedrock](https://roots.io/bedrock/), a block theme with ACF Pro blocks, a versioned content model, automated coding standards and accessibility checks, and push-to-deploy to WP Engine.

Every company, figure and client story in the seeded content is fictional.

[![CI](https://github.com/goranstosic/bedrock-demo/actions/workflows/ci.yml/badge.svg)](https://github.com/goranstosic/bedrock-demo/actions/workflows/ci.yml)
[![Accessibility](https://github.com/goranstosic/bedrock-demo/actions/workflows/a11y.yml/badge.svg)](https://github.com/goranstosic/bedrock-demo/actions/workflows/a11y.yml)

## What it shows

| Area | What is in this repository |
| --- | --- |
| Stack | Bedrock, PHP 8.3, WordPress 7.1, MariaDB 10.11, DDEV |
| Dependencies | WordPress core, plugins and ACF Pro installed by Composer; nothing third-party is committed |
| Content model | `Sector` and `Client story` post types, a `Region` taxonomy, and a bidirectional ACF relationship between sectors and stories, all in a must-use plugin so it survives a theme change |
| Blocks | Three ACF blocks (Stats, Client Story Cards, Region Switcher) with escaped output, empty states and token-only CSS |
| Regions | Visitors pick Global, US, UK or Australia; stories tagged Global show everywhere. The region travels as `?region=uk`, so every page stays cacheable |
| Editorial tooling | A pattern, an Appearance > Pattern library screen, and an [editor guide](docs/editor-guide.md) |
| Migration tooling | `wp northwind redirects import`, an idempotent CSV importer for the Redirection plugin with a dry run |
| Quality gates | PHPCS with WordPress Coding Standards and PHPCompatibility, axe-core and keyboard tests with Playwright, actionlint, all on every pull request |
| Delivery | GitHub Actions deploys `staging` and `main` to WP Engine; Dependabot keeps Composer, npm and Actions up to date |

## Quick start

You need [DDEV](https://ddev.com/get-started/) and an ACF Pro license key.

```sh
git clone https://github.com/goranstosic/bedrock-demo.git
cd bedrock-demo

# The key stays on your machine: .ddev/.env is gitignored.
echo "ACF_PRO_KEY=your-key-here" > .ddev/.env

ddev start
ddev setup
```

`ddev setup` installs Composer dependencies, creates `.env` with fresh salts, installs WordPress, activates the plugins and theme, seeds the demo content and imports the sample redirects. It is safe to run again.

- Site: <https://northwind-demo.ddev.site>
- Admin: <https://northwind-demo.ddev.site/wp/wp-admin> (user `admin`, password `admin`)

Things to try:

- `/sectors/financial-services/`, then switch region in the header. The client story cards change with the region.
- `/sectors/public-sector/` has a story for Australia only, so the cards block renders nothing in other regions.
- `/industries/banking/` is a redirect imported from `data/redirects.csv`.
- Appearance > Pattern library lists the patterns and the blocks each one uses.

## Everyday commands

| Command | What it does |
| --- | --- |
| `ddev composer lint` | Check PHP against the coding standards |
| `ddev composer lint:fix` | Fix what can be fixed automatically |
| `scripts/a11y.sh` | Run the axe and keyboard tests against the local site (needs Node.js 20+) |
| `ddev wp northwind seed` | Create any missing demo content |
| `ddev wp northwind redirects import data/redirects.csv --dry-run` | Preview a redirect import |
| `ddev exec composer build:wpengine` | Build the WP Engine artifact into `build/wp-content` |

## Repository layout

```
config/                         Bedrock configuration (environment specific in config/environments)
data/redirects.csv              Sample redirect map for the importer
web/app/mu-plugins/northwind-core/
  northwind-core.php            Bootstraps the content model
  src/                          PSR-4 classes: post types, taxonomy, region, queries, CLI commands
  acf-json/                     ACF field groups, versioned as JSON
web/app/themes/northwind/
  theme.json                    Design tokens: colour, type, spacing
  templates/, parts/            Block templates and template parts
  blocks/<name>/                block.json, render.php and style.css for each block
  patterns/                     Block patterns
scripts/                        Build, deploy, accessibility and GitHub setup scripts
tests/a11y/                     Playwright and axe-core tests
docs/                           Block standards, editor guide, deploy guide
```

## Branches and delivery

```
feature/*  ->  develop  ->  staging  ->  main
                           (WP Engine    (WP Engine
                            staging)      production)
```

- Work happens on short-lived branches off `develop`, merged by pull request.
- Every pull request runs **Lint**, **WP Engine build** and **Accessibility**. `main` is protected and needs all three to pass (see `scripts/protect-main.sh`).
- A push to `staging` or `main` builds a flat `wp-content` artifact and deploys it to the matching WP Engine environment. Full details, including first-time setup and rollback, are in [docs/wpengine-deploy.md](docs/wpengine-deploy.md).

## Secrets

Nothing secret is committed. The ACF Pro key lives in:

- `.ddev/.env` locally (gitignored),
- the `ACF_PRO_KEY` Actions secret and Dependabot secret on GitHub.

`auth.json`, `.env` files and the ACF Pro plugin itself are gitignored, and CI fails if the key ever shows up in the build artifact. Pull requests from forks do not receive secrets, so their checks that install ACF Pro will fail; a maintainer can rerun them from a branch.

## Further reading

- [Block standards](docs/block-standards.md): how blocks in this repository are built and reviewed
- [Editor guide](docs/editor-guide.md): for the people who write the content
- [WP Engine deploy](docs/wpengine-deploy.md): setup, day-to-day deploys and recovery

## License

[MIT](LICENSE). Based on Roots Bedrock, also MIT.
