# Telesalud

Sistema de telemedicina y videoconsulta.

## Tabla de Contenidos

1. [Arquitectura del Sistema](#arquitectura-del-sistema)
2. [Usuarios y Roles](#usuarios-y-roles)
3. [Módulos del Sistema](#módulos-del-sistema)
4. [Requisitos de Infraestructura](#requisitos-de-infraestructura)
5. [Dimensionamiento de Recursos](#dimensionamiento-de-recursos)
6. [Estructura del Proyecto](#estructura-del-proyecto)
7. [Instalación Local](#instalación-local)
8. [Configuración de Servicios](#configuración-de-servicios)
9. [Puertos y Endpoints](#puertos-y-endpoints)
10. [Acceso de Pacientes](#acceso-de-pacientes)
11. [Monitoreo de Recursos](#monitoreo-de-recursos)

---

## Arquitectura del Sistema

```
┌─────────────────────────────────────────────────────────────────┐
│                        CLIENTE WEB                               │
│                   (Navegador del médico/paciente)               │
└─────────────────────────────┬───────────────────────────────────┘
                              │ HTTPS/WSS
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                      LIVEKIT SERVER                              │
│                   Servidor de Videoconferencia                   │
│                   Puerto: 7880 (WSS), 7881 (API)                │
└─────────────────────────────┬───────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                    TELEHEALTH APP (PHP)                          │
│              Aplicación de Gestión Clínica                       │
│              Puerto: 80/8090                                     │
│                                                                      │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐            │
│  │  Dashboard   │  │  Schedule    │  │  Patients    │            │
│  │  Clinical    │  │  Recordings  │  │  Users       │            │
│  │  History     │  │  Teleconsult │  │  Reports     │            │
│  └──────────────┘  └──────────────┘  └──────────────┘            │
└─────────────────────────────┬───────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                    TELEHEALTH MYSQL                              │
│              Base de Datos de Telesalud                          │
│              Puerto: 3306/3308                                   │
└─────────────────────────────────────────────────────────────────┘
```

---

## Usuarios y Roles

El sistema cuenta con un módulo de gestión de usuarios con **4 roles diferenciados**:

### Roles de Usuario

| Rol | Código | Descripción | Permisos |
|-----|--------|-------------|----------|
| Administrador | `admin` | Gestión del sistema | Acceso total, gestión de usuarios, informes del servidor |
| Médico | `doctor` | Profesional de salud | Dashboard, pacientes, videoconsultas, historia clínica |
| Enfermería | `nurse` | Personal de enfermería | Dashboard, pacientes, agenda, historia clínica |
| Auxiliar | `assistant` | Personal administrativo | Dashboard, pacientes, agenda, historia clínica |

### Credenciales por Defecto

| Usuario | Rol | Contraseña | Nombre Completo |
|---------|-----|-----------|-----------------|
| `admin` | Administrador | `pass` | Administrador del Sistema |
| `medico` | Médico | `pass` | Dr. Juan Pérez |
| `enfermera` | Enfermería | `pass` | Enfermera María López |
| `auxiliar` | Auxiliar | `pass` | Auxiliar Carlos García |

**⚠️ IMPORTANTE:** Cambiar las contraseñas en un entorno de producción.

### Permisos por Módulo

| Módulo | Admin | Médico | Enfermería | Auxiliar |
|--------|:-----:|:------:|:----------:|:--------:|
| Dashboard | ✅ | ✅ | ✅ | ✅ |
| Gestión de Usuarios | ✅ | ❌ | ❌ | ❌ |
| Informes del Sistema | ✅ | ❌ | ❌ | ❌ |
| Agendamiento/Citas | ✅ | ✅ | ✅ | ✅ |
| Gestión de Pacientes | ✅ | ✅ | ✅ | ✅ |
| Historia Clínica | ✅ | ✅ | ✅ | ✅ |
| Videoconsulta | ✅ | ✅ | ❌ | ❌ |
| Grabaciones | ✅ | ✅ | ✅ | ✅ |
| Generar PDF HC | ✅ | ✅ | ❌ | ❌ |

### Gestión de Usuarios (Solo Admin)

El administrador puede:
- **Crear** nuevos usuarios con diferentes roles
- **Editar** información de usuarios
- **Activar/Inactivar** usuarios
- **Cambiar contraseñas**
- **Ver histórico de accesos**

---

## Módulos del Sistema

| Módulo | Archivo | Descripción |
|--------|---------|-------------|
| Login | `login.php` | Autenticación de usuarios |
| Dashboard | `dashboard.php` | Panel principal con estadísticas |
| Usuarios | `users.php` | Gestión de usuarios (solo admin) |
| Agendamiento | `schedule.php` | Calendario de citas |
| Pacientes | `patients.php` | Gestión de pacientes |
| Historia Clínica | `clinical_history.php` | Crear/editar historia clínica |
| Lista HC | `clinical_history_list.php` | Ver historiales clínicos |
| Videoconsulta | `teleconsulta.php` | Sala de videoconsulta médica |
| Unirse Consulta | `join_consultation.php` | Iniciar videoconsulta |
| Acceso Paciente | `patient_join.php` | Portal de acceso para pacientes |
| Sala Paciente | `patient_room.php` | Sala de videoconsulta paciente |
| Grabaciones | `watch_recording.php` | Ver grabaciones |
| Detalle Consulta | `consultation_detail.php` | Ver detalles de consulta |
| PDF HC | `teleconsulta_pdf.php` | Generar PDF de historia clínica |

---

## Requisitos de Infraestructura

### Desarrollo Local (1-5 usuarios simultáneos)

| Componente | CPU | RAM | Almacenamiento | Cantidad |
|------------|-----|-----|----------------|----------|
| Docker Host | 4 cores | 8 GB | 20 GB SSD | 1 |
| LiveKit Server | 2 cores | 2 GB | - | Contenedor |
| MySQL 8.0 | 1 core | 1 GB | 5 GB | Contenedor |
| PHP App | 1 core | 512 MB | 1 GB | Contenedor |

### Producción - Dimensionamiento por Consultas Simultáneas

| Consultas Simultáneas | CPU Total | RAM Total | Ancho de Banda | LiveKit Rooms |
|----------------------|-----------|-----------|----------------|---------------|
| 1-5 | 4 cores | 8 GB | 20 Mbps | 5 |
| 6-15 | 8 cores | 16 GB | 50 Mbps | 15 |
| 16-30 | 16 cores | 32 GB | 100 Mbps | 30 |
| 31-50 | 32 cores | 64 GB | 200 Mbps | 50 |
| 51-100 | 64 cores | 128 GB | 500 Mbps | 100 |

### Requisitos por Videoconsulta (1:1)

| Recurso | 480p | 720p | 1080p |
|---------|------|------|-------|
| CPU por participante | 5-10% | 10-20% | 15-30% |
| RAM por participante | 100 MB | 200 MB | 300 MB |
| Ancho de banda (subida) | 1 Mbps | 2 Mbps | 4 Mbps |
| Ancho de banda (bajada) | 1 Mbps | 2 Mbps | 4 Mbps |

### LiveKit - Especificaciones por Room

| Aspecto | Valor |
|---------|-------|
| RAM por Room | 50-100 MB |
| Conexiones máxima por Room | 100 (recomendado: 20-30) |
| Timeout de Room inactivo | 5 minutos |
| Codec de video | VP8/VP9/H264 |
| Codec de audio | Opus |

---

## Dimensionamiento de Recursos

### Cálculo para N consultas simultáneas

```javascript
// Ejemplo: 20 consultas simultáneas a 720p
const consultas = 20;
const calidad = '720p';

const recursos = {
  cpu: consultas * 2,        // 40 cores
  ram: {
    livekit: consultas * 200, // 4000 MB
    mysql: 1024,              // 1 GB fijo
    php: 512,                 // 512 MB fijo
    os: 2048,                 // 2 GB SO
    total: (consultas * 200) + 3584 // 7584 MB ≈ 8 GB
  },
  bandwidth: {
    uplink: consultas * 2,    // 40 Mbps
    downlink: consultas * 2,   // 40 Mbps
    total: consultas * 4      // 80 Mbps
  }
};
```

### Requisitos de Red

| Tipo de Conexión | Mínimo | Recomendado |
|-----------------|--------|-------------|
| Latencia (entre participantes) | < 150 ms | < 50 ms |
| Jitter | < 30 ms | < 10 ms |
| Paquete perdido tolerable | < 1% | < 0.1% |
| Ancho de banda por consulta | 4 Mbps | 6-8 Mbps |

---

## Estructura del Proyecto

```
paho_telesalud/
├── telehealth-server/           # Aplicación principal PHP
│   ├── docker-compose.yml       # Orquestación de servicios
│   ├── Dockerfile               # Imagen PHP
│   ├── init.sql                 # Esquema de base de datos
│   ├── www/                     # Aplicación web
│   │   ├── index.php            # Punto de entrada
│   │   ├── login.php            # Autenticación
│   │   ├── dashboard.php        # Panel principal
│   │   ├── schedule.php         # Agendamiento de citas
│   │   ├── patients.php          # Gestión de pacientes
│   │   ├── clinical_history.php  # Historia clínica
│   │   ├── teleconsulta.php      # Sala de videoconsulta
│   │   ├── join_consultation.php # Unirse a consulta
│   │   ├── recordings/           # Grabaciones de consultas
│   │   └── tcpdf/                # Generación de PDFs
│   └── README.md
│
├── livekit/                     # Servidor de videoconsulta
│   ├── docker-compose.yml       # Orquestación LiveKit
│   ├── livekit.yaml             # Configuración de LiveKit
│   ├── generate_token.php       # Generación de tokens de acceso
│   ├── create_room.php          # Creación de rooms
│   ├── videoconsulta.html       # Cliente web de videoconsulta
│   └── keys.txt                 # API Keys (NO incluir en producción)
│
├── openemr-telesalud/           # Integración OpenEMR (opcional)
│
├── ciips-telesalud/             # Módulo CIIPS (opcional)
│
├── base-db.sql                  # Base de datos inicial
├── telehealth-only.sql          # Schema de telesalud
└── README.md                    # Este archivo
```

---

## Instalación Local

### Requisitos Previos

- **Docker** 20.10+ y **Docker Compose** 2.0+
- **8 GB RAM mínimo** (para desarrollo local)
- **Puerto 8090, 7880, 3308 disponibles**

### Pasos de Instalación

```bash
# 1. Clonar o entrar al directorio del proyecto
cd C:\paho_telesalud

# 2. Crear red de Docker para LiveKit
docker network create livekit_livekit-network

# 3. Iniciar servidor de base de datos
cd telehealth-server
docker-compose up -d telehealth-db

# 4. Esperar a que MySQL esté listo (~30 segundos)
docker logs -f telehealth-mysql

# 5. Iniciar aplicación PHP
docker-compose up -d telehealth-app

# 6. Iniciar servidor LiveKit
cd ../livekit
docker-compose up -d livekit
```

### Verificación de Servicios

```bash
# Ver estado de todos los contenedores
docker ps

# Ver logs de un servicio específico
docker logs -f telehealth-app

# Ver consumo de recursos
docker stats
```

---

## Configuración de Servicios

### Base de Datos (MySQL)

```yaml
Host: telehealth-mysql
Puerto: 3306
Usuario: telehealth
Contraseña: telehealth123
Base de datos: telehealth
Puerto externo: 3308
```

### LiveKit Server

```yaml
URL WebSocket: ws://localhost:7880
Puerto HTTP API: 7881
Puerto TURN/STUN: 7882/UDP
API Key: APInewKey123
API Secret: NewSecret456789012345678901234567890
```

### Aplicación PHP

```yaml
Puerto HTTP: 8090
Variables de entorno:
  - DB_HOST: telehealth-mysql
  - DB_USER: telehealth
  - DB_PASS: telehealth123
  - DB_NAME: telehealth
  - LIVEKIT_URL: ws://localhost:7880
  - LIVEKIT_API_KEY: APInewKey123
  - LIVEKIT_API_SECRET: NewSecret456789012345678901234567890
```

---

## Puertos y Endpoints

| Servicio | Puerto | URL | Propósito |
|----------|--------|-----|------------|
| Telehealth App | 8090 | http://localhost:8090 | Aplicación principal |
| OpenEMR | 8092 | http://localhost:8092 | Historia clínica integrada |
| LiveKit WSS | 7880 | ws://localhost:7880 | Videoconferencia |
| LiveKit API | 7881 | http://localhost:7881 | API REST |
| MySQL | 3308 | localhost:3308 | Base de datos |

### Credenciales del Sistema (DESACTIVAR EN PRODUCCIÓN)

```yaml
# Usuarios de la aplicación
Aplicación:
  Admin: admin / pass
  Médico: medico / pass
  Enfermera: enfermera / pass
  Auxiliar: auxiliar / pass

# Base de datos
MySQL:
  Root: root / root
  Usuario: telehealth / telehealth123
```

---

## Acceso de Pacientes

El sistema cuenta con un portal de acceso para pacientes que pueden conectarse a videoconsultas desde cualquier dispositivo.

### Enlace de Acceso

```
http://localhost:8090/patient_join.php
```

### Métodos de Acceso

1. **Por Token Único**: El sistema genera un token único para cada cita
   ```
   http://localhost:8090/patient_join.php?token=a1fa89dfe804e00ac4add42911d680cc
   ```

2. **Por Número de Consulta**: Ingresando ID de consulta y nombre

### Envío de Enlaces

Desde el módulo de **Agendamiento**, el médico puede:
- **Copiar enlace** directo para el paciente
- **Enviar por WhatsApp** directamente

### Características del Portal Paciente

- Diseño **responsive** para móviles y tablets
- Controles táctiles optimizados
- Indicador de conexión en tiempo real
- Compatible con cámara y micrófono del dispositivo

---

## Monitoreo de Recursos

### Comandos de Monitoreo Local

```bash
# Ver consumo de contenedores en tiempo real
docker stats

# Ver uso de disco
docker system df

# Monitorear red de un contenedor específico
docker exec telehealth-app cat /proc/net/dev

# Ver logs de LiveKit
docker logs -f livekit-server

# Monitorear conexiones activas
curl http://localhost:7881/v1/room
```

### Métricas a Monitorear en Producción

| Métrica | Umbral de Alerta | Acción |
|---------|------------------|--------|
| CPU > 80% | Escalar horizontalmente | Agregar instancias LiveKit |
| RAM > 85% | Agregar RAM o optimizar | Reducir participantes por room |
| Latencia > 150ms | Revisar red | Usar servidor TURN |
| Conexiones > 80/room | Dividir room | Crear nuevo room |
| Ancho de banda > 70% | Upgrade de conexión | Implementar QoS |

### Módulo de Informes del Dashboard

El dashboard incluye un módulo de monitoreo en tiempo real accesible desde el botón **"📊 Informes del Sistema"**.

**Métricas disponibles:**

| Categoría | Métricas |
|-----------|----------|
| **CPU** | Load Average, núcleos, utilización PHP |
| **Memoria** | Total, usada, disponible, PHP memory |
| **Red** | RX/TX en bytes y MB, totales desde inicio |
| **Disco** | Espacio total, usado, libre, porcentaje |

**Uso del módulo:**

1. Ir a **Dashboard** → clic en **"📊 Informes del Sistema"**
2. Ver **Resumen General** con métricas principales
3. Explorar tabs: CPU, Memoria, Red, Almacenamiento
4. Cada tab incluye guías de dimensionamiento para planificar capacidad

**Auto-refresh:** Las métricas se actualizan automáticamente cada 30 segundos cuando el panel está visible.

### Script de Benchmark Local

```bash
# Crear archivo benchmark.sh
#!/bin/bash
echo "=== Telesalud Benchmark ==="
echo "Fecha: $(date)"
echo ""
echo "--- Consumo de Recursos ---"
docker stats --no-stream --format "table {{.Name}}\t{{.CPUPerc}}\t{{.MemUsage}}\t{{.NetIO}}"
echo ""
echo "--- Conexiones LiveKit ---"
curl -s http://localhost:7881/v1/room 2>/dev/null | jq '.' || echo "No se puede conectar a LiveKit"
```

---

## Licencia

MIT License
