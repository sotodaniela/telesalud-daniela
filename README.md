# Telesalud PAHO

Sistema de telemedicina y videoconsulta para la Organización Panamericana de la Salud.

## Características

- Sistema de videoconsultas con LiveKit
- Gestión de pacientes con historia clínica (normativa colombiana)
- Agendamiento de citas con calendario
- Grabación de consultas
- Historia clínica electrónica

## Requisitos

- Docker y Docker Compose
- PHP 8.0+ (para desarrollo local)
- MySQL 8.0+ (para desarrollo local)

## Instalación con Docker

```bash
cd telehealth-server
docker-compose up -d
```

## Estructura del Proyecto

```
telehealth-server/
├── docker-compose.yml
├── init.sql              # Esquema de base de datos
├── www/                  # Aplicación PHP
│   ├── login.php
│   ├── dashboard.php
│   ├── schedule.php
│   ├── patients.php
│   ├── clinical_history.php
│   ├── join_consultation.php
│   └── ...
└── README.md
```

## Configuración

### Base de datos
- Host: telehealth-db
- Usuario: telehealth
- Contraseña: telehealth123
- Base de datos: telehealth

### LiveKit
- URL: ws://localhost:7880
- API Key: APInewKey123
- API Secret: NewSecret456789012345678901234567890

## Credenciales por defecto

- Usuario: admin
- Contraseña: pass

## Puertos

- Telehealth App: http://localhost:8090
- OpenEMR: http://localhost:8092
- LiveKit: ws://localhost:7880

## Licencia

MIT License
