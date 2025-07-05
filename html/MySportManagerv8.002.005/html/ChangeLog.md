# My Sport Manager Change Log

## My Sport Manager v8.002.004

### Released 23/05/2025

#### Features (v8.002.004)

- Updated UI: Improved timetable header sticky behavior
- Updated UI: Simplified timetable header implementation
- Updated UI: Optimized z-index handling between navigation and table header

#### Bug Fixes (v8.002.004)

**#0018:** Timetable header was sticking at wrong position
**#0019:** Z-index conflicts between navigation and table header
**#0020:** Duplicate header elements causing layout issues
**#0021:** Mobile view header behavior issues

## My Sport Manager v8.002.003

### Released 26/03/2025

#### Features (v8.002.003)

- Updated UI: Added dropdowns in admin site for the groups.
- Connected accounts work.
- Update UI: Make all pages work and look good on mobile devices.
- Update UI: Make is so when a user clicks a session on the timetable it opens a window with more information.

### Bug Fixes (v8.002.003)

**#0017:** CSV Uploads and downloads didn't include group splits.

## My Sport Manager v8.002.002

### Released 17/02/2025

#### Features (v8.002.002)

- Added: Attendence: In user site link will appear when they are POD to allow them to take the session attendance and attendance report available in coachs-site.

### Bug Fixes (v8.002.002)

**#0015:** POD sessions wasn't displaying on user site.
**#0016:** Coach Signature wasn't working on mobile/touchscreens.

## My Sport Manager v8.002.001

### Released 14/02/2025

#### Features (v8.002.001)

- Added option to add swimmers dob and automaticaly exempt them from pod when they turn 18.
- Added Excel output/download of sessions.
- Added A dropdown option to allow admins to move athletes to another squad/group.
- Added group Split.
- Added Session plan in coaches site.

### Bug Fixes (v8.002.001)

- **#0014:** Creating an invoice doesn't work.

## My Sport Manager v8.002.000

### Released 07/02/2025

#### Features (v8.002.000)

- Add Ai Games 1 v 1 games user vs Ai, club sets price, and then 10% goes to ME.
  - AI Games tic tac toe, connect 4, snake duel

## My Sport Manager v8.001.001

### Released 1/02/2025

#### Features (v8.001.001)

- Add Settings to admin site to manage My Sport Manager subscription.

## My Sport Manager v8.001.000

### Released 30/01/2025

#### Features (v8.001.000)

- Added: GoCardless Payment option
- Added Ai Games 1 v 1 games:
  - AI Games tic tac toe, connect 4, snake duel
- Added option to update swimmer name in users.php

#### Bug Fixes (v8.001.000)

- **#0013:** Timetable previous/next week wasn't working is the week was empty

## My Sport Manager v8.000.004

### Released 31/12/2024

#### Bug Fixes (v8.000.004)

- **#0012:** Pre-Saves wasn't working

#### Features (v8.000.004)

- Added page: Lotto game on both user and admin sites
- Added page: Raffle game on both user and admin sites
- Added: Normal donation option: no game, just donation

## My Sport Manager v8.000.003

### Released 24/12/2024

#### Bug Fixes (v8.000.003)

- **#0010:** Upload CSV file wasn't working
- **#0011:** Find All Pods wasn't working properly

#### Features (v8.000.003)

- Added Page: Fundraising: Clubs can receive donations from members when they play a game
- Added page: Bingo Game on both user and admin sites

## My Sport Manager v8.000.002

### Released 08/12/2024

#### Features (v8.000.002)

- Added: Sign-Out Button to all pages
- Added: Back button to all applicable screens
- Added: Find All Pods: run find pods function on all sessions in the current week on screen

#### Bug Fixes (v8.000.002)

- **#0009:** Find pod wasn't working when a session had more than one group in it. Fixed by querying the DB for each

## My Sport Manager v8.000.001

### Released 30/11/2024

#### Bug Fixes (v8.000.001)

- **#0008:** Pre-saves wasn't saving because the club wasn't fetched and sent to the DB

## My Sport Manager v8.000.000

### Released 29/11/2024

#### Features (v8.000.000)

- Added: New find pod algorithm (admin.php)
- Added: Timetable style: Looks better (timetable.php)
- Added: Users page: Admins can see and edit user info, and create new coach and admin accounts (users.php)
- Added: Membership page: Create and see invoices
- Added: Email dashboard: Send, receive, report as spam, delete and view emails
- Added: ComingSoon page for fundraising site
- Added: Better Styling: Improved styling and overall look and feel of all pages

#### Bug Fixes (v8.000.000)

- **#0007:** Dashboard page was too slow

#### Other

- New Name: My Sport Manager
- New Logo: in images folder

## Pod Rota v6.001.000

### Released 23/08/2024

#### Bug Fixes (v6.001.000)

- **#0004:** The Add item feature was not working (admin.php)
- **#0005:** The delete item function was not working (admin.php)
- **#0006:** The pre-save function was not working (coachs-site.php)

## Pod Rota v6.000.000

### Released 21/08/2024

#### Features (v6.000.000)

- Added: How-To-Use: The landing page shows users/parents how to use the site (index.html)
- Deprecated: Styling of the dashboard: Removed the blue line under the livesearch input box (dashboard.php)
- Added: Clubs: Different clubs can have access to their own version of the site, displaying only their own swimmers and timetable (site-wide)
- Added: Pod day exemptions: Swimmers can now be excluded from the pod duty for certain days (admin.php)

#### Bug Fixes (v6.000.000)

- **#0003:** The search bar for swimmers is now working (dashboard.php)

## Pod Rota v4.003.001

### Released 14/08/2024

#### Features (v4.003.001)

- Multiple weeks into the future and past (coachs-site.php + timetable.php)

## Pod Rota v4.003.000

### Released 13/08/2024

#### Features (v4.003.000)

- Push notifications (dashboard.php + notifications.php)
- Only allow access to the admin site and coach's site if the user is logged in as admin/coach (e.g., Warren, Mick, Seamus, Niamh, Dimitri, Terry, etc.) (login.php + admin.php + coachs-site.php)

## Pod Rota v4.002.002

### Released 06/08/2024

#### Features (v4.002.002)

- Add 'X' button that deletes a name from the Groups list (admin.php)

## Pod Rota v4.002.001

### Released 05/08/2024

#### Features (v4.002.001)

- As well as downloading the CSV file, an email will be sent acting as a trigger for the zap. The CSV file will be the attachment (admin.php)
- Redesign session display (admin.php)

## Pod Rota v4.002.000

### Released 04/08/2024

#### Features (v4.002.000)

- Download CSV file (admin.php)
- Fetch data from DB tables and join them by pod and swimmer (comma-separated value)

## Pod Rota v4.001.003

### Released 01.5/08/2024

#### Features (v4.001.003)

- Only the sessions that a user's swimmers are in are highlighted (timetable.php)
- Pod exemptions (admin.php)

#### Bug Fixes (v4.001.003)

- **#0002:** Swimmers that aren't in a group in a session that is being swapped can be pod (dashboard.php)

## Pod Rota v4.001.002

### Released 01/08/2024

#### Features (v4.001.002)

- Only one swimmer is entered as pod when a user agrees to swap (dashboard.php)

#### Bug Fixes (v4.001.002)

- **#0001:** Pod doesn't show even when one of the swimmers attached to a user was selected for pod (dashboard.php)
