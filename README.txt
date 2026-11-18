SMART GARBAGE COLLECTION SYSTEM (PHP + MySQL, XAMPP)

1. Extract this zip into C:\xampp\htdocs so you get C:\xampp\htdocs\smart-garbage
2. Start Apache and MySQL in the XAMPP Control Panel.
3. Open http://localhost/phpmyadmin -> Import -> choose database.sql -> Go
4. Open http://localhost/smart-garbage/
   Admin login: admin@estate.com / admin123
5. VS Code: File > Open Folder > C:\xampp\htdocs\smart-garbage

MySQL port: config.php uses 3307. If your MySQL runs on 3306, change DB_PORT in config.php.
If phpMyAdmin cannot connect on 3307, add this line to C:\xampp\phpMyAdmin\config.inc.php:
  $cfg['Servers'][$i]['port'] = '3307';
