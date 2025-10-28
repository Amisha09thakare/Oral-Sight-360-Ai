# 🦷 Oral Sight AI360  
**Smart Dental Management and AI-Based Oral Health Monitoring System**

---

## 📖 Overview  
**Oral Sight AI360** is an advanced web-based dental management platform designed to simplify communication between doctors and patients.  
It enables efficient appointment scheduling, treatment plan tracking, scan uploads, and AI-powered oral health insights — all in one place.

This system helps dental clinics digitize their workflow and patients to monitor their oral health progress seamlessly.

---

## 🚀 Key Features  

### 👨‍⚕️ Doctor Dashboard  
- Manage patient records and appointments.  
- Assign, edit, and track treatment plans with weekly progress.  
- Upload and analyze oral scans.  
- View patient visit history and treatment completion progress.  

### 🧑‍🦰 Patient Dashboard  
- View upcoming and past appointments.  
- Track treatment progress in real-time.  
- Access home care instructions and watch tutorial videos.  
- Upload oral scans for analysis and feedback.  

### ⚙️ Admin/Shared Features  
- Secure login and session management.  
- Dynamic dashboard with search and pagination.  
- Progress bar visualization for treatment tracking.  
- MySQL-based storage with validation and security.  

---

## 🧰 Tech Stack  

| Layer | Technology |
|-------|-------------|
| **Frontend** | HTML, CSS, Bootstrap, JavaScript, AJAX |
| **Backend** | PHP (XAMPP / Apache) |
| **Database** | MySQL |
| **Version Control** | Git & GitHub |
| **Deployment (optional)** | 000webhost / InfinityFree |

---

## 🗂️ Folder Structure  
```
oral_sight_ai360/
│
├── html/
│   ├── connect/
│   │   └── db.php
│   ├── doctor_dashboard.php
│   ├── patient_dashboard.php
│   ├── appointment_form.php
│   └── ...
│
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
│
├── sql/
│   └── oral_sight_ai360.sql
│
├── README.md
└── index.php
```

---

## ⚙️ Local Setup  

### Prerequisites  
- [XAMPP](https://www.apachefriends.org/index.html) installed  
- PHP 8+ and MySQL running  
- Git installed  

### Steps  
1. Clone the repository:  
   ```bash
   git clone https://github.com/Amisha09thakare/Oral-Sight-360-Ai.git
   ```
2. Move the folder into your XAMPP `htdocs` directory:  
   ```
   C:\xampp\htdocs\oral_sight_ai360
   ```
3. Import the database:  
   - Open **phpMyAdmin**  
   - Create a new database `oral_sight_ai360`  
   - Import the SQL file from `/sql/oral_sight_ai360.sql`

4. Update database credentials in `html/connect/db.php`:  
   ```php
   $servername = "localhost";
   $username = "root";
   $password = "";
   $dbname = "oral_sight_ai360";
   ```

5. Start Apache and MySQL from XAMPP Control Panel.  

6. Visit:  
   ```
   http://localhost/oral_sight_ai360/
   ```

---

## 🌐 Deployment (Optional)
If you want to deploy this project online:
- Use **000webhost** or **InfinityFree** (supports PHP + MySQL).  
- Upload files via GitHub integration or FTP.  
- Update database credentials to match the hosting provider.

---

## 📸 Screenshots (Add yours)
- Doctor Dashboard  
- Patient Dashboard  
- Treatment Progress Bar  
- Appointment Form  
*(Add your screenshots in the `assets/images` folder and link them here)*

---

## 💡 Future Enhancements  
- AI-based cavity detection using image classification.  
- Automated treatment suggestions.  
- Email / SMS reminders for appointments.  
- Cloud-based storage integration.  

---

## 🧑‍💻 Author  
**Amisha Thakare**  
📍 Computer Science Engineering | COEP Aspirant  
🌐 [GitHub Profile](https://github.com/Amisha09thakare)

---

## 🪪 License  
This project is open-source and available under the [MIT License](LICENSE).
