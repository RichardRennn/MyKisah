# 🎬 MyKisah - Cinema Ticket Booking System

MyKisah adalah aplikasi web pemesanan tiket bioskop yang dibangun menggunakan **PHP Native**, **MySQL**, serta **HTML, CSS, dan JavaScript**. Aplikasi ini memungkinkan pengguna untuk melihat daftar film, melakukan pemesanan tiket, memilih kursi, melakukan pembayaran, dan mengelola profil pengguna. Selain itu, tersedia dashboard admin untuk mengelola data film, jadwal tayang, dan booking pelanggan.

---

## ✨ Features

### 👤 User Features

* User registration and login system
* Browse now playing and coming soon movies
* View movie details and schedules
* Seat selection system
* Ticket booking and payment confirmation
* Booking confirmation page
* User profile management
* Logout functionality

### 🔐 Admin Features

* Admin authentication and authorization
* Dashboard with booking and revenue statistics
* Manage movies (Create, Read, Update, Delete)
* Manage movie schedules
* Manage customer bookings
* Cinema hall management support

---

## 🛠️ Technologies Used

* **Backend:** PHP Native
* **Database:** MySQL
* **Frontend:** HTML5, CSS3, JavaScript
* **Server Environment:** Apache (XAMPP/Laragon)

---

## 📁 Project Structure

```text
MyKisah/
├── admin/
│   ├── index.php
│   ├── manage-bookings.php
│   ├── manage-movies.php
│   ├── manage-schedules.php
│   └── get-halls.php
│
├── images/
│   └── Movie posters and assets
│
├── includes/
│   └── config.php
│
├── index.php
├── login.php
├── register.php
├── profile.php
├── movie-detail.php
├── select-seat.php
├── payment.php
├── confirmation.php
└── logout.php
```

---

## 🗄️ Database Configuration

Database configuration is located in:

```php
includes/config.php
```

Default configuration:

```php
DB_HOST = localhost
DB_USER = root
DB_PASS = 1234
DB_NAME = cinema_booking
```

Make sure the database and tables are created before running the application.

---

## 🚀 Installation

### 1. Clone Repository

```bash
git clone https://github.com/your-username/MyKisah.git
```

### 2. Move Project

Place the project folder inside:

**XAMPP**

```text
htdocs/MyKisah
```

or

**Laragon**

```text
www/MyKisah
```

### 3. Create Database

Create a new database:

```sql
CREATE DATABASE cinema_booking;
```

Then import the provided SQL file (if available).

### 4. Configure Database

Open:

```text
includes/config.php
```

Adjust the database credentials according to your local environment.

### 5. Run Application

Start:

* Apache
* MySQL

Open in browser:

```text
http://localhost/MyKisah
```

---

## 📸 Application Modules

### Customer Module

* Authentication
* Movie browsing
* Movie detail page
* Seat reservation
* Payment process
* Booking confirmation
* Profile management

### Admin Module

* Dashboard analytics
* Movie management
* Schedule management
* Booking management

---

## 🔒 Security Features

* Password hashing using PHP's `password_hash()` and `password_verify()`
* Session-based authentication
* Role-based access control for admin pages
* Prepared statements for SQL queries to reduce SQL Injection risks

---

## 🎯 Future Improvements

* Online payment gateway integration
* Email ticket confirmation
* QR code e-ticket generation
* Search and filtering system
* Movie reviews and ratings
* Responsive mobile interface
* Booking history and invoice download

---

## 👨‍💻 Authors

Developed as a Web Programming Project by:

**MyKisah Development Team**

---

## 📄 License

This project is developed for educational purposes and can be modified and distributed for learning and non-commercial use.
