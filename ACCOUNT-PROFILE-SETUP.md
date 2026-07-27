# North Mountain Media Account Profile v38

Build: `20260726-publishing-workflow-v56`

## Required SQL

Import:

`database/account_profile_v38.sql`

This migration:

- Adds `profile_image_stored_name` to `users`
- Adds `profile_image_mime` to `users`
- Adds `profile_image_updated_at` to `users`
- Removes the obsolete `contact_email` row from `settings`

The migration is safe to run on MySQL 8 and MariaDB 10.11 and checks each column before adding it.

## Upload

Upload v38 over v37 while preserving:

- Active `config.php`
- `storage/`
- Existing uploads
- Call Center recordings
- Voicemail greetings
- Live Knowledge Center JSON and JavaScript when newer than the package

The package includes:

- `portal/profile-image.php`
- `storage/profile-images/.gitkeep`
- `assets/images/profile-placeholder.svg`

## Configure the administrator account

1. Sign in as administrator.
2. Open **System → Account**.
3. Enter:
   - Display name
   - Email
   - Phone
   - Company
4. Upload a JPG, PNG, WebP, or GIF profile photo up to 5 MB.
5. Select **Save account settings**.

The primary administrator account now supplies public contact information to:

- Public index
- Contact form fallback
- Sidebar profile footer
- Call Us page
- Call Center Email Dave action
- Knowledge-base contact answers

## Reset password

1. Open **System → Account**.
2. Enter the current password.
3. Enter and confirm a new password with at least 12 characters.
4. Select **Reset password**.

## Verify the main index

While signed in:

1. Open the public index.
2. Confirm the header displays an account button with:
   - Uploaded photo
   - Correct display name
   - Correct role
3. Open the dropdown.
4. Confirm it displays the account email and links to:
   - Dashboard
   - Account settings
   - Sign out
5. Confirm the bottom sidebar profile uses the uploaded administrator photo.

## Verify Call Us

1. Open `/call-dave.php`.
2. Confirm the header uses the same account dropdown.
3. Confirm the administrator profile photo appears to the left of the status message.
4. Confirm Email is generated from Account settings.
5. Confirm the saved phone number is not displayed publicly.
6. While signed in, confirm Name, Email, Phone, and Company pre-fill in the call form.

## Verify portal headers

1. Open any administrator or client portal page.
2. Confirm the top-right account avatar uses the uploaded profile photo.
3. Confirm clients without an uploaded photo receive the neutral placeholder.

## General Settings

The administrator Settings page no longer contains contact email or phone. Those values are managed only through Account settings.
