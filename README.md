# Sedona SAX Editor

A modern web-based visual editor for Sedona Framework SAX files built with Vue3, Laravel, and MySQL. This tool provides a drag-and-drop interface for creating and editing Sedona automation programs.

![Sedona SAX Editor](https://img.shields.io/badge/Vue-3.x-brightgreen) ![Laravel](https://img.shields.io/badge/Laravel-10.x-red) ![MySQL](https://img.shields.io/badge/MySQL-8.0-blue)

## Features

- 🎨 **Visual Programming Interface**: Drag-and-drop components onto a canvas
- 🔗 **Wire Connections**: Connect component slots with visual links
- 📁 **Project Management**: Save and load SAX projects
- 🏷️ **Component Library**: Extensible kit system with various component types
- ⚙️ **Property Editor**: Configure component properties in real-time
- 📤 **Import/Export**: Load and save standard SAX XML files
- 👥 **Collaboration**: Real-time multi-user editing support
- 🔍 **Search & Filter**: Quickly find components and connections
- 📱 **Responsive Design**: Works on desktop and tablet devices

## Tech Stack

### Backend
- **Laravel 10.x**: PHP framework for API and business logic
- **MySQL 8.0**: Database for storing projects and metadata
- **Laravel WebSockets**: Real-time collaboration
- **Laravel Sanctum**: API authentication

### Frontend
- **Vue 3**: Progressive JavaScript framework
- **Vite**: Build tool and dev server
- **Pinia**: State management
- **Vue Router**: Client-side routing
- **Tailwind CSS**: Utility-first CSS framework
- **Konva.js**: 2D canvas library for the visual editor

## Prerequisites

- PHP 8.1 or higher
- Composer
- Node.js 16.x or higher
- npm or yarn
- MySQL 8.0
- Redis (optional, for caching and queues)

## Installation

### 1. Clone the repository

```bash
git clone https://github.com/schneider82/sedona-sax-editor.git
cd sedona-sax-editor
```

### 2. Backend Setup

```bash
# Install PHP dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Configure your database in .env file
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=sedona_sax_editor
# DB_USERNAME=your_username
# DB_PASSWORD=your_password

# Run migrations
php artisan migrate

# Seed the database with sample data (optional)
php artisan db:seed

# Start the Laravel development server
php artisan serve
```

### 3. Frontend Setup

```bash
# Navigate to frontend directory
cd frontend

# Install dependencies
npm install

# Copy environment file
cp .env.example .env.local

# Start the development server
npm run dev
```

### 4. WebSocket Server (for real-time features)

```bash
# In a separate terminal
php artisan websockets:serve
```

## Project Structure

```
sedona-sax-editor/
├── backend/                 # Laravel backend
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   └── Resources/
│   │   ├── Models/
│   │   └── Services/
│   ├── database/
│   │   ├── migrations/
│   │   └── seeders/
│   └── routes/
├── frontend/               # Vue3 frontend
│   ├── src/
│   │   ├── components/
│   │   │   ├── canvas/
│   │   │   ├── palette/
│   │   │   └── properties/
│   │   ├── composables/
│   │   ├── stores/
│   │   └── views/
│   └── public/
└── docs/                   # Documentation

```

## Usage

### Creating a New Project

1. Click "New Project" in the toolbar
2. Drag components from the palette onto the canvas
3. Connect component slots by clicking and dragging between pins
4. Configure component properties in the properties panel
5. Save your project or export as SAX file

### Importing SAX Files

1. Click "Import" in the File menu
2. Select a `.sax` file from your computer
3. The visual representation will be generated automatically

### Keyboard Shortcuts

- `Ctrl/Cmd + S`: Save project
- `Ctrl/Cmd + O`: Open project
- `Ctrl/Cmd + Z`: Undo
- `Ctrl/Cmd + Y`: Redo
- `Delete`: Delete selected component
- `Ctrl/Cmd + C`: Copy selected components
- `Ctrl/Cmd + V`: Paste components
- `Arrow Keys`: Move selected components

## API Documentation

The backend provides a RESTful API for all operations. See [API Documentation](docs/API.md) for details.

### Main Endpoints

- `GET /api/projects` - List all projects
- `POST /api/projects` - Create new project
- `GET /api/projects/{id}` - Get project details
- `PUT /api/projects/{id}` - Update project
- `DELETE /api/projects/{id}` - Delete project
- `POST /api/projects/{id}/export` - Export as SAX
- `POST /api/import` - Import SAX file

## Development

### Running Tests

```bash
# Backend tests
php artisan test

# Frontend tests
cd frontend
npm run test
```

### Building for Production

```bash
# Backend
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Frontend
cd frontend
npm run build
```

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Acknowledgments

- Sedona Framework documentation and specifications
- The original OpenBMCS progtool for inspiration
- Vue.js and Laravel communities

## Support

For questions and support, please open an issue in the GitHub repository.
