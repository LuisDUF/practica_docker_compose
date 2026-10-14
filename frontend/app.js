document.addEventListener('DOMContentLoaded', () => {
    const API_URL = '/api';
    
    // Elementos del DOM
    const form = document.getElementById('equipo-form');
    const tbody = document.getElementById('equipos-tbody');
    const formTitle = document.getElementById('form-title');
    const equipoIdInput = document.getElementById('equipo-id');
    const btnCancel = document.getElementById('btn-cancel');
    const btnSave = document.getElementById('btn-save');
    const searchInput = document.getElementById('search-input');
    const btnSearch = document.getElementById('btn-search');
    const cacheIndicator = document.getElementById('cache-indicator');
    const visitasContador = document.getElementById('visitas-contador');
    const healthStatus = document.getElementById('health-status');

    // Cargar datos iniciales
    checkHealth();
    loadEquipos();

    // Eventos
    form.addEventListener('submit', handleFormSubmit);
    btnCancel.addEventListener('click', resetForm);
    
    // Búsqueda en tiempo real al escribir o hacer click en buscar
    btnSearch.addEventListener('click', () => loadEquipos(searchInput.value));
    searchInput.addEventListener('keyup', (e) => {
        if(e.key === 'Enter') loadEquipos(searchInput.value);
    });

    // Funciones
    async function checkHealth() {
        try {
            const res = await fetch(`${API_URL}/health`);
            const data = await res.json();
            
            if (data.status === 'ok') {
                healthStatus.className = 'health-container health-ok';
                healthStatus.innerHTML = `<strong>Todo en línea</strong><br>MariaDB: ${data.mariadb}<br>Redis: ${data.redis}`;
            } else {
                healthStatus.className = 'health-container health-error';
                healthStatus.innerHTML = `<strong>Problema de conexión</strong><br>Revisa los logs del servidor.`;
            }
        } catch (error) {
            healthStatus.className = 'health-container health-error';
            healthStatus.innerHTML = `Error contactando a la API: ${error.message}`;
        }
    }

    async function loadEquipos(query = '') {
        try {
            const url = query ? `${API_URL}/equipos?buscar=${encodeURIComponent(query)}` : `${API_URL}/equipos`;
            const res = await fetch(url);
            const response = await res.json();
            
            const data = response.data || [];
            
            // Actualizar interfaz basada en caché y visitas (Redis)
            if (response.cached) {
                cacheIndicator.style.display = 'flex';
            } else {
                cacheIndicator.style.display = 'none';
            }

            if (response.visitas !== undefined) {
                visitasContador.innerText = response.visitas;
            }

            // Renderizar tabla
            tbody.innerHTML = '';
            if (data.length === 0) {
                tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding: 2rem; color: #6B7280;">No se encontraron equipos en el laboratorio.</td></tr>`;
                return;
            }

            data.forEach(equipo => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>#${equipo.id}</td>
                    <td><strong>${equipo.nombre}</strong></td>
                    <td>${equipo.descripcion || '<span style="color:#9CA3AF">Sin descripción</span>'}</td>
                    <td><span class="badge badge-${equipo.estado.replace(' ', '\\ ')}">${equipo.estado}</span></td>
                    <td>${new Date(equipo.fecha_creacion).toLocaleDateString()}</td>
                    <td class="actions-cell">
                        <button class="btn btn-sm btn-edit" onclick="editEquipo(${equipo.id}, '${escapeHtml(equipo.nombre)}', '${escapeHtml(equipo.descripcion || '')}', '${equipo.estado}')">Editar</button>
                        <button class="btn btn-sm btn-danger" onclick="deleteEquipo(${equipo.id})">Borrar</button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        } catch (error) {
            console.error('Error cargando equipos:', error);
            tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; color: red;">Error al cargar los datos de la API.</td></tr>`;
        }
    }

    async function handleFormSubmit(e) {
        e.preventDefault();
        
        const id = equipoIdInput.value;
        const payload = {
            nombre: document.getElementById('nombre').value,
            estado: document.getElementById('estado').value,
            descripcion: document.getElementById('descripcion').value
        };

        try {
            const method = id ? 'PUT' : 'POST';
            const url = id ? `${API_URL}/equipos/${id}` : `${API_URL}/equipos`;

            const res = await fetch(url, {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            if (res.ok) {
                resetForm();
                loadEquipos(searchInput.value); // Recargar
                
                // Mostrar alerta bonita
                const msg = id ? 'Equipo actualizado' : 'Equipo registrado exitosamente';
                alert(msg + (id ? '' : '. Se ha enviado una notificación a Mailpit.'));
            } else {
                const error = await res.json();
                alert(`Error: ${error.message}`);
            }
        } catch (error) {
            alert('Error de conexión al guardar.');
        }
    }

    window.editEquipo = (id, nombre, desc, estado) => {
        formTitle.innerText = "Editar Equipo #" + id;
        equipoIdInput.value = id;
        document.getElementById('nombre').value = nombre;
        document.getElementById('descripcion').value = desc;
        document.getElementById('estado').value = estado;
        
        btnSave.innerText = "Actualizar Equipo";
        btnCancel.style.display = 'block';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    window.deleteEquipo = async (id) => {
        if (!confirm('¿Estás seguro de eliminar este equipo del inventario?')) return;
        
        try {
            const res = await fetch(`${API_URL}/equipos/${id}`, { method: 'DELETE' });
            if (res.ok) {
                loadEquipos(searchInput.value);
            } else {
                alert('No se pudo eliminar el equipo.');
            }
        } catch (error) {
            alert('Error de red al intentar eliminar.');
        }
    };

    function resetForm() {
        form.reset();
        equipoIdInput.value = '';
        formTitle.innerText = "Registrar Nuevo Equipo";
        btnSave.innerText = "Guardar Equipo";
        btnCancel.style.display = 'none';
    }

    // Helper para sanitizar strings y evitar XSS básico en inyección de HTML
    function escapeHtml(unsafe) {
        return unsafe
             .replace(/&/g, "&amp;")
             .replace(/</g, "&lt;")
             .replace(/>/g, "&gt;")
             .replace(/"/g, "&quot;")
             .replace(/'/g, "&#039;");
    }
});
