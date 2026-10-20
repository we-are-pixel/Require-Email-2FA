=== Require Email 2FA ===
Contributors: wearepixel
Tags: two-factor, 2fa, security
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Requires two-factor login for every user on Pixel-managed sites. Built on the Two Factor plugin.

== Changelog ==

= 2.0.0 =
* Rewritten. Same settings and login behaviour as 1.13.2, except as listed here.
* Local copies (WP_ENVIRONMENT_TYPE "local" on a .local, .test or localhost address) only ask users who set up an authenticator app.
* Unchecking Email in Settings → Two-Factor no longer switches the email codes off.
* Removed: blocking mode (FORCE_2FA_BLOCKING_MODE). Logins still need the second factor.
* The XML-RPC check runs last, so other plugins can't undo a refusal.
* XML-RPC stays restricted while Two Factor is inactive.
* Excluded roles (FORCE_2FA_EXCLUDED_ROLES) never leave out a super admin.
* Deactivating the plugin no longer resets who must use two-factor; use the "2FA scope" link on the Plugins screen.
* The scope form pre-selects "Contributors and up".
* Fixed: a per-site activation on multisite (e.g. MainWP "Activate") no longer network-deactivates the plugin.
* Updates only come from published GitHub releases.
* A leftover 1.x copy and 2.x never run at the same time: whichever loads first stays in charge.
