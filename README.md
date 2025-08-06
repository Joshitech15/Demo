# DataTable with Pagination Script

A complete, ready-to-use pagination solution for data tables with modern design, responsive layout, and comprehensive features.

## 🚀 Features

- **Server-side Pagination** - Efficient handling of large datasets
- **Real-time Search** - Search across all columns with customizable delay
- **Responsive Design** - Mobile-first approach with adaptive layouts
- **Modern UI** - Clean design with hover effects and loading states
- **Customizable** - Highly configurable with custom formatters and styling
- **AJAX Loading** - Smooth data loading without page refreshes
- **Dark Mode Support** - Automatic dark mode detection
- **Print Friendly** - Optimized styles for printing
- **Cross-browser Compatible** - Works on all modern browsers

## 📁 Files Included

- `Pagination.php` - PHP class for server-side pagination logic
- `api.php` - RESTful API endpoint for serving paginated data
- `datatable.js` - JavaScript DataTable class
- `datatable.css` - Modern CSS styling
- `index.html` - Complete demo page with examples
- `array.php` - Original API integration example

## 🔧 Installation

1. **Upload Files**: Copy all files to your web server
2. **Configure API**: Update the API URL and credentials in `api.php` if using external data
3. **Include Files**: Add the CSS and JS files to your HTML page
4. **Initialize**: Create a DataTable instance with your configuration

```html
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="datatable.css">
</head>
<body>
    <div id="datatable-container"></div>
    
    <script src="datatable.js"></script>
    <script>
        const dataTable = new DataTable({
            container: '#datatable-container',
            apiUrl: 'api.php'
        });
    </script>
</body>
</html>
```

## 💻 Usage Examples

### Basic Implementation

```javascript
const dataTable = new DataTable({
    container: '#datatable-container',
    apiUrl: 'api.php',
    recordsPerPage: 10
});
```

### Advanced Configuration

```javascript
const dataTable = new DataTable({
    container: '#datatable-container',
    apiUrl: 'api.php',
    recordsPerPage: 25,
    searchDelay: 500,
    showSearch: true,
    showRecordsPerPage: true,
    showPaginationInfo: true,
    sortable: true,
    responsive: true,
    columns: [
        { key: 'id', title: 'ID' },
        { key: 'name', title: 'Full Name' },
        { key: 'email', title: 'Email Address' },
        { 
            key: 'salary', 
            title: 'Salary',
            formatter: (value) => new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD'
            }).format(value)
        },
        {
            key: 'join_date',
            title: 'Join Date',
            formatter: (value) => new Date(value).toLocaleDateString()
        }
    ]
});
```

### Custom Data Source

```javascript
// Using with your existing API
const dataTable = new DataTable({
    container: '#datatable-container',
    apiUrl: 'your-custom-api.php',
    recordsPerPage: 20,
    columns: [
        { key: 'user_id', title: 'User ID' },
        { key: 'username', title: 'Username' },
        { key: 'status', title: 'Status' }
    ]
});
```

## 🔌 API Integration

### Request Parameters

Your API endpoint should accept these GET parameters:

- `page` - Current page number (default: 1)
- `per_page` - Records per page (default: 10)
- `search` - Search term (optional)

### Response Format

Your API should return JSON in this format:

```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "first_name": "John",
            "last_name": "Doe",
            "email": "john.doe@example.com",
            "company": "Tech Corp",
            "salary": 75000
        }
    ],
    "pagination": {
        "current_page": 1,
        "total_pages": 15,
        "total_records": 150,
        "records_per_page": 10,
        "start_record": 1,
        "end_record": 10,
        "has_previous": false,
        "has_next": true,
        "search_term": ""
    },
    "page_numbers": [1, 2, 3, 4, 5]
}
```

## ⚙️ Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `container` | string | `'#datatable-container'` | CSS selector for the container element |
| `apiUrl` | string | `'api.php'` | API endpoint URL |
| `recordsPerPage` | number | `10` | Default number of records per page |
| `searchDelay` | number | `300` | Delay in milliseconds for search input |
| `showSearch` | boolean | `true` | Show search input field |
| `showRecordsPerPage` | boolean | `true` | Show records per page selector |
| `showPaginationInfo` | boolean | `true` | Show pagination information |
| `columns` | array | `[]` | Column definitions |
| `sortable` | boolean | `true` | Enable column sorting |
| `responsive` | boolean | `true` | Enable responsive design |
| `loadingText` | string | `'Loading...'` | Text shown during loading |
| `noDataText` | string | `'No data available'` | Text shown when no data |

## 🎯 Methods

### Navigation
```javascript
dataTable.goToPage(3);           // Go to specific page
dataTable.refresh();             // Refresh current data
```

### Search & Filter
```javascript
dataTable.search('john');        // Search for specific term
dataTable.sort('email');         // Sort by column
```

### Configuration
```javascript
dataTable.updateConfig({         // Update configuration
    recordsPerPage: 25,
    showSearch: false
});

dataTable.destroy();             // Clean up and destroy table
```

## 🎨 Styling

The DataTable comes with modern CSS styling that includes:

- **Responsive Design** - Adapts to mobile devices
- **Dark Mode Support** - Automatic dark theme detection
- **Hover Effects** - Interactive table rows and buttons
- **Loading States** - Smooth loading animations
- **Print Styles** - Optimized for printing

### Custom Styling

You can override the default styles by adding your own CSS:

```css
.datatable-wrapper {
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.datatable th {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}
```

## 🔧 PHP Backend

### Using the Pagination Class

```php
<?php
require_once 'Pagination.php';

// Sample data (replace with your database query)
$data = [
    ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'],
    ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com'],
    // ... more data
];

// Create pagination instance
$pagination = new Pagination($data, 10);

// Apply search if provided
if (!empty($_GET['search'])) {
    $pagination->search($_GET['search']);
}

// Set current page
$pagination->setCurrentPage($_GET['page'] ?? 1);

// Return JSON response
header('Content-Type: application/json');
echo json_encode($pagination->getResponse());
?>
```

### Database Integration

```php
<?php
// Example with database
$page = $_GET['page'] ?? 1;
$perPage = $_GET['per_page'] ?? 10;
$search = $_GET['search'] ?? '';

// Build query
$query = "SELECT * FROM users";
$params = [];

if ($search) {
    $query .= " WHERE name LIKE ? OR email LIKE ?";
    $params = ["%$search%", "%$search%"];
}

// Get total count
$countQuery = str_replace("SELECT *", "SELECT COUNT(*)", $query);
$totalRecords = $pdo->prepare($countQuery)->execute($params)->fetchColumn();

// Get paginated data
$offset = ($page - 1) * $perPage;
$query .= " LIMIT $perPage OFFSET $offset";
$data = $pdo->prepare($query)->execute($params)->fetchAll(PDO::FETCH_ASSOC);

// Use pagination class
$pagination = new Pagination($data, $perPage);
// ... rest of implementation
?>
```

## 📱 Browser Support

- Chrome 60+
- Firefox 55+
- Safari 12+
- Edge 79+
- Mobile browsers (iOS Safari, Chrome Mobile)

## 🤝 Contributing

Feel free to submit issues, feature requests, or pull requests to improve this DataTable pagination script.

## 📄 License

This project is open source and available under the MIT License.

---

**Ready to use!** Simply upload the files to your server and start implementing paginated data tables with modern UI and responsive design.
