UniFlow - Student Micro-Lending Platform
Project Description
UniFlow is a digital platform providing DTEF-sponsored university students in Botswana with responsible, flexible micro-loans directly from FNB. The system combines secure banking infrastructure with student-centric features to prevent predatory debt cycles while promoting financial wellness.

Key Features
Instant, low-interest micro-loans

DTEF system integration for verification

AI-powered eligibility assessment

Flexible allowance-aligned repayments

Integrated financial education

Robust security and transparency

Technology Stack
Frontend: HTML5, CSS3, Vanilla JavaScript

Backend: PHP

Database: MySQL

Hosting: AWS/Azure (planned)

Security: Multi-factor auth, end-to-end encryption

Database Schema
The system uses 10 core tables:

students - Student profiles and verification

universities - Partner institutions

loans - Loan applications and status

repayments - Payment tracking

academy_courses - Financial education

student_course_completion - Learning progress

student_financial_profiles - Risk assessment

gamification_points - Engagement tracking

admin_logs - Activity auditing

allowance_verification_logs - DTEF integration

Installation
Clone repository:

text
git clone https://github.com/infotech-product/uniflow.git
Import database schema from /uniflow_db.sql

Configure connection in /config.php:
Configure connection in /dbconnections.php:


php
define('DB_HOST', 'localhost');
define('DB_NAME', 'uniflow_db');
define('DB_USER', 'root'); 
define('DB_PASS', '');
Deploy to web server (Apache/Nginx with PHP/MySQL)

Usage
Access via web browser at your deployment URL. Three main interfaces:

Student Portal:

Loan applications

Repayment management

Financial courses

Admin Dashboard:

Loan approvals

Student verification

Reporting

API Endpoints:

/api/verify-student - DTEF verification

/api/loan-application - Submit new loans

/api/repayment - Process payments

Security
All sensitive data encrypted

Role-based access control

Comprehensive activity logging

Regular security audits recommended

License
Proprietary software developed for FNB Botswana

Contact
Development Team: InnovativeVisionaries
Project Manager: Thabang Olebogeng - thabang@hmcp.tech

Future Roadmap
Mobile app development

Expanded financial wellness features

Additional university integrations

Advanced AI risk modeling

Admin Cred: username:admin
            password:admin@123A

This README provides a comprehensive overview of the UniFlow system. For detailed implementation guides or security protocols, please contact the development team.
