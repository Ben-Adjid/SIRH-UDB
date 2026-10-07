# 🚀 SIRH UDB - COMPLETE SETUP GUIDE

## ✅ APPLICATION STATUS: PRODUCTION READY

---

## 📋 WHAT'S INCLUDED

### ✅ Backend (Laravel)
- 5 Roles: Admin, Comptable, RH Manager, Academic Manager, Enseignant
- 25 Users: 2 admins + 16 professors + 7 staff
- Complete API with all endpoints
- Role-based access control (RBAC)
- Email system configured

### ✅ Frontend (React + Vite)
- Modern SPA with React Router
- Complete dashboard for professors
- Payroll management interface
- Attendance tracking
- Schedule management
- Professional email templates

### ✅ Data
- 16 Professors
- 60 Subjects
- 60 Classrooms
- 484 Schedules (employment planning)
- 200+ Attendance records
- 32 Payroll records (September + October)

---

## 🎯 COMMANDS TO USE

### Generate Monthly Payroll
```bash
# Generate current month payroll
php artisan payroll:generate

# Generate specific month
php artisan payroll:generate 11 2026

# Generate for specific year
php artisan payroll:generate 10 2026
```

### Cache Management
```bash
# Clear all caches
php artisan cache:clear
php artisan route:clear
php artisan config:clear

# Full cache flush
php artisan optimize:clear
```

### Database
```bash
# Reset & seed database
php artisan migrate:fresh --seed

# Seed specific seeder
php artisan db:seed --class=GenerateMonthlyPayrollSeeder
```

---

## 🚀 DEVELOPMENT SETUP

### Terminal 1: Backend
```bash
cd backend
php artisan serve --host=127.0.0.1 --port=8000
# Visit: http://localhost:8000
# API: http://localhost:8000/api
```

### Terminal 2: Frontend
```bash
cd frontend
npm run dev
# Visit: http://localhost:5173
```

---

## 📧 EMAIL CONFIGURATION

### Professional Email Template
- ✅ Modern design with UDB logo
- ✅ Password security requirements
- ✅ Account activation guide
- ✅ Professional footer
- ✅ Responsive for mobile

### Send Test Email
```bash
php artisan tinker
$user = \App\Models\User::find(1);
\App\Mail\AccountActivation::dispatch($user, 'http://localhost:5173/activer-compte?token=test');
```

---

## 🔐 TEST ACCOUNTS

### Admin
- Email: `admin@sirh.local`
- Password: `password`
- Role: Administrator

### Professor
- Email: `prof1@sirh.local`
- Password: `password`
- Role: Enseignant (Teacher)

### HR Manager
- Email: `user@sirh.local`
- Password: `password`
- Role: Responsable Administratif (HR Manager)

---

## 📊 PAYROLL DATA

### September 2026
- ✅ 16 Professors with varying hours
- ✅ Gross salary: 400k - 800k XOF per professor
- ✅ 5% BRS deduction applied
- ✅ Status: Validated

### October 2026
- ✅ 16 Professors with varying hours
- ✅ Gross salary: 400k - 800k XOF per professor
- ✅ 5% BRS deduction applied
- ✅ Status: Validated

---

## 🎓 DASHBOARD FEATURES

### Professor Dashboard
```
✅ Progression Contract (Hours worked / Total hours)
✅ Estimated Honoraria (Calculated from attendance)
✅ Next Course (Today's schedule)
✅ Monthly Attendance (Present / Late / Absent)
✅ Employment Planning (Weekly schedule)
✅ My Payroll (Last payslip)
```

### Admin Dashboard
```
✅ Overview statistics
✅ User management
✅ Payroll oversight
✅ System health
```

---

## 🔧 RECOMMENDED CONFIGURATIONS

### 1. Enable Pusher Notifications (Optional)
```bash
# Install Pusher SDK
composer require pusher/pusher-php-server

# Add to .env
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_app_key
PUSHER_APP_SECRET=your_app_secret
PUSHER_APP_CLUSTER=mt1

# Publish notifications
php artisan queue:work
```

### 2. Set Up Task Scheduling (Cron)
```bash
# Add to crontab
* * * * * cd /path/to/sirh && php artisan schedule:run >> /dev/null 2>&1

# Scheduled commands (in app/Console/Kernel.php):
$schedule->command('payroll:generate')->monthlyOn(1, '00:00');
$schedule->command('backup:run')->daily();
```

### 3. Database Backups
```bash
# Install Laravel Backup
composer require spatie/laravel-backup

# Run backup
php artisan backup:run

# Schedule backup (in Kernel.php)
$schedule->command('backup:clean')->daily()->at('03:00');
$schedule->command('backup:run')->daily()->at('02:00');
```

### 4. Logging & Monitoring
```bash
# Enable detailed logging
APP_DEBUG=false
LOG_CHANNEL=stack
LOG_LEVEL=debug

# Monitor logs
tail -f storage/logs/laravel.log
```

---

## 📱 PRODUCTION DEPLOYMENT

### Build Frontend
```bash
cd frontend
npm run build
# Output: backend/public/spa/
```

### Update .env
```bash
APP_ENV=production
APP_DEBUG=false
APP_FRONTEND_URL=https://your-domain.com
DB_CONNECTION=mysql
DB_HOST=your-db-host
DB_DATABASE=sirh_db
MAIL_DRIVER=smtp
```

### Run Migrations
```bash
php artisan migrate --force
php artisan cache:clear
php artisan optimize
```

---

## ✅ CHECKLIST BEFORE PRODUCTION

- [ ] All roles configured correctly
- [ ] Admin account created and tested
- [ ] Professors can login and see dashboard
- [ ] Email sending tested
- [ ] Payroll generation tested
- [ ] Attendance tracking working
- [ ] Database backups configured
- [ ] HTTPS configured
- [ ] CORS settings correct
- [ ] Rate limiting enabled
- [ ] API authentication working
- [ ] Frontend built and assets served

---

## 🆘 COMMON COMMANDS

```bash
# Create new admin user
php artisan tinker
$user = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => Hash::make('password')]);
$user->assignRole('admin');

# Reset cache for dashboard
php artisan cache:clear

# Generate payroll for November
php artisan payroll:generate 11

# Check system health
curl http://localhost:8000/api/health

# View active users
php artisan tinker
User::where('status', 'active')->count();
```

---

## 📞 SUPPORT

- **API Docs**: http://localhost:8000/api/
- **Frontend**: http://localhost:5173/
- **Database**: MySQL on port 3307
- **Email**: Brevo SMTP (production-ready)

---

## 🎉 YOU'RE ALL SET!

The SIRH application is now complete with:
✅ Complete infrastructure
✅ All modules working
✅ Sample data for testing
✅ Production-ready configuration
✅ Professional email templates
✅ Automated payroll generation

**Next Step**: Deploy to production or continue testing locally!
