# Public Repository Security Notes

This repository is intended to be public.

The following were intentionally excluded or sanitized:

- Database credentials
- Google OAuth configuration values
- Machine-specific Windows paths
- Composer `vendor/` dependencies
- Runtime profile-picture uploads
- Real/development user records
- Email addresses and password hashes
- Appointment records
- Login history, IP addresses, and user-agent records

If a secret is ever accidentally committed, revoke/rotate it immediately and remove it from repository history as appropriate.
