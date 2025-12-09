# City Zone Shipping Management Feature

## Overview

The City Zone Shipping Management feature provides a comprehensive admin interface for managing shipping costs based on geographical locations (governorates/cities). This feature allows administrators to set, update, view, and delete shipping costs for different cities through a user-friendly CRUD interface.

## Features

- ✅ **Full CRUD Operations** (Create, Read, Update, Delete)
- ✅ **Search Functionality** by city name
- ✅ **Pagination** for large datasets
- ✅ **Responsive Design** consistent with project UI
- ✅ **Form Validation** with detailed error messages
- ✅ **Toast Notifications** for user feedback
- ✅ **Modal Confirmations** for destructive actions
- ✅ **Detailed View** with comprehensive information
- ✅ **Error Handling** with proper logging

## File Structure

### Controllers
```
app/Http/Controllers/Admin/Shipping/CityZoneShippingController.php
```
Main controller handling all CRUD operations with proper error handling and logging.

### Form Requests (Validation)
```
app/Http/Requests/Admin/CityZoneShippingStoreRequest.php
app/Http/Requests/Admin/CityZoneShippingUpdateRequest.php
```
Form request classes with validation rules for creating and updating shipping costs.

### Views
```
resources/views/admin-views/shipping/city-zone-shipping/
├── index.blade.php     # List all shipping costs with search and pagination
├── create.blade.php    # Add new shipping cost form
├── edit.blade.php      # Edit existing shipping cost form
└── show.blade.php      # View detailed shipping cost information
```

### Models Used
```
app/Models/CityShippingCost.php     # Main model for shipping costs
app/Models/Governorate.php          # Related model for cities/governorates
```

### Routes
All routes use the unique prefix `admin/shipping/city-zone-shipping/` to avoid conflicts.

## Database Schema

### Table: `city_shipping_costs`
```sql
- id (Primary Key)
- governorate_id (Foreign Key to governorates table, Unique)
- cost (Decimal 8,2, Default: 0)
- created_at (Timestamp)
- updated_at (Timestamp)
```

### Relationships
- **CityShippingCost** belongs to **Governorate**
- **Governorate** has one **CityShippingCost**

## Routes

| Method | URL | Route Name | Action |
|--------|-----|------------|--------|
| GET | `/admin/shipping/city-zone-shipping` | `admin.shipping.city-zone-shipping.index` | List all |
| GET | `/admin/shipping/city-zone-shipping/create` | `admin.shipping.city-zone-shipping.create` | Show create form |
| POST | `/admin/shipping/city-zone-shipping/store` | `admin.shipping.city-zone-shipping.store` | Store new record |
| GET | `/admin/shipping/city-zone-shipping/{id}` | `admin.shipping.city-zone-shipping.show` | View details |
| GET | `/admin/shipping/city-zone-shipping/{id}/edit` | `admin.shipping.city-zone-shipping.edit` | Show edit form |
| PUT | `/admin/shipping/city-zone-shipping/{id}` | `admin.shipping.city-zone-shipping.update` | Update record |
| DELETE | `/admin/shipping/city-zone-shipping/{id}` | `admin.shipping.city-zone-shipping.destroy` | Delete record |
| POST | `/admin/shipping/city-zone-shipping/update-status` | `admin.shipping.city-zone-shipping.update-status` | Update status |

## Navigation

**Admin Panel → System Settings → City Zone Shipping Management**

The menu item is located in the System Settings section as a main navigation item.

## Validation Rules

### Store/Create Validation
- **governorate_id**: Required, must exist in governorates table, must be unique
- **cost**: Required, numeric, minimum 0, maximum 999999.99

### Update Validation
- **governorate_id**: Required, must exist in governorates table, unique (ignoring current record)
- **cost**: Required, numeric, minimum 0, maximum 999999.99

## User Interface Features

### Index Page (List View)
- **Search**: Search by city name (Arabic)
- **Pagination**: 15 records per page
- **Actions**: View, Edit, Delete (for existing costs), Add Cost (for cities without costs)
- **Status**: Visual indicators for set/unset shipping costs
- **Responsive**: Mobile-friendly design

### Create Page
- **Form**: Simple form with city selection and cost input
- **Validation**: Real-time client-side validation
- **Currency**: Displays appropriate currency symbol
- **Availability Check**: Only shows cities without existing shipping costs

### Edit Page
- **Pre-filled Form**: Current values pre-populated
- **Current Info Display**: Shows existing information for reference
- **Validation**: Same as create with unique constraint ignoring current record

### Show/Detail Page
- **Comprehensive Info**: All record details in organized sections
- **Quick Actions**: Edit, Add New, Delete buttons
- **Record Info**: Creation/update timestamps and metadata
- **Confirmation**: Delete action requires modal confirmation

## Security Features

- **CSRF Protection**: All forms include CSRF tokens
- **Form Request Validation**: Server-side validation with detailed error messages
- **SQL Injection Prevention**: Uses Eloquent ORM for database operations
- **Permission Check**: Uses `business_settings` module permission
- **Error Handling**: Graceful error handling with user-friendly messages

## Technical Implementation

### Controller Methods

```php
index()          // List with search and pagination
create()         // Show create form
store()          // Process create form
show()           // Display detailed view
edit()           // Show edit form
update()         // Process edit form
destroy()        // Delete record
updateStatus()   // Update status via AJAX
```

### Key Features
- **Error Logging**: Detailed error logging for debugging
- **Toast Notifications**: User feedback using ToastMagic
- **Try-Catch Blocks**: Comprehensive error handling
- **Interface Compliance**: Implements required ControllerInterface

### Icons Used
All icons use the Flaticon (`fi fi-*`) system consistent with the project:
- **Search**: `fi fi-rr-search`
- **Add/Plus**: `fi fi-rr-plus`
- **View/Eye**: `fi fi-sr-eye`
- **Edit**: `fi fi-rr-edit`
- **Delete/Trash**: `fi fi-rr-trash`
- **Save/Disk**: `fi fi-rr-disk`

## Installation & Setup

1. **Models Already Exist**: `CityShippingCost` and `Governorate` models are already in the system
2. **Migration**: The `city_shipping_costs` table migration is already applied
3. **Routes**: Routes are defined in `routes/admin/routes.php`
4. **Views**: All view files are in `resources/views/admin-views/shipping/city-zone-shipping/`
5. **Sidebar**: Menu item added to System Settings section

## Usage

1. **Access**: Navigate to Admin Panel → System Settings → City Zone Shipping Management
2. **View List**: See all cities with their shipping cost status
3. **Search**: Use the search box to find specific cities
4. **Add Cost**: Click "Add Cost" for cities without shipping costs
5. **Edit**: Click the edit icon to modify existing shipping costs
6. **View Details**: Click the eye icon to see comprehensive information
7. **Delete**: Click the trash icon and confirm to remove shipping costs

## Error Handling

- **Controller Errors**: Logged to Laravel log with detailed information
- **User Feedback**: Toast notifications for success/error messages
- **Graceful Degradation**: Redirects to appropriate pages on errors
- **Validation Errors**: Displayed inline with form fields

## Customization

### Adding New Fields
1. Add field to `city_shipping_costs` migration
2. Update `CityShippingCost` model fillable array
3. Add field to form request validation rules
4. Update view forms to include new field

### Modifying Validation
Edit the validation rules in:
- `CityZoneShippingStoreRequest.php`
- `CityZoneShippingUpdateRequest.php`

### Styling Changes
Modify the Blade view files in `resources/views/admin-views/shipping/city-zone-shipping/`

## Unique Naming Convention

All components use the unique prefix `city-zone-shipping` to avoid conflicts:
- **Routes**: `admin.shipping.city-zone-shipping.*`
- **URLs**: `/admin/shipping/city-zone-shipping/*`
- **Controller**: `CityZoneShippingController`
- **Views**: `city-zone-shipping/*`
- **Form Requests**: `CityZoneShipping*Request`

## Dependencies

- **Laravel Framework**: Core framework
- **ToastMagic**: For notification messages
- **Flaticon**: For UI icons
- **Bootstrap**: For responsive UI components
- **jQuery**: For client-side interactions

## Permissions

The feature uses the `business_settings` module permission check to control access.

## Support

For issues or questions regarding this feature:
1. Check Laravel logs for detailed error information
2. Verify database relationships and constraints
3. Ensure proper permissions are set
4. Review form validation rules

---

**Created**: August 2025  
**Version**: 1.0  
**Compatible**: Laravel-based E-commerce Platform
