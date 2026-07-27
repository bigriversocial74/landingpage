# Creating the Administrator Account

## Correct first-install sequence

1. Upload and extract the package.
2. Copy `config-example.php` to `config.php`.
3. Enter the database credentials.
4. During initial setup:
   - Leave `base_url` blank.
   - Keep `force_https` set to `false`.
5. Replace `setup_token` with a private value. Example:

   `'setup_token' => 'nmm-setup-2026-change-this-to-a-long-private-value',`

6. Open `/install.php`.
7. Paste the setup token into the installer screen.
8. Create the administrator name, email, and password.
9. Delete or rename `install.php`.
10. Replace the setup token in `config.php`.
11. Add the final HTTPS `base_url`.
12. After HTTPS works correctly, change `force_https` to `true`.
13. Sign in at `/portal/login.php?role=admin`.

## Common reasons the old installer did not work

- `base_url` was still set to `https://example.com`.
- `force_https` was enabled before SSL was configured.
- The server was behind a proxy that was not listed under `trusted_proxies`.
- The setup token still contained the example placeholder.
- Extra quotes or spaces were pasted into the installer URL.
- The database credentials were incomplete.

The repaired installer no longer requires the token to be placed in the URL.
