# Moms Beauty Touch — Database Setup Instructions

This guide provides simple, step-by-step instructions for importing and setting up the MySQL database using **XAMPP**.

---

## 1. Prerequisites

1. Install **XAMPP** (version 8.0 or newer).
2. Open the **XAMPP Control Panel**.
3. Start the **Apache** and **MySQL** modules (both buttons should show green status indicators).

---

## 2. Importing the Database via phpMyAdmin (Recommended for Beginners)

1. Open your web browser and go to:
   [http://localhost/phpmyadmin/](http://localhost/phpmyadmin/)
2. In the top navigation bar, click on **Import**.
3. Under **File to import**, click **Choose File** (or **Browse**).
4. Navigate to your project folder and select:
   `moms-beauty-touch/database/schema.sql`
5. Ensure the character set is set to **utf-8**.
6. Scroll to the bottom and click the **Import** button.
7. You should see a green success banner indicating that the database `moms_beauty_touch` and all 8 tables were created and populated with initial seed data.

---

## 3. Alternative: Importing via MySQL Command Line

If you prefer using the command line:

```bash
# Open command prompt or terminal and run:
mysql -u root -p < "C:\path\to\moms-beauty-touch\database\schema.sql"
```
*(By default in XAMPP, the password for user `root` is blank, so simply press Enter when prompted).*

---

## 4. Tables Created

| Table Name | Purpose | Key Features |
| :--- | :--- | :--- |
| **`admins`** | Administrator accounts | Password hashing (`password_hash`), no hardcoded default accounts; secure initial setup on first visit |
| **`login_attempts`** | Security rate-limiting | Tracks IP and attempt timestamps to block brute-force attacks |
| **`business_settings`** | Core business profile | Singleton row (#1) with phone, WhatsApp, address, rating, and operating hours |
| **`services`** | Service catalog | Slugs, descriptions, pricing, image paths, sort order, and active toggle |
| **`gallery`** | Portfolio looks | Image paths, categories (Bridal, Party, Engagement, Wedding, Special Occasions) |
| **`appointments`** | Customer booking enquiries | Unique `ref_code` (e.g. `MBT-XXXXXX`), foreign key to `services`, event date, time, location, and status |
| **`testimonials`** | Client reviews & feedback | Rating (1-5 stars), text, and `is_published` visibility toggle |
| **`contact_messages`** | Direct contact submissions | Name, message, submission timestamp, and `is_read` flag |

---

## 5. Seed Data Included

The database automatically imports:
1. **Business Profile**: All contact information, Kaner/Vasai-Virar address, and 4.52 Google rating.
2. **4 Core Services**: Bridal Makeup, Party Makeup, Wedding Makeup, and Engagement Makeup.
3. **6 Gallery Looks**: Across Bridal, Party, Engagement, Wedding, and Special Occasions categories.
4. **Admins Table**: Left clean. The one-time secure admin setup wizard will guide you to create your own secure username and password upon first access.

