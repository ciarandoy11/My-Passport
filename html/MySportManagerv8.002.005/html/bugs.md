# Bugs

## Fixed

**#0001:**

- Pod doesn't show even when one of the swimmers attached to a user was selected for pod (dashboard.php)(v4.001.002).

**#0002:**

- Swimmers that aren't in a group in a session that is being swapped can be pod (dashboard.php)(v4.001.003).

**#0003:**

- The search bar for swimmers is not working (dashboard.php)(v5.001.003).

**#0004:**

- The Add item feature stopped working (admin.php)(v6.000.000).

**#0005:**

- The delete item function stopped working (admin.php)(v6.000.000).

**#0006:**

- The pre-save function stopped working (coachs-site.php)(v6.000.000).

**#0007:**

- Dashboard page was too slow (v8.000.001).

**#0008:**

- Pre-saves wasn't saving because the club wasn't fetched and sent to the DB (v8.000.001).

**#0009:**

- Find pod wasn't working when a session had more than one group in it (v8.000.002).

**#0010:**

- Upload CSV file wasn't working (v8.000.003).

**#0011:**

- Find All Pods wasn't working properly (v8.000.003).

**#0012:**

- Pre-Saves wasn't working (v8.000.004).

**#0013:**

- The timetable previous/next week function wasn't working if there was no sessios that week

**#0014:**

- Creating an invoice doesn't work. Fixed by removing goCardless integration(v8.002.001)

**#0015:** 
- POD sessions wasn't displaying on user site (v8.002.002)

**#0016:**
- Coach Signature wasn't working on mobile/touchscreens (v8.002.002)

**#0017:** 
- CSV Uploads and downloads didn't include group splits (v8.002.003)

**#0018:**
- Timetable header was sticking at wrong position (v8.002.004)

**#0019:**
- Z-index conflicts between navigation and table header (v8.002.004)

**#0020:**
- Duplicate header elements causing layout issues (v8.002.004)

**#0021:**
- Mobile view header behavior issues (v8.002.004)

# Known Bugs

## High Priority
- Timetable header may flicker on fast scroll
- Mobile view header positioning needs refinement
- Table header z-index may conflict with other elements
- Session cards may overlap incorrectly on certain screen sizes

## Medium Priority
- Timetable performance could be improved on large datasets
- Mobile view needs better handling of long session names
- Table scrolling behavior could be smoother
- Session info modal positioning needs improvement

## Low Priority
- Minor visual glitches in header transitions
- Inconsistent spacing in timetable cells
- Session card shadows may overlap incorrectly
- Mobile view button spacing needs adjustment