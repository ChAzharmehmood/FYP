# Role and permission matrix

Enforced server-side in `pms/includes/auth.php` (roles) and `pms/includes/permissions.php` (record ownership). Menus only hide links; every page and POST handler re-checks. Posted `role`, `station_id`, `staff_id` or hidden fields are never trusted.

Legend: **All** = every station · **Own** = own station only · **Self** = own records only · — = no access

| Area | Action | Head office admin | Station admin | Staff |
|---|---|---|---|---|
| Account | Sign in / out, change own password, edit own email | Yes | Yes | Yes |
| Account | Forgot / reset password | Yes | Yes | Yes |
| Stations | View list | All | Own | — |
| Stations | Add, edit, rename, deactivate, delete (empty only) | Yes | — | — |
| Staff | View list | All | Own | — |
| Staff | Add | Any role, any station | Own station; roles `staff` or `admin station` only | — |
| Staff | Edit (name, email, designation, CNIC, password reset) | All | Own station, not admin accounts, cannot move to another station | — |
| Staff | Change role | Any (not own) | `staff` ↔ `admin station` only, never `admin`, not own | — |
| Staff | Deactivate / reactivate | Any except self | Own station, not admin accounts, not self | — |
| Staff | Hard delete | — (deactivate instead) | — | — |
| Reports | File a report | Any station | Own station (posted station id ignored) | Own station |
| Reports | View / print | All | Own | Own station |
| Reports | Edit details | All (not archived) | Own (not archived) | Only own report while it is *Open* |
| Reports | Change status (Open ↔ Under Investigation ↔ Closed, reopen) | All | Own | — |
| Reports | Assign officer | All | Own station's active staff | — |
| Reports | Archive | All | Own | — |
| Reports | Restore from archive | Yes | — | — |
| Reports | Upload evidence | All | Own | Only on own report |
| Reports | Remove evidence (soft) | All | Own | — |
| Reports | Download evidence | All | Own | Own station |
| Reports | Search by CNIC, analysis charts, CSV export | All | Own | — |
| Duties | Assign / edit / cancel / complete / resend email | All | Own station's staff | — |
| Duties | View | All (list + calendar) | Own (list + calendar) | Self (list + calendar) |
| Leave | Submit, withdraw pending | (as any user) | (as any user) | Self |
| Leave | Approve / reject | All | Own station | — |
| Leave | Configure leave types and allowances | Yes | — | — |
| Alerts | Create / edit / deactivate / archive | Any target (everyone, district, station) | Own station only (target forced server-side) | — |
| Alerts | See | All | Everyone + own district + own station | Everyone + own district + own station |
| Audit log | View / export | Yes | — | — |
| Exports | CSV of any list | Yes | Own scope only | — |

## Notes

- **Staff and station reports.** The original application already let staff see every report of their own station (`view_reports.php` joined on the station name); that behaviour is kept, but the accused CNIC is masked for staff and staff cannot edit reports they did not file.
- **Deactivated accounts** lose access on their next request even with an open session (`session_version` check on every request), and cannot log in.
- **Password reset or admin-set password** signs the affected user out everywhere.
- **404 vs 403.** A record outside the user's scope answers *404 Not found*, the same as a record that does not exist, so ids cannot be probed. A page the role may never open answers *403*.
- **Public routes** (no login): `index.php`, `admin_login.php`, `login_station_admin.php`, `forgot_password.php`, `reset_password.php`, `logout.php`.
