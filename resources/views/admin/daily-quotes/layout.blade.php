<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Panel de Administración - Frases Diarias')</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #4a5568;
            --secondary-color: #5a6c7d;
            --accent-color: #2c5282;
            --light-bg: #edf2f7;
        }
        
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #e0e7ef 0%, #cbd5e0 100%);
        }
        
        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 1.5rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--accent-color) 0%, #3182ce 100%);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(44, 82, 130, 0.25);
            background: linear-gradient(135deg, #2c5282 0%, #2b6cb0 100%);
        }
        
        .table {
            background: white;
            border-radius: 10px;
        }
        
        .badge {
            padding: 0.5rem 1rem;
            border-radius: 10px;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        /* Estilos personalizados para el scrollbar */
        .table-scroll-container {
            max-height: 500px;
            overflow-y: auto;
            overflow-x: auto;
        }
        
        /* Scrollbar para Chrome, Safari y Opera */
        .table-scroll-container::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }
        
        .table-scroll-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        .table-scroll-container::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #718096 0%, #4a5568 100%);
            border-radius: 10px;
        }
        
        .table-scroll-container::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #4a5568 0%, #2d3748 100%);
        }
        
        /* Scrollbar para Firefox */
        .table-scroll-container {
            scrollbar-width: thin;
            scrollbar-color: #718096 #f1f1f1;
        }
        
        /* Header fijo de la tabla */
        .table-scroll-container thead th {
            position: sticky;
            top: 0;
            background: #f8f9fa;
            z-index: 10;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>
<body>
    <!-- Header -->
    <nav class="navbar navbar-dark mb-4" style="background: linear-gradient(135deg, #4a5568 0%, #2d3748 100%); box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);">
        <div class="container-fluid">
            <div class="d-flex align-items-center">
                <a class="navbar-brand me-4" href="{{ route('admin.daily-quotes.index') }}">
                    <i class="bi bi-quote"></i> Panel de Administración
                </a>
                <div class="navbar-nav flex-row">
                    <a class="nav-link text-white me-3" href="{{ route('admin.daily-quotes.index') }}">
                        <i class="bi bi-quote"></i> Frases Diarias
                    </a>
                    <a class="nav-link text-white" href="{{ route('admin.books.index') }}">
                        <i class="bi bi-book"></i> Subir Libros
                    </a>
                </div>
            </div>
            <div>
                <span class="text-white me-3"><i class="bi bi-person-circle"></i> Administrador</span>
                <form action="{{ route('admin.logout') }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-box-arrow-right"></i> Salir
                    </button>
                </form>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="admin-container">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle"></i> {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @yield('content')
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Flatpickr CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <!-- Flatpickr JS -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    @yield('scripts')
</body>
</html>

