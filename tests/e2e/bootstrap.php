<?php
/**
 * End-to-end tests: real HTTP logins against the site served by tests/e2e/run.sh.
 *
 * @package Force_2FA
 */

require __DIR__ . '/E2ETestCase.php';

Force_2FA\Tests\E2E\E2ETestCase::create_users();
