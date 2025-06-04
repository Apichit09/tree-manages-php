# ระบบจัดการสวนไม้ (Tree Management System)

## Overview ภาพรวม
Tree Management System คือเว็บแอปสำหรับบริหารจัดการสวนไม้ (Nursery) เหมาะกับ:
- Inventory CRUD: เพิ่ม/แก้ไข/ลบต้นไม้ (Create, Read, Update, Delete)
- Order & Invoice: สร้าง, ยกเลิก, ลบออเดอร์ พร้อม PDF export
- Dashboard & Reports: สถิติรายรับ-รายจ่าย (Income/Expense) พร้อม Chart.js graphs
- Activity Logs: ติดตามกิจกรรมล่าสุด (view, order_sent ฯลฯ)
- User Profile: จัดการบัญชีผู้ใช้ (login, register, update profile)

Tech Stack:
- PHP 7.4+ with PDO
- MySQL 5.7+
- Bootstrap 5.3 (UI)
- Chart.js (Charts)
- Optional: Composer for dependencies

## Features คุณสมบัติหลัก
1. Secure Auth: Login/Registration with CSRF, password_hash  
2. Tree Management: CRUD trees + image upload  
3. Order Processing: Create, cancel, delete orders + stock adjust  
4. Dashboard: Monthly summary + 12-month trend charts  
5. Logs: View/Delete activity logs  
6. Profile: Update user info, change password, upload avatar  

## Installation & Setup การติดตั้ง
1. Clone repo:  
   ```
   git clone https://github.com/your/repo.git d:\MAMP\MAMP\htdocs\tree-manages
   ```  
2. Database: import `db.sql` into MySQL  
3. Config: edit `/config/db.php` with your DB credentials  
4. Permissions: ensure `uploads/` is writable by web server  
5. Run: เปิด `http://localhost/tree-manages/pages/login.php`

## Project Structure โครงสร้างโปรเจค
```
/config           ── DB connection
/includes         ── header, footer, functions, CSRF
/pages            ── app pages: login, register, dashboard, trees, orders, logs, profile
/uploads          ── user avatars & tree images
/public/css       ── custom styles
/public/js        ── custom scripts & Chart.js config
README.md         ── this file
```

## Dependencies & Requirements
- PHP >= 7.4  
- MySQL >= 5.7  
- Bootstrap 5.3 (CDN)  
- Chart.js (CDN)  
- Composer (optional)