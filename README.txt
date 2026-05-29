SYSTEM MONITORING PHP + MYSQL SETUP

This version saves data to a MySQL database and exports a real .xlsx Excel file.
Multiple computers on the same network can encode at the same time.
The page now includes:
- a company switch for Mitsubishi and Hyundai
- separate database tables for each company
- a dark mode toggle

RECOMMENDED INSTALLATION:
Use XAMPP on the main/server computer.

PROJECT STRUCTURE:
1. index.php
   - Encoding form, company switch, dark mode, and monitoring summary table.

2. save.php
   - Saves submitted records to the selected company table in MySQL.

3. config.php
   - Database connection settings.

4. export_excel.php
   - Downloads the latest records as .xlsx.

5. database\setup.sql
   - Creates the database and both company tables:
     `MICEI system monitoring`
     `NTR system monitoring`

6. scripts\export_excel_helper.py
   - Builds the formatted Excel workbook used by the export.

HOW TO SET UP USING XAMPP:

1. Install XAMPP.
2. Open XAMPP Control Panel.
3. Start Apache.
4. Start MySQL.
5. Copy the folder "system_monitoring" to:
   C:\xampp\htdocs\

6. Open your browser and go to:
   http://localhost/phpmyadmin

7. Click Import.
8. Choose database\setup.sql.
9. Click Go.

10. Open:
    http://localhost/system_monitoring/

11. Use the company switch at the top of the page to move between:
    - Mitsubishi / MICEI System Monitoring
    - Hyundai / NTR System Monitoring

HOW OTHER COMPUTERS CAN ACCESS IT:

1. On the main computer, open Command Prompt.
2. Type:
   ipconfig

3. Look for IPv4 Address.
   Example:
   192.168.99.141

4. On another computer connected to the same Wi-Fi/LAN, open:
   http://192.168.99.141/system_monitoring/

Replace 192.168.99.141 with the actual IPv4 address of the main computer.

IMPORTANT FIREWALL NOTE:

If other computers cannot open the page:
1. Open Windows Defender Firewall.
2. Allow Apache HTTP Server on Private networks.
3. Or create an inbound rule for TCP port 80.

EXCEL NOTE:

You can keep Excel open separately because the live data is saved in MySQL.
Use the Export to Excel button anytime to download the latest summary.

LIVE AND TEST SERVER SETUP:

- Live server:
  http://localhost/system_monitoring/

- Test server:
  http://localhost/system_monitoring_test/

- Live database:
  system_monitoring_db

- Test database:
  system_monitoring_test_db

The app uses the local `.app-env` marker file to decide whether it is running
as the live server or the test server.

PROMOTE TO LIVE WORKFLOW:

Always make and test changes in:
  C:\xampp\htdocs\system_monitoring_test

When ready to deploy those changes to live, run this from:
  C:\xampp\htdocs\system_monitoring

1. Preview the promotion first:
   powershell -ExecutionPolicy Bypass -File scripts\promote_to_live.ps1

2. Promote test changes to live:
   powershell -ExecutionPolicy Bypass -File scripts\promote_to_live.ps1 -Apply

3. If you intentionally removed files in test and want them removed in live too:
   powershell -ExecutionPolicy Bypass -File scripts\promote_to_live.ps1 -Apply -DeleteRemoved

BROWSER BUTTON:

When you open the test server locally, a `Promote To Live` button appears in the
top header. It opens a promotion page that:

- previews the current test-to-live file diff
- lets you promote from the browser
- shows the promotion result/output after it runs

WHAT THE PROMOTION SCRIPT DOES:

- reads code from `C:\xampp\htdocs\system_monitoring_test`
- promotes it into `C:\xampp\htdocs\system_monitoring`
- skips `.app-env`, `.git`, and `uploads`
- creates a backup before overwriting live files
- runs a live schema sync after promotion

HELPER SCRIPT:

You can manually sync the current environment schema anytime with:
  php scripts\sync_environment_schema.php
