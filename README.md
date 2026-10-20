# Require Email 2FA

Our plugin that makes two-factor login mandatory on client sites. It works together with the free [Two Factor](https://wordpress.org/plugins/two-factor/) plugin: if a user hasn't set up an authenticator app, WordPress emails them a login code.

## How to use it

Install Two Factor, then upload `Require-Email-2FA-main.zip` from the [latest release](../../releases/latest) and activate both.

The first time you open the admin, the plugin asks who should use 2FA. On shops pick "Contributors and up", so customers can still log in with just their password.

Updates show up in MainWP like any other plugin. Update a few sites first, check you can log in, then do the rest.

## If something goes wrong

- **Emails don't arrive, so nobody can log in:** add `define( 'FORCE_2FA_DISABLE', true );` to `wp-config.php`, fix the email, then remove the line.
- **You need the previous version back:** upload the older zip in MainWP with "Overwrite existing".
- **The plugin is suddenly inactive:** someone renamed the `Require-Email-2FA-main` folder or `force-email-two-factor.php`. Put the names back.

## Settings

Most sites need none. If you do, they go in `wp-config.php`:

| Setting | Use it to |
| --- | --- |
| `FORCE_2FA_DISABLE` | switch the plugin off in an emergency |
| `FORCE_2FA_ENFORCED_CAPABILITY` | set who must use 2FA in code instead of the admin screen |
| `FORCE_2FA_EXCLUDED_ROLES` | let some roles, like customers, skip it |
| `FORCE_2FA_API_LOGIN_ALLOWLIST` | let a service account log in over XML-RPC with an Application Password |
| `FORCE_2FA_DISABLE_SELF_UPDATE` | stop the site checking GitHub for updates |

Developers can use filters too: `force_2fa_enforced_capability`, `force_2fa_excluded_roles`, `force_2fa_api_login_allowlist`, `force_2fa_self_update_enabled` and `force_2fa_user_is_exempt`.

## For developers

Run `composer install`, then `composer check` before every pull request. It runs the code checks and all the tests on throwaway WordPress sites.

To release, bump the version in `force-email-two-factor.php`, add a line to `readme.txt`, merge the pull request and approve the Release run in GitHub Actions.
