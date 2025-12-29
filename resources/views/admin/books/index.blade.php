@extends('admin.daily-quotes.layout')

@section('title', 'Subir Libros')

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="mb-0"><i class="bi bi-book"></i> Subir Libros</h3>
        <p class="mb-0 opacity-75">Sube archivos PDF para que estén disponibles en el sistema</p>
    </div>
    <div class="card-body">
        <form action="{{ route('admin.books.upload') }}" method="POST" enctype="multipart/form-data" id="uploadForm">
            @csrf
            
            <div class="mb-4">
                <label for="book" class="form-label">
                    <i class="bi bi-file-earmark-pdf"></i> Seleccionar archivo PDF
                </label>
                <input 
                    type="file" 
                    class="form-control @error('book') is-invalid @enderror" 
                    id="book" 
                    name="book" 
                    accept=".pdf"
                    required>
                @error('book')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <small class="form-text text-muted">
                    <i class="bi bi-info-circle"></i> Tamaño máximo: 100MB. Solo archivos PDF.
                </small>
            </div>

            <div class="mb-3" id="filePreview" style="display: none;">
                <div class="alert alert-info">
                    <i class="bi bi-file-earmark-pdf"></i> 
                    <strong>Archivo seleccionado:</strong> 
                    <span id="fileName"></span>
                    <span id="fileSize" class="text-muted"></span>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center">
                <a href="{{ route('admin.daily-quotes.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <i class="bi bi-upload"></i> Subir Libro
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Instrucciones -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-info-circle"></i> Instrucciones</h5>
    </div>
    <div class="card-body">
        <ul class="mb-0">
            <li>Selecciona un archivo PDF desde tu dispositivo</li>
            <li>El archivo será enviado al servicio de IA para procesamiento</li>
            <li>El tamaño máximo permitido es de 100MB</li>
            <li>Una vez subido, el libro estará disponible en el sistema</li>
        </ul>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.getElementById('book').addEventListener('change', function(e) {
        const file = e.target.files[0];
        const preview = document.getElementById('filePreview');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');
        const maxSize = 100 * 1024 * 1024; // 100MB en bytes
        
        if (file) {
            // Validar tamaño del archivo
            if (file.size > maxSize) {
                alert('El archivo es demasiado grande. El tamaño máximo permitido es 100MB.');
                e.target.value = ''; // Limpiar el input
                preview.style.display = 'none';
                return;
            }
            
            // Validar tipo de archivo
            if (file.type !== 'application/pdf') {
                alert('Por favor, selecciona solo archivos PDF.');
                e.target.value = ''; // Limpiar el input
                preview.style.display = 'none';
                return;
            }
            
            fileName.textContent = file.name;
            fileSize.textContent = ' (' + formatFileSize(file.size) + ')';
            preview.style.display = 'block';
        } else {
            preview.style.display = 'none';
        }
    });

    document.getElementById('uploadForm').addEventListener('submit', function(e) {
        const submitBtn = document.getElementById('submitBtn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Subiendo...';
    });

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }
</script>
@endsection

