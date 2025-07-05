# My Sport Manager

## In Progress


## TODO

- Add timetable filtering options
- Implement timetable search functionality
- Add Hytek file upload system:
  - Create secure file upload endpoint for .hyv and .hy3 files
  - Implement Hytek file parser to extract:
    - Meet information
    - Athlete entries
    - Event results
    - Team information
  - Add database tables for meet entries and results
  - Implement automatic POD exemption for athletes in meets
  - Add meet entry management interface
  - Add meet results display and reporting
  - Add validation and error handling for file uploads
- Add "Other Activity" checkbox for athles who don't go to other activities e.g group gym sessions
- On mobile make the email nav a dropdown instead.

## Ideas

- Include Fundraising games as part of another product with more games similar to roblox/poki/steam but with scoring system based on how many games played/hours and achivments in games
- Automate admin.php, including find pod, download csv/send email with csv attachment
- Add timetable conflict with other clubs detection

## Done

- Add multiple swimmers (dashboard.php)(v4.001.003).
- Pod Exemptions (admin.php)(v4.001.003).
- Notifications, by email and phone notification(external + admin.php)(v4.002.000).
- Send email with csv attachment, this will be the zap trigger(admin.php)(v4.002.001).
- Add 'X' to remove swimmer entry (admin.php)(v4.002.002).
- Push notifications(dashboard.php + notifications.php)(v4.002.003).
- Only allow access to the admin site and coach's site if the user is logged in as admin/coach_(e.g: Warren, Mick, Seamus, Niamh, Dimitri, Terry, etc)(login.php + admin.php + coachs-site.php)(v4.002.003).
- Multiple weeks in to the future and past(coachs-site.php + timetable.php)(v4.003.001).
- Re-design landing page (index.php)(v5.002.002).
- Fix feature: pre-saves (coachs-site.php)(v8.000.001)
- Add Feature: Sign-out button in all pages(v8.000.002)
- Add Feature: Back button the all applicable screens(v8.000.002)
- Fix Bug: #0009: Find pod wasn't working when a session had more than one group in it
- Add Feature: Find All Pods: run find pods function on all sessions in the current week on screen
- Add Page: Fundraising (v8.000.003)
- Add page: Bingo Game both for admin and user (v8.000.003)
- Fix Bug: #0011: Find All pods wasn't working properly (v8.000.003)
- Fix Bug: #0012: Pre-Saves wasn't working (v8.000.004)
- Add lotto game(v8.000.004)
- Add raffle game(v8.000.004)
- Add normal donation option: no game just donation(v8.000.004)
- Add payment service option Gocardless(v8.001.000)
- Add Settings to admin site to manage My Sport Manager subscription(v8.001.001)
- Add Ai Games 1 v 1 games user vs Ai, club sets price, and then 10% goes to ME.
  - AI Games tic tac toe, connect 4, snake duel (v8.002.000)
- Add option to add swimmers dob and automaticaly exempt them from pod when they turn 18 (v8.002.001)
- Add Excel output/download of sessions (v8.002.001)
- Add feature: A dropdown option to allow admins to move athletes to another squad/group (v8.002.001)
- Add feature: Group Split (v8.002.001)
- Fix bug: #0014: Creating an invoice doesn't work (v8.002.001)
- Add feature: Session plan in coaches site (v8.002.001)
- Add feature: Attendence: in user site link will appear when they are POD to allow them to take the session attendance and attendance report available in coachs-site (v8.002.002)
- Fixed bug: #0015: POD sessions wasn't displaying on user site (v8.002.002)
- Updated UI: Added dropdowns in admin site for the groups (v8.002.003)
- Update UI: Make all pages work and look good on mobile devices(v8.002.003)
- Update UI: Make is so when a user clicks a session on the timetable it opens a window with more information(v8.002.003)
- Add timetable export features (v8.002.003)
- Optimize timetable header performance(v8.002.004)
- Add smooth transitions for header stick/unstick(v8.002.004)
- Improve mobile responsiveness of timetable(v8.002.004)
- Add GoCardless integration again(v8.002.005)
- Set up Connect on stripe for the ai games to work(v8.002.005)
- Add loading states for timetable data(v8.002.005)
- Implement better error handling for timetable data(v8.002.005)
- Implement real-time timetable updates(v8.002.005)
- Fix position of the show/hide password button on the login page(v8.002.005)
- Fix Bug: #0022: The show/hide password button position wasn't right(v8.002.005)
- Fix Bug: #0023: On the admin page the groups section disapears if the first or second group is opened(v8.002.005)
- Fix Bug: #0024: On the competition page: Notice: Undefined variable: loginNeeded in /media/pi/My Passport/html/alpha_env/db.php on line 2(v8.002.005)
- Fix Bug: #0025: On the coming soon page, on mobile the nav bar is slighly visible and the toggle nav button is invisible.(v8.002.005)
- Moved the emails nav to the top left and widened(v8.002.005)
