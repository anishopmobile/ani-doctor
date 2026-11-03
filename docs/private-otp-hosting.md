# Shared-host private OTP
Upload these four runtime files preserving their paths:
- app/Modules/Identity/Application/PrivateOtpDelivery.php (new)
- app/Modules/Identity/Application/AuthService.php (replace)
- config/identity.php (replace)
- routes/console.php (replace)

No migration or Composer dependency change. Back up replaced files first. Never place the application root or private directory in public_html; only public/ is web accessible.

Create /home/USERNAME/private-otp through File Manager with permissions 0700, outside public_html and any web-accessible alias. Files must be 0600. Only hosting account owner should access this directory.

Add to .env:
PRIVATE_OTP_ENABLED=true
PRIVATE_OTP_MOBILE=YOUR_11_DIGIT_MOBILE
PRIVATE_OTP_UNTIL="2026-11-04T20:00:00+03:30"
PRIVATE_OTP_DIRECTORY=/home/USERNAME/private-otp

Choose a future expiry within the next 24 hours; example date is not a permanent setting. APP_URL must be your real HTTPS URL. Keep APP_ENV=production, APP_DEBUG=false, LOCAL_OTP_PREVIEW=false and SESSION_SECURE_COOKIE=true. Do not change APP_KEY.

One-off cron (remove after it runs):
/usr/local/bin/php /home/USERNAME/SITE_FOLDER/artisan config:clear

Every-minute cron (* * * * *):
/usr/local/bin/php /home/USERNAME/SITE_FOLDER/artisan identity:export-private-otp >/dev/null 2>&1

Replace PHP binary with hosting PHP CLI path and SITE_FOLDER with application root containing artisan. Use the same PHP version as the site. Do not redirect output into a public file.

Request login for your exact configured mobile in your browser, wait for the next cron run, then read /home/USERNAME/private-otp/login-code.txt in File Manager. Enter the six-digit code in the same browser within the existing 120-second expiry. Resend starts a new challenge. Other mobiles still need SMS. No code appears on the website or cron email. pending.json is encrypted; do not edit it. Do not run the cron manually in overlapping jobs.

The cron removes the readable file on its next run after expiry, consumption or disable. A file already read/copied cannot be revoked, but expired/consumed codes cannot log in. File Manager may cache contents: reopen the file after resending.

After testing: set PRIVATE_OTP_ENABLED=false, clear config using the one-off cron again, delete both private files, then remove the export cron. Roll back the four files if needed. No live deployment is performed by this PR.
