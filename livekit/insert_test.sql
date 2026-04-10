-- Insert test videoconsultation
INSERT INTO livekit_videoconsultations (room_name, patient_name, patient_identity, medic_name, medic_identity, patient_token, medic_token, appointment_date, appointment_time, status, created_at)
VALUES ('vc_prueba001', 'Daniela Soto', 'patient_ds001', 'Dr. Karen Perez', 'medic_kp001', 'TEST_TOKEN_PACIENTE', 'TEST_TOKEN_MEDICO', CURDATE(), '14:00:00', 'pending', NOW());
