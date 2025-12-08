# SellSuite Admin React App

## Setup & Build Instructions

### 1. Install Dependencies

Navigate to the admin folder and install npm packages:

```bash
cd admin
npm install
```

### 2. Build the React App

**For production build:**
```bash
npm run build
```

**For development with watch mode:**
```bash
npm run dev
```

Or:
```bash
npm start
```

### 3. File Structure

```
admin/
├── src/
│   ├── index.js          # Entry point
│   └── App.js            # Main App component (currently shows "Hello World")
├── build/
│   └── index.js          # Compiled bundle (created after build)
├── package.json          # Dependencies
└── webpack.config.js     # Webpack configuration
```

### 4. How It Works

1. **PHP Side** (`class-sellsuite-admin.php`):
   - Renders `<div id="sellsuite-settings-root"></div>`
   - Enqueues React, ReactDOM, and the compiled bundle
   - Passes data via `sellsuiteData` global variable

2. **React Side** (`src/index.js`):
   - Waits for DOM to load
   - Finds the root element
   - Mounts the App component

3. **App Component** (`src/App.js`):
   - Currently displays "Hello World"
   - Ready for you to build the settings interface

### 5. Available Global Data

Access WordPress data in your React components:

```javascript
const { apiUrl, nonce, currentPage, settings } = window.sellsuiteData;

// Example:
console.log(settings); // Current plugin settings
console.log(apiUrl);   // REST API URL
```

### 6. Next Steps

Ready to build the settings UI! The "Hello World" is working and waiting for your instructions.

---

**Reminder Keyword:** `SELLSUITE_POINTS_CONFIG`

Use this keyword to recall all the points system settings and options we discussed.
