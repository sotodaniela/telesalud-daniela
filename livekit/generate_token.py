import base64
import hmac
import hashlib
import json
import sys
import time

def base64url_encode(data):
    if isinstance(data, str):
        data = data.encode('utf-8')
    return base64.urlsafe_b64encode(data).rstrip(b'=').decode('utf-8')

def generate_token(api_key, api_secret, room_name, participant_name, participant_identity):
    expires_at = int(time.time()) + 7200
    
    header = {'alg': 'HS256', 'typ': 'JWT'}
    payload = {
        'iss': api_key,
        'sub': participant_identity,
        'room': room_name,
        'name': participant_name,
        'exp': expires_at,
        'canPublish': True,
        'canSubscribe': True,
        'canPublishData': True,
    }
    
    header_encoded = base64url_encode(json.dumps(header))
    payload_encoded = base64url_encode(json.dumps(payload))
    
    message = f"{header_encoded}.{payload_encoded}"
    signature = hmac.new(api_secret.encode('utf-8'), message.encode('utf-8'), hashlib.sha256).digest()
    signature_encoded = base64url_encode(signature)
    
    return f"{header_encoded}.{payload_encoded}.{signature_encoded}"

api_key = "APIKcsHKD3zqcZ6"
api_secret = "pbp7lAIZVbGTc5SLAlag9HP8kXvkdsWCySG7ao4YnJL"

room_name = "testroom"
patient_name = "Daniela Soto"
patient_identity = "patient_ds001"
medic_name = "Dr. Karen Perez"
medic_identity = "medic_kp001"

patient_token = generate_token(api_key, api_secret, room_name, patient_name, patient_identity)
medic_token = generate_token(api_key, api_secret, room_name, medic_name, medic_identity)

print(f"Patient Token: {patient_token}")
print(f"Medic Token: {medic_token}")
print(f"")
print(f"Patient URL: http://localhost:7880/room/{room_name}?token={patient_token}")
print(f"Medic URL: http://localhost:7880/room/{room_name}?token={medic_token}")
