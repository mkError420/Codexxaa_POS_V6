# Codexaa Site Deployment Guide

## Database Configuration

Your site is configured with the following MySQL credentials:
- **Host:** sql309.infinityfree.com
- **Database Name:** if0_42451104_codexaa
- **User Name:** if0_42451104
- **Password:** I8Kw8aZkldJO

These credentials are already configured in `backend/config/db.php` for production use.

## Deployment Steps

### 1. Upload Files to InfinityFree

Upload the following structure to your InfinityFree File Manager in the `/htdocs/` directory:

```
/htdocs/
  ├── .htaccess (root level - already created)
  ├── backend/
  │   ├── .htaccess
  │   ├── config/
  │   │   └── db.php (with your credentials)
  │   ├── controllers/
  │   ├── middleware/
  │   └── index.php
  └── frontend/
      ├── index.html
      └── dist/ (build output - already built)
```

### 2. Database Setup (Important!)

**Before uploading files, you MUST import the database schema:**

1. Log in to your InfinityFree control panel
2. Open phpMyAdmin for your database: `if0_42451104_codexaa`
3. Click on the "Import" tab
4. Select the `database/schema.sql` file from this project
5. Click "Go" to import the schema

**Note:** The schema has been updated to use your pre-created database name (`if0_42451104_codexaa`) instead of trying to create a new database, which is not allowed on InfinityFree.

### 3. Important Notes

- **Database credentials** are already configured in `backend/config/db.php`
- **Frontend is already built** - the `frontend/dist` folder is ready for deployment
- **Root .htaccess** is already created for proper routing
- **Database schema must be imported manually** via phpMyAdmin before first use
- **Super Admin** will be automatically seeded with email: `mk.rabbani.cse@gmail.com` and password: `123456789`

### 3. Access Your Site

After uploading, access your site at: https://codexaa.xo.je/

### 4. First Login

Use these credentials to login as Super Admin:
- **Email:** mk.rabbani.cse@gmail.com
- **Password:** 123456789

**Important:** Change the Super Admin password immediately after first login!

### 5. Create Your First Shop

After logging in as Super Admin:
1. Go to "Manage Shops"
2. Click "Add New Shop"
3. Fill in shop details (name, email, phone, address)
4. Create a Shop Admin account for the shop
5. The shop will be immediately available for use

## Features Available

### Super Admin Features
- Create and manage multiple tenant shops
- Manage all users across all shops
- Reset any user's password
- Suspend/activate users and shops
- View system-wide analytics

### Shop Admin Features
- POS Checkout system
- Inventory management
- Supplier management
- Customer management
- Sales history and analytics
- Staff management
- Purchase orders
- Manual orders
- Returns management
- Wastage tracking
- Due payment tracking

### Shop Staff Features
- POS Checkout
- View inventory
- Customer lookup
- Limited access based on permissions

## Troubleshooting

### Database Connection Issues
If you encounter database connection errors:
1. Verify your MySQL credentials are correct
2. Check that the database exists in InfinityFree phpMyAdmin
3. Ensure the database user has proper permissions

### Frontend Not Loading
If the frontend doesn't load:
1. Clear your browser cache
2. Check that the `frontend/dist` folder was uploaded correctly
3. Verify the root `.htaccess` file is present

### API Errors
If you encounter API errors:
1. Check browser console (F12) for specific error messages
2. Verify the backend files are uploaded correctly
3. Check that PHP version is 8.0 or higher

## Security Recommendations

1. **Change default passwords** immediately after first login
2. **Use strong passwords** for all user accounts
3. **Enable HTTPS** (InfinityFree provides free SSL)
4. **Regular backups** - export your database regularly
5. **Monitor user activity** - review user access periodically

## Support

For issues or questions:
- Check the INFINITYFREE_DEPLOYMENT.md for additional deployment details
- Review the README.md for system features and usage
- Check browser console for JavaScript errors
- Check network tab for failed API requests
