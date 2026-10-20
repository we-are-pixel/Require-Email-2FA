# Require Email 2FA

Read README.md first. Code style: the `anthropic-skills:wp-code-standards` and `anthropic-skills:every-line-counts` skills (WooCommerce-style: final classes in `includes/`, HTML in `includes/views/html-*.php`).

This plugin runs on about 300 client sites. Never break these:

- The installed path is `Require-Email-2FA-main/force-email-two-factor.php`. Never rename the main file, and never assume the folder name in code.
- The release asset stays `Require-Email-2FA-main.zip` with that top folder (`bin/build.sh`). Never ship a `two-factor/` folder: it would overwrite the official Two Factor plugin.
- Keep the `FORCE_2FA_*` constants and `force_2fa_*` filters listed in README, the `FORCE_2FA_LOADED` guard and the `force_2fa_enforced_capability` option. (Blocking mode was removed in 2.0 on purpose.)
- Plugin code must run on PHP 7.2 and WordPress 6.7 with Two Factor 0.14+. Tests may use newer PHP.
- Add no database state, so rolling back to the previous release stays safe.

Before a pull request run `composer check` (audit, standards, PHPStan and every test suite).
