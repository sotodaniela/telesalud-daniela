-- ============================================
-- Base de datos Telehealth - Estructura Completa
-- Conforme a normatividad colombiana
-- ============================================

CREATE DATABASE IF NOT EXISTS telehealth;
USE telehealth;

-- Tabla para doctores/médicos
CREATE TABLE IF NOT EXISTS doctors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    specialty VARCHAR(100),
    professional_id VARCHAR(20),
    email VARCHAR(100),
    phone VARCHAR(20),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabla para pacientes (Datos básicos)
CREATE TABLE IF NOT EXISTS patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Tipo y número de identificación (según normativa colombiana)
    id_type ENUM('CC', 'CE', 'PA', 'RC', 'TI', 'NIT', 'PEP', 'MSP') DEFAULT 'CC',
    id_number VARCHAR(20) UNIQUE NOT NULL,
    
    -- Datos personales
    first_name VARCHAR(50) NOT NULL,
    second_name VARCHAR(50),
    last_name VARCHAR(50) NOT NULL,
    second_last_name VARCHAR(50),
    birth_date DATE NOT NULL,
    gender ENUM('M', 'F', 'O') NOT NULL,
    
    -- Información de contacto
    email VARCHAR(100),
    phone VARCHAR(20),
    mobile VARCHAR(20),
    address VARCHAR(255),
    city VARCHAR(100),
    department VARCHAR(100),
    
    -- Datos de contacto de emergencia
    emergency_contact_name VARCHAR(100),
    emergency_contact_phone VARCHAR(20),
    emergency_contact_relation VARCHAR(50),
    
    -- Información EPS/ARL
    eps_name VARCHAR(100),
    eps_afiliation_type ENUM('Contributivo', 'Subsidiado', 'Vinculado', 'Especial', 'Particular') DEFAULT 'Contributivo',
    
    -- Datos adicionales
    occupation VARCHAR(100),
    education_level VARCHAR(50),
    marital_status ENUM('Soltero', 'Casado', 'Unión Libre', 'Viudo', 'Separado') DEFAULT 'Soltero',
    race_ethnicity VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabla para Historia Clínica (odontología, medicina general, etc.)
CREATE TABLE IF NOT EXISTS clinical_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    consultation_id INT,
    
    -- Fecha y motivo de consulta
    consultation_date DATETIME NOT NULL,
    consultation_type ENUM('Primera Vez', 'Control', 'Urgencia', 'Telemedicina') DEFAULT 'Primera Vez',
    reason_consultation TEXT,
    
    -- Enfermedad o problema actual
    current_illness TEXT,
    
    -- Historia de la enfermedad actual (Evolución)
    illness_evolution TEXT,
    
    -- Revisión por sistemas (Según RIPS/SISPRO)
    review_systems JSON,
    
    -- Signos vitales
    vital_signs JSON,
    
    -- Examen físico
    physical_exam TEXT,
    
    -- Diagnósticos (CIE-10)
    diagnoses JSON,
    
    -- Órdenes médicas
    medical_orders JSON,
    
    -- Prescripciones/M medicamentos
    prescriptions JSON,
    
    -- Órdenes de laboratorio
    lab_orders JSON,
    
    -- Órdenes de imágenes diagnósticas
    imaging_orders JSON,
    
    -- Procedimientos realizados
    procedures_performed TEXT,
    
    -- Plan de manejo
    management_plan TEXT,
    
    -- Recomendaciones
    recommendations TEXT,
    
    -- follow-up
    next_appointment_date DATE,
    next_appointment_time TIME,
    
    -- Firmas y validaciones
    doctor_signature TEXT,
    doctor_name VARCHAR(100),
    doctor_professional_id VARCHAR(20),
    signature_date DATETIME,
    
    -- Estados
    status ENUM('active', 'signed', 'cancelled') DEFAULT 'active',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    FOREIGN KEY (consultation_id) REFERENCES consultations(id) ON DELETE SET NULL
);

-- Tabla para Antecedentes Personales
CREATE TABLE IF NOT EXISTS personal_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    
    -- Antecedentes Patológicos
    pathological_enfermedades TEXT,
    pathological_cirugias TEXT,
    pathological_traumatismos TEXT,
    pathological_transfusiones TEXT,
    pathological_hospitalizaciones TEXT,
    pathological_alergias TEXT,
    pathological_medicamentos_alergia TEXT,
    
    -- Antecedentes Ginecológicos (para mujeres)
    gynecological_menarquia VARCHAR(50),
    gynecological_fur VARCHAR(50),
    gynecological_gestaciones INT,
    gynecological_partos INT,
    gynecological_cesareas INT,
    gynecological_abortos INT,
    gynecological_metodo_anticonceptivo VARCHAR(100),
    gynecological_ultima_citologia DATE,
    gynecological_resultado_citologia VARCHAR(50),
    gynecological_ultima_mamografia DATE,
    gynecological_resultado_mamografia VARCHAR(50),
    
    -- Antecedentes No Patológicos
    non_pathological_habits TEXT,
    non_pathological_smoking ENUM('Si', 'No', 'Ocasional') DEFAULT 'No',
    non_pathological_alcohol ENUM('Si', 'No', 'Ocasional') DEFAULT 'No',
    non_pathological_drugs ENUM('Si', 'No', 'Ocasional') DEFAULT 'No',
    non_pathological_exercise ENUM('Si', 'No', 'Ocasional') DEFAULT 'No',
    non_pathological_diet VARCHAR(255),
    
    -- Antecedentes Familiares
    family_history TEXT,
    family_diabetes ENUM('Si', 'No', 'NS') DEFAULT 'NS',
    family_hypertension ENUM('Si', 'No', 'NS') DEFAULT 'NS',
    family_heart_disease ENUM('Si', 'No', 'NS') DEFAULT 'NS',
    family_cancer VARCHAR(100),
    family_mental_illness VARCHAR(100),
    family_tuberculosis ENUM('Si', 'No', 'NS') DEFAULT 'NS',
    family_epilepsy ENUM('Si', 'No', 'NS') DEFAULT 'NS',
    family_congenital_diseases VARCHAR(255),
    
    -- Antecedentes Ocupacionales
    occupational_exposure VARCHAR(255),
    occupational_hazards VARCHAR(255),
    occupational_protection VARCHAR(255),
    
    -- Fecha de actualización
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    update_doctor_id INT,
    
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    FOREIGN KEY (update_doctor_id) REFERENCES doctors(id) ON DELETE SET NULL
);

-- Tabla para Videoconsultas
CREATE TABLE IF NOT EXISTS consultations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_name VARCHAR(100) NOT NULL,
    doctor_id INT NOT NULL,
    patient_id INT NOT NULL,
    scheduled_date DATETIME NOT NULL,
    duration_minutes INT DEFAULT 0,
    status ENUM('scheduled', 'in_progress', 'completed', 'cancelled', 'no_show') DEFAULT 'scheduled',
    notes TEXT,
    recording_path VARCHAR(255),
    clinical_history_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id),
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (clinical_history_id) REFERENCES clinical_history(id) ON DELETE SET NULL
);

-- Tabla para resultados de laboratorio
CREATE TABLE IF NOT EXISTS lab_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    clinical_history_id INT,
    doctor_id INT NOT NULL,
    
    -- Información de la orden
    order_date DATETIME NOT NULL,
    order_type VARCHAR(100) NOT NULL,
    laboratory_name VARCHAR(100),
    
    -- Resultados en JSON
    results JSON,
    
    -- Estado
    status ENUM('ordered', 'sample_collected', 'in_process', 'completed', 'cancelled') DEFAULT 'ordered',
    
    -- Observaciones
    observations TEXT,
    
    -- Archivo adjunto (ruta)
    attachment_path VARCHAR(255),
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (clinical_history_id) REFERENCES clinical_history(id) ON DELETE SET NULL,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id)
);

-- Tabla para permisos de módulos por usuario
CREATE TABLE IF NOT EXISTS user_module_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    module_key VARCHAR(50) NOT NULL,
    module_name VARCHAR(100) NOT NULL,
    is_enabled BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_module (user_id, module_key)
);

-- Insertar doctor admin por defecto
INSERT INTO doctors (username, password, full_name, specialty, professional_id, email) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Administrador', 'Medicina General', 'RM12345', 'admin@paho.org');
-- Contraseña: pass

-- Insertar permisos por defecto para admin
INSERT INTO user_module_permissions (user_id, module_key, module_name, is_enabled) VALUES 
(1, 'dashboard', 'Dashboard', TRUE),
(1, 'patients', 'Pacientes', TRUE),
(1, 'schedule', 'Agenda/Citas', TRUE),
(1, 'teleconsulta', 'Teleconsulta', TRUE),
(1, 'clinical_history', 'Historia Clínica', TRUE),
(1, 'users', 'Gestión de Usuarios', TRUE);

-- Insertar pacientes de ejemplo
INSERT INTO patients (id_type, id_number, first_name, second_name, last_name, second_last_name, birth_date, gender, email, phone, mobile, address, city, department, eps_name, eps_afiliation_type, occupation, emergency_contact_name, emergency_contact_phone) VALUES 
('CC', '12345678', 'Juan', 'Carlos', 'Pérez', 'García', '1985-03-15', 'M', 'juan.perez@email.com', '+6012345678', '+573001234567', 'Calle 100 #45-67', 'Bogotá', 'Cundinamarca', 'Sanitas', 'Contributivo', 'Ingeniero', 'María Pérez', '+573001234569'),
('CC', '87654321', 'María', 'Eugenia', 'García', 'López', '1990-07-22', 'F', 'maria.garcia@email.com', '+6098765432', '+573009876543', 'Carrera 15 #78-90', 'Medellín', 'Antioquia', 'Sura', 'Subsidiado', 'Médica', 'Carlos García', '+573009876545'),
('CC', '11223344', 'Carlos', 'Andrés', 'López', 'Martínez', '1978-11-30', 'M', 'carlos.lopez@email.com', '+6011223344', '+573001122334', 'Av. Caracas #56-78', 'Cali', 'Valle del Cauca', 'Coomeva', 'Contributivo', 'Abogado', 'Ana López', '+573001122336');

-- Insertar historias clínicas de ejemplo
INSERT INTO clinical_history (patient_id, doctor_id, consultation_date, consultation_type, reason_consultation, current_illness, vital_signs, diagnoses, management_plan) VALUES 
(1, 1, NOW(), 'Primera Vez', 'Control general anual', 'Paciente refiere cefalea ocasional hace 2 semanas', '{"systolic_bp": 120, "diastolic_bp": 80, "heart_rate": 72, "temperature": 36.5, "respiratory_rate": 18, "oxygen_saturation": 98, "weight": 75, "height": 175, "bmi": 24.49}', '[{"code": "J06.9", "name": "Infección respiratoria aguda no especificada", "type": "Principal"}]', 'Se ordena laboratorio de control. Seguimiento en 30 días.'),
(2, 1, NOW(), 'Control', 'Seguimiento por hipertensión', 'Paciente controlada con medicación actual', '{"systolic_bp": 125, "diastolic_bp": 82, "heart_rate": 68, "temperature": 36.8, "respiratory_rate": 16, "oxygen_saturation": 99, "weight": 62, "height": 160, "bmi": 24.22}', '[{"code": "I10", "name": "Hipertensión esencial primaria", "type": "Principal"}]', 'Continuar con Amlodipino 5mg daily. Control en 3 meses.');

-- Insertar antecedentes de ejemplo
INSERT INTO personal_history (patient_id, doctor_id, pathological_enfermedades, pathological_medicamentos_alergia, family_diabetes, family_hypertension, non_pathological_smoking, non_pathological_exercise) VALUES 
(1, 1, 'Sin antecedentes patológicos significativos', 'Penicilina', 'Si', 'Si', 'No', 'Si'),
(2, 1, 'Hipertensión arterial diagnosticada hace 3 años', 'Ninguna conocida', 'No', 'Si', 'No', 'Si');
