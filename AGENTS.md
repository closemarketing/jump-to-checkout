# Jump to Checkout — Agent Instructions

## Project Overview

**Jump to Checkout** is a free WooCommerce plugin that generates secure direct checkout links with pre-selected products. When a customer clicks a link, their cart is populated and they are redirected to checkout.

- **Plugin slug**: `jump-to-checkout`
- **PHP namespace**: `CLOSE\JumpToCheckout`
- **Function/option prefix**: `jptc_` (constant prefix `JTPC_`)
- **Text domain**: `jump-to-checkout`
- **Requires**: WordPress 5.0+, WooCommerce 5.0+, PHP 7.4+

## Features

- Unlimited checkout links
- Multiple products per link (simple, variable, and variations)
- Link expiration (set custom expiry hours)
- Visits and conversions tracking per link
- Conversion rate statistics
- Enable/disable links without deleting them
- Export statistics to CSV/Excel
- Advanced analytics with charts
- Automatic coupon application on link click
- Templates for reusing link configurations
- UTM parameter tracking
- Webhooks for conversion and link events
- REST API for programmatic link management
- Scheduled link activation/deactivation
- Cryptographically signed tokens (HMAC-SHA256)

## File Structure

```
jump-to-checkout/
├── jump-to-checkout.php          # Bootstrap: constants, activation/deactivation hooks
├── includes/
│   ├── Core/
│   │   ├── JumpToCheckout.php    # Main logic: URL handling, cart population, conversion tracking
│   │   └── Features.php          # Feature availability checks
│   ├── Admin/
│   │   ├── AdminPanel.php        # "Generate Link" admin page + AJAX handlers
│   │   └── LinksManager.php      # "Manage Links" admin page + AJAX handlers
│   └── Database/
│       └── Database.php          # All DB operations (table: {prefix}jptc_links)
├── assets/
│   ├── css/admin.css             # Styles for AdminPanel page
│   ├── css/manager.css           # Styles for LinksManager page
│   ├── js/admin.js               # JS for AdminPanel (link generation, product search)
│   └── js/manager.js             # JS for LinksManager (delete, toggle, copy)
├── tests/
│   ├── Unit/
│   │   ├── AdminPanelTest.php
│   │   ├── DatabaseTest.php
│   │   └── JumpToCheckoutTest.php
│   └── bootstrap.php
└── .github/workflows/            # CI: phplint, phpunit, deploy
```

## Database

Single custom table `{prefix}jptc_links`:

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | Auto-increment |
| `name` | varchar(255) | Human label |
| `token` | text | 10-char alphanumeric short token (new) or base64 long token (legacy) |
| `url` | text | Full URL: `{home}/jump-to-checkout/{token}` |
| `products` | longtext | JSON array: `[{product_id, quantity, variation_id?, variation?}]` |
| `expiry_hours` | int | 0 = never expires |
| `expires_at` | datetime | NULL = never expires |
| `created_at` | datetime | Auto |
| `visits` | int | Incremented on each link click |
| `conversions` | int | Incremented on order completion |
| `status` | varchar(20) | `active` / `inactive` |

DB version tracked in option `jptc_db_version`. Current: `1.0.0`.

## Key Hooks

### Actions registered by the plugin

| Hook | Class | Purpose |
|---|---|---|
| `template_redirect` | `JumpToCheckout` | Intercepts link visits, populates cart, redirects |
| `init` | `JumpToCheckout` | Registers rewrite rule `jump-to-checkout/([^/]+)` |
| `woocommerce_thankyou` / `woocommerce_payment_complete` / `woocommerce_order_status_*` | `JumpToCheckout` | Conversion tracking |
| `woocommerce_checkout_order_processed` | `JumpToCheckout` | Saves link ID to order meta |
| `admin_menu` | `AdminPanel`, `LinksManager` | Adds admin pages |
| `admin_enqueue_scripts` | `AdminPanel`, `LinksManager` | Loads CSS/JS per page |
| `wp_ajax_jptc_generate_link` | `AdminPanel` | AJAX: generate new link |
| `wp_ajax_jptc_search_products` | `AdminPanel` | AJAX: product search (Select2) |
| `wp_ajax_jptc_delete_link` | `LinksManager` | AJAX: delete link |
| `wp_ajax_jptc_toggle_status` | `LinksManager` | AJAX: enable/disable link |

### Filters provided for extension

| Filter | Purpose |
|---|---|
| `jptc_link_expiry` | Modify expiry before link creation |
| `jptc_link_data_before_insert` | Modify full link data before DB insert |
| `jptc_link_is_expired` | Override expiry check on link visit |
| `jptc_token_expiry_check` | Override token expiry for old-format tokens |
| `jptc_ajax_link_expiry` | Override expiry value in AJAX handler |

### Actions provided for extension

| Action | Purpose |
|---|---|
| `jptc_render_expiry_section` | Render expiry UI in AdminPanel |

## Coding Standards

- **Indentation**: tabs (no spaces).
- **Alignment**: spaces to align `=` within same group of variable assignments.
- **Language**: all code and comments in English.
- **Comments**: PHP inline comments start with a capital letter and end with a period. Keep them concise; only add when the WHY is non-obvious.
- **Conditions**: always use Yoda conditions (`if ( 'active' === $status )`).
- **JavaScript**: vanilla JS only, no jQuery.
- **PHP minimum**: 7.4 (typed properties and arrow functions allowed).
- **Security**: nonces on every AJAX call, `current_user_can()` checks, `sanitize_*` / `esc_*` everywhere, `$wpdb->prepare()` for all queries.
- **No docs folder** unless explicitly requested; add to `.distignore` if created.
- Autoloading via Composer PSR-4 (`vendor/autoload.php`).

## Token Format

New tokens are 10-char alphanumeric, generated by `generate_short_token()` in `JumpToCheckout`. Products are stored in the DB, not encoded in the token. Legacy long-format (base64) tokens are still decoded via `decode_token()` for backward compatibility — do not use that format for new links. Short token check: `is_short_token()` returns `true` for tokens ≤ 20 chars.

## Testing

```bash
# Install test suite (first time)
bash bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version]

# Run unit tests
composer test

# PHP lint
composer lint

# PHPStan
composer phpstan
```

Test files live in `tests/Unit/`. Bootstrap is `tests/bootstrap.php`. PHPStan config: `phpstan.neon.dist`. PHPCS config: `.phpcs.xml.dist`.

## CI/CD

Three GitHub Actions workflows:

| Workflow | Trigger | Purpose |
|---|---|---|
| `phplint.yml` | push/PR | PHP syntax check |
| `phpunit.yml` | push/PR | Unit tests |
| `deploy.yml` | push to `main` | Deploy to WordPress.org SVN |

## Conventions for AI Agents

- Always read the file before editing it.
- Never add features or refactoring beyond the scope of the current task.
- When modifying the DB schema, increment `jptc_db_version` and handle the migration in `maybe_create_table`.
- Do not use `base64_encode`/`base64_decode` for new code — only kept for legacy token backward compatibility.
