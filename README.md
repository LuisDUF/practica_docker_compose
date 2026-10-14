# Inventario de Laboratorio

Aplicación web distribuida desarrollada con Docker Compose, PHP 8.3, MariaDB, Redis y Nginx.
Proyecto de: Luis Carlos Duran Farias (Control: 22121357)

## Requisitos
- Docker
- Docker Compose

## Instrucciones de Ejecución

1. Renombrar el archivo `.env.example` a `.env` (las contraseñas por defecto ya están configuradas para desarrollo).
   (En Windows puedes simplemente copiar el archivo y cambiarle el nombre a `.env`).

2. Levantar los contenedores con Docker Compose:
   Ejecuta en tu terminal, en la carpeta raíz del proyecto:
   `docker compose up -d --build`

3. Acceder a la aplicación web (Frontend):
   - http://localhost:8081

4. Acceder a la interfaz de correos (Mailpit):
   - http://localhost:8025

## Características implementadas
- Arquitectura de 5 contenedores (frontend, backend, db, redis, mailpit).
- Healthchecks para servicios de base de datos y caché.
- Uso de redes y volúmenes de Docker.
- API REST con PHP nativo.
- Sistema de caché con Redis para optimizar el listado (30 seg).
- Notificación automática por correo (SMTP) a Mailpit al registrar un nuevo Equipo.
- Filtro de búsqueda (Mejora Obligatoria).
